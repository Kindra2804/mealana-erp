---
name: project-sow-shop-design
description: "Sockenwolle-online: Logo + Woo-Layout; Hausfarben je Shop (MeaLana blau, SOW weiches Rot, bio-wolle grün); Babsis Logo-Entwurf in 4 Rottönen, Menüstruktur bleibt"
metadata:
  node_type: memory
  type: project
  originSessionId: 80613b77-1678-41f0-9689-0851467d1707
  modified: 2026-11-05T09:32:35.164Z
---

Start 2026-11-05: Während Live noch nicht steht, gestalten wir Logo und Layout für sockenwolle-online.at (später Woo, nach indra-design.at, siehe [[project-soft-golive-sockenwolle]]).

- **Altes Logo** (von Jacky selbst gemacht): Josefin Sans Light, dreizeilig „Socken / wo[Socken]e / online“, die beiden „l“ sind Socken. Jacky will ausdrücklich attraktivere Vorschläge.
- **Menü bleibt, das kennen die Kunden:** Hersteller (automatisch aus dem ERP wie im MeaLana-Demoshop, muss aber ins Menü), Stärke (2-2,5 / 3-3,5 / 4-4,5), Zusammensetzung (Klassisch 75/25, Bambus, Merino, Seide), Farbe (Farbverlauf, Streifen, Gemustert, Jacquard, For men, Trendfarben, Unifarben), Sparpakete (Sortimente, Farbpakete), Nadeln & Zubehör, Bücher/Anleitungen, Sale (nur wenn Artikel drin sind). Die Kategorien gibt es im ERP schon, aber noch ohne Artikel.
- **Logo-Entwurf 1** (2026-11-05): 6 Ideen (1 Weiterentwickelt, 2 Ringelsocke, 3 Ferse mit Herz, 4 Stempel, 5 Wollfaden, 6 Knäuel-o). Vorschlag für die Palette: Petrol #1f5f66, Koralle #e46a4f, Senf #e3a72f, Beere #a8325e, Mint #8cc7b5, Creme #faf5ee. Skript `.claude-browser-tools/sow_logo.js`, PNG/HTML im Session-Scratchpad sow_logo/. Wartet auf die Auswahl durch Babsi/Jacky.

**Hausfarben je Shop (Babsi 2026-11-05):** MeaLana = **blau**, Sockenwolle = **weiches Rot** (Hauptfarbe), bio-wolle = **grün**. Für SOW also kein Grün/Türkis/Petrol (meine erste Palette mit Petrol ist damit hinfällig).

**Babsis Logo-Entwurf (2026-11-05):** einzeilig „Sockenwo[2 Norweger-Socken als ll]e online“, gerundete Schrift (ähnlich Andika), Wollfaden mit Schlaufe unter „Sockenwo“, Socken terrakotta, Hintergrund creme. Kam als Rastergrafik (2000 px, vermutlich KI) mit dunkelgrüner Schrift. Ich habe die Schrift umgefärbt, 4 Varianten: A Weinrot #8e3438, B Ziegelrot #b0463e, C Himbeerrot #a03a52, D Espresso #3e2a26 mit roten Akzenten. Skript inline, Ergebnis `scratchpad/sow_logo/babsi/vergleich.png` (Session 8763d5dc…). Wartet auf Auswahl. Danach als Vektor nachbauen (transparent, Favicon = eine Socke).

**Palette „Rustic Ember“ (Babsi 2026-11-05, A oder D bevorzugt):** Tomate #FF6347, Dunkelgrau #A9A9A9, Firebrick #B22222, Gainsboro #DCDCDC, Grau #808080. Ganzes Logo umgefärbt (Socken Tomate, Fersen Gainsboro, Faden #A9A9A9, Hintergrund weiß). Varianten: A Schrift Firebrick (Claude-Favorit), D Schrift #808080, D-dunkel #4D4D4D (Zusatz). Datei `scratchpad/sow_logo/babsi/rustic.png`. Hinweis: weiße Schrift auf Tomate ist schwach lesbar (ca. 3:1) → Buttons/Leiste in Firebrick, Tomate nur als Akzent.

**Stand 2026-11-05 nachmittags:** „Rustic Ember“ war ein Irrtum (falsche Palette geschickt). Babsi tendiert zu **A Weinrot** (Creme-Hintergrund, Socken terrakotta, Faden beige): Weinrot #8e3438, Koralle #c8574a, Rosé #e9b8ad, Senf #d9a441, Creme #fdf8f2, Espresso #3b2a26. Startseiten-Entwurf in dieser Palette: Skript `.claude-browser-tools/sow_startseite.js` (Schrift Nunito, echte Produktbilder aus dem Dev-ERP, Menü wie bisher, Stärke-Chips, Hersteller-Leiste, Wollboutique-Box). Ergebnis `scratchpad/sow_logo/startseite_weinrot_A*.png`.

**✅ Babsi-OK 2026-11-05 für A Weinrot + Startseiten-Entwurf**, mit Änderungen (umgesetzt in v2, `startseite_weinrot_A_v2.*`): sockenwolle-online und bio-wolle sollen **eigenständig** wirken, also **keine Hinweise auf die Wollboutique** außer im Footer unter Kontakt. Leiste oben: „Hier gibt es alles für Sockenstricker“. Statt der Wollboutique-Box gibt es die Box „Wie viel Wolle brauche ich?“ mit Richtwerten 4/6/8-fach (Babsi prüft die Werte). „Beratung vom Fach“: „Unser Team hilft gern“.

**✅ Logo als Vektor fertig 2026-11-05** (Commit 0e23cbc): `shop/branding/sockenwolle/` (SVG creme/transparent/weiß, Favicon-Socke, PNG 1600 + Favicon 512, README mit Farben, `logo_bauen.py`). Schrift Andika Regular (SIL OFL) mit Kontur 52 Einheiten als Pfade.

**Idee Jacky 2026-11-05: Startseiten-Kacheln aus dem ERP pflegen** (heute statische Gutenberg-Spalten, nur im WP-Editor änderbar). Vorschlag: dritte Position „kachel“ im Slider-Modul (Bild, Titel, Untertitel, Ziel, Sortierung, Zeitraum, Kopieren), Shortcode `[nahtlos_kacheln]`. Danach eigener Punkt **Menü aus dem ERP** (WP-Menüs sind heute von Hand gebaut; SOW braucht Hersteller automatisch im Menü). Kacheln ✅ gebaut 2026-11-05 (siehe [[project-newsletter-slider-ideen]]). Menü aus dem ERP ✅ gebaut 2026-11-05 (siehe [[project-shop-menue]]).

**Babsis eigene Canva-Version (2026-11-05, 13:18):** `shop/branding/sockenwolle/Logo für Sockenwolle online.svg` (noch nicht committed). Gleicher Aufbau, aber Schrift **dunkelbraun #3d261d** (dünner, kein Weinrot), Socken **leuchtend rot** mit Norweger-Muster, Faden beige, Creme-Hintergrund. Technisch: Canva-KI (C2PA-Metadaten), 1,2 MB, quadratisch 2000 px mit viel Leerraum, **Socken und Teile als eingebettete PNG-Bilder** → kein sauberer Vektor. Offen: Gilt Babsis Version statt A Weinrot? Wenn ja, mit `logo_bauen.py` in ihren Farben als echten Vektor nachbauen.

**How to apply:** Reihenfolge Logo → Startseiten-Layout (ASCII → SVG → HTML, [[feedback-design-workflow]]) → Slider-Modul im ERP ([[project-newsletter-slider-ideen]]). Das fertige Logo als Vektor ausliefern (Schrift in Pfade umwandeln).
