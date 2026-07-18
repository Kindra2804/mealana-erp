<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';

$service = new InventurService();
$fehler  = $_SESSION['fehler'] ?? [];
unset($_SESSION['fehler']);

$alleLager        = $service->getAlleLagerFuerAuswahl();
$alleLagerplaetze = $service->getAlleLagerplaetzeFuerAuswahl();
$alleKategorien   = $service->getAlleKategorienFuerAuswahl();
$alleMietfaecher  = $service->getAlleMietfaecherFuerAuswahl();

$pageTitle        = 'Neue Inventur starten';
$activeModule     = 'lager';
$actionBarContent = '<a href="' . BASE_PATH . '/inventur/liste.php" class="btn btn-secondary btn-sm">← Liste</a>';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if (!empty($fehler)): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(implode(', ', $fehler)) ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:560px">
    <form method="post" action="<?= BASE_PATH ?>/inventur/starten.php">
        <div style="margin-bottom:14px">
            <label class="erp-label">Was soll gezählt werden? *</label>
            <select name="scope_tabelle" id="scope_tabelle" class="erp-select" style="width:100%" onchange="scopeWechsel()">
                <option value="lager">Ganzes Lager</option>
                <option value="lagerplaetze">Ein Lagerplatz</option>
                <option value="kategorien">Eine Kategorie</option>
                <option value="artikel">Ein einzelner Artikel</option>
                <option value="mietfaecher">Ein Mietfach</option>
            </select>
        </div>

        <div id="scope-lager" class="scope-feld">
            <label class="erp-label">Lager *</label>
            <select name="scope_id_lager" class="erp-select" style="width:100%">
                <?php foreach ($alleLager as $l): ?>
                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="scope-lagerplaetze" class="scope-feld" style="display:none">
            <label class="erp-label">Lagerplatz *</label>
            <select name="scope_id_lagerplaetze" class="erp-select" style="width:100%">
                <?php foreach ($alleLagerplaetze as $lp): ?>
                <option value="<?= $lp['id'] ?>"><?= htmlspecialchars($lp['bezeichnung']) ?> (<?= htmlspecialchars($lp['lager_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="scope-kategorien" class="scope-feld" style="display:none">
            <label class="erp-label">Kategorie *</label>
            <select name="scope_id_kategorien" class="erp-select" style="width:100%">
                <?php foreach ($alleKategorien as $k): ?>
                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="scope-artikel" class="scope-feld" style="display:none">
            <label class="erp-label">Artikel *</label>
            <input type="text" id="artikel_suche" class="erp-input" style="width:100%" placeholder="Name oder Artikelnummer eingeben...">
            <input type="hidden" name="scope_id_artikel" id="scope_id_artikel">
            <div id="artikel_treffer" style="border:1px solid var(--color-border);border-radius:4px;margin-top:4px;max-height:180px;overflow-y:auto;display:none"></div>
            <div id="artikel_gewaehlt" style="margin-top:6px;font-size:13px;color:var(--color-success)"></div>
        </div>

        <div id="scope-mietfaecher" class="scope-feld" style="display:none">
            <label class="erp-label">Mietfach *</label>
            <select name="scope_id_mietfaecher" class="erp-select" style="width:100%">
                <?php foreach ($alleMietfaecher as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['fach_bezeichnung']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin:14px 0;display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="blind_modus" id="blind_modus" value="1" checked>
            <label for="blind_modus" style="cursor:pointer;font-size:13px">Blind zählen (Soll-Bestand für den Zähler ausblenden)</label>
        </div>

        <div style="margin-bottom:14px">
            <label class="erp-label">Notiz (optional)</label>
            <input type="text" name="notiz" class="erp-input" style="width:100%">
        </div>

        <button type="submit" class="btn btn-primary btn-sm">Inventur starten</button>
    </form>
</div>

<script>
function scopeWechsel() {
    var gewaehlt = document.getElementById('scope_tabelle').value;
    document.querySelectorAll('.scope-feld').forEach(function (el) { el.style.display = 'none'; });
    document.getElementById('scope-' + gewaehlt).style.display = 'block';
}

(function () {
    var suchfeld = document.getElementById('artikel_suche');
    var treffer   = document.getElementById('artikel_treffer');
    var hiddenId  = document.getElementById('scope_id_artikel');
    var gewaehlt  = document.getElementById('artikel_gewaehlt');
    var timer;

    suchfeld.addEventListener('input', function () {
        clearTimeout(timer);
        hiddenId.value = '';
        gewaehlt.textContent = '';
        var q = suchfeld.value.trim();
        if (q.length < 2) { treffer.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch(window.BASE_PATH + '/inventur/artikel_suche_ajax.php?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    treffer.innerHTML = '';
                    data.forEach(function (a) {
                        var div = document.createElement('div');
                        div.style.cssText = 'padding:6px 10px;cursor:pointer;font-size:13px';
                        div.textContent = a.name + ' (' + a.artikelnummer + ')';
                        div.onclick = function () {
                            hiddenId.value = a.id;
                            gewaehlt.textContent = 'Gewählt: ' + a.name + ' (' + a.artikelnummer + ')';
                            suchfeld.value = a.name;
                            treffer.style.display = 'none';
                        };
                        treffer.appendChild(div);
                    });
                    treffer.style.display = data.length ? 'block' : 'none';
                });
        }, 250);
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
