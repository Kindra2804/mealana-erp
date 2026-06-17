<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/MerkmaleRepository.php';
require_once __DIR__ . '/../../src/core/Database.php';

$repo     = new MerkmaleRepository();
$merkmale = $repo->findAllMitWerten();
$db       = Database::getInstance();
$artikeltypen = $db->query("SELECT id, name FROM artikel_typen ORDER BY name")->fetchAll();

$pageTitle    = 'Merkmale verwalten';
$activeModule = 'artikel';

$actionBarContent = <<<HTML
<button onclick="merkmalNeu()" class="btn btn-primary btn-sm">+ Neues Merkmal</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div style="padding:var(--space-md)">

<?php if (empty($merkmale)): ?>
    <div class="card" style="color:var(--color-text-muted);font-size:13px">
        Noch keine Merkmale angelegt. Klicke auf „+ Neues Merkmal".
    </div>
<?php endif; ?>

<?php foreach ($merkmale as $mi => $m): ?>
<div class="card" style="margin-bottom:var(--space-sm)" id="merkmal-<?= $m['id'] ?>">

    <div style="display:flex;align-items:center;gap:var(--space-sm);flex-wrap:wrap">
        <strong style="font-size:14px;flex:1"><?= htmlspecialchars($m['name']) ?></strong>

        <?php if ($m['slug']): ?>
            <span style="font-size:11px;color:var(--color-text-muted);font-family:monospace">pa_<?= htmlspecialchars($m['slug']) ?></span>
        <?php endif; ?>

        <span class="chip <?= $m['mehrfach_auswahl'] ? 'chip-aktiv' : '' ?>" style="font-size:11px">
            <?= $m['mehrfach_auswahl'] ? 'Multi' : 'Single' ?>
        </span>

        <?php if ($m['filterbar']): ?>
            <span class="chip chip-aktiv" style="font-size:11px;background:#d1fae5;color:#065f46;border-color:#6ee7b7">Filterbar</span>
        <?php endif; ?>

        <?php if (!empty($m['artikeltyp_ids'])): ?>
            <?php foreach ($artikeltypen as $at): ?>
                <?php if (in_array($at['id'], $m['artikeltyp_ids'])): ?>
                    <span class="chip" style="font-size:11px;background:#ede9fe;color:#5b21b6;border-color:#c4b5fd"><?= htmlspecialchars($at['name']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <span style="font-size:11px;color:var(--color-text-muted)">Alle Typen</span>
        <?php endif; ?>

        <div style="display:flex;gap:4px;margin-left:auto">
            <?php if ($mi > 0): ?>
                <button class="btn btn-secondary btn-xs" onclick="merkmalSort(<?= $m['id'] ?>, 'hoch')" title="Nach oben">▲</button>
            <?php endif; ?>
            <?php if ($mi < count($merkmale) - 1): ?>
                <button class="btn btn-secondary btn-xs" onclick="merkmalSort(<?= $m['id'] ?>, 'runter')" title="Nach unten">▼</button>
            <?php endif; ?>
            <button class="btn btn-secondary btn-xs" onclick="merkmalBearbeiten(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m)) ?>)">Bearb.</button>
            <button class="btn btn-danger btn-xs" onclick="merkmalLoeschen(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m['name'])) ?>)">Löschen</button>
        </div>
    </div>

    <!-- Werte -->
    <div style="margin-top:var(--space-sm);padding-top:var(--space-sm);border-top:1px solid var(--color-border)">
        <div id="werte-<?= $m['id'] ?>">
            <?php foreach ($m['werte'] as $wi => $w): ?>
            <div class="mw-zeile" id="wert-<?= $w['id'] ?>" style="display:flex;align-items:center;gap:var(--space-sm);padding:3px 0">
                <span style="font-size:13px;flex:1"><?= htmlspecialchars($w['wert']) ?></span>
                <div style="display:flex;gap:2px">
                    <?php if ($wi > 0): ?>
                        <button class="btn btn-secondary btn-xs" onclick="wertSort(<?= $w['id'] ?>, 'hoch')">▲</button>
                    <?php endif; ?>
                    <?php if ($wi < count($m['werte']) - 1): ?>
                        <button class="btn btn-secondary btn-xs" onclick="wertSort(<?= $w['id'] ?>, 'runter')">▼</button>
                    <?php endif; ?>
                    <button class="btn btn-secondary btn-xs" onclick="wertBearbeiten(<?= $w['id'] ?>, <?= htmlspecialchars(json_encode($w['wert'])) ?>)">✏️</button>
                    <button class="btn btn-danger btn-xs" onclick="wertLoeschen(<?= $w['id'] ?>, <?= htmlspecialchars(json_encode($w['wert'])) ?>)">✕</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:var(--space-sm);margin-top:var(--space-xs)">
            <input type="text" id="neu-wert-<?= $m['id'] ?>" class="erp-input" style="flex:1" placeholder="Neuer Wert...">
            <button class="btn btn-secondary btn-sm" onclick="wertNeu(<?= $m['id'] ?>)">+ Wert</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

</div>

<!-- Modal: Merkmal anlegen / bearbeiten -->
<div id="merkmal-backdrop" class="modal-backdrop" style="display:none" onclick="merkmalModalSchliessen()">
    <div class="modal" style="max-width:480px" onclick="event.stopPropagation()">
        <div class="modal-header">Merkmal</div>
        <input type="hidden" id="mf-id">

        <div class="form-group" style="margin-top:var(--space-sm)">
            <label class="form-label">Name *</label>
            <input type="text" id="mf-name" class="erp-input" style="width:100%" placeholder="z.B. Maschenprobe">
        </div>
        <div class="form-group">
            <label class="form-label">Slug (WooCommerce)</label>
            <input type="text" id="mf-slug" class="erp-input" style="width:100%" placeholder="z.B. maschenprobe">
            <span style="font-size:11px;color:var(--color-text-muted)">Wird als pa_{slug} in WooCommerce exportiert</span>
        </div>
        <div style="display:flex;gap:var(--space-md);margin-top:var(--space-sm)">
            <label class="form-check">
                <input type="checkbox" id="mf-mehrfach"> Mehrfach-Auswahl
            </label>
            <label class="form-check">
                <input type="checkbox" id="mf-filterbar"> Im Shop filterbar
            </label>
        </div>
        <div class="form-group" style="margin-top:var(--space-sm)">
            <label class="form-label">Nur für Artikeltypen (leer = alle)</label>
            <div id="mf-typen" style="display:flex;flex-wrap:wrap;gap:var(--space-xs)">
                <?php foreach ($artikeltypen as $at): ?>
                    <label class="form-check" style="margin:0">
                        <input type="checkbox" class="mf-typ-cb" value="<?= $at['id'] ?>">
                        <?= htmlspecialchars($at['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="mf-fehler" style="color:var(--color-danger);font-size:13px;min-height:18px"></div>
        <div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-sm)">
            <button class="btn btn-secondary" onclick="merkmalModalSchliessen()">Abbrechen</button>
            <button class="btn btn-primary" id="mf-btn-speichern" onclick="merkmalSpeichern()">Speichern</button>
        </div>
    </div>
</div>

<script>
function ajax(action, data) {
    return fetch('merkmal_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action, ...data})
    }).then(r => r.json());
}

function reload() { location.reload(); }

// ── Merkmal ──────────────────────────────────────────────────
function merkmalNeu() {
    document.getElementById('mf-id').value = '';
    document.getElementById('mf-name').value = '';
    document.getElementById('mf-slug').value = '';
    document.getElementById('mf-mehrfach').checked = false;
    document.getElementById('mf-filterbar').checked = false;
    document.querySelectorAll('.mf-typ-cb').forEach(cb => cb.checked = false);
    document.getElementById('mf-fehler').textContent = '';
    document.getElementById('merkmal-backdrop').style.display = 'flex';
    document.getElementById('mf-name').focus();
}

function merkmalBearbeiten(id, m) {
    document.getElementById('mf-id').value = id;
    document.getElementById('mf-name').value = m.name;
    document.getElementById('mf-slug').value = m.slug || '';
    document.getElementById('mf-mehrfach').checked = !!m.mehrfach_auswahl;
    document.getElementById('mf-filterbar').checked = !!m.filterbar;
    document.querySelectorAll('.mf-typ-cb').forEach(cb => {
        cb.checked = (m.artikeltyp_ids || []).includes(parseInt(cb.value));
    });
    document.getElementById('mf-fehler').textContent = '';
    document.getElementById('merkmal-backdrop').style.display = 'flex';
}

function merkmalModalSchliessen() {
    document.getElementById('merkmal-backdrop').style.display = 'none';
}

function merkmalSpeichern() {
    const id        = document.getElementById('mf-id').value;
    const name      = document.getElementById('mf-name').value.trim();
    const slug      = document.getElementById('mf-slug').value.trim();
    const mehrfach  = document.getElementById('mf-mehrfach').checked;
    const filterbar = document.getElementById('mf-filterbar').checked;
    const typIds    = [...document.querySelectorAll('.mf-typ-cb:checked')].map(cb => parseInt(cb.value));
    const fehlerEl  = document.getElementById('mf-fehler');

    if (!name) { fehlerEl.textContent = 'Name darf nicht leer sein'; return; }

    const action = id ? 'merkmal_bearbeiten' : 'merkmal_neu';
    const btn = document.getElementById('mf-btn-speichern');
    btn.disabled = true;

    ajax(action, {id: id ? parseInt(id) : undefined, name, slug, mehrfach_auswahl: mehrfach, filterbar, artikeltyp_ids: typIds})
        .then(d => {
            if (d.erfolg) { reload(); }
            else { fehlerEl.textContent = d.fehler || 'Fehler'; btn.disabled = false; }
        });
}

function merkmalLoeschen(id, name) {
    if (!confirm('Merkmal „' + name + '" und alle zugehörigen Werte löschen?')) return;
    ajax('merkmal_loeschen', {id}).then(d => { if (d.erfolg) reload(); else alert(d.fehler); });
}

function merkmalSort(id, richtung) {
    ajax('merkmal_sort', {id, richtung}).then(d => { if (d.erfolg) reload(); });
}

// ── Werte ─────────────────────────────────────────────────────
function wertNeu(merkmalId) {
    const input = document.getElementById('neu-wert-' + merkmalId);
    const wert  = input.value.trim();
    if (!wert) return;
    ajax('wert_neu', {merkmal_id: merkmalId, wert}).then(d => {
        if (d.erfolg) reload();
        else alert(d.fehler);
    });
}

function wertBearbeiten(id, wertAlt) {
    const neu = prompt('Wert bearbeiten:', wertAlt);
    if (neu === null || neu.trim() === '') return;
    ajax('wert_bearbeiten', {id, wert: neu.trim()}).then(d => {
        if (d.erfolg) reload(); else alert(d.fehler);
    });
}

function wertLoeschen(id, wert) {
    if (!confirm('Wert „' + wert + '" löschen?')) return;
    ajax('wert_loeschen', {id}).then(d => { if (d.erfolg) reload(); else alert(d.fehler); });
}

function wertSort(id, richtung) {
    ajax('wert_sort', {id, richtung}).then(d => { if (d.erfolg) reload(); });
}

// Auto-Slug aus Name generieren
document.getElementById('mf-name').addEventListener('input', function() {
    const slugField = document.getElementById('mf-slug');
    if (!slugField.dataset.manuell) {
        slugField.value = this.value.toLowerCase()
            .replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss')
            .replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
    }
});
document.getElementById('mf-slug').addEventListener('input', function() {
    this.dataset.manuell = this.value ? '1' : '';
});
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
