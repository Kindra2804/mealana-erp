---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata: 
  node_type: memory
  type: project
  originSessionId: f42de806-6c53-4c08-b255-b4829200b8a1
---

Stand: 2026-06-17

## Git Repository
`D:/ERP/mealana/` — nicht in `D:/ERP` suchen!
Commit-Befehl: `git -C "D:/ERP/mealana" ...`

## Schema-Referenz
- Aktueller Dump: `mealana/erp/database/schema_current.sql` (33 Tabellen, Stand 2026-06-11)
- Bei neuen Migrations: Dump aktualisieren mit `& "C:\xampp\mysql\bin\mysqldump.exe" --host=localhost --user=root --no-tablespaces --routines --skip-comments mealana_erp | Out-File -FilePath "D:\ERP\mealana\erp\database\schema_current.sql" -Encoding utf8`

## Heute abgeschlossen (2026-06-11)

### Varianten-System DB (Migrations 022–027) ✅
- 022: varianten_achsen (global, darstellungsform VARCHAR statt ENUM)
- 023: varianten_achse_werte (pro Artikel+Achse, aufpreis, wert_zusatz für Hex)
- 024: artikel_achsen (pro Artikel, inkl. bedingte Achsen bedingungs_achse_id/bedingungs_wert_id)
- 025: varianten_kombination_werte (KIND-Artikel → varianten_achse_werte, composite PK)
- 026: Datenmigration (bestehende farbe_name/farbe_hex → neues System)
- 027: Aufräumen (farbe_name, farbe_hex, varianten_darstellung aus artikel entfernt)

### PHP-Bereinigung farbe_name/farbe_hex/varianten_darstellung ✅
Alle Referenzen entfernt aus ArtikelRepository, ArtikelService, speichern/aktualisieren/bearbeiten/neu, variante_bearbeiten/neu, detail.php, LagerRepository, lager/variante_suche/wareneingang/nachtrag_liste

### Sonstiges ✅
- ueberverkauf_erlaubt: UI komplett (checkbox, banner, whitelist, fallback)
- reservierungen-Tabelle (Migration 021)
- schema_current.sql neu gedumpt (33 Tabellen)

## Heute abgeschlossen (2026-06-11, Session 2)

### Varianten-System UI Schritt 1+2 ✅
- AchsenRepository + AchsenService (src/modules/achsen/)
- public/achsen/ — volles CRUD (liste, neu, bearbeiten, loeschen, speichern, aktualisieren)
- VariantenRepository + VariantenService (src/modules/varianten/) — Delete-and-Reinsert
- public/artikel/achsen_zuweisen.php + achsen_speichern.php
- nav.php: "📐 Achsen" Link hinzugefügt

## Heute abgeschlossen (2026-06-12)

### Varianten-System UI komplett ✅
- VarKombi-Generator (varkombi_generator.php): editierbare Tabelle, hat_eigenen_lagerstand-Frage, existing Kombis read-only
- varkombi_erstellen.php: POST-Handler, erbt steuerklasse_id/artikeltyp_id/einheit_id/charge_pflicht vom Vater
- VariantenRepository: findWerteByIds, insertKindArtikel, insertKombinationWert
- VariantenService: erstelleKombinationen

## Heute abgeschlossen (2026-06-13)

### UI-Redesign Shell + Components ✅
- variables.css, layout.css, components.css (Card, Buttons, Chips, Table, Filter-Bar)
- shell_top.php (dynamisch: $pageTitle, $activeModule, $actionBarContent, Sidebar per match())
- shell_bottom.php, logo.png deployt
- liste.php migriert auf Shell + alle Component-Klassen + Action Bar

### Pagination Artikelliste ✅
- ArtikelRepository: findAll(LIMIT/OFFSET) + countAll()
- ArtikelController: count()
- liste.php: Seite/ProSeite aus GET, Offset, Pagination-HTML mit aktiver Seite, Zeilen/Seite-Dropdown

## Heute abgeschlossen (2026-06-13, Session 2)

### Artikel detail.php — Lieferanten-Tab ✅
- Tabelle mit allen Lieferant-Feldern (EK, VPE, Lieferzeit, Mindestabnahme, Standard, Währung)
- hover-reveal Aktionsbuttons (CSS-Pattern, wiederverwendbar)
- Modal für Neu + Bearbeiten (ein Modal, zwei Modi via alId-Parameter)
- data-Attribute Pattern auf `<tr>` für Edit-Prefill ohne AJAX-Roundtrip
- `artikel_lieferant_speichern.php`: INSERT/UPDATE mit PDO Prepared Statements (SQL-Injection-safe)

## Heute abgeschlossen (2026-06-14)

### Preise-Modul — Planung + Migrations + Tabs ✅
- Migration 028–031: artikel_zustand, artikel.uvp/preise_vererben, artikel_staffelpreise, preis_aktionen
- PreisRepository + PreisService (src/modules/preise/)
- detail.php Preise-Tab: KG-Preise, Staffelpreise, Preis-Aktionen-Card
- preis_speichern.php, staffelpreis_speichern.php, staffelpreis_loeschen.php, preis_loeschen.php

### Bug Fix: Dezimalmenge Schnell-WE ✅
- Migration 032: artikel_typen.teilbar
- detail.php WE-Modal: step/min dynamisch aus artikeltyp_teilbar
- lager_schnell_we.php: serverseitige Validierung

### Zustandsartikel-System komplett ✅
- Migration 033: artikel.zustand_vater_id
- ArtikelRepository/Service: CRUD + Suche + Validierung
- neu.php, bearbeiten.php: Zustand-Dropdown + Vater-Suche
- liste.php: Zustandsartikel eingerückt unter Vater + blauer "!"-Badge
- detail.php Lager-Tab: Card "B-Ware / Zustandsartikel"

### Artikelliste Redesign + Verbesserungen ✅
- Spaltenstruktur: [▶] | Thumb | ART.-NR. | STATUS | ARTIKELNAME | KANÄLE | BST. | PREIS
- STATUS-Chips, hover-reveal Aktionsbuttons, Smart Pagination, Sortierung ▲/▼
- Filter: Status, Kategorie, Freitext — onchange Auto-Submit
- Toggle "alle zuklappen"
- ⚠-Indikatoren (Preis/Auslauf/Inaktiv/Überverkauf Abweichungen)

## Heute abgeschlossen (2026-06-14, Session 7)

### Kategoriebaum komplett ✅
- KategorieRepository + ArtikelService: getKategorienBaum(), getAlleNachkommenIds()
- shell_top.php: rekursiver Baum mit localStorage open/close-State
- kategorie_erstellen/bearbeiten/loeschen_ajax.php
- Sidebar-Filter rekursiv (Artikel aus Kategorie + allen Unterkategorien)
- Kategorielos-Chip + Filter "Ohne Kategorie"

## Heute abgeschlossen (2026-06-15)

### Shell-Migration + CSS-Cleanup ✅
- neu.php, bearbeiten.php: Shell-Integration
- `<style>`-Blöcke aus allen Views entfernt
- components.css: alle ausgelagerten Klassen konsolidiert

### detail.php Stammdaten-Tab Redesign ✅
- 2-Spalten-Grid: Kern-Daten + Einstellungen
- Kategorie-Baum-Modal (rekursiv mit ├─/└─ Konnektoren)
- EAN-Duplikat-Check (blur → ean_check.php → blaues warn-badge)
- Artikelnummer-Fallback ART-001, ART-002...

## Heute abgeschlossen (2026-06-16)

### Artikelliste UX ✅
- Sortierung: klickbare Spaltenköpfe mit ▲/▼/↕, SQL-safe Whitelist
- localStorage: aufgeklappte Vater-Artikel bleiben nach Seitennavigation erhalten
- "ab X,XX €"-Prefix beim Vaterpreis wenn mind. 1 Kind teurer ist

### Kategorie-Sortierung ✅
- KategorieRepository: getSiblingsWithSort + updateSortierung
- kategorie_sort_ajax.php: normalize + swap-Logik

### Achsen-Liste Shell-Redesign + Sortierung ✅
- achsen/liste.php: komplett neu mit Shell-Layout, erp-table, ▲/▼ sort

### Achsen-Modal in detail.php ✅
- Varianten-Tab: "Achsen bearbeiten"-Button öffnet Modal statt eigene Seite
- Gesperrte Werte (in varianten_kombination_werte referenziert): 🔒 statt ✕
- achsen_zuweisen_ajax.php: Smart-Update statt DELETE-ALL (KRITISCHE Sicherheitslücke behoben)

### SEO-Tab in detail.php ✅
- seo_speichern.php: eigener Endpunkt (direktes PDO, 3-Feld UPDATE)
- components.css: .erp-textarea

### Datenfehler artikel_achsen behoben ✅
- VariantenRepository::isKindArtikel() + Guard in achsen_zuweisen_ajax.php
- Varianten-Tab für Kind-Artikel ausgeblendet

## Heute abgeschlossen (2026-06-17)

### detail.php — Unsaved-Indicator, Flash-Banner, Logger ✅
- `components.css`: Abschnitt "BANNERS / HINWEISE"; `.unsaved-indicator` (oranger Punkt + kursiver Text), `.success-banner`, `.error-banner`
- `detail.php`: Unsaved-Indicator in `$actionBarContent` (3-Spalten: links/mitte/rechts, kein Layout-Shift)
- `layout.css`: `.actionbar-left` (neu) + `.actionbar-right` (`flex: 1; justify-content: flex-end`)
- `detail.php`: PHP Flash-Banners — Session auslesen + unset; Erfolg Auto-Hide 3s, Fehler bleibt
- Logger ergänzt: `PreisService` (4 Methoden), `ArtikelService` (3 Kategorie-Methoden), `seo_speichern.php`, `artikel_lieferant_speichern.php`

## Nächste Schritte

1. **Massenauswahl-Checkbox** in Artikelliste — Lerneinheit für Karl
2. Spalten-Anpassung (Column Picker) in Artikelliste — Lerneinheit für Karl
3. **Achsen-Sortierung pro Artikel** (KLEIN) — `artikel_achsen.sort_order` nutzen

## Bug-Queue

### Dezimalmenge Schnell-WE (erledigt 2026-06-14)
Fix bereits eingebaut via artikel_typen.teilbar

## Offene technische Punkte

### Preis-Query Datums-Filter fehlt
Der aktuelle JOIN auf `artikel_preise` hat keinen Datums-Filter. Wenn Sonderpreise (gueltig_ab/gueltig_bis) gebaut werden:
```sql
AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= CURDATE())
AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= CURDATE())
```
Muss in ArtikelRepository.php (findById, findKinder-Query, etc.) ergänzt werden.

### artikel_achsen.sort_order noch nicht genutzt
Feld existiert (Migration 024), aber kein UI zum Sortieren der Achsen-Reihenfolge pro Artikel.

## Danach: Neue Module (Reihenfolge)
1. Kundendatenbank
2. Bestellwesen / Einkaufsmodul
3. Kasse (RKSV/Fiskaly)
4. Packplatz/Picklisten
5. Versandmodul
6. Bedingte Achsen — UI + Generator-Logik
7. Shop-Anbindung REST API

## Fertig (vollständige Liste)
- Artikel CRUD + Kopieren + Lieferanten-Tab + Filter
- Varianten (Kind-Artikel) CRUD — Achsen/Werte/Kombinationen UI fertig
- Lager: Wareneingang, EAN-Scan, Chargen, Nachtrag-Workflow
- Lieferanten CRUD inkl. Vertreter
- Auth + RBAC (3 Rollen, 47 Berechtigungen)
- Auslaufartikel (auto-deaktivierung/-reaktivierung)
- ueberverkauf_erlaubt + reservierungen
- Varianten-System DB komplett (Achsen, Werte, Kombinationen)
- Preise-Modul (KG-Preise, Staffelpreise, Aktionen-Placeholder)
- Zustandsartikel-System
- Kategoriebaum (CRUD, Sortierung, rekursiver Filter)
- SEO-Tab
- UI-Shell komplett (Shell-Top/Bottom, CSS-System, Action-Bar)
