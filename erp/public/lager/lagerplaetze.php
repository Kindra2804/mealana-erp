<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$service     = new LagerService();
$filterLager = (int)($_GET['lager_id'] ?? 0);
$filterAktiv = $_GET['aktiv'] ?? '1';

$aktivParam = $filterAktiv !== '' ? (int)$filterAktiv : null;
$lagerplaetze = $service->getAlleLagerplaetze($filterLager, $aktivParam);
$alleLager    = $service->getAlleLager();

$pageTitle        = 'Lagerplätze';
$activeModule     = 'lager';
$actionBarContent = <<<HTML
    <button class="btn btn-primary btn-sm" onclick="modalNeuOeffnen()">+ Neuer Lagerplatz</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card">
    <div class="filter-bar" style="margin-bottom:16px">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <select name="lager_id" class="erp-select" onchange="this.form.requestSubmit()">
                <option value="0">Alle Lager</option>
                <?php foreach ($alleLager as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $filterLager === (int)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="aktiv" class="erp-select" onchange="this.form.requestSubmit()">
                <option value="1" <?= $filterAktiv === '1' ? 'selected' : '' ?>>Nur aktive</option>
                <option value=""  <?= $filterAktiv === ''  ? 'selected' : '' ?>>Alle</option>
                <option value="0" <?= $filterAktiv === '0' ? 'selected' : '' ?>>Nur inaktive</option>
            </select>
        </form>
    </div>

    <table class="erp-table">
        <thead>
            <tr>
                <th>BEZEICHNUNG</th>
                <th style="width:200px">LAGER</th>
                <th style="width:80px">STATUS</th>
                <th style="width:90px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($lagerplaetze)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--color-text-muted);padding:24px">Keine Lagerplätze gefunden.</td></tr>
        <?php endif; ?>
        <?php foreach ($lagerplaetze as $lp): ?>
            <tr <?= $lp['aktiv'] ? '' : 'style="opacity:.55"' ?>>
                <td><strong><?= htmlspecialchars($lp['bezeichnung']) ?></strong></td>
                <td><?= htmlspecialchars($lp['lager_name']) ?></td>
                <td><?= $lp['aktiv'] ? '<span class="chip chip-aktiv">Aktiv</span>' : '<span class="chip">Inaktiv</span>' ?></td>
                <td style="white-space:nowrap">
                    <?php $json = htmlspecialchars(json_encode($lp), ENT_QUOTES); ?>
                    <button class="btn btn-secondary btn-sm" onclick="modalBearbeitenOeffnen(<?= $json ?>)" title="Bearbeiten">✎</button>
                    <?php if ($lp['aktiv']): ?>
                        <button class="btn btn-secondary btn-sm" onclick="statusDeaktivieren(<?= $lp['id'] ?>, '<?= htmlspecialchars($lp['bezeichnung'], ENT_QUOTES) ?>')" title="Deaktivieren">🗑️</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- MODAL: Lagerplatz Neu -->
<div id="modal-neu" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:420px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Neuer Lagerplatz</h3>
            <button onclick="modalNeuSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-neu" onsubmit="lagerplatzSpeichern(event)">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <?= lagerplatzFormFelder($alleLager) ?>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="modalNeuSchliessen()">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Lagerplatz Bearbeiten -->
<div id="modal-bearbeiten" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:420px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Lagerplatz bearbeiten</h3>
            <button onclick="modalBearbeitenSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-bearbeiten" onsubmit="lagerplatzAktualisieren(event)">
            <input type="hidden" name="id" id="edit-id">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <?= lagerplatzFormFelder($alleLager, 'edit-') ?>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="modalBearbeitenSchliessen()">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>

<div id="banner" style="display:none;position:fixed;top:16px;right:16px;z-index:2000;padding:10px 18px;border-radius:6px;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.2)"></div>

<?php
function lagerplatzFormFelder(array $alleLager, string $prefix = ''): string
{
    $p = $prefix;
    $options = '';
    foreach ($alleLager as $l) {
        $options .= '<option value="' . $l['id'] . '">' . htmlspecialchars($l['name']) . '</option>';
    }
    return <<<HTML
        <div>
            <label class="erp-label">Lager *</label>
            <select name="lager_id" id="{$p}lager_id" class="erp-select">
                {$options}
            </select>
        </div>
        <div>
            <label class="erp-label">Bezeichnung *</label>
            <input type="text" name="bezeichnung" id="{$p}bezeichnung" class="erp-input" style="width:100%;box-sizing:border-box" placeholder="z.B. Regal 8 / Fach 3" required>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="aktiv" id="{$p}aktiv" value="1" checked>
            <label for="{$p}aktiv" style="cursor:pointer;font-size:13px">Aktiv</label>
        </div>
    HTML;
}
?>

<script src="<?= BASE_PATH ?>/js/lagerplaetze.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
