<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$service = new InventurService();
$laufId  = (int)($_GET['lauf_id'] ?? 0);
$lauf    = $service->getById($laufId);
if (!$lauf) { header('Location: ' . BASE_PATH . '/inventur/liste.php'); exit; }

$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

// Live-Sperre: bei Scope=Lagerplatz wird der Platz automatisch beansprucht.
// Bei Scope=Lager wählt der Zähler unten selbst einen Arbeitsbereich.
$sperrWarnung = null;
if ($lauf['scope_tabelle'] === 'lagerplaetze') {
    $claim = $service->lagerplatzWaehlen($laufId, (int)$lauf['scope_id'], $benutzerId);
    $sperrWarnung = $claim['warnung'];
}

$lagerplaetzeFuerAuswahl = $lauf['scope_tabelle'] === 'lager'
    ? (new LagerService())->getAlleLagerplaetze((int)$lauf['scope_id'], 1)
    : [];

$sollListe  = $service->getSollListe($lauf);
$positionen = $service->getPositionenFuerLauf($laufId);

// Index der bereits gezählten Positionen nach Schlüssel, für Vorbelegung in der Soll-Liste
$positionenIndex = [];
foreach ($positionen as $p) {
    $key = $p['artikel_id'] . '|' . $p['lager_id'] . '|' . ($p['lagerplatz_id'] ?? '') . '|' . ($p['charge'] ?? '');
    $positionenIndex[$key] = $p;
}

$scopeLabels = [
    'lager'        => 'Ganzes Lager',
    'lagerplaetze' => 'Lagerplatz',
    'kategorien'   => 'Kategorie',
    'artikel'      => 'Einzelner Artikel',
    'mietfaecher'  => 'Mietfach',
];

$pageTitle        = 'Zählen: ' . $lauf['scope_bezeichnung'];
$activeModule     = 'lager';
$actionBarContent = '<a href="' . BASE_PATH . '/inventur/liste.php" class="btn btn-secondary btn-sm">← Zur Liste</a>';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase">
                <?= $scopeLabels[$lauf['scope_tabelle']] ?? htmlspecialchars($lauf['scope_tabelle']) ?>
            </div>
            <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($lauf['scope_bezeichnung']) ?></div>
        </div>
        <?php if ($lauf['blind_modus']): ?>
            <span class="chip chip-auslauf">🙈 Blind-Modus — Soll ausgeblendet</span>
        <?php else: ?>
            <span class="chip chip-aktiv">👁 Soll sichtbar</span>
        <?php endif; ?>
    </div>

    <?php if ($sperrWarnung): ?>
        <div style="margin-top:10px;padding:8px;background:#fff8e6;border-radius:4px;font-size:13px;color:#c0820a">
            ⚠ <?= htmlspecialchars($sperrWarnung) ?>
        </div>
    <?php endif; ?>

    <?php if ($lauf['scope_tabelle'] === 'lager'): ?>
        <div style="margin-top:12px;max-width:320px">
            <label class="erp-label">Ich zähle gerade an (Lagerplatz, optional)</label>
            <select id="aktueller_lagerplatz" class="erp-select" style="width:100%" onchange="lagerplatzWaehlen()">
                <option value="">— kein bestimmter Platz —</option>
                <?php foreach ($lagerplaetzeFuerAuswahl as $lp): ?>
                <option value="<?= $lp['id'] ?>"><?= htmlspecialchars($lp['bezeichnung']) ?></option>
                <?php endforeach; ?>
            </select>
            <div id="lagerplatz_warnung" style="margin-top:6px;font-size:13px;color:#c0820a"></div>
        </div>
    <?php endif; ?>
</div>

<div id="banner" style="display:none;position:fixed;top:16px;right:16px;z-index:2000;padding:10px 18px;border-radius:6px;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.2)"></div>

<!-- Neue Position erfassen (Scan/Suche) -->
<div class="card" style="margin-bottom:12px">
    <strong style="font-size:13px;display:block;margin-bottom:10px">Artikel erfassen (auch neu, nicht auf der Liste unten)</strong>
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end">
        <div>
            <label class="erp-label">Artikel</label>
            <input type="text" id="neu_artikel_suche" class="erp-input" style="width:100%" placeholder="Name oder Artikelnummer...">
            <input type="hidden" id="neu_artikel_id">
            <div id="neu_artikel_treffer" style="border:1px solid var(--color-border);border-radius:4px;margin-top:4px;max-height:180px;overflow-y:auto;display:none;position:absolute;background:#fff;z-index:100"></div>
        </div>
        <div>
            <label class="erp-label">Charge (optional)</label>
            <input type="text" id="neu_charge" class="erp-input" style="width:100%" placeholder="neue oder bestehende">
        </div>
        <div>
            <label class="erp-label">Menge *</label>
            <input type="number" step="0.001" id="neu_menge" class="erp-input" style="width:100%">
        </div>
        <div>
            <label class="erp-label">Notiz</label>
            <input type="text" id="neu_notiz" class="erp-input" style="width:100%">
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="neuePositionSpeichern()">Erfassen</button>
    </div>
    <?php if (in_array($lauf['scope_tabelle'], ['kategorien', 'artikel', 'mietfaecher'], true)): ?>
    <div style="margin-top:10px;max-width:260px">
        <label class="erp-label">Lager * (Scope legt hier kein Lager fest)</label>
        <select id="neu_lager_id" class="erp-select" style="width:100%">
            <?php foreach ($service->getAlleLagerFuerAuswahl() as $l): ?>
            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div id="neu_gewaehlt" style="margin-top:6px;font-size:13px;color:var(--color-success)"></div>
</div>

<!-- Soll-Liste -->
<div class="card">
    <strong style="font-size:13px;display:block;margin-bottom:10px">
        Zählliste
        <?php if (empty($sollListe)): ?>
            <span style="font-weight:400;color:var(--color-text-muted)"> — noch keine Vorbelegung, oben frei erfassen</span>
        <?php endif; ?>
    </strong>
    <table class="erp-table" id="zaehl-tabelle">
        <thead>
            <tr>
                <th>Artikel</th>
                <th>Lager</th>
                <th>Charge</th>
                <?php if (!$lauf['blind_modus']): ?><th style="text-align:right">Soll</th><?php endif; ?>
                <th style="width:130px">Ist</th>
                <th style="width:160px">Notiz</th>
                <th style="width:60px"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sollListe as $s):
            $key = $s['artikel_id'] . '|' . $s['lager_id'] . '|' . ($s['lagerplatz_id'] ?? '') . '|' . ($s['charge'] ?? '');
            $bestehend = $positionenIndex[$key] ?? null;
        ?>
            <tr data-artikel="<?= $s['artikel_id'] ?>" data-lager="<?= $s['lager_id'] ?>"
                data-lagerplatz="<?= htmlspecialchars($s['lagerplatz_id'] ?? '') ?>"
                data-charge="<?= htmlspecialchars($s['charge'] ?? '') ?>"
                data-soll="<?= $s['soll_menge'] !== null ? $s['soll_menge'] : '' ?>">
                <td><?= htmlspecialchars($s['artikel_name']) ?> <span style="color:var(--color-text-muted);font-size:11px">(<?= htmlspecialchars($s['artikelnummer']) ?>)</span></td>
                <td><?= htmlspecialchars($s['lager_name']) ?></td>
                <td><?= htmlspecialchars($s['charge'] ?? '—') ?></td>
                <?php if (!$lauf['blind_modus']): ?>
                    <td style="text-align:right"><?= $s['soll_menge'] !== null ? number_format((float)$s['soll_menge'], 0) : '—' ?></td>
                <?php endif; ?>
                <td><input type="number" step="0.001" class="erp-input ist-eingabe" style="width:100%" value="<?= $bestehend ? $bestehend['ist_menge'] : '' ?>"></td>
                <td><input type="text" class="erp-input notiz-eingabe" style="width:100%" value="<?= htmlspecialchars($bestehend['notiz'] ?? '') ?>"></td>
                <td><button type="button" class="btn btn-secondary btn-sm zeile-speichern">💾</button></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($sollListe)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--color-text-muted);padding:24px">Keine Vorbelegung für diesen Scope.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    window.INVENTUR_LAUF_ID = <?= $laufId ?>;
    window.INVENTUR_SCOPE_TABELLE = <?= json_encode($lauf['scope_tabelle']) ?>;
    window.INVENTUR_SCOPE_ID = <?= (int)$lauf['scope_id'] ?>;
    window.AKTUELLER_LAGERPLATZ_ID = null;
</script>
<script src="<?= BASE_PATH ?>/js/inventur_zaehlen.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
