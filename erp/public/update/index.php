<?php
// Updates einspielen (Paket von NAHTLOS) — nur Superadmin
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/update/UpdateService.php';

$version  = UpdateService::installierteVersion();
$lizenz   = Lizenz::status();
$wartung  = Lizenz::wartungBis();
$verlauf  = UpdateService::verlauf();
$paket    = $_SESSION['update_paket'] ?? null;
$pruefung = ($paket && is_file($paket)) ? UpdateService::pruefen($paket) : null;
$ergebnis = $_SESSION['update_ergebnis'] ?? null;
$erfolg   = $_SESSION['erfolg'] ?? null;
$fehler   = $_SESSION['fehler'] ?? [];
unset($_SESSION['update_ergebnis'], $_SESSION['erfolg'], $_SESSION['fehler']);

// „Was ist neu“ (Markdown-Liste aus docs/versionen/<v>.md): - Zeile, **fett**
$neuHtml = function (string $md): string {
    $zeilen = array_filter(array_map('trim', explode("\n", $md)));
    $html = '';
    foreach ($zeilen as $z) {
        $z = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars(ltrim($z, '-* ')));
        $html .= '<li>' . $z . '</li>';
    }
    return '<ul style="margin:6px 0 0;padding-left:20px;line-height:1.7">' . $html . '</ul>';
};
$statusText = [
    'laeuft' => ['läuft / abgebrochen', '#d97706'], 'erfolgreich' => ['erfolgreich', '#16a34a'],
    'fehlgeschlagen' => ['fehlgeschlagen', '#dc2626'], 'zurueckgespielt' => ['zurückgespielt', '#64748b'],
];
$letzter = $verlauf[0] ?? null;
$uploadMax = ini_get('upload_max_filesize');

$pageTitle        = 'Updates';
$activeModule     = 'einstellungen';
$actionBarContent = '';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="max-width:900px">

<?php if ($erfolg): ?>
    <div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>
<?php if ($fehler): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)"><?= implode('<br>', array_map('htmlspecialchars', (array)$fehler)) ?></div>
<?php endif; ?>

<?php if (UpdateService::wartungAktiv()): ?>
    <div class="card" style="border-left:3px solid #d97706;margin-bottom:12px;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px">
        <span><strong>Wartungsmodus ist an</strong> — Mitarbeiter sehen „Update läuft“. Nach einem fehlgeschlagenen Update zuerst die Sicherung zurückspielen.</span>
        <form method="post" action="aktion.php" onsubmit="return confirm('Wartungsmodus wirklich aufheben?')">
            <input type="hidden" name="aktion" value="wartung_aus"><button class="btn btn-sm">Aufheben</button>
        </form>
    </div>
<?php endif; ?>

<?php if ($ergebnis): ?>
    <?php if ($ergebnis['erfolg']): ?>
        <div class="card" style="border-left:3px solid var(--color-success);margin-bottom:16px;padding:14px 18px">
            <div style="font-size:16px;font-weight:700;color:var(--color-success)">✓ <?= htmlspecialchars($ergebnis['meldung']) ?></div>
            <?php $neuDatei = UpdateService::wurzel() . '/docs/versionen/' . $version . '.md'; ?>
            <?php if (is_file($neuDatei)): ?>
                <div style="margin-top:10px;font-weight:600">Was ist neu in <?= htmlspecialchars($version) ?></div>
                <?= $neuHtml(file_get_contents($neuDatei)) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:16px;padding:14px 18px">
            <div style="font-weight:700;color:var(--color-danger)">Update fehlgeschlagen</div>
            <div style="margin:6px 0 10px;font-size:13px"><?= htmlspecialchars($ergebnis['meldung']) ?></div>
            <?php if ($ergebnis['verlauf_id'] && UpdateService::wartungAktiv()): ?>
            <form method="post" action="aktion.php" onsubmit="return confirm('Sicherung jetzt zurückspielen? Dateien und Datenbank werden auf den Stand vor dem Update gesetzt.')">
                <input type="hidden" name="aktion" value="zurueckspielen"><input type="hidden" name="verlauf_id" value="<?= (int)$ergebnis['verlauf_id'] ?>">
                <button class="btn btn-primary">↩ Sicherung zurückspielen</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <div class="card-header">Installierte Version</div>
    <div style="padding:14px 16px;display:flex;gap:40px;font-size:14px">
        <div><div style="color:var(--color-text-muted);font-size:12px">Version</div><strong style="font-size:20px"><?= htmlspecialchars($version) ?></strong></div>
        <div><div style="color:var(--color-text-muted);font-size:12px">Lizenz</div>
            <?= $lizenz['status'] === 'aktiv' ? htmlspecialchars($lizenz['daten']['lizenz_nr'] ?? '') : '<span style="color:var(--color-danger)">keine — Testversion, keine Updates</span>' ?></div>
        <div><div style="color:var(--color-text-muted);font-size:12px">Updates bis (Wartung)</div>
            <?= $lizenz['status'] !== 'aktiv' ? '–' : ($wartung ? date('d.m.Y', strtotime($wartung)) . (Lizenz::wartungAbgelaufen() ? ' <span style="color:var(--color-danger)">(abgelaufen)</span>' : '') : 'unbegrenzt') ?></div>
    </div>
</div>

<?php if ($pruefung): $m = $pruefung['manifest']; ?>
<div class="card" style="margin-bottom:16px;border-left:3px solid <?= $pruefung['ok'] ? '#2563eb' : 'var(--color-danger)' ?>">
    <div class="card-header">Hochgeladenes Paket<?= $m ? ': Version ' . htmlspecialchars($m['version']) . ' vom ' . date('d.m.Y', strtotime($m['datum'])) : '' ?></div>
    <div style="padding:14px 16px;font-size:13px">
        <?php if ($m): ?>
            <div><strong><?= htmlspecialchars($version) ?> → <?= htmlspecialchars($m['version']) ?></strong> · <?= count($m['dateien']) ?> Dateien · Signatur von NAHTLOS ✓</div>
            <div style="margin-top:10px;font-weight:600">Was ist neu</div>
            <?= $neuHtml($m['neu'] ?? '') ?>
        <?php endif; ?>
        <?php foreach ($pruefung['fehler'] as $f): ?><div style="color:var(--color-danger);margin-top:8px">✗ <?= htmlspecialchars($f) ?></div><?php endforeach; ?>
        <?php foreach ($pruefung['hinweise'] as $h): ?><div style="color:#d97706;margin-top:8px">! <?= htmlspecialchars($h) ?></div><?php endforeach; ?>

        <div style="display:flex;gap:10px;align-items:flex-start;margin-top:16px;padding-top:14px;border-top:1px solid var(--color-border)">
            <?php if ($pruefung['ok']): ?>
            <form method="post" action="aktion.php" style="flex:1" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Update läuft … bitte warten (Seite nicht schließen)';">
                <input type="hidden" name="aktion" value="einspielen">
                <label style="display:flex;gap:8px;align-items:flex-start;margin-bottom:10px;cursor:pointer">
                    <input type="checkbox" name="bestaetigt" value="1" required style="margin-top:2px">
                    <span>Die Kassen sind abgeschlossen und gerade arbeitet niemand im ERP. Vorher wird die Datenbank gesichert; Mitarbeiter sehen während des Updates „Update läuft“.</span>
                </label>
                <button class="btn btn-primary">🔄 Update jetzt einspielen</button>
            </form>
            <?php endif; ?>
            <form method="post" action="aktion.php"><input type="hidden" name="aktion" value="verwerfen"><button class="btn">Paket verwerfen</button></form>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Update einspielen</div>
    <form method="post" action="aktion.php" enctype="multipart/form-data" style="padding:14px 16px;display:grid;gap:10px;font-size:13px">
        <input type="hidden" name="aktion" value="hochladen">
        <div style="color:var(--color-text-muted)">
            Das Update-Paket (<code>nahtlos-x.y.z.zip</code>) bekommst du von NAHTLOS. Es wird zuerst nur geprüft (Signatur, Version, Wartung) —
            eingespielt wird erst nach deiner Bestätigung. Maximale Größe hier: <?= htmlspecialchars($uploadMax) ?>.
        </div>
        <input type="file" name="paket" accept=".zip" class="erp-input" required>
        <div><button class="btn btn-primary btn-sm">Hochladen und prüfen</button></div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Verlauf</div>
    <?php if (!$verlauf): ?>
        <div style="padding:14px 16px;color:var(--color-text-muted);font-size:13px">Noch keine Updates eingespielt.</div>
    <?php else: ?>
    <table class="erp-table" style="width:100%">
        <thead><tr><th>Datum</th><th>Version</th><th>Status</th><th>Benutzer</th><th>Meldung</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($verlauf as $v): [$txt, $farbe] = $statusText[$v['status']] ?? [$v['status'], '#64748b']; ?>
            <tr>
                <td><?= date('d.m.Y H:i', strtotime($v['gestartet_am'])) ?></td>
                <td><?= htmlspecialchars($v['version_von']) ?> → <?= htmlspecialchars($v['version_nach']) ?></td>
                <td style="color:<?= $farbe ?>;font-weight:600"><?= $txt ?></td>
                <td><?= htmlspecialchars($v['formularname'] ?? '') ?></td>
                <td style="font-size:12px;max-width:300px"><?= htmlspecialchars((string)$v['meldung']) ?></td>
                <td>
                    <?php if ($letzter && $v['id'] === $letzter['id'] && in_array($v['status'], ['erfolgreich', 'fehlgeschlagen', 'laeuft'], true)
                              && is_file(UpdateService::ordner() . '/' . $v['sicherung_ordner'] . '/datenbank.sql')): ?>
                    <form method="post" action="aktion.php" onsubmit="return confirm('Auf den Stand vor diesem Update zurückspielen? Alles, was seit dem Update im ERP erfasst wurde, geht dabei verloren!')">
                        <input type="hidden" name="aktion" value="zurueckspielen"><input type="hidden" name="verlauf_id" value="<?= (int)$v['id'] ?>">
                        <button class="btn btn-sm">↩ Zurückspielen</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
