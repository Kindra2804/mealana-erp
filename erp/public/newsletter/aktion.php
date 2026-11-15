<?php
// Newsletter-Empfänger: Aktionen (von Hand abmelden)
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterEmpfaengerService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: empfaenger.php'); exit; }
$shopId = (int)($_POST['shop_id'] ?? 0);
if (($_POST['aktion'] ?? '') === 'eintragen') {
    require_once __DIR__ . '/../../src/modules/newsletter/NewsletterPortalService.php';
    $erg = (new NewsletterPortalService())->vonHandEintragen($shopId, (string)($_POST['email'] ?? ''), (string)($_POST['vorname'] ?? ''),
                                                             (string)($_POST['notiz'] ?? ''), (int)$_SESSION['benutzer']['id']);
    $_SESSION[$erg['erfolg'] ? 'erfolg' : 'fehler'] = $erg['erfolg']
        ? ($erg['mail'] ? 'Eingetragen — die Bestätigungs-Mail ist unterwegs.' : 'Eingetragen, aber die Bestätigungs-Mail ging nicht raus (Mail-Einstellungen prüfen).')
        : $erg['fehler'];
}
if (($_POST['aktion'] ?? '') === 'abmelden') {
    $erg = (new NewsletterEmpfaengerService())->abmelden((int)($_POST['id'] ?? 0), (int)$_SESSION['benutzer']['id'], 'von Hand im ERP');
    $_SESSION[$erg['erfolg'] ? 'erfolg' : 'fehler'] = $erg['erfolg'] ? 'Empfänger abgemeldet.' : $erg['fehler'];
}
header('Location: empfaenger.php?shop_id=' . $shopId);
exit;
