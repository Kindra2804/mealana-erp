---
name: project-bio-wolle-aufbau
description: "bio-wolle.at WordPress-Aufbau ab 2026-11-12 nach SOW-Muster – Stand, Skripte, offene Schritte"
metadata:
  node_type: memory
  type: project
  originSessionId: 450f5684-1786-448a-83f3-3d027746006f
  modified: 2026-11-12T12:50:19.554Z
---

Start 2026-11-12: frisches WP auf bio-wolle.at (Plesk, eigener Server; Jacky hat PHP-Speicher + 300 s Laufzeit erhöht – mit 128 MB stürzte die Plugin-Liste ab). WP-Admin **claude**, Zugang im Block `bio-wolle.at:` in `import/zugang Woo Claude.txt` (Schlüssel dort `PW:` groß → Regex /i). Woo-Admin-Benutzer #1 = BIOAdmin.

**Erledigt (alles per Skript in `.claude-browser-tools`, `bio_lib.js` = Login, REST über ?rest_route=):**
- Permalinks, de_DE, Wien, Untertitel „Wolle, die der Natur gut tut“; Plugins wie SOW (Woo, Germanized, Blocksy + Companion, WPCode, Yoast, SMTP, Swatches, PayPal, OSS, Limit Login, NAHTLOS Connector 1.6.0).
- `bio_einstellungen.js`: Woo/Germanized-Einstellungen + 8 Rechtsseiten + 220 Steuersätze + Versandzonen von **SOW** (nicht indra) kopiert, Marke ersetzt; Vorkasse an. Absender shop@bio-wolle.at, SMTP eingerichtet + Testmail ok 2026-11-12 (`bio_smtp.js`).
- `bio_theme.js`: Tanne-Palette, Nunito (Text) + Fraunces (h1–h3) lokal, Logo/Favicon, Fuß-Menüs + Widgets, Grundstil-Snippet 53 (`shop/wp-snippets/bio_grundstil.css`). Temp-Snippet 34 (aus, `bio_temp_schalter.js an|aus`).
- Fußband: Gewährleistung klein + Popover, MwSt-Hinweis, Widerrufsbutton überall. Fallen dabei: Optionen `woocommerce_gzd_legal_guarantee_enabled`, `woocommerce_gzd_display_footer_vat_notice`, `eu_owb_woocommerce_embed_everywhere` sind NICHT in der REST-Gruppe → per Option setzen. Widgets landen nach dem Theme-Wechsel in „inaktiv“ → per PUT sidebar zuordnen. Blocksy-Spalten erst nach Customizer-Speichern richtig.
- Snippets 56–60 wie SOW. Woo-REST-Schlüssel + App-Passwort → `import/zugang BIO ERP.txt`.
- Live-ERP: Kanal 2 „bio-wolle.at“ hat 20 Väter.

- 2026-11-12 nachm.: Komplettabgleich (20 Väter), Kategoriebasis /kategorie/, Live-ERP Shop 2: 6 Kacheln (Slides 31–36, `live_bio_kacheln.js`, Bilder aus Artikelfotos), Hero-Slide 37, Menü (`live_bio_menue.js`: Hersteller, Zusammensetzung, Nadelstärke, Lauflänge, Maschenprobe, Bücher). Startseite = Seite 1072 (`bio_startseite_inhalt.html`, `bio_startseite_bauen.js`), GOTS-Box mit Anker #gots. Leinen-Kachel fehlt bewusst (Studio Linen ohne Bestand).

- Füllmenge in Gramm statt kg: Snippet `fuellmenge_in_gramm.php` (Filter woocommerce_gzd_product_units_html) auf Bio (1107) + SOW (3535).
- 301: `bio_weiterleitungen_robots.php` (Snippet 1108), 164/164 alte Adressen getestet → 200. Bank + PayPal von Jacky eingetragen.

**Offen:** Testbestellung; Go-Live: Wartungs-Snippet 1109 „BIO: Wartungsseite (Umbau)“ deaktivieren (503 wie SOW-Snippet 25; Woo „Demnächst verfügbar“ seit 2026-11-12 AUS).

Siehe [[project-bio-wolle-shop-design]], [[project-sow-umstellung]].
