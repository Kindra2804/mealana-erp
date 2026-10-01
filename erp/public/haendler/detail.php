<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/haendler/HaendlerService.php';

$kundeId = (int)($_GET['kunde_id'] ?? 0);
$svc     = new HaendlerService();
$kunde   = (new KundenService())->getById($kundeId);
if (!$kunde) { header('Location: ' . BASE_PATH . '/haendler/liste.php'); exit; }
$name    = $svc->kundenName($kunde);
$lager   = $svc->getLager($kundeId);
$tab     = $_GET['tab'] ?? 'bestand';
$bestand = $lager ? $svc->getBestand($kundeId) : [];
$belege  = $lager && $tab === 'belege' ? $svc->getBelege($kundeId) : [];
$bewegungen = $lager && $tab === 'bewegungen' ? $svc->getBewegungen($kundeId) : [];

$pageTitle        = 'Händler ' . $name;
$activeModule     = 'verkauf';
$actionBarContent = '<a href="' . BASE_PATH . '/haendler/liste.php" class="btn btn-secondary btn-sm">← Händler</a>'
    . ' <a href="' . BASE_PATH . '/kunden/detail.php?id=' . $kundeId . '" class="btn btn-secondary btn-sm">Kunde</a>';
require_once __DIR__ . '/../includes/shell_top.php';

$zahl = fn($v) => rtrim(rtrim(number_format((float)$v, 3, ',', '.'), '0'), ',');
$tabLink = fn($key, $label) => '<a class="tab' . ($key === $tab ? ' active' : '') . '" href="?kunde_id=' . $kundeId . '&tab=' . $key . '">' . $label . '</a>';
?>
<div class="card" style="margin-bottom:12px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <div style="font-size:18px;font-weight:700;color:var(--color-nav)">🏬 <?= htmlspecialchars($name) ?></div>
    <span class="chip">Händler-Außenlager</span>
    <?php if ($lager): ?>
        <span style="font-size:12px;color:var(--color-text-muted)"><?= $zahl(array_sum(array_column($bestand, 'menge'))) ?> Stk beim Händler · Wert € <?= number_format(array_sum(array_column($bestand, 'wert_netto')), 2, ',', '.') ?> netto</span>
    <?php endif; ?>
</div>

<?php if (!$lager): ?>
<div class="card" style="padding:24px;text-align:center">
    <p style="margin-top:0;font-size:14px">Ware in Kommission an <strong><?= htmlspecialchars($name) ?></strong> liefern: Die Ware bleibt euer Bestand, bis der Händler den Verkauf meldet.<br>Beim Einrichten kommt der Kunde in die Kundengruppe „Händler“.</p>
    <button class="btn btn-primary" onclick="aktion({ aktion: 'lager_anlegen', kunde_id: <?= $kundeId ?> })">Als Händler einrichten</button>
</div>
<?php else: ?>

<div class="tab-bar" style="margin-bottom:12px">
    <?= $tabLink('bestand', 'Bestand beim Händler') ?>
    <?= $tabLink('belege', 'Belege & Rechnungen') ?>
    <?= $tabLink('bewegungen', 'Bewegungen') ?>
</div>

<?php if ($tab === 'bestand'): ?>
<div class="card">
    <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-bottom:10px">
        <a class="btn btn-primary btn-sm" href="<?= BASE_PATH ?>/haendler/buchen.php?kunde_id=<?= $kundeId ?>&typ=lieferung">🚚 Ware liefern</a>
        <a class="btn btn-primary btn-sm" href="<?= BASE_PATH ?>/haendler/verkauf.php?kunde_id=<?= $kundeId ?>">🧾 Verkauf melden &amp; abrechnen</a>
        <a class="btn btn-secondary btn-sm" href="<?= BASE_PATH ?>/haendler/buchen.php?kunde_id=<?= $kundeId ?>&typ=ruecknahme">↩ Rücknahme</a>
        <a class="btn btn-secondary btn-sm" href="<?= BASE_PATH ?>/haendler/buchen.php?kunde_id=<?= $kundeId ?>&typ=schwund">⚠ Schwund</a>
    </div>
    <table class="erp-table">
        <thead><tr><th style="width:150px">NR.</th><th>ARTIKEL</th><th style="width:80px;text-align:right">STÜCK</th>
            <th style="width:140px;text-align:right">HÄNDLERPREIS NETTO</th><th style="width:110px;text-align:right">VK BRUTTO</th><th style="width:110px;text-align:right">WERT NETTO</th></tr></thead>
        <tbody>
        <?php if (!$bestand): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:24px">Beim Händler liegt nichts — „🚚 Ware liefern“.</td></tr>
        <?php endif; ?>
        <?php foreach ($bestand as $b): ?>
            <tr>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($b['artikelnummer']) ?></td>
                <td><?= htmlspecialchars($b['name']) ?></td>
                <td style="text-align:right;font-weight:600"><?= $zahl($b['menge']) ?></td>
                <td style="text-align:right" title="<?= $b['preis_min'] != $b['preis_max'] ? 'Lieferungen zu verschiedenen Preisen — abgerechnet wird die älteste zuerst' : '' ?>">
                    € <?= number_format((float)$b['preis_fifo'], 2, ',', '.') ?><?= $b['preis_min'] != $b['preis_max'] ? ' <span style="color:var(--color-text-muted);font-size:11px">(bis ' . number_format((float)$b['preis_max'], 2, ',', '.') . ')</span>' : '' ?>
                </td>
                <td style="text-align:right"><?= $b['vk_brutto'] !== null ? '€ ' . number_format((float)$b['vk_brutto'], 2, ',', '.') : '–' ?></td>
                <td style="text-align:right">€ <?= number_format((float)$b['wert_netto'], 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'belege'): ?>
<div class="card">
    <table class="erp-table">
        <thead><tr><th style="width:170px">BELEG</th><th style="width:150px">ART</th><th style="width:130px">DATUM</th><th style="width:70px;text-align:right">STÜCK</th>
            <th style="width:120px;text-align:right">NETTO</th><th>RECHNUNG / NOTIZ</th><th style="width:110px">VON</th></tr></thead>
        <tbody>
        <?php if (!$belege): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--color-text-muted);padding:24px">Noch keine Belege.</td></tr>
        <?php endif; ?>
        <?php $art = ['lieferung' => '🚚 Lieferschein', 'verkauf' => '🧾 Verkaufsmeldung', 'ruecknahme' => '↩ Rücknahme', 'schwund' => '⚠ Schwund']; ?>
        <?php foreach ($belege as $b): ?>
            <tr>
                <td style="font-family:monospace;font-weight:600">
                    <?php if ($b['typ'] === 'verkauf'): ?>
                        <a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= (int)$b['auftrag_id'] ?>"><?= htmlspecialchars(substr($b['nummer'], 3)) ?></a>
                    <?php else: ?>
                        <a href="<?= BASE_PATH ?>/haendler/beleg_pdf.php?id=<?= (int)$b['id'] ?>" target="_blank"><?= htmlspecialchars($b['nummer']) ?></a>
                    <?php endif; ?>
                </td>
                <td><?= $art[$b['typ']] ?? htmlspecialchars($b['typ']) ?></td>
                <td style="font-size:12px"><?= date('d.m.Y H:i', strtotime($b['erstellt_am'])) ?></td>
                <td style="text-align:right"><?= $zahl($b['stueck']) ?></td>
                <td style="text-align:right">€ <?= number_format((float)$b['wert_netto'], 2, ',', '.') ?></td>
                <td style="font-size:12px">
                    <?php if ($b['rechnung_nr']): ?><strong><?= htmlspecialchars($b['rechnung_nr']) ?></strong> · <?= htmlspecialchars($b['zahlungsstatus']) ?><?php endif; ?>
                    <?= htmlspecialchars($b['notiz'] ?? '') ?>
                </td>
                <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($b['benutzer_name'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php else: ?>
<div class="card">
    <table class="erp-table">
        <thead><tr><th style="width:130px">DATUM</th><th style="width:90px">ART</th><th style="width:150px">NR.</th><th>ARTIKEL</th>
            <th style="width:70px;text-align:right">MENGE</th><th style="width:70px;text-align:right">DANACH</th><th>REFERENZ</th></tr></thead>
        <tbody>
        <?php if (!$bewegungen): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--color-text-muted);padding:24px">Noch keine Bewegungen.</td></tr>
        <?php endif; ?>
        <?php foreach ($bewegungen as $b): $minus = in_array($b['bewegungstyp'], ['ausgang', 'schwund'], true); ?>
            <tr>
                <td style="font-size:12px"><?= date('d.m.Y H:i', strtotime($b['erstellt_am'])) ?></td>
                <td><span class="chip"><?= htmlspecialchars(ucfirst($b['bewegungstyp'])) ?></span></td>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($b['artikelnummer']) ?></td>
                <td><?= htmlspecialchars($b['name']) ?><?= $b['charge'] ? ' <span style="font-size:11px;color:var(--color-text-muted)">· ' . htmlspecialchars($b['charge']) . '</span>' : '' ?></td>
                <td style="text-align:right;font-weight:600;color:<?= $minus ? 'var(--color-danger)' : 'var(--color-success)' ?>"><?= ($minus ? '−' : '+') . $zahl($b['menge']) ?></td>
                <td style="text-align:right"><?= $zahl($b['bestand_nachher']) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($b['referenz'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
function aktion(daten) {
    fetch(window.BASE_PATH + '/haendler/aktion.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(daten) })
        .then(r => r.json()).then(d => { if (!d.erfolg) { alert((d.fehler || ['Fehler']).join(' ')); return; } location.reload(); });
}
</script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
