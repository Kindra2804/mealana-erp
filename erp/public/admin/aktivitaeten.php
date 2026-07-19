<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/admin/AktivitaetenService.php';

$service = new AktivitaetenService();

$filter = [
    'benutzer_id' => $_GET['benutzer_id'] ?? '',
    'modul'       => $_GET['modul'] ?? '',
    'tabelle'     => $_GET['tabelle'] ?? '',
    'stufe'       => $_GET['stufe'] ?? '',
    'von'         => $_GET['von'] ?? '',
    'bis'         => $_GET['bis'] ?? '',
    'suche'       => trim($_GET['suche'] ?? ''),
];
$seite    = (int)($_GET['seite'] ?? 1);
$proSeite = (int)($_GET['pro_seite'] ?? 25);

$ergebnis = $service->getGefiltert($filter, $seite, $proSeite);
$alleBenutzer   = $service->getBenutzerListe();
$alleModule     = $service->getModule();
$alleTabellen   = $service->getReferenzTabellen();

$stufeLabels = [
    'info'  => ['label' => 'Info',  'farbe' => 'var(--color-success)'],
    'warn'  => ['label' => 'Warnung', 'farbe' => 'var(--color-warning)'],
    'error' => ['label' => 'Fehler', 'farbe' => 'var(--color-danger)'],
];

$pageTitle        = 'Aktivitäten-Log';
$activeModule     = 'einstellungen';
$actionBarContent = '';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="filter-bar" style="margin-bottom:12px;flex-wrap:wrap">
    <input type="text" class="erp-input" placeholder="Suche in Aktion/Details…" style="width:220px;font-size:13px"
        value="<?= htmlspecialchars($filter['suche']) ?>" id="af-suche"
        onkeydown="if(event.key==='Enter') afApplyFilter()">

    <select class="erp-select" style="font-size:13px" id="af-benutzer">
        <option value="">Alle Benutzer</option>
        <?php foreach ($alleBenutzer as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= (string)$filter['benutzer_id'] === (string)$b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['formularname']) ?>
            </option>
        <?php endforeach ?>
    </select>

    <select class="erp-select" style="font-size:13px" id="af-modul">
        <option value="">Alle Module</option>
        <?php foreach ($alleModule as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>" <?= $filter['modul'] === $m ? 'selected' : '' ?>>
                <?= htmlspecialchars($m) ?>
            </option>
        <?php endforeach ?>
    </select>

    <select class="erp-select" style="font-size:13px" id="af-tabelle">
        <option value="">Alle Tabellen</option>
        <?php foreach ($alleTabellen as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $filter['tabelle'] === $t ? 'selected' : '' ?>>
                <?= htmlspecialchars($t) ?>
            </option>
        <?php endforeach ?>
    </select>

    <select class="erp-select" style="font-size:13px" id="af-stufe">
        <option value="">Alle Stufen</option>
        <?php foreach ($stufeLabels as $key => $s): ?>
            <option value="<?= $key ?>" <?= $filter['stufe'] === $key ? 'selected' : '' ?>><?= $s['label'] ?></option>
        <?php endforeach ?>
    </select>

    <input type="date" class="erp-input" style="width:140px;font-size:13px" id="af-von" value="<?= htmlspecialchars($filter['von']) ?>">
    <span style="color:var(--color-text-muted)">–</span>
    <input type="date" class="erp-input" style="width:140px;font-size:13px" id="af-bis" value="<?= htmlspecialchars($filter['bis']) ?>">

    <button class="btn btn-secondary btn-sm" onclick="afApplyFilter()">Filtern</button>
    <a href="aktivitaeten.php" class="btn btn-secondary btn-sm">Zurücksetzen</a>
</div>

<div class="card">
    <?php if (empty($ergebnis['items'])): ?>
        <p style="color:var(--color-text-muted);padding:16px">Keine Aktivitäten gefunden.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th style="width:24px"></th>
                    <th>Datum/Zeit</th>
                    <th>Benutzer</th>
                    <th>Aktion</th>
                    <th>Referenz</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ergebnis['items'] as $i => $a):
                    $stufe = $stufeLabels[$a['stufe']] ?? $stufeLabels['info'];
                    $referenz = $a['referenz_tabelle']
                        ? htmlspecialchars($a['referenz_tabelle']) . ' #' . (int)$a['referenz_id']
                        : '—';
                    $details = $a['details'] ? json_decode($a['details'], true) : null;
                    $rowId = 'af-row-' . $i;
                ?>
                    <tr>
                        <td title="<?= $stufe['label'] ?>">
                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $stufe['farbe'] ?>"></span>
                        </td>
                        <td style="white-space:nowrap"><?= date('d.m.Y H:i:s', strtotime($a['erstellt_am'])) ?></td>
                        <td><?= htmlspecialchars($a['benutzer_name'] ?? '—') ?></td>
                        <td><code><?= htmlspecialchars($a['aktion']) ?></code></td>
                        <td><?= $referenz ?></td>
                        <td>
                            <?php if ($details): ?>
                                <button class="btn btn-secondary btn-sm" onclick="document.getElementById('<?= $rowId ?>').classList.toggle('af-hidden')">Details</button>
                            <?php endif ?>
                        </td>
                    </tr>
                    <?php if ($details): ?>
                        <tr id="<?= $rowId ?>" class="af-hidden">
                            <td></td>
                            <td colspan="5">
                                <pre style="margin:0;padding:8px;background:var(--color-bg-secondary);border-radius:4px;font-size:12px;white-space:pre-wrap"><?= htmlspecialchars(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
                            </td>
                        </tr>
                    <?php endif ?>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<div class="card">
    <div class="pagination-bar">
        <div>
            Zeige <?= ($ergebnis['seite'] - 1) * $ergebnis['pro_seite'] + 1 ?>–<?= min($ergebnis['seite'] * $ergebnis['pro_seite'], $ergebnis['gesamt']) ?> von <?= $ergebnis['gesamt'] ?> Einträgen
        </div>
        <div class="pagination">
            <?php for ($p = 1; $p <= $ergebnis['seiten']; $p++):
                $params = array_merge($_GET, ['seite' => $p]);
                $qs = http_build_query($params);
                $aktiv = ($p === $ergebnis['seite']) ? 'active' : '';
            ?>
                <a class="<?= $aktiv ?>" href="aktivitaeten.php?<?= $qs ?>"><?= $p ?></a>
            <?php endfor ?>
        </div>
        <div>
            Pro Seite:
            <select onchange="
                var p = new URLSearchParams(window.location.search);
                p.set('pro_seite', this.value);
                p.set('seite', 1);
                window.location.href = 'aktivitaeten.php?' + p.toString();
            ">
                <option value="25" <?= $proSeite == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $proSeite == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $proSeite == 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>
    </div>
</div>

<style>.af-hidden { display: none; }</style>
<script src="<?= BASE_PATH ?>/js/admin_aktivitaeten.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
