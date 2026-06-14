<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$service        = new ArtikelService();
$kategorienBaum = $service->getKategorienBaum();

$pageTitle    = 'Kategorien verwalten';
$activeModule = 'artikel';

$actionBarContent = <<<HTML
<button onclick="katNeuOeffnen(null, null)" class="btn btn-primary btn-sm">+ Neue Hauptkategorie</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

function renderVerwaltungsKnoten(array $knoten, int $tiefe): void
{
    $einzug  = $tiefe * 24;
    $istBlatt = empty($knoten['kinder']);
    $anzahl  = (int)($knoten['artikel_anzahl'] ?? 0);
    ?>
    <div class="katv-zeile" data-id="<?= $knoten['id'] ?>" data-name="<?= htmlspecialchars($knoten['name']) ?>"
         data-parent="<?= $knoten['parent_id'] ?? '' ?>" style="padding-left:<?= 16 + $einzug ?>px">

        <span class="katv-toggle <?= $istBlatt ? 'katv-leer' : '' ?>"
              onclick="this.closest('.katv-gruppe').querySelector('.katv-kinder')?.classList.toggle('versteckt');this.textContent=this.textContent==='▶'?'▼':'▶'">
            <?= $istBlatt ? '' : '▼' ?>
        </span>

        <span class="katv-name"><?= htmlspecialchars($knoten['name']) ?></span>

        <?php if ($anzahl > 0): ?>
            <span class="katv-anzahl"><?= $anzahl ?> Artikel</span>
        <?php endif; ?>

        <div class="katv-aktionen">
            <button class="btn btn-secondary btn-xs"
                    onclick="katNeuOeffnen(<?= $knoten['id'] ?>, <?= htmlspecialchars(json_encode($knoten['name'])) ?>)"
                    title="Neue Unterkategorie anlegen">+ Unter-Kat.</button>
            <button class="btn btn-secondary btn-xs"
                    onclick="katBearbeiten(<?= $knoten['id'] ?>, <?= htmlspecialchars(json_encode($knoten['name'])) ?>, <?= $knoten['parent_id'] ?? 'null' ?>)"
                    title="Name oder Oberkategorie ändern">Bearb.</button>
            <button class="btn btn-danger btn-xs"
                    onclick="katLoeschenVorschau(<?= $knoten['id'] ?>, <?= htmlspecialchars(json_encode($knoten['name'])) ?>)"
                    title="Kategorie löschen">Löschen</button>
        </div>
    </div>

    <?php if (!$istBlatt): ?>
        <div class="katv-kinder">
            <?php foreach ($knoten['kinder'] as $kind): ?>
                <div class="katv-gruppe">
                    <?php renderVerwaltungsKnoten($kind, $tiefe + 1); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
}

// Flache Liste für Dropdowns (Bearbeiten / Neu)
function flattenBaum(array $baum, int $tiefe = 0): array
{
    $result = [];
    foreach ($baum as $k) {
        $result[] = ['id' => $k['id'], 'name' => $k['name'], 'tiefe' => $tiefe];
        if (!empty($k['kinder'])) {
            $result = array_merge($result, flattenBaum($k['kinder'], $tiefe + 1));
        }
    }
    return $result;
}
$flacheListe = flattenBaum($kategorienBaum);
?>

<div class="card">
    <?php if (empty($kategorienBaum)): ?>
        <p style="color:var(--color-text-muted);padding:var(--space-md)">
            Noch keine Kategorien angelegt.
            <button onclick="katNeuOeffnen(null)" class="btn btn-primary btn-sm" style="margin-left:8px">Erste Kategorie anlegen</button>
        </p>
    <?php else: ?>
        <div class="katv-baum">
            <?php foreach ($kategorienBaum as $wurzel): ?>
                <div class="katv-gruppe">
                    <?php renderVerwaltungsKnoten($wurzel, 0); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ── Neu/Bearbeiten Modal ───────────────────────────────────────── -->
<div id="katv-modal" class="modal-backdrop">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span id="katv-modal-titel">Neue Kategorie</span>
            <button onclick="katvModalSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="katv-edit-id" value="">
            <div class="form-row">
                <label class="form-label">Name *</label>
                <input id="katv-name" type="text" class="erp-input" style="width:100%"
                       onkeydown="if(event.key==='Enter')katvSpeichern()">
            </div>
            <div class="form-row">
                <label class="form-label">Oberkategorie</label>
                <select id="katv-parent" class="erp-select" style="width:100%">
                    <option value="">– Keine (Hauptkategorie) –</option>
                    <?php foreach ($flacheListe as $f): ?>
                        <option value="<?= $f['id'] ?>">
                            <?= str_repeat('  ', $f['tiefe']) . ($f['tiefe'] > 0 ? '↳ ' : '') . htmlspecialchars($f['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="katv-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px"></div>
        </div>
        <div class="modal-footer">
            <button onclick="katvModalSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button onclick="katvSpeichern()" class="btn btn-primary btn-sm">Speichern</button>
        </div>
    </div>
</div>

<!-- ── Löschen-Bestätigungs Modal ───────────────────────────────── -->
<div id="katv-del-modal" class="modal-backdrop">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <span>Kategorie löschen</span>
            <button onclick="katvDelSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <p id="katv-del-info" style="margin-bottom:var(--space-sm)"></p>
            <div id="katv-del-warnungen"></div>
            <div id="katv-del-optionen" style="display:none;margin-top:12px;padding:10px;background:#f8f9fa;border-radius:4px;border:1px solid var(--color-border)">
                <div style="font-size:12.5px;font-weight:600;margin-bottom:8px">Was soll mit den Artikeln passieren?</div>
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:12.5px;margin-bottom:8px;cursor:pointer">
                    <input type="radio" name="katv-del-modus" value="verschieben" checked style="margin-top:2px">
                    <span>Artikel in Oberkategorie <strong id="katv-del-parent-name"></strong> verschieben</span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:12.5px;cursor:pointer">
                    <input type="radio" name="katv-del-modus" value="entfernen" style="margin-top:2px">
                    <span>Zuweisung nur entfernen <span style="color:var(--color-text-muted)">(Artikel können kategorielos werden)</span></span>
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="katvDelSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button id="katv-del-btn" onclick="katvLoeschenBestaetigt()" class="btn btn-danger btn-sm">Löschen</button>
        </div>
    </div>
</div>

<style>
.katv-baum { padding: var(--space-sm) 0; }

.katv-gruppe { border-bottom: 1px solid var(--color-border); }
.katv-gruppe:last-child { border-bottom: none; }

.katv-zeile {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding-top: 7px;
    padding-bottom: 7px;
    padding-right: var(--space-md);
}
.katv-zeile:hover { background: var(--color-bg); }

.katv-toggle {
    font-size: 9px;
    color: var(--color-text-muted);
    cursor: pointer;
    width: 14px;
    flex-shrink: 0;
    user-select: none;
}
.katv-leer { cursor: default; }

.katv-name {
    font-size: 13.5px;
    font-weight: 500;
    flex: 1;
}

.katv-anzahl {
    font-size: 11px;
    color: var(--color-text-muted);
    background: #f0f2f5;
    border-radius: 10px;
    padding: 1px 7px;
    flex-shrink: 0;
}

.katv-aktionen {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
    margin-left: auto;
}

.katv-kinder { border-left: 2px solid var(--color-border); margin-left: 28px; }
</style>

<script>
var katvLoeschId       = null;
var katvLoeschParentId = null;

// ── Neu/Bearbeiten ──────────────────────────────────────────────────
function katNeuOeffnen(parentId, parentName) {
    var titel = parentName ? 'Neue Unterkategorie unter "' + parentName + '"' : 'Neue Hauptkategorie';
    document.getElementById('katv-modal-titel').textContent = titel;
    document.getElementById('katv-edit-id').value = '';
    document.getElementById('katv-name').value = '';
    document.getElementById('katv-parent').value = parentId ?? '';
    document.getElementById('katv-fehler').textContent = '';
    document.getElementById('katv-modal').style.display = 'flex';
    setTimeout(function() { document.getElementById('katv-name').focus(); }, 50);
}

function katBearbeiten(id, name, parentId) {
    document.getElementById('katv-modal-titel').textContent = 'Kategorie bearbeiten';
    document.getElementById('katv-edit-id').value = id;
    document.getElementById('katv-name').value = name;
    document.getElementById('katv-parent').value = parentId ?? '';
    document.getElementById('katv-fehler').textContent = '';
    // Eigene Option deaktivieren (kann nicht Elternteil von sich selbst sein)
    document.querySelectorAll('#katv-parent option').forEach(function(o) {
        o.disabled = (parseInt(o.value) === id);
    });
    document.getElementById('katv-modal').style.display = 'flex';
    setTimeout(function() { document.getElementById('katv-name').focus(); }, 50);
}

function katvModalSchliessen() {
    document.getElementById('katv-modal').style.display = 'none';
    // Optionen wieder aktivieren
    document.querySelectorAll('#katv-parent option').forEach(function(o) { o.disabled = false; });
}

function katvSpeichern() {
    var editId   = document.getElementById('katv-edit-id').value;
    var name     = document.getElementById('katv-name').value.trim();
    var parentId = document.getElementById('katv-parent').value;
    var fehler   = document.getElementById('katv-fehler');

    if (!name) { fehler.textContent = 'Name ist Pflichtfeld'; return; }
    fehler.textContent = '';

    var url    = editId ? '/mealana/artikel/kategorie_bearbeiten_ajax.php' : '/mealana/artikel/kategorie_erstellen.php';
    var body   = 'name=' + encodeURIComponent(name) + '&parent_id=' + encodeURIComponent(parentId);
    if (editId) body += '&id=' + encodeURIComponent(editId);

    fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.erfolg) { window.location.reload(); }
            else { fehler.textContent = d.fehler || 'Fehler beim Speichern'; }
        });
}

// ── Löschen ──────────────────────────────────────────────────────────
function katLoeschenVorschau(id, name) {
    katvLoeschId       = id;
    katvLoeschParentId = null;
    document.getElementById('katv-del-info').textContent = 'Kategorie "' + name + '" wird gelöscht. Bitte warten…';
    document.getElementById('katv-del-warnungen').innerHTML = '';
    document.getElementById('katv-del-optionen').style.display = 'none';
    document.getElementById('katv-del-btn').disabled = true;
    document.getElementById('katv-del-modal').style.display = 'flex';

    fetch('/mealana/artikel/kategorie_loeschen_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&aktion=vorschau'
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        var warnHTML = '';
        if (d.kinder_anzahl > 0) {
            warnHTML += '<div style="background:#fff8e1;border:1px solid #f0c000;border-radius:4px;padding:8px 10px;margin-bottom:8px;font-size:12.5px">'
                + '⚠ Diese Kategorie hat <strong>' + d.kinder_anzahl + ' Unterkategorie(n)</strong> — diese werden ebenfalls gelöscht.</div>';
        }
        if (d.artikel_ohne_kat && d.artikel_ohne_kat.length > 0) {
            warnHTML += '<div style="background:#fff0f0;border:1px solid #f5b8b8;border-radius:4px;padding:8px 10px;font-size:12.5px">'
                + '<strong style="color:var(--color-danger)">⚠ ' + d.artikel_ohne_kat.length + ' Artikel würden dadurch kategorielos:</strong>'
                + '<div style="margin-top:6px;max-height:100px;overflow-y:auto">';
            d.artikel_ohne_kat.forEach(function(a) {
                warnHTML += '<div style="font-size:12px;padding:2px 0;border-bottom:1px solid #f5b8b8">'
                    + '<strong>' + a.artikelnummer + '</strong> – ' + a.name + '</div>';
            });
            warnHTML += '</div></div>';
        }
        document.getElementById('katv-del-info').textContent = warnHTML
            ? 'Kategorie "' + name + '" löschen – bitte Warnungen beachten:'
            : 'Kategorie "' + name + '" löschen?';
        document.getElementById('katv-del-warnungen').innerHTML = warnHTML;

        // Radio-Optionen zeigen wenn ein Elternteil existiert
        if (d.parent) {
            katvLoeschParentId = d.parent.id;
            document.getElementById('katv-del-parent-name').textContent = '"' + d.parent.name + '"';
            document.getElementById('katv-del-optionen').style.display = '';
            // Radio auf "verschieben" zurücksetzen
            var radVers = document.querySelector('input[name="katv-del-modus"][value="verschieben"]');
            if (radVers) radVers.checked = true;
        }

        document.getElementById('katv-del-btn').disabled = false;
    });
}

function katvDelSchliessen() {
    document.getElementById('katv-del-modal').style.display = 'none';
    katvLoeschId       = null;
    katvLoeschParentId = null;
}

function katvLoeschenBestaetigt() {
    if (!katvLoeschId) return;
    document.getElementById('katv-del-btn').disabled = true;

    var modus = document.querySelector('input[name="katv-del-modus"]:checked');
    var verschiebeId = '';
    if (modus && modus.value === 'verschieben' && katvLoeschParentId) {
        verschiebeId = katvLoeschParentId;
    }

    fetch('/mealana/artikel/kategorie_loeschen_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + katvLoeschId + '&aktion=loeschen&verschiebe_zu_parent_id=' + verschiebeId
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.erfolg) { window.location.reload(); }
        else { alert(d.fehler || 'Fehler beim Löschen'); katvDelSchliessen(); }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
