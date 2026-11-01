<?php
// Einrichtungs-Assistent: Speichern der einzelnen Schritte — nur Superadmin
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/einrichtung/OnboardingService.php';
require_once __DIR__ . '/../../src/core/Firmenlogo.php';
require_once __DIR__ . '/../../src/core/Logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['benutzer']['rolle_rang'] ?? 0) < 100) {
    header('Location: index.php');
    exit;
}

$db      = Database::getInstance();
$aktion  = $_POST['aktion'] ?? '';
$fehler  = [];
$weiter  = null;   // Schritt, der danach offen angezeigt wird
$setze   = fn(string $k, string $v) => $db->prepare("INSERT INTO system_einstellungen (schluessel, wert) VALUES (?, ?)
                                                     ON DUPLICATE KEY UPDATE wert = VALUES(wert)")->execute([$k, $v]);

switch ($aktion) {
    case 'firma':
        $werte = [];
        foreach (['firmenname', 'strasse', 'plz', 'ort', 'land', 'telefon', 'email', 'uid_nummer', 'steuernummer'] as $k) {
            $werte[$k] = trim($_POST[$k] ?? '');
        }
        $werte['uid_nummer'] = strtoupper(str_replace(' ', '', $werte['uid_nummer']));
        foreach (['firmenname' => 'Firmenname', 'strasse' => 'Straße', 'plz' => 'PLZ', 'ort' => 'Ort'] as $k => $label) {
            if ($werte[$k] === '') $fehler[] = $label . ' fehlt.';
        }
        if ($werte['uid_nummer'] === '' && $werte['steuernummer'] === '') {
            $fehler[] = 'UID-Nummer oder Steuernummer angeben (eine von beiden reicht).';
        }
        if ($fehler) break;
        foreach ($werte as $k => $v) $setze($k, $v);
        if (!empty($_FILES['logo_datei']) && $_FILES['logo_datei']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                if ($_FILES['logo_datei']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload-Fehler ' . $_FILES['logo_datei']['error']);
                $setze('logo_pfad', Firmenlogo::hochladen($_FILES['logo_datei'], 'firma'));
            } catch (RuntimeException $e) {
                $fehler[] = 'Firmendaten gespeichert, Logo aber nicht: ' . $e->getMessage();
            }
        }
        Logger::log('einrichtung.firma', null, null, ['firmenname' => $werte['firmenname']]);
        break;

    case 'superadmin':
        $id       = (int)$_SESSION['benutzer']['id'];
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['formularname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $pw       = (string)($_POST['passwort'] ?? '');
        if ($username === '' || $name === '') $fehler[] = 'Benutzername und Name sind Pflicht.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $fehler[] = 'Die E-Mail-Adresse ist ungültig.';
        if (strlen($pw) < 8) $fehler[] = 'Das Passwort braucht mindestens 8 Zeichen.';
        if ($pw !== (string)($_POST['passwort2'] ?? '')) $fehler[] = 'Die beiden Passwörter stimmen nicht überein.';
        $st = $db->prepare("SELECT COUNT(*) FROM benutzer WHERE id <> ? AND (username = ? OR (? <> '' AND email = ?))");
        $st->execute([$id, $username, $email, $email]);
        if ((int)$st->fetchColumn() > 0) $fehler[] = 'Benutzername oder E-Mail ist schon bei einem anderen Konto vergeben.';
        if ($fehler) break;
        $db->prepare("UPDATE benutzer SET username = ?, formularname = ?, email = ?, passwort = ? WHERE id = ?")
           ->execute([$username, $name, $email !== '' ? $email : null, password_hash($pw, PASSWORD_DEFAULT), $id]);
        $_SESSION['benutzer']['username']     = $username;
        $_SESSION['benutzer']['formularname'] = $name;
        OnboardingService::bestaetigen('superadmin');
        Logger::log('einrichtung.superadmin', 'benutzer', $id, ['username' => $username]);
        break;

    case 'lizenz':
        $inhalt = trim($_POST['inhalt'] ?? '');
        if (!empty($_FILES['datei']['tmp_name']) && is_uploaded_file($_FILES['datei']['tmp_name'])) {
            $inhalt = trim(file_get_contents($_FILES['datei']['tmp_name']));
        }
        if ($inhalt === '') { $fehler[] = 'Bitte eine Lizenzdatei auswählen oder ihren Inhalt einfügen.'; $weiter = 'lizenz'; break; }
        $erg = Lizenz::einspielen($inhalt, (int)$_SESSION['benutzer']['id']);
        if (!$erg['erfolg']) {
            Logger::log('lizenz.abgelehnt', 'lizenz_dateien', null, ['fehler' => $erg['fehler']], null, 'warn');
            $fehler[] = $erg['fehler'];
            $weiter = 'lizenz';
            break;
        }
        Logger::log('lizenz.eingespielt', 'lizenz_dateien', null, [
            'lizenz_nr' => $erg['daten']['lizenz_nr'], 'module' => $erg['daten']['module'], 'wartung_bis' => $erg['daten']['wartung_bis'],
        ]);
        $_SESSION['erfolg'] = 'Lizenz ' . $erg['daten']['lizenz_nr'] . ' eingespielt.';
        break;

    case 'grundmodul':
        OnboardingService::bestaetigen('lizenz');
        Logger::log('einrichtung.nur_grundmodul');
        break;

    case 'steuer':
        OnboardingService::bestaetigen('steuer');
        Logger::log('einrichtung.steuer_bestaetigt', null, null, ['profil' => 'regelbesteuert/bilanz/soll']);
        break;

    case 'lager':
        foreach ((array)($_POST['lager'] ?? []) as $lagerId => $name) {
            $name = mb_substr(trim((string)$name), 0, 50);
            if ($name === '') { $fehler[] = 'Ein Lager braucht einen Namen.'; continue; }
            $db->prepare("UPDATE lager SET name = ? WHERE id = ? AND lager_beziehung = 'eigen'")->execute([$name, (int)$lagerId]);
        }
        break;

    case 'allein':
        OnboardingService::bestaetigen('benutzer');
        break;

    case 'keine_abholung':
        OnboardingService::bestaetigen('abholorte');
        break;

    case 'spaeter':
        // Empfohlene Schritte später — nur möglich, wenn die Pflicht erledigt ist
        OnboardingService::neuLaden();
        if (!OnboardingService::pflichtOffen()) {
            header('Location: ' . BASE_PATH . '/start.php');
            exit;
        }
        break;
}

OnboardingService::neuLaden();
if ($fehler) $_SESSION['fehler'] = $fehler;
header('Location: index.php' . ($fehler || $weiter ? '?schritt=' . urlencode($weiter ?? $aktion) : ''));
exit;
