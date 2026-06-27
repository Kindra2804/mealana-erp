<?php
require_once __DIR__ . '/includes/auth_check.php';

$benutzer = Auth::benutzer();
$stunde   = (int) date('G');
$gruss    = $stunde < 11 ? 'Guten Morgen' : ($stunde < 17 ? 'Guten Tag' : 'Guten Abend');

$kacheln = [
    [
        'titel'  => 'ERP',
        'icon'   => '📦',
        'text'   => 'Artikel, Lager, Einkauf, Verkauf',
        'href'   => '/mealana/artikel/liste.php',
        'farbe'  => '#4a7cb5',
    ],
    [
        'titel'  => 'Kasse',
        'icon'   => '🛒',
        'text'   => 'POS · Kassenbuch · Tagesabschluss',
        'href'   => '/mealana/kasse/index.php',
        'farbe'  => '#2e7d54',
    ],
    [
        'titel'  => 'Packplatz',
        'icon'   => '📬',
        'text'   => 'Versand · Retouren · Picklisten',
        'href'   => '/mealana/packplatz/index.php',
        'farbe'  => '#7b5ea7',
    ],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Start – MeaLana ERP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #1e2a38;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: sans-serif;
            padding: 24px;
        }
        .start-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .start-logo {
            font-size: 18px;
            font-weight: 700;
            color: #90aecb;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .start-gruss {
            font-size: 28px;
            font-weight: 300;
            color: #fff;
        }
        .start-gruss strong {
            font-weight: 700;
        }
        .start-frage {
            font-size: 14px;
            color: #90aecb;
            margin-top: 6px;
        }
        .kacheln {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .kachel {
            background: #fff;
            border-radius: 10px;
            width: 200px;
            padding: 32px 20px 28px;
            text-align: center;
            text-decoration: none;
            color: #1e2a38;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            transition: transform .15s, box-shadow .15s;
            display: block;
        }
        .kachel:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 28px rgba(0,0,0,0.4);
        }
        .kachel-icon {
            font-size: 40px;
            margin-bottom: 14px;
            filter: grayscale(0);
        }
        .kachel-titel {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .kachel-text {
            font-size: 12px;
            color: #666;
            line-height: 1.5;
        }
        .kachel-balken {
            height: 4px;
            border-radius: 0 0 10px 10px;
            margin: 20px -20px -28px;
        }
        .logout-link {
            margin-top: 48px;
            font-size: 13px;
            color: #5a7a9a;
            text-decoration: none;
        }
        .logout-link:hover { color: #90aecb; }
    </style>
</head>
<body>
    <div class="start-header">
        <div class="start-logo">MeaLana ERP</div>
        <div class="start-gruss"><?= $gruss ?>, <strong><?= htmlspecialchars($benutzer['formularname']) ?></strong></div>
        <div class="start-frage">Womit möchtest du beginnen?</div>
    </div>

    <div class="kacheln">
        <?php foreach ($kacheln as $k): ?>
        <a href="<?= $k['href'] ?>" class="kachel">
            <div class="kachel-icon"><?= $k['icon'] ?></div>
            <div class="kachel-titel"><?= $k['titel'] ?></div>
            <div class="kachel-text"><?= $k['text'] ?></div>
            <div class="kachel-balken" style="background:<?= $k['farbe'] ?>"></div>
        </a>
        <?php endforeach; ?>
    </div>

    <a href="/mealana/logout.php" class="logout-link">Abmelden</a>
</body>
</html>
