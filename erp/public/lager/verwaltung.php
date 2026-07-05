<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$service     = new LagerService();
$filterAktiv = $_GET['aktiv'] ?? '1';

$filter = [];
if ($filterAktiv !== '') $filter['aktiv'] = (int)$filterAktiv;

$gruppiert = $service->getAlleGruppiert($filter);

$pageTitle        = 'Lagerverwaltung';
$activeModule     = 'lager';
$actionBarContent = <<<HTML
    <button class="btn btn-primary btn-sm" onclick="modalNeuOeffnen()">+ Neues Lager</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

function typ_label(string $typ): string
{
    return match ($typ) {
        'ladengeschaeft' => 'Ladengeschäft',
        'messe'          => 'Messe',
        'extern'         => 'Extern',
        'lager'          => 'Lager',
        default          => htmlspecialchars($typ),
    };
}

function status_chip(array $lager): string
{
    return $lager['aktiv']
        ? '<span class="chip chip-aktiv">Aktiv</span>'
        : '<span class="chip">Inaktiv</span>';
}

/**
 * Bearbeiten-Button immer sichtbar; Deaktivieren (🗑️ + Bestätigung, wie Hersteller-Modul)
 * nur bei aktiven Lagern. Reaktivieren läuft über das Aktiv-Häkchen im Bearbeiten-Modal,
 * kein separater "Aktivieren"-Button (wieder wie Hersteller-Modul).
 */
function aktionen(array $lager): string
{
    $json   = htmlspecialchars(json_encode($lager), ENT_QUOTES);
    $name   = htmlspecialchars($lager['name'], ENT_QUOTES);
    $loesch = $lager['aktiv']
        ? <<<HTML
            <button class="btn btn-secondary btn-sm" onclick="statusDeaktivieren({$lager['id']}, '{$name}')" title="Deaktivieren">🗑️</button>
          HTML
        : '';
    return <<<HTML
        <button class="btn btn-secondary btn-sm" onclick="modalBearbeitenOeffnen({$json})" title="Bearbeiten">✎</button>
        {$loesch}
    HTML;
}
?>

<div class="card">
    <div class="filter-bar" style="margin-bottom:16px">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <select name="aktiv" class="erp-select" onchange="this.form.requestSubmit()">
                <option value="1" <?= $filterAktiv === '1' ? 'selected' : '' ?>>Nur aktive</option>
                <option value=""  <?= $filterAktiv === ''  ? 'selected' : '' ?>>Alle</option>
                <option value="0" <?= $filterAktiv === '0' ? 'selected' : '' ?>>Nur inaktive</option>
            </select>
        </form>
    </div>

    <h3 style="margin:24px 0 8px">Eigene Lager</h3>
    <table class="erp-table">
        <thead>
            <tr>
                <th>NAME</th>
                <th style="width:140px">TYP</th>
                <th style="width:110px">OFFLINE-KASSE</th>
                <th style="width:80px">STATUS</th>
                <th style="width:90px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($gruppiert['eigen'])): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--color-text-muted);padding:24px">Keine eigenen Lager gefunden.</td></tr>
        <?php endif; ?>
        <?php foreach ($gruppiert['eigen'] as $l): ?>
            <tr <?= $l['aktiv'] ? '' : 'style="opacity:.55"' ?>>
                <td><strong><?= htmlspecialchars($l['name']) ?></strong></td>
                <td><?= typ_label($l['typ']) ?></td>
                <td><?= $l['fuer_offline_kasse_waehlbar'] ? '✓' : '–' ?></td>
                <td><?= status_chip($l) ?></td>
                <td style="white-space:nowrap"><?= aktionen($l) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="margin:24px 0 8px">Partner-Bestand bei uns</h3>
    <table class="erp-table">
        <thead>
            <tr>
                <th>NAME</th>
                <th>PARTNER</th>
                <th style="width:80px">STATUS</th>
                <th style="width:90px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($gruppiert['partner_bestand'])): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--color-text-muted);padding:24px">Kein Partner-Lager angelegt.</td></tr>
        <?php endif; ?>
        <?php foreach ($gruppiert['partner_bestand'] as $l): ?>
            <tr <?= $l['aktiv'] ? '' : 'style="opacity:.55"' ?>>
                <td><strong><?= htmlspecialchars($l['name']) ?></strong></td>
                <td>
                    <?php if ($l['partner_name']): ?>
                        <?= htmlspecialchars($l['partner_name']) ?>
                    <?php else: ?>
                        <span style="color:#e67e22;font-size:12px">⚠ noch keinem Partner zugewiesen</span>
                    <?php endif; ?>
                </td>
                <td><?= status_chip($l) ?></td>
                <td style="white-space:nowrap"><?= aktionen($l) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="margin:24px 0 8px">Unsere Ware bei Händlern</h3>
    <table class="erp-table">
        <thead>
            <tr>
                <th>NAME</th>
                <th>HÄNDLER (KUNDE)</th>
                <th style="width:80px">STATUS</th>
                <th style="width:90px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($gruppiert['haendler_aussenlager'])): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--color-text-muted);padding:24px">Kein Händler-Außenlager angelegt.</td></tr>
        <?php endif; ?>
        <?php foreach ($gruppiert['haendler_aussenlager'] as $l): ?>
            <tr <?= $l['aktiv'] ? '' : 'style="opacity:.55"' ?>>
                <td><strong><?= htmlspecialchars($l['name']) ?></strong></td>
                <td>
                    <?php if ($l['kunde_kundennummer']): ?>
                        <?= htmlspecialchars($l['kunde_kundennummer']) ?>
                    <?php else: ?>
                        <span style="color:#e67e22;font-size:12px">⚠ noch keinem Kunden zugewiesen</span>
                    <?php endif; ?>
                </td>
                <td><?= status_chip($l) ?></td>
                <td style="white-space:nowrap"><?= aktionen($l) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ================================================================
     MODAL: Lager Neu
================================================================ -->
<div id="modal-neu" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:480px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Neues Lager</h3>
            <button onclick="modalNeuSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-neu" onsubmit="lagerSpeichern(event)">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <?= lagerFormFelder() ?>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="modalNeuSchliessen()">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL: Lager Bearbeiten
================================================================ -->
<div id="modal-bearbeiten" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:480px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Lager bearbeiten</h3>
            <button onclick="modalBearbeitenSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-bearbeiten" onsubmit="lagerAktualisieren(event)">
            <input type="hidden" name="id" id="edit-id">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <?= lagerFormFelder('edit-') ?>
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
function lagerFormFelder(string $prefix = ''): string
{
    $p = $prefix;
    return <<<HTML
        <div>
            <label class="erp-label">Name *</label>
            <input type="text" name="name" id="{$p}name" class="erp-input" style="width:100%;box-sizing:border-box" required>
        </div>
        <div>
            <label class="erp-label">Typ *</label>
            <select name="typ" id="{$p}typ" class="erp-select">
                <option value="ladengeschaeft">Ladengeschäft</option>
                <option value="messe">Messe</option>
                <option value="extern">Extern</option>
                <option value="lager">Lager</option>
            </select>
        </div>
        <div>
            <label class="erp-label">Beziehung *</label>
            <select name="lager_beziehung" id="{$p}lager_beziehung" class="erp-select">
                <option value="eigen">Eigenes Lager</option>
                <option value="partner_bestand">Partner-Bestand bei uns</option>
                <option value="haendler_aussenlager">Händler-Außenlager</option>
            </select>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="aktiv" id="{$p}aktiv" value="1" checked>
            <label for="{$p}aktiv" style="cursor:pointer;font-size:13px">Aktiv</label>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="fuer_offline_kasse_waehlbar" id="{$p}fuer_offline_kasse_waehlbar" value="1">
            <label for="{$p}fuer_offline_kasse_waehlbar" style="cursor:pointer;font-size:13px">Für Offline-Kassen auswählbar</label>
        </div>
    HTML;
}
?>

<script src="<?= BASE_PATH ?>/js/lager_verwaltung.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
