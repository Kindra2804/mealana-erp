<?php
// Lizenzverwaltung (Ebene 2): Lizenzkunden + Ausgaben — nur sichtbar mit Schlüsseldatei (LizenzGenerator::aktiv)
require_once __DIR__ . '/../includes/auth_check.php';

if (!Lizenz::generatorAktiv()) {   // lädt LizenzGenerator, beim Kunden nicht vorhanden
    header('Location: index.php');
    exit;
}
$gen    = new LizenzGenerator();
$kunden = $gen->kunden();
$offen  = (int)($_GET['kunde'] ?? 0);
$neu    = (int)($_GET['neu'] ?? 0);

$erfolg = $_SESSION['erfolg'] ?? null;
$fehler = $_SESSION['fehler'] ?? [];
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$modulKurz = function (?string $json): string {
    $m = json_decode((string)$json, true) ?: [];
    unset($m['grundmodul']);
    if (!$m) return 'nur Grundmodul';
    return implode(', ', array_map(fn($c, $a) => Lizenz::label($c) . (Lizenz::MODULE[$c]['menge'] ?? false ? ' ×' . ($a >= 99 ? '∞' : $a) : ''), array_keys($m), $m));
};

$pageTitle        = 'Lizenzkunden';
$activeModule     = 'einstellungen';
$actionBarContent = '<a href="index.php" class="btn btn-secondary btn-sm">← Eigene Lizenz</a>'
                  . '<a href="ausstellen.php" class="btn btn-primary btn-sm">+ Neue Lizenz ohne Auftrag</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($erfolg): ?><div class="banner banner-success" id="erfolg-banner"><?= htmlspecialchars($erfolg) ?></div><?php endif; ?>
<?php foreach ($fehler as $f): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;color:var(--color-danger)"><?= htmlspecialchars($f) ?></div>
<?php endforeach; ?>
<?php if ($neu): $na = $gen->ausgabe($neu); ?>
    <?php if ($na): ?>
    <div class="card" style="margin-bottom:12px;border-left:3px solid #16a34a">
        Lizenz <strong><?= htmlspecialchars($na['lizenz_nr']) ?></strong>, Ausgabe <?= (int)$na['ausgabe'] ?> ausgestellt —
        <a class="btn btn-primary btn-sm" href="datei.php?id=<?= (int)$na['id'] ?>">⬇ <?= htmlspecialchars(LizenzGenerator::dateiname($na)) ?></a>
        <span style="font-size:12px;color:var(--color-text-muted)">Datei an den Kunden schicken → dort ··· → Lizenzverwaltung → einspielen.</span>
    </div>
    <?php endif; ?>
<?php endif; ?>

<div class="card">
    <table class="erp-table">
        <thead><tr><th>Lizenz-Nr.</th><th>Firma</th><th>UID / St.-Nr.</th><th>Module (letzte Ausgabe)</th><th>Wartung bis</th><th>Ausgabe</th><th></th></tr></thead>
        <tbody>
        <?php if (!$kunden): ?>
            <tr><td colspan="7" style="color:var(--color-text-muted)">Noch keine Lizenzen ausgestellt.</td></tr>
        <?php endif; ?>
        <?php foreach ($kunden as $k):
            $abgelaufen = $k['wartung_bis'] && $k['wartung_bis'] < date('Y-m-d'); ?>
            <tr>
                <td style="font-family:monospace;font-weight:600;white-space:nowrap"><?= htmlspecialchars($k['lizenz_nr']) ?></td>
                <td>
                    <?php if ($k['kunde_id']): ?><a href="<?= BASE_PATH ?>/kunden/detail.php?id=<?= (int)$k['kunde_id'] ?>"><?= htmlspecialchars($k['firma']) ?></a>
                    <?php else: ?><?= htmlspecialchars($k['firma']) ?><?php endif; ?>
                    <?= $k['hauslizenz'] ? ' <span class="chip chip-auslauf">Hauslizenz</span>' : '' ?>
                </td>
                <td><?= htmlspecialchars($k['uid'] ?: ($k['steuernummer'] ?: '–')) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($modulKurz($k['module'])) ?></td>
                <td style="<?= $abgelaufen ? 'color:var(--color-danger);font-weight:600' : '' ?>">
                    <?= $k['ausgabe_id'] ? ($k['wartung_bis'] ? date('d.m.Y', strtotime($k['wartung_bis'])) : 'unbegrenzt') : '–' ?>
                </td>
                <td><?= $k['ausgabe'] ? (int)$k['ausgabe'] . ' · ' . date('d.m.Y', strtotime($k['ausgabe_am'])) : '–' ?></td>
                <td style="white-space:nowrap">
                    <a class="btn btn-secondary btn-sm" href="?kunde=<?= (int)$k['id'] ?>#historie">Historie</a>
                    <a class="btn btn-secondary btn-sm" href="ausstellen.php?lizenz_kunde_id=<?= (int)$k['id'] ?>">Neue Ausgabe</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($offen && ($lk = $gen->kunde($offen))): ?>
<div class="card" id="historie" style="margin-top:12px">
    <div class="card-header">Historie <?= htmlspecialchars($lk['lizenz_nr']) ?> · <?= htmlspecialchars($lk['firma']) ?></div>
    <table class="erp-table">
        <thead><tr><th>Ausgabe</th><th>Datum</th><th>Module</th><th>Wartung bis</th><th>Auftrag</th><th>Grund</th><th>von</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($gen->ausgaben($offen) as $a): ?>
            <tr>
                <td><?= (int)$a['ausgabe'] ?></td>
                <td><?= date('d.m.Y H:i', strtotime($a['erstellt_am'])) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($modulKurz($a['module'])) ?></td>
                <td><?= $a['wartung_bis'] ? date('d.m.Y', strtotime($a['wartung_bis'])) : 'unbegrenzt' ?></td>
                <td><?= $a['auftrag_id'] ? '<a href="' . BASE_PATH . '/auftraege/detail.php?id=' . (int)$a['auftrag_id'] . '">' . htmlspecialchars($a['auftrag_nr']) . '</a>' : '–' ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($a['grund'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['formularname'] ?? '–') ?></td>
                <td><a class="btn btn-secondary btn-sm" href="datei.php?id=<?= (int)$a['id'] ?>">⬇ .lic</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
