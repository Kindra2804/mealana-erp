---
name: reference-wp-snippets-repo
description: Alle selbst gebauten WordPress/WooCommerce-Snippets von indra-design.at liegen als Kopie in D:\ERP\mealana\shop\wp-snippets\ im Git-Repo
metadata:
  node_type: memory
  type: reference
  originSessionId: 40a29a40-0c3b-483d-82e6-51045de0676d
  modified: 2026-08-29T11:51:46.699Z
---

`D:\ERP\mealana\shop\wp-snippets\` enthält Kopien aller MeaLana-eigenen WPCode-Snippets von
`indra-design.at` (nicht Teil des ERP-Autoloads, reine Ablage): `bar_bei_abholung.php` (34171),
`konfigurator_options_picker.php` (34170, aktiv), `mindestabnahme.php` (34156),
`leere_kategorien_ausblenden.php` (32095), `ausverkauft_statt_weiterlesen.php` (32085),
`hersteller_menue.php` (32070), `variations_threshold_anheben.php` (34174, seit 2026-08-29).
README.md dort hat Details + Einspiel-Anleitung für weitere Shops.

**Kein automatischer Sync mit WPCode** -- bei jeder künftigen Snippet-Änderung in wp-admin (siehe [[reference_wp_claude_zugang]] für den Login) die Kopie hier von Hand nachziehen, sonst laufen Repo und Live-Shop auseinander.

**Why:** Jacky bat darum, nachdem eine Testbestellung zeigte, dass die Snippets bisher nirgends außerhalb der WPCode-Datenbank existierten (kein Backup, keine Versionshistorie in der Lite-Version) -- und weil bio-wolle.at/sockenwolle-online.at (siehe [[project_shop_sync]]) dieselben Snippets brauchen werden, sobald sie angebunden werden.
**How to apply:** Vor dem Bauen eines neuen Shop-Snippets erst hier nachsehen, ob es nicht schon eine wiederverwendbare Vorlage gibt. Nach jeder Live-Änderung eines bestehenden Snippets die Kopie hier aktualisieren + committen.
