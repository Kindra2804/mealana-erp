<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

$service    = new BestellungService();
$statusFilter = $_GET['status'] ?? '';
$bestellungen = $service->getAll($statusFilter);
$bestellService = $service;

$statusLabels = [
    'offen'          => ['label' => 'Offen',           'class' => 'chip-aktiv'],
    'teilgeliefert'  => ['label' => 'Teilgeliefert',   'class' => 'chip-auslauf'],
    'erledigt'       => ['label' => 'Erledigt',        'class' => 'chip-inaktiv'],
    'storniert'      => ['label' => 'Storniert',       'class' => 'chip-inaktiv'],
    'entwurf'        => ['label' => 'Entwurf',         'class' => 'chip-inaktiv'],
];

$pageTitle        = 'Bestellungen';
$activeModule     = 'einkauf';
$actionBarContent = <<<HTML
<a href="/mealana/bestellungen/neu.php" class="btn btn-primary btn-sm">+ Neue Bestellung</a>
<div class="actionbar-sep"></div>
<div class="actionbar-right">
    <select class="erp-select" style="font-size:13px" onchange="window.location='?status='+this.value">
        <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Alle Status</option>
        <option value="offen"         <?= $statusFilter === 'offen'         ? 'selected' : '' ?>>Offen</option>
        <option value="teilgeliefert" <?= $statusFilter === 'teilgeliefert' ? 'selected' : '' ?>>Teilgeliefert</option>
        <option value="erledigt"      <?= $statusFilter === 'erledigt'      ? 'selected' : '' ?>>Erledigt</option>
        <option value="storniert"     <?= $statusFilter === 'storniert'     ? 'selected' : '' ?>>Storniert</option>
    </select>
</div>
HTML;
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php $mode = 'liste'; require __DIR__ . '/../includes/bestellvorschlaege_box.php'; ?>

<div class="card">
    <?php if (empty($bestellungen)): ?>
        <p style="color:var(--color-text-muted);padding:16px">Keine Bestellungen gefunden.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Nr.</th>
                    <th>Lieferant</th>
                    <th>Datum</th>
                    <th>Erwartet</th>
                    <th>Positionen</th>
                    <th>Gesamt EK</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bestellungen as $b):
                    $nr      = \BestellungService::bestellnummer($b['id'], $b['bestelldatum']);
                    $sl      = $statusLabels[$b['status']] ?? ['label' => $b['status'], 'class' => ''];
                    $offen   = (float)$b['gesamt_bestellt'] - (float)$b['gesamt_eingegangen'];
                ?>
                    <tr>
                        <td><a href="/mealana/bestellungen/detail.php?id=<?= $b['id'] ?>" style="font-weight:600"><?= htmlspecialchars($nr) ?></a></td>
                        <td><?= htmlspecialchars($b['lieferant_name']) ?></td>
                        <td><?= date('d.m.Y', strtotime($b['bestelldatum'])) ?></td>
                        <td><?= $b['erwartet_am'] ? date('d.m.Y', strtotime($b['erwartet_am'])) : ($b['lieferzeit_text'] ? htmlspecialchars($b['lieferzeit_text']) : '—') ?></td>
                        <td><?= (int)$b['anzahl_positionen'] ?> Pos. <?= $offen > 0 ? '<span style="color:var(--color-warning);font-size:12px">(' . number_format($offen, 0) . ' offen)</span>' : '' ?></td>
                        <td><?= $b['gesamt_ek'] !== null ? number_format((float)$b['gesamt_ek'], 2, ',', '.') . ' €' : '—' ?></td>
                        <td><span class="chip <?= $sl['class'] ?>"><?= $sl['label'] ?></span></td>
                        <td>
                            <a href="/mealana/bestellungen/detail.php?id=<?= $b['id'] ?>" class="btn btn-secondary btn-sm">Detail</a>
                            <?php if (in_array($b['status'], ['offen','teilgeliefert'])): ?>
                                <a href="/mealana/wareneingang/detail.php?bestellung_id=<?= $b['id'] ?>" class="btn btn-primary btn-sm">Wareneingang</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
