<?php
// Newsletter-Empfänger aus dem JTL-Shop importieren (CSV aus phpMyAdmin), erst Probelauf, dann Import.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterEmpfaengerService.php';

$db     = Database::getInstance();
$shops  = $db->query("SELECT id, name FROM shops WHERE ist_aktiv = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$shopId = (int)($_POST['shop_id'] ?? $_GET['shop_id'] ?? ($shops[0]['id'] ?? 0));
$ergebnis = null;
$probelauf = true;
$tmpOrdner = __DIR__ . '/../../storage/newsletter_import';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_dir($tmpOrdner)) mkdir($tmpOrdner, 0700, true);
    // Probelauf: Datei kurz ablegen (nur bis zum Import, danach gelöscht — enthält personenbezogene Daten)
    if (!empty($_FILES['datei']['tmp_name']) && is_uploaded_file($_FILES['datei']['tmp_name'])) {
        $_SESSION['newsletter_import_datei'] = $tmpOrdner . '/' . bin2hex(random_bytes(8)) . '.csv';
        move_uploaded_file($_FILES['datei']['tmp_name'], $_SESSION['newsletter_import_datei']);
    }
    $datei = $_SESSION['newsletter_import_datei'] ?? null;
    if (($_POST['aktion'] ?? '') === 'verwerfen' || !$datei || !is_file($datei)) {
        if ($datei) @unlink($datei);
        unset($_SESSION['newsletter_import_datei']);
        if (($_POST['aktion'] ?? '') !== 'verwerfen') $ergebnis = ['erfolg' => false, 'fehler' => 'Bitte eine CSV-Datei auswählen.'];
    } else {
        $probelauf = ($_POST['aktion'] ?? '') !== 'importieren';
        $ergebnis = (new NewsletterEmpfaengerService())->importJtlCsv($shopId, $datei, $probelauf, (int)$_SESSION['benutzer']['id']);
        if (!$probelauf || !$ergebnis['erfolg']) {
            @unlink($datei);
            unset($_SESSION['newsletter_import_datei']);
        }
    }
}
$dateiBereit = !empty($_SESSION['newsletter_import_datei']) && is_file($_SESSION['newsletter_import_datei']);

$pageTitle        = 'Newsletter: Import aus JTL-Shop';
$activeModule     = 'kunden';
$actionBarContent = '<a href="empfaenger.php?shop_id=' . $shopId . '" class="btn btn-secondary btn-sm">← Empfänger</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="max-width:900px">
<div class="card" style="margin-bottom:12px">
    <div class="card-header">1. Export im JTL-Shop (phpMyAdmin → Shop-Datenbank → Reiter SQL)</div>
    <div style="padding:12px 16px;font-size:13px">
        Diese Abfrage ausführen (nur lesend), dann unter dem Ergebnis <strong>Exportieren → CSV</strong>, Haken bei
        <strong>„Spaltennamen in die erste Zeile“</strong>. Bestätigte Abonnenten mit Opt-in-Nachweis + alle Adressen der Blacklist (gesperrt = 1):
        <pre style="background:var(--color-bg-secondary,#f6f8fa);padding:10px;border-radius:6px;overflow:auto;font-size:12px;margin-top:8px">SELECT e.cEmail AS email, e.cAnrede AS anrede, e.cVorname AS vorname, e.cNachname AS nachname,
       e.dEingetragen AS eingetragen, h.cRegIp AS anmelde_ip, h.cOptIp AS optin_ip,
       h.dOptCode AS optin_datum, e.dLetzterNewsletter AS letzter_newsletter, 0 AS gesperrt
FROM tnewsletterempfaenger e
LEFT JOIN tnewsletterempfaengerhistory h ON h.kNewsletterEmpfaengerHistory = (
    SELECT MAX(h2.kNewsletterEmpfaengerHistory) FROM tnewsletterempfaengerhistory h2
    WHERE h2.cEmail = e.cEmail AND h2.dOptCode IS NOT NULL)
WHERE e.nAktiv = 1
  AND e.cEmail NOT IN (SELECT cMail FROM tnewsletterempfaengerblacklist)
UNION ALL
SELECT b.cMail, NULL, NULL, NULL, b.dErstellt, NULL, NULL, NULL, NULL, 1
FROM tnewsletterempfaengerblacklist b;</pre>
        Die CSV enthält personenbezogene Daten — nach dem Import nicht aufheben.
    </div>
</div>

<div class="card" style="margin-bottom:12px">
    <div class="card-header">2. Datei prüfen und importieren</div>
    <form method="post" enctype="multipart/form-data" style="padding:12px 16px;display:grid;gap:10px;font-size:13px">
        <label>Shop
            <select name="shop_id" class="erp-select">
                <?php foreach ($shops as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $shopId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php if (!$dateiBereit): ?>
            <input type="file" name="datei" accept=".csv,text/csv" class="erp-input" required>
            <div><button class="btn btn-primary btn-sm" name="aktion" value="probelauf">Probelauf (ändert nichts)</button></div>
        <?php else: ?>
            <div style="display:flex;gap:8px">
                <button class="btn btn-primary btn-sm" name="aktion" value="importieren" onclick="return confirm('Jetzt importieren?')">✓ Jetzt importieren</button>
                <button class="btn btn-sm" name="aktion" value="verwerfen" formnovalidate>Verwerfen</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($ergebnis): ?>
<div class="card" style="border-left:3px solid <?= $ergebnis['erfolg'] ? ($probelauf ? '#2563eb' : 'var(--color-success)') : 'var(--color-danger)' ?>">
    <div class="card-header"><?= !$ergebnis['erfolg'] ? 'Fehler' : ($probelauf ? 'Probelauf — so würde importiert' : 'Import abgeschlossen') ?></div>
    <div style="padding:12px 16px;font-size:13px;line-height:1.8">
        <?php if (!$ergebnis['erfolg']): ?>
            <span style="color:var(--color-danger)"><?= htmlspecialchars($ergebnis['fehler']) ?></span>
        <?php else: ?>
            Neu bestätigt (aktiv): <strong><?= $ergebnis['neu_aktiv'] ?></strong><br>
            Neu gesperrt (Blacklist → abgemeldet): <strong><?= $ergebnis['neu_gesperrt'] ?></strong><br>
            Schon vorhanden, auf abgemeldet gesetzt (Blacklist): <?= $ergebnis['gesperrt_gesetzt'] ?><br>
            Schon vorhanden, unverändert: <?= $ergebnis['vorhanden'] ?><br>
            <?php if ($ergebnis['ohne_optin']): ?><span style="color:#d97706">Ohne Opt-in-Datum (Eintragedatum verwendet): <?= $ergebnis['ohne_optin'] ?></span><br><?php endif; ?>
            <?php if ($ergebnis['ungueltig']): ?><span style="color:#d97706">Ungültige Adressen übersprungen: <?= $ergebnis['ungueltig'] ?></span>
                <div style="font-size:11px;color:var(--color-text-muted)"><?= implode('<br>', array_map('htmlspecialchars', $ergebnis['ungueltige'])) ?></div><?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
