# WordPress/WooCommerce-Snippets (indra-design.at)

Diese Dateien sind **Kopien** von PHP-Snippets, die über das Plugin [WPCode](https://wpcode.com/)
direkt im WordPress von `indra-design.at` laufen. Sie sind **nicht Teil des ERP-PHP-Autoloads**
und werden von keinem ERP-Prozess eingebunden — reine Ablage, damit der Code:

- versioniert ist (WPCode Lite hat keine Revisionshistorie),
- bei einem Server-Totalausfall nicht nur in der WordPress-DB existiert,
- als Vorlage für weitere Shops (bio-wolle.at, sockenwolle-online.at) wiederverwendbar ist,
  sobald die anbinden (siehe [[project_shop_sync]]-Memory).

**Kein automatischer Sync.** Wird ein Snippet in wp-admin geändert, muss die Kopie hier von Hand
nachgezogen werden (und umgekehrt). Quelle der Wahrheit ist immer WPCode selbst
(`wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=<ID>`).

## Übersicht

| Datei | WPCode-ID | Titel | Status | Einfügemethode |
|---|---|---|---|---|
| `bar_bei_abholung.php` | 34171 | MeaLana: Bar bei Abholung (Zahlungsart + Versandart) | ✅ Aktiv | Überall ausführen |
| `konfigurator_options_picker.php` | 34170 | Konfigurator: Options-Picker | ✅ Aktiv (Status-Notiz vom 28.08. war veraltet, siehe [[project_konfigurator_modul]]) | Überall ausführen |
| `variations_threshold_anheben.php` | 34174 | WooCommerce Variations-Schwellwert anheben (30 → 1000) | ✅ Aktiv | Überall ausführen |
| `mindestabnahme.php` | 34156 | Mindestabnahme (Meterware-Mindestmenge + Intervall) | ✅ Aktiv | Überall ausführen |
| `leere_kategorien_ausblenden.php` | 32095 | Leere Kategorien ausblenden | ✅ Aktiv | Überall ausführen |
| `ausverkauft_statt_weiterlesen.php` | 32085 | "Ausverkauft" statt "Weiterlesen" bei Out-of-Stock | ✅ Aktiv | Überall ausführen |
| `hersteller_menue.php` | 32070 | Hersteller-Menü (Shortcode `[hersteller_liste]` fürs Mega-Menü) | ✅ Aktiv | Nur Frontend |

Zwei weitere WPCode-Einträge (32068 "Nachricht nach 1. Absatz", 32069 "Kommentare deaktivieren")
sind unveränderte Vorlagen aus der WPCode-Bibliothek, nicht MeaLana-spezifisch — bewusst nicht
hier abgelegt.

## Was macht was (Kurzfassung)

- **bar_bei_abholung** — eigene Zahlungsart "Bar bei Abholung", nur wählbar wenn im Checkout die
  native WooCommerce-Abholung ("Abholung vor Ort") gewählt wurde. Enthält zwei Teile: ein
  klassisches `WC_Payment_Gateway` (Absicherung für einen eventuellen Shortcode-Checkout) UND
  eine Blocks-Payment-Method-Registrierung (PHP + Inline-JS) — **beide Teile sind nötig**, ein
  klassisches Gateway allein ist im blockbasierten Checkout unsichtbar (siehe
  [[project_shop_sync]]-Memory, Fund vom 2026-08-29).
- **konfigurator_options_picker** — Frontend-UI-Baustein für den Artikel-Konfigurator (Achsen/Werte
  als klickbare Optionen statt Dropdown). Noch inaktiv, siehe [[project_konfigurator_modul]].
- **mindestabnahme** — Mengenfeld-Vorbelegung + Kundenhinweistext + serverseitige Validierung für
  Meterware mit Mindestabnahme/Abnahmeintervall (liest `_mealana_mindestabnahme*`-Metafelder, die
  `ShopSyncService::baueMindestabnahmeFelder()` beim Produkt-Sync setzt).
- **leere_kategorien_ausblenden** — Filtert das Hauptmenü, damit Kategorien ohne (sichtbare)
  Produkte nicht als leere Menüpunkte erscheinen.
- **ausverkauft_statt_weiterlesen** — Ersetzt den WooCommerce-Standardtext "Weiterlesen" durch
  "Ausverkauft" auf nicht-vorrätigen Produktkarten.
- **hersteller_menue** — Shortcode `[hersteller_liste]`, listet alle Hersteller-Attributwerte
  (`pa_hersteller`) als Link-Spalten fürs Mega-Menü.
- **variations_threshold_anheben** — `woocommerce_ajax_variation_threshold`-Filter von 30 (WC-Default)
  auf 1000 angehoben. WooCommerce bettet die Variations-Auswahl bei mehr Kombinationen als dem
  Schwellwert nicht mehr fertig als JSON ins Seiten-HTML ein, sondern lädt jede Auswahl per AJAX nach
  -- betrifft Artikel mit vielen Kombinationen (Rundnadeln bis 141, DMC-Garnfarben bis 499 Stand
  2026-08-29). Live verifiziert: DMC-Produkt bettet danach 304 Variationen (alle mit Bestand) inline
  ein statt vorher `false`.

## Bei einem weiteren Shop einspielen

1. Datei hier öffnen, Inhalt (ohne die `<?php`-Kopfzeile — WPCode fügt sie selbst hinzu) in ein
   neues PHP-Snippet in WPCode einfügen.
2. Shop-spezifische Werte prüfen/anpassen (z.B. Abholadresse in `bar_bei_abholung.php`,
   Taxonomie-Namen in `hersteller_menue.php` falls abweichend).
3. Erst inaktiv speichern, testen, dann aktivieren — gleiches Vorsichtsmuster wie beim
   Erstaufbau auf indra-design.at.
