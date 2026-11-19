<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/JtlCsvStream.php';
require_once __DIR__ . '/JtlArchivImportService.php';

/**
 * JTL-Kunden- und Archiv-Import im Browser (Kunden → ⬇ JTL-Import, Migration 257).
 *
 * Ablauf je Datei („Lauf“): stückweise hochladen → Kopfzeile prüfen → Probelauf → Import.
 * Probelauf und Import arbeiten in Abschnitten (STAPEL), die Datei wird ab der gemerkten
 * Byte-Stelle weitergelesen (JtlCsvStream). So reichen Standard-Speicher und -Zeitlimit auch
 * für den 90-MB-Voll-Export, und ein abgebrochener Import läuft beim nächsten Klick weiter.
 * Die eigentliche Verarbeitung macht JtlArchivImportService (dieselbe wie die CLI-Skripte).
 *
 * Unbezahlte Archiv-Aufträge jünger als 365 Tage landen in jtl_import_offen → Prüfliste.
 */
class JtlArchivLaufService
{
    public const STAPEL = ['kunden' => 400, 'auftraege' => 250];
    /** Spalten, an denen die Dateiart erkannt wird */
    private const PFLICHT = [
        'kunden'    => ['Kundennummer', 'E-Mail-Adresse', 'Nachname'],
        'auftraege' => ['Auftragsnummer', 'Kundennummer', 'Menge', 'Netto-VK'],
    ];
    private const MAX_FEHLER = 300;

    private PDO $db;
    private string $ordner;

    public function __construct()
    {
        $this->db     = Database::getInstance();
        $this->ordner = __DIR__ . '/../../../storage/jtl_import';
    }

    /** Neuen Lauf anlegen (vor dem Hochladen) */
    public function neu(string $art, string $dateiname, ?int $benutzerId): int
    {
        if (!isset(self::PFLICHT[$art])) throw new InvalidArgumentException('Unbekannte Import-Art.');
        if (!is_dir($this->ordner)) mkdir($this->ordner, 0775, true);
        $this->db->prepare("INSERT INTO jtl_import_laeufe (art, datei, dateiname, benutzer_id) VALUES (?, '', ?, ?)")
                 ->execute([$art, mb_substr(basename($dateiname), 0, 255), $benutzerId]);
        $id = (int) $this->db->lastInsertId();
        $datei = "jtl_{$art}_{$id}_" . date('Ymd_His') . '.csv';
        $this->db->prepare('UPDATE jtl_import_laeufe SET datei = ? WHERE id = ?')->execute([$datei, $id]);
        return $id;
    }

    /** Ein Stück der Datei anhängen (Rohdaten, max. ein paar MB je Aufruf) */
    public function teil(int $id, string $daten): void
    {
        $l = $this->lauf($id);
        if ($l['status'] !== 'hochgeladen') throw new RuntimeException('Dieser Lauf ist schon hochgeladen.');
        if (file_put_contents($this->pfad($l), $daten, FILE_APPEND) === false) throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }

    /** Hochladen abgeschlossen: Kopfzeile prüfen, Größe merken */
    public function hochgeladen(int $id): array
    {
        $l = $this->lauf($id);
        $stream = new JtlCsvStream($this->pfad($l));
        $fehlt = array_diff(self::PFLICHT[$l['art']], $stream->kopf());
        if ($fehlt) {
            $this->loeschen($id);
            $andere = $l['art'] === 'kunden' ? 'auftraege' : 'kunden';
            $hinweis = !array_diff(self::PFLICHT[$andere], $stream->kopf())
                ? ' Das ist ein ' . ($andere === 'kunden' ? 'Kundendaten' : 'Auftrags') . '-Export – bitte im anderen Feld hochladen.' : '';
            throw new RuntimeException('Spalten fehlen: ' . implode(', ', $fehlt) . '.' . $hinweis);
        }
        $this->db->prepare('UPDATE jtl_import_laeufe SET groesse = ? WHERE id = ?')->execute([$stream->groesse(), $id]);
        return $this->lauf($id);
    }

    /**
     * Einen Abschnitt verarbeiten. $neuStart = true beginnt am Dateianfang (Zahlen zurückgesetzt).
     * @return array{position:int, groesse:int, fertig:bool, zahlen:array, fehler_neu:string[]}
     */
    public function abschnitt(int $id, bool $probelauf, bool $neuStart): array
    {
        @set_time_limit(300);
        $l = $this->lauf($id);
        if ($l['status'] === 'fertig') throw new RuntimeException('Dieser Import ist schon fertig.');
        if (!$probelauf && $l['art'] === 'auftraege' && $neuStart && $l['status'] !== 'fertig_probelauf' && $l['status'] !== 'import') {
            throw new RuntimeException('Bitte zuerst den Probelauf machen.');
        }
        $modus = $probelauf ? 'probelauf' : 'import';
        if ($neuStart || $l['status'] !== $modus) {
            if ($l['status'] === 'import' && $probelauf) throw new RuntimeException('Der Import läuft schon – bitte „Import fortsetzen“.');
            $l['position'] = 0;
            $l['zahlen'] = '{}';
            $l['fehler'] = '[]';
            $this->db->prepare("UPDATE jtl_import_laeufe SET status = ?, position = 0, zahlen = '{}', fehler = '[]' WHERE id = ?")->execute([$modus, $id]);
        }

        $stream = new JtlCsvStream($this->pfad($l));
        $svc = new JtlArchivImportService();
        if (!$probelauf) $this->db->beginTransaction();
        try {
            if ($l['art'] === 'kunden') {
                [$zeilen, $position, $ende] = $stream->leseZeilen((int) $l['position'], self::STAPEL['kunden']);
                $erg = $svc->kundenZeilen($zeilen, $probelauf);
            } else {
                [$gruppen, $position, $ende] = $stream->leseGruppen((int) $l['position'], self::STAPEL['auftraege'], 'Auftragsnummer');
                $erg = $svc->auftragGruppen($gruppen, $probelauf);
                if (!$probelauf && $erg['offen_ids']) {
                    $ins = $this->db->prepare('INSERT IGNORE INTO jtl_import_offen (lauf_id, auftrag_id) VALUES (?, ?)');
                    foreach ($erg['offen_ids'] as $aid) $ins->execute([$id, $aid]);
                }
                unset($erg['offen_ids']);
            }

            $zahlen = json_decode((string) $l['zahlen'], true) ?: [];
            foreach ($erg as $k => $v) if (is_int($v)) $zahlen[$k] = ($zahlen[$k] ?? 0) + $v;
            $fehler = json_decode((string) $l['fehler'], true) ?: [];
            $fehlerNeu = $erg['fehler'] ?? [];
            $zahlen['fehler'] = ($zahlen['fehler'] ?? 0) + count($fehlerNeu);
            $fehler = array_slice(array_merge($fehler, $fehlerNeu), 0, self::MAX_FEHLER);

            $status = $ende ? ($probelauf ? 'fertig_probelauf' : 'fertig') : $modus;
            $this->db->prepare('UPDATE jtl_import_laeufe SET status = ?, position = ?, zahlen = ?, fehler = ? WHERE id = ?')
                     ->execute([$status, $position, json_encode($zahlen), json_encode($fehler, JSON_UNESCAPED_UNICODE), $id]);
            if (!$probelauf) $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        if ($ende && !$probelauf) {
            Logger::log('jtl_import.lauf_fertig', 'jtl_import_laeufe', $id, ['art' => $l['art'], 'datei' => $l['dateiname'], 'zahlen' => $zahlen]);
            @unlink($this->pfad($l));   // Kundendaten nicht länger als nötig liegen lassen
        }
        return ['position' => $position, 'groesse' => (int) $l['groesse'], 'fertig' => $ende, 'zahlen' => $zahlen, 'fehler_neu' => $fehlerNeu];
    }

    /** Noch nicht durchgesehene offene Archiv-Aufträge (alle Läufe) */
    public function offene(): array
    {
        return $this->db->query("
            SELECT o.lauf_id, a.id, a.auftrag_nr, a.erstellt_am, a.bruttobetrag, a.zahlungsart, a.kunden_snapshot, a.zahlungsstatus
            FROM jtl_import_offen o JOIN auftraege a ON a.id = o.auftrag_id
            WHERE o.entscheidung IS NULL
            ORDER BY a.erstellt_am")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prüfliste speichern: $erledigt = angehakte Auftrags-IDs → bezahlt, die übrigen aus $alle bleiben offen.
     * @return array{bezahlt:int, offen:int}
     */
    public function offeneEntscheiden(array $alle, array $erledigt): array
    {
        $alle = array_values(array_unique(array_map('intval', $alle)));
        $erledigt = array_flip(array_map('intval', $erledigt));
        $bezahlt = $offen = 0;
        $setze = $this->db->prepare("UPDATE auftraege SET zahlungsstatus = 'bezahlt', bezahlt_am = COALESCE(bezahlt_am, erstellt_am)
                                     WHERE id = ? AND kanal = 'jtl_archiv' AND zahlungsstatus = 'ausstehend'");
        $merke = $this->db->prepare("UPDATE jtl_import_offen SET entscheidung = ?, entschieden_am = NOW() WHERE auftrag_id = ? AND entscheidung IS NULL");
        $this->db->beginTransaction();
        foreach ($alle as $aid) {
            if (isset($erledigt[$aid])) {
                $setze->execute([$aid]);
                $merke->execute(['bezahlt', $aid]);
                $bezahlt++;
            } else {
                $merke->execute(['offen', $aid]);
                $offen++;
            }
        }
        $this->db->commit();
        Logger::log('jtl_import.offene_geprueft', null, null, ['bezahlt' => $bezahlt, 'bleiben_offen' => $offen]);
        return ['bezahlt' => $bezahlt, 'offen' => $offen];
    }

    /** Letzte Läufe für die Übersicht */
    public function laeufe(int $anzahl = 10): array
    {
        $st = $this->db->prepare("SELECT l.*, b.formularname AS benutzer FROM jtl_import_laeufe l
                                  LEFT JOIN benutzer b ON b.id = l.benutzer_id ORDER BY l.id DESC LIMIT " . (int) $anzahl);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lauf(int $id): array
    {
        $st = $this->db->prepare('SELECT * FROM jtl_import_laeufe WHERE id = ?');
        $st->execute([$id]);
        $l = $st->fetch(PDO::FETCH_ASSOC);
        if (!$l) throw new RuntimeException('Import-Lauf nicht gefunden.');
        return $l;
    }

    /** Lauf samt Datei verwerfen (nur, solange nichts importiert wurde) */
    public function loeschen(int $id): void
    {
        $l = $this->lauf($id);
        if (in_array($l['status'], ['import', 'fertig'], true)) throw new RuntimeException('Ein gestarteter Import kann nicht verworfen werden.');
        @unlink($this->pfad($l));
        $this->db->prepare('DELETE FROM jtl_import_laeufe WHERE id = ?')->execute([$id]);
    }

    private function pfad(array $l): string
    {
        return $this->ordner . '/' . basename($l['datei']);
    }
}
