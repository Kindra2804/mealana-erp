<?php

/**
 * create_admin.php – legt interaktiv einen Admin-Benutzer an
 *
 * Ersetzt das manuelle Zusammenbauen eines INSERT-Statements samt
 * password_hash()-Aufruf bei einer Neuinstallation. Weist dem neuen
 * Benutzer außerdem die Rolle "superadmin" zu (Migration 005 seedet
 * diese Rolle bereits, auch wenn die Rechteprüfung selbst im Code
 * noch nicht ausgewertet wird — siehe docs/installation.md Anhang B).
 *
 * Aufruf: php create_admin.php
 */

$config = require __DIR__ . '/../config/database.php';
$dsn    = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
$pdo    = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "Benutzername: ";
$username = trim(fgets(STDIN));

$stmt = $pdo->prepare("SELECT id FROM benutzer WHERE username = :u");
$stmt->execute(['u' => $username]);
if ($stmt->fetchColumn()) {
    fwrite(STDERR, "Benutzer '$username' existiert bereits. Abbruch.\n");
    exit(1);
}

echo "Anzeigename (z.B. \"Max Mustermann\"): ";
$formularname = trim(fgets(STDIN));

echo "Passwort: ";
$passwort = trim(fgets(STDIN));

if ($username === '' || $formularname === '' || $passwort === '') {
    fwrite(STDERR, "Alle drei Angaben sind Pflicht. Abbruch.\n");
    exit(1);
}

$hash = password_hash($passwort, PASSWORD_DEFAULT);

$pdo->prepare("
    INSERT INTO benutzer (username, passwort, formularname, aktiv)
    VALUES (:u, :p, :f, 1)
")->execute(['u' => $username, 'p' => $hash, 'f' => $formularname]);

$benutzerId = (int) $pdo->lastInsertId();

$rolleId = $pdo->query("SELECT id FROM rollen WHERE name = 'superadmin'")->fetchColumn();
if ($rolleId) {
    $pdo->prepare("INSERT INTO benutzer_rollen (benutzer_id, rolle_id) VALUES (:b, :r)")
        ->execute(['b' => $benutzerId, 'r' => (int)$rolleId]);
}

echo "Benutzer '$username' angelegt (id=$benutzerId)" . ($rolleId ? ", Rolle superadmin zugewiesen." : ".") . "\n";
