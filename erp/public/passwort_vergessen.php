<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
require_once __DIR__ . '/../src/modules/benutzer/PasswortResetService.php';

$abgeschickt = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email !== '') {
        // Verrät absichtlich nie, ob die E-Mail existiert — immer dieselbe Meldung unten.
        (new PasswortResetService())->angefordertFuerEmail($email);
    }
    $abgeschickt = true;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Passwort vergessen – MeaLana ERP</title>
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
        .login-card {
            background: #fff;
            border-radius: 8px;
            padding: 40px 44px 36px;
            width: 360px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.35);
        }
        h1 { font-size: 18px; margin-bottom: 18px; color: #1e2a38; }
        p.hinweis { font-size: 13px; color: #555; margin-bottom: 18px; line-height: 1.5; }
        .erfolg {
            background: #e8f8ef;
            border-left: 3px solid #2ecc71;
            padding: 12px 14px;
            border-radius: 4px;
            font-size: 13px;
            color: #1e7a44;
            margin-bottom: 18px;
            line-height: 1.5;
        }
        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input[type=email] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d0d7e0;
            border-radius: 4px;
            font-size: 15px;
            margin-bottom: 16px;
            outline: none;
        }
        button[type=submit] {
            width: 100%;
            background: #4a7cb5;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 11px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
        button[type=submit]:hover { background: #3a6aa0; }
        a.zurueck { display: block; text-align: center; margin-top: 16px; font-size: 13px; color: #4a7cb5; text-decoration: none; }
        a.zurueck:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Passwort vergessen</h1>

        <?php if ($abgeschickt): ?>
            <div class="erfolg">Falls diese E-Mail-Adresse einem Konto zugeordnet ist, wurde ein Link zum Passwort-Setzen verschickt.</div>
        <?php else: ?>
            <p class="hinweis">Gib deine hinterlegte E-Mail-Adresse ein — wir schicken dir einen Link zum Passwort setzen.</p>
            <form method="POST">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" required autofocus>
                <button type="submit">Link anfordern</button>
            </form>
        <?php endif; ?>

        <a class="zurueck" href="<?= BASE_PATH ?>/login.php">← Zurück zum Login</a>
    </div>
</body>
</html>
