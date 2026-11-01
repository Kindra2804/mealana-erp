<?php
// Einmalig: Schlüsselpaar für Lizenzen erzeugen.
//   php tools/lizenz/schluessel_erzeugen.php
// Privat → D:\ERP\lizenz_schluessel\ (NICHT ins Repo, unbedingt sichern!)
// Öffentlich → erp/config/lizenz_public.pem (geht mit jedem ERP mit)
require __DIR__ . '/../../erp/src/modules/lizenzgenerator/LizenzSigner.php';

$privatPfad = LizenzSigner::PRIVAT_STANDARD;
$publicPfad = __DIR__ . '/../../erp/config/lizenz_public.pem';
if (is_file($privatPfad)) {
    fwrite(STDERR, "Abbruch: $privatPfad existiert schon. Ein neuer Schlüssel macht ALLE ausgestellten Lizenzen ungültig.\n");
    exit(1);
}
[$privat, $public] = LizenzSigner::schluesselErzeugen();
@mkdir(dirname($privatPfad), 0700, true);
file_put_contents($privatPfad, $privat);
file_put_contents($publicPfad, $public);
echo "Privater Schlüssel: $privatPfad\nÖffentlicher Schlüssel: $publicPfad\n";
