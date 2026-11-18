---
name: project-woo-shop-checkliste
description: "Checkliste für jeden neuen Woo-Shop (MeaLana kommt noch) – alles, was beim SOW/Bio/indra-Test 2026-11-13 nachträglich korrigiert werden musste"
metadata:
  node_type: memory
  type: project
  originSessionId: bce6e723-88a5-4bb5-8c51-ffe306df12f3
  modified: 2026-11-13T14:20:10.246Z
---

**Jacky 2026-11-13:** „die ganzen Anpassungen auch merken für später, wenn MeaLana kommt“. Beim Shop-Test über Dev ([[project-shop-tests-ueber-dev]]) nachgezogen in SOW, Bio und indra – bei MeaLana (und jedem weiteren Shop) **gleich beim Aufsetzen** erledigen:

1. **Übersetzungen:** Dashboard → Aktualisierungen → „Übersetzungen aktualisieren“ (Skript `.claude-browser-tools/uebersetzungen.js <shop> machen`).
2. **Germanized-Checkbox-Texte deutsch** (bei SOW waren alle 9 Checkboxen englisch, weil Germanized vor der Sprache aktiviert wurde): Vorlage = Bio, Skript `gzd_texte_kopieren.js` (Quelle/Ziel anpassen), Kontrolle `gzd_englisch_suchen.js`.
3. **Woo-Seiten + Konto-Endpunkte deutsch:** Warenkorb /warenkorb/, Kasse /kasse/, Mein Konto /mein-konto/; Endpunkte bestellungen, bestellung-ansehen, konto-bearbeiten, adressen, zahlungsarten, passwort-vergessen, abmelden, bezahlen, bestellung-erhalten (`woo_seiten_deutsch.js <shop> machen`), danach Permalinks speichern + Cache leeren (`menue_permalinks.js`). Ist der Shop schon live: vorher Snippet `shop/wp-snippets/woo_alte_adressen_weiterleiten.php` aktiv anlegen (indra = WPCode 35330). robots.txt/301-Snippets auf die neuen Pfade.
3b. **Leerer Warenkorb:** Woo-Vorlage `woocommerce/cart-empty-message` ist im Sprachpaket unübersetzt („Your cart is empty“), ältere Shops haben feste englische Texte („Your cart is currently empty!“, „New in store“, „You may be interested in…“) → `warenkorb_deutsch.js <shop> machen` (setzt die Vorlage deutsch ein, Sicherung warenkorb_<shop>_vorher.html).
4. **Lager-Warnmails aus** (Bestand führt das ERP): `woocommerce_notify_low_stock` + `_no_stock` = no (`lager_mails_aus.js`).
5. **Widerrufsbutton** (Germanized ≥ 4) aktivieren, siehe [[project_widerrufsbutton]].
6. **Absender je Kanal** im ERP (Name + Adresse) – sonst „Ihr MEALANA KG-Team“ und fremde Absenderadresse ([[project_mail_absender_je_shop]]).
7. **Wartungsseite** als WPCode-Snippet (503 + Retry-After, Admins sehen den Shop), Go-Live = Snippet aus + Cache leeren.
8. Mails: Germanized-Bestellbestätigung ersetzt Woo „in Wartestellung/in Bearbeitung“ → Zahlungsbestätigung kommt nur vom ERP (gewollt). Woo-Kundennotiz-Mails kamen beim Test nicht an.
8b. **Datenschutzerklärung mit „Verantwortlich für die Datenverarbeitung“** (fehlte in allen übernommenen Texten; SOW/Bio 2026-11-13 mit MEALANA KG ergänzt, Skript `ds_verantwortlich.js`).
9. Vor Go-Live **Testrunde über Dev** (Bestellung → Zahlung → Versand → Teilwiderruf → Retoure → Rückerstattung) nach [[project-shop-tests-ueber-dev]].

Gemischte Bestellung MeaLana (KG-Ware + Indra) → 2 Aufträge, siehe [[project-indra-design-eu]].

- **Mail-Snippet „ERP: Zahlung/Versand ohne doppelte Shop-Mail“** in der AKTUELLEN Fassung (`shop/wp-snippets/erp_zahlung_ohne_shop_mail.php`, inkl. Filter `woocommerce_email_enabled_customer_note`) – sonst bekommt der Kunde zu jeder ERP-Statusmeldung zusätzlich „Deiner Bestellung wurde ein Hinweis hinzugefügt“. indra (#34191) hatte bis 2026-11-14 die alte Fassung → aktualisiert; SOW + Bio aktuell. Einspielen: `node snippet_setzen.js <lib> <id> <datei>`.
- **Datums-/Zeitformat** (Einstellungen → Allgemein): `j. F Y` + `H:i` – sonst steht in Woo-Mails „November 14, 2026“ / „10:42 am“. indra bis 2026-11-14 falsch → korrigiert; SOW + Bio passten. Skript: `node datum_format.js <lib> [setzen]`.
- **Newsletter (ab 2026-11-15):** Connector ≥ 1.8.0, Seite /newsletter/ + Fußzeilen-Link per `.claude-browser-tools/nl_formular_einbauen.js` (LIB/SHOP/NAME), Kanal Website + Hausfarbe im ERP.

**WP Super Cache (2026-11-16, Jacky):** SOW lief ohne Seiten-Cache in das Ressourcenlimit des Hosters (508, ERP-Bestellabruf Zeitüberschreitung 20 s ab 06:58). WP Super Cache 3.1.5 auf SOW + Bio eingerichtet (Einfach, Caching an, 30 min Lebensdauer; Skript `.claude-browser-tools/super_cache_einrichten.js sow|bio`), indra hatte es schon. Startseite 1,8 s → 0,08 s, Warenkorb/Kasse nicht gecacht. **In jedem neuen Shop einrichten.** Nach Menü-/CSS-Änderungen Cache leeren. Bestellungen gehen bei Abruf-Fehlern nicht verloren (Cursor rückt nur bei Erfolg).


**Alte JTL-Filteradressen abweisen (2026-11-17):** Botnetz (22.000 IPs, Chrome-Tarnung) fragte auf SOW ~20.000 alte JTL-Filterkombinationen ab (`__…__`, `?Sortierung=`, `mf=`) → jede startet WP komplett (404 nicht gecacht) → Prozess-Limit 40 voll → 508, auch für den ERP-Abgleich. Das ERP selbst war nur 26 Zugriffe/Tag. Lösung: `shop/wp-snippets/jtl_filter_410.htaccess` ganz oben in die `.htaccess` (410 ohne PHP; einfache `X__Y`-Adressen bleiben bei der 301 aus `sow_weiterleitungen_robots.php`). Zusätzlich: WP-Cron durch Plesk-Cron ersetzen; falls nötig Cloudflare Free. **Bei jedem Shop, der von JTL umzieht (MeaLana!), einbauen.** ✅ Auf SOW + Bio eingebaut 2026-11-17 (410 bestätigt); morgen Plesk-Eingabeprozesse + ERP-Abgleich prüfen.


## Früherer Index-Eintrag (aus MEMORY.md verschoben 2026-11-18)
- [✅ Woo-Shop-Checkliste](project_woo_shop_checkliste.md) — für MeaLana & jeden neuen Shop: Übersetzungen, Germanized-Texte, Seiten/Endpunkte deutsch (+Weiterleitung), Lager-Mails aus, Absender je Kanal, Testrunde über Dev, WP Super Cache (SOW+Bio ✅ 2026-11-16 wegen 508); JTL-Filter-410 per .htaccess gegen Bot (SOW+Bio ✅ 2026-11-17, Wirkung 18.11. mittags prüfen, MeaLana beim Umzug)
