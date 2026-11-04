---
name: project-indra-sortiment
description: "Indra-Design-Sortiment: Motivcodes aus der Motivmappe (W/A/K/N/P/S/C), Produktarten je Motiv, Preisliste Stand 24.10.2025, geplante Artikelstruktur im ERP"
metadata:
  node_type: memory
  type: project
  originSessionId: ef009f0b-163c-454c-9dcc-cdd9cc4fc4bf
  modified: 2026-11-04T14:26:38.967Z
---

Quelle: Jackys „Motivmappe“ (PDF, älterer Stand, die neuen Motive fehlen) + Preisliste 24.10.2025, beide 2026-11-04 im Chat.

**Motivcodes** (je Motiv eine Legende, welche Produktarten möglich sind: Schlüsselanhänger · Schild · Untersetzer · Metallkarte · Korksticker (= Klebepad, sechseckig) · P = personalisierbar):
- **W01–W24** Schilder (rund, mehrschichtig; W22–24 Platzhalter). W12/W13/W14/W19/W20 personalisierbar (z. B. „Barbaras Nähzimmer“).
- **A01–A54** Handarbeiten allgemein (A25–A40 sechseckig = Korksticker, A41–A48 Metallkarten, A49–A54 Platzhalter)
- **K01–K18** Stricken · **N01–N12** Nähen · **P01–P06** Patchwork · **S01–S06** Spinnen (noch leer) · **C01–C12** Häkeln
- Gleiche Motive tauchen in mehreren Gruppen auf (z. B. W02 = N02 Atelier).

**Preise (Brutto):**
- Schilder 15/20/25/30/38 cm: Filz/Moosgummi 15/20/25/30/– · personalisiert (Moosgummi, nur auf Bestellung, 7 Tage) 25/30/35/40/48 · Holzrückseite 20/25/30/35/43 · Filz/Moosgummi + 3 mm Holz 22,50/27,50/32,50/37,50/– · Holz + Furnier 25/30/35/45/50
- Untersetzer: Kork Stk 10 cm 6,00 · 19 cm 22,00 · 30 cm 37,00 (Sonderbestellung) · Kork 4er-Set 20,00 · Kunstleder Stk 6,00 · Schiefer 10/19/25/30 cm 9,00/25,00/32,50/40,00 · Schiefer 4er-Set 34,00
- Untersetzer-Sets (1 × 19 cm + 4 × 10 cm): Kork 40,00 · Schiefer 55,00
- Schlüsselanhänger, Klebepads, Metallkarten: Preise noch offen

**Entscheidungen Jacky 2026-11-04:**
- Untersetzer = Vater je Motiv mit Material als Variante. Sets, Türschilder und Anhänger so wie von Claude vorgeschlagen (Set: Form; Anhänger: Lederfarbe).
- **Schilder vorerst nur als Motive anlegen.** Für sie wurde der Konfigurator gebaut ([[project-konfigurator-modul]]): Hintergrund Holz/Moosgummi/Filz in Farben, Motiv natur oder 1- bis 2-färbig (ca. 12 Farben), Glitzer usw. Welche Farben und Hintergründe „Standard“ werden, entscheidet Jacky später.
- Artikel zuerst im Dev anlegen, nach Live gezielt übertragen (Export/Import). Fotos: [[project-indra-design-shop]], [[project-newsletter-slider-ideen]].
- **✅ Im Dev angelegt 2026-11-04** (Backup vorher: D:\ERP\backups\mealana_erp_vor_indra_import_20261104.sql), Skripte im Scratchpad-Ordner indra_import/ dieser Session (daten.php = Zuordnung Foto→Motiv, bilder.php, import.php mit --schreiben, idempotent):
  - Artikelnummern `ID-U-<Motiv>` (Untersetzer, Achse **Material** id 67: KO10 Kork rund Ø 10 € 6 · KLS/KLB Kunstleder schwarz/braun 10×10 € 6 · SC10 Schiefer 10×10 € 9), `ID-SA-<Motiv>` (Achse **Lederfarbe** id 68, € 5,50), `ID-KP-<Motiv>` Klebepads € 4, `ID-SET-<A..I>` € 40, Schilder `ID-W02`–`ID-W21` (€ 15, personalisiert W12/13/14/19/20 € 25, keine_lagerbestandsfuehrung=1).
  - 45 Väter + 66 Kinder + 36 Einzelartikel, 173 Bilder (1600² JPG; Kork kreisrund freigestellt auf Creme). Kategorien unter IndraDesign: 421 umbenannt in „Schilder“, 423 in „Schlüsselanhänger“, neu 429 „Untersetzer-Sets“, 430 „Klebepads“.
  - Neue Codes: A55–A68 Sprüche, A69–A76 Klebepads, K19–K26. **K23** „Masche für Masche zum Glück“ (Herz) hat denselben Text wie K08, aber eine andere Grafik. **K01/K02** (eckig, Kaffee/Tee) gibt es nur in Leder/Schiefer.
  - Nicht importiert: die älteren Fotos (Fertige Untersetzer 2025, Kork-Renderings), Metallkarten. W06 und W09–W21 haben noch kein Bild. Lagerbestand = 0 (Startinventur kommt später).
- Im Dev existiert bereits `ID-W01` Holzschild „Wollzimmer“ (€ 15), Hersteller „IndraDesign“ (id 76), Kategorie „IndraDesign“ (420) mit Holzschilder 421, Untersetzer 422, Anhänger 423.
