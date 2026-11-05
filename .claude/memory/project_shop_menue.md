---
name: project-shop-menue
description: "Shop-Hauptmenü aus dem ERP (Migration 226, Plugin 1.4.0): Artikel → Shop-Menü, max. 3 Ebenen, Alle Hersteller in Spalten, nur wenn Artikel drin; live auf indra-design"
metadata:
  type: project
---

Gebaut 2026-11-05 (Commit 2e654ad). Jacky: gilt für alle Shops (SOW, MeaLana, bio-wolle).

**Jackys Entscheidungen:** Menüpunkt unter Artikel → Shop-Menü mit Recht slider.verwalten. **Max. 3 Ebenen.** Hersteller **in Spalten** (eine Hersteller-Seite mit Logos nur, wenn sie viel einfacher wäre; vorgemerkt für später). **Footer-Menüs bleiben in WP.**

**Technik:**
- Tabelle `shop_menue_punkte` (typ kategorie/hersteller_alle/hersteller/artikel/url, unterkategorien_auto, nur_mit_artikeln, spalten). shops.menue_orte (Standard menu_1), menue_uebertragen_am/_fehler/_geaendert.
- `src/modules/slider/MenueService.php` + `MenueRepository.php`, Seite `public/slider/menue.php` + `menue_aktion.php`, `js/slider_menue.js`.
- Das ERP löst automatische Unterkategorien beim Übertragen auf. Der Cron (shop_sync) überträgt neu, wenn kategorien.aktualisiert_am oder kategorie_shops.synced_at neuer ist, aber nur ohne offene Änderungen.
- Plugin: POST nahtlos/v1/menue baut das WP-Menü „NAHTLOS Hauptmenü“ jedes Mal komplett neu und hängt es an die Theme-Orte. Filter `wp_nav_menu_objects`: fügt bei „Alle Hersteller“ die pa_hersteller-Begriffe mit Artikeln ein (virtuelle IDs 2000000000+term_id) und entfernt leere Punkte mit „nur mit Artikeln“ samt Unterpunkten. CSS assets/menue.css (Spalten ab 1000 px).

**indra-design:** ERP-Menü Shop 4 = die bisherigen 8 Punkte (flach, Orte menu_1 + menu_mobile). Das alte WP-Menü „Indra Hauptmenü“ (8722) existiert noch, man kann zurückschalten.

**Nicht live getestet:** Hersteller-Spalten und „nur wenn Artikel drin“ (indra hat keine Hersteller). Die Filterlogik ist mit WP-Stub getestet (Scratchpad test/menue_filter_test.php). Erst mit SOW/MeaLana echt prüfen, vor allem das Blocksy-Dropdown-CSS der Spalten.

**How to apply:** Für SOW: Menü (Hersteller=Alle Hersteller, Stärke/Zusammensetzung/Farbe/Sparpakete/Nadeln/Bücher mit Unterkategorien automatisch, Sale nur mit Artikeln) im ERP vorbereiten, sobald die Woo-Installation steht. Siehe [[project-sow-shop-design]], [[project-newsletter-slider-ideen]].
