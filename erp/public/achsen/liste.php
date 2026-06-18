<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';

$service = new AchsenService();
$achsen  = $service->findAll();

$flash        = $_SESSION['erfolg'] ?? null;
$flashFehler  = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$pageTitle    = 'Variantenachsen';
$activeModule = 'artikel';

$actionBarContent = '<button onclick="achseNeuOeffnen()" class="btn btn-primary btn-sm">+ Neue Achse</button>';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($flash): ?>
<div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;padding:8px 12px;margin-bottom:var(--space-md);color:#155724;font-size:13px">
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>
<?php if ($flashFehler): ?>
<div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;padding:8px 12px;margin-bottom:var(--space-md);color:#721c24;font-size:13px">
    <?= is_array($flashFehler) ? implode(', ', array_map('htmlspecialchars', $flashFehler)) : htmlspecialchars($flashFehler) ?>
</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-md)">
        <h3 style="margin:0">Achsen</h3>
        <button onclick="achseNeuOeffnen()" class="btn btn-primary btn-sm">+ Neue Achse</button>
    </div>

    <?php if (empty($achsen)): ?>
        <p style="color:var(--color-text-muted)">Noch keine Achsen angelegt.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Darstellung</th>
                    <th>Abhängig von</th>
                    <th style="width:72px;text-align:center">Sort.</th>
                    <th style="width:110px"></th>
                </tr>
            </thead>
            <tbody>
                <?php $n = count($achsen); foreach ($achsen as $i => $a): ?>
                <tr class="artikel-zeile">
                    <td style="font-weight:600"><?= htmlspecialchars($a['name']) ?></td>
                    <td style="font-family:monospace;font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($a['code']) ?></td>
                    <td style="font-size:12px">
                        <span style="background:#EDF2F7;color:#4A5568;border-radius:10px;padding:2px 8px;font-size:11px">
                            <?= htmlspecialchars($a['darstellungsform']) ?>
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--color-text-muted)">
                        <?php if ($a['abhaengig_von_name']): ?>
                            <span style="background:#ede9fe;color:#5b21b6;border-radius:10px;padding:2px 8px;font-size:11px">
                                <?= htmlspecialchars($a['abhaengig_von_name']) ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;white-space:nowrap">
                        <?php if ($i > 0): ?>
                            <button onclick="achseSortieren(<?= $a['id'] ?>, 'hoch')"
                                    class="btn btn-secondary btn-xs" title="Nach oben">▲</button>
                        <?php endif; ?>
                        <?php if ($i < $n - 1): ?>
                            <button onclick="achseSortieren(<?= $a['id'] ?>, 'runter')"
                                    class="btn btn-secondary btn-xs" title="Nach unten">▼</button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-aktionen">
                            <button onclick="achseBearbeitenOeffnen(<?= $a['id'] ?>, <?= htmlspecialchars(json_encode($a['name'])) ?>, <?= htmlspecialchars(json_encode($a['code'])) ?>, <?= htmlspecialchars(json_encode($a['darstellungsform'])) ?>, <?= (int)$a['sort_order'] ?>, <?= $a['abhaengig_von_achse_id'] ?? 'null' ?>)"
                                    class="btn btn-secondary btn-xs">Bearb.</button>
                            <?php if ($a['in_use']): ?>
                                <span title="Achse ist Artikeln zugewiesen – kann nicht gelöscht werden"
                                      style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:24px;border-radius:4px;background:#f1f5f9;color:#94a3b8;font-size:13px;cursor:default">🔒</span>
                            <?php else: ?>
                                <button onclick="achseLoeschen(<?= $a['id'] ?>, <?= htmlspecialchars(json_encode($a['name'])) ?>)"
                                        class="btn btn-danger btn-xs">Löschen</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Bearbeiten/Neu-Modal -->
<div id="edit-modal" class="modal-backdrop">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <span id="edit-modal-titel">Neue Achse</span>
            <button onclick="editSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-id" value="0">

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">
                    Name <span style="color:var(--color-danger)">*</span>
                </label>
                <input type="text" id="edit-name" class="erp-input" style="width:100%" placeholder="z.B. Farbe">
            </div>

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">
                    Code <span style="color:var(--color-danger)">*</span>
                </label>
                <input type="text" id="edit-code" class="erp-input" style="width:100%;font-family:monospace" placeholder="z.B. farbe">
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px">Kleinbuchstaben, kein Leerzeichen. Wird automatisch bereinigt.</div>
            </div>

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Darstellungsform</label>
                <select id="edit-darstellung" class="erp-select" style="width:100%">
                    <option value="swatches">swatches</option>
                    <option value="dropdown">dropdown</option>
                    <option value="radiobutton">radiobutton</option>
                    <option value="freitext">freitext</option>
                    <option value="pflichtfreitext">pflicht-freitext</option>
                </select>
            </div>

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Abhängig von Achse</label>
                <select id="edit-abhaengig" class="erp-select" style="width:100%">
                    <option value="">— keine Abhängigkeit —</option>
                    <?php foreach ($achsen as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px">
                    Werte dieser Achse werden pro Wert der Eltern-Achse gefiltert (z.B. Farbe abhängig von Typ)
                </div>
            </div>

            <div style="margin-bottom:var(--space-sm)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Reihenfolge</label>
                <input type="number" id="edit-sort" class="erp-input" style="width:80px" min="0" step="1" value="0">
            </div>

            <div id="edit-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:var(--space-sm)"></div>
        </div>
        <div class="modal-footer">
            <button onclick="editSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button id="edit-btn" onclick="editAbsenden()" class="btn btn-primary btn-sm">Speichern</button>
        </div>
    </div>
</div>

<!-- Löschen-Modal -->
<div id="del-modal" class="modal-backdrop">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span>Achse löschen</span>
            <button onclick="delSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <p>Achse <strong id="del-name"></strong> wirklich löschen?</p>
            <p style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
                Eine Achse kann nur gelöscht werden wenn sie keinem Artikel zugewiesen ist.
            </p>
            <div id="del-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:6px"></div>
        </div>
        <div class="modal-footer">
            <button onclick="delSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button id="del-btn" onclick="delBestaetigt()" class="btn btn-danger btn-sm">Löschen</button>
        </div>
    </div>
</div>

<script>
// ── Sortieren ──────────────────────────────────────────────────────────────
function achseSortieren(id, richtung) {
    fetch('/mealana/achsen/sort_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, richtung: richtung})
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.erfolg) { window.location.reload(); }
        else { alert(d.fehler || 'Fehler beim Sortieren'); }
    });
}

// ── Bearbeiten/Neu Modal ───────────────────────────────────────────────────
function achseNeuOeffnen() {
    document.getElementById('edit-modal-titel').textContent = 'Neue Achse';
    document.getElementById('edit-id').value = '0';
    document.getElementById('edit-name').value = '';
    document.getElementById('edit-code').value = '';
    document.getElementById('edit-darstellung').value = 'swatches';
    document.getElementById('edit-abhaengig').value = '';
    document.getElementById('edit-sort').value = '0';
    document.getElementById('edit-fehler').textContent = '';
    document.getElementById('edit-btn').disabled = false;

    // Selbst-Option ausblenden hat bei Neu keine Bedeutung — alle Optionen sichtbar
    Array.from(document.getElementById('edit-abhaengig').options).forEach(function(o) {
        o.hidden = false;
    });

    document.getElementById('edit-modal').style.display = 'flex';
    document.getElementById('edit-name').focus();
}

function achseBearbeitenOeffnen(id, name, code, darstellung, sort, abhaengigId) {
    document.getElementById('edit-modal-titel').textContent = 'Achse bearbeiten';
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-code').value = code;
    document.getElementById('edit-darstellung').value = darstellung;
    document.getElementById('edit-abhaengig').value = abhaengigId || '';
    document.getElementById('edit-sort').value = sort;
    document.getElementById('edit-fehler').textContent = '';
    document.getElementById('edit-btn').disabled = false;

    // Achse kann nicht von sich selbst abhängen — eigene Option verstecken
    Array.from(document.getElementById('edit-abhaengig').options).forEach(function(o) {
        o.hidden = (o.value !== '' && parseInt(o.value) === id);
    });

    document.getElementById('edit-modal').style.display = 'flex';
    document.getElementById('edit-name').focus();
}

function editSchliessen() {
    document.getElementById('edit-modal').style.display = 'none';
}

function editAbsenden() {
    var id        = parseInt(document.getElementById('edit-id').value);
    var name      = document.getElementById('edit-name').value.trim();
    var code      = document.getElementById('edit-code').value.trim();
    var darstl    = document.getElementById('edit-darstellung').value;
    var abhaengig = document.getElementById('edit-abhaengig').value;
    var sort      = parseInt(document.getElementById('edit-sort').value) || 0;

    document.getElementById('edit-fehler').textContent = '';

    if (!name) {
        document.getElementById('edit-fehler').textContent = 'Name ist Pflichtfeld';
        document.getElementById('edit-name').focus();
        return;
    }
    if (!code) {
        document.getElementById('edit-fehler').textContent = 'Code ist Pflichtfeld';
        document.getElementById('edit-code').focus();
        return;
    }

    var url = id > 0 ? '/mealana/achsen/achse_aktualisieren_ajax.php' : '/mealana/achsen/achse_speichern_ajax.php';

    var body = new FormData();
    if (id > 0) body.append('id', id);
    body.append('name', name);
    body.append('code', code);
    body.append('darstellungsform', darstl);
    body.append('abhaengig_von_achse_id', abhaengig);
    body.append('sort_order', sort);

    document.getElementById('edit-btn').disabled = true;

    fetch(url, {method: 'POST', body: body})
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.erfolg) {
            window.location.reload();
        } else {
            var fehler = Array.isArray(d.fehler) ? d.fehler.join(', ') : (d.fehler || 'Unbekannter Fehler');
            document.getElementById('edit-fehler').textContent = fehler;
            document.getElementById('edit-btn').disabled = false;
        }
    })
    .catch(function() {
        document.getElementById('edit-fehler').textContent = 'Serverfehler, bitte nochmal versuchen.';
        document.getElementById('edit-btn').disabled = false;
    });
}

// Code-Feld automatisch bereinigen beim Verlassen
document.getElementById('edit-code').addEventListener('blur', function() {
    this.value = this.value.toLowerCase().replace(/\s+/g, '_');
});

// ── Löschen Modal ──────────────────────────────────────────────────────────
var delAchseId = null;

function achseLoeschen(id, name) {
    delAchseId = id;
    document.getElementById('del-name').textContent = name;
    document.getElementById('del-fehler').textContent = '';
    document.getElementById('del-btn').disabled = false;
    document.getElementById('del-modal').style.display = 'flex';
}

function delSchliessen() {
    document.getElementById('del-modal').style.display = 'none';
    delAchseId = null;
}

function delBestaetigt() {
    if (!delAchseId) return;
    document.getElementById('del-btn').disabled = true;

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/mealana/achsen/loeschen.php';
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'id';
    input.value = delAchseId;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
