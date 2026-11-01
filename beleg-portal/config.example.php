<?php
// Belegseite (Paperless) — Konfiguration. Als config.php speichern und anpassen.
// config.php NIE öffentlich machen (siehe .htaccess) und nicht ins Repo.
return [
    // Gleicher Schlüssel wie im ERP unter Einstellungen → System → Paperless (lang + zufällig!)
    'api_schluessel' => 'HIER-EINEN-LANGEN-ZUFALLSWERT-EINTRAGEN',

    // Datenbank: entweder SQLite (nichts einzurichten) …
    'db' => ['typ' => 'sqlite', 'datei' => __DIR__ . '/daten/belege.sqlite'],
    // … oder die vorhandene WordPress-Datenbank mit eigenem Tabellen-Präfix:
    // 'db' => ['typ' => 'mysql', 'host' => 'localhost', 'name' => 'wp_datenbank', 'benutzer' => '…', 'passwort' => '…', 'praefix' => 'nahtlos_'],

    // PDF-Ablage (muss beschreibbar sein; daten/ ist per .htaccess gesperrt)
    'ordner' => __DIR__ . '/daten',

    // Aufbewahrung: nicht abgerufen → nach X Stunden löschen, abgerufen → nach X Tagen
    'unabgerufen_stunden' => 24,
    'abgerufen_tage'      => 30,
];
