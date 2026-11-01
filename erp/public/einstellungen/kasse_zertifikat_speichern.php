<?php
// Ablaufdatum des Signaturzertifikats der aktiven RKSV-Registrierung setzen (leer = gemeldet + 5 Jahre)
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Logger.php';

$kasseId = (int)($_POST['kasse_id'] ?? 0);
$regId   = (int)($_POST['registrierung_id'] ?? 0);
$datum   = trim($_POST['zertifikat_gueltig_bis'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$kasseId || !$regId) {
    header('Location: index.php?tab=kassen');
    exit;
}
if ($datum !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
    $_SESSION['fehler'] = 'Ungültiges Datum.';
    header('Location: kasse_registrierung.php?id=' . $kasseId);
    exit;
}
Database::getInstance()
    ->prepare("UPDATE bfr_kassen_registrierungen SET zertifikat_gueltig_bis = ? WHERE id = ? AND kasse_id = ?")
    ->execute([$datum ?: null, $regId, $kasseId]);
Logger::log('kasse.zertifikat_gueltig_bis', 'bfr_kassen_registrierungen', $regId, ['datum' => $datum ?: 'Vorgabe (+5 Jahre)']);
$_SESSION['erfolg'] = 'Ablaufdatum des Zertifikats gespeichert.';
header('Location: kasse_registrierung.php?id=' . $kasseId);
exit;
