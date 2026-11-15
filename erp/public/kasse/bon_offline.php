<?php
// Bewusst KEINE dynamischen Server-Daten (Sync-ID, Lager etc.) mehr auf dieser Seite —
// die Seite muss als reine, statische Hülle vom Service Worker cachebar sein, damit
// sie auch ganz ohne Serververbindung neu geladen werden kann (Browser-Absturz o.ä.
// während der Messe). sync_id wird stattdessen clientseitig aus der URL gelesen
// (siehe kasse_bon_offline.js). auth_check.php läuft nur beim allerersten (Online-)
// Laden — danach liefert der Service Worker die Seite direkt aus dem Cache, ohne
// dass der Server je wieder angefragt wird.
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Offline-Kasse';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: #f1f5f9;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      color: #1e293b;
      min-height: 100vh;
    }
    .ob-header {
      background: #1e3a5f; color: #fff; padding: 10px 20px;
      display: flex; align-items: center; justify-content: space-between;
      height: 54px; position: sticky; top: 0; z-index: 50;
    }
    .ob-header-title { font-size: 18px; font-weight: 800; letter-spacing: .5px; }
    .ob-header-sub { font-size: 12px; color: #93c5fd; }
    .ob-status { display: flex; align-items: center; gap: 14px; font-size: 13px; }
    .ob-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    .ob-dot.bereit { background: #22c55e; }
    .ob-dot.fehlt  { background: #f59e0b; }
    .ob-btn {
      border: none; border-radius: 6px; padding: 8px 16px; font-size: 13px; font-weight: 700;
      cursor: pointer; font-family: inherit;
    }
    .ob-btn-primary { background: #2563eb; color: #fff; }
    .ob-btn-secondary { background: #334155; color: #e2e8f0; }
    .ob-btn:disabled { opacity: .4; cursor: not-allowed; }

    .ob-main { padding: 20px; max-width: 1200px; margin: 0 auto; }
    .ob-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 16px; }
    .ob-card-title { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 12px; }

    .ob-grid { display: grid; grid-template-columns: 1fr 380px; gap: 18px; align-items: start; }
    .ob-scan { width: 100%; font-size: 18px; padding: 12px 14px; border: 2px solid #cbd5e1; border-radius: 8px; }
    .ob-scan:focus { border-color: #2563eb; outline: none; }

    .ob-warenkorb { list-style: none; margin: 14px 0 0; padding: 0; }
    .ob-zeile { display: flex; align-items: center; justify-content: space-between; padding: 10px 4px; border-bottom: 1px solid #f1f5f9; gap: 10px; }
    .ob-zeile-name { font-size: 14px; font-weight: 600; }
    .ob-zeile-sub { font-size: 11px; color: #94a3b8; }
    .ob-zeile-menge { display: flex; align-items: center; gap: 6px; }
    .ob-zeile-menge button { width: 26px; height: 26px; border: 1px solid #cbd5e1; background: #fff; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .ob-zeile-preis { font-weight: 700; width: 80px; text-align: right; }
    .ob-zeile-x { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 15px; padding: 4px; }
    .ob-charge { font-size: 11px; width: 90px; padding: 3px 5px; border: 1px solid #cbd5e1; border-radius: 4px; }

    .ob-summe-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; color: #64748b; }
    .ob-summe-gesamt { display: flex; justify-content: space-between; padding-top: 10px; margin-top: 8px; border-top: 2px solid #1e3a5f; font-size: 22px; font-weight: 800; color: #1e3a5f; }

    .ob-zahlarten { display: flex; gap: 8px; margin: 14px 0; }
    .ob-zahlart-btn { flex: 1; padding: 12px; border: 2px solid #cbd5e1; border-radius: 8px; background: #fff; cursor: pointer; font-size: 14px; font-weight: 700; text-align: center; }
    .ob-zahlart-btn.aktiv { border-color: #2563eb; background: #eff6ff; color: #2563eb; }

    .ob-input { width: 100%; padding: 10px 12px; border: 2px solid #cbd5e1; border-radius: 8px; font-size: 16px; margin-bottom: 8px; }

    .ob-abschluss-btn { width: 100%; padding: 16px; font-size: 18px; font-weight: 800; border: none; border-radius: 10px; background: #16a34a; color: #fff; cursor: pointer; }
    .ob-abschluss-btn:disabled { background: #94a3b8; cursor: not-allowed; }

    .ob-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 200; align-items: center; justify-content: center; }
    .ob-overlay.aktiv { display: flex; }
    .ob-overlay-box { background: #fff; border-radius: 14px; padding: 28px; width: 90%; max-width: 420px; max-height: 85vh; overflow-y: auto; }

    /* Freier Artikel: Gruppen-Kacheln, Steuer-Tasten, eigenes Numpad (wie bon.php) */
    .ob-div-label { font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px; }
    .ob-div-gruppen { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .ob-div-gruppen button {
      flex: 1 1 110px; min-height: 44px; padding: 6px 8px;
      border-radius: 8px; border: 1.5px solid #cbd5e1; background: #f8fafc; color: #334155;
      font-size: 13px; font-weight: 600; font-family: inherit; cursor: pointer;
    }
    .ob-div-gruppen button.aktiv { background: #2563eb; border-color: #2563eb; color: #fff; }
    .ob-div-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .ob-div-steuer { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
    .ob-div-steuer button {
      height: 40px; border-radius: 8px; border: 1.5px solid #cbd5e1; background: #f8fafc;
      font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; color: #334155;
    }
    .ob-div-steuer button.aktiv { background: #1e3a5f; border-color: #1e3a5f; color: #fff; }
    .ob-div-preis { font-size: 26px; text-align: right; }
    .ob-div-np { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .ob-div-np button {
      height: 46px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f1f5f9;
      font-size: 20px; font-weight: 600; font-family: inherit; cursor: pointer; color: #1e293b;
    }
    .ob-div-np button:active { background: #cbd5e1; }

    .ob-quittung { font-family: 'Courier New', monospace; font-size: 13px; }
    .ob-quittung .z { text-align: center; }
    .ob-quittung .b { font-weight: bold; }
    .ob-quittung .l { border-top: 1px dashed #999; margin: 6px 0; }
    .ob-quittung .r { display: flex; justify-content: space-between; }

    /* Mengenvorwahl + Artikel-Kacheln (beide Anordnungen) */
    .ob-mengen { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin: 10px 0 4px; font-size: 12px; color: #64748b; }
    .ob-mengen button { min-width: 40px; height: 36px; border: 1.5px solid #cbd5e1; background: #f8fafc; border-radius: 8px;
                        font-weight: 700; font-size: 15px; font-family: inherit; cursor: pointer; color: #334155; }
    .ob-mengen button.aktiv { background: #1e3a5f; color: #fff; border-color: #1e3a5f; }
    .ob-kacheln { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; margin-top: 10px;
                  max-height: 300px; overflow-y: auto; }
    .ob-kachel { border: 1.5px solid #cbd5e1; border-radius: 10px; background: #fff; padding: 8px; min-height: 66px; text-align: left;
                 font: inherit; cursor: pointer; display: flex; flex-direction: column; justify-content: space-between; color: #1e293b; }
    .ob-kachel:active { background: #eff6ff; }
    .ob-kachel .n { font-size: 13px; font-weight: 600; line-height: 1.2; overflow: hidden; display: -webkit-box;
                    -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .ob-kachel .p { font-size: 13px; font-weight: 800; color: #1e3a5f; margin-top: 4px; }
    .ob-kachel .p s { font-weight: 400; color: #94a3b8; }
    .ob-kachel .a { font-size: 10px; font-weight: 700; color: #b45309; }
    .ob-kachel.aktion { border-color: #f59e0b; }
    .ob-bottombar { display: none; }
    #ob-autodruck-wrap { display: none; font-size: 13px; color: #475569; margin-top: 10px; gap: 6px; align-items: center; }

    /* ── Hochformat (Sunmi V2, ~360 px breit): Artikel-Seite ⇄ Warenkorb-Seite, Leiste unten ── */
    body.lay-hoch .ob-header { height: auto; min-height: 48px; padding: 6px 10px; flex-wrap: wrap; gap: 4px; }
    body.lay-hoch .ob-header-title { font-size: 15px; }
    body.lay-hoch .ob-status { gap: 8px; font-size: 12px; }
    body.lay-hoch .ob-main { padding: 8px 8px 80px; }
    body.lay-hoch .ob-grid { grid-template-columns: 1fr; gap: 0; }
    body.lay-hoch .ob-grid > * { min-width: 0; }
    body.lay-hoch .ob-zeile { flex-wrap: wrap; gap: 6px; }
    body.lay-hoch .ob-zeile > div:first-child { flex: 1 1 100%; }
    body.lay-hoch .ob-zeile-menge { margin-right: auto; }
    body.lay-hoch .ob-zeile-menge button { width: 38px; height: 38px; font-size: 18px; }
    body.lay-hoch .ob-header-sub { font-size: 11px; }
    body.lay-hoch #ob-bereit-text { display: none; }
    body.lay-hoch .ob-status .ob-btn { padding: 6px 10px; font-size: 12px; }
    body.lay-hoch .ob-card { padding: 10px; margin-bottom: 8px; border-radius: 8px; }
    body.lay-hoch .ob-scan { font-size: 16px; padding: 10px; }
    body.lay-hoch .ob-kacheln { grid-template-columns: 1fr 1fr; max-height: none; }
    body.lay-hoch .ob-korb-card, body.lay-hoch .ob-rechts { display: none; }
    body.lay-hoch.korb-offen .ob-korb-card, body.lay-hoch.korb-offen .ob-rechts { display: block; }
    body.lay-hoch.korb-offen .ob-artikel-card { display: none; }
    body.lay-hoch .ob-bottombar { display: flex; position: fixed; left: 0; right: 0; bottom: 0; gap: 8px; padding: 8px;
                                  background: #1e3a5f; z-index: 60; }
    .ob-bottombar button { flex: 1; height: 54px; border: none; border-radius: 10px; font-size: 16px; font-weight: 800;
                           font-family: inherit; cursor: pointer; }
    #ob-bb-links { background: #334155; color: #fff; flex: 0 0 36%; }
    #ob-bb-rechts { background: #16a34a; color: #fff; }
    body.lay-hoch:not(.korb-offen) #ob-bb-links { display: none; }
    body.lay-hoch .ob-overlay-box { padding: 18px; width: 94%; }

    /* ── Druck: nur der Beleg, 58-mm-Bon (Sunmi-Drucker, druckbar ~48 mm) ── */
    @media print {
      @page { size: 58mm auto; margin: 0; }
      body { background: #fff; min-height: 0; }
      body > *:not(#ob-quittung-overlay) { display: none !important; }
      #ob-quittung-overlay { display: block !important; position: static; background: none; }
      #ob-quittung-overlay .ob-overlay-box { width: auto; max-width: none; max-height: none; overflow: visible;
                                             padding: 1mm 2mm; border-radius: 0; box-shadow: none; }
      .ob-noprint { display: none !important; }
      .ob-quittung { width: 48mm; font-size: 9pt; color: #000; }
    }

    .ob-feedback { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; }
    .ob-feedback.ok { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
    .ob-feedback.fehler { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
    .ob-feedback.info { background: #eff6ff; color: #1d4ed8; border: 1px solid #93c5fd; }
  </style>
</head>
<body>

<div class="ob-header">
  <div>
    <div class="ob-header-title">🎪 OFFLINE-KASSE</div>
    <div class="ob-header-sub" id="ob-kasse-label">Wird geladen…</div>
  </div>
  <div class="ob-status">
    <button class="ob-btn ob-btn-secondary" onclick="obLayoutWechseln()" title="Anordnung Hochformat / Querformat" style="padding:6px 10px">⇅</button>
    <span id="ob-bons-zaehler">0 Bons offen</span>
    <button class="ob-btn ob-btn-secondary" id="ob-upload-btn" onclick="obBonsHochladen()" disabled>⤴ Bons hochladen</button>
    <span><span class="ob-dot fehlt" id="ob-bereit-dot"></span><span id="ob-bereit-text">Prüfe…</span></span>
  </div>
</div>

<div class="ob-main">

  <div id="ob-vorbereitung-hinweis" class="ob-card" style="display:none">
    <div class="ob-card-title">Vorbereitung</div>
    <p style="font-size:14px;color:#475569;margin-bottom:14px">
      Für diese Kasse liegen noch keine Offline-Daten vor. Solange noch Internet-/Serververbindung
      besteht, jetzt einmalig laden — danach funktioniert diese Seite komplett ohne Server.
    </p>
    <div style="margin-bottom:12px;font-size:13px;color:#475569">
      <label>Gerät-Nr. <input type="text" id="ob-geraet" value="1" maxlength="3" style="width:60px;padding:6px;border:1px solid #cbd5e1;border-radius:6px;font-size:15px"></label>
      <span style="font-size:12px;color:#94a3b8">— nur Mobil-Messe: bei mehreren Geräten 1, 2, 3 … (steht in der Belegnummer)</span>
    </div>
    <button class="ob-btn ob-btn-primary" onclick="obVorbereitungLaden()">📥 Sync-Daten laden</button>
    <div id="ob-vorbereitung-feedback" style="margin-top:10px"></div>
  </div>

  <div id="ob-kasse-bereich" style="display:none">
    <div class="ob-grid">

      <!-- LINKS: Scan + Kacheln, darunter Warenkorb -->
      <div>
        <div class="ob-card ob-artikel-card">
          <div style="position:relative">
            <input type="text" id="ob-scan" class="ob-scan" placeholder="EAN / Artikelnummer scannen — oder Artikelname eingeben zum Suchen" autofocus autocomplete="off">
            <div id="ob-such-ergebnisse" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 8px 8px;max-height:260px;overflow-y:auto;z-index:20;box-shadow:0 6px 14px rgba(0,0,0,.08)"></div>
          </div>
          <div id="ob-scan-feedback" style="font-size:13px;margin-top:6px;min-height:16px"></div>

          <button type="button" class="ob-btn ob-btn-secondary" style="margin-top:4px" onclick="obFreierArtikelOeffnen()">➕ Freier Artikel</button>

          <!-- Mengenvorwahl: gilt für den nächsten Artikel (Kachel/Scan), danach wieder 1 -->
          <div class="ob-mengen" id="ob-mengen-leiste"></div>
          <!-- Kacheln = alle zur Messe mitgenommenen Artikel; Eingabe oben filtert -->
          <div class="ob-kacheln" id="ob-kacheln"></div>
        </div>

        <div class="ob-card ob-korb-card">
          <div class="ob-card-title">Warenkorb</div>
          <ul class="ob-warenkorb" id="ob-warenkorb-liste">
            <li style="text-align:center;color:#94a3b8;padding:20px 0">Warenkorb ist leer.</li>
          </ul>
        </div>
      </div>

      <!-- RECHTS: Summe + Zahlung -->
      <div class="ob-rechts">
        <div class="ob-card">
          <div class="ob-card-title">Summe</div>
          <div id="ob-steuer-zeilen"></div>
          <div class="ob-summe-gesamt">
            <span>GESAMT</span>
            <span id="ob-gesamt-anzeige">€ 0,00</span>
          </div>

          <div class="ob-zahlarten">
            <button type="button" class="ob-zahlart-btn aktiv" data-zahlart="bar" onclick="obZahlartWaehlen('bar')">💶 Bar</button>
            <button type="button" class="ob-zahlart-btn" data-zahlart="karte_extern" onclick="obZahlartWaehlen('karte_extern')">💳 Karte</button>
          </div>

          <div id="ob-bar-felder">
            <label style="font-size:12px;color:#64748b">Gegeben</label>
            <input type="number" step="0.01" id="ob-gegeben" class="ob-input" placeholder="0,00">
            <div style="font-size:14px;color:#475569" id="ob-rueckgeld-anzeige"></div>
          </div>

          <button class="ob-abschluss-btn" id="ob-abschluss-btn" onclick="obVerkaufAbschliessen()" disabled style="margin-top:10px">
            ✓ Verkauf abschließen
          </button>
          <label id="ob-autodruck-wrap"><input type="checkbox" id="ob-autodruck" onchange="obAutodruckMerken()"> Beleg gleich drucken</label>
        </div>
      </div>

    </div>
  </div>

</div>

<!-- Hochformat: Leiste unten (Artikel ⇄ Warenkorb/Bezahlen) -->
<div class="ob-bottombar" id="ob-bottombar">
  <button id="ob-bb-links" onclick="obKorbUmschalten(false)">◀ Artikel</button>
  <button id="ob-bb-rechts" onclick="obLeisteRechts()">🛒 leer</button>
</div>

<!-- Quittungs-Overlay -->
<div class="ob-overlay" id="ob-quittung-overlay">
  <div class="ob-overlay-box">
    <div id="ob-quittung-inhalt" class="ob-quittung"></div>
    <div class="ob-noprint" style="display:flex;gap:8px;margin-top:16px">
      <button class="ob-btn ob-btn-primary" style="flex:1" onclick="window.print()">🖨 Drucken</button>
      <button class="ob-btn ob-btn-secondary" style="flex:1" onclick="obQuittungSchliessen()">Weiter</button>
    </div>
  </div>
</div>

<!-- RKSV: BFR nicht erreichbar (2 Eskalationsstufen) -->
<div class="ob-overlay" id="ob-bfr-overlay">
  <div class="ob-overlay-box" style="text-align:center;max-width:420px">
    <div style="font-size:32px;margin-bottom:6px">⚠</div>
    <div id="ob-bfr-stufe1">
      <div style="font-weight:800;font-size:18px;margin-bottom:10px">Dienst nicht erreichbar!</div>
      <p style="color:#64748b;font-size:14px;margin-bottom:20px">
        Die technische Sicherheitseinrichtung (BFR) antwortet nicht.
      </p>
    </div>
    <div id="ob-bfr-stufe2" style="display:none">
      <div style="font-weight:800;font-size:18px;margin-bottom:10px">Dienst immer noch nicht erreichbar</div>
      <p style="color:#64748b;font-size:14px;margin-bottom:12px">
        Die Kasse bleibt gesperrt, bis der BFR-Dienst wieder antwortet. Bitte am Gerät prüfen:
      </p>
      <ul style="text-align:left;color:#64748b;font-size:13px;margin:0 0 20px 20px;padding:0">
        <li>Läuft "BFR" in der Taskleiste?</li>
        <li>Signaturkarte im Kartenleser gesteckt?</li>
        <li>Windows-Update / Firewall gerade aktiv?</li>
      </ul>
    </div>
    <button class="ob-btn ob-btn-primary" id="ob-bfr-retry-btn" style="width:100%" onclick="obBfrErneutVersuchen()">Erneut versuchen</button>
  </div>
</div>

<!-- Chargen-Auswahl-Overlay (nur bei charge_pflicht-Artikeln, echte mitgenommene Chargen) -->
<div class="ob-overlay" id="ob-charge-overlay">
  <div class="ob-overlay-box">
    <div style="font-weight:700;font-size:15px;margin-bottom:4px" id="ob-charge-titel">Charge wählen</div>
    <div style="font-size:12px;color:#64748b;margin-bottom:12px">Nur die tatsächlich zur Messe mitgenommenen Chargen stehen zur Auswahl.</div>
    <div id="ob-charge-liste" style="display:flex;flex-direction:column;gap:8px;max-height:280px;overflow-y:auto"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px">
      <button type="button" class="ob-btn ob-btn-secondary" onclick="obChargeAbbrechen()">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Freier-Artikel-Overlay (gleicher Aufbau wie Divers-Dialog in bon.php).
     Gruppen-Kacheln kommen aus den Sync-Daten (IndexedDB), nicht vom Server. -->
<div class="ob-overlay" id="ob-freier-overlay">
  <div class="ob-overlay-box" style="max-width:760px">
    <div style="font-weight:700;font-size:15px;margin-bottom:12px">➕ Freier Artikel</div>

    <div id="ob-freier-gruppen-block">
      <div class="ob-div-label">Artikelgruppe</div>
      <div class="ob-div-gruppen" id="ob-freier-gruppen"></div>
    </div>

    <div class="ob-div-layout">
      <div>
        <div class="ob-div-label">Bezeichnung</div>
        <input type="text" id="ob-freier-bezeichnung" class="ob-input" maxlength="300" oninput="obFreierPruefen()">
        <div class="ob-div-label">Steuer</div>
        <div class="ob-div-steuer" id="ob-freier-steuer">
          <button type="button" data-satz="20" onclick="obFreierSteuerSetzen(20)">20 %</button>
          <button type="button" data-satz="13" onclick="obFreierSteuerSetzen(13)">13 %</button>
          <button type="button" data-satz="10" onclick="obFreierSteuerSetzen(10)">10 %</button>
          <button type="button" data-satz="0"  onclick="obFreierSteuerSetzen(0)">0 %</button>
        </div>
      </div>
      <div>
        <div class="ob-div-label">Preis (brutto, €)</div>
        <input type="text" id="ob-freier-preis" class="ob-input ob-div-preis" inputmode="decimal" placeholder="0,00"
               oninput="obFreierPruefen()"
               onkeydown="if(event.key==='Enter'&&!document.getElementById('ob-freier-ok').disabled)obFreierArtikelHinzufuegen()">
        <div class="ob-div-np">
          <button type="button" onclick="obFreierNp('7')">7</button><button type="button" onclick="obFreierNp('8')">8</button><button type="button" onclick="obFreierNp('9')">9</button>
          <button type="button" onclick="obFreierNp('4')">4</button><button type="button" onclick="obFreierNp('5')">5</button><button type="button" onclick="obFreierNp('6')">6</button>
          <button type="button" onclick="obFreierNp('1')">1</button><button type="button" onclick="obFreierNp('2')">2</button><button type="button" onclick="obFreierNp('3')">3</button>
          <button type="button" onclick="obFreierNp(',')">,</button><button type="button" onclick="obFreierNp('0')">0</button><button type="button" onclick="obFreierNpBack()">⌫</button>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px">
      <button type="button" class="ob-btn ob-btn-secondary" style="flex:1;padding:12px" onclick="obFreierArtikelSchliessen()">Abbrechen</button>
      <button type="button" class="ob-btn ob-btn-primary" style="flex:1;padding:12px" id="ob-freier-ok" onclick="obFreierArtikelHinzufuegen()" disabled>✓ Hinzufügen</button>
    </div>
  </div>
</div>

<script>
  // Bewusst rein clientseitig berechnet (kein PHP) — macht diese Seite zu einer
  // echten statischen Hülle, die der Service Worker 1:1 cachen kann.
  window.BASE_PATH = '/' + location.pathname.split('/')[1];
  window.OB_SYNC_ID = parseInt(new URLSearchParams(location.search).get('sync_id') || '0', 10);

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw_bon_offline.js', { scope: './' })
      .catch(err => console.error('Service Worker Registrierung fehlgeschlagen:', err));
  }
</script>
<!-- Relativer Pfad (nicht BASE_PATH) — diese Seite ist bewusst root-Pfad-unabhängig,
     damit sie unverändert cachebar bleibt egal unter welchem Installationspfad. -->
<script src="../js/mengen_aktion_rechner.js"></script>
<script src="../js/kasse_bon_offline.js"></script>

</body>
</html>
