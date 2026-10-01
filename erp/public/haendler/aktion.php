<?php
/** Händler-Außenlager: alle Buchungen als JSON (Lager anlegen, Rabatt, Lieferung/Rücknahme/Schwund, Verkauf). */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/haendler/HaendlerService.php';

header('Content-Type: application/json');
$in  = json_decode(file_get_contents('php://input'), true) ?: [];
$svc = new HaendlerService();
$uid = (int)($_SESSION['benutzer']['id'] ?? 0);
$kid = (int)($in['kunde_id'] ?? 0);
$notiz = trim((string)($in['notiz'] ?? '')) ?: null;

echo json_encode(match ($in['aktion'] ?? '') {
    'lager_anlegen' => $svc->lagerAnlegen($kid),
    'rabatt'        => $svc->setStandardRabatt(($in['rabatt'] ?? '') === '' ? null : (float)str_replace(',', '.', (string)$in['rabatt'])),
    'buchen'        => $svc->buchen($kid, (string)($in['typ'] ?? ''), (array)($in['positionen'] ?? []), $notiz, $uid, (int)($in['lager_id'] ?? 1)),
    'verkauf'       => $svc->verkaufAbrechnen($kid, (array)($in['positionen'] ?? []), $notiz, $uid),
    default         => ['erfolg' => false, 'fehler' => ['Unbekannte Aktion.']],
});
