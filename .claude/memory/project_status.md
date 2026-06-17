---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata:
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

Stand: 2026-06-17 (Vollständig abgeglichen mit tatsächlichem Code-Stand)

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
- 36 Migrations angewendet (001–036)
- 36 DB-Tabellen insgesamt
- Artikel-Tabelle: 39 Spalten
- Dump aktualisieren: `& "C:\xampp\mysql\bin\mysqldump.exe" --host=localhost --user=root --no-tablespaces --routines --skip-comments mealana_erp | Out-File -FilePath "D:\ERP\mealana\erp\database\schema_current.sql" -Encoding utf8`

## ✅ Fertige Module (Stand 2026-06-17)

### Artikel-Modul (erp/public/artikel/ — 36 PHP-Dateien)
- CRUD: neu, bearbeiten, detail, kopieren, delete
- detail.php: 7 Tabs (Stammdaten, Varianten, Preise, Lager, Bilder*, Merkmale*, Lieferanten, SEO)
- Varianten-System: Achsen + VarKombi-Generator (kartesisches Produkt)
- Preise: Kundengruppen, Staffel, UVP, Aktionen
- Texte: kurzbeschreibung, beschreibung, technische_details, beschreibung_intern
- Physikalisch: Gewicht, Maße, Versandklasse
- SEO: meta_titel, meta_description, url_slug + seo_speichern.php
- Zustandsartikel: 8 Zustände, in Liste eingerückt unter Vater
- Artikel-Liste: Spalten-Picker (user-spezifisch), Massenauswahl, Sticky-Spalten, Loop-Rendering
- Kategorien: Baum-Manager (AJAX CRUD, Drag-Drop Sort)
- Lieferanten-Tab: CRUD, Modal, AJAX-Save
- Chargen-Tracking, Auslaufartikel, Überverkauf
- deaktiviert_mit_vater + auslauf_mit_vater Kaskaden-Logik
- *Bilder + Merkmale: Platzhalter-Tabs, Backend fehlt noch

### Lager-Modul (erp/public/lager/)
- Wareneingang mit EAN-Scan, Chargen-Tracking, Bewegungslog
- Schnell-Wareneingang aus Artikel-Detail

### Lieferanten-Modul (erp/public/lieferanten/)
- CRUD + Vertreter

### Achsen-Modul (erp/public/achsen/)
- Globale Achsenverwaltung (CRUD + Drag-Drop Sort)

### Berechtigungssystem
- 3 Rollen: superadmin, admin, mitarbeiter
- 47 Permissions im Format modul.aktion
- Audit-Log (aktivitaeten-Tabelle)

## 🔴 Noch nicht gebaut

| Modul | Priorität |
|---|---|
| Filterung Artikelliste (Typ/Hersteller/Kategorie/Bestand) | JETZT |
| Merkmale-UI | HOCH (vor Shop) |
| Bilder-Upload | HOCH (vor Shop) |
| Bestellwesen/Einkauf | HOCH |
| Auftragsmodul/Verkauf | HOCH |
| Kasse/POS | HOCH (RKSV-Pflicht AT) |
| Inventur | MITTEL |
| Shop-Export | MITTEL |
| Buchhaltung/DATEV | MITTEL |
| Seriennummern | NIEDRIG |

## Aktuelle Baustelle (2026-06-17)

Artikel-Modul fast abgeschlossen. Noch offen:
1. **Bug: Kategorie ändern in detail.php** — Regression, muss sofort gefixt werden
2. **Filterung in Artikelliste** — nach Typ, Hersteller, Kategorie, nur mit Bestand
3. **Artikeltyp als Spalte** im Spalten-Picker (sortierbar, wie Hersteller)

## Offene technische Punkte

### Preis-Query Datums-Filter fehlt
JOIN auf artikel_preise hat keinen gueltig_ab/gueltig_bis Filter. Muss ergänzt werden wenn Sonderpreise aktiv genutzt werden.

### artikel_achsen.sort_order noch nicht genutzt
Feld existiert (Migration 024), aber kein UI zum Sortieren der Achsen-Reihenfolge pro Artikel.
