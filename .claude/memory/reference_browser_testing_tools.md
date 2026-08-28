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
