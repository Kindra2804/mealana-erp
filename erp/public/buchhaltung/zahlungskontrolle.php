<?php
/**
 * Zahlungs-Kontrolle: Kassenbons und Aufträge mit ihren Zahlungen und der Konten-
 * Aufteilung, wie sie der DATEV/CSV-Export bucht -- zum Gegenprüfen vor der Übergabe
 * an den Steuerberater (v.a. gemischte Zahlungen und Gutscheine).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/buchhaltung/BuchhaltungExportService.php';

$von  = $_GET['von'] ?? date('Y-m-01');
$bis  = $_GET['bis'] ?? date('Y-m-t');
$alle = !empty($_GET['alle']);

$liste = (new BuchhaltungExportService())->kontrollListe($von, $bis, $alle);

$kontoNamen = Database::getInstance()->query("SELECT kontonummer, name FROM kontenplan")->fetchAll(PDO::FETCH_KEY_PAIR);

const ZAHLUNGSART_TEXT = ['bar' => 'Bar', 'karte_extern' => 'Karte', 'gutschein' => 'Gutschein', 'vorkasse' => 'Vorkasse', 'paypal' => 'PayPal', 'rechnung' => 'Rechnung', 'nachnahme' => 'Nachnahme'];

function eur(float $v): string { return number_format($v, 2, ',', '.'); }
function betragOderStrich(float $v): string { return abs($v) < 0.005 ? '<span style="color:var(--color-text-muted)">–</span>' : eur($v); }
function kontenText(array $konten): string
{
    $teile = [];
    foreach ($konten as $k => $b) $teile[] = '<code style="font-size:11px">' . htmlspecialchars((string)$k) . '</code> ' . eur($b);
    return implode('<br>', $teile);
}

$anzahlAuffaellig = count(array_filter($liste['bons'], fn($b) => $b['auffaellig']))
                  + count(array_filter($liste['auftraege'], fn($a) => $a['auffaellig']));

$pageTitle    = 'Zahlungs-Kontrolle';
$activeModule = 'buchhaltung';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<style>
.zk-warn td { background: #fff7ed; }
.zk-diff { color: #c2410c; font-weight: 700; }
.zk-num { text-align: right; white-space: nowrap; }
.zk-klein { font-size: 11px; color: var(--color-text-muted); }
.zk-nr { white-space: nowrap; }
.zk-tab th, .zk-tab td { padding-left: 8px; padding-right: 8px; font-size: 13px; }
</style>

<div class="card" style="margin-bottom:16px">
    <div class="card-header">Zeitraum</div>
    <form method="get" style="padding:0 16px 14px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group">
            <label class="form-label">Von</label>
            <input type="date" name="von" id="f-von" class="erp-input" value="<?= htmlspecialchars($von) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Bis</label>
            <input type="date" name="bis" id="f-bis" class="erp-input" value="<?= htmlspecialchars($bis) ?>">
        </div>
        <label style="display:flex;gap:6px;align-items:center;font-size:13px;padding-bottom:6px">
            <input type="checkbox" name="alle" value="1" <?= $alle ? 'checked' : '' ?>> alle Belege zeigen
        </label>
        <button type="submit" class="btn btn-secondary btn-sm">Anzeigen</button>
        <div style="display:flex;gap:6px;margin-left:12px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="zeitraumSetzen('monat')">Dieser Monat</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="zeitraumSetzen('quartal')">Dieses Quartal</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="zeitraumSetzen('jahr')">Dieses Jahr</button>
        </div>
    </form>
    <div style="padding:0 16px 14px;font-size:12px;color:var(--color-text-muted)">
        <?= $alle ? 'Alle Kassenbons und Aufträge des Zeitraums.' : 'Nur Belege mit gemischter Zahlung, Gutschein oder Differenz — „alle Belege zeigen" für die komplette Liste.' ?>
        Die Spalte „Buchung" zeigt, wie der <a href="<?= BASE_PATH ?>/buchhaltung/export.php?von=<?= urlencode($von) ?>&bis=<?= urlencode($bis) ?>">DATEV/CSV-Export</a> den Betrag auf die Konten verteilt.
    </div>
</div>

<?php if ($anzahlAuffaellig): ?>
<div class="card" style="border-left:3px solid #f59e0b;margin-bottom:16px;padding:10px 16px;font-size:13px">
    <strong><?= $anzahlAuffaellig ?> Beleg(e) mit Differenz</strong> (orange markiert) — bitte prüfen.
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <div class="card-header">Kassenbons — <?= count($liste['bons']) ?></div>
    <div style="overflow-x:auto">
    <table class="erp-table zk-tab">
        <thead>
            <tr>
                <th>Bon</th><th>Datum</th><th>Zahlungsart</th>
                <th class="zk-num">Betrag</th><th class="zk-num">Bar</th><th class="zk-num">Karte</th>
                <th class="zk-num">Gutschein</th><th class="zk-num">Rückgeld</th>
                <th>Buchung</th><th style="min-width:170px">Prüfung</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($liste['bons'] as $b): ?>
            <tr class="<?= $b['auffaellig'] ? 'zk-warn' : '' ?>">
                <td class="zk-nr"><a href="<?= BASE_PATH ?>/kasse/bon_a4.php?id=<?= (int)$b['id'] ?>" target="_blank"><?= htmlspecialchars($b['bon_nr']) ?></a></td>
                <td class="zk-nr"><?= date('d.m.Y', strtotime($b['erstellt_am'])) ?><div class="zk-klein"><?= date('H:i', strtotime($b['erstellt_am'])) ?></div></td>
                <td><?= htmlspecialchars($b['gemischt'] ? ($b['zahlungsart'] === 'kombi' ? 'Bar + Karte' : 'Gutschein + Rest') : (ZAHLUNGSART_TEXT[$b['zahlungsart']] ?? $b['zahlungsart'])) ?></td>
                <td class="zk-num"><strong><?= eur((float)$b['bruttobetrag']) ?></strong></td>
                <td class="zk-num"><?= betragOderStrich($b['bar_netto']) ?>
                    <?php if ($b['zahlungsart'] === 'kombi' && (float)$b['bar_betrag'] > 0): ?><div class="zk-klein">gegeben <?= eur((float)$b['bar_betrag']) ?></div><?php endif; ?></td>
                <td class="zk-num"><?= betragOderStrich($b['karte']) ?></td>
                <td class="zk-num"><?= betragOderStrich($b['gutschein']) ?>
                    <?php if ($b['gutschein_code']): ?><div class="zk-klein"><?= htmlspecialchars($b['gutschein_code']) ?></div><?php endif; ?></td>
                <td class="zk-num"><?= betragOderStrich((float)$b['rueckgeld']) ?></td>
                <td style="font-size:12px"><?= kontenText($b['konten']) ?></td>
                <td style="font-size:12px">
                    <?php if (abs($b['differenz']) > 0.004): ?><div class="zk-diff" title="Die Differenz wird im Export dem Baranteil zugeschlagen.">Zahlungen <?= eur($b['differenz']) ?> daneben</div><?php endif; ?>
                    <?php if (abs($b['pos_differenz']) > 0.004): ?><div class="zk-diff" title="Die Positionen ergeben einen anderen Betrag als der Bon. Der Export bucht die Positionen.">Positionen <?= eur((float)$b['positionen_summe']) ?> ≠ Bon</div><?php endif; ?>
                    <?php if (!$b['auffaellig']): ?><span style="color:var(--color-success)">✓</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$liste['bons']): ?>
            <tr><td colspan="10" style="text-align:center;color:var(--color-text-muted);padding:20px">Keine Kassenbons in diesem Zeitraum</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header">Aufträge — <?= count($liste['auftraege']) ?></div>
    <div style="overflow-x:auto">
    <table class="erp-table zk-tab">
        <thead>
            <tr>
                <th>Auftrag</th><th>Datum</th><th>Kanal / Zahlungsart</th><th>Status</th>
                <th class="zk-num">Betrag</th><th class="zk-num">Gutschein</th><th>Zahlungen</th>
                <th class="zk-num">Offen</th><th>Buchung</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($liste['auftraege'] as $a): ?>
            <tr class="<?= $a['auffaellig'] ? 'zk-warn' : '' ?>">
                <td class="zk-nr"><a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['auftrag_nr']) ?></a></td>
                <td><?= date('d.m.Y', strtotime($a['erstellt_am'])) ?></td>
                <td><?= htmlspecialchars($a['kanal']) ?> / <?= htmlspecialchars(ZAHLUNGSART_TEXT[$a['zahlungsart']] ?? $a['zahlungsart']) ?></td>
                <td><?= htmlspecialchars($a['zahlungsstatus']) ?></td>
                <td class="zk-num"><strong><?= eur((float)$a['bruttobetrag']) ?></strong>
                    <?php if ((float)$a['versandkosten'] > 0): ?><div class="zk-klein">inkl. Versand <?= eur((float)$a['versandkosten']) ?></div><?php endif; ?></td>
                <td class="zk-num"><?= betragOderStrich((float)$a['gutschein_betrag']) ?>
                    <?php if ($a['gutschein_codes']): ?><div class="zk-klein"><?= htmlspecialchars($a['gutschein_codes']) ?></div><?php endif; ?></td>
                <td style="font-size:12px;min-width:180px">
                    <?php foreach ($a['zahlungen'] as $z): ?>
                        <?= date('d.m.Y', strtotime($z['buchungsdatum'])) ?> — <?= eur((float)$z['betrag']) ?>
                        <?php if ($z['notiz']): ?><div class="zk-klein" style="margin-bottom:3px"><?= htmlspecialchars($z['notiz']) ?></div><?php else: ?><br><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$a['zahlungen']): ?><span class="zk-klein">keine erfasst</span><?php endif; ?>
                </td>
                <td class="zk-num <?= $a['auffaellig'] ? 'zk-diff' : '' ?>"><?= betragOderStrich($a['offen']) ?>
                    <?php if ($a['offen'] < -0.004): ?><div class="zk-klein">Überzahlung</div><?php endif; ?></td>
                <td style="font-size:12px"><?= $a['konten'] ? kontenText($a['konten']) : '<span class="zk-klein">–</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$liste['auftraege']): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--color-text-muted);padding:20px">Keine Aufträge in diesem Zeitraum</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <div style="padding:8px 16px;font-size:11px;color:var(--color-text-muted)">
        Offen = Betrag − Gutschein − Zahlungen. Bei Status „ausstehend" ist ein offener Betrag normal und nicht markiert.
        Gutschein-Einlösung bei Online-Aufträgen bucht der Export als Umbuchung Bank → 3230.
    </div>
</div>

<div class="card">
    <div class="card-header">Summe je Konto (aufgelistete Belege)</div>
    <table class="erp-table" style="max-width:520px">
        <tbody>
        <?php foreach ($liste['konten'] as $k => $summe): ?>
            <tr>
                <td><code><?= htmlspecialchars((string)$k) ?></code></td>
                <td><?= htmlspecialchars($kontoNamen[$k] ?? '') ?></td>
                <td class="zk-num"><strong><?= eur($summe) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="<?= BASE_PATH ?>/js/buchhaltung_export.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
