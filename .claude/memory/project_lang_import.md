---
name: project-lang-import
description: "2026-11-16 Lang-Yarns-Import gebaut (Commit bdd367a): Artikelstamm-CSV → Garne + deutsche Bücher, Regeln + Entscheidungen Jacky, Live offen"
metadata:
  node_type: memory
  type: project
  originSessionId: c93adcf2-ed10-4b2f-ab3a-cb436120bb32
  modified: 2026-11-16T20:53:06.514Z
---

**Gebaut 2026-11-16** (Commit bdd367a, für Paket 1.0.23, keine Migration): Artikelliste → „⬇ Lang-Import“ (`artikel/lang_import.php`, `js/lang_import.js`, `src/modules/import/LangImportService.php` + `LangImportRepository.php`). Upload nach `storage/lang_import/`, Probelauf, Import je 20 Väter per AJAX, idempotent.

**Datei** (`import/LangYarns_Artikelstamm.csv`, Windows-1252, `;`): am Ende ~206 Zeilen HTML vom Lang-Webshop („Scripte“) → ignoriert. Status 99 = Vater, Kind `749.0003` → `LY-749-0003`. EP/KG = EK je kg (× KnGWEDI; ohne Gewicht = Stückpreis), VP/KN = UVP brutto, Spalten „… K“ = Kopien. HGrp 10 Garne, 20 RAW, 30 Bücher, 40 Nadeln, 50 Deco.

**Entscheidungen Jacky:** Status 35/40/55/61 nicht anlegen, vorhandene → Auslauf; nur HGrp 10 + 30, Zolltarifnummer mit (8-stellig aus Zollnummer_DE); EK + UVP immer, VK nur bei neuen; neue aktiv ohne Shop; ERP-LY nicht in Datei nur anzeigen („evtl. falsche Nummer, auf jeden Fall checken“).
**Meine Annahmen (Jacky bestätigen lassen):** Bücher = Einzelartikel, nur deutsche Fassungen, Gratis-Flyer weg; Vater mit eigenem Bestand bekommt keine neuen Farben; Treffer über EAN behalten ihre Nummer.

**Dev-Probelauf:** 48 Väter + 1.063 Farben + 24 Bücher neu, 1.479 aktualisieren, 401 Auslauf, 1.620 nicht in Datei (57 mit Bestand), 9 EAN-Treffer; kompletter Lauf 19 s in Rollback-Transaktion, 2. Lauf legt nichts doppelt an. Merkmale (Wollqualität, Pflegesymbole) nicht gesetzt; neue Artikel ohne Kategorie.

**Offen:** Paket bauen + Live (vorher DB-Sicherung, upload_max_filesize ≥ 10 MB), Kategorien für neue Lang-Artikel, Nicht-in-Datei-Liste durchgehen. Browser-Test `.claude-browser-tools/lang_import_ui_test.js` (nur lesend).

Siehe [[project-dropshipping]] (Lieferant 36 Lang Garn & Wolle GmbH), [[project-roadmap-reihenfolge]].
