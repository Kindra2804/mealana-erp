<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

$service   = new KassenService();
$kasseInfo = $service->getKasse(1);
$pageTitle = 'Kasse — Startportal';
require_once __DIR__ . '/shell_top.php';

$istMesse = ($kasseInfo['modus'] ?? 'online') === 'offline';
?>

<div style="text-align:center;margin:24px 0 28px">
  <div style="display:inline-block;background:#fff;border-radius:12px;padding:10px 18px 6px;margin-bottom:10px;box-shadow:0 4px 20px rgba(0,0,0,.08)">
    <img src="<?= BASE_PATH ?>/img/nahtlos.png" alt="NahtlOS" style="width:110px;height:auto;display:block">
  </div>
  <div style="font-size:16px;color:#888;margin-top:6px;display:flex;align-items:center;justify-content:center;gap:12px">
    <span><?= htmlspecialchars($kasseInfo['name'] ?? 'Hauptkasse') ?> · <?= date('d.m.Y') ?></span>
    <?php if ($istMesse): ?>
      <span style="font-size:12px;font-weight:700;padding:3px 12px;border-radius:12px;background:#fff3e0;color:#e67e22;letter-spacing:.5px">MESSEBETRIEB</span>
    <?php else: ?>
      <span style="font-size:12px;font-weight:700;padding:3px 12px;border-radius:12px;background:#e8f5e9;color:#2e7d32;letter-spacing:.5px">ONLINE</span>
    <?php endif; ?>
  </div>
</div>

<div class="ks-kacheln" style="max-width:900px;margin:0 auto">

  <a href="<?= BASE_PATH ?>/kasse/bon.php" class="ks-kachel">
    <span class="ks-kachel-icon">🛒</span>
    <div class="ks-kachel-label">Kassieren</div>
    <div class="ks-kachel-sub">Artikel scannen · Bar · Karte · Gutschein</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/offene_auswahl.php" class="ks-kachel">
    <span class="ks-kachel-icon">↗️</span>
    <div class="ks-kachel-label">Mitgeben</div>
    <div class="ks-kachel-sub">Artikel zur Ansicht · Farbvergleich</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/kassenbuch.php" class="ks-kachel">
    <span class="ks-kachel-icon">💰</span>
    <div class="ks-kachel-label">Kassenbuch</div>
    <div class="ks-kachel-sub">Einlage · Entnahme</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/kassensturz.php" class="ks-kachel">
    <span class="ks-kachel-icon">📊</span>
    <div class="ks-kachel-label">Kassenstand</div>
    <div class="ks-kachel-sub">X-Bon · Z-Bon · Zählhilfe</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/bon_journal.php" class="ks-kachel">
    <span class="ks-kachel-icon">📋</span>
    <div class="ks-kachel-label">Bon-Journal</div>
    <div class="ks-kachel-sub">Alle Bons · Suche · Storno</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/kassen_einstellungen.php" class="ks-kachel">
    <span class="ks-kachel-icon">⚙</span>
    <div class="ks-kachel-label">Einstellungen</div>
    <div class="ks-kachel-sub">Ausgabeformat · Modus</div>
  </a>

</div>

<div style="text-align:center;margin-top:28px">
  <a href="<?= BASE_PATH ?>/start.php" style="color:#444;font-size:13px;text-decoration:none">→ Startseite</a>
  &nbsp;&nbsp;·&nbsp;&nbsp;
  <a href="<?= BASE_PATH ?>/logout.php" style="color:#444;font-size:13px;text-decoration:none">Abmelden</a>
  &nbsp;&nbsp;·&nbsp;&nbsp;
  <span style="color:#bbb;font-size:12px">v<?= htmlspecialchars(APP_VERSION) ?></span>
</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
