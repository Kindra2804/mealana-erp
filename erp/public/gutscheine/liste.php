<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinRepository.php';

$repo   = new GutscheinRepository();
$status = $_GET['status'] ?? '';
$suche  = trim($_GET['suche'] ?? '');
$gutscheine = $repo->findAll($status, $suche);

$fehler = $_SESSION['fehler'] ?? null;
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$pageTitle    = 'Gutscheine';
$activeModule = 'gutscheine';
$actionBarContent = <<<HTML
    <a href="neu.php" class="btn btn-primary btn-sm">+ Neuer Gutschein</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

$statusLabels = [
    'aktiv'      => ['label' => 'Aktiv',       'class' => 'chip-aktiv'],
    'teilweise'  => ['label' => 'Teilweise eingelöst', 'class' => 'chip-auslauf'],
    'eingeloest' => ['label' => 'Eingelöst',   'class' => 'chip-inaktiv'],
    'abgelaufen' => ['label' => 'Abgelaufen',  'class' => 'sc-fehlbest'],
    'storniert'  => ['label' => 'Storniert',   'class' => 'chip-inaktiv'],
];
?>

<?php if ($erfolg): ?>
<div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)">
    <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>
<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px;padding:12px 16px">
    <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="text" name="suche" class="erp-input" placeholder="Code oder Empfänger…" value="<?= htmlspecialchars($suche) ?>" style="width:220px">
        <select name="status" class="erp-select" onchange="this.form.submit()">
            <option value="">Alle Status</option>
            <?php foreach ($statusLabels as $val => $info): ?>
                <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $info['label'] ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filtern</button>
    </form>
</div>

<div class="card">
    <?php if (empty($gutscheine)): ?>
        <p style="color:var(--color-text-muted);padding:16px">Keine Gutscheine gefunden.</p>
    <?php else: ?>
    <table class="erp-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Betrag</th>
                <th>Restguthaben</th>
                <th>Status</th>
                <th>Empfänger</th>
                <th>Gültig bis</th>
                <th>Erstellt</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gutscheine as $g):
                $sl = $statusLabels[$g['status']] ?? ['label' => $g['status'], 'class' => ''];
            ?>
            <tr>
                <td><a href="detail.php?id=<?= $g['id'] ?>" style="font-family:monospace;font-weight:600"><?= htmlspecialchars($g['code']) ?></a></td>
                <td style="text-align:right"><?= number_format((float)$g['betrag'], 2, ',', '.') ?> €</td>
                <td style="text-align:right;font-weight:600"><?= number_format((float)$g['restguthaben'], 2, ',', '.') ?> €</td>
                <td><span class="chip <?= $sl['class'] ?>"><?= $sl['label'] ?></span></td>
                <td><?= htmlspecialchars($g['empfaenger_name'] ?? '—') ?></td>
                <td><?= $g['gueltig_bis'] ? date('d.m.Y', strtotime($g['gueltig_bis'])) : '—' ?></td>
                <td style="white-space:nowrap"><?= date('d.m.Y', strtotime($g['erstellt_am'])) ?></td>
                <td><a href="detail.php?id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm">Detail</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
