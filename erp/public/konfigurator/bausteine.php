<?php
/**
 * Schild-Bausteine (Migration 232): Kürzel + Rohmaterial zentral je Achse + Wert für alle
 * Konfigurator-Layouts. Logik: src/modules/konfigurator/KonfigBausteineService.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/konfigurator/KonfigBausteineService.php';

$service = new KonfigBausteineService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fehler = $service->speichern($_POST['b'] ?? []);
    if ($fehler) {
        $_SESSION['fehler'] = $fehler;
        $_SESSION['bausteine_eingabe'] = $_POST['b'] ?? [];
    } else {
        $_SESSION['erfolg'] = 'Bausteine gespeichert. Der Shop übernimmt die Verfügbarkeit beim nächsten Sync.';
    }
    header('Location: bausteine.php');
    exit;
}

$fehler  = $_SESSION['fehler'] ?? [];
$erfolg  = $_SESSION['erfolg'] ?? null;
$eingabe = $_SESSION['bausteine_eingabe'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['bausteine_eingabe']);

$zeilen = $service->alle();
$gruppen = [];
foreach ($zeilen as $z) $gruppen[$z['achse_id']]['name'] = $z['achse'];
foreach ($zeilen as $z) $gruppen[$z['achse_id']]['werte'][] = $z;
$ohneKuerzel = count(array_filter($zeilen, fn($z) => $z['kuerzel'] === null));
$leer = count(array_filter($zeilen, fn($z) => $z['ampel'] === 'rot'));

$ampelFarbe = ['gruen' => '#16a34a', 'gelb' => '#d97706', 'rot' => '#dc2626'];
$ampelText  = ['gruen' => 'vorrätig', 'gelb' => 'knapp (≤ Mindestbestand)', 'rot' => 'leer — im Shop ausgegraut'];

$pageTitle    = 'Schild-Bausteine';
$activeModule = 'artikel';
$actionBarContent = '<button form="bausteine-form" class="btn btn-primary btn-sm">Speichern</button>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
    <div class="card" style="border-left:4px solid var(--color-danger);margin-bottom:12px">
        <p style="margin:0 0 4px;color:var(--color-danger)"><strong>Nicht gespeichert:</strong></p>
        <ul style="margin:0;padding-left:18px;color:var(--color-danger)"><?php foreach ((array) $fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php if ($erfolg): ?>
    <div class="card" style="border-left:4px solid var(--color-success);margin-bottom:12px"><p style="margin:0;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></p></div>
<?php endif; ?>

<div class="card" style="margin-bottom:12px;font-size:13px">
    <p style="margin:0 0 6px">Jeder Wert der Schild-Layouts bekommt hier <strong>einmal für alle Layouts</strong>:</p>
    <ul style="margin:0;padding-left:18px">
        <li><strong>Kürzel</strong> — Baustein der Artikelnummer fertiger Schilder (z.B. <code>ID-W01-20-2F-RT-WS-FI-RT-GL</code>). Leer = kommt nicht in die Nummer (z.B. „nein, Danke“).</li>
        <li><strong>Rohmaterial</strong> — Artikelnummer des Lagerartikels (z.B. Filzplatte rot). Bestand 0 → der Wert ist im Shop ausgegraut, an der Kasse erscheint ein Hinweis.</li>
    </ul>
    <p style="margin:6px 0 0;color:var(--color-text-muted)"><?= count($zeilen) ?> Werte · <?= $ohneKuerzel ?> ohne Kürzel · <?= $leer ?> mit leerem Rohmaterial</p>
</div>

<?php if (!$zeilen): ?>
    <div class="card"><p style="margin:0">Noch keine konfigurierbaren Artikel mit Werten vorhanden.</p></div>
<?php else: ?>
<form id="bausteine-form" method="POST">
    <?php foreach ($gruppen as $achseId => $g): ?>
    <div class="card" style="margin-bottom:12px">
        <h3 style="margin:0 0 8px"><?= htmlspecialchars($g['name']) ?></h3>
        <table class="erp-table" style="font-size:13px">
            <thead><tr><th>Wert</th><th style="width:70px;text-align:right">Layouts</th><th style="width:110px">Kürzel</th><th style="width:200px">Rohmaterial (Artikelnummer)</th><th>Material</th></tr></thead>
            <tbody>
            <?php foreach ($g['werte'] as $w):
                $key = $achseId . '|' . $w['wert'];
                $k = $eingabe[$key]['kuerzel'] ?? ($w['kuerzel'] ?? '');
                $r = $eingabe[$key]['rohmaterial'] ?? ($w['roh_nr'] ?? ''); ?>
                <tr>
                    <td><?= htmlspecialchars($w['wert']) ?></td>
                    <td style="text-align:right;color:var(--color-text-muted)"><?= (int) $w['layouts'] ?></td>
                    <td><input type="text" name="b[<?= htmlspecialchars($key) ?>][kuerzel]" value="<?= htmlspecialchars($k) ?>" maxlength="12" class="erp-select" style="width:100%;font-family:monospace"></td>
                    <td><input type="text" name="b[<?= htmlspecialchars($key) ?>][rohmaterial]" value="<?= htmlspecialchars($r) ?>" class="erp-select" style="width:100%" placeholder="–"></td>
                    <td>
                        <?php if ($w['ampel']): ?>
                            <span title="<?= $ampelText[$w['ampel']] ?>" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $ampelFarbe[$w['ampel']] ?>;margin-right:6px"></span><?= htmlspecialchars($w['roh_name']) ?>
                            <span style="color:var(--color-text-muted)">· <?= rtrim(rtrim(number_format((float) $w['roh_bestand'], 3, ',', '.'), '0'), ',') ?> Stk.</span>
                        <?php else: ?><span style="color:var(--color-text-muted)">–</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
    <button class="btn btn-primary btn-sm">Speichern</button>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
