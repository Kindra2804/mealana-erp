<?php
/**
 * Auslöser für Cronjobs der Demo-Installation, wenn der Hoster nur URL-Cronjobs kennt
 * (World4You): https://…/demo/demo_cron.php?k=<reset_schluessel aus config/demo.php>
 *
 * Startet scripts/demo/demo_erzeugen.php im Hintergrund mit dem PHP der Kommandozeile
 * ('php_cli' in config/demo.php) und antwortet sofort — der Neuaufbau dauert länger, als ein
 * Webaufruf laufen darf. Außerhalb einer Demo-Installation: 404.
 */
require_once __DIR__ . '/../config/bootstrap.php';

$konf = Demo::aktiv() ? Demo::konfig() : [];
if (!$konf) { http_response_code(404); exit; }

$schluessel = (string)($konf['reset_schluessel'] ?? '');
if (strlen($schluessel) < 20 || !hash_equals($schluessel, (string)($_GET['k'] ?? ''))) {
    http_response_code(403);
    exit('Kein Zugriff.');
}
if (!function_exists('exec')) { http_response_code(500); exit('exec() ist auf diesem Server gesperrt.'); }

$php     = (string)($konf['php_cli'] ?? 'php');
$skript  = realpath(__DIR__ . '/../scripts/demo/demo_erzeugen.php');
$log     = __DIR__ . '/../storage/demo_erzeugen.log';
$sperre  = __DIR__ . '/../storage/demo_erzeugen.lock';

// Läuft schon einer (Sperre jünger als 15 Minuten)? Dann nicht doppelt starten.
if (is_file($sperre) && time() - filemtime($sperre) < 900) {
    exit('Läuft bereits seit ' . (time() - filemtime($sperre)) . ' s.');
}
touch($sperre);
exec(sprintf('nohup sh -c %s > /dev/null 2>&1 &',
    escapeshellarg(sprintf('%s %s > %s 2>&1; rm -f %s', escapeshellarg($php), escapeshellarg($skript), escapeshellarg($log), escapeshellarg($sperre)))));
echo 'Neuaufbau gestartet ' . date('d.m.Y H:i:s') . ' (Protokoll: storage/demo_erzeugen.log)';
