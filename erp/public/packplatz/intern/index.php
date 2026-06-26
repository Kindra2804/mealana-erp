<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';

$lagerService = new LagerService();
$alleLager    = $lagerService->getAlleLager();

$pageTitle = 'Intern';
$backUrl   = '/mealana/packplatz/index.php';
$headerSub = 'Intern';
require_once __DIR__ . '/../shell_top.php';
?>

<style>
.int-card { background:#16213e; border:1px solid #0f3460; border-radius:10px; padding:20px; margin-bottom:16px; }
.int-input { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:20px; padding:10px 14px; outline:none; }
.int-input:focus { border-color:#e94560; }
.int-select { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:16px; padding:10px 12px; outline:none; width:100%; }
.int-select:focus { border-color:#e94560; }
.int-label { font-size:12px; color:#aaa; display:block; margin-bottom:6px; }
.int-lager-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #1a1a3e; font-size:14px; }
.int-lager-row:last-child { border-bottom:none; }
.int-menge { font-weight:700; color:#4caf50; }
.int-menge-null { color:#555; }
</style>

<div style="max-width:1000px;margin:0 auto">

    <!-- SCAN-BEREICH -->
    <div class="int-card">
        <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">🔍 Artikel suchen</div>
        <div style="display:flex;gap:10px;align-items:center">
            <input type="text" id="ean-input" class="int-input" style="flex:1"
                   placeholder="EAN scannen oder Artikelnummer eingeben…"
                   autofocus autocomplete="off">
            <button class="pp-btn pp-btn-secondary" style="padding:10px 20px;font-size:16px" onclick="artikelSuchen()">→</button>
        </div>
        <div id="suche-fehler" style="color:#ef5350;font-size:13px;margin-top:8px;display:none"></div>
    </div>

    <!-- ARTIKEL-INFO (nach Scan) -->
    <div id="artikel-bereich" style="display:none">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">

            <!-- Links: Artikel + Lagerstand -->
            <div>
                <div class="int-card">
                    <div style="display:flex;gap:14px;align-items:center">
                        <div id="artikel-bild" style="width:80px;height:80px;border-radius:8px;background:#0a0a1a;border:1px solid #0f3460;display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0">📦</div>
                        <div>
                            <div id="artikel-name" style="font-size:18px;font-weight:700"></div>
                            <div id="artikel-nr" style="font-size:13px;color:#aaa;margin-top:4px"></div>
                            <div id="artikel-ean" style="font-size:12px;color:#6c8ebf;margin-top:2px"></div>
                        </div>
                    </div>
                </div>

                <div class="int-card">
                    <div style="font-size:14px;font-weight:700;color:#aaa;margin-bottom:10px">Lagerstand</div>
                    <div id="lager-liste"></div>
                </div>
            </div>

            <!-- Rechts: Aktionen -->
            <div>

                <!-- Lagerumbuchung -->
                <div class="int-card">
                    <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">📦 Lagerumbuchung</div>
                    <input type="hidden" id="u-artikel-id">
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <div>
                            <label class="int-label">Von Lager</label>
                            <select id="u-von-lager" class="int-select" onchange="vonLagerGewaehlt()">
                                <?php foreach ($alleLager as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="u-von-bestand" style="font-size:12px;color:#aaa;margin-top:4px"></div>
                        </div>
                        <div>
                            <label class="int-label">Zu Lager</label>
                            <select id="u-zu-lager" class="int-select">
                                <?php foreach ($alleLager as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="int-label">Menge</label>
                            <input type="number" id="u-menge" class="int-input" style="width:100%;font-size:24px;text-align:center" min="1" step="1" placeholder="0">
                        </div>
                        <button class="pp-btn pp-btn-primary" style="width:100%;font-size:18px;padding:14px" onclick="umbuchenSpeichern()">
                            ↔ Umbuchen
                        </button>
                        <div id="u-fehler" style="color:#ef5350;font-size:13px;display:none"></div>
                        <div id="u-erfolg" style="color:#4caf50;font-size:13px;display:none"></div>
                    </div>
                </div>

                <!-- Direkte Zustandsänderung -->
                <div id="zustandaend-bereich" class="int-card">
                    <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">🏷 Zustand setzen</div>
                    <div style="font-size:13px;color:#aaa;margin-bottom:12px">
                        Aktueller Zustand: <span id="za-aktuell" style="color:#eee;font-weight:600"></span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <div>
                            <label class="int-label">Neuer Zustand</label>
                            <select id="za-zustand" class="int-select">
                                <option value="neu">Neu</option>
                                <option value="gebraucht">Gebraucht</option>
                                <option value="generalueberholt">Generalüberholt</option>
                                <option value="beschaedigt">Beschädigt</option>
                                <option value="retour">Retour</option>
                                <option value="demo">Demo</option>
                                <option value="muster">Muster</option>
                                <option value="ausstellungsstueck">Ausstellungsstück</option>
                            </select>
                        </div>
                        <button class="pp-btn pp-btn-secondary" style="width:100%;font-size:18px;padding:14px" onclick="zustandAendernSpeichern()">
                            ✎ Zustand speichern
                        </button>
                        <div id="za-fehler" style="color:#ef5350;font-size:13px;display:none"></div>
                        <div id="za-erfolg" style="color:#4caf50;font-size:13px;display:none"></div>
                    </div>
                </div>

                <!-- Zustandsumbuchung (nur wenn Zustandsartikel vorhanden) -->
                <div id="zustand-bereich" class="int-card" style="display:none">
                    <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">🔁 Zustand umbuchen</div>
                    <div style="font-size:13px;color:#aaa;margin-bottom:12px">
                        Einheiten vom Neuzustand auf einen B-Ware-Artikel umbuchen
                    </div>
                    <input type="hidden" id="z-von-artikel-id">
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <div>
                            <label class="int-label">Zu Zustandsartikel</label>
                            <select id="z-zu-artikel" class="int-select"></select>
                        </div>
                        <div>
                            <label class="int-label">Zu Lager</label>
                            <select id="z-lager" class="int-select">
                                <?php foreach ($alleLager as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="int-label">Menge</label>
                            <input type="number" id="z-menge" class="int-input" style="width:100%;font-size:24px;text-align:center" min="1" step="1" placeholder="0">
                        </div>
                        <div>
                            <label class="int-label">Von Lager</label>
                            <select id="z-von-lager" class="int-select">
                                <?php foreach ($alleLager as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="pp-btn pp-btn-warning" style="width:100%;font-size:18px;padding:14px" onclick="zustandUmbuchenSpeichern()">
                            → Umbuchen
                        </button>
                        <div id="z-fehler" style="color:#ef5350;font-size:13px;display:none"></div>
                        <div id="z-erfolg" style="color:#4caf50;font-size:13px;display:none"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<script>
var artikelBestand = {};
var zaArtikelId = null;

var zustandLabels = {
    neu: 'Neu', gebraucht: 'Gebraucht', generalueberholt: 'Generalüberholt',
    beschaedigt: 'Beschädigt', retour: 'Retour', demo: 'Demo',
    muster: 'Muster', ausstellungsstueck: 'Ausstellungsstück'
};

document.getElementById('ean-input').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); artikelSuchen(); }
});

async function artikelSuchen() {
    var q      = document.getElementById('ean-input').value.trim();
    var fehler = document.getElementById('suche-fehler');
    if (!q) return;
    fehler.style.display = 'none';

    var r    = await fetch('/mealana/packplatz/intern/artikel_ajax.php?q=' + encodeURIComponent(q));
    var data = await r.json();

    if (!data.gefunden) {
        fehler.textContent = 'Artikel nicht gefunden: ' + q;
        fehler.style.display = 'block';
        document.getElementById('artikel-bereich').style.display = 'none';
        return;
    }

    var a = data.artikel;
    document.getElementById('u-artikel-id').value = a.id;
    document.getElementById('z-von-artikel-id').value = a.id;
    document.getElementById('artikel-name').textContent = a.name;
    document.getElementById('artikel-nr').textContent = a.artikelnummer;
    document.getElementById('artikel-ean').textContent = a.ean ? 'EAN: ' + a.ean : '';

    if (a.hauptbild) {
        document.getElementById('artikel-bild').innerHTML =
            '<img src="/mealana/uploads/artikel/' + a.id + '/' + escH(a.hauptbild) + '" style="width:80px;height:80px;object-fit:contain;border-radius:8px" onerror="this.parentElement.innerHTML=\'📦\'">';
    } else {
        document.getElementById('artikel-bild').innerHTML = '📦';
    }

    // Lagerstand anzeigen
    artikelBestand = {};
    var lagerHtml = '';
    (data.bestand || []).forEach(function (lb) {
        artikelBestand[lb.lager_id] = parseFloat(lb.bestand);
        lagerHtml += '<div class="int-lager-row">' +
            '<span>' + escH(lb.lager_name) + '</span>' +
            '<span class="' + (lb.bestand > 0 ? 'int-menge' : 'int-menge-null') + '">' + parseFloat(lb.bestand) + '</span>' +
            '</div>';
    });
    document.getElementById('lager-liste').innerHTML = lagerHtml || '<div style="color:#555;font-size:13px">Kein Lagerbestand</div>';

    vonLagerGewaehlt();

    // Zustandsartikel
    if (data.zustandsartikel && data.zustandsartikel.length > 0) {
        var opts = '';
        data.zustandsartikel.forEach(function (z) {
            opts += '<option value="' + z.id + '">' + escH(z.zustand_label) + ' — ' + escH(z.name) + '</option>';
        });
        document.getElementById('z-zu-artikel').innerHTML = opts;
        document.getElementById('zustand-bereich').style.display = 'block';
    } else {
        document.getElementById('zustand-bereich').style.display = 'none';
    }

    // Zustandsänderung
    zaArtikelId = a.id;
    var aktuell = a.zustand || 'neu';
    document.getElementById('za-aktuell').textContent = zustandLabels[aktuell] || aktuell;
    document.getElementById('za-zustand').value = aktuell;
    document.getElementById('za-fehler').style.display = 'none';
    document.getElementById('za-erfolg').style.display = 'none';

    document.getElementById('artikel-bereich').style.display = 'block';
    document.getElementById('u-menge').focus();
}

function vonLagerGewaehlt() {
    var vonId  = parseInt(document.getElementById('u-von-lager').value);
    var bestand = artikelBestand[vonId] || 0;
    document.getElementById('u-von-bestand').textContent = 'Bestand: ' + bestand;
    document.getElementById('u-menge').max = bestand;
}

async function umbuchenSpeichern() {
    var artikelId = document.getElementById('u-artikel-id').value;
    var vonLager  = document.getElementById('u-von-lager').value;
    var zuLager   = document.getElementById('u-zu-lager').value;
    var menge     = parseFloat(document.getElementById('u-menge').value);
    var fehlerEl  = document.getElementById('u-fehler');
    var erfolgEl  = document.getElementById('u-erfolg');

    fehlerEl.style.display = 'none'; erfolgEl.style.display = 'none';
    if (!menge || menge <= 0) { fehlerEl.textContent = 'Bitte Menge eingeben.'; fehlerEl.style.display = 'block'; return; }

    var body = new FormData();
    body.append('artikel_id', artikelId);
    body.append('von_lager_id', vonLager);
    body.append('zu_lager_id', zuLager);
    body.append('menge', menge);

    var r    = await fetch('/mealana/packplatz/intern/umbuchen.php', { method: 'POST', body });
    var data = await r.json();
    if (data.erfolg) {
        erfolgEl.textContent = '✓ Umgebucht! ' + menge + ' Stk.';
        erfolgEl.style.display = 'block';
        document.getElementById('u-menge').value = '';
        artikelSuchen(); // Lagerstand aktualisieren
    } else {
        fehlerEl.textContent = data.fehler || 'Fehler beim Umbuchen.';
        fehlerEl.style.display = 'block';
    }
}

async function zustandUmbuchenSpeichern() {
    var vonArtikelId = document.getElementById('z-von-artikel-id').value;
    var zuArtikelId  = document.getElementById('z-zu-artikel').value;
    var vonLagerId   = document.getElementById('z-von-lager').value;
    var zuLagerId    = document.getElementById('z-lager').value;
    var menge        = parseFloat(document.getElementById('z-menge').value);
    var fehlerEl     = document.getElementById('z-fehler');
    var erfolgEl     = document.getElementById('z-erfolg');

    fehlerEl.style.display = 'none'; erfolgEl.style.display = 'none';
    if (!menge || menge <= 0) { fehlerEl.textContent = 'Bitte Menge eingeben.'; fehlerEl.style.display = 'block'; return; }

    var body = new FormData();
    body.append('von_artikel_id', vonArtikelId);
    body.append('zu_artikel_id', zuArtikelId);
    body.append('von_lager_id', vonLagerId);
    body.append('zu_lager_id', zuLagerId);
    body.append('menge', menge);

    var r    = await fetch('/mealana/packplatz/intern/zustand_umbuchen.php', { method: 'POST', body });
    var data = await r.json();
    if (data.erfolg) {
        erfolgEl.textContent = '✓ Umgebucht!';
        erfolgEl.style.display = 'block';
        document.getElementById('z-menge').value = '';
        artikelSuchen();
    } else {
        fehlerEl.textContent = data.fehler || 'Fehler beim Umbuchen.';
        fehlerEl.style.display = 'block';
    }
}

async function zustandAendernSpeichern() {
    var fehlerEl = document.getElementById('za-fehler');
    var erfolgEl = document.getElementById('za-erfolg');
    fehlerEl.style.display = 'none'; erfolgEl.style.display = 'none';

    if (!zaArtikelId) { fehlerEl.textContent = 'Kein Artikel geladen.'; fehlerEl.style.display = 'block'; return; }

    var neuerZustand = document.getElementById('za-zustand').value;
    var body = new FormData();
    body.append('artikel_id', zaArtikelId);
    body.append('zustand', neuerZustand);

    var r    = await fetch('/mealana/packplatz/intern/zustand_aendern.php', { method: 'POST', body });
    var data = await r.json();
    if (data.erfolg) {
        var label = zustandLabels[neuerZustand] || neuerZustand;
        document.getElementById('za-aktuell').textContent = label;
        erfolgEl.textContent = '✓ Zustand gesetzt: ' + label;
        erfolgEl.style.display = 'block';
    } else {
        fehlerEl.textContent = data.fehler || 'Fehler beim Speichern.';
        fehlerEl.style.display = 'block';
    }
}

function escH(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
