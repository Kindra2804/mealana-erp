<?php
// Generator (Lizenzverwaltung im ERP) für DIESE Installation einschalten: Pfad zum privaten Schlüssel speichern.
// Nur in Jackys eigener Installation verwenden — beim Kunden nie.
//   php tools/lizenz/generator_aktivieren.php [--pfad=D:/ERP/lizenz_schluessel/nahtlos_lizenz_privat.pem]
//   php tools/lizenz/generator_aktivieren.php --aus
require __DIR__ . '/../../erp/vendor/autoload.php';
require __DIR__ . '/../../erp/src/modules/lizenzgenerator/LizenzSigner.php';
require __DIR__ . '/../../erp/src/core/Database.php';

$o = getopt('', ['pfad::', 'aus']);
$pfad = isset($o['aus']) ? '' : ($o['pfad'] ?? LizenzSigner::PRIVAT_STANDARD);
if ($pfad !== '' && !is_file($pfad)) {
    fwrite(STDERR, "Datei nicht gefunden: $pfad\n");
    exit(1);
}
Database::getInstance()
    ->prepare("UPDATE system_einstellungen SET wert = ? WHERE schluessel = 'lizenz_privat_schluessel_pfad'")
    ->execute([$pfad]);
echo $pfad === '' ? "Generator ausgeschaltet.\n" : "Generator aktiv mit Schlüssel: $pfad\n";
