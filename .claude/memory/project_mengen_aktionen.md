---
name: project-mengen-aktionen
description: "Mengen-Aktionen „X für Y €“ + „Kauf X, zahl Y“ (Kasse + Shop) – Entscheidungen Jacky 2026-11-14, Bau in 5 Schritten"
metadata:
  type: project
---

Punkt 2 der Reihenfolge ([[project-roadmap-reihenfolge]]), TODO aus [[project-preise]]. Erster Fall: Indra-Weihnachtsanhänger 3,50 €, 4 für 12 € gemischt. Übergang bis jetzt: WPCode-Snippet 34685 (indra), Kasse händisch.

**Entscheidungen Jacky 2026-11-14:**
1. Zwei Arten: **„X Stück für Y €“** (4 für 12) und **„Kauf X, zahl Y“** (3 für 2, das günstigste gratis). Keine Staffeln (gibt es schon).
2. Gruppe: Kategorien + einzelne Artikel (Massenaktion „Zu Mengen-Aktion hinzufügen“). Vater nimmt Kinder mit, beliebig gemischt.
3. Wiederholt sich (9 Stück = 2× 4 für 12 + 1 normal).
4. „4 für 12“ mit gemischten Preisen: die teuersten Stücke zuerst ins Paket. „3 für 2“: das günstigste gratis.
5. Keine doppelten Rabatte: Artikel mit laufendem Sale-/Aktionspreis zählen nicht mit.
6. Nur Standard-Kundengruppe (Endkunden).
7. Je Aktion Zeitraum (ohne Ende möglich) + Kanäle (Kasse, jeder Shop einzeln).
8. Rabatt in die Positionen eingerechnet, **GLEICHMÄSSIG auf alle Stücke im Paket verteilt** (auch bei 3 für 2, wegen Teil-Retoure: sonst behält der Kunde das Gratis-Stück, wenn er ein bezahltes zurückschickt). Hinweis „Aktion …“ auf dem Beleg, keine Minuszeile. Woo-Bestellungen kommen mit reduzierten Positionspreisen.
9. Shop: automatisches Badge („4 für 12 €“ / „3 für 2“) + Warenkorbhinweis („Noch 1 Stück, dann gilt …“).

**Technik-Notizen:** kassen_bon_positionen.rabatt_prozent / auftrag_positionen.rabatt_prozent ist DECIMAL(5,2) → Cent-Rundung beachten (Paketsumme muss exakt stimmen). einzelpreis_brutto hat 4 Nachkommastellen. Eigene Tabellen, nicht das bestehende Aktionsmodul ([[project-aktionen-modul]], feste Preise je Kategorie).

**Bau-Reihenfolge:** 1. Verwaltung (Migr. 241 `mengen_aktionen` + Kategorien/Artikel/Shops, Seite unter Preise/Aktionen, Massenaktion) → 2. Rechenkern (PHP + gleich in JS) → 3. Kasse → 4. Shop-Plugin (NAHTLOS Connector, ersetzt Snippet 34685) → 5. Bestellübernahme prüfen.

**Fortschritt:** Schritt 1 ✅ (e5559b5), Schritt 2 Rechenkern ✅ (0fbbd0b, `MengenAktionRechner.php` + `js/mengen_aktion_rechner.js`, Prüffälle `erp/tests/mengen_aktion_*`). Anzeige Kasse (Jacky ok 2026-11-14): alter Preis durchgestrichen + neuer, darunter „Aktion 4 für 12 €“, unten „Ersparnis durch Aktion“; Zeile mit manuellem Rabatt fällt aus der Aktion.

**Erweiterung Staffel über Varianten (Jacky 2026-11-14):** Fall „Raunzmarker“ (MeaLana, handgemachte Maschenmarkierer, evtl. Indra; auf Dev nicht vorhanden), 12 Varianten gemischt: 1 Stk 2,50 · 2 Stk 4,50 · 4 Stk 8,– · 8 Stk 15,– · 12 Stk 20,–. Plan: Preise bleiben bei den Staffelpreisen des Vaters + Haken „Menge über alle Varianten zusammenzählen“ (war seit Juni geplant, nie gebaut), Rechnung als dritte Regel-Art im Rechenkern. Fund: Staffelpreise wirken heute NIRGENDS (weder Kasse noch Shop-Sync). **Entschieden Jacky:** Variante B, „schöne“ Preise statt Stückpreis der Stufe (5 Stk = 8,00 + 2,50 = 10,50; heute händisch so gerechnet). → Umgesetzt als **dritte Art `paketpreise`** der Mengen-Aktionen (Migr. 242, Tabelle `mengen_aktion_pakete`, menge = kleinste Größe), NICHT über Staffelpreise. Günstigste Zerlegung per DP (16 = 12+4), teuerste Stücke in die Pakete. Commit nach 0fbbd0b, 17 Prüffälle. Raunzmarker auf Live als Mengen-Aktion anlegen (dauerhaft, Vater zuordnen), sobald der Artikel im ERP ist.
- **Eigener offener Punkt:** echte Staffelpreise (artikel_staffelpreise) wirken nirgends – Kasse/Shop ignorieren sie.

**Schritt 3 Kasse ✅ 2026-11-14 (Migr. 243):** bon.php rechnet live (mengenAktionenAnwenden → p._mengen, posSumme), Footer zeigt Ersparnis + „noch x Stk → …“. bon_speichern.php rechnet mit PHP-Rechner NACH und speichert reduzierten einzelpreis_brutto (4 Nachkommastellen) + preis_vorher_brutto + mengen_aktion_id + aktion_hinweis (kassen_bon_positionen). Bon-Druck/A4/Storno zeigen es. Kasse nutzt nur Standardpreis (Sale/Aktion kommen an der Kasse gar nicht an) → „reduziert“ = manueller Zeilen-/Bon-Rabatt. Offline-/Messe-Kasse (kasse_bon_offline.js, MesseSyncService) noch OHNE Mengen-Aktionen. Klicktest Jacky offen.

**Offene Nebenpunkte (Jacky 2026-11-14 gefragt, nach dem Plugin angehen, vor dem nächsten Paket):**
- ✅ 2026-11-14: Kasse kennt jetzt Sale-/Aktionspreise (KassenService::mitEffektivPreis über PreisService, Standard-KG; Anzeige „Sale · statt €“; reduzierteArtikel() für bon_speichern → keine Mengen-Aktion). Messe-/Offline-Kasse (messe_strichliste, MesseSync) weiter nur Standardpreis.
- Echte Staffelpreise (artikel_staffelpreise) wirken nirgends (Kasse + Shop); auf Dev 0 Einträge → niedrige Priorität, auf der Liste lassen.
- Offline-/Messe-Kasse ohne Mengen-Aktionen.

**Schritt 4 ✅ 2026-11-14 (d674de4, Migr. 244):** NAHTLOS Connector **1.7.0** (`includes/mengen-aktionen.php`, Rechner-Kopie `includes/MengenAktionRechner.php` – Test prüft md5-Gleichheit). Alle 3 Shops nutzen **Block-Warenkorb/-Kasse** → Preis über woocommerce_before_calculate_totals (Block zeigt alten Preis selbst durchgestrichen), Hinweis je Zeile über woocommerce_get_item_data, Badge (Loop + Produkt-Blöcke + Produktseite), Bestell-Meta „Aktion“. Sale-Artikel (is_on_sale) ausgenommen. Zeitraum prüft der Shop (Europe/Vienna). ERP: shopPayload/uebertrageShop/uebertrageAlle (shops.mengen_hash/_uebertragen_am/_fehler), beim Speichern + Cron shop_sync.php, nur bei Änderung, pausierte Shops übersprungen.
- **Offen:** Plugin auf den Shops installieren (Live-Test mit Jacky abstimmen), dann **Snippet 34685 (indra, Weihnachtsanhänger) deaktivieren** – sonst doppelter Rabatt. Schritt 5: echte Testbestellung → ERP-Import prüfen (Positionspreise reduziert).

**Live-Test indra 2026-11-14 abends ✅ (Variante A):** Wartung an, Live-Sync pausiert (Jacky), Plugin **1.7.0 auf indra-design installiert** (bleibt, Aktionsliste leer), Dev-Kanal 4 mit vorübergehendem Schlüssel + App-PW, Dev-external_ids der 22 Anhänger kurz auf Live-Woo-IDs (Dev hat veraltete IDs vom alten Shop!), Testaktion „4 für 12 €“ gepusht. Badge, Produktseite, Block-Warenkorb (Durchstreichen + „Spare“ + Hinweis) ok. Testbestellung 35365 → Dev A-IND-2026-00005: 4 × 3,00 + 3,50 + 6,50 Versand = 22,00 ✅ (Schritt 5 erledigt). Badge auf Jackys Wunsch neben den Preis verschoben (3d3ba93). Danach alles zurück: leere Liste gesendet, Bestellung gelöscht, Schlüssel + App-PW widerrufen, Dev-Kanal geleert, Snippet 34685 wieder AN, Wartung aus. **Jacky: Live-Sync indra wieder einschalten.**
- Snippet 34685 heißt „Weihnachtsanhänger **3 für 12 €**“ → auf Live die Aktion so anlegen. Snippet erst ausschalten, wenn Live das Paket mit Migr. 241–244 hat und die Aktion übertragen ist (sonst doppelter Rabatt).
- ✅ Connector 1.7.1 auf allen 3 Shops (2026-11-14).
- Plugin-ZIP NICHT mit PowerShell Compress-Archive bauen (Backslash-Pfade → WP behält alte Version), sondern mit Python zipfile und „/“-Pfaden.
