---
name: reference-browser-testing-tools
description: Node.js + Playwright lokal installiert für echte Browser-Tests von UI-Änderungen (nicht nur curl/PHP-Checks)
metadata: 
  node_type: memory
  type: reference
  originSessionId: ef5ab404-461a-4211-ab17-449670c5039a
  modified: 2026-08-28T09:44:22.261Z
---

Node.js LTS (via winget) + Playwright + Chromium sind auf Jackys Dev-PC installiert unter `C:\Users\indy1\.claude-browser-tools\` (eigenes `npm`-Projekt, getrennt vom `mealana`-Repo).

**Verwendung:** Ad-hoc-Testskript in diesem Verzeichnis schreiben (`require('playwright')`, `chromium.launch()`), dann:
```
Set-Location "C:\Users\indy1\.claude-browser-tools"
node <script>.js
```
Login-Formular des ERP: `input[name="username"]` / `input[name="passwort"]` auf `login.php`.

**Warum angelegt (2026-08-28):** `chromium-cli` (das im `run`-Skill referenzierte Standard-Tool) ist auf diesem Windows-Dev-PC nicht verfügbar — kein Node/npm vorhanden, kein Container. Auf Jackys Wunsch installiert, um echte JS-Interaktionen (nicht nur PHP-Rendering per curl) automatisiert prüfen zu können. Erster Einsatz fand direkt einen echten Bug (Hinweistext-Reset in `achsen_zuweisen.js`, siehe [[project_konfigurator_modul]]).

**Aufräumen:** Screenshots/Testskripte im Tools-Verzeichnis nach jedem Testlauf löschen (nur `node_modules`/`package.json`/`package-lock.json` bleiben dauerhaft) — sonst sammeln sich verwaiste Artefakte an, siehe [[feedback_test_isolation]].

**PDF ansehen (2026-09-30):** `node pdf_zu_png.js <pdf> <png> [skalierung]` im Tools-Verzeichnis rendert Seite 1 eines echten Dompdf-PDFs per pdf.js (npm `pdfjs-dist@3.11.174`) zu PNG — dauerhaftes Werkzeug, NICHT löschen. Wichtig: Browser-Vorschau von Twig-HTML ≠ Dompdf-Ergebnis (andere Schriftgrößen/Layout, z.B. Barcode-Überlappung beim Gutschein) — Dokumente immer am echten PDF prüfen.
**Falle:** `npm install <paket>` räumt nicht in package.json eingetragene Pakete weg — Playwright war nicht eingetragen und verschwand. Jetzt fest auf `playwright: 1.62.0` gepinnt, weil nur Chromium-Build 1234 unter `%LOCALAPPDATA%\ms-playwright` liegt (1.63 will 1243 → Download nötig).
**Falle Formular-Submit (2026-10-02):** `Promise.all([page.waitForLoadState('load'), btn.click()])` wartet NICHT auf die neue Seite (löst sofort auf, weil die aktuelle schon geladen ist) — ein direkt folgendes `goto` bricht den POST ab, Cookies fehlen → Scheinfehler "Warenkorb leer". Stattdessen `waitForResponse(r => r.request().method()==='POST' && ...)` bzw. `waitForURL`.
