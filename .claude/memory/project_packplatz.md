---
name: project-packplatz
description: "Packplatz-Modul: Was fertig ist und was noch offen bleibt"
metadata:
  node_type: memory
  type: project
  originSessionId: 3bbaa246-5729-4e26-8fb8-4785822652ed
---

## ✅ Fertig (2026-06-25)

- `public/packplatz/` — eigene Shell (Dark-UI, kein ERP-Sidebar, tablet-optimiert)
- Hauptmenü: 4 Kacheln (Warenausgang / Wareneingang → existierendes WE-Modul / Intern disabled / Retoure disabled)
- **Warenausgang vollständig:**
  - `warenausgang/index.php`: offene Picklisten + Auftrag-Direktauswahl
  - `warenausgang/scan.php`: EAN-Scan, Vorwahl-Menge, Artikelbild, Grün/Rot-Feedback
  - Overlay: Gewicht eingeben (vorausgefüllt aus Artikelgewichten) → Tracking scannen
  - `warenausgang/abschliessen.php`: Status-Update, EasyPak-XML, Versandmail, Pickliste-Abschluss
- `src/core/EasyPakExporter.php`: XML für PLC-Ordner (Österreichische Post)
  - AT/EU/International Item-IDs, Nachnahme-Support, ISO-8859-1
  - Polling-Ordner konfigurierbar in Einstellungen → System (`plc_polling_ordner`)
- `templates/mails/versandbestaetigung.html.twig`: inkl. Post-Tracking-Link

## ✅ Neu fertig (2026-06-26)

- **EAN Nachtragen** im WE-Detail: orange "+ EAN"-Button für Artikel ohne EAN → Modal → AJAX → `ean_nachtragen.php`
- **Pickliste neues Fenster**: nach Erstellen → `picklisten.php?neu=X` → `window.open()` PDF in neuem Tab
- **Packplatz/Intern** (`intern/index.php`):
  - EAN/Artikelnummer-Scan → Lagerstand-Übersicht
  - Lagerumbuchung: Von/Zu-Lager-Dropdown + Menge → `umbuchen.php` → `LagerService::umbucheZwischenLager()`
  - Zustandsumbuchung: falls Zustandsartikel vorhanden → `zustand_umbuchen.php` (Ausgang Neu, Eingang Zustandsartikel)
- **Packplatz/Retoure** (`retoure/`):
  - `index.php`: Auftragsnummer scannen / zuletzt versendete Aufträge
  - `detail.php`: Positionen mit Checkbox + Menge + Zustand-select, Lager-select, Ergebnis (GS/Ersatz/nur_einbuchen)
  - `speichern.php`: Lager einbuchen + optionale GS via DokumentService + Mail via retoure.html.twig
  - Mail-Template: `templates/mails/retoure.html.twig`
- **LagerService**: neue Methode `umbucheZwischenLager()`

## 🔴 Bugfixes (Session 2026-06-29)

- Picklisten-Anzeige: nach Verpacken+Tracking ausblenden, nach Verpacken ohne Tracking → nur Tracking-Eingabe (kein Scan)
- Navigation: nach Abschluss immer → index.php (nicht auf nächste scan.php)
- Refresh-Button (Touchscreen) + Auto-Refresh (Page Visibility API) auf index.php
- Loader-Overlay in Packplatz-Shell einbauen
- **Teilgeliefert-Workflow (ERP, nicht Packplatz):** Im ERP unter Lager/Picklisten müssen für Aufträge mit lieferstatus='teilgeliefert' neue Picklisten für die noch offenen Positionen erstellt werden können (nur Restmengen drauf). → Noch nicht angegangen.
- **Chargen-Bug:** Zieht sich durch Wareneingang, Lagerabbuchung, Packplatz, Inventur — wird als eigener großer Bugfix-Zyklus separat behandelt.

## 🔴 Noch offen (korrigiert 2026-07-03 — B, C, E waren stale, siehe unten)

### ✅ BUG behoben 2026-07-03: Erledigte Teillieferungs-Aufträge blieben in der Picklisten-Liste

War: `lager/picklisten.php` (`$auftraegeRaw`) zeigte Aufträge mit `lieferstatus='teilgeliefert'` weiter an, auch wenn inzwischen alle Positionen ausgeliefert waren (0 offene Menge) — nichts schaltete den Status automatisch weiter, die Lieferbarkeits-Prüfung wertete 0-von-0-Positionen fälschlich als "✓ Vollständig lieferbar".
**Fix in `packplatz/warenausgang/abschliessen.php`:** nach der Mengenbuchung wird jetzt geprüft, ob wirklich noch offene Positionen übrig sind (`SELECT COUNT(*) ... WHERE menge - menge_geliefert > 0`). Ist das nicht der Fall, wird die vom Formular übergebene `teillieferung`-Markierung serverseitig auf `false` korrigiert — unabhängig davon, welcher Button (Verpacken/Teillieferung) geklickt wurde. Wirkt sich automatisch auch auf Statuslog-Text, Rechnung-vs-Lieferschein-Entscheidung und Mailtext aus, da all diese Stellen dieselbe `$istTeillieferung`-Variable nutzen.
Bestehende Daten geprüft (2026-07-03): keine Aufträge aktuell betroffen, reiner Vorsorge-Fix für künftige Fälle.
Dashboard (`$fehlbestandAuftraege`) war durch einen zufälligen `$total > 0`-Schutz nie betroffen — dort war nichts zu fixen.

### ✅ Geprüft 2026-07-03: Teillieferung-Status-Logik ist ein bewusster Doppel-Button-Flow, kein Checkbox-Risiko
`packplatz_scan.js`: "Verpacken abschließen" und "Teillieferung" sind zwei getrennte Buttons/Overlays (`verpackenAbschliessen(istTeillieferung)`), kein Toggle der versehentlich angehakt bleiben könnte. Der obige Fix ist trotzdem sinnvoll als Sicherheitsnetz für Grenzfälle (Packer weiß nicht ob noch mehr kommt, klickt aber zufällig die letzte Resteinheit ab).

### ✅ BUG #2 behoben 2026-07-03: "Offene Picklisten" zeigte 15 längst erledigte Picklisten an
Nachdem der Bug oben behoben war, meldete Jacky dass `lager/picklisten.php` (rechte Spalte) und das Dashboard ("Picklisten offen: 16") weiterhin viel zu viele offene Picklisten zeigten. Ursache: **zwei Wege zum selben Auftrag** auf `packplatz/warenausgang/index.php` — "📋 Pickliste öffnen" (setzt `pickliste_id` korrekt) vs. "📦 Auftrag direkt verpacken" (`scan.php?modus=auftrag`, setzte `$pickliste = null` **immer**, auch wenn der Auftrag tatsächlich zu einer offenen Pickliste gehörte). Wurde ein Auftrag über den zweiten Weg fertig verpackt, lief der Auto-Abschluss-Check in `abschliessen.php` nie, weil `pickliste_id` leer ankam — die Pickliste blieb für immer auf `gedruckt` hängen. Bestätigt per DB-Abfrage: alle 15 betroffenen Picklisten hatten genau 1 Auftrag, dessen `lieferstatus` bereits terminal war (versendet/abholbereit/abgeschlossen).

**Fix (zwei Stellen):**
1. `packplatz/warenausgang/scan.php`: im `auftrag`-Modus wird jetzt aktiv geprüft, ob der Auftrag zu einer offenen/gedruckten Pickliste gehört, und `$pickliste` entsprechend automatisch gesetzt — unabhängig vom Einstiegsweg.
2. `packplatz/warenausgang/index.php`: die "Direktauswahl"-Liste ("für Artikel die nicht auf Picklisten kommen") schließt jetzt Aufträge aus, die bereits auf einer offenen Pickliste stehen — passend zum eigentlichen Zweck dieser Liste.
3. **Einmalige Datenbereinigung** ausgeführt: 15 bereits betroffene Picklisten (IDs 7-15, 19-24) auf `abgeschlossen` gesetzt, nachdem verifiziert wurde dass alle zugehörigen Aufträge tatsächlich fertig waren. Danach: nur noch 1 echte offene Pickliste übrig (PL-2026-00016).

### ✅ A — Picklisten-Manager — WAR STALE, TATSÄCHLICH SCHON GEBAUT (verifiziert 2026-07-09)
`erp/public/lager/picklisten.php` existiert bereits vollständig: Greedy-Zuteilung ältester-Auftrag-zuerst, Voll/Teilweise/Nicht-lieferbar-Badges, Checkbox-Override (Abwählen → beim nächsten Aufruf neu berechnet), aufklappbare Lagerstand-Übersicht (Ist/Reserviert/Verfügbar pro Artikel), PDF-Erstellung mit Barcode (`pickliste_pdf.php`), offene + abgeschlossene Picklisten-Liste. Diese Notiz war seit mindestens 2026-07-05 falsch als offen markiert.
Jacky erinnert sich, dass noch eine konkrete Sache reinsollte, weiß aber nicht mehr genau was — **offen gelassen bis es ihm beim Benutzen wieder auffällt**, nicht spekulativ nachgebaut.

### ✅ D (Teil 1) — EAN nacherfassen beim Picken — FERTIG (2026-07-09)
Doppelklick auf die EAN-Zelle bzw. Klick auf "⚠ Kein EAN — nachtragen" öffnet ein dunkles Overlay (`overlay-ean` in `warenausgang/scan.php`), speichert über den bestehenden `packplatz/wareneingang/ean_nachtragen.php`-Endpoint (wiederverwendet, artikel_id-basiert, kein Wareneingang-spezifischer State). Aktualisiert sofort `POSITIONEN[idx].ean` im Browser-Speicher, damit der frisch erfasste Code direkt weitergescannt werden kann, ohne die Seite neu zu laden.

**🔴 Dabei gefundener, unabhängiger Bug (mitgefixt):** `packplatz/shell_top.php` setzte nie `window.BASE_PATH` (im Gegensatz zur normalen ERP-Shell). `packplatz_scan.js` nutzte das aber an zwei bestehenden Stellen bereits — die Chargen-Auswahl-Abfrage (`chargen_ajax.php`, Zeile ~249) ist `await`ed mit try/catch-Fallback auf "ohne Charge buchen bei jedem Fehler" → die Chargen-Auswahl beim Picken hat dadurch vermutlich **noch nie funktioniert**, fiel still auf ungetrackte Buchung zurück. Die zweite Stelle (Kommissioniert-Status vorab speichern) ist harmloses Fire-and-Forget, fiel nur lautlos durch. Fix: `window.BASE_PATH = <?= json_encode(BASE_PATH) ?>;` in `packplatz/shell_top.php` ergänzt (gleiche Zeile wie in der normalen Shell). Betrifft nur diese eine JS-Datei (verifiziert per Grep über alle Packplatz-JS-Dateien).
**Noch zu verifizieren:** Jacky sollte beim nächsten echten Picking-Vorgang mit einem chargenpflichtigen Artikel testen, ob das Chargen-Popup jetzt tatsächlich erscheint.

### ✅ D (Teil 1) Doku nachgezogen 2026-07-10
War im Code fertig, fehlte aber komplett in `docs/handbuch/05_packplatz.md` und `bedienungsanleitung.php` — beide jetzt um die Doppelklick-EAN-Nacherfassung ergänzt.

### ✅ Verifiziert 2026-07-10: "Kein neue Pickliste für den Rest bei Teillieferung" — bereits korrekt, kein Bug
Jacky erinnerte sich vage an ein Problem (dieselbe Erinnerung, die am 2026-07-09 als "irgendwas fehlt noch, weiß nicht mehr was" offen gelassen wurde). Nachgestellt: isolierten Test-Auftrag mit Teillieferung (6 von 10 geliefert) auf einer Pickliste simuliert, geprüft ob `abschliessen.php`s Pickliste-Abschluss-Logik (`lieferstatus NOT IN (...,'teilgeliefert',...)`) die Pickliste korrekt schließt UND ob `lager/picklisten.php`s Hauptabfrage den Auftrag danach mit der korrekten Restmenge (4) neu anbietet — beides funktioniert wie vorgesehen. Gleiche Prüfung für `packplatz/warenausgang/index.php`s "Direktauswahl"-Liste — auch korrekt. Vermutlich bereits in einer früheren Session (2026-07-03 oder 2026-07-09) mitgefixt, ohne dass diese konkrete Alt-Notiz aktualisiert wurde. Bei diesem Anlass stattdessen einen echten, unabhängigen Bug bei der Nachnahme-Teillieferung gefunden — siehe [[project_plc_versand]].

### D (Teil 2) — Teillieferung-Split-Logik — weiterhin offen (2026-07-10 nochmal bestätigt offen)
Phase 2, aktuell bleibt Restmenge nur im Auftrag stehen, kein echter Positions-Split.

### ~~B — Packplatz: Intern~~ / ~~C — Packplatz: Retoure~~ / ~~E — Fehlende Mail-Templates~~ — STALE, bereits erledigt (verifiziert 2026-07-03)
Diese drei Punkte waren schon durch die "✅ Neu fertig (2026-06-26)"-Sektion oben in dieser selben Datei widerlegt, standen aber trotzdem noch als offen weiter unten — Selbstwiderspruch in der Memory. Verifiziert per Audit-Agent 2026-07-03:
- Intern (`intern/index.php`, `umbuchen.php`, `zustand_umbuchen.php`) voll gebaut, zuletzt noch mit Charge-Auswahl erweitert
- Retoure (`retoure/index.php`, `detail.php`, `speichern.php`) voll gebaut inkl. GS-Auslösung + Mail
- Mail-Templates existieren alle (`auftragsbestaetigung.html.twig`, `rechnung_mail.html.twig`, `gutschrift_mail.html.twig`) und werden aktiv aus `auftraege/dokument_erstellen.php` + `gutschrift_speichern.php` verschickt

## Technische Details
- PLC = Packet Label Creator (lokale Software der Österreichischen Post)
- Trackingnummer: wird vom PLC auf Label gedruckt → am Packplatz vom Label abgescannt
- Kein PLC-Response-Parsing (hat nie funktioniert) → manueller Scan reicht
- Lieferadresse-Snapshot hat strasse + hausnummer getrennt → passt für EasyPak <Street>/<HomeNr>
