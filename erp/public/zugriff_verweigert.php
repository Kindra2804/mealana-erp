<?php
require_once __DIR__ . '/../config/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/core/Auth.php';
Auth::check();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kein Zugriff – MeaLana ERP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #1e2a38;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: sans-serif;
        }
        .karte {
            background: #fff;
            border-radius: 8px;
            padding: 40px 44px 36px;
            width: 380px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.35);
        }
        .icon { font-size: 40px; margin-bottom: 12px; }
        h1 { font-size: 18px; color: #1e3a5f; margin-bottom: 10px; }
        p { font-size: 14px; color: #555; line-height: 1.5; margin-bottom: 24px; }
        a.button {
            display: block;
            background: #4a7cb5;
            color: #fff;
            border-radius: 4px;
            padding: 11px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s;
        }
        a.button:hover { background: #3a6aa0; }
    </style>
</head>
<body>
    <div class="karte">
        <div class="icon">🔒</div>
        <h1>Kein Zugriff</h1>
        <p>Dein Benutzerkonto hat für diesen Bereich keine Berechtigung. Wenn du glaubst, dass das nicht stimmt, wende dich an deinen Administrator.</p>
        <a class="button" href="<?= BASE_PATH ?>/start.php">Zurück zur Übersicht</a>
    </div>
</body>
</html>
