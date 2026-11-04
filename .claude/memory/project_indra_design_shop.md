---
name: project-indra-design-shop
description: "indra-design.at = erster Live-Shop an der 1.0 (Laser-Label der MEALANA KG): Logo, Farben (Kork/Creme + MeaLana-Navy), Schrift Jost, Snippet-Entscheidungen, Entwürfe"
metadata:
  node_type: memory
  type: project
  originSessionId: ef009f0b-163c-454c-9dcc-cdd9cc4fc4bf
  modified: 2026-11-04T14:49:32.128Z
---

Stand 2026-11-04 (zurückgesetzt, siehe [[project-1-0-check]]):
- **Marke:** INDRA DESIGN, schwarzes Logo mit Monogramm (D mit I). Original 1024 px PNG von Jacky, freigestellte Varianten im Scratchpad-Ordner logo/ der Session ef009f0b (gestapelt/quer/Favicon, schwarz+weiß). MeaLana ist das Mutterunternehmen („Ein Label der MEALANA KG“), auch die Rechtsseiten bleiben MeaLana und werden nur dezent angepasst (Jacky schaut sie durch).
- **Farben (Jacky: Kork/Creme ja, Akzent = MeaLana-Blau):** Creme #f7f2ea / #efe5d6, Kork #c49a6c / dunkel #8a6340, Text #1f1d1b, **Navy #1d4076** (Buttons, Topbar), **Sky #98cce9** + hell #e3f1fa (Akzente). Die MeaLana-Logofarben sind exakt #1d4076, #98cce9, #c7e6f5.
- **Schrift:** Jost (Google, geometrisch, passt zum Logo), Navigation in Versalien mit Sperrung.
- **Snippets:** bleiben alle (Abholung, weil die Laserartikel auch in der Wollboutique verkauft werden; Gutscheine für später). **Mindestabnahme deaktiviert** (nicht gelöscht).
- **Versand ab € 6,50.** Farbschema von Jacky abgenommen. Der Hero „Schluck für Schluck“ klingt laut Jacky nach Alkohol und ist verworfen. Stattdessen die lustigen neuen Kork-Untersetzer: „Strickmodus: an. Welt: aus.“ mit Wollhamster, Strickmodus und Schnecke. Die Freisteller (kreisrund aus den Fotos, Skript kreis.php) liegen im Scratchpad-Ordner freigestellt/.
- Entwürfe: slider/untersetzer, schilder, weihnacht, startseite (.html/.jpg) im Scratchpad; Skripte in `.claude-browser-tools/` (slider_entwurf.js, slider_weihnacht.js, startseite_entwurf.js).
- Sortiment: Untersetzer (Kork rund, Kunstleder, Schiefer), 8 Themen-Sets (Kanne + 4), Türschilder, Schlüsselanhänger, Klebepads. Bildmaterial siehe [[project-newsletter-slider-ideen]].

**✅ Theme umgesetzt 2026-11-04** (indra-design.at, live sichtbar, der Shop ist noch leer):
- Seitentitel „INDRA DESIGN“, Logo quer (Media 34210), Favicon (34214), weitere Logo-Varianten 34211–34213.
- Blocksy-Theme-Mods: colorPalette (color1 Navy #1d4076 … color7 Creme #f7f2ea), Typografie Jost (root n3/17px, Überschriften n4/n5), Header neu (oben Textleiste, Mitte Logo|Menü|Suche+Warenkorb; mobil Logo|Warenkorb+Trigger), Footer 3 Spalten + Copyright „© {Jahr} INDRA DESIGN · Ein Label der MEALANA KG“.
- **Wichtig:** Blocksy cached das CSS im Transient `blocksy_dynamic_styles_descriptor` (12 Monate). Nach Theme-Mod-Änderungen per Code diesen Transient löschen, sonst bleibt das alte CSS.
- **WPCode-CSS-Snippet 34236 „INDRA DESIGN – Grundstil“** (dauerhaft): Leiste, Menü-Versalien, Buttons, dunkler Footer, Startseiten-Klassen indra-hero/indra-kachel/indra-vorteil/indra-kicker. Lokale Kopie: `.claude-browser-tools/indra_grundstil.css`.
- Max Mega Menu **deaktiviert** (nicht gelöscht). 60 alte MeaLana-Widgets entfernt.
- Menüs: „Indra Hauptmenü“ (menu_1), „Indra Shop“, „Indra Informationen“ (footer). Die Kategorie-Links sind **erwartete** Slugs (`/produkt-kategorie/untersetzer|untersetzer-sets|schilder|schluesselanhaenger|klebepads/`) → nach dem ersten ERP-Sync prüfen. Der Sync legt die Kategorien unter dem Elternknoten „IndraDesign“ an.
- Seiten: Startseite 34243 (Gutenberg: Cover-Hero mit Hintergrundbild hero_bg + 5 Kategorie-Kacheln + 3 Vorteile) als Startseite gesetzt; „Über uns“ 34216 mit Entwurfstext.
- Temporäre Admin-Snippets wurden nach Gebrauch gelöscht. Helfer: `wp_lib.js` (Login mit Wiederholversuch, Imunify bricht bei vielen Logins ab), `wp_snippet_lib.js` (WPCode anlegen/aktualisieren).
- Offen: Social-Icons zeigen noch auf die MeaLana-Konten. Unter dem Footer stehen Germanized „Alle Preise inkl. MwSt.“ + Bild „Gesetzliche Gewährleistung“ (nicht von uns, bewusst gelassen).

**How to apply:** Das Design geht in Blocksy (Customizer: Farben/Schrift/Header) + Elementor für die Startseite. Den Slider später über das ERP-Slider-Modul + NAHTLOS-Connector-Plugin.

**Dev-Sync-Test 2026-11-04:** Dev-Kanal **id 4 „indra-design.at“ (Kürzel IND)** mit neuem Woo-REST-Schlüssel „NAHTLOS ERP Dev (indra-design, Test)“ + App-Passwort „NAHTLOS ERP Dev Bildersync“ (Benutzer karlindra). Der Dev-Kanal 1 (MeaLana) hat keine indra-URL mehr. 148 Artikelzeilen fehlerfrei synchronisiert (Runner `indra_import/sync.php`, ca. 3 Min. je 20 Artikel wegen der Bilder). Kanal danach entpausiert (der Dev-Cron synct im 15-Minuten-Takt, auch Bestellungen).
- Der Woo-Kategorie-Präfix ist **/product-category/** (nicht produkt-kategorie). Die Menüpunkte sind jetzt echte product_cat-Links (Term-IDs 8725 IndraDesign, 8727 Untersetzer, 8780 Sets, 8726 Schilder, 8773 Schlüsselanhänger, 8778 Klebepads).
- „Ausverkauftes ausblenden“ = yes → Untersetzer/Anhänger/Pads/Sets sind unsichtbar, bis Bestand gebucht ist. Nur die Schilder sind sichtbar (keine Lagerführung).
- **Fix im ERP:** `ShopSyncRepository::findBestandInfo()` beachtet jetzt `artikel.keine_lagerbestandsfuehrung` (vorher nur artikeltyp.hat_lagerstand → Artikel landeten als „ausverkauft“ im Shop).
- Woo: Nachnahme deaktiviert, Versandpauschale AT 6,50 (vorher 6,90). PayPal verbindet Jacky nächste Woche am Live-Server.
- Motivcodes umnummeriert: neue A-Motive **A49–A70** (vorher A55–A76), K19–K26 bleiben. CSV: `E:\Laser\Katalog, Preisliste\Motivliste_neue_Codes_2026-11-04.csv`.
- Für die Live-Umstellung: Die Woo-Produkte hängen an den Dev-IDs → entweder die Indra-Artikel samt artikel_shops/kategorie_shops-Mapping nach Live übernehmen, oder die Woo-Produkte leeren und Live neu befüllen lassen.
