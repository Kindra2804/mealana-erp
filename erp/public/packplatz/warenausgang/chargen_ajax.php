<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
header('Content-Type: application/json');

$artikelId = (int)($_GET['artikel_id'] ?? 0);
$lagerId   = (int)($_GET['lager_id'] ?? 1);

if (!$artikelId) { echo json_encode([]); exit; }

$db   = Database::getInstance();
$stmt = $db->prepare("
    SELECT id, charge, bestand,
           -- Bestand ohne Charge (z.B. Startbestand aus dem JTL-Abgleich) wie „nachzutragen“: der Packer
           -- trägt die Charge vom Etikett ein (LagerService::chargeNachtragen) – vorher Sackgasse
           -- „Keine Chargen im Lager vorhanden“ bei chargenpflichtigen Artikeln (Shop-Test 2026-11-13)
           CASE WHEN charge IS NULL OR charge = '' THEN 'nachzutragen' ELSE charge_status END AS charge_status
    FROM lagerbestand
    WHERE artikel_id = :aid AND lager_id = :lid
      AND bestand > 0
    ORDER BY (charge IS NULL OR charge = '' OR charge_status = 'nachzutragen') DESC, erstellt_am ASC
");
$stmt->execute([':aid' => $artikelId, ':lid' => $lagerId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
