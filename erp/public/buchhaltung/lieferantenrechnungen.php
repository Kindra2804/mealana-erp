<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

$service = new BestellungService();
$fehler  = $_SESSION['fehler'] ?? null;
$erfolg  = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$filter = $_GET['filter'] ?? 'offen';
if (!in_array($filter, ['offen', 'teilbezahlt', 'bezahlt', 'alle'], true)) {
    $filter = 'offen';
}
$rechnungen = $service->getLieferantenrechnungen($filter === 'alle' ? '' : $filter);

$heute = date('Y-m-d');

$statusLabels = [
    'offen'       => ['label' => 'offen',       'class' => 'chip-inaktiv'],
    'teilbezahlt' => ['label' => 'teilbezahlt', 'class' => 'chip-auslauf'],
    'bezahlt'     => ['label' => 'bezahlt',     'class' => 'chip-aktiv'],
];

$pageTitle    = 'Lieferantenrechnungen';
$activeModule = 'buchhaltung';
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
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Kreditoren-Übersicht — Lieferantenrechnungen</span>
        <div style="display:flex;gap:6px">
            <a href="?filter=offen"       class="btn btn-sm <?= $filter === 'offen'       ? 'btn-primary' : 'btn-secondary' ?>">Offen</a>
            <a href="?filter=teilbezahlt" class="btn btn-sm <?= $filter === 'teilbezahlt' ? 'btn-primary' : 'btn-secondary' ?>">Teilbezahlt</a>
            <a href="?filter=bezahlt"     class="btn btn-sm <?= $filter === 'bezahlt'     ? 'btn-primary' : 'btn-secondary' ?>">Bezahlt</a>
            <a href="?filter=alle"        class="btn btn-sm <?= $filter === 'alle'        ? 'btn-primary' : 'btn-secondary' ?>">Alle</a>
        </div>
    </div>
    <div style="font-size:12px;color:var(--color-text-muted);padding:0 16px 10px">
        Rechnungsdaten kommen aus der jeweiligen Bestellung (Einkauf). Fälligkeit = Rechnungsdatum + Zahlungsziel des Lieferanten, Skonto-Frist analog aus den Lieferanten-Stammdaten.
        Zahlungen (auch Guthaben-Verrechnungen im DROPS-Modell) werden direkt auf der Bestellung gebucht.
    </div>
    <table class="erp-table">
        <thead>
            <tr>
                <th>Lieferant</th>
                <th>Rechnungs-Nr.</th>
                <th>Rechnungsdatum</th>
                <th style="text-align:right">Betrag</th>
                <th style="text-align:right">Bezahlt</th>
                <th>Fällig am</th>
                <th>Skonto</th>
                <th>Status</th>
                <th style="width:100px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rechnungen as $r): ?>
            <?php
                $ueberfaellig    = $r['zahlungsstatus'] !== 'bezahlt' && $r['faellig_am'] !== null && $r['faellig_am'] < $heute;
                $skontoNochOffen = $r['skonto_frist_am'] !== null && $r['zahlungsstatus'] !== 'bezahlt' && $r['skonto_frist_am'] >= $heute;
                $sl              = $statusLabels[$r['zahlungsstatus']] ?? ['label' => $r['zahlungsstatus'], 'class' => ''];
            ?>
            <tr>
                <td><a href="<?= BASE_PATH ?>/lieferanten/detail.php?id=<?= $r['id'] ?>" style="color:var(--color-nav)"><?= htmlspecialchars($r['lieferant_name']) ?></a></td>
                <td>
                    <a href="<?= BASE_PATH ?>/bestellungen/detail.php?id=<?= $r['id'] ?>" style="color:var(--color-nav)">
                        <?= htmlspecialchars($r['rechnung_nummer']) ?>
                    </a>
                </td>
                <td><?= $r['rechnung_datum'] ? date('d.m.Y', strtotime($r['rechnung_datum'])) : '—' ?></td>
                <td style="text-align:right;font-weight:600"><?= $r['rechnung_betrag'] !== null ? number_format((float)$r['rechnung_betrag'], 2, ',', '.') . ' €' : '—' ?></td>
                <td style="text-align:right"><?= number_format((float)$r['bezahlt'], 2, ',', '.') ?> €</td>
                <td style="<?= $ueberfaellig ? 'color:var(--color-danger);font-weight:600' : '' ?>">
                    <?= $r['faellig_am'] ? date('d.m.Y', strtotime($r['faellig_am'])) : '—' ?>
                    <?= $ueberfaellig ? ' ⚠' : '' ?>
                </td>
                <td>
                    <?php if ($skontoNochOffen): ?>
                        <span style="color:var(--color-success);font-size:12px">
                            <?= rtrim(rtrim(number_format((float)$r['skonto_prozent'], 2, ',', '.'), '0'), ',') ?>% bis <?= date('d.m.Y', strtotime($r['skonto_frist_am'])) ?>
                        </span>
                    <?php elseif ($r['skonto_prozent']): ?>
                        <span style="color:var(--color-text-muted);font-size:12px">abgelaufen</span>
                    <?php else: ?>
                        <span style="color:var(--color-text-muted);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="chip <?= $ueberfaellig && $sl['class'] !== 'chip-aktiv' ? 'chip-auslauf' : $sl['class'] ?>"><?= $sl['label'] ?></span>
                </td>
                <td>
                    <?php if ($r['zahlungsstatus'] !== 'bezahlt'): ?>
                        <a href="<?= BASE_PATH ?>/bestellungen/detail.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">Zahlung buchen</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rechnungen)): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--color-text-muted);padding:20px">Keine Lieferantenrechnungen in dieser Ansicht</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
