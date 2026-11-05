---
name: project-bio-wolle-shop-design
description: "bio-wolle.at: Logo + Startseite nach SOW-Ablauf; Schwerpunkt GOTS/Bio; 6 Logo-Ideen + 3 Grüntöne (Salbei/Moos/Tanne) warten auf Babsi"
metadata:
  node_type: memory
  type: project
  originSessionId: 745e566f-eead-45c0-b033-974ece3f704d
  modified: 2026-11-05T15:44:58.997Z
---

Start 2026-11-05, gleicher Ablauf wie bei [[project-sow-shop-design]]: Logo → Startseite → Slider/Kacheln/Menü aus dem ERP befüllen (alles schon gebaut, siehe [[project-shop-menue]], [[project-newsletter-slider-ideen]]).

- **Seite:** https://bio-wolle.at/ (derzeit fast leer).
- **Altes Logo:** zweizeilig „Bi[Baumwollkapsel mit grünem Blatt]-/Wolle“, dünne Schrift (Josefin-Sans-artig), schwarz. Jacky: gerne nachbessern.
- **Menü (kennen die Kunden):** Hersteller, Zusammensetzung, Nadelstärke, Lauflänge/50 g, Maschenprobe/10 cm, Bücher/Anleitungen, Sale (nur wenn Artikel drin). Kategorien gibt es im ERP schon als eigene Wurzelkategorien, noch ohne Artikel.
- **Abgrenzung zu SOW:** GOTS- und Bio-Siegel im Vordergrund, der Shop ist darauf spezialisiert. Eigenständig wirken, Wollboutique nur im Footer (wie bei SOW).
- **Hausfarbe grün**; Babsis Wahl Salbei/Moos/Tanne und Stil (natürlich/verspielt/schlicht) steht noch aus. Darum haben wir ihr einen Vorschlag gemacht.

**Entwurf 1 (2026-11-05):** Skript `.claude-browser-tools/bio_logo.js` (Aufruf `node bio_logo.js <zielordner>`), Ergebnis im Scratchpad der Session 745e566f…/bio_logo/: `logo_ideen.png` (1 Weiterentwickelt/Kapsel, 2 Knäuel mit Blatt, 3 Schaf, 4 Siegel, 5 Blatt als i-Punkt, 6 Faden wird Blatt) und `gruentoene.png` (Salbei #5f7a5b + Ton, Moos #5a6b2a + Honig, Tanne #2f5a45 + Holz; Shop-Kopf-Mockup). Mein Favorit: Idee 2 in Tanne. Das Siegel darf keine Zertifizierung behaupten und das GOTS-Zeichen nicht nachbauen; das echte GOTS-Logo nur bei zertifizierten Artikeln zeigen (Lizenzbedingungen GOTS).

**Stand 2026-11-05 abends:** Jacky schwankt zwischen Logo 1 und 2, **Moos ist der Favorit**. Vergleichsblatt `moos_logo1.png` (Aufruf `node bio_logo.js <ordner> 1`): 1 wie bisher (Light), 1a Regular + Kapsel ×1,15, 1b „-Wolle“ in Moos, 1c alles in Moos, 1d Semibold (Bi Dunkelgrün, Wolle Moos), 2 zum Vergleich. Mein Rat: 1b oder 1d. Die Antwort von Babsi steht noch aus.
**Danach: Babsi findet, dass Tannengrün am Handy besser wirkt** → `tanne_logo1.png` (Aufruf `node bio_logo.js <ordner> 1 tanne`). In Tanne unterscheiden sich Schwarz und Dunkelgrün kaum, darum fallen 1b/1d weniger auf; dafür funktioniert 1c (alles in Tanne) hier gut. Die Wahl ist noch offen.
Jacky fragte nach einem helleren Ton → `tanne_hell.png` (`node bio_logo.js <ordner> 1 hell`): Tanne #2f5a45, Tanne hell #3b6b52 (dunkel #264a39, hell #dbe8df), Tanne heller #4a7d62 (dunkel #2d5543, hell #e0ece4), jeweils mit Logo 1b und 1c. Mein Rat: Tanne hell #3b6b52.

**✅ Entscheidung 2026-11-05: Logo 1b in Tanne hell #3b6b52.** Vektor fertig in `shop/branding/bio-wolle/` (Josefin Sans Regular als Pfade, Kapsel 0,9 × Schriftgröße, Unterschneidung W-o −70; README mit Farben). PNG-Wandler `.claude-browser-tools/svg_png.js`. Startseiten-Entwurf `.claude-browser-tools/bio_startseite.js` → `startseite_tanne.png` + `_handy.png`: Leiste, Menü wie alt, Hero „Wolle, die der Natur gut tut.“, Siegel-Reihe (GOTS, Bio-Fasern, mulesingfrei, schonend gefärbt), Kacheln nach Faser, Neuheiten mit Siegel-Chips, Box „Was bedeutet GOTS?“, Herstellerleiste, Willkommen + Vorteile, Wollboutique nur im Footer. Die Aussagen in der Siegel-Reihe (mulesingfrei, schonend gefärbt) muss Babsi noch bestätigen. Nächster Schritt: Babsis Feedback einholen, dann Slider/Kacheln/Menü im ERP befüllen.

**How to apply:** Nach Babsis Wahl: Logo als Vektor nach dem Muster `shop/branding/sockenwolle/logo_bauen.py` in `shop/branding/bio-wolle/` bauen, danach den Startseiten-Entwurf nach dem Muster `sow_startseite.js`.
