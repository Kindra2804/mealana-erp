---
name: project_google_shopping_search_console
description: "Barbaras Wunsch: Google Shopping (Produktfeed) + Google Search Console-Anbindung an den Online-Shop"
metadata: 
  node_type: memory
  type: project
  originSessionId: 66d7f6e5-73a2-4fbe-ae8f-903ff8a00d54
  modified: 2026-08-01T08:21:29.549Z
---

## Anforderung gemeldet 2026-08-01, noch 0% Konzept/Code

Barbara wünscht sich für den Online-Shop:
- **Google Shopping** — Produktfeed, damit Artikel in der Google-Shopping-Ergebnisliste erscheinen
- **Google Search Console** — Website-Verifizierung/Anbindung für organische Suchsichtbarkeit/Indexierung

**Why:** Sichtbarkeit des neuen Shops in Google — sowohl bezahlt/gelistet (Shopping-Feed) als auch organisch (Search Console).

**How to apply:** Gehört fachlich zum Themenblock [[project_roadmap_reihenfolge]] Punkt "Online-Shop-Anbindung" bzw. läuft parallel zu [[project_shop_theme]] (Theme/UX) und [[project_shop_sync]] (WooCommerce-Sync). Bei Google Shopping wahrscheinlich Merchant-Center-Produktfeed via WooCommerce-Plugin (z.B. Google Listings & Ads) oder eigener XML/CSV-Feed aus unseren Artikeldaten (EAN, Bild, Preis, Verfügbarkeit — vieles davon existiert schon aus dem WooCommerce-Sync, siehe [[project_shop_sync]]) zu prüfen. Noch nicht konkretisiert (kein Termin, keine Sub-Reihenfolge) — beim nächsten Antritt des Online-Shop-Themas mit Jacky/Barbara Scope klären (eigener Feed vs. Plugin, welcher Shop zuerst).

## 2026-11-15: Plan mit Jacky + Punkt 2 gebaut
**Plan:** (1) Search Console je Shop gemeinsam (Yoast-Verifizierung + Sitemap) — offen: Google-Konto der Firma (nicht privat), indra-design (EU Babsi) eigenes Merchant-Center-Konto. (2) ✅ ERP→Woo: `global_unique_id` = eindeutige GTIN13 (doppelte EAN nicht senden, Woo lehnt ab), Yoast `_yoast_wpseo_title`/`_yoast_wpseo_metadesc` aus Reiter SEO (nur wenn gefüllt), Alt-Text-Ersatz = Artikelname bei neuen Bildern + `scripts/alt_texte_nachziehen.php <slug> [--probelauf] [--max]` (WP-Medien-API) für Bestand. **Marke bewusst NICHT als Woo-Brands** (doppelte Archivseiten zu /hersteller/) → im Plugin „Google for WooCommerce“ Marke aus Attribut pa_hersteller mappen. (3) Merchant Center + Plugin zuerst SOW. (4) später Kategorie-Texte, Meta-Beschreibungen Top-Artikel.
**Nach Live-Update 1.0.19:** Komplettabgleich je Shop abends, Alt-Skript erst --probelauf. Erster echter Abgleich zeigt, ob global_unique_id ankommt (nicht gegen Shop getestet; Dev hat keinen aktiven Kanal). Dev: 0 Meta-Beschreibungen, ~7.900/15.200 Shop-Artikel mit EAN, 26.400/26.700 Bilder ohne Alt.
