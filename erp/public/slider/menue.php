<?php
/**
 * Shop-Menü: Hauptmenü je Shop pflegen (max. 3 Ebenen) und an den Shop übertragen.
 * Logik: src/modules/slider/MenueService.php, Oberfläche: js/slider_menue.js
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/slider/MenueService.php';

$service  = new MenueService();
$shopRepo = $service->getShopRepo();
$shops    = $shopRepo->findShops();

$shopId = (int)($_GET['shop'] ?? ($_SESSION['slider_shop'] ?? ($shops[0]['id'] ?? 0)));
$shop = null;
foreach ($shops as $s) if ((int)$s['id'] === $shopId) $shop = $s;
if (!$shop && $shops) { $shop = $shops[0]; $shopId = (int)$shop['id']; }
$_SESSION['slider_shop'] = $shopId;

$stand = $shop ? $service->getRepo()->findShopStand($shopId) : null;
$baum  = $shop ? $service->baum($shopId) : [];

// Auswahllisten (wie beim Slider: Kategorien mit Pfad, Hersteller)
$alleKat = $shopRepo->findAlleKategorien();
$pfad = function (int $id) use ($alleKat): string {
    $t = [];
    for ($i = 0; $id && isset($alleKat[$id]) && $i < 8; $i++) { array_unshift($t, $alleKat[$id]['name']); $id = (int)$alleKat[$id]['parent_id']; }
    return implode(' › ', $t);
};
$kategorien = [];
foreach ($shop ? $shopRepo->findShopKategorien($shopId) : [] as $k) $kategorien[] = ['id' => (int)$k['id'], 'name' => $pfad((int)$k['id'])];
usort($kategorien, fn($a, $b) => strcmp($a['name'], $b['name']));
$hersteller = $shop ? $shopRepo->findShopHersteller($shopId) : [];

// Flache Liste für JS + Auswahl „übergeordnet“ (nur Punkte bis Ebene 2, nicht „Alle Hersteller“)
$flach = [];
$elternWahl = [];
$walk = function (array $knoten, int $ebene) use (&$walk, &$flach, &$elternWahl) {
    foreach ($knoten as $p) {
        $kinder = $p['kinder'];
        unset($p['kinder']);
        $p['ebene'] = $ebene;
        $flach[(int)$p['id']] = $p;
        if ($ebene < MenueService::MAX_EBENEN && $p['typ'] !== 'hersteller_alle') {
            $elternWahl[] = ['id' => (int)$p['id'], 'ebene' => $ebene, 'name' => $p['bezeichnung'] ?: $p['ziel_name']];
        }
        $walk($kinder, $ebene + 1);
    }
};
$walk($baum, 1);

$typText = [
    'kategorie'       => 'Kategorie',
    'hersteller_alle' => 'Alle Hersteller (automatisch)',
    'hersteller'      => 'Hersteller',
    'artikel'         => 'Artikel',
    'url'             => 'Adresse',
];

$pageTitle    = 'Shop-Menü';
$activeModule = 'artikel';
$actionBarContent = $shop ? '<button type="button" class="btn btn-primary btn-sm" onclick="menueNeu()">+ Menüpunkt</button>' : '';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<style>
    .mn-kopf { display:flex; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:16px }
    .mn-kopf select { padding:6px 8px; font-size:14px }
    .mn-stand { font-size:12px; color:var(--color-text-muted) }
    .mn-offen { background:#fef3c7; color:#92400e; border-radius:10px; padding:2px 10px; font-size:12px; font-weight:600 }
    .mn-zeile { display:flex; align-items:center; gap:10px; padding:9px 12px; border-bottom:1px solid var(--color-border); background:#fff }
    .mn-zeile.aus { opacity:.5 }
    .mn-zeile.mn-drag { opacity:.35 }
    .mn-zeile.mn-ueber { box-shadow: inset 0 2px 0 var(--color-nav) }
    .mn-griff { cursor:grab; color:#aaa; font-size:16px; width:18px; user-select:none }
    .mn-name { font-weight:600; color:var(--color-nav); min-width:200px }
    .mn-typ { font-size:12px; color:var(--color-text-muted); flex:1 }
    .mn-chip { display:inline-block; font-size:11px; border-radius:9px; padding:1px 8px; margin-left:6px; background:#eef2f7; color:#475569 }
    .mn-auto { padding:4px 12px 6px; font-size:12px; color:var(--color-text-muted); background:#fafbfc; border-bottom:1px solid var(--color-border) }
    #mn-modal .modal { width:620px; max-width:96vw }
    .mn-feld { margin-bottom:12px } .mn-feld label { display:block; font-size:12px; color:var(--color-text-muted); margin-bottom:3px }
    .mn-feld input[type=text], .mn-feld select { width:100%; padding:6px 8px; font-size:13px; box-sizing:border-box }
    .mn-check { display:flex; align-items:center; gap:6px; font-size:13px; margin:6px 0 }
    .mn-treffer { position:absolute; z-index:5; background:#fff; border:1px solid var(--color-border); box-shadow:0 4px 12px rgba(0,0,0,.12); max-height:220px; overflow-y:auto; width:100% }
    .mn-treffer div { padding:6px 8px; font-size:13px; cursor:pointer } .mn-treffer div:hover { background:#f0f4ff }
</style>

<?php if (!$shops): ?>
    <div class="card"><p style="padding:var(--space-md)">Es ist noch kein Shop angebunden (Einstellungen → Kanäle).</p></div>
<?php else: ?>

<div class="mn-kopf">
    <label style="font-size:13px;color:var(--color-text-muted)">Shop
        <select onchange="location.href='?shop='+this.value">
            <?php foreach ($shops as $s): ?><option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $shopId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
        </select>
    </label>
    <?php if (!$baum): ?><button type="button" class="btn btn-secondary btn-sm" onclick="menueAktion('vorschlag')">Aus Kategoriebaum vorschlagen</button><?php endif; ?>
    <span style="flex:1"></span>
    <?php if ($stand['menue_geaendert']): ?><span class="mn-offen">Änderungen noch nicht übertragen</span><?php endif; ?>
    <span class="mn-stand">
        <?php if ($stand['menue_fehler']): ?><span style="color:var(--color-danger)">Letzte Übertragung fehlgeschlagen: <?= htmlspecialchars($stand['menue_fehler']) ?></span>
        <?php elseif ($stand['menue_uebertragen_am']): ?>Zuletzt übertragen: <?= date('d.m.Y H:i', strtotime($stand['menue_uebertragen_am'])) ?>
        <?php elseif ($shop['wc_url']): ?>Noch nie übertragen<?php endif; ?>
    </span>
    <?php if ($shop['wc_url'] && $shop['wp_username']): ?>
        <button type="button" class="btn btn-primary btn-sm" id="mn-uebertragen" onclick="menueUebertragen()">An Shop übertragen</button>
    <?php else: ?>
        <span class="mn-stand">Shop noch nicht angebunden — das Menü kann schon vorbereitet werden</span>
    <?php endif; ?>
</div>

<div class="card" style="padding:0">
    <?php if (!$baum): ?>
        <p style="color:var(--color-text-muted);padding:var(--space-md)">
            Für <?= htmlspecialchars($shop['name']) ?> gibt es noch kein Menü. Am schnellsten: <strong>Aus Kategoriebaum vorschlagen</strong>
            (legt die Hauptkategorien des Shops an, je mit Unterkategorien automatisch) und dann anpassen.
        </p>
    <?php endif; ?>
    <?php
    $zeile = function (array $p, int $ebene) use (&$zeile, $typText) {
        $name = $p['bezeichnung'] ?: ($p['ziel_name'] ?: '(ohne Namen)');
        $typ = $typText[$p['typ']];
        if (in_array($p['typ'], ['kategorie', 'hersteller', 'artikel'], true) && $p['bezeichnung'] && $p['ziel_name']) $typ .= ': ' . htmlspecialchars($p['ziel_name']);
        if ($p['typ'] === 'url') $typ .= ': ' . htmlspecialchars((string)$p['url']);
        echo '<div class="mn-zeile' . ($p['ist_aktiv'] ? '' : ' aus') . '" data-id="' . $p['id'] . '" data-gruppe="' . (int)$p['parent_id'] . '" style="padding-left:' . (12 + ($ebene - 1) * 34) . 'px">'
           . '<span class="mn-griff" title="Ziehen zum Sortieren (innerhalb derselben Ebene)">≡</span>'
           . '<a href="#" class="mn-name" onclick="menueBearbeiten(' . $p['id'] . ');return false">' . htmlspecialchars($name) . '</a>'
           . '<span class="mn-typ">' . $typ
           . ($p['unterkategorien_auto'] ? '<span class="mn-chip">+ Unterkategorien automatisch</span>' : '')
           . ($p['nur_mit_artikeln'] ? '<span class="mn-chip">nur wenn Artikel drin</span>' : '')
           . ($p['typ'] === 'hersteller_alle' && $p['spalten'] ? '<span class="mn-chip">in Spalten</span>' : '')
           . ($p['ist_aktiv'] ? '' : '<span class="mn-chip">aus</span>') . '</span>'
           . '<span style="white-space:nowrap">'
           . ($ebene < MenueService::MAX_EBENEN && $p['typ'] !== 'hersteller_alle' ? '<button type="button" class="btn btn-secondary btn-xs" onclick="menueNeu(' . $p['id'] . ')">+ Unterpunkt</button> ' : '')
           . '<button type="button" class="btn btn-secondary btn-xs" onclick="menueBearbeiten(' . $p['id'] . ')">Bearbeiten</button> '
           . '<button type="button" class="btn btn-secondary btn-xs" onclick="menueAktion(\'aktiv\', {id:' . $p['id'] . ', aktiv:' . ($p['ist_aktiv'] ? 0 : 1) . '})">' . ($p['ist_aktiv'] ? 'Ausschalten' : 'Einschalten') . '</button> '
           . '<button type="button" class="btn btn-danger btn-xs" onclick="menueLoeschen(' . $p['id'] . ')">Löschen</button></span></div>';
        foreach ($p['kinder'] as $k) $zeile($k, $ebene + 1);
    };
    foreach ($baum as $p) $zeile($p, 1);
    ?>
</div>
<p style="font-size:12px;color:var(--color-text-muted);margin-top:8px">
    Höchstens <?= MenueService::MAX_EBENEN ?> Ebenen. „Unterkategorien automatisch“ übernimmt neue Unterkategorien beim Übertragen von selbst
    (der nächtliche Abgleich überträgt neu, wenn sich Kategorien geändert haben). „Alle Hersteller“ und „nur wenn Artikel drin“ prüft der Shop bei jedem Aufruf.
</p>
<details style="font-size:12px;color:var(--color-text-muted);margin-top:14px">
    <summary style="cursor:pointer">Technik: Menü-Ort im Theme</summary>
    <div style="margin-top:8px">Das Menü wird im Shop an diese Theme-Orte gehängt (Blocksy: <code>menu_1</code> = Hauptmenü oben, <code>menu_mobile</code> = Handy-Menü). Mehrere mit Komma trennen.
        <input type="text" id="mn-orte" value="<?= htmlspecialchars($stand['menue_orte']) ?>" style="width:220px;padding:4px 6px;margin-left:8px">
        <button type="button" class="btn btn-secondary btn-xs" onclick="menueAktion('orte', {orte: document.getElementById('mn-orte').value})">Speichern</button></div>
</details>

<!-- Bearbeiten -->
<div id="mn-modal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header" id="mn-modal-titel">Menüpunkt</div>
    <form id="mn-form" onsubmit="menueSpeichern(event)">
      <input type="hidden" name="id"><input type="hidden" name="shop_id" value="<?= $shopId ?>">
      <div class="mn-feld"><label>Worauf zeigt der Menüpunkt?</label>
        <select name="typ" onchange="menueTyp()">
          <?php foreach ($typText as $wert => $text): ?><option value="<?= $wert ?>"><?= $text ?></option><?php endforeach; ?>
        </select></div>
      <div class="mn-feld mn-nur" data-typ="kategorie"><label>Kategorie</label>
        <select name="ziel_kategorie"><option value="">– wählen –</option>
          <?php foreach ($kategorien as $k): ?><option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option><?php endforeach; ?></select></div>
      <div class="mn-feld mn-nur" data-typ="hersteller"><label>Hersteller</label>
        <select name="ziel_hersteller"><option value="">– wählen –</option>
          <?php foreach ($hersteller as $h): ?><option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option><?php endforeach; ?></select></div>
      <div class="mn-feld mn-nur" data-typ="artikel" style="position:relative"><label>Artikel</label>
        <input type="text" id="mn-artikel-suche" placeholder="Artikel suchen …" autocomplete="off" oninput="menueArtikelSuche(this.value)">
        <input type="hidden" name="ziel_artikel"><div class="mn-treffer" id="mn-treffer" style="display:none"></div></div>
      <div class="mn-feld mn-nur" data-typ="url"><label>Adresse</label><input type="text" name="url" placeholder="https://… oder /seite/"></div>
      <div class="mn-feld"><label id="mn-bez-label">Bezeichnung im Menü</label><input type="text" name="bezeichnung" maxlength="100"></div>
      <div class="mn-feld"><label>Übergeordneter Menüpunkt</label>
        <select name="parent_id"><option value="">– keiner (Hauptpunkt) –</option>
          <?php foreach ($elternWahl as $e): ?><option value="<?= $e['id'] ?>"><?= str_repeat('— ', $e['ebene'] - 1) . htmlspecialchars((string)$e['name']) ?></option><?php endforeach; ?></select></div>
      <label class="mn-check mn-nur" data-typ="kategorie"><input type="checkbox" name="unterkategorien_auto" value="1"> Unterkategorien automatisch als Unterpunkte</label>
      <label class="mn-check mn-nur" data-typ="kategorie hersteller"><input type="checkbox" name="nur_mit_artikeln" value="1"> nur zeigen, wenn Artikel drin sind (z. B. Sale)</label>
      <label class="mn-check mn-nur" data-typ="hersteller_alle"><input type="checkbox" name="spalten" value="1"> Hersteller in Spalten anzeigen (großes Aufklappfeld)</label>
      <label class="mn-check"><input type="checkbox" name="ist_aktiv" value="1"> eingeschaltet</label>
      <div id="mn-fehler" style="color:var(--color-danger);font-size:13px;margin-top:10px"></div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('mn-modal').style.display='none'">Abbrechen</button>
        <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
      </div>
    </form>
  </div>
</div>

<script>
const MENUE = { basis: <?= json_encode(BASE_PATH) ?>, shopId: <?= $shopId ?>, punkte: <?= json_encode((object)$flach, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?> };
</script>
<script src="<?= BASE_PATH ?>/js/slider_menue.js"></script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
