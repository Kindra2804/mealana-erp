<?php
/**
 * Manueller Neuaufbau der Demo (normalerweise nicht nötig: Demo::frischHalten erledigt das beim
 * ersten Aufruf eines neuen Tages). Aufruf: …/demo/demo_cron.php?k=<reset_schluessel aus config/demo.php>
 * Außerhalb einer Demo-Installation: 404.
 */
require_once __DIR__ . '/../config/bootstrap.php';

$konf = Demo::aktiv() ? Demo::konfig() : [];
if (!$konf) { http_response_code(404); exit; }

$schluessel = (string)($konf['reset_schluessel'] ?? '');
if (strlen($schluessel) < 20 || !hash_equals($schluessel, (string)($_GET['k'] ?? ''))) {
    http_response_code(403);
    exit('Kein Zugriff.');
}
echo Demo::neuaufbauStarten()
    ? 'Neuaufbau gestartet ' . date('d.m.Y H:i:s') . ' (Protokoll: storage/demo_erzeugen.log)'
    : 'Nicht gestartet: der letzte Start ist keine 15 Minuten her (oder exec() ist gesperrt).';
