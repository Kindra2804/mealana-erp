---
name: reference-dev-erp-login
description: Browser-Login fürs Dev-ERP (localhost/mealana) für Claudes Playwright-Tests – Benutzer claude, Passwort in Zugangsdatei
metadata:
  type: reference
---

Dev-ERP im Browser (http://localhost/mealana): Benutzer **claude**. Das Passwort steht in `D:\ERP\mealana\import\zugang Woo Claude.txt` (Abschnitt „DEV-Browser“; dort auch die WP-Zugänge der 3 Shops). Die Datei ist per .gitignore ausgeschlossen. Skripte lesen das Passwort zur Laufzeit aus der Datei und schreiben es nicht ins Skript, Vorlage: `C:\Users\indy1\.claude-browser-tools\mengen_aktion_erp_test.js`. Bekommen 2026-11-14.

Einschränkung: Die Kassen-Seite (kasse/bon.php) braucht einen Arbeitsplatz. Die Hauptkasse ist auf Dev an Jackys Gerät gebunden; eine Übernahme würde seinen Arbeitsplatz abmelden. Kassen-Oberfläche deshalb von Jacky klicktesten lassen. Siehe auch [[reference-browser-testing-tools]].


## Früherer Index-Eintrag (aus MEMORY.md verschoben 2026-11-18)
- [Dev-ERP Browser-Login](reference_dev_erp_login.md) — Benutzer claude, PW in import/zugang Woo Claude.txt; Kasse nicht (Arbeitsplatz gebunden)
