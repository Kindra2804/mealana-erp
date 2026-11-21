<?php

require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/Demo.php';
require_once __DIR__ . '/../../src/core/Migrator.php';

/**
 * Hilfen für den Demo-Generator (scripts/demo/demo_erzeugen.php): Datenbank neu aufsetzen,
 * Zufall mit festem Startwert, Datum relativ zu heute, kurze Insert-Funktion.
 *
 * Grundsatz: Alle Daten sind erfunden. Datumswerte immer relativ zu HEUTE erzeugen,
 * damit die Demo nach dem nächtlichen Neuaufbau aktuell aussieht.
 */
class DemoBasis
{
    public PDO $db;
    public int $jarvisId = 0;
    public int $demoBenutzerId = 0;
    /** Gemerkte IDs für die nächsten Teile (Lager, Shops, Kategorien, Artikel …) */
    public array $ids = [];

    public function __construct(int $startwert)
    {
        mt_srand($startwert);
        $this->db = Database::getInstance();
    }

    public function log(string $text): void
    {
        echo '[' . date('H:i:s') . '] ' . $text . PHP_EOL;
    }

    // ── Datenbank + Dateien neu ───────────────────────────────────────────

    /** Alle Tabellen löschen, Grundschema + Migrationen einspielen, Upload-/Beleg-Ordner leeren. */
    public function neuAufsetzen(): void
    {
        $c = require __DIR__ . '/../../config/database.php';
        $pdo = new PDO("mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}",
            $c['username'], $c['password'] ?? '', [
                PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ]);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$name, $typ]) {
            $pdo->exec(($typ === 'VIEW' ? 'DROP VIEW ' : 'DROP TABLE ') . '`' . str_replace('`', '', $name) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $this->log('Tabellen gelöscht');

        $st = $pdo->query(file_get_contents(__DIR__ . '/../../database/baseline_schema.sql'));
        do { /* alle Teil-Ergebnisse abholen, damit Fehler auch in späteren Befehlen auffallen */ } while ($st->nextRowset());
        $st->closeCursor();
        $this->log('Grundschema eingespielt');

        $erg = (new Migrator())->ausfuehren();
        if (!$erg['erfolg']) {
            throw new RuntimeException('Migration ' . $erg['fehler_datei'] . ' fehlgeschlagen: ' . $erg['fehler']);
        }
        $this->log(count($erg['angewendet']) . ' Migrationen eingespielt');

        $wurzel = realpath(__DIR__ . '/../..');
        foreach (['public/uploads/artikel', 'public/uploads/kategorien', 'public/uploads/slider', 'public/uploads/newsletter',
                  'storage/dokumente', 'storage/bons', 'storage/picklisten', 'storage/partner_belege', 'public/img/logos'] as $ordner) {
            $this->ordnerLeeren($wurzel . '/' . $ordner);
        }
        $this->log('Upload- und Belegordner geleert');

        $this->jarvisId = (int)$this->db->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn();
    }

    private function ordnerLeeren(string $pfad): void
    {
        if (!is_dir($pfad)) { @mkdir($pfad, 0775, true); return; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pfad, FilesystemIterator::SKIP_DOTS),
                                            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            if ($f->getFilename() === '.gitkeep') continue;
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
    }

    // ── Schreiben ─────────────────────────────────────────────────────────

    /** INSERT mit benannten Parametern, liefert die neue ID */
    public function ins(string $tabelle, array $werte): int
    {
        $spalten = array_keys($werte);
        $schluessel = $tabelle . '|' . implode(',', $spalten);
        // vorbereitete Befehle wiederverwenden (Teil 3 schreibt zehntausende Zeilen)
        $st = $this->befehle[$schluessel] ??= $this->db->prepare(
            'INSERT INTO `' . $tabelle . '` (`' . implode('`, `', $spalten) . '`) VALUES (:' . implode(', :', $spalten) . ')');
        $st->execute($werte);
        return (int)$this->db->lastInsertId();
    }

    private array $befehle = [];

    public function einstellung(string $schluessel, string $wert): void
    {
        $this->db->prepare("INSERT INTO system_einstellungen (schluessel, wert) VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE wert = VALUES(wert)")->execute([$schluessel, $wert]);
    }

    // ── Zufall + Datum ────────────────────────────────────────────────────

    public function zufall(int $min, int $max): int { return mt_rand($min, $max); }

    /** true mit Wahrscheinlichkeit $prozent */
    public function chance(float $prozent): bool { return mt_rand(0, 9999) < $prozent * 100; }

    public function eins(array $liste): mixed { return $liste[array_rand($liste)]; }

    /** Gewichtete Auswahl: ['a' => 70, 'b' => 30] */
    public function gewichtet(array $gewichte): string|int
    {
        $summe = array_sum($gewichte);
        $wurf = mt_rand(1, max(1, $summe));
        foreach ($gewichte as $wert => $g) { if (($wurf -= $g) <= 0) return $wert; }
        return array_key_first($gewichte);
    }

    /** Datum/Zeit vor $tage Tagen (0 = heute), Uhrzeit zufällig in Geschäftszeiten */
    public function vorTagen(int $tage, int $vonStunde = 8, int $bisStunde = 20): string
    {
        $t = strtotime('today') - $tage * 86400 + mt_rand($vonStunde * 3600, $bisStunde * 3600);
        return date('Y-m-d H:i:s', min($t, time() - 60));
    }
}
