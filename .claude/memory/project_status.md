---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata:
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

Stand: 2026-06-18 (VarKombi-Generator + granulare Achsen-Sperrung abgeschlossen)

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
- 41 Migrations angewendet (001–041)
- varianten_achsen.ist_gruppe (Migration 041): Gruppenachse-Flag
- varianten_achsen.abhaengig_von_achse_id (Migration 040): Sub-Achsen-Baum
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
- Kategorien: Baum-Manager (AJAX CRUD, Drag-Drop Sort)
- Lieferanten-Tab: CRUD, Modal, AJAX-Save
- Chargen-Tracking, Auslaufartikel, Überverkauf
- deaktiviert_mit_vater + auslauf_mit_vater Kaskaden-Logik
- *Bilder + Merkmale: Platzhalter-Tabs, Backend fehlt noch

### Achsen-Modul ✅ VOLLSTÄNDIG (2026-06-18)
- Globale Achsenverwaltung: CRUD, Edit-Modal, Sortierung
- **Abhängige Achsen** (Migration 040+041): Gruppenachse + Sub-Achsen-Baum
- achsen_zuweisen.php: Baumstruktur, Chip-Input, ◀▶ Werte sortierbar, ↔ Wert verschieben
- Gruppenachse-Schutz: Client + Server (hasChildren-Guard)
- Achsen-Sortierung: tree-aware (nur Geschwister)
- Sidebar-Link zu liste.php entfernt (Management jetzt inline)

### Varianten-System ✅ VOLLSTÄNDIG (2026-06-18)
- VarKombi-Generator: Achsen-Hierarchie bekannt, Sub-Achsen = UNION + Suffix, nie Kreuzprodukt
- achsen_zuweisen.php: Granulare Sperrung — verwendete Werte 🔒, neue hinzufügen/freie löschen OK
- VariantenService: kein Vollblock mehr; nur in-use Werte+Achsen bleiben geschützt

### Lager-Modul (erp/public/lager/)
- Wareneingang mit EAN-Scan, Chargen-Tracking, Bewegungslog
- Schnell-Wareneingang aus Artikel-Detail

### Lieferanten-Modul (erp/public/lieferanten/)
- CRUD + Vertreter

### Berechtigungssystem
- 3 Rollen: superadmin, admin, mitarbeiter
- 47 Permissions im Format modul.aktion
- Audit-Log (aktivitaeten-Tabelle)

## 🔴 Noch nicht gebaut

| Modul | Priorität |
|---|---|
| Aktions-Modul | HOCH |
| Bilder-Upload | HOCH (vor Shop) |
| Bestellwesen/Einkauf | HOCH |
| Auftragsmodul/Verkauf | HOCH |
| Kasse/POS | HOCH (RKSV-Pflicht AT) |
| Inventur | MITTEL |
| Shop-Export | MITTEL |
| Buchhaltung/DATEV | MITTEL |
| Seriennummern | NIEDRIG |

## Aktuelle Baustelle (2026-06-18)

Varianten-System vollständig. Nächste Optionen:
1. **Aktions-Modul** — Lieferanten-Kampagnen mit kategorie-basierter Auto-Preissetzung
2. **Bilder-Upload** — Tab "Bilder" ist noch Platzhalter
3. **Bestellwesen/Einkauf** — Lieferantenbestellungen

## Offene technische Punkte

### Preis-Query Datums-Filter fehlt
JOIN auf artikel_preise hat keinen gueltig_ab/gueltig_bis Filter. Muss ergänzt werden wenn Sonderpreise aktiv genutzt werden.

### artikel_achsen.sort_order noch nicht genutzt
Feld existiert (Migration 024), aber kein UI zum Sortieren der Achsen-Reihenfolge pro Artikel.
