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
    echo json_encode($service->vorschau($layoutId, $_POST['wert'] ?? [], $_POST['sonder'] ?? [], $_POST['ptext'] ?? []), JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Anlegen / Einbuchen ---
if (($_POST['aktion'] ?? '') === 'anlegen') {
    try {
        $erg = $service->anlegen($layoutId, $_POST['wert'] ?? [], (float) str_replace(',', '.', $_POST['menge'] ?? '0'),
            (int) ($_POST['lager_id'] ?? 0), (int) ($_POST['kategorie_id'] ?? 0), (int) ($_SESSION['benutzer']['id'] ?? 0),
            $_POST['sonder'] ?? [], $_POST['ptext'] ?? []);
        $_SESSION['erfolg'] = ($erg['neu'] ? 'Fertiges Schild ' . $erg['artikelnummer'] . ' angelegt' : 'Vorhandenes Schild ' . $erg['artikelnummer'] . ' gefunden')
            . ($erg['menge'] > 0 ? ' und ' . rtrim(rtrim(number_format($erg['menge'], 3, ',', ''), '0'), ',') . ' Stk. eingebucht.' : '.');
        $_SESSION['fertig_letzte_auswahl'] = $_POST['wert'] ?? [];
        $_SESSION['fertig_letzter_artikel'] = (int) $erg['artikel_id'];   // -> Knopf "Kuvert-Etikett drucken"
    } catch (Throwable $e) {
        $_SESSION['fehler'] = [$e->getMessage()];
        $_SESSION['fertig_letzte_auswahl'] = $_POST['wert'] ?? [];
        $_SESSION['fertig_letzte_sonder'] = $_POST['sonder'] ?? [];
        $_SESSION['fertig_letzte_texte'] = $_POST['ptext'] ?? [];
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
$auswahl = array_map('strval', $_SESSION['fertig_letzte_auswahl'] ?? []);   // wert_id oder 'sonder'
$sonderEingabe = $_SESSION['fertig_letzte_sonder'] ?? [];
$textEingabe   = $_SESSION['fertig_letzte_texte'] ?? [];
$letzterArtikel = (int) ($_SESSION['fertig_letzter_artikel'] ?? 0);
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['fertig_letzte_auswahl'], $_SESSION['fertig_letzte_sonder'], $_SESSION['fertig_letzte_texte'], $_SESSION['fertig_letzter_artikel']);

$konfiguration = (new KonfiguratorService())->getKonfiguration($layoutId);
$vorhandene    = $service->vorhandene($layoutId);
$db            = Database::getInstance();
$lager         = $db->query("SELECT id, name FROM lager WHERE lager_beziehung = 'eigen' AND COALESCE(aktiv, 1) = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$kategorienBaum = (new ArtikelService())->getKategorienBaum();
// Eigentümer des Layouts (Migration 237): Partner-Layouts buchen immer ins Partner-Lager
$eigSt = $db->prepare("SELECT p.name, l.name AS lager FROM artikel a JOIN partner p ON p.id = a.partner_id
                       LEFT JOIN lager l ON l.partner_id = p.id AND l.lager_beziehung = 'partner_bestand' WHERE a.id = ?");
$eigSt->execute([$layoutId]);
$eigentuemer = $eigSt->fetch(PDO::FETCH_ASSOC) ?: null;
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
    <div class="card" style="border-left:4px solid var(--color-success);margin-bottom:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <p style="margin:0;color:var(--color-success);flex:1"><?= htmlspecialchars($erfolg) ?></p>
        <?php if ($letzterArtikel): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="kuvertEtikettDialog([<?= $letzterArtikel ?>])">🖨 Kuvert-Etikett drucken</button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(320px,1fr);gap:12px;align-items:start">
<form method="POST" id="fs-form" class="card">
    <input type="hidden" name="layout" value="<?= $layoutId ?>">
    <input type="hidden" name="aktion" value="anlegen">
    <h3 style="margin:0 0 4px"><?= htmlspecialchars($layout['artikelnummer'] . ' ' . $layout['name']) ?></h3>
    <p style="margin:0 0 12px;font-size:13px;color:var(--color-text-muted)">Werte des gebauten Schilds wählen. Gibt es die Kombination schon, wird nur eingebucht.</p>
    <?php if ($eigentuemer): ?><p style="margin:-6px 0 12px;font-size:13px;color:#2563eb">Gehört <?= htmlspecialchars($eigentuemer['name']) ?> – das Schild wird deren Artikel und kommt in ihr Lager.</p><?php endif; ?>

    <?php foreach ($konfiguration as $achse):
        $aid = (int) $achse['achse_id'];
        // Achse mit nur einem Wert (z.B. Personalisierung „Metallschild“) vorwählen
        if (!isset($auswahl[$aid]) && count($achse['werte']) === 1) $auswahl[$aid] = (string) $achse['werte'][0]['wert_id'];
        $istSonder = ($auswahl[$aid] ?? '') === 'sonder';
        $se = $sonderEingabe[$aid] ?? [];
        $textWert = null;
        foreach ($achse['werte'] as $w) if (!empty($w['texteingabe']) && ($auswahl[$aid] ?? '') === (string) $w['wert_id']) $textWert = $w; ?>
        <div class="fs-achse" data-achse="<?= (int) $achse['achse_id'] ?>"
             <?php if ($achse['bedingung']): ?>data-bed-achse="<?= (int) $achse['bedingung']['achse_id'] ?>" data-bed-wert="<?= (int) $achse['bedingung']['wert_id'] ?>"<?php endif; ?>
             style="margin-bottom:10px">
            <label class="erp-label"><?= htmlspecialchars($achse['name']) ?></label>
            <select name="wert[<?= (int) $achse['achse_id'] ?>]" class="erp-select fs-wert" style="width:100%">
                <option value="">– wählen –</option>
                <?php foreach ($achse['werte'] as $w): ?>
                    <option value="<?= (int) $w['wert_id'] ?>" <?= ($auswahl[$aid] ?? '') === (string) $w['wert_id'] ? 'selected' : '' ?>
                        <?= !empty($w['texteingabe']) ? 'data-text="1" data-max="' . (int) ($w['max_zeichen'] ?: 30) . '"' : '' ?>>
                        <?= htmlspecialchars($w['wert'] . ($w['verfuegbar'] === false ? ' (Material leer)' : '')) ?></option>
                <?php endforeach; ?>
                <option value="sonder" <?= $istSonder ? 'selected' : '' ?>>Sonder … (Freitext)</option>
            </select>
            <div class="fs-sonder" style="display:<?= $istSonder ? 'grid' : 'none' ?>;grid-template-columns:1fr 70px 90px;gap:6px;margin-top:4px">
                <input type="text" name="sonder[<?= $aid ?>][text]" value="<?= htmlspecialchars($se['text'] ?? '') ?>" maxlength="60" class="erp-select" placeholder="z.B. MG pfirsich, Holz 6 mm">
                <input type="text" name="sonder[<?= $aid ?>][kuerzel]" value="<?= htmlspecialchars($se['kuerzel'] ?? '') ?>" maxlength="12" class="erp-select" placeholder="Kürzel S" title="Kürzel für die Artikelnummer (leer = S)" style="font-family:monospace">
                <input type="text" name="sonder[<?= $aid ?>][aufpreis]" value="<?= htmlspecialchars($se['aufpreis'] ?? '') ?>" class="erp-select" placeholder="+ € 0,00" title="Aufpreis brutto" inputmode="decimal">
            </div>
            <input type="text" name="ptext[<?= $aid ?>]" class="erp-select fs-ptext" value="<?= htmlspecialchars($textEingabe[$aid] ?? '') ?>"
                   maxlength="<?= (int) ($textWert['max_zeichen'] ?? 30) ?>" placeholder="Personalisierungstext (z.B. Name)"
                   style="display:<?= $textWert ? 'block' : 'none' ?>;width:100%;margin-top:4px">
        </div>
    <?php endforeach; ?>

    <div style="display:grid;grid-template-columns:100px 1fr;gap:10px;margin-top:14px">
        <div><label class="erp-label">Menge</label><input type="number" name="menge" value="1" min="0" step="1" class="erp-select" style="width:100%"></div>
        <div><label class="erp-label">Lager</label>
            <?php if ($eigentuemer): ?>
                <div class="erp-select" style="width:100%;background:var(--color-bg-subtle,#f8fafc)" title="Layout gehört <?= htmlspecialchars($eigentuemer['name']) ?>"><?= htmlspecialchars($eigentuemer['lager'] ?: 'Partner-Lager fehlt!') ?></div>
                <input type="hidden" name="lager_id" value="0">
            <?php else: ?>
            <select name="lager_id" class="erp-select" style="width:100%"><?php foreach ($lager as $l): ?><option value="<?= (int) $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?></select>
            <?php endif; ?></div>
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
        <table class="erp-table" style="font-size:13px"><thead><tr><th>Artikelnummer</th><th>Name</th><th style="text-align:right">Bestand</th><th></th></tr></thead><tbody>
        <?php foreach ($vorhandene as $f): ?>
            <tr style="<?= $f['aktiv'] ? '' : 'opacity:.55' ?>"><td><a href="<?= $basePath ?>/artikel/detail.php?id=<?= (int) $f['id'] ?>"><?= htmlspecialchars($f['artikelnummer']) ?></a></td>
                <td><?= htmlspecialchars($f['name']) ?></td><td style="text-align:right"><?= rtrim(rtrim(number_format((float) $f['bestand'], 3, ',', '.'), '0'), ',') ?></td>
                <td style="text-align:right"><button type="button" class="btn btn-secondary btn-sm" title="Kuvert-Etikett drucken" onclick="kuvertEtikettDialog([<?= (int) $f['id'] ?>])">🖨</button></td></tr>
        <?php endforeach; ?></tbody></table>
    <?php endif; ?>
</div>
</div>

<script src="<?= BASE_PATH ?>/js/kuvert_etikett.js"></script>
<script>
(function () {
    var form = document.getElementById('fs-form'), box = document.getElementById('fs-vorschau'), knopf = document.getElementById('fs-knopf');
    function sichtbarkeit() {
        form.querySelectorAll('.fs-achse').forEach(function (d) {
            var sel = d.querySelector('select');
            var sonder = d.querySelector('.fs-sonder');
            if (sonder) sonder.style.display = sel.value === 'sonder' ? 'grid' : 'none';
            var ptext = d.querySelector('.fs-ptext'), opt = sel.selectedOptions[0];
            if (ptext) {
                var an = !!(opt && opt.dataset.text === '1');
                ptext.style.display = an ? 'block' : 'none';
                ptext.disabled = !an;
                if (an) ptext.maxLength = parseInt(opt.dataset.max, 10) || 30;
            }
        });
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
                       : v.sonder ? '<div style="color:#16a34a;margin-top:4px">Sonderschild → wird immer als eigener Artikel angelegt.</div>'
                                   : '<div style="color:#16a34a;margin-top:4px">Neu → wird als Artikel angelegt.</div>')
                    + (v.ohne_material.length ? '<div style="color:#c2410c">Hinweis: Material leer (' + v.ohne_material.map(esc).join(', ') + ')</div>' : '');
                knopf.disabled = false;
                knopf.textContent = v.vorhanden ? 'Einbuchen' : 'Anlegen & einbuchen';
            });
        }, 200);
    }
    form.addEventListener('change', vorschau);
    form.addEventListener('input', function (e) { if (e.target.closest('.fs-sonder') || e.target.classList.contains('fs-ptext')) vorschau(); });
    vorschau();
})();
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
