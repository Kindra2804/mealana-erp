<?php
// Superadmin-Übergabe — ohne Anmeldung erreichbar (Fall: niemand kommt mehr rein).
// Schritt 1: Übergabe-Schlüssel von NAHTLOS einfügen/hochladen → Schritt 2: neuen Superadmin wählen
// oder anlegen, bisherige herabstufen oder deaktivieren.
require_once __DIR__ . '/../config/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/modules/benutzer/SuperadminUebergabe.php';

$svc    = new SuperadminUebergabe();
$fehler = null;
$fertig = false;
$aktion = $_POST['aktion'] ?? '';

if ($aktion === 'pruefen') {
    $inhalt = trim($_POST['schluessel'] ?? '');
    if (!empty($_FILES['datei']['tmp_name']) && is_uploaded_file($_FILES['datei']['tmp_name'])) {
        $inhalt = trim(file_get_contents($_FILES['datei']['tmp_name']));
    }
    $p = $svc->pruefeSchluessel($inhalt);
    if ($p['ok']) {
        $_SESSION['su_schluessel'] = $inhalt;
    } else {
        unset($_SESSION['su_schluessel']);
        $fehler = $p['fehler'];
    }
} elseif ($aktion === 'abbrechen') {
    unset($_SESSION['su_schluessel']);
} elseif ($aktion === 'durchfuehren' && !empty($_SESSION['su_schluessel'])) {
    $ziel = ($_POST['ziel'] ?? '') === 'neu'
        ? ['neu' => [
            'username'     => $_POST['neu_username'] ?? '',
            'formularname' => $_POST['neu_formularname'] ?? '',
            'email'        => $_POST['neu_email'] ?? '',
            'passwort'     => $_POST['neu_passwort'] ?? '',
          ]]
        : ['benutzer_id' => (int)($_POST['ziel'] ?? 0)];
    if (isset($ziel['neu']) && ($_POST['neu_passwort'] ?? '') !== ($_POST['neu_passwort_wdh'] ?? '')) {
        $fehler = 'Die Passwörter stimmen nicht überein.';
    } else {
        $erg = $svc->durchfuehren($_SESSION['su_schluessel'], $ziel, $_POST['alt'] ?? [], $_SERVER['REMOTE_ADDR'] ?? null);
        if ($erg['erfolg']) {
            unset($_SESSION['su_schluessel']);
            $fertig = true;
        } else {
            $fehler = $erg['fehler'];
        }
    }
}

// Schlüssel weiterhin gültig? (z.B. nach Fehler im zweiten Schritt)
$schluessel = null;
if (!$fertig && !empty($_SESSION['su_schluessel'])) {
    $p = $svc->pruefeSchluessel($_SESSION['su_schluessel']);
    if ($p['ok']) {
        $schluessel = $p['daten'];
    } else {
        unset($_SESSION['su_schluessel']);
        $fehler ??= $p['fehler'];
    }
}
$benutzer = $schluessel ? $svc->benutzer() : [];
$rollen   = $schluessel ? $svc->rollenUnterSuperadmin() : [];
$superadmins = array_values(array_filter($benutzer, fn($b) => $b['rolle'] === 'superadmin'));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin-Übergabe – NAHTLOS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #1e2a38; min-height: 100vh; display: flex; align-items: flex-start; justify-content: center;
               font-family: sans-serif; padding: 40px 16px; }
        .karte { background: #fff; border-radius: 8px; padding: 32px 36px; width: 100%; max-width: 620px; box-shadow: 0 8px 32px rgba(0,0,0,.35); }
        h1 { font-size: 19px; color: #1e2a38; margin-bottom: 8px; }
        h2 { font-size: 14px; color: #1e2a38; margin: 20px 0 8px; text-transform: uppercase; letter-spacing: .5px; }
        p, li { font-size: 13px; color: #555; line-height: 1.5; }
        p { margin-bottom: 12px; }
        .fehler { background: #fde8e8; border-left: 3px solid #c0392b; padding: 10px 12px; border-radius: 4px; font-size: 13px; color: #c0392b; margin-bottom: 16px; }
        .erfolg { background: #e8f8ef; border-left: 3px solid #2ecc71; padding: 12px 14px; border-radius: 4px; font-size: 14px; color: #1e7a44; margin-bottom: 16px; line-height: 1.5; }
        .info { background: #eef4fb; border-left: 3px solid #4a7cb5; padding: 10px 12px; border-radius: 4px; font-size: 13px; color: #1e3a5f; margin-bottom: 14px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
        input[type=text], input[type=email], input[type=password], textarea, select {
            width: 100%; padding: 8px 10px; border: 1px solid #d0d7e0; border-radius: 4px; font-size: 14px; margin-bottom: 10px; }
        textarea { font-family: monospace; font-size: 12px; }
        .wahl { display: flex; gap: 8px; align-items: center; padding: 7px 10px; border: 1px solid #e3e8ef; border-radius: 4px; margin-bottom: 6px; font-size: 14px; cursor: pointer; }
        .wahl small { color: #888; }
        .neu-felder { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; margin: 8px 0 0 26px; }
        .alt { border: 1px solid #e3e8ef; border-radius: 4px; padding: 10px; margin-bottom: 8px; font-size: 14px; }
        .alt-zeile { display: flex; gap: 14px; align-items: center; margin-top: 6px; flex-wrap: wrap; font-size: 13px; }
        .alt-zeile select { width: auto; margin: 0; }
        .knoepfe { display: flex; gap: 10px; margin-top: 18px; }
        button { background: #4a7cb5; color: #fff; border: none; border-radius: 4px; padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer; }
        button.zweit { background: #e3e8ef; color: #1e2a38; }
        a { color: #4a7cb5; font-size: 13px; }
    </style>
</head>
<body>
<div class="karte">
    <h1>Superadmin-Übergabe</h1>

    <?php if ($fertig): ?>
        <div class="erfolg">Erledigt — der neue Superadmin ist eingerichtet. Bisherige Superadmins wurden umgestellt,
            ihre laufenden Anmeldungen enden beim nächsten Klick.</div>
        <a href="<?= BASE_PATH ?>/login.php">→ Zur Anmeldung</a>

    <?php elseif (!$schluessel): ?>
        <p>Mit einem <strong>Übergabe-Schlüssel von NAHTLOS</strong> wird ein neuer Superadmin festgelegt, z.B. wenn der
            bisherige nicht mehr verfügbar ist. Den Schlüssel stellt NAHTLOS nur nach schriftlicher Anfrage des
            Inhabers bzw. der Geschäftsführung aus. Er gilt nur für diese Firma, ist einige Tage gültig und lässt sich nur einmal verwenden.</p>
        <?php if ($fehler): ?><div class="fehler"><?= htmlspecialchars($fehler) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="aktion" value="pruefen">
            <label for="datei">Schlüssel-Datei</label>
            <input type="file" id="datei" name="datei" accept=".txt,.json,.key" style="margin-bottom:10px">
            <label for="schluessel">… oder Inhalt einfügen</label>
            <textarea id="schluessel" name="schluessel" rows="6"></textarea>
            <div class="knoepfe"><button type="submit">Schlüssel prüfen</button></div>
        </form>
        <p style="margin-top:16px"><a href="<?= BASE_PATH ?>/login.php">← Zurück zur Anmeldung</a></p>

    <?php else: ?>
        <div class="info">Schlüssel <strong><?= htmlspecialchars($schluessel['schluessel_nr'] ?? '') ?></strong> gültig für
            <?= htmlspecialchars($schluessel['firma']) ?> bis <?= date('d.m.Y', strtotime($schluessel['gueltig_bis'])) ?>.</div>
        <?php if ($fehler): ?><div class="fehler"><?= htmlspecialchars($fehler) ?></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="aktion" value="durchfuehren">

            <h2>1 · Neuer Superadmin</h2>
            <?php foreach ($benutzer as $b): ?>
                <label class="wahl">
                    <input type="radio" name="ziel" value="<?= (int)$b['id'] ?>" <?= ($_POST['ziel'] ?? '') == $b['id'] ? 'checked' : '' ?>>
                    <?= htmlspecialchars($b['formularname']) ?> <small><?= htmlspecialchars($b['username']) ?> · <?= htmlspecialchars($b['rolle'] ?? 'ohne Rolle') ?></small>
                </label>
            <?php endforeach; ?>
            <label class="wahl">
                <input type="radio" name="ziel" value="neu" <?= ($_POST['ziel'] ?? '') === 'neu' ? 'checked' : '' ?>> Neuen Benutzer anlegen
            </label>
            <div class="neu-felder">
                <div><label for="nu">Benutzername</label><input type="text" id="nu" name="neu_username" value="<?= htmlspecialchars($_POST['neu_username'] ?? '') ?>"></div>
                <div><label for="nf">Anzeigename</label><input type="text" id="nf" name="neu_formularname" value="<?= htmlspecialchars($_POST['neu_formularname'] ?? '') ?>"></div>
                <div style="grid-column:1/3"><label for="ne">E-Mail</label><input type="email" id="ne" name="neu_email" value="<?= htmlspecialchars($_POST['neu_email'] ?? '') ?>"></div>
                <div><label for="np">Passwort (mind. 8 Zeichen)</label><input type="password" id="np" name="neu_passwort"></div>
                <div><label for="nw">Passwort wiederholen</label><input type="password" id="nw" name="neu_passwort_wdh"></div>
            </div>

            <?php if ($superadmins): ?>
                <h2>2 · Bisherige Superadmins</h2>
                <p>Werden nie gelöscht (Protokolle, Belege). Wird jemand oben als neuer Superadmin gewählt, bleibt er es.</p>
                <?php foreach ($superadmins as $sa): $w = $_POST['alt'][$sa['id']] ?? 'rolle:' . ($rollen[0]['id'] ?? ''); ?>
                    <div class="alt">
                        <strong><?= htmlspecialchars($sa['formularname']) ?></strong> <small style="color:#888"><?= htmlspecialchars($sa['username']) ?></small>
                        <div class="alt-zeile">
                            <label style="display:flex;gap:6px;align-items:center;margin:0;font-weight:normal">
                                <input type="radio" name="alt_modus[<?= (int)$sa['id'] ?>]" value="rolle" <?= $w !== 'deaktivieren' ? 'checked' : '' ?>
                                       onchange="altSetzen(<?= (int)$sa['id'] ?>)"> herabstufen auf
                            </label>
                            <select id="altrolle<?= (int)$sa['id'] ?>" onchange="altSetzen(<?= (int)$sa['id'] ?>)">
                                <?php foreach ($rollen as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= $w === 'rolle:' . $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label style="display:flex;gap:6px;align-items:center;margin:0;font-weight:normal">
                                <input type="radio" name="alt_modus[<?= (int)$sa['id'] ?>]" value="deaktivieren" <?= $w === 'deaktivieren' ? 'checked' : '' ?>
                                       onchange="altSetzen(<?= (int)$sa['id'] ?>)"> deaktivieren
                            </label>
                            <input type="hidden" name="alt[<?= (int)$sa['id'] ?>]" id="alt<?= (int)$sa['id'] ?>" value="<?= htmlspecialchars($w) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="knoepfe">
                <button type="submit" onclick="return confirm('Superadmin jetzt übergeben? Der Schlüssel ist danach verbraucht.')">Übergabe durchführen</button>
                <button type="submit" class="zweit" name="aktion" value="abbrechen" formnovalidate>Abbrechen</button>
            </div>
        </form>
        <script>
        // Auswahl "herabstufen auf <Rolle>" / "deaktivieren" in ein Feld je Benutzer zusammenführen
        function altSetzen(id) {
            var modus = document.querySelector('input[name="alt_modus[' + id + ']"]:checked').value;
            document.getElementById('alt' + id).value = modus === 'deaktivieren'
                ? 'deaktivieren' : 'rolle:' + document.getElementById('altrolle' + id).value;
        }
        </script>
    <?php endif; ?>
</div>
</body>
</html>
