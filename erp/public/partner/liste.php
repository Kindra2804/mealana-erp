<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerService.php';

$service     = new PartnerService();
$filterTyp   = $_GET['typ']   ?? '';
$filterAktiv = $_GET['aktiv'] ?? '1';

$filter = [];
if ($filterTyp   !== '') $filter['typ']   = $filterTyp;
if ($filterAktiv !== '') $filter['aktiv'] = (int)$filterAktiv;

$partner = $service->getAll($filter);

// Auswahllisten fürs Formular
$db          = Database::getInstance();
$laender     = $db->query("SELECT iso_code, name_de FROM laender ORDER BY iso_code <> 'AT', iso_code <> 'DE', name_de")->fetchAll();
$lieferanten = $db->query("SELECT id, COALESCE(NULLIF(firma, ''), name) AS name FROM lieferanten WHERE aktiv = 1 ORDER BY 2")->fetchAll();

$pageTitle       = 'Partner';
$activeModule     = 'partner';
$actionBarContent = <<<HTML
    <button class="btn btn-primary btn-sm" onclick="modalNeuOeffnen()">+ Neuer Partner</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

function typ_chip(string $typ): string {
    return match($typ) {
        'fremdrechnung' => '<span class="chip" style="background:#fff8e1;color:#e65100;border:1px solid #ffcc02">Fremdrechnung</span>',
        'kommission'    => '<span class="chip" style="background:#e3f2fd;color:#1565c0;border:1px solid #90caf9">Kommission</span>',
        'spende'        => '<span class="chip" style="background:#f3e5f5;color:#6a1b9a;border:1px solid #ce93d8">Spende</span>',
        default         => htmlspecialchars($typ),
    };
}
?>

<div class="card">
    <div class="filter-bar" style="margin-bottom:16px">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <select name="typ" class="erp-select" onchange="this.form.requestSubmit()">
                <option value="" <?= $filterTyp === '' ? 'selected' : '' ?>>Alle Verkaufsarten</option>
                <?php foreach (PartnerService::VERKAUFSARTEN as $wert => $label): ?>
                <option value="<?= $wert ?>" <?= $filterTyp === $wert ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <select name="aktiv" class="erp-select" onchange="this.form.requestSubmit()">
                <option value="1" <?= $filterAktiv === '1' ? 'selected' : '' ?>>Nur aktive</option>
                <option value=""  <?= $filterAktiv === ''  ? 'selected' : '' ?>>Alle</option>
                <option value="0" <?= $filterAktiv === '0' ? 'selected' : '' ?>>Nur inaktive</option>
            </select>
        </form>
        <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
            <?= count($partner) ?> Partner
        </div>
    </div>

    <table class="erp-table">
        <thead>
            <tr>
                <th>NAME</th>
                <th style="width:140px">VERKAUFSART</th>
                <th style="width:110px">MIETFÄCHER</th>
                <th style="width:90px">PROVISION</th>
                <th style="width:160px">ORT</th>
                <th style="width:80px">STATUS</th>
                <th style="width:90px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($partner)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--color-text-muted);padding:32px">Keine Partner gefunden.</td></tr>
        <?php endif; ?>
        <?php foreach ($partner as $p): ?>
            <tr <?= $p['aktiv'] ? '' : 'style="opacity:.55"' ?>>
                <td>
                    <a href="<?= BASE_PATH ?>/partner/detail.php?id=<?= (int)$p['id'] ?>"><strong><?= htmlspecialchars($p['name']) ?></strong></a>
                    <?php if ($p['email']): ?>
                        <div style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars($p['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?= typ_chip($p['typ']) ?>
                    <?php if ($p['kleinunternehmer']): ?><div style="font-size:11px;color:var(--color-text-muted)">Kleinunternehmer</div><?php endif; ?>
                </td>
                <td>
                    <?php $anzahl = (int)($p['aktuelle_faecher'] ?? 0); ?>
                    <?php if ($anzahl > 0): ?>
                        <a href="<?= BASE_PATH ?>/partner/mietfaecher.php"
                           style="font-size:12px;color:var(--color-primary)">
                            <?= $anzahl ?> Fach<?= $anzahl !== 1 ? '&auml;cher' : '' ?>
                        </a>
                    <?php else: ?>
                        <span style="font-size:12px;color:var(--color-text-muted)">–</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px">
                    <?php if ($p['typ'] === 'kommission'): ?>
                        <?= number_format((float)$p['provisions_satz'], 1) ?>&thinsp;%
                    <?php else: ?>
                        <span style="color:var(--color-text-muted)">–</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px">
                    <?php if ($p['ort']): ?>
                        <?= htmlspecialchars(trim($p['plz'] . ' ' . $p['ort'])) ?>
                    <?php else: ?>
                        <span style="font-size:11px;color:#b45309" title="Adresse wird für Bon und Abrechnung gebraucht">Adresse fehlt</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['aktiv']): ?>
                        <span class="chip chip-aktiv">Aktiv</span>
                    <?php else: ?>
                        <span class="chip">Inaktiv</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                    <button class="btn btn-secondary btn-sm"
                            onclick="modalBearbeitenOeffnen(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"
                            title="Bearbeiten">✎</button>
                    <button class="btn btn-secondary btn-sm"
                            onclick="statusToggle(<?= $p['id'] ?>, <?= $p['aktiv'] ? 0 : 1 ?>)"
                            title="<?= $p['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                        <?= $p['aktiv'] ? '⏸' : '▶' ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ================================================================
     MODAL: Partner Neu
================================================================ -->
<div id="modal-neu" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:560px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Neuer Partner</h3>
            <button onclick="modalNeuSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-neu" onsubmit="partnerSpeichern(event)">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <?= partnerFormFelder('', $laender, $lieferanten) ?>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="modalNeuSchliessen()">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL: Partner Bearbeiten
================================================================ -->
<div id="modal-bearbeiten" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:560px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Partner bearbeiten</h3>
            <button onclick="modalBearbeitenSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-bearbeiten" onsubmit="partnerAktualisieren(event)">
            <input type="hidden" name="id" id="edit-id">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <?= partnerFormFelder('edit-', $laender, $lieferanten) ?>
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
function partnerFormFelder(string $prefix, array $laender, array $lieferanten): string {
    $p = $prefix;
    $landOptionen = '';
    foreach ($laender as $l) {
        $landOptionen .= '<option value="' . $l['iso_code'] . '">' . htmlspecialchars($l['iso_code'] . ' – ' . $l['name_de']) . '</option>';
    }
    $lieferantOptionen = '<option value="">— noch keiner —</option>';
    foreach ($lieferanten as $l) {
        $lieferantOptionen .= '<option value="' . (int)$l['id'] . '">' . htmlspecialchars($l['name']) . '</option>';
    }
    $kuStandard = htmlspecialchars(PartnerService::KU_STANDARDTEXT);
    return <<<HTML
        <div>
            <label class="erp-label">Name *</label>
            <input type="text" name="name" id="{$p}name" class="erp-input" style="width:100%;box-sizing:border-box" required>
        </div>
        <div>
            <label class="erp-label">Straße</label>
            <input type="text" name="strasse" id="{$p}strasse" class="erp-input" style="width:100%;box-sizing:border-box">
        </div>
        <div style="display:flex;gap:10px">
            <div style="width:90px">
                <label class="erp-label">PLZ</label>
                <input type="text" name="plz" id="{$p}plz" class="erp-input" style="width:100%;box-sizing:border-box">
            </div>
            <div style="flex:1;min-width:0">
                <label class="erp-label">Ort</label>
                <input type="text" name="ort" id="{$p}ort" class="erp-input" style="width:100%;box-sizing:border-box">
            </div>
            <div style="width:150px">
                <label class="erp-label">Land</label>
                <select name="land" id="{$p}land" class="erp-select" style="width:100%">{$landOptionen}</select>
            </div>
        </div>
        <div>
            <label class="erp-label">Verkaufsart *</label>
            <select name="typ" id="{$p}typ" class="erp-select" onchange="typToggle('{$p}')">
                <option value="fremdrechnung">Fremdrechnung – im Namen und auf Rechnung des Partners</option>
                <option value="kommission">Kommission – unser Umsatz, Partner stellt uns Rechnung</option>
                <option value="spende">Spende – Durchlauf (z.B. Yarnpride)</option>
            </select>
            <div id="{$p}typ-hinweis" style="font-size:11px;color:var(--color-text-muted);margin-top:4px"></div>
        </div>
        <div style="display:flex;gap:10px">
            <div style="flex:1;min-width:0">
                <label class="erp-label">E-Mail</label>
                <input type="email" name="email" id="{$p}email" class="erp-input" style="width:100%;box-sizing:border-box">
            </div>
            <div style="flex:1;min-width:0">
                <label class="erp-label">Telefon</label>
                <input type="text" name="telefon" id="{$p}telefon" class="erp-input" style="width:100%;box-sizing:border-box">
            </div>
        </div>
        <div>
            <label class="erp-label">IBAN</label>
            <input type="text" name="iban" id="{$p}iban" class="erp-input" style="width:100%;box-sizing:border-box" placeholder="AT00 0000 0000 0000 0000">
        </div>
        <div style="display:flex;gap:10px">
            <div style="flex:1;min-width:0">
                <label class="erp-label">UID-Nummer</label>
                <input type="text" name="uid_nummer" id="{$p}uid_nummer" class="erp-input" style="width:100%;box-sizing:border-box">
            </div>
            <div style="flex:1;min-width:0">
                <label class="erp-label">ZVR-Nummer (Verein)</label>
                <input type="text" name="zvr_nummer" id="{$p}zvr_nummer" class="erp-input" style="width:100%;box-sizing:border-box">
            </div>
        </div>
        <div id="{$p}kommission-zeile" style="display:flex;gap:10px">
            <div style="width:130px">
                <label class="erp-label">Provision % (netto)</label>
                <input type="number" name="provisions_satz" id="{$p}provisions_satz"
                       class="erp-input" step="0.01" min="0" max="100" value="0" style="width:100%;box-sizing:border-box">
            </div>
            <div style="flex:1;min-width:0">
                <label class="erp-label">Lieferant (stellt uns die Rechnung)</label>
                <select name="lieferant_id" id="{$p}lieferant_id" class="erp-select" style="width:100%">{$lieferantOptionen}</select>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="kleinunternehmer" id="{$p}kleinunternehmer" value="1" onchange="typToggle('{$p}')">
            <label for="{$p}kleinunternehmer" style="cursor:pointer;font-size:13px">Kleinunternehmer (kein USt-Ausweis)</label>
        </div>
        <div id="{$p}ku-zeile">
            <label class="erp-label">Kleinunternehmer-Text am Bon (leer = Standard)</label>
            <input type="text" name="ku_text" id="{$p}ku_text" class="erp-input" maxlength="255"
                   style="width:100%;box-sizing:border-box" placeholder="{$kuStandard}">
        </div>
        <div>
            <label class="erp-label">Notiz</label>
            <textarea name="notiz" id="{$p}notiz" class="erp-input" rows="2"
                      style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
        </div>
    HTML;
}
?>

<script src="<?= BASE_PATH ?>/js/partner_liste.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
