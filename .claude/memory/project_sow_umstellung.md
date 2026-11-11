---
name: project-sow-umstellung
description: "sockenwolle-online JTL → Woo (Start 2026-11-11): Ablauf A Shop umstellen / B Artikel per JTL-Import mit Kategorie-Zuordnungstabelle (Migr. 230), Entscheidung: keine Wurzelkategorie je Shop"
metadata:
  type: project
---

**Start 2026-11-11.** Stand: auf sockenwolle-online.at läuft noch JTL (vom Wawi schon getrennt). Artikel kommen per **frischem JTL-Export** (nicht per Datentransfer aus Dev).

**Entscheidung Jacky 2026-11-11:** **Keine automatische Wurzelkategorie je Shop** („dann hätten wir das gleiche Problem wie bei JTL“). Lieber klein-klein einspielen und selbst zuordnen → weniger Nacharbeit (verschieben, unnötige Kategorien löschen). Darum gebaut: **Kategorie-Zuordnungstabelle im JTL-Import** (Commit 5ea1aa5, Migration 230 `import_kategorie_zuordnung`, Versionsnotiz 1.0.3).
- Ameise-Stammdaten haben KEINE Kategorie-Spalten → dritte optionale Datei „Kategorien-CSV“ = Jackys **Eigener Export** (Spalten Artikelnummer, KategorieIDs, Kategorien; paarweise, pipe-getrennt), Join über Artikelnummer.
- Zuordnung über die **JTL-Kategorie-ID** (Namen nicht eindeutig: im Gesamtexport 108 Namen mit mehreren IDs, z. B. Amigurumi/Rundnadeln). Kontrollliste zeigt Zeilen, „zusammen mit“, Beispiele, Filter; Wahl wird gemerkt (0/NULL = nicht zuordnen).
- Vater bekommt die JTL-Kategorien seiner Kinder-Zeilen mit. Bestehende Kategorien bleiben. Feste Kategorie jetzt optional. Optional „Shop-Kanal einschalten“ (upsertZuweisung samt Kindern).
- Getestet Dev (100alpaca mit ZZTEST-Präfix, Testskript `.claude-browser-tools/jtl_kat_zuordnung_test.js`, Testdaten-Generator scratchpad/jtltest/erzeuge.php), Testdaten entfernt.

**Gesichert:** JTL-Sitemap von sockenwolle-online.at (93 URLs) in `import/sow_jtl_urls/` (urls.txt, robots.txt) → für 301-Weiterleitungen nach dem Umstieg.

**Ablauf A (Shop):** Plesk-Sicherung SOW (Dateien+DB) herunterladen + JTL-Wawi-DB-Sicherung → JTL entfernen → WordPress (WP Toolkit; ggf. gleich auf dem neuen eigenen Server) → wp-admin „claude“ → Claude: Blocksy, Germanized+Widerrufsbutton, NAHTLOS Connector, SMTP, Logo/Farben Weinrot ([[project-sow-shop-design]]), robots.txt mit gesperrten Filtern, Woo-Keys, Kanal SOW im Live-ERP, Absender bestellung@sockenwolle-online.at ([[project-mail-absender-je-shop]]).
**Ablauf B (Artikel):** Ziel-Kategorien im Live-ERP anlegen → je Hersteller/Gruppe: Ameise-Stammdaten + Variationskombinationen + Eigener Export → JTL-Import mit Zuordnung + Kanal SOW → Bilder-Import → Startbestand.
**Kategorien-CSV:** Jacky nutzt den Ameise-Export „Artikelkategorien“ (je Artikel+Kategorie eine Zeile, Kategorie Ebene 1–10, Datei `import/JTL-Export-Artikelkategorien-11112026.csv`, 105.781 Zeilen, 1.352 Pfade, JTL-Wurzeln u. a. „Sockenwolle-online.at“, „bio-wolle.at“, „WAWI“) → voller Pfad = fremd_id (VARCHAR 255). Eigener Export mit KategorieIDs geht weiterhin.
✅ **Paket 1.0.3 gebaut 2026-11-11** (`D:/ERP/pakete/nahtlos-1.0.3.zip`, Commit b00753b). Offen: auf Live einspielen.

## Shop-Aufbau 2026-11-11 (WordPress frisch, Login `claude` aus `import/zugang Woo Claude.txt`, Block „sockenwolle-online.at:“, Helfer `.claude-browser-tools/sow_lib.js`)
- **Server:** PHP 8.5, memory_limit war 128M → Admin/REST brachen mit Woo+Yoast ab; Jacky hat in Plesk 512M gesetzt. Fatal-Errors stehen im Woo-Log (WooCommerce → Status → Logs, fatal-errors-*).
- Permalinks /%postname%/ (sonst REST 404). Sprache **de_DE** (de_AT hat Woo nur halb übersetzt), Zeitzone Europe/Vienna, Datum „j. F Y“, H:i.
- Plugins wie indra ohne Elementor/Jetpack/Site Kit/Image-Opt/Updraft/CF7/Super Cache: Woo 11.2.2, Germanized 4.1.7, Blocksy + Companion, WP Mail SMTP, WPCode, Variation Swatches, PayPal Payments, OSS, Yoast, Limit Login. **NAHTLOS Connector 1.6.0** (Zip mit Python bauen — PowerShell Compress-Archive schreibt Backslash-Pfade, WP erkennt das Plugin dann nicht; 4 Müll-Dateien „nahtlos-connector\…“ liegen evtl. noch in wp-content/plugins).
- Woo/Germanized-Einstellungen + 100 OSS-Steuersätze + Steuerklassen von indra kopiert (`settings_ind.json` → `sow_einstellungen.js`). Absender Woo: Sockenwolle-online / **shop@sockenwolle-online.at**; ✅ WP Mail SMTP mail.sockenwolle-online.at:465 SSL, Absender erzwungen, Testmail an office@mealana.at ok (Zugang `import/zugang Mail shop@sockenwolle-online.at.txt`). Widerruf-Kontakt office@mealana.at, Rücksendeadresse MEALANA KG Wollboutique Bahngasse 13.
- Versandzonen nach der Versandseite: AT 6,50 / DE 9,90 / IT+HU 15,90 (✅ Jacky: passt). Zahlungsart nur Vorkasse (Bankdaten? PayPal verbindet Jacky; ✅ Jacky: kein Bar bei Abholung).
- Rechtsseiten von indra kopiert („INDRA DESIGN“ → „Sockenwolle-online“): Datenschutz 26, Impressum 27, AGB 28, Widerrufsbelehrung 29, Versand 30, Zahlungsarten 31, Bewertungen 32, Vertrag widerrufen 13. Kein „Über uns“.
- Design: Blocksy-Mods von indra übernommen, Palette Weinrot (color1 #8e3438 … color7 Creme #fdf8f2), Schrift **SowNunito** lokal (uploads/2026/11/nunito-*-wght-normal.woff2), Logo Media 21, weiß 22, Favicon 23. Kopfleiste „Hier gibt es alles für Sockenstricker“. Fußzeile Espresso mit Marke/Kontakt (Wollboutique nur hier), Menüs „SOW Shop“ 43 (vorläufig auch menu_1/menu_mobile bis ERP-Menü) + „SOW Informationen“ 42. Grundstil-Snippet 52 (Kopie `shop/wp-snippets/sow_grundstil.css`).
- **Wartungsseite** WPCode 25 (503 + Retry-After, Admins sehen den Shop) — Woo-„Demnächst verfügbar“ wirkt mit Blocksy nicht. **Zum Go-Live Snippet 25 deaktivieren** (+ Woo-Sichtbarkeit auf „Live“).
- ERP-Zugang angelegt: Woo-REST-Key „NAHTLOS ERP Live“ (User SoWoAdmin) + App-Passwort Bildersync → `D:\ERP\mealana\import\zugang SOW ERP.txt`.
- **Offen:** Kanal im Live-ERP anlegen (Jacky), Startseite (nach Artikelimport, Kacheln/Slider aus dem ERP), ERP-Shop-Menü SOW, robots.txt-Filtersperren, 301 aus `import/sow_jtl_urls/`, Gutschein-Snippet ja/nein, WP Super Cache erst zum Go-Live.

**Stand Abend 2026-11-11:** SOW-Artikel auf Live importiert (89 Väter, 1.289 Kinder, 122 Einzelartikel, Kanal SOW). Gefundene Stolpersteine, alle gelöst: fehlende Warengruppen (artikelgruppen_mealana.sql), Stammdaten ohne Kinderzeilen, max_input_vars=1000 auf Live (→ 20000, Sperre im Import gebaut). Startbestand kommt über den JTL-Abgleich (Erstabgleich, siehe [[project_jtl_parallelbetrieb]]), sobald 1.0.4 live ist.
**Für 2026-11-12 eingeplant:** SOW-Startseite fertig bauen wie im Entwurf: Hero-Slider, „Schnell finden nach Stärke“-Knöpfe (4-fach/6-fach/8-fach), Kacheln „Beliebte Kategorien“, „Neu eingetroffen“, Hersteller-Band, Willkommen-Text + Box Wollboutique, 3 Vorteils-Boxen. Dazu das ERP-Shop-Menü (Hersteller, Stärke, Zusammensetzung, Farbe, Sparpakete, Nadeln & Zubehör, Bücher/Anleitungen, Sale %). Die Artikel sollten dann schon im Shop sein.
**bio-wolle gesichert 2026-11-11:** `import/bio_jtl_urls/` (UEBERSICHT.md, alle_urls.tsv, urls.txt): 168 Adressen = 48 aus der Sitemap, 20 Inhaltsseiten, 94 Filterseiten, 5 technische. Der Durchlauf ohne Filter-Kombinationen (`A__B`) ist vollständig. Jacky darf bio-wolle abschalten und mit Woo vorbereiten (neue Server erst Ende November).
