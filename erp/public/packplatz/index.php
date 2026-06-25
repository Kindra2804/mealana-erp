<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Packplatz';
require_once __DIR__ . '/shell_top.php';
?>

<div style="text-align:center;margin-top:40px;margin-bottom:30px">
    <div style="font-size:28px;font-weight:700;color:#e94560">Was möchtest du tun?</div>
</div>

<div class="pp-kacheln" style="max-width:900px;margin:0 auto">

    <a href="/mealana/packplatz/warenausgang/index.php" class="pp-kachel">
        <span class="pp-kachel-icon">📤</span>
        <div class="pp-kachel-label">Warenausgang</div>
        <div class="pp-kachel-sub">Pickliste verpacken · Auftrag verpacken</div>
    </a>

    <a href="/mealana/lager/wareneingang.php" class="pp-kachel">
        <span class="pp-kachel-icon">📥</span>
        <div class="pp-kachel-label">Wareneingang</div>
        <div class="pp-kachel-sub">Mit Bestellung · Freier WE</div>
    </a>

    <a href="/mealana/packplatz/intern/index.php" class="pp-kachel disabled">
        <span class="pp-kachel-icon">🔧</span>
        <div class="pp-kachel-label">Intern</div>
        <div class="pp-kachel-sub">Artikelzustand ändern</div>
    </a>

    <a href="/mealana/packplatz/retoure/index.php" class="pp-kachel disabled">
        <span class="pp-kachel-icon">↩️</span>
        <div class="pp-kachel-label">Retoure</div>
        <div class="pp-kachel-sub">Retourenverarbeitung</div>
    </a>

</div>

<div style="text-align:center;margin-top:60px">
    <a href="/mealana/auftraege/liste.php" style="color:#555;font-size:13px;text-decoration:none">
        → Zurück zum ERP-System
    </a>
    &nbsp;&nbsp;·&nbsp;&nbsp;
    <a href="/mealana/logout.php" style="color:#555;font-size:13px;text-decoration:none">Abmelden</a>
</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
