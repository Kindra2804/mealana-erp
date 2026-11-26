---
name: project_shop_anzeige_merkmale
description: "2026-11-26 geplant: Merkmale + Auszeichnung als Woo-Attribute/Filter, Pflege-Box mit Symbolen + Badges im Connector-Plugin, Sale-Badge mit % + „Alter Preis“; zuerst sockenwolle-online"
metadata:
  node_type: memory
  type: project
  originSessionId: e3f99429-4c56-407b-a054-e920207a65aa
  modified: 2026-11-26T10:03:25.913Z
---

**Stand 2026-11-26:** Merkmale (inkl. Pflege, Auszeichnung GOTS/BIO/Handgefärbt) gehen NICHT in den Shop – ShopSyncService schickt nur Varianten-Achsen + Hersteller als attributes. Jacky stimmt zu, „gleich einplanen“, zuerst SOW.

Plan (von Jacky abgenommen, gestartet 2026-11-26):
1. ERP→Woo: alle Merkmale außer lauflaenge_bereich/maschenprobe_10cm als globale Attribute (wie Hersteller-Zuweisung), Filter-Archive für Garngruppe, Nadelstärke, Wollqualität, Auszeichnung.
2. Connector-Plugin (nach 1.8.0): Pflege-Box mit Symbolen (Zeichnungen aus src/modules/schilder/PflegeSymbole.php, 22 Schlüssel seit Migr. 265) + Auszeichnungs-Badges.
3. Plugin: Sale-Badge „SALE 36%“ (bei Varianten „bis zu“) + Zeile „Alter Preis: ~~x~~“ wie alter JTL-Shop; Omnibus-30-Tage-Tiefstpreis mit Germanized prüfen.
4. Je Shop einmal Filter-Blöcke „Nach Attribut filtern“ in die Seitenleiste.

- ✅ Schritt 1 (45e11c1, Migr. 266 artikel_shops.merkmale_hash): baueMerkmalAttribute() in ShopSyncService, Attribut je Merkmal über Namen (Cache je Shop, neu mit slug=merkmal-slug, order_by menu_order), Terms slug=merkmal_werte.symbol (hand30, gots, bio, gg_a, lb_450 …) + menu_order; Achse gleichen Namens (Nadelstärke) teilt Attribut; Fälligkeit über MD5 der wert_ids (alle Schreibwege); nur mit Fake-Client getestet, NICHT gegen echten Shop. Wert-Umbenennung im ERP = neuer Term (alter bleibt stehen). Live: nach Merkmal-Import werden alle SOW/Bio-Artikel mit Merkmalen einmal neu übertragen. **Nächste Migr. 267.**

- ✅ Schritt 2 (7edcba2): Connector-Plugin **1.9.0** (ZIP D:/ERP/pakete/nahtlos-connector-1.9.0.zip, noch nicht installiert): includes/pflege-auszeichnung.php – Attribute über NAMEN „Pflege“/„Auszeichnung“ gefunden, Symbol = Term-Slug; Pflege-Kasten summary prio 35, Badges summary prio 6 + Liste über woocommerce_get_price_html prio 25 (wie Mengen-Badge, wegen Blocksy-Karten); GOTS weiß mit grünem Rahmen, BIO grün, Handgefärbt lila, sonst grau. PflegeSymbole.php = Kopie, Prüfung tests/pflege_symbole_test.php. ERP: pflege in MERKMALE_NUR_FILTER (unsichtbar in Zusätzliche Informationen). Nur mit WP-Attrappe getestet – im echten Shop (Blocksy) Position prüfen!

- ✅ Schritt 3 (2026-11-26): includes/sale-anzeige.php im Plugin 1.9.0 – woocommerce_sale_flash → „SALE 36%“/„SALE bis X%“, woocommerce_get_price_html prio 15 → Preis + „Alter Preis: ~~x~~“ (nur bei EINEM Preis, Spannen bleiben Woo). Vergleichspreis zentral in nahtlos_vergleichspreis() = regulärer Preis. **Omnibus offen:** ERP hat KEINE Preis-Historie (artikel_preise ohne Verlauf, preis_aktionen_positionen leer) → 30-Tage-Tiefstpreis bräuchte Verlaufstabelle (z.B. täglicher Schnappschuss effektiver Preis) + Meta an Woo; Jacky noch fragen. Ob Blocksy woocommerce_sale_flash nutzt: im echten Shop prüfen.

- ✅ 2026-11-26: ERP 1.0.31 LIVE (Jacky). Plugin 1.9.0 auf SOW + Bio installiert (Skript .claude-browser-tools/connector_19.js, LIB=sow_lib|bio_lib), Seiten laden ohne Fehler; kein Sale-Artikel in beiden Shops, Badges/Pflege erst nach Merkmal-Import sichtbar. indra-design ebenfalls 1.9.0 (2026-11-26, LIB=wp_lib).

Babsi verschiebt „Zum Filzen geeignet“ im Live von Pflege zu Auszeichnung.

Zusammenhang [[project_merkmale]], [[project_shop_sync]], [[project_preisschilder]].
