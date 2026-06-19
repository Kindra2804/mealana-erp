---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

Stand: 2026-06-19 (Session 3)

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
- 47 Migrations angewendet (001–047)
- aktionen (042): umgebaut aus preis_aktionen, kein typ/zeitraum mehr auf Aktion selbst
- aktionen_kategorien (042): Kategorie ↔ Aktion + Zeitraum pro Zuweisung
- aktionen_artikel_preise (042): Preiseingaben pro Aktion + Vater + Sub-Achse + KG
- aktionen.gestartet (043): manueller Start-Flag
- kundengruppen.ist_standard (044): ersetzt rabatt_prozent, Endkunden = 1
- artikel_bilder + artikel_bilder_shops (045)
- zahlungsbedingungen (046): geteilt Kunden + Lieferanten, 5 Standard-Einträge
- kunden + kunden_adressen + kunden_ansprechpartner + kunden_dsgvo_consent + kunden_shops + kunden_merge_queue (047): AES-256-GCM Verschlüsselung, Laufkunde id=1
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
- *Bilder + Merkmale: Platzhalter-Tabs, Backend fehlt noch

### Vater-Kind Vererbung ✅ VOLLSTÄNDIG (2026-06-19)
- erstelleKombinationen(): erbt alle ~25 Felder vom Vater (vorher nur 4)
- kopiereVaterRelationenZuKindern(): Kategorien + Merkmale + Lieferanten + Preise beim Erstellen
- propagiereZuKindern(): alle gemeinsamen Felder beim Vater-Update an Kinder
- syncKategorienZuKindern(): Kategorie-Sync beim saveKategorien()
- Workflow-Doku: docs/workflows/artikel_workflows.md (Mermaid)

### Achsen-Modul ✅ VOLLSTÄNDIG
- Globale Achsenverwaltung: CRUD, Edit-Modal, Sortierung
- Abhängige Achsen (Migration 040+041): Gruppenachse + Sub-Achsen-Baum
- achsen_zuweisen.php: Baumstruktur, Chip-Input, Granulare Sperrung

### Varianten-System ✅ VOLLSTÄNDIG
- VarKombi-Generator: Sub-Achsen = UNION + Suffix, nie Kreuzprodukt
- Granulare Sperrung: verwendete Werte 🔒, neue hinzufügen/freie löschen OK

### Lager-Modul (erp/public/lager/)
- Wareneingang mit EAN-Scan, Chargen-Tracking, Bewegungslog
- Schnell-Wareneingang aus Artikel-Detail

### Lieferanten-Modul (erp/public/lieferanten/)
- CRUD + Vertreter

### Berechtigungssystem
- 3 Rollen: superadmin, admin, mitarbeiter
- 47 Permissions im Format modul.aktion
- Audit-Log (aktivitaeten-Tabelle)

### Aktions-Modul ✅ VOLLSTÄNDIG (2026-06-18)
- DB: aktionen, aktionen_kategorien, aktionen_artikel_preise (042+043)
- Kategorien: ist_aktions_kategorie Checkbox, ⏰-Symbol (grau=geplant, orange=aktiv)
- liste.php + bearbeiten.php: in Artikel-Sidebar als "Preise/Aktionen" ($activeModule='artikel')
- shell_top.php: Active-Detection via REQUEST_URI (statt basename — URL-Rewrite-sicher)
- liste.php: Übersicht mit Status-Chips (Entwurf/Geplant/Aktiv/Abgelaufen)
- bearbeiten.php: Stammdaten + Kategorie-Zuweisung + Preiseingabe-Screen
- AJAX: speichern, kategorie add/remove, starten/stoppen, löschen, preise_speichern (batch upsert)

### PreisService ✅ VOLLSTÄNDIG (2026-06-18)
- getEffektiverPreis(artikelId, kgId): 4-stufige Prioritätskette
- SALE-Override UI, Aktions-Banner, Preis-Status-Chips in Liste

### Kunden-Modul ✅ VOLLSTÄNDIG (2026-06-19)
- Migrations 046+047: zahlungsbedingungen + 6 Kunden-Tabellen
- Encryption.php (src/core/): AES-256-GCM, HMAC-SHA256 Suche, Crypto-Shredding-fähig
- Keys in erp/config/encryption.php (gitignored!)
- KundenRepository: transparente Ver-/Entschlüsselung, alle _enc Felder
- KundenService: Validierung, Kundennummer KD-XXXXX, E-Mail-Duplikat via Hash
- public/kunden/: liste, neu, speichern, detail (4 Tabs), bearbeiten, aktualisieren
- Adressen: Modal-Neu + Modal-Edit (data-Attribute), speichern/aktualisieren/loeschen
- DSGVO: consent_speichern.php, Consent-Log-Tabelle unveränderlich
- status_setzen.php: aktiv/gesperrt/geloescht
- Laufkunde: id=1, fest in DB, kein Login/Shop-Account
- shell_top.php: Kunden-Sidebar ergänzt (Liste + Neuer Kunde)

## 🟡 Offene Bugs (vor Barbara-Test angehen)

| Bug | Datei | Priorität |
|-----|-------|-----------|
| Aktions-Kategorie-Zuweisung: kein Auto-Aktionspreis | bug_aktionskategorie_zuweisung.md | MITTEL |
| Aktions-Kategorie-Symbol im Baum verschwunden | bug_aktionskategorie_zuweisung.md | NIEDRIG |

## 🔴 Noch nicht gebaut

| Modul | Priorität |
|---|---|
| Workflow-Doku restliche Module | HOCH (laufend) |
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
- Aktions-Kategorie: keine Auto-Preise bei nachträglicher Zuweisung

## Workflow-Dokumentation (docs/workflows/)
- artikel_workflows.md: Artikel anlegen + Varianten erstellen (Mermaid, mit DB-Feldern)
- Weitere Module folgen laufend
