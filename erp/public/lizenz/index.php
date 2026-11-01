<?php
// Lizenzverwaltung (Ebene 1): eigene Lizenz ansehen + Lizenzdatei einspielen — nur Superadmin
require_once __DIR__ . '/../includes/auth_check.php';

$db     = Database::getInstance();
$status = Lizenz::status();
$daten  = $status['daten'];
$firma  = Lizenz::firmendaten();

// Nutzung für Module mit Menge
$nutzung = [
    'kasse' => (int)$db->query("SELECT COUNT(*) FROM kassen WHERE aktiv = 1")->fetchColumn(),
    'shop'  => (int)$db->query("SELECT COUNT(*) FROM shops WHERE ist_aktiv = 1")->fetchColumn(),
];

$historie = $db->query("
    SELECT l.id, l.lizenz_nr, l.aktiv, l.eingespielt_am, b.formularname
    FROM lizenz_dateien l LEFT JOIN benutzer b ON b.id = l.eingespielt_von
    ORDER BY l.id DESC LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$erfolg = $_SESSION['erfolg'] ?? null;
$fehler = $_SESSION['fehler'] ?? [];
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$statusText = [
    'aktiv'         => ['Lizenz aktiv', '#16a34a'],
    'keine'         => ['Keine Lizenz — nur Grundmodul', '#2563eb'],
    'ungueltig'     => ['Lizenzdatei ungültig — nur Grundmodul', '#dc2626'],
    'falsche_firma' => ['Lizenz passt nicht zu den Firmendaten — nur Grundmodul', '#dc2626'],
][$status['status']];

$pageTitle    = 'Lizenzverwaltung';
$activeModule = 'einstellungen';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($erfolg): ?>
    <div class="banner banner-success" id="erfolg-banner"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>
<?php foreach ($fehler as $f): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;color:var(--color-danger)"><?= htmlspecialchars($f) ?></div>
<?php endforeach; ?>

<div class="card" style="margin-bottom:12px;border-left:3px solid <?= $statusText[1] ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
            <div style="font-size:18px;font-weight:700;color:<?= $statusText[1] ?>"><?= $statusText[0] ?></div>
            <?php if ($status['fehler']): ?>
                <div style="margin-top:4px;font-size:13px"><?= htmlspecialchars($status['fehler']) ?></div>
            <?php endif; ?>
            <?php if ($daten): ?>
                <div style="margin-top:8px;font-size:13px;display:grid;grid-template-columns:auto auto;gap:2px 14px">
                    <span style="color:var(--color-text-muted)">Lizenz-Nr.</span><span><?= htmlspecialchars($daten['lizenz_nr']) ?><?= !empty($daten['hauslizenz']) ? ' · Hauslizenz' : '' ?></span>
                    <span style="color:var(--color-text-muted)">Lizenziert für</span><span><?= htmlspecialchars($daten['firma']) ?> · <?= htmlspecialchars($daten['uid'] ?: $daten['steuernummer']) ?></span>
                    <span style="color:var(--color-text-muted)">Ausgestellt am</span><span><?= date('d.m.Y', strtotime($daten['ausgestellt_am'])) ?></span>
                    <span style="color:var(--color-text-muted)">Wartung bis</span>
                    <span>
                        <?php if (empty($daten['wartung_bis'])): ?>unbegrenzt
                        <?php else: ?>
                            <?= date('d.m.Y', strtotime($daten['wartung_bis'])) ?>
                            <?php if ($daten['wartung_bis'] < date('Y-m-d')): ?>
                                <strong style="color:var(--color-danger)">— abgelaufen (Programm läuft weiter, keine neuen Updates)</strong>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);text-align:right">
            Firmendaten dieser Installation (Einstellungen → Firma):<br>
            <strong><?= htmlspecialchars($firma['firmenname'] ?: '–') ?></strong><br>
            UID <?= htmlspecialchars($firma['uid_nummer'] ?: '–') ?> · St.-Nr. <?= htmlspecialchars($firma['steuernummer'] ?: '–') ?>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:12px">
    <div class="card-header">Module</div>
    <table class="erp-table">
        <thead><tr><th>Modul</th><th>Status</th><th style="text-align:right">Lizenziert</th><th style="text-align:right">In Verwendung</th></tr></thead>
        <tbody>
        <?php foreach (Lizenz::MODULE as $code => $m):
            $anz = Lizenz::anzahl($code);
            $ueber = $m['menge'] && isset($nutzung[$code]) && $nutzung[$code] > $anz; ?>
            <tr style="<?= $anz ? '' : 'color:var(--color-text-muted)' ?>">
                <td><?= htmlspecialchars($m['label']) ?></td>
                <td><?= $anz ? '✓ lizenziert' : '<span style="filter:grayscale(1)">🔒</span> nicht lizenziert' ?></td>
                <td style="text-align:right"><?= $m['menge'] ? ($anz >= 99 ? 'unbegrenzt' : $anz . ' ' . $m['einheit']) : '' ?></td>
                <td style="text-align:right;<?= $ueber ? 'color:var(--color-danger);font-weight:600' : '' ?>">
                    <?= $m['menge'] && isset($nutzung[$code]) ? $nutzung[$code] . ' aktiv' . ($ueber ? ' — mehr als lizenziert' : '') : '' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card" style="margin-bottom:12px">
    <div class="card-header">Lizenzdatei einspielen</div>
    <form method="post" action="hochladen.php" enctype="multipart/form-data" style="padding:14px 16px;display:grid;gap:10px">
        <div style="font-size:13px;color:var(--color-text-muted)">
            Die Lizenzdatei (.lic) bekommt ihr von NAHTLOS. Sie gilt nur für die oben angezeigten Firmendaten.
            Die bisherige Lizenz bleibt in der Historie.
        </div>
        <input type="file" name="datei" accept=".lic,.json,.txt" class="erp-input">
        <details>
            <summary style="cursor:pointer;font-size:13px">… oder Inhalt einfügen</summary>
            <textarea name="inhalt" class="erp-input" rows="6" style="width:100%;font-family:monospace;font-size:12px;margin-top:6px"></textarea>
        </details>
        <div><button type="submit" class="btn btn-primary btn-sm">Lizenz einspielen</button></div>
    </form>
</div>

<?php if ($historie): ?>
<div class="card">
    <div class="card-header">Historie</div>
    <table class="erp-table">
        <thead><tr><th>Lizenz-Nr.</th><th>Eingespielt am</th><th>von</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($historie as $h): ?>
            <tr>
                <td><?= htmlspecialchars($h['lizenz_nr']) ?></td>
                <td><?= date('d.m.Y H:i', strtotime($h['eingespielt_am'])) ?></td>
                <td><?= htmlspecialchars($h['formularname'] ?? '–') ?></td>
                <td><?= $h['aktiv'] ? '<span class="chip chip-aktiv">aktiv</span>' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
