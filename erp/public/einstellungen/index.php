<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/artikel/EinheitenRepository.php';

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
            'shopsync' => 'Shop-Synchronisierung',
            'mail'     => 'Mail / SMTP',
            'system'   => 'System',
            'kassen'   => 'Kassen',
            'nummernkreise' => 'Nummernkreise',
            'einheiten' => 'Einheiten',
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
                // Hauptlogo = erster angelegter Kanal (kleinste id), nicht an einen
                // fixen Slug gebunden — $shops ist bereits "ORDER BY id" geladen.
                $logoShop = $shops[0] ?? null;
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
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;margin-top:10px">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">WooCommerce URL</label>
                        <input type="text" name="neu_wc_url" class="erp-input" placeholder="https://shop-test.deinedomain.at">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">REST-API Consumer Key</label>
                        <input type="password" name="neu_wc_key" class="erp-input" placeholder="ck_..." autocomplete="off">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">REST-API Consumer Secret</label>
                        <input type="password" name="neu_wc_secret" class="erp-input" placeholder="cs_..." autocomplete="off">
                    </div>
                    <div></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;margin-top:10px">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">WordPress-Benutzername (für Bilder-Upload)</label>
                        <input type="text" name="neu_wp_username" class="erp-input" placeholder="tatsächlicher Login-Name, NICHT das App-Passwort-Label" autocomplete="off">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">WordPress Application-Passwort</label>
                        <input type="password" name="neu_wp_app_password" class="erp-input" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" autocomplete="off">
                    </div>
                    <div></div>
                    <div></div>
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
                <div style="display:grid;grid-template-columns:120px 1fr 1fr 120px auto;gap:12px;align-items:end;margin-top:10px">
                    <div></div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">REST-API Consumer Key</label>
                        <input type="password" name="wc_key" class="erp-input" placeholder="ck_..." value="<?= htmlspecialchars($shop['wc_key'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">REST-API Consumer Secret</label>
                        <input type="password" name="wc_secret" class="erp-input" placeholder="cs_..." value="<?= htmlspecialchars($shop['wc_secret'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div></div>
                    <div></div>
                </div>
                <div style="display:grid;grid-template-columns:120px 1fr 1fr 120px auto;gap:12px;align-items:end;margin-top:10px">
                    <div></div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">WordPress-Benutzername (für Bilder-Upload)</label>
                        <input type="text" name="wp_username" class="erp-input" placeholder="tatsächlicher Login-Name, NICHT das App-Passwort-Label" value="<?= htmlspecialchars($shop['wp_username'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">WordPress Application-Passwort</label>
                        <input type="password" name="wp_app_password" class="erp-input" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" value="<?= htmlspecialchars($shop['wp_app_password'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div></div>
                    <div></div>
                </div>
            </form>
        <?php endforeach; ?>
    </div>

<?php elseif ($aktTab === 'shopsync'): ?>
    <!-- ═══════════ TAB: SHOP-SYNCHRONISIERUNG ═══════════ -->
    <?php
    require_once __DIR__ . '/../../src/modules/shop/ShopSyncRepository.php';
    require_once __DIR__ . '/shop_sync_aktivitaeten_render.php';
    $syncShops = array_filter($shops, fn($sh) => !empty($sh['wc_url']) && !empty($sh['wc_key']) && !empty($sh['wc_secret']));

    $letzteLaeufe = $db->query("
        SELECT a.referenz_id AS shop_id, a.aktion, a.details, a.erstellt_am, a.stufe
        FROM aktivitaeten a
        WHERE a.referenz_tabelle = 'shops'
          AND a.aktion IN ('shop.sync_lauf', 'shop.cron_fehler', 'shop.komplettabgleich_gestartet', 'shop.bilder_ftp_gestartet')
        ORDER BY a.erstellt_am DESC
        LIMIT 40
    ")->fetchAll(PDO::FETCH_ASSOC);
    $laeufeProShop = [];
    foreach ($letzteLaeufe as $lauf) {
        $laeufeProShop[(int)$lauf['shop_id']][] = $lauf;
    }
    ?>

    <?php if (empty($syncShops)): ?>
        <div class="card"><div style="padding:20px;color:var(--color-text-muted)">Noch kein Shop mit vollständiger WooCommerce-Anbindung (URL + Key + Secret) im Tab "Kanäle" hinterlegt.</div></div>
    <?php endif; ?>

    <?php foreach ($syncShops as $sh): ?>
        <div class="card" style="margin-bottom:16px" data-shopsync-card data-shop-id="<?= $sh['id'] ?>">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <span><?= htmlspecialchars($sh['name']) ?> <code style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars($sh['slug']) ?></code></span>
                <span data-status-badge style="font-size:11px;padding:2px 8px;border-radius:10px;
                    <?= $sh['bulk_import_aktiv'] ? 'background:#fff3e0;color:#e67e22' : ($sh['sync_pausiert'] ? 'background:#f5f5f5;color:var(--color-text-muted)' : 'background:var(--color-success-bg,#e8f5e9);color:var(--color-success)') ?>">
                    <?= $sh['bulk_import_aktiv'] ? 'Vorgang läuft…' : ($sh['sync_pausiert'] ? 'Automatischer Sync pausiert' : 'Bereit') ?>
                </span>
            </div>
            <div style="padding:16px;display:flex;gap:16px;align-items:end;flex-wrap:wrap">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Batch-Größe</label>
                    <input type="number" data-batch-groesse class="erp-input" value="200" min="1" style="width:100px">
                </div>
                <label style="font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;padding-bottom:8px">
                    <input type="checkbox" data-mit-bildern checked>
                    mit Bildern
                </label>
                <button type="button" class="btn btn-primary btn-sm" data-btn-start
                    <?= $sh['bulk_import_aktiv'] ? 'disabled' : '' ?>>Komplettabgleich starten</button>
                <button type="button" class="btn btn-secondary btn-sm" data-btn-pause data-pausiert="<?= $sh['sync_pausiert'] ?>">
                    <?= $sh['sync_pausiert'] ? 'Automatischen Sync fortsetzen' : 'Automatischen Sync pausieren' ?>
                </button>
                <div style="font-size:11px;color:var(--color-text-muted)">
                    Der 15-Minuten-Cron läuft unabhängig weiter (kleine Häppchen) — der Komplettabgleich hier holt einen großen Rückstau zügig auf und pausiert bei Bedarf automatisch bei einer Rate-Limit-Sperre.
                </div>
            </div>
            <div style="padding:0 16px 16px;display:flex;gap:16px;align-items:end;flex-wrap:wrap;border-top:1px solid var(--color-border);padding-top:16px">
                <div class="form-group" style="margin:0;flex:1;min-width:280px">
                    <label class="form-label">Bilder-Basis-URL (nach FTP-Upload von <code>public/uploads/artikel/</code>)</label>
                    <input type="text" data-bilder-basis-url class="erp-input" style="width:100%"
                        placeholder="https://mealana.at/wp-content/uploads/mealana-erstimport"
                        value="<?= htmlspecialchars($sh['bilder_basis_url'] ?? '') ?>">
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-btn-bilder-ftp
                    <?= $sh['bulk_import_aktiv'] ? 'disabled' : '' ?>>Bilder-Verknüpfung starten</button>
            </div>
            <pre data-log-tail style="display:<?= $sh['bulk_import_aktiv'] ? 'block' : 'none' ?>;margin:0 16px 16px;padding:10px 12px;background:#1e1e1e;color:#d4d4d4;font-size:11px;max-height:220px;overflow:auto;border-radius:4px"></pre>

            <div data-aktivitaeten-block style="border-top:1px solid var(--color-border);padding:10px 16px;<?= empty($laeufeProShop[$sh['id']]) ? 'display:none' : '' ?>">
                <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:6px">Letzte Cron-/Komplettabgleich-Aktivität</div>
                <div data-aktivitaeten-liste><?= renderShopSyncAktivitaetenZeilen($laeufeProShop[$sh['id']] ?? []) ?></div>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
    (function() {
        function poll(card, shopId, logName) {
            fetch('shop_sync_status.php?shop_id=' + shopId + '&log=' + encodeURIComponent(logName || ''))
                .then(r => r.json())
                .then(d => {
                    if (!d.erfolg) return;
                    const badge     = card.querySelector('[data-status-badge]');
                    const pre       = card.querySelector('[data-log-tail]');
                    const btnStart  = card.querySelector('[data-btn-start]');
                    const btnBilder = card.querySelector('[data-btn-bilder-ftp]');
                    if (d.log_tail) {
                        pre.style.display = 'block';
                        pre.textContent = d.log_tail;
                        pre.scrollTop = pre.scrollHeight;
                    }
                    const aktBlock = card.querySelector('[data-aktivitaeten-block]');
                    const aktListe = card.querySelector('[data-aktivitaeten-liste]');
                    if (d.aktivitaeten_html !== undefined) {
                        aktListe.innerHTML = d.aktivitaeten_html;
                        aktBlock.style.display = d.aktivitaeten_html ? '' : 'none';
                    }
                    if (d.laeuft) {
                        badge.textContent = 'Vorgang läuft…';
                        badge.style.background = '#fff3e0';
                        badge.style.color = '#e67e22';
                        btnStart.disabled = true;
                        btnBilder.disabled = true;
                        // d.log: falls logName leer war (z.B. nach Seiten-Reload),
                        // hat shop_sync_status.php den Namen aus der DB nachgeliefert --
                        // ab jetzt direkt verwenden statt jedes Mal neu nachzuschlagen.
                        setTimeout(() => poll(card, shopId, d.log || logName), 3000);
                    } else {
                        badge.textContent = 'Bereit';
                        badge.style.background = 'var(--color-success-bg,#e8f5e9)';
                        badge.style.color = 'var(--color-success)';
                        btnStart.disabled = false;
                        btnBilder.disabled = false;
                    }
                });
        }

        document.querySelectorAll('[data-shopsync-card]').forEach(card => {
            const shopId = card.dataset.shopId;

            card.querySelector('[data-btn-start]').addEventListener('click', () => {
                const batch  = card.querySelector('[data-batch-groesse]').value || 200;
                const bilder = card.querySelector('[data-mit-bildern]').checked ? '1' : '0';
                const body = new FormData();
                body.append('shop_id', shopId);
                body.append('batch_groesse', batch);
                body.append('mit_bildern', bilder);
                fetch('shop_sync_start.php', { method: 'POST', body })
                    .then(r => r.json())
                    .then(d => {
                        if (!d.erfolg) { alert(d.meldung); return; }
                        poll(card, shopId, d.log);
                    });
            });

            card.querySelector('[data-btn-bilder-ftp]').addEventListener('click', () => {
                const basisUrl = card.querySelector('[data-bilder-basis-url]').value.trim();
                const body = new FormData();
                body.append('shop_id', shopId);
                body.append('basis_url', basisUrl);
                fetch('shop_sync_bilder_ftp_start.php', { method: 'POST', body })
                    .then(r => r.json())
                    .then(d => {
                        if (!d.erfolg) { alert(d.meldung); return; }
                        poll(card, shopId, d.log);
                    });
            });

            card.querySelector('[data-btn-pause]').addEventListener('click', () => {
                const btn = card.querySelector('[data-btn-pause]');
                const neuerStatus = btn.dataset.pausiert === '1' ? '0' : '1';
                const body = new FormData();
                body.append('shop_id', shopId);
                body.append('pausiert', neuerStatus);
                fetch('shop_sync_pause.php', { method: 'POST', body })
                    .then(r => r.json())
                    .then(d => {
                        if (!d.erfolg) { alert(d.meldung || 'Fehler'); return; }
                        btn.dataset.pausiert = neuerStatus;
                        btn.textContent = neuerStatus === '1' ? 'Automatischen Sync fortsetzen' : 'Automatischen Sync pausieren';
                        const badge = card.querySelector('[data-status-badge]');
                        if (btn.closest('[data-shopsync-card]').querySelector('[data-btn-start]').disabled) return; // läuft gerade, Badge bleibt orange
                        badge.textContent = neuerStatus === '1' ? 'Automatischer Sync pausiert' : 'Bereit';
                        badge.style.background = neuerStatus === '1' ? '#f5f5f5' : 'var(--color-success-bg,#e8f5e9)';
                        badge.style.color = neuerStatus === '1' ? 'var(--color-text-muted)' : 'var(--color-success)';
                    });
            });

            // Läuft von einem früheren Start (z.B. Seite neu geladen) noch was?
            // Ohne bekannten Log-Dateinamen weiter -- Status-Endpunkt liefert
            // dann einfach keinen log_tail, "läuft"-Flag funktioniert trotzdem.
            if (card.querySelector('[data-btn-start]').disabled) {
                poll(card, shopId, '');
            }
        });
    })();
    </script>

    <div class="card" style="margin-top:4px">
        <div class="card-header">Bilder per FTP statt Byte-Upload (schneller Weg für große Bildmengen)</div>
        <div style="padding:16px;font-size:12px;line-height:1.6;color:var(--color-text-muted)">
            Der Komplettabgleich oben lädt Bilder standardmäßig einzeln per Byte-Upload hoch (langsam,
            ca. 0,4s Pause je Bild). Bei sehr vielen Bildern geht es schneller: "ohne Bilder" abgleichen,
            dann den kompletten lokalen Ordner <code>public/uploads/artikel/</code> <strong>1:1</strong> per FTP auf den
            Webserver kopieren (Ordnerstruktur und Dateinamen müssen exakt gleich bleiben — die Zuordnung
            läuft über Artikel-ID + Dateiname, kein Umbenennen/Umsortieren). Danach bei jedem Shop oben
            die Bilder-Basis-URL eintragen (die Adresse, unter der der hochgeladene <code>artikel/</code>-Ordner
            beim jeweiligen Shop erreichbar ist, z.B. <code>https://mealana.at/wp-content/uploads/mealana-erstimport</code>)
            und auf "Bilder-Verknüpfung starten" klicken — läuft im Hintergrund genau wie der
            Komplettabgleich, kein Kommandozeilen-Zugriff mehr nötig. Bereits verknüpfte
            Bilder werden dabei automatisch übersprungen — der ganze Ordner kann also jederzeit gefahrlos
            erneut hochgeladen/laufen gelassen werden. Voraussetzung: der Text-Sync muss für die Artikel
            schon gelaufen sein (ohne WooCommerce-Produkt-ID kann kein Bild angehängt werden).
            <br><br>
            <strong>Kleinerer Shop mit nur einem Teilsortiment?</strong> Statt den kompletten Ordner
            hochzuladen (unnötig viel Datenvolumen, wenn der Shop nur einen Bruchteil aller Artikel führt):
            <code>php scripts/bilder_export_fuer_shop.php &lt;shop-slug&gt;</code> kopiert lokal nur die
            Bilder-Ordner der Artikel, die für diesen Shop im Kanal aktiv sind, nach
            <code>storage/shop_export/&lt;shop-slug&gt;/artikel/</code> — nur diesen (kleineren) Ordner per FTP hochladen.
        </div>
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
            <div class="card-header">Versand / Lieferschein</div>
            <div style="padding:16px">
                <label style="font-size:13px;cursor:pointer;display:flex;align-items:flex-start;gap:10px">
                    <input type="checkbox" name="lieferschein_charge_anzeigen" value="1" style="margin-top:2px"
                        <?= ($rows['lieferschein_charge_anzeigen'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <div>
                        <div style="font-weight:600">Charge auf Lieferschein anzeigen</div>
                        <div style="color:var(--color-text-muted);font-size:12px;margin-top:2px">
                            Zeigt bei chargenpflichtigen Artikeln die Chargennummer unter der Positionsbezeichnung — z.B. damit Kunden bei Nachbestellung gezielt dieselbe Färbung verlangen können. Intern (Auftrags-Detailseite) ist die Charge immer sichtbar, unabhängig von dieser Einstellung.
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

<?php elseif ($aktTab === 'einheiten'): ?>
    <!-- ═══════════ TAB: EINHEITEN ═══════════ -->
    <?php $einheitenListe = (new EinheitenRepository())->findAllMitVerwendung(); ?>

    <div class="card" style="margin-bottom:12px">
        <div class="card-header">Neue Einheit anlegen</div>
        <form method="post" action="speichern.php">
            <input type="hidden" name="tab" value="einheiten_neu">
            <div style="padding:16px;display:grid;grid-template-columns:2fr 1fr 100px auto;gap:12px;align-items:end">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="erp-input" placeholder="z.B. Strang" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Kürzel</label>
                    <input type="text" name="kuerzel" class="erp-input" placeholder="z.B. Str" maxlength="10">
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Sortierung</label>
                    <input type="number" name="sortierung" class="erp-input" value="0">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Anlegen</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">Bestehende Einheiten</div>
        <div style="display:grid;grid-template-columns:2fr 1fr 100px 80px auto;gap:12px;padding:10px 16px;font-size:12px;color:var(--color-text-muted);border-bottom:1px solid var(--color-border)">
            <div>Name</div><div>Kürzel</div><div>Sortierung</div><div>Artikel</div><div></div>
        </div>
        <?php foreach ($einheitenListe as $eh): ?>
            <form method="post" action="speichern.php" style="display:grid;grid-template-columns:2fr 1fr 100px 80px auto;gap:12px;padding:10px 16px;border-bottom:1px solid var(--color-border);align-items:center">
                <input type="hidden" name="tab" value="einheiten_update">
                <input type="hidden" name="id" value="<?= $eh['id'] ?>">
                <input type="text" name="name" class="erp-input" value="<?= htmlspecialchars($eh['name']) ?>" required>
                <input type="text" name="kuerzel" class="erp-input" value="<?= htmlspecialchars($eh['kuerzel'] ?? '') ?>" maxlength="10">
                <input type="number" name="sortierung" class="erp-input" value="<?= (int) $eh['sortierung'] ?>">
                <span style="color:var(--color-text-muted)"><?= (int) $eh['artikel_anzahl'] ?></span>
                <div style="display:flex;gap:6px">
                    <button type="submit" class="btn btn-secondary btn-sm">Speichern</button>
                </div>
            </form>
            <?php if ((int) $eh['artikel_anzahl'] === 0): ?>
            <form method="post" action="speichern.php" onsubmit="return confirm('Einheit &quot;<?= htmlspecialchars($eh['name'], ENT_QUOTES) ?>&quot; wirklich löschen?')" style="padding:0 16px 10px;margin-top:-6px;border-bottom:1px solid var(--color-border)">
                <input type="hidden" name="tab" value="einheiten_loeschen">
                <input type="hidden" name="id" value="<?= $eh['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--color-danger);font-size:11px">Löschen</button>
            </form>
            <?php else: ?>
            <div style="padding:0 16px 10px;margin-top:-6px;border-bottom:1px solid var(--color-border);font-size:11px;color:var(--color-text-muted)">
                🔒 Wird von <?= (int) $eh['artikel_anzahl'] ?> Artikel(n) verwendet — kann nicht gelöscht werden
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($einheitenListe)): ?>
            <div style="text-align:center;color:var(--color-text-muted);padding:20px">Noch keine Einheiten angelegt.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>