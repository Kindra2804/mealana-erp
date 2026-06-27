<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$service    = new KassenService();
$lagerSvc   = new LagerService();
$kasseInfo  = $service->getKasse(1);
$lagerId    = (int)($kasseInfo['lager_id'] ?? 1);
$kasseId    = (int)($kasseInfo['id'] ?? 1);

$pageTitle    = 'Kassieren';
$activeKasseNav = 'bon';
require_once __DIR__ . '/shell_top.php';
?>

<style>
#bon-layout {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 16px;
  height: calc(100vh - 100px);
}
#scan-bereich { display: flex; flex-direction: column; gap: 12px; }
#warenkorb-panel {
  display: flex; flex-direction: column; gap: 0;
  background: #0d1b2a;
  border: 1px solid #1a3a5c;
  border-radius: 10px;
  overflow: hidden;
}
#warenkorb-liste { flex: 1; overflow-y: auto; max-height: 46vh; }
#warenkorb-table { width: 100%; border-collapse: collapse; }
#warenkorb-table th { background: #071018; color: #666; font-size: 11px; padding: 7px 10px; text-transform: uppercase; }
#warenkorb-table td { padding: 9px 10px; border-bottom: 1px solid #071018; font-size: 14px; vertical-align: middle; }
#warenkorb-table td:last-child { text-align: right; white-space: nowrap; }
.wk-remove { background: none; border: none; color: #c0392b; cursor: pointer; font-size: 16px; padding: 0 4px; }
.wk-remove:hover { color: #e74c3c; }
#warenkorb-footer { padding: 14px 16px; border-top: 2px solid #1a3a5c; }
#warenkorb-gesamt { font-size: 28px; font-weight: 900; color: #e67e22; margin-bottom: 12px; }
#warenkorb-leer { padding: 40px; text-align: center; color: #444; font-size: 14px; }

.pay-btn {
  width: 100%; padding: 16px; font-size: 17px; font-weight: 700;
  border: none; border-radius: 8px; cursor: pointer; margin-bottom: 8px;
}
.pay-btn-bar   { background: #27ae60; color: #fff; }
.pay-btn-bar:hover   { background: #1e8449; }
.pay-btn-karte { background: #2980b9; color: #fff; }
.pay-btn-karte:hover { background: #1a5276; }
.pay-btn-gs    { background: #8e44ad; color: #fff; }
.pay-btn-gs:hover    { background: #6c3483; }
.pay-btn-kombi { background: #16a085; color: #fff; }
.pay-btn-kombi:hover { background: #0e6655; }
.pay-btn:disabled { opacity:.35; cursor:not-allowed; }

/* Scan-Artikel-Info */
#scan-info-box {
  background: #071828;
  border: 2px solid #1a3a5c;
  border-radius: 10px;
  padding: 16px;
  min-height: 90px;
  display: flex; gap: 14px; align-items: center;
}
#scan-bild { width:80px;height:80px;object-fit:contain;border-radius:6px;background:#0a1520; }
#scan-kinder-liste { margin-top: 10px; }
.kind-chip {
  display: inline-block; background: #0d2a4a; border: 1px solid #1a4a7c;
  border-radius: 6px; padding: 6px 12px; margin: 3px; cursor: pointer;
  font-size: 13px; color: #ccc;
}
.kind-chip:hover { background: #1a4a7c; color: #fff; }

/* Scan-Bar */
#scan-bar {
  display: flex; gap: 10px; align-items: center;
  background: #0d1b2a; border: 1px solid #1a3a5c;
  border-radius: 10px; padding: 12px 16px;
}
#vorwahl {
  background: #071018; border: 2px solid #1a3a5c; border-radius: 6px;
  color: #e67e22; font-size: 22px; font-weight: 700;
  padding: 8px 6px; width: 60px; text-align: center; outline: none;
}
#vorwahl:focus { border-color: #e67e22; }
#scan-input {
  background: #071018; border: 2px solid #1a3a5c; border-radius: 8px;
  color: #fff; font-size: 20px; padding: 10px 14px; flex: 1; outline: none;
}
#scan-input:focus { border-color: #e67e22; }

/* Rabatt-Zeile im Warenkorb-Footer */
#rabatt-row { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }
#rabatt-input {
  background: #071018; border: 1px solid #1a3a5c; border-radius: 6px;
  color: #fff; font-size: 14px; padding: 6px 10px; width: 80px; outline: none;
}
#rabatt-input:focus { border-color: #e67e22; }

/* Payment overlay */
.pay-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.85); z-index: 300;
  align-items: center; justify-content: center;
}
.pay-overlay.aktiv { display: flex; }
.pay-overlay-box {
  background: #0d1b2a; border: 2px solid #e67e22;
  border-radius: 16px; padding: 36px; min-width: 380px; max-width: 500px; width: 90%;
}
.pay-total { font-size: 36px; font-weight: 900; color: #e67e22; text-align: center; margin-bottom: 24px; }
.pay-label { font-size: 13px; color: #888; margin-bottom: 6px; }
.pay-big-input {
  background: #071018; border: 2px solid #1a3a5c; border-radius: 8px;
  color: #fff; font-size: 28px; font-weight: 700; padding: 12px 16px;
  width: 100%; text-align: right; outline: none;
}
.pay-big-input:focus { border-color: #e67e22; }
.pay-rueckgeld { font-size: 22px; font-weight: 700; color: #27ae60; text-align: right; margin: 12px 0; }

/* Vater-Auswahl Overlay */
#overlay-vater { }

/* Divers-Overlay */
#overlay-divers { }
</style>

<div id="bon-layout">

  <!-- LINKE SPALTE: SCAN + INFO -->
  <div id="scan-bereich">

    <div id="scan-bar">
      <div style="font-size:13px;color:#888">Menge</div>
      <input type="number" id="vorwahl" value="1" min="1" max="999">
      <input type="text" id="scan-input" placeholder="EAN oder Artikelnummer scannen…" autocomplete="off" autofocus>
      <button class="ks-btn ks-btn-secondary" onclick="scannenOK()" style="white-space:nowrap">OK</button>
    </div>

    <div id="scan-info-box">
      <div id="scan-info-leer" style="color:#444;font-size:14px">Scan-Ergebnis erscheint hier…</div>
      <div id="scan-info-inhalt" style="display:none;width:100%">
        <div style="display:flex;gap:14px;align-items:center">
          <img id="scan-bild" src="" alt="" style="display:none">
          <div id="scan-bild-placeholder" style="width:80px;height:80px;border-radius:6px;background:#071018;border:1px dashed #333;display:flex;align-items:center;justify-content:center;font-size:28px;color:#333">📦</div>
          <div style="flex:1">
            <div id="scan-name" style="font-size:18px;font-weight:700;color:#eee"></div>
            <div id="scan-nr" style="font-size:12px;color:#888;margin-top:2px"></div>
            <div id="scan-preis" style="font-size:22px;font-weight:700;color:#e67e22;margin-top:4px"></div>
            <div id="scan-bestand" style="font-size:12px;color:#888;margin-top:2px"></div>
            <div id="scan-charge" style="font-size:11px;color:#64b5f6;margin-top:2px;display:none">Charge: <span id="scan-charge-val"></span></div>
          </div>
        </div>
        <div id="scan-kinder-liste" style="display:none;margin-top:12px">
          <div style="font-size:12px;color:#888;margin-bottom:6px">Bitte Variante wählen:</div>
          <div id="scan-kinder-chips"></div>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="ks-btn ks-btn-secondary" onclick="diversDialog()">+ Freier Preis</button>
      <button class="ks-btn ks-btn-secondary" onclick="mitgebenDialog()">↗ Mitgeben</button>
      <button class="ks-btn ks-btn-secondary" onclick="warenkorbLeeren()" id="btn-leeren" style="margin-left:auto;display:none">🗑 Leeren</button>
    </div>

    <!-- Feedback -->
    <div id="scan-feedback"></div>

  </div>

  <!-- RECHTE SPALTE: WARENKORB + ZAHLUNG -->
  <div id="warenkorb-panel">
    <div style="padding:12px 16px;border-bottom:1px solid #071018;font-size:13px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px">
      Warenkorb
    </div>
    <div id="warenkorb-liste">
      <div id="warenkorb-leer">Noch keine Artikel</div>
      <table id="warenkorb-table" style="display:none">
        <thead><tr><th>Artikel</th><th>Mge</th><th>Preis</th><th></th></tr></thead>
        <tbody id="warenkorb-body"></tbody>
      </table>
    </div>
    <div id="warenkorb-footer">
      <div id="rabatt-row" style="display:none">
        <div style="font-size:13px;color:#888;white-space:nowrap">Gesamt-Rabatt:</div>
        <input type="number" id="rabatt-input" value="0" min="0" max="100" step="1" oninput="aktualisiereAnzeige()">
        <div style="color:#888;font-size:13px">%</div>
        <button onclick="document.getElementById('rabatt-input').value=0;aktualisiereAnzeige();" style="background:none;border:none;color:#888;cursor:pointer;font-size:16px">✕</button>
      </div>
      <div id="warenkorb-gesamt">€ 0,00</div>
      <div id="warenkorb-steuer" style="font-size:12px;color:#666;margin-bottom:12px"></div>

      <button class="pay-btn pay-btn-bar"   id="btn-bar"   onclick="zahlenBar()"   disabled>💶 Bar</button>
      <button class="pay-btn pay-btn-karte" id="btn-karte" onclick="zahlenKarte()" disabled>💳 Karte extern</button>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <button class="pay-btn pay-btn-gs"    id="btn-gs"    onclick="zahlenGutschein()" disabled style="font-size:15px">🎁 Gutschein</button>
        <button class="pay-btn pay-btn-kombi" id="btn-kombi" onclick="zahlenKombi()"     disabled style="font-size:15px">💱 Kombi</button>
      </div>
    </div>
  </div>

</div>

<!-- ── Overlay: BAR ───────────────────────────────────────────────────── -->
<div class="pay-overlay" id="overlay-bar">
  <div class="pay-overlay-box">
    <div class="pay-total" id="bar-total-anzeige">€ 0,00</div>
    <div class="pay-label">Gegeben</div>
    <input class="pay-big-input" type="number" id="bar-gegeben" step="0.01" min="0" placeholder="0,00" oninput="berechneRueckgeld()">
    <div class="pay-rueckgeld" id="bar-rueckgeld">Rückgeld: € 0,00</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px">
      <div id="bar-schnell" style="display:grid;grid-template-columns:1fr 1fr;gap:6px"></div>
      <button class="ks-btn ks-btn-success ks-btn-lg" id="btn-bar-ok" onclick="bonAbschliessenBar()" disabled>✓ Abschließen</button>
    </div>
    <button class="ks-btn ks-btn-secondary" style="width:100%;margin-top:12px" onclick="overlaySchliessen('overlay-bar')">Abbrechen</button>
  </div>
</div>

<!-- ── Overlay: KARTE ────────────────────────────────────────────────── -->
<div class="pay-overlay" id="overlay-karte">
  <div class="pay-overlay-box">
    <div class="pay-total" id="karte-total-anzeige">€ 0,00</div>
    <div style="text-align:center;font-size:17px;color:#aaa;margin-bottom:28px">
      💳 Bitte Zahlung am Terminal<br>(<span id="karte-terminal-hint">Bankomat oder SumUp</span>) abschließen.
    </div>
    <button class="ks-btn ks-btn-success ks-btn-lg" style="width:100%;margin-bottom:10px" onclick="bonAbschliessenKarte()">✓ Terminal bestätigt</button>
    <button class="ks-btn ks-btn-secondary" style="width:100%" onclick="overlaySchliessen('overlay-karte')">Abbrechen</button>
  </div>
</div>

<!-- ── Overlay: GUTSCHEIN ────────────────────────────────────────────── -->
<div class="pay-overlay" id="overlay-gutschein">
  <div class="pay-overlay-box">
    <div class="pay-total" id="gs-total-anzeige">€ 0,00</div>
    <div class="pay-label">Gutschein-Code</div>
    <input class="pay-big-input" type="text" id="gs-code" placeholder="Code eingeben…" style="font-size:20px;text-align:left" onkeyup="gsCodeGeaendert()">
    <div id="gs-info" style="min-height:30px;margin-top:10px;font-size:14px;color:#888"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px">
      <button class="ks-btn ks-btn-success ks-btn-lg" id="btn-gs-ok" onclick="bonAbschliessenGutschein()" disabled>✓ Einlösen</button>
      <button class="ks-btn ks-btn-secondary" onclick="overlaySchliessen('overlay-gutschein')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- ── Overlay: KOMBI ────────────────────────────────────────────────── -->
<div class="pay-overlay" id="overlay-kombi">
  <div class="pay-overlay-box">
    <div class="pay-total" id="kombi-total-anzeige">€ 0,00</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div>
        <div class="pay-label">Karte (€)</div>
        <input class="pay-big-input" type="number" id="kombi-karte" step="0.01" min="0" placeholder="0,00" oninput="kombiBerechnen()" style="font-size:22px">
      </div>
      <div>
        <div class="pay-label">Bar (€)</div>
        <input class="pay-big-input" type="number" id="kombi-bar" step="0.01" min="0" placeholder="0,00" oninput="kombiBerechnen()" style="font-size:22px">
      </div>
    </div>
    <div class="pay-rueckgeld" id="kombi-diff" style="color:#888"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
      <button class="ks-btn ks-btn-success" id="btn-kombi-ok" onclick="bonAbschliessenKombi()" disabled>✓ Abschließen</button>
      <button class="ks-btn ks-btn-secondary" onclick="overlaySchliessen('overlay-kombi')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- ── Overlay: VATER-KINDER ────────────────────────────────────────── -->
<div class="pay-overlay" id="overlay-vater">
  <div class="pay-overlay-box" style="max-width:600px;max-height:80vh;overflow-y:auto">
    <div class="ks-overlay-titel" id="overlay-vater-titel">Variante wählen</div>
    <div id="overlay-vater-kinder"></div>
    <button class="ks-btn ks-btn-secondary" style="width:100%;margin-top:16px" onclick="overlaySchliessen('overlay-vater')">Abbrechen</button>
  </div>
</div>

<!-- ── Overlay: DIVERS-ARTIKEL ──────────────────────────────────────── -->
<div class="pay-overlay" id="overlay-divers">
  <div class="pay-overlay-box">
    <div class="ks-overlay-titel">Freier Preis-Artikel</div>
    <div class="pay-label">Bezeichnung</div>
    <input class="pay-big-input" type="text" id="divers-name" placeholder="z.B. Strickberatung" style="font-size:16px;text-align:left;margin-bottom:14px" oninput="diversPruefen()">
    <div class="pay-label">Bruttopreis (€)</div>
    <input class="pay-big-input" type="number" id="divers-preis" step="0.01" min="0" placeholder="0,00" oninput="diversPruefen()">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px">
      <div>
        <div class="pay-label">Steuer</div>
        <select class="ks-select" id="divers-steuer">
          <option value="20">20 %</option>
          <option value="10">10 %</option>
          <option value="0">0 % (steuerbefreit)</option>
        </select>
      </div>
      <div style="display:flex;align-items:flex-end">
        <button class="ks-btn ks-btn-success" id="btn-divers-ok" onclick="diversHinzufuegen()" disabled style="width:100%">+ Hinzufügen</button>
      </div>
    </div>
    <button class="ks-btn ks-btn-secondary" style="width:100%;margin-top:10px" onclick="overlaySchliessen('overlay-divers')">Abbrechen</button>
  </div>
</div>

<!-- ── Overlay: MITGEBEN ────────────────────────────────────────────── -->
<div class="pay-overlay" id="overlay-mitgeben">
  <div class="pay-overlay-box">
    <div class="ks-overlay-titel">↗ Mitgeben (Offene Auswahl)</div>
    <div class="pay-label">Kundenname (optional)</div>
    <input class="pay-big-input" type="text" id="mg-name" placeholder="Name oder leer lassen" style="font-size:16px;text-align:left;margin-bottom:14px">
    <div class="pay-label">Rückgabe bis (optional)</div>
    <input class="pay-big-input" type="date" id="mg-datum" style="font-size:16px;text-align:left;margin-bottom:14px">
    <div style="font-size:13px;color:#888;margin-bottom:14px">
      Die aktuellen Warenkorb-Artikel werden als "mitgegeben" gebucht und sofort aus dem Lager ausgebucht.
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <button class="ks-btn ks-btn-success" onclick="mitgebenSpeichern()">↗ Mitgeben</button>
      <button class="ks-btn ks-btn-secondary" onclick="overlaySchliessen('overlay-mitgeben')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- ── Overlay: LADE-SPINNER ─────────────────────────────────────────── -->
<div class="pay-overlay" id="overlay-laden">
  <div style="text-align:center">
    <div style="width:56px;height:56px;border:5px solid #1a3a5c;border-top-color:#e67e22;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 16px"></div>
    <div style="color:#eee;font-size:16px">Bon wird gespeichert…</div>
  </div>
</div>
<style>@keyframes spin { to { transform:rotate(360deg) } }</style>

<script>
var KASSE_ID  = <?= $kasseId ?>;
var LAGER_ID  = <?= $lagerId ?>;
var warenkorb = [];

// ── Scan ──────────────────────────────────────────────────────────────────
document.getElementById('scan-input').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') scannenOK();
});

function scannenOK() {
    var code = document.getElementById('scan-input').value.trim();
    if (!code) return;
    scanFeedback('');
    fetch('/mealana/kasse/ajax_artikel.php?code=' + encodeURIComponent(code) + '&lager_id=' + LAGER_ID)
        .then(r => r.json())
        .then(data => {
            if (!data.erfolg) {
                scanFeedback('❌ ' + (data.fehler || 'Artikel nicht gefunden'), 'fehler');
                document.getElementById('scan-input').select();
                return;
            }
            if (data.typ === 'vater') {
                zeigeVaterAuswahl(data);
            } else {
                artikelHinzufuegen(data);
            }
            document.getElementById('scan-input').value = '';
            document.getElementById('scan-input').focus();
        })
        .catch(() => scanFeedback('❌ Verbindungsfehler', 'fehler'));
}

function zeigeVaterAuswahl(vater) {
    document.getElementById('overlay-vater-titel').textContent = vater.bezeichnung + ' — Variante wählen';
    var html = '';
    (vater.kinder || []).forEach(function(k) {
        var bestandFarbe = k.lagerbestand > 0 ? '#4caf50' : '#ef5350';
        html += '<div class="kind-chip" onclick=\'kindGewaehlt(' + JSON.stringify(k) + ')\'>'
            + htmlEsc(k.bezeichnung)
            + '<br><span style="font-size:11px;color:#aaa">€ ' + parseFloat(k.brutto_vk).toFixed(2).replace('.',',')
            + ' · <span style="color:' + bestandFarbe + '">Bestand: ' + k.lagerbestand + '</span></span>'
            + '</div>';
    });
    document.getElementById('overlay-vater-kinder').innerHTML = html || '<p style="color:#888">Keine Varianten verfügbar.</p>';
    document.getElementById('overlay-vater').classList.add('aktiv');
}

function kindGewaehlt(kind) {
    overlaySchliessen('overlay-vater');
    artikelHinzufuegen(kind);
}

function artikelHinzufuegen(a) {
    var menge = parseInt(document.getElementById('vorwahl').value) || 1;
    var preis = parseFloat(a.brutto_vk) || 0;
    if (!preis) {
        scanFeedback('⚠ Kein Preis hinterlegt für: ' + a.bezeichnung, 'fehler');
        return;
    }
    // Bestandsprüfung (Warnung, kein Stopp)
    if (a.lagerbestand <= 0 && !a.ueberverkauf_erlaubt) {
        if (!confirm('⚠ Kein Lagerbestand vorhanden.\n\nTrotzdem hinzufügen?')) return;
    }

    var existierend = warenkorb.findIndex(p => p.artikel_id === a.id && p.charge === (a.fifo_charge || null));
    if (existierend >= 0) {
        warenkorb[existierend].menge += menge;
    } else {
        warenkorb.push({
            artikel_id: a.id,
            bezeichnung: a.bezeichnung,
            ean: a.ean || null,
            menge: menge,
            einzelpreis_brutto: preis,
            steuer_prozent: parseFloat(a.steuer_prozent) || 20,
            rabatt_prozent: 0,
            charge: a.fifo_charge || null,
            istDivers: false
        });
    }
    zeigeArtikelInfo(a);
    aktualisiereWarenkorb();
    document.getElementById('vorwahl').value = 1;
    scanFeedback('✓ ' + a.bezeichnung + ' (Menge: ' + menge + ')', 'ok');
}

function zeigeArtikelInfo(a) {
    document.getElementById('scan-info-leer').style.display = 'none';
    document.getElementById('scan-info-inhalt').style.display = 'block';
    document.getElementById('scan-name').textContent = a.bezeichnung;
    document.getElementById('scan-nr').textContent   = 'Art.-Nr.: ' + (a.artikelnummer || '—') + (a.ean ? '  EAN: ' + a.ean : '');
    document.getElementById('scan-preis').textContent = '€ ' + parseFloat(a.brutto_vk).toFixed(2).replace('.', ',');
    var bestandEl = document.getElementById('scan-bestand');
    var bestand = parseInt(a.lagerbestand) || 0;
    bestandEl.textContent = 'Bestand: ' + bestand;
    bestandEl.style.color = bestand > 0 ? '#4caf50' : '#ef5350';

    if (a.fifo_charge) {
        document.getElementById('scan-charge').style.display = 'block';
        document.getElementById('scan-charge-val').textContent = a.fifo_charge;
    } else {
        document.getElementById('scan-charge').style.display = 'none';
    }

    var bildEl = document.getElementById('scan-bild');
    var placeholder = document.getElementById('scan-bild-placeholder');
    if (a.bild_dateiname) {
        bildEl.src = '/mealana/storage/bilder/' + (a.id || '') + '/' + a.bild_dateiname;
        bildEl.style.display = 'block';
        placeholder.style.display = 'none';
    } else {
        bildEl.style.display = 'none';
        placeholder.style.display = 'flex';
    }
    document.getElementById('scan-kinder-liste').style.display = 'none';
}

// ── Warenkorb anzeigen ────────────────────────────────────────────────────
function aktualisiereWarenkorb() {
    var leer = document.getElementById('warenkorb-leer');
    var tabelle = document.getElementById('warenkorb-table');
    var body = document.getElementById('warenkorb-body');
    var btnLeeren = document.getElementById('btn-leeren');

    if (warenkorb.length === 0) {
        leer.style.display = 'block';
        tabelle.style.display = 'none';
        btnLeeren.style.display = 'none';
    } else {
        leer.style.display = 'none';
        tabelle.style.display = 'table';
        btnLeeren.style.display = 'inline-block';
        var html = '';
        warenkorb.forEach(function(p, i) {
            var rabattFaktor = 1 - (p.rabatt_prozent / 100);
            var zeile = (p.menge * p.einzelpreis_brutto * rabattFaktor).toFixed(2).replace('.', ',');
            html += '<tr>';
            html += '<td><strong>' + htmlEsc(p.bezeichnung) + '</strong>'
                + (p.charge ? '<br><span style="font-size:11px;color:#64b5f6">Charge: ' + htmlEsc(p.charge) + '</span>' : '')
                + (p.rabatt_prozent > 0 ? '<br><span style="font-size:11px;color:#e67e22">-' + p.rabatt_prozent + '%</span>' : '')
                + '</td>';
            html += '<td style="text-align:center"><button class="wk-remove" onclick="mengeMinus(' + i + ')">-</button>'
                + ' <strong>' + p.menge + '</strong> '
                + '<button class="wk-remove" onclick="mengePlus(' + i + ')" style="color:#27ae60">+</button></td>';
            html += '<td>€ ' + zeile + '</td>';
            html += '<td><button class="wk-remove" onclick="positionEntfernen(' + i + ')" title="Entfernen">✕</button></td>';
            html += '</tr>';
        });
        body.innerHTML = html;
    }
    aktualisiereAnzeige();
    document.getElementById('rabatt-row').style.display = warenkorb.length > 0 ? 'flex' : 'none';
}

function aktualisiereAnzeige() {
    var globalRabatt = parseFloat(document.getElementById('rabatt-input').value) || 0;
    var gesamt = 0, steuer20 = 0, steuer10 = 0;
    warenkorb.forEach(function(p) {
        var posRabatt = 1 - Math.max(p.rabatt_prozent, globalRabatt) / 100;
        var netto = p.menge * p.einzelpreis_brutto * posRabatt / (1 + p.steuer_prozent / 100);
        gesamt += p.menge * p.einzelpreis_brutto * posRabatt;
        if (p.steuer_prozent === 20) steuer20 += netto * 0.2;
        else if (p.steuer_prozent === 10) steuer10 += netto * 0.1;
    });
    document.getElementById('warenkorb-gesamt').textContent = '€ ' + gesamt.toFixed(2).replace('.', ',');
    var stInfo = [];
    if (steuer20 > 0) stInfo.push('USt 20%: € ' + steuer20.toFixed(2).replace('.', ','));
    if (steuer10 > 0) stInfo.push('USt 10%: € ' + steuer10.toFixed(2).replace('.', ','));
    document.getElementById('warenkorb-steuer').textContent = stInfo.join('  ');
    var hatArtikel = warenkorb.length > 0;
    ['btn-bar','btn-karte','btn-gs','btn-kombi'].forEach(function(id) {
        document.getElementById(id).disabled = !hatArtikel;
    });
}

function getGesamtBetrag() {
    var globalRabatt = parseFloat(document.getElementById('rabatt-input').value) || 0;
    var gesamt = 0;
    warenkorb.forEach(function(p) {
        var posRabatt = 1 - Math.max(p.rabatt_prozent, globalRabatt) / 100;
        gesamt += p.menge * p.einzelpreis_brutto * posRabatt;
    });
    return Math.round(gesamt * 100) / 100;
}

function positionEntfernen(i) {
    warenkorb.splice(i, 1);
    aktualisiereWarenkorb();
}
function mengeMinus(i) { if (warenkorb[i].menge > 1) { warenkorb[i].menge--; aktualisiereWarenkorb(); } }
function mengePlus(i)  { warenkorb[i].menge++; aktualisiereWarenkorb(); }
function warenkorbLeeren() { if (!confirm('Warenkorb leeren?')) return; warenkorb = []; aktualisiereWarenkorb(); }

// ── Divers-Artikel ────────────────────────────────────────────────────────
function diversDialog() {
    document.getElementById('divers-name').value  = '';
    document.getElementById('divers-preis').value = '';
    document.getElementById('btn-divers-ok').disabled = true;
    document.getElementById('overlay-divers').classList.add('aktiv');
    setTimeout(() => document.getElementById('divers-name').focus(), 100);
}
function diversPruefen() {
    var ok = document.getElementById('divers-name').value.trim() && parseFloat(document.getElementById('divers-preis').value) > 0;
    document.getElementById('btn-divers-ok').disabled = !ok;
}
function diversHinzufuegen() {
    var name  = document.getElementById('divers-name').value.trim();
    var preis = parseFloat(document.getElementById('divers-preis').value) || 0;
    var steuer = parseFloat(document.getElementById('divers-steuer').value) || 20;
    if (!name || preis <= 0) return;
    warenkorb.push({ artikel_id: null, bezeichnung: name, ean: null, menge: 1,
        einzelpreis_brutto: preis, steuer_prozent: steuer, rabatt_prozent: 0, charge: null, istDivers: true });
    overlaySchliessen('overlay-divers');
    aktualisiereWarenkorb();
}

// ── Mitgeben ──────────────────────────────────────────────────────────────
function mitgebenDialog() {
    if (warenkorb.length === 0) { scanFeedback('Warenkorb ist leer.', 'fehler'); return; }
    document.getElementById('mg-datum').value = '';
    document.getElementById('mg-name').value = '';
    document.getElementById('overlay-mitgeben').classList.add('aktiv');
}
function mitgebenSpeichern() {
    var positionen = warenkorb.filter(p => !p.istDivers && p.artikel_id);
    if (!positionen.length) { alert('Nur echte Artikel können mitgegeben werden.'); return; }
    fetch('/mealana/kasse/offene_auswahl_speichern.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            kunden_name: document.getElementById('mg-name').value.trim() || null,
            rueckgabe_bis: document.getElementById('mg-datum').value || null,
            lager_id: LAGER_ID,
            positionen: positionen
        })
    }).then(r => r.json()).then(d => {
        overlaySchliessen('overlay-mitgeben');
        if (d.erfolg) {
            scanFeedback('✓ Artikel mitgegeben. OA #' + d.oa_id, 'ok');
            warenkorb = []; aktualisiereWarenkorb();
        } else {
            scanFeedback('❌ ' + (d.fehler || 'Fehler'), 'fehler');
        }
    });
}

// ── Bezahlen ──────────────────────────────────────────────────────────────
function zahlenBar() {
    var gesamt = getGesamtBetrag();
    document.getElementById('bar-total-anzeige').textContent = '€ ' + gesamt.toFixed(2).replace('.', ',');
    document.getElementById('bar-gegeben').value = '';
    document.getElementById('bar-rueckgeld').textContent = 'Rückgeld: € 0,00';
    document.getElementById('btn-bar-ok').disabled = true;

    // Schnell-Beträge: nächste sinnvolle Geldschein-Werte
    var schnellBetrage = [5, 10, 20, 50, 100, 200, 500].filter(b => b >= gesamt);
    var html = '';
    schnellBetrage.slice(0, 4).forEach(function(b) {
        html += '<button class="ks-btn ks-btn-secondary" onclick="barGegeben(' + b + ')" style="padding:10px 8px;font-size:14px">€ ' + b + '</button>';
    });
    document.getElementById('bar-schnell').innerHTML = html;

    document.getElementById('overlay-bar').classList.add('aktiv');
    setTimeout(() => document.getElementById('bar-gegeben').focus(), 100);
}

function barGegeben(betrag) {
    document.getElementById('bar-gegeben').value = betrag.toFixed(2);
    berechneRueckgeld();
}

function berechneRueckgeld() {
    var gesamt  = getGesamtBetrag();
    var gegeben = parseFloat(document.getElementById('bar-gegeben').value) || 0;
    var rueck   = gegeben - gesamt;
    var rueckEl = document.getElementById('bar-rueckgeld');
    if (gegeben >= gesamt) {
        rueckEl.textContent = 'Rückgeld: € ' + rueck.toFixed(2).replace('.', ',');
        rueckEl.style.color = '#27ae60';
        document.getElementById('btn-bar-ok').disabled = false;
    } else {
        rueckEl.textContent = rueck < 0 ? 'Noch fehlend: € ' + Math.abs(rueck).toFixed(2).replace('.', ',') : '';
        rueckEl.style.color = '#ef5350';
        document.getElementById('btn-bar-ok').disabled = true;
    }
}

function bonAbschliessenBar() {
    var gesamt  = getGesamtBetrag();
    var gegeben = parseFloat(document.getElementById('bar-gegeben').value) || 0;
    if (gegeben < gesamt) return;
    bonSpeichern({ zahlungsart: 'bar', gegeben: gegeben, rueckgeld: Math.max(0, gegeben - gesamt) });
}

function zahlenKarte() {
    var gesamt = getGesamtBetrag();
    document.getElementById('karte-total-anzeige').textContent = '€ ' + gesamt.toFixed(2).replace('.', ',');
    document.getElementById('overlay-karte').classList.add('aktiv');
}
function bonAbschliessenKarte() {
    bonSpeichern({ zahlungsart: 'karte_extern' });
}

function zahlenGutschein() {
    var gesamt = getGesamtBetrag();
    document.getElementById('gs-total-anzeige').textContent = '€ ' + gesamt.toFixed(2).replace('.', ',');
    document.getElementById('gs-code').value = '';
    document.getElementById('gs-info').textContent = '';
    document.getElementById('btn-gs-ok').disabled = true;
    document.getElementById('overlay-gutschein').classList.add('aktiv');
    setTimeout(() => document.getElementById('gs-code').focus(), 100);
}
function gsCodeGeaendert() {
    var code = document.getElementById('gs-code').value.trim();
    document.getElementById('btn-gs-ok').disabled = code.length < 3;
}
function bonAbschliessenGutschein() {
    var code = document.getElementById('gs-code').value.trim();
    if (!code) return;
    bonSpeichern({ zahlungsart: 'gutschein', gutschein_code: code });
}

function zahlenKombi() {
    var gesamt = getGesamtBetrag();
    document.getElementById('kombi-total-anzeige').textContent = '€ ' + gesamt.toFixed(2).replace('.', ',');
    document.getElementById('kombi-karte').value = '';
    document.getElementById('kombi-bar').value   = '';
    document.getElementById('kombi-diff').textContent = '';
    document.getElementById('btn-kombi-ok').disabled = true;
    document.getElementById('overlay-kombi').classList.add('aktiv');
}
function kombiBerechnen() {
    var gesamt = getGesamtBetrag();
    var karte  = parseFloat(document.getElementById('kombi-karte').value) || 0;
    var bar    = parseFloat(document.getElementById('kombi-bar').value)   || 0;
    var diff   = karte + bar - gesamt;
    var diffEl = document.getElementById('kombi-diff');
    if (Math.abs(diff) < 0.005) {
        diffEl.textContent = '✓ Passt genau';
        diffEl.style.color = '#27ae60';
        document.getElementById('btn-kombi-ok').disabled = false;
    } else if (diff > 0.005) {
        diffEl.textContent = 'Rückgeld Bar: € ' + diff.toFixed(2).replace('.', ',');
        diffEl.style.color = '#27ae60';
        document.getElementById('btn-kombi-ok').disabled = false;
    } else {
        diffEl.textContent = 'Noch fehlend: € ' + Math.abs(diff).toFixed(2).replace('.', ',');
        diffEl.style.color = '#ef5350';
        document.getElementById('btn-kombi-ok').disabled = true;
    }
}
function bonAbschliessenKombi() {
    var karte = parseFloat(document.getElementById('kombi-karte').value) || 0;
    var bar   = parseFloat(document.getElementById('kombi-bar').value)   || 0;
    var diff  = karte + bar - getGesamtBetrag();
    bonSpeichern({ zahlungsart: 'kombi', karten_betrag: karte, bar_betrag: bar, rueckgeld: Math.max(0, diff) });
}

// ── Bon speichern ─────────────────────────────────────────────────────────
function bonSpeichern(zahlungsDaten) {
    ['overlay-bar','overlay-karte','overlay-gutschein','overlay-kombi'].forEach(overlaySchliessen);
    document.getElementById('overlay-laden').classList.add('aktiv');

    var globalRabatt = parseFloat(document.getElementById('rabatt-input').value) || 0;
    var positionen = warenkorb.map(function(p) {
        return Object.assign({}, p, { rabatt_prozent: Math.max(p.rabatt_prozent, globalRabatt) });
    });

    fetch('/mealana/kasse/bon_speichern.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(Object.assign({
            kasse_id: KASSE_ID,
            lager_id: LAGER_ID,
            bruttobetrag: getGesamtBetrag(),
            positionen: positionen
        }, zahlungsDaten))
    })
    .then(r => r.json())
    .then(function(d) {
        document.getElementById('overlay-laden').classList.remove('aktiv');
        if (d.erfolg) {
            warenkorb = [];
            aktualisiereWarenkorb();
            document.getElementById('scan-info-leer').style.display = 'block';
            document.getElementById('scan-info-inhalt').style.display = 'none';
            window.open('/mealana/kasse/bon_druck.php?id=' + d.bon_id, '_blank');
        } else {
            alert('Fehler: ' + (d.fehler || 'Unbekannt'));
        }
    })
    .catch(function() {
        document.getElementById('overlay-laden').classList.remove('aktiv');
        alert('Netzwerkfehler. Bitte erneut versuchen.');
    });
}

// ── Hilfs-Funktionen ──────────────────────────────────────────────────────
function overlaySchliessen(id) {
    document.getElementById(id).classList.remove('aktiv');
    document.getElementById('scan-input').focus();
}

function scanFeedback(msg, typ) {
    var el = document.getElementById('scan-feedback');
    if (!msg) { el.innerHTML = ''; return; }
    el.innerHTML = '<div class="ks-feedback ' + (typ || 'info') + '">' + htmlEsc(msg) + '</div>';
    if (typ === 'ok') setTimeout(() => { if (el.innerHTML) el.innerHTML = ''; }, 3000);
}

function htmlEsc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.getElementById('scan-input').focus();
</script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
