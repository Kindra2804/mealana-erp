<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
require_once __DIR__ . '/../src/core/Auth.php';

if (!empty($_SESSION['benutzer']['id'])) {
    header('Location: ' . BASE_PATH . '/start.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $passwort = $_POST['passwort'] ?? '';
    if (Auth::login($username, $passwort)) {
        header('Location: ' . BASE_PATH . '/start.php');
        exit;
    } else {
        $_SESSION['fehler'] = 'Ungültige Anmeldedaten!';
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }
}

$fehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['fehler']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Anmelden – MeaLana ERP</title>
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
        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-logo img {
            width: 150px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .fehler {
            background: #fde8e8;
            border-left: 3px solid #c0392b;
            padding: 10px 12px;
            border-radius: 4px;
            font-size: 13px;
            color: #c0392b;
            margin-bottom: 18px;
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
        input[type=text], input[type=password] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d0d7e0;
            border-radius: 4px;
            font-size: 15px;
            margin-bottom: 16px;
            outline: none;
            transition: border-color .15s;
        }
        input[type=text]:focus, input[type=password]:focus {
            border-color: #4a7cb5;
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
            margin-top: 4px;
            transition: background .15s;
        }
        button[type=submit]:hover { background: #3a6aa0; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <img src="<?= BASE_PATH ?>/img/nahtlos.png" alt="NahtlOS – ERP für Handarbeitsgeschäfte">
        </div>

        <?php if ($fehler): ?>
            <div class="fehler"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_PATH ?>/login.php">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" autofocus autocomplete="username">

            <label for="passwort">Passwort</label>
            <input type="password" id="passwort" name="passwort" autocomplete="current-password">

            <button type="submit">Anmelden</button>
        </form>
        <a href="<?= BASE_PATH ?>/passwort_vergessen.php" style="display:block;text-align:center;margin-top:16px;font-size:13px;color:#4a7cb5;text-decoration:none">Passwort vergessen?</a>
    </div>
</body>
</html>
