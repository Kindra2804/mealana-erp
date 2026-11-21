---
name: project_artikel_benennung
description: "2026-11-21 Babsi/Jacky: neues Schema Artikelnummer Hersteller-Vater-Kind(4-stellig), Name 'Vater Farbe [Art] (Nummer)'; betrifft Kind-Generator, JTL-Importer, MeaLana-Artikelübernahme"
metadata:
  type: project
---

**Entscheidung 2026-11-21:** Babsi sucht nach Farbnummer (sortiert nach Artikelnummer), nach Art wie Mix/Uni (sortiert nach Preis) oder nach Farbe (sortiert nach Name). Darum:
- **Artikelnummer** = Herstellerkürzel-Vater-Kind, Kind immer 4-stellig, z. B. `LN-113-0001`
- **Artikelname** = `Vater Farbe [Art] (Nummer)`, z. B. `Karisma natur [Uni] (01)`; in Klammern die echte Farbnummer

Kind-Generator (`public/artikel/varkombi_generator.php:122`, heute Vater-Nr + '-' + Wert) und JTL-Importer müssen umgebaut werden. Hersteller hat noch kein Kürzel-Feld.

Ausgangslage MeaLana-Übernahme: Babsis Ausschlussliste ausgewertet (`import/Babsi_Auswertung_2026-11-21/`, 11.591 Artikel, 117 Umbenennungen, Prüfliste beantwortet). Babsis Excel-Datei hat GTINs zerstört, deshalb nur Schlüssel + neue Nummern daraus verwenden. Vollständige Variationen: `import/JTL-Export-Variationskombinationen-21112026_kompletterArtikelstamm.csv`.
Auswertung der behaltenen 10.173 Kinder: etwa 7.900 mit Farb-Achse, etwa 2.100 Nadeln/Größen (Länge+Stärke usw.). Farbwerte: 4.071 „Farbe (Nr)“, 2.625 „Nr Farbe“, 1.495 nur Nummer, 1.621 sonstiges. Die Art [Uni/Mix] steht nirgends in den JTL-Daten.

**Why:** schnellere Kundenanfragen im Alltag. **How to apply:** offene Fragen und Vorschlag „JTL-Nummer im ERP mitspeichern statt in JTL umbenennen“ siehe Gespräch 2026-11-21; Zusammenhang [[project_jtl_parallelbetrieb]], [[project_jtl_vater_kind_import]].
