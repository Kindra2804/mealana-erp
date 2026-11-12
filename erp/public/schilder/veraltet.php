<?php
/**
 * Schilder veraltet (PreisschildService::veraltete): gedruckte Schilder, deren Preis, Aktion, Texte oder Badges
 * sich seither geändert haben. Nachdrucken über die Auswahlseite, oder "ist erledigt" ohne Druck.
 * Messe-Schilder nur auf Wunsch (werden meist je Messe neu gedruckt).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/schilder/PreisschildService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$svc = new PreisschildService();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'erledigt') {
    $n = $svc->alsAktuellMarkieren((array) ($_POST['ids'] ?? []), (string) ($_POST['vorlage'] ?? ''), (int) ($_SESSION['benutzer']['id'] ?? 0) ?: null);
    $_SESSION['erfolg'] = "$n Schild(er) als erledigt markiert.";
    header('Location: veraltet.php' . (!empty($_POST['mit_messe']) ? '?messe=1' : ''));
    exit;
}

$mitMesse = !empty($_GET['messe']);
$zeilen   = $svc->veraltete($mitMesse ? ['laden', 'messe'] : ['laden']);
$gruppen  = [];
foreach ($zeilen as $z) $gruppen[$z['vorlage']][] = $z;
unset($_SESSION['schilder_veraltet']);   // Dashboard-Zahl beim nächsten Mal neu rechnen

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$pageTitle    = 'Schilder veraltet';
$activeModule = 'artikel';
$kategorienBaum = (new ArtikelService())->getKategorienBaum();
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="padding:var(--space-md);max-width:1100px">
    <?php if ($erfolg): ?><div class="success-banner" style="margin-bottom:var(--space-sm)">✓ <?= htmlspecialchars($erfolg) ?></div><?php endif; ?>

    <div class="card" style="margin-bottom:var(--space-sm);font-size:13px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <div style="flex:1;min-width:300px">
            <strong>Schilder, die nicht mehr stimmen</strong><br>
            <span style="color:var(--color-text-muted)">Preis, Aktion, Grundpreis, Texte oder Badges haben sich seit dem Druck geändert. Haken setzen → nachdrucken. Wurde ein Schild abgenommen oder von Hand korrigiert: „ist erledigt“.</span>
        </div>
        <a href="?<?= $mitMesse ? '' : 'messe=1' ?>" class="btn btn-secondary btn-sm"><?= $mitMesse ? 'Messe-Schilder ausblenden' : 'auch Messe-Schilder zeigen' ?></a>
        <a href="auswahl.php" class="btn btn-secondary btn-sm">🖨 Preisschilder drucken</a>
    </div>

    <?php if (!$zeilen): ?>
        <div class="card" style="font-size:13px">✓ Alle gedruckten <?= $mitMesse ? 'Schilder' : 'Laden-Schilder' ?> sind aktuell.</div>
    <?php endif; ?>

    <?php foreach ($gruppen as $vorlage => $liste): ?>
    <form method="post" class="card" style="margin-bottom:var(--space-sm)">
        <input type="hidden" name="vorlage" value="<?= htmlspecialchars($vorlage) ?>">
        <?php if ($mitMesse): ?><input type="hidden" name="mit_messe" value="1"><?php endif; ?>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <strong style="font-size:14px"><?= $vorlage === 'laden' ? '🏪' : '🎪' ?> <?= htmlspecialchars(PreisschildService::VORLAGEN[$vorlage]['name']) ?> (<?= count($liste) ?>)</strong>
            <button type="submit" formaction="auswahl.php" formtarget="_blank" class="btn btn-primary btn-sm" style="margin-left:auto">🖨 Ausgewählte nachdrucken</button>
            <button type="submit" name="aktion" value="erledigt" class="btn btn-secondary btn-sm" onclick="return confirm('Ausgewählte als erledigt markieren (ohne Druck)?')">✓ ist erledigt</button>
        </div>
        <table class="erp-table">
            <thead><tr><th style="width:28px"><input type="checkbox" checked onclick="this.closest('form').querySelectorAll('.va-cb').forEach(c => c.checked = this.checked)"></th>
                <th>Artikelnr.</th><th>Name</th><th>Gedruckt</th><th>Was hat sich geändert</th></tr></thead>
            <tbody>
            <?php foreach ($liste as $z): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= (int)$z['id'] ?>" class="va-cb" checked></td>
                    <td style="font-family:monospace;font-size:12px"><a href="<?= BASE_PATH ?>/artikel/detail.php?id=<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['artikelnummer']) ?></a></td>
                    <td><?= htmlspecialchars($z['titel']) ?></td>
                    <td style="font-size:12px;color:var(--color-text-muted);white-space:nowrap"><?= date('d.m.Y', strtotime($z['gedruckt_am'])) ?></td>
                    <td style="font-size:12px;color:#b45309"><?= htmlspecialchars(implode(' · ', $z['gruende'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form>
    <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
