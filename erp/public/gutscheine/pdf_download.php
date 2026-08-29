<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';

$id = (int)($_GET['id'] ?? 0);

$doc = new DokumentService();
$pfad = $doc->erstelleGutscheinPdf($id);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($pfad) . '"');
header('Content-Length: ' . filesize($pfad));
readfile($pfad);
exit;
