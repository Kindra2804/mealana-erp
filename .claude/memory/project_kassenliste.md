---
name: project_kassenliste
description: "Kassen-Liste / Preisliste / Mini-Anhänger 60×30 mit Kurznamen — gebaut 2026-11-19 (069dd71, a5e6c18, Migr. 256), Notiz 1.0.27, Live + Scan-Test offen"
metadata:
  node_type: memory
  type: project
  originSessionId: 2c373746-fb14-4030-b558-93c75377b332
  modified: 2026-11-19T09:51:04.900Z
---

**Idee Jacky 2026-11-19:** Fachmieter-Kleinkram ohne EAN und Indra-Schlüsselanhänger (Anhänger 50×90 zu groß) → Liste neben der Kasse zum Scannen, normale Preisliste, kleine Anhänger 30×60. Entwurf `D:\ERP\kassenliste_mockup.svg/.png` (Generator im Scratchpad, echte Code-128-Codes).

**Entscheidungen Jacky:** Filter Kategorie/Hersteller/Partner reichen; „nur lagernde“ wählbar, Standard an; **Standardpreis** (Kasse nimmt Aktion beim Scannen selbst); Bild an; Mini: Loch **links** (60 breit × 30 hoch), Preis, Barcode, Nummer darunter, Kurzname; Kurzname nur im **Zwischenschritt**, kein Feld in allen Artikeln → eigene Tabelle `artikel_kurznamen` (Migr. 256).

**Gebaut:** `src/modules/schilder/KassenlisteService.php` (artikel(): jede Variante einzeln, Väter → Kinder, Varianten erben Hersteller/Partner/Kategorie über COALESCE mit Vater; Bestand alle Lager außer Messe/Händler-Außenlager; Barcode GTIN13/GTIN8 sonst Artikelnummer; kurzAuto(): Text in „…“ + Zusatz nach –), Vorlagen `preisschild/kassenliste.html.twig` (auch Preisliste, kompakt) + `mini.html.twig` (27/A4, 3×9, Rand 15/13,5 mm), Seite `public/schilder/kassenliste.php` + `kassenliste_drucken.php` + `js/kassenliste.js`, Seitenleiste Preisschilder → 🧾 Kassen-Liste, Massenaktion Artikelliste, Zugriffsregeln artikel.anzeigen, beide Handbücher, `docs/versionen/1.0.27.md`. Klicktest `.claude-browser-tools/kassenliste_test.js` (ändert nichts).

**Offen:** echter Scan-Test (Liste + Mini mit Jackys Scanner), Babsis Urteil zum Mini-Layout, Live mit nächstem Paket (Migr. 256). Nächste Migration **257**.

**How to apply:** Beim Ausbau [[project_preisschilder]] und [[project_kuvert_etikett]] mitdenken (Kasse sucht Artikelnummer scan-tolerant).
