<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=kassen');
    exit;
}

$kasseId    = (int)($_POST['kasse_id'] ?? 0);
$aktion     = $_POST['aktion'] ?? '';
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
$service    = new BfrService();

if ($kasseId < 1) {
    header('Location: index.php?tab=kassen');
    exit;
}

if ($aktion === 'neu') {
    $service->starteRegistrierung($kasseId, $benutzerId ?: null);
    $_SESSION['erfolg'] = 'Neue Registrierung gestartet.';
    header('Location: kasse_registrierung.php?id=' . $kasseId);
    exit;
}

if ($aktion === 'abrufen') {
    $entwurfId = (int)($_POST['entwurf_id'] ?? 0);
    if (!$entwurfId) {
        $entwurfId = $service->starteRegistrierung($kasseId, $benutzerId ?: null);
    }

    $bfrUrl = trim($_POST['bfr_url'] ?? '');
    $info   = $bfrUrl !== '' ? $service->leseZertifikatInfo($bfrUrl) : ['erfolg' => false];

    $daten = [
        'rksv_kassen_id'            => $info['erfolg'] ? $info['rksv_kassen_id'] : trim($_POST['rksv_kassen_id'] ?? ''),
        'bfr_url'                   => $bfrUrl,
        'uid_nummer'                => $info['erfolg'] ? $info['uid_nummer']                : trim($_POST['uid_nummer'] ?? ''),
        'vertrauensdiensteanbieter' => $info['erfolg'] ? $info['vertrauensdiensteanbieter']  : trim($_POST['vertrauensdiensteanbieter'] ?? ''),
        'zertifikat_seriennr_dez'   => $info['erfolg'] ? $info['zertifikat_seriennr_dez']    : trim($_POST['zertifikat_seriennr_dez'] ?? ''),
        'zertifikat_seriennr_hex'   => $info['erfolg'] ? $info['zertifikat_seriennr_hex']    : trim($_POST['zertifikat_seriennr_hex'] ?? ''),
        'zertifikat_gemeldet'       => !empty($_POST['zertifikat_gemeldet']),
        'kasse_gemeldet'            => !empty($_POST['kasse_gemeldet']),
        'startbeleg_geprueft'       => !empty($_POST['startbeleg_geprueft']),
        'startbeleg_inhalt'         => trim($_POST['startbeleg_inhalt'] ?? ''),
    ];
    $service->aktualisiereRegistrierung($entwurfId, $daten);

    $_SESSION[$info['erfolg'] ? 'erfolg' : 'fehler'] = $info['erfolg']
        ? 'Daten von /state übernommen.'
        : 'BFR nicht erreichbar — Felder wurden aus der Eingabe übernommen, bitte manuell prüfen.';
    header('Location: kasse_registrierung.php?id=' . $kasseId);
    exit;
}

if ($aktion === 'speichern' || $aktion === 'abschliessen') {
    $entwurfId = (int)($_POST['entwurf_id'] ?? 0);
    if (!$entwurfId) {
        $entwurfId = $service->starteRegistrierung($kasseId, $benutzerId ?: null);
    }

    $daten = [
        'rksv_kassen_id'            => trim($_POST['rksv_kassen_id'] ?? ''),
        'bfr_url'                   => trim($_POST['bfr_url'] ?? ''),
        'uid_nummer'                => trim($_POST['uid_nummer'] ?? ''),
        'vertrauensdiensteanbieter' => trim($_POST['vertrauensdiensteanbieter'] ?? ''),
        'zertifikat_seriennr_dez'   => trim($_POST['zertifikat_seriennr_dez'] ?? ''),
        'zertifikat_seriennr_hex'   => trim($_POST['zertifikat_seriennr_hex'] ?? ''),
        'zertifikat_gemeldet'       => !empty($_POST['zertifikat_gemeldet']),
        'kasse_gemeldet'            => !empty($_POST['kasse_gemeldet']),
        'startbeleg_geprueft'       => !empty($_POST['startbeleg_geprueft']),
        'startbeleg_inhalt'         => trim($_POST['startbeleg_inhalt'] ?? ''),
    ];
    $service->aktualisiereRegistrierung($entwurfId, $daten);

    if ($aktion === 'speichern') {
        $_SESSION['erfolg'] = 'Gespeichert.';
        header('Location: kasse_registrierung.php?id=' . $kasseId);
        exit;
    }

    $ergebnis = $service->schliesseRegistrierungAb($entwurfId);
    $_SESSION[$ergebnis['erfolg'] ? 'erfolg' : 'fehler'] = $ergebnis['erfolg']
        ? 'Registrierung abgeschlossen — Kassen-ID ist jetzt aktiv und gesperrt.'
        : ($ergebnis['fehler'] ?? 'Konnte nicht abgeschlossen werden.');
    header('Location: kasse_registrierung.php?id=' . $kasseId);
    exit;
}

header('Location: kasse_registrierung.php?id=' . $kasseId);
