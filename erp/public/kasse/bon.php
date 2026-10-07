<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';
require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';

$svc = new KassenService();

$aktuelleKasseId = (new ArbeitsplatzService())->aktuelleKasseId();
if ($aktuelleKasseId === null) {
    // Unbekanntes Gerät, kein sicherer Fallback (Hauptkasse hat BFR aktiv) — erst über
    // die Kasse-Startseite einen Arbeitsplatz zuweisen lassen, statt fälschlich als
    // Kasse 1 aufzutreten (RKSV-Signatur-Risiko).
    $_SESSION['fehler'] = 'Dieses Gerät ist keiner Kasse zugeordnet. Bitte zuerst einen Arbeitsplatz auswählen.';
    header('Location: ' . BASE_PATH . '/kasse/index.php');
    exit;
}

$kasseInfo = $svc->getKasse($aktuelleKasseId);
$lagerId   = (int)($kasseInfo['lager_id']      ?? 1);
$kasseId   = (int)($kasseInfo['id']            ?? 1);
$lagerName = $kasseInfo['lager_name']          ?? 'Hauptlager';
$rksvId    = $kasseInfo['rksv_kassen_id']      ?? null;
$modus     = $kasseInfo['modus']               ?? 'online';

// Gutschein-Ausgabe bei Retoure (statt Bar-Auszahlung) braucht einen echten
// Artikel mit freiem Preis, genau wie der bestehende Divers-Artikel (99-9999) --
// siehe project_gutscheine.md. Kann NULL sein solange dieser Artikel noch nicht
// angelegt wurde -- der Button in ov-retour-bar wird dann serverseitig deaktiviert.
$gutscheinArtikelId = (int)(Database::getInstance()
    ->query("SELECT id FROM artikel WHERE ist_gutschein = 1 LIMIT 1")
    ->fetchColumn() ?: 0) ?: null;

// Resync-Sperre: verhindert Bon-Nr-Kollisionen, wenn diese Kasse noch unsynchronisierte
// Messe-Daten hat (siehe MesseSyncService::hatOffenenResync). Die eigentliche, nicht
// umgehbare Sperre sitzt zusätzlich in bon_speichern.php — das hier ist nur die UX,
// damit man gar nicht erst in der leeren Kasse landet, sondern direkt bei den offenen Syncs.
if ((new MesseSyncService())->hatOffenenResync($kasseId)) {
    $_SESSION['fehler'] = 'Diese Kasse hat noch offene Messe-Daten (Sync ausstehend) — bitte zuerst synchronisieren, bevor hier online verkauft wird.';
    header('Location: ' . BASE_PATH . '/kasse/messe_vorbereiten.php');
    exit;
}

// Inventur-Sperre: UX-Vorabprüfung, damit man gar nicht erst in der leeren Kasse
// landet. Die eigentliche, nicht umgehbare Sperre sitzt zusätzlich in
// KassenService::erstelleBon() (gleiches Muster wie die Resync-Sperre oben).
if ((new InventurService())->gibtEsLaufendeVollinventur($lagerId)) {
    $_SESSION['fehler'] = 'Für dieses Lager läuft gerade eine Inventur — Kasse ist bis zum Abschluss gesperrt.';
    header('Location: ' . BASE_PATH . '/kasse/index.php');
    exit;
}

$schnellwahl = $svc->getSchnellwahl($kasseId);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Kasse — <?= htmlspecialchars($kasseInfo['name'] ?? 'Kasse') ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Segoe UI', Arial, sans-serif;
  background: #f1f5f9;
  height: 100vh;
  overflow: hidden;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

/* ── Header ─────────────────────────────────────────────────────────────── */
.ph {
  background: #1e3a5f;
  height: 50px;
  display: flex;
  align-items: center;
  padding: 0 14px;
  gap: 12px;
  position: relative;
  z-index: 100;
}
.ph-title  { color: #fff; font-size: 17px; font-weight: 700; white-space: nowrap; }
.ph-sub    { color: #93c5fd; font-size: 12px; white-space: nowrap; }
.ph-rksv   { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #86efac; white-space: nowrap; cursor: pointer; }
.ph-rksv-dot { width: 9px; height: 9px; border-radius: 50%; background: #22c55e; flex-shrink: 0; }
.ph-rksv-dot.offline { background: #f59e0b; }
.ph-right  { margin-left: auto; display: flex; gap: 7px; align-items: center; }
.ph-btn {
  border: none; border-radius: 5px;
  padding: 0 14px; height: 30px; font-size: 12px; cursor: pointer;
  white-space: nowrap; font-family: inherit; line-height: 30px;
}
.ph-btn-menu    { background: #334155; color: #e2e8f0; }
.ph-btn-mitgeb  { background: #b45309; color: #fff; }
.ph-btn-parken  { background: #334155; color: #e2e8f0; }
.ph-btn-close   { background: #dc2626; color: #fff; }
.ph-btn:hover   { filter: brightness(1.15); }

/* ── Menü-Dropdown ───────────────────────────────────────────────────────── */
.ph-dropdown {
  display: none;
  position: absolute;
  top: 50px;
  right: 14px;
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 8px;
  min-width: 200px;
  z-index: 200;
  box-shadow: 0 8px 24px rgba(0,0,0,.4);
  overflow: hidden;
}
.ph-dropdown.offen { display: block; }
.ph-dd-item {
  display: block;
  padding: 12px 18px;
  color: #e2e8f0;
  font-size: 13px;
  text-decoration: none;
  cursor: pointer;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
  font-family: inherit;
}
.ph-dd-item:hover { background: #334155; }
.ph-dd-sep { height: 1px; background: #334155; margin: 4px 0; }

/* ── Hauptlayout ─────────────────────────────────────────────────────────── */
.pos-layout {
  display: flex;
  height: calc(100vh - 50px);
  overflow: hidden;
}

/* ── Linke Spalte (Bon-Liste) ─────────────────────────────────────────────── */
.pos-links {
  width: 550px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e2e8f0;
}
.pos-kunde {
  height: 50px;
  background: #eff6ff;
  border-bottom: 1px solid #bfdbfe;
  display: flex;
  align-items: center;
  padding: 0 16px;
  gap: 10px;
  flex-shrink: 0;
}
.pos-kunde-name { color: #1e3a5f; font-size: 14px; flex: 1; }
.pos-kunde-btn {
  background: #2563eb; color: #fff;
  border: none; border-radius: 5px;
  padding: 0 14px; height: 28px; font-size: 12px; cursor: pointer;
  font-family: inherit;
}
.pos-kunde-btn:hover { background: #1d4ed8; }
.pos-bonkopf {
  height: 32px;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  padding: 0 16px;
  font-size: 11px;
  font-weight: 700;
  color: #475569;
  letter-spacing: 0.3px;
  flex-shrink: 0;
}
.pos-bonkopf span:nth-child(1) { width: 28px; }
.pos-bonkopf span:nth-child(2) { flex: 1; }
.pos-bonkopf span:nth-child(3) { width: 50px; text-align: right; }
.pos-bonkopf span:nth-child(4) { width: 74px; text-align: right; }
.pos-bonkopf span:nth-child(5) { width: 74px; text-align: right; }

.pos-bonliste {
  flex: 1;
  overflow-y: auto;
  background: #fff;
}
.bon-leer {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #94a3b8;
  font-size: 14px;
}
/* Bon-Zeile */
.bon-row {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  padding: 0 16px;
  min-height: 52px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: background 0.08s;
}
.bon-row:hover { background: #f8fafc; }
.bon-row.aktiv { background: #eff6ff; }
.bon-row.aktiv::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: #2563eb;
}
.bon-row.storno-anim {
  animation: storno-flash 0.3s ease;
}
@keyframes storno-flash {
  0% { background: #fef2f2; }
  100% { background: transparent; }
}
.bon-row-nr   { width: 28px; font-size: 13px; color: #94a3b8; flex-shrink: 0; padding: 14px 0; }
.bon-row-name { flex: 1; font-size: 13px; font-weight: 600; color: #1e3a5f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 14px 8px 14px 0; }
.bon-row-rabatt { font-size: 10px; color: #f59e0b; font-weight: 700; margin-left: 4px; }
.bon-row-auftrag { border-left: 3px solid #3b82f6; padding-left: 6px; background: rgba(59,130,246,.04); }
.bon-row-auftrag-badge { font-size: 11px; margin-right: 4px; opacity: .7; }
.bon-row-retour { border-left: 3px solid #dc2626; padding-left: 6px; background: rgba(220,38,38,.04); }
.bon-row-retour-badge { font-size: 11px; margin-right: 4px; opacity: .8; }
.bon-row-retour .bon-row-menge, .bon-row-retour .bon-row-summe { color: #dc2626; }
.bon-row-separator { font-size: 10px; color: #94a3b8; text-align: center; padding: 4px 0; letter-spacing: .05em; }
.bon-row-auftrag-header { font-size: 11px; color: #3b82f6; padding: 6px 6px 2px 6px; font-weight: 700; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.bon-hdr-x { margin-left: auto; border: 1px solid #cbd5e1; background: #fff; color: #64748b; border-radius: 4px; width: 22px; height: 20px; font-size: 11px; cursor: pointer; font-family: inherit; }
.bon-hdr-hinweis { flex-basis: 100%; font-size: 11px; font-weight: 400; color: #b45309; }
.bon-row-teil { display: block; font-size: 10px; color: #b45309; font-weight: 400; }
.weitere-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
.weitere-item:hover { background: #eff6ff; }
.weitere-item input { width: 18px; height: 18px; flex-shrink: 0; }
.weitere-item-info { flex: 1; }
.weitere-item-sub { font-size: 11px; color: #64748b; }
.weitere-item-warn { color: #b45309; }
.weitere-item-offen .auftrag-item-nr { color: #94a3b8; }
.a-chip-ausstehend, .a-chip-teilbezahlt { background: #fef3c7; color: #92400e; }
.bon-row-menge { width: 50px; font-size: 13px; color: #1e3a5f; text-align: right; padding: 14px 0; }
.bon-row-ep    { width: 74px; font-size: 12px; color: #475569; text-align: right; padding: 14px 0; }
.bon-row-summe { width: 74px; font-size: 13px; font-weight: 600; color: #1e3a5f; text-align: right; padding: 14px 0; }

/* Retoure-Sektion (versendet/teilgeliefert-Auftrag geladen) */
.pos-retoure-sektion { background: #fef2f2; border-bottom: 2px solid #dc2626; flex-shrink: 0; max-height: 220px; overflow-y: auto; }
.pos-retoure-kopf { font-size: 12px; font-weight: 700; color: #991b1b; padding: 8px 12px 4px; }
.pos-retoure-zeile { display: flex; align-items: center; gap: 8px; padding: 6px 12px; font-size: 12px; border-top: 1px solid #fecaca; }
.pos-retoure-name { flex: 1; color: #1e3a5f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pos-retoure-charge { font-size: 10px; color: #7c2d12; background: #fed7aa; padding: 1px 6px; border-radius: 3px; white-space: nowrap; }
.pos-retoure-stepper { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.pos-retoure-stepper button { width: 22px; height: 22px; border: 1px solid #dc2626; background: #fff; color: #dc2626; border-radius: 4px; cursor: pointer; font-size: 13px; line-height: 1; font-family: inherit; }
.pos-retoure-stepper button:disabled { opacity: .3; cursor: default; }
.pos-retoure-menge { width: 26px; text-align: center; font-weight: 700; color: #991b1b; }
.pos-retoure-summe { padding: 6px 12px; font-size: 12px; font-weight: 700; color: #991b1b; text-align: right; border-top: 1px solid #fecaca; }

/* Kontroll-Buttons (sichtbar wenn aktiv) */
.bon-row-ctrl {
  display: none;
  width: 100%;
  padding: 4px 0 10px 28px;
  gap: 8px;
  align-items: center;
}
.bon-row.aktiv .bon-row-ctrl { display: flex; }
.bon-ctrl {
  width: 108px; height: 36px;
  border: 1.5px solid #3b82f6;
  border-radius: 6px;
  background: #dbeafe;
  color: #1d4ed8;
  font-size: 24px;
  font-weight: 700;
  cursor: pointer;
  line-height: 1;
  font-family: inherit;
  display: flex; align-items: center; justify-content: center;
}
.bon-ctrl:hover { background: #bfdbfe; }
.bon-ctrl:active { background: #93c5fd; }
.bon-ctrl-hint { font-size: 10px; color: #94a3b8; margin-left: auto; }

/* Linker Footer */
.pos-links-footer {
  height: 60px;
  background: #fff;
  border-top: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  padding: 0 16px;
  flex-shrink: 0;
}
.pos-footer-info { flex: 1; }
.pos-footer-cnt  { font-size: 12px; color: #475569; }
.pos-footer-tax  { font-size: 11px; color: #94a3b8; }
.pos-bonrab-btn {
  border: 1.5px solid #2563eb;
  background: #fff;
  color: #2563eb;
  border-radius: 6px;
  padding: 0 16px;
  height: 34px;
  font-size: 12px;
  cursor: pointer;
  font-family: inherit;
}
.pos-bonrab-btn:hover { background: #eff6ff; }

/* ── Rechte Spalte ─────────────────────────────────────────────────────────── */
.pos-rechts {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: #fff;
}

/* Scan-Bereich */
.pos-scan {
  height: 90px;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  padding: 0 14px;
  gap: 10px;
  flex-shrink: 0;
}
#scan-input {
  flex: 1;
  height: 44px;
  border: 2px solid #2563eb;
  border-radius: 8px;
  background: #f8fafc;
  color: #1e293b;
  font-size: 16px;
  padding: 0 14px;
  outline: none;
  font-family: inherit;
}
#scan-input:focus { border-color: #1d4ed8; background: #fff; }
#scan-input::placeholder { color: #94a3b8; font-size: 14px; }
.pos-menge-box {
  width: 110px;
  height: 44px;
  background: #eff6ff;
  border: 1.5px solid #93c5fd;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  cursor: pointer;
}
.pos-menge-label { font-size: 9px; color: #64748b; font-weight: 700; letter-spacing: 1px; }
#menge-display   { font-size: 20px; font-weight: 700; color: #1d4ed8; line-height: 1; }

/* Artikel-Info */
.pos-ai {
  height: 80px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  padding: 8px 14px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  flex-shrink: 0;
}
.pos-ai-leer  { color: #94a3b8; font-size: 13px; }
.pos-ai-name  { font-size: 14px; font-weight: 700; color: #1e3a5f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pos-ai-meta  { font-size: 11px; color: #64748b; margin: 2px 0; }
.pos-ai-prow  { display: flex; align-items: center; gap: 10px; }
.pos-ai-preis { font-size: 14px; font-weight: 700; color: #1e293b; }
.pos-ai-akt   { background: #fef3c7; border: 1px solid #fbbf24; border-radius: 10px; padding: 1px 8px; font-size: 10px; color: #92400e; }
.pos-lager-row { display: flex; align-items: center; gap: 14px; margin-top: 4px; }
.pos-lag-item  { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #475569; }
.pos-lag-dot   { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

/* Schnellwahl */
.pos-sw {
  flex: 1;
  border-bottom: 1px solid #e2e8f0;
  padding: 6px 10px 6px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-height: 120px;
}
.pos-sw-label {
  font-size: 9px; color: #94a3b8; font-weight: 700; letter-spacing: 1.5px;
  margin-bottom: 5px; flex-shrink: 0;
}
.pos-sw-grid {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(3, 1fr);
  gap: 5px;
}
.sw-btn {
  border-radius: 8px;
  border: 1.5px solid #93c5fd;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 4px 6px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: inherit;
  transition: background 0.08s;
}
.sw-btn:hover  { background: #dbeafe; }
.sw-btn:active { background: #93c5fd; }
.sw-btn.leer   { background: #f8fafc; border: 1px dashed #cbd5e1; color: #94a3b8; cursor: default; font-size: 18px; }
.sw-btn.sonder { background: #f0fdf4; border: 1.5px solid #86efac; color: #166534; }

/* Numpad */
.pos-numpad {
  height: 286px;
  flex-shrink: 0;
  padding: 7px 10px;
  display: grid;
  grid-template-columns: repeat(3, 1fr) repeat(2, 1.55fr);
  grid-template-rows: repeat(4, 1fr);
  gap: 5px;
  background: #fff;
  border-top: 1px solid #e2e8f0;
}
.np {
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  color: #1e293b;
  font-size: 22px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.06s;
}
.np:hover  { background: #f1f5f9; }
.np:active { background: #e2e8f0; }
.np-fn {
  border: 1.5px solid #93c5fd;
  border-radius: 8px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  display: flex; align-items: center; justify-content: center;
  text-align: center;
  transition: background 0.06s;
}
.np-fn:hover  { background: #dbeafe; }
.np-fn:active { background: #93c5fd; }
.np-del {
  border: 1.5px solid #fca5a5;
  border-radius: 8px;
  background: #fef2f2;
  color: #dc2626;
  font-size: 22px;
  cursor: pointer;
  font-family: inherit;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.06s;
}
.np-del:hover  { background: #fee2e2; }
.np-storno {
  border: 2px solid #fca5a5;
  border-radius: 8px;
  background: #fef2f2;
  color: #dc2626;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.06s;
}
.np-storno:hover  { background: #fee2e2; }
.np-storno:active { background: #fca5a5; }
.np-empty {
  border: 1px dashed #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}

/* Rechter Footer */
.pos-rf {
  height: 60px;
  background: #1e3a5f;
  display: flex;
  align-items: center;
  padding: 0 14px;
  gap: 10px;
  flex-shrink: 0;
}
.pos-rf-info { display: flex; flex-direction: column; gap: 2px; }
.pos-rf-cnt  { color: #93c5fd; font-size: 11px; }
.pos-rf-ges  { color: #fff; font-size: 22px; font-weight: 700; }
.pos-bez-btn {
  margin-left: auto;
  background: #16a34a; color: #fff;
  border: none; border-radius: 8px;
  padding: 0 36px; height: 42px;
  font-size: 16px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  white-space: nowrap;
}
.pos-bez-btn:hover    { background: #15803d; }
.pos-bez-btn:disabled { opacity: 0.35; cursor: not-allowed; }

/* ── Overlays ──────────────────────────────────────────────────────────────── */
.ov {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.7);
  z-index: 500;
  align-items: center;
  justify-content: center;
}
.ov.offen { display: flex; }
.ov-box {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 32px 36px;
  min-width: 360px;
  max-width: 520px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0,0,0,.25);
}
.ov-title { font-size: 20px; font-weight: 700; color: #1e3a5f; margin-bottom: 20px; }
.ov-label { font-size: 12px; color: #64748b; margin-bottom: 5px; font-weight: 600; }
.ov-total { font-size: 36px; font-weight: 900; color: #1e3a5f; text-align: center; margin-bottom: 22px; }
.ov-input {
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  color: #1e293b;
  font-size: 28px;
  font-weight: 700;
  padding: 10px 16px;
  width: 100%;
  text-align: right;
  outline: none;
  font-family: inherit;
}
.ov-input:focus { border-color: #2563eb; }
.ov-input-sm {
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  color: #1e293b;
  font-size: 16px;
  padding: 9px 14px;
  width: 100%;
  outline: none;
  font-family: inherit;
}
.ov-input-sm:focus { border-color: #2563eb; }
.ov-rueck { font-size: 20px; font-weight: 700; color: #16a34a; text-align: right; margin: 10px 0; }
.ov-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; }
.ov-btn {
  border: none; border-radius: 8px;
  padding: 13px 20px;
  font-size: 15px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  display: block; width: 100%; text-align: center;
  text-decoration: none;
}
.ov-btn + .ov-btn { margin-top: 8px; }
.ov-btn-prim  { background: #2563eb; color: #fff; }
.ov-btn-prim:hover  { background: #1d4ed8; }
.ov-btn-ok    { background: #16a34a; color: #fff; }
.ov-btn-ok:hover    { background: #15803d; }
.ov-btn-ok:disabled { opacity: .35; cursor: not-allowed; }
.ov-btn-sec   { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.ov-btn-sec:hover   { background: #e2e8f0; }
.ov-btn-red   { background: #dc2626; color: #fff; }
.ov-btn-red:hover   { background: #b91c1c; }

/* Schnellbeträge */
.ov-schnell { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-bottom: 14px; }
.ov-schnell-btn {
  background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px;
  padding: 8px; font-size: 13px; cursor: pointer; font-family: inherit;
  color: #1e3a5f; font-weight: 600;
}
.ov-schnell-btn:hover { background: #dbeafe; border-color: #93c5fd; }

/* Suchergebnis-Liste */
.such-liste { max-height: 300px; overflow-y: auto; margin-top: 10px; }
.such-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  border-radius: 6px;
}
.such-item:hover { background: #eff6ff; }
.such-item-name { flex: 1; font-size: 14px; font-weight: 600; color: #1e3a5f; }
.such-item-sub  { font-size: 11px; color: #64748b; }
.such-item-preis { font-size: 14px; font-weight: 700; color: #1e293b; white-space: nowrap; }
.such-item-bestand { font-size: 11px; white-space: nowrap; }

/* Chip-Varianten */
.kind-chip {
  display: inline-flex; flex-direction: column;
  background: #eff6ff; border: 1.5px solid #93c5fd; border-radius: 8px;
  padding: 8px 14px; margin: 4px; cursor: pointer;
  font-size: 13px; color: #1d4ed8; font-weight: 600;
}
.kind-chip:hover { background: #dbeafe; }
.kind-chip-sub { font-size: 11px; color: #64748b; font-weight: 400; margin-top: 2px; }
.konfig-wert-chip.gewaehlt { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.konfig-wert-chip.gewaehlt .kind-chip-sub { color: #dbeafe; }

/* Feedback-Snackbar */
#feedback {
  position: fixed;
  bottom: 74px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 600;
  pointer-events: none;
}
.fb-msg {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  box-shadow: 0 4px 16px rgba(0,0,0,.2);
  margin-bottom: 6px;
}
.fb-ok    { background: #16a34a; color: #fff; }
.fb-fehler { background: #dc2626; color: #fff; }
.fb-info  { background: #1e3a5f; color: #fff; }

/* Reservierung-Warnung */
.warn-box {
  background: #fef3c7; border: 2px solid #fbbf24; border-radius: 10px;
  padding: 14px 16px; margin-bottom: 16px; font-size: 13px; color: #92400e;
}

/* Geldschein-Tasten (Bar) */
.note-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 5px;
  margin-bottom: 10px;
}
.note-btn {
  border: none; border-radius: 6px;
  padding: 8px 4px; font-size: 13px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  color: #fff; text-align: center;
  transition: filter 0.1s;
}
.note-btn:hover  { filter: brightness(1.15); }
.note-btn:active { filter: brightness(0.9); }
.note-5   { background: #78716c; }
.note-10  { background: #dc2626; }
.note-20  { background: #2563eb; }
.note-50  { background: #d97706; }
.note-100 { background: #16a34a; }
.note-200 { background: #b45309; }
.note-500 { background: #7c3aed; }
.note-clr { background: #94a3b8; }
.bar-gegeben-row { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; }
.bar-gegeben-row .ov-label { white-space: nowrap; margin-bottom: 0; }

/* Tab-Toggle (Rabatt % / €) */
.tab-toggle { display: flex; border: 1.5px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 16px; }
.tab-btn { flex: 1; padding: 9px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; font-family: inherit; background: #f8fafc; color: #64748b; }
.tab-btn.aktiv { background: #2563eb; color: #fff; }

/* Preis-Override Button in aktiver Zeile */
.bon-ctrl-preis {
  width: auto; padding: 0 12px; height: 36px;
  border: 1.5px solid #f59e0b;
  border-radius: 6px;
  background: #fef3c7;
  color: #92400e;
  font-size: 13px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  display: flex; align-items: center; justify-content: center;
}
.bon-ctrl-preis:hover { background: #fde68a; }

/* Auftrag laden Taste */
.np-auftrag {
  border: 1.5px solid #f59e0b;
  border-radius: 8px;
  background: #fef3c7;
  color: #92400e;
  font-size: 11px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 2px;
  transition: background 0.08s;
  line-height: 1.2;
}
.np-auftrag:hover { background: #fde68a; }
.np-auftrag.geladen { background: #fde68a; border-color: #d97706; }

/* Auftrag-Suchliste */
.auftrag-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
}
.auftrag-item:hover { background: #eff6ff; }
.auftrag-item-nr { font-size: 13px; font-weight: 700; color: #1e3a5f; width: 120px; flex-shrink: 0; }
.auftrag-item-info { flex: 1; font-size: 13px; color: #374151; }
.auftrag-item-status { display: flex; gap: 4px; flex-shrink: 0; }
.auftrag-item-betrag { font-size: 14px; font-weight: 700; color: #1e3a5f; width: 80px; text-align: right; flex-shrink: 0; }
.a-chip { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; white-space: nowrap; }
.a-chip-offen { background: #dbeafe; color: #1e40af; }
.a-chip-bezahlt { background: #dcfce7; color: #166534; }
.a-chip-versandbereit { background: #fef3c7; color: #92400e; }
.a-chip-abgeschlossen { background: #f3f4f6; color: #6b7280; }

/* Sondertasten im Numpad (Kassenlade + Freier Artikel) */
.np-lade {
  border: 1.5px solid #475569;
  border-radius: 8px;
  background: #334155;
  color: #e2e8f0;
  font-size: 12px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.08s;
}
.np-lade:hover  { background: #475569; }
.np-add {
  border: 1.5px solid #86efac;
  border-radius: 8px;
  background: #f0fdf4;
  color: #166534;
  font-size: 12px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.08s;
}
.np-add:hover  { background: #dcfce7; }

/* Spinner */
.spinner-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.5); z-index: 700;
  align-items: center; justify-content: center;
}
.spinner-overlay.offen { display: flex; }
.spinner {
  width: 52px; height: 52px;
  border: 5px solid rgba(255,255,255,.2);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<!-- ── HEADER ────────────────────────────────────────────────────────────── -->
<div class="ph">
  <div class="ph-title">MeaLana · Kasse</div>
  <div class="ph-sub"><?= htmlspecialchars($kasseInfo['name'] ?? '') ?> (<?= htmlspecialchars($kasseInfo['kasse_nr'] ?? '') ?>) · Lager: <?= htmlspecialchars($lagerName) ?></div>
  <div style="font-size:11px;font-weight:700;padding:2px 10px;border-radius:10px;letter-spacing:.4px;white-space:nowrap;
              <?= $modus === 'offline'
                  ? 'background:#451a03;color:#f59e0b;'
                  : 'background:#052e16;color:#4ade80;' ?>">
    <?= $modus === 'offline' ? 'MESSEBETRIEB' : 'ONLINE' ?>
  </div>
  <?php if ($rksvId): ?>
  <div class="ph-rksv" onclick="nullbonDialog()" title="Nullbon erstellen">
    <div class="ph-rksv-dot<?= $modus === 'offline' ? ' offline' : '' ?>"></div>
    RKSV <?= $modus === 'offline' ? 'offline' : 'aktiv' ?>
  </div>
  <?php endif; ?>
  <div class="ph-right">
    <button class="ph-btn ph-btn-menu" onclick="toggleMenue(event)">⚙ Menü</button>
    <button class="ph-btn ph-btn-mitgeb" onclick="mitgebenDialog()">Mitgeben ▷</button>
    <button class="ph-btn ph-btn-parken" id="btn-parken" onclick="bonParken()">⏸ Parken</button>
    <button class="ph-btn ph-btn-close" onclick="location.href='<?= BASE_PATH ?>/kasse/index.php'">✕ Schließen</button>
  </div>
  <!-- Dropdown -->
  <div class="ph-dropdown" id="ph-dropdown">
    <button class="ph-dd-item" onclick="kasseladeOeffnen()">⊟ Kassenlade öffnen</button>
    <button class="ph-dd-item" onclick="diversDialog()">+ Freier Artikel</button>
    <button class="ph-dd-item" onclick="gutscheinVerkaufDialog()">🎁 Gutschein verkaufen</button>
    <button class="ph-dd-item" onclick="gutscheinAbfrageDialog()">🔍 Gutschein abfragen</button>
    <button class="ph-dd-item" onclick="rechnungZahlenDialog()">💶 Rechnung bezahlen</button>
    <button class="ph-dd-item" onclick="freitextRetourDialog()">↩ Freitext-Retour</button>
    <div class="ph-dd-sep"></div>
    <button class="ph-dd-item" onclick="bonAbrufen()">⏸ Geparkten Bon abrufen</button>
    <div class="ph-dd-sep"></div>
    <a class="ph-dd-item" href="<?= BASE_PATH ?>/kasse/kassenbuch.php">💰 Kassenbuch</a>
    <a class="ph-dd-item" href="<?= BASE_PATH ?>/kasse/kassensturz.php">📊 Kassenstand / X-Bon</a>
    <a class="ph-dd-item" href="<?= BASE_PATH ?>/kasse/bon_journal.php">📋 Bon-Journal</a>
    <div class="ph-dd-sep"></div>
    <a class="ph-dd-item" href="<?= BASE_PATH ?>/kasse/offene_auswahl.php">↗ Offene Auswahl (Mitgegeben)</a>
  </div>
</div>

<!-- ── HAUPTLAYOUT ────────────────────────────────────────────────────────── -->
<div class="pos-layout">

  <!-- ── LINKE SPALTE ───────────────────────────────────────────────────── -->
  <div class="pos-links">

    <!-- Kundenkopf -->
    <div class="pos-kunde">
      <span style="font-size:18px">👤</span>
      <span class="pos-kunde-name" id="kunden-anzeige">Laufkunde</span>
      <button class="pos-kunde-btn" onclick="kundeDialog()">+ Kunde suchen</button>
    </div>

    <!-- Retoure-Sektion (nur bei versendet/teilgeliefert-Auftrag geladen) -->
    <div class="pos-retoure-sektion" id="retoure-sektion" style="display:none">
      <div class="pos-retoure-kopf">↩ Retoure zu <span id="retoure-auftrag-nr"></span></div>
      <div id="retoure-liste"></div>
      <div class="pos-retoure-summe" id="retoure-summe"></div>
    </div>

    <!-- Spaltenkopf -->
    <div class="pos-bonkopf">
      <span>#</span><span>ARTIKEL</span><span>MNG</span><span>E-PREIS</span><span>SUMME</span>
    </div>

    <!-- Bon-Liste -->
    <div class="pos-bonliste" id="bon-liste">
      <div class="bon-leer" id="bon-leer">Noch keine Artikel</div>
    </div>

    <!-- Linker Footer -->
    <div class="pos-links-footer">
      <div class="pos-footer-info">
        <div class="pos-footer-cnt" id="footer-cnt">0 Artikel</div>
        <div class="pos-footer-tax" id="footer-tax">inkl. MwSt.</div>
      </div>
      <button class="pos-bonrab-btn" onclick="bonRabattDialog()">% Bon-Rabatt</button>
    </div>

  </div><!-- /pos-links -->

  <!-- ── RECHTE SPALTE ──────────────────────────────────────────────────── -->
  <div class="pos-rechts">

    <!-- Scan-Bereich -->
    <div class="pos-scan">
      <input type="text" id="scan-input"
             placeholder="EAN / Artikelnummer scannen…"
             autocomplete="off" spellcheck="false">
      <button type="button" onclick="openArtikelSuche()" title="Artikel nach Name suchen"
          style="height:44px;width:44px;padding:0;font-size:20px;background:none;border:2px solid #2563eb;border-radius:8px;color:#2563eb;cursor:pointer;flex-shrink:0">
          🔍
      </button>
      <div class="pos-menge-box" onclick="mengeReset()">
        <div class="pos-menge-label">MENGE</div>
        <div id="menge-display">1 ×</div>
      </div>
    </div>

    <!-- Artikel-Suche Modal -->
    <div class="ov" id="ov-artikelsuche">
      <div class="ov-box" style="max-width:600px;width:90vw">
        <div class="ov-title">Artikel suchen</div>
        <div style="display:flex;gap:8px;margin-bottom:12px">
          <input type="text" id="as-input" class="bon-input" style="flex:1;font-size:15px"
              placeholder="Name, Artikelnummer oder EAN…"
              oninput="artikelSucheInput()" autocomplete="off">
          <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-artikelsuche')">✕</button>
        </div>
        <div id="as-ergebnisse" style="max-height:400px;overflow-y:auto"></div>
      </div>
    </div>

    <!-- Artikel-Info -->
    <div class="pos-ai" id="ai-box">
      <div class="pos-ai-leer" id="ai-leer">Scan-Ergebnis erscheint hier…</div>
      <div id="ai-inhalt" style="display:none">
        <div class="pos-ai-name" id="ai-name"></div>
        <div class="pos-ai-meta" id="ai-meta"></div>
        <div class="pos-ai-prow">
          <span class="pos-ai-preis" id="ai-preis"></span>
          <span class="pos-ai-akt"  id="ai-akt"  style="display:none"></span>
        </div>
        <div class="pos-lager-row" id="ai-lager"></div>
      </div>
    </div>

    <!-- Schnellwahl -->
    <div class="pos-sw">
      <div class="pos-sw-label">SCHNELLWAHL</div>
      <div class="pos-sw-grid" id="sw-grid">
        <!-- Wird per PHP/JS befüllt -->
      </div>
    </div>

    <!-- Numpad -->
    <div class="pos-numpad">
      <!-- Zeile 1: 7 8 9 | ×Mal | %Rabatt -->
      <button class="np" onclick="npDruck('7')">7</button>
      <button class="np" onclick="npDruck('8')">8</button>
      <button class="np" onclick="npDruck('9')">9</button>
      <button class="np-fn" onclick="npMal()">× Mal</button>
      <button class="np-fn" onclick="npRabatt()">% Rabatt</button>
      <!-- Zeile 2: 4 5 6 | ⌫ | [leer] -->
      <button class="np" onclick="npDruck('4')">4</button>
      <button class="np" onclick="npDruck('5')">5</button>
      <button class="np" onclick="npDruck('6')">6</button>
      <button class="np-del" onclick="npBack()">⌫</button>
      <button class="np-auftrag" id="btn-auftrag-laden" onclick="auftragLadenDialog()" title="Auftrag laden / Abholung">📦<span>Auftrag</span></button>
      <!-- Zeile 3: 1 2 3 | STORNO (span 2) -->
      <button class="np" onclick="npDruck('1')">1</button>
      <button class="np" onclick="npDruck('2')">2</button>
      <button class="np" onclick="npDruck('3')">3</button>
      <button class="np-storno" style="grid-column:4/6" onclick="npStorno()">STORNO — aktive Zeile</button>
      <!-- Zeile 4: 0 (span 2) , | Kassenlade | Freier Artikel -->
      <button class="np" style="grid-column:1/3" onclick="npDruck('0')">0</button>
      <button class="np" onclick="npDruck(',')">&#44;</button>
      <button class="np-lade" onclick="kasseladeOeffnen()" title="Kassenlade öffnen">⊟ Lade</button>
      <button class="np-add"  onclick="diversDialog()"      title="Freier Artikel">+ Artikel</button>
    </div>

    <!-- Rechter Footer -->
    <div class="pos-rf">
      <div class="pos-rf-info">
        <div class="pos-rf-cnt" id="rf-cnt">0 Artikel · inkl. MwSt.</div>
        <div class="pos-rf-ges" id="rf-ges">€ 0,00</div>
      </div>
      <button class="pos-bez-btn" id="btn-bezahlen" onclick="bezahlenDialog()" disabled>BEZAHLEN ▶</button>
    </div>

  </div><!-- /pos-rechts -->
</div><!-- /pos-layout -->


<!-- ══════════════════════════════════════════════════════════════════════════
     OVERLAYS
     ══════════════════════════════════════════════════════════════════════════ -->

<!-- Bezahlen -->
<div class="ov" id="ov-bezahlen">
  <div class="ov-box" style="max-width:480px">
    <div class="ov-title">Zahlung</div>
    <div class="ov-total" id="bez-total">€ 0,00</div>
    <div class="ov-grid2">
      <button class="ov-btn" style="background:#16a34a;color:#fff;font-size:16px" onclick="zahlenBar()">💶 Bar</button>
      <button class="ov-btn" style="background:#2563eb;color:#fff;font-size:16px" onclick="zahlenKarte()">💳 Karte</button>
      <button class="ov-btn" style="background:#7c3aed;color:#fff" onclick="zahlenGutschein()">🎁 Gutschein</button>
      <button class="ov-btn" style="background:#0891b2;color:#fff" onclick="zahlenKombi()">💱 Kombi</button>
    </div>
    <button class="ov-btn ov-btn-sec" style="margin-top:10px" onclick="ovSchliessen('ov-bezahlen')">Abbrechen</button>
  </div>
</div>

<!-- Bar -->
<div class="ov" id="ov-bar">
  <div class="ov-box" style="max-width:500px">
    <div class="ov-title">Bar bezahlen</div>
    <div class="ov-total" id="bar-total">€ 0,00</div>

    <!-- Geldscheine — immer alle sichtbar, klick addiert -->
    <div class="note-grid">
      <button class="note-btn note-5"   onclick="barNoteAdd(5)">€ 5</button>
      <button class="note-btn note-10"  onclick="barNoteAdd(10)">€ 10</button>
      <button class="note-btn note-20"  onclick="barNoteAdd(20)">€ 20</button>
      <button class="note-btn note-50"  onclick="barNoteAdd(50)">€ 50</button>
      <button class="note-btn note-100" onclick="barNoteAdd(100)">€ 100</button>
      <button class="note-btn note-200" onclick="barNoteAdd(200)">€ 200</button>
      <button class="note-btn note-clr" onclick="barClear()" title="Zurücksetzen">C</button>
    </div>

    <!-- Zusammenfassung der eingetippten Scheine -->
    <div id="bar-scheine-log" style="font-size:12px;color:#64748b;min-height:18px;margin-bottom:8px;text-align:right"></div>

    <!-- Direkte Eingabe -->
    <div class="bar-gegeben-row">
      <div class="ov-label">Gegeben (€)</div>
    </div>
    <input class="ov-input" type="number" id="bar-gegeben" step="0.01" min="0"
           placeholder="0,00" oninput="barBerechneManual()">

    <div class="ov-rueck" id="bar-rueck"></div>
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-ok" id="btn-bar-ok" onclick="abschliessenBar()">✓ Abschließen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-bar')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Karte -->
<div class="ov" id="ov-karte">
  <div class="ov-box" style="text-align:center">
    <div class="ov-title" style="text-align:left">Kartenzahlung</div>
    <div class="ov-total" id="karte-total">€ 0,00</div>
    <p style="color:#64748b;font-size:15px;margin-bottom:24px">💳 Bitte Zahlung am Terminal<br>abschließen</p>
    <button class="ov-btn ov-btn-ok" style="margin-bottom:10px" onclick="abschliessenKarte()">✓ Terminal bestätigt</button>
    <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-karte')">Abbrechen</button>
  </div>
</div>

<!-- Gutschein -->
<div class="ov" id="ov-gutschein">
  <div class="ov-box">
    <div class="ov-title">Mit Gutschein bezahlen</div>
    <div class="ov-total" id="gs-total">€ 0,00</div>
    <div class="ov-label">Gutschein-Code</div>
    <div style="display:flex;gap:8px">
      <input class="ov-input-sm" type="text" id="gs-code" placeholder="MEA-XXXX-XXXX-XXXX"
             style="font-size:20px;flex:1;text-transform:uppercase" oninput="gsCodeGeaendert()"
             onkeydown="if(event.key==='Enter')gsPruefen()">
      <button class="ov-btn ov-btn-sec" style="width:auto;padding:0 18px" onclick="gsPruefen()">Prüfen</button>
    </div>
    <div id="gs-info" style="min-height:24px;margin-top:10px;font-size:14px;color:#64748b"></div>
    <div id="gs-rest-bereich" hidden style="margin-top:10px">
      <div class="ov-label">Rest bar gegeben (€, optional)</div>
      <input class="ov-input-sm" type="number" id="gs-gegeben" step="0.01" min="0" placeholder="0,00"
             oninput="gsRueckgeld()" style="font-size:18px">
      <div id="gs-rueck" style="min-height:20px;margin-top:6px;font-size:14px"></div>
      <div class="ov-grid2" style="margin-top:10px">
        <button class="ov-btn" style="background:#16a34a;color:#fff" onclick="abschliessenGS('bar')">💶 Rest bar</button>
        <button class="ov-btn" style="background:#2563eb;color:#fff" onclick="abschliessenGS('karte_extern')">💳 Rest Karte</button>
      </div>
    </div>
    <div class="ov-grid2" style="margin-top:14px">
      <button class="ov-btn ov-btn-ok" id="btn-gs-ok" onclick="abschliessenGS(null)" disabled>✓ Einlösen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-gutschein')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Kombi -->
<div class="ov" id="ov-kombi">
  <div class="ov-box">
    <div class="ov-title">Kombizahlung</div>
    <div class="ov-total" id="kombi-total">€ 0,00</div>
    <div class="ov-grid2">
      <div>
        <div class="ov-label">Karte (€)</div>
        <input class="ov-input" type="number" id="kombi-karte" step="0.01" min="0"
               placeholder="0,00" oninput="kombiBerechne()" style="font-size:22px">
      </div>
      <div>
        <div class="ov-label">Bar (€)</div>
        <input class="ov-input" type="number" id="kombi-bar" step="0.01" min="0"
               placeholder="0,00" oninput="kombiBerechne()" style="font-size:22px">
      </div>
    </div>
    <div class="ov-rueck" id="kombi-diff" style="color:#64748b"></div>
    <div class="ov-grid2" style="margin-top:8px">
      <button class="ov-btn ov-btn-ok" id="btn-kombi-ok" onclick="abschliessenKombi()" disabled>✓ Abschließen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-kombi')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Vater-Variante -->
<div class="ov" id="ov-vater">
  <div class="ov-box" style="max-width:580px;max-height:80vh;overflow-y:auto">
    <div class="ov-title" id="vater-titel">Variante wählen</div>
    <div id="vater-kinder"></div>
    <button class="ov-btn ov-btn-sec" style="margin-top:14px" onclick="ovSchliessen('ov-vater')">Abbrechen</button>
  </div>
</div>

<!-- Konfigurator-Auswahl -->
<div class="ov" id="ov-konfigurator">
  <div class="ov-box" style="max-width:580px;max-height:80vh;overflow-y:auto">
    <div class="ov-title" id="konfig-titel">Optionen wählen</div>
    <div id="konfig-achsen"></div>
    <div class="ov-total" id="konfig-preis">€ 0,00</div>
    <div id="konfig-fehler" style="color:#dc2626;font-size:13px;margin-bottom:8px"></div>
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-ok" id="btn-konfig-ok" onclick="konfigBestaetigen()" disabled>✓ Übernehmen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-konfigurator')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Divers-Artikel -->
<div class="ov" id="ov-divers">
  <div class="ov-box">
    <div class="ov-title">Freier Preis-Artikel</div>
    <div class="ov-label">Bezeichnung</div>
    <input class="ov-input-sm" type="text" id="div-name" placeholder="z.B. Strickberatung"
           style="margin-bottom:12px" oninput="divPruefen()">
    <div class="ov-label">Bruttopreis (€)</div>
    <input class="ov-input" type="number" id="div-preis" step="0.01" min="0"
           placeholder="0,00" oninput="divPruefen()" style="margin-bottom:12px;font-size:22px">
    <div class="ov-label">Steuer</div>
    <select class="ov-input-sm" id="div-steuer" style="margin-bottom:14px">
      <option value="20">20 %</option>
      <option value="10">10 %</option>
      <option value="0">0 %</option>
    </select>
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-ok" id="btn-div-ok" onclick="divHinzufuegen()" disabled>+ Hinzufügen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-divers')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Gutschein abfragen (nur Auskunft, keine Buchung) -->
<div class="ov" id="ov-gs-abfrage">
  <div class="ov-box">
    <div class="ov-title">🔍 Gutschein abfragen</div>
    <div class="ov-label">Gutschein-Code</div>
    <div style="display:flex;gap:8px">
      <input class="ov-input-sm" type="text" id="gsa-code" placeholder="MEA-XXXX-XXXX-XXXX"
             style="font-size:20px;flex:1;text-transform:uppercase"
             onkeydown="if(event.key==='Enter')gutscheinAbfragen()">
      <button class="ov-btn ov-btn-sec" style="width:auto;padding:0 18px" onclick="gutscheinAbfragen()">Abfragen</button>
    </div>
    <div id="gsa-ergebnis" style="min-height:24px;margin:14px 0;font-size:14px"></div>
    <div style="font-size:12px;color:#94a3b8;margin-bottom:10px">Nur Auskunft — es wird nichts gebucht.</div>
    <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-gs-abfrage')">Schließen</button>
  </div>
</div>

<!-- Gutschein verkaufen -->
<div class="ov" id="ov-gs-verkauf">
  <div class="ov-box">
    <div class="ov-title">🎁 Gutschein verkaufen</div>
    <div class="ov-label">Betrag (€)</div>
    <input class="ov-input" type="number" id="gsv-betrag" step="0.01" min="0"
           placeholder="0,00" oninput="gsvPruefen()" style="margin-bottom:8px;font-size:22px">
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
      <?php foreach ([10, 20, 25, 30, 50, 100] as $gsvBetrag): ?>
        <button class="ov-btn ov-btn-sec" style="width:auto;flex:1;padding:8px 0"
                onclick="gsvBetragSetzen(<?= $gsvBetrag ?>)"><?= $gsvBetrag ?> €</button>
      <?php endforeach; ?>
    </div>
    <div class="ov-label">Für (Name auf dem Gutschein, optional)</div>
    <input class="ov-input-sm" type="text" id="gsv-empfaenger" maxlength="150" placeholder="z.B. Maria"
           style="margin-bottom:14px">
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-ok" id="btn-gsv-ok" onclick="gsvHinzufuegen()" disabled>+ Hinzufügen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-gs-verkauf')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Rechnung bezahlen (Zahlbeleg, 0 % — USt steht schon auf der Rechnung) -->
<div class="ov" id="ov-re-zahlung">
  <div class="ov-box">
    <div class="ov-title">💶 Rechnung bezahlen</div>
    <div class="ov-label">Rechnungs-/Auftragsnummer (auch Teil, z.B. 0045) oder Kundenname</div>
    <input class="ov-input-sm" type="text" id="rz-suche" placeholder="z.B. R-2026-00012, 0045 oder Huber"
           oninput="rzSuchenVerzoegert()" onkeydown="if(event.key==='Enter'){rzSuchen();}" style="margin-bottom:8px">
    <div id="rz-ergebnis" style="font-size:14px;margin-bottom:10px;max-height:260px;overflow:auto"></div>
    <div id="rz-betrag-box" style="display:none">
      <div class="ov-label">Betrag (€) — höchstens offener Betrag</div>
      <input class="ov-input" type="number" id="rz-betrag" step="0.01" min="0" style="margin-bottom:12px;font-size:22px">
    </div>
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-ok" id="btn-rz-ok" onclick="rzHinzufuegen()" disabled>+ Hinzufügen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-re-zahlung')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Mitgeben -->
<div class="ov" id="ov-mitgeben">
  <div class="ov-box">
    <div class="ov-title">↗ Mitgeben (Offene Auswahl)</div>
    <div class="ov-label">Kundenname (optional)</div>
    <input class="ov-input-sm" type="text" id="mg-name" placeholder="Name oder leer lassen"
           style="margin-bottom:12px">
    <div class="ov-label">Rückgabe bis (optional)</div>
    <input class="ov-input-sm" type="date" id="mg-datum" style="margin-bottom:14px">
    <p style="font-size:12px;color:#64748b;margin-bottom:16px">
      Die aktuellen Bon-Artikel werden als „mitgegeben" gebucht und aus dem Lager ausgebucht.
      Beim Rückkauf wird der Bon erstellt.
    </p>
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-ok" onclick="mitgebenSpeichern()">↗ Mitgeben</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-mitgeben')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Bon-Rabatt -->
<div class="ov" id="ov-bonrab">
  <div class="ov-box">
    <div class="ov-title">Bon-Rabatt</div>

    <!-- Toggle % / € -->
    <div class="tab-toggle">
      <button class="tab-btn aktiv" id="rab-tab-pct" onclick="rabTab('pct')">% Prozent</button>
      <button class="tab-btn"       id="rab-tab-eur" onclick="rabTab('eur')">€ Neuer Gesamtpreis</button>
    </div>

    <!-- Prozent-Eingabe -->
    <div id="rab-pct-area">
      <div class="ov-label">Rabatt (%)</div>
      <input class="ov-input" type="number" id="bonrab-pct" min="0" max="100" step="1"
             placeholder="z.B. 10" style="font-size:28px;margin-bottom:12px" oninput="bonRabattVorschau()">
    </div>

    <!-- Betrag-Eingabe -->
    <div id="rab-eur-area" style="display:none">
      <div class="ov-label">Neuer Gesamtpreis (€)</div>
      <input class="ov-input" type="number" id="bonrab-eur" min="0" step="0.01"
             placeholder="0,00" style="font-size:28px;margin-bottom:8px" oninput="bonRabattVorschauEur()">
      <p style="font-size:11px;color:#94a3b8;margin-bottom:12px">
        Rabatt wird proportional auf alle Artikel aufgeteilt (anteilig je Steuerklasse — wie Lidl/Billa).
      </p>
    </div>

    <div id="bonrab-vorschau" style="font-size:13px;color:#64748b;min-height:20px;margin-bottom:16px"></div>

    <div class="ov-grid2">
      <button class="ov-btn ov-btn-ok" onclick="bonRabattAnwenden()">✓ Anwenden</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-bonrab')">Abbrechen</button>
    </div>
    <button class="ov-btn ov-btn-sec" style="margin-top:8px" onclick="bonRabattEntfernen()">✕ Rabatt entfernen</button>
  </div>
</div>

<!-- Kunden-Suche -->
<div class="ov" id="ov-kunde">
  <div class="ov-box" style="max-width:540px">
    <div class="ov-title">👤 Kunde suchen</div>
    <input class="ov-input-sm" type="text" id="kunde-input"
           placeholder="Name, Firma, Kundennummer oder E-Mail…"
           oninput="kundeSucheLive(this.value)" style="margin-bottom:6px">
    <div class="such-liste" id="kunde-liste"></div>
    <div style="margin-top:14px;display:flex;gap:8px">
      <button class="ov-btn ov-btn-sec" style="flex:1" onclick="kundeLaufkunde()">Laufkunde</button>
      <button class="ov-btn ov-btn-sec" style="flex:1" onclick="ovSchliessen('ov-kunde')">Schließen</button>
    </div>
  </div>
</div>

<!-- Artikel-Suche -->
<div class="ov" id="ov-suche">
  <div class="ov-box" style="max-width:560px">
    <div class="ov-title">Artikel suchen</div>
    <input class="ov-input-sm" type="text" id="suche-input"
           placeholder="Name, Artikelnummer oder EAN…"
           oninput="sucheLive(this.value)" style="margin-bottom:6px">
    <div class="such-liste" id="such-liste"></div>
    <button class="ov-btn ov-btn-sec" style="margin-top:14px" onclick="ovSchliessen('ov-suche')">Schließen</button>
  </div>
</div>

<!-- Freitext-Retour: Artikel ohne Auftragsbezug zurücknehmen (JTL-Altbestand o.ä.) -->
<div class="ov" id="ov-freitext-retour">
  <div class="ov-box" style="max-width:560px">
    <div class="ov-title">↩ Freitext-Retour</div>
    <div id="fr-schritt-suche">
      <div style="font-size:12px;color:#64748b;margin-bottom:10px">
        Für Rückgaben ohne Auftrag im System (z.B. alter JTL-Verkauf). Artikel wählen, dann Menge und Preis der Rückgabe eintragen.
      </div>
      <input class="ov-input-sm" type="text" id="fr-such-input"
             placeholder="Name, Artikelnummer oder EAN…"
             oninput="freitextRetourSucheLive(this.value)" style="margin-bottom:6px">
      <div class="such-liste" id="fr-such-liste"></div>
    </div>
    <div id="fr-schritt-menge" style="display:none">
      <div style="font-size:15px;font-weight:700;color:#1e3a5f;margin-bottom:14px" id="fr-artikel-name"></div>
      <label style="display:block;font-size:12px;color:#64748b;margin-bottom:4px">Menge (zurückgenommen):</label>
      <input class="ov-input-sm" type="number" id="fr-menge" value="1" min="1" step="1" style="margin-bottom:12px">
      <label style="display:block;font-size:12px;color:#64748b;margin-bottom:4px">Preis pro Stück (€, Rückerstattung):</label>
      <input class="ov-input-sm" type="number" id="fr-preis" step="0.01" min="0" style="margin-bottom:16px">
      <div id="fr-charge-block" style="display:none;margin-bottom:16px">
        <label style="display:block;font-size:12px;color:#64748b;margin-bottom:4px">Charge/Los dieses Artikels (Chargenpflicht!):</label>
        <input class="ov-input-sm" type="text" id="fr-charge" placeholder="z.B. LOT-2024-007" style="margin-bottom:6px">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#64748b;cursor:pointer">
          <input type="checkbox" id="fr-charge-unbekannt" onchange="freitextRetourChargeUnbekanntToggle()">
          Charge unbekannt — muss von Packplatz nachgetragen werden
        </label>
      </div>
      <button class="ov-btn ov-btn-prim" onclick="freitextRetourUebernehmen()">↩ Zurücknehmen</button>
      <button class="ov-btn ov-btn-sec" onclick="freitextRetourZurueckZurSuche()">◀ Anderer Artikel</button>
    </div>
    <button class="ov-btn ov-btn-sec" style="margin-top:14px" onclick="ovSchliessen('ov-freitext-retour')">Schließen</button>
  </div>
</div>

<!-- Charge-Auswahl -->
<div class="ov" id="ov-charge">
  <div class="ov-box" style="max-width:540px">
    <div class="ov-title" id="charge-ov-titel">Charge auswählen</div>
    <div id="charge-ov-body" style="max-height:320px;overflow-y:auto;margin-bottom:16px"></div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid #e2e8f0;margin-bottom:12px">
      <span style="font-size:13px;color:#64748b">Benötigt: <strong id="charge-ov-menge">—</strong></span>
      <span style="font-size:13px">Gewählt: <strong id="charge-ov-gesamt" style="color:#2563eb">0</strong></span>
    </div>
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-sec" onclick="chargeKasseAbbrechen()">Abbrechen</button>
      <button class="ov-btn ov-btn-blue" id="btn-charge-kasse-ok" onclick="chargeKasseBestaetigen()" disabled>✓ Bestätigen</button>
    </div>
  </div>
</div>

<!-- Reservierung-Warnung -->
<div class="ov" id="ov-reswarn">
  <div class="ov-box">
    <div class="ov-title" style="color:#92400e">⚠ Lagerkonflikt</div>
    <div class="warn-box" id="reswarn-text"></div>
    <p style="font-size:13px;color:#64748b;margin-bottom:16px">
      Trotzdem hinzufügen? Der reservierte Auftrag könnte nicht mehr erfüllbar sein.
    </p>
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-red" onclick="reswarnBestaetigen()">Trotzdem buchen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-reswarn')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- RKSV: BFR nicht erreichbar (2 Eskalationsstufen) -->
<div class="ov" id="ov-bfr-ausfall" style="z-index:900">
  <div class="ov-box" style="max-width:460px;text-align:center">
    <div style="font-size:32px;margin-bottom:6px">⚠</div>
    <div id="bfr-popup-stufe1">
      <div class="ov-title" style="text-align:center">Dienst nicht erreichbar!</div>
      <p style="color:#64748b;font-size:14px;margin-bottom:22px">
        Die technische Sicherheitseinrichtung (BFR) antwortet nicht.
      </p>
    </div>
    <div id="bfr-popup-stufe2" style="display:none">
      <div class="ov-title" style="text-align:center">Dienst immer noch nicht erreichbar</div>
      <p style="color:#64748b;font-size:14px;margin-bottom:14px">
        Die Kasse bleibt gesperrt, bis der BFR-Dienst wieder antwortet.
        Bitte am Gerät prüfen:
      </p>
      <ul style="text-align:left;color:#64748b;font-size:13px;margin:0 0 22px 20px;padding:0">
        <li>Läuft "BFR" in der Taskleiste?</li>
        <li>Signaturkarte im Kartenleser gesteckt?</li>
        <li>Windows-Update / Firewall gerade aktiv?</li>
      </ul>
    </div>
    <div id="bfr-popup-kontext" style="font-size:12px;color:#94a3b8;margin-bottom:16px"></div>
    <?php if (!empty($kasseInfo['bfr_url'])): ?>
    <p style="font-size:12px;color:#94a3b8;margin:0 0 16px">
        Gemeldete BFR-Adresse: <strong><?= htmlspecialchars($kasseInfo['bfr_url']) ?></strong> — korrekt?
        Sonst in den <a href="<?= BASE_PATH ?>/einstellungen/kasse_registrierung.php?id=<?= $kasseId ?>" target="_blank">Kassen-Einstellungen ändern</a>.
    </p>
    <?php endif; ?>
    <button class="ov-btn ov-btn-prim" id="btn-bfr-retry" onclick="bfrErneutVersuchen()">Erneut versuchen</button>
  </div>
</div>

<div class="ov" id="ov-nullbestand">
  <div class="ov-box">
    <div class="ov-title" style="color:#92400e">⚠ Lagerbestand 0</div>
    <div class="warn-box" style="margin-bottom:14px">
      <strong id="nullbest-name" style="display:block;margin-bottom:4px"></strong>
      <span style="font-size:12px;color:#64748b" id="nullbest-artnr"></span>
    </div>
    <p style="font-size:13px;color:#64748b;margin-bottom:16px">
      Systembestand ist 0 — Artikel physisch gefunden?<br>
      Eine Korrekturbuchung wird automatisch erstellt.
    </p>
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-red" onclick="nullbestandBestaetigen()">Trotzdem buchen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-nullbestand')">Abbrechen</button>
    </div>
  </div>
</div>

<div class="ov" id="ov-nullbon">
  <div class="ov-box">
    <div class="ov-title">RKSV Nullbon</div>
    <p style="font-size:13px;color:#64748b;margin-bottom:16px">
      Nullbon jetzt erstellen? Kein Umsatz, dient nur der RKSV-Absicherung
      (z.B. monatliche Kontrolle oder auf Wunsch der Buchhaltung).
    </p>
    <div class="ov-grid2">
      <button class="ov-btn ov-btn-ok" onclick="nullbonBestaetigen()">Nullbon erstellen</button>
      <button class="ov-btn ov-btn-sec" onclick="ovSchliessen('ov-nullbon')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Spinner -->
<div class="spinner-overlay" id="spinner">
  <div class="spinner"></div>
</div>

<!-- ── Auftrag laden ──────────────────────────────────────────────────────── -->
<div class="ov" id="ov-auftrag-laden">
  <div class="ov-box" style="max-width:620px;max-height:80vh;display:flex;flex-direction:column">
    <div class="ov-title">📦 Auftrag laden / Abholung</div>
    <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center">
      <input type="text" id="auftrag-such-feld" class="ov-input" placeholder="Auftragsnummer oder Kundenname …"
             oninput="auftragSuchen()" style="flex:1;margin-bottom:0">
      <label style="display:flex;align-items:center;gap:5px;font-size:12px;white-space:nowrap;cursor:pointer">
        <input type="checkbox" id="auftrag-alle-cb" onchange="auftragSucheAusfuehren()"> Alle Aufträge
      </label>
    </div>
    <div id="auftrag-liste" style="flex:1;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;min-height:200px">
      <div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px">Lädt …</div>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:12px">
      <button class="ov-btn ov-btn-sec" style="width:auto;padding:0 20px;height:40px" onclick="ovSchliessen('ov-auftrag-laden')">Abbrechen</button>
    </div>
  </div>
</div>

<!-- ── Geparkte Bons ─────────────────────────────────────────────────────── -->
<div class="ov" id="ov-geparkt">
  <div class="ov-box" style="max-width:560px;max-height:80vh;display:flex;flex-direction:column">
    <div class="ov-title">⏸ Geparkte Bons</div>
    <div id="geparkt-liste" style="flex:1;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;min-height:120px;margin-bottom:14px">
      <div style="padding:20px;text-align:center;color:#94a3b8;font-size:13px">Lädt …</div>
    </div>
    <div style="display:flex;justify-content:flex-end">
      <button class="ov-btn ov-btn-sec" style="width:auto;padding:0 20px;height:38px" onclick="ovSchliessen('ov-geparkt')">Schließen</button>
    </div>
  </div>
</div>

<!-- ── Sammelabholung: weitere Abholungen desselben Kunden ────────────────── -->
<div class="ov" id="ov-weitere-auftraege">
  <div class="ov-box" style="max-width:560px;max-height:80vh;display:flex;flex-direction:column">
    <div class="ov-title" id="weitere-titel">📦 Auftrag geladen</div>
    <div id="weitere-info" style="font-size:13px;color:#374151;margin-bottom:10px"></div>
    <div id="weitere-liste" style="flex:1;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px"></div>
    <div style="font-size:11px;color:#64748b;margin-top:8px">Noch nicht gepackte Aufträge sind nicht vorausgewählt.</div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
      <button class="ov-btn ov-btn-sec" style="width:auto;padding:0 20px;height:40px" onclick="ovSchliessen('ov-weitere-auftraege')">Nur diesen</button>
      <button class="ov-btn ov-btn-ok" style="width:auto;padding:0 20px;height:40px" id="btn-weitere-laden" onclick="weitereMitladen()">Ausgewählte mitladen</button>
    </div>
  </div>
</div>

<!-- ── Mitnehmen-Frage ────────────────────────────────────────────────────── -->
<div class="ov" id="ov-mitnehmen">
  <div class="ov-box" style="max-width:480px">
    <div class="ov-title">📦 Auftrag — Was passiert mit der Ware?</div>
    <div id="ov-mitnehmen-info" style="color:#94a3b8;font-size:13px;margin-bottom:20px"></div>
    <div style="display:flex;flex-direction:column;gap:12px">
      <button class="ov-btn ov-btn-ok" onclick="auftragMitnahmeBestaetigen(true)"
              style="font-size:14px;padding:14px 20px;text-align:left">
        ✓ Ware wird jetzt mitgenommen
        <div style="font-size:11px;color:#86efac;margin-top:3px;font-weight:400">Lager wird abgebucht, Auftrag abgeschlossen</div>
      </button>
      <button class="ov-btn ov-btn-sec" onclick="auftragMitnahmeBestaetigen(false)"
              style="font-size:14px;padding:14px 20px;text-align:left">
        💳 Nur Zahlung — Versand/Abholung folgt
        <div style="font-size:11px;color:#94a3b8;margin-top:3px;font-weight:400">Auftrag bleibt offen, Packplatz-Flow läuft weiter</div>
      </button>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:16px">
      <button class="ov-btn ov-btn-sec" style="width:auto;padding:0 20px;height:36px;font-size:13px"
              onclick="auftragMitnehmenAbbrechen()">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Bereits bezahlt: kein Bon nötig -->
<div id="ov-bezahlt-info" class="ov">
  <div class="ov-box" style="max-width:440px">
    <div class="ov-title">✅ Auftrag bereits bezahlt</div>
    <div style="padding:20px;text-align:center">
      <div id="bezahlt-info-text" style="font-size:15px;margin-bottom:12px;color:#374151"></div>
      <p style="font-size:13px;color:#6b7280;margin-bottom:24px">
        Kein Bon — Status wird auf <strong>Abgeschlossen</strong> gesetzt und eine Bestätigungsmail gesendet.
      </p>
      <div style="display:flex;gap:12px;justify-content:center">
        <button onclick="ovSchliessen('ov-bezahlt-info')" class="ov-btn ov-btn-sec">Abbrechen</button>
        <button onclick="abschliessenOhneBon()" class="ov-btn ov-btn-prim">✓ Abschließen</button>
      </div>
    </div>
  </div>
</div>

<!-- Retour-Bon: Barauszahlung bestätigen -->
<!-- Kunde nimmt von einem Auftrag weniger mit: Rest später abholen oder will er nicht? -->
<div id="ov-rest" class="ov">
  <div class="ov-box" style="max-width:560px">
    <div class="ov-title">Nicht alles mitgenommen</div>
    <div style="padding:16px 20px">
      <p style="font-size:13px;color:#6b7280;margin-bottom:12px">Was passiert mit dem Rest?</p>
      <div id="rest-liste"></div>
      <div id="rest-fehler" style="color:#dc2626;font-size:13px;margin-top:8px;display:none">Bitte für jede Zeile auswählen.</div>
      <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px">
        <button onclick="ovSchliessen('ov-rest')" class="ov-btn ov-btn-sec">Abbrechen</button>
        <button onclick="restFrageBestaetigen()" class="ov-btn ov-btn-prim">Weiter</button>
      </div>
    </div>
  </div>
</div>

<div id="ov-retour-bar" class="ov">
  <div class="ov-box" style="max-width:440px">
    <div class="ov-title">↩ Rückgabe — Barauszahlung</div>
    <div style="padding:20px;text-align:center">
      <div id="retour-betrag-anzeige" style="font-size:32px;font-weight:700;color:#dc2626;margin-bottom:8px"></div>
      <p id="retour-info-text" style="font-size:13px;color:#6b7280;margin-bottom:24px"></p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <button onclick="ovSchliessen('ov-retour-bar')" class="ov-btn ov-btn-sec">Abbrechen</button>
        <button onclick="retourBestaetigen()" class="ov-btn ov-btn-red">↩ Bar auszahlen</button>
        <button onclick="retourAlsGutschein()" class="ov-btn ov-btn-ok" id="btn-retour-gutschein"
                <?= $gutscheinArtikelId ? '' : 'disabled title="Kein Gutschein-Artikel angelegt (artikel.ist_gutschein) — bitte zuerst in den Artikelstammdaten anlegen."' ?>>
          🎁 Als Gutschein ausstellen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Ergebnis: erstellte Gutscheine (Retoure, Verkauf, Restguthaben nach Einlösung) -->
<div id="ov-gutschein-ausgabe-ergebnis" class="ov">
  <div class="ov-box" style="max-width:460px">
    <div class="ov-title" id="ga-titel">🎁 Gutschein erstellt</div>
    <div id="ga-liste" style="padding:12px 0"></div>
    <button onclick="gutscheinErgebnisSchliessen()" class="ov-btn ov-btn-sec">Weiter</button>
  </div>
</div>

<!-- Manager-Freigabe per PIN (Auszahlung ohne kasse.auszahlung-Recht) -->
<div id="ov-manager-pin" class="ov">
  <div class="ov-box" style="max-width:360px">
    <div class="ov-title">🔒 Manager-Freigabe nötig</div>
    <div style="padding:20px;text-align:center">
      <p id="manager-pin-info-text" style="font-size:13px;color:#6b7280;margin-bottom:16px"></p>
      <input type="password" id="manager-pin-input" inputmode="numeric" pattern="\d{4,6}" maxlength="6"
             placeholder="PIN" autocomplete="off"
             style="width:140px;text-align:center;font-size:22px;letter-spacing:6px;padding:10px;border:1px solid #d0d7e0;border-radius:6px;margin-bottom:8px">
      <p id="manager-pin-fehler" style="color:#dc2626;font-size:12px;min-height:16px;margin-bottom:12px"></p>
      <div style="display:flex;gap:12px;justify-content:center">
        <button onclick="managerPinAbbrechen()" class="ov-btn ov-btn-sec">Abbrechen</button>
        <button onclick="managerPinBestaetigen()" class="ov-btn ov-btn-prim">Freigeben</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Ausgabe-Auswahl nach Zahlung ──────────────────────────────────────── -->
<div class="ov" id="ov-ausgabe">
  <div class="ov-box" style="max-width:400px;text-align:center">
    <div style="font-size:28px;margin-bottom:8px">✓</div>
    <div class="ov-title" style="text-align:center;margin-bottom:6px">Bon gespeichert</div>
    <div id="ov-ausgabe-nr" style="font-size:13px;color:#64748b;margin-bottom:22px"></div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <button class="ov-btn" id="btn-ausgabe-80mm"
              style="background:#1e3a5f;color:#fff;font-size:15px;padding:14px"
              onclick="ausgabeOeffnen('80mm')">🖨 80mm Bon drucken</button>
      <button class="ov-btn" id="btn-ausgabe-a4"
              style="background:#2563eb;color:#fff;font-size:15px;padding:14px"
              onclick="ausgabeOeffnen('a4')">📄 A4 Rechnung öffnen</button>
      <button class="ov-btn ov-btn-sec" style="padding:12px"
              onclick="ovSchliessen('ov-ausgabe')">✕ Ohne Druck fertig</button>
    </div>
  </div>
</div>

<!-- Feedback Snackbar -->
<div id="feedback"></div>

<!-- ══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════════════════════════════════════════ -->
<script>
var KASSE_ID       = <?= $kasseId ?>;
var LAGER_ID       = <?= $lagerId ?>;
var AUSGABE_FORMAT = <?= json_encode($kasseInfo['ausgabe_format'] ?? 'fragen') ?>;
var KASSE_MODUS    = <?= json_encode($modus) ?>;
var GUTSCHEIN_ARTIKEL_ID = <?= json_encode($gutscheinArtikelId) ?>;

// ── Kundenanzeige-Sync ────────────────────────────────────────────────────────
// Schreibt den aktuellen Anzeige-Zustand für das Kundenanzeige-Tablet (falls eins
// angeschlossen ist). Fire-and-forget, kein Warten auf Antwort — darf den
// Kassiervorgang nie verzögern oder blockieren.
function kdSync(zustand, payload) {
    var body = new FormData();
    body.append('kasse_id', KASSE_ID);
    body.append('zustand', zustand);
    body.append('payload', JSON.stringify(payload || {}));
    fetch('<?= BASE_PATH ?>/kasse/ajax_kundenanzeige_sync.php', { method: 'POST', body: body }).catch(function() {});
}

// ── Zustand ──────────────────────────────────────────────────────────────────
var warenkorb          = [];
var aktiveZeile        = -1;
var globalRabatt       = 0;
var numpadBuf          = '';
var pendingArtikel        = null;
var nullbestandPendingArtikel = null;
var nullbestandPendingMenge   = 1;
var kundeId            = null;
var geladenerAuftragId       = null;
var geladenerAuftragNr       = null;
var geladenerAuftragStatus   = null;
var geladenerAuftragMitnehmen     = null;
var geladenerAuftragZahlungsstatus = null;
var aktuellerZahlBetrag            = null;
var zusatzPositionen               = [];
var retourePositionen               = []; // {artikel_id, bezeichnung, ean, einzelpreis_brutto, steuer_prozent, rabatt_prozent, maxMenge, retourMenge, charge}
// Sammelabholung: alle geladenen Web-Aufträge (ein Kunde), siehe _hauptAuftragSpiegeln()
var geladeneAuftraege              = []; // {id, nr, status, mitnehmen, zahlungsstatus, kunden_id, kunden_email, kunden_name}
var mitnehmenWarteschlange         = []; // Auftrags-IDs, für die "mitnehmen oder nur zahlen?" noch offen ist
var weitereAuftraegePruefenFuer    = null;

// ── Schnellwahl befüllen (PHP → JS) ─────────────────────────────────────────
(function() {
    var sw = <?= json_encode($schnellwahl) ?>;
    var grid = document.getElementById('sw-grid');
    for (var slot = 1; slot <= 9; slot++) {
        var btn = document.createElement('button');
        if (sw[slot] && sw[slot].artikel_id) {
            var d = sw[slot];
            btn.className = 'sw-btn';
            btn.textContent = d.anzeige_name || d.artikel_name || 'Slot ' + slot;
            btn.dataset.artikelId = d.artikel_id;
            btn.dataset.bezeichnung = d.anzeige_name;
            btn.dataset.ean = d.ean || '';
            btn.dataset.preis = d.brutto_vk || '0';
            btn.dataset.steuer = d.steuer_prozent || '20';
            btn.onclick = function() { swArtikelLaden(this); };
        } else {
            btn.className = 'sw-btn leer';
            btn.textContent = '—';
        }
        grid.appendChild(btn);
    }
})();

// ── Schnellwahl: Artikel direkt hinzufügen ───────────────────────────────────
function swArtikelLaden(btn) {
    var artikelId = btn.dataset.artikelId;
    if (!artikelId) return;
    fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?code=' + encodeURIComponent(btn.dataset.ean || btn.dataset.bezeichnung)
        + '&lager_id=' + LAGER_ID)
        .then(r => r.json())
        .then(d => {
            if (d.erfolg && d.typ !== 'vater') {
                artikelHinzufuegen(d);
            } else if (!d.erfolg) {
                // Fallback: Artikel mit gespeicherten Daten
                artikelHinzufuegen({
                    id: parseInt(artikelId),
                    bezeichnung: btn.dataset.bezeichnung,
                    ean: btn.dataset.ean || null,
                    brutto_vk: parseFloat(btn.dataset.preis) || 0,
                    steuer_prozent: parseFloat(btn.dataset.steuer) || 20,
                    bestand_physisch: 0, bestand_reserviert: 0, bestand_verkaufbar: 0,
                    ueberverkauf_erlaubt: true, typ: 'artikel'
                });
            }
        });
}

// ── Scan-Input ────────────────────────────────────────────────────────────────
var scanInput = document.getElementById('scan-input');
scanInput.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') scannenOK();
});
scanInput.addEventListener('focus', function() {
    aktiveZeile = -1;
    renderBon();
});

function scannenOK() {
    var raw = scanInput.value.trim();
    if (!raw) return;
    scanInput.value = '';

    // Gutschein-Barcode (MEA-XXXX-XXXX-XXXX) im Artikel-Scanfeld: volle Kasse -> direkt
    // mit Gutschein bezahlen, leere Kasse -> Gutschein abfragen. "ß" statt "-" kommt von
    // Scannern mit US-Tastaturbelegung an einem deutschen PC (Server korrigiert auch Y/Z).
    var gsScan = raw.replace(/ß/g, '-').toUpperCase();
    if (/^MEA-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/.test(gsScan)) {
        if (warenkorb.length > 0 && getGesamt() > 0) {
            zahlenGutschein();
            document.getElementById('gs-code').value = gsScan;
            gsCodeGeaendert();
            gsPruefen();
        } else {
            gutscheinAbfrageDialog();
            document.getElementById('gsa-code').value = gsScan;
            gutscheinAbfragen();
        }
        return;
    }

    // Menge-Präfix: z.B. "5×4002309302009" oder "5*4002309302009"
    var mengePrefix = raw.match(/^(\d+)[×*xX](.+)$/);
    var menge, code;
    if (mengePrefix) {
        menge = parseInt(mengePrefix[1]) || 1;
        code  = mengePrefix[2].trim();
        numpadBuf = String(menge);
        aktualisiereMenuge();
    } else {
        menge = getMenge();
        code  = raw;
    }

    fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?code=' + encodeURIComponent(code) + '&lager_id=' + LAGER_ID)
        .then(r => r.json())
        .then(function(d) {
            if (!d.erfolg) {
                // Fallback: Textsuche
                if (code.length >= 2) {
                    ov('ov-suche');
                    document.getElementById('suche-input').value = code;
                    sucheLive(code);
                } else {
                    feedback('Artikel nicht gefunden: ' + code, 'fehler');
                }
                return;
            }
            if (d.typ === 'vater') {
                zeigeVaterAuswahl(d);
            } else if (d.typ === 'konfigurator') {
                zeigeKonfiguratorAuswahl(d);
            } else {
                artikelHinzufuegen(d);
            }
        })
        .catch(() => feedback('Verbindungsfehler', 'fehler'));
}

// ── Artikel hinzufügen ────────────────────────────────────────────────────────
function artikelHinzufuegen(a) {
    // Gutschein-Artikel hat keinen festen Preis -- Betrag wird im eigenen Dialog erfasst
    if (GUTSCHEIN_ARTIKEL_ID && a.id == GUTSCHEIN_ARTIKEL_ID) {
        gutscheinVerkaufDialog();
        return;
    }
    var menge = getMenge();
    var preis = parseFloat(a.brutto_vk) || 0;
    if (preis <= 0 && !a.istDivers) {
        feedback('⚠ Kein Preis für: ' + a.bezeichnung, 'fehler');
        return;
    }

    // Bestand=0 Warnung (gilt für alle Artikel ausser Divers)
    if ((parseFloat(a.bestand_physisch) || 0) <= 0 && !a.istDivers) {
        nullbestandPendingArtikel = a;
        nullbestandPendingMenge   = menge;
        document.getElementById('nullbest-name').textContent  = a.bezeichnung;
        document.getElementById('nullbest-artnr').textContent = a.artikelnummer || '';
        ov('ov-nullbestand');
        return;
    }

    // Charge-Auswahl wenn Artikel Chargen hat oder charge_pflicht=1
    if (a.charge_pflicht || a.hat_chargen) {
        zeigeKasseChargePopup(a, menge);
        return;
    }

    // Reservierungs-Prüfung
    var physisch   = parseFloat(a.bestand_physisch)   || 0;
    var reserviert = parseFloat(a.bestand_reserviert) || 0;
    var verkaufbar = parseFloat(a.bestand_verkaufbar !== undefined ? a.bestand_verkaufbar : (physisch - reserviert));
    if (!a.ueberverkauf_erlaubt && menge > verkaufbar && reserviert > 0) {
        pendingArtikel = { a: a, menge: menge };
        document.getElementById('reswarn-text').innerHTML =
            '<strong>' + esc(a.bezeichnung) + '</strong><br>' +
            'Physisch: ' + physisch + ' · Reserviert: ' + reserviert + ' · Verkaufbar: ' + Math.max(0,verkaufbar) + '<br>' +
            'Angefordert: ' + menge;
        ov('ov-reswarn');
        return;
    }

    _artikelEinfuegen(a, menge);
}

function reswarnBestaetigen() {
    ovSchliessen('ov-reswarn');
    if (pendingArtikel) {
        _artikelEinfuegen(pendingArtikel.a, pendingArtikel.menge);
        pendingArtikel = null;
    }
}

function nullbestandBestaetigen() {
    ovSchliessen('ov-nullbestand');
    var a = nullbestandPendingArtikel;
    var m = nullbestandPendingMenge;
    nullbestandPendingArtikel = null;
    if (!a) return;
    if (a.hat_chargen || a.charge_pflicht) {
        // Synthetische "neue Charge"-Zeile — kein bestehender Lagerbestand-Eintrag
        var aMitNeu = Object.assign({}, a, {
            alle_chargen: [{ id: null, charge: null, bestand: 0, charge_status: 'nachzutragen' }],
            fifo_charge: null
        });
        zeigeKasseChargePopup(aMitNeu, m);
    } else {
        _artikelEinfuegen(a, m);
    }
}

function _artikelEinfuegen(a, menge) {
    var preis        = parseFloat(a.brutto_vk) || 0;
    var chargeNeu    = a._gewaehltCharge !== undefined ? a._gewaehltCharge : (a.fifo_charge || null);
    var konfigIds    = a._konfig_wert_ids || null;
    var konfigJson   = JSON.stringify((konfigIds || []).slice().sort());
    // Gleiche Charge UND gleiche Konfigurator-Auswahl: zusammenführen; sonst neue Zeile
    // (zwei unterschiedlich konfigurierte Schilder dürfen nie zu einer Menge verschmelzen)
    var idx = warenkorb.findIndex(p =>
        p.artikel_id == a.id && !p.istDivers && !p.vonAuftrag
        && (p.charge || null) === (chargeNeu || null)
        && JSON.stringify((p.konfig_wert_ids || []).slice().sort()) === konfigJson
    );
    if (idx >= 0 && a.id) {
        warenkorb[idx].menge += menge;
    } else {
        warenkorb.push({
            artikel_id:                  a.id || null,
            bezeichnung:                 a.bezeichnung,
            ean:                         a.ean || null,
            artnr:                       a.artikelnummer || null,
            menge:                       menge,
            einzelpreis_brutto:          preis,
            steuer_prozent:              parseFloat(a.steuer_prozent) || 20,
            rabatt_prozent:              0,
            charge:                      chargeNeu,
            konfig_wert_ids:             konfigIds,
            nachzutragen_lagerbestand_id: a._nachtragen_lagerbestand_id || null,
            istDivers:                   !!a.istDivers,
            hat_chargen:                 !!a.hat_chargen,
            charge_pflicht:              !!a.charge_pflicht,
            bestand_physisch:            parseFloat(a.bestand_physisch)   || 0,
            bestand_reserviert:          parseFloat(a.bestand_reserviert) || 0,
            bestand_verkaufbar:          parseFloat(a.bestand_verkaufbar !== undefined ? a.bestand_verkaufbar : 0)
        });
        idx = warenkorb.length - 1;
    }
    aktiveZeile = idx;
    zeigeArtikelInfo(a);
    renderBon();
    clearNumpad();
    feedback('✓ ' + a.bezeichnung + (menge > 1 ? ' (' + menge + '×)' : ''), 'ok');
}

// ── Artikel-Info Box ──────────────────────────────────────────────────────────
function zeigeArtikelInfo(a) {
    document.getElementById('ai-leer').style.display  = 'none';
    document.getElementById('ai-inhalt').style.display = 'block';
    document.getElementById('ai-name').textContent = a.bezeichnung;
    document.getElementById('ai-meta').textContent =
        'Art-Nr: ' + (a.artikelnummer || '—') + (a.ean ? '  ·  EAN: ' + a.ean : '');
    document.getElementById('ai-preis').textContent = '€ ' + fmt(parseFloat(a.brutto_vk) || 0) + ' / Stk';
    document.getElementById('ai-akt').style.display = 'none';

    var physisch   = parseFloat(a.bestand_physisch)   || 0;
    var reserviert = parseFloat(a.bestand_reserviert) || 0;
    var verkaufbar = parseFloat(a.bestand_verkaufbar !== undefined ? a.bestand_verkaufbar : Math.max(0, physisch - reserviert));
    document.getElementById('ai-lager').innerHTML =
        lagerpunkt('#22c55e', 'Physisch: ' + physisch) +
        lagerpunkt('#f59e0b', 'Reserviert: ' + reserviert) +
        lagerpunkt('#2563eb', 'Verkaufbar: ' + Math.max(0, verkaufbar));
}

function lagerpunkt(farbe, text) {
    return '<div class="pos-lag-item"><div class="pos-lag-dot" style="background:' + farbe + '"></div>' + esc(text) + '</div>';
}

// ── Vater-Auswahl ─────────────────────────────────────────────────────────────
function zeigeVaterAuswahl(vater) {
    document.getElementById('vater-titel').textContent = vater.bezeichnung + ' — Variante wählen';
    var html = '';
    (vater.kinder || []).forEach(function(k) {
        var bestand = parseInt(k.lagerbestand) || 0;
        html += '<div class="kind-chip" onclick=\'kindGewaehlt(' + JSON.stringify(k) + ')\'>'
            + esc(k.bezeichnung)
            + '<span class="kind-chip-sub">€ ' + fmt(parseFloat(k.brutto_vk))
            + ' · <span style="color:' + (bestand > 0 ? '#16a34a' : '#dc2626') + '">Bestand: ' + bestand + '</span></span>'
            + '</div>';
    });
    document.getElementById('vater-kinder').innerHTML = html || '<p style="color:#64748b">Keine Varianten.</p>';
    ov('ov-vater');
}
function kindGewaehlt(kind) {
    ovSchliessen('ov-vater');
    kind.bestand_physisch = kind.lagerbestand || 0;
    kind.bestand_reserviert = 0;
    kind.bestand_verkaufbar = kind.lagerbestand || 0;
    artikelVollstaendigHinzufuegen(kind);
}

/**
 * Varianten-Liste und Textsuche liefern nur Kurzdaten OHNE Chargen-Liste
 * (alle_chargen/hat_chargen/fifo_charge) -- das Chargen-Popup war dadurch bei
 * Charge-Pflicht immer leer ("bitte Charge im Wareneingang eintragen"), obwohl
 * Bestand da war. Deshalb vor dem Hinzufügen die vollständigen Daten wie beim
 * Scannen nachladen; bei Fehler mit den Kurzdaten weitermachen.
 */
function artikelVollstaendigHinzufuegen(kurz) {
    var code = kurz.artikelnummer || kurz.ean;
    if (!code) { artikelHinzufuegen(kurz); return; }
    fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?code=' + encodeURIComponent(code) + '&lager_id=' + LAGER_ID)
        .then(r => r.json())
        .then(function(d) {
            if (d.erfolg && d.typ === 'artikel' && d.id == kurz.id) {
                artikelHinzufuegen(d);
            } else {
                artikelHinzufuegen(kurz);
            }
        })
        .catch(function() { artikelHinzufuegen(kurz); });
}

// ── Konfigurator-Auswahl ──────────────────────────────────────────────────────
// Preis kommt IMMER vom Server (ajax_konfigurator.php -> KonfiguratorService::berechnePreis()) --
// kein Doppelrechnen im JS, sonst könnten Client und Server auseinanderlaufen.
var konfigAktuellerArtikel = null;  // { id, bezeichnung, artikelnummer, ean, konfiguration: [...] }
var konfigAuswahl          = {};    // achse_id -> wert_id
var konfigLetzteBerechnung = null;  // letzte erfolgreiche Antwort von ajax_konfigurator.php

function zeigeKonfiguratorAuswahl(a) {
    konfigAktuellerArtikel = a;
    konfigAuswahl = {};
    konfigLetzteBerechnung = null;
    document.getElementById('konfig-titel').textContent = a.bezeichnung + ' — Optionen wählen';
    document.getElementById('konfig-fehler').textContent = '';
    document.getElementById('konfig-preis').textContent = '€ 0,00';
    document.getElementById('btn-konfig-ok').disabled = true;
    konfigRenderAchsen();
    ov('ov-konfigurator');
}

function konfigRenderAchsen() {
    var html = '';
    (konfigAktuellerArtikel.konfiguration || []).forEach(function(achse) {
        // Bedingte Anzeige: Achse nur zeigen, wenn ihre Bedingungs-Achse den passenden Wert hat
        if (achse.bedingung && konfigAuswahl[achse.bedingung.achse_id] !== achse.bedingung.wert_id) return;
        html += '<div class="ov-label" style="margin-top:10px">' + esc(achse.name) + '</div><div>';
        achse.werte.forEach(function(w) {
            var gewaehlt = konfigAuswahl[achse.achse_id] === w.wert_id;
            html += '<div class="kind-chip konfig-wert-chip' + (gewaehlt ? ' gewaehlt' : '') + '" '
                  + 'onclick="konfigWertGewaehlt(' + achse.achse_id + ',' + w.wert_id + ')">'
                  + esc(w.wert)
                  + (w.wert_aufpreis > 0 ? '<span class="kind-chip-sub">+€ ' + fmt(w.wert_aufpreis) + '</span>' : '')
                  + '</div>';
        });
        html += '</div>';
    });
    document.getElementById('konfig-achsen').innerHTML = html;
}

function konfigWertGewaehlt(achseId, wertId) {
    konfigAuswahl[achseId] = wertId;
    // Achsen, deren Bedingung durch diese Auswahl nicht mehr erfüllt ist, verlieren ihre eigene Auswahl
    (konfigAktuellerArtikel.konfiguration || []).forEach(function(achse) {
        if (achse.bedingung && konfigAuswahl[achse.bedingung.achse_id] !== achse.bedingung.wert_id) {
            delete konfigAuswahl[achse.achse_id];
        }
    });
    konfigRenderAchsen();
    konfigPreisAktualisieren();
}

function konfigPreisAktualisieren() {
    var wertIds = Object.keys(konfigAuswahl).map(function (k) { return konfigAuswahl[k]; });
    var vollstaendig = (konfigAktuellerArtikel.konfiguration || []).every(function(achse) {
        if (achse.bedingung && konfigAuswahl[achse.bedingung.achse_id] !== achse.bedingung.wert_id) return true;
        return konfigAuswahl[achse.achse_id] !== undefined;
    });

    fetch('<?= BASE_PATH ?>/kasse/ajax_konfigurator.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ artikel_id: konfigAktuellerArtikel.id, wert_ids: wertIds })
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        var fehlEl = document.getElementById('konfig-fehler');
        var btn    = document.getElementById('btn-konfig-ok');
        if (d.erfolg) {
            document.getElementById('konfig-preis').textContent = '€ ' + fmt(d.brutto);
            fehlEl.textContent = vollstaendig ? '' : 'Bitte alle Optionen wählen';
            btn.disabled = !vollstaendig;
            konfigLetzteBerechnung = d;
        } else {
            document.getElementById('konfig-preis').textContent = '€ 0,00';
            fehlEl.textContent = (d.fehler || []).join(', ');
            btn.disabled = true;
            konfigLetzteBerechnung = null;
        }
    })
    .catch(function () {
        document.getElementById('konfig-fehler').textContent = 'Serverfehler';
        document.getElementById('btn-konfig-ok').disabled = true;
        konfigLetzteBerechnung = null;
    });
}

function konfigBestaetigen() {
    if (!konfigLetzteBerechnung || !konfigLetzteBerechnung.erfolg) return;
    var a = konfigAktuellerArtikel;
    var d = konfigLetzteBerechnung;
    ovSchliessen('ov-konfigurator');
    artikelHinzufuegen({
        id:                  a.id,
        bezeichnung:         a.bezeichnung + ' (' + d.beschreibung + ')',
        artikelnummer:       a.artikelnummer,
        ean:                 a.ean,
        brutto_vk:           d.brutto,
        steuer_prozent:      d.steuer_prozent,
        ueberverkauf_erlaubt: true,
        bestand_physisch:    999999,
        bestand_reserviert:  0,
        bestand_verkaufbar:  999999,
        istDivers:           false,
        _konfig_wert_ids:    Object.keys(konfigAuswahl).map(function (k) { return konfigAuswahl[k]; })
    });
}

// ── Bon rendern ───────────────────────────────────────────────────────────────
function renderBon(skipKdSync) {
    var liste = document.getElementById('bon-liste');
    var leer  = document.getElementById('bon-leer');

    if (warenkorb.length === 0) {
        leer.style.display = 'flex';
        // Entferne alle Zeilen außer dem Leer-Div
        Array.from(liste.querySelectorAll('.bon-row')).forEach(r => r.remove());
        // Bezahlen bleibt möglich, wenn eine reine Retoure (ohne normalen Warenkorb-Inhalt) aktiv ist
        document.getElementById('btn-bezahlen').disabled = !retoureAktiv();
        aktualisiereFooter();
        if (!skipKdSync) kdSyncWarenkorb(); // reine Rückgabe → Anzeige, sonst idle
        return;
    }
    leer.style.display = 'none';

    // Vorhandene Zeilen und Separator entfernen und neu aufbauen
    Array.from(liste.querySelectorAll('.bon-row, .bon-row-separator, .bon-row-auftrag-header')).forEach(r => r.remove());

    // Reihenfolge: je geladenem Auftrag seine Zeilen unter einer Überschrift (Sammelabholung:
    // mehrere), danach alle weiteren Artikel. Die Indizes bleiben die des warenkorb-Arrays.
    var gruppen = [];
    geladeneAuftraege.forEach(function(a) {
        var idx = [];
        warenkorb.forEach(function(p, i) { if (zeileAuftragId(p) === a.id) idx.push(i); });
        if (idx.length) gruppen.push({ auftrag: a, idx: idx });
    });
    var restIdx = [];
    warenkorb.forEach(function(p, i) { if (!auftragInfo(zeileAuftragId(p))) restIdx.push(i); });
    var mehrereAuftraege = geladeneAuftraege.length > 1;
    var lfdNr = 0;

    function zeileRendern(i) {
        var p = warenkorb[i];
            var rabFaktor = 1 - (posRabatt(p) / 100);
            var summe = p.menge * p.einzelpreis_brutto * rabFaktor;
            var istAktiv = (i === aktiveZeile);

            var istRetour = (p.block === 'retour' && !p.vonAuftrag);

            var div = document.createElement('div');
            div.className = 'bon-row' + (istAktiv ? ' aktiv' : '') + (p.vonAuftrag ? ' bon-row-auftrag' : '') + (istRetour ? ' bon-row-retour' : '');
            div.dataset.idx = i;
            div.onclick = function() { zeilaKlick(i); };

            var rabHtml = posRabatt(p) > 0
                ? '<span class="bon-row-rabatt">-' + posRabatt(p) + '%</span>'
                : '';
            var auftragBadge = p.vonAuftrag ? '<span class="bon-row-auftrag-badge">📦</span>' : '';
            var retourBadge  = istRetour ? '<span class="bon-row-retour-badge">↩</span>' : '';
            // Teilabholung sichtbar machen: weniger mitgenommen als bestellt/gepackt
            var orig = p.original_menge !== undefined ? p.original_menge : p.menge;
            var teilHtml = (p.vonAuftrag && p.menge < orig)
                ? '<span class="bon-row-teil">' + p.menge + ' von ' + orig + ' mitgenommen</span>'
                : '';

            div.innerHTML =
                '<div class="bon-row-nr">' + (++lfdNr) + '</div>' +
                '<div class="bon-row-name">' + auftragBadge + retourBadge + esc(p.bezeichnung) + rabHtml + teilHtml + '</div>' +
                '<div class="bon-row-menge">' + p.menge + '</div>' +
                '<div class="bon-row-ep">€ ' + fmt(p.einzelpreis_brutto) + '</div>' +
                '<div class="bon-row-summe">€ ' + fmt(summe) + '</div>' +
                (istAktiv ?
                    '<div class="bon-row-ctrl">' +
                    '  <button class="bon-ctrl" onclick="event.stopPropagation();zeileMinus(' + i + ')">−</button>' +
                    '  <button class="bon-ctrl" onclick="event.stopPropagation();zeilePlus(' + i + ')">+</button>' +
                    '  <button class="bon-ctrl bon-ctrl-preis" onclick="event.stopPropagation();preisOverride(' + i + ')" title="Preis überschreiben (Zahl auf Numpad, dann hier drücken)">€ Preis</button>' +
                    '  <span class="bon-ctrl-hint">STORNO-Taste zum Entfernen</span>' +
                    '</div>'
                    : ''
                );

            liste.appendChild(div);
    }

    gruppen.forEach(function(g) {
        var a = g.auftrag;
        var nichtsMit = auftragNichtsMitgenommen(a.id);
        var hdr = document.createElement('div');
        hdr.className = 'bon-row-auftrag-header';
        hdr.innerHTML = '📦 <strong>' + esc(a.nr) + '</strong> '
            + (a.zahlungsstatus === 'bezahlt'
                ? '<span class="a-chip a-chip-bezahlt">bezahlt</span>'
                : '<span class="a-chip a-chip-offen">unbezahlt</span>')
            + (a.mitnehmen === false ? ' <span class="a-chip a-chip-versandbereit">nur Zahlung</span>' : '')
            + (mehrereAuftraege
                ? '<button class="bon-hdr-x" title="Auftrag wieder vom Bon nehmen" onclick="event.stopPropagation();auftragEntfernen(' + a.id + ')">✕</button>'
                : '')
            + (nichtsMit
                ? '<div class="bon-hdr-hinweis">nichts mitgenommen — Auftrag bleibt unverändert liegen</div>'
                : '');
        liste.appendChild(hdr);
        g.idx.forEach(zeileRendern);
    });
    // Trennlinie zwischen Auftrag-Blöcken und normalen Artikeln
    if (gruppen.length && restIdx.length) {
        var sep = document.createElement('div');
        sep.className = 'bon-row-separator';
        sep.textContent = '─── weitere Artikel ───';
        liste.appendChild(sep);
    }
    restIdx.forEach(zeileRendern);

    document.getElementById('btn-bezahlen').disabled = false;
    aktualisiereFooter();
    if (!skipKdSync) kdSyncWarenkorb();
}

// ── Kundenanzeige-Sync: Warenkorb-Stand als Payload aufbereiten ──────────────
function kdSyncWarenkorb() {
    if (warenkorb.length === 0 && !retoureAktiv()) { kdSync('idle', {}); return; }
    var aktiv = (aktiveZeile >= 0 ? warenkorb[aktiveZeile] : warenkorb[warenkorb.length - 1]) || null;
    var positionen = warenkorb.map(function(p) {
        var rab = 1 - posRabatt(p) / 100;
        return {
            bezeichnung: p.bezeichnung,
            menge:       p.menge,
            summe:       p.menge * p.einzelpreis_brutto * rab,
            vonAuftrag:  !!p.vonAuftrag,
            bezahlt:     !!(p.vonAuftrag && auftragBezahlt(zeileAuftragId(p)))
        };
    });
    // Rückgabe-Zeilen (bereits ausgelieferter Auftrag) mit negativem Betrag dazu
    retourePositionen.forEach(function(p) {
        if (p.retourMenge <= 0) return;
        positionen.push({
            bezeichnung: p.bezeichnung,
            menge:       p.retourMenge,
            summe:       -p.retourMenge * p.einzelpreis_brutto * (1 - (p.rabatt_prozent || 0) / 100),
            vonAuftrag:  false,
            retour:      true
        });
    });
    // Wie die Summe unten an der Kasse: bereits bezahlte Aufträge werden nicht kassiert,
    // Rückgaben werden abgezogen
    var gesamt = getGesamt(), bereitsBezahlt = 0;
    if (geladeneAuftraege.some(function(a) { return a.zahlungsstatus === 'bezahlt'; }) || retoureAktiv()) {
        bereitsBezahlt = positionen.reduce(function(s, z) { return s + (z.bezahlt ? z.summe : 0); }, 0);
        gesamt = berechneAbrechnungsModus().netBrutto;
    }
    kdSync('warenkorb', {
        artikel_id:          aktiv ? (aktiv.artikel_id || null) : null,
        artikel_name:        aktiv ? aktiv.bezeichnung : 'Rückgabe',
        artikel_variante:    null,
        artikel_einzelpreis: aktiv ? aktiv.einzelpreis_brutto : null,
        positionen:          positionen,
        gesamt:              Math.round(gesamt * 100) / 100,
        bereits_bezahlt:     Math.round(bereitsBezahlt * 100) / 100,
        auftrag_nr:          auftragNummernText() || null
    });
}

// ── Retoure-Sektion (versendet/teilgeliefert-Auftrag geladen) ────────────────
function retoureAktiv() {
    return retourePositionen.some(function(p) { return p.retourMenge > 0; });
}

function renderRetoureSektion() {
    var sektion = document.getElementById('retoure-sektion');
    if (retourePositionen.length === 0) {
        sektion.style.display = 'none';
        return;
    }
    sektion.style.display = 'block';
    document.getElementById('retoure-auftrag-nr').textContent = geladenerAuftragNr || '';

    var liste = document.getElementById('retoure-liste');
    liste.innerHTML = '';
    var summe = 0;
    retourePositionen.forEach(function(p, i) {
        summe += p.retourMenge * p.einzelpreis_brutto * (1 - p.rabatt_prozent / 100);
        var row = document.createElement('div');
        row.className = 'pos-retoure-zeile';
        var chargeHtml = p.charge ? '<span class="pos-retoure-charge">Charge ' + esc(p.charge) + '</span>' : '';
        row.innerHTML =
            '<div class="pos-retoure-name">' + esc(p.bezeichnung) + '</div>' +
            chargeHtml +
            '<div class="pos-retoure-stepper">' +
            '  <button' + (p.retourMenge <= 0 ? ' disabled' : '') + ' onclick="retoureMinus(' + i + ')">−</button>' +
            '  <span class="pos-retoure-menge">' + p.retourMenge + '</span>' +
            '  <button' + (p.retourMenge >= p.maxMenge ? ' disabled' : '') + ' onclick="retourePlus(' + i + ')">+</button>' +
            '</div>';
        liste.appendChild(row);
    });
    document.getElementById('retoure-summe').textContent =
        summe > 0.005 ? 'Rückgabe: € ' + fmt(summe) : '';

    // Bezahlen-Button freischalten, auch wenn der normale Warenkorb (noch) leer ist
    if (warenkorb.length === 0) {
        document.getElementById('btn-bezahlen').disabled = !retoureAktiv();
    }
}

function retoureMinus(i) {
    if (retourePositionen[i].retourMenge > 0) {
        retourePositionen[i].retourMenge--;
        renderRetoureSektion();
        kdSyncWarenkorb();
    }
}
function retourePlus(i) {
    var p = retourePositionen[i];
    if (p.retourMenge < p.maxMenge) {
        p.retourMenge++;
        renderRetoureSektion();
        kdSyncWarenkorb();
    }
}

function zeilaKlick(i) {
    aktiveZeile = (aktiveZeile === i) ? -1 : i;
    renderBon();
    if (aktiveZeile >= 0) {
        var p = warenkorb[aktiveZeile];
        zeigeArtikelInfo({
            bezeichnung: p.bezeichnung, artikelnummer: '', ean: p.ean,
            brutto_vk: p.einzelpreis_brutto, steuer_prozent: p.steuer_prozent,
            bestand_physisch: p.bestand_physisch, bestand_reserviert: p.bestand_reserviert,
            bestand_verkaufbar: p.bestand_verkaufbar
        });
    }
}

function zeileMinus(i) {
    var pRetour = warenkorb[i];
    if (pRetour.block === 'retour' && !pRetour.vonAuftrag) {
        // Freitext-Retour: Menge ist negativ — '−' verringert die zurückgenommene
        // Menge (Richtung 0), bei 1 wird die Zeile komplett entfernt.
        if (pRetour.menge < -1) { pRetour.menge++; renderBon(); }
        else { zeileEntfernen(i); }
        return;
    }
    if (warenkorb[i].menge > 1) {
        warenkorb[i].menge--;
        renderBon();
    } else if (warenkorb[i].vonAuftrag) {
        // Vollständige Rückgabe dieser Position — Zeile bleibt sichtbar (menge=0),
        // sonst sieht die Retour-Berechnung (original_menge - menge) sie nicht mehr.
        warenkorb[i].menge = 0;
        renderBon();
    } else {
        zeileEntfernen(i);
    }
}
function zeilePlus(i) {
    var p = warenkorb[i];
    if (p.block === 'retour' && !p.vonAuftrag) {
        // Freitext-Retour: '+' erhöht die zurückgenommene Menge (weiter von 0 weg).
        p.menge--;
        renderBon();
        return;
    }
    if (p.vonAuftrag) {
        // Rückgängig machen einer (Teil-)Rückgabe — nie über die ursprüngliche Menge hinaus,
        // Mehrmenge gehört als eigener Scan (Extra-Position), nicht als erhöhte Auftrags-Menge.
        var orig = p.original_menge !== undefined ? p.original_menge : p.menge;
        if (p.menge < orig) { p.menge++; renderBon(); }
        return;
    }
    if ((p.hat_chargen || p.charge_pflicht) && p.artikel_id) {
        var code = p.ean || p.artnr || String(p.artikel_id);
        fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?code=' + encodeURIComponent(code) + '&lager_id=' + LAGER_ID)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.erfolg) {
                    if ((parseFloat(d.bestand_physisch) || 0) <= 0) {
                        nullbestandPendingArtikel = d;
                        nullbestandPendingMenge   = 1;
                        document.getElementById('nullbest-name').textContent  = d.bezeichnung;
                        document.getElementById('nullbest-artnr').textContent = d.artikelnummer || '';
                        ov('ov-nullbestand');
                    } else {
                        zeigeKasseChargePopup(d, 1);
                    }
                } else {
                    p.menge++;
                    renderBon();
                }
            })
            .catch(function() { p.menge++; renderBon(); });
        return;
    }
    p.menge++;
    renderBon();
}
function zeileEntfernen(i) {
    warenkorb.splice(i, 1);
    aktiveZeile = -1;
    renderBon();
}

// ── Footer & Gesamt ───────────────────────────────────────────────────────────
function aktualisiereFooter() {
    var gesamt = 0, st20 = 0, st10 = 0, anzahl = 0;
    warenkorb.forEach(function(p) {
        var rab = 1 - posRabatt(p) / 100;
        var pos = p.menge * p.einzelpreis_brutto * rab;
        gesamt += pos;
        anzahl += p.menge;
        var netto = pos / (1 + p.steuer_prozent / 100);
        if (p.steuer_prozent == 20) st20 += netto * 0.2;
        else if (p.steuer_prozent == 10) st10 += netto * 0.1;
    });

    document.getElementById('footer-cnt').textContent = anzahl + ' Artikel' + (globalRabatt > 0 ? ' · ' + globalRabatt + '% Rabatt' : '');
    var stInfo = [];
    if (st20 > 0) stInfo.push('USt 20%: € ' + fmt(st20));
    if (st10 > 0) stInfo.push('USt 10%: € ' + fmt(st10));
    document.getElementById('footer-tax').textContent = stInfo.length ? stInfo.join('  ') : 'inkl. MwSt.';

    document.getElementById('rf-cnt').textContent  = anzahl + ' Artikel' + (globalRabatt > 0 ? ' · ' + globalRabatt + '% Rabatt' : '') + ' · inkl. MwSt.';
    document.getElementById('rf-ges').textContent  = '€ ' + fmt(gesamt);
    // Bereits bezahlte Aufträge werden nicht kassiert -- Anzeige = tatsächlicher Zahlbetrag
    if (geladeneAuftraege.some(function(a) { return a.zahlungsstatus === 'bezahlt'; })) {
        var m = berechneAbrechnungsModus();
        document.getElementById('rf-cnt').textContent += ' · bezahlte Aufträge nicht enthalten';
        document.getElementById('rf-ges').textContent  = '€ ' + fmt(m.netBrutto);
    }
}

// Effektiver Rabatt einer Position (Zeilen- oder Bon-Rabatt, der höhere zählt).
// Gutscheine sind Zahlungsmittel, nie rabattierbar -- Server erzwingt das zusätzlich.
function posRabatt(p) {
    if (p.block === 'gutschein_kauf' || p.block === 'zahlung') return 0;
    return Math.max(p.rabatt_prozent, globalRabatt);
}

function getGesamt() {
    var g = 0;
    warenkorb.forEach(function(p) {
        var rab = 1 - posRabatt(p) / 100;
        g += p.menge * p.einzelpreis_brutto * rab;
    });
    return Math.round(g * 100) / 100;
}

// ── Numpad ────────────────────────────────────────────────────────────────────
function npDruck(z) {
    if (z === ',' && numpadBuf.includes(',')) return;
    if (numpadBuf.length >= 6) return;
    numpadBuf += z;
    aktualisiereMenuge();
}
function npBack() {
    numpadBuf = numpadBuf.slice(0, -1);
    aktualisiereMenuge();
}
function clearNumpad() {
    numpadBuf = '';
    aktualisiereMenuge();
}
function aktualisiereMenuge() {
    var m = getMenge();
    document.getElementById('menge-display').textContent = m + ' ×';
}
function getMenge() {
    var v = numpadBuf.replace(',', '.');
    return parseInt(v) || 1;
}
function mengeReset() {
    clearNumpad();
}

function npMal() {
    // Bestätigt die Menge, fokussiert Scan für nächsten Scan
    aktualisiereMenuge();
    scanInput.focus();
    aktiveZeile = -1;
    renderBon();
}

function npRabatt() {
    var pct = parseFloat(numpadBuf.replace(',', '.')) || 0;
    if (pct <= 0 || pct > 100) {
        feedback('Bitte zuerst Rabatt % auf Numpad eingeben', 'info');
        return;
    }
    if (aktiveZeile >= 0) {
        warenkorb[aktiveZeile].rabatt_prozent = pct;
        feedback('Positionsrabatt ' + pct + '% gesetzt', 'ok');
    } else {
        globalRabatt = pct;
        feedback('Bon-Rabatt ' + pct + '% gesetzt', 'ok');
    }
    clearNumpad();
    aktiveZeile = -1;
    renderBon();
}

function npStorno() {
    if (aktiveZeile < 0) {
        feedback('Bitte zuerst eine Zeile auswählen', 'info');
        return;
    }
    var p = warenkorb[aktiveZeile];
    zeileEntfernen(aktiveZeile);
    feedback('Storniert: ' + p.bezeichnung, 'ok');
}

// ── Bon-Rabatt Dialog ─────────────────────────────────────────────────────────
function bonRabattDialog() {
    rabTab('pct');
    document.getElementById('bonrab-pct').value = globalRabatt || '';
    document.getElementById('bonrab-eur').value = '';
    bonRabattVorschau();
    ov('ov-bonrab');
}
function bonRabattVorschau() {
    var pct  = parseFloat(document.getElementById('bonrab-pct').value) || 0;
    var ges  = getGesamt();
    var nachR = ges * (1 - pct / 100);
    document.getElementById('bonrab-vorschau').textContent =
        pct > 0 ? 'Ersparnis: € ' + fmt(ges - nachR) + ' → Gesamt: € ' + fmt(nachR) : '';
}
function bonRabattAnwenden() {
    var pct = parseFloat(document.getElementById('bonrab-val').value) || 0;
    if (pct < 0 || pct > 100) { feedback('Ungültiger Wert', 'fehler'); return; }
    globalRabatt = pct;
    ovSchliessen('ov-bonrab');
    renderBon();
}
function bonRabattEntfernen() {
    globalRabatt = 0;
    ovSchliessen('ov-bonrab');
    renderBon();
}

// ── Divers-Artikel ────────────────────────────────────────────────────────────
function diversDialog() {
    document.getElementById('div-name').value  = '';
    document.getElementById('div-preis').value = '';
    document.getElementById('btn-div-ok').disabled = true;
    ov('ov-divers');
    setTimeout(() => document.getElementById('div-name').focus(), 100);
}
function divPruefen() {
    var ok = document.getElementById('div-name').value.trim()
             && parseFloat(document.getElementById('div-preis').value) > 0;
    document.getElementById('btn-div-ok').disabled = !ok;
}
function divHinzufuegen() {
    var name  = document.getElementById('div-name').value.trim();
    var preis = parseFloat(document.getElementById('div-preis').value) || 0;
    var steuer = parseFloat(document.getElementById('div-steuer').value) || 20;
    if (!name || preis <= 0) return;
    var a = {
        id: null, bezeichnung: name, ean: null,
        brutto_vk: preis, steuer_prozent: steuer,
        bestand_physisch: 0, bestand_reserviert: 0, bestand_verkaufbar: 0,
        ueberverkauf_erlaubt: true, typ: 'artikel', istDivers: true
    };
    ovSchliessen('ov-divers');
    _artikelEinfuegen(a, 1);
}

// ── Gutschein abfragen (Auskunft ohne Buchung) ────────────────────────────────
function gutscheinAbfrageDialog() {
    document.getElementById('ph-dropdown').classList.remove('offen');
    document.getElementById('gsa-code').value = '';
    document.getElementById('gsa-ergebnis').innerHTML = '';
    ov('ov-gs-abfrage');
    setTimeout(() => document.getElementById('gsa-code').focus(), 100);
}
function gutscheinAbfragen() {
    var code = document.getElementById('gsa-code').value.trim().toUpperCase();
    var el = document.getElementById('gsa-ergebnis');
    if (code.length < 3) return;
    el.style.color = '#64748b';
    el.textContent = 'Frage ab…';
    fetch('<?= BASE_PATH ?>/gutscheine/pruefen.php?info=1&code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(function(d) {
            if (!d.erfolg) {
                el.style.color = '#dc2626';
                el.textContent = '✕ ' + d.fehler;
                return;
            }
            var datum = function(s) { return s ? s.substr(0, 10).split('-').reverse().join('.') : '—'; };
            var farbe = d.einloesbar ? '#16a34a' : '#dc2626';
            var zeile = function(l, w) {
                return '<div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #f1f5f9">' +
                       '<span style="color:#64748b">' + l + '</span><span>' + w + '</span></div>';
            };
            var html = '<div style="font-size:22px;font-weight:700;color:' + farbe + ';margin-bottom:8px">' +
                       (d.einloesbar ? '✓ € ' + fmt(d.restguthaben) + ' verfügbar' : '✕ nicht einlösbar') + '</div>';
            html += zeile('Code', '<span style="font-family:monospace">' + esc(d.code) + '</span>');
            html += zeile('Status', esc(d.status_text));
            html += zeile('Ursprünglicher Wert', '€ ' + fmt(d.betrag));
            html += zeile('Restguthaben', '€ ' + fmt(d.restguthaben));
            html += zeile('Gültig bis', datum(d.gueltig_bis));
            html += zeile('Ausgestellt am', datum(d.ausgestellt));
            if (d.empfaenger) html += zeile('Für', esc(d.empfaenger));
            if (d.nachfolger) {
                html += '<div style="margin-top:10px;padding:8px;background:#fef3c7;border-radius:6px;color:#92400e">' +
                        'Restguthaben liegt auf neuem Code <strong style="font-family:monospace">' + esc(d.nachfolger.code) +
                        '</strong> (€ ' + fmt(d.nachfolger.restguthaben) + ')</div>';
            } else if (!d.einloesbar && d.hinweis) {
                html += '<div style="margin-top:10px;color:#dc2626">' + esc(d.hinweis) + '</div>';
            }
            el.style.color = '#1e293b';
            el.innerHTML = html;
        })
        .catch(function() {
            el.style.color = '#dc2626';
            el.textContent = 'Verbindungsfehler bei der Abfrage.';
        });
}

// ── Gutschein verkaufen ───────────────────────────────────────────────────────
function gutscheinVerkaufDialog() {
    document.getElementById('ph-dropdown').classList.remove('offen');
    if (!GUTSCHEIN_ARTIKEL_ID) {
        feedback('Kein Gutschein-Artikel angelegt — bitte zuerst einen Artikel vom Typ "Gutschein" anlegen.', 'fehler');
        return;
    }
    document.getElementById('gsv-betrag').value = '';
    document.getElementById('gsv-empfaenger').value = '';
    document.getElementById('btn-gsv-ok').disabled = true;
    ov('ov-gs-verkauf');
    setTimeout(() => document.getElementById('gsv-betrag').focus(), 100);
}
function gsvBetragSetzen(b) {
    document.getElementById('gsv-betrag').value = b;
    gsvPruefen();
}
function gsvPruefen() {
    document.getElementById('btn-gsv-ok').disabled = !(parseFloat(document.getElementById('gsv-betrag').value) > 0);
}
function gsvHinzufuegen() {
    var betrag = Math.round((parseFloat(document.getElementById('gsv-betrag').value) || 0) * 100) / 100;
    if (betrag <= 0) return;
    var empfaenger = document.getElementById('gsv-empfaenger').value.trim();
    // Direkt anhängen statt _artikelEinfuegen(): dort würden zwei Gutscheine mit
    // unterschiedlichem Betrag zu einer Zeile zusammengeführt (Merge nur über artikel_id)
    warenkorb.push({
        artikel_id: GUTSCHEIN_ARTIKEL_ID,
        bezeichnung: 'Gutschein' + (empfaenger ? ' für ' + empfaenger : ''),
        ean: null, artnr: null, menge: 1,
        einzelpreis_brutto: betrag, steuer_prozent: 0, rabatt_prozent: 0,
        charge: null, konfig_wert_ids: null, istDivers: false,
        hat_chargen: false, charge_pflicht: false,
        bestand_physisch: 0, bestand_reserviert: 0, bestand_verkaufbar: 0,
        block: 'gutschein_kauf', gutschein_empfaenger: empfaenger || null,
    });
    aktiveZeile = warenkorb.length - 1;
    ovSchliessen('ov-gs-verkauf');
    renderBon();
}

// ── Rechnung bezahlen (Zahlbeleg) ─────────────────────────────────────────────
// Eigene Bon-Zeile "Zahlung zu Auftrag ..." mit 0 % -- die USt steht schon auf der
// Rechnung. Betrag/Bezeichnung legt bon_speichern.php serverseitig fest.
var rzAuswahl = null;
function rechnungZahlenDialog() {
    document.getElementById('ph-dropdown').classList.remove('offen');
    if (warenkorb.some(p => p.vonAuftrag || p.block === 'retour')) {
        feedback('Rechnung bezahlen bitte als eigenen Bon — nicht zusammen mit einer Abholung oder Retoure.', 'fehler');
        return;
    }
    rzAuswahl = null;
    document.getElementById('rz-suche').value = '';
    document.getElementById('rz-ergebnis').innerHTML = '';
    document.getElementById('rz-betrag-box').style.display = 'none';
    document.getElementById('btn-rz-ok').disabled = true;
    ov('ov-re-zahlung');
    setTimeout(() => document.getElementById('rz-suche').focus(), 100);
}
var _rzTimer = null;
function rzSuchenVerzoegert() {
    clearTimeout(_rzTimer);
    _rzTimer = setTimeout(rzSuchen, 300);
}
var _rzTimer = null;
function rzSuchenVerzoegert() {
    clearTimeout(_rzTimer);
    _rzTimer = setTimeout(rzSuchen, 300);
}
function rzSuchen() {
    var q = document.getElementById('rz-suche').value.trim();
    var el = document.getElementById('rz-ergebnis');
    if (q.length < 3) { el.textContent = 'Bitte mindestens 3 Zeichen eingeben.'; return; }
    el.textContent = 'Suche …';
    fetch('<?= BASE_PATH ?>/kasse/ajax_rechnung_offen.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(function(d) {
            if (!d.erfolg) { el.textContent = d.fehler || 'Fehler'; return; }
            if (!d.treffer.length) { el.textContent = 'Keine Rechnung gefunden.'; return; }
            // offene zuerst; sind alle bezahlt, klar sagen warum nichts auswählbar ist
            d.treffer.sort(function(x, y) { return (y.offen > 0.004) - (x.offen > 0.004); });
            var keinOffen = !d.treffer.some(function(t) { return t.offen > 0.004; });
            el.innerHTML = d.treffer.map(function(t, i) {
                var re = t.ohne_rechnung
                    ? 'Noch keine Rechnung (' + (t.lieferart === 'abholung' ? 'Abholung' : 'Versand') + ') — Klick lädt den Auftrag in die Kasse'
                    : t.rechnungen.map(r => esc(r.rechnung_nr) + ' (' + r.datum + ', € ' + fmt(r.bruttobetrag) + ')').join('<br>');
                var offen = t.offen > 0.004
                    ? '<strong style="color:#b45309">offen € ' + fmt(t.offen) + '</strong>'
                    : '<span style="color:#15803d">bezahlt</span>';
                return '<div class="rz-treffer" data-i="' + i + '" style="padding:8px;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:6px;cursor:' + (t.offen > 0.004 ? 'pointer' : 'default') + '">'
                    + '<strong>' + esc(t.auftrag_nr) + '</strong> — ' + esc(t.kunde || '') + '<br>'
                    + '<span style="font-size:12px;color:#64748b">' + re + '</span><br>' + offen + '</div>';
            }).join('') + (keinOffen ? '<div style="color:#64748b;font-size:12px;margin-top:4px">Alle gefundenen Rechnungen sind bereits bezahlt — hier gibt es nichts zu kassieren.</div>' : '');
            el.querySelectorAll('.rz-treffer').forEach(function(div) {
                var t = d.treffer[+div.dataset.i];
                if (t.offen <= 0.004) return;
                div.onclick = function() { rzWaehlen(t, div); };
            });
            if (d.treffer.length === 1 && d.treffer[0].offen > 0.004 && !d.treffer[0].ohne_rechnung) rzWaehlen(d.treffer[0], el.querySelector('.rz-treffer'));
        })
        .catch(() => { el.textContent = 'Verbindungsfehler.'; });
}
function rzWaehlen(t, div) {
    // Noch keine Rechnung (z.B. Zahlart Rechnung + Abholung): normal als Auftrag laden --
    // Abholung/Zahlung läuft dann über den Bon (= Beleg), inkl. Charge und "mitnehmen?"
    if (t.ohne_rechnung) {
        ovSchliessen('ov-re-zahlung');
        fetch('<?= BASE_PATH ?>/kasse/ajax_auftrag_laden.php?q=' + encodeURIComponent(t.auftrag_nr))
            .then(r => r.json())
            .then(function(liste) {
                var a = (liste || []).filter(function(x) { return x.id === t.auftrag_id; })[0];
                if (a) auftragWaehlen(a); else feedback('Auftrag ' + t.auftrag_nr + ' kann an der Kasse nicht geladen werden', 'fehler');
            })
            .catch(() => feedback('Verbindungsfehler', 'fehler'));
        return;
    }
    rzAuswahl = t;
    document.querySelectorAll('.rz-treffer').forEach(x => x.style.background = '');
    if (div) div.style.background = '#eff6ff';
    document.getElementById('rz-betrag-box').style.display = '';
    var b = document.getElementById('rz-betrag');
    b.value = t.offen.toFixed(2);
    b.max = t.offen.toFixed(2);
    document.getElementById('btn-rz-ok').disabled = false;
}
function rzHinzufuegen() {
    if (!rzAuswahl) return;
    var betrag = Math.round((parseFloat(document.getElementById('rz-betrag').value) || 0) * 100) / 100;
    if (betrag <= 0 || betrag > rzAuswahl.offen + 0.004) { feedback('Betrag muss zwischen 0 und € ' + fmt(rzAuswahl.offen) + ' liegen', 'fehler'); return; }
    if (warenkorb.some(p => p.block === 'zahlung' && p.zahlung_auftrag_id === rzAuswahl.auftrag_id)) {
        feedback('Zahlung zu diesem Auftrag ist schon im Bon', 'fehler'); return;
    }
    warenkorb.push({
        artikel_id: null,
        bezeichnung: 'Zahlung zu Auftrag ' + rzAuswahl.auftrag_nr,
        ean: null, artnr: null, menge: 1,
        einzelpreis_brutto: betrag, steuer_prozent: 0, rabatt_prozent: 0,
        charge: null, konfig_wert_ids: null, istDivers: false,
        hat_chargen: false, charge_pflicht: false,
        bestand_physisch: 0, bestand_reserviert: 0, bestand_verkaufbar: 0,
        block: 'zahlung', zahlung_auftrag_id: rzAuswahl.auftrag_id,
    });
    aktiveZeile = warenkorb.length - 1;
    ovSchliessen('ov-re-zahlung');
    renderBon();
}

// ── Mitgeben ──────────────────────────────────────────────────────────────────
function mitgebenDialog() {
    if (warenkorb.length === 0) { feedback('Bon ist leer', 'info'); return; }
    document.getElementById('mg-name').value  = '';
    document.getElementById('mg-datum').value = '';
    ov('ov-mitgeben');
}
function mitgebenSpeichern() {
    var positionen = warenkorb.filter(p => !p.istDivers && p.artikel_id && p.block !== 'gutschein_kauf');
    if (!positionen.length) { feedback('Nur echte Artikel können mitgegeben werden', 'fehler'); return; }
    fetch('<?= BASE_PATH ?>/kasse/offene_auswahl_speichern.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            kunden_name: document.getElementById('mg-name').value.trim() || null,
            rueckgabe_bis: document.getElementById('mg-datum').value || null,
            lager_id: LAGER_ID,
            positionen: positionen
        })
    }).then(r => r.json()).then(d => {
        ovSchliessen('ov-mitgeben');
        if (d.erfolg) {
            warenkorb = []; aktiveZeile = -1; clearNumpad(); renderBon();
            feedback('✓ Mitgegeben. OA #' + d.oa_id, 'ok');
        } else {
            feedback('❌ ' + (d.fehler || 'Fehler'), 'fehler');
        }
    }).catch(() => feedback('Verbindungsfehler', 'fehler'));
}

// ── Ausgabe nach Zahlung ──────────────────────────────────────────────────────
var _letzterBonId = null;
var _istGutscheinAusgabe = false; // true zwischen retourAlsGutschein() und der Server-Antwort
var _gutscheinAusgabeBetrag = 0;  // Betrag dieses Gutscheins — für die Kundenanzeige

function ausgabeNachZahlung(bonId, bonNr) {
    _letzterBonId = bonId;
    // Offline oder fix konfiguriert: direkt ausgeben ohne Dialog
    var format = AUSGABE_FORMAT;
    if (KASSE_MODUS === 'offline') format = '80mm';
    if (format === '80mm') { ausgabeOeffnen('80mm'); return; }
    if (format === 'a4')   { ausgabeOeffnen('a4');   return; }
    // 'fragen': Auswahl-Overlay zeigen
    document.getElementById('ov-ausgabe-nr').textContent = bonNr ? 'Bon-Nr.: ' + bonNr : '';
    ov('ov-ausgabe');
}

function ausgabeOeffnen(format) {
    ovSchliessen('ov-ausgabe');
    if (!_letzterBonId) return;
    if (format === '80mm') {
        window.open('<?= BASE_PATH ?>/kasse/bon_druck.php?id=' + _letzterBonId, '_blank');
    } else {
        window.open('<?= BASE_PATH ?>/kasse/bon_a4.php?id=' + _letzterBonId, '_blank');
    }
    _letzterBonId = null;
}

// ── Parken ────────────────────────────────────────────────────────────────────
// Pro Kasse höchstens EIN geparkter Bon (Jacky 2026-10-02). Ist einer geparkt, wird der
// Parken-Knopf zu "Geparkten holen" — ein zweiter wird nicht angenommen (Server prüft auch).
var geparkterBonId = null;
function parkenKnopfSetzen(id) {
    geparkterBonId = id || null;
    var btn = document.getElementById('btn-parken');
    if (btn) btn.textContent = geparkterBonId ? '▶ Geparkten holen' : '⏸ Parken';
}
// Abgleich mit dem Server (z.B. nach Kassenstart) — nie aus dem Browser-Cache
function geparktStatusLaden() {
    fetch('<?= BASE_PATH ?>/kasse/ajax_parken.php?aktion=liste&kasse_id=' + KASSE_ID + '&_=' + Date.now(), { cache: 'no-store' })
        .then(r => r.json())
        .then(function(d) { parkenKnopfSetzen((d.erfolg && d.liste.length) ? d.liste[0].id : null); })
        .catch(function() {});
}
document.addEventListener('DOMContentLoaded', geparktStatusLaden);

function bonParken() {
    if (geparkterBonId) { geparktenLaden(geparkterBonId); return; }
    if (warenkorb.length === 0) { feedback('Kein Bon zum Parken', 'info'); return; }
    document.getElementById('ph-dropdown').classList.remove('offen');
    var kundenAnzeige = document.getElementById('kunden-anzeige').textContent.trim();
    fetch('<?= BASE_PATH ?>/kasse/ajax_parken.php?aktion=speichern', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            kasse_id:     KASSE_ID,
            warenkorb:    warenkorb,
            global_rabatt: globalRabatt,
            kunden_id:    kundeId,
            kunden_name:  (kundeId || geladeneAuftraege.length) ? kundenAnzeige : null,
            auftrag_id:   geladenerAuftragId,
            kontext: {
                auftraege:               geladeneAuftraege,
                auftrag_nr:              geladenerAuftragNr,
                auftrag_status:          geladenerAuftragStatus,
                auftrag_mitnehmen:       geladenerAuftragMitnehmen,
                auftrag_zahlungsstatus:  geladenerAuftragZahlungsstatus,
                zusatz_positionen:       zusatzPositionen,
            },
        })
    })
    .then(r => r.json())
    .then(d => {
        if (!d.erfolg) { feedback(d.fehler || 'Parken fehlgeschlagen', 'fehler'); geparktStatusLaden(); return; }
        // Bon zurücksetzen
        warenkorb = []; aktiveZeile = -1; globalRabatt = 0;
        kundeId = null; geladeneAuftraege = []; mitnehmenWarteschlange = [];
        _hauptAuftragSpiegeln(); zusatzPositionen = [];
        document.getElementById('kunden-anzeige').textContent = 'Laufkunde';
        clearNumpad(); renderBon();
        parkenKnopfSetzen(d.id); // sofort umschalten, ohne auf den Server zu warten
        feedback('Bon geparkt (#' + d.id + ')', 'ok');
    })
    .catch(() => feedback('Verbindungsfehler', 'fehler'));
}

function bonAbrufen() {
    document.getElementById('ph-dropdown').classList.remove('offen');
    fetch('<?= BASE_PATH ?>/kasse/ajax_parken.php?aktion=liste&kasse_id=' + KASSE_ID)
        .then(r => r.json())
        .then(d => {
            if (!d.erfolg || d.liste.length === 0) {
                feedback('Keine geparkten Bons vorhanden', 'info'); return;
            }
            renderGeparktListe(d.liste);
            ov('ov-geparkt');
        })
        .catch(() => feedback('Verbindungsfehler', 'fehler'));
}

function renderGeparktListe(liste) {
    var html = '';
    liste.forEach(function(b) {
        var zeit = b.erstellt_am ? b.erstellt_am.substring(11, 16) : '';
        var datum = b.erstellt_am ? b.erstellt_am.substring(0, 10) : '';
        var kunde = b.kunden_name || 'Laufkunde';
        var total = b.total ? parseFloat(b.total).toFixed(2).replace('.', ',') : '—';
        var auftrag = b.auftrag_id ? (' · Auftrag #' + b.auftrag_id) : '';
        html += '<div style="display:flex;align-items:center;gap:10px;padding:12px 14px;border-bottom:1px solid #f1f5f9">';
        html +=   '<div style="flex:1">';
        html +=     '<div style="font-size:14px;font-weight:600;color:#1e3a5f">' + esc(kunde) + auftrag + '</div>';
        html +=     '<div style="font-size:11px;color:#64748b">' + datum + ' ' + zeit + ' · ' + b.positionen_anz + ' Pos.</div>';
        if (b.notiz) html += '<div style="font-size:11px;color:#92400e;margin-top:2px">' + esc(b.notiz) + '</div>';
        html +=   '</div>';
        html +=   '<div style="font-size:15px;font-weight:700;color:#1e293b;min-width:70px;text-align:right">€ ' + total + '</div>';
        html +=   '<button onclick="geparktenLaden(' + b.id + ')" style="height:34px;padding:0 14px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit">Laden</button>';
        html +=   '<button onclick="geparktenLoeschen(' + b.id + ', this)" style="height:34px;padding:0 10px;background:#fef2f2;color:#dc2626;border:1.5px solid #fca5a5;border-radius:6px;font-size:13px;cursor:pointer;font-family:inherit">✕</button>';
        html += '</div>';
    });
    document.getElementById('geparkt-liste').innerHTML = html || '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:13px">Keine geparkten Bons</div>';
}

function geparktenLaden(id) {
    if (warenkorb.length > 0 && !confirm('Aktuellen Bon verwerfen und geparkten Bon laden?')) return;
    fetch('<?= BASE_PATH ?>/kasse/ajax_parken.php?aktion=laden&id=' + id + '&kasse_id=' + KASSE_ID)
        .then(r => r.json())
        .then(d => {
            if (!d.erfolg) { feedback(d.fehler || 'Fehler', 'fehler'); return; }
            var b = d.bon;
            warenkorb    = b.warenkorb || [];
            globalRabatt = parseFloat(b.global_rabatt) || 0;
            kundeId      = b.kunden_id ? parseInt(b.kunden_id) : null;
            if (b.kunden_name) document.getElementById('kunden-anzeige').textContent = b.kunden_name;
            else               document.getElementById('kunden-anzeige').textContent = 'Laufkunde';
            var ktx = b.kontext ? (typeof b.kontext === 'string' ? JSON.parse(b.kontext) : b.kontext) : {};
            if (ktx.auftraege) {
                geladeneAuftraege = ktx.auftraege;
            } else if (b.auftrag_id) {
                // vor der Sammelabholung geparkt: ein Auftrag im alten Format
                geladeneAuftraege = [{
                    id: parseInt(b.auftrag_id), nr: ktx.auftrag_nr || null, status: ktx.auftrag_status || null,
                    mitnehmen: ktx.auftrag_mitnehmen === undefined ? null : ktx.auftrag_mitnehmen,
                    zahlungsstatus: ktx.auftrag_zahlungsstatus || null,
                    kunden_id: kundeId, kunden_email: null, kunden_name: '',
                }];
            } else {
                geladeneAuftraege = [];
            }
            mitnehmenWarteschlange = [];
            _hauptAuftragSpiegeln();
            zusatzPositionen               = ktx.zusatz_positionen      || [];
            aktiveZeile = -1;
            // Nach Laden aus DB löschen
            parkenKnopfSetzen(null);
            fetch('<?= BASE_PATH ?>/kasse/ajax_parken.php?aktion=loeschen&id=' + id + '&kasse_id=' + KASSE_ID, { method: 'POST' })
                .then(geparktStatusLaden, geparktStatusLaden);
            ovSchliessen('ov-geparkt');
            renderBon();
            feedback('Bon geladen', 'ok');
        })
        .catch(() => feedback('Verbindungsfehler', 'fehler'));
}

function geparktenLoeschen(id, btn) {
    if (!confirm('Geparkten Bon löschen?')) return;
    fetch('<?= BASE_PATH ?>/kasse/ajax_parken.php?aktion=loeschen&id=' + id + '&kasse_id=' + KASSE_ID, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (!d.erfolg) { feedback('Löschen fehlgeschlagen', 'fehler'); return; }
            var zeile = btn.closest('div[style]');
            if (zeile) zeile.remove();
            if (!document.getElementById('geparkt-liste').querySelector('div[style]')) {
                document.getElementById('geparkt-liste').innerHTML =
                    '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:13px">Keine geparkten Bons</div>';
            }
            geparktStatusLaden();
            feedback('Gelöscht', 'ok');
        })
        .catch(() => feedback('Verbindungsfehler', 'fehler'));
}

// ── Artikel-Suche ─────────────────────────────────────────────────────────────
var suchTimer = null;
function sucheLive(val) {
    clearTimeout(suchTimer);
    var liste = document.getElementById('such-liste');
    if (val.length < 2) { liste.innerHTML = ''; return; }
    suchTimer = setTimeout(function() {
        fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?suche=' + encodeURIComponent(val) + '&lager_id=' + LAGER_ID)
            .then(r => r.json())
            .then(function(d) {
                if (!d.erfolg || !d.ergebnisse.length) {
                    liste.innerHTML = '<p style="color:#94a3b8;padding:12px;font-size:13px">Kein Treffer für „' + esc(val) + '"</p>';
                    return;
                }
                var html = '';
                d.ergebnisse.forEach(function(a) {
                    var bestand = parseInt(a.bestand_physisch) || 0;
                    html += '<div class="such-item" onclick=\'suchWaehlen(' + JSON.stringify(a) + ')\'>' +
                        '<div>' +
                        '<div class="such-item-name">' + esc(a.bezeichnung) + '</div>' +
                        '<div class="such-item-sub">' + esc(a.artikelnummer || '—') + (a.ean ? ' · EAN: ' + esc(a.ean) : '') + '</div>' +
                        '</div>' +
                        '<div style="text-align:right">' +
                        '<div class="such-item-preis">€ ' + fmt(parseFloat(a.brutto_vk)) + '</div>' +
                        '<div class="such-item-bestand" style="color:' + (bestand > 0 ? '#16a34a' : '#dc2626') + '">Bestand: ' + bestand + '</div>' +
                        '</div>' +
                        '</div>';
                });
                liste.innerHTML = html;
            });
    }, 250);
}
function suchWaehlen(a) {
    ovSchliessen('ov-suche');
    if (a.ist_vater) {
        fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?code=' + encodeURIComponent(a.artikelnummer) + '&lager_id=' + LAGER_ID)
            .then(r => r.json()).then(d => { if (d.erfolg) zeigeVaterAuswahl(d); });
    } else if (a.ist_konfigurierbar) {
        fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?code=' + encodeURIComponent(a.artikelnummer) + '&lager_id=' + LAGER_ID)
            .then(r => r.json()).then(d => { if (d.erfolg) zeigeKonfiguratorAuswahl(d); });
    } else {
        a.bestand_verkaufbar = Math.max(0, (parseFloat(a.bestand_physisch) || 0));
        artikelVollstaendigHinzufuegen(a);
    }
}

// ── Freitext-Retour: Rückgabe ohne Auftragsbezug (z.B. alter JTL-Verkauf) ────
// Eigener, schlanker Suche-Schritt (nicht die normale sucheLive/suchWaehlen-Kette,
// die direkt einen Kauf hinzufügt) — hier folgt nach der Auswahl noch Menge+Preis,
// bevor die Zeile mit negativer Menge/block='retour' in den Warenkorb kommt.
var freitextRetourArtikel = null;
var frSuchTimer = null;

function freitextRetourDialog() {
    document.getElementById('fr-such-input').value = '';
    document.getElementById('fr-such-liste').innerHTML = '';
    freitextRetourArtikel = null;
    document.getElementById('fr-schritt-suche').style.display = 'block';
    document.getElementById('fr-schritt-menge').style.display = 'none';
    ov('ov-freitext-retour');
    setTimeout(() => document.getElementById('fr-such-input').focus(), 100);
}

function freitextRetourSucheLive(val) {
    clearTimeout(frSuchTimer);
    var liste = document.getElementById('fr-such-liste');
    if (val.length < 2) { liste.innerHTML = ''; return; }
    frSuchTimer = setTimeout(function() {
        fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?suche=' + encodeURIComponent(val) + '&lager_id=' + LAGER_ID)
            .then(r => r.json())
            .then(function(d) {
                if (!d.erfolg || !d.ergebnisse.length) {
                    liste.innerHTML = '<p style="color:#94a3b8;padding:12px;font-size:13px">Kein Treffer für „' + esc(val) + '"</p>';
                    return;
                }
                var html = '';
                d.ergebnisse.forEach(function(a) {
                    html += '<div class="such-item" onclick=\'freitextRetourArtikelWaehlen(' + JSON.stringify(a) + ')\'>' +
                        '<div>' +
                        '<div class="such-item-name">' + esc(a.bezeichnung) + '</div>' +
                        '<div class="such-item-sub">' + esc(a.artikelnummer || '—') + (a.ean ? ' · EAN: ' + esc(a.ean) : '') + '</div>' +
                        '</div>' +
                        '<div style="text-align:right"><div class="such-item-preis">€ ' + fmt(parseFloat(a.brutto_vk)) + '</div></div>' +
                        '</div>';
                });
                liste.innerHTML = html;
            });
    }, 250);
}

function freitextRetourArtikelWaehlen(a) {
    if (a.ist_vater) {
        alert('Das ist ein Vater-Artikel mit Varianten — bitte die konkrete Variante (Farbe/Stärke) suchen, nicht den Vater.');
        return;
    }
    freitextRetourArtikel = a;
    document.getElementById('fr-artikel-name').textContent = a.bezeichnung;
    document.getElementById('fr-menge').value = 1;
    document.getElementById('fr-preis').value = (parseFloat(a.brutto_vk) || 0).toFixed(2);
    document.getElementById('fr-charge').value = '';
    document.getElementById('fr-charge-unbekannt').checked = false;
    // Chargenpflicht (z.B. Garn — Farbkonsistenz!) muss auch bei einer Rückgabe ohne
    // Auftrag beachtet werden, sonst landet Ware unkontrolliert im Bestand. Siehe
    // packplatz/ruecklagerungen.php, das dieselbe Charge übernimmt bzw. bei Bedarf
    // noch abfragt, falls hier "unbekannt" angehakt wird.
    document.getElementById('fr-charge-block').style.display = a.charge_pflicht ? 'block' : 'none';
    document.getElementById('fr-schritt-suche').style.display = 'none';
    document.getElementById('fr-schritt-menge').style.display = 'block';
}

function freitextRetourZurueckZurSuche() {
    freitextRetourArtikel = null;
    document.getElementById('fr-schritt-suche').style.display = 'block';
    document.getElementById('fr-schritt-menge').style.display = 'none';
    setTimeout(() => document.getElementById('fr-such-input').focus(), 100);
}

function freitextRetourChargeUnbekanntToggle() {
    var unbekannt = document.getElementById('fr-charge-unbekannt').checked;
    var feld = document.getElementById('fr-charge');
    feld.disabled = unbekannt;
    if (unbekannt) feld.value = '';
}

function freitextRetourUebernehmen() {
    if (!freitextRetourArtikel) return;
    var a = freitextRetourArtikel;

    var charge = null;
    if (a.charge_pflicht) {
        var unbekannt = document.getElementById('fr-charge-unbekannt').checked;
        charge = document.getElementById('fr-charge').value.trim();
        if (!unbekannt && charge === '') {
            alert('Dieser Artikel ist chargenpflichtig — bitte Charge eintragen oder "Charge unbekannt" anhaken.');
            return;
        }
        if (unbekannt) charge = null;
    }

    var menge = Math.max(1, parseInt(document.getElementById('fr-menge').value) || 1);
    var preis = Math.max(0, parseFloat(document.getElementById('fr-preis').value) || 0);

    warenkorb.push({
        artikel_id: a.id, bezeichnung: a.bezeichnung, ean: a.ean || null,
        menge: -menge, einzelpreis_brutto: preis,
        steuer_prozent: a.steuer_prozent, rabatt_prozent: 0,
        charge: charge || null, istDivers: false, vonAuftrag: false,
        block: 'retour', kein_lagerabzug: true,
    });

    ovSchliessen('ov-freitext-retour');
    renderBon();
    feedback('↩ Freitext-Retour: ' + menge + '× ' + a.bezeichnung, 'ok');
}

// ── Kunde suchen ──────────────────────────────────────────────────────────────
function kundeDialog() {
    document.getElementById('kunde-input').value = '';
    document.getElementById('kunde-liste').innerHTML = '';
    ov('ov-kunde');
    setTimeout(() => document.getElementById('kunde-input').focus(), 100);
}

var kundeTimer = null;
function kundeSucheLive(val) {
    clearTimeout(kundeTimer);
    var liste = document.getElementById('kunde-liste');
    if (val.length < 2) { liste.innerHTML = ''; return; }
    kundeTimer = setTimeout(function() {
        fetch('<?= BASE_PATH ?>/kasse/ajax_kunden_suche.php?suche=' + encodeURIComponent(val))
            .then(r => r.json())
            .then(function(d) {
                if (!d.erfolg || !d.kunden.length) {
                    liste.innerHTML = '<p style="color:#94a3b8;padding:12px;font-size:13px">Kein Treffer für „' + esc(val) + '"</p>';
                    return;
                }
                var html = '';
                d.kunden.forEach(function(k) {
                    html += '<div class="such-item" onclick=\'kundeWaehlen(' + JSON.stringify(k) + ')\'>' +
                        '<div style="font-size:16px;margin-right:4px">' + (k.ist_firma ? '🏢' : '👤') + '</div>' +
                        '<div style="flex:1">' +
                        '  <div class="such-item-name">' + esc(k.name) + '</div>' +
                        '  <div class="such-item-sub">' + esc(k.kundennummer) +
                            (k.kundengruppe ? ' · ' + esc(k.kundengruppe) : '') +
                            (k.email ? ' · ' + esc(k.email) : '') + '</div>' +
                        '</div>' +
                        '</div>';
                });
                liste.innerHTML = html;
            });
    }, 250);
}

function kundeWaehlen(k) {
    kundeId = k.id;
    document.getElementById('kunden-anzeige').textContent = k.name + ' (' + k.kundennummer + ')';
    ovSchliessen('ov-kunde');
    feedback('Kunde: ' + k.name, 'ok');
}

function kundeLaufkunde() {
    kundeId = null;
    document.getElementById('kunden-anzeige').textContent = 'Laufkunde';
    ovSchliessen('ov-kunde');
}

// ── Menü-Dropdown ─────────────────────────────────────────────────────────────
function toggleMenue(e) {
    e.stopPropagation();
    document.getElementById('ph-dropdown').classList.toggle('offen');
}
document.addEventListener('click', function() {
    document.getElementById('ph-dropdown').classList.remove('offen');
});

// ── Bezahlen ──────────────────────────────────────────────────────────────────
function _zahlBetrag() {
    return aktuellerZahlBetrag !== null ? aktuellerZahlBetrag : getGesamt();
}

function berechneAbrechnungsModus() {
    var extraBrutto  = 0;
    var retourBrutto = 0;
    warenkorb.forEach(function(p) {
        var rab = 1 - (posRabatt(p) / 100);
        if (p.vonAuftrag && auftragNichtsMitgenommen(zeileAuftragId(p))) return;
        if (p.vonAuftrag && auftragBezahlt(zeileAuftragId(p))) {
            var origMenge = p.original_menge !== undefined ? p.original_menge : p.menge;
            var diff = origMenge - p.menge;
            // Nur was der Kunde nicht will, wird erstattet — "später abholen" bleibt bezahlt+offen
            if (diff > 0.001 && p.restModus === 'verzicht') retourBrutto += diff * p.einzelpreis_brutto * rab;
        } else {
            // Extras + Zeilen noch unbezahlter Aufträge (Sammelabholung) werden kassiert
            extraBrutto += p.menge * p.einzelpreis_brutto * rab;
        }
    });
    retourePositionen.forEach(function(p) {
        if (p.retourMenge > 0) {
            retourBrutto += p.retourMenge * p.einzelpreis_brutto * (1 - p.rabatt_prozent / 100);
        }
    });
    guthabenAuftraege().forEach(function(a) { retourBrutto += a.guthaben; });
    var netBrutto = extraBrutto - retourBrutto;
    var modus = (retourBrutto < 0.005 && extraBrutto < 0.005) ? 'exakt'
              : (netBrutto < -0.005)                          ? 'retour'
              :                                                  'extra';
    return { modus: modus, extraBrutto: extraBrutto, retourBrutto: retourBrutto, netBrutto: netBrutto };
}

// Geladene, bezahlte Aufträge mit Guthaben (mehr bezahlt als der Auftragsbetrag, z.B. Versand
// → Abholung umgestellt), von denen etwas mitgenommen wird -> Auszahlung bar oder als Gutschein
// über die normale Rückgabe-Abfrage (ov-retour-bar). Server prüft den Betrag nach.
function guthabenAuftraege() {
    return geladeneAuftraege.filter(function(a) {
        return a.guthaben > 0.004 && a.zahlungsstatus === 'bezahlt' && !auftragNichtsMitgenommen(a.id);
    });
}

function berechneZusatzPositionen() {
    zusatzPositionen = [];
    guthabenAuftraege().forEach(function(a) {
        zusatzPositionen.push({
            artikel_id: null, bezeichnung: 'Guthaben zu Auftrag ' + a.nr, ean: null,
            menge: -1, einzelpreis_brutto: a.guthaben, steuer_prozent: 0, rabatt_prozent: 0,
            charge: null, istDivers: false, vonAuftrag: false, auftrag_position_id: null,
            web_auftrag_id: a.id, kein_lagerabzug: true, block: 'retour', guthaben: true,
        });
    });
    warenkorb.forEach(function(p) {
        if (!p.vonAuftrag || !auftragBezahlt(zeileAuftragId(p)) || auftragNichtsMitgenommen(zeileAuftragId(p))) return;
        var origMenge = p.original_menge !== undefined ? p.original_menge : p.menge;
        var diff = origMenge - p.menge;
        if (diff < 0.001 || p.restModus !== 'verzicht') return;
        zusatzPositionen.push({
            artikel_id: p.artikel_id, bezeichnung: p.bezeichnung, ean: p.ean || null,
            menge: -diff, einzelpreis_brutto: p.einzelpreis_brutto,
            steuer_prozent: p.steuer_prozent,
            rabatt_prozent: posRabatt(p),
            charge: p.charge || null, istDivers: false,
            vonAuftrag: false, auftrag_position_id: null,
            web_auftrag_id: zeileAuftragId(p),
            kein_lagerabzug: true, block: 'retour',
        });
    });
    retourePositionen.forEach(function(p) {
        if (p.retourMenge <= 0) return;
        zusatzPositionen.push({
            artikel_id: p.artikel_id, bezeichnung: p.bezeichnung, ean: p.ean || null,
            menge: -p.retourMenge, einzelpreis_brutto: p.einzelpreis_brutto,
            steuer_prozent: p.steuer_prozent, rabatt_prozent: p.rabatt_prozent,
            charge: p.charge || null, istDivers: false,
            // auftrag_position_id bewusst NICHT durchreichen (wie bei der bestehenden
            // Retour-Logik oben) — bon_speichern.php filtert Positionen mit gesetzter
            // auftrag_position_id sonst als "schon bezahlt, nicht Teil des Bons" heraus.
            // retour_von_position_id ist ein separates Feld nur zur Rückverfolgung, damit
            // menge_retourniert auf der Original-Position korrekt hochgezählt werden kann.
            vonAuftrag: false, auftrag_position_id: null,
            retour_von_position_id: p.auftrag_position_id || null,
            web_auftrag_id: geladenerAuftragId,
            kein_lagerabzug: true, block: 'retour',
        });
    });
}

// ── Weniger mitgenommen als bestellt: Rest später abholen oder will er nicht? ──
// (Jacky 2026-10-02) Gilt für bezahlte und unbezahlte Aufträge. "Will er nicht" = wie eine
// Retoure des Rests (bezahlt: Geld zurück), "später" = Rest bleibt offen (teilgeliefert).
function reduzierteAuftragsZeilen() {
    return warenkorb.filter(function(p) {
        if (!p.vonAuftrag || !p.auftrag_position_id || auftragNichtsMitgenommen(zeileAuftragId(p))) return false;
        var orig = p.original_menge !== undefined ? p.original_menge : p.menge;
        return orig - p.menge > 0.001;
    });
}
function restFrageOffen() {
    return reduzierteAuftragsZeilen().some(function(p) { return !p.restModus || p.restModusMenge !== p.menge; });
}
function restVerzichtListe() {
    return reduzierteAuftragsZeilen().filter(function(p) { return p.restModus === 'verzicht'; }).map(function(p) {
        var orig = p.original_menge !== undefined ? p.original_menge : p.menge;
        return { auftrag_position_id: p.auftrag_position_id, menge: orig - p.menge };
    });
}
function restFrageZeigen() {
    var html = '';
    reduzierteAuftragsZeilen().forEach(function(p, i) {
        var orig = p.original_menge !== undefined ? p.original_menge : p.menge;
        var a = auftragInfo(zeileAuftragId(p));
        var gewaehlt = p.restModusMenge === p.menge ? p.restModus : null;
        html += '<div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;margin-bottom:8px">'
              + '<div style="font-weight:600;font-size:14px">' + esc(p.bezeichnung) + '</div>'
              + '<div style="font-size:12px;color:#6b7280;margin-bottom:6px">' + (a ? esc(a.nr) + ' · ' + (a.zahlungsstatus === 'bezahlt' ? 'bezahlt' : 'unbezahlt') + ' · ' : '')
              + p.menge + ' von ' + orig + ' mitgenommen — Rest ' + (orig - p.menge) + '</div>'
              + '<label style="display:block;font-size:14px;padding:3px 0;cursor:pointer"><input type="radio" name="rest-' + i + '" value="spaeter"' + (gewaehlt === 'spaeter' ? ' checked' : '') + '> Holt der Kunde <strong>später</strong> ab (bleibt offen)</label>'
              + '<label style="display:block;font-size:14px;padding:3px 0;cursor:pointer"><input type="radio" name="rest-' + i + '" value="verzicht"' + (gewaehlt === 'verzicht' ? ' checked' : '') + '> Will der Kunde <strong>nicht</strong> (Ware zurück ins Lager'
              + (a && a.zahlungsstatus === 'bezahlt' ? ', Geld zurück' : '') + ')</label>'
              + '</div>';
    });
    document.getElementById('rest-liste').innerHTML = html;
    document.getElementById('rest-fehler').style.display = 'none';
    ov('ov-rest');
}
function restFrageBestaetigen() {
    var zeilen = reduzierteAuftragsZeilen();
    var wahl = zeilen.map(function(p, i) {
        var r = document.querySelector('input[name="rest-' + i + '"]:checked');
        return r ? r.value : null;
    });
    if (wahl.indexOf(null) !== -1) { document.getElementById('rest-fehler').style.display = 'block'; return; }
    zeilen.forEach(function(p, i) { p.restModus = wahl[i]; p.restModusMenge = p.menge; });
    ovSchliessen('ov-rest');
    bezahlenDialog();
}

// ── Charge für mitgenommene Auftragsware (nicht gepackt, "Ware wird mitgenommen") ──
// Die Kasse bucht diese Ware selbst ab -> Charge wie beim Scannen abfragen (Fund Klicktest
// 2026-10-07: wurde ohne Charge gebucht). Gefragt wird beim Bezahlen, damit Mengenänderungen
// vorher noch möglich sind. Mehrere Chargen -> Zeile wird aufgeteilt (gleiche Auftragsposition).
var _mitnahmeChargeZeile = null;
function _mitnahmeChargeOffen() {
    for (var i = 0; i < warenkorb.length; i++) {
        var p = warenkorb[i];
        if (!p.vonAuftrag || !p.artikel_id || p.menge <= 0 || p.charge || p._chargeGeprueft) continue;
        var a = auftragInfo(zeileAuftragId(p));
        if (a && a.mitnehmen === true) return i;
    }
    return -1;
}
function _mitnahmeChargeFragen(idx) {
    var p = warenkorb[idx];
    fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?code=' + encodeURIComponent(p.artnr || p.ean || '') + '&lager_id=' + LAGER_ID)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.erfolg || !(d.charge_pflicht || d.hat_chargen)) {
                if (!d.erfolg && p.charge_pflicht) { feedback('Chargen zu ' + p.bezeichnung + ' nicht ladbar — bitte prüfen', 'fehler'); return; }
                p._chargeGeprueft = true; bezahlenDialog(); return;
            }
            _mitnahmeChargeZeile = idx;
            zeigeKasseChargePopup(Object.assign({}, d, { bezeichnung: p.bezeichnung }), p.menge);
        })
        .catch(function() { feedback('Verbindungsfehler beim Laden der Chargen', 'fehler'); });
}
function _mitnahmeChargeUebernehmen(eintraege) {
    var idx = _mitnahmeChargeZeile; _mitnahmeChargeZeile = null;
    var p = warenkorb[idx];
    if (!eintraege.length) { p._chargeGeprueft = true; bezahlenDialog(); return; } // "Ohne Charge" (nur ohne Pflicht)
    var teile = eintraege.map(function(e) {
        return Object.assign({}, p, { menge: e.menge, original_menge: e.menge, charge: e.charge,
            nachzutragen_lagerbestand_id: e.nachtragen ? e.lagerbestand_id : null, _chargeGeprueft: true });
    });
    warenkorb.splice.apply(warenkorb, [idx, 1].concat(teile));
    renderBon();
    bezahlenDialog();
}

function bezahlenDialog() {
    if (warenkorb.length === 0 && !retoureAktiv()) return;
    if (geladeneAuftraege.length && restFrageOffen()) { restFrageZeigen(); return; }
    var chargeIdx = _mitnahmeChargeOffen();
    if (chargeIdx >= 0) { _mitnahmeChargeFragen(chargeIdx); return; }

    var einAuftragBezahlt = geladeneAuftraege.some(function(a) { return a.zahlungsstatus === 'bezahlt'; });
    if ((einAuftragBezahlt || retoureAktiv()) && geladeneAuftraege.length) {
        var m = berechneAbrechnungsModus();
        aktuellerZahlBetrag = m.netBrutto;
        // Immer schon hier berechnen (nicht erst im Zahlungs-Popup) — sonst geht bei
        // "extra, Netto ungleich 0" (eigenes ov-bezahlen-Popup) die Retour-Position verloren.
        berechneZusatzPositionen();

        if (m.modus === 'exakt') {
            var origTotal = 0;
            warenkorb.forEach(function(p) {
                if (p.vonAuftrag) {
                    var orig = p.original_menge !== undefined ? p.original_menge : p.menge;
                    origTotal += orig * p.einzelpreis_brutto * (1 - posRabatt(p) / 100);
                }
            });
            document.getElementById('bezahlt-info-text').textContent =
                (geladeneAuftraege.length > 1 ? 'Aufträge ' : 'Auftrag ') + auftragNummernText()
                + ' · € ' + fmt(origTotal) + ' — vollständig bezahlt.'
                + (reduzierteAuftragsZeilen().length ? ' Nicht Mitgenommenes bleibt offen (teilgeliefert) und kann später abgeholt werden.' : '');
            ov('ov-bezahlt-info');
            return;
        }
        if (m.modus === 'retour') {
            var auszahlung = Math.abs(m.netBrutto);
            document.getElementById('retour-betrag-anzeige').textContent = '€ ' + fmt(auszahlung);
            document.getElementById('retour-info-text').textContent = m.extraBrutto > 0.005
                ? 'Extras +€' + fmt(m.extraBrutto) + ' · Rückgabe −€' + fmt(m.retourBrutto) + ' · Auszahlung: €' + fmt(auszahlung)
                : 'Rückgabe: €' + fmt(m.retourBrutto) + ' werden bar ausgezahlt.';
            ov('ov-retour-bar');
            return;
        }
        // modus === 'extra' (netto positiv oder 0)
        if (Math.abs(m.netBrutto) < 0.005) {
            berechneZusatzPositionen();
            bonSpeichern({ zahlungsart: 'bar', gegeben: 0, rueckgeld: 0 });
            return;
        }
        document.getElementById('bez-total').textContent = '€ ' + fmt(m.netBrutto);
        kdSync('abrechnen', { betrag: m.netBrutto, gegeben: null, rueckgeld: null, abgeschlossen: false });
        ov('ov-bezahlen');
        return;
    }

    var g = getGesamt();
    aktuellerZahlBetrag = g;
    document.getElementById('bez-total').textContent = '€ ' + fmt(g);
    kdSync('abrechnen', { betrag: g, gegeben: null, rueckgeld: null, abgeschlossen: false });
    ov('ov-bezahlen');
}

function zahlenBar() {
    ovSchliessen('ov-bezahlen');
    var g = _zahlBetrag();
    document.getElementById('bar-total').textContent = '€ ' + fmt(g);
    barClear();
    ov('ov-bar');
    setTimeout(() => document.getElementById('bar-gegeben').focus(), 100);
}
function barBerechne() {
    var g = _zahlBetrag();
    var geg = parseFloat(document.getElementById('bar-gegeben').value) || 0;
    var rueck = geg - g;
    var el = document.getElementById('bar-rueck');
    if (geg > 0 && geg >= g) {
        el.textContent = 'Rückgeld: € ' + fmt(rueck);
        el.style.color = '#16a34a';
    } else if (geg > 0) {
        el.textContent = 'Fehlend: € ' + fmt(Math.abs(rueck));
        el.style.color = '#dc2626';
    } else {
        el.textContent = '';
    }
}
function abschliessenBar() {
    var g   = _zahlBetrag();
    var geg = parseFloat(document.getElementById('bar-gegeben').value) || 0;
    if (geg > 0) {
        bonSpeichern({ zahlungsart: 'bar', gegeben: geg, rueckgeld: Math.max(0, geg - g) });
    } else {
        bonSpeichern({ zahlungsart: 'bar' });
    }
}

function zahlenKarte() {
    ovSchliessen('ov-bezahlen');
    document.getElementById('karte-total').textContent = '€ ' + fmt(_zahlBetrag());
    ov('ov-karte');
}
function abschliessenKarte() { bonSpeichern({ zahlungsart: 'karte_extern' }); }

// Ergebnis der letzten Code-Prüfung -- nur ein geprüfter Code kann eingelöst werden
var _gsGeprueft = null; // { code, restguthaben, einloesen, offen }

function zahlenGutschein() {
    if (warenkorb.some(p => p.block === 'gutschein_kauf')) {
        feedback('Gutscheine können nicht mit einem Gutschein bezahlt werden.', 'fehler');
        return;
    }
    if (_zahlBetrag() <= 0) {
        feedback('Gutschein-Zahlung ist nur bei einem positiven Betrag möglich.', 'fehler');
        return;
    }
    ovSchliessen('ov-bezahlen');
    document.getElementById('gs-total').textContent = '€ ' + fmt(_zahlBetrag());
    document.getElementById('gs-code').value = '';
    gsCodeGeaendert();
    ov('ov-gutschein');
    setTimeout(() => document.getElementById('gs-code').focus(), 100);
}
function gsCodeGeaendert() {
    _gsGeprueft = null;
    document.getElementById('gs-info').textContent = '';
    document.getElementById('gs-rest-bereich').hidden = true;
    document.getElementById('gs-gegeben').value = '';
    document.getElementById('gs-rueck').textContent = '';
    document.getElementById('btn-gs-ok').disabled = true;
}
function gsPruefen() {
    var code = document.getElementById('gs-code').value.trim().toUpperCase();
    if (code.length < 3) return;
    var info = document.getElementById('gs-info');
    info.style.color = '#64748b';
    info.textContent = 'Prüfe…';
    fetch('<?= BASE_PATH ?>/gutscheine/pruefen.php?code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(function(d) {
            if (!d.erfolg) {
                info.style.color = '#dc2626';
                info.textContent = '✕ ' + d.fehler;
                return;
            }
            var g = _zahlBetrag();
            var einloesen = Math.min(d.restguthaben, g);
            var offen = Math.round((g - einloesen) * 100) / 100;
            var restNachher = Math.round((d.restguthaben - einloesen) * 100) / 100;
            _gsGeprueft = { code: d.code, restguthaben: d.restguthaben, einloesen: einloesen, offen: offen };
            info.style.color = '#16a34a';
            var html = '✓ Guthaben € ' + fmt(d.restguthaben) + ' — wird eingelöst: € ' + fmt(einloesen);
            if (restNachher > 0.005) {
                html += '<br><span style="color:#64748b">Restguthaben € ' + fmt(restNachher) + ' → Kunde bekommt einen neuen Code</span>';
            }
            if (offen > 0.005) {
                html += '<br><strong style="color:#b45309">Offen: € ' + fmt(offen) + ' — Rest bar oder mit Karte</strong>';
                document.getElementById('gs-rest-bereich').hidden = false;
                document.getElementById('btn-gs-ok').disabled = true;
            } else {
                document.getElementById('btn-gs-ok').disabled = false;
            }
            info.innerHTML = html;
        })
        .catch(function() {
            info.style.color = '#dc2626';
            info.textContent = 'Verbindungsfehler beim Prüfen.';
        });
}
function gsRueckgeld() {
    var el = document.getElementById('gs-rueck');
    var geg = parseFloat(document.getElementById('gs-gegeben').value) || 0;
    if (!_gsGeprueft || geg <= 0) { el.textContent = ''; return; }
    var diff = geg - _gsGeprueft.offen;
    el.style.color = diff >= -0.005 ? '#16a34a' : '#dc2626';
    el.textContent = diff >= -0.005 ? 'Rückgeld: € ' + fmt(diff) : 'Fehlend: € ' + fmt(-diff);
}
function abschliessenGS(restArt) {
    if (!_gsGeprueft) return;
    var daten = { zahlungsart: 'gutschein', gutschein_code: _gsGeprueft.code, gutschein_betrag: _gsGeprueft.einloesen };
    if (_gsGeprueft.offen > 0.005) {
        if (!restArt) return;
        daten.rest_zahlungsart = restArt;
        if (restArt === 'bar') {
            var geg = parseFloat(document.getElementById('gs-gegeben').value) || 0;
            if (geg > 0 && geg < _gsGeprueft.offen - 0.005) {
                feedback('Gegebener Betrag reicht nicht für den Rest.', 'fehler');
                return;
            }
            if (geg > 0) {
                daten.gegeben = geg;
                daten.rueckgeld = Math.round((geg - _gsGeprueft.offen) * 100) / 100;
            }
        }
    }
    bonSpeichern(daten);
}

function zahlenKombi() {
    ovSchliessen('ov-bezahlen');
    document.getElementById('kombi-total').textContent = '€ ' + fmt(_zahlBetrag());
    document.getElementById('kombi-karte').value = '';
    document.getElementById('kombi-bar').value   = '';
    document.getElementById('kombi-diff').textContent = '';
    document.getElementById('btn-kombi-ok').disabled = true;
    ov('ov-kombi');
}
function kombiBerechne() {
    var g = _zahlBetrag();
    var k = parseFloat(document.getElementById('kombi-karte').value) || 0;
    var b = parseFloat(document.getElementById('kombi-bar').value)   || 0;
    var diff = k + b - g;
    var el = document.getElementById('kombi-diff');
    if (Math.abs(diff) < 0.005) {
        el.textContent = '✓ Passt genau'; el.style.color = '#16a34a';
        document.getElementById('btn-kombi-ok').disabled = false;
    } else if (diff > 0.005) {
        el.textContent = 'Rückgeld Bar: € ' + fmt(diff); el.style.color = '#16a34a';
        document.getElementById('btn-kombi-ok').disabled = false;
    } else {
        el.textContent = 'Fehlend: € ' + fmt(Math.abs(diff)); el.style.color = '#dc2626';
        document.getElementById('btn-kombi-ok').disabled = true;
    }
}
function abschliessenKombi() {
    var k = parseFloat(document.getElementById('kombi-karte').value) || 0;
    var b = parseFloat(document.getElementById('kombi-bar').value)   || 0;
    var diff = k + b - _zahlBetrag();
    bonSpeichern({ zahlungsart: 'kombi', karten_betrag: k, bar_betrag: b, rueckgeld: Math.max(0, diff) });
}

// ── Bon speichern ─────────────────────────────────────────────────────────────
function _resetKasseState() {
    warenkorb = []; aktiveZeile = -1; globalRabatt = 0; clearNumpad(); kundeId = null;
    geladeneAuftraege = []; mitnehmenWarteschlange = []; weitereAuftraegePruefenFuer = null;
    _hauptAuftragSpiegeln();
    aktuellerZahlBetrag = null; zusatzPositionen = [];
    retourePositionen = [];
    document.getElementById('ai-leer').style.display = 'block';
    document.getElementById('ai-inhalt').style.display = 'none';
    document.getElementById('kunden-anzeige').textContent = 'Laufkunde';
    renderBon(true); // Sync übernimmt bonSpeichern()/abschliessenOhneBon() selbst (Danke-Screen soll stehen bleiben)
    renderRetoureSektion();
}

function bonSpeichern(zahlDaten) {
    ['ov-bar','ov-karte','ov-gutschein','ov-kombi'].forEach(ovSchliessen);
    document.getElementById('spinner').classList.add('offen');

    var g = _zahlBetrag();
    var positionen = warenkorb.map(function(p) {
        return Object.assign({}, p, { rabatt_prozent: posRabatt(p) });
    }).concat(zusatzPositionen);
    var zp = zusatzPositionen.slice(); // Kopie vor Reset
    zusatzPositionen = [];

    fetch('<?= BASE_PATH ?>/kasse/bon_speichern.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({
            kasse_id: KASSE_ID,
            lager_id: LAGER_ID,
            kunden_id: kundeId,
            bruttobetrag: g,
            positionen: positionen,
            web_auftraege:               geladeneAuftraege.map(function(a) { return { id: a.id, mitnehmen: a.mitnehmen }; }),
            rest_verzicht:               restVerzichtListe(),
        }, zahlDaten))
    })
    .then(r => r.json())
    .then(function(d) {
        document.getElementById('spinner').classList.remove('offen');
        if (d.erfolg) {
            _bfrFehlschlagAnzahl = 0;
            if (_istGutscheinAusgabe) {
                // Rückgabe als Gutschein: kein Rückgeld, sondern Gutschrift (Code folgt, siehe
                // zeigeGutscheinAusgabeErgebnis)
                kdSync('abrechnen', { betrag: -_gutscheinAusgabeBetrag, gutschein_ausgabe: true, gutschein_code: null, abgeschlossen: true });
            } else {
                kdSync('abrechnen', {
                    betrag:       g,
                    gegeben:      (zahlDaten.gegeben !== undefined ? zahlDaten.gegeben : null),
                    rueckgeld:    (zahlDaten.rueckgeld !== undefined ? zahlDaten.rueckgeld : null),
                    abgeschlossen: true
                });
            }
            _resetKasseState();
            (d.warnungen || []).forEach(w => feedback('⚠ ' + w, 'fehler'));
            if (d.bon_id) {
                var gsListe = (d.gutscheine_ausgestellt || []).map(g => Object.assign({ label: 'Gutschein' }, g));
                if (d.gutschein_rest) gsListe.push(Object.assign({ label: 'Restguthaben — neuer Code' }, d.gutschein_rest));
                if (_istGutscheinAusgabe) {
                    _istGutscheinAusgabe = false;
                    zeigeGutscheinAusgabeErgebnis(d.bon_id);
                } else if (gsListe.length) {
                    // Erst Codes/PDF zeigen, danach wie gewohnt den Bon ausgeben
                    zeigeGutscheinErgebnis(gsListe, function() { ausgabeNachZahlung(d.bon_id, d.bon_nr || ''); });
                } else {
                    ausgabeNachZahlung(d.bon_id, d.bon_nr || '');
                }
            }
        } else if (d.braucht_manager_pin) {
            zusatzPositionen = zp; // Rücksetzen, Retry übernimmt zp erneut
            _managerPinPendingZahlDaten = zahlDaten;
            document.getElementById('manager-pin-info-text').textContent = d.fehler;
            document.getElementById('manager-pin-fehler').textContent = '';
            document.getElementById('manager-pin-input').value = '';
            ov('ov-manager-pin');
            setTimeout(() => document.getElementById('manager-pin-input').focus(), 100);
        } else if (d.bfr_nicht_erreichbar) {
            zusatzPositionen = zp; // Rücksetzen, Retry übernimmt zp erneut
            zeigeBfrPopup(zahlDaten, 'Beleg wartet: ' + warenkorb.length + ' Position(en), € ' + fmt(g));
        } else {
            zusatzPositionen = zp; // Rücksetzen bei Fehler
            feedback('❌ ' + (d.fehler || 'Unbekannter Fehler'), 'fehler');
        }
    })
    .catch(function() {
        document.getElementById('spinner').classList.remove('offen');
        feedback('Netzwerkfehler — bitte erneut versuchen', 'fehler');
    });
}

function abschliessenOhneBon() {
    ovSchliessen('ov-bezahlt-info');
    document.getElementById('spinner').classList.add('offen');
    var positionen = warenkorb.map(function(p) {
        return Object.assign({}, p, { rabatt_prozent: posRabatt(p) });
    });
    fetch('<?= BASE_PATH ?>/kasse/bon_speichern.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            kasse_id: KASSE_ID, lager_id: LAGER_ID, kunden_id: kundeId, bruttobetrag: 0,
            zahlungsart: 'bar', positionen: positionen,
            web_auftraege:              geladeneAuftraege.map(function(a) { return { id: a.id, mitnehmen: a.mitnehmen }; }),
            rest_verzicht:              restVerzichtListe(),
            nur_abschliessen:           true,
        })
    })
    .then(r => r.json())
    .then(function(d) {
        document.getElementById('spinner').classList.remove('offen');
        if (d.erfolg) {
            _resetKasseState();
            kdSync('idle', {});
            feedback('✓ Auftrag ' + (d.auftrag_nr || '') + ' abgeschlossen — Bestätigung gesendet', 'ok');
        } else {
            feedback('❌ ' + (d.fehler || 'Fehler beim Abschließen'), 'fehler');
        }
    })
    .catch(function() {
        document.getElementById('spinner').classList.remove('offen');
        feedback('Netzwerkfehler', 'fehler');
    });
}

function retourBestaetigen() {
    ovSchliessen('ov-retour-bar');
    berechneZusatzPositionen();
    // Auszahlung aus Kundensicht: nichts gegeben, Betrag als Rückgeld (Bon-Betrag/RKSV bleibt negativ)
    var auszahlung = Math.round(Math.abs(_zahlBetrag()) * 100) / 100;
    bonSpeichern({ zahlungsart: 'bar', gegeben: 0, rueckgeld: auszahlung });
}

/**
 * Gutschein statt Bar-Auszahlung bei Retoure: fügt eine zusätzliche, echte
 * Bon-Position "Gutschein-Verkauf" (0% MwSt, Betrag = Retourbetrag) hinzu --
 * die Retour-Position (negativ) + diese Position (positiv) summieren sich zu
 * 0, der Bon wird trotzdem ganz normal RKSV-signiert statt eine stille
 * DB-Buchung ohne Bon-Bezug zu sein (siehe project_gutscheine.md).
 */
function retourAlsGutschein() {
    if (!GUTSCHEIN_ARTIKEL_ID) {
        feedback('Kein Gutschein-Artikel angelegt — bitte zuerst in den Artikelstammdaten anlegen.', 'fehler');
        return;
    }
    ovSchliessen('ov-retour-bar');
    berechneZusatzPositionen();
    var m = berechneAbrechnungsModus();
    var betrag = Math.round(Math.abs(m.netBrutto) * 100) / 100;
    zusatzPositionen.push({
        artikel_id: GUTSCHEIN_ARTIKEL_ID, bezeichnung: 'Gutschein-Verkauf', ean: null,
        menge: 1, einzelpreis_brutto: betrag, steuer_prozent: 0, rabatt_prozent: 0,
        charge: null, istDivers: false, vonAuftrag: false, auftrag_position_id: null,
        kein_lagerabzug: true, block: 'gutschein_verkauf',
    });
    _istGutscheinAusgabe = true;
    _gutscheinAusgabeBetrag = betrag;
    bonSpeichern({ zahlungsart: 'gutschein_ausgabe', gegeben: 0, rueckgeld: 0 });
}

function zeigeGutscheinAusgabeErgebnis(bonId) {
    fetch('<?= BASE_PATH ?>/gutscheine/letzter_fuer_bon.php?bon_id=' + bonId)
        .then(r => r.json())
        .then(function(d) {
            if (d.erfolg) {
                kdSync('abrechnen', { betrag: -parseFloat(d.betrag), gutschein_ausgabe: true, gutschein_code: d.code, abgeschlossen: true });
                zeigeGutscheinErgebnis([{ id: d.id, code: d.code, betrag: d.betrag, label: 'Erstattung als Gutschein' }], null);
            } else {
                feedback('Gutschein wurde erstellt, aber Code konnte nicht geladen werden — bitte in der Gutscheine-Liste nachsehen.', 'fehler');
            }
        })
        .catch(function() {
            feedback('Gutschein wurde erstellt, aber Code konnte nicht geladen werden — bitte in der Gutscheine-Liste nachsehen.', 'fehler');
        });
}

var _gsErgebnisDanach = null;
/** Zeigt erstellte Gutschein-Codes mit PDF-Link; danach() läuft beim Schließen (z.B. Bon-Ausgabe). */
function zeigeGutscheinErgebnis(liste, danach) {
    _gsErgebnisDanach = danach || null;
    document.getElementById('ga-titel').textContent = liste.length > 1 ? '🎁 Gutscheine erstellt' : '🎁 Gutschein erstellt';
    document.getElementById('ga-liste').innerHTML = liste.map(function(g) {
        return '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:10px;text-align:center">' +
            '<div style="font-size:12px;color:#64748b">' + esc(g.label || 'Gutschein') + '</div>' +
            '<div style="font-family:monospace;font-size:20px;font-weight:700;margin:4px 0">' + esc(g.code) + '</div>' +
            '<div style="font-size:15px;color:#374151;margin-bottom:8px">Wert: € ' + fmt(g.betrag) + '</div>' +
            '<a href="<?= BASE_PATH ?>/gutscheine/pdf_download.php?id=' + encodeURIComponent(g.id) + '" target="_blank" ' +
            'class="ov-btn ov-btn-ok" style="display:inline-block;width:auto;padding:8px 18px;text-decoration:none">📄 PDF öffnen</a>' +
            '</div>';
    }).join('');
    ov('ov-gutschein-ausgabe-ergebnis');
}
function gutscheinErgebnisSchliessen() {
    ovSchliessen('ov-gutschein-ausgabe-ergebnis');
    var f = _gsErgebnisDanach;
    _gsErgebnisDanach = null;
    if (f) f();
}

// ── Manager-Freigabe per PIN ─────────────────────────────────────────────────
var _managerPinPendingZahlDaten = null;

function managerPinAbbrechen() {
    ovSchliessen('ov-manager-pin');
    _managerPinPendingZahlDaten = null;
    document.getElementById('spinner').classList.remove('offen');
}

function managerPinBestaetigen() {
    var pin = document.getElementById('manager-pin-input').value.trim();
    if (!/^\d{4,6}$/.test(pin)) {
        document.getElementById('manager-pin-fehler').textContent = 'PIN muss 4-6 Ziffern haben.';
        return;
    }
    var zahlDaten = Object.assign({}, _managerPinPendingZahlDaten, { manager_pin: pin });
    ovSchliessen('ov-manager-pin');
    bonSpeichern(zahlDaten);
}

// ── Charge-Auswahl (Kasse) ───────────────────────────────────────────────────
var kasseChargePendingArtikel = null;
var kasseChargePendingMenge   = 1;
var kasseChargeEingaben       = {}; // 'row_N' → {charge, menge, lagerbestand_id, nachtragen}
var _kasseChargenDaten        = []; // aktuell angezeigte Chargen-Zeilen

function zeigeKasseChargePopup(a, menge) {
    kasseChargePendingArtikel = a;
    kasseChargePendingMenge   = menge;
    kasseChargeEingaben       = {};
    _kasseChargenDaten        = a.alle_chargen || [];

    document.getElementById('charge-ov-titel').textContent = 'Charge — ' + a.bezeichnung;
    document.getElementById('charge-ov-menge').textContent = menge + ' Stk.';
    document.getElementById('charge-ov-gesamt').textContent = '0';
    document.getElementById('btn-charge-kasse-ok').disabled = true;

    var chargen = _kasseChargenDaten;
    var body    = document.getElementById('charge-ov-body');
    body.innerHTML = '';

    if (chargen.length === 0) {
        body.innerHTML = '<p style="color:#64748b;font-size:13px">Keine Chargen im Lager vorhanden.</p>';
        if (a.charge_pflicht) {
            body.innerHTML += '<p style="color:#dc2626;font-size:12px;margin-top:6px">Charge-Pflicht: bitte zuerst Charge im Wareneingang eintragen.</p>';
        }
    } else {
        var html = '<table style="width:100%;border-collapse:collapse">';
        html += '<thead><tr style="font-size:12px;color:#94a3b8">' +
            '<th style="text-align:left;padding:6px 8px">Charge</th>' +
            '<th style="text-align:right;padding:6px 8px">Bestand</th>' +
            '<th style="text-align:center;padding:6px 8px;min-width:160px">Menge</th>' +
            '</tr></thead><tbody>';

        chargen.forEach(function(c, rowIdx) {
            var isNt = c.charge_status === 'nachzutragen';
            var chargeLabel = isNt
                ? '<input type="text" id="kc-name-' + rowIdx + '" class="bon-input" style="width:120px;padding:4px 8px;font-size:12px" placeholder="Chargennummer" oninput="kasseChargeNtNamen(' + rowIdx + ')">'
                : '<span style="font-family:monospace;font-size:13px">' + esc(c.charge) + '</span>';

            html += '<tr style="border-top:1px solid #e2e8f0">';
            html += '<td style="padding:8px">' + chargeLabel + '</td>';
            html += '<td style="text-align:right;padding:8px;color:#94a3b8">' + parseFloat(c.bestand).toFixed(0) + '</td>';
            html += '<td style="text-align:center;padding:8px">';
            html += '<div style="display:flex;align-items:center;gap:5px;justify-content:center">';
            html += '<button type="button" onclick="kasseChargeBtn(' + rowIdx + ',-1)" ' +
                'style="width:30px;height:30px;background:#dc2626;border:none;border-radius:6px;color:#fff;font-size:16px;cursor:pointer;line-height:1">−</button>';
            html += '<input type="number" id="kc-menge-' + rowIdx + '" value="0" min="0" ' +
                'style="width:54px;text-align:center;background:#f8fafc;border:1px solid #cbd5e1;color:#1e293b;padding:4px;border-radius:6px;font-size:13px" ' +
                'oninput="kasseChargeInputGeaendert(' + rowIdx + ',this.value)">';
            html += '<button type="button" onclick="kasseChargeBtn(' + rowIdx + ',1)" ' +
                'style="width:30px;height:30px;background:#2563eb;border:none;border-radius:6px;color:#fff;font-size:16px;cursor:pointer;line-height:1">+</button>';
            html += '</div></td></tr>';
        });
        html += '</tbody></table>';

        if (!a.charge_pflicht) {
            html += '<div style="margin-top:12px;padding:8px;border-top:1px solid #e2e8f0">' +
                '<button class="ov-btn ov-btn-sec" style="width:100%;font-size:12px" onclick="kasseChargeOhne()">Ohne Charge buchen</button>' +
                '</div>';
        }

        body.innerHTML = html;

        // FIFO-Charge vorbelegen
        if (a.fifo_charge) {
            var fifoIdx = chargen.findIndex(function(c) { return c.charge === a.fifo_charge; });
            if (fifoIdx >= 0) {
                document.getElementById('kc-menge-' + fifoIdx).value = menge;
                _kasseChargeZeileAktualisieren(fifoIdx, menge);
            }
        }
    }

    ov('ov-charge');
}

// Helfer: rowIdx → Charge-Daten aus _kasseChargenDaten nachschlagen
function kasseChargeBtn(rowIdx, delta) {
    var input = document.getElementById('kc-menge-' + rowIdx);
    if (!input) return;
    var neu = Math.max(0, (parseFloat(input.value) || 0) + delta);
    input.value = neu;
    _kasseChargeZeileAktualisieren(rowIdx, neu);
}

function kasseChargeInputGeaendert(rowIdx, val) {
    _kasseChargeZeileAktualisieren(rowIdx, parseFloat(val) || 0);
}

function kasseChargeNtNamen(rowIdx) {
    var menge = parseFloat((document.getElementById('kc-menge-' + rowIdx) || {}).value) || 0;
    _kasseChargeZeileAktualisieren(rowIdx, menge);
}

function _kasseChargeZeileAktualisieren(rowIdx, menge) {
    var c  = _kasseChargenDaten[rowIdx];
    if (!c) return;
    var isNt  = c.charge_status === 'nachzutragen';
    var charge = isNt
        ? ((document.getElementById('kc-name-' + rowIdx) || {}).value || '').trim()
        : c.charge;
    var key = 'row_' + rowIdx;
    if (menge <= 0) {
        delete kasseChargeEingaben[key];
    } else {
        kasseChargeEingaben[key] = { charge: charge || null, menge: menge, lagerbestand_id: c.id, nachtragen: isNt };
    }
    kasseChargeUpdateGesamt();
}

function kasseChargeUpdateGesamt() {
    var total = Object.values(kasseChargeEingaben).reduce(function(s, e) { return s + e.menge; }, 0);
    document.getElementById('charge-ov-gesamt').textContent = total;
    document.getElementById('btn-charge-kasse-ok').disabled = total <= 0;
}

function kasseChargeOhne() {
    ovSchliessen('ov-charge');
    if (_mitnahmeChargeZeile !== null) { _mitnahmeChargeUebernehmen([]); return; }
    var a = Object.assign({}, kasseChargePendingArtikel, { _gewaehltCharge: null, _nachtragen_lagerbestand_id: null });
    _fortsetzungNachChargeAuswahl(a, kasseChargePendingMenge);
}

function chargeKasseBestaetigen() {
    var eintraege = Object.values(kasseChargeEingaben);
    if (eintraege.length === 0) return;
    for (var i = 0; i < eintraege.length; i++) {
        if (eintraege[i].nachtragen && !eintraege[i].charge) {
            alert('Bitte Chargennummer für alle "nachzutragen"-Zeilen eingeben.');
            return;
        }
    }
    if (_mitnahmeChargeZeile !== null) {
        var summe = eintraege.reduce(function(s, e) { return s + e.menge; }, 0);
        if (Math.abs(summe - kasseChargePendingMenge) > 0.001) {
            alert('Bitte genau ' + kasseChargePendingMenge + ' Stk. auf die Chargen verteilen (derzeit ' + summe + ').');
            return;
        }
        ovSchliessen('ov-charge');
        _mitnahmeChargeUebernehmen(eintraege);
        return;
    }
    ovSchliessen('ov-charge');
    eintraege.forEach(function(entry) {
        var a = Object.assign({}, kasseChargePendingArtikel, {
            _gewaehltCharge:             entry.charge,
            _nachtragen_lagerbestand_id: entry.nachtragen ? entry.lagerbestand_id : null,
        });
        _fortsetzungNachChargeAuswahl(a, entry.menge);
    });
}

function chargeKasseAbbrechen() {
    ovSchliessen('ov-charge');
    _mitnahmeChargeZeile = null; // Bezahlen abgebrochen -- beim nächsten Bezahlen wird wieder gefragt
    kasseChargePendingArtikel = null;
    kasseChargeEingaben       = {};
}

function _fortsetzungNachChargeAuswahl(a, menge) {
    // Reservierungs-Prüfung (wie in artikelHinzufuegen)
    var physisch   = parseFloat(a.bestand_physisch)   || 0;
    var reserviert = parseFloat(a.bestand_reserviert) || 0;
    var verkaufbar = parseFloat(a.bestand_verkaufbar !== undefined ? a.bestand_verkaufbar : (physisch - reserviert));
    if (!a.ueberverkauf_erlaubt && menge > verkaufbar && reserviert > 0) {
        pendingArtikel = { a: a, menge: menge };
        document.getElementById('reswarn-text').innerHTML =
            '<strong>' + esc(a.bezeichnung) + '</strong><br>' +
            'Physisch: ' + physisch + ' · Reserviert: ' + reserviert + ' · Verkaufbar: ' + Math.max(0,verkaufbar) + '<br>' +
            'Angefordert: ' + menge;
        ov('ov-reswarn');
        return;
    }
    _artikelEinfuegen(a, menge);
}

// ── Kasse Artikel-Suche Modal ─────────────────────────────────────────────────
var artikelSucheTimer = null;

function openArtikelSuche() {
    ov('ov-artikelsuche');
    var inp = document.getElementById('as-input');
    inp.value = '';
    document.getElementById('as-ergebnisse').innerHTML = '<div style="color:#94a3b8;font-size:13px;padding:8px">Mindestens 2 Zeichen eingeben…</div>';
    setTimeout(function() { inp.focus(); }, 100);
}

function artikelSucheInput() {
    clearTimeout(artikelSucheTimer);
    var val = document.getElementById('as-input').value.trim();
    var box = document.getElementById('as-ergebnisse');
    if (val.length < 2) {
        box.innerHTML = '<div style="color:#94a3b8;font-size:13px;padding:8px">Mindestens 2 Zeichen eingeben…</div>';
        return;
    }
    box.innerHTML = '<div style="color:#94a3b8;font-size:13px;padding:8px">Suche…</div>';
    artikelSucheTimer = setTimeout(function() {
        fetch('<?= BASE_PATH ?>/kasse/ajax_artikel.php?suche=' + encodeURIComponent(val) + '&lager_id=' + LAGER_ID)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.erfolg || !d.ergebnisse || !d.ergebnisse.length) {
                    box.innerHTML = '<div style="color:#94a3b8;font-size:13px;padding:8px">Keine Treffer.</div>';
                    return;
                }
                box.innerHTML = '';
                d.ergebnisse.forEach(function(a) {
                    var div = document.createElement('div');
                    div.style.cssText = 'padding:10px 14px;border-bottom:1px solid #e2e8f0;cursor:pointer;display:flex;justify-content:space-between;align-items:center';
                    div.onmouseenter = function() { div.style.background = '#f1f5f9'; };
                    div.onmouseleave = function() { div.style.background = ''; };
                    var preis = a.brutto_vk ? parseFloat(a.brutto_vk).toFixed(2).replace('.', ',') + ' €' : '—';
                    var bestand = parseFloat(a.bestand_physisch || 0).toFixed(0);
                    div.innerHTML = '<div>'
                        + '<div style="font-weight:600;font-size:14px">' + esc(a.bezeichnung) + '</div>'
                        + '<div style="font-size:12px;color:#64748b">' + esc(a.artikelnummer || '') + (a.ean ? ' · ' + esc(a.ean) : '') + '</div>'
                        + '</div>'
                        + '<div style="text-align:right;flex-shrink:0;margin-left:12px">'
                        + '<div style="font-weight:700;font-size:14px">' + preis + '</div>'
                        + '<div style="font-size:11px;color:#94a3b8">Lager: ' + bestand + '</div>'
                        + '</div>';
                    div.addEventListener('click', function() {
                        ovSchliessen('ov-artikelsuche');
                        artikelHinzufuegen(a);
                    });
                    box.appendChild(div);
                });
            });
    }, 250);
}

// ── Overlay-Helfer ────────────────────────────────────────────────────────────
function ov(id) {
    document.getElementById(id).classList.add('offen');
}
function ovSchliessen(id) {
    document.getElementById(id).classList.remove('offen');
    scanInput.focus();
}

// ── RKSV: BFR-Erreichbarkeits-Popup (2 Eskalationsstufen) ────────────────────
var _bfrPendingZahlDaten = null;
var _bfrFehlschlagAnzahl = 0;

function zeigeBfrPopup(zahlDaten, kontextText) {
    _bfrPendingZahlDaten = zahlDaten || null;
    _bfrFehlschlagAnzahl++;
    var stufe2 = _bfrFehlschlagAnzahl >= 2;
    document.getElementById('bfr-popup-stufe1').style.display = stufe2 ? 'none' : 'block';
    document.getElementById('bfr-popup-stufe2').style.display = stufe2 ? 'block' : 'none';
    document.getElementById('btn-bfr-retry').textContent = stufe2
        ? 'Überprüft — Dienst sollte wieder laufen'
        : 'Erneut versuchen';
    document.getElementById('bfr-popup-kontext').textContent = kontextText || '';
    ov('ov-bfr-ausfall');
}

function bfrErneutVersuchen() {
    document.getElementById('ov-bfr-ausfall').classList.remove('offen');
    if (_bfrPendingZahlDaten) {
        var zd = _bfrPendingZahlDaten;
        _bfrPendingZahlDaten = null;
        bonSpeichern(zd);
    } else {
        // Kassenstart-Fall — kein Bon anhängig, nur State erneut prüfen
        bfrKassenstartPruefen();
    }
}

function bfrKassenstartPruefen() {
    fetch('<?= BASE_PATH ?>/kasse/ajax_bfr_check.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'kasse_id=' + KASSE_ID + '&aktion=kassenstart',
    })
    .then(r => r.json())
    .then(function(d) {
        if (d.erreichbar || !d.bfr_konfiguriert) {
            _bfrFehlschlagAnzahl = 0;
            document.getElementById('ov-bfr-ausfall').classList.remove('offen');
        } else {
            zeigeBfrPopup(null, 'Kasse kann erst gestartet werden, sobald der Dienst wieder antwortet.');
        }
    })
    .catch(function() {
        zeigeBfrPopup(null, 'Kasse kann erst gestartet werden, sobald der Dienst wieder antwortet.');
    });
}

document.addEventListener('DOMContentLoaded', bfrKassenstartPruefen);

// ── Feedback Snackbar ─────────────────────────────────────────────────────────
var feedbackTimer = null;
function feedback(msg, typ) {
    var el = document.getElementById('feedback');
    el.innerHTML = '<div class="fb-msg fb-' + (typ || 'info') + '">' + esc(msg) + '</div>';
    clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => { el.innerHTML = ''; }, typ === 'fehler' ? 5000 : 2500);
}

// ── Preis-Override in aktiver Zeile ──────────────────────────────────────────
function preisOverride(i) {
    var neuerPreis = parseFloat(numpadBuf.replace(',', '.'));
    if (!neuerPreis || neuerPreis <= 0) {
        feedback('Bitte zuerst neuen Preis auf Numpad eingeben', 'info');
        return;
    }
    var alt = warenkorb[i].einzelpreis_brutto;
    warenkorb[i].einzelpreis_brutto = neuerPreis;
    clearNumpad();
    renderBon();
    feedback('Preis: € ' + fmt(alt) + ' → € ' + fmt(neuerPreis), 'ok');
}

// ── Bar: Geldscheine akkumulieren ─────────────────────────────────────────────
var barScheine = [];

function barNoteAdd(betrag) {
    barScheine.push(betrag);
    var summe = barScheine.reduce(function(a, b) { return a + b; }, 0);
    document.getElementById('bar-gegeben').value = summe.toFixed(2);
    var log = barScheine.join(' + ') + ' = € ' + fmt(summe);
    document.getElementById('bar-scheine-log').textContent = log;
    barBerechne();
}
function barClear() {
    barScheine = [];
    document.getElementById('bar-gegeben').value = '';
    document.getElementById('bar-scheine-log').textContent = '';
    document.getElementById('bar-rueck').textContent = '';
}
function barBerechneManual() {
    barScheine = [];  // Manuelle Eingabe überschreibt Schein-Log
    document.getElementById('bar-scheine-log').textContent = '';
    barBerechne();
}

// ── Rabatt: Tab-Umschalter ────────────────────────────────────────────────────
var rabaktivTab = 'pct';
function rabTab(tab) {
    rabaktivTab = tab;
    document.getElementById('rab-tab-pct').classList.toggle('aktiv', tab === 'pct');
    document.getElementById('rab-tab-eur').classList.toggle('aktiv', tab === 'eur');
    document.getElementById('rab-pct-area').style.display = tab === 'pct' ? 'block' : 'none';
    document.getElementById('rab-eur-area').style.display = tab === 'eur' ? 'block' : 'none';
    document.getElementById('bonrab-vorschau').textContent = '';
}
function bonRabattVorschauEur() {
    var neu = parseFloat(document.getElementById('bonrab-eur').value) || 0;
    var alt = getGesamt();
    if (alt <= 0 || neu <= 0 || neu >= alt) {
        document.getElementById('bonrab-vorschau').textContent =
            neu >= alt ? '⚠ Neuer Preis muss unter aktuellem Gesamt liegen' : '';
        return;
    }
    var pct = (1 - neu / alt) * 100;
    var ersparnis = alt - neu;
    document.getElementById('bonrab-vorschau').textContent =
        'Entspricht ' + pct.toFixed(2).replace('.', ',') + '% Rabatt · Ersparnis: € ' + fmt(ersparnis);
}
function bonRabattAnwenden() {
    var pct;
    if (rabaktivTab === 'pct') {
        pct = parseFloat(document.getElementById('bonrab-pct').value) || 0;
    } else {
        var neu = parseFloat(document.getElementById('bonrab-eur').value) || 0;
        var alt = getGesamt();
        if (neu <= 0 || neu >= alt) { feedback('Ungültiger Betrag', 'fehler'); return; }
        pct = (1 - neu / alt) * 100;
    }
    if (pct < 0 || pct > 100) { feedback('Ungültiger Rabatt', 'fehler'); return; }
    globalRabatt = Math.round(pct * 100) / 100;
    ovSchliessen('ov-bonrab');
    renderBon();
    feedback('Bon-Rabatt ' + fmt(globalRabatt).replace(',00','') + '% angewendet', 'ok');
}

// ── Kassenlade öffnen ─────────────────────────────────────────────────────────
function kasseladeOeffnen() {
    document.getElementById('ph-dropdown').classList.remove('offen');
    fetch('<?= BASE_PATH ?>/kasse/ajax_kassenlade.php', { method: 'POST' })
        .then(r => r.json())
        .then(d => { feedback(d.hinweis || '⊟ Kassenlade geöffnet', 'ok'); })
        .catch(() => feedback('⊟ Kassenlade-Befehl gesendet', 'ok'));
}

function nullbonDialog() {
    ov('ov-nullbon');
}

function nullbonBestaetigen() {
    ovSchliessen('ov-nullbon');
    fetch('<?= BASE_PATH ?>/kasse/ajax_nullbon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'kasse_id=' + <?= (int)$kasseId ?>,
    })
        .then(r => r.json())
        .then(d => {
            if (d.erfolg && d.ausgefallen) {
                feedback('⚠ Nullbon erstellt, aber Sicherheitseinrichtung meldet "ausgefallen" (' + d.beleg_nr + ')', 'info');
            } else if (d.erfolg) {
                feedback('✓ Nullbon erstellt (' + d.beleg_nr + ')', 'ok');
            } else {
                feedback(d.fehler || 'Nullbon fehlgeschlagen', 'fehler');
            }
        })
        .catch(() => feedback('Nullbon fehlgeschlagen — keine Antwort vom Server', 'fehler'));
}

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────
function fmt(n) {
    return (Math.round(n * 100) / 100).toFixed(2).replace('.', ',');
}
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Auftrag laden ─────────────────────────────────────────────────────────────
var auftragSuchTimer = null;

function auftragLadenDialog() {
    document.getElementById('ph-dropdown').classList.remove('offen');
    document.getElementById('auftrag-such-feld').value = '';
    document.getElementById('auftrag-alle-cb').checked = false;
    ov('ov-auftrag-laden');
    auftragSucheAusfuehren();
    setTimeout(() => document.getElementById('auftrag-such-feld').focus(), 150);
}

function auftragSuchen() {
    clearTimeout(auftragSuchTimer);
    auftragSuchTimer = setTimeout(auftragSucheAusfuehren, 350);
}

function auftragSucheAusfuehren() {
    var q    = document.getElementById('auftrag-such-feld').value.trim();
    var alle = document.getElementById('auftrag-alle-cb').checked ? '1' : '0';
    var liste = document.getElementById('auftrag-liste');
    liste.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px">Lädt …</div>';

    fetch('<?= BASE_PATH ?>/kasse/ajax_auftrag_laden.php?q=' + encodeURIComponent(q) + '&alle=' + alle)
        .then(r => r.json())
        .then(function(data) {
            if (!data.length) {
                liste.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px">Keine offenen Aufträge gefunden</div>';
                return;
            }
            liste.innerHTML = '';
            data.forEach(function(a) {
                var div = document.createElement('div');
                div.className = 'auftrag-item';
                div._auftragDaten = a;
                div.innerHTML = '<div class="auftrag-item-nr">' + esc(a.auftrag_nr) + '</div>'
                    + '<div class="auftrag-item-info">' + esc(a.kunden_name) + '<br><span style="font-size:11px;color:#94a3b8">' + esc(a.erstellt_datum) + '</span></div>'
                    + '<div class="auftrag-item-status">'
                        + '<span class="a-chip a-chip-' + a.lieferstatus + '">' + esc(a.lieferstatus_label) + '</span>'
                        + '<span class="a-chip a-chip-' + a.zahlungsstatus + '">' + esc(a.zahlungsstatus_label) + '</span>'
                    + '</div>'
                    + '<div class="auftrag-item-betrag">€ ' + fmt(parseFloat(a.bruttobetrag)) + '</div>';
                div.addEventListener('click', function() {
                    auftragWaehlen(this._auftragDaten);
                });
                liste.appendChild(div);
            });
        })
        .catch(function() {
            liste.innerHTML = '<div style="padding:24px;text-align:center;color:#dc2626;font-size:13px">Fehler beim Laden</div>';
        });
}

// ── Geladene Web-Aufträge (Sammelabholung) ───────────────────────────────────
// geladeneAuftraege = alle Aufträge auf diesem Bon (nur EIN Kunde). geladenerAuftragId
// & Co. spiegeln den ersten davon -- Retoure-Modus, Parken und älterer Code lesen die.
function _hauptAuftragSpiegeln() {
    var a = geladeneAuftraege[0] || null;
    geladenerAuftragId             = a ? a.id : null;
    geladenerAuftragNr             = a ? a.nr : null;
    geladenerAuftragStatus         = a ? a.status : null;
    geladenerAuftragMitnehmen      = a ? a.mitnehmen : null;
    geladenerAuftragZahlungsstatus = a ? a.zahlungsstatus : null;
    document.getElementById('btn-auftrag-laden').classList.toggle('geladen', !!a);
}
function auftragInfo(id) {
    for (var i = 0; i < geladeneAuftraege.length; i++) if (geladeneAuftraege[i].id === id) return geladeneAuftraege[i];
    return null;
}
function auftragBezahlt(id) {
    var a = auftragInfo(id);
    return !!a && a.zahlungsstatus === 'bezahlt';
}
// Zeile → Auftrag. Ältere geparkte Bons kennen web_auftrag_id an der Zeile noch nicht.
function zeileAuftragId(p) {
    if (!p.vonAuftrag) return null;
    return p.web_auftrag_id || (geladeneAuftraege[0] ? geladeneAuftraege[0].id : null);
}
// Sammelabholung: von diesem Auftrag wird gar nichts mitgenommen → er bleibt unverändert
// liegen (keine Zahlung, keine Erstattung). Gleiche Regel wie in bon_speichern.php.
function auftragNichtsMitgenommen(id) {
    if (geladeneAuftraege.length < 2) return false;
    var zeilen = warenkorb.filter(function(p) { return zeileAuftragId(p) === id; });
    return zeilen.length > 0 && zeilen.every(function(p) { return p.menge <= 0; });
}
function auftragNummernText() {
    return geladeneAuftraege.map(function(a) { return a.nr; }).join(', ');
}
function _kundenAnzeigeAuftraege() {
    var name = geladeneAuftraege.length ? geladeneAuftraege[0].kunden_name : '';
    document.getElementById('kunden-anzeige').textContent = geladeneAuftraege.length
        ? '📦 ' + auftragNummernText() + (name ? ' · ' + name : '')
        : 'Laufkunde';
}
function _gleicherKunde(a, b) {
    if (a.kunden_id && b.kunden_id) return a.kunden_id === b.kunden_id;
    return !!a.kunden_email && a.kunden_email === b.kunden_email;
}
function _istRetoureStatus(lieferstatus) {
    // versendet/teilgeliefert/abgeschlossen: Ware ist (teilweise) schon raus — es gibt nichts
    // zu "behalten", einzig sinnvolle Aktion ist eine Rückgabe. Eigene Retoure-Sektion statt
    // Warenkorb-Zeilen. 'abgeschlossen' zählt mit, weil ein bezahlter, versendeter Auftrag
    // durch die Auto-Logik in packplatz/warenausgang/abschliessen.php sofort dorthin springt —
    // der Praxisfall "bezahlt + versendet" landet also fast nie sichtbar bei 'versendet'.
    return lieferstatus === 'versendet' || lieferstatus === 'teilgeliefert' || lieferstatus === 'abgeschlossen' || lieferstatus === 'retoure_offen';
}
// Ausnahme: Abholung, von der noch etwas offen ist ("holt er später", Rest im Abholfach oder
// noch ungepackt) — die wird wieder als Abholung geladen, nicht als Retoure (Jacky 2026-10-02)
function _istRetoureAuftrag(a) {
    if (a.lieferstatus === 'teilgeliefert' && a.lieferart === 'abholung' && parseFloat(a.menge_offen || 0) > 0) return false;
    return _istRetoureStatus(a.lieferstatus);
}
// Gepackte Ware liegt im Abholfach → Übergabe ohne neue Lagerbuchung, keine Mitnehmen-Frage
function _istFachAuftrag(a) {
    return a.lieferstatus === 'abholbereit' || (a.lieferstatus === 'teilgeliefert' && parseFloat(a.menge_im_fach || 0) > 0);
}

function auftragWaehlen(a) {
    if (auftragInfo(a.id)) {
        ovSchliessen('ov-auftrag-laden');
        feedback('Auftrag ' + a.auftrag_nr + ' ist schon geladen', 'info');
        return;
    }
    var istRetoure = _istRetoureAuftrag(a);

    // Bereits manuell gescannte Artikel (Laufkunde) bleiben erhalten und werden als
    // "weitere Artikel" neben dem geladenen Auftrag geführt. Ist schon ein Auftrag geladen:
    // weiterer Abhol-Auftrag DESSELBEN Kunden kommt dazu (Sammelabholung), alles andere
    // (anderer Kunde, Retoure) ersetzt nach Rückfrage den Bon wie bisher -- Retoure und
    // Sammelabholung auf einem Bon ist (noch) nicht unterstützt.
    if (geladeneAuftraege.length) {
        var dazu = !istRetoure && retourePositionen.length === 0 && _gleicherKunde(geladeneAuftraege[0], a);
        if (dazu) {
            ovSchliessen('ov-auftrag-laden');
            _auftragHinzufuegen(a);
            renderBon();
            feedback('Auftrag ' + a.auftrag_nr + ' dazugeladen — Sammelabholung', 'ok');
            _naechsteMitnehmenFrage();
            return;
        }
        var grund = istRetoure || retourePositionen.length ? '' : ' (anderer Kunde)';
        if (!confirm('Es ist bereits ' + auftragNummernText() + ' geladen' + grund + ' — wirklich durch ' + a.auftrag_nr + ' ersetzen?')) return;
        warenkorb = []; aktiveZeile = -1; globalRabatt = 0; clearNumpad();
        geladeneAuftraege = []; mitnehmenWarteschlange = [];
        retourePositionen = [];
    }
    aktuellerZahlBetrag = null;
    kundeId = a.kunden_id || null;

    _auftragHinzufuegen(a);
    renderBon();
    renderRetoureSektion();
    ovSchliessen('ov-auftrag-laden');

    if (istRetoure) {
        feedback('Auftrag ' + a.auftrag_nr + ' geladen — bereits ausgeliefert. Menge zurück eintragen für die Rückgabe.', 'ok');
        return;
    }
    if (_istFachAuftrag(a)) {
        feedback(a.zahlungsstatus === 'bezahlt'
            ? 'Auftrag ' + a.auftrag_nr + ' geladen — bereits bezahlt · Abholung'
            : 'Auftrag ' + a.auftrag_nr + ' geladen — bereit zur Abholung', 'ok');
    }
    // Erst ggf. "Was passiert mit der Ware?" beantworten, danach nach weiteren
    // Abholungen desselben Kunden schauen (siehe _naechsteMitnehmenFrage)
    weitereAuftraegePruefenFuer = a.id;
    _naechsteMitnehmenFrage();
}

function _auftragHinzufuegen(a) {
    var istRetoure = _istRetoureAuftrag(a);
    geladeneAuftraege.push({
        id: a.id, nr: a.auftrag_nr, status: a.lieferstatus || null,
        mitnehmen: null, zahlungsstatus: a.zahlungsstatus || null,
        kunden_id: a.kunden_id || null, kunden_email: a.kunden_email || null,
        kunden_name: a.kunden_name || '',
        guthaben: parseFloat(a.guthaben || 0), // z.B. Versandkosten nach Umstellung auf Abholung entfallen
    });
    _hauptAuftragSpiegeln();
    if (!kundeId && a.kunden_id) kundeId = a.kunden_id;

    if (istRetoure) {
        a.positionen.forEach(function(p) {
            // 'versendet'/'abgeschlossen' = der ganze Auftrag ist raus (auch wenn menge_geliefert
            // aus einem einfacheren Status-Pfad, z.B. reiner Tracking-Nr.-Eingabe, nie gepflegt
            // wurde) — nur bei echtem 'teilgeliefert' zählt die tatsächlich gelieferte Teilmenge.
            var maxMenge = a.lieferstatus === 'teilgeliefert'
                ? parseFloat(p.menge_geliefert || 0)
                : parseFloat(p.menge);
            // Schon früher über die Kasse retournierte Menge abziehen — sonst könnte
            // dieselbe Position bei einem zweiten Kasse-Besuch nochmal zurückgenommen werden.
            maxMenge -= parseFloat(p.menge_retourniert || 0);
            if (maxMenge <= 0) return; // nichts mehr geliefert bzw. schon vollständig retourniert
            retourePositionen.push({
                artikel_id:          p.artikel_id,
                auftrag_position_id: p.auftrag_position_id || null,
                bezeichnung:         p.bezeichnung,
                ean:                 p.ean || null,
                einzelpreis_brutto:  parseFloat(p.einzelpreis_brutto),
                steuer_prozent:      parseFloat(p.steuer_prozent) || 20,
                rabatt_prozent:      parseFloat(p.rabatt_prozent) || 0,
                charge:              p.charge || null,
                maxMenge:            maxMenge,
                retourMenge:         0,
            });
        });
    } else {
        var fachModus = _istFachAuftrag(a);
        a.positionen.forEach(function(p) {
            // Abholfach: nur was gepackt bereitliegt; teilgeliefert ohne Fach: noch offene Menge
            var menge = fachModus ? parseFloat(p.menge_im_fach !== undefined ? p.menge_im_fach : p.menge)
                      : (a.lieferstatus === 'teilgeliefert' ? parseFloat(p.menge_offen || 0) : parseFloat(p.menge));
            if (menge <= 0 && a.lieferstatus !== 'abholbereit') return;
            warenkorb.push({
                artikel_id:           p.artikel_id,
                bezeichnung:          p.bezeichnung,
                ean:                  p.ean || null,
                menge:                menge,
                original_menge:       menge,
                einzelpreis_brutto:   parseFloat(p.einzelpreis_brutto),
                steuer_prozent:       parseFloat(p.steuer_prozent) || 20,
                rabatt_prozent:       parseFloat(p.rabatt_prozent) || 0,
                charge:               p.charge || null,
                istDivers:            false,
                bestand_physisch:     0,
                bestand_reserviert:   0,
                bestand_verkaufbar:   0,
                vonAuftrag:           true,
                auftrag_position_id:  p.auftrag_position_id || null,
                web_auftrag_id:       a.id,
                artnr:                p.artikelnummer || null,
                charge_pflicht:       !!p.charge_pflicht,
            });
        });
        // Noch nicht gepackte Aufträge: "mitnehmen oder nur zahlen?" fragen
        if (!fachModus) mitnehmenWarteschlange.push(a.id);
    }
    _kundenAnzeigeAuftraege();
}

// Ein Auftrag aus der Sammelabholung wieder vom Bon nehmen (✕ in der Auftrags-Überschrift)
function auftragEntfernen(id) {
    if (geladeneAuftraege.length <= 1) { auftragMitnehmenAbbrechen(); return; }
    warenkorb = warenkorb.filter(function(p) { return zeileAuftragId(p) !== id; });
    geladeneAuftraege = geladeneAuftraege.filter(function(a) { return a.id !== id; });
    mitnehmenWarteschlange = mitnehmenWarteschlange.filter(function(x) { return x !== id; });
    aktiveZeile = -1;
    _hauptAuftragSpiegeln();
    _kundenAnzeigeAuftraege();
    renderBon();
}

// Mitnehmen-Frage nacheinander für jeden noch nicht gepackten Auftrag; ist keiner mehr
// offen, ggf. nach weiteren Abholungen desselben Kunden suchen.
var _mitnehmenFrageFuer = null;
function _naechsteMitnehmenFrage() {
    if (mitnehmenWarteschlange.length) {
        _mitnehmenFrageFuer = mitnehmenWarteschlange[0];
        var a = auftragInfo(_mitnehmenFrageFuer);
        document.getElementById('ov-mitnehmen-info').textContent =
            'Auftrag ' + (a ? a.nr : '') + ' — was passiert mit der Ware?';
        ov('ov-mitnehmen');
        return;
    }
    _mitnehmenFrageFuer = null;
    if (weitereAuftraegePruefenFuer) {
        var refId = weitereAuftraegePruefenFuer;
        weitereAuftraegePruefenFuer = null;
        weitereAbholungenPruefen(refId);
    }
}

function auftragMitnahmeBestaetigen(mitnehmen) {
    var a = auftragInfo(_mitnehmenFrageFuer);
    if (a) a.mitnehmen = mitnehmen;
    _hauptAuftragSpiegeln();
    mitnehmenWarteschlange.shift();
    ovSchliessen('ov-mitnehmen');
    if (a) feedback(
        mitnehmen
            ? 'Auftrag ' + a.nr + ' — Ware wird mitgenommen'
            : 'Auftrag ' + a.nr + ' — nur Zahlung, Versand folgt',
        'ok'
    );
    _naechsteMitnehmenFrage();
}

function auftragMitnehmenAbbrechen() {
    ovSchliessen('ov-mitnehmen');
    // Sammelabholung: nur den gefragten Auftrag wieder herausnehmen
    if (geladeneAuftraege.length > 1 && _mitnehmenFrageFuer) {
        var nr = (auftragInfo(_mitnehmenFrageFuer) || {}).nr || '';
        auftragEntfernen(_mitnehmenFrageFuer);
        feedback('Auftrag ' + nr + ' wieder entfernt', 'info');
        _naechsteMitnehmenFrage();
        return;
    }
    warenkorb = []; aktiveZeile = -1; globalRabatt = 0; clearNumpad();
    geladeneAuftraege = []; mitnehmenWarteschlange = []; weitereAuftraegePruefenFuer = null;
    _hauptAuftragSpiegeln();
    aktuellerZahlBetrag = null; zusatzPositionen = [];
    retourePositionen = [];
    kundeId = null;
    document.getElementById('kunden-anzeige').textContent = '';
    renderBon();
    renderRetoureSektion();
    feedback('Auftrag entladen', 'info');
}

// ── Sammelabholung: weitere offene Abholungen desselben Kunden anbieten ──────
var _weitereAuftraegeDaten = [];
function weitereAbholungenPruefen(refId) {
    fetch('<?= BASE_PATH ?>/kasse/ajax_auftrag_laden.php?weitere_zu=' + refId)
        .then(r => r.json())
        .then(function(data) {
            // Inzwischen anders entschieden (Bon entladen/ersetzt)?
            if (!auftragInfo(refId)) return;
            _weitereAuftraegeDaten = (data || []).filter(function(a) { return !auftragInfo(a.id); });
            if (!_weitereAuftraegeDaten.length) return;

            var ref = auftragInfo(refId);
            document.getElementById('weitere-titel').textContent = '📦 ' + ref.nr + ' geladen';
            document.getElementById('weitere-info').textContent = (ref.kunden_name || 'Dieser Kunde')
                + ' hat noch ' + (_weitereAuftraegeDaten.length === 1 ? 'eine weitere Abholung' : _weitereAuftraegeDaten.length + ' weitere Abholungen') + ':';
            var liste = document.getElementById('weitere-liste');
            liste.innerHTML = '';
            _weitereAuftraegeDaten.forEach(function(a, i) {
                var bereit = a.lieferstatus === 'abholbereit';
                var zeile = document.createElement('label');
                zeile.className = 'weitere-item' + (bereit ? '' : ' weitere-item-offen');
                zeile.innerHTML =
                    '<input type="checkbox" data-idx="' + i + '"' + (bereit ? ' checked' : '') + ' onchange="weitereZaehlen()">' +
                    '<div class="weitere-item-info"><div class="auftrag-item-nr">' + esc(a.auftrag_nr) + '</div>' +
                    '<div class="weitere-item-sub' + (bereit ? '' : ' weitere-item-warn') + '">' + esc(a.erstellt_datum) + ' · '
                        + (bereit ? 'Abholbereit' : 'noch nicht gepackt (' + esc(a.lieferstatus_label) + ')') + '</div></div>' +
                    '<span class="a-chip a-chip-' + esc(a.zahlungsstatus) + '">' + esc(a.zahlungsstatus_label) + '</span>' +
                    '<div class="auftrag-item-betrag">€ ' + fmt(parseFloat(a.bruttobetrag)) + '</div>';
                liste.appendChild(zeile);
            });
            weitereZaehlen();
            ov('ov-weitere-auftraege');
        })
        .catch(function() { /* Hinweis ist optional -- Kasse läuft ohne weiter */ });
}

function weitereZaehlen() {
    var n = document.querySelectorAll('#weitere-liste input:checked').length;
    var btn = document.getElementById('btn-weitere-laden');
    btn.textContent = 'Ausgewählte mitladen (' + n + ')';
    btn.disabled = n === 0;
}

function weitereMitladen() {
    var gewaehlt = Array.from(document.querySelectorAll('#weitere-liste input:checked'))
        .map(function(cb) { return _weitereAuftraegeDaten[parseInt(cb.dataset.idx)]; });
    ovSchliessen('ov-weitere-auftraege');
    gewaehlt.forEach(function(a) { if (!auftragInfo(a.id)) _auftragHinzufuegen(a); });
    renderBon();
    if (gewaehlt.length) feedback(gewaehlt.length + ' weitere(r) Auftrag/Aufträge dazugeladen — Sammelabholung', 'ok');
    _naechsteMitnehmenFrage();
}

// ── Init ──────────────────────────────────────────────────────────────────────
renderBon();
scanInput.focus();
</script>

</body>
</html>
