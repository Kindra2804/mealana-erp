---
name: project-widerrufsbutton
description: "Widerrufsbutton (EU-RL 2023/2673, Pflicht seit 19.06.2026) in allen Woo-Shops über Germanized ab 4.0, kein eigenes Plugin nötig; Stand indra-design 2026-11-05"
metadata:
  node_type: memory
  type: project
  originSessionId: 745e566f-eead-45c0-b033-974ece3f704d
  modified: 2026-11-05T16:20:14.450Z
---

Jacky am 2026-11-05: Der Widerrufsbutton muss in alle Woo-Shops. Die Gewährleistungs-Info kommt schon über Germanized und muss nur je Shop aktiviert werden.

**Ergebnis der Prüfung:** Germanized (gratis) hat den Widerrufsbutton ab Version 4.0 eingebaut: WooCommerce → Einstellungen → Germanized → Allgemein → Abschnitt „Widerrufsbutton“ (`tab=germanized-general&section=withdrawal_button`). Wir brauchen also **weder ein eigenes Plugin noch eigenen Code**.

**indra-design.at (Germanized 4.1.6), gelesen am 2026-11-05:** schon aktiv. Seite „Vertrag widerrufen“ (ID 49, /vertrag-widerrufen/), im Footer eingebettet = AN, **Überall anzeigen = aus** (der Button erscheint nur auf Shop-Seiten, nicht auf der Startseite), Teilwiderrufe AN, Frist 14, nicht widerrufbar = Virtuelles Produkt, **Kontakt-E-Mail leer**, **Mein-Konto-Link aus**, Gastvalidierung „Nicht validieren“.
**✅ 2026-11-05 umgestellt (Jacky-OK):** Überall anzeigen AN, Kontakt-E-Mail office@indra-design.at, Mein-Konto-Link AN (Skript `wp_widerruf_setzen.js`; die Kippschalter sind versteckte Checkboxen, darum per evaluate setzen). Cache geleert, der Button ist jetzt auch auf Startseite und Kontakt zu sehen. **Offen:** Test-Widerruf. Danach einen Test-Widerruf machen und dabei prüfen, was mit dem Bestellstatus passiert und ob der ERP-Sync darauf reagiert.

**Test 2026-11-05 (Woo #34748 = ERP A-IND-2026-00001, Gast, 1× Engel XM-E06, Bar bei Abholung):**
- Der Auftrag kam korrekt ins ERP. Lücke: Abholung ohne `abholort_id` (Wollboutique wird nicht zugeordnet).
- Der Widerruf über den Button funktioniert, auch direkt von der Bestellbestätigung aus. Woo-Status danach `pending-wdraw` („Widerruf ausstehend“).
- **✅ ERP-Umbau gebaut 2026-11-05 (Migration 227):** `auftraege.widerruf_status` ENUM(beantragt, widerrufen) + `widerruf_am` (Shop-Zeit aus date_modified_gmt). Sync: pending-wdraw → beantragt, withdrawn → widerrufen; zurück auf einen normalen Status bei „beantragt“ → Markierung weg (abgelehnt); `meldeStatusAnShop` ändert bei Widerruf den Woo-Status nicht. Sperren: picklisten.php, pickliste_erstellen.php, Warenausgang (Listen, scan.php, abschliessen.php), Kasse ajax_auftrag_laden (Abholung gesperrt, Retoure versendeter Ware erlaubt). Anzeige: roter Hinweis in detail.php, Chip in liste.php. Handbücher ergänzt. Klicktest `.claude-browser-tools/widerruf_erp_test.js` ok. **Nicht getestet:** Ablehnen im Shop und „withdrawn“.
- Achtung Abruf-Cursor: `modified_after` schließt den Zeitpunkt aus; für den Test musste `bestellungen_letzter_sync` um 1 s zurückgesetzt werden (die alte Version hatte die Bestellung schon übersprungen).
- WP-Zeitzone von indra-design steht offenbar auf UTC (date_modified = GMT) → in WP auf Wien stellen?
- **Mails:** kamen doch an, nur stark verzögert (vermutlich wegen DMARC p=reject + PHP-mail). ✅ WP Mail SMTP 4.10.3 installiert, SMTP mail.indra-design.at:465 SSL als bestellung@indra-design.at, Absender erzwungen; Testmail an office@ kam sofort an. Zugangsdaten: `import/zugang Mail bestellung@indra-design.at.txt` (von shop/ dorthin verschoben, weil shop/ nicht gitignored ist).
- **✅ Entscheidung im ERP gebaut 2026-11-05 (Jacky: single source, Babsi soll sich nicht im Shop anmelden):** Plugin NAHTLOS Connector **1.5.0** (live auf indra-design) mit `GET nahtlos/v1/widerruf/{bestellung}` und `POST …/{bestellung}/bestaetigen|ablehnen` (grund) → ruft `eu_owb_order_confirm_withdrawal_request` / `eu_owb_order_reject_withdrawal_request` auf (Antrag = eigener Woo-Order-Typ `shop_order_withdraw`, Status requested/confirmed/rejected; die Germanized-Mails an den Kunden gehen automatisch raus; beim Bestätigen gibt Woo den Bestand im Shop frei). ERP: `WiderrufService` + `auftraege/widerruf_aktion.php`, Buttons im roten Hinweis in detail.php (Artikel live aus dem Shop). Bestätigen voll + nicht versendet → `AuftragService::stornieren(..., kundenMail: false)`; versendet → 'widerrufen'; Teilwiderruf → Sperre weg, Positionen von Hand entfernen (vorher). Ablehnen → Pflicht-Grund, Sperre weg.
- Abholort: kein Fehler — Abholorte hängen an Lagern, NULL = Standard (Wollboutique), wird beim Packen gesetzt. Lücke nur bei mehreren Abholorten im Shop zur Auswahl.
- Jacky hat die WP-Zeitzone von indra-design auf Wien gestellt (2026-11-05).
- Der Engel steht in Woo noch auf Bestand 0 → nach dem Test wieder einbuchen.

**How to apply:** Bei jedem neuen Woo-Shop (SOW, bio-wolle, MeaLana) auf die Checkliste setzen: Germanized ≥ 4.0, Widerrufsbutton + Gewährleistungs-Info aktivieren, gleiche Einstellungen wie auf indra-design. Prüfskript nur lesend: `.claude-browser-tools/wp_widerruf_check4.js`. Siehe [[project-soft-golive-sockenwolle]], [[project-bio-wolle-shop-design]].
