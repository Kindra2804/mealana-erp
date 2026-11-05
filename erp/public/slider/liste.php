<?php
/**
 * Shop-Slider: Slides je Shop pflegen (Startseite + Kategorie-Banner) und an den Shop übertragen.
 * Logik: src/modules/slider/SliderService.php, Oberfläche: js/slider.js
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/slider/SliderService.php';

$service = new SliderService();
$repo    = $service->getRepo();
$shops   = $repo->findShops();

$shopId = (int)($_GET['shop'] ?? ($_SESSION['slider_shop'] ?? ($shops[0]['id'] ?? 0)));
$shop   = null;
foreach ($shops as $s) if ((int)$s['id'] === $shopId) $shop = $s;
if (!$shop && $shops) { $shop = $shops[0]; $shopId = (int)$shop['id']; }
$_SESSION['slider_shop'] = $shopId;

$slides = $shop ? $repo->findByShop($shopId) : [];

// Kategorien mit Pfad ("Wolle › Sockenwolle") für die Auswahl
$alleKat = $repo->findAlleKategorien();
$pfad = function (int $id) use ($alleKat): string {
    $teile = [];
    for ($i = 0; $id && isset($alleKat[$id]) && $i < 8; $i++) {
        array_unshift($teile, $alleKat[$id]['name']);
        $id = (int)$alleKat[$id]['parent_id'];
    }
    return implode(' › ', $teile);
};
$kategorien = [];
foreach ($shop ? $repo->findShopKategorien($shopId) : [] as $k) {
    $kategorien[] = ['id' => (int)$k['id'], 'name' => $pfad((int)$k['id'])];
}
usort($kategorien, fn($a, $b) => strcmp($a['name'], $b['name']));
$hersteller = $shop ? $repo->findShopHersteller($shopId) : [];
$aktionen   = $repo->findAktionen();

// Daten für das Bearbeiten-Fenster (Artikelnamen für gesetzte Artikel-Ziele mitgeben)
$slidesJs = [];
foreach ($slides as $s) {
    $s['status'] = SliderService::status($s);
    foreach ([1, 2] as $n) {
        $s["button{$n}_artikel_name"] = $s["button{$n}_ziel_typ"] === 'artikel' && $s["button{$n}_ziel_id"]
            ? $repo->findArtikelName((int)$s["button{$n}_ziel_id"]) : null;
    }
    $slidesJs[(int)$s['id']] = $s;
}

$pageTitle    = 'Shop-Slider';
$activeModule = 'artikel';
$actionBarContent = $shop ? '<button type="button" class="btn btn-primary btn-sm" onclick="sliderNeu()">+ Neue Slide</button>' : '';
require_once __DIR__ . '/../includes/shell_top.php';

$statusInfo = [
    'sichtbar'   => ['● sichtbar',  '#107c10', '#e8f5e8'],
    'geplant'    => ['◷ geplant',   '#0078d4', '#e8f3fc'],
    'abgelaufen' => ['abgelaufen',  '#999',    '#f0f0f0'],
    'aus'        => ['○ aus',       '#888',    '#f5f5f5'],
    'kein_bild'  => ['Bild fehlt',  '#b45309', '#fef3c7'],
];
$gruppen = ['startseite' => [], 'kategorie' => []];
foreach ($slides as $s) $gruppen[$s['position']][] = $s;
?>

<style>
    .sl-kopf { display:flex; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:16px }
    .sl-kopf select { padding:6px 8px; font-size:14px }
    .sl-stand { font-size:12px; color:var(--color-text-muted) }
    .sl-offen { background:#fef3c7; color:#92400e; border-radius:10px; padding:2px 10px; font-size:12px; font-weight:600 }
    .sl-thumb { width:110px; height:42px; object-fit:cover; border-radius:3px; background:#eee; display:block }
    .sl-pill { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap }
    .sl-griff { cursor:grab; color:#aaa; font-size:16px; width:24px; user-select:none }
    tr.sl-drag { opacity:.4 }
    tr.sl-ueber td { border-top:2px solid var(--color-nav) }
    .sl-gruppe { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--color-text-muted); margin:18px 0 6px }
    .sl-gruppe:first-child { margin-top:0 }
    #sl-modal .modal { width:960px; max-width:96vw; max-height:92vh; overflow-y:auto }
    .sl-raster { display:grid; grid-template-columns:1fr 1fr; gap:6px 22px }
    .sl-raster .voll { grid-column:1 / -1 }
    .sl-feld label { display:block; font-size:12px; color:var(--color-text-muted); margin-bottom:3px }
    .sl-feld input[type=text], .sl-feld input[type=url], .sl-feld input[type=datetime-local], .sl-feld select, .sl-feld textarea { width:100%; padding:6px 8px; font-size:13px; box-sizing:border-box }
    .sl-feld textarea { resize:vertical; min-height:54px; font-family:inherit }
    .sl-wahl { display:flex; gap:14px; flex-wrap:wrap; font-size:13px; padding:4px 0 }
    .sl-wahl label { display:flex; align-items:center; gap:5px; color:var(--color-text); font-size:13px; margin:0 }
    .sl-abschnitt { grid-column:1 / -1; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--color-text-muted); border-bottom:1px solid var(--color-border); padding:12px 0 4px; margin-bottom:4px }
    .sl-button-zeile { display:grid; grid-template-columns:1.1fr .8fr 1.4fr; gap:8px }
    .sl-bild-feld { display:flex; gap:10px; align-items:center }
    .sl-bild-feld img { width:120px; height:50px; object-fit:cover; border-radius:3px; background:#eee }
    .sl-treffer { position:absolute; z-index:5; background:#fff; border:1px solid var(--color-border); box-shadow:0 4px 12px rgba(0,0,0,.12); max-height:220px; overflow-y:auto; width:100% }
    .sl-treffer div { padding:6px 8px; font-size:13px; cursor:pointer }
    .sl-treffer div:hover { background:#f0f4ff }
    /* Vorschau: gleiche Aufteilung wie im Shop-Plugin */
    .sl-vorschau { position:relative; aspect-ratio:1920/700; background:#e9e4dc center/cover no-repeat; border-radius:4px; overflow:hidden; display:flex; align-items:center }
    .sl-vorschau::before { content:''; position:absolute; inset:0 }
    .sl-vorschau.links::before  { background:linear-gradient(90deg, var(--sl-schleier) 0, transparent 65%) }
    .sl-vorschau.rechts::before { background:linear-gradient(270deg, var(--sl-schleier) 0, transparent 65%) }
    .sl-vorschau.mitte::before  { background:radial-gradient(ellipse at center, var(--sl-schleier) 0, transparent 70%) }
    .sl-vorschau.dunkel { --sl-schleier:rgba(255,255,255,.55); color:#1f1d1b }
    .sl-vorschau.hell   { --sl-schleier:rgba(0,0,0,.45); color:#fff }
    .sl-vorschau.fertig::before { display:none }
    .sl-vtext { position:relative; width:46%; margin:0 6% }
    .sl-vorschau.mitte .sl-vtext { margin:0 auto; text-align:center; width:60% }
    .sl-vorschau.rechts .sl-vtext { margin-left:auto }
    .sl-vkicker { font-size:10px; letter-spacing:.2em; text-transform:uppercase; opacity:.8; margin-bottom:6px }
    .sl-vtitel { font-size:24px; line-height:1.1; margin-bottom:8px }
    .sl-vbeschr { font-size:12px; line-height:1.5; margin-bottom:12px }
    .sl-vbtn { display:inline-block; font-size:10px; letter-spacing:.1em; text-transform:uppercase; padding:8px 14px; background:#1d4076; color:#fff; margin-right:8px }
    .sl-vbtn.zwei { background:none; color:inherit; border-bottom:2px solid currentColor; padding:4px 0 }
</style>

<?php if (!$shops): ?>
    <div class="card"><p style="padding:var(--space-md)">Es ist noch kein Shop angebunden (Einstellungen → Kanäle).</p></div>
<?php else: ?>

<div class="sl-kopf">
    <label style="font-size:13px;color:var(--color-text-muted)">Shop
        <select onchange="location.href='?shop='+this.value">
            <?php foreach ($shops as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $shopId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label style="font-size:13px;color:var(--color-text-muted)">Wechsel alle
        <select id="sl-intervall" onchange="sliderIntervall(this.value)">
            <?php foreach ([4, 5, 6, 7, 8, 10, 12] as $sek): ?>
                <option value="<?= $sek ?>" <?= (int)$shop['slider_intervall_sek'] === $sek ? 'selected' : '' ?>><?= $sek ?> Sekunden</option>
            <?php endforeach; ?>
        </select>
    </label>
    <span style="flex:1"></span>
    <?php if ($shop['slider_geaendert']): ?><span class="sl-offen">Änderungen noch nicht übertragen</span><?php endif; ?>
    <span class="sl-stand">
        <?php if ($shop['slider_fehler']): ?>
            <span style="color:var(--color-danger)">Letzte Übertragung fehlgeschlagen: <?= htmlspecialchars($shop['slider_fehler']) ?></span>
        <?php elseif ($shop['slider_uebertragen_am']): ?>
            Zuletzt übertragen: <?= date('d.m.Y H:i', strtotime($shop['slider_uebertragen_am'])) ?>
        <?php elseif ($shop['wc_url']): ?>
            Noch nie übertragen
        <?php endif; ?>
    </span>
    <?php if ($shop['wc_url'] && $shop['wp_username']): ?>
        <button type="button" class="btn btn-primary btn-sm" id="sl-uebertragen" onclick="sliderUebertragen()">An Shop übertragen</button>
    <?php else: ?>
        <span class="sl-stand" title="Einstellungen → Kanäle">Shop noch nicht angebunden — Slides können schon vorbereitet werden</span>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (!$slides): ?>
        <p style="color:var(--color-text-muted);padding:var(--space-md)">
            Für <?= htmlspecialchars($shop['name']) ?> gibt es noch keine Slides.
            <button type="button" class="btn btn-primary btn-sm" style="margin-left:8px" onclick="sliderNeu()">Erste Slide anlegen</button>
        </p>
    <?php endif; ?>

    <?php foreach (['startseite' => 'Startseite (Hero-Slider)', 'kategorie' => 'Banner auf Kategorieseiten'] as $pos => $ueberschrift): ?>
        <?php if (!$gruppen[$pos]) continue; ?>
        <div class="sl-gruppe"><?= $ueberschrift ?></div>
        <table class="erp-table">
            <thead><tr><th></th><th>BILD</th><th>TITEL</th><?= $pos === 'kategorie' ? '<th>KATEGORIE</th>' : '' ?><th>SICHTBAR</th><th>STATUS</th><th></th></tr></thead>
            <tbody class="sl-sortierbar">
            <?php foreach ($gruppen[$pos] as $s):
                $st = $statusInfo[SliderService::status($s)];
                [$von, $bis] = SliderService::zeitraum($s);
                $sichtbar = match ($s['sichtbar']) {
                    'immer'    => 'immer',
                    'zeitraum' => ($von ? date('d.m.Y H:i', strtotime($von)) : '…') . ' – ' . ($bis ? date('d.m.Y H:i', strtotime($bis)) : '…'),
                    'aktion'   => 'Aktion „' . htmlspecialchars((string)$s['aktion_name']) . '“'
                                  . ($von ? '<br><span style="color:var(--color-text-muted)">' . date('d.m.', strtotime($von)) . ' – ' . date('d.m.Y', strtotime($bis)) . ($s['aktion_gestartet'] ? '' : ', nicht gestartet') . '</span>' : ''),
                };
            ?>
                <tr data-id="<?= $s['id'] ?>" data-gruppe="<?= $pos . '-' . (int)$s['kategorie_id'] ?>" class="<?= $s['ist_aktiv'] ? '' : 'row-inaktiv' ?>">
                    <td class="sl-griff" title="Ziehen zum Sortieren">≡</td>
                    <td><?php if ($s['bild_desktop']): ?><img class="sl-thumb" src="<?= BASE_PATH ?>/uploads/slider/<?= $s['id'] ?>/<?= htmlspecialchars($s['bild_desktop']) ?>" alt=""><?php else: ?><span class="sl-thumb"></span><?php endif; ?></td>
                    <td>
                        <a href="#" onclick="sliderBearbeiten(<?= $s['id'] ?>);return false" style="font-weight:600;color:var(--color-nav);text-decoration:none"><?= htmlspecialchars($s['bezeichnung']) ?></a>
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px">
                            <?= $s['art'] === 'fertiges_bild' ? 'Fertiges Bild' : 'Bild + Text' ?><?= $s['bild_mobil'] ? ' · eigenes Handy-Bild' : '' ?>
                        </div>
                    </td>
                    <?php if ($pos === 'kategorie'): ?><td style="font-size:12px"><?= htmlspecialchars($pfad((int)$s['kategorie_id'])) ?></td><?php endif; ?>
                    <td style="font-size:12px"><?= $sichtbar ?></td>
                    <td><span class="sl-pill" style="color:<?= $st[1] ?>;background:<?= $st[2] ?>"><?= $st[0] ?></span></td>
                    <td style="text-align:right;white-space:nowrap">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="sliderBearbeiten(<?= $s['id'] ?>)">Bearbeiten</button>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="sliderAktion('aktiv', {id:<?= $s['id'] ?>, aktiv:<?= $s['ist_aktiv'] ? 0 : 1 ?>})"><?= $s['ist_aktiv'] ? 'Ausschalten' : 'Einschalten' ?></button>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="sliderKopieren(<?= $s['id'] ?>)" title="Kopieren, auch in einen anderen Shop">Kopieren</button>
                        <button type="button" class="btn btn-danger btn-xs" onclick="sliderLoeschen(<?= $s['id'] ?>)">Löschen</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</div>
<p style="font-size:12px;color:var(--color-text-muted);margin-top:8px">
    Änderungen erscheinen erst nach „An Shop übertragen“ im Shop. Das Ein- und Ausblenden nach Datum macht der Shop danach selbst.
</p>

<!-- Bearbeiten -->
<div id="sl-modal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header" id="sl-modal-titel">Slide bearbeiten</div>
    <form id="sl-form" onsubmit="sliderSpeichern(event)">
      <input type="hidden" name="id">
      <input type="hidden" name="shop_id" value="<?= $shopId ?>">
      <div class="sl-raster">
        <div class="sl-feld">
          <label>Wo erscheint die Slide?</label>
          <div class="sl-wahl">
            <label><input type="radio" name="position" value="startseite"> Startseite</label>
            <label><input type="radio" name="position" value="kategorie"> Kategorieseite</label>
          </div>
        </div>
        <div class="sl-feld" id="sl-kat-feld">
          <label>Kategorie</label>
          <select name="kategorie_id"><option value="">– wählen –</option>
            <?php foreach ($kategorien as $k): ?><option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="sl-feld voll">
          <label>Art</label>
          <div class="sl-wahl">
            <label><input type="radio" name="art" value="gestaltet"> Bild + Text (Text wird im Shop über das Bild gelegt)</label>
            <label><input type="radio" name="art" value="fertiges_bild"> Fertiges Bild (Text ist schon im Bild, z. B. aus Canva)</label>
          </div>
        </div>

        <div class="sl-abschnitt">Bilder</div>
        <div class="sl-feld">
          <label>Bild Desktop <span style="font-weight:400">(ideal 1920 × 700 px, Querformat)</span></label>
          <div class="sl-bild-feld"><img id="sl-bild-desktop-vorschau" alt=""><input type="file" name="bild_desktop" accept="image/jpeg,image/png,image/webp"></div>
        </div>
        <div class="sl-feld">
          <label>Bild Handy <span style="font-weight:400">(optional, Hochformat ca. 900 × 1100 px)</span></label>
          <div class="sl-bild-feld"><img id="sl-bild-mobil-vorschau" alt=""><input type="file" name="bild_mobil" accept="image/jpeg,image/png,image/webp">
            <label id="sl-mobil-weg" style="font-size:12px;white-space:nowrap"><input type="checkbox" name="bild_mobil_entfernen" value="1"> entfernen</label></div>
        </div>
        <div class="sl-feld voll">
          <label>Bildbeschreibung für Google und Screenreader <span style="font-weight:400">(optional, sonst der Titel)</span></label>
          <input type="text" name="alt_text" maxlength="150">
        </div>

        <div class="sl-abschnitt">Text</div>
        <div class="sl-feld nur-gestaltet">
          <label>Kleiner Titel (oben, optional)</label>
          <input type="text" name="kicker" maxlength="80" placeholder="z. B. UNTERSETZER MIT AUGENZWINKERN">
        </div>
        <div class="sl-feld">
          <label id="sl-titel-label">Titel</label>
          <input type="text" name="titel" maxlength="150">
        </div>
        <div class="sl-feld voll nur-gestaltet">
          <label>Text (optional)</label>
          <textarea name="text" maxlength="400"></textarea>
        </div>
        <div class="sl-feld voll nur-fertig">
          <label>Bezeichnung in der Liste</label>
          <input type="text" name="bezeichnung" maxlength="100" placeholder="z. B. Weihnachtsbanner Canva">
        </div>

        <div class="sl-abschnitt" id="sl-buttons-titel">Buttons</div>
        <?php foreach ([1, 2] as $n): ?>
        <div class="sl-feld voll <?= $n === 2 ? 'nur-gestaltet' : '' ?>">
          <label class="sl-btn-label-<?= $n ?>"><?= $n === 1 ? 'Button 1 (Hauptbutton)' : 'Button 2 (optional, als Textlink)' ?></label>
          <div class="sl-button-zeile">
            <input type="text" name="button<?= $n ?>_text" maxlength="60" placeholder="Beschriftung" class="nur-gestaltet">
            <select name="button<?= $n ?>_ziel_typ" data-n="<?= $n ?>" onchange="sliderZielTyp(<?= $n ?>)">
              <option value="">– kein Ziel –</option><option value="kategorie">Kategorie</option><option value="artikel">Artikel</option>
              <option value="hersteller">Hersteller</option><option value="url">Adresse (URL)</option>
            </select>
            <div style="position:relative">
              <select name="button<?= $n ?>_kategorie" class="sl-ziel sl-ziel-kategorie"><option value="">– Kategorie –</option>
                <?php foreach ($kategorien as $k): ?><option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option><?php endforeach; ?></select>
              <select name="button<?= $n ?>_hersteller" class="sl-ziel sl-ziel-hersteller"><option value="">– Hersteller –</option>
                <?php foreach ($hersteller as $h): ?><option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option><?php endforeach; ?></select>
              <input type="text" class="sl-ziel sl-ziel-artikel" placeholder="Artikel suchen …" autocomplete="off" oninput="sliderArtikelSuche(<?= $n ?>, this.value)">
              <input type="hidden" name="button<?= $n ?>_artikel">
              <div class="sl-treffer" id="sl-treffer-<?= $n ?>" style="display:none"></div>
              <input type="text" name="button<?= $n ?>_url" class="sl-ziel sl-ziel-url" placeholder="https://… oder /seite/">
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="sl-abschnitt nur-gestaltet">Darstellung</div>
        <div class="sl-feld nur-gestaltet">
          <label>Text-Position</label>
          <div class="sl-wahl">
            <label><input type="radio" name="text_position" value="links"> links</label>
            <label><input type="radio" name="text_position" value="mitte"> Mitte</label>
            <label><input type="radio" name="text_position" value="rechts"> rechts</label>
          </div>
        </div>
        <div class="sl-feld nur-gestaltet">
          <label>Schrift</label>
          <div class="sl-wahl">
            <label><input type="radio" name="schrift" value="dunkel"> dunkel (für helle Bilder)</label>
            <label><input type="radio" name="schrift" value="hell"> hell (für dunkle Bilder)</label>
          </div>
        </div>

        <div class="sl-abschnitt">Sichtbar</div>
        <div class="sl-feld voll">
          <div class="sl-wahl">
            <label><input type="radio" name="sichtbar" value="immer"> immer</label>
            <label><input type="radio" name="sichtbar" value="zeitraum"> im Zeitraum</label>
            <label><input type="radio" name="sichtbar" value="aktion"> solange eine Preis-Aktion läuft</label>
            <label style="margin-left:auto"><input type="checkbox" name="ist_aktiv" value="1"> eingeschaltet</label>
          </div>
        </div>
        <div class="sl-feld nur-zeitraum"><label>von</label><input type="datetime-local" name="gueltig_von"></div>
        <div class="sl-feld nur-zeitraum"><label>bis</label><input type="datetime-local" name="gueltig_bis"></div>
        <div class="sl-feld voll nur-aktion">
          <label>Aktion</label>
          <select name="aktion_id"><option value="">– wählen –</option>
            <?php foreach ($aktionen as $a): ?>
              <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?><?= $a['von'] ? ' (' . date('d.m.', strtotime($a['von'])) . '–' . date('d.m.Y', strtotime($a['bis'])) . ')' : ' (noch kein Zeitraum)' ?><?= $a['gestartet'] ? '' : ' – nicht gestartet' ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="sl-abschnitt">Vorschau <span style="font-weight:400;text-transform:none;letter-spacing:0">(Schrift und Farben im Shop wie im Shop-Design)</span></div>
        <div class="voll">
          <div class="sl-vorschau" id="sl-vorschau">
            <div class="sl-vtext"><div class="sl-vkicker"></div><div class="sl-vtitel"></div><div class="sl-vbeschr"></div><div class="sl-vbtns"></div></div>
          </div>
        </div>
      </div>
      <div id="sl-fehler" style="color:var(--color-danger);font-size:13px;margin-top:12px"></div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
        <button type="button" class="btn btn-secondary btn-sm" onclick="sliderSchliessen()">Abbrechen</button>
        <button type="submit" class="btn btn-primary btn-sm" id="sl-speichern">Speichern</button>
      </div>
    </form>
  </div>
</div>

<!-- Kopieren -->
<div id="sl-kopie-modal" class="modal-backdrop">
  <div class="modal" style="width:400px">
    <div class="modal-header">Slide kopieren</div>
    <p style="font-size:13px;margin-bottom:10px">In welchen Shop? Die Kopie ist zunächst ausgeschaltet.</p>
    <select id="sl-kopie-shop" style="width:100%;padding:6px">
      <?php foreach ($shops as $s): ?><option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $shopId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
    </select>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
      <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('sl-kopie-modal').style.display='none'">Abbrechen</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="sliderKopierenOk()">Kopieren</button>
    </div>
  </div>
</div>

<script>
const SLIDER = {
    basis: <?= json_encode(BASE_PATH) ?>,
    shopId: <?= $shopId ?>,
    slides: <?= json_encode($slidesJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
};
</script>
<script src="<?= BASE_PATH ?>/js/slider.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
