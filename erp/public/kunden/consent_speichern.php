<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$kundeId = (int) ($_POST['kunde_id'] ?? 0);
$service = new KundenService();

$service->consentEintragen([
    'kunde_id'     => $kundeId,
    'consent_typ'  => $_POST['consent_typ']  ?? 'newsletter',
    'eingewilligt' => (int) ($_POST['eingewilligt'] ?? 0),
    'quelle'       => $_POST['quelle']       ?? 'erp_manuell',
    'kommentar'    => trim($_POST['kommentar'] ?? '') ?: null,
]);

header('Location: detail.php?id=' . $kundeId . '&tab=dsgvo');
exit;
