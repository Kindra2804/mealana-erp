<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/MietfachService.php';

$service = new MietfachService();
$faecher = $service->getAllMitStatus();

$pageTitle        = 'Mietfächer';
$activeModule     = 'partner';
$actionBarContent = <<<HTML
    <button class="btn btn-primary btn-sm" onclick="modalFachNeuOeffnen()">+ Neues Fach anlegen</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

function status_badge(array $f): string {
    if (!$f['aktiv']) {
        return '<span class="chip" style="background:#f5f5f5;color:#999">Inaktiv</span>';
    }
    if (!$f['vertrag_id']) {
        return '<span class="chip chip-aktiv" style="background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7">● Frei</span>';
    }
    $bis = $f['mietende']
        ? '<br><span style="font-size:10px;color:#888">bis ' . date('d.m.Y', strtotime($f['mietende'])) . '</span>'
        : '<br><span style="font-size:10px;color:#888">unbefristet</span>';
    return '<span class="chip" style="background:#fff3e0;color:#e65100;border:1px solid #ffcc80">● Belegt</span>' . $bis;
}

function masse_str(array $f): string {
    $teile = array_filter([
        $f['laenge_cm'] ? $f['laenge_cm'] . ' cm' : null,
        $f['breite_cm'] ? $f['breite_cm'] . ' cm' : null,
        $f['hoehe_cm']  ? $f['hoehe_cm']  . ' cm' : null,
    ]);
    return $teile ? implode(' × ', $teile) : '–';
}
?>

<div class="card">
    <div style="margin-bottom:16px;font-size:12px;color:var(--color-text-muted)">
        <?= count($faecher) ?> Fächer gesamt &nbsp;·&nbsp;
        <?= count(array_filter($faecher, fn($f) => $f['aktiv'] && !$f['vertrag_id'])) ?> frei &nbsp;·&nbsp;
        <?= count(array_filter($faecher, fn($f) => $f['aktiv'] && $f['vertrag_id']))  ?> belegt
    </div>

    <table class="erp-table">
        <thead>
            <tr>
                <th>BEZEICHNUNG</th>
                <th style="width:180px">ORT</th>
                <th style="width:140px">MASSE (L×B×H)</th>
                <th style="width:110px">STANDARD-PREIS</th>
                <th style="width:160px">MIETER</th>
                <th style="width:120px">STATUS</th>
                <th style="width:110px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($faecher)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--color-text-muted);padding:32px">Noch keine Fächer angelegt.</td></tr>
        <?php endif; ?>
        <?php foreach ($faecher as $f): ?>
            <tr <?= $f['aktiv'] ? '' : 'style="opacity:.5"' ?>>
                <td>
                    <strong><?= htmlspecialchars($f['fach_bezeichnung']) ?></strong>
                    <?php if ($f['notiz']): ?>
                        <div style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars($f['notiz']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px"><?= htmlspecialchars($f['ort_beschreibung'] ?? '–') ?></td>
                <td style="font-size:13px"><?= masse_str($f) ?></td>
                <td style="font-size:13px">
                    <?= $f['standard_preis'] ? number_format((float)$f['standard_preis'], 2) . ' €' : '–' ?>
                </td>
                <td>
                    <?php if ($f['mieter_name']): ?>
                        <span style="font-size:13px;font-weight:500"><?= htmlspecialchars($f['mieter_name']) ?></span>
                        <div style="font-size:11px;color:var(--color-text-muted)">
                            ab <?= date('d.m.Y', strtotime($f['mietbeginn'])) ?>
                            · <?= number_format((float)$f['vertrag_preis'], 2) ?> €
                        </div>
                    <?php else: ?>
                        <span style="color:var(--color-text-muted);font-size:12px">–</span>
                    <?php endif; ?>
                </td>
                <td><?= status_badge($f) ?></td>
                <td style="white-space:nowrap;display:flex;gap:4px">
                    <button class="btn btn-secondary btn-sm"
                            onclick='modalFachBearbeitenOeffnen(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)'
                            title="Fach bearbeiten">✎</button>
                    <?php if ($f['aktiv'] && !$f['vertrag_id']): ?>
                        <button class="btn btn-primary btn-sm"
                                onclick='modalVertragOeffnen(<?= $f['id'] ?>, <?= htmlspecialchars(json_encode($f['fach_bezeichnung']), ENT_QUOTES) ?>, <?= (float)($f['standard_preis'] ?? 0) ?>)'
                                title="Mieter zuweisen">Vermieten</button>
                    <?php elseif ($f['vertrag_id']): ?>
                        <button class="btn btn-secondary btn-sm"
                                onclick='modalVertragBeendenOeffnen(<?= $f['vertrag_id'] ?>, <?= htmlspecialchars(json_encode($f['fach_bezeichnung']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($f['mieter_name']), ENT_QUOTES) ?>)'
                                title="Mietvertrag beenden" style="color:#e67e22">Kündigen</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ================================================================
     MODAL: Fach neu anlegen
================================================================ -->
<div id="modal-fach-neu" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:480px;width:calc(100% - 32px);margin:50px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:18px 22px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:15px">Neues Mietfach anlegen</h3>
            <button onclick="document.getElementById('modal-fach-neu').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-fach-neu" onsubmit="fachSpeichern(event)">
            <div style="padding:18px 22px;display:flex;flex-direction:column;gap:12px">
                <?= fachFormFelder() ?>
            </div>
            <div style="padding:14px 22px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal-fach-neu').style.display='none'">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL: Fach bearbeiten
================================================================ -->
<div id="modal-fach-bearbeiten" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:480px;width:calc(100% - 32px);margin:50px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:18px 22px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:15px">Mietfach bearbeiten</h3>
            <button onclick="document.getElementById('modal-fach-bearbeiten').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-fach-bearbeiten" onsubmit="fachAktualisieren(event)">
            <input type="hidden" name="id" id="fb-id">
            <div style="padding:18px 22px;display:flex;flex-direction:column;gap:12px">
                <?= fachFormFelder('fb-') ?>
            </div>
            <div style="padding:14px 22px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal-fach-bearbeiten').style.display='none'">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL: Mieter zuweisen (Vertrag starten)
================================================================ -->
<div id="modal-vertrag" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:420px;width:calc(100% - 32px);margin:60px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:18px 22px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:15px" id="vertrag-titel">Mieter zuweisen</h3>
            <button onclick="document.getElementById('modal-vertrag').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-vertrag" onsubmit="vertragSpeichern(event)">
            <input type="hidden" name="mietfach_id" id="v-fach-id">
            <div style="padding:18px 22px;display:flex;flex-direction:column;gap:12px">
                <div>
                    <label class="erp-label">Partner (Mieter) *</label>
                    <select name="partner_id" id="v-partner" class="erp-select" required>
                        <option value="">— bitte wählen —</option>
                        <?php
                        require_once __DIR__ . '/../../src/modules/partner/PartnerService.php';
                        $partnerListe = (new PartnerService())->getAll(['aktiv' => 1]);
                        foreach ($partnerListe as $p):
                            if (!in_array($p['typ'], ['mietfach','kommission','beides'])) continue;
                        ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:10px">
                    <div style="flex:1;min-width:0">
                        <label class="erp-label">Mietbetrag/Monat (€) *</label>
                        <input type="number" name="mietbetrag_monatlich" id="v-preis"
                               class="erp-input" style="width:100%;box-sizing:border-box"
                               step="0.01" min="0.01" required>
                    </div>
                    <div style="width:90px;flex-shrink:0">
                        <label class="erp-label">MwSt %</label>
                        <input type="number" name="mwst_satz" id="v-mwst"
                               class="erp-input" style="width:100%;box-sizing:border-box"
                               step="0.01" min="0" value="20">
                    </div>
                </div>
                <div style="display:flex;gap:10px">
                    <div style="flex:1;min-width:0">
                        <label class="erp-label">Mietbeginn *</label>
                        <input type="date" name="mietbeginn" id="v-beginn"
                               class="erp-input" style="width:100%;box-sizing:border-box" required>
                    </div>
                    <div style="flex:1;min-width:0">
                        <label class="erp-label">Mietende (leer = unbefristet)</label>
                        <input type="date" name="mietende" id="v-ende"
                               class="erp-input" style="width:100%;box-sizing:border-box">
                    </div>
                </div>
                <div>
                    <label class="erp-label">Notiz</label>
                    <input type="text" name="notiz" id="v-notiz" class="erp-input" style="width:100%;box-sizing:border-box">
                </div>
            </div>
            <div style="padding:14px 22px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal-vertrag').style.display='none'">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Vertrag starten</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL: Vertrag beenden / kündigen
================================================================ -->
<div id="modal-kuendigen" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:380px;width:calc(100% - 32px);margin:80px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:18px 22px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:15px">Mietvertrag beenden</h3>
            <button onclick="document.getElementById('modal-kuendigen').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-kuendigen" onsubmit="vertragBeenden(event)">
            <input type="hidden" name="vertrag_id" id="k-vertrag-id">
            <div style="padding:18px 22px;display:flex;flex-direction:column;gap:12px">
                <p id="k-hinweis" style="margin:0;font-size:13px;color:#555"></p>
                <div>
                    <label class="erp-label">Mietende (Datum) *</label>
                    <input type="date" name="mietende" id="k-datum"
                           class="erp-input" style="width:100%;box-sizing:border-box" required>
                </div>
            </div>
            <div style="padding:14px 22px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal-kuendigen').style.display='none'">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm" style="background:#e74c3c;border-color:#e74c3c">Vertrag beenden</button>
            </div>
        </form>
    </div>
</div>

<div id="banner" style="display:none;position:fixed;top:16px;right:16px;z-index:2000;padding:10px 18px;border-radius:6px;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.2)"></div>

<?php
function fachFormFelder(string $prefix = ''): string {
    $p = $prefix;
    return <<<HTML
        <div>
            <label class="erp-label">Bezeichnung *</label>
            <input type="text" name="fach_bezeichnung" id="{$p}bezeichnung"
                   class="erp-input" style="width:100%;box-sizing:border-box" required
                   placeholder="z.B. Regal 3, Fach 2">
        </div>
        <div>
            <label class="erp-label">Ort / Beschreibung</label>
            <input type="text" name="ort_beschreibung" id="{$p}ort"
                   class="erp-input" style="width:100%;box-sizing:border-box"
                   placeholder="z.B. Rechtes Regal, Mitte">
        </div>
        <div>
            <label class="erp-label">Maße (cm) — Länge × Breite × Höhe</label>
            <div style="display:flex;gap:8px;align-items:center">
                <input type="number" name="laenge_cm" id="{$p}laenge"
                       class="erp-input" style="flex:1;min-width:0;box-sizing:border-box"
                       step="0.1" min="0" placeholder="L">
                <span style="color:#aaa;font-size:13px">×</span>
                <input type="number" name="breite_cm" id="{$p}breite"
                       class="erp-input" style="flex:1;min-width:0;box-sizing:border-box"
                       step="0.1" min="0" placeholder="B">
                <span style="color:#aaa;font-size:13px">×</span>
                <input type="number" name="hoehe_cm" id="{$p}hoehe"
                       class="erp-input" style="flex:1;min-width:0;box-sizing:border-box"
                       step="0.1" min="0" placeholder="H">
                <span style="font-size:12px;color:#888;white-space:nowrap">cm</span>
            </div>
        </div>
        <div>
            <label class="erp-label">Standard-Mietpreis/Monat (€)</label>
            <input type="number" name="standard_preis" id="{$p}preis"
                   class="erp-input" style="width:140px;box-sizing:border-box"
                   step="0.01" min="0" placeholder="0.00">
        </div>
        <div>
            <label class="erp-label">Notiz</label>
            <textarea name="notiz" id="{$p}notiz" class="erp-input" rows="2"
                      style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="aktiv" id="{$p}aktiv" value="1" checked>
            <label for="{$p}aktiv" style="cursor:pointer;font-size:13px">Fach aktiv (vermietbar)</label>
        </div>
    HTML;
}
?>

<script src="/mealana/js/partner_mietfaecher.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
