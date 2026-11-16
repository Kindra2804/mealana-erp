---
name: project-lang-import
description: "2026-11-16 Lang-Yarns-Import gebaut (bdd367a, 0dd1a24, Migr. 254): Artikelstamm-CSV → Garne + dt. Bücher, Abwahl, Preisänderungen, Strecke, ohne UVP; Live offen"
metadata:
  node_type: memory
  type: project
  originSessionId: c93adcf2-ed10-4b2f-ab3a-cb436120bb32
  modified: 2026-11-16T20:53:06.514Z
---

**Gebaut 2026-11-16** (Commit bdd367a + 0dd1a24, für Paket 1.0.23, Migration 254; nächste 255): Artikelliste → „⬇ Lang-Import“ (`artikel/lang_import.php`, `js/lang_import.js`, `src/modules/import/LangImportService.php` + `LangImportRepository.php`). Upload nach `storage/lang_import/`, Probelauf, Import je 20 Väter per AJAX, idempotent.

**Datei** (`import/LangYarns_Artikelstamm.csv`, Windows-1252, `;`): am Ende ~206 Zeilen HTML vom Lang-Webshop („Scripte“) → ignoriert. Status 99 = Vater, Kind `749.0003` → `LY-749-0003`. EP/KG = EK je kg (× KnGWEDI; ohne Gewicht = Stückpreis), VP/KN = UVP brutto, Spalten „… K“ = Kopien. HGrp 10 Garne, 20 RAW, 30 Bücher, 40 Nadeln, 50 Deco.

**Entscheidungen Jacky:** Status 35/40/55/61 nicht anlegen, vorhandene → Auslauf; nur HGrp 10 + 30, Zolltarifnummer mit (8-stellig aus Zollnummer_DE); EK + UVP immer, VK nur bei neuen; neue aktiv ohne Shop; ERP-LY nicht in Datei nur anzeigen („evtl. falsche Nummer, auf jeden Fall checken“).
**Bestätigt Jacky:** Bücher = Einzelartikel, nur deutsche Fassungen; Vater mit eigenem Bestand bekommt keine neuen Farben; EAN-Treffer behalten ihre Nummer, aber markiert anführen → Babsi bessert auf Lang-Nummer aus („damit alle Garne gleich aussehen“).
**Nachtrag 2026-11-16 (0dd1a24, Migr. 254 `import_ausschluss`):** Väter abwählbar im Probelauf (bleibt gespeichert, quelle 'lang' + Vaternummer); Preisänderungen markiert (EK alt/neu ▲▼ gruppiert je Garn, unser VK vs. Lang-VP; Dev: 548 Farben EK gestiegen); **UVP wird nicht übernommen** (VP nur als VK neuer Artikel + Vergleich); Garne aus der Liste `artikel_lieferanten.dropshipping = 1`, Auslauf = 0. **Strecke (Jacky, 7036a3c):** „grundsätzlich Strecke“ war nur Kompromiss ohne Liste → Import stellt Lieferant `dropshipping_aktiv = 0` (Pauschale bleibt, Anzeige „Nur markierte Artikel“), Garne aus der Liste = 1, Auslauf = 0, nicht mehr gelistete mit händischem 1 → NULL. Eigener Bestand anderer Lang-Artikel nur noch aus unserem Lager.

**Dev-Probelauf:** 48 Väter + 1.063 Farben + 24 Bücher neu, 1.479 aktualisieren, 401 Auslauf, 1.620 nicht in Datei (57 mit Bestand), 9 EAN-Treffer; kompletter Lauf 19 s in Rollback-Transaktion, 2. Lauf legt nichts doppelt an. Merkmale (Wollqualität, Pflegesymbole) nicht gesetzt; neue Artikel ohne Kategorie.

**Paket 1.0.23 neu gebaut** 2026-11-16 (D:/ERP/pakete/nahtlos-1.0.23.zip, Commit a133f42, Migr. 254 + 255; nächste 256) – 1.0.23 war nur auf Dev, deshalb Fix hineingenommen statt 1.0.24.
**Fund Jacky (LY-1168-0002):** `VariantenRepository::insertKindArtikel` speicherte nur 10 der ~25 Vater-Felder (Inhalt, Gewicht, Grundpreis, Texte, Maße, TARIC fehlten bei JEDEM Generator-Kind, bis der Vater gespeichert wurde – wohl auch Ursache der 841 Väter vom 2026-08-11). Gefixt (alle Felder) + Migration 255 füllt nur LEERE Kinderfelder vom Vater (eigene Werte bleiben; nicht `backfill_vater_kind_sync.php`, das überschreibt). Dev: 1.129 Kinder repariert, 66 davon im Shop. **Offen:** Live einspielen (vorher DB-Sicherung, upload_max_filesize ≥ 10 MB), Kategorien für neue Lang-Artikel, Nicht-in-Datei-Liste durchgehen. Browser-Test `.claude-browser-tools/lang_import_ui_test.js` (nur lesend).

Siehe [[project-dropshipping]] (Lieferant 36 Lang Garn & Wolle GmbH), [[project-roadmap-reihenfolge]].
