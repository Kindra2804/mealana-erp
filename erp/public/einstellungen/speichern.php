<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// PHP leert $_POST/$_FILES komplett und OHNE jeden Fehlercode, wenn die Gesamtgröße
// des Requests post_max_size übersteigt — das erklärt "Formular tut einfach gar
// nichts, keine Fehlermeldung, kein Feld wird gespeichert" bei größeren Uploads.
// Ohne diesen Check würde $tab unten einfach leer sein und keiner der Blöcke greifen.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $_SESSION['fehler'] = 'Die hochgeladene Datei ist zu groß für die Server-Konfiguration (post_max_size in php.ini). Bitte eine kleinere Datei wählen oder den Administrator bitten, post_max_size/upload_max_filesize zu erhöhen.';
    header('Location: index.php');
    exit;
}

/** Übersetzt einen PHP-Upload-Fehlercode in eine verständliche Meldung. */
function uploadFehlerText(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei zu groß.',
        UPLOAD_ERR_PARTIAL      => 'Datei wurde nur teilweise hochgeladen (Verbindungsabbruch?).',
        UPLOAD_ERR_NO_TMP_DIR   => 'Server-Konfigurationsfehler: kein temporäres Upload-Verzeichnis (upload_tmp_dir in php.ini prüfen).',
        UPLOAD_ERR_CANT_WRITE   => 'Server konnte die Datei nicht auf die Festplatte schreiben (Schreibrechte prüfen).',
        UPLOAD_ERR_EXTENSION    => 'Upload wurde durch eine PHP-Erweiterung blockiert.',
        default                 => 'Unbekannter Upload-Fehler (Code ' . $code . ').',
    };
}

$db  = Database::getInstance();
$tab = $_POST['tab'] ?? '';

function setSetting(PDO $db, string $key, string $value): void
{
    $db->prepare("INSERT INTO system_einstellungen (schluessel, wert) VALUES (:k, :v)
                  ON DUPLICATE KEY UPDATE wert = :v2")
       ->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
}

/**
 * Speichert ein hochgeladenes Shop-Logo. Wirft RuntimeException mit einer für den
 * Anwender verständlichen Meldung statt still null zurückzugeben — sonst zeigt die
 * Seite "Firmenangaben gespeichert", obwohl das Logo gar nicht übernommen wurde,
 * ohne jeden Hinweis warum.
 */
function speichereShopLogo(array $file, string $slug): string
{
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Datei zu groß (max. 2 MB).');
    }

    $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $erlaubt = ['png', 'jpg', 'jpeg', 'webp'];
    if (!in_array($ext, $erlaubt)) {
        throw new RuntimeException('Nicht unterstütztes Dateiformat (' . $ext . ') — erlaubt: PNG, JPG, WEBP.');
    }

    $zielDir = __DIR__ . '/../img/logos/';
    if (!is_dir($zielDir) && !mkdir($zielDir, 0755, true) && !is_dir($zielDir)) {
        throw new RuntimeException('Zielordner für Logos konnte nicht angelegt werden (Schreibrechte prüfen).');
    }
    if (!is_writable($zielDir)) {
        throw new RuntimeException('Zielordner für Logos ist nicht beschreibbar (Schreibrechte prüfen).');
    }

    $dateiname = 'logo_' . preg_replace('/[^a-z0-9\-]/', '', $slug) . '.' . $ext;
    $zielPfad  = $zielDir . $dateiname;
    if (!move_uploaded_file($file['tmp_name'], $zielPfad)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden (Schreibrechte prüfen).');
    }

    return 'img/logos/' . $dateiname;
}

// ─── TAB: FIRMA ─────────────────────────────────────────────────────────────
if ($tab === 'firma') {
    $felder = ['firmenname', 'strasse', 'plz', 'ort', 'land',
               'telefon', 'fax', 'email', 'website',
               'uid_nummer', 'steuernummer', 'bank_name', 'iban', 'bic',
               'firma_web', 'social_instagram', 'social_facebook', 'social_tiktok',
               'social_youtube', 'social_pinterest'];

    foreach ($felder as $feld) {
        setSetting($db, $feld, trim($_POST[$feld] ?? ''));
    }

    // Logo für den Hauptshop — bewusst NICHT an einen fixen Slug wie 'mealana'
    // gebunden (das würde bei jeder Weitergabe an einen anderen Betrieb falsch
    // sein), sondern an den ERSTEN angelegten Kanal (kleinste id). Jeder Fehlercode
    // außer "keine Datei ausgewählt" muss gemeldet werden, sonst scheitert der
    // Upload lautlos.
    if (!empty($_FILES['logo_datei']) && $_FILES['logo_datei']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo_datei']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['fehler'] = ['Logo nicht übernommen: ' . uploadFehlerText($_FILES['logo_datei']['error'])];
        } else {
            $shopRow = $db->query("SELECT id, slug FROM shops ORDER BY id LIMIT 1")->fetch();
            if (!$shopRow) {
                $_SESSION['fehler'] = ['Logo nicht übernommen: Es ist noch kein Kanal angelegt (Tab "Kanäle" → "+ Kanal hinzufügen").'];
            } else {
                try {
                    $pfad = speichereShopLogo($_FILES['logo_datei'], $shopRow['slug']);
                    $db->prepare("UPDATE shops SET logo_pfad = ? WHERE id = ?")->execute([$pfad, $shopRow['id']]);
                    setSetting($db, 'logo_pfad', $pfad);
                } catch (RuntimeException $e) {
                    $_SESSION['fehler'] = ['Logo nicht übernommen: ' . $e->getMessage()];
                }
            }
        }
    }

    if (empty($_SESSION['fehler'])) {
        $_SESSION['erfolg'] = 'Firmenangaben gespeichert.';
    }
    header('Location: index.php?tab=firma');
    exit;
}

// ─── TAB: KANÄLE UPDATE ─────────────────────────────────────────────────────
if ($tab === 'kanaele_update') {
    $shopId = (int)($_POST['shop_id'] ?? 0);
    if (!$shopId) {
        $_SESSION['fehler'] = 'Ungültige Shop-ID.';
        header('Location: index.php?tab=kanaele');
        exit;
    }

    $stmt = $db->prepare("SELECT slug FROM shops WHERE id = ?");
    $stmt->execute([$shopId]);
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$shop) {
        $_SESSION['fehler'] = 'Shop nicht gefunden.';
        header('Location: index.php?tab=kanaele');
        exit;
    }

    $name        = trim($_POST['shop_name'] ?? '');
    $wcUrl       = trim($_POST['wc_url'] ?? '');
    $wcKey       = trim($_POST['wc_key'] ?? '');
    $wcSecret    = trim($_POST['wc_secret'] ?? '');
    $wpUsername  = trim($_POST['wp_username'] ?? '');
    $wpAppPasswd = trim($_POST['wp_app_password'] ?? '');
    $subMarke = isset($_POST['sub_marke']) && $_POST['sub_marke'] === '1' ? 1 : 0;
    $istAktiv = isset($_POST['ist_aktiv']) && $_POST['ist_aktiv'] === '1' ? 1 : 0;

    $logoPfad = null;
    $logoFehler = null;
    if (!empty($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['shop_logo']['error'] !== UPLOAD_ERR_OK) {
            $logoFehler = 'Logo nicht übernommen: ' . uploadFehlerText($_FILES['shop_logo']['error']);
        } else {
            try {
                $logoPfad = speichereShopLogo($_FILES['shop_logo'], $shop['slug']);
            } catch (RuntimeException $e) {
                $logoFehler = 'Logo nicht übernommen: ' . $e->getMessage();
            }
        }
    }

    if ($logoPfad) {
        $db->prepare("UPDATE shops SET name=?, wc_url=?, wc_key=?, wc_secret=?, wp_username=?, wp_app_password=?, sub_marke=?, ist_aktiv=?, logo_pfad=? WHERE id=?")
           ->execute([$name, $wcUrl ?: null, $wcKey ?: null, $wcSecret ?: null, $wpUsername ?: null, $wpAppPasswd ?: null, $subMarke, $istAktiv, $logoPfad, $shopId]);
    } else {
        $db->prepare("UPDATE shops SET name=?, wc_url=?, wc_key=?, wc_secret=?, wp_username=?, wp_app_password=?, sub_marke=?, ist_aktiv=? WHERE id=?")
           ->execute([$name, $wcUrl ?: null, $wcKey ?: null, $wcSecret ?: null, $wpUsername ?: null, $wpAppPasswd ?: null, $subMarke, $istAktiv, $shopId]);
    }

    if ($logoFehler) {
        $_SESSION['fehler'] = [$logoFehler];
    } else {
        $_SESSION['erfolg'] = 'Kanal gespeichert.';
    }
    header('Location: index.php?tab=kanaele');
    exit;
}

// ─── TAB: KANÄLE NEU ────────────────────────────────────────────────────────
if ($tab === 'kanaele_neu') {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['neu_slug'] ?? '')));
    $name = trim($_POST['neu_name'] ?? '');
    $wcUrl       = trim($_POST['neu_wc_url'] ?? '');
    $wcKey       = trim($_POST['neu_wc_key'] ?? '');
    $wcSecret    = trim($_POST['neu_wc_secret'] ?? '');
    $wpUsername  = trim($_POST['neu_wp_username'] ?? '');
    $wpAppPasswd = trim($_POST['neu_wp_app_password'] ?? '');

    if (!$slug || !$name) {
        $_SESSION['fehler'] = 'Slug und Name sind Pflichtfelder.';
        header('Location: index.php?tab=kanaele');
        exit;
    }

    $logoPfad = null;
    $logoFehler = null;
    if (!empty($_FILES['neu_logo']) && $_FILES['neu_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['neu_logo']['error'] !== UPLOAD_ERR_OK) {
            $logoFehler = 'Kanal angelegt, aber Logo nicht übernommen: ' . uploadFehlerText($_FILES['neu_logo']['error']);
        } else {
            try {
                $logoPfad = speichereShopLogo($_FILES['neu_logo'], $slug);
            } catch (RuntimeException $e) {
                $logoFehler = 'Kanal angelegt, aber Logo nicht übernommen: ' . $e->getMessage();
            }
        }
    }

    try {
        $db->prepare("INSERT INTO shops (slug, name, logo_pfad, wc_url, wc_key, wc_secret, wp_username, wp_app_password, sub_marke, ist_aktiv) VALUES (?,?,?,?,?,?,?,?,0,1)")
           ->execute([$slug, $name, $logoPfad, $wcUrl ?: null, $wcKey ?: null, $wcSecret ?: null, $wpUsername ?: null, $wpAppPasswd ?: null]);
        $_SESSION[$logoFehler ? 'fehler' : 'erfolg'] = $logoFehler ? [$logoFehler] : 'Kanal angelegt.';
    } catch (PDOException $e) {
        $_SESSION['fehler'] = 'Slug bereits vorhanden.';
    }
    header('Location: index.php?tab=kanaele');
    exit;
}

// ─── TAB: MAIL ──────────────────────────────────────────────────────────────
if ($tab === 'mail') {
    setSetting($db, 'mail_smtp_host',       trim($_POST['mail_smtp_host'] ?? ''));
    setSetting($db, 'mail_smtp_port',       (string)(int)($_POST['mail_smtp_port'] ?? 587));
    setSetting($db, 'mail_smtp_user',       trim($_POST['mail_smtp_user'] ?? ''));
    setSetting($db, 'mail_smtp_encryption', in_array($_POST['mail_smtp_encryption'] ?? 'tls', ['tls','ssl','']) ? $_POST['mail_smtp_encryption'] : 'tls');
    setSetting($db, 'mail_from_name',       trim($_POST['mail_from_name'] ?? ''));
    setSetting($db, 'mail_from_address',    trim($_POST['mail_from_address'] ?? ''));
    setSetting($db, 'mail_aktiv',           isset($_POST['mail_aktiv']) ? '1' : '0');
    setSetting($db, 'mail_test_adresse',   trim($_POST['mail_test_adresse'] ?? ''));

    // Passwort nur speichern wenn neu eingegeben (nicht leer)
    $neuesPasswort = $_POST['mail_smtp_pass'] ?? '';
    if ($neuesPasswort !== '') {
        setSetting($db, 'mail_smtp_pass', $neuesPasswort);
    }

    $_SESSION['erfolg'] = 'SMTP-Einstellungen gespeichert.';
    header('Location: index.php?tab=mail');
    exit;
}

// ─── TAB: SYSTEM ─────────────────────────────────────────────────────────────
if ($tab === 'system') {
    $preisanzeige   = in_array($_POST['preisanzeige_auftrag'] ?? 'brutto', ['brutto','netto','beides'])
        ? $_POST['preisanzeige_auftrag'] : 'brutto';
    $kleinunternehmer = isset($_POST['kleinunternehmer']) ? '1' : '0';
    $kdWillkommen     = trim($_POST['kundenanzeige_willkommenstext'] ?? '');
    $kdQrAktiv        = isset($_POST['kundenanzeige_qr_aktiv']) ? '1' : '0';
    $lsChargeAnzeigen = isset($_POST['lieferschein_charge_anzeigen']) ? '1' : '0';

    setSetting($db, 'preisanzeige_auftrag', $preisanzeige);
    setSetting($db, 'kleinunternehmer',     $kleinunternehmer);
    setSetting($db, 'kundenanzeige_willkommenstext', $kdWillkommen);
    setSetting($db, 'kundenanzeige_qr_aktiv',        $kdQrAktiv);
    setSetting($db, 'lieferschein_charge_anzeigen',  $lsChargeAnzeigen);

    $_SESSION['erfolg'] = 'System-Einstellungen gespeichert.';
    header('Location: index.php?tab=system');
    exit;
}

header('Location: index.php');
exit;
