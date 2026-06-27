<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db      = Database::getInstance();
$ich     = Auth::benutzer();
$meldung = null;
$fehler  = null;

// ── Stammdaten speichern ─────────────────────────────────────────────────────
if (isset($_POST['aktion']) && $_POST['aktion'] === 'stammdaten') {
    $vorname      = trim($_POST['vorname']      ?? '');
    $nachname     = trim($_POST['nachname']     ?? '');
    $formularname = trim($_POST['formularname'] ?? '');
    $email        = trim($_POST['email']        ?? '') ?: null;

    if ($formularname === '') {
        $fehler = 'Formularname darf nicht leer sein.';
    } else {
        $stmt = $db->prepare("
            UPDATE benutzer
            SET vorname = :v, nachname = :n, formularname = :f, email = :e
            WHERE id = :id
        ");
        $stmt->execute([
            'v'  => $vorname,
            'n'  => $nachname,
            'f'  => $formularname,
            'e'  => $email,
            'id' => $ich['id'],
        ]);
        $_SESSION['benutzer']['formularname'] = $formularname;
        $meldung = 'Stammdaten gespeichert.';
    }
}

// ── Passwort ändern ───────────────────────────────────────────────────────────
if (isset($_POST['aktion']) && $_POST['aktion'] === 'passwort') {
    $aktuell    = $_POST['aktuell']    ?? '';
    $neu        = $_POST['neu']        ?? '';
    $bestaetign = $_POST['bestaetign'] ?? '';

    $row = $db->prepare("SELECT passwort FROM benutzer WHERE id = :id");
    $row->execute(['id' => $ich['id']]);
    $hash = $row->fetchColumn();

    if (!password_verify($aktuell, $hash)) {
        $fehler = 'Aktuelles Passwort ist falsch.';
    } elseif (strlen($neu) < 8) {
        $fehler = 'Neues Passwort muss mindestens 8 Zeichen haben.';
    } elseif ($neu !== $bestaetign) {
        $fehler = 'Neues Passwort und Bestätigung stimmen nicht überein.';
    } else {
        $stmt = $db->prepare("UPDATE benutzer SET passwort = :pw WHERE id = :id");
        $stmt->execute(['pw' => password_hash($neu, PASSWORD_BCRYPT), 'id' => $ich['id']]);
        $meldung = 'Passwort erfolgreich geändert.';
    }
}

// Aktuelle Daten laden
$zeile = $db->prepare("SELECT vorname, nachname, formularname, email FROM benutzer WHERE id = :id");
$zeile->execute(['id' => $ich['id']]);
$daten = $zeile->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="erp-content">
    <div style="display:flex;align-items:baseline;gap:12px;margin-bottom:20px">
        <h1>Mein Profil</h1>
        <span style="color:#888;font-size:13px">@<?= htmlspecialchars($ich['username'] ?? '') ?></span>
    </div>

    <?php if ($meldung): ?>
        <div class="success-banner" id="banner-ok"><?= htmlspecialchars($meldung) ?></div>
        <script>
            setTimeout(() => document.getElementById('banner-ok').style.display = 'none', 3000)
        </script>
    <?php endif; ?>
    <?php if ($fehler): ?>
        <div class="error-banner"><?= htmlspecialchars($fehler) ?></div>
    <?php endif; ?>

    <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">

        <!-- Stammdaten -->
        <div class="card" style="flex:1;min-width:280px">
            <h2 style="margin-bottom:16px;font-size:16px">Stammdaten</h2>
            <form method="POST">
                <input type="hidden" name="aktion" value="stammdaten">
                <div class="form-group">
                    <label class="form-label">Vorname</label>
                    <input class="erp-input" type="text" name="vorname" value="<?= htmlspecialchars($daten['vorname'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Nachname</label>
                    <input class="erp-input" type="text" name="nachname" value="<?= htmlspecialchars($daten['nachname'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Formularname <span style="color:red">*</span></label>
                    <input class="erp-input" type="text" name="formularname" value="<?= htmlspecialchars($daten['formularname'] ?? '') ?>" required>
                    <small style="color:#888;font-size:12px">Wird in der Shell und auf Dokumenten angezeigt</small>
                </div>
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">E-Mail</label>
                    <input class="erp-input" type="email" name="email" value="<?= htmlspecialchars($daten['email'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </form>
        </div>

        <!-- Passwort -->
        <div class="card" style="flex:1;min-width:280px">
            <h2 style="margin-bottom:16px;font-size:16px">Passwort ändern</h2>
            <form method="POST">
                <input type="hidden" name="aktion" value="passwort">
                <div class="form-group">
                    <label class="form-label">Aktuelles Passwort</label>
                    <input class="erp-input" type="password" name="aktuell" autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label">Neues Passwort</label>
                    <input class="erp-input" type="password" name="neu" autocomplete="new-password">
                    <small style="color:#888;font-size:12px">Mindestens 8 Zeichen</small>
                </div>
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Bestätigung</label>
                    <input class="erp-input" type="password" name="bestaetign" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary">Passwort ändern</button>
            </form>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>