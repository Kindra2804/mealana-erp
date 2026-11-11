<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'meldung' => 'Nur POST erlaubt.']);
    exit;
}

$db   = Database::getInstance();
$conf = $db->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel LIKE 'mail_%'")->fetchAll(PDO::FETCH_KEY_PAIR);

// Empfänger: aus Formular, sonst eigene Benutzer-Mail, sonst Absenderadresse
$an = trim($_POST['test_empfaenger'] ?? '')
    ?: ($_SESSION['benutzer']['email'] ?? '')
    ?: ($conf['mail_from_address'] ?? '');

if (!$an) {
    echo json_encode(['erfolg' => false, 'meldung' => 'Kein Empfänger — bitte eine Test-Adresse eingeben oder SMTP-Absenderadresse eintragen.']);
    exit;
}

try {
    // shop_id > 0: mit dem Absender/SMTP dieses Kanals testen (Einstellungen → Mail → Absender je Kanal)
    $mailer = (new Mailer())->fuerShop((int)($_POST['shop_id'] ?? 0));
    $abs    = $mailer->absender();
    $mailer->sende(
        empfaenger: $an,
        betreff:    'MeaLana ERP — Test-Mail',
        htmlBody:   '<p>Die SMTP-Konfiguration funktioniert korrekt.</p><p>Diese Nachricht wurde manuell aus den ERP-Einstellungen gesendet.</p>',
        textBody:   'Die SMTP-Konfiguration funktioniert korrekt.',
        erzwinge:   true   // Bypass mail_aktiv-Check beim Test
    );
    echo json_encode(['erfolg' => true, 'meldung' => 'Test-Mail gesendet an: ' . $an . ' (Absender ' . $abs['adresse'] . ' über ' . $abs['smtp'] . ')']);
} catch (Throwable $e) {
    echo json_encode(['erfolg' => false, 'meldung' => 'SMTP-Fehler: ' . $e->getMessage()]);
}
