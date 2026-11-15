<?php
// Newsletter: fertiger Code für das Anmeldeformular des gewählten Shops (Woo-Shortcode bzw. HTML für JTL/andere).
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/PaperlessService.php';

$db     = Database::getInstance();
$shops  = $db->query("SELECT id, slug, name, wc_url, website_url, design_farbe FROM shops WHERE ist_aktiv = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$shopId = (int)($_GET['shop_id'] ?? ($shops[0]['id'] ?? 0));
$shop   = null;
foreach ($shops as $s) if ((int)$s['id'] === $shopId) $shop = $s;
$portal = PaperlessService::konfig()['url'];
$farbe  = $shop && preg_match('/^#[0-9a-f]{6}$/i', (string)$shop['design_farbe']) ? $shop['design_farbe'] : '#2c3e50';
$web    = $shop ? rtrim(($shop['website_url'] ?: $shop['wc_url']) ?: '', '/') : '';

$shortcode = $shop ? '[nahtlos_newsletter portal="' . $portal . '" shop="' . $shop['slug'] . '" name="' . $shop['name'] . '"]' : '';
$zustimmung = $shop ? 'Ja, ich möchte den Newsletter von ' . $shop['name'] . ' per E-Mail erhalten. Abmelden kann ich mich jederzeit über den Link in jedem Newsletter.' : '';
$html = $shop ? '<!-- Newsletter-Anmeldung ' . $shop['name'] . ' (NAHTLOS) -->
<form method="post" action="' . $portal . '/newsletter.php" style="max-width:560px;font-family:inherit">
  <input type="hidden" name="shop" value="' . $shop['slug'] . '">
  <input type="hidden" name="zurueck" value="' . ($web ?: 'https://') . '/">
  <div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0">
    <input type="text" name="vorname" placeholder="Vorname (optional)" maxlength="100" style="flex:1 1 160px;padding:10px">
    <input type="email" name="email" placeholder="E-Mail-Adresse" required maxlength="200" style="flex:1 1 200px;padding:10px">
    <button type="submit" style="background:' . $farbe . ';color:#fff;border:0;border-radius:6px;padding:10px 18px;font-weight:700;cursor:pointer">Anmelden</button>
  </div>
  <label style="display:flex;gap:8px;font-size:13px;line-height:1.5">
    <input type="checkbox" name="zustimmung" value="' . htmlspecialchars($zustimmung) . '" required>
    <span>' . htmlspecialchars($zustimmung) . ' Mehr in der <a href="' . ($web ?: '') . '/datenschutz/">Datenschutzerklärung</a>.</span>
  </label>
</form>' : '';

$pageTitle        = 'Newsletter: Anmeldeformular';
$activeModule     = 'kunden';
$actionBarContent = '<a href="empfaenger.php?shop_id=' . $shopId . '" class="btn btn-secondary btn-sm">← Empfänger</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="max-width:900px">
<div class="card" style="margin-bottom:12px;padding:12px 16px">
    <form method="get" style="display:flex;gap:10px;align-items:center">
        <select name="shop_id" class="erp-select" onchange="this.form.submit()">
            <?php foreach ($shops as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $shopId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
        </select>
        <span style="font-size:13px;color:var(--color-text-muted)">Website: <?= htmlspecialchars($web ?: '— (Einstellungen → Kanäle)') ?> · Hausfarbe <span style="display:inline-block;width:14px;height:14px;border-radius:3px;vertical-align:middle;background:<?= htmlspecialchars($farbe) ?>"></span></span>
    </form>
    <?php if ($portal === ''): ?>
        <div style="color:var(--color-danger);margin-top:8px;font-size:13px">Die Belegseite ist noch nicht eingerichtet (Einstellungen → System → Paperless). Ohne sie funktioniert die Anmeldung nicht.</div>
    <?php endif; ?>
</div>

<?php if ($shop): ?>
<div class="card" style="margin-bottom:12px">
    <div class="card-header">WooCommerce-Shop (NAHTLOS Connector ab 1.8.0)</div>
    <div style="padding:12px 16px;font-size:13px">
        In WordPress auf der gewünschten Seite (z.B. Startseite oder Fußbereich) einen <strong>Shortcode-Block</strong> einfügen:
        <pre style="background:var(--color-bg-secondary,#f6f8fa);padding:10px;border-radius:6px;white-space:pre-wrap;margin-top:8px"><?= htmlspecialchars($shortcode) ?></pre>
        Optional: <code>titel="…"</code>, <code>text="…"</code>, <code>knopf="…"</code>, <code>datenschutz="/datenschutz/"</code>.
    </div>
</div>
<div class="card">
    <div class="card-header">Andere Shops (z.B. JTL-Shop, mealana.at)</div>
    <div style="padding:12px 16px;font-size:13px">
        Diesen HTML-Code dort einfügen, wo das Formular stehen soll (JTL: Admin → Inhalte/Boxen → eigene Box bzw. Eigene Seite → HTML).
        Nach der Anmeldung leitet die Belegseite zur Startseite zurück. Prüfe die Adresse der Datenschutzerklärung.
        <textarea class="erp-input" rows="14" style="width:100%;font-family:monospace;font-size:12px;margin-top:8px" readonly onclick="this.select()"><?= htmlspecialchars($html) ?></textarea>
        <div style="margin-top:10px;font-weight:600">Vorschau</div>
        <div style="border:1px dashed var(--color-border);border-radius:8px;padding:12px;margin-top:6px"><?= $html ?></div>
    </div>
</div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
