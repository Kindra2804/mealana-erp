<?php

require_once __DIR__ . '/../../core/database.php';

/**
 * Schild-Bausteine (Migration 232): Kürzel + Rohmaterial ZENTRAL je Achse + Wert-Text.
 *
 * Jedes Konfigurator-Layout hat eigene Kopien seiner Werte (varianten_achse_werte je artikel_id);
 * die Bausteine gelten für alle Layouts mit demselben Achsen-/Wert-Text auf einmal.
 * - Kürzel: Bausteine der Artikelnummer fertiger Schilder (FertigesSchildService).
 * - Rohmaterial: Lagerartikel (z.B. "Filzplatte rot"). Bestand <= 0 -> Wert "nicht verfügbar":
 *   im Shop ausgegraut (Picker-Snippet), an der Kasse nur Hinweis.
 *   varianten_achse_werte.rohmaterial_artikel_id (Migr. 169) hat als Ausnahme je Layout Vorrang.
 */
class KonfigBausteineService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Alle Achse+Wert-Paare der konfigurierbaren Artikel mit Kürzel, Rohmaterial und Ampel. */
    public function alle(): array
    {
        $zeilen = $this->db->query("
            SELECT w.achse_id, ac.name AS achse, w.wert,
                   COUNT(DISTINCT w.artikel_id) AS layouts,
                   MIN(aa.sort_order) AS achse_sort, MIN(w.sort_order) AS wert_sort,
                   kb.kuerzel, kb.rohmaterial_artikel_id, COALESCE(kb.nur_intern, 0) AS nur_intern,
                   COALESCE(kb.texteingabe, 0) AS texteingabe, kb.max_zeichen,
                   ra.artikelnummer AS roh_nr, ra.name AS roh_name
            FROM varianten_achse_werte w
            JOIN artikel a        ON a.id = w.artikel_id AND a.ist_konfigurierbar = 1
            JOIN varianten_achsen ac ON ac.id = w.achse_id
            LEFT JOIN artikel_achsen aa ON aa.artikel_id = w.artikel_id AND aa.achse_id = w.achse_id
            LEFT JOIN konfig_bausteine kb ON kb.achse_id = w.achse_id AND kb.wert = w.wert
            LEFT JOIN artikel ra  ON ra.id = kb.rohmaterial_artikel_id
            GROUP BY w.achse_id, ac.name, w.wert, kb.kuerzel, kb.rohmaterial_artikel_id, kb.nur_intern, kb.texteingabe, kb.max_zeichen, ra.artikelnummer, ra.name
            ORDER BY achse_sort, ac.name, wert_sort, w.wert
        ")->fetchAll(PDO::FETCH_ASSOC);

        $bestand = $this->rohmaterialBestand(array_filter(array_column($zeilen, 'rohmaterial_artikel_id')));
        foreach ($zeilen as &$z) {
            $z['ampel'] = null;
            $z['roh_bestand'] = null;
            if ($z['rohmaterial_artikel_id']) {
                $b = $bestand[(int) $z['rohmaterial_artikel_id']] ?? ['bestand' => 0.0, 'mindest' => 0.0];
                $z['roh_bestand'] = $b['bestand'];
                $z['ampel'] = $b['bestand'] <= 0 ? 'rot' : ($b['bestand'] <= $b['mindest'] ? 'gelb' : 'gruen');
            }
        }
        return $zeilen;
    }

    /**
     * Speichert Kürzel/Rohmaterial. $eingaben = [ "achseId|wert" => ['kuerzel' => .., 'rohmaterial' => Artikelnummer] ].
     * Gibt Fehlerliste zurück (leer = gespeichert). Prüft: Kürzel nur Buchstaben/Ziffern, max. 12, eindeutig je Achse;
     * Rohmaterial muss ein vorhandener, lagerführender Artikel (kein Vater) sein.
     */
    public function speichern(array $eingaben): array
    {
        $fehler = [];
        $proAchse = [];
        $daten = [];
        $artikelNrStmt = $this->db->prepare("SELECT id, ist_vater FROM artikel WHERE artikelnummer = ?");
        foreach ($eingaben as $schluessel => $e) {
            [$achseId, $wert] = explode('|', $schluessel, 2) + [1 => ''];
            $achseId = (int) $achseId;
            $kuerzel = trim($e['kuerzel'] ?? '');
            $rohNr   = trim($e['rohmaterial'] ?? '');
            if ($kuerzel !== '' && !preg_match('/^[A-Za-z0-9]{1,12}$/', $kuerzel)) {
                $fehler[] = "„{$wert}“: Kürzel „{$kuerzel}“ — nur Buchstaben und Ziffern, höchstens 12 Zeichen.";
                continue;
            }
            if ($kuerzel !== '') {
                $k = mb_strtolower($kuerzel);
                if (isset($proAchse[$achseId][$k])) {
                    $fehler[] = "Kürzel „{$kuerzel}“ ist in derselben Achse doppelt („{$proAchse[$achseId][$k]}“ und „{$wert}“).";
                    continue;
                }
                $proAchse[$achseId][$k] = $wert;
            }
            $rohId = null;
            if ($rohNr !== '') {
                $artikelNrStmt->execute([$rohNr]);
                $ra = $artikelNrStmt->fetch(PDO::FETCH_ASSOC);
                if (!$ra) {
                    $fehler[] = "„{$wert}“: Rohmaterial-Artikelnummer „{$rohNr}“ gibt es nicht.";
                    continue;
                }
                if ((int) $ra['ist_vater'] === 1) {
                    $fehler[] = "„{$wert}“: „{$rohNr}“ ist ein Vater-Artikel — bitte die konkrete Variante angeben.";
                    continue;
                }
                $rohId = (int) $ra['id'];
            }
            $maxRoh = trim((string) ($e['max_zeichen'] ?? ''));
            if ($maxRoh !== '' && (!ctype_digit($maxRoh) || (int) $maxRoh < 1 || (int) $maxRoh > 200)) {
                $fehler[] = "„{$wert}“: max. Zeichen bitte als Zahl von 1 bis 200.";
                continue;
            }
            $daten[] = [
                $achseId,
                $wert,
                $kuerzel !== '' ? $kuerzel : null,
                $rohId,
                !empty($e['intern']) ? 1 : 0,
                !empty($e['text']) ? 1 : 0,
                $maxRoh !== '' ? (int) $maxRoh : null
            ];
        }
        if ($fehler) return $fehler;

        $up = $this->db->prepare("
            INSERT INTO konfig_bausteine (achse_id, wert, kuerzel, rohmaterial_artikel_id, nur_intern, texteingabe, max_zeichen)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE kuerzel = VALUES(kuerzel), rohmaterial_artikel_id = VALUES(rohmaterial_artikel_id),
                                    nur_intern = VALUES(nur_intern), texteingabe = VALUES(texteingabe), max_zeichen = VALUES(max_zeichen)");
        $this->db->beginTransaction();
        try {
            foreach ($daten as $d) $up->execute($d);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return [];
    }

    /** Bestand (eigene Lager ohne Messe) + Mindestbestand je Rohmaterial-Artikel. */
    public function rohmaterialBestand(array $artikelIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $artikelIds)));
        if (!$ids) return [];
        $out = [];
        foreach (
            $this->db->query("
            SELECT lb.artikel_id, SUM(lb.bestand) AS bestand, MAX(COALESCE(lb.mindestbestand, 0)) AS mindest
            FROM lagerbestand lb
            JOIN lager l ON l.id = lb.lager_id AND l.lager_beziehung = 'eigen' AND l.typ <> 'messe'
            WHERE lb.artikel_id IN (" . implode(',', $ids) . ")
            GROUP BY lb.artikel_id") as $r
        ) {
            $out[(int) $r['artikel_id']] = ['bestand' => (float) $r['bestand'], 'mindest' => (float) $r['mindest']];
        }
        return $out;
    }

    /**
     * Verfügbarkeit je wert_id eines Layouts: [wert_id => ['verfuegbar' => bool, 'rohmaterial' => Name|null]].
     * Werte ohne Rohmaterial sind immer verfügbar.
     */
    public function verfuegbarkeit(int $artikelId): array
    {
        $st = $this->db->prepare("
            SELECT w.id, COALESCE(w.rohmaterial_artikel_id, kb.rohmaterial_artikel_id) AS roh_id, ra.name AS roh_name
            FROM varianten_achse_werte w
            LEFT JOIN konfig_bausteine kb ON kb.achse_id = w.achse_id AND kb.wert = w.wert
            LEFT JOIN artikel ra ON ra.id = COALESCE(w.rohmaterial_artikel_id, kb.rohmaterial_artikel_id)
            WHERE w.artikel_id = ?");
        $st->execute([$artikelId]);
        $zeilen = $st->fetchAll(PDO::FETCH_ASSOC);
        $bestand = $this->rohmaterialBestand(array_filter(array_column($zeilen, 'roh_id')));
        $out = [];
        foreach ($zeilen as $z) {
            $verf = !$z['roh_id'] || (($bestand[(int) $z['roh_id']]['bestand'] ?? 0) > 0);
            $out[(int) $z['id']] = ['verfuegbar' => $verf, 'rohmaterial' => $z['roh_name']];
        }
        return $out;
    }

    /**
     * wert_ids eines Layouts, die "nur intern" sind (Migr. 236): nicht im Shop, aber beim Anlegen
     * fertiger Schilder und an der Kasse wählbar. Rückgabe: [wert_id => true].
     */
    public function interneWertIds(int $artikelId): array
    {
        $st = $this->db->prepare("
            SELECT w.id FROM varianten_achse_werte w
            JOIN konfig_bausteine kb ON kb.achse_id = w.achse_id AND kb.wert = w.wert AND kb.nur_intern = 1
            WHERE w.artikel_id = ?");
        $st->execute([$artikelId]);
        return array_fill_keys(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    /** Standard-Länge für Personalisierungstexte, wenn beim Baustein nichts eingetragen ist. */
    public const MAX_ZEICHEN_STANDARD = 30;

    /**
     * Werte eines Layouts mit Texteingabe (Personalisierung, Migr. 236): [wert_id => max. Zeichen].
     */
    public function textWerte(int $artikelId): array
    {
        $st = $this->db->prepare("
            SELECT w.id, COALESCE(kb.max_zeichen, " . self::MAX_ZEICHEN_STANDARD . ") FROM varianten_achse_werte w
            JOIN konfig_bausteine kb ON kb.achse_id = w.achse_id AND kb.wert = w.wert AND kb.texteingabe = 1
            WHERE w.artikel_id = ?");
        $st->execute([$artikelId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** Kürzel je wert_id eines Layouts: [wert_id => kuerzel|null]. */
    public function kuerzelJeWert(int $artikelId): array
    {
        $st = $this->db->prepare("
            SELECT w.id, kb.kuerzel FROM varianten_achse_werte w
            LEFT JOIN konfig_bausteine kb ON kb.achse_id = w.achse_id AND kb.wert = w.wert
            WHERE w.artikel_id = ?");
        $st->execute([$artikelId]);
        return array_map(fn($k) => $k !== null ? (string) $k : null, $st->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** Prüfen ob Schild auf Platte passt (normal oder gedreht) */
    public static function passtAufPlatte(?int $schildB, ?int $schildH, ?int $platteB, ?int $platteH): bool
    {
        if ($schildB === null || $schildH === null || $platteB === null || $platteH === null) {
            return true;
        }

        return (($schildB <= $platteB && $schildH <= $platteH) || ($schildB <= $platteH && $schildH <= $platteB));
    }

    /**
     * SQL-Bedingung für den Shop-Sync (ShopSyncRepository): ein konfigurierbarer Artikel ist fällig,
     * wenn sich der Bestand eines seiner Rohmaterialien oder ein Baustein nach dem letzten Sync geändert hat.
     * Erwartet die Aliase a (artikel) und ash (artikel_shops).
     */
    public static function syncFaelligSql(): string
    {
        return "
                  OR (a.ist_konfigurierbar = 1 AND EXISTS (
                      SELECT 1 FROM varianten_achse_werte kw
                      LEFT JOIN konfig_bausteine kkb ON kkb.achse_id = kw.achse_id AND kkb.wert = kw.wert
                      LEFT JOIN lagerbestand klb ON klb.artikel_id = COALESCE(kw.rohmaterial_artikel_id, kkb.rohmaterial_artikel_id)
                      WHERE kw.artikel_id = a.id
                        AND (kkb.geaendert_am > ash.synced_at OR klb.geaendert_am > ash.synced_at)
                  ))";
    }
}
