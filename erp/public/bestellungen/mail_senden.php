<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellDokumentService.php';
require_once __DIR__ . '/../../src/core/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/bestellungen/liste.php');
    exit;
}

$dokumentId = (int)($_POST['dokument_id'] ?? 0);
$empfaenger = trim($_POST['empfaenger'] ?? '');
$betreff    = trim($_POST['betreff'] ?? '');
$nachricht  = trim($_POST['nachricht'] ?? '');

$service  = new BestellDokumentService();
$dokument = $dokumentId ? $service->getDokumentMitBestellung($dokumentId) : null;

if (!$dokument || !$empfaenger || !filter_var($empfaenger, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['fehler'] = ['Ungültige Anfrage oder E-Mail-Adresse.'];
    header('Location: ' . BASE_PATH . '/bestellungen/mail_vorschau.php?dokument_id=' . $dokumentId);
    exit;
}

$bestellungId = (int)$dokument['bestellung_id'];

try {
    require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';
    $nummer = BestellungService::bestellnummer($bestellungId, (new BestellungService())->getById($bestellungId)['bestelldatum']);

    $mailer     = new Mailer();
    $logoBase64 = $mailer->ladeShopLogo(1);
    $dateipfad  = $service->getDateipfad($bestellungId, $dokument['dateiname']);

    $mailer->sendeTemplate(
        $empfaenger,
        $betreff ?: ('Bestellung ' . $nummer),
        'mails/bestellung_lieferant.html.twig',
        [
            'logo_base64'  => $logoBase64,
            'bestellnummer'=> $nummer,
            'nachricht'    => $nachricht,
        ],
        [['pfad' => $dateipfad, 'name' => $dokument['dateiname']]]
    );

    $service->markiereMailGesendet($dokumentId);
    $_SESSION['erfolg'] = 'Mail wurde an ' . $empfaenger . ' gesendet.';
} catch (Throwable $e) {
    error_log('[Bestellung Mail] ' . $e->getMessage());
    $_SESSION['fehler'] = ['Mail konnte nicht gesendet werden: ' . $e->getMessage()];
}

header('Location: ' . BASE_PATH . '/bestellungen/detail.php?id=' . $bestellungId);
exit;
