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

## ▶ HIER MORGEN (2026-11-22) WEITERMACHEN
Noch nichts gebaut. Stand Ende 2026-11-21:
- **Art [Uni/Mix/…] kommt über die Achsen** (Jacky, Screenshot): Die Achse FARBE hat Unter-Gruppen „↳ [MIX]“, „↳ [UNI]“, darin die Farbwerte (z. B. „77 EICHE HELL“, „01 NATUR“). Also kein eigenes Merkmal, die Art kommt aus der Unter-Achse, in der der Farbwert liegt. Vorher prüfen, wie diese Unter-Achsen in der DB abgebildet sind (varianten_achsen / varianten_achse_werte).
- **Offene Fragen, morgen mit Jacky klären:**
  1. Kindnummer 4-stellig = echte Farbnummer aufgefüllt (01 → 0001) oder fortlaufend? Was bei mehr als 4 Stellen oder Buchstaben (V1, ge, 011010173)?
  2. (erledigt durch Achsen) Art-Liste = Unter-Achsen
  3. Nadeln/Größen (etwa 2.100 Kinder, Länge+Stärke): Namens- und Nummernschema?
  4. 1.495 Kinder nur mit Nummer, ohne Farbname: vorerst „Hannah [Uni] (001)“?
  5. Herstellerkürzel = heutiger Anfang der Vaternummer (LN, PRY, BC)? Neues Feld hersteller.kuerzel
- **Vorschlag Claude (Jacky hat noch nicht zugestimmt):** NICHT in JTL umbenennen. Das ERP speichert beim Import artikel.jtl_artikelnummer + jtl_kartikel (kArtikel = „Interner Schlüssel“ im Stammdaten-Export). Lagerabgleich (Ameise braucht die JTL-Nummer), JTL-Archiv-Auftragsimport (JtlArchivImportService findByArtikelnummer) und der Kassen-Scan alter Etiketten laufen dann über die JTL-Nummer. Jacky wollte ursprünglich in JTL umbenennen und die Ameise testen.
- **Vorgeschlagene Reihenfolge:** 1) Migration (hersteller.kuerzel, artikel.jtl_artikelnummer/jtl_kartikel) → 2) Vorschauseite im ERP alt/neu Nr+Name für Babsi (NICHT über Excel, zerstört GTINs) → 3) Importer neu → 4) Kind-Generator → 5) Abgleich/Auftragsimport über JTL-Nr.
- **Prüfliste beantwortet** (import/Babsi_Auswertung_2026-11-21/Pruefliste_fuer_Babsi.csv): Tippfehler → PL-278480 und SO-P-2000; ELI-SW: auch die Kinder umbenennen (EL-LE59-xx); EL26-Apollo: Vater → EL-26; KZ4001: Kinder → KZ-4001-…; HY-Sharp Fixed: Vater wieder mitnehmen; KP-23247, OV-Schkl, ART-40/2013, ART-51/2013: komplett weg (Kinder auch); 15 Väter ohne Kinder: weg; LN-118 POEMA: Vater bleibt leer (neue Farben kommen). Noch NICHT in die bereinigte Datei eingearbeitet (wird ohnehin mit dem neuen Schema neu erzeugt). Hilfsskripte lagen im Scratchpad (vergleich/pruef/bauen.php), bei Bedarf neu schreiben.
- Danach (laut Plan 2026-11-19): Ausschluss-Filter JTL-Importe, „Bareingang zu JTL-Rechnung“ an der Kasse, Checkliste/Ablaufplan Umstellung ([[project_jtl_parallelbetrieb]]).

**Why:** schnellere Kundenanfragen im Alltag. **How to apply:** offene Fragen und Vorschlag „JTL-Nummer im ERP mitspeichern statt in JTL umbenennen“ siehe Gespräch 2026-11-21; Zusammenhang [[project_jtl_parallelbetrieb]], [[project_jtl_vater_kind_import]].
