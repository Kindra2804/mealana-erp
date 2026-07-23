<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/statistik/StatistikRepository.php';

$repo = new StatistikRepository();

// ── Zeitraum-Filter ──────────────────────────────────────────────────────────
$zeitraum = $_GET['zeitraum'] ?? '30t';
$kanal    = $_GET['kanal'] ?? '';
$kanal    = in_array($kanal, ['kasse', 'online', 'manuell'], true) ? $kanal : null;

$heute = date('Y-m-d');
switch ($zeitraum) {
    case 'heute':
        $von = $heute; $bis = $heute; break;
    case '7t':
        $von = date('Y-m-d', strtotime('-6 days')); $bis = $heute; break;
    case 'monat':
        $von = date('Y-m-01'); $bis = $heute; break;
    case 'jahr':
        $von = date('Y-01-01'); $bis = $heute; break;
    case 'custom':
        $von = $_GET['von'] ?? date('Y-m-d', strtotime('-29 days'));
        $bis = $_GET['bis'] ?? $heute;
        break;
    case '30t':
    default:
        $zeitraum = '30t';
        $von = date('Y-m-d', strtotime('-29 days')); $bis = $heute; break;
}
// Für den DB-Vergleich mit erstellt_am (DATETIME) braucht $bis den ganzen Tag.
$vonDatetime = $von . ' 00:00:00';
$bisDatetime = $bis . ' 23:59:59';

// Granularität für den Zeitverlauf: bei einer Spanne über 62 Tagen nach Monat
// gruppieren statt nach Tag, sonst würde ein Jahres-Zeitraum ~365 Balken zeigen.
$tageSpanne    = (strtotime($bis) - strtotime($von)) / 86400;
$granularitaet = $tageSpanne > 62 ? 'monat' : 'tag';

$topseller     = $repo->findTopseller($vonDatetime, $bisDatetime, $kanal);
$zeitverlauf   = $repo->findUmsatzZeitverlauf($vonDatetime, $bisDatetime, $kanal, $granularitaet);
$margeGruppen  = $repo->findMargeProGruppe($vonDatetime, $bisDatetime, $kanal);
$jahresvergleich = $repo->findJahresvergleich(3);

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────
function eur(float $betrag): string {
    return '€ ' . number_format($betrag, 2, ',', '.');
}

$pageTitle    = 'Statistik';
$activeModule = 'verkauf';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<style>
.st-filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.st-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px; }
.db-card {
    background:white; border:1px solid #e2e8f0; border-radius:8px;
    padding:16px; box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.db-card-title { font-weight:700; font-size:13px; color:#1e3a5f; margin-bottom:12px; }
.db-table { width:100%; border-collapse:collapse; font-size:12px; }
.db-table th { background:#f8fafc; color:#64748b; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; padding:6px 8px; text-align:left; border-bottom:1px solid #e2e8f0; }
.db-table td { padding:7px 8px; border-bottom:1px solid #f1f5f9; color:#1e3a5f; vertical-align:middle; }
.db-table tr:last-child td { border-bottom:none; }
.db-table tr:hover td { background:#f8fafc; }
.db-bar-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.db-bar-label { width:170px; font-size:12px; color:#475569; flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.db-bar-track { flex:1; height:14px; background:#e2e8f0; border-radius:6px; overflow:hidden; display:flex; }
.db-bar-fill  { height:100%; }
.db-bar-amt   { width:90px; text-align:right; font-size:12px; font-weight:600; color:#1e3a5f; flex-shrink:0; }
.st-legende { display:flex; gap:14px; font-size:11px; color:#64748b; margin-bottom:10px; }
.st-legende-dot { display:inline-block; width:9px; height:9px; border-radius:2px; margin-right:4px; }
.st-jahr-card { text-align:center; padding:14px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; }
.st-jahr-card.aktuell { background:#eff6ff; border-color:#bfdbfe; }
.st-jahr-zahl { font-size:22px; font-weight:800; color:#1e3a5f; }
</style>

<!-- ── Filter ───────────────────────────────────────────────────────────────── -->
<form method="get" class="st-filter-bar db-card">
    <select name="zeitraum" class="erp-select" onchange="this.form.submit()">
        <option value="heute" <?= $zeitraum === 'heute' ? 'selected' : '' ?>>Heute</option>
        <option value="7t"    <?= $zeitraum === '7t'    ? 'selected' : '' ?>>Letzte 7 Tage</option>
        <option value="30t"   <?= $zeitraum === '30t'   ? 'selected' : '' ?>>Letzte 30 Tage</option>
        <option value="monat" <?= $zeitraum === 'monat' ? 'selected' : '' ?>>Dieser Monat</option>
        <option value="jahr"  <?= $zeitraum === 'jahr'  ? 'selected' : '' ?>>Dieses Jahr</option>
        <option value="custom" <?= $zeitraum === 'custom' ? 'selected' : '' ?>>Zeitraum wählen…</option>
    </select>
    <?php if ($zeitraum === 'custom'): ?>
        <input type="date" name="von" class="erp-input" value="<?= htmlspecialchars($von) ?>">
        <span style="color:#94a3b8">bis</span>
        <input type="date" name="bis" class="erp-input" value="<?= htmlspecialchars($bis) ?>">
        <button type="submit" class="btn btn-secondary btn-sm">Anwenden</button>
    <?php endif; ?>
    <div style="width:1px;height:24px;background:#e2e8f0"></div>
    <select name="kanal" class="erp-select" onchange="this.form.submit()">
        <option value="" <?= $kanal === null ? 'selected' : '' ?>>Alle Kanäle</option>
        <option value="kasse" <?= $kanal === 'kasse' ? 'selected' : '' ?>>Nur Kasse</option>
        <option value="online" <?= $kanal === 'online' ? 'selected' : '' ?>>Nur Online</option>
        <option value="manuell" <?= $kanal === 'manuell' ? 'selected' : '' ?>>Nur Manuell</option>
    </select>
    <div style="flex:1"></div>
    <div style="font-size:11px;color:#94a3b8"><?= date('d.m.Y', strtotime($von)) ?> – <?= date('d.m.Y', strtotime($bis)) ?></div>
</form>

<div class="st-grid-2">

    <!-- Topseller -->
    <div class="db-card">
        <div class="db-card-title">Topseller (nach Menge)</div>
        <table class="db-table">
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th style="text-align:right">Menge</th>
                    <th style="text-align:right">Umsatz</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topseller as $t): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_PATH ?>/artikel/detail.php?id=<?= $t['artikel_id'] ?>" style="color:#2563eb;text-decoration:none">
                            <?= htmlspecialchars($t['name']) ?>
                        </a>
                        <div style="font-size:10px;color:#94a3b8"><?= htmlspecialchars($t['artikelnummer']) ?></div>
                    </td>
                    <td style="text-align:right;font-weight:600"><?= (int)$t['menge'] ?></td>
                    <td style="text-align:right"><?= eur((float)$t['umsatz_brutto']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($topseller)): ?>
                <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:20px">Keine Verkäufe im Zeitraum</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Marge nach Artikelgruppe -->
    <div class="db-card">
        <div class="db-card-title">Deckungsbeitrag nach Artikelgruppe</div>
        <table class="db-table">
            <thead>
                <tr>
                    <th>Gruppe</th>
                    <th style="text-align:right">Umsatz (netto)</th>
                    <th style="text-align:right">DB</th>
                    <th style="text-align:right">Marge %</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($margeGruppen as $m):
                $db_  = (float)$m['umsatz_netto'] - (float)$m['ek_gesamt'];
                $pct  = (float)$m['umsatz_netto'] > 0 ? round($db_ / (float)$m['umsatz_netto'] * 100, 1) : null;
            ?>
                <tr>
                    <td><?= htmlspecialchars($m['gruppe']) ?></td>
                    <td style="text-align:right"><?= eur((float)$m['umsatz_netto']) ?></td>
                    <td style="text-align:right;font-weight:600"><?= eur($db_) ?></td>
                    <td style="text-align:right;color:<?= $pct !== null && $pct < 20 ? '#dc2626' : '#16a34a' ?>">
                        <?= $pct !== null ? number_format($pct, 1, ',', '.') . '%' : '–' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($margeGruppen)): ?>
                <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px">Keine Verkäufe im Zeitraum</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <div style="font-size:10px;color:#94a3b8;margin-top:8px">
            EK-Basis: Standard-Lieferant je Artikel. Ohne gesetzten Standard-Lieferant fließt EK=0 ein (Marge dann zu hoch).
        </div>
    </div>

</div>

<!-- Umsatz-Zeitverlauf -->
<div class="db-card" style="margin-bottom:16px">
    <div class="db-card-title">Umsatz-Zeitverlauf (<?= $granularitaet === 'monat' ? 'nach Monat' : 'nach Tag' ?>)</div>
    <div class="st-legende">
        <span><span class="st-legende-dot" style="background:#2563eb"></span>Kasse</span>
        <span><span class="st-legende-dot" style="background:#16a34a"></span>Online</span>
        <span><span class="st-legende-dot" style="background:#f59e0b"></span>Manuell</span>
    </div>
    <?php
    $maxPeriode = max([1, ...array_column($zeitverlauf, 'umsatz_gesamt')]);
    foreach ($zeitverlauf as $z):
        $pctKasse   = $maxPeriode > 0 ? round((float)$z['umsatz_kasse'] / $maxPeriode * 100, 2) : 0;
        $pctOnline  = $maxPeriode > 0 ? round((float)$z['umsatz_online'] / $maxPeriode * 100, 2) : 0;
        $pctManuell = $maxPeriode > 0 ? round((float)$z['umsatz_manuell'] / $maxPeriode * 100, 2) : 0;
        $label = $granularitaet === 'monat'
            ? date('M Y', strtotime($z['periode'] . '-01'))
            : date('d.m.', strtotime($z['periode']));
    ?>
    <div class="db-bar-row">
        <div class="db-bar-label"><?= $label ?></div>
        <div class="db-bar-track">
            <div class="db-bar-fill" style="width:<?= $pctKasse ?>%;background:#2563eb"></div>
            <div class="db-bar-fill" style="width:<?= $pctOnline ?>%;background:#16a34a"></div>
            <div class="db-bar-fill" style="width:<?= $pctManuell ?>%;background:#f59e0b"></div>
        </div>
        <div class="db-bar-amt"><?= eur((float)$z['umsatz_gesamt']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($zeitverlauf)): ?>
        <div style="text-align:center;color:#94a3b8;padding:20px">Keine Verkäufe im Zeitraum</div>
    <?php endif; ?>
</div>

<!-- Jahresvergleich -->
<div class="db-card">
    <div class="db-card-title">Jahresvergleich (Aufträge/Umsatz)</div>
    <div style="display:grid;grid-template-columns:repeat(<?= max(1, count($jahresvergleich)) ?>,1fr);gap:12px">
        <?php foreach ($jahresvergleich as $j): ?>
        <div class="st-jahr-card <?= (int)$j['jahr'] === (int)date('Y') ? 'aktuell' : '' ?>">
            <div style="font-size:12px;color:#64748b;margin-bottom:6px"><?= (int)$j['jahr'] ?></div>
            <div class="st-jahr-zahl"><?= eur((float)$j['umsatz']) ?></div>
            <div style="font-size:11px;color:#64748b;margin-top:4px"><?= (int)$j['anzahl'] ?> Aufträge</div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($jahresvergleich)): ?>
        <div style="text-align:center;color:#94a3b8;padding:20px">Keine Daten</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
