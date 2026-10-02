<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/statistik/LagerwertService.php';

$service    = new LagerwertService();
$positionen = $service->berechne();
$aktuell    = $service->zusammenfassen($positionen);
$snapshots  = $service->findSnapshots(60);

// Wert und Anzahl je EK-Quelle — zeigt, wie belastbar der Gesamtwert ist
$quellen = [];
foreach (array_keys(LagerwertService::EK_QUELLEN) as $q) $quellen[$q] = ['anzahl' => 0, 'wert' => 0.0];
foreach ($positionen as $p) {
    $quellen[$p['ek_quelle']]['anzahl']++;
    $quellen[$p['ek_quelle']]['wert'] += $p['wert'];
}

function eur(float $betrag): string {
    return '€ ' . number_format($betrag, 2, ',', '.');
}

$pageTitle    = 'Lagerwert';
$activeModule = 'lager';
$basePath     = BASE_PATH;
$actionBarContent = <<<HTML
    <a href="{$basePath}/lager/lagerwert_csv.php" class="btn btn-secondary btn-sm">⬇ Aktuelle Bewertungsliste (CSV)</a>
HTML;
require_once __DIR__ . '/../includes/shell_top.php';
?>
<style>
.lw-kacheln { display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin-bottom:16px; }
.lw-grid-2  { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px; }
.db-card { background:white; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.db-card-title { font-weight:700; font-size:13px; color:#1e3a5f; margin-bottom:12px; }
.lw-zahl  { font-size:22px; font-weight:800; color:#1e3a5f; }
.lw-label { font-size:12px; color:#64748b; margin-bottom:6px; }
.lw-sub   { font-size:11px; color:#94a3b8; margin-top:4px; }
.db-table { width:100%; border-collapse:collapse; font-size:12px; }
.db-table th { background:#f8fafc; color:#64748b; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; padding:6px 8px; text-align:left; border-bottom:1px solid #e2e8f0; }
.db-table td { padding:7px 8px; border-bottom:1px solid #f1f5f9; color:#1e3a5f; vertical-align:middle; }
.db-table tr:last-child td { border-bottom:none; }
.db-table .r { text-align:right; }
.lw-chip { display:inline-block; font-size:10px; padding:1px 7px; border-radius:10px; background:#f1f5f9; color:#475569; }
.lw-chip.monatsende { background:#eff6ff; color:#1d4ed8; }
.lw-chip.inventur_start, .lw-chip.inventur_abschluss { background:#f0fdf4; color:#15803d; }
.lw-warn { color:#b45309; }
@media (max-width: 1100px) { .lw-kacheln { grid-template-columns:1fr 1fr; } .lw-grid-2 { grid-template-columns:1fr; } }
</style>

<div class="lw-kacheln">
    <div class="db-card">
        <div class="lw-label">Lagerwert aktuell (EK netto)</div>
        <div class="lw-zahl"><?= eur($aktuell['wert_gesamt']) ?></div>
        <div class="lw-sub"><?= number_format($aktuell['artikel_anzahl'], 0, ',', '.') ?> Artikel mit Bestand</div>
    </div>
    <div class="db-card">
        <div class="lw-label">Eigene Lager</div>
        <div class="lw-zahl"><?= eur($aktuell['wert_eigen']) ?></div>
    </div>
    <div class="db-card">
        <div class="lw-label">Bei Händlern (Kommission)</div>
        <div class="lw-zahl"><?= eur($aktuell['wert_haendler']) ?></div>
        <div class="lw-sub">bleibt bis zur Verkaufsmeldung unser Bestand</div>
    </div>
    <div class="db-card">
        <div class="lw-label">Artikel ohne EK (zählen mit 0 €)</div>
        <div class="lw-zahl <?= $aktuell['artikel_ohne_ek'] ? 'lw-warn' : '' ?>"><?= number_format($aktuell['artikel_ohne_ek'], 0, ',', '.') ?></div>
        <?php if ($aktuell['artikel_ohne_ek']): ?>
            <div class="lw-sub"><a href="<?= BASE_PATH ?>/artikel/liste.php?status_filter=kein_ek" style="color:#2563eb">→ in der Artikelliste nachpflegen</a></div>
        <?php endif; ?>
    </div>
</div>

<div class="lw-grid-2">
    <div class="db-card">
        <div class="db-card-title">Aktuell je Lager</div>
        <table class="db-table">
            <thead><tr><th>Lager</th><th class="r">Artikel</th><th class="r">Menge</th><th class="r">ohne EK</th><th class="r">Wert</th></tr></thead>
            <tbody>
            <?php foreach ($aktuell['lager'] as $l): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($l['lager_name']) ?>
                        <?php if ($l['lager_beziehung'] === 'haendler_aussenlager'): ?><span class="lw-chip">Händler</span><?php endif; ?>
                    </td>
                    <td class="r"><?= number_format($l['artikel_anzahl'], 0, ',', '.') ?></td>
                    <td class="r"><?= number_format($l['menge'], 0, ',', '.') ?></td>
                    <td class="r <?= $l['artikel_ohne_ek'] ? 'lw-warn' : '' ?>"><?= number_format($l['artikel_ohne_ek'], 0, ',', '.') ?></td>
                    <td class="r" style="font-weight:600"><?= eur($l['wert']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($aktuell['lager'])): ?>
                <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px">Kein Bestand</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="db-card">
        <div class="db-card-title">Woher kommt der EK?</div>
        <table class="db-table">
            <thead><tr><th>EK-Quelle</th><th class="r">Positionen</th><th class="r">Wert</th></tr></thead>
            <tbody>
            <?php foreach ($quellen as $q => $d): ?>
                <tr>
                    <td class="<?= $q === 'keiner' && $d['anzahl'] ? 'lw-warn' : '' ?>"><?= htmlspecialchars(LagerwertService::EK_QUELLEN[$q]) ?></td>
                    <td class="r"><?= number_format($d['anzahl'], 0, ',', '.') ?></td>
                    <td class="r"><?= eur($d['wert']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="lw-sub" style="margin-top:8px">
            Reihenfolge: echter EK aus dem letzten Wareneingang → Standardlieferant → günstigster Lieferant → Vater-/Originalartikel.
            Partnerware und Partner-Lager zählen nie mit.
        </div>
    </div>
</div>

<div class="db-card">
    <div class="db-card-title">Festgehaltene Werte</div>
    <table class="db-table">
        <thead>
            <tr>
                <th>Stichtag</th><th>Anlass</th><th class="r">Eigene Lager</th><th class="r">Händler</th>
                <th class="r">Gesamt</th><th class="r">Veränderung</th><th class="r">ohne EK</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($snapshots as $i => $s):
            $vorher = $snapshots[$i + 1] ?? null;
            $diff   = $vorher ? (float)$s['wert_gesamt'] - (float)$vorher['wert_gesamt'] : null;
        ?>
            <tr>
                <td><?= date('d.m.Y H:i', strtotime($s['stichtag'])) ?></td>
                <td>
                    <span class="lw-chip <?= htmlspecialchars($s['anlass']) ?>"><?= htmlspecialchars(LagerwertService::ANLAESSE[$s['anlass']] ?? $s['anlass']) ?></span>
                    <?php if ($s['inventur_lauf_id']): ?>
                        <a href="<?= BASE_PATH ?>/inventur/liste.php" style="color:#2563eb;font-size:11px">
                            <?= htmlspecialchars($s['inventur_bezeichnung'] ?? ('Lauf #' . $s['inventur_lauf_id'])) ?>
                        </a>
                    <?php endif; ?>
                </td>
                <td class="r"><?= eur((float)$s['wert_eigen']) ?></td>
                <td class="r"><?= eur((float)$s['wert_haendler']) ?></td>
                <td class="r" style="font-weight:600"><?= eur((float)$s['wert_gesamt']) ?></td>
                <td class="r" style="color:<?= $diff === null ? '#94a3b8' : ($diff < 0 ? '#dc2626' : '#16a34a') ?>">
                    <?= $diff === null ? '–' : (($diff >= 0 ? '+' : '−') . ' ' . eur(abs($diff))) ?>
                </td>
                <td class="r <?= $s['artikel_ohne_ek'] ? 'lw-warn' : '' ?>"><?= (int)$s['artikel_ohne_ek'] ?></td>
                <td class="r"><a href="<?= BASE_PATH ?>/lager/lagerwert_csv.php?id=<?= (int)$s['id'] ?>" style="color:#2563eb">CSV</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($snapshots)): ?>
            <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:20px">
                Noch nichts festgehalten. Das passiert automatisch am Monatsende und bei Start/Abschluss jeder Inventur.
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
