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

require_once __DIR__ . '/../src/core/Migrator.php';

$command  = $argv[1] ?? 'run';
$migrator = new Migrator();
$offen    = $migrator->offen();

if ($command === 'status') {
    echo count($migrator->angewendet()) . " Migration(en) angewendet, " . count($offen) . " offen.
";
    foreach ($offen as $pfad) {
        echo "  offen: " . basename($pfad) . "
";
    }
    exit(0);
}

if ($command === 'bootstrap') {
    if (empty($offen)) {
        echo "Nichts zu tun — alle Migrationen sind bereits vermerkt.
";
        exit(0);
    }
    echo count($offen) . " Migration(en) werden als 'bereits angewendet' vermerkt, OHNE sie auszuführen.
";
    echo "Nur fortfahren, wenn die Datenbank nachweislich schon exakt diesen Stand hat!
";
    echo "Fortfahren? (j/n): ";
    if (strtolower(trim(fgets(STDIN))) !== 'j') {
        echo "Abgebrochen.
";
        exit(1);
    }
    foreach ($migrator->alsAngewendetVermerken() as $name) {
        echo "  vermerkt: $name
";
    }
    echo "Fertig.
";
    exit(0);
}

if (empty($offen)) {
    echo "Alles aktuell — keine offenen Migrationen.
";
    exit(0);
}

echo count($offen) . " offene Migration(en) werden angewendet:
";
$erg = $migrator->ausfuehren(function (string $name, ?string $fehler) {
    echo "  -> $name ... " . ($fehler === null ? "OK" : "FEHLER") . "
";
});
if (!$erg['erfolg']) {
    fwrite(STDERR, "Migration '{$erg['fehler_datei']}' fehlgeschlagen: {$erg['fehler']}
");
    fwrite(STDERR, "Abbruch. Bereits erfolgreich angewendete Migrationen bleiben vermerkt; '{$erg['fehler_datei']}' wurde NICHT vermerkt.
");
    exit(1);
}
echo "Fertig — alle offenen Migrationen angewendet.
";
