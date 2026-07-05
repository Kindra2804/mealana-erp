<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
require_once __DIR__ . '/../src/modules/benutzer/PasswortResetService.php';

$service    = new PasswortResetService();
$token      = trim($_GET['token'] ?? $_POST['token'] ?? '');
$istPost    = $_SERVER['REQUEST_METHOD'] === 'POST';
$fehler     = null;
$erfolg     = false;
$tokenDefekt = false; // true = Token fehlt/ungültig/abgelaufen -> kein Formular, nur Link zu "neu anfordern"

if ($token === '') {
    $fehler      = 'Kein Token angegeben.';
    $tokenDefekt = true;
} elseif ($istPost) {
    $ergebnis = $service->setzeNeuesPasswort($token, $_POST['neu'] ?? '', $_POST['wdh'] ?? '');
    if ($ergebnis['erfolg']) {
        $erfolg = true;
    } else {
        $fehler = implode(' ', $ergebnis['fehler']);
    }
} elseif (!$service->validiereToken($token)) {
    $fehler      = 'Der Link ist ungültig oder abgelaufen.';
    $tokenDefekt = true;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Passwort setzen – MeaLana ERP</title>
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
        .erfolg {
            background: #e8f8ef; border-left: 3px solid #2ecc71; padding: 12px 14px;
            border-radius: 4px; font-size: 13px; color: #1e7a44; margin-bottom: 18px; line-height: 1.5;
        }
        .fehler {
            background: #fde8e8; border-left: 3px solid #c0392b; padding: 10px 12px;
            border-radius: 4px; font-size: 13px; color: #c0392b; margin-bottom: 18px;
        }
        label {
            display: block; font-size: 12px; font-weight: 600; color: #555;
            margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        input[type=password] {
            width: 100%; padding: 10px 12px; border: 1px solid #d0d7e0; border-radius: 4px;
            font-size: 15px; margin-bottom: 16px; outline: none;
        }
        button[type=submit] {
            width: 100%; background: #4a7cb5; color: #fff; border: none; border-radius: 4px;
            padding: 11px; font-size: 15px; font-weight: 600; cursor: pointer;
        }
        button[type=submit]:hover { background: #3a6aa0; }
        a.zurueck { display: block; text-align: center; margin-top: 16px; font-size: 13px; color: #4a7cb5; text-decoration: none; }
        a.zurueck:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Passwort setzen</h1>

        <?php if ($erfolg): ?>
            <div class="erfolg">Passwort erfolgreich gesetzt. Du kannst dich jetzt anmelden.</div>
            <a class="zurueck" href="<?= BASE_PATH ?>/login.php">→ Zum Login</a>
        <?php elseif ($tokenDefekt): ?>
            <div class="fehler"><?= htmlspecialchars($fehler) ?></div>
            <a class="zurueck" href="<?= BASE_PATH ?>/passwort_vergessen.php">Neuen Link anfordern</a>
        <?php else: ?>
            <?php if ($fehler): ?>
                <div class="fehler"><?= htmlspecialchars($fehler) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <label for="neu">Neues Passwort</label>
                <input type="password" id="neu" name="neu" required autocomplete="new-password">
                <label for="wdh">Wiederholung</label>
                <input type="password" id="wdh" name="wdh" required autocomplete="new-password">
                <button type="submit">Speichern</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
