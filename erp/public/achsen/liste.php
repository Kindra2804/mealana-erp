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

$actionBarContent = '<a href="/mealana/achsen/neu.php" class="btn btn-primary btn-sm">+ Neue Achse</a>';

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
    <?php if (empty($achsen)): ?>
        <p style="color:var(--color-text-muted);padding:var(--space-md)">
            Noch keine Achsen angelegt.
            <a href="/mealana/achsen/neu.php" class="btn btn-primary btn-sm" style="margin-left:8px">Erste Achse anlegen</a>
        </p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Darstellungsform</th>
                    <th style="width:72px;text-align:center">Reihenfolge</th>
                    <th style="width:100px"></th>
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
                            <a href="/mealana/achsen/bearbeiten.php?id=<?= $a['id'] ?>"
                               class="btn btn-secondary btn-xs">Bearb.</a>
                            <button onclick="achseLoeschen(<?= $a['id'] ?>, <?= htmlspecialchars(json_encode($a['name'])) ?>)"
                                    class="btn btn-danger btn-xs">Löschen</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
var delAchseId = null;

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
