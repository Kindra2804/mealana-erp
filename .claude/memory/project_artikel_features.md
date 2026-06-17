---
name: projekt-artikel-features
description: "Offene und geplante Features im Artikel-Modul — was fertig ist, was noch offen"
metadata:
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

Stand: 2026-06-17 (Vollständig neu abgeglichen mit tatsächlichem Code-Stand)

## ✅ FERTIG — bereits vollständig implementiert

- **Artikel-CRUD** — neu.php, bearbeiten.php, detail.php (7 Tabs), kopieren.php, delete.php
- **Varianten-System mit Achsen** — varianten_achsen, varianten_achse_werte, artikel_achsen, varianten_kombination_werte (Migrations 022-027); VarKombi-Generator mit kartesischem Produkt, Achsen-Modal in detail.php
- **Preise komplett** — Kundengruppen-Preise, Staffelpreise, UVP, Preis-Aktionen (Migrations 028-031), Tab "Preise" in detail.php
- **SEO-Felder** — meta_titel, meta_description, url_slug auf artikel (Migration 017), Tab "SEO" + seo_speichern.php
- **Artikel-Texte** — kurzbeschreibung, beschreibung, technische_details, beschreibung_intern (Migration 017), in Stammdaten-Tab
- **Gewicht + Maße** — laenge, breite, hoehe, gewicht_artikel, gewicht_versand, versandklasse_id (Migration 018)
- **Zustandsartikel** — zustand VARCHAR(30) DEFAULT 'neu' (Migration 028), zustand_vater_id (Migration 033), alle 8 Zustände (neu/gebraucht/generalueberholt/beschaedigt/retour/demo/muster/ausstellungsstueck), Zustandsartikel in liste.php eingerückt unter Vater
- **Auslaufartikel** — ist_auslaufartikel, auslauf_mit_vater (Migrations 016, 035), Kaskaden-Logik
- **Überverkauf-System** — ueberverkauf_erlaubt, reservierungen-Tabelle (Migrations 020-021)
- **Einheiten** — einheiten-Tabelle inkl. teilbar-Flag (Migrations 013, 032)
- **Lieferanten-Tab** — CRUD, Modal, AJAX-Save, standard_lieferant Flag (detail.php Tab "Lieferanten")
- **Artikel-Liste** — Spalten-Picker (user-spezifisch), Massenauswahl, Sticky-Spalten, Loop-basiertes Rendering
- **Chargen-Tracking** — charge_pflicht, lager_bewegungen mit charge-Spalte
- **Lager & Wareneingang** — wareneingang.php, EAN-Scan, LagerService, Bewegungslog
- **deaktiviert_mit_vater / auslauf_mit_vater** — Kaskaden-Logik bei Vater-Deaktivierung (Migrations 034-035)
- **Hersteller** — hersteller-Tabelle, FK auf artikel
- **Kategorien** — Baum-Manager (AJAX CRUD, Drag-Drop Sort), Viele-zu-Viele auf artikel
- **Lieferanten-Modul** — lieferanten/, CRUD + Vertreter
- **Achsen-Modul** — achsen/, globale Achsenverwaltung

## ⚠ PLATZHALTER — Tabellen da, UI fehlt noch

- **Merkmale-UI** — Design abgestimmt 2026-06-17, Implementierung läuft. Siehe [[project-merkmale]].
- **Bilder-Upload** — Tab "Bilder" in detail.php ist Platzhalter. Backend fehlt komplett. Morgen.

## ✅ Fertig (heute 2026-06-17 ergänzt)

- **Filterung in der Liste** — Hersteller, Artikeltyp, Status, Kategorie-Filter — fertig
- **Artikeltyp als Spalte** im Spalten-Picker (sortierbar) — fertig
- **"Ungespeicherte Änderungen"-Banner** — fertig (commit 751f5ee)
- **Kategorie-Modal Bug** — War in tab-seo (versteckt) eingeschlossen — gefixt (commit af5b17d)

## 🔴 NOCH OFFEN im Artikel-Modul

- **Merkmale-UI** — In Arbeit (Migration + Admin + detail.php Tab)
- **Bilder-Upload** — morgen
- **Qualitätslisten** — "Welche Varianten haben keine EAN?", "Doppelte EAN?" — geplant, niedrige Prio

## 🔜 MIT ANDEREN MODULEN (nicht jetzt)

- **Staffelpreise Lieferanten-EK** — kommt mit Einkaufsmodul
- **Bestellvorschläge** — Artikel unter Mindestbestand, Saisonware-Logik
- **Shop-Export** — url_slug, meta_*, Bilder → WooCommerce/Shopware-Adapter
- **Kasse** — Bestätigungsdialog für zustand != 'neu'
- **Inventur** — eigenes Modul, Platzhalter-Spalte in Artikelliste bereits vorbereitet
- **Seriennummern** — geplant
- **Mehrsprachigkeit** — vorbereiten (artikel_translations), aber nicht jetzt

## Spalten-Picker: Platzhalter-Status

| Schlüssel | Status |
|---|---|
| merkmale | ⏳ Platzhalter bis Merkmale-UI fertig |
| lagerplatz | ⏳ Platzhalter bis lagerplaetze-Tabelle + Modul |
| letzte_inventur | ⏳ Platzhalter bis Inventur-Modul |
| artikeltyp | 🔴 Noch nicht eingebaut — kommt mit Filterung |
