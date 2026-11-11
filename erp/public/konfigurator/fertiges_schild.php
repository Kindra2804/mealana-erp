<?php
/**
 * Fertiges Schild anlegen/einbuchen (Migration 232): Werte am Layout wählen -> Artikelnummer aus den
 * Kürzeln, gleiche Kombination = derselbe Artikel (nur einbuchen). Logik: FertigesSchildService.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/konfigurator/FertigesSchildService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$service  = new FertigesSchildService();
$layoutId = (int) ($_GET['layout'] ?? $_POST['layout'] ?? 0);

// --- Vorschau (AJAX) ---
if (($_POST['aktion'] ?? '') === 'vorschau') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($service->vorschau($layoutId, $_POST['wert'] ?? []), JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Anlegen / Einbuchen ---
if (($_POST['aktion'] ?? '') === 'anlegen') {
    try {
        $erg = $service->anlegen($layoutId, $_POST['wert'] ?? [], (float) str_replace(',', '.', $_POST['menge'] ?? '0'),
            (int) ($_POST['lager_id'] ?? 0), (int) ($_POST['kategorie_id'] ?? 0), (int) ($_SESSION['benutzer']['id'] ?? 0));
        $_SESSION['erfolg'] = ($erg['neu'] ? 'Fertiges Schild ' . $erg['artikelnummer'] . ' angelegt' : 'Vorhandenes Schild ' . $erg['artikelnummer'] . ' gefunden')
            . ($erg['menge'] > 0 ? ' und ' . rtrim(rtrim(number_format($erg['menge'], 3, ',', ''), '0'), ',') . ' Stk. eingebucht.' : '.');
        $_SESSION['fertig_letzte_auswahl'] = $_POST['wert'] ?? [];
    } catch (Throwable $e) {
        $_SESSION['fehler'] = [$e->getMessage()];
        $_SESSION['fertig_letzte_auswahl'] = $_POST['wert'] ?? [];
    }
    header('Location: fertiges_schild.php?layout=' . $layoutId);
    exit;
}

$layout = (new ArtikelRepository())->findById($layoutId);
if (!$layout || empty($layout['ist_konfigurierbar'])) {
    http_response_code(404);
    exit('Kein konfigurierbarer Artikel.');
}

$fehler  = $_SESSION['fehler'] ?? [];
$erfolg  = $_SESSION['erfolg'] ?? null;
$auswahl = array_map('intval', $_SESSION['fertig_letzte_auswahl'] ?? []);
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['fertig_letzte_auswahl']);

$konfiguration = (new KonfiguratorService())->getKonfiguration($layoutId);
$vorhandene    = $service->vorhandene($layoutId);
$db            = Database::getInstance();
$lager         = $db->query("SELECT id, name FROM lager WHERE lager_beziehung = 'eigen' AND COALESCE(aktiv, 1) = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$kategorienBaum = (new ArtikelService())->getKategorienBaum();
$zielKat       = $service->zielKategorieId();

function fsKategorieOptionen(array $knoten, int $gewaehlt, int $tiefe = 0): string
{
    $html = '';
    foreach ($knoten as $k) {
        $html .= '<option value="' . (int) $k['id'] . '"' . ((int) $k['id'] === $gewaehlt ? ' selected' : '') . '>'
            . htmlspecialchars(str_repeat('— ', $tiefe) . $k['name']) . '</option>';
        if (!empty($k['kinder'])) $html .= fsKategorieOptionen($k['kinder'], $gewaehlt, $tiefe + 1);
    }
    return $html;
}

$pageTitle    = 'Fertiges Schild';
$activeModule = 'artikel';
$basePath     = BASE_PATH;
$actionBarContent = '<a href="' . $basePath . '/artikel/detail.php?id=' . $layoutId . '" class="btn btn-secondary btn-sm">← ' . htmlspecialchars($layout['artikelnummer']) . '</a>'
    . ' <a href="' . $basePath . '/konfigurator/bausteine.php" class="btn btn-secondary btn-sm">🧩 Schild-Bausteine</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
    <div class="card" style="border-left:4px solid var(--color-danger);margin-bottom:12px">
        <ul style="margin:0;padding-left:18px;color:var(--color-danger)"><?php foreach ((array) $fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php if ($erfolg): ?>
    <div class="card" style="border-left:4px solid var(--color-success);margin-bottom:12px"><p style="margin:0;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></p></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(320px,1fr);gap:12px;align-items:start">
<form method="POST" id="fs-form" class="card">
    <input type="hidden" name="layout" value="<?= $layoutId ?>">
    <input type="hidden" name="aktion" value="anlegen">
    <h3 style="margin:0 0 4px"><?= htmlspecialchars($layout['artikelnummer'] . ' ' . $layout['name']) ?></h3>
    <p style="margin:0 0 12px;font-size:13px;color:var(--color-text-muted)">Werte des gebauten Schilds wählen. Gibt es die Kombination schon, wird nur eingebucht.</p>

    <?php foreach ($konfiguration as $achse): ?>
        <div class="fs-achse" data-achse="<?= (int) $achse['achse_id'] ?>"
             <?php if ($achse['bedingung']): ?>data-bed-achse="<?= (int) $achse['bedingung']['achse_id'] ?>" data-bed-wert="<?= (int) $achse['bedingung']['wert_id'] ?>"<?php endif; ?>
             style="margin-bottom:10px">
            <label class="erp-label"><?= htmlspecialchars($achse['name']) ?></label>
            <select name="wert[<?= (int) $achse['achse_id'] ?>]" class="erp-select fs-wert" style="width:100%">
                <option value="">– wählen –</option>
                <?php foreach ($achse['werte'] as $w): ?>
                    <option value="<?= (int) $w['wert_id'] ?>" <?= ($auswahl[$achse['achse_id']] ?? 0) === (int) $w['wert_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($w['wert'] . ($w['verfuegbar'] === false ? ' (Material leer)' : '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>

    <div style="display:grid;grid-template-columns:100px 1fr;gap:10px;margin-top:14px">
        <div><label class="erp-label">Menge</label><input type="number" name="menge" value="1" min="0" step="1" class="erp-select" style="width:100%"></div>
        <div><label class="erp-label">Lager</label>
            <select name="lager_id" class="erp-select" style="width:100%"><?php foreach ($lager as $l): ?><option value="<?= (int) $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div style="margin-top:10px"><label class="erp-label">Kategorie für neue fertige Schilder</label>
        <select name="kategorie_id" class="erp-select" style="width:100%"><option value="">– wählen –</option><?= fsKategorieOptionen($kategorienBaum, $zielKat) ?></select>
        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px">Wird gemerkt. Shop-Kanäle übernimmt das Schild vom Layout.</div></div>

    <div id="fs-vorschau" style="margin:14px 0;padding:10px 12px;border-radius:8px;background:var(--color-bg-subtle, #f8fafc);font-size:13px">Werte wählen …</div>
    <button class="btn btn-primary btn-sm" id="fs-knopf" disabled>Anlegen &amp; einbuchen</button>
</form>

<div class="card">
    <h3 style="margin:0 0 8px">Fertige Schilder aus diesem Layout</h3>
    <?php if (!$vorhandene): ?>
        <p style="font-size:13px;color:var(--color-text-muted);margin:0">Noch keine.</p>
    <?php else: ?>
        <table class="erp-table" style="font-size:13px"><thead><tr><th>Artikelnummer</th><th>Name</th><th style="text-align:right">Bestand</th></tr></thead><tbody>
        <?php foreach ($vorhandene as $f): ?>
            <tr style="<?= $f['aktiv'] ? '' : 'opacity:.55' ?>"><td><a href="<?= $basePath ?>/artikel/detail.php?id=<?= (int) $f['id'] ?>"><?= htmlspecialchars($f['artikelnummer']) ?></a></td>
                <td><?= htmlspecialchars($f['name']) ?></td><td style="text-align:right"><?= rtrim(rtrim(number_format((float) $f['bestand'], 3, ',', '.'), '0'), ',') ?></td></tr>
        <?php endforeach; ?></tbody></table>
    <?php endif; ?>
</div>
</div>

<script>
(function () {
    var form = document.getElementById('fs-form'), box = document.getElementById('fs-vorschau'), knopf = document.getElementById('fs-knopf');
    function sichtbarkeit() {
        form.querySelectorAll('.fs-achse[data-bed-achse]').forEach(function (d) {
            var sel = form.querySelector('.fs-achse[data-achse="' + d.dataset.bedAchse + '"] select');
            var an = sel && sel.value === d.dataset.bedWert && sel.closest('.fs-achse').style.display !== 'none';
            d.style.display = an ? '' : 'none';
            d.querySelector('select').disabled = !an;
        });
    }
    function esc(t) { var e = document.createElement('div'); e.textContent = t; return e.innerHTML; }
    var timer;
    function vorschau() {
        sichtbarkeit(); sichtbarkeit(); // zweimal: verschachtelte Bedingungen
        clearTimeout(timer);
        timer = setTimeout(function () {
            var fd = new FormData(form); fd.set('aktion', 'vorschau');
            fetch('fertiges_schild.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (v) {
                if (v.fehler && v.fehler.length) { box.innerHTML = v.fehler.map(esc).join('<br>'); knopf.disabled = true; return; }
                box.innerHTML = '<div style="font-family:monospace;font-size:15px;font-weight:700">' + esc(v.artikelnummer) + '</div>'
                    + '<div>' + esc(v.name) + '</div>'
                    + '<div>Preis: € ' + Number(v.brutto).toFixed(2).replace('.', ',') + '</div>'
                    + (v.vorhanden ? '<div style="color:#2563eb;margin-top:4px">Gibt es schon → wird nur eingebucht' + (v.vorhanden.aktiv ? '' : ' und wieder aktiviert') + '.</div>'
                                   : '<div style="color:#16a34a;margin-top:4px">Neu → wird als Artikel angelegt.</div>')
                    + (v.ohne_material.length ? '<div style="color:#c2410c">Hinweis: Material leer (' + v.ohne_material.map(esc).join(', ') + ')</div>' : '');
                knopf.disabled = false;
                knopf.textContent = v.vorhanden ? 'Einbuchen' : 'Anlegen & einbuchen';
            });
        }, 200);
    }
    form.addEventListener('change', vorschau);
    vorschau();
})();
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
