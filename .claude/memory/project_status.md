---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

Stand: 2026-06-27 (Session 13)

## Git Repository
`D:/ERP/mealana/` — nicht in `D:/ERP` suchen!
Commit-Befehl: `git -C "D:/ERP/mealana" ...`

## Memory-Backup
Memory-Dateien werden in `D:/ERP/mealana/.claude/memory/` mitgepflegt (im Git-Repo).
Am Ende jeder Session syncen:
```powershell
Copy-Item "C:\Users\indy1\.claude\projects\d--ERP\memory\*.md" "D:\ERP\mealana\.claude\memory\" -Force
git -C "D:/ERP/mealana" add .claude/memory/ && git -C "D:/ERP/mealana" commit -m "Memory-Backup"
```

## Schema-Referenz
- 78 Migrations angewendet (001–078)
- Wichtige neue Tabellen: auftrag_zahlungen (076), mahnungen (069), shops (067), auftraege/auftrag_positionen/rechnungen/auftrag_dokumente/auftrag_statuslog (060–062)
- Dump aktualisieren: `& "C:\xampp\mysql\bin\mysqldump.exe" --host=localhost --user=root --no-tablespaces --routines --skip-comments mealana_erp | Out-File -FilePath "D:\ERP\mealana\erp\database\schema_current.sql" -Encoding utf8`

## ✅ Fertige Module (Stand 2026-06-26)

### Artikel-Modul ✅ VOLLSTÄNDIG
- CRUD, 7 Tabs, Varianten, Preise, Bilder, Merkmale, Lieferanten, SEO
- lieferzeit_text: in detail.php + aktualisieren.php (erscheint auf Dokumenten wenn Lagerbestand=0)
- Vater-Kind Vererbung vollständig
- VarKombi Generator: EAN-Feld, Kindname = Vater + Achsenname + Wert
- Bulk-Kategorie-Zuweisung in liste.php (Mehrfachauswahl → Modal → INSERT IGNORE + Kinder)
- Fehlbest.-Chip: nur bei reserviert > gesamtbestand

### Achsen-Modul ✅ VOLLSTÄNDIG
- Aufpreis/Direktpreis pro Achse (Migration 074, Toggle-UI)
- sort_order wird beim Speichern korrekt gesetzt (INSERT + UPDATE)

### Varianten-System ✅ VOLLSTÄNDIG
### Lager-Modul ✅ VOLLSTÄNDIG
### Lieferanten-Modul ✅ VOLLSTÄNDIG
### Aktions-Modul ✅ VOLLSTÄNDIG
### PreisService ✅ VOLLSTÄNDIG
- artikel_preise JOIN mit Datumsfilter: bevorzugt aktiven Sonderpreis über Basispreis

### Kunden-Modul ✅ VOLLSTÄNDIG (AES-256-GCM, DSGVO)
### Partner-Modul ✅ VOLLSTÄNDIG (Mietfächer, Vertragshistory)
### Hersteller-Modul ✅ VOLLSTÄNDIG (GPSR-Felder, aktualisiert_am)
### Bestellwesen/Einkauf ✅ VOLLSTÄNDIG

### Auftragsmodul/Verkauf ✅ WEITGEHEND FERTIG (aktualisiert 2026-06-26)
- Migrations 060–068 eingespielt
- liste, neu, detail, bearbeiten, aktualisieren, stornieren
- Dokumente: Rechnung, Auftragsbestätigung, Lieferschein, Abholzettel, Gutschrift
- **Zahlung buchen** (NEU 2026-06-26):
  - Migration 076: auftrag_zahlungen (id, auftrag_id, betrag, buchungsdatum, notiz, erfasst_von)
  - detail.php: Zahlungsverlauf + Buchungsformular (Betrag vorausgefüllt, Datepicker)
  - Teilzahlung → Status 'teilbezahlt'; Vollzahlung → 'bezahlt'; Überzahlung → chip 'Überbezahlt'
  - liste.php: Chips Teilbezahlt/Überbezahlt + Filter für alle Zahlungsstatus

### Einstellungen-Modul ✅ NEU (2026-06-25)
### Mail-Infrastruktur ✅ NEU (2026-06-25)
### Mahnwesen-Cronjob ✅ NEU (2026-06-25)

### Kasse/POS ✅ Phase 1 FERTIG + Bugfixes (2026-06-27)
- Migration 077: kassen, kassen_bons, kassen_bon_positionen, kassenbuch, offene_auswahl
- Migration 078: Divers-Platzhalter-Artikel 99-9999 (für auftrag_positionen FK)
- public/kasse/: 16 Dateien — index, bon, ajax_artikel, bon_speichern, bon_druck, kassenbuch(+speichern), kassensturz(+speichern), offene_auswahl(+speichern+verarbeiten), bon_journal, bon_stornieren
- KassenService: erstelleBon, storniereBon, findArtikelByCode(FIFO-Charge), X-Bon/Z-Bon, Kassenbuch, Offene Auswahl
- Features: EAN-Scan, Vater→Variante-Auswahl, Divers-Artikel, Rabatt, Bar+Rückgeld, Karte extern, Gutschein, Kombi, 80mm Druck, Zählhilfe
- **Jeder Bon erstellt automatisch Auftrag (kanal='kasse')** → erscheint in auftraege/liste.php
- **Korrekturbuchung bei 0-Bestand**: +Eingang vor Ausgang statt negativer Bestand (Log ehrlich)
- **Divers-Positionen in auftrag_positionen** via Platzhalter 99-9999 (getDiversArtikelId())
- **auftraege/detail.php**: kanal='kasse' → Dokumente gesperrt, nur "Kassenbon drucken" sichtbar
- Bugfixes: steuerklassen.satz (war prozentsatz), artikel_preise.kundengruppen_id (war kunden_gruppe_id)
- Phase 2 offen: RKSV/BFR-BONit, Auftrag laden (Abholung), Bon-Park, A4-Bon als Rechnung

## 🔴 Noch nicht gebaut (Reihenfolge = geplante Priorität)

| Modul | Priorität | Anmerkung |
|---|---|---|
| Kasse Phase 2 | HOCH | RKSV/BFR BONit, Auftrag laden, Bon-Park |
| **Auth & Benutzer-Cluster** | **HOCH** | **Zusammenhängend, in dieser Reihenfolge bauen:** |
| Login / Logout (Shell) | HOCH | Basis für alles weitere; rechts oben in Shell |
| Anmeldeschirm + Rollenauswahl | HOCH | nach Login: ERP / Packplatz / Kasse je nach Rechten |
| Benutzer-Profil UI | HOCH | Namen, Formularname, E-Mail ändern (derzeit nur in DB) |
| Passwort ändern | HOCH | via E-Mail-Link (Mail-Infra bereits vorhanden) |
| Rechteverwaltung | MITTEL | Admin-Seite: Rollen zuweisen; eher für Weitergabe |
| Anmeldekontrolle / Zwangsabmeldung | MITTEL | Session-Management; für Weitergabe (Praktikanten) |
| ~~Zentrales Dokumentenarchiv~~ | ✅ FERTIG 2026-06-27 | Kassenbons via UNION ALL integriert; X/Z-Bons in Einstellungen/Kassen |
| Dashboard | MITTEL | Lagerwert, Low-Stock, Tagesübersicht; design_mockup.svg als Basis |
| Log-Aufbereitung + Shell-Footer | MITTEL | info/warn/error Klassifizierung; Zeile in Shell-Bottom (siehe project_logger_ui.md) |
| Inventur | MITTEL | inkl. Inventurliste (Druck) + mobile App |
| Shop-Export / WooCommerce Sync | MITTEL | Design: db_design_entscheidungen.md |
| Gutschein-Modul | MITTEL | Design: project_gutscheine.md |
| Buchhaltung/DATEV | MITTEL | Design: project_buchhaltung.md |
| Etiketten-Modul | MITTEL | ZPL vs. Dompdf — Entscheidung offen |
| Adressetiketten | MITTEL | A4-Druck, Sichtkuvert |
| Installationsanleitung | MITTEL | Server-Setup, Composer, Migrations, Cronjobs (inkl. Mahnwesen), RKSV |
| Abrechnung Mietfach | NIEDRIG | monatlich/quartalsweise |
| Spendenübersicht Yarnpride | NIEDRIG | |
| Preisliste | NIEDRIG | |
| Anzahlungsrechnung | NIEDRIG | ANZ-2026-XXXXX |
| Kunden-Merge-UI | NIEDRIG | |
| Seriennummern | NIEDRIG | |

## Offene technische Punkte
- ~~Preis-Query Datums-Filter~~ ✅ behoben 2026-06-26
- ~~artikel_achsen.sort_order~~ ✅ behoben 2026-06-26
- ~~Hersteller: aktualisiert_am~~ ✅ war bereits vorhanden
