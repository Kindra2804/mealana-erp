<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: liste.php');
    exit;
}

$service    = new LieferantenService();
$lieferant  = $service->findByIdMitVertretern($id);

if ($lieferant === false) {
    header('Location: liste.php');
    exit;
}

$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['erfolg']);

$pageTitle        = htmlspecialchars($lieferant['name']);
$activeModule     = 'lieferanten';
$actionBarContent = <<<HTML
    <a href="/mealana/lieferanten/bearbeiten.php?id={$id}" class="btn btn-secondary btn-sm">✏️ Bearbeiten</a>
    <a href="/mealana/lieferanten/delete.php?id={$id}" class="btn btn-danger btn-sm"
       onclick="return confirm('Lieferant wirklich deaktivieren?')">Deaktivieren</a>
    <div class="actionbar-sep"></div>
    <div class="actionbar-right">
        <a href="/mealana/lieferanten/liste.php" class="btn btn-secondary btn-sm">← Liste</a>
    </div>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($erfolg): ?>
    <div class="card" style="border-left:4px solid var(--color-success);margin-bottom:12px">
        <p style="margin:0;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></p>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom:12px">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px 24px">
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Lieferantennr.</div>
            <div><?= $lieferant['id'] ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Status</div>
            <div><?= $lieferant['aktiv'] ? '<span class="chip chip-aktiv">Aktiv</span>' : '<span class="chip chip-inaktiv">Inaktiv</span>' ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Land</div>
            <div><?= htmlspecialchars($lieferant['land'] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Website</div>
            <div><?= htmlspecialchars($lieferant['website'] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">E-Mail</div>
            <div><?= htmlspecialchars($lieferant['email'] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Telefon</div>
            <div><?= htmlspecialchars($lieferant['telefon'] ?? '–') ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">Vertreter</h3>
        <a href="vertreter_neu.php?lieferant_id=<?= $id ?>" class="btn btn-primary btn-sm">+ Neuer Vertreter</a>
    </div>

    <?php if (empty($lieferant['vertreter'])): ?>
        <p style="color:var(--color-text-muted);margin:0">Noch keine Vertreter angelegt.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>E-MAIL</th>
                    <th>TELEFON</th>
                    <th>MOBIL</th>
                    <th>NOTIZEN</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lieferant['vertreter'] as $v): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['vorname'] . ' ' . $v['nachname']) ?></strong></td>
                    <td><?= htmlspecialchars($v['email'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($v['telefon'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($v['mobil'] ?? '–') ?></td>
                    <td title="<?= htmlspecialchars($v['notizen'] ?? '') ?>">
                        <?= htmlspecialchars(mb_strimwidth($v['notizen'] ?? '', 0, 40, '…')) ?>
                    </td>
                    <td>
                        <a href="vertreter_bearbeiten.php?id=<?= $v['id'] ?>" style="text-decoration:none" title="Bearbeiten">✏️</a>
                        <a href="vertreter_delete.php?lieferant_id=<?= $id ?>&id=<?= $v['id'] ?>"
                           style="text-decoration:none" title="Löschen"
                           onclick="return confirm('Vertreter wirklich löschen?')">🗑️</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
