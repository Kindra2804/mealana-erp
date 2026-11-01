<?php
// Einrichtungs-Assistent (Onboarding): Pflicht-Schritte, ohne die das ERP nicht läuft, + empfohlene Schritte je Lizenz
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/einrichtung/OnboardingService.php';
require_once __DIR__ . '/../../src/core/Firmenlogo.php';

$istSuperadmin = ($_SESSION['benutzer']['rolle_rang'] ?? 0) >= 100;
if ($istSuperadmin) OnboardingService::standardlagerSicherstellen();

$schritte     = OnboardingService::schritte();
$pflichtOffen = OnboardingService::pflichtOffen();
$offen        = OnboardingService::anzahlOffen();

// Mitarbeiter ohne Superadmin-Rechte: während der Einrichtung nur ein Hinweis, danach direkt ins ERP
if (!$istSuperadmin) {
    if (!$pflichtOffen) { header('Location: ' . BASE_PATH . '/start.php'); exit; }
    ?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Einrichtung läuft</title></head>
    <body style="font-family:sans-serif;background:#1e2a38;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0">
    <div style="background:#fff;color:#1e2a38;border-radius:12px;padding:32px 36px;max-width:420px;text-align:center">
        <div style="font-size:32px;margin-bottom:8px">🛠</div>
        <h2 style="margin:0 0 10px">Einrichtung läuft noch</h2>
        <p style="color:#64748b;line-height:1.5">Das ERP wird gerade eingerichtet. Bitte wende dich an deinen Administrator — sobald die Einrichtung abgeschlossen ist, kannst du dich normal anmelden.</p>
        <a href="<?= BASE_PATH ?>/logout.php" style="color:#2563eb">Abmelden</a>
    </div></body></html><?php
    exit;
}

// Welcher Schritt ist aufgeklappt: ?schritt=…, sonst der erste offene (Pflicht vor Empfehlung)
$aktiv = $_GET['schritt'] ?? null;
if (!isset($schritte[$aktiv])) {
    $aktiv = null;
    foreach ($schritte as $key => $sch) if (!$sch['erledigt']) { $aktiv = $key; break; }
}

$db     = Database::getInstance();
$e      = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
$v      = fn(string $k) => htmlspecialchars($e[$k] ?? '');
$ich    = $db->prepare("SELECT username, formularname, email FROM benutzer WHERE id = ?");
$ich->execute([(int)$_SESSION['benutzer']['id']]);
$ich    = $ich->fetch(PDO::FETCH_ASSOC);
$lager  = $db->query("SELECT id, name FROM lager WHERE aktiv = 1 AND lager_beziehung = 'eigen' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$lizenz = Lizenz::status();
$logo   = Firmenlogo::haupt();

$erfolg = $_SESSION['erfolg'] ?? null;
$fehler = $_SESSION['fehler'] ?? [];
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$erledigt = count($schritte) - $offen;
$pageTitle        = 'Einrichtung';
$activeModule     = 'einstellungen';
$actionBarContent = '';
require_once __DIR__ . '/../includes/shell_top.php';

$nr = 0;
$zeile = function (string $key, array $sch) use (&$nr, $aktiv) {
    $nr++;
    $symbol = $sch['erledigt'] ? '<span style="color:#16a34a">✓</span>' : ($key === $aktiv ? '<span style="color:#2563eb">●</span>' : '<span style="color:#94a3b8">○</span>');
    $offenAuf = $key === $aktiv;
    echo '<a href="?schritt=' . $key . '" style="display:flex;align-items:center;gap:12px;padding:11px 16px;text-decoration:none;color:inherit;'
       . ($offenAuf ? 'background:#eff6ff;' : '') . 'border-top:1px solid var(--color-border)">'
       . '<span style="font-size:18px;width:18px;text-align:center">' . $symbol . '</span>'
       . '<span style="width:24px;color:var(--color-text-muted)">' . $nr . '</span>'
       . '<strong style="width:130px">' . htmlspecialchars($sch['titel']) . '</strong>'
       . '<span style="flex:1;color:var(--color-text-muted);font-size:13px">' . htmlspecialchars($sch['info']) . '</span>'
       . '<span style="font-size:12px;color:#2563eb">' . ($offenAuf ? '' : ($sch['erledigt'] ? 'ändern' : 'einrichten')) . '</span></a>';
    return $offenAuf;
};
$feld = fn(string $name, string $label, string $wert, string $extra = '') =>
    '<div class="form-group"><label class="form-label">' . $label . '</label><input name="' . $name . '" class="erp-input" style="width:100%" value="' . $wert . '" ' . $extra . '></div>';
?>

<div style="max-width:860px">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:12px">
        <div>
            <h2 style="margin:0">NahtlOS einrichten</h2>
            <div style="color:var(--color-text-muted);font-size:13px;margin-top:4px">
                <?= $pflichtOffen ? 'Die Pflicht-Schritte braucht das ERP, um zu laufen.' : 'Pflicht erledigt — die übrigen Schritte kannst du auch später machen.' ?>
            </div>
        </div>
        <div style="font-size:14px;font-weight:600"><?= $erledigt ?> von <?= count($schritte) ?> erledigt</div>
    </div>

    <?php if ($erfolg): ?>
        <div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></div>
    <?php endif; ?>
    <?php if ($fehler): ?>
        <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)"><?= implode('<br>', array_map('htmlspecialchars', (array)$fehler)) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:16px;overflow:hidden">
        <div class="card-header">Pflicht</div>
        <?php foreach ($schritte as $key => $sch): if (!$sch['pflicht']) continue; ?>
            <?php if (!$zeile($key, $sch)) continue; ?>
            <div style="padding:14px 16px 18px 70px;background:#f8fafc;border-top:1px solid var(--color-border)">

            <?php if ($key === 'firma'): ?>
                <form method="post" action="speichern.php" enctype="multipart/form-data">
                    <input type="hidden" name="aktion" value="firma">
                    <div style="display:grid;grid-template-columns:2fr 1fr 2fr;gap:10px">
                        <div style="grid-column:1/-1"><?= $feld('firmenname', 'Firmenname *', $v('firmenname'), 'required') ?></div>
                        <div style="grid-column:1/-1"><?= $feld('strasse', 'Straße + Hausnummer *', $v('strasse'), 'required') ?></div>
                        <?= $feld('plz', 'PLZ *', $v('plz'), 'required') ?>
                        <div style="grid-column:2/4"><?= $feld('ort', 'Ort *', $v('ort'), 'required') ?></div>
                        <?= $feld('land', 'Land', $v('land') ?: 'Österreich') ?>
                        <?= $feld('telefon', 'Telefon', $v('telefon')) ?>
                        <?= $feld('email', 'E-Mail', $v('email'), 'type="email"') ?>
                        <?= $feld('uid_nummer', 'UID-Nummer', $v('uid_nummer'), 'placeholder="ATU12345678"') ?>
                        <?= $feld('steuernummer', 'Steuernummer', $v('steuernummer')) ?>
                        <div></div>
                    </div>
                    <div style="font-size:12px;color:var(--color-text-muted);margin:2px 0 12px">
                        UID-Nummer <strong>oder</strong> Steuernummer reicht. Die Lizenz gilt für Firmenname + UID (ohne UID: Steuernummer).
                        Kassen mit RKSV-Signatur (BFR) brauchen eine UID-Nummer.
                    </div>
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:14px">
                        <?php if ($logo): ?><img src="<?= BASE_PATH . '/' . htmlspecialchars($logo) ?>" style="max-height:48px;max-width:160px;border:1px solid var(--color-border);border-radius:4px;padding:3px;background:#fff"><?php endif; ?>
                        <div>
                            <label class="form-label">Logo (PNG/JPG/WEBP, max. 2 MB) — für Bons, Rechnungen, Mails</label>
                            <input type="file" name="logo_datei" accept="image/png,image/jpeg,image/webp" class="erp-input" style="padding:4px">
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm">Speichern und weiter</button>
                </form>

            <?php elseif ($key === 'superadmin'): ?>
                <form method="post" action="speichern.php">
                    <input type="hidden" name="aktion" value="superadmin">
                    <div style="font-size:13px;color:var(--color-text-muted);margin-bottom:10px">
                        Das ist <strong>dein</strong> Konto mit allen Rechten. Bitte eigenen Namen und ein eigenes Passwort setzen —
                        das Startpasswort aus der Installation gilt danach nicht mehr.
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <?= $feld('formularname', 'Name (erscheint z.B. am Bon) *', htmlspecialchars($ich['formularname'] ?? ''), 'required') ?>
                        <?= $feld('username', 'Benutzername (Anmeldung) *', htmlspecialchars($ich['username'] ?? ''), 'required autocomplete="username"') ?>
                        <div style="grid-column:1/-1"><?= $feld('email', 'E-Mail (für „Passwort vergessen“)', htmlspecialchars($ich['email'] ?? ''), 'type="email"') ?></div>
                        <?= $feld('passwort', 'Neues Passwort * (mind. 8 Zeichen)', '', 'type="password" required minlength="8" autocomplete="new-password"') ?>
                        <?= $feld('passwort2', 'Passwort wiederholen *', '', 'type="password" required minlength="8" autocomplete="new-password"') ?>
                    </div>
                    <button class="btn btn-primary btn-sm" style="margin-top:12px">Speichern und weiter</button>
                </form>

            <?php elseif ($key === 'lizenz'): ?>
                <?php if (in_array($lizenz['status'], ['falsche_firma', 'ungueltig'], true)): ?>
                    <div style="color:var(--color-danger);font-size:13px;margin-bottom:10px">
                        <?= $lizenz['status'] === 'falsche_firma'
                            ? 'Die eingespielte Lizenz passt nicht zu den Firmendaten (Firmenname/UID). Bitte Schritt 1 prüfen oder die richtige Lizenzdatei einspielen.'
                            : 'Die eingespielte Lizenzdatei ist ungültig: ' . htmlspecialchars((string)($lizenz['fehler'] ?? '')) ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="speichern.php" enctype="multipart/form-data" style="display:grid;gap:10px">
                    <input type="hidden" name="aktion" value="lizenz">
                    <div style="font-size:13px;color:var(--color-text-muted)">
                        Die Lizenzdatei (.lic) bekommst du von NAHTLOS. Sie gilt nur für die Firmendaten aus Schritt 1.
                    </div>
                    <input type="file" name="datei" accept=".lic,.json,.txt" class="erp-input">
                    <details>
                        <summary style="cursor:pointer;font-size:13px">… oder Inhalt einfügen</summary>
                        <textarea name="inhalt" class="erp-input" rows="5" style="width:100%;font-family:monospace;font-size:12px;margin-top:6px"></textarea>
                    </details>
                    <div><button class="btn btn-primary btn-sm">Lizenz einspielen</button></div>
                </form>
                <?php if ($lizenz['status'] === 'keine'): ?>
                <form method="post" action="speichern.php" style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--color-border)">
                    <input type="hidden" name="aktion" value="grundmodul">
                    <span style="font-size:13px;color:var(--color-text-muted)">Noch keine Lizenz? Das ERP läuft auch nur mit dem Grundmodul — die Lizenz kann jederzeit unter ··· → Lizenzverwaltung nachgereicht werden.</span>
                    <div style="margin-top:8px"><button class="btn btn-sm">Ohne Lizenz weiter (nur Grundmodul)</button></div>
                </form>
                <?php endif; ?>

            <?php elseif ($key === 'steuer'): ?>
                <form method="post" action="speichern.php">
                    <input type="hidden" name="aktion" value="steuer">
                    <div style="font-size:13px;line-height:1.6;margin-bottom:12px">
                        Diese Version rechnet als <strong>regelbesteuertes Unternehmen</strong> (Umsatzsteuer auf Belegen und im Export),
                        mit <strong>doppelter Buchführung (Bilanz)</strong> und <strong>Soll-Versteuerung</strong> (Umsatzsteuer nach Rechnungsdatum).<br>
                        <span style="color:var(--color-text-muted)">Kleinunternehmer und Einnahmen-Ausgaben-Rechnung folgen mit Version 1.1. Bei Fragen bitte vorher mit NAHTLOS bzw. der Steuerberatung klären.</span>
                    </div>
                    <button class="btn btn-primary btn-sm">Passt so — bestätigen</button>
                </form>

            <?php elseif ($key === 'lager'): ?>
                <form method="post" action="speichern.php">
                    <input type="hidden" name="aktion" value="lager">
                    <div style="font-size:13px;color:var(--color-text-muted);margin-bottom:10px">
                        Jeder Bestand liegt in einem Lager. Ein „Standardlager“ ist schon angelegt — benenne es z.B. nach deinem Geschäft.
                        Weitere Lager (Messe, Außenlager …) später unter Lager.
                    </div>
                    <?php foreach ($lager as $l): ?>
                        <input name="lager[<?= (int)$l['id'] ?>]" class="erp-input" style="max-width:320px;display:block;margin-bottom:6px" value="<?= htmlspecialchars($l['name']) ?>" maxlength="50">
                    <?php endforeach; ?>
                    <button class="btn btn-primary btn-sm" style="margin-top:6px">Speichern</button>
                </form>
            <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card" style="margin-bottom:16px;overflow:hidden<?= $pflichtOffen ? ';opacity:.55' : '' ?>">
        <div class="card-header">Empfohlen <?= $pflichtOffen ? '<span style="font-weight:400;color:var(--color-text-muted)">(nach den Pflicht-Schritten)</span>' : '' ?></div>
        <?php foreach ($schritte as $key => $sch): if ($sch['pflicht']) continue; ?>
            <?php if (!$zeile($key, $sch)) continue; ?>
            <div style="padding:14px 16px 18px 70px;background:#f8fafc;border-top:1px solid var(--color-border);font-size:13px">
                <?php if ($pflichtOffen): ?>
                    <span style="color:var(--color-text-muted)">Zuerst die Pflicht-Schritte oben erledigen.</span>
                <?php else: ?>
                    <?= [
                        'benutzer'  => 'Lege für jede Mitarbeiterin ein eigenes Konto mit passender Rolle an (z.B. Kassier, Lager). So arbeitet nicht jeder mit dem Superadmin, und im Protokoll steht, wer was gemacht hat.',
                        'mail'      => 'Zugangsdaten des Mail-Postfachs eintragen, Test-Mail senden und „Mailversand aktiv“ einschalten — für Rechnungen, Auftragsbestätigungen und „Passwort vergessen“.',
                        'kasse'     => 'Kasse anlegen (Name, Lager, Bon-Format). Die RKSV-Registrierung mit BFR kann später erfolgen.',
                        'shop'      => 'Online-Shop als Kanal anlegen (Name + Adresse). Die API-Schlüssel können später eingetragen werden.',
                        'abholorte' => 'Adresse und Öffnungszeiten für Selbstabholung — erscheint auf Abholzettel, Abholmail und Rechnung.',
                    ][$key] ?? '' ?>
                    <div style="display:flex;gap:8px;margin-top:10px">
                        <a href="<?= BASE_PATH . $sch['link'] ?>" class="btn btn-primary btn-sm">Einrichten →</a>
                        <?php if ($key === 'benutzer' && !$sch['erledigt']): ?>
                            <form method="post" action="speichern.php"><input type="hidden" name="aktion" value="allein"><button class="btn btn-sm">Ich arbeite allein</button></form>
                        <?php elseif ($key === 'abholorte' && !$sch['erledigt']): ?>
                            <form method="post" action="speichern.php"><input type="hidden" name="aktion" value="keine_abholung"><button class="btn btn-sm">Keine Selbstabholung</button></form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$pflichtOffen): ?>
    <form method="post" action="speichern.php" style="display:flex;justify-content:flex-end">
        <input type="hidden" name="aktion" value="spaeter">
        <button class="btn btn-primary"><?= $offen ? 'Später — weiter zum ERP' : '✓ Fertig — weiter zum ERP' ?></button>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
