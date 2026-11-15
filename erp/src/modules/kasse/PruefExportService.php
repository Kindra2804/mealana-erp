<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/BfrService.php';

/**
 * Datenexport der Kasse für die Betriebsprüfung (§ 131 BAO, Kassenrichtlinie).
 * Vorbild BONit (Handbuch bfp, F.A.Q. Finanzprüfung): die Kassentabellen 1:1 als CSV mit TAB,
 * dazu optional das DEP vom BFR. Bei uns bewusst OHNE Artikelstammdaten (zu groß) — Name, EAN
 * und Artikelnummer stehen ohnehin auf jeder Bon-Position. Nur kleine Stammdaten zum Lesen der
 * Schlüssel (Kassen, Artikelgruppen, Benutzer, Partner) kommen mit.
 *
 * Ergebnis: ein ZIP mit je einer .csv pro Tabelle (UTF-8 mit BOM, TAB, Kopfzeile, Werte wie in
 * der Datenbank: Dezimalpunkt, Datum JJJJ-MM-TT hh:mm:ss) + LIESMICH.txt mit Zeitraum, Zeilen-
 * anzahl und SHA-256 je Datei. ZIP über PharData, weil die zip-Erweiterung nicht überall aktiv ist.
 */
class PruefExportService
{
    private PDO $db;

    /** Spalten, die nie in den Export gehören (Zugangsschlüssel, keine Buchhaltungsdaten). */
    private const OHNE_SPALTEN = ['paperless_token'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return array ['erfolg' => true, 'pfad' => string, 'dateiname' => string, 'hinweise' => string[]]
     *             | ['erfolg' => false, 'fehler' => string]
     */
    public function erstelleZip(string $von, string $bis, ?int $kasseId, bool $mitDep, int $benutzerId): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $von) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bis) || $von > $bis) {
            return ['erfolg' => false, 'fehler' => 'Bitte einen gültigen Zeitraum wählen (von ≤ bis).'];
        }

        $kassen = $this->kassen($kasseId);
        if (empty($kassen)) {
            return ['erfolg' => false, 'fehler' => 'Keine Kasse gefunden.'];
        }
        $kIds  = implode(',', array_map('intval', array_column($kassen, 'id')));
        $p     = ['von' => $von . ' 00:00:00', 'bis' => $bis . ' 23:59:59'];
        $pDat  = ['von' => $von, 'bis' => $bis];

        // Datei => [SQL, Parameter, Beschreibung]. Bon-Positionen hängen am Bon (nicht am eigenen Datum).
        $bonFilter = "SELECT id FROM kassen_bons WHERE kasse_id IN ($kIds) AND erstellt_am BETWEEN :von AND :bis";
        $tabellen = [
            'kassen' => ["SELECT * FROM kassen WHERE id IN ($kIds) ORDER BY id", [],
                'Kassen (Stammdaten): Kassen-Nr., RKSV-Kassen-ID, Umsatzzähler'],
            'rksv_registrierungen' => ["SELECT id, kasse_id, rksv_kassen_id, bfr_url, uid_nummer, vertrauensdiensteanbieter,
                    zertifikat_seriennr_dez, zertifikat_seriennr_hex, zertifikat_gemeldet_am, zertifikat_gueltig_bis,
                    kasse_gemeldet_am, startbeleg_geprueft_am, startbeleg_inhalt, abgeschlossen_am, benutzer_id, erstellt_am
                FROM bfr_kassen_registrierungen WHERE kasse_id IN ($kIds) ORDER BY id", [],
                'RKSV-Registrierungen der Kassen (Protokoll der FinanzOnline-Meldung)'],
            'bons' => ["SELECT * FROM kassen_bons WHERE kasse_id IN ($kIds) AND erstellt_am BETWEEN :von AND :bis ORDER BY kasse_id, id", $p,
                'Belege (Verkauf, Storno, Zahlbelege, Z-/X-Bon-Belege) mit RKSV-Signatur, Steuergruppen A–E, Zahlungsaufteilung'],
            'bon_positionen' => ["SELECT bp.*, a.artikelnummer AS artikelnummer
                FROM kassen_bon_positionen bp
                LEFT JOIN artikel a ON a.id = bp.artikel_id
                WHERE bp.bon_id IN ($bonFilter) ORDER BY bp.bon_id, bp.sort_order, bp.id", $p,
                'Belegpositionen (Bezeichnung, EAN, Artikelnummer, Menge, Einzelpreis brutto, Rabatt, Steuersatz, Charge)'],
            'bon_auftraege' => ["SELECT * FROM kassen_bon_auftraege WHERE bon_id IN ($bonFilter) ORDER BY bon_id, id", $p,
                'Verknüpfung Beleg ↔ Auftrag (Abholung/Zahlung offener Aufträge an der Kasse)'],
            'kassenbuch' => ["SELECT * FROM kassenbuch WHERE kasse_id IN ($kIds) AND erstellt_am BETWEEN :von AND :bis ORDER BY kasse_id, id", $p,
                'Kassenbuch: Bareinlagen, Entnahmen, Bargeldbewegungen je Beleg'],
            'abschluesse' => ["SELECT * FROM kassen_abschluesse WHERE kasse_id IN ($kIds) AND datum BETWEEN :von AND :bis ORDER BY kasse_id, id", $pDat,
                'Tagesabschlüsse (Z-Bon) und Zwischenberichte (X-Bon); Spalte daten = vollständige Abschlusswerte als JSON'],
            'nullbelege' => ["SELECT * FROM bfr_nullbelege WHERE kasse_id IN ($kIds) AND erstellt_am BETWEEN :von AND :bis ORDER BY kasse_id, id", $p,
                'RKSV-Nullbelege (Monat/manuell) mit Signatur'],
            'rksv_ausfaelle' => ["SELECT * FROM bfr_ausfaelle WHERE kasse_id IN ($kIds)
                    AND erste_erkennung_am <= :bis AND COALESCE(geloest_am, NOW()) >= :von ORDER BY kasse_id, id", $p,
                'Ausfälle der Signatureinrichtung (Episoden)'],
            'rksv_ausfall_ereignisse' => ["SELECT e.* FROM bfr_ausfall_ereignisse e JOIN bfr_ausfaelle f ON f.id = e.ausfall_id
                WHERE f.kasse_id IN ($kIds) AND f.erste_erkennung_am <= :bis AND COALESCE(f.geloest_am, NOW()) >= :von
                ORDER BY e.ausfall_id, e.id", $p,
                'Einzelne Ereignisse je Ausfall (betroffene Belegnummern)'],
            'messen' => ["SELECT * FROM kassen_messe_sync WHERE kasse_id IN ($kIds) AND erstellt_am BETWEEN :von AND :bis ORDER BY id", $p,
                'Messe-Einsätze (Offline-Kasse / Papier-Messe mit nacherfassten Handbelegen)'],
            'artikelgruppen' => ["SELECT id, konto_nr, name, standard_steuer_prozent, aktiv FROM artikel_gruppen ORDER BY id", [],
                'Artikelgruppen/Warengruppen mit Erlöskonto (Schlüssel artikel_gruppe_id)'],
            'benutzer' => ["SELECT id, username, formularname, aktiv FROM benutzer ORDER BY id", [],
                'Benutzer/Kassierer (Schlüssel benutzer_id, kassierer_id) — ohne Passwörter'],
            'partner' => ["SELECT id, name, typ, uid_nummer, kleinunternehmer FROM partner ORDER BY id", [],
                'Partner (Fremdrechnung/Kommission/Spende, Schlüssel partner_id)'],
        ];

        $tmp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'pruefexport_' . bin2hex(random_bytes(6));
        if (!mkdir($tmp, 0700, true)) {
            return ['erfolg' => false, 'fehler' => 'Temporärer Ordner konnte nicht angelegt werden.'];
        }

        $uebersicht = [];
        $hinweise   = [];
        try {
            foreach ($tabellen as $name => [$sql, $param, $beschreibung]) {
                $datei = $name . '.csv';
                $anzahl = $this->schreibeCsv($sql, $param, $tmp . DIRECTORY_SEPARATOR . $datei);
                $uebersicht[] = [$datei, $anzahl, hash_file('sha256', $tmp . DIRECTORY_SEPARATOR . $datei), $beschreibung];
            }

            if ($mitDep) {
                $bfr = new BfrService();
                foreach ($kassen as $k) {
                    if (empty($k['bfr_url']) || empty($k['bfr_aktiv_seit'])) continue;
                    $dep = $bfr->holeDepExport((int)$k['id'], $benutzerId);
                    if ($dep['erfolg']) {
                        file_put_contents($tmp . DIRECTORY_SEPARATOR . $dep['dateiname'], $dep['daten']);
                        $uebersicht[] = [$dep['dateiname'], null, hash('sha256', $dep['daten']),
                            'DEP (RKSV § 7) von Kasse ' . $k['kasse_nr'] . ', direkt vom BFR, kompletter Zeitraum seit Inbetriebnahme'];
                    } else {
                        $hinweise[] = 'DEP von Kasse ' . $k['kasse_nr'] . ' fehlt: ' . $dep['fehler'];
                    }
                }
            }

            file_put_contents($tmp . DIRECTORY_SEPARATOR . 'LIESMICH.txt',
                $this->liesmich($von, $bis, $kassen, $uebersicht, $hinweise, $benutzerId));

            $kNr       = $kasseId ? preg_replace('/[^A-Za-z0-9_-]/', '_', $kassen[0]['kasse_nr']) : 'alle';
            $dateiname = 'Kassendaten_' . $kNr . '_' . $von . '_bis_' . $bis . '.zip';
            $zipPfad   = $tmp . '.zip';
            $zip = new PharData($zipPfad);
            foreach (scandir($tmp) as $f) {
                if ($f[0] !== '.') $zip->addFile($tmp . DIRECTORY_SEPARATOR . $f, $f);
            }
            unset($zip);
        } catch (Throwable $e) {
            $this->aufraeumen($tmp);
            return ['erfolg' => false, 'fehler' => 'Export fehlgeschlagen: ' . $e->getMessage()];
        }
        $this->aufraeumen($tmp);

        Logger::log('kasse.rksv.pruefexport', 'kassen', $kasseId, [
            'von' => $von, 'bis' => $bis, 'kassen' => array_column($kassen, 'kasse_nr'),
            'mit_dep' => $mitDep, 'dateien' => array_map(fn($u) => $u[0] . ' (' . ($u[1] ?? '-') . ')', $uebersicht),
        ], $benutzerId);

        return ['erfolg' => true, 'pfad' => $zipPfad, 'dateiname' => $dateiname, 'hinweise' => $hinweise];
    }

    /** Kassen für die Auswahl im Formular (alle, auch inaktive — die Prüfung kann auch alte Kassen betreffen). */
    public function alleKassen(): array
    {
        return $this->db->query("SELECT id, name, kasse_nr, aktiv FROM kassen ORDER BY kasse_nr")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function kassen(?int $kasseId): array
    {
        if ($kasseId) {
            $st = $this->db->prepare("SELECT * FROM kassen WHERE id = ?");
            $st->execute([$kasseId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->db->query("SELECT * FROM kassen ORDER BY kasse_nr")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Schreibt das Abfrageergebnis zeilenweise (wenig Speicher) als TAB-CSV mit Kopfzeile. */
    private function schreibeCsv(string $sql, array $param, string $pfad): int
    {
        $st = $this->db->prepare($sql);
        $st->execute($param);
        $fh = fopen($pfad, 'w');
        fwrite($fh, "\xEF\xBB\xBF");   // BOM, damit Excel UTF-8 erkennt
        $anzahl = 0;
        $kopf   = false;
        $weg = array_flip(self::OHNE_SPALTEN);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $row = array_diff_key($row, $weg);
            if (!$kopf) {
                fputcsv($fh, array_keys($row), "\t", '"', '');
                $kopf = true;
            }
            fputcsv($fh, array_map(fn($v) => $v === null ? '' : (string)$v, $row), "\t", '"', '');
            $anzahl++;
        }
        if (!$kopf) {
            // Leere Tabelle: trotzdem Spaltennamen ausgeben
            $spalten = [];
            for ($i = 0; $i < $st->columnCount(); $i++) {
                $spalten[] = $st->getColumnMeta($i)['name'] ?? ('spalte' . $i);
            }
            $spalten = array_values(array_diff($spalten, self::OHNE_SPALTEN));
            fputcsv($fh, $spalten, "\t", '"', '');
        }
        fclose($fh);
        return $anzahl;
    }

    private function liesmich(string $von, string $bis, array $kassen, array $uebersicht, array $hinweise, int $benutzerId): string
    {
        $e = $this->db->query("SELECT schluessel, wert FROM system_einstellungen
                               WHERE schluessel IN ('firmenname','strasse','plz','ort','uid_nummer','steuernummer')")
                      ->fetchAll(PDO::FETCH_KEY_PAIR);
        $st = $this->db->prepare("SELECT formularname FROM benutzer WHERE id = ?");
        $st->execute([$benutzerId]);
        $wer = (string)$st->fetchColumn();
        $d = fn($x) => date('d.m.Y', strtotime($x));

        $t  = "DATENEXPORT KASSE FÜR DIE BETRIEBSPRÜFUNG (§ 131 BAO)\r\n";
        $t .= str_repeat('=', 54) . "\r\n\r\n";
        $t .= 'Unternehmen:   ' . trim(($e['firmenname'] ?? '') . ', ' . ($e['strasse'] ?? '') . ', ' . ($e['plz'] ?? '') . ' ' . ($e['ort'] ?? ''), ', ') . "\r\n";
        if (!empty($e['uid_nummer']))   $t .= 'UID-Nummer:    ' . $e['uid_nummer'] . "\r\n";
        if (!empty($e['steuernummer'])) $t .= 'Steuernummer:  ' . $e['steuernummer'] . "\r\n";
        $t .= 'Zeitraum:      ' . $d($von) . ' bis ' . $d($bis) . "\r\n";
        $t .= 'Kassen:        ' . implode(', ', array_map(fn($k) => $k['kasse_nr'] . ' ' . $k['name']
                  . ($k['rksv_kassen_id'] ? ' (RKSV-Kassen-ID ' . $k['rksv_kassen_id'] . ')' : ''), $kassen)) . "\r\n";
        $t .= 'Erstellt:      ' . date('d.m.Y H:i:s') . ' von ' . $wer . "\r\n";
        $t .= 'Software:      NAHTLOS ' . (defined('APP_VERSION') ? APP_VERSION : '') . "\r\n\r\n";

        $t .= "FORMAT\r\n------\r\n";
        $t .= "- Eine CSV-Datei je Tabelle, Zeichensatz UTF-8 (mit BOM), Trennzeichen TAB, erste Zeile = Spaltennamen.\r\n";
        $t .= "- Werte unverändert aus der Datenbank: Dezimalpunkt, Beträge in EUR brutto (wo nicht anders benannt),\r\n";
        $t .= "  Datum/Zeit JJJJ-MM-TT hh:mm:ss, leeres Feld = kein Wert. Texte mit TAB/Zeilenumbruch stehen in \"…\".\r\n";
        $t .= "- Schlüsselfelder: kasse_id → kassen.csv, bon_id → bons.csv, benutzer_id/kassierer_id → benutzer.csv,\r\n";
        $t .= "  artikel_gruppe_id → artikelgruppen.csv, partner_id → partner.csv.\r\n";
        $t .= "- Steuergruppen RKSV: A = 20 %, B = 10 %, C = 13 %, D = 0 %, E = Sondersteuersatz.\r\n";
        $t .= "- Artikelstammdaten sind nicht enthalten; Bezeichnung, EAN und Artikelnummer stehen je Belegposition.\r\n";
        $t .= "- Das DEP (Datenerfassungsprotokoll nach RKSV § 7) kommt direkt von der Signatureinrichtung (BFR)\r\n";
        $t .= "  und umfasst immer den gesamten Zeitraum seit der Inbetriebnahme der Kasse.\r\n\r\n";

        $t .= "DATEIEN\r\n-------\r\n";
        foreach ($uebersicht as [$datei, $anzahl, $hash, $beschreibung]) {
            $t .= $datei . ($anzahl !== null ? ' (' . $anzahl . ' Zeilen)' : '') . "\r\n";
            $t .= '  ' . $beschreibung . "\r\n";
            $t .= '  SHA-256: ' . $hash . "\r\n";
        }
        if ($hinweise) {
            $t .= "\r\nHINWEISE\r\n--------\r\n- " . implode("\r\n- ", $hinweise) . "\r\n";
        }
        return $t;
    }

    private function aufraeumen(string $ordner): void
    {
        foreach (glob($ordner . DIRECTORY_SEPARATOR . '*') ?: [] as $f) @unlink($f);
        @rmdir($ordner);
    }
}
