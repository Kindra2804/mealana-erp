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

    .ob-quittung { font-family: 'Courier New', monospace; font-size: 13px; }
    .ob-quittung .z { text-align: center; }
    .ob-quittung .b { font-weight: bold; }
    .ob-quittung .l { border-top: 1px dashed #999; margin: 6px 0; }
    .ob-quittung .r { display: flex; justify-content: space-between; }

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
    <button class="ob-btn ob-btn-primary" onclick="obVorbereitungLaden()">📥 Sync-Daten laden</button>
    <div id="ob-vorbereitung-feedback" style="margin-top:10px"></div>
  </div>

  <div id="ob-kasse-bereich" style="display:none">
    <div class="ob-grid">

      <!-- LINKS: Scan + Warenkorb -->
      <div>
        <div class="ob-card">
          <div style="position:relative">
            <input type="text" id="ob-scan" class="ob-scan" placeholder="EAN / Artikelnummer scannen — oder Artikelname eingeben zum Suchen" autofocus autocomplete="off">
            <div id="ob-such-ergebnisse" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 8px 8px;max-height:260px;overflow-y:auto;z-index:20;box-shadow:0 6px 14px rgba(0,0,0,.08)"></div>
          </div>
          <div id="ob-scan-feedback" style="font-size:13px;margin-top:6px;min-height:16px"></div>

          <button type="button" class="ob-btn ob-btn-secondary" style="margin-top:4px" onclick="obFreierArtikelOeffnen()">➕ Freier Artikel</button>

          <ul class="ob-warenkorb" id="ob-warenkorb-liste">
            <li style="text-align:center;color:#94a3b8;padding:20px 0">Warenkorb ist leer.</li>
          </ul>
        </div>
      </div>

      <!-- RECHTS: Summe + Zahlung -->
      <div>
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
        </div>
      </div>

    </div>
  </div>

</div>

<!-- Quittungs-Overlay -->
<div class="ob-overlay" id="ob-quittung-overlay">
  <div class="ob-overlay-box">
    <div id="ob-quittung-inhalt" class="ob-quittung"></div>
    <div style="display:flex;gap:8px;margin-top:16px">
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

<!-- Freier-Artikel-Overlay -->
<div class="ob-overlay" id="ob-freier-overlay">
  <div class="ob-overlay-box">
    <div style="font-weight:700;font-size:15px;margin-bottom:12px">➕ Freier Artikel</div>
    <label style="font-size:12px;color:#64748b">Bezeichnung</label>
    <input type="text" id="ob-freier-bezeichnung" class="ob-input" placeholder="z.B. Sonderanfertigung">
    <label style="font-size:12px;color:#64748b">Preis (brutto)</label>
    <input type="number" step="0.01" id="ob-freier-preis" class="ob-input" placeholder="0,00">
    <label style="font-size:12px;color:#64748b">Steuersatz</label>
    <select id="ob-freier-steuer" class="ob-input">
      <option value="20">20 %</option>
      <option value="10">10 %</option>
      <option value="13">13 %</option>
      <option value="0">0 %</option>
    </select>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button type="button" class="ob-btn ob-btn-secondary" style="flex:1" onclick="obFreierArtikelSchliessen()">Abbrechen</button>
      <button type="button" class="ob-btn ob-btn-primary" style="flex:1" onclick="obFreierArtikelHinzufuegen()">Hinzufügen</button>
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
<script src="../js/kasse_bon_offline.js"></script>

</body>
</html>
