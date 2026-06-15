<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

$service          = new ArtikelService();
$lieferantService = new LieferantenService();

$hersteller      = $service->getAllHersteller();
$steuerklassen   = $service->getAllSteuerklassen();
$artikelTypen    = $service->getAllArtikelTypen();
$alleEinheiten   = $service->getAllEinheiten();
$alleKategorien  = $service->getAlleKategorien();
$alleLieferanten = $lieferantService->findAll();

$zustandSuffixMap = [
    'neu'                => '',
    'gebraucht'          => 'GEB',
    'generalueberholt'   => 'GUE',
    'beschaedigt'        => 'BSC',
    'retour'             => 'RET',
    'demo'               => 'DMO',
    'muster'             => 'MST',
    'ausstellungsstueck' => 'AST',
];

function old(string $field, array $formdata, string $default = ''): string
{
    return htmlspecialchars($formdata[$field] ?? $default);
}

function selected(string $field, string $value, array $formdata): string
{
    return ($formdata[$field] ?? '') === $value ? 'selected' : '';
}

$pageTitle      = 'Neuer Artikel';
$activeModule   = 'artikel';
$actionBarContent = <<<HTML
<button form="artikel-neu-form" type="submit" class="btn btn-primary btn-sm">💾 Speichern</button>
<a href="liste.php" class="btn btn-secondary btn-sm">Abbrechen</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if (!empty($fehler)): ?>
<div style="background:#fff5f5;border-left:3px solid var(--color-danger);padding:var(--space-sm) var(--space-md);border-radius:4px;margin-bottom:var(--space-md)">
    <strong>Bitte korrigiere folgende Fehler:</strong>
    <ul style="margin:var(--space-xs) 0 0 var(--space-md)">
        <?php foreach ($fehler as $f): ?>
            <li><?= htmlspecialchars($f) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="pflicht-banner">ℹ Felder mit <span class="pflicht-stern">*</span> sind Pflichtangaben</div>

<form id="artikel-neu-form" action="speichern.php" method="POST">
<input type="hidden" name="aktiv" value="1">

<!-- ── Stammdaten ──────────────────────────────────────── -->
<div class="card">
    <div class="form-section">
        <div class="form-section-header">Stammdaten</div>

        <div class="form-row">
            <label class="form-label">Artikelname <span class="pflicht-stern">*</span></label>
            <input type="text" name="name" class="erp-input" style="width:100%"
                required value="<?= old('name', $formdata) ?>">
        </div>
        <div class="form-row">
            <label class="form-label">Artikelnummer <span class="pflicht-stern">*</span></label>
            <input type="text" name="artikelnummer" id="artikelnummer" class="erp-input"
                required value="<?= old('artikelnummer', $formdata) ?>">
        </div>
        <div class="form-row">
            <label class="form-label">EAN / GTIN13</label>
            <input type="text" name="ean_gtin13" class="erp-input" maxlength="13"
                value="<?= old('ean_gtin13', $formdata) ?>">
        </div>
        <div class="form-row">
            <label class="form-label">Hersteller</label>
            <select name="hersteller_id" class="erp-select">
                <option value="">– kein Hersteller –</option>
                <?php foreach ($hersteller as $h): ?>
                    <option value="<?= $h['id'] ?>"
                        <?= selected('hersteller_id', (string)$h['id'], $formdata) ?>>
                        <?= htmlspecialchars($h['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- ── Klassifizierung ─────────────────────────────────── -->
<div class="card">
    <div class="form-section">
        <div class="form-section-header">Klassifizierung</div>

        <div class="form-row">
            <label class="form-label">Artikeltyp <span class="pflicht-stern">*</span></label>
            <select name="artikeltyp" id="artikeltyp" class="erp-select">
                <option value="">– bitte wählen –</option>
                <?php foreach ($artikelTypen as $typ): ?>
                    <option value="<?= htmlspecialchars($typ['code']) ?>"
                        <?= selected('artikeltyp', $typ['code'], $formdata) ?>>
                        <?= htmlspecialchars($typ['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label">Steuerklasse <span class="pflicht-stern">*</span></label>
            <select name="steuerklasse_id" id="steuerklasse_id" class="erp-select">
                <?php foreach ($steuerklassen as $s): ?>
                    <option value="<?= $s['id'] ?>" data-satz="<?= $s['satz'] ?>"
                        <?= selected('steuerklasse_id', (string)$s['id'], $formdata) ?>>
                        <?= htmlspecialchars($s['name']) ?> (<?= $s['satz'] ?>%)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label">Einheit</label>
            <select name="einheit_id" class="erp-select">
                <?php foreach ($alleEinheiten as $e): ?>
                    <option value="<?= $e['id'] ?>"
                        <?= selected('einheit_id', (string)$e['id'], $formdata) ?>>
                        <?= htmlspecialchars($e['name']) ?>
                        <?= $e['kuerzel'] ? ' (' . htmlspecialchars($e['kuerzel']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label">Zustand <span class="pflicht-stern">*</span></label>
            <select name="zustand" id="zustand_select" class="erp-select"
                onchange="zustandGeaendert(this.value)">
                <?php foreach ($zustandSuffixMap as $wert => $suffix): ?>
                    <option value="<?= $wert ?>" <?= selected('zustand', $wert, $formdata) ?>>
                        <?= $wert === 'neu'
                            ? 'Neu (Standard)'
                            : htmlspecialchars(ucfirst(str_replace('_', ' ', $wert))) . ' (' . $suffix . ')' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label">Kategorie</label>
            <select name="kategorie_id" class="erp-select">
                <option value="">– keine –</option>
                <?php foreach ($alleKategorien as $k): ?>
                    <option value="<?= $k['id'] ?>"
                        <?= selected('kategorie_id', (string)$k['id'], $formdata) ?>>
                        <?= htmlspecialchars($k['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Vaterartikel — nur sichtbar wenn Zustand ≠ Neu -->
        <div id="vater_suche_bereich" class="versteckt" style="position:relative">
            <div class="form-row">
                <label class="form-label">Vater-Artikel <span class="pflicht-stern">*</span></label>
                <div style="position:relative;flex:1">
                    <input type="text" id="vater_suche_input" class="erp-input" style="width:100%"
                        placeholder="Mind. 2 Zeichen – Artikelnummer oder Name…"
                        autocomplete="off"
                        oninput="vaterSuchen(this.value)">
                    <div id="vater_suche_ergebnis"
                        style="position:absolute;top:100%;left:0;width:100%;border:1px solid var(--color-border);border-radius:4px;background:#fff;display:none;max-height:200px;overflow-y:auto;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,0.15)">
                    </div>
                </div>
            </div>
            <input type="hidden" name="zustand_vater_id" id="zustand_vater_id"
                value="<?= (int)($formdata['zustand_vater_id'] ?? 0) ?: '' ?>">
            <div id="vater_info"
                style="padding-left:196px;font-size:12px;color:var(--color-text-muted);margin-top:2px">
                <?php if (!empty($formdata['zustand_vater_id'])): ?>
                    <?php $vaterInfo = $service->findByIdSimple((int)$formdata['zustand_vater_id']); ?>
                    <?php if ($vaterInfo): ?>
                        Vater: <strong><?= htmlspecialchars($vaterInfo['artikelnummer']) ?></strong>
                        – <?= htmlspecialchars($vaterInfo['name']) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Verkaufspreis ────────────────────────────────────── -->
<div class="card">
    <div class="form-section">
        <div class="form-section-header">Verkaufspreis</div>

        <div class="form-row">
            <label class="form-label">Brutto-VK <span class="pflicht-stern">*</span></label>
            <input type="number" step="0.01" name="brutto_vk" id="brutto_vk" class="erp-input"
                value="<?= old('brutto_vk', $formdata) ?>">
        </div>
        <div class="form-row">
            <label class="form-label">Netto-VK (berechnet)</label>
            <input type="number" step="0.0001" name="netto_vk" id="netto_vk" class="erp-input"
                readonly style="background:var(--color-bg)"
                value="<?= old('netto_vk', $formdata) ?>">
        </div>

        <!-- Inhalt-Felder — werden per JS bei physischen Einheiten eingeblendet -->
        <div id="felder-physisch" class="versteckt">
            <div class="form-row" style="margin-top:var(--space-md);padding-top:var(--space-md);border-top:1px solid var(--color-border)">
                <label class="form-label">Inhalt / Menge</label>
                <input type="number" step="0.001" name="inhalt_menge" class="erp-input"
                    value="<?= old('inhalt_menge', $formdata) ?>">
            </div>
            <div class="form-row">
                <label class="form-label">Inhalt-Einheit (g, m, ml…)</label>
                <input type="text" name="inhalt_einheit" class="erp-input"
                    value="<?= old('inhalt_einheit', $formdata) ?>">
            </div>
        </div>

        <!-- Grundpreis — wird per JS bei Meterware / Gramm-Einheiten eingeblendet -->
        <div id="grundpreis_container" class="versteckt">
            <div class="form-row">
                <label class="form-label" id="bezugsmenge_label">Bezugsmenge (g)</label>
                <input type="number" name="grundpreis_bezugsmenge" class="erp-input"
                    value="<?= old('grundpreis_bezugsmenge', $formdata, '100') ?>">
            </div>
            <div class="form-row">
                <label class="form-label">Grundpreis (berechnet)</label>
                <div id="grundpreis_anzeige"
                    style="font-size:1rem;color:var(--color-nav);font-weight:bold;padding:4px 0">
                    – wird berechnet –
                </div>
            </div>
            <div class="form-row">
                <label class="form-label">Grundpreis im Shop</label>
                <select name="grundpreis_anzeigen" class="erp-select">
                    <option value="1" <?= selected('grundpreis_anzeigen', '1', $formdata) ?>>Ja</option>
                    <option value="0" <?= selected('grundpreis_anzeigen', '0', $formdata) ?>>Nein</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- ── Lieferant (optional, einklappbar) ───────────────── -->
<div class="card">
    <div class="form-section-header" style="cursor:pointer;margin-bottom:0"
        onclick="toggleLieferantSektion(this)">
        <span id="lf-toggle-icon">▶</span>
        Lieferant
        <span style="font-weight:normal;color:var(--color-text-muted);font-size:0.85em;margin-left:4px">(optional)</span>
    </div>
    <div id="lieferant-bereich" class="versteckt" style="margin-top:var(--space-md)">
        <div class="form-row">
            <label class="form-label">Lieferant</label>
            <select name="lf_lieferant_id" id="lf_lieferant_id" class="erp-select">
                <option value="">– auswählen –</option>
                <?php foreach ($alleLieferanten as $lf): ?>
                    <option value="<?= $lf['id'] ?>"
                        <?= selected('lf_lieferant_id', (string)$lf['id'], $formdata) ?>>
                        <?= htmlspecialchars($lf['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label">Lieferanten-Artikelnr.</label>
            <input type="text" name="lf_artikelnummer" class="erp-input"
                value="<?= old('lf_artikelnummer', $formdata) ?>">
        </div>
        <div class="form-row">
            <label class="form-label">EK Netto (€)</label>
            <input type="number" step="0.0001" name="lf_ek_netto" id="lf_ek_netto" class="erp-input"
                value="<?= old('lf_ek_netto', $formdata) ?>"
                oninput="berechneEkBrutto()">
        </div>
        <div class="form-row">
            <label class="form-label">EK Brutto (auto)</label>
            <input type="number" step="0.01" name="lf_ek_brutto" id="lf_ek_brutto" class="erp-input"
                readonly style="background:var(--color-bg)"
                value="<?= old('lf_ek_brutto', $formdata) ?>">
        </div>
        <div class="form-row">
            <label class="form-label">Währung</label>
            <select name="lf_waehrung" class="erp-select">
                <option value="EUR" <?= selected('lf_waehrung', 'EUR', $formdata) ?>>EUR</option>
                <option value="USD" <?= selected('lf_waehrung', 'USD', $formdata) ?>>USD</option>
                <option value="CHF" <?= selected('lf_waehrung', 'CHF', $formdata) ?>>CHF</option>
            </select>
        </div>
    </div>
</div>

</form>

<script src="/mealana/js/artikel.js"></script>
<script>
const zustandSuffixMap = <?= json_encode(array_filter($zustandSuffixMap)) ?>;
let vaterArtikelNummer = '';

function zustandGeaendert(wert) {
    const bereich = document.getElementById('vater_suche_bereich');
    const artnrInput = document.getElementById('artikelnummer');
    if (wert === 'neu') {
        bereich.classList.add('versteckt');
        artnrInput.readOnly = false;
        artnrInput.style.background = '';
        artnrInput.value = '';
        vaterArtikelNummer = '';
    } else {
        bereich.classList.remove('versteckt');
        artnrInput.readOnly = true;
        artnrInput.style.background = 'var(--color-bg)';
        aktualisiereArtnr(wert);
    }
}

function aktualisiereArtnr(zustand) {
    const suffix = zustandSuffixMap[zustand] || '';
    const artnrInput = document.getElementById('artikelnummer');
    artnrInput.value = (vaterArtikelNummer && suffix) ? vaterArtikelNummer + '-' + suffix : '';
}

let vaterSuchTimer = null;
function vaterSuchen(q) {
    clearTimeout(vaterSuchTimer);
    const ergebnisDiv = document.getElementById('vater_suche_ergebnis');
    if (q.length < 2) { ergebnisDiv.style.display = 'none'; return; }
    vaterSuchTimer = setTimeout(() => {
        fetch('artikel_vater_suche.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.length) { ergebnisDiv.style.display = 'none'; return; }
                ergebnisDiv.innerHTML = data.map(a =>
                    `<div style="padding:6px 10px;cursor:pointer;border-bottom:1px solid #eee;font-size:13px"
                        onmousedown="vaterAuswaehlen(${a.id},'${a.artikelnummer.replace(/'/g,"\\'")}','${a.name.replace(/'/g,"\\'")}')">
                        <strong>${a.artikelnummer}</strong> – ${a.name}
                    </div>`
                ).join('');
                ergebnisDiv.style.display = 'block';
            });
    }, 250);
}

function vaterAuswaehlen(id, artnr, name) {
    document.getElementById('zustand_vater_id').value = id;
    document.getElementById('vater_suche_input').value = artnr + ' – ' + name;
    document.getElementById('vater_info').innerHTML =
        'Vater: <strong>' + artnr + '</strong> – ' + name;
    document.getElementById('vater_suche_ergebnis').style.display = 'none';
    vaterArtikelNummer = artnr;
    aktualisiereArtnr(document.getElementById('zustand_select').value);
}

function toggleLieferantSektion(headerEl) {
    const body = document.getElementById('lieferant-bereich');
    const icon = document.getElementById('lf-toggle-icon');
    const oeffnen = body.classList.contains('versteckt');
    body.classList.toggle('versteckt', !oeffnen);
    icon.textContent = oeffnen ? '▼' : '▶';
}

function berechneEkBrutto() {
    const netto = parseFloat(document.getElementById('lf_ek_netto').value) || 0;
    const steuerSel = document.getElementById('steuerklasse_id');
    const satz = parseFloat(steuerSel?.selectedOptions[0]?.dataset.satz || 0);
    const brutto = netto * (1 + satz / 100);
    document.getElementById('lf_ek_brutto').value = brutto > 0 ? brutto.toFixed(2) : '';
}

// Init
const initZustand = '<?= old('zustand', $formdata, 'neu') ?>';
if (initZustand !== 'neu') zustandGeaendert(initZustand);

const gespeicherterTyp = '<?= old('artikeltyp', $formdata) ?>';
if (gespeicherterTyp) zeigeFelder(gespeicherterTyp);

document.getElementById('artikeltyp').addEventListener('change', e => zeigeFelder(e.target.value));
document.getElementById('brutto_vk').addEventListener('input', () => { berechneNetto(); berechneGrundpreis(); });
document.getElementById('steuerklasse_id').addEventListener('change', () => { berechneNetto(); berechneEkBrutto(); });
document.querySelector('[name="grundpreis_bezugsmenge"]')?.addEventListener('input', berechneGrundpreis);
document.querySelector('[name="inhalt_menge"]')?.addEventListener('input', berechneGrundpreis);
document.querySelector('[name="inhalt_einheit"]')?.addEventListener('input', berechneGrundpreis);

berechneNetto();
berechneGrundpreis();
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
