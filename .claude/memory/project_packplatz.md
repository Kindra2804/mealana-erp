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

## 🔴 Noch offen

### A — Picklisten-Manager (Babsi-Arbeitsplatz, nicht Packplatz-PC)
- Übersicht: welche Aufträge können mit aktuellem Lagerbestand komplett ausgeliefert werden
- Reihung: Alter (ältester zuerst), Teillieferung-Aufträge eingeschlossen
- Override: einzelne Aufträge deselektieren → neue Kombinationsberechnung
- Picklisten drucken (PDF, Barcode der PL-Nummer drauf, zum Abscannen am Packplatz)
- **Benötigt zuerst: Lagerstand ist/reserviert/verfügbar** (reservierungen-Tabelle existiert schon aus Migration, UI fehlt)

### B — Packplatz: Intern
- Artikelzustand ändern (gebraucht, defekt, etc.) am Packplatz-PC
- Direktlink zu artikel/detail.php oder eigenes Formular

### C — Packplatz: Retoure
- Retourenverarbeitung: Ware einbuchen + Gutschrift auslösen (DokumentService vorhanden)
- Ersatzlieferung: vorerst weglassen (wird nie verwendet laut Jacky)

### D — Scan-Interface Verbesserungen
- Doppelklick auf Artikel → EAN nacherfassen (für Artikel ohne EAN)
- Teillieferung: Positions-Split-Logik (Phase 2 — aktuell bleibt Restmenge nur im Auftrag, kein echter Split)

### E — Fehlende Mail-Templates
- Auftragsbestätigung (AB) an Kunden — wenn AB-Dokument erstellt wird
- Rechnung per Mail — für Rechnungszahler (Anhang als PDF)
- Gutschrift per Mail — nach GS-Erstellung

## Technische Details
- PLC = Packet Label Creator (lokale Software der Österreichischen Post)
- Trackingnummer: wird vom PLC auf Label gedruckt → am Packplatz vom Label abgescannt
- Kein PLC-Response-Parsing (hat nie funktioniert) → manueller Scan reicht
- Lieferadresse-Snapshot hat strasse + hausnummer getrennt → passt für EasyPak <Street>/<HomeNr>
