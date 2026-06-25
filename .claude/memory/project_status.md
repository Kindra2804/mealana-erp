---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

Stand: 2026-06-25 (Session 10)

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
- 69 Migrations angewendet (001–069)
- Wichtige neue Tabellen: mahnungen (069), shops (067), auftraege/auftrag_positionen/rechnungen/auftrag_dokumente/auftrag_statuslog (060–062)
- Dump aktualisieren: `& "C:\xampp\mysql\bin\mysqldump.exe" --host=localhost --user=root --no-tablespaces --routines --skip-comments mealana_erp | Out-File -FilePath "D:\ERP\mealana\erp\database\schema_current.sql" -Encoding utf8`

## ✅ Fertige Module (Stand 2026-06-25)

### Artikel-Modul ✅ VOLLSTÄNDIG
- CRUD, 7 Tabs, Varianten, Preise, Bilder, Merkmale, Lieferanten, SEO
- lieferzeit_text: in detail.php + aktualisieren.php (erscheint auf Dokumenten wenn Lagerbestand=0)
- Vater-Kind Vererbung vollständig

### Achsen-Modul ✅ VOLLSTÄNDIG
### Varianten-System ✅ VOLLSTÄNDIG
### Lager-Modul ✅ VOLLSTÄNDIG
### Lieferanten-Modul ✅ VOLLSTÄNDIG
### Aktions-Modul ✅ VOLLSTÄNDIG
### PreisService ✅ VOLLSTÄNDIG
### Kunden-Modul ✅ VOLLSTÄNDIG (AES-256-GCM, DSGVO)
### Partner-Modul ✅ VOLLSTÄNDIG (Mietfächer, Vertragshistory)
### Hersteller-Modul ✅ VOLLSTÄNDIG (GPSR-Felder)
### Bestellwesen/Einkauf ✅ VOLLSTÄNDIG

### Auftragsmodul/Verkauf ✅ WEITGEHEND FERTIG (2026-06-25)
- Migrations 060–068 eingespielt
- liste, neu, detail, bearbeiten (Positionen änderbar bis versendet/abgeschlossen), aktualisieren, stornieren
- Dokumente: Rechnung, Auftragsbestätigung, Lieferschein, Abholzettel, Gutschrift (Vollstorno + Teilgutschrift)
- detail.php: Adressboxen (RGN + Lieferadr), Verlauf einklappbar, Rechnung-Guard (kein Duplikat)
- Gutschrift: erstelleGutschrift() in DokumentService, gutschrift_erstellen.php + gutschrift_speichern.php
- DokumentService + DokumentRepository: wiederverwendbar für Kassa (gleicher Service, anderes UI)

### Einstellungen-Modul ✅ NEU (2026-06-25)
- public/einstellungen/index.php — 4 Tabs: Firma / Kanäle / Mail+SMTP / System
- Tab Firma: Adresse, UID, IBAN, Bank, Logo-Upload → befüllt PDF-Header/Footer sofort
- Tab Kanäle: shops-Tabelle CRUD, Logo-Upload pro Kanal, WC-URL
- Tab Mail/SMTP: Host, Port, User, Pass, Verschlüsselung, Absender + Test-Mail-Button
- Tab System: Preisanzeige, Kleinunternehmer-Modus
- ⚙️-Icon in Top-Nav jetzt aktiv (war disabled)
- speichern.php + test_mail.php

### Mail-Infrastruktur ✅ NEU (2026-06-25)
- PHPMailer ^7.1 via Composer (zip-Extension in php.ini aktiviert)
- src/core/Mailer.php: SMTP-Wrapper, liest Config aus system_einstellungen
- sendeTemplate(): Twig-Render + Mail in einem Schritt
- mail_aktiv-Flag: 0 = nur loggen, 1 = wirklich senden; Test-Mail umgeht Flag
- templates/mails/basis_layout.html.twig + mahnwesen/erinnerung.html.twig + stornierung.html.twig

### Mahnwesen-Cronjob ✅ NEU (2026-06-25)
- erp/cron/mahnwesen.php — täglich ausführen (empfohlen 06:00)
- 14+ Tage unbezahlt → Erinnerungsmail (einmalig pro Auftrag)
- 30+ Tage unbezahlt → automatische Stornierung + Lagerrückbuchung + Stornierungsmail
- Protokoll in mahnungen-Tabelle (Migration 069)
- Betrifft nur: zahlungsart='rechnung' + zahlungsstatus='offen' + nicht storniert/abgeschlossen
- Windows Task Scheduler / Linux crontab — Befehl steht als Kommentar im File

## 🔴 Noch nicht gebaut (Reihenfolge = geplante Priorität)

| Modul | Priorität | Anmerkung |
|---|---|---|
| Packplatz | HOCH | eigenes Modul public/packplatz/, Tablet-Touch-freundlich |
| Kasse/POS | HOCH (RKSV-Pflicht AT) | Design: project_kasse_bon_design.md |
| Zentrales Dokumentenarchiv | MITTEL | alle Dokumente, Filter nach Typ+Zeitraum; wichtig für DATEV-Export |
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
- Preis-Query Datums-Filter fehlt (gueltig_ab/gueltig_bis in artikel_preise-JOIN)
- artikel_achsen.sort_order noch nicht genutzt
- Hersteller: aktualisiert_am Spalte fehlt noch
