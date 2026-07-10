<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db = Database::getInstance();

// Alle system_einstellungen laden
$rows = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);

// Shops laden
$shops = $db->query("SELECT * FROM shops ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$e    = $_SESSION['erfolg'] ?? null;
$f    = $_SESSION['fehler'] ?? null;
$aktTab = $_GET['tab'] ?? 'firma';
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$pageTitle        = 'Einstellungen';
$activeModule     = 'einstellungen';
$actionBarContent = '';
require_once __DIR__ . '/../includes/shell_top.php';

$s = fn(string $key, string $fallback = '') => htmlspecialchars($rows[$key] ?? $fallback);
?>

<?php if ($e): ?>
    <div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)"><?= htmlspecialchars($e) ?></div>
<?php endif; ?>
<?php if ($f): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)"><?= htmlspecialchars(is_array($f) ? implode(', ', $f) : $f) ?></div>
<?php endif; ?>

<!-- Tab-Navigation -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--color-border);margin-bottom:16px">
    <?php foreach (
        [
            'firma'    => 'Firma',
            'kanaele'  => 'Kanäle',
            'mail'     => 'Mail / SMTP',
            'system'   => 'System',
            'kassen'   => 'Kassen',
            'nummernkreise' => 'Nummernkreise',
        ] as $tabId => $tabLabel
    ): ?>
        <a href="?tab=<?= $tabId ?>"
            style="padding:8px 20px;font-size:13px;font-weight:600;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;
                  <?= $aktTab === $tabId ? 'color:var(--color-nav);border-bottom-color:var(--color-nav)' : 'color:var(--color-text-muted)' ?>">
            <?= $tabLabel ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($aktTab === 'firma'): ?>
    <!-- ═══════════ TAB: FIRMA ═══════════ -->
    <form method="post" action="speichern.php" enctype="multipart/form-data">
        <input type="hidden" name="tab" value="firma">

        <div class="card" style="margin-bottom:12px">
            <div class="card-header">Firmenangaben (erscheinen auf Dokumenten)</div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">

                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">Firmenname *</label>
                    <input type="text" name="firmenname" class="erp-input" style="max-width:400px"
                        value="<?= $s('firmenname', 'MEALANA KG') ?>" required>
                </div>

                <div class="form-group" style="grid-column:1/3">
                    <label class="form-label">Straße + Hausnummer</label>
                    <input type="text" name="strasse" class="erp-input" value="<?= $s('strasse') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">PLZ</label>
                    <input type="text" name="plz" class="erp-input" value="<?= $s('plz') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Ort</label>
                    <input type="text" name="ort" class="erp-input" value="<?= $s('ort') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Land</label>
                    <input type="text" name="land" class="erp-input" value="<?= $s('land', 'Österreich') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="text" name="telefon" class="erp-input" value="<?= $s('telefon') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Fax (optional)</label>
                    <input type="text" name="fax" class="erp-input" value="<?= $s('fax') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">E-Mail</label>
                    <input type="email" name="email" class="erp-input" value="<?= $s('email') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Website</label>
                    <input type="text" name="website" class="erp-input" value="<?= $s('website') ?>">
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:12px">
            <div class="card-header">Steuer &amp; Bank</div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                <div class="form-group">
                    <label class="form-label">UID-Nummer (z.B. ATU12345678)</label>
                    <input type="text" name="uid_nummer" class="erp-input" value="<?= $s('uid_nummer') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Steuernummer</label>
                    <input type="text" name="steuernummer" class="erp-input" value="<?= $s('steuernummer') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Bank</label>
                    <input type="text" name="bank_name" class="erp-input" value="<?= $s('bank_name') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">IBAN</label>
                    <input type="text" name="iban" class="erp-input" value="<?= $s('iban') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">BIC</label>
                    <input type="text" name="bic" class="erp-input" value="<?= $s('bic') ?>">
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:12px">
            <div class="card-header">Online-Präsenz &amp; Social Media</div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label class="form-label">Website (URL)</label>
                    <input type="url" name="firma_web" class="erp-input" placeholder="https:/<?= BASE_PATH ?>.at" value="<?= $s('firma_web') ?>">
                </div>
                <div class="form-group"></div>
                <div class="form-group">
                    <label class="form-label">Instagram</label>
                    <input type="url" name="social_instagram" class="erp-input" placeholder="https://instagram.com<?= BASE_PATH ?>" value="<?= $s('social_instagram') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Facebook</label>
                    <input type="url" name="social_facebook" class="erp-input" placeholder="https://facebook.com<?= BASE_PATH ?>" value="<?= $s('social_facebook') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">TikTok</label>
                    <input type="url" name="social_tiktok" class="erp-input" placeholder="https://tiktok.com/@mealana" value="<?= $s('social_tiktok') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">YouTube</label>
                    <input type="url" name="social_youtube" class="erp-input" placeholder="https://youtube.com/@mealana" value="<?= $s('social_youtube') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Pinterest</label>
                    <input type="url" name="social_pinterest" class="erp-input" placeholder="https://pinterest.com<?= BASE_PATH ?>" value="<?= $s('social_pinterest') ?>">
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:12px">
            <div class="card-header">Hauptlogo (erscheint auf Dokumenten oben links)</div>
            <div style="padding:16px;display:flex;align-items:center;gap:24px">
                <?php
                $logoShop = null;
                foreach ($shops as $sh) {
                    if ($sh['slug'] === 'mealana') {
                        $logoShop = $sh;
                        break;
                    }
                }
                $logoPfad = $logoShop['logo_pfad'] ?? '';
                ?>
                <?php if ($logoPfad && file_exists(__DIR__ . '/../' . $logoPfad)): ?>
                    <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($logoPfad) ?>" style="max-height:60px;max-width:200px;border:1px solid var(--color-border);border-radius:4px;padding:4px">
                <?php else: ?>
                    <div style="width:200px;height:60px;border:2px dashed var(--color-border);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--color-text-muted)">Kein Logo</div>
                <?php endif; ?>
                <div>
                    <label class="form-label">Logo hochladen (PNG, max. 2 MB)</label>
                    <input type="file" name="logo_datei" accept="image/png,image/jpeg,image/webp" class="erp-input" style="padding:4px">
                    <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">Empfohlen: 400×120 px, transparenter Hintergrund (PNG)</div>
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-primary">Firmenangaben speichern</button>
        </div>
    </form>

<?php elseif ($aktTab === 'kanaele'): ?>
    <!-- ═══════════ TAB: KANÄLE ═══════════ -->
    <div class="card" style="margin-bottom:12px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span>Kanäle / Shops</span>
            <button class="btn btn-secondary btn-sm" onclick="document.getElementById('shop-neu-form').style.display='block';this.style.display='none'">+ Kanal hinzufügen</button>
        </div>

        <!-- Neuer Kanal Form (versteckt) -->
        <div id="shop-neu-form" style="display:none;padding:16px;border-bottom:1px solid var(--color-border);background:var(--color-bg)">
            <form method="post" action="speichern.php" enctype="multipart/form-data">
                <input type="hidden" name="tab" value="kanaele_neu">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Slug (eindeutig, keine Leerzeichen)</label>
                        <input type="text" name="neu_slug" class="erp-input" placeholder="bio-wolle" required pattern="[a-z0-9\-]+">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Name</label>
                        <input type="text" name="neu_name" class="erp-input" placeholder="bio-wolle.at" required>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Logo (PNG)</label>
                        <input type="file" name="neu_logo" accept="image/png,image/jpeg,image/webp" class="erp-input" style="padding:4px">
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="submit" class="btn btn-primary btn-sm">Anlegen</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('shop-neu-form').style.display='none';document.querySelector('[onclick*=shop-neu-form]').style.display=''">Abbrechen</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bestehende Shops -->
        <?php foreach ($shops as $shop): ?>
            <form method="post" action="speichern.php" enctype="multipart/form-data" style="padding:16px;border-bottom:1px solid var(--color-border)">
                <input type="hidden" name="tab" value="kanaele_update">
                <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                <div style="display:grid;grid-template-columns:120px 1fr 1fr 120px auto;gap:12px;align-items:center">
                    <!-- Logo -->
                    <div style="text-align:center">
                        <?php if ($shop['logo_pfad'] && file_exists(__DIR__ . '/../' . $shop['logo_pfad'])): ?>
                            <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($shop['logo_pfad']) ?>" style="max-height:40px;max-width:110px">
                        <?php else: ?>
                            <div style="width:110px;height:40px;border:1px dashed var(--color-border);border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--color-text-muted)">kein Logo</div>
                        <?php endif; ?>
                        <div style="margin-top:4px"><input type="file" name="shop_logo" accept="image/png,image/jpeg,image/webp" style="font-size:11px;width:110px"></div>
                    </div>
                    <div>
                        <label class="form-label">Name</label>
                        <input type="text" name="shop_name" class="erp-input" value="<?= htmlspecialchars($shop['name']) ?>" required>
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px">Slug: <code><?= htmlspecialchars($shop['slug']) ?></code></div>
                    </div>
                    <div>
                        <label class="form-label">WooCommerce URL</label>
                        <input type="text" name="wc_url" class="erp-input" placeholder="https://shop.example.com" value="<?= htmlspecialchars($shop['wc_url'] ?? '') ?>">
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;padding-top:18px">
                        <label style="font-size:13px;cursor:pointer">
                            <input type="checkbox" name="sub_marke" value="1" <?= $shop['sub_marke'] ? 'checked' : '' ?>>
                            Sub-Marke
                        </label>
                        <label style="font-size:13px;cursor:pointer">
                            <input type="checkbox" name="ist_aktiv" value="1" <?= $shop['ist_aktiv'] ? 'checked' : '' ?>>
                            Aktiv
                        </label>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                    </div>
                </div>
            </form>
        <?php endforeach; ?>
    </div>

<?php elseif ($aktTab === 'mail'): ?>
    <!-- ═══════════ TAB: MAIL / SMTP ═══════════ -->
    <form method="post" action="speichern.php">
        <input type="hidden" name="tab" value="mail">

        <div class="card" style="margin-bottom:12px">
            <div class="card-header">SMTP-Konfiguration</div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">

                <div class="form-group" style="grid-column:1/3">
                    <label class="form-label">SMTP-Host (z.B. smtp.gmx.at)</label>
                    <input type="text" name="mail_smtp_host" class="erp-input" value="<?= $s('mail_smtp_host') ?>" placeholder="smtp.gmx.at">
                </div>
                <div class="form-group">
                    <label class="form-label">Port</label>
                    <input type="number" name="mail_smtp_port" class="erp-input" value="<?= $s('mail_smtp_port', '587') ?>" style="width:100px">
                </div>
                <div class="form-group">
                    <label class="form-label">Benutzername (meist E-Mail-Adresse)</label>
                    <input type="text" name="mail_smtp_user" class="erp-input" value="<?= $s('mail_smtp_user') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Passwort</label>
                    <input type="password" name="mail_smtp_pass" class="erp-input"
                        value="<?= $s('mail_smtp_pass') ?>" autocomplete="new-password"
                        placeholder="<?= ($rows['mail_smtp_pass'] ?? '') ? '(gespeichert — leer lassen zum Beibehalten)' : '' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Verschlüsselung</label>
                    <select name="mail_smtp_encryption" class="erp-select">
                        <?php foreach (['tls' => 'TLS (Port 587)', 'ssl' => 'SSL (Port 465)', '' => 'Keine'] as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= ($rows['mail_smtp_encryption'] ?? 'tls') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:12px">
            <div class="card-header">Absender</div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label class="form-label">Absender-Name</label>
                    <input type="text" name="mail_from_name" class="erp-input" value="<?= $s('mail_from_name', 'MEALANA KG') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Absender-Adresse</label>
                    <input type="email" name="mail_from_address" class="erp-input" value="<?= $s('mail_from_address') ?>">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label style="font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="mail_aktiv" value="1" <?= ($rows['mail_aktiv'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span>Mailversand aktiv (deaktiviert = Mails werden nicht versendet, außer Test-Adresse ist gesetzt)</span>
                    </label>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">Test-Adresse (optional)</label>
                    <input type="email" name="mail_test_adresse" class="erp-input"
                        value="<?= htmlspecialchars($rows['mail_test_adresse'] ?? '') ?>"
                        placeholder="z.B. jacky@mealana.at"
                        style="max-width:280px">
                    <div style="font-size:12px;color:var(--color-text-muted);margin-top:4px">
                        Wenn Mailversand deaktiviert und eine Test-Adresse eingetragen ist:
                        Mails werden an diese Adresse umgeleitet (Betreff enthält den echten Empfänger).
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-primary">SMTP speichern</button>
        </div>
    </form>

    <div class="card" style="margin-top:12px">
        <div class="card-header">Test-Mail</div>
        <div style="padding:16px;display:flex;gap:12px;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1">
                <label class="form-label">Empfänger-Adresse für Test</label>
                <input type="email" id="test-empfaenger" class="erp-input"
                    placeholder="z.B. jacky@mealana.at"
                    value="<?= htmlspecialchars($_SESSION['benutzer']['email'] ?? $rows['mail_from_address'] ?? '') ?>">
            </div>
            <button type="button" class="btn btn-secondary" onclick="testMail()" id="test-mail-btn" style="white-space:nowrap">Test-Mail senden</button>
        </div>
        <div id="test-mail-result" style="padding:0 16px 12px;font-size:13px;min-height:20px"></div>
    </div>

    <script>
        function testMail() {
            const btn = document.getElementById('test-mail-btn');
            const res = document.getElementById('test-mail-result');
            const an = document.getElementById('test-empfaenger').value.trim();
            btn.disabled = true;
            btn.textContent = 'Sende...';
            res.textContent = '';
            const body = new FormData();
            body.append('test_empfaenger', an);
            fetch('test_mail.php', {
                    method: 'POST',
                    body
                })
                .then(r => r.json())
                .then(d => {
                    res.style.color = d.erfolg ? 'var(--color-success)' : 'var(--color-danger)';
                    res.textContent = d.meldung;
                    btn.disabled = false;
                    btn.textContent = 'Test-Mail senden';
                })
                .catch(() => {
                    res.style.color = 'var(--color-danger)';
                    res.textContent = 'Verbindungsfehler.';
                    btn.disabled = false;
                    btn.textContent = 'Test-Mail senden';
                });
        }
    </script>

<?php elseif ($aktTab === 'system'): ?>
    <!-- ═══════════ TAB: SYSTEM ═══════════ -->
    <form method="post" action="speichern.php">
        <input type="hidden" name="tab" value="system">

        <div class="card" style="margin-bottom:12px">
            <div class="card-header">Preisanzeige</div>
            <div style="padding:16px">
                <div class="form-group">
                    <label class="form-label">Preisanzeige in Auftragsformularen</label>
                    <select name="preisanzeige_auftrag" class="erp-select" style="max-width:260px">
                        <?php foreach (['brutto' => 'Brutto', 'netto' => 'Netto', 'beides' => 'Brutto + Netto'] as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= ($rows['preisanzeige_auftrag'] ?? 'brutto') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:12px">
            <div class="card-header">Steuerrecht</div>
            <div style="padding:16px">
                <label style="font-size:13px;cursor:pointer;display:flex;align-items:flex-start;gap:10px">
                    <input type="checkbox" name="kleinunternehmer" value="1" style="margin-top:2px"
                        <?= ($rows['kleinunternehmer'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <div>
                        <div style="font-weight:600">Kleinunternehmer-Modus (§ 6 UStG AT)</div>
                        <div style="color:var(--color-text-muted);font-size:12px;margin-top:2px">
                            Kein Steuerausweis auf Rechnungen · Einkaufspreise werden brutto verbucht ·
                            Hinweis "Kein Steuerausweis gemäß § 6 Abs. 1 Z 27 UStG" erscheint auf Dokumenten
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <div class="card" style="margin-bottom:12px">
            <div class="card-header">Kundenanzeige</div>
            <div style="padding:16px">
                <div class="form-group">
                    <label class="form-label">Willkommenstext (Idle-Bildschirm)</label>
                    <input type="text" name="kundenanzeige_willkommenstext" class="erp-input" style="width:100%;max-width:480px"
                        value="<?= htmlspecialchars($rows['kundenanzeige_willkommenstext'] ?? '') ?>"
                        placeholder="Herzlich willkommen bei MEALANA KG!">
                </div>
                <label style="font-size:13px;cursor:pointer;display:flex;align-items:flex-start;gap:10px;margin-top:10px">
                    <input type="checkbox" name="kundenanzeige_qr_aktiv" value="1" style="margin-top:2px"
                        <?= ($rows['kundenanzeige_qr_aktiv'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <div>
                        <div style="font-weight:600">QR-Code beim Bezahlen anzeigen</div>
                        <div style="color:var(--color-text-muted);font-size:12px;margin-top:2px">
                            Platzhalter bis zum Paperless-Rechnung-Modul — zeigt schon jetzt das geteilte Layout (QR links, Betrag rechts) statt der zentrierten Ansicht.
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-primary">System-Einstellungen speichern</button>
        </div>
    </form>
<?php elseif ($aktTab === 'kassen'): ?>
    <!-- ═══════════ TAB: KASSEN ═══════════ -->
    <?php
    $kassen = $db->query("
    SELECT k.*, l.name AS lager_name
    FROM kassen k
    LEFT JOIN lager l ON l.id = k.lager_id
    ORDER BY k.id
")->fetchAll(PDO::FETCH_ASSOC);

    $bons = $db->query("
    SELECT 
        kb.id,
        kb.kasse_id,
        kb.bon_nr,
        kb.typ,
        kb.bruttobetrag,
        b.formularname,
        kb.erstellt_am,
        kb.gedruckt
    FROM kassen_bons kb 
    JOIN benutzer b ON b.id = kb.benutzer_id
    WHERE kb.typ IN ('z_bon','x_bon')
    ORDER BY kb.erstellt_am DESC
")->fetchAll();

    $bonsByKasse = [];
    foreach ($bons as $bon) {
        $bonsByKasse[$bon['kasse_id']][] = $bon;
    }
    ?>

    <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
        <a href="kasse_edit.php?neu=1" class="btn btn-primary btn-sm">+ Neue Kasse</a>
    </div>

    <div class="card">
        <div class="card-header">Registrierkassen</div>
        <table class="erp-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:60px">Nr.</th>
                    <th>Name</th>
                    <th>Lager</th>
                    <th style="width:100px">Modus</th>
                    <th style="width:80px">RKSV-ID</th>
                    <th style="width:60px">Bon-Logo</th>
                    <th style="width:60px">Aktiv</th>
                    <th style="width:80px"></th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kassen as $k): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($k['kasse_nr']) ?></code></td>
                        <td><?= htmlspecialchars($k['name']) ?></td>
                        <td><?= htmlspecialchars($k['lager_name'] ?? '—') ?></td>
                        <td>
                            <span style="font-size:11px;padding:2px 8px;border-radius:10px;
                        background:<?= $k['modus'] === 'online' ? 'var(--color-success-bg,#e8f5e9)' : '#fff3e0' ?>;
                        color:<?= $k['modus'] === 'online' ? 'var(--color-success)' : '#e67e22' ?>">
                                <?= $k['modus'] === 'online' ? 'Online' : 'Offline' ?>
                            </span>
                        </td>
                        <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($k['rksv_kassen_id'] ?? '—') ?></td>
                        <td style="text-align:center"><?= $k['bon_logo'] ? '✓' : '—' ?></td>
                        <td style="text-align:center"><?= $k['aktiv'] ? '✓' : '—' ?></td>
                        <td>
                            <a href="kasse_edit.php?id=<?= $k['id'] ?>" class="btn btn-secondary btn-sm">Bearbeiten</a>
                        </td>
                        <td>
                            <button onclick="var el=document.getElementById('bons-<?= $k['id'] ?>');el.style.display=el.style.display==='none'?'table-row':'none'">▼ Bons</button>
                        </td>
                    </tr>
                    <tr id="bons-<?= $k['id'] ?>" style="display:none">
                        <?php if (empty($bonsByKasse[$k['id']])) { ?>
                            <!-- hier: if leer, sonst Mini-Tabelle -->
                            <td colspan="9" style="text-align:center;color:var(--color-text-muted);padding:20px">Noch keine X/Z-Bons erzeugt.</td>
                        <?php } else { ?>
                            <td colspan="9">
                                <table class="erp-table" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>X/Z-Bon</th>
                                            <th>Bonnummer</th>
                                            <th>Datum</th>
                                            <th>Bruttobetrag</th>
                                            <th>Benutzer</th>
                                            <th>gedruckt</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bonsByKasse[$k['id']] as $bons): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($bons['typ'] === 'x_bon' ? 'X-Bon' : 'Z-Bon') ?></td>
                                                <td><?= htmlspecialchars($bons['bon_nr']) ?></td>
                                                <td><?= htmlspecialchars($bons['erstellt_am']) ?></td>
                                                <td><?= htmlspecialchars(number_format($bons['bruttobetrag'], 2, ',', '.') . ' €') ?></td>
                                                <td><?= htmlspecialchars($bons['formularname'] ?? '—') ?></td>
                                                <td><?= htmlspecialchars($bons['gedruckt'] ? '✓' : '—') ?></td>
                                                <td>
                                                    <a href="<?= BASE_PATH ?>/kasse/bon_druck.php?id=<?= $bons['id'] ?>" target="_blank"
                                                        class="erp-btn erp-btn-secondary">
                                                        X/Z-Bon <?= htmlspecialchars($bons['bon_nr']) ?> drucken
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>

                        <?php } ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($kassen)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;color:var(--color-text-muted);padding:20px">Noch keine Kassen angelegt.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($aktTab === 'nummernkreise'): ?>
    <!-- ═══════════ TAB: NUMMERNKREISE ═══════════ -->
    <?php
    $nummernTypLabels = [
        'auftrag'      => 'Auftrag',
        'rechnung'     => 'Rechnung',
        'gutschrift'   => 'Gutschrift',
        'lieferschein' => 'Lieferschein',
        'mietrechnung' => 'Mietrechnung',
        'abrechnung'   => 'Abrechnung (Partner)',
        'pickliste'    => 'Pickliste',
    ];
    $dokNummern = $db->query("SELECT * FROM dokument_nummern ORDER BY typ, jahr DESC")->fetchAll(PDO::FETCH_ASSOC);

    $kassenBonStand = $db->query("
        SELECT k.id, k.kasse_nr, k.name,
               COUNT(kb.id) AS anzahl_heuer
        FROM kassen k
        LEFT JOIN kassen_bons kb ON kb.kasse_id = k.id AND YEAR(kb.erstellt_am) = YEAR(CURDATE())
        WHERE k.aktiv = 1
        GROUP BY k.id, k.kasse_nr, k.name
        ORDER BY k.kasse_nr
    ")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="card" style="margin-bottom:12px">
        <div class="card-header">Dokument-Nummernkreise (Auftrag, Rechnung, Gutschrift, ...)</div>
        <table class="erp-table" style="width:100%">
            <thead>
                <tr>
                    <th>Typ</th>
                    <th style="width:80px">Jahr</th>
                    <th style="width:100px">Präfix</th>
                    <th style="width:120px">Letzte Nr.</th>
                    <th style="width:200px">Nächste Nummer wäre</th>
                    <th style="width:100px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dokNummern as $dn): ?>
                <tr>
                    <td><?= htmlspecialchars($nummernTypLabels[$dn['typ']] ?? $dn['typ']) ?></td>
                    <td><?= (int) $dn['jahr'] ?></td>
                    <td><code><?= htmlspecialchars($dn['praefix']) ?></code></td>
                    <td><?= (int) $dn['letzt_nr'] ?></td>
                    <td style="font-family:monospace;color:var(--color-text-muted)">
                        <?= htmlspecialchars($dn['praefix']) ?>-<?= (int) $dn['jahr'] ?>-<?= str_pad((string)($dn['letzt_nr'] + 1), 5, '0', STR_PAD_LEFT) ?>
                    </td>
                    <td>
                        <button class="btn btn-secondary btn-sm"
                            onclick="nummernkreisBearbeiten(<?= $dn['id'] ?>, '<?= htmlspecialchars($nummernTypLabels[$dn['typ']] ?? $dn['typ'], ENT_QUOTES) ?>', <?= (int) $dn['jahr'] ?>, '<?= htmlspecialchars($dn['praefix'], ENT_QUOTES) ?>', <?= (int) $dn['letzt_nr'] ?>)">
                            ✏ Bearbeiten
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($dokNummern)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:20px">Noch keine Nummernkreise angelegt — werden automatisch beim ersten Beleg des jeweiligen Jahres erstellt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header">Kassenbon-Nummernkreise (nicht konfigurierbar)</div>
        <div style="padding:12px 16px;font-size:12px;color:var(--color-text-muted)">
            Kassenbons laufen über einen eigenen Mechanismus (Format <code>Kassennummer-Jahr-Laufnummer</code>,
            pro Kasse und Jahr gezählt) — kein fester Nummernkreis-Datensatz, daher hier nur zur Übersicht:
        </div>
        <table class="erp-table" style="width:100%">
            <thead>
                <tr>
                    <th>Kasse</th>
                    <th>Name</th>
                    <th style="width:160px">Bons heuer</th>
                    <th style="width:200px">Nächste Nummer wäre</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($kassenBonStand as $kb): ?>
                <tr>
                    <td><code><?= htmlspecialchars($kb['kasse_nr']) ?></code></td>
                    <td><?= htmlspecialchars($kb['name']) ?></td>
                    <td><?= (int) $kb['anzahl_heuer'] ?></td>
                    <td style="font-family:monospace;color:var(--color-text-muted)">
                        <?= htmlspecialchars($kb['kasse_nr']) ?>-<?= date('Y') ?>-<?= str_pad((string)($kb['anzahl_heuer'] + 1), 6, '0', STR_PAD_LEFT) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal: Nummernkreis bearbeiten -->
    <div id="nummernkreis-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;padding:24px;width:360px;box-shadow:0 4px 24px rgba(0,0,0,.2)">
            <div style="font-weight:700;font-size:14px;color:var(--color-nav);margin-bottom:16px" id="nk-modal-titel">Nummernkreis bearbeiten</div>
            <form method="POST" action="nummernkreis_aktualisieren.php">
                <input type="hidden" name="id" id="nk-id">

                <label class="erp-label">Präfix</label>
                <input type="text" name="praefix" id="nk-praefix" class="erp-input" style="width:100%;margin-bottom:12px" maxlength="10" required>

                <label class="erp-label">Letzte vergebene Nummer</label>
                <input type="number" name="letzt_nr" id="nk-letzt-nr" class="erp-input" style="width:100%;margin-bottom:4px" min="0" required>
                <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:16px">
                    ⚠ Vorsicht: Der nächste Beleg bekommt "Letzte Nummer + 1". Nur ändern wenn wirklich nötig
                    (z.B. Korrektur nach einem Fehler) — nie eine Nummer vergeben, die schon mal existiert hat.
                </div>

                <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" onclick="document.getElementById('nummernkreis-modal').style.display='none'" class="btn btn-secondary btn-sm">Abbrechen</button>
                    <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function nummernkreisBearbeiten(id, typLabel, jahr, praefix, letztNr) {
        document.getElementById('nk-id').value = id;
        document.getElementById('nk-modal-titel').textContent = typLabel + ' ' + jahr + ' bearbeiten';
        document.getElementById('nk-praefix').value = praefix;
        document.getElementById('nk-letzt-nr').value = letztNr;
        document.getElementById('nummernkreis-modal').style.display = 'flex';
    }
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>