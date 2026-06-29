<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

$svc       = new KassenService();
$kasseInfo = $svc->getKasse(1);
$lagerId   = (int)($kasseInfo['lager_id']      ?? 1);
$kasseId   = (int)($kasseInfo['id']            ?? 1);
$lagerName = $kasseInfo['lager_name']          ?? 'Hauptlager';
$rksvId    = $kasseInfo['rksv_kassen_id']      ?? null;
$modus     = $kasseInfo['modus']               ?? 'online';

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
.ph-rksv   { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #86efac; white-space: nowrap; }
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
.bon-row-separator { font-size: 10px; color: #94a3b8; text-align: center; padding: 4px 0; letter-spacing: .05em; }
.bon-row-auftrag-header { font-size: 11px; color: #3b82f6; padding: 6px 0 2px 6px; font-weight: 700; }
.bon-row-menge { width: 50px; font-size: 13px; color: #1e3a5f; text-align: right; padding: 14px 0; }
.bon-row-ep    { width: 74px; font-size: 12px; color: #475569; text-align: right; padding: 14px 0; }
.bon-row-summe { width: 74px; font-size: 13px; font-weight: 600; color: #1e3a5f; text-align: right; padding: 14px 0; }

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
  <div class="ph-sub">Lager: <?= htmlspecialchars($lagerName) ?></div>
  <div style="font-size:11px;font-weight:700;padding:2px 10px;border-radius:10px;letter-spacing:.4px;white-space:nowrap;
              <?= $modus === 'offline'
                  ? 'background:#451a03;color:#f59e0b;'
                  : 'background:#052e16;color:#4ade80;' ?>">
    <?= $modus === 'offline' ? 'MESSEBETRIEB' : 'ONLINE' ?>
  </div>
  <?php if ($rksvId): ?>
  <div class="ph-rksv">
    <div class="ph-rksv-dot<?= $modus === 'offline' ? ' offline' : '' ?>"></div>
    RKSV <?= $modus === 'offline' ? 'offline' : 'aktiv' ?>
  </div>
  <?php endif; ?>
  <div class="ph-right">
    <button class="ph-btn ph-btn-menu" onclick="toggleMenue(event)">⚙ Menü</button>
    <button class="ph-btn ph-btn-mitgeb" onclick="mitgebenDialog()">Mitgeben ▷</button>
    <button class="ph-btn ph-btn-parken" onclick="bonParken()">⏸ Parken</button>
    <button class="ph-btn ph-btn-close" onclick="location.href='/mealana/kasse/index.php'">✕ Schließen</button>
  </div>
  <!-- Dropdown -->
  <div class="ph-dropdown" id="ph-dropdown">
    <button class="ph-dd-item" onclick="kasseladeOeffnen()">⊟ Kassenlade öffnen</button>
    <button class="ph-dd-item" onclick="diversDialog()">+ Freier Artikel</button>
    <div class="ph-dd-sep"></div>
    <button class="ph-dd-item" onclick="bonAbrufen()">⏸ Geparkten Bon abrufen</button>
    <div class="ph-dd-sep"></div>
    <a class="ph-dd-item" href="/mealana/kasse/kassenbuch.php">💰 Kassenbuch</a>
    <a class="ph-dd-item" href="/mealana/kasse/kassensturz.php">📊 Kassenstand / X-Bon</a>
    <a class="ph-dd-item" href="/mealana/kasse/bon_journal.php">📋 Bon-Journal</a>
    <div class="ph-dd-sep"></div>
    <a class="ph-dd-item" href="/mealana/kasse/offene_auswahl.php">↗ Offene Auswahl (Mitgegeben)</a>
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
             placeholder="🔍  EAN / Artikelnummer scannen…"
             autocomplete="off" spellcheck="false">
      <button type="button" class="ov-btn" onclick="openArtikelSuche()"
          style="height:44px;padding:0 14px;font-size:13px;background:#1e40af;border:none;color:#fff;border-radius:6px;cursor:pointer;white-space:nowrap;flex-shrink:0">
          🔍 Suche
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
      <button class="ov-btn ov-btn-ok" id="btn-bar-ok" onclick="abschliessenBar()" disabled>✓ Abschließen</button>
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
    <div class="ov-title">Gutschein</div>
    <div class="ov-total" id="gs-total">€ 0,00</div>
    <div class="ov-label">Gutschein-Code</div>
    <input class="ov-input-sm" type="text" id="gs-code" placeholder="Code eingeben…"
           style="font-size:20px" onkeyup="gsPruefen()">
    <div id="gs-info" style="min-height:24px;margin-top:8px;font-size:13px;color:#64748b"></div>
    <div class="ov-grid2" style="margin-top:14px">
      <button class="ov-btn ov-btn-ok" id="btn-gs-ok" onclick="abschliessenGS()" disabled>✓ Einlösen</button>
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

<!-- Charge-Auswahl -->
<div class="ov" id="ov-charge">
  <div class="ov-box" style="max-width:540px">
    <div class="ov-title" id="charge-ov-titel">Charge auswählen</div>
    <div id="charge-ov-body" style="max-height:320px;overflow-y:auto;margin-bottom:16px"></div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid #e2e8f0;margin-bottom:12px">
      <span style="font-size:13px;color:#64748b">Menge: <strong id="charge-ov-menge">—</strong></span>
      <span style="font-size:13px">Charge: <strong id="charge-ov-gewaehlt" style="color:#2563eb">—</strong></span>
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
<div id="ov-bezahlt-info" class="overlay">
  <div class="overlay-box" style="max-width:440px">
    <div class="overlay-header">✅ Auftrag bereits bezahlt</div>
    <div style="padding:20px;text-align:center">
      <div id="bezahlt-info-text" style="font-size:15px;margin-bottom:12px;color:#374151"></div>
      <p style="font-size:13px;color:#6b7280;margin-bottom:24px">
        Kein Bon — Status wird auf <strong>Abgeschlossen</strong> gesetzt und eine Bestätigungsmail gesendet.
      </p>
      <div style="display:flex;gap:12px;justify-content:center">
        <button onclick="ovSchliessen('ov-bezahlt-info')" class="btn btn-secondary">Abbrechen</button>
        <button onclick="abschliessenOhneBon()" class="btn btn-primary">✓ Abschließen</button>
      </div>
    </div>
  </div>
</div>

<!-- Retour-Bon: Barauszahlung bestätigen -->
<div id="ov-retour-bar" class="overlay">
  <div class="overlay-box" style="max-width:440px">
    <div class="overlay-header">↩ Rückgabe — Barauszahlung</div>
    <div style="padding:20px;text-align:center">
      <div id="retour-betrag-anzeige" style="font-size:32px;font-weight:700;color:#dc2626;margin-bottom:8px"></div>
      <p id="retour-info-text" style="font-size:13px;color:#6b7280;margin-bottom:24px"></p>
      <div style="display:flex;gap:12px;justify-content:center">
        <button onclick="ovSchliessen('ov-retour-bar')" class="btn btn-secondary">Abbrechen</button>
        <button onclick="retourBestaetigen()" class="btn btn-danger">↩ Auszahlen + Bon</button>
      </div>
    </div>
  </div>
</div>

<!-- Feedback Snackbar -->
<div id="feedback"></div>

<!-- ══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════════════════════════════════════════ -->
<script>
var KASSE_ID  = <?= $kasseId ?>;
var LAGER_ID  = <?= $lagerId ?>;

// ── Zustand ──────────────────────────────────────────────────────────────────
var warenkorb          = [];
var aktiveZeile        = -1;
var globalRabatt       = 0;
var numpadBuf          = '';
var pendingArtikel     = null;
var kundeId            = null;
var geladenerAuftragId       = null;
var geladenerAuftragNr       = null;
var geladenerAuftragStatus   = null;
var geladenerAuftragMitnehmen     = null;
var geladenerAuftragZahlungsstatus = null;
var aktuellerZahlBetrag            = null;
var zusatzPositionen               = [];

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
    fetch('/mealana/kasse/ajax_artikel.php?code=' + encodeURIComponent(btn.dataset.ean || btn.dataset.bezeichnung)
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

    fetch('/mealana/kasse/ajax_artikel.php?code=' + encodeURIComponent(code) + '&lager_id=' + LAGER_ID)
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
            } else {
                artikelHinzufuegen(d);
            }
        })
        .catch(() => feedback('Verbindungsfehler', 'fehler'));
}

// ── Artikel hinzufügen ────────────────────────────────────────────────────────
function artikelHinzufuegen(a) {
    var menge = getMenge();
    var preis = parseFloat(a.brutto_vk) || 0;
    if (preis <= 0 && !a.istDivers) {
        feedback('⚠ Kein Preis für: ' + a.bezeichnung, 'fehler');
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

function _artikelEinfuegen(a, menge) {
    var preis      = parseFloat(a.brutto_vk) || 0;
    var chargeNeu  = a._gewaehltCharge !== undefined ? a._gewaehltCharge : (a.fifo_charge || null);
    // Gleiche Charge: zusammenführen; unterschiedliche Charge: neue Zeile
    var idx = warenkorb.findIndex(p =>
        p.artikel_id == a.id && !p.istDivers && !p.vonAuftrag
        && (p.charge || null) === (chargeNeu || null)
    );
    if (idx >= 0 && a.id) {
        warenkorb[idx].menge += menge;
    } else {
        warenkorb.push({
            artikel_id:                  a.id || null,
            bezeichnung:                 a.bezeichnung,
            ean:                         a.ean || null,
            menge:                       menge,
            einzelpreis_brutto:          preis,
            steuer_prozent:              parseFloat(a.steuer_prozent) || 20,
            rabatt_prozent:              0,
            charge:                      chargeNeu,
            nachzutragen_lagerbestand_id: a._nachtragen_lagerbestand_id || null,
            istDivers:                   !!a.istDivers,
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
    artikelHinzufuegen(kind);
}

// ── Bon rendern ───────────────────────────────────────────────────────────────
function renderBon() {
    var liste = document.getElementById('bon-liste');
    var leer  = document.getElementById('bon-leer');

    if (warenkorb.length === 0) {
        leer.style.display = 'flex';
        // Entferne alle Zeilen außer dem Leer-Div
        Array.from(liste.querySelectorAll('.bon-row')).forEach(r => r.remove());
        document.getElementById('btn-bezahlen').disabled = true;
        aktualisiereFooter();
        return;
    }
    leer.style.display = 'none';

    // Vorhandene Zeilen und Separator entfernen und neu aufbauen
    Array.from(liste.querySelectorAll('.bon-row, .bon-row-separator, .bon-row-auftrag-header')).forEach(r => r.remove());

    var hatAuftrag     = warenkorb.some(p => p.vonAuftrag);
    var hatNormal      = warenkorb.some(p => !p.vonAuftrag);
    var sepGesetzt     = false;
    var hdrGesetzt     = false;

    warenkorb.forEach(function(p, i) {
        // Auftrag-Header über den ersten 📦-Zeilen
        if (p.vonAuftrag && !hdrGesetzt && geladenerAuftragNr) {
            var hdr = document.createElement('div');
            hdr.className = 'bon-row-auftrag-header';
            hdr.innerHTML = '📦 <strong>' + esc(geladenerAuftragNr) + '</strong>';
            liste.appendChild(hdr);
            hdrGesetzt = true;
        }
        // Trennlinie zwischen Auftrag-Block und normalen Artikeln
        if (hatAuftrag && hatNormal && !p.vonAuftrag && !sepGesetzt) {
            var sep = document.createElement('div');
            sep.className = 'bon-row-separator';
            sep.textContent = '─── weitere Artikel ───';
            liste.appendChild(sep);
            sepGesetzt = true;
        }

        var rabFaktor = 1 - (Math.max(p.rabatt_prozent, globalRabatt) / 100);
        var summe = p.menge * p.einzelpreis_brutto * rabFaktor;
        var istAktiv = (i === aktiveZeile);

        var div = document.createElement('div');
        div.className = 'bon-row' + (istAktiv ? ' aktiv' : '') + (p.vonAuftrag ? ' bon-row-auftrag' : '');
        div.dataset.idx = i;
        div.onclick = function() { zeilaKlick(i); };

        var rabHtml = (p.rabatt_prozent > 0 || globalRabatt > 0)
            ? '<span class="bon-row-rabatt">-' + Math.max(p.rabatt_prozent, globalRabatt) + '%</span>'
            : '';
        var auftragBadge = p.vonAuftrag ? '<span class="bon-row-auftrag-badge">📦</span>' : '';

        div.innerHTML =
            '<div class="bon-row-nr">' + (i + 1) + '</div>' +
            '<div class="bon-row-name">' + auftragBadge + esc(p.bezeichnung) + rabHtml + '</div>' +
            '<div class="bon-row-menge">' + p.menge + '</div>' +
            '<div class="bon-row-ep">€ ' + fmt(p.einzelpreis_brutto) + '</div>' +
            '<div class="bon-row-summe">€ ' + fmt(summe) + '</div>' +
            (istAktiv ?
                '<div class="bon-row-ctrl">' +
                '  <button class="bon-ctrl" onclick="event.stopPropagation();zeileMinus(' + i + ')">−</button>' +
                (!p.vonAuftrag ? '  <button class="bon-ctrl" onclick="event.stopPropagation();zeilePlus(' + i + ')">+</button>' : '') +
                '  <button class="bon-ctrl bon-ctrl-preis" onclick="event.stopPropagation();preisOverride(' + i + ')" title="Preis überschreiben (Zahl auf Numpad, dann hier drücken)">€ Preis</button>' +
                '  <span class="bon-ctrl-hint">STORNO-Taste zum Entfernen</span>' +
                '</div>'
                : ''
            );

        liste.appendChild(div);
    });

    document.getElementById('btn-bezahlen').disabled = false;
    aktualisiereFooter();
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
    if (warenkorb[i].menge > 1) {
        warenkorb[i].menge--;
        renderBon();
    } else {
        zeileEntfernen(i);
    }
}
function zeilePlus(i) {
    if (warenkorb[i].vonAuftrag) return;
    warenkorb[i].menge++;
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
        var rab = 1 - Math.max(p.rabatt_prozent, globalRabatt) / 100;
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
}

function getGesamt() {
    var g = 0;
    warenkorb.forEach(function(p) {
        var rab = 1 - Math.max(p.rabatt_prozent, globalRabatt) / 100;
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

// ── Mitgeben ──────────────────────────────────────────────────────────────────
function mitgebenDialog() {
    if (warenkorb.length === 0) { feedback('Bon ist leer', 'info'); return; }
    document.getElementById('mg-name').value  = '';
    document.getElementById('mg-datum').value = '';
    ov('ov-mitgeben');
}
function mitgebenSpeichern() {
    var positionen = warenkorb.filter(p => !p.istDivers && p.artikel_id);
    if (!positionen.length) { feedback('Nur echte Artikel können mitgegeben werden', 'fehler'); return; }
    fetch('/mealana/kasse/offene_auswahl_speichern.php', {
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

// ── Parken ────────────────────────────────────────────────────────────────────
function bonParken() {
    if (warenkorb.length === 0) { feedback('Kein Bon zum Parken', 'info'); return; }
    sessionStorage.setItem('geparkterBon_' + KASSE_ID, JSON.stringify({ warenkorb, globalRabatt }));
    warenkorb = []; aktiveZeile = -1; globalRabatt = 0; clearNumpad(); renderBon();
    feedback('Bon geparkt', 'ok');
    document.getElementById('ph-dropdown').classList.remove('offen');
}
function bonAbrufen() {
    document.getElementById('ph-dropdown').classList.remove('offen');
    var raw = sessionStorage.getItem('geparkterBon_' + KASSE_ID);
    if (!raw) { feedback('Kein geparkter Bon vorhanden', 'info'); return; }
    if (warenkorb.length > 0 && !confirm('Aktuellen Bon verwerfen und geparkten abrufen?')) return;
    var data = JSON.parse(raw);
    warenkorb = data.warenkorb || [];
    globalRabatt = data.globalRabatt || 0;
    aktiveZeile = -1;
    sessionStorage.removeItem('geparkterBon_' + KASSE_ID);
    renderBon();
    feedback('Bon abgerufen', 'ok');
}

// ── Artikel-Suche ─────────────────────────────────────────────────────────────
var suchTimer = null;
function sucheLive(val) {
    clearTimeout(suchTimer);
    var liste = document.getElementById('such-liste');
    if (val.length < 2) { liste.innerHTML = ''; return; }
    suchTimer = setTimeout(function() {
        fetch('/mealana/kasse/ajax_artikel.php?suche=' + encodeURIComponent(val) + '&lager_id=' + LAGER_ID)
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
        fetch('/mealana/kasse/ajax_artikel.php?code=' + encodeURIComponent(a.artikelnummer) + '&lager_id=' + LAGER_ID)
            .then(r => r.json()).then(d => { if (d.erfolg) zeigeVaterAuswahl(d); });
    } else {
        a.bestand_verkaufbar = Math.max(0, (parseFloat(a.bestand_physisch) || 0));
        artikelHinzufuegen(a);
    }
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
        fetch('/mealana/kasse/ajax_kunden_suche.php?suche=' + encodeURIComponent(val))
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
        var rab = 1 - (Math.max(p.rabatt_prozent, globalRabatt) / 100);
        if (p.vonAuftrag) {
            var origMenge = p.original_menge !== undefined ? p.original_menge : p.menge;
            var diff = origMenge - p.menge;
            if (diff > 0.001) retourBrutto += diff * p.einzelpreis_brutto * rab;
        } else {
            extraBrutto += p.menge * p.einzelpreis_brutto * rab;
        }
    });
    var netBrutto = extraBrutto - retourBrutto;
    var modus = (retourBrutto < 0.005 && extraBrutto < 0.005) ? 'exakt'
              : (netBrutto < -0.005)                          ? 'retour'
              :                                                  'extra';
    return { modus: modus, extraBrutto: extraBrutto, retourBrutto: retourBrutto, netBrutto: netBrutto };
}

function berechneZusatzPositionen() {
    zusatzPositionen = [];
    warenkorb.forEach(function(p) {
        if (!p.vonAuftrag) return;
        var origMenge = p.original_menge !== undefined ? p.original_menge : p.menge;
        var diff = origMenge - p.menge;
        if (diff < 0.001) return;
        zusatzPositionen.push({
            artikel_id: p.artikel_id, bezeichnung: p.bezeichnung, ean: p.ean || null,
            menge: -diff, einzelpreis_brutto: p.einzelpreis_brutto,
            steuer_prozent: p.steuer_prozent,
            rabatt_prozent: Math.max(p.rabatt_prozent, globalRabatt),
            charge: p.charge || null, istDivers: false,
            vonAuftrag: false, auftrag_position_id: null,
            kein_lagerabzug: true, block: 'retour',
        });
    });
}

function bezahlenDialog() {
    if (warenkorb.length === 0) return;

    if (geladenerAuftragZahlungsstatus === 'bezahlt' && geladenerAuftragId) {
        var m = berechneAbrechnungsModus();
        aktuellerZahlBetrag = m.netBrutto;

        if (m.modus === 'exakt') {
            var origTotal = 0;
            warenkorb.forEach(function(p) {
                if (p.vonAuftrag) {
                    var orig = p.original_menge !== undefined ? p.original_menge : p.menge;
                    origTotal += orig * p.einzelpreis_brutto * (1 - Math.max(p.rabatt_prozent, globalRabatt) / 100);
                }
            });
            document.getElementById('bezahlt-info-text').textContent =
                'Auftrag ' + geladenerAuftragNr + ' · € ' + fmt(origTotal) + ' — vollständig bezahlt.';
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
        ov('ov-bezahlen');
        return;
    }

    var g = getGesamt();
    aktuellerZahlBetrag = g;
    document.getElementById('bez-total').textContent = '€ ' + fmt(g);
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
    if (geg >= g) {
        el.textContent = 'Rückgeld: € ' + fmt(rueck);
        el.style.color = '#16a34a';
        document.getElementById('btn-bar-ok').disabled = false;
    } else {
        el.textContent = rueck < 0 ? 'Fehlend: € ' + fmt(Math.abs(rueck)) : '';
        el.style.color = '#dc2626';
        document.getElementById('btn-bar-ok').disabled = true;
    }
}
function abschliessenBar() {
    var g = _zahlBetrag();
    var geg = parseFloat(document.getElementById('bar-gegeben').value) || 0;
    if (geg < g && g > 0.005) return;
    bonSpeichern({ zahlungsart: 'bar', gegeben: geg, rueckgeld: Math.max(0, geg - g) });
}

function zahlenKarte() {
    ovSchliessen('ov-bezahlen');
    document.getElementById('karte-total').textContent = '€ ' + fmt(_zahlBetrag());
    ov('ov-karte');
}
function abschliessenKarte() { bonSpeichern({ zahlungsart: 'karte_extern' }); }

function zahlenGutschein() {
    ovSchliessen('ov-bezahlen');
    document.getElementById('gs-total').textContent = '€ ' + fmt(_zahlBetrag());
    document.getElementById('gs-code').value = '';
    document.getElementById('gs-info').textContent = '';
    document.getElementById('btn-gs-ok').disabled = true;
    ov('ov-gutschein');
    setTimeout(() => document.getElementById('gs-code').focus(), 100);
}
function gsPruefen() {
    var code = document.getElementById('gs-code').value.trim();
    document.getElementById('btn-gs-ok').disabled = code.length < 3;
}
function abschliessenGS() {
    var code = document.getElementById('gs-code').value.trim();
    if (!code) return;
    bonSpeichern({ zahlungsart: 'gutschein', gutschein_code: code });
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
    geladenerAuftragId = null; geladenerAuftragNr = null;
    geladenerAuftragStatus = null; geladenerAuftragMitnehmen = null;
    geladenerAuftragZahlungsstatus = null; aktuellerZahlBetrag = null; zusatzPositionen = [];
    document.getElementById('btn-auftrag-laden').classList.remove('geladen');
    document.getElementById('ai-leer').style.display = 'block';
    document.getElementById('ai-inhalt').style.display = 'none';
    document.getElementById('kunden-anzeige').textContent = 'Laufkunde';
    renderBon();
}

function bonSpeichern(zahlDaten) {
    ['ov-bar','ov-karte','ov-gutschein','ov-kombi'].forEach(ovSchliessen);
    document.getElementById('spinner').classList.add('offen');

    var g = _zahlBetrag();
    var positionen = warenkorb.map(function(p) {
        return Object.assign({}, p, { rabatt_prozent: Math.max(p.rabatt_prozent, globalRabatt) });
    }).concat(zusatzPositionen);
    var zp = zusatzPositionen.slice(); // Kopie vor Reset
    zusatzPositionen = [];

    fetch('/mealana/kasse/bon_speichern.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({
            kasse_id: KASSE_ID,
            lager_id: LAGER_ID,
            kunden_id: kundeId,
            bruttobetrag: g,
            positionen: positionen,
            web_auftrag_id:              geladenerAuftragId,
            web_auftrag_status:          geladenerAuftragStatus,
            web_auftrag_mitnehmen:       geladenerAuftragMitnehmen,
            web_auftrag_zahlungsstatus:  geladenerAuftragZahlungsstatus,
        }, zahlDaten))
    })
    .then(r => r.json())
    .then(function(d) {
        document.getElementById('spinner').classList.remove('offen');
        if (d.erfolg) {
            _resetKasseState();
            if (d.bon_id) {
                window.open('/mealana/kasse/bon_druck.php?id=' + d.bon_id, '_blank');
            }
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
        return Object.assign({}, p, { rabatt_prozent: Math.max(p.rabatt_prozent, globalRabatt) });
    });
    fetch('/mealana/kasse/bon_speichern.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            kasse_id: KASSE_ID, lager_id: LAGER_ID, kunden_id: kundeId, bruttobetrag: 0,
            zahlungsart: 'bar', positionen: positionen,
            web_auftrag_id:             geladenerAuftragId,
            web_auftrag_status:         geladenerAuftragStatus,
            web_auftrag_mitnehmen:      geladenerAuftragMitnehmen,
            web_auftrag_zahlungsstatus: geladenerAuftragZahlungsstatus,
            nur_abschliessen:           true,
        })
    })
    .then(r => r.json())
    .then(function(d) {
        document.getElementById('spinner').classList.remove('offen');
        if (d.erfolg) {
            _resetKasseState();
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
    bonSpeichern({ zahlungsart: 'bar', gegeben: 0, rueckgeld: 0 });
}

// ── Charge-Auswahl (Kasse) ───────────────────────────────────────────────────
var kasseChargePendingArtikel = null;
var kasseChargePendingMenge   = 1;
var kasseChargeGewaehlt       = null; // {charge, lagerbestand_id, nachtragen}

function zeigeKasseChargePopup(a, menge) {
    kasseChargePendingArtikel = a;
    kasseChargePendingMenge   = menge;
    kasseChargeGewaehlt       = null;

    document.getElementById('charge-ov-titel').textContent = 'Charge — ' + a.bezeichnung;
    document.getElementById('charge-ov-menge').textContent = menge + ' Stk.';
    document.getElementById('charge-ov-gewaehlt').textContent = '—';
    document.getElementById('btn-charge-kasse-ok').disabled = true;

    var chargen = a.alle_chargen || [];
    var body    = document.getElementById('charge-ov-body');
    body.innerHTML = '';

    if (chargen.length === 0) {
        body.innerHTML = '<p style="color:#64748b;font-size:13px">Keine Chargen im Lager vorhanden.</p>';
        if (a.charge_pflicht) {
            body.innerHTML += '<p style="color:#dc2626;font-size:12px">Charge-Pflicht: bitte zuerst Charge im Wareneingang eintragen.</p>';
        }
    } else {
        var html = '<table style="width:100%;border-collapse:collapse">';
        html += '<thead><tr style="font-size:12px;color:#94a3b8"><th style="text-align:left;padding:6px 8px">Charge</th><th style="text-align:right;padding:6px 8px">Bestand</th><th style="text-align:center;padding:6px 8px">Wählen</th></tr></thead><tbody>';

        chargen.forEach(function(c, i) {
            var isNt  = c.charge_status === 'nachzutragen';
            var rowId = 'kc-row-' + i;
            html += '<tr id="' + rowId + '" style="border-top:1px solid #e2e8f0;cursor:pointer" onclick="kasseChargeWaehlen(' + i + ')">';
            if (isNt) {
                html += '<td style="padding:8px"><input type="text" id="kc-name-' + i + '" class="bon-input" style="width:130px;padding:4px 8px" placeholder="Chargennummer" onclick="event.stopPropagation()" oninput="kasseChargeNtInput(' + i + ',' + c.id + ')"></td>';
            } else {
                html += '<td style="padding:8px;font-family:monospace;font-size:13px">' + esc(c.charge) + '</td>';
            }
            html += '<td style="text-align:right;padding:8px;color:#94a3b8">' + parseFloat(c.bestand).toFixed(0) + '</td>';
            html += '<td style="text-align:center;padding:8px"><div id="kc-sel-' + i + '" style="width:22px;height:22px;border-radius:50%;border:2px solid #cbd5e1;margin:auto"></div></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';

        // Kein-Charge Option (nur wenn nicht charge_pflicht)
        if (!a.charge_pflicht) {
            html += '<div style="margin-top:12px;padding:8px;border-top:1px solid #e2e8f0">';
            html += '<button class="ov-btn ov-btn-sec" style="width:100%;font-size:12px" onclick="kasseChargeOhne()">Ohne Charge buchen</button>';
            html += '</div>';
        }

        body.innerHTML = html;

        // FIFO-Charge vorselektieren wenn vorhanden
        if (a.fifo_charge) {
            var fifoIdx = chargen.findIndex(function(c) { return c.charge === a.fifo_charge; });
            if (fifoIdx >= 0) kasseChargeWaehlen(fifoIdx);
        }
    }

    ov('ov-charge');
}

function kasseChargeWaehlen(idx) {
    var chargen = kasseChargePendingArtikel ? (kasseChargePendingArtikel.alle_chargen || []) : [];
    var c = chargen[idx];
    if (!c) return;
    if (c.charge_status === 'nachzutragen') {
        // Für nachzutragen: Name aus Input holen
        kasseChargeNtInput(idx, c.id);
        return;
    }

    // Markierung setzen
    chargen.forEach(function(_, i) {
        var sel = document.getElementById('kc-sel-' + i);
        if (sel) sel.style.cssText = 'width:22px;height:22px;border-radius:50%;border:2px solid #cbd5e1;margin:auto';
    });
    var selEl = document.getElementById('kc-sel-' + idx);
    if (selEl) selEl.style.cssText = 'width:22px;height:22px;border-radius:50%;border:2px solid #2563eb;background:#2563eb;margin:auto';

    kasseChargeGewaehlt = { charge: c.charge, lagerbestand_id: c.id, nachtragen: false };
    document.getElementById('charge-ov-gewaehlt').textContent = c.charge;
    document.getElementById('btn-charge-kasse-ok').disabled = false;
}

function kasseChargeNtInput(idx, lbId) {
    var input = document.getElementById('kc-name-' + idx);
    var name  = input ? input.value.trim() : '';

    // Markierung
    var chargen = kasseChargePendingArtikel ? (kasseChargePendingArtikel.alle_chargen || []) : [];
    chargen.forEach(function(_, i) {
        var sel = document.getElementById('kc-sel-' + i);
        if (sel) sel.style.cssText = 'width:22px;height:22px;border-radius:50%;border:2px solid #cbd5e1;margin:auto';
    });
    var selEl = document.getElementById('kc-sel-' + idx);

    if (name) {
        if (selEl) selEl.style.cssText = 'width:22px;height:22px;border-radius:50%;border:2px solid #2563eb;background:#2563eb;margin:auto';
        kasseChargeGewaehlt = { charge: name, lagerbestand_id: lbId, nachtragen: true };
        document.getElementById('charge-ov-gewaehlt').textContent = name + ' (neu)';
        document.getElementById('btn-charge-kasse-ok').disabled = false;
    } else {
        if (selEl) selEl.style.cssText = 'width:22px;height:22px;border-radius:50%;border:2px solid #cbd5e1;margin:auto';
        kasseChargeGewaehlt = null;
        document.getElementById('charge-ov-gewaehlt').textContent = '—';
        document.getElementById('btn-charge-kasse-ok').disabled = true;
    }
}

function kasseChargeOhne() {
    kasseChargeGewaehlt = { charge: null, lagerbestand_id: null, nachtragen: false };
    ovSchliessen('ov-charge');
    var a = Object.assign({}, kasseChargePendingArtikel, { _gewaehltCharge: null });
    _fortsetzungNachChargeAuswahl(a, kasseChargePendingMenge);
}

function chargeKasseBestaetigen() {
    if (!kasseChargeGewaehlt) return;
    ovSchliessen('ov-charge');
    var a = Object.assign({}, kasseChargePendingArtikel, {
        _gewaehltCharge:          kasseChargeGewaehlt.charge,
        _nachtragen_lagerbestand_id: kasseChargeGewaehlt.nachtragen ? kasseChargeGewaehlt.lagerbestand_id : null,
    });
    _fortsetzungNachChargeAuswahl(a, kasseChargePendingMenge);
}

function chargeKasseAbbrechen() {
    ovSchliessen('ov-charge');
    kasseChargePendingArtikel = null;
    kasseChargeGewaehlt       = null;
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
        fetch('/mealana/kasse/ajax_artikel.php?suche=' + encodeURIComponent(val) + '&lager_id=' + LAGER_ID)
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
    document.getElementById('btn-bar-ok').disabled = true;
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
    fetch('/mealana/kasse/ajax_kassenlade.php', { method: 'POST' })
        .then(r => r.json())
        .then(d => { feedback(d.hinweis || '⊟ Kassenlade geöffnet', 'ok'); })
        .catch(() => feedback('⊟ Kassenlade-Befehl gesendet', 'ok'));
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

    fetch('/mealana/kasse/ajax_auftrag_laden.php?q=' + encodeURIComponent(q) + '&alle=' + alle)
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
                    var d = this._auftragDaten;
                    auftragWaehlen(d.id, d.auftrag_nr, d.positionen, d.lieferstatus, d.kunden_id, d.kunden_name, d.zahlungsstatus);
                });
                liste.appendChild(div);
            });
        })
        .catch(function() {
            liste.innerHTML = '<div style="padding:24px;text-align:center;color:#dc2626;font-size:13px">Fehler beim Laden</div>';
        });
}

function auftragWaehlen(id, nr, positionen, lieferstatus, kunden_id, kunden_name, zahlungsstatus) {
    if (warenkorb.length > 0) {
        if (!confirm('Aktueller Bon hat ' + warenkorb.length + ' Position(en) — wirklich ersetzen?')) return;
        warenkorb = []; aktiveZeile = -1; globalRabatt = 0; clearNumpad();
    }
    geladenerAuftragId              = id;
    geladenerAuftragNr              = nr;
    geladenerAuftragStatus          = lieferstatus || null;
    geladenerAuftragMitnehmen       = null;
    geladenerAuftragZahlungsstatus  = zahlungsstatus || null;
    aktuellerZahlBetrag             = null;
    kundeId = kunden_id || null;

    positionen.forEach(function(p) {
        var menge = parseFloat(p.menge);
        warenkorb.push({
            artikel_id:           p.artikel_id,
            bezeichnung:          p.bezeichnung,
            ean:                  p.ean || null,
            menge:                menge,
            original_menge:       menge,
            einzelpreis_brutto:   parseFloat(p.einzelpreis_brutto),
            steuer_prozent:       parseFloat(p.steuer_prozent) || 20,
            rabatt_prozent:       parseFloat(p.rabatt_prozent) || 0,
            charge:               null,
            istDivers:            false,
            bestand_physisch:     0,
            bestand_reserviert:   0,
            bestand_verkaufbar:   0,
            vonAuftrag:           true,
            auftrag_position_id:  p.auftrag_position_id || null,
        });
    });
    document.getElementById('kunden-anzeige').textContent = '📦 ' + nr + (kunden_name ? ' · ' + kunden_name : '');
    document.getElementById('btn-auftrag-laden').classList.add('geladen');
    renderBon();
    ovSchliessen('ov-auftrag-laden');

    if (lieferstatus === 'abholbereit') {
        geladenerAuftragMitnehmen = null;
        var msg = zahlungsstatus === 'bezahlt'
            ? 'Auftrag ' + nr + ' geladen — bereits bezahlt · Abholung'
            : 'Auftrag ' + nr + ' geladen — bereit zur Abholung';
        feedback(msg, 'ok');
    } else {
        document.getElementById('ov-mitnehmen-info').textContent =
            'Auftrag ' + nr + ' — was passiert mit der Ware?';
        ov('ov-mitnehmen');
    }
}

function auftragMitnahmeBestaetigen(mitnehmen) {
    geladenerAuftragMitnehmen = mitnehmen;
    ovSchliessen('ov-mitnehmen');
    feedback(
        mitnehmen
            ? 'Auftrag ' + geladenerAuftragNr + ' — Ware wird mitgenommen'
            : 'Auftrag ' + geladenerAuftragNr + ' — nur Zahlung, Versand folgt',
        'ok'
    );
}

function auftragMitnehmenAbbrechen() {
    ovSchliessen('ov-mitnehmen');
    warenkorb = []; aktiveZeile = -1; globalRabatt = 0; clearNumpad();
    geladenerAuftragId = null; geladenerAuftragNr = null;
    geladenerAuftragStatus = null; geladenerAuftragMitnehmen = null;
    geladenerAuftragZahlungsstatus = null; aktuellerZahlBetrag = null; zusatzPositionen = [];
    kundeId = null;
    document.getElementById('kunden-anzeige').textContent = '';
    document.getElementById('btn-auftrag-laden').classList.remove('geladen');
    renderBon();
    feedback('Auftrag entladen', 'info');
}

// ── Init ──────────────────────────────────────────────────────────────────────
renderBon();
scanInput.focus();
</script>

</body>
</html>
