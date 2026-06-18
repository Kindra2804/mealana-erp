---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata:
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

Stand: 2026-06-18 (Aktions-Modul Grundgerüst abgeschlossen)

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
- 44 Migrations angewendet (001–044)
- aktionen (042): umgebaut aus preis_aktionen, kein typ/zeitraum mehr auf Aktion selbst
- aktionen_kategorien (042): Kategorie ↔ Aktion + Zeitraum pro Zuweisung
- aktionen_artikel_preise (042): Preiseingaben pro Aktion + Vater + Sub-Achse + KG
- aktionen.gestartet (043): manueller Start-Flag
- kundengruppen.ist_standard (044): ersetzt rabatt_prozent, Endkunden = 1
- Dump aktualisieren: `& "C:\xampp\mysql\bin\mysqldump.exe" --host=localhost --user=root --no-tablespaces --routines --skip-comments mealana_erp | Out-File -FilePath "D:\ERP\mealana\erp\database\schema_current.sql" -Encoding utf8`

## ✅ Fertige Module (Stand 2026-06-18)

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
- bearbeiten.php: Stammdaten + Kategorie-Zuweisung + Preiseingabe-Screen (Normal-VK Referenz, Brutto/Netto bidirektional)
- AJAX: speichern, kategorie add/remove, starten/stoppen, löschen, preise_speichern (batch upsert)
- kundengruppen.ist_standard (044): ⭐-Markierung, dynamischer Default

### PreisService ✅ VOLLSTÄNDIG (2026-06-18)
- getEffektiverPreis(artikelId, kgId): 4-stufige Prioritätskette (SALE → Aktion → KG → Standard), gibt quelle+bis zurück
- SALE-Override UI in detail.php Preise-Tab: Modal mit Brutto/Netto, gültig ab/bis, bis-Lagerstand-0
- detail.php Header: Streichpreis bei aktiver Aktion + oranges "🔥 Aktion aktiv · [Name] · bis [Datum] · [Preis]" Banner
- detail.php Preise-Tab: ★ vor Standard-Kundengruppen-Name
- Artikel-Liste: roter SALE-Chip + amber ⏰ (Aktion aktiv) + grauer ⏰ (Aktion vorhanden aber durch SALE überschrieben)
- ArtikelRepository.getPreisStatusBatch: 2-Query Batch-Check (keine N+1 Queries)

## 🔴 Noch nicht gebaut

| Modul | Priorität |
|---|---|
| Bilder-Upload | HOCH (vor Shop) |
| Bestellwesen/Einkauf | HOCH |
| Auftragsmodul/Verkauf | HOCH |
| Kasse/POS | HOCH (RKSV-Pflicht AT) |
| Inventur | MITTEL |
| Shop-Export | MITTEL |
| Buchhaltung/DATEV | MITTEL |
| Seriennummern | NIEDRIG |

## Offene technische Punkte

### Preis-Query Datums-Filter fehlt
JOIN auf artikel_preise hat keinen gueltig_ab/gueltig_bis Filter. Muss ergänzt werden wenn Sonderpreise aktiv genutzt werden.

### artikel_achsen.sort_order noch nicht genutzt
Feld existiert (Migration 024), aber kein UI zum Sortieren der Achsen-Reihenfolge pro Artikel.
