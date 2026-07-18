<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';

$service = new InventurService();
$fehler  = $_SESSION['fehler'] ?? null;
$erfolg  = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$laeufe = $service->getAlle();

$scopeLabels = [
    'lager'        => 'Ganzes Lager',
    'lagerplaetze' => 'Lagerplatz',
    'kategorien'   => 'Kategorie',
    'artikel'      => 'Einzelner Artikel',
    'mietfaecher'  => 'Mietfach',
];
$statusLabels = [
    'laufend'      => ['label' => 'Läuft',       'class' => 'chip-aktiv'],
    'pausiert'     => ['label' => 'Pausiert',    'class' => 'chip-auslauf'],
    'abgeschlossen' => ['label' => 'Abgeschlossen', 'class' => 'chip-inaktiv'],
    'abgebrochen'  => ['label' => 'Abgebrochen', 'class' => 'chip-inaktiv'],
];

$pageTitle        = 'Inventur';
$activeModule     = 'lager';
$actionBarContent = '<a href="' . BASE_PATH . '/inventur/neu.php" class="btn btn-primary btn-sm">+ Neue Inventur starten</a>';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>
<?php if ($erfolg): ?>
<div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)">
    <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>

<div class="card">
    <table class="erp-table">
        <thead>
            <tr>
                <th>Scope</th>
                <th>Ziel</th>
                <th>Status</th>
                <th>Gestartet</th>
                <th>Von</th>
                <th style="width:220px"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($laeufe as $l): ?>
            <?php $sl = $statusLabels[$l['status']] ?? ['label' => $l['status'], 'class' => '']; ?>
            <tr>
                <td><?= $scopeLabels[$l['scope_tabelle']] ?? htmlspecialchars($l['scope_tabelle']) ?></td>
                <td><strong><?= htmlspecialchars($l['scope_bezeichnung']) ?></strong>
                    <?php if ($l['vorgaenger_lauf_id']): ?>
                        <span style="font-size:11px;color:var(--color-text-muted)">(Fortsetzung von #<?= $l['vorgaenger_lauf_id'] ?>)</span>
                    <?php endif; ?>
                </td>
                <td><span class="chip <?= $sl['class'] ?>"><?= $sl['label'] ?></span></td>
                <td><?= date('d.m.Y H:i', strtotime($l['gestartet_am'])) ?></td>
                <td><?= htmlspecialchars($l['benutzer_name'] ?? '—') ?></td>
                <td style="white-space:nowrap">
                    <?php if ($l['status'] === 'laufend'): ?>
                        <a href="<?= BASE_PATH ?>/inventur/zaehlen.php?lauf_id=<?= $l['id'] ?>" class="btn btn-primary btn-sm">Zählen</a>
                        <form method="post" action="<?= BASE_PATH ?>/inventur/pausieren.php" style="display:inline">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Pausieren</button>
                        </form>
                        <form method="post" action="<?= BASE_PATH ?>/inventur/abbrechen.php" style="display:inline" onsubmit="return confirm('Inventur #<?= $l['id'] ?> wirklich abbrechen?')">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Abbrechen</button>
                        </form>
                    <?php elseif ($l['status'] === 'pausiert'): ?>
                        <form method="post" action="<?= BASE_PATH ?>/inventur/fortsetzen.php" style="display:inline">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm">Fortsetzen</button>
                        </form>
                        <form method="post" action="<?= BASE_PATH ?>/inventur/abbrechen.php" style="display:inline" onsubmit="return confirm('Inventur #<?= $l['id'] ?> wirklich abbrechen?')">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Abbrechen</button>
                        </form>
                    <?php elseif ($l['status'] === 'abgebrochen'): ?>
                        <form method="post" action="<?= BASE_PATH ?>/inventur/fortsetzen.php" style="display:inline">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Fortsetzen</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($laeufe)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:24px">Noch keine Inventur durchgeführt.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
