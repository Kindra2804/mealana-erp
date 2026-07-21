---
name: project-hersteller-shop-filter
description: "Hersteller-Filter im Shop: ALS WC-Produktattribut FERTIG 2026-07-21 (unabhängig vom bestehenden Kategorie-Ast); GPSR-Herstellerangaben-Pflicht weiterhin bewusst zurueckgestellt"
metadata: 
  node_type: memory
  type: project
  originSessionId: bcf52b92-a756-4c54-8a41-faaebdece89e
  modified: 2026-07-21T16:03:41.351Z
---

## ✅ Hersteller-Filter (WC-Produktattribut) FERTIG (2026-07-21)

**Entscheidung zum Kategorie-Ast geklärt (Jacky, 2026-07-21):** Der bestehende
Kategorie-Ast "Hersteller" ist ein reiner Vor-Filter/Gruppierung für Kunden
(wie Zubehör/Amigurumi/...), kein "echter" auf den Hersteller fokussierter
Eintrag. Bleibt darum **komplett unangetastet und unabhängig** vom neuen
Attribut bestehen -- kein Aufräumen, keine Änderung am bestehenden
Kategorie-Sync-Code.

**Umsetzung:** EIN globales WC-Attribut "Hersteller" mit `has_archives: true`
(WooCommerce baut daraus automatisch Übersichts-/Einzelseiten je Hersteller).
Neue Tabelle `hersteller_shops` (Migration 144+145: hersteller_id, shop_id,
externe_attribut_id, externe_term_id) -- die Attribut-ID ist pro Shop überall
gleich (nur EIN Attribut, anders als bei den Achsen mit mehreren
unterschiedlichen Attributen), wird darum bewusst redundant pro Zeile
mitgespeichert statt einer eigenen Ein-Zeilen-Tabelle nur dafür.

Nur am Vater/Standalone-Payload angehängt (`variation => false`, ändert
`type` nicht), NICHT an Kind-Variationen -- WooCommerce-Variationen kennen
nur die eigenen Variations-Attribute des Elternprodukts, keine zusätzlichen
Nicht-Variations-Attribute. Gespeist aus dem längst vorhandenen
`artikel.hersteller_id`-Dropdown, keine doppelte Pflege.

**End-to-End getestet** gegen `indra-design.at` (gleiches Vater/Kind-Paar
#2852/#2853/#2854 wie beim Variations-Test): Vater korrekt mit ZWEI
Attributen (Farbe variation=true, Hersteller variation=false + has_archives),
Idempotenz über 3 Durchläufe bestätigt. Aufgeräumt (WC-Produkt+beide
Attribute gelöscht, alle Test-Zeilen aus den Zuweisungstabellen entfernt).

**GPSR-Herstellerangaben bleiben wie gehabt zurückgestellt** (siehe Abschnitt
unten) -- unabhängig von diesem Attribut-Feature, betrifft nur die
Herstellerkontaktdaten auf der Produktseite selbst.

## Business-Entscheidung (Jacky, 2026-07-20)

**Hersteller-Filter im Shop soll als WooCommerce-Produktattribut umgesetzt werden, nicht über den bestehenden Kategorie-Ast** ("Wolle und Garne → Hersteller → X", siehe [[db_design_entscheidungen]] Abschnitt "WooCommerce Kategorie-Sync" — der Ast existiert bereits und wurde live gegen den Testshop gesynct).

**Why:** Manche Hersteller bieten sowohl Garne als auch Zubehör an — als Unterkategorie von "Wolle und Garne" eingesperrt, kann ein Hersteller nicht gleichzeitig mehreren Produktkategorien zugeordnet sein. Ein Produktattribut ("Hersteller" mit WooCommerce "Archive aktivieren") ist kategorieübergreifend und WooCommerce generiert automatisch Übersichts- + Einzelseiten dafür — kein Plugin nötig, analog zu JTL-Shops nativem Hersteller-Verzeichnis (Vorbild: mealana.at/Hersteller unter JTL).

**Wichtiger Vorteil ggü. der Kategorie-Lösung:** Ein Attribut-Sync könnte direkt aus dem bereits vorhandenen `artikel.hersteller_id`-Feld gespeist werden (Dropdown existiert schon am Artikel-Formular, jeder Artikel hat das längst gesetzt) — keine doppelte manuelle Pflege wie aktuell bei der Kategorie-Zuweisung (Hersteller-Kategorie muss bisher separat von Hand zugewiesen werden, unabhängig vom `hersteller_id`-Dropdown).

**Noch offen / nicht am 2026-07-20 entschieden:** Ob/wie der bestehende "Hersteller"-Kategorie-Ast danach aufgeräumt, ersetzt oder einfach parallel weitergeführt wird. Auch die technische Sync-Umsetzung selbst (WC-Attribut anlegen, Terms pro Hersteller, Zuweisung beim Artikel-Sync) ist noch nicht gebaut — reine Richtungsentscheidung bisher.

## GPSR-Herstellerangaben-Pflicht — Umsetzung zurückgestellt (Jacky, 2026-07-20)

Recherche in dieser Session ergab: Art. 19 GPSR (General Product Safety Regulation, EU-weit seit 13.12.2024) verlangt Name/Handelsname + Post- und E-Mail-Adresse des Herstellers direkt auf der Produktseite im Online-Shop (nicht nur irgendwo verlinkt erreichbar). Bei Herstellern außerhalb der EU zusätzlich ein "Responsible Economic Operator" (EU-Ansprechperson) mit eigenen Kontaktdaten.

Quellen: [Wikipedia](https://en.wikipedia.org/wiki/General_Product_Safety_Regulation), [KPMG-Law](https://kpmg-law.de/en/gpsr-what-the-new-eu-product-safety-regulation-means/), [EU Access2Markets](https://trade.ec.europa.eu/access-to-markets/en/news/eus-general-product-safety-regulation-gpsr-new-era-consumer-protection).

**Wichtiger Fund:** Die `hersteller`-Tabelle hat bereits alle dafür nötigen Felder — `name`/`strasse`/`plz`/`ort`/`land`/`email` PLUS separat `reo_name`/`reo_strasse`/`reo_plz`/`reo_ort`/`reo_land`/`reo_email` (reo = Responsible Economic Operator, exakt der EU-Verantwortliche-Person-Fall). War beim DB-Design also schon mitgedacht, ist aber aktuell nirgends in den Shop-Sync verdrahtet — `ShopSyncService::baueProduktPayload()` schickt nur Name/SKU/Beschreibung/Preis/Status/Kategorien, keine Hersteller-Kontaktdaten.

**Jacky stellt die konkrete Umsetzung bewusst zurück**, bis er entweder Detailantworten hat (vermutlich Rechts-/Steuerberatung) oder sich angeschaut hat, wie Mitbewerber-Shops das in der Praxis lösen. **Kein Anwalt-Ersatz durch Claude** — vor Live-Umsetzung rechtlich absichern.

**Fallback-Idee (Jacky), falls nichts Elegantes gefunden wird:** Eigene kleine Unterseite bauen, die alle Hersteller + deren GPSR-Pflichtangaben aus der `hersteller`-Tabelle auflistet — "dann haben wir zumindest mal nicht Nix".

**How to apply:** Bei Wiedereinstieg ins Thema Shop-Sync/Hersteller diese Datei lesen, bevor mit der Umsetzung begonnen wird — beide Punkte sind bisher reine Design-Vorentscheidungen, noch keine Code-Umsetzung. Siehe auch [[project_shop_sync]] für den Gesamtstand der Online-Shop-Anbindung.
