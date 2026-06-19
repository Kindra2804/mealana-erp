<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$service = new LieferantenService();

$zeigeInaktive = isset($_GET['inaktive']) && $_GET['inaktive'] == '1';
$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $lieferanten = $service->search($q);
} else {
    $lieferanten = $service->findAll($zeigeInaktive);
}

$pageTitle        = 'Lieferanten';
$activeModule     = 'lieferanten';
$actionBarContent = <<<HTML
    <a href="/mealana/lieferanten/neu.php" class="btn btn-primary btn-sm">+ Neuer Lieferant</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card">
    <div class="filter-bar" style="margin-bottom:16px">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                   placeholder="Name, Land oder Website …" class="erp-input" style="width:260px">
            <?php if ($zeigeInaktive): ?>
                <input type="hidden" name="inaktive" value="1">
            <?php endif; ?>
            <button type="submit" class="btn btn-secondary btn-sm">Suchen</button>
            <?php if ($q !== ''): ?>
                <a href="liste.php<?= $zeigeInaktive ? '?inaktive=1' : '' ?>" class="btn btn-secondary btn-sm">✕ Filter aufheben</a>
            <?php endif; ?>
            <?php if ($zeigeInaktive): ?>
                <a href="liste.php" class="btn btn-secondary btn-sm">Nur aktive anzeigen</a>
            <?php else: ?>
                <a href="liste.php?inaktive=1" class="btn btn-secondary btn-sm">Auch deaktivierte</a>
            <?php endif; ?>
        </form>
        <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
            <?= count($lieferanten) ?> Lieferanten gefunden
        </div>
    </div>

    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:60px">NR.</th>
                <th>NAME</th>
                <th style="width:80px">LAND</th>
                <th>WEBSITE</th>
                <th>E-MAIL</th>
                <th style="width:130px">TELEFON</th>
                <th style="width:70px">STATUS</th>
                <th style="width:60px"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lieferanten as $l): ?>
            <tr <?= $l['aktiv'] ? '' : 'style="opacity:.6"' ?>>
                <td><a href="detail.php?id=<?= $l['id'] ?>"><?= htmlspecialchars($l['id']) ?></a></td>
                <td><a href="detail.php?id=<?= $l['id'] ?>"><strong><?= htmlspecialchars($l['name']) ?></strong></a></td>
                <td><?= htmlspecialchars($l['land'] ?? '') ?></td>
                <td><?= htmlspecialchars($l['website'] ?? '') ?></td>
                <td><?= htmlspecialchars($l['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($l['telefon'] ?? '') ?></td>
                <td>
                    <?php if ($l['aktiv']): ?>
                        <span class="chip chip-aktiv">Aktiv</span>
                    <?php else: ?>
                        <span class="chip chip-inaktiv">Inaktiv</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="bearbeiten.php?id=<?= $l['id'] ?>" title="Bearbeiten" style="text-decoration:none">✏️</a>
                    <a href="delete.php?id=<?= $l['id'] ?>" title="Deaktivieren" style="text-decoration:none"
                       onclick="return confirm('Lieferant wirklich deaktivieren?')">🗑️</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
