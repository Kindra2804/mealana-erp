<?php
// Lizenzdatei einspielen (Datei-Upload oder eingefügter Text) — nur Superadmin
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$inhalt = trim($_POST['inhalt'] ?? '');
if (!empty($_FILES['datei']['tmp_name']) && is_uploaded_file($_FILES['datei']['tmp_name'])) {
    $inhalt = trim(file_get_contents($_FILES['datei']['tmp_name']));
}
if ($inhalt === '') {
    $_SESSION['fehler'] = ['Bitte eine Lizenzdatei auswählen oder ihren Inhalt einfügen.'];
    header('Location: index.php');
    exit;
}

$erg = Lizenz::einspielen($inhalt, (int)($_SESSION['benutzer']['id'] ?? 0));
if ($erg['erfolg']) {
    Logger::log('lizenz.eingespielt', 'lizenz_dateien', null, [
        'lizenz_nr' => $erg['daten']['lizenz_nr'], 'module' => $erg['daten']['module'], 'wartung_bis' => $erg['daten']['wartung_bis'],
    ]);
    $_SESSION['erfolg'] = 'Lizenz ' . $erg['daten']['lizenz_nr'] . ' eingespielt.';
} else {
    Logger::log('lizenz.abgelehnt', 'lizenz_dateien', null, ['fehler' => $erg['fehler']], null, 'warn');
    $_SESSION['fehler'] = [$erg['fehler']];
}
header('Location: index.php');
exit;
