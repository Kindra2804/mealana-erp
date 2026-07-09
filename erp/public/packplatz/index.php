<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/packplatz/RuecklagerungRepository.php';

$offeneRuecklagerungen = (new RuecklagerungRepository())->zaehleOffene();

$pageTitle = 'Packplatz';
require_once __DIR__ . '/shell_top.php';
?>

<div style="text-align:center;margin-top:24px;margin-bottom:22px">
    <div style="display:inline-block;background:#fff;border-radius:12px;padding:10px 18px 6px;margin-bottom:10px;box-shadow:0 4px 20px rgba(0,0,0,.25)">
        <img src="<?= BASE_PATH ?>/img/nahtlos.png" alt="NahtlOS" style="width:110px;height:auto;display:block">
    </div>
    <div style="font-size:28px;font-weight:700;color:#e94560">Was möchtest du tun?</div>
</div>

<div class="pp-kacheln" style="max-width:900px;margin:0 auto">

    <a href="<?= BASE_PATH ?>/packplatz/warenausgang/index.php" class="pp-kachel">
        <span class="pp-kachel-icon">📤</span>
        <div class="pp-kachel-label">Warenausgang</div>
        <div class="pp-kachel-sub">Pickliste verpacken · Auftrag verpacken</div>
    </a>

    <a href="<?= BASE_PATH ?>/packplatz/wareneingang/index.php" class="pp-kachel">
        <span class="pp-kachel-icon">📥</span>
        <div class="pp-kachel-label">Wareneingang</div>
        <div class="pp-kachel-sub">Mit Bestellung · Freier WE</div>
    </a>

    <a href="<?= BASE_PATH ?>/packplatz/intern/index.php" class="pp-kachel">
        <span class="pp-kachel-icon">🔧</span>
        <div class="pp-kachel-label">Intern</div>
        <div class="pp-kachel-sub">Lagerumbuchung · Zustand</div>
    </a>

    <a href="<?= BASE_PATH ?>/packplatz/retoure/index.php" class="pp-kachel">
        <span class="pp-kachel-icon">↩️</span>
        <div class="pp-kachel-label">Retoure</div>
        <div class="pp-kachel-sub">Rückbuchen · GS · Ersatz</div>
    </a>

    <a href="<?= BASE_PATH ?>/packplatz/ruecklagerungen.php" class="pp-kachel" style="position:relative">
        <?php if ($offeneRuecklagerungen > 0): ?>
            <span style="position:absolute;top:10px;right:10px;background:#e94560;color:#fff;font-size:12px;font-weight:700;border-radius:10px;padding:2px 9px">
                <?= $offeneRuecklagerungen ?>
            </span>
        <?php endif; ?>
        <span class="pp-kachel-icon">📦↩</span>
        <div class="pp-kachel-label">Rücklagerungen</div>
        <div class="pp-kachel-sub">Kassen-Retouren einbuchen</div>
    </a>

</div>

<div style="text-align:center;margin-top:28px">
    <a href="<?= BASE_PATH ?>/start.php" style="color:#555;font-size:13px;text-decoration:none">→ Startseite</a>
    &nbsp;&nbsp;·&nbsp;&nbsp;
    <a href="<?= BASE_PATH ?>/logout.php" style="color:#555;font-size:13px;text-decoration:none">Abmelden</a>
    &nbsp;&nbsp;·&nbsp;&nbsp;
    <span style="color:#666;font-size:12px">v<?= htmlspecialchars(APP_VERSION) ?></span>
</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
