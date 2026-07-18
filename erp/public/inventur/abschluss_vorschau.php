<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';

$service = new InventurService();
$laufId  = (int)($_GET['lauf_id'] ?? 0);
$vorschau = $service->vorschauAbschluss($laufId);
if (!$vorschau['erfolg']) {
    $_SESSION['fehler'] = $vorschau['fehler'];
    header('Location: ' . BASE_PATH . '/inventur/liste.php');
    exit;
}

$lauf = $vorschau['lauf'];
$fehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['fehler']);

$pageTitle        = 'Prüfen: ' . $lauf['scope_bezeichnung'];
$activeModule     = 'lager';
$actionBarContent = '<a href="' . BASE_PATH . '/inventur/liste.php" class="btn btn-secondary btn-sm">← Zur Liste</a>';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:12px">
    <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase">Vor dem Abschluss prüfen</div>
    <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($lauf['scope_bezeichnung']) ?></div>
</div>

<div class="card" style="margin-bottom:12px">
    <strong style="font-size:13px;display:block;margin-bottom:10px">
        Abweichungen (Soll ≠ Ist) — <?= count($vorschau['abweichungen']) ?> Zeile(n)
    </strong>
    <?php if (empty($vorschau['abweichungen'])): ?>
        <p style="color:var(--color-text-muted);margin:0">Keine Abweichungen in den bisher gezählten (vollständigen) Positionen.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th>Lager</th>
                    <th>Lagerplatz</th>
                    <th>Charge</th>
                    <th style="text-align:right">Soll</th>
                    <th style="text-align:right">Ist</th>
                    <th style="text-align:right">Differenz</th>
                    <th>Notiz</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vorschau['abweichungen'] as $a): $diff = $a['ist'] - $a['soll']; ?>
                <tr>
                    <td><?= htmlspecialchars($a['artikel_name']) ?> <span style="color:var(--color-text-muted);font-size:11px">(<?= htmlspecialchars($a['artikelnummer']) ?>)</span></td>
                    <td><?= htmlspecialchars($a['lager_name']) ?></td>
                    <td><?= htmlspecialchars($a['lagerplatz_bezeichnung'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($a['charge'] ?? '—') ?></td>
                    <td style="text-align:right"><?= number_format($a['soll'], 0) ?></td>
                    <td style="text-align:right"><?= number_format($a['ist'], 0) ?></td>
                    <td style="text-align:right;font-weight:600;color:<?= $diff < 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>">
                        <?= $diff > 0 ? '+' : '' ?><?= number_format($diff, 0) ?>
                    </td>
                    <td style="color:var(--color-text-muted)"><?= htmlspecialchars($a['notiz'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($vorschau['unvollstaendig'])): ?>
<div class="card" style="margin-bottom:12px">
    <strong style="font-size:13px;display:block;margin-bottom:10px">
        ⚠ Noch nicht vollständig gezählt — wird beim Abschluss NICHT gebucht
    </strong>
    <table class="erp-table">
        <thead><tr><th>Artikel</th><th>Lager</th></tr></thead>
        <tbody>
        <?php foreach ($vorschau['unvollstaendig'] as $g): ?>
            <tr>
                <td><?= htmlspecialchars($g['artikel_name']) ?> <span style="color:var(--color-text-muted);font-size:11px">(<?= htmlspecialchars($g['artikelnummer']) ?>)</span></td>
                <td><?= htmlspecialchars($g['lager_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:8px">
        <a href="<?= BASE_PATH ?>/inventur/zaehlen.php?lauf_id=<?= $laufId ?>" style="font-size:12px;color:var(--color-nav)">→ Noch fehlende Positionen zählen</a>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <form method="post" action="<?= BASE_PATH ?>/inventur/abschluss_bestaetigen.php" onsubmit="return confirm('Jetzt buchen und Inventur abschließen? Das korrigiert echte Lagerbestände.')">
            <input type="hidden" name="id" value="<?= $laufId ?>">
            <button type="submit" class="btn btn-primary btn-sm">✅ Jetzt buchen &amp; abschließen</button>
        </form>
        <?php if ($lauf['status'] === 'laufend'): ?>
        <form method="post" action="<?= BASE_PATH ?>/inventur/pausieren.php">
            <input type="hidden" name="id" value="<?= $laufId ?>">
            <button type="submit" class="btn btn-secondary btn-sm">⏸ Ohne Buchung pausieren</button>
        </form>
        <?php endif; ?>
        <form method="post" action="<?= BASE_PATH ?>/inventur/abbrechen.php" onsubmit="return confirm('Wirklich verwerfen? Alle bisher gezählten Mengen bleiben unverbucht.')">
            <input type="hidden" name="id" value="<?= $laufId ?>">
            <button type="submit" class="btn btn-danger btn-sm">✕ Verwerfen ohne Buchung</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
