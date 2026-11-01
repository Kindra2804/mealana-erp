<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/artikel/EinheitenRepository.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

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

/**
 * Prüft ein Nummernkreis-Kürzel (A-Z/0-9, max. 5, '' = Hauptshop) und ob ein anderer Shop es schon nutzt.
 * Liefert den Fehlertext oder null, wenn das Kürzel verwendet werden darf.
 */
function pruefeNrKuerzel(PDO $db, string $kuerzel, int $shopId = 0): ?string
{
    if (!preg_match('/^[A-Z0-9]{0,5}$/', $kuerzel)) {
        return 'Nummernkreis-Kürzel: nur Buchstaben und Ziffern, höchstens 5 Zeichen.';
    }
    if ($kuerzel === '') return null;
    $stmt = $db->prepare("SELECT name FROM shops WHERE nr_kuerzel = ? AND id <> ?");
    $stmt->execute([$kuerzel, $shopId]);
    $andererShop = $stmt->fetchColumn();
    return $andererShop ? "Nummernkreis-Kürzel '$kuerzel' wird schon von „{$andererShop}“ verwendet." : null;
}

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

    // Hauptlogo (Kasse, Laden-Belege, Kanäle ohne eigenes Logo). Jeder Fehlercode
    // außer "keine Datei ausgewählt" muss gemeldet werden, sonst scheitert der
    // Upload lautlos.
    if (!empty($_FILES['logo_datei']) && $_FILES['logo_datei']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo_datei']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['fehler'] = ['Logo nicht übernommen: ' . uploadFehlerText($_FILES['logo_datei']['error'])];
        } else {
            // Hauptlogo gehört zur Firma, nicht zu einem Kanal (siehe Firmenlogo) — Kanal-Logos: Tab „Kanäle“
            try {
                setSetting($db, 'logo_pfad', speichereShopLogo($_FILES['logo_datei'], 'firma'));
            } catch (RuntimeException $e) {
                $_SESSION['fehler'] = ['Logo nicht übernommen: ' . $e->getMessage()];
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

    $stmt = $db->prepare("SELECT slug, nr_kuerzel FROM shops WHERE id = ?");
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
    // Lizenz: Anzahl aktiver Shops — darüber hinaus bleibt der Shop inaktiv
    if ($istAktiv && !(int)$shop['ist_aktiv']) {
        require_once __DIR__ . '/../../src/core/Lizenz.php';
        $aktiveShops = (int)$db->query("SELECT COUNT(*) FROM shops WHERE ist_aktiv = 1")->fetchColumn();
        if ($aktiveShops >= Lizenz::anzahl('shop')) {
            $istAktiv = 0;
            $lizenzHinweis = Lizenz::hat('shop')
                ? 'Shop nicht aktiviert: die Lizenz erlaubt ' . Lizenz::anzahl('shop') . ' aktive(n) Shop(s).'
                : 'Shop nicht aktiviert: das Modul Shop-Anbindung ist nicht lizenziert.';
        }
    }

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

    // Nummernkreis-Kürzel: gesperrte Felder schickt der Browser nicht mit; die Sperre
    // (Kanal hat schon Aufträge) wird hier trotzdem nochmal geprüft
    $fehler = $logoFehler ? [$logoFehler] : [];
    if (!empty($lizenzHinweis)) $fehler[] = $lizenzHinweis;
    if (isset($_POST['nr_kuerzel'])) {
        $kuerzel = strtoupper(trim($_POST['nr_kuerzel']));
        if ($kuerzel !== $shop['nr_kuerzel']) {
            $anzAuftraege = $db->prepare("SELECT COUNT(*) FROM auftraege WHERE shop_id = ?");
            $anzAuftraege->execute([$shopId]);
            $kuerzelFehler = $anzAuftraege->fetchColumn() > 0
                ? 'Nummernkreis-Kürzel nicht geändert: der Kanal hat bereits Aufträge.'
                : pruefeNrKuerzel($db, $kuerzel, $shopId);
            if ($kuerzelFehler) {
                $fehler[] = $kuerzelFehler;
            } else {
                $db->prepare("UPDATE shops SET nr_kuerzel = ? WHERE id = ?")->execute([$kuerzel, $shopId]);
            }
        }
    }

    if ($fehler) {
        $_SESSION['fehler'] = $fehler;
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

    $nrKuerzel   = strtoupper(trim($_POST['neu_nr_kuerzel'] ?? ''));

    if (!$slug || !$name) {
        $_SESSION['fehler'] = 'Slug und Name sind Pflichtfelder.';
        header('Location: index.php?tab=kanaele');
        exit;
    }
    if ($kuerzelFehler = pruefeNrKuerzel($db, $nrKuerzel)) {
        $_SESSION['fehler'] = $kuerzelFehler;
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
        $db->prepare("INSERT INTO shops (slug, nr_kuerzel, name, logo_pfad, wc_url, wc_key, wc_secret, wp_username, wp_app_password, sub_marke, ist_aktiv) VALUES (?,?,?,?,?,?,?,?,?,0,1)")
           ->execute([$slug, $nrKuerzel, $name, $logoPfad, $wcUrl ?: null, $wcKey ?: null, $wcSecret ?: null, $wpUsername ?: null, $wpAppPasswd ?: null]);
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
    $downloadLimit    = trim($_POST['download_limit_standard'] ?? '');

    setSetting($db, 'preisanzeige_auftrag', $preisanzeige);
    setSetting($db, 'kleinunternehmer',     $kleinunternehmer);
    setSetting($db, 'kundenanzeige_willkommenstext', $kdWillkommen);
    setSetting($db, 'kundenanzeige_qr_aktiv',        $kdQrAktiv);
    setSetting($db, 'lieferschein_charge_anzeigen',  $lsChargeAnzeigen);
    setSetting($db, 'download_limit_standard',       $downloadLimit !== '' ? (string)(int)$downloadLimit : '');

    // Paperless — Schlüssel nur bei Neueingabe überschreiben (wie SMTP-Passwort)
    setSetting($db, 'paperless_url', rtrim(trim($_POST['paperless_url'] ?? ''), '/'));
    $ppSchluessel = trim($_POST['paperless_schluessel'] ?? '');
    if ($ppSchluessel !== '') setSetting($db, 'paperless_schluessel', $ppSchluessel);

    // Artikelbilder je Belegtyp (BelegBilder) — nicht angehakt = aus
    require_once __DIR__ . '/../../src/modules/dokumente/BelegBilder.php';
    $belegBilder = (array)($_POST['beleg_bilder'] ?? []);
    foreach (array_keys(BelegBilder::BELEGE + BelegBilder::MAILS) as $typ) {
        setSetting($db, 'beleg_bilder_' . $typ, in_array($typ, $belegBilder, true) ? '1' : '0');
    }

    // Mahnwesen (Rechnungskunden) — Tage ganzzahlig, Gebühren mit Komma oder Punkt
    foreach (['mahnung_erinnerung_tage' => 7, 'mahnung_stufe1_tage' => 14, 'mahnung_stufe2_tage' => 14] as $k => $vorgabe) {
        $wert = trim($_POST[$k] ?? '');
        setSetting($db, $k, (string)max(0, $wert === '' ? $vorgabe : (int)$wert));
    }
    foreach (['mahnung_gebuehr_stufe1' => 5, 'mahnung_gebuehr_stufe2' => 10] as $k => $vorgabe) {
        $wert = trim(str_replace(',', '.', $_POST[$k] ?? ''));
        setSetting($db, $k, number_format(max(0, $wert === '' ? $vorgabe : (float)$wert), 2, '.', ''));
    }

    $_SESSION['erfolg'] = 'System-Einstellungen gespeichert.';
    header('Location: index.php?tab=system');
    exit;
}

// ─── TAB: EINHEITEN NEU ─────────────────────────────────────────────────────
if ($tab === 'einheiten_neu') {
    $name       = trim($_POST['name'] ?? '');
    $kuerzel    = trim($_POST['kuerzel'] ?? '');
    $sortierung = (int) ($_POST['sortierung'] ?? 0);

    if ($name === '') {
        $_SESSION['fehler'] = 'Name ist ein Pflichtfeld.';
    } else {
        (new EinheitenRepository())->insert($name, $kuerzel ?: null, $sortierung);
        $_SESSION['erfolg'] = 'Einheit angelegt.';
    }
    header('Location: index.php?tab=einheiten');
    exit;
}

// ─── TAB: EINHEITEN UPDATE ──────────────────────────────────────────────────
if ($tab === 'einheiten_update') {
    $id         = (int) ($_POST['id'] ?? 0);
    $name       = trim($_POST['name'] ?? '');
    $kuerzel    = trim($_POST['kuerzel'] ?? '');
    $sortierung = (int) ($_POST['sortierung'] ?? 0);

    if ($id <= 0 || $name === '') {
        $_SESSION['fehler'] = 'Ungültige Eingabe.';
    } else {
        (new EinheitenRepository())->update($id, $name, $kuerzel ?: null, $sortierung);
        $_SESSION['erfolg'] = 'Einheit gespeichert.';
    }
    header('Location: index.php?tab=einheiten');
    exit;
}

// ─── TAB: EINHEITEN LÖSCHEN ─────────────────────────────────────────────────
if ($tab === 'einheiten_loeschen') {
    $id   = (int) ($_POST['id'] ?? 0);
    $repo = new EinheitenRepository();

    // Serverseitig nachprüfen statt nur auf die UI zu vertrauen -- die "Löschen"-Aktion
    // ist in der UI zwar nur bei artikel_anzahl=0 sichtbar, ein zwischenzeitlich neu
    // zugewiesener Artikel (paralleler Import) darf trotzdem nicht durch eine FK-Verletzung
    // als kryptischer Fehler enden.
    if ($id > 0 && $repo->zaehleVerwendung($id) === 0) {
        $repo->delete($id);
        $_SESSION['erfolg'] = 'Einheit gelöscht.';
    } else {
        $_SESSION['fehler'] = 'Einheit wird noch von Artikeln verwendet und kann nicht gelöscht werden.';
    }
    header('Location: index.php?tab=einheiten');
    exit;
}

// ─── TAB: MINDESTABNAHME (Artikeltyp-Vorgabe) ──────────────────────────────
if ($tab === 'artikeltyp_mindestabnahme_update') {
    $id               = (int) ($_POST['id'] ?? 0);
    $mindestabnahme   = trim($_POST['mindestabnahme_default'] ?? '');
    $abnahmeintervall = trim($_POST['abnahmeintervall_default'] ?? '');

    if ($id <= 0) {
        $_SESSION['fehler'] = 'Ungültiger Artikeltyp.';
    } else {
        (new ArtikelService())->setArtikeltypMindestabnahme(
            $id,
            $mindestabnahme === '' ? null : (float) $mindestabnahme,
            $abnahmeintervall === '' ? null : (float) $abnahmeintervall
        );
        $_SESSION['erfolg'] = 'Mindestabnahme-Vorgabe gespeichert.';
    }
    header('Location: index.php?tab=mindestabnahme');
    exit;
}

header('Location: index.php');
exit;
