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
- Menüs: „Indra Hauptmenü“ (menu_1), „Indra Shop“, „Indra Informationen“ (footer). Die Kategorie-Links sind **erwartete** Slugs (`/produkt-kategorie/untersetzer|untersetzer-sets|schilder|schluesselanhaenger|klebepads/`) → nach dem ersten ERP-Sync prüfen. Seit 2026-11-04 (Migration 223) hat der Shop `wurzel_kategorie_id = 420` (IndraDesign) → Unterkategorien sind im Shop Hauptkategorien, Term 8725 gelöscht, Startseiten-Kacheln auf /product-category/<slug>/ umgestellt. **Auf Live nach Neuaufsatz: in Einstellungen → Kanäle „Kategorien im Shop ab: IndraDesign“ setzen.**
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

**Cookie/DSGVO-Check 2026-11-04 (indra-design, gilt sinngemäß für alle Woo-Shops):** Gemessen als anonymer Besucher bis zur Kasse. Vorher: WooCommerce-Bestellzuordnung (`sbjs_*`-Cookies, Marketing) + Google Fonts von googleapis. Umgestellt: Option `woocommerce_feature_order_attribution_enabled` = no; die Schrift **Jost lokal** (4 woff2 in /wp-content/uploads/2026/11/, @font-face „IndraJost“ im Grundstil-Snippet 34236, Blocksy-Typografie family = IndraJost → Blocksy lädt keine Google Fonts mehr). Danach nur noch technisch notwendige Woo-Cookies; als fremder Server nur paypal.com an der Kasse. → **Kein Cookie-Banner nötig**, solange kein Analytics/Pixel/Embed dazukommt (dann Real Cookie Banner oder Complianz). Social-Icons sind reine Links, keine Einwilligung nötig. Jacky: Social-Links, AGB, Widerrufsbelehrung und Datenschutz sind für alle Shops gleich (nur der Shopname unterscheidet sich). Zahlungsarten: Vorkasse, PayPal, Bar bei Abholung (Bankomat vorhanden). Links zu den Schwester-Shops in der Fußzeile kommen, wenn alle umgestellt sind.

## Kategorien 2026-11-04 (Saisonales / Sonstiges)
- ERP unter IndraDesign 420: **Saisonales 433** (Woo 8784) → **Weihnachten 431** (früher „Weihnachtsanhänger“, Woo 8781, Slug bleibt `weihnachtsanhaenger` → 4-für-12-Snippet 34685 greift weiter); **Sonstiges 434** (Woo 8783) = Kraut und Rüben ohne eigene Unterkategorie, darin die Dosen. Kategorie „Dosen“ 432 / Woo 8782 gelöscht.
- Menüs 8722 (Haupt) + 8723 (Shop): Saisonales + Sonstiges nach Klebepads. Hauptmenü ist damit voll → „Über uns“ rutscht ins Blocksy-„MEHR“.
- Später (Jacky): Saison-Kategorien mit Zeitraum „sichtbar von/bis“ ins ERP (Ostern, Muttertag …), siehe [[project_preise]].
- **Header 2026-11-04:** einzeilig (Logo | Menü | Suche+Warenkorb), Menü bricht per CSS (Snippet 34236, Abschnitt „Menü bricht … 2 Zeilen um“) linksbündig in 2 Zeilen um — Jackys Wunsch, NICHT Logo-oben-Menü-unten. Blocksy-Menü hat `data-responsive="no"`.
- **WP Super Cache** leert sich bei Menü- und CSS-Snippet-Änderungen NICHT → danach `node wp_cache_leeren.js` (Einstellungen → WP Super Cache → Cache leeren). Sonst zeigt die Startseite das alte Menü.
- Temporäre Snippets 34691/34692 (Theme-Mods) und „Theme-Datei lesen“ sind deaktiviert; können in WPCode gelöscht werden.
- **Startseite 2026-11-04:** 7 Kategorie-Kacheln in 2 `wp:columns`-Blöcken mit Klasse `indra-kacheln` (4 + 3 mittig, CSS gibt jeder Kachel ¼ Breite). Neue Kacheln Saisonales (Bild 34695 Schaf XM-S02) + Sonstiges (34696 Dose DO-02). Neue Kategorie = neue Spalte in Reihe 2 ergänzen.
- **Mobilmenü:** Menü-Platz `menu_mobile` war leer → jetzt „Indra Hauptmenü“ 8722 (menu_1 + menu_mobile). Optik ist noch Blocksy-Standard (dunkles Overlay, fette weiße Schrift) — evtl. später an Indra-Stil anpassen.
- **Kategorie-Zähler (2026-11-04):** Oberkategorie ohne eigene Produkte (Saisonales → Weihnachten) fehlte auf /shop/, weil Woo-Zähler nach REST-Umhängen veraltet. Fix im ERP-Sync: nach angelegten/geänderten Kategorien `WooCommerceClient::zaehleKategorienNeu()` (Woo-Tool recount_terms). REST-Feld `count` bleibt 0 (nur eigene Produkte) — Anzeige nutzt eigene Term-Meta.
- WP Super Cache: auch /shop/ bleibt nach Sync-Änderungen gecacht, bis der Cache abläuft oder geleert wird.
