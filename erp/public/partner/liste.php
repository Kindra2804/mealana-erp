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

$pageTitle        = 'Partner';
$activeModule     = 'partner';
$actionBarContent = <<<HTML
    <button class="btn btn-primary btn-sm" onclick="modalNeuOeffnen()">+ Neuer Partner</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

function typ_chip(string $typ): string {
    return match($typ) {
        'mietfach'   => '<span class="chip" style="background:#fff8e1;color:#e65100;border:1px solid #ffcc02">Mietfach</span>',
        'kommission' => '<span class="chip" style="background:#e3f2fd;color:#1565c0;border:1px solid #90caf9">Kommission</span>',
        'spende'     => '<span class="chip" style="background:#f3e5f5;color:#6a1b9a;border:1px solid #ce93d8">Spende</span>',
        'beides'     => '<span class="chip" style="background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7">Sonderfall</span>',
        default      => htmlspecialchars($typ),
    };
}

function beleg_chip(string $typ): string {
    return match($typ) {
        'gutschrift'    => '<span style="font-size:11px;color:#555">Gutschrift</span>',
        'fremdrechnung' => '<span style="font-size:11px;color:#e67e22">Fremdrechnung</span>',
        'info'          => '<span style="font-size:11px;color:#888">Info</span>',
        default         => htmlspecialchars($typ),
    };
}
?>

<div class="card">
    <div class="filter-bar" style="margin-bottom:16px">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <select name="typ" class="erp-select" onchange="this.form.submit()">
                <option value=""           <?= $filterTyp === ''           ? 'selected' : '' ?>>Alle Typen</option>
                <option value="mietfach"   <?= $filterTyp === 'mietfach'   ? 'selected' : '' ?>>Mietfach</option>
                <option value="kommission" <?= $filterTyp === 'kommission' ? 'selected' : '' ?>>Kommission</option>
                <option value="spende"     <?= $filterTyp === 'spende'     ? 'selected' : '' ?>>Spende</option>
                <option value="beides"     <?= $filterTyp === 'beides'     ? 'selected' : '' ?>>Sonderfall</option>
            </select>
            <select name="aktiv" class="erp-select" onchange="this.form.submit()">
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
                <th style="width:140px">TYP</th>
                <th style="width:110px">MIETFÄCHER</th>
                <th style="width:90px">PROVISION</th>
                <th style="width:130px">ABRECHNUNG</th>
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
                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                    <?php if ($p['email']): ?>
                        <div style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars($p['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= typ_chip($p['typ']) ?></td>
                <td>
                    <?php $anzahl = (int)($p['aktuelle_faecher'] ?? 0); ?>
                    <?php if ($anzahl > 0): ?>
                        <a href="/mealana/partner/mietfaecher.php"
                           style="font-size:12px;color:var(--color-primary)">
                            <?= $anzahl ?> Fach<?= $anzahl !== 1 ? '&auml;cher' : '' ?>
                        </a>
                    <?php else: ?>
                        <span style="font-size:12px;color:var(--color-text-muted)">–</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px">
                    <?php if (in_array($p['typ'], ['kommission','beides'])): ?>
                        <?= number_format((float)$p['provisions_satz'], 1) ?>&thinsp;%
                    <?php elseif ($p['typ'] === 'mietfach'): ?>
                        <span style="font-size:11px;color:var(--color-text-muted)">Fremdrechnung</span>
                    <?php else: ?>
                        <span style="color:var(--color-text-muted)">–</span>
                    <?php endif; ?>
                </td>
                <td><?= beleg_chip($p['abrechnungs_beleg_typ']) ?></td>
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
                <?= partnerFormFelder() ?>
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
                <?= partnerFormFelder('edit-') ?>
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
function partnerFormFelder(string $prefix = ''): string {
    $p = $prefix;
    return <<<HTML
        <div>
            <label class="erp-label">Name *</label>
            <input type="text" name="name" id="{$p}name" class="erp-input" style="width:100%;box-sizing:border-box" required>
        </div>
        <div>
            <label class="erp-label">Typ *</label>
            <select name="typ" id="{$p}typ" class="erp-select" onchange="typToggle('{$p}')">
                <option value="mietfach">Mietfach – Fremdrechnung (Quittungsblock des Fachieters)</option>
                <option value="kommission">Kommission – eigene Abrechnung, Provision</option>
                <option value="spende">Spende – Durchlaufposten (z.B. Yarnpride)</option>
                <option value="beides">Sonderfall (Kombination)</option>
            </select>
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
        <div id="{$p}provision-zeile">
            <label class="erp-label">Provision %</label>
            <input type="number" name="provisions_satz" id="{$p}provisions_satz"
                   class="erp-input" step="0.01" min="0" max="100" value="0" style="width:110px">
        </div>
        <div style="display:flex;gap:10px">
            <div style="flex:1;min-width:0">
                <label class="erp-label">Abrechnungs-Modus</label>
                <select name="abrechnungs_modus" id="{$p}abrechnungs_modus" class="erp-select">
                    <option value="getrennt">Getrennt</option>
                    <option value="gegenverrechnung">Gegenverrechnung</option>
                </select>
            </div>
            <div style="flex:1;min-width:0">
                <label class="erp-label">Beleg-Typ</label>
                <select name="abrechnungs_beleg_typ" id="{$p}abrechnungs_beleg_typ" class="erp-select">
                    <option value="gutschrift">Gutschrift (MeaLana stellt)</option>
                    <option value="fremdrechnung">Fremdrechnung (Partner stellt)</option>
                    <option value="info">Info-Abrechnung</option>
                </select>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="kleinunternehmer" id="{$p}kleinunternehmer" value="1">
            <label for="{$p}kleinunternehmer" style="cursor:pointer;font-size:13px">Kleinunternehmer (kein USt-Ausweis)</label>
        </div>
        <div>
            <label class="erp-label">Notiz</label>
            <textarea name="notiz" id="{$p}notiz" class="erp-input" rows="2"
                      style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
        </div>
    HTML;
}
?>

<script>
function zeigeBanner(msg, ok = true) {
    const b = document.getElementById('banner');
    b.textContent = msg;
    b.style.background = ok ? '#2ecc71' : '#e74c3c';
    b.style.color      = '#fff';
    b.style.display    = 'block';
    setTimeout(() => { b.style.display = 'none'; }, 3000);
}

function typToggle(prefix) {
    const typ       = document.getElementById(prefix + 'typ').value;
    const provZeile = document.getElementById(prefix + 'provision-zeile');
    const belegSel  = document.getElementById(prefix + 'abrechnungs_beleg_typ');

    // Provision nur bei Kommission und Sonderfall sinnvoll
    if (provZeile) provZeile.style.display = ['kommission','beides'].includes(typ) ? '' : 'none';

    // Beleg-Typ automatisch vorbelegen (nur wenn Nutzer noch nichts geändert hat)
    if (belegSel) {
        const autoMap = { mietfach: 'fremdrechnung', kommission: 'gutschrift', spende: 'info', beides: 'gutschrift' };
        if (autoMap[typ]) belegSel.value = autoMap[typ];
    }
}

// Partner Neu
function modalNeuOeffnen() {
    document.getElementById('form-neu').reset();
    typToggle('');
    document.getElementById('modal-neu').style.display = 'block';
}
function modalNeuSchliessen() {
    document.getElementById('modal-neu').style.display = 'none';
}
async function partnerSpeichern(e) {
    e.preventDefault();
    const res  = await fetch('/mealana/partner/speichern.php', { method: 'POST', body: new FormData(e.target) });
    const data = await res.json();
    if (data.erfolg) { zeigeBanner('Partner gespeichert.'); modalNeuSchliessen(); setTimeout(() => location.reload(), 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

// Partner Bearbeiten
function modalBearbeitenOeffnen(p) {
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
    const chk = (id, val) => { const el = document.getElementById(id); if (el) el.checked = !!parseInt(val); };
    document.getElementById('edit-id').value = p.id;
    set('edit-name',                  p.name);
    set('edit-typ',                   p.typ);
    set('edit-email',                 p.email);
    set('edit-telefon',               p.telefon);
    set('edit-iban',                  p.iban);
    set('edit-uid_nummer',            p.uid_nummer);
    set('edit-zvr_nummer',            p.zvr_nummer);
    set('edit-provisions_satz',       p.provisions_satz);
    set('edit-abrechnungs_modus',     p.abrechnungs_modus);
    set('edit-abrechnungs_beleg_typ', p.abrechnungs_beleg_typ);
    set('edit-notiz',                 p.notiz);
    chk('edit-kleinunternehmer',      p.kleinunternehmer);
    typToggle('edit-');
    document.getElementById('modal-bearbeiten').style.display = 'block';
}
function modalBearbeitenSchliessen() {
    document.getElementById('modal-bearbeiten').style.display = 'none';
}
async function partnerAktualisieren(e) {
    e.preventDefault();
    const res  = await fetch('/mealana/partner/aktualisieren.php', { method: 'POST', body: new FormData(e.target) });
    const data = await res.json();
    if (data.erfolg) { zeigeBanner('Partner gespeichert.'); modalBearbeitenSchliessen(); setTimeout(() => location.reload(), 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

// Status Toggle
async function statusToggle(id, aktiv) {
    const fd = new FormData();
    fd.append('id', id); fd.append('aktiv', aktiv);
    const res  = await fetch('/mealana/partner/status_setzen.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.erfolg) { zeigeBanner(aktiv ? 'Aktiviert.' : 'Deaktiviert.'); setTimeout(() => location.reload(), 600); }
    else             { zeigeBanner('Fehler beim Speichern.', false); }
}

document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    modalNeuSchliessen();
    modalBearbeitenSchliessen();
});
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
