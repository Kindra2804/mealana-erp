---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

Stand: 2026-06-19 (Session 4)

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
- 48 Migrations angewendet (001–048)
- aktionen (042): umgebaut aus preis_aktionen, kein typ/zeitraum mehr auf Aktion selbst
- aktionen_kategorien (042): Kategorie ↔ Aktion + Zeitraum pro Zuweisung
- aktionen_artikel_preise (042): Preiseingaben pro Aktion + Vater + Sub-Achse + KG
- aktionen.gestartet (043): manueller Start-Flag
- kundengruppen.ist_standard (044): ersetzt rabatt_prozent, Endkunden = 1
- artikel_bilder + artikel_bilder_shops (045)
- zahlungsbedingungen (046): geteilt Kunden + Lieferanten, 5 Standard-Einträge
- kunden + kunden_adressen + kunden_ansprechpartner + kunden_dsgvo_consent + kunden_shops + kunden_merge_queue (047): AES-256-GCM Verschlüsselung, Laufkunde id=1
- hersteller: ALTER TABLE (048) — handelsname, email, strasse, plz, ort, logo_pfad, reo_name/strasse/plz/ort/land/email
- Dump aktualisieren: `& "C:\xampp\mysql\bin\mysqldump.exe" --host=localhost --user=root --no-tablespaces --routines --skip-comments mealana_erp | Out-File -FilePath "D:\ERP\mealana\erp\database\schema_current.sql" -Encoding utf8`

## ✅ Fertige Module (Stand 2026-06-19)

### Artikel-Modul (erp/public/artikel/ — 36+ PHP-Dateien)
- CRUD: neu, bearbeiten, detail, kopieren, delete
- detail.php: 7 Tabs (Stammdaten, Varianten, Preise, Lager, Bilder*, Merkmale*, Lieferanten, SEO)
- Preise: Kundengruppen, Staffel, UVP, Aktionen
- Texte: kurzbeschreibung, beschreibung, technische_details, beschreibung_intern
- Physikalisch: Gewicht, Maße, Versandklasse
- SEO: meta_titel, meta_description, url_slug + seo_speichern.php
- Zustandsartikel: 8 Zustände, in Liste eingerückt unter Vater
- Artikel-Liste: Spalten-Picker (user-spezifisch), Massenauswahl, Sticky-Spalten, Loop-Rendering
- Kategorien: Baum-Manager (AJAX CRUD, Drag-Drop Sort) + ist_aktions_kategorie Checkbox + ⏰-Symbol
- Lieferanten-Tab: CRUD, Modal, AJAX-Save
- Chargen-Tracking, Auslaufartikel, Überverkauf
- deaktiviert_mit_vater + auslauf_mit_vater Kaskaden-Logik
- Hersteller-Dropdown: + Button (schnell_speichern.php) in neu.php + bearbeiten.php

### Vater-Kind Vererbung ✅ VOLLSTÄNDIG (2026-06-19)
- erstelleKombinationen(): erbt alle ~25 Felder vom Vater (vorher nur 4)
- kopiereVaterRelationenZuKindern(): Kategorien + Merkmale + Lieferanten + Preise beim Erstellen
- propagiereZuKindern(): alle gemeinsamen Felder beim Vater-Update an Kinder
- syncKategorienZuKindern(): Kategorie-Sync beim saveKategorien()

### Achsen-Modul ✅ VOLLSTÄNDIG
### Varianten-System ✅ VOLLSTÄNDIG

### Lager-Modul (erp/public/lager/)
- Wareneingang mit EAN-Scan, Chargen-Tracking, Bewegungslog

### Lieferanten-Modul (erp/public/lieferanten/)
- CRUD + Vertreter

### Berechtigungssystem
- 3 Rollen: superadmin, admin, mitarbeiter
- 47 Permissions im Format modul.aktion

### Aktions-Modul ✅ VOLLSTÄNDIG (2026-06-18)
### PreisService ✅ VOLLSTÄNDIG (2026-06-18)

### Kunden-Modul ✅ VOLLSTÄNDIG (2026-06-19)
- Migrations 046+047, AES-256-GCM, Laufkunde id=1
- DSGVO-Consent-Log, Adressen-Modals

### Hersteller-Modul ✅ VOLLSTÄNDIG (2026-06-19)
- Migration 048: GPSR-Felder (Adresse, E-Mail, Handelsname, Logo, REO)
- HerstellerRepository + HerstellerService (EU-Check, GPSR-Status, Logo GD-Upload 200×200)
- public/hersteller/: liste.php (Modal Neu+Bearbeiten), speichern, aktualisieren, loeschen, schnell_speichern
- GPSR-Status-Chip: ✓ EU / ✓ REO / ⚠ REO fehlt
- REO-Sektion im Modal: auto show/hide je nach Land (EU/nicht-EU)
- shell_top.php: Hersteller im Artikel-Sidebar + eigenes Modul 'hersteller'
- GPSR-Basis: EU 2023/988, seit 13.12.2024: Name, Adresse, E-Mail Pflicht im Shop
- Drops (NO) + Lang Yarns (CH) = nicht EU → REO erforderlich!

## 🟡 Offene Bugs

| Bug | Priorität |
|-----|-----------|
| Aktions-Kategorie-Zuweisung: kein Auto-Aktionspreis | MITTEL |

## 🔴 Noch nicht gebaut

| Modul | Priorität |
|---|---|
| Bestellwesen/Einkauf | HOCH |
| Auftragsmodul/Verkauf | HOCH |
| Kasse/POS | HOCH (RKSV-Pflicht AT) |
| Inventur | MITTEL |
| Shop-Export (inkl. WooCommerce Kunden-Sync) | MITTEL |
| Buchhaltung/DATEV | MITTEL |
| Kunden-Merge-UI (kunden_merge_queue) | NIEDRIG |
| Seriennummern | NIEDRIG |

## Offene technische Punkte
- Preis-Query Datums-Filter fehlt (gueltig_ab/gueltig_bis in artikel_preise-JOIN)
- artikel_achsen.sort_order noch nicht genutzt (UI fehlt)
- Hersteller: aktualisiert_am Spalte fehlt noch (nicht in Migration 048 aufgenommen)
