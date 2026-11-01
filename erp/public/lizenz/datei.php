<?php
// Lizenzdatei einer Ausgabe herunterladen (Generator)
require_once __DIR__ . '/../includes/auth_check.php';

$a = Lizenz::generatorAktiv() ? (new LizenzGenerator())->ausgabe((int)($_GET['id'] ?? 0)) : null;
if (!$a) {
    http_response_code(404);
    exit('Nicht gefunden.');
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . LizenzGenerator::dateiname($a) . '"');
header('Content-Length: ' . strlen($a['inhalt']));
echo $a['inhalt'];
