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
9. Vor Go-Live **Testrunde über Dev** (Bestellung → Zahlung → Versand → Teilwiderruf → Retoure → Rückerstattung) nach [[project-shop-tests-ueber-dev]].

Gemischte Bestellung MeaLana (KG-Ware + Indra) → 2 Aufträge, siehe [[project-indra-design-eu]].
