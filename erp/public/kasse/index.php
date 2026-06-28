<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

$service   = new KassenService();
$kasseInfo = $service->getKasse(1);
$pageTitle = 'Kasse — Startportal';
require_once __DIR__ . '/shell_top.php';

$istMesse = ($kasseInfo['modus'] ?? 'online') === 'offline';
?>

<div style="text-align:center;margin:50px 0 36px">
  <div style="font-size:32px;font-weight:900;color:#e67e22">MeaLana Kasse</div>
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

  <a href="/mealana/kasse/bon.php" class="ks-kachel">
    <span class="ks-kachel-icon">🛒</span>
    <div class="ks-kachel-label">Kassieren</div>
    <div class="ks-kachel-sub">Artikel scannen · Bar · Karte · Gutschein</div>
  </a>

  <a href="/mealana/kasse/offene_auswahl.php" class="ks-kachel">
    <span class="ks-kachel-icon">↗️</span>
    <div class="ks-kachel-label">Mitgeben</div>
    <div class="ks-kachel-sub">Artikel zur Ansicht · Farbvergleich</div>
  </a>

  <a href="/mealana/kasse/kassenbuch.php" class="ks-kachel">
    <span class="ks-kachel-icon">💰</span>
    <div class="ks-kachel-label">Kassenbuch</div>
    <div class="ks-kachel-sub">Einlage · Entnahme</div>
  </a>

  <a href="/mealana/kasse/kassensturz.php" class="ks-kachel">
    <span class="ks-kachel-icon">📊</span>
    <div class="ks-kachel-label">Kassenstand</div>
    <div class="ks-kachel-sub">X-Bon · Z-Bon · Zählhilfe</div>
  </a>

  <a href="/mealana/kasse/bon_journal.php" class="ks-kachel">
    <span class="ks-kachel-icon">📋</span>
    <div class="ks-kachel-label">Bon-Journal</div>
    <div class="ks-kachel-sub">Alle Bons · Suche · Storno</div>
  </a>

</div>

<div style="text-align:center;margin-top:60px">
  <a href="/mealana/start.php" style="color:#444;font-size:13px;text-decoration:none">→ Startseite</a>
  &nbsp;&nbsp;·&nbsp;&nbsp;
  <a href="/mealana/logout.php" style="color:#444;font-size:13px;text-decoration:none">Abmelden</a>
</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
