<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../dokumente/DokumentRepository.php';
require_once __DIR__ . '/PartnerRepository.php';

/**
 * PartnerKontoService – Partner-Konto (Partner-Abrechnung Schritt 3)
 *
 * Fremdrechnung + Spende (Klärung Steuerberatung 2026-10-26): das Geld gehört dem
 * Partner, wir verwahren es nur — es gibt KEINE Rechnung/Gutschrift von uns, nur eine
 * Verkaufsaufstellung + Zahlungsaviso. Saldo = Verkäufe an der Kasse − Auszahlungen.
 *
 * Kommission: eigener Umsatz, der Partner stellt UNS Rechnung zu vorab vereinbarten
 * EK-Preisen (netto − Provision, hinterlegt als artikel_lieferanten.netto_ek beim
 * verknüpften Lieferanten). Hier nur eine Kontrollliste zum Abgleich seiner Rechnung.
 *
 * Verkäufe = Bon-Positionen mit Partner-Snapshot (kassen_bon_positionen.partner_modus),
 * nur gültige Verkaufsbons; Retouren stehen dort mit negativer Menge, stornierte Bons
 * (und damit ihr Storno-Beleg) fallen ganz weg — gleiche Regel wie die Verkaufsliste.
 */
class PartnerKontoService
{
    private const ZEILE = 'bp.menge * bp.einzelpreis_brutto * (1 - bp.rabatt_prozent / 100)';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Fremdrechnungs-/Spenden-Verkäufe im Zeitraum (je Position) */
    public function getVerkaeufe(int $partnerId, string $von, string $bis): array
    {
        $stmt = $this->db->prepare("
            SELECT b.id AS bon_id, b.erstellt_am, b.bon_nr, b.zahlungsart, a.artikelnummer, bp.bezeichnung, bp.menge,
                   bp.einzelpreis_brutto, bp.rabatt_prozent, bp.steuer_prozent,
                   ROUND(" . self::ZEILE . ", 2) AS summe
            FROM kassen_bon_positionen bp
            JOIN kassen_bons b ON b.id = bp.bon_id AND b.typ = 'verkauf' AND b.storniert = 0
            LEFT JOIN artikel a ON a.id = bp.artikel_id
            WHERE bp.partner_id = ? AND bp.partner_modus IN ('fremdrechnung', 'spende')
              AND DATE(b.erstellt_am) BETWEEN ? AND ?
            ORDER BY b.erstellt_am, b.id, bp.sort_order
        ");
        $stmt->execute([$partnerId, $von, $bis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Kennzahlen eines Partners: Verkäufe/Auszahlungen gesamt + im Zeitraum, Saldo.
     * Spende zusätzlich: Bar-Anteil (liegt in der Spendenlade) und was davon schon
     * übergeben wurde — der Rest der Spenden kam per Karte auf unser Konto.
     */
    public function getKonto(int $partnerId, ?string $von = null, ?string $bis = null): array
    {
        $verkauf = function (?string $von, ?string $bis) use ($partnerId): float {
            $sql = "SELECT COALESCE(SUM(" . self::ZEILE . "), 0)
                    FROM kassen_bon_positionen bp
                    JOIN kassen_bons b ON b.id = bp.bon_id AND b.typ = 'verkauf' AND b.storniert = 0
                    WHERE bp.partner_id = ? AND bp.partner_modus IN ('fremdrechnung', 'spende')";
            $p = [$partnerId];
            if ($von) { $sql .= " AND DATE(b.erstellt_am) BETWEEN ? AND ?"; $p[] = $von; $p[] = $bis; }
            $s = $this->db->prepare($sql);
            $s->execute($p);
            return round((float)$s->fetchColumn(), 2);
        };

        // Bar-Anteil der Spende je Partner: spende_bar des Bons anteilig nach seinem Spendenanteil
        $spendeBar = function (?string $von, ?string $bis) use ($partnerId): float {
            $sql = "SELECT COALESCE(SUM(b.spende_bar * x.summe / b.spende_betrag), 0)
                    FROM kassen_bons b
                    JOIN (SELECT bp.bon_id, SUM(" . self::ZEILE . ") AS summe
                          FROM kassen_bon_positionen bp
                          WHERE bp.partner_id = ? AND bp.partner_modus = 'spende'
                          GROUP BY bp.bon_id) x ON x.bon_id = b.id
                    WHERE b.typ = 'verkauf' AND b.storniert = 0 AND b.spende_betrag <> 0";
            $p = [$partnerId];
            if ($von) { $sql .= " AND DATE(b.erstellt_am) BETWEEN ? AND ?"; $p[] = $von; $p[] = $bis; }
            $s = $this->db->prepare($sql);
            $s->execute($p);
            return round((float)$s->fetchColumn(), 2);
        };

        $az = $this->db->prepare("
            SELECT COALESCE(SUM(betrag), 0) AS gesamt,
                   COALESCE(SUM(CASE WHEN art = 'spendenlade' THEN betrag ELSE 0 END), 0) AS spendenlade
            FROM partner_auszahlungen WHERE partner_id = ? AND storniert = 0
        ");
        $az->execute([$partnerId]);
        $azRow = $az->fetch(PDO::FETCH_ASSOC);

        $verkaufGesamt = $verkauf(null, null);
        $barGesamt     = $spendeBar(null, null);
        return [
            'verkauf_gesamt'       => $verkaufGesamt,
            'ausgezahlt_gesamt'    => round((float)$azRow['gesamt'], 2),
            'saldo'                => round($verkaufGesamt - (float)$azRow['gesamt'], 2),
            'verkauf_zeitraum'     => $von ? $verkauf($von, $bis) : null,
            'spende_bar_gesamt'    => $barGesamt,
            'spende_bar_zeitraum'  => $von ? $spendeBar($von, $bis) : null,
            'spendenlade_offen'    => round($barGesamt - (float)$azRow['spendenlade'], 2),
        ];
    }

    public function getAuszahlungen(int $partnerId): array
    {
        $stmt = $this->db->prepare("
            SELECT pa.*, k.kasse_nr, u.formularname AS benutzer_name
            FROM partner_auszahlungen pa
            LEFT JOIN kassen k ON k.id = pa.kasse_id
            LEFT JOIN benutzer u ON u.id = pa.benutzer_id
            WHERE pa.partner_id = ?
            ORDER BY pa.datum DESC, pa.id DESC
        ");
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAuszahlung(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM partner_auszahlungen WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Auszahlung buchen (= Zahlungsaviso). Höchstens der offene Saldo; Spendenlade nur
     * bei Spende und höchstens deren offener Inhalt. Bar aus der Kasse → Kassenbuch-Entnahme.
     */
    public function auszahlungBuchen(int $partnerId, array $d, int $benutzerId): array
    {
        $partner = (new PartnerRepository())->findById($partnerId);
        if (!$partner) return ['erfolg' => false, 'fehler' => ['Partner nicht gefunden.']];
        if ($partner['typ'] === 'kommission') {
            return ['erfolg' => false, 'fehler' => ['Kommission wird nicht ausgezahlt — der Partner stellt Rechnung (Einkauf).']];
        }

        $betrag = round((float)str_replace(',', '.', (string)($d['betrag'] ?? 0)), 2);
        $art    = (string)($d['art'] ?? '');
        $datum  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['datum'] ?? '') ? $d['datum'] : date('Y-m-d');
        $von    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['zeitraum_von'] ?? '') ? $d['zeitraum_von'] : null;
        $bis    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['zeitraum_bis'] ?? '') ? $d['zeitraum_bis'] : null;
        $kasseId = !empty($d['kasse_id']) ? (int)$d['kasse_id'] : null;
        $notiz  = trim((string)($d['notiz'] ?? '')) ?: null;

        $konto  = $this->getKonto($partnerId);
        $fehler = [];
        if ($betrag <= 0) $fehler[] = 'Betrag muss größer als 0 sein.';
        if (!in_array($art, ['bar_kasse', 'spendenlade', 'ueberweisung'], true)) $fehler[] = 'Auszahlungsart fehlt.';
        if ($betrag > $konto['saldo'] + 0.005) $fehler[] = 'Höchstens der offene Saldo von € ' . number_format($konto['saldo'], 2, ',', '.') . '.';
        if ($art === 'spendenlade') {
            if ($partner['typ'] !== 'spende') $fehler[] = 'Spendenlade gibt es nur bei Spenden-Partnern.';
            elseif ($betrag > $konto['spendenlade_offen'] + 0.005) $fehler[] = 'In der Spendenlade liegen laut Kasse nur € ' . number_format($konto['spendenlade_offen'], 2, ',', '.') . '.';
        }
        if ($art === 'bar_kasse') {
            $k = $this->db->prepare("SELECT id FROM kassen WHERE id = ? AND aktiv = 1");
            $k->execute([$kasseId]);
            if (!$k->fetchColumn()) $fehler[] = 'Bitte die Kasse wählen, aus deren Lade bar ausgezahlt wird.';
        } else {
            $kasseId = null;
        }
        if ($fehler) return ['erfolg' => false, 'fehler' => $fehler];

        $this->db->beginTransaction();
        try {
            $nummer = (new DokumentRepository())->naechsteNummer('partner_auszahlung', (int)substr($datum, 0, 4));
            $kbId = null;
            if ($art === 'bar_kasse') {
                $this->db->prepare("INSERT INTO kassenbuch (typ, betrag, notiz, kasse_id, benutzer_id) VALUES ('entnahme', ?, ?, ?, ?)")
                    ->execute([-$betrag, 'Auszahlung an Partner ' . $partner['name'] . ' (' . $nummer . ')', $kasseId, $benutzerId ?: null]);
                $kbId = (int)$this->db->lastInsertId();
            }
            $this->db->prepare("
                INSERT INTO partner_auszahlungen (partner_id, nummer, datum, betrag, art, zeitraum_von, zeitraum_bis, kasse_id, kassenbuch_id, notiz, benutzer_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$partnerId, $nummer, $datum, $betrag, $art, $von, $bis, $kasseId, $kbId, $notiz, $benutzerId ?: null]);
            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => ['Datenbankfehler: ' . $e->getMessage()]];
        }
        Logger::log('partner.auszahlung', 'partner_auszahlungen', $id, ['partner_id' => $partnerId, 'nummer' => $nummer, 'betrag' => $betrag, 'art' => $art]);
        return ['erfolg' => true, 'id' => $id, 'nummer' => $nummer];
    }

    /** Storno einer Auszahlung; Bar aus der Kasse wird per Kassenbuch-Einlage zurückgebucht */
    public function auszahlungStornieren(int $id, int $benutzerId): array
    {
        $a = $this->getAuszahlung($id);
        if (!$a) return ['erfolg' => false, 'fehler' => ['Auszahlung nicht gefunden.']];
        if ($a['storniert']) return ['erfolg' => false, 'fehler' => ['Schon storniert.']];

        $this->db->beginTransaction();
        try {
            $kbId = null;
            if ($a['kassenbuch_id']) {
                $this->db->prepare("INSERT INTO kassenbuch (typ, betrag, notiz, kasse_id, benutzer_id) VALUES ('einlage', ?, ?, ?, ?)")
                    ->execute([(float)$a['betrag'], 'Storno Partner-Auszahlung ' . $a['nummer'], $a['kasse_id'], $benutzerId ?: null]);
                $kbId = (int)$this->db->lastInsertId();
            }
            $this->db->prepare("UPDATE partner_auszahlungen SET storniert = 1, storno_kassenbuch_id = ? WHERE id = ?")->execute([$kbId, $id]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => ['Datenbankfehler: ' . $e->getMessage()]];
        }
        Logger::log('partner.auszahlung_storno', 'partner_auszahlungen', $id, ['nummer' => $a['nummer']]);
        return ['erfolg' => true];
    }

    /**
     * Kommission: verkaufte Menge je Artikel im Zeitraum × vereinbarter EK (netto) —
     * zum Abgleich mit der Rechnung des Partners. Ohne hinterlegten EK bleibt ek_netto NULL.
     */
    public function getKommissionKontrolle(int $partnerId, string $von, string $bis): array
    {
        $stmt = $this->db->prepare("
            SELECT bp.artikel_id, a.artikelnummer, a.name, bp.steuer_prozent,
                   SUM(bp.menge) AS menge,
                   ROUND(SUM(" . self::ZEILE . "), 2) AS vk_brutto,
                   al.netto_ek AS ek_netto
            FROM kassen_bon_positionen bp
            JOIN kassen_bons b ON b.id = bp.bon_id AND b.typ = 'verkauf' AND b.storniert = 0
            JOIN artikel a ON a.id = bp.artikel_id
            JOIN partner p ON p.id = bp.partner_id
            LEFT JOIN artikel_lieferanten al ON al.artikel_id = bp.artikel_id AND al.lieferant_id = p.lieferant_id
            WHERE bp.partner_id = ? AND bp.partner_modus = 'kommission'
              AND DATE(b.erstellt_am) BETWEEN ? AND ?
            GROUP BY bp.artikel_id, a.artikelnummer, a.name, bp.steuer_prozent, al.netto_ek
            HAVING SUM(bp.menge) <> 0
            ORDER BY a.artikelnummer
        ");
        $stmt->execute([$partnerId, $von, $bis]);
        $zeilen = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $satz = (float)$r['steuer_prozent'];
            $r['vk_netto']   = round((float)$r['vk_brutto'] / (1 + $satz / 100), 2);
            $r['ek_summe']   = $r['ek_netto'] !== null ? round((float)$r['ek_netto'] * (float)$r['menge'], 2) : null;
            $zeilen[] = $r;
        }
        return $zeilen;
    }
}
