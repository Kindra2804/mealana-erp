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
    <button class="btn btn-secondary btn-sm" onclick="etikettenDrucken()">🏷 Etiketten drucken</button>
    <button class="btn btn-secondary btn-sm" onclick="modalSerieOeffnen()">+ Regal mit Fächern</button>
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
            <span style="font-size:12px;color:var(--color-text-muted)"><?= count($lagerplaetze) ?> Lagerplätze · Etiketten: Plätze anhaken (ohne Auswahl = alle angezeigten)</span>
        </form>
    </div>

    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('.lp-cb').forEach(c => c.checked = this.checked)" title="Alle"></th>
                <th style="width:140px">KÜRZEL</th>
                <th style="width:200px">LAGER</th>
                <th>ARTIKEL</th>
                <th style="width:80px">STATUS</th>
                <th style="width:120px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($lagerplaetze)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:24px">Keine Lagerplätze gefunden — mit „+ Regal mit Fächern“ ein ganzes Regal auf einmal anlegen.</td></tr>
        <?php endif; ?>
        <?php foreach ($lagerplaetze as $lp): ?>
            <tr <?= $lp['aktiv'] ? '' : 'style="opacity:.55"' ?>>
                <td><input type="checkbox" class="lp-cb" value="<?= (int)$lp['id'] ?>"></td>
                <td><strong style="font-family:monospace;font-size:14px"><?= htmlspecialchars($lp['bezeichnung']) ?></strong></td>
                <td><?= htmlspecialchars($lp['lager_name']) ?></td>
                <td style="font-size:12px;color:var(--color-text-muted)">
                    <?php if ($lp['anzahl_stamm'] || $lp['anzahl_nachfuell']): ?>
                        <a href="<?= BASE_PATH ?>/artikel/liste.php?lagerplatz_id=<?= (int)$lp['id'] ?>">
                            <?= (int)$lp['anzahl_stamm'] ?> Stammplatz<?= $lp['anzahl_nachfuell'] ? ' · ' . (int)$lp['anzahl_nachfuell'] . ' Nachfüller' : '' ?>
                        </a>
                    <?php else: ?>–<?php endif; ?>
                </td>
                <td><?= $lp['aktiv'] ? '<span class="chip chip-aktiv">Aktiv</span>' : '<span class="chip">Inaktiv</span>' ?></td>
                <td style="white-space:nowrap">
                    <?php $json = htmlspecialchars(json_encode($lp), ENT_QUOTES); ?>
                    <a class="btn btn-secondary btn-sm" href="<?= BASE_PATH ?>/lager/lagerplaetze_etiketten.php?ids=<?= (int)$lp['id'] ?>" target="_blank" title="Etikett drucken">🏷</a>
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
    <div style="background:#fff;max-width:440px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Neuer Lagerplatz</h3>
            <button onclick="modalNeuSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-neu" onsubmit="lagerplatzSpeichern(event)">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <?= lagerplatzFormFelder($alleLager, '', $filterLager) ?>
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
    <div style="background:#fff;max-width:440px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Lagerplatz bearbeiten</h3>
            <button onclick="modalBearbeitenSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-bearbeiten" onsubmit="lagerplatzAktualisieren(event)">
            <input type="hidden" name="id" id="edit-id">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <?= lagerplatzFormFelder($alleLager, 'edit-') ?>
                <div style="font-size:11px;color:var(--color-text-muted)">Ein geändertes Kürzel braucht ein neues Etikett — der QR-Code selbst bleibt gültig.</div>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="modalBearbeitenSchliessen()">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Regal mit Fächern (Serie) -->
<div id="modal-serie" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:440px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Regal mit Fächern anlegen</h3>
            <button onclick="modalSerieSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-serie" onsubmit="serieSpeichern(event)">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="erp-label">Lager *</label>
                    <select name="lager_id" class="erp-select" style="width:100%"><?= lagerOptionen($alleLager, $filterLager) ?></select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <label class="erp-label">Bereich <span style="font-weight:400">(optional)</span></label>
                        <input type="text" name="bereich" id="serie-bereich" class="erp-input" style="width:100%;box-sizing:border-box" maxlength="20" placeholder="z.B. K" oninput="serieVorschau()">
                    </div>
                    <div>
                        <label class="erp-label">Regal *</label>
                        <input type="text" name="regal" id="serie-regal" class="erp-input" style="width:100%;box-sizing:border-box" maxlength="10" placeholder="z.B. 3" required oninput="serieVorschau()">
                    </div>
                    <div>
                        <label class="erp-label">Fach von *</label>
                        <input type="number" name="fach_von" id="serie-von" class="erp-input" style="width:100%;box-sizing:border-box" min="1" value="1" required oninput="serieVorschau()">
                    </div>
                    <div>
                        <label class="erp-label">Fach bis *</label>
                        <input type="number" name="fach_bis" id="serie-bis" class="erp-input" style="width:100%;box-sizing:border-box" min="1" value="20" required oninput="serieVorschau()">
                    </div>
                </div>
                <div id="serie-vorschau" style="font-size:13px;color:var(--color-nav)"></div>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="modalSerieSchliessen()">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Anlegen</button>
            </div>
        </form>
    </div>
</div>

<div id="banner" style="display:none;position:fixed;top:16px;right:16px;z-index:2000;padding:10px 18px;border-radius:6px;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.2)"></div>

<?php
function lagerOptionen(array $alleLager, int $vorwahl = 0): string
{
    $options = '';
    foreach ($alleLager as $l) {
        $sel = (int)$l['id'] === $vorwahl ? ' selected' : '';
        $options .= '<option value="' . $l['id'] . '"' . $sel . '>' . htmlspecialchars($l['name']) . '</option>';
    }
    return $options;
}

function lagerplatzFormFelder(array $alleLager, string $prefix = '', int $vorwahl = 0): string
{
    $p = $prefix;
    $options = lagerOptionen($alleLager, $vorwahl);
    return <<<HTML
        <div>
            <label class="erp-label">Lager *</label>
            <select name="lager_id" id="{$p}lager_id" class="erp-select" style="width:100%">
                {$options}
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
            <div>
                <label class="erp-label">Bereich</label>
                <input type="text" name="bereich" id="{$p}bereich" class="erp-input" style="width:100%;box-sizing:border-box" maxlength="20" placeholder="optional" oninput="kuerzelVorschau('{$p}')">
            </div>
            <div>
                <label class="erp-label">Regal *</label>
                <input type="text" name="regal" id="{$p}regal" class="erp-input" style="width:100%;box-sizing:border-box" maxlength="10" placeholder="3" oninput="kuerzelVorschau('{$p}')">
            </div>
            <div>
                <label class="erp-label">Fach *</label>
                <input type="text" name="fach" id="{$p}fach" class="erp-input" style="width:100%;box-sizing:border-box" maxlength="10" placeholder="12" oninput="kuerzelVorschau('{$p}')">
            </div>
        </div>
        <div style="font-size:13px">Kürzel: <strong id="{$p}kuerzel" style="font-family:monospace">—</strong>
            <span style="font-size:11px;color:var(--color-text-muted)">· Bereich z.B. „K“ für Keller → K-R1-F4</span></div>
        <input type="hidden" name="bezeichnung" id="{$p}bezeichnung">
        <div style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="aktiv" id="{$p}aktiv" value="1" checked>
            <label for="{$p}aktiv" style="cursor:pointer;font-size:13px">Aktiv</label>
        </div>
    HTML;
}
?>

<script src="<?= BASE_PATH ?>/js/lagerplaetze.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
