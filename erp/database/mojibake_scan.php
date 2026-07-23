<?php

/**
 * mojibake_scan.php – durchsucht alle Text-Spalten der DB nach bekannten
 * Mojibake-Mustern (CP850-Fehlkodierung, siehe .claude/memory/project_infrastruktur.md).
 *
 * Reiner Scan, kein Auto-Fix: Terminal-Ausgabe von Umlauten ist nicht vertrauenswürdig
 * (hat beim Umzug am 2026-07-17 zweimal getäuscht), deshalb wird zu jedem Treffer
 * zusätzlich HEX() ausgegeben — das ist die einzige verlässliche Bestätigung.
 *
 * Aufruf: php mojibake_scan.php
 */

$config = require __DIR__ . '/../config/database.php';
$dsn    = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
$pdo    = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Bekannte Mojibake-Marker (siehe Memory für die Herleitung):
//   ├   -> CP850-Fehlkodierung von Umlauten (ä ö ü ß), Fund vom PC-Umzug 2026-07-17
//   ÔÇ  -> CP850-Fehlkodierung von Sonderzeichen die mit E2 80 beginnen (– " € u.ä.)
//   Ã¤ / Ã¶ / Ã¼ / Ã\x9F -> klassische Latin-1-Doppelkodierung (anderer Fehlerkanal, z.B. falscher Editor/Import)
$marker = ['├', 'ÔÇ', 'Ã¤', 'Ã¶', 'Ã¼', "Ã\x9F"];

$spalten = $pdo->prepare("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = :db
      AND DATA_TYPE IN ('varchar','char','text','tinytext','mediumtext','longtext')
    ORDER BY TABLE_NAME, ORDINAL_POSITION
");
$spalten->execute(['db' => $config['dbname']]);
$spalten = $spalten->fetchAll();

$gefundenGesamt = 0;

foreach ($spalten as $sp) {
    $table  = $sp['TABLE_NAME'];
    $column = $sp['COLUMN_NAME'];

    $pkStmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND CONSTRAINT_NAME = 'PRIMARY'
        LIMIT 1
    ");
    $pkStmt->execute(['db' => $config['dbname'], 't' => $table]);
    $pk = $pkStmt->fetchColumn();
    if (!$pk) {
        continue; // Tabelle ohne Primärschlüssel — überspringen, kann nicht referenziert werden
    }

    // LIKE BINARY statt LIKE: normale ci-Collations werten Umlaute/Sonderzeichen als
    // "gleich" zu Buchstabenkombinationen (echter Fund beim Testen: 'inventur.abgebrochen'
    // matchte fälschlich 'ÔÇ' unter utf8mb4_*_ci) — hier zählt nur der exakte Byte-Vergleich.
    $bedingungen = [];
    foreach (array_keys($marker) as $i) {
        $bedingungen[] = "`{$column}` LIKE BINARY :m{$i}";
    }
    $sql = "SELECT `{$pk}` AS pk_wert, `{$column}` AS wert, HEX(`{$column}`) AS wert_hex
            FROM `{$table}` WHERE " . implode(' OR ', $bedingungen) . " LIMIT 50";
    $stmt = $pdo->prepare($sql);
    foreach ($marker as $i => $m) {
        $stmt->bindValue(":m{$i}", '%' . $m . '%');
    }
    $stmt->execute();
    $treffer = $stmt->fetchAll();

    foreach ($treffer as $row) {
        $gefundenGesamt++;
        echo "{$table}.{$column} (id={$row['pk_wert']}): {$row['wert']}\n";
        echo "  HEX: {$row['wert_hex']}\n";
    }
}

if ($gefundenGesamt === 0) {
    echo "Keine bekannten Mojibake-Muster gefunden.\n";
    exit(0);
}

echo "\n{$gefundenGesamt} verdächtige Zeile(n) gefunden. Vor der Korrektur:\n";
echo "  1. HEX() oben mit einer bekannt korrekten Zeile vergleichen (nie der Terminal-Darstellung trauen)\n";
echo "  2. Backup ziehen\n";
echo "  3. Korrektur gezielt per UPDATE (z.B. iconv('UTF-8','CP850', \$text) in einem kleinen PHP-Skript)\n";
exit(1);
