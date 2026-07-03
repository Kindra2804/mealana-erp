<?php

/**
 * migrate.php – Migrations-Runner
 *
 * Wendet alle SQL-Dateien aus database/migrations/ an, die noch nicht in der
 * Tabelle schema_migrations vermerkt sind. Reihenfolge = Dateiname (funktioniert
 * wegen der durchgehend 3-stelligen Nummern-Präfixe 004_...104_...).
 *
 * Aufruf (im Ordner erp/database/):
 *   php migrate.php            → wendet alle offenen Migrationen an
 *   php migrate.php status     → zeigt an was bereits angewendet / offen ist
 *   php migrate.php bootstrap  → trägt ALLE vorhandenen Dateien als "bereits
 *                                 angewendet" ein, OHNE sie auszuführen.
 *                                 Nur verwenden wenn die Datenbank nachweislich
 *                                 schon exakt diesen Stand hat (z.B. eine
 *                                 bestehende Dev-Datenbank, deren Migrationen
 *                                 bisher von Hand eingespielt wurden).
 *
 * Achtung: MySQL committet DDL-Anweisungen (CREATE/ALTER TABLE) sofort und
 * einzeln — eine Transaktion um eine Migrationsdatei schützt NICHT vor einem
 * halb angewendeten Zustand, falls mittendrin ein Fehler auftritt. Bricht eine
 * Datei ab, wird sie NICHT als angewendet vermerkt; das Skript stoppt sofort,
 * damit man den Fehler beheben kann, bevor spätere Migrationen draufbauen.
 */

$migrationsDir = __DIR__ . '/migrations';
$command       = $argv[1] ?? 'run';

$config = require __DIR__ . '/../config/database.php';
$dsn    = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";

$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
]);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        dateiname      VARCHAR(255) NOT NULL,
        angewendet_am  DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_dateiname (dateiname)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$dateien = glob($migrationsDir . '/*.sql');
sort($dateien);

$angewendet    = $pdo->query("SELECT dateiname FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$angewendetSet = array_flip($angewendet);
$offen         = array_values(array_filter($dateien, fn($pfad) => !isset($angewendetSet[basename($pfad)])));

if ($command === 'status') {
    echo count($angewendet) . " Migration(en) angewendet, " . count($offen) . " offen.\n";
    foreach ($offen as $pfad) {
        echo "  offen: " . basename($pfad) . "\n";
    }
    exit(0);
}

if ($command === 'bootstrap') {
    if (empty($offen)) {
        echo "Nichts zu tun — alle Migrationen sind bereits vermerkt.\n";
        exit(0);
    }
    echo count($offen) . " Migration(en) werden als 'bereits angewendet' vermerkt, OHNE sie auszuführen.\n";
    echo "Nur fortfahren, wenn die Datenbank nachweislich schon exakt diesen Stand hat!\n";
    echo "Fortfahren? (j/n): ";
    $antwort = trim(fgets(STDIN));
    if (strtolower($antwort) !== 'j') {
        echo "Abgebrochen.\n";
        exit(1);
    }
    $insertStmt = $pdo->prepare("INSERT INTO schema_migrations (dateiname, angewendet_am) VALUES (:d, NOW())");
    foreach ($offen as $pfad) {
        $insertStmt->execute(['d' => basename($pfad)]);
        echo "  vermerkt: " . basename($pfad) . "\n";
    }
    echo "Fertig.\n";
    exit(0);
}

if (empty($offen)) {
    echo "Alles aktuell — keine offenen Migrationen.\n";
    exit(0);
}

echo count($offen) . " offene Migration(en) werden angewendet:\n";
$insertStmt = $pdo->prepare("INSERT INTO schema_migrations (dateiname, angewendet_am) VALUES (:d, NOW())");

foreach ($offen as $pfad) {
    $name = basename($pfad);
    echo "  -> $name ... ";
    try {
        $pdo->exec(file_get_contents($pfad));
        $insertStmt->execute(['d' => $name]);
        echo "OK\n";
    } catch (PDOException $e) {
        echo "FEHLER\n";
        fwrite(STDERR, "Migration '$name' fehlgeschlagen: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Abbruch. Bereits erfolgreich angewendete Migrationen bleiben vermerkt; '$name' wurde NICHT vermerkt.\n");
        exit(1);
    }
}

echo "Fertig — alle offenen Migrationen angewendet.\n";
