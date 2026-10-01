<?php
/**
 * Ziel der QR-Codes auf den Fach-Etiketten (lager/lagerplaetze_etiketten.php).
 * Läuft schon eine Inventur für dieses Fach oder für das ganze Lager → direkt zur
 * Zählung (bei Lager-Inventur mit dem Fach vorausgewählt). Sonst kann man hier eine
 * Zwischenzählung nur für dieses Fach starten.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$lpId = (int)($_GET['lp'] ?? $_POST['lp'] ?? 0);
$lp   = $lpId ? (new LagerService())->getLagerplatzById($lpId) : false;
if (!$lp) {
    $_SESSION['fehler'] = 'Unbekannter Lagerplatz (QR-Code ungültig?).';
    header('Location: ' . BASE_PATH . '/inventur/liste.php');
    exit;
}

$service = new InventurService();
$lauf    = $service->laufFuerLagerplatz($lpId);
if ($lauf) {
    $ziel = BASE_PATH . '/inventur/zaehlen.php?lauf_id=' . $lauf['lauf_id'];
    if ($lauf['scope'] === 'lager') $ziel .= '&lagerplatz=' . $lpId;
    header('Location: ' . $ziel);
    exit;
}

$darfStarten = Auth::kann('inventur.anlegen');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $darfStarten) {
    $r = $service->starten([
        'scope_tabelle' => 'lagerplaetze',
        'scope_id'      => $lpId,
        'blind_modus'   => !empty($_POST['blind_modus']) ? 1 : 0,
        'notiz'         => 'Zwischenzählung per Fach-QR-Code',
    ]);
    if ($r['erfolg']) {
        header('Location: ' . BASE_PATH . '/inventur/zaehlen.php?lauf_id=' . $r['id']);
        exit;
    }
    $_SESSION['fehler'] = $r['fehler'];
}

$pageTitle    = 'Fach ' . $lp['bezeichnung'];
$activeModule = 'lager';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div class="card" style="max-width:460px;margin:24px auto;text-align:center;padding:24px">
    <div style="font-size:13px;color:var(--color-text-muted)">Lagerplatz</div>
    <div style="font-size:34px;font-weight:700;font-family:monospace;margin:6px 0 16px"><?= htmlspecialchars($lp['bezeichnung']) ?></div>
    <p style="font-size:14px">Für dieses Fach läuft gerade keine Inventur.</p>
    <?php if ($darfStarten): ?>
        <form method="post">
            <input type="hidden" name="lp" value="<?= $lpId ?>">
            <label style="display:block;font-size:13px;margin-bottom:14px">
                <input type="checkbox" name="blind_modus" value="1"> Blind zählen (Soll-Menge ausblenden)
            </label>
            <button type="submit" class="btn btn-primary" style="font-size:16px;padding:12px 24px">Zwischenzählung starten</button>
        </form>
    <?php else: ?>
        <p style="font-size:13px;color:var(--color-text-muted)">Zum Starten einer Zählung fehlt die Berechtigung „Inventur anlegen“.</p>
    <?php endif; ?>
    <p style="margin-top:16px"><a href="<?= BASE_PATH ?>/artikel/liste.php?lagerplatz_id=<?= $lpId ?>">Artikel in diesem Fach anzeigen</a></p>
</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
