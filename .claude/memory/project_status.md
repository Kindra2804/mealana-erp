---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

Stand: 2026-06-24 (Session 9)

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
- 58 Migrations angewendet (001–058)
- aktionen (042): umgebaut aus preis_aktionen, kein typ/zeitraum mehr auf Aktion selbst
- aktionen_kategorien (042): Kategorie ↔ Aktion + Zeitraum pro Zuweisung
- aktionen_artikel_preise (042): Preiseingaben pro Aktion + Vater + Sub-Achse + KG
- aktionen.gestartet (043): manueller Start-Flag
- kundengruppen.ist_standard (044): ersetzt rabatt_prozent, Endkunden = 1
- artikel_bilder + artikel_bilder_shops (045)
- zahlungsbedingungen (046): geteilt Kunden + Lieferanten, 5 Standard-Einträge
- kunden + kunden_adressen + kunden_ansprechpartner + kunden_dsgvo_consent + kunden_shops + kunden_merge_queue (047): AES-256-GCM Verschlüsselung, Laufkunde id=1
- hersteller: ALTER TABLE (048) — handelsname, email, strasse, plz, ort, logo_pfad, reo_name/strasse/plz/ort/land/email
- partner + mietfaecher (049-052): Stamm, Belege, Spenden-Log, artikel_partner
- mietfaecher redesign (053): physische Stammdaten, mietfach_mietvertraege (History)
- partner.typ ENUM + 'mietfach' (054)
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

### Partner-Modul ✅ VOLLSTÄNDIG (2026-06-21)
- Migrations 049–054 eingespielt
- Partner-Typen: mietfach / kommission / spende / beides (+ Auto-Beleg-Typ)
- Mietfächer als physische Einheiten (Maße, Ort, Standardpreis)
- Mietverträge mit History (vertrag_starten / vertrag_beenden)
- public/partner/: liste.php, mietfaecher.php + alle AJAX-Endpoints
- MietfachRepository + MietfachService

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

### Bestellwesen/Einkauf ✅ VOLLSTÄNDIG (2026-06-23)
- Migrations 055–059: meldebestand/sicherheitsbestand/standardbestellmenge, bestellungen, bestellung_positionen (inkl. lieferzeit_text 059), bestellung_eingaenge
- BestellungRepository + BestellungService + WareneingangRepository + WareneingangService
- public/bestellungen/: liste, neu, detail, bearbeiten (Header+Positionen+neue hinzufügen), aktualisieren, speichern, rechnung_speichern, stornieren + AJAX (artikel_ajax ?q=/?alle=1, reserviert_ajax)
- public/wareneingang/: index (EAN-Scan + Kacheln), detail (Scan-Modus + Abschluss-Dialog + ✏ Artikel-bearbeiten pro Zeile), speichern, abschliessen + AJAX
- Packplatz-ready: wareneingang als eigenständiges Modul
- Reserviert-Infobox: VPE-Berechnung + 1-Klick Übernahme in Bestellung
- Teillieferung-Dialog: "warten" oder "Rest streichen" (DROPS-Modell mit Gutschrift-Notiz)
- Artikelbild beim Scan-Modus (Fehlerreduktion)
- Shell: Einkauf-Nav → bestellungen/liste.php, Sidebar: Bestellungen + Wareneingang + Lieferanten

**Babsi-Feedback (alle erledigt 2026-06-23):**
- **Punkt 2** — EAN nicht gefunden → "Neuen Artikel anlegen" → Session-Breadcrumb → artikel/neu.php mit EAN vorbelegt → nach Save zurück zu WE mit EAN auto-gesucht
  - wareneingang/artikel_vorbereiten.php (setzt $_SESSION['we_ean'] + we_rueckkehr)
  - artikel/neu.php + speichern.php: Breadcrumb-Banner + Redirect zurück
- **Punkt 1** — Artikel gefunden, keine offene Bestellung → "Zur Sammelliste" → Session-Durchlauf sammeln → Lieferant wählen → Bestellung anlegen + sofort erledigt buchen
  - wareneingang/durchlauf_add.php + durchlauf_clear.php + bestellung_aus_durchlauf.php
  - wareneingang/index.php: Sammelliste-Box oben wenn Durchlauf nicht leer
- **Szenario B** — ✏-Button in WE-Detailansicht pro Position → Artikel bearbeiten → zurück zu WE
  - wareneingang/artikel_bearbeiten_vorbereiten.php (Session-Breadcrumb)
  - artikel/bearbeiten.php + aktualisieren.php: Breadcrumb-Banner + Redirect zurück
- **bestellungen/bearbeiten.php** — Header-Edit + bestehende Positionen anzeigen + neue Positionen hinzufügen (Typeahead alle Artikel via ?alle=1)
- **JS-Validierung** in bestellungen/neu.php: Artikel muss aus Typeahead geklickt werden (nicht nur getippt)
- **ArtikelRepository Bugfix**: Qualitätslisten suchten `typ='ean'` statt `typ='GTIN13'` — behoben

## ✅ Modulpflege abgeschlossen (2026-06-23)
- **JS auslagern**: 21 JS-Dateien aus 21 PHP-Dateien extrahiert (kein inline JS-Block mehr außer PHP-Var-Initialisierern mit `window.*`)
  - PHP→JS Brücke: `<script>window.VAR = <?= ... ?>;</script>` inline, dann `<script src="/mealana/js/xxx.js">` extern
  - Erstellt: shell.js, artikel.js, artikel_detail.js, artikel_neu.js, artikel_bearbeiten.js, aktionen.js, aktionen_liste.js, bestellungen_neu.js, bestellungen_bearbeiten.js, wareneingang_index.js, wareneingang_detail.js, lager_wareneingang.js, partner_liste.js, partner_mietfaecher.js, achsen_liste.js, achsen_zuweisen.js, kategorien_verwalten.js, merkmale_verwalten.js, kunden.js, kunden_detail.js, hersteller_liste.js
- **Bedienungsanleitung**: `public/bedienungsanleitung.php` mit TOC + Kapitel-Platzhaltern (Fertig/Geplant-Badges). 📖-Link in Top-Nav.

## 🔴 Noch nicht gebaut

| Modul | Priorität |
|---|---|
| Auftragsmodul/Verkauf | HOCH |
| Kasse/POS | HOCH (RKSV-Pflicht AT) |
| Inventur | MITTEL |
| Shop-Export (inkl. WooCommerce Kunden-Sync) | MITTEL — Design-Entscheidungen 2026-06-21 fertig (siehe db_design_entscheidungen.md) |
| Buchhaltung/DATEV | MITTEL |
| Kunden-Merge-UI (kunden_merge_queue) | NIEDRIG |
| Seriennummern | NIEDRIG |

## ✅ Modulpflege PHP-Kommentare (2026-06-24)
- Alle 32 PHP-Klassen in `erp/src/` mit PHPDoc-Klassen-Kommentaren und Methoden-Kommentaren versehen
- Kommentiert: alle Core-Klassen (Auth, Encryption, Database, Logger) + alle Module
  (Artikel, Varianten, Achsen, Kategorien, Bilder, Merkmale, Preise, Aktionen, Lager,
   Lieferanten, Hersteller, Kunden, Partner/Mietfach, Bestellungen, Wareneingang)

## Offene technische Punkte
- Preis-Query Datums-Filter fehlt (gueltig_ab/gueltig_bis in artikel_preise-JOIN)
- artikel_achsen.sort_order noch nicht genutzt (UI fehlt)
- Hersteller: aktualisiert_am Spalte fehlt noch (nicht in Migration 048 aufgenommen)
