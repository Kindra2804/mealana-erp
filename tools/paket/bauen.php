<?php
/**
 * Update-/Installationspaket bauen (nur bei NAHTLOS, nie im Kundenpaket).
 *
 *   php tools/paket/bauen.php 1.0.0            → D:/ERP/pakete/nahtlos-1.0.0.zip
 *   php tools/paket/bauen.php 1.0.0 --ziel=X   → anderer Ausgabeordner
 *
 * Inhalt = letzter Commit (git archive, sauberer Stand) + erp/vendor aus dem Arbeitsordner,
 * ohne alles Interne (Werkzeuge, Lizenz-Generator, Entwickler-Notizen, Dev-Dumps, Shop-Snippets).
 * Ins Paket kommt ein signiertes Manifest (Version, Datum, „Was ist neu“ aus docs/versionen/<version>.md,
 * SHA-256 je Datei). Das ERP spielt nur Pakete mit gültiger Signatur ein.
 */

$repo = realpath(__DIR__ . '/../..');
require_once $repo . '/erp/src/modules/lizenzgenerator/LizenzSigner.php';
require_once $repo . '/erp/src/modules/update/UpdateService.php';   // GESCHUETZT: was beim Kunden nie überschrieben wird

$version = $argv[1] ?? '';
$ziel    = 'D:/ERP/pakete';
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--ziel=')) $ziel = substr($arg, 7);
}
$fehler = fn(string $m) => fwrite(STDERR, "FEHLER: $m\n") && exit(1);

if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) $fehler('Version im Format 1.0.0 angeben.');
$neuDatei = "$repo/docs/versionen/$version.md";
if (!is_file($neuDatei)) $fehler("„Was ist neu“ fehlt: docs/versionen/$version.md anlegen und committen.");

// Nur ein sauberer, committeter Stand wird ausgeliefert
chdir($repo);
$geaendert = trim((string)shell_exec('git status --porcelain --untracked-files=no'));
if ($geaendert !== '') $fehler("Es gibt nicht committete Änderungen:\n$geaendert");
if (trim((string)shell_exec('git ls-files ' . escapeshellarg("docs/versionen/$version.md"))) === '') {
    $fehler("docs/versionen/$version.md ist nicht committet.");
}
$commit = trim((string)shell_exec('git rev-parse --short HEAD'));

// Nicht ins Kundenpaket
$weglassen = [
    'tools/', '.claude/', 'import/', 'exports/', 'shop/', 'website/', 'CLAUDE.md', 'README.md', 'database.example.php',
    '.gitignore', '.gitattributes',
    'erp/src/modules/lizenzgenerator/',
    'erp/public/shell-demo.php', 'erp/public/shell-test.php', 'erp/public/datenbankvorlagen.md',
    'erp/database/schema_current.sql', 'erp/database/truncate_testdaten.sql', 'erp/database/mojibake_scan.php',
    'erp/database/export_kategorien_hersteller_dev_2026-07-09.sql',
];
$nurDocs = ['docs/handbuch/', 'docs/installation.md', 'docs/versionen/'];

$tmp = sys_get_temp_dir() . '/nahtlos_paket_' . getmypid();
@mkdir($tmp, 0777, true);
$tar = "$tmp/quelle.tar";
exec('git archive --format=tar -o ' . escapeshellarg($tar) . ' HEAD', $o, $rc);
if ($rc !== 0) $fehler('git archive fehlgeschlagen.');
$bau = "$tmp/bau";
(new PharData($tar))->extractTo($bau);
unlink($tar);

$loeschen = function (string $pfad) use (&$loeschen) {
    if (is_dir($pfad) && !is_link($pfad)) {
        foreach (array_diff(scandir($pfad), ['.', '..']) as $e) $loeschen("$pfad/$e");
        rmdir($pfad);
    } elseif (file_exists($pfad)) {
        unlink($pfad);
    }
};
foreach ($weglassen as $w) $loeschen("$bau/" . rtrim($w, '/'));

// vendor (nicht im Git) aus dem Arbeitsordner
$kopieren = function (string $von, string $nach) use (&$kopieren) {
    if (is_dir($von)) {
        @mkdir($nach, 0777, true);
        foreach (array_diff(scandir($von), ['.', '..']) as $e) $kopieren("$von/$e", "$nach/$e");
    } else {
        copy($von, $nach);
    }
};
if (!is_file("$repo/erp/vendor/autoload.php")) $fehler('erp/vendor fehlt — vorher composer install.');
$kopieren("$repo/erp/vendor", "$bau/erp/vendor");
file_put_contents("$bau/erp/VERSION", $version);

// Dateiliste + Prüfsummen
$dateien = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bau, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($bau) + 1));
    if (str_starts_with($rel, 'docs/') && !array_filter($nurDocs, fn($d) => str_starts_with($rel, $d))) {
        unlink($f->getPathname());
        continue;
    }
    if (UpdateService::geschuetzt($rel)) {   // Konfiguration/Belege/Uploads der Installation gehören nie ins Paket
        unlink($f->getPathname());
        continue;
    }
    $dateien[$rel] = hash_file('sha256', $f->getPathname());
}
ksort($dateien);

// Nur in der Groß-/Kleinschreibung umbenannte Dateien (1.0.28: database.php -> Database.php): Ein Update, das
// noch ein älterer Update-Code einspielt, hält die alte Schreibweise für „veraltet“ und löscht sie – unter
// Windows ist das dieselbe Datei wie die neue (so passiert beim Einspielen von 1.0.28). Darum liegt die alte
// Schreibweise zusätzlich mit gleichem Inhalt im Paket; das Update überschreibt dieselbe Datei nur nochmal.
$aliase = [];   // alte Schreibweise => neue
$neuKlein = [];
foreach (array_keys($dateien) as $rel) $neuKlein[strtolower($rel)] = $rel;
foreach (glob(rtrim($ziel, '/\\') . '/nahtlos-*.zip') ?: [] as $altZip) {
    $huelle = json_decode((string)@file_get_contents('phar://' . str_replace('\\', '/', $altZip) . '/nahtlos-manifest.json'), true);
    $alt    = is_string($huelle['daten'] ?? null) ? json_decode($huelle['daten'], true) : $huelle;
    foreach (array_keys($alt['dateien'] ?? []) as $rel) {
        $neu = $neuKlein[strtolower($rel)] ?? null;
        if ($neu !== null && $neu !== $rel) $aliase[$rel] = $neu;
    }
}
foreach ($aliase as $alt => $neu) $dateien[$alt] = $dateien[$neu];
ksort($dateien);

$manifest = [
    'produkt' => 'NAHTLOS ERP',
    'version' => $version,
    'datum'   => date('Y-m-d'),
    'commit'  => $commit,
    'neu'     => trim(file_get_contents($neuDatei)),
    // „Was ist neu“ aller Versionen bis zu dieser — die Update-Seite zeigt bei einem Sprung über
    // mehrere Versionen alle Zwischenversionen (neuer als die installierte)
    'neu_versionen' => (function () use ($repo, $version) {
        $t = [];
        foreach (glob("$repo/docs/versionen/*.md") as $f) {
            $v = basename($f, '.md');
            if (preg_match('/^\d+\.\d+\.\d+$/', $v) && version_compare($v, $version, '<=')) $t[$v] = trim(file_get_contents($f));
        }
        uksort($t, fn($a, $b) => version_compare($b, $a));
        return $t;
    })(),
    'dateien' => $dateien,
];
file_put_contents("$bau/nahtlos-manifest.json", LizenzSigner::signierePaket($manifest));

@mkdir($ziel, 0777, true);
$zip = rtrim($ziel, '/\\') . "/nahtlos-$version.zip";
if (file_exists($zip)) unlink($zip);
$z = new PharData($zip, 0, null, Phar::ZIP);
$z->buildFromDirectory($bau);
foreach ($aliase as $alt => $neu) $z->addFromString($alt, file_get_contents("$bau/$neu"));
$z->compressFiles(Phar::GZ);
unset($z);
$loeschen($tmp);

printf("Paket: %s\n  Version %s (Commit %s), %d Dateien, %.1f MB\n", $zip, $version, $commit, count($dateien), filesize($zip) / 1048576);
foreach ($aliase as $alt => $neu) echo "  Alte Schreibweise mitgeliefert: $alt (= $neu)\n";
