<?php
/**
 * Kuvert-Etikett als PDF (KuvertEtikettService): ?ids=1,2,3&start=oben|unten&schnitt=1|0
 * Aufgerufen aus Artikelliste (Massenaktion), Artikel-Detail und "Fertiges Schild" über js/kuvert_etikett.js.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/konfigurator/KuvertEtikettService.php';

$ids = array_map('intval', explode(',', (string) ($_GET['ids'] ?? '')));

try {
    $pdf = (new KuvertEtikettService())->pdf($ids, ($_GET['start'] ?? 'oben') === 'unten', ($_GET['schnitt'] ?? '1') === '1');
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Kuvert-Etikett: ' . $e->getMessage());
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Kuvert-Etikett.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
