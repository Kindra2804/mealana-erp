<?php
// Updates: Paket hochladen + prüfen, einspielen, verwerfen, zurückspielen, Wartungsmodus aufheben — nur Superadmin
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/update/UpdateService.php';
require_once __DIR__ . '/../../src/core/Logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
$eingang = UpdateService::ordner() . '/eingang';
$paket   = $_SESSION['update_paket'] ?? null;
$weg     = function () use (&$paket) {
    if ($paket && is_file($paket)) @unlink($paket);
    unset($_SESSION['update_paket']);
};

switch ($_POST['aktion'] ?? '') {
    case 'hochladen':
        $weg();
        $f = $_FILES['paket'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $_SESSION['fehler'] = ['Upload fehlgeschlagen' . ($f && $f['error'] === UPLOAD_ERR_INI_SIZE ? ' — Datei größer als upload_max_filesize (php.ini).' : '.')];
            break;
        }
        if (!is_dir($eingang)) mkdir($eingang, 0755, true);
        $ziel = $eingang . '/' . date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($f['name']));
        if (!str_ends_with(strtolower($ziel), '.zip')) $ziel .= '.zip';   // PharData erkennt das Format an der Endung
        move_uploaded_file($f['tmp_name'], $ziel);
        $_SESSION['update_paket'] = $ziel;
        break;

    case 'verwerfen':
        $weg();
        break;

    case 'einspielen':
        if (!$paket || !is_file($paket)) { $_SESSION['fehler'] = ['Kein Paket hochgeladen.']; break; }
        if (empty($_POST['bestaetigt'])) { $_SESSION['fehler'] = ['Bitte bestätigen, dass gerade niemand arbeitet.']; break; }
        $erg = UpdateService::einspielen($paket, (int)$_SESSION['benutzer']['id']);
        Logger::log($erg['erfolg'] ? 'update.eingespielt' : 'update.fehlgeschlagen', 'update_verlauf', $erg['verlauf_id'] ?: null,
                    ['meldung' => $erg['meldung'], 'migrationen' => $erg['migrationen']], null, $erg['erfolg'] ? 'info' : 'error');
        $_SESSION['update_ergebnis'] = $erg;
        $weg();
        break;

    case 'zurueckspielen':
        $erg = UpdateService::zurueckspielen((int)($_POST['verlauf_id'] ?? 0));
        Logger::log('update.zurueckgespielt', 'update_verlauf', (int)($_POST['verlauf_id'] ?? 0), ['meldung' => $erg['meldung']], null, $erg['erfolg'] ? 'warn' : 'error');
        $_SESSION[$erg['erfolg'] ? 'erfolg' : 'fehler'] = $erg['erfolg'] ? $erg['meldung'] : [$erg['meldung']];
        unset($_SESSION['update_ergebnis']);
        break;

    case 'wartung_aus':
        UpdateService::wartung(false);
        Logger::log('update.wartung_aufgehoben', null, null, [], null, 'warn');
        $_SESSION['erfolg'] = 'Wartungsmodus aufgehoben.';
        break;
}
header('Location: index.php');
exit;
