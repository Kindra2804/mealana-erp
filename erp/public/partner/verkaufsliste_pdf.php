<?php
/** Verkaufsliste der Partnerware für einen Zeitraum (immer frisch erzeugt). */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerLagerService.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerService.php';
require_once __DIR__ . '/../../src/modules/dokumente/PdfGenerator.php';

$partnerId = (int)($_GET['id'] ?? 0);
$partner   = (new PartnerService())->getById($partnerId);
if (!$partner) { http_response_code(404); exit('Partner nicht gefunden.'); }
$von = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['von'] ?? '') ? $_GET['von'] : date('Y-m-01');
$bis = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['bis'] ?? '') ? $_GET['bis'] : date('Y-m-d');

$svc       = new PartnerLagerService();
$verkaeufe = $svc->getVerkaeufe($partnerId, $von, $bis);
$tmp = sys_get_temp_dir() . '/verkaufsliste_' . $partnerId . '_' . uniqid() . '.pdf';
(new PdfGenerator())->generiere('partner/verkaufsliste.html.twig', $svc->dokumentBasis() + [
    'partner'      => $partner,
    'von'          => $von,
    'bis'          => $bis,
    'verkaeufe'    => $verkaeufe,
    'menge_gesamt' => array_sum(array_column($verkaeufe, 'menge')),
    'summe_gesamt' => array_sum(array_column($verkaeufe, 'summe')),
], $tmp);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Verkaufsliste_' . preg_replace('/[^A-Za-z0-9]/', '_', $partner['name']) . '_' . $von . '_' . $bis . '.pdf"');
readfile($tmp);
unlink($tmp);
