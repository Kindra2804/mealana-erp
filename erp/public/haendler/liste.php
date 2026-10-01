<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/haendler/HaendlerService.php';

$svc      = new HaendlerService();
$haendler = $svc->getAlle();
$gruppe   = $svc->getHaendlerGruppe();

$pageTitle        = 'Händler';
$activeModule     = 'verkauf';
$actionBarContent = '<a href="' . BASE_PATH . '/kunden/liste.php" class="btn btn-secondary btn-sm">Kunde als Händler einrichten …</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div class="card" style="margin-bottom:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <strong style="font-size:14px">Standard-Händlerrabatt</strong>
    <input type="text" id="rabatt" class="erp-input" style="width:80px;text-align:right" value="<?= $gruppe && $gruppe['rabatt_prozent'] !== null ? rtrim(rtrim(number_format((float)$gruppe['rabatt_prozent'], 2, ',', ''), '0'), ',') : '' ?>" placeholder="z.B. 40">
    <span>% auf den Endkunden-VK (netto)</span>
    <button class="btn btn-primary btn-sm" onclick="rabattSpeichern()">Speichern</button>
    <span style="font-size:12px;color:var(--color-text-muted)">Ein eigener Preis für die Kundengruppe „<?= htmlspecialchars($gruppe['name'] ?? 'Händler') ?>“ im Preise-Reiter des Artikels hat Vorrang.</span>
    <?php if (!$gruppe || $gruppe['rabatt_prozent'] === null): ?>
        <div style="flex-basis:100%;font-size:13px;color:var(--color-danger)">Ohne Rabatt kann nur Ware mit eigenem Händlerpreis geliefert werden.</div>
    <?php endif; ?>
</div>

<div class="card">
    <table class="erp-table">
        <thead><tr><th>HÄNDLER</th><th style="width:110px;text-align:right">STÜCK BEIM HÄNDLER</th><th style="width:150px;text-align:right">WERT (HÄNDLERPREIS)</th><th style="width:160px">LETZTE ABRECHNUNG</th></tr></thead>
        <tbody>
        <?php if (!$haendler): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--color-text-muted);padding:24px">Noch keine Händler. Kunden öffnen → „🏬 Als Händler einrichten“.</td></tr>
        <?php endif; ?>
        <?php foreach ($haendler as $h): ?>
            <tr>
                <td><a href="<?= BASE_PATH ?>/haendler/detail.php?kunde_id=<?= (int)$h['kunde_id'] ?>"><strong><?= htmlspecialchars(preg_replace('/^Händler: /u', '', $h['name'])) ?></strong></a></td>
                <td style="text-align:right"><?= rtrim(rtrim(number_format((float)$h['stueck'], 3, ',', '.'), '0'), ',') ?></td>
                <td style="text-align:right">€ <?= number_format((float)$h['wert_netto'], 2, ',', '.') ?></td>
                <td style="font-size:12px"><?= $h['letzte_abrechnung'] ? date('d.m.Y', strtotime($h['letzte_abrechnung'])) : '–' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
function rabattSpeichern() {
    fetch(window.BASE_PATH + '/haendler/aktion.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ aktion: 'rabatt', rabatt: document.getElementById('rabatt').value }) })
        .then(r => r.json()).then(d => { if (!d.erfolg) { alert((d.fehler || ['Fehler']).join(' ')); return; } location.reload(); });
}
</script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
