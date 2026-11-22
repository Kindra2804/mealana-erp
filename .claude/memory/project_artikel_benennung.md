---
name: project_artikel_benennung
description: "2026-11-21 Babsi/Jacky: neues Schema Artikelnummer Hersteller-Vater-Kind(4-stellig), Name 'Vater Farbe [Art] (Nummer)'; betrifft Kind-Generator, JTL-Importer, MeaLana-Artikelübernahme"
metadata:
  node_type: memory
  type: project
  originSessionId: 80e0cd93-5868-409a-a6e4-214f7c72e810
  modified: 2026-11-22T11:09:56.884Z
---

**Entscheidung 2026-11-21:** Babsi sucht nach Farbnummer (sortiert nach Artikelnummer), nach Art wie Mix/Uni (sortiert nach Preis) oder nach Farbe (sortiert nach Name). Darum:
- **Artikelnummer** = Herstellerkürzel-Vater-Kind, Kind immer 4-stellig, z. B. `LN-113-0001`
- **Artikelname** = `Vater Farbe [Art] (Nummer)`, z. B. `Karisma natur [Uni] (01)`; in Klammern die echte Farbnummer

Kind-Generator (`public/artikel/varkombi_generator.php:122`, heute Vater-Nr + '-' + Wert) und JTL-Importer müssen umgebaut werden. Hersteller hat noch kein Kürzel-Feld.

Ausgangslage MeaLana-Übernahme: Babsis Ausschlussliste ausgewertet (`import/Babsi_Auswertung_2026-11-21/`, 11.591 Artikel, 117 Umbenennungen, Prüfliste beantwortet). Babsis Excel-Datei hat GTINs zerstört, deshalb nur Schlüssel + neue Nummern daraus verwenden. Vollständige Variationen: `import/JTL-Export-Variationskombinationen-21112026_kompletterArtikelstamm.csv`.
Auswertung der behaltenen 10.173 Kinder: etwa 7.900 mit Farb-Achse, etwa 2.100 Nadeln/Größen (Länge+Stärke usw.). Farbwerte: 4.071 „Farbe (Nr)“, 2.625 „Nr Farbe“, 1.495 nur Nummer, 1.621 sonstiges. Die Art [Uni/Mix] steht nirgends in den JTL-Daten.

## ✅ Entscheidungen 2026-11-22 (Jacky)
- **Kindnummer Farben** = echte Farbnummer auf 4 Stellen aufgefüllt (01 → 0001, 113 → 0113; ab 4 Stellen unverändert). Farben haben laut Jacky immer eine Nummer, nie nur Buchstaben → Ausreißer (V1, ge …) in der Vorschau markieren.
- **Name Farben** = `Vater Farbe [Art] (0001)`, Nummer in der Klammer IMMER 4-stellig; ohne Farbnamen `Hannah [Uni] (0001)`; [Art] nur, wenn bekannt.
- **Art** = Name der Unter-Achse mit `varianten_achsen.abhaengig_von_achse_id = 7` (Farbe): [Uni] 8, [Mix] 9, [Print] 22, [Long Print] 23, [Recycled Denim] 24. Dev: nur etwa 50 Väter einsortiert, etwa 720 Väter haben die Werte direkt unter Farbe (7) → Name dort vorerst ohne [Art].
- **Nadeln/Größen**: Artikelnummer bleibt (trägt schon die Herstellernummer, z. B. KP-20415); Name = `Vater Länge Stärke`.
- **Herstellerkürzel**: neues Feld hersteller.kuerzel, aus Anfang der Vaternummern vorbefüllt, in der Hersteller-Maske änderbar.
- **JTL NICHT umbenennen**: ERP speichert artikel.jtl_artikelnummer (+ jtl_kartikel); Abgleich/Archivimport/Kassen-Scan alter Etiketten über die JTL-Nr.
- Spalte für die Vaterachse im artikel: `vaterartikel_id`.
- JTL-Nr kommt in die vorhandene (leere) Tabelle `artikel_externe_referenzen` (datenquelle='jtl', externe_id=JTL-Nr; UNIQUE), statt neuer Spalte; wird beim Artikel-Löschen + import_zuruecknehmen schon aufgeräumt.

## Baufortschritt
- ✅ Schritt 1 (7970a3e, 2026-11-22): Migr. 258 hersteller.kuerzel, vorbefüllt, Feld in Hersteller-Maske + Liste. Auf Dev eingespielt. Falsche Vorschläge zum Korrigieren: PRYM→P (soll PRY?), MEALANA→WS, SCHEEPJES/OPRY→DB, Lord/Pracht→BEL, MyBoshi→LF. **Nächste Migr. 259.**
- ✅ Schritt 1b (2026-11-22): Migr. 259 Kürzel **max. 3** (VARCHAR(3), Prüfung im Service) + `hersteller.weitere_namen` (";"-getrennt, DROPS Design = "DROPS", weil JTL „DROPS“ liefert → früher „Hersteller unbekannt“). `HerstellerRepository::namensIndex()` in JTL-Vater/Kind-, Standard-CSV- und Lang-Import. **Nächste Migr. 260.**
- `artikel_externe_referenzen` war laut Designnotiz genau dafür gedacht: datenquelle 'jtl' = kArtikel (Interner Schlüssel), zusätzlich 'jtl_nr' = JTL-Artikelnummer.
- Aktionspreise hängen an artikel_id + sub_achse_id ([Uni]/[Mix], v. a. DROPS wegen Preisunterschied) → Unter-Achsen beim Umbau NUR lesen, nie ändern.
- ⏳ Bei Babsi (2026-11-22): `import/Babsi_Auswertung_2026-11-22/Hersteller_Kuerzel_fuer_Babsi.csv` (55 JTL-Hersteller, Vorschlag + Hinweise, Spalte „Kürzel Babsi“) und `Artikel_ohne_Hersteller.csv` (112 Väter/Einzel ohne Hersteller in JTL). Offen: „Sonstige“ (340 Väter, gemischte Nummern) – alte Nummern behalten? „DB“ ist bei mehreren Herstellern Anfang (SCHEEPJES, OPRY …). Wenn zurück: Kürzel ins ERP übernehmen (Skript).
- ✅ Schritt 2 (2026-11-22): **Vorschau „JTL: neue Nummern“** `public/artikel/jtl_benennung.php` (Artikelliste → ⬇ Import ▾). Regeln in `src/modules/import/ArtikelBenennung.php` (rein, ohne DB), Daten in `JtlBenennungVorschau.php`: JTL-Dateien in `erp/storage/jtl_benennung/` (stammdaten.csv + variationen.csv, Upload auf der Seite), basis.json-Cache; Ausschluss/Umbenennung/Kürzel bei jedem Aufruf frisch aus der DB. Filter, Hinweis-Chips, CSV. Achsen über `parseVariationskombinationen` (gleiche Zerlegung wie Import, erkennt [Uni]/[Mix] im JTL-Farbwert!). `kuerzeDropsName` jetzt public static.
  - Migr. 260 `import_umbenennung` (quelle, nummer_alt, nummer_neu). Babsis Auswahl eingelesen (Skript `import/Babsi_Auswertung_2026-11-22/babsi_auswahl_einlesen.php`, nur Dev!): 9.170 Ausschlüsse + 113 Umbenennungen (inkl. Prüfliste: PL-278480, SO-P-2000, EL-26; HY-Sharp Fixed wieder dazu; 4 Väter samt Kindern + 15 Väter ohne Kinder weg, LN-118 bleibt). **Für Live: Skript dort laufen lassen oder per Datentransfer.** **Nächste Migr. 261.**
  - Zusatzregel: Farbwert ohne Nummer → Nummer aus Kindnummer-Ende `-Ziffern` (Lang: LY-71-0324), Info-Hinweis grau.
  - **Problem:** Stammdaten-Export vom 11.11. ist unvollständig (956 Väter) – Variationen 21.11. hat 1.346 Väter → **390 Väter (~4.500 Kinder) hat Babsi nie gesehen** (Hinweis „von Babsi nicht geprüft“, ohne Hersteller). Braucht frischen vollständigen Stammdaten-Export aus JTL.
  - Offene Hinweise in der Vorschau: Leerzeichen in Nummer 707 (HY-Sharp Fixed …), Farbe ohne Nummer 333 (8S-…-rt, DMC e130, Hoooked sc02), Farbe + weitere Achse 216 (DROPS Lace Wickelservice), Nummer doppelt 50.
  - Offene Frage: Einzelartikel bekommen derzeit auch Kürzel-Rest (SO-613606 → bleibt ohne Kürzel) – Regel mit Jacky bestätigen.
- ✅ Schritt 2b (2026-11-22 Nachmittag):
  - Babsis Kürzel übernommen (15 Änderungen, Skript `import/Babsi_Auswertung_2026-11-22/kuerzel_uebernehmen.php`; Babsi schrieb in Spalte „Vorschlag“): PRYM=PRY, MEALANA=MEA, LYKKE=LYK, Schachenmayr SMC=SMC, Sonstige+Pracht=SO, Anchor=ANC, TOPP=TOP, Milward/DeBondt=DB … Gemeinsame Kürzel bewusst gelassen (DB, LY, SO). IndraDesign weitere_namen = „Indra Design“ (so in JTL).
  - Jacky hat **vollständigen JTL-Stammdaten-Export** abgelegt: `import/JTL-Export-Artikelstammdaten-22112026_kompletter Artikelstamm.csv` (26.709 Artikel, 1.346 Väter, 22.232 Kinder = passt zu Variationen; **keine Spalte „aktiv“** im Export). Liegt als stammdaten.csv in der Vorschau.
  - Migr. 261 `import_geprueft` (quelle, nummer): Babsis 11.11.-Liste = 3.299 Väter/Einzel als geprüft eingetragen (Skript `geprueft_einlesen.php`, benutzer_id NULL). Vorschau: Häkchen „übernehmen“ je Vater/Einzel (AJAX, js/jtl_benennung.js), Auswahl-Filter (übernommen / noch nicht geprüft / ausgeschlossen), Kachel „noch nicht geprüft“, Knopf „Seite als geprüft markieren“. **1.178 ungeprüft** (390 Väter, 788 Einzel) → Babsi entscheidet im ERP. **Nächste Migr. 262.**
  - Fix: numerische JTL-Nummern wurden durch PHP-Array-Schlüssel zu int → (string)-Cast in basis().
  - Offen: Marianne Hobby (5) + Witzgall (2) fehlen als Hersteller im ERP; CraSy, Vervaco, Stocker Verlag ohne Kürzel. Sonstige=SO ergibt z. B. SO-8S-B37642 / SO-2TTicket (Babsi zeigen). Einzelartikel-Regel bestätigt (Kürzel + Rest).
- ✅ 2026-11-22 (Jacky): Kinder, deren Nummer nicht aus der Farbe kommt (Farbe ohne Nummer, Farbe + weitere Achse, Nadeln, ohne Achse), bekommen trotzdem das Kürzel: Anfang = alte Vaternummer → durch neue ersetzt (8S-800086-rt → SO-8S-800086-rt), sonst Vater-Regel (8S-B10132 → SO-8S-B10132; KP-20415 bleibt). rechne() bekommt dafür 'jtl_nr' des Vaters.
  - Migr. 262 `import_vorgabe` (quelle, nummer, nummer_neu, hersteller_id) – Babsis Wunsch 2026-11-22: in der Vorschau ✎ je Vater/Einzel → fertige Nummer vorgeben (z.B. 7004KAZ → SO-KAZ-7004, KEIN Kürzel davor; Kinder folgen: SO-KAZ-7004-0001) und/oder Hersteller zuordnen (bestimmt Kürzel). `ArtikelBenennung::rechne` Option `nr_fest`. Info-Chips „Nummer vorgegeben“/„Hersteller zugeordnet“. Hersteller-Filter nach geltendem Hersteller. Nicht mehr benötigt = Häkchen raus (z.B. 1113KZ). **Nächste Migr. 263.**
- Live: Liste übertragen = import_ausschluss + import_umbenennung + import_geprueft + **import_vorgabe** + Herstellerkürzel/weitere_namen; Artikel holt der Import im Live direkt aus JTL (nicht Dev→Live kopieren). Neue JTL-Artikel tauchen später automatisch als „noch nicht geprüft“ auf.
- ✅ Schritt 3 (2026-11-22): JTL-Import (artikel/jtl_import.php) Häkchen „Babsis Liste + neues Nummernschema anwenden“ (Standard an). Gemeinsame Klasse `JtlImportListe` (Vorschau + Import rechnen identisch, Vergleich alt/neu 0 Unterschiede). Ausgeschlossenes + ungeprüftes übersprungen (Banner), neue Nr/Namen in der Kontrollliste (JTL-Nr klein darunter, weiter korrigierbar), Hersteller-Zuordnung gilt. `artikel_externe_referenzen`: 'jtl_nr' = JTL-Artikelnummer, 'jtl' = Interner Schlüssel (kArtikel). Artikel mit alter JTL-Nummer (früherer Import) wird **umnummeriert** (Nr + Name, Log jtl_import.umnummeriert) statt doppelt; neue Nr bei anderem JTL-Artikel → Fehler. Kategorie-Zuordnung sucht Stammdatenzeile über jtl_roh/jtl_nr. Babsi: 0 ungeprüft (Stand 22.11. abends). Keine Migration (nächste weiter 263).
- ✅ 2026-11-22 VarKombi-Generator (artikel/detail.php Tab Varianten) rechnet Vorschläge mit ArtikelBenennung (nr_fest = Vaternummer bleibt): Farbe mit Nr → Vater-0018 + „Vater braun [Uni] (0018)“, sonst Vater-Werte + „Vater Länge Stärke“. Hinweis-Chips (Nummer doppelt, Farbe ohne Nummer, Leerzeichen, **Nummer schon vergeben**). Alte Seite varkombi_generator.php leitet auf den Tab um. Schilder-Konfigurator nicht betroffen (keine Kinder). Im Dev viele doppelte Farbwerte (Ziegel/ziegel) → „Nummer doppelt“ = Datenpflege.
- ✅ 2026-11-22 Farbwerte im **Dev** bereinigt (Jacky: „alles klein und weiß“), Skripte in import/Babsi_Auswertung_2026-11-22/ (nicht in git): `farbwerte_bereinigen.php` (Farb-Achsen klein + weiss→weiß, gleiche Schreibweise je Vater/Achse zusammengelegt: 32 zusammen, 182 umbenannt), `farbwerte_tippfehler.php` (21 Kinder mit 2 Schreibweisen, richtige gewählt), `farbwerte_gruppe_entkoppeln.php` (93 Kinder bei 6 DROPS/Fenice-Vätern hingen an „Farbe X“ UND „[Uni] X“ → Farbe-Verbindung weg, Unter-Achse bleibt). Danach Generator ohne Doppelvorschläge. Kindernamen NICHT angepasst (kommen bei MeaLana neu). Live nicht betroffen (MeaLana kommt frisch).
- Große Übernahme: Jacky importiert **je JTL-Kategorie** (wie SOW); nicht mehr existierende JTL-Kategorien = „– nicht zuordnen –“ (Standard bei „neu“), feste Kategorie als Sicherheitsnetz.
- ▶ Offen: Wie läuft die große Übernahme praktisch (je JTL-Kategorie-Export wie bei SOW? Artikeltyp/-gruppe je Artikel in der Kontrollliste, max_input_vars)? Dann Lagerabgleich/Archiv-Aufträge/Kassen-Scan über 'jtl_nr', Live: Liste übertragen.

## Stand vom 2026-11-21 (Fragen inzwischen beantwortet, siehe oben)
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
