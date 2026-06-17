---
name: project-merkmale
description: "Merkmale-Modul Design — DB-Struktur, UI-Konzept, WooCommerce-Vorbereitung"
metadata: 
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

Stand: 2026-06-17 (Design abgestimmt, Implementierung startet)

## Konzept

Merkmale = Produktattribute (Maschenprobe, Garngruppe, Nadelstärke, Waschempfehlung...)
Immer exakt 2 Ebenen tief: Merkmal → Wert. Nie tiefer.

Single-select (Radio): Maschenprobe, Garngruppe
Multi-select (Checkbox): Nadelstärke, Waschempfehlung

## DB-Struktur (Ziel nach Migration)

```
merkmale
  id, name, slug (für WooCommerce pa_xxx), datentyp (text/zahl/bool),
  filterbar, mehrfach_auswahl, sort_order, aktiv

merkmal_artikeltypen          ← NEU (Sichtbarkeit nach Artikeltyp)
  merkmal_id, artikeltyp_id
  (leer = Merkmal gilt für alle Artikeltypen)

merkmal_werte                 ← NEU (Level 2 — vordefinierte Werte)
  id, merkmal_id, wert, sort_order

artikel_merkmale              ← VEREINFACHT (war wert_text/wert_zahl/wert_bool)
  id, artikel_id, merkmal_id, merkmal_wert_id
```

Bestehende `merkmal_gruppen`-Tabelle (Garninfo, Verarbeitung, Pflege) bleibt
als optionale Anzeigegruppe im Admin, aber nicht Teil der Hierarchie.

Bestehende `artikel_merkmale`-Daten (wert_text/wert_zahl) müssen migriert werden:
- Freitext-Werte → in `merkmal_werte` heben + in `artikel_merkmale` verknüpfen

## UI-Konzept

### Admin (merkmale_verwalten.php) — wie kategorien_verwalten.php
- Level 1: Merkmal anlegen (Name, Slug, Single/Multi, Artikeltypen-Filter, filterbar)
- Level 2: Werte mit ▲/▼ Sortierung direkt darunter
- Rechte-Bindung kommt später (Artikelbearbeiten-Rechte), vorerst Admin-only

### Artikel-Detail (Tab "Merkmale")
- Zeigt nur Merkmale die zum Artikeltyp passen (merkmal_artikeltypen)
- Single-select → Radio-Buttons im Modal
- Multi-select → Checkboxen im Modal
- Modal-Muster: wie Kategorie-Modal (Button → Modal mit Liste → Übernehmen)
- Chips-Anzeige der gewählten Werte

## WooCommerce-Vorbereitung
- merkmale.slug → pa_{slug} in WooCommerce
- merkmale.filterbar → WC layered navigation filter
- merkmal_werte.wert → WC attribute terms
- mehrfach_auswahl → WC "used for variations" vs. display only

## Migrations-Reihenfolge
1. Migration: merkmal_werte, merkmal_artikeltypen, merkmale-Spalten (slug, mehrfach_auswahl, sort_order)
2. Admin-Seite: merkmale_verwalten.php (AJAX CRUD + ▲/▼ Sort, 2-Ebenen)
3. Artikel-Detail: Tab "Merkmale" mit Modal-Auswahl
