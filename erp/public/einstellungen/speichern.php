<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db  = Database::getInstance();
$tab = $_POST['tab'] ?? '';

function setSetting(PDO $db, string $key, string $value): void
{
    $db->prepare("INSERT INTO system_einstellungen (schluessel, wert) VALUES (:k, :v)
                  ON DUPLICATE KEY UPDATE wert = :v2")
       ->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
}

function speichereShopLogo(array $file, string $slug): ?string
{
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 2 * 1024 * 1024) return null;

    $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $erlaubt = ['png', 'jpg', 'jpeg', 'webp'];
    if (!in_array($ext, $erlaubt)) return null;

    $zielDir = __DIR__ . '/../img/logos/';
    if (!is_dir($zielDir)) mkdir($zielDir, 0755, true);

    $dateiname = 'logo_' . preg_replace('/[^a-z0-9\-]/', '', $slug) . '.' . $ext;
    $zielPfad  = $zielDir . $dateiname;
    if (!move_uploaded_file($file['tmp_name'], $zielPfad)) return null;

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

    // Logo für Hauptshop (slug='mealana')
    if (!empty($_FILES['logo_datei']) && $_FILES['logo_datei']['error'] === UPLOAD_ERR_OK) {
        $shopRow = $db->query("SELECT id FROM shops WHERE slug = 'mealana' LIMIT 1")->fetch();
        if ($shopRow) {
            $pfad = speichereShopLogo($_FILES['logo_datei'], 'mealana');
            if ($pfad) {
                $db->prepare("UPDATE shops SET logo_pfad = ? WHERE slug = 'mealana'")->execute([$pfad]);
                setSetting($db, 'logo_pfad', $pfad);
            }
        }
    }

    $_SESSION['erfolg'] = 'Firmenangaben gespeichert.';
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

    $shopRow = $db->prepare("SELECT slug FROM shops WHERE id = ?")->execute([$shopId])
        ? $db->prepare("SELECT slug FROM shops WHERE id = ?") : null;
    $stmt = $db->prepare("SELECT slug FROM shops WHERE id = ?");
    $stmt->execute([$shopId]);
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$shop) {
        $_SESSION['fehler'] = 'Shop nicht gefunden.';
        header('Location: index.php?tab=kanaele');
        exit;
    }

    $name     = trim($_POST['shop_name'] ?? '');
    $wcUrl    = trim($_POST['wc_url'] ?? '');
    $subMarke = isset($_POST['sub_marke']) && $_POST['sub_marke'] === '1' ? 1 : 0;
    $istAktiv = isset($_POST['ist_aktiv']) && $_POST['ist_aktiv'] === '1' ? 1 : 0;

    $logoPfad = null;
    if (!empty($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
        $logoPfad = speichereShopLogo($_FILES['shop_logo'], $shop['slug']);
    }

    if ($logoPfad) {
        $db->prepare("UPDATE shops SET name=?, wc_url=?, sub_marke=?, ist_aktiv=?, logo_pfad=? WHERE id=?")
           ->execute([$name, $wcUrl ?: null, $subMarke, $istAktiv, $logoPfad, $shopId]);
    } else {
        $db->prepare("UPDATE shops SET name=?, wc_url=?, sub_marke=?, ist_aktiv=? WHERE id=?")
           ->execute([$name, $wcUrl ?: null, $subMarke, $istAktiv, $shopId]);
    }

    $_SESSION['erfolg'] = 'Kanal gespeichert.';
    header('Location: index.php?tab=kanaele');
    exit;
}

// ─── TAB: KANÄLE NEU ────────────────────────────────────────────────────────
if ($tab === 'kanaele_neu') {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['neu_slug'] ?? '')));
    $name = trim($_POST['neu_name'] ?? '');

    if (!$slug || !$name) {
        $_SESSION['fehler'] = 'Slug und Name sind Pflichtfelder.';
        header('Location: index.php?tab=kanaele');
        exit;
    }

    $logoPfad = null;
    if (!empty($_FILES['neu_logo']) && $_FILES['neu_logo']['error'] === UPLOAD_ERR_OK) {
        $logoPfad = speichereShopLogo($_FILES['neu_logo'], $slug);
    }

    try {
        $db->prepare("INSERT INTO shops (slug, name, logo_pfad, sub_marke, ist_aktiv) VALUES (?,?,?,0,1)")
           ->execute([$slug, $name, $logoPfad]);
        $_SESSION['erfolg'] = 'Kanal angelegt.';
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

    setSetting($db, 'preisanzeige_auftrag', $preisanzeige);
    setSetting($db, 'kleinunternehmer',     $kleinunternehmer);
    setSetting($db, 'kundenanzeige_willkommenstext', $kdWillkommen);
    setSetting($db, 'kundenanzeige_qr_aktiv',        $kdQrAktiv);

    $_SESSION['erfolg'] = 'System-Einstellungen gespeichert.';
    header('Location: index.php?tab=system');
    exit;
}

header('Location: index.php');
exit;
