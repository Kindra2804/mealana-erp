---
name: project-paperless-rechnung-modul
description: "Lizenz-Modul Paperless (99 €): Beleg per QR an der Kasse — Feindesign 2026-11-01 abgenommen, Bau läuft (komplett gebaut 2026-11-01, Klicktest auf beleg.mealana.at offen), eigene Seite beleg.<domain> statt Woo"
metadata: 
  node_type: memory
  type: project
  originSessionId: 1018675b-06b0-4bee-b923-24fdc5ebd59a
  modified: 2026-11-01T15:39:56.247Z
---

## Idee (Jacky, 2026-07-07)

Neues, **extra buchbares** Lizenz-Modul (siehe Lizenzmodell in [[db_design_entscheidungen]]) — Voraussetzung: **mindestens 1 Shop aktiv** (braucht Webspace zum Hosten des Rechnungsinhalts).

**Ablauf:** Rechnungsinhalt wird auf den Webspace geladen, an der Kasse erscheint ein QR-Code (Inhalt = Link auf den jeweiligen Eintrag). Scannt der Kunde den Code:
- Rechnung bleibt **1 Monat** online gespeichert, von dort als A4 herunterladbar.
- Wird der Code **nicht** gescannt, wird der Eintrag nach **24h** automatisch gelöscht (kein Interesse unterstellt).

**Rechtlicher Hintergrund:** Österreich kennt eine Belegerteilungspflicht — das macht diesen QR-Weg zur einzigen Möglichkeit, ganz ohne Papierbeleg auszukommen. Aktuelle Druck-Auswahl an der Kasse ist `Bondruck | A4-Druck | Keiner`. Ist das Modul aktiv, ersetzt der QR-Code die Option `Keiner` (die dann verschwindet); ohne das Modul bleibt alles wie bisher.

## GEKLÄRT (Jacky, 2026-07-07): "Keiner" ist schon heute nicht erlaubt

Bestätigt: die Druckoption `Keiner` verstößt bereits jetzt gegen die Belegerteilungspflicht. **Unkritisch nur solange die Kasse noch nicht produktiv im Einsatz ist** (aktuell der Fall) — muss aber **vor dem ersten produktiven Kasseneinsatz in jedem Fall** ausgeblendet bzw. durch das Paperless-Modul ersetzt werden. Das ist damit ein härterer, zeitkritischerer Punkt als der Rest des Moduls: selbst wenn das volle Paperless-Feature noch nicht gebaut ist, muss `Keiner` spätestens beim Go-Live entfernt werden (Belegerteilungspflicht duldet keine Ausnahme mehr, sobald echte Kunden bedient werden).

**Why:** Barbara/Kunden wollen vermutlich zunehmend papierlose Belege; Lizenzmodell soll das als Zusatzfeature verkaufbar machen, nicht als Standard.

**How to apply:** Zeitliche Einordnung (Jacky, 2026-07-07): Paperless-Rechnung + Kundenanzeige ([[project_kundenanzeige_modul]]) sind beides Kassen-Themen — sollen NACH erfolgreichem BFR-Hardware-Test eingeschoben werden, vorausgesetzt es gibt für die Kasse dann keine weiteren offenen Punkte außer BFR. Das "Keiner"-Ausblenden selbst ist aber ein **Blocker vor dem ersten produktiven Kasseneinsatz**, unabhängig davon ob das volle Modul bis dahin fertig ist — im Zweifel reicht auch ein Minimal-Fix (Option einfach entfernen), bis das Modul selbst dran ist.

## ✅ Feindesign abgenommen (Jacky 2026-11-01) — gilt vor dem alten Konzept oben
- **Rechtslage:** seit 1.10.2026 digitaler Beleg erlaubt, BMF-Pressemeldung 29.09.2026 (https://www.bmf.gv.at/presse/pressemeldungen/2026/september-2026/digitaler-beleg.html): „mit einem Endgerät vor Ort ausgelesen … etwa in Form eines QR-Codes“; Belegerteilungspflicht bleibt, Kontrollschwerpunkt bargeldintensive Branchen. Keine Angaben zu Abrufdauer/Datenschutz.
- **Ablauf Kasse:** nach Zahlung „📱 Beleg per QR“ statt „✕ Ohne Druck fertig“ (das fliegt für ALLE raus, Belegerteilungspflicht). A4-Bon-PDF (mit RKSV-QR) hochladen → QR auf Kundenanzeige bzw. groß im Kassenfenster → Kasse fragt Status ab → „✓ Beleg abgerufen“, sonst „trotzdem drucken“. Offline/Messe → Druck.
- **Hosting (Jacky-Idee):** NICHT WooCommerce (Laufkunden/DSGVO), sondern eigene kleine Seite **beleg.<domain>** (MeaLana: beleg.mealana.at, selber Hoster wie WordPress). DB: SQLite ODER WordPress-DB mit Präfix nahtlos_ (beides in config.php). Logo = Logo der Kasse/des Bons (wird beim Upload mitgeschickt).
- Aufbewahrung: nicht abgerufen 24 h, abgerufen 30 Tage; Aufräumen bei jedem Aufruf (kein Cron).

## Bau
- ✅ Schritt 1 (2026-11-01): Ordner `beleg-portal/` im Repo (index.php Abrufseite, api.php test/upload/status mit Header X-Nahtlos-Schluessel, lib.php, config.example.php, .htaccess sperrt config/lib/daten, robots noindex, README). config.php + daten/* in .gitignore. Lokal mit php -S getestet: falscher Schlüssel, keine PDF, Upload mit Logo, Status vorher/nachher, Abrufseite, Download byte-gleich, Aufräumen 24 h + 30 Tage (29 bleibt, 31 weg).
- ✅ Schritt 2 (2026-11-01): Migration 220 (kassen_bons.paperless_token/_url/_hochgeladen_am/_abgerufen_am, Settings paperless_url/_schluessel); `src/modules/kasse/PaperlessService.php` (verfuegbar = Lizenz + URL + Schlüssel, test, hochladen idempotent mit Logo Shop 1 nur wenn kassen.bon_logo, abgerufen); `BonA4Renderer::pdfSpeichern()` (auch Abholmail); Einstellungen → System → Karte Paperless (🎲 Schlüssel erzeugen, nur bei Neueingabe gespeichert) + einstellungen/paperless_test.php. Test 14/14 gegen lokale Belegseite.
- Jacky 2026-11-01: Belegseite beim Klicktest gleich direkt auf beleg.mealana.at (bleibt für Live); beim Live-Gang nicht leeren nötig (niemand hat die Test-QRs, nach 30 Tagen eh weg).
- ✅ Schritt 3 (2026-11-01): Kasse bon.php — „✕ Ohne Druck fertig“ entfernt (für alle); mit Paperless (Lizenz+Konfig, nicht offline) Knopf „📱 Beleg per QR“ und Auswahl-Dialog IMMER (bei festem Format 80mm/A4 nur dieser Druckknopf + QR; ohne Paperless wie bisher direkt drucken). Overlay ov-beleg-qr: Upload → QR 260px, Status alle 2 s (kasse/ajax_paperless.php aktion=hochladen|status), nach 60 s Hinweis, nach 10 min Polling-Ende; abgerufen → ✓ + Auto-Schließen 2,5 s; kein Schließen ohne Beleg, nur „Trotzdem drucken“/A4. Kundenanzeige: Zustand abrechnen + payload beleg_qr/beleg_abgerufen (keine Migration), nur wenn Setting kundenanzeige_qr_aktiv (umbenannt „Beleg-QR auf der Kundenanzeige zeigen“, Default 0 → beim Klicktest einschalten!); Anzeige bleibt 3 min bzw. 8 s nach ✓. Tests: Playwright-Harness 21/21 + echte Kundenanzeige-Screenshots.
- ✅ Schritt 4 (2026-11-01): Handbücher 11_kasse.md + 09_einstellungen.md + bedienungsanleitung.php (#kasse-paperless).
- **Offen Klicktest (gemeinsam):** Jacky legt Subdomain beleg.mealana.at (HTTPS) an → beleg-portal/ per FTP hoch, config.php (Schlüssel aus ERP 🎲) → ERP Einstellungen → System → Paperless + Verbindung testen + Kundenanzeige-Haken → echter Verkauf an der Kasse mit Handy-Scan; .htaccess-Sperren (config.php, lib.php, daten/) am echten Webspace prüfen. Bleibt so für Live, nicht leeren.

