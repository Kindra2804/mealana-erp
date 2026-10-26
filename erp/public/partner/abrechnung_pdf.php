<?php
/**
 * Partner-Abrechnung als PDF (immer frisch erzeugt), je nach Verkaufsart:
 *   fremdrechnung → Verkaufsaufstellung + Zahlungsaviso (keine Rechnung von uns!)
 *   spende        → Spendenliste für die Übergabe (inkl. Bar-Anteil Spendenlade)
 *   kommission    → Kontrollliste verkaufte Ware × vereinbarter EK (Abgleich seiner Rechnung)
 * ?aviso=<id> setzt die Daten einer gebuchten Auszahlung (Nummer, Betrag, Art) aufs Aviso.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerKontoService.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerLagerService.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerService.php';
require_once __DIR__ . '/../../src/modules/dokumente/PdfGenerator.php';

$partnerId = (int)($_GET['id'] ?? 0);
$partner   = (new PartnerService())->getById($partnerId);
if (!$partner) { http_response_code(404); exit('Partner nicht gefunden.'); }

$konto  = new PartnerKontoService();
$aviso  = !empty($_GET['aviso']) ? $konto->getAuszahlung((int)$_GET['aviso']) : null;
if ($aviso && (int)$aviso['partner_id'] !== $partnerId) $aviso = null;

$datum = fn($k, $default) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET[$k] ?? '') ? $_GET[$k] : $default;
$von = $aviso['zeitraum_von'] ?? $datum('von', date('Y-m-01'));
$bis = $aviso['zeitraum_bis'] ?? $datum('bis', date('Y-m-d'));

$daten = (new PartnerLagerService())->dokumentBasis() + [
    'partner'   => $partner,
    'modus'     => $partner['typ'],
    'von'       => $von,
    'bis'       => $bis,
    'aviso'     => $aviso,
    'ku_text'   => $partner['kleinunternehmer'] ? ($partner['ku_text'] ?: PartnerService::KU_STANDARDTEXT) : null,
];
if ($partner['typ'] === 'kommission') {
    $zeilen = $konto->getKommissionKontrolle($partnerId, $von, $bis);
    $daten += [
        'kontrolle'      => $zeilen,
        'vk_netto_summe' => array_sum(array_column($zeilen, 'vk_netto')),
        'ek_summe'       => array_sum(array_map(fn($z) => (float)($z['ek_summe'] ?? 0), $zeilen)),
        'ek_fehlt'       => count(array_filter($zeilen, fn($z) => $z['ek_netto'] === null)),
    ];
    $titel = 'Kontrollliste';
} else {
    $verkaeufe = $konto->getVerkaeufe($partnerId, $von, $bis);
    $daten += [
        'verkaeufe'    => $verkaeufe,
        'summe_gesamt' => array_sum(array_column($verkaeufe, 'summe')),
        'konto'        => $konto->getKonto($partnerId, $von, $bis),
    ];
    $titel = $partner['typ'] === 'spende' ? 'Spendenliste' : 'Verkaufsaufstellung';
}

$tmp = sys_get_temp_dir() . '/partner_abrechnung_' . $partnerId . '_' . uniqid() . '.pdf';
(new PdfGenerator())->generiere('partner/abrechnung.html.twig', $daten, $tmp);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $titel . '_' . preg_replace('/[^A-Za-z0-9]/', '_', $partner['name'])
    . ($aviso ? '_' . $aviso['nummer'] : '_' . $von . '_' . $bis) . '.pdf"');
readfile($tmp);
unlink($tmp);
