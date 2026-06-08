<?php
session_start();
require_once __DIR__ . '/../src/core/Auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $passwort = $_POST['passwort'] ?? '';

    if (Auth::login($username, $passwort)) {
        header('Location: /mealana/artikel/liste.php');
        exit;
    } else {
        $_SESSION['fehler'] = 'Ungültige Anmeldedaten!';
        header('Location: login.php');
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
    <title>Login – MeaLana ERP</title>
    <link rel="stylesheet" href="/mealana/css/app.css">
</head>

<body>
    <h1>Login</h1>

    <?php if (!empty($fehler)): ?>
        <div class="fehler-box">
            <strong>ACHTUNG:</strong>
            <p><?= htmlspecialchars($fehler) ?></p>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">


        <div>

            <label>Benutzername:</label>
            <input type="text" name="username"
                value="">

            <label>Passwort:</label>
            <input type="password" name="passwort"
                value="">
        </div>

        <button type="submit">Einloggen</button>

    </form>
</body>

</html>