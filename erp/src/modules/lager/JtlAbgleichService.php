<?php

require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../import/JtlCsvReader.php';

/**
 * JTL-Lagerabgleich für den Parallelbetrieb (Migration 231, Jacky/Babsi 2026-11-11).
 *
 * MeaLana-Shop + Laden buchen in JTL, SOW im ERP -- beide verkaufen aus demselben Lager.
 * Täglicher Ablauf:
 *   1. erstelleExport(): ERP-Bestand jetzt minus Basis (= JTL-Stand beim letzten Abgleich), je
 *      Artikel/Lager/Charge -> CSV für Ameise "Preise/Bestände (QuickSync)" mit "Neuen und alten
 *      Lagerbestand addieren". Danach bestaetigeExport().
 *   2. importVorschau() + importBuchen(): JTL-Datei (alle Artikel, auch Bestand 0). Ziel je Schlüssel =
 *      JTL + (ERP jetzt - Export-Schnappschuss) -- was zwischen Export und Import im ERP passiert ist,
 *      bleibt erhalten und geht mit dem nächsten Export nach JTL. Neue Basis = JTL-Werte.
 *
 * Schlüssel: "artikelId|lagerId|charge", charge '' = ohne Charge (lagerbestand.charge IS NULL).
 * Nur eigene Lager (keine Messe-/Partner-/Händlerlager). Nur Artikel im Abgleichsumfang (stehen in
 * der JTL-Datei) -- reine ERP-Artikel (z.B. indra-design) werden nie angefasst.
 */
class JtlAbgleichService
{
    /** Ab so vielen Artikeln, die von Bestand > 0 auf 0 fallen würden, braucht der Import eine Bestätigung. */
    public const PLAUSI_ANTEIL = 0.10;
    public const PLAUSI_MIN    = 20;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ------------------------------------------------------------------ Zustand

    public function letzterLauf(): ?array
    {
        $r = $this->db->query("SELECT * FROM jtl_abgleich_laeufe WHERE status <> 'verworfen' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function letzterAbgeschlossenerLauf(): ?array
    {
        $r = $this->db->query("SELECT * FROM jtl_abgleich_laeufe WHERE status = 'abgeschlossen' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function verlauf(int $anzahl = 20): array
    {
        return $this->db->query("
            SELECT l.*, b.username AS benutzername
            FROM jtl_abgleich_laeufe l LEFT JOIN benutzer b ON b.id = l.benutzer_id
            ORDER BY l.id DESC LIMIT " . (int) $anzahl)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function umfangAnzahl(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM jtl_abgleich_umfang")->fetchColumn();
    }

    /**
     * Was als Nächstes dran ist: 'erstabgleich' (noch nie importiert), 'export' (ERP-Änderungen seit der
     * Basis offen), 'bestaetigen' (Export erstellt, nicht bestätigt), 'import' (bereit für JTL-Datei).
     */
    public function naechsterSchritt(): array
    {
        $letzter = $this->letzterLauf();
        if ($letzter && $letzter['status'] === 'export_offen') {
            return ['schritt' => 'bestaetigen', 'lauf' => $letzter];
        }
        if ($letzter && $letzter['status'] === 'export_bestaetigt') {
            return ['schritt' => 'import', 'lauf' => $letzter];
        }
        if (!$this->letzterAbgeschlossenerLauf()) {
            return ['schritt' => 'erstabgleich', 'lauf' => null];
        }
        $delta = $this->berechneDelta();
        return ['schritt' => $delta ? 'export' : 'import_ohne_aenderung', 'lauf' => null, 'delta_anzahl' => count($delta)];
    }

    // ------------------------------------------------------------------ Bestände lesen

    /** ERP-Bestand je Schlüssel (nur eigene Lager ohne Messe), optional auf Artikel eingeschränkt. */
    private function erpBestand(?array $nurArtikel = null): array
    {
        $sql = "
            SELECT lb.artikel_id, lb.lager_id, COALESCE(lb.charge, '') AS charge, SUM(lb.bestand) AS bestand
            FROM lagerbestand lb
            JOIN lager l ON l.id = lb.lager_id AND l.lager_beziehung = 'eigen' AND l.typ <> 'messe'
            WHERE lb.artikel_id IS NOT NULL";
        if ($nurArtikel !== null) {
            if (!$nurArtikel) return [];
            $sql .= " AND lb.artikel_id IN (" . implode(',', array_map('intval', $nurArtikel)) . ")";
        }
        $sql .= " GROUP BY lb.artikel_id, lb.lager_id, COALESCE(lb.charge, '')";
        $out = [];
        foreach ($this->db->query($sql) as $r) {
            if (abs((float) $r['bestand']) < 0.0005) continue;
            $out[$r['artikel_id'] . '|' . $r['lager_id'] . '|' . $r['charge']] = (float) $r['bestand'];
        }
        return $out;
    }

    private function schnappschuss(int $laufId, string $art): array
    {
        $st = $this->db->prepare("SELECT artikel_id, lager_id, charge, bestand FROM jtl_abgleich_bestand WHERE lauf_id = :l AND art = :a");
        $st->execute(['l' => $laufId, 'a' => $art]);
        $out = [];
        foreach ($st as $r) $out[$r['artikel_id'] . '|' . $r['lager_id'] . '|' . $r['charge']] = (float) $r['bestand'];
        return $out;
    }

    private function speichereSchnappschuss(int $laufId, string $art, array $bestand): void
    {
        $st = $this->db->prepare("INSERT INTO jtl_abgleich_bestand (lauf_id, art, artikel_id, lager_id, charge, bestand) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($bestand as $key => $menge) {
            if (abs($menge) < 0.0005) continue;
            [$a, $l, $c] = explode('|', $key, 3);
            $st->execute([$laufId, $art, (int) $a, (int) $l, $c, round($menge, 3)]);
        }
    }

    private function umfang(): array
    {
        return array_map('intval', $this->db->query("SELECT artikel_id FROM jtl_abgleich_umfang")->fetchAll(PDO::FETCH_COLUMN));
    }

    // ------------------------------------------------------------------ Schritt 1: Export

    /** Delta ERP jetzt minus Basis, je Schlüssel (nur Artikel im Umfang). */
    private function berechneDelta(): array
    {
        $basisLauf = $this->letzterAbgeschlossenerLauf();
        if (!$basisLauf) return [];
        $basis = $this->schnappschuss((int) $basisLauf['id'], 'basis');
        $jetzt = $this->erpBestand($this->umfang());
        $delta = [];
        foreach (array_keys($basis + $jetzt) as $key) {
            $d = ($jetzt[$key] ?? 0.0) - ($basis[$key] ?? 0.0);
            if (abs($d) >= 0.0005) $delta[$key] = round($d, 3);
        }
        return $delta;
    }

    /**
     * Erstellt den Export (Lauf mit Status export_offen, Export-Schnappschuss). Ohne Änderungen wird
     * der Lauf gleich als bestätigt angelegt (nichts einzuspielen). Gibt den Lauf zurück.
     */
    public function erstelleExport(int $benutzerId): array
    {
        $basisLauf = $this->letzterAbgeschlossenerLauf();
        if (!$basisLauf) throw new RuntimeException('Zuerst den Erstabgleich machen (JTL-Datei einlesen).');
        $offen = $this->letzterLauf();
        if ($offen && in_array($offen['status'], ['export_offen', 'export_bestaetigt'], true)) {
            throw new RuntimeException('Es gibt schon einen offenen Abgleich (#' . $offen['id'] . '). Erst abschließen oder verwerfen.');
        }

        $this->db->beginTransaction();
        try {
            $delta  = $this->berechneDelta();
            $status = $delta ? 'export_offen' : 'export_bestaetigt';
            $this->db->prepare("INSERT INTO jtl_abgleich_laeufe (status, export_am, export_bestaetigt_am, benutzer_id) VALUES (?, NOW(), ?, ?)")
                ->execute([$status, $delta ? null : date('Y-m-d H:i:s'), $benutzerId]);
            $laufId = (int) $this->db->lastInsertId();
            $this->speichereSchnappschuss($laufId, 'export', $this->erpBestand($this->umfang()));
            [$zeilen, $hinweise] = $this->exportZeilen($delta, $this->schnappschuss((int) $basisLauf['id'], 'basis'));
            $this->db->prepare("UPDATE jtl_abgleich_laeufe SET export_zeilen = ?, export_hinweise = ? WHERE id = ?")
                ->execute([count($zeilen), $hinweise ? implode("\n", $hinweise) : null, $laufId]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->lauf($laufId);
    }

    public function lauf(int $id): array
    {
        $st = $this->db->prepare("SELECT * FROM jtl_abgleich_laeufe WHERE id = ?");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new RuntimeException("Abgleich #$id nicht gefunden.");
        return $r;
    }

    /**
     * Baut die Ameise-Zeilen aus dem Delta. Delta ohne Charge (z.B. SOW-Verkauf ohne Chargenwahl)
     * wird bei negativen Mengen auf die ältesten Chargen mit Bestand verteilt (wie LS-POS): Reihenfolge
     * nach lagerbestand.id (= Anlagereihenfolge), verfügbar = Basis (JTL-Stand) + bereits gebuchtes Delta.
     * Rest, der nicht verteilt werden kann, geht ohne Charge raus + Hinweis.
     * Rückgabe: [[['artikelnummer','lager','charge','menge'], ...], hinweise[]]
     */
    private function exportZeilen(array $delta, array $basis): array
    {
        if (!$delta) return [[], []];
        $artikelIds = array_unique(array_map(fn($k) => (int) explode('|', $k)[0], array_keys($delta)));
        $nr = $this->db->query("SELECT id, artikelnummer FROM artikel WHERE id IN (" . implode(',', $artikelIds) . ")")->fetchAll(PDO::FETCH_KEY_PAIR);
        $lagerName = $this->db->query("SELECT id, name FROM lager")->fetchAll(PDO::FETCH_KEY_PAIR);
        // Chargen-Reihenfolge (älteste zuerst) je Artikel+Lager
        $reihenfolge = [];
        foreach ($this->db->query("SELECT artikel_id, lager_id, charge FROM lagerbestand WHERE charge IS NOT NULL AND charge <> '' AND artikel_id IN (" . implode(',', $artikelIds) . ") ORDER BY id") as $r) {
            $reihenfolge[$r['artikel_id'] . '|' . $r['lager_id']][] = $r['charge'];
        }

        $mengen = [];   // key => menge (ohne die charge-losen negativen, die werden verteilt)
        $ohneCharge = [];
        foreach ($delta as $key => $d) {
            [$a, $l, $c] = explode('|', $key, 3);
            if ($c === '' && $d < 0) { $ohneCharge["$a|$l"] = $d; continue; }
            $mengen[$key] = ($mengen[$key] ?? 0) + $d;
        }

        $hinweise = [];
        foreach ($ohneCharge as $al => $d) {
            $rest = -$d;
            // zuerst der chargenlose JTL-Bestand selbst, erst danach die ältesten Chargen
            $ohne = max(0.0, min($basis["$al|"] ?? 0, $rest));
            if ($ohne > 0.0005) { $mengen["$al|"] = ($mengen["$al|"] ?? 0) - $ohne; $rest -= $ohne; }
            $chargen = array_unique(array_merge($reihenfolge[$al] ?? [], array_map(fn($k) => explode('|', $k, 3)[2], array_filter(array_keys($basis), fn($k) => str_starts_with($k, $al . '|')))));
            foreach ($chargen as $c) {
                if ($c === '' || $rest <= 0.0005) continue;
                $verfuegbar = ($basis["$al|$c"] ?? 0) + ($mengen["$al|$c"] ?? 0);
                if ($verfuegbar <= 0.0005) continue;
                $ab = min($verfuegbar, $rest);
                $mengen["$al|$c"] = ($mengen["$al|$c"] ?? 0) - $ab;
                $rest -= $ab;
            }
            if ($rest > 0.0005) {
                $mengen["$al|"] = ($mengen["$al|"] ?? 0) - $rest;
                [$a] = explode('|', $al);
                $hinweise[] = ($nr[$a] ?? "Artikel $a") . ": " . $this->zahl($rest) . " ohne Charge abgezogen (in JTL war nicht genug Bestand bekannt) — in JTL prüfen.";
            }
        }

        $zeilen = [];
        foreach ($mengen as $key => $m) {
            if (abs($m) < 0.0005) continue;
            [$a, $l, $c] = explode('|', $key, 3);
            $zeilen[] = ['artikelnummer' => $nr[$a] ?? '', 'lager' => $lagerName[$l] ?? '', 'charge' => $c, 'menge' => round($m, 3)];
        }
        usort($zeilen, fn($x, $y) => [$x['artikelnummer'], $x['lager'], $x['charge']] <=> [$y['artikelnummer'], $y['lager'], $y['charge']]);
        return [$zeilen, $hinweise];
    }

    /** CSV-Inhalt für die Ameise (windows-1252, Semikolon, Kopfzeile) eines Laufs -- jederzeit neu erzeugbar. */
    public function exportCsv(int $laufId): string
    {
        $lauf = $this->lauf($laufId);
        $basisLauf = $this->db->prepare("SELECT id FROM jtl_abgleich_laeufe WHERE status = 'abgeschlossen' AND id < ? ORDER BY id DESC LIMIT 1");
        $basisLauf->execute([$laufId]);
        $basisId = (int) $basisLauf->fetchColumn();
        $basis   = $basisId ? $this->schnappschuss($basisId, 'basis') : [];
        $export  = $this->schnappschuss($laufId, 'export');
        $delta = [];
        foreach (array_keys($basis + $export) as $key) {
            $d = ($export[$key] ?? 0.0) - ($basis[$key] ?? 0.0);
            if (abs($d) >= 0.0005) $delta[$key] = round($d, 3);
        }
        [$zeilen] = $this->exportZeilen($delta, $basis);

        $f = fopen('php://temp', 'w+');
        fputcsv($f, ['Artikelnummer', 'Lagername', 'Charge', 'Lagerbestand'], ';');
        foreach ($zeilen as $z) {
            fputcsv($f, [$z['artikelnummer'], $z['lager'], $z['charge'], $this->zahl($z['menge'])], ';');
        }
        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);
        return mb_convert_encoding($csv, 'Windows-1252', 'UTF-8');
    }

    public function bestaetigeExport(int $laufId): void
    {
        $st = $this->db->prepare("UPDATE jtl_abgleich_laeufe SET status = 'export_bestaetigt', export_bestaetigt_am = NOW() WHERE id = ? AND status = 'export_offen'");
        $st->execute([$laufId]);
        if (!$st->rowCount()) throw new RuntimeException('Dieser Export ist nicht (mehr) offen.');
    }

    /** Export wurde NICHT in JTL eingespielt -> verwerfen; der nächste Export enthält dieselben Änderungen wieder. */
    public function verwerfeExport(int $laufId): void
    {
        $st = $this->db->prepare("UPDATE jtl_abgleich_laeufe SET status = 'verworfen' WHERE id = ? AND status IN ('export_offen','export_bestaetigt')");
        $st->execute([$laufId]);
        if (!$st->rowCount()) throw new RuntimeException('Dieser Abgleich kann nicht mehr verworfen werden.');
    }

    // ------------------------------------------------------------------ Schritt 2: JTL-Import

    /**
     * Liest die JTL-Datei (eigener Export: Artikelnummer, Warenlager, Charge, BestandAktuell; Lagerplatz-
     * Zeilen werden summiert) und rechnet die Buchungen aus, ohne etwas zu ändern.
     */
    public function importVorschau(string $pfad): array
    {
        $schritt = $this->naechsterSchritt();
        if (!in_array($schritt['schritt'], ['erstabgleich', 'import', 'import_ohne_aenderung'], true)) {
            throw new RuntimeException($schritt['schritt'] === 'bestaetigen'
                ? 'Der Export #' . $schritt['lauf']['id'] . ' ist noch nicht als „in JTL eingespielt“ bestätigt.'
                : 'Im ERP gibt es Bestandsänderungen, die noch nicht nach JTL exportiert wurden. Zuerst Schritt 1 (Export) machen.');
        }

        $zeilen = JtlCsvReader::lese($pfad);
        if (!$zeilen) throw new RuntimeException('Die Datei ist leer oder nicht lesbar.');
        foreach (['Artikelnummer', 'Warenlager', 'BestandAktuell'] as $sp) {
            if (!array_key_exists($sp, $zeilen[0])) throw new RuntimeException("Spalte „{$sp}“ fehlt in der JTL-Datei (gefunden: " . implode(', ', array_keys($zeilen[0])) . ').');
        }

        $artikel = [];
        foreach ($this->db->query("SELECT id, artikelnummer, ist_vater FROM artikel") as $a) $artikel[$a['artikelnummer']] = $a;
        $lagerByName = [];
        foreach ($this->db->query("SELECT id, name FROM lager WHERE lager_beziehung = 'eigen' AND typ <> 'messe'") as $l) $lagerByName[mb_strtolower(trim($l['name']))] = (int) $l['id'];

        $jtl = [];
        $neuerUmfang = [];
        $unbekannteLager = [];
        $nichtImErp = 0;
        foreach ($zeilen as $z) {
            $nrJtl = trim($z['Artikelnummer']);
            if ($nrJtl === '') continue;
            $a = $artikel[$nrJtl] ?? null;
            if (!$a) { $nichtImErp++; continue; }
            if ((int) $a['ist_vater'] === 1 || (($z['ProduktTyp'] ?? '') === 'VATER')) continue;
            $aid = (int) $a['id'];
            $neuerUmfang[$aid] = true;
            $menge = (float) str_replace(',', '.', $z['BestandAktuell'] ?: '0');
            $lagerName = trim($z['Warenlager']);
            if ($lagerName === '' || abs($menge) < 0.0005) continue; // leeres Lager = Bestand 0
            $lid = $lagerByName[mb_strtolower($lagerName)] ?? null;
            if ($lid === null) { $unbekannteLager[$lagerName] = ($unbekannteLager[$lagerName] ?? 0) + 1; continue; }
            $charge = mb_substr(trim($z['Charge'] ?? ''), 0, 20);
            $key = "$aid|$lid|$charge";
            $jtl[$key] = ($jtl[$key] ?? 0) + $menge;
        }
        if ($unbekannteLager) {
            throw new RuntimeException('Diese JTL-Warenlager gibt es im ERP nicht (Name muss gleich sein): '
                . implode(', ', array_map(fn($n, $c) => "„{$n}“ ($c Zeilen)", array_keys($unbekannteLager), $unbekannteLager)));
        }
        if (!$neuerUmfang) throw new RuntimeException('Keine einzige Artikelnummer der Datei gibt es im ERP.');

        $alterUmfang = array_flip($this->umfang());
        $exportLauf  = $schritt['lauf'];
        // Ohne Export-Lauf (keine ERP-Änderung seit der Basis) ist der Vergleichsstand die Basis selbst
        $basisLauf   = $this->letzterAbgeschlossenerLauf();
        $export      = $exportLauf ? $this->schnappschuss((int) $exportLauf['id'], 'export')
                     : ($basisLauf ? $this->schnappschuss((int) $basisLauf['id'], 'basis') : []);
        $jetzt       = $this->erpBestand(array_keys($neuerUmfang));

        $buchungen = [];
        $ueberverkauf = [];
        $zielSumme = [];
        $jetztSumme = [];
        foreach (array_keys($jtl + $jetzt + array_filter($export, fn($k) => isset($neuerUmfang[(int) explode('|', $k)[0]]), ARRAY_FILTER_USE_KEY)) as $key) {
            $aid = (int) explode('|', $key)[0];
            if (!isset($neuerUmfang[$aid])) continue;
            $luecke = isset($alterUmfang[$aid]) ? (($jetzt[$key] ?? 0) - ($export[$key] ?? 0)) : 0.0; // ERP-Änderungen nach dem Export
            $ziel = ($jtl[$key] ?? 0) + $luecke;
            if ($ziel < -0.0005) { $ueberverkauf[] = $key; $ziel = 0.0; }
            $vorher = $jetzt[$key] ?? 0.0;
            $zielSumme[$aid]  = ($zielSumme[$aid] ?? 0) + $ziel;
            $jetztSumme[$aid] = ($jetztSumme[$aid] ?? 0) + $vorher;
            if (abs($ziel - $vorher) >= 0.0005) $buchungen[$key] = ['vorher' => $vorher, 'nachher' => round($ziel, 3)];
        }

        $mitBestand = count(array_filter($jetztSumme, fn($s) => $s > 0.0005));
        $aufNull = 0;
        foreach ($jetztSumme as $aid => $s) if ($s > 0.0005 && ($zielSumme[$aid] ?? 0) < 0.0005) $aufNull++;
        $grenze = max(self::PLAUSI_MIN, (int) ceil($mitBestand * self::PLAUSI_ANTEIL));

        return [
            'ist_erstabgleich' => $schritt['schritt'] === 'erstabgleich',
            'export_lauf_id'   => $exportLauf['id'] ?? null,
            'jtl'              => $jtl,
            'umfang'           => array_keys($neuerUmfang),
            'buchungen'        => $buchungen,
            'zeilen_datei'     => count($zeilen),
            'artikel_umfang'   => count($neuerUmfang),
            'neu_im_umfang'    => count(array_diff_key($neuerUmfang, $alterUmfang)),
            'nicht_im_erp'     => $nichtImErp,
            'ueberverkauf'     => $this->schluesselText($ueberverkauf),
            'auf_null'         => $aufNull,
            'mit_bestand'      => $mitBestand,
            'braucht_bestaetigung' => $aufNull > $grenze,
            'zugang'           => array_sum(array_map(fn($b) => max(0, $b['nachher'] - $b['vorher']), $buchungen)),
            'abgang'           => array_sum(array_map(fn($b) => max(0, $b['vorher'] - $b['nachher']), $buchungen)),
            'beispiele'        => $this->beispiele($buchungen),
        ];
    }

    /** Bucht eine Vorschau (aus importVorschau). Alles in einer Transaktion. */
    public function importBuchen(array $v, string $dateiname, int $benutzerId, bool $plausiBestaetigt): array
    {
        if ($v['braucht_bestaetigung'] && !$plausiBestaetigt) {
            throw new RuntimeException("Der Abgleich würde {$v['auf_null']} von {$v['mit_bestand']} Artikeln mit Bestand auf 0 setzen. Bitte die Datei prüfen oder ausdrücklich bestätigen.");
        }
        // Zustand darf sich seit der Vorschau nicht geändert haben
        $schritt = $this->naechsterSchritt();
        $passt = $v['export_lauf_id']
            ? ($schritt['schritt'] === 'import' && (int) $schritt['lauf']['id'] === (int) $v['export_lauf_id'])
            : ($schritt['schritt'] === ($v['ist_erstabgleich'] ? 'erstabgleich' : 'import_ohne_aenderung'));
        if (!$passt) throw new RuntimeException('Der Abgleich-Stand hat sich seit der Vorschau geändert. Bitte die Datei neu hochladen.');

        $chargePflicht = array_flip(array_map('intval', $this->db->query("SELECT id FROM artikel WHERE charge_pflicht = 1")->fetchAll(PDO::FETCH_COLUMN)));
        $sel = $this->db->prepare("SELECT id, bestand, charge_status FROM lagerbestand WHERE artikel_id = ? AND lager_id = ? AND charge <=> ? ORDER BY id");
        $upd = $this->db->prepare("UPDATE lagerbestand SET bestand = ?, charge_status = ? WHERE id = ?");
        $ins = $this->db->prepare("INSERT INTO lagerbestand (artikel_id, lager_id, charge, charge_status, bestand, mindestbestand) VALUES (?, ?, ?, ?, ?, 0)");
        $bew = $this->db->prepare("
            INSERT INTO lager_bewegungen (artikel_id, lager_id, charge, bewegungstyp, menge, bestand_vorher, bestand_nachher, referenz, notiz, benutzer_id)
            VALUES (?, ?, ?, 'korrektur', ?, ?, ?, ?, 'Korrektur durch JTL-Lagerstandsabgleich', ?)");

        $this->db->beginTransaction();
        try {
            if ($v['export_lauf_id']) {
                $laufId = (int) $v['export_lauf_id'];
            } else {
                // Erstabgleich oder keine ERP-Änderung seit der Basis: Lauf ohne Exportzeilen anlegen
                $this->db->prepare("INSERT INTO jtl_abgleich_laeufe (status, ist_erstabgleich, export_am, export_bestaetigt_am, benutzer_id) VALUES ('export_bestaetigt', ?, NOW(), NOW(), ?)")
                    ->execute([$v['ist_erstabgleich'] ? 1 : 0, $benutzerId]);
                $laufId = (int) $this->db->lastInsertId();
            }
            $ref = 'JTL-Abgleich #' . $laufId;

            foreach ($v['buchungen'] as $key => $b) {
                [$aid, $lid, $c] = explode('|', $key, 3);
                $aid = (int) $aid; $lid = (int) $lid;
                $charge = $c === '' ? null : $c;
                $sel->execute([$aid, $lid, $charge]);
                $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
                $nachher = max(0.0, (float) $b['nachher']);
                $status = $charge !== null ? 'erfasst' : ((isset($chargePflicht[$aid]) && $nachher > 0) ? 'nachzutragen' : 'unbekannt');
                if ($rows) {
                    // erste Zeile bekommt den Zielwert, eventuelle Doppelzeilen (NULL-Charge) werden 0
                    $upd->execute([$nachher, $rows[0]['charge_status'] === 'unbekannt' ? $status : $rows[0]['charge_status'], $rows[0]['id']]);
                    for ($i = 1; $i < count($rows); $i++) $upd->execute([0, $rows[$i]['charge_status'], $rows[$i]['id']]);
                } elseif ($nachher > 0) {
                    $ins->execute([$aid, $lid, $charge, $status, $nachher]);
                }
                $bew->execute([$aid, $lid, $charge, abs($nachher - $b['vorher']), $b['vorher'], $nachher, $ref, $benutzerId]);
            }

            // Neue Basis = JTL-Werte; Umfang = Artikel der Datei
            $this->db->prepare("DELETE FROM jtl_abgleich_bestand WHERE lauf_id = ? AND art = 'basis'")->execute([$laufId]);
            $this->speichereSchnappschuss($laufId, 'basis', $v['jtl']);
            $this->db->exec("DELETE FROM jtl_abgleich_umfang");
            $insU = $this->db->prepare("INSERT INTO jtl_abgleich_umfang (artikel_id, lauf_id) VALUES (?, ?)");
            foreach ($v['umfang'] as $aid) $insU->execute([(int) $aid, $laufId]);

            $ergebnis = [
                'buchungen' => count($v['buchungen']), 'zugang' => $v['zugang'], 'abgang' => $v['abgang'],
                'artikel_umfang' => $v['artikel_umfang'], 'nicht_im_erp' => $v['nicht_im_erp'],
                'ueberverkauf' => $v['ueberverkauf'], 'auf_null' => $v['auf_null'],
            ];
            $this->db->prepare("UPDATE jtl_abgleich_laeufe SET status = 'abgeschlossen', import_am = NOW(), import_datei = ?, import_ergebnis = ?, benutzer_id = ? WHERE id = ?")
                ->execute([mb_substr($dateiname, 0, 255), json_encode($ergebnis, JSON_UNESCAPED_UNICODE), $benutzerId, $laufId]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['lauf_id' => $laufId] + $ergebnis;
    }

    // ------------------------------------------------------------------ Hilfen

    /** Vorschau-Zeilen für die Anzeige: größte Abweichungen zuerst. */
    public function exportVorschau(int $laufId, int $max = 50): array
    {
        $csv = mb_convert_encoding($this->exportCsv($laufId), 'UTF-8', 'Windows-1252');
        $zeilen = array_map(fn($l) => str_getcsv($l, ';'), array_filter(explode("\n", trim($csv))));
        array_shift($zeilen);
        return array_slice($zeilen, 0, $max);
    }

    private function beispiele(array $buchungen, int $max = 40): array
    {
        uasort($buchungen, fn($a, $b) => abs($b['nachher'] - $b['vorher']) <=> abs($a['nachher'] - $a['vorher']));
        $out = [];
        foreach (array_slice($buchungen, 0, $max, true) as $key => $b) $out[] = ['text' => $this->schluesselText([$key])[0]] + $b;
        return $out;
    }

    private function schluesselText(array $keys): array
    {
        if (!$keys) return [];
        $ids = array_unique(array_map(fn($k) => (int) explode('|', $k)[0], $keys));
        $nr = $this->db->query("SELECT id, CONCAT(artikelnummer, ' ', name) FROM artikel WHERE id IN (" . implode(',', $ids) . ")")->fetchAll(PDO::FETCH_KEY_PAIR);
        $lager = $this->db->query("SELECT id, name FROM lager")->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_map(function ($k) use ($nr, $lager) {
            [$a, $l, $c] = explode('|', $k, 3);
            return ($nr[$a] ?? "Artikel $a") . ' · ' . ($lager[$l] ?? "Lager $l") . ($c !== '' ? ' · Charge ' . $c : '');
        }, $keys);
    }

    private function zahl(float $z): string
    {
        $s = rtrim(rtrim(number_format($z, 3, ',', ''), '0'), ',');
        return $s === '-0' ? '0' : $s;
    }
}
