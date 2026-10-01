<?php
/** Übernahme-/Rückgabeschein als PDF (wird beim ersten Aufruf erzeugt und abgelegt). */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerLagerService.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerService.php';
require_once __DIR__ . '/../../src/modules/dokumente/PdfGenerator.php';

$svc   = new PartnerLagerService();
$beleg = $svc->getBeleg((int)($_GET['id'] ?? 0));
if (!$beleg) { http_response_code(404); exit('Beleg nicht gefunden.'); }

$pfad = __DIR__ . '/../../storage/partner_belege/' . preg_replace('/[^A-Za-z0-9\-]/', '', $beleg['nummer']) . '.pdf';
if (!file_exists($pfad)) {
    (new PdfGenerator())->generiere('partner/beleg.html.twig', $svc->dokumentBasis() + [
        'beleg'   => $beleg,
        'partner' => (new PartnerService())->getById((int)$beleg['partner_id']),
        'summe'   => array_sum(array_column($beleg['positionen'], 'menge')),
    ], $pfad);
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $beleg['nummer'] . '.pdf"');
readfile($pfad);
