---
name: project-shop-sync
description: "Online-Shop-Anbindung (WooCommerce): Phase 1 komplett fertig (Sync-Logik, Kanal-Chips/Gating/Filter, Kategorie-Sync); offen nur noch cron + Live-Rollout-Themen"
metadata:
  node_type: memory
  type: project
  originSessionId: b67547bf-d9a0-405b-832f-e145eff451fa
  modified: 2026-07-20T17:43:49.724Z
---

## Referenz-Check (2026-07-19)

- **JTL-Connector** (Jacky kennt nur JTL↔JTL-Shop, nicht JTL↔WooCommerce): vollautomatischer Echtzeit-Abgleich, **ERP ist führend** (deckt sich mit unserer Hub-and-Spoke-Entscheidung), granular pro Datentyp konfigurierbar (z.B. "nur Bestand raus, nur Bestellungen rein").
- **JTL-Lektion von Jacky**: Sync-Worker fiel nach Server-Neustart mal aus, bemerkt erst als Kunde nach seiner Bestellung fragte — reiner Monitoring-Gap. Bei uns über Logger-UI (`stufe='error'`) abgedeckt: Sync-Fehler landen sofort in Shell-Zeile + Aktivitäten-Log, nicht erst auf Kundennachfrage. **Vorgemerkt für "ganz am Ende"**: automatisierter Neustart+Health-Check des Servers nach Stromausfall/Reboot (Jackys Idee, niedrige Priorität).
- **WooCommerce-Best-Practice** (aktuelle Websuche): Hybrid-Ansatz — Webhooks für Bestellungen (Echtzeit), Polling als Sicherheitsnetz (WooCommerce deaktiviert Webhooks automatisch nach 5 fehlgeschlagenen Zustellungen, still). Webhook-Handler soll WooCommerce nicht warten lassen (Event entgegennehmen, Verarbeitung async).

## Business-Entscheidung (Jacky, 2026-07-19)

Shops sind **immer B2C**. Nur der Endkunden-Preis geht in den Shop, kein Kundengruppen-Mapping nötig für den Start. Falls später B2B-Web-Bestellungen kommen: **eigener Shop unter eigener Subdomain** statt Multi-Preis-Logik in einem Shop — passt zur bestehenden Multi-Shop-Architektur (`shops`-Tabelle unterstützt beliebig viele unabhängige Instanzen), kein Umbau nötig, nur eine neue Zeile + späteres "welcher Preis für welchen Shop"-Flag.

## Ist-Stand vs. altem Design-Dokument (`db_design_entscheidungen.md`, war 5 Wochen alt)

Die alte Design-Session skizzierte `shops`/`artikel_shops`/`kategorie_shops`/`sync_konfiguration`/`sync_log` — real umgesetzt war nur eine einfachere `shops`-Tabelle (id/slug/name/logo_pfad/sub_marke/wc_url/wc_key/wc_secret/ist_aktiv) plus das Sync-Tracking-Pattern schon zweimal woanders gebaut (`artikel_bilder_shops`, `kunden_shops`: external_id/sync_status enum pending-synced-error/synced_at/fehler_meldung). `artikel_shops`/`kategorie_shops` existierten NICHT, genauso wenig wie jeglicher Sync-Code (verifiziert per grep über src/ — nichts gefunden).

## ✅ Phase 1 Grundgerüst FERTIG (2026-07-19)

- **Migration 142**: `artikel.aktualisiert_am` (fehlte komplett — ohne das kein Change-Detection fürs Sync-Cron möglich), neue Tabellen `artikel_shops` (gleiches Pattern wie kunden_shops/artikel_bilder_shops, PLUS `aktiv`-Flag: 0 = beim nächsten Sync im Shop auf Entwurf setzen statt löschen), `kategorie_shops` (1:1 aus dem alten Design übernommen)
- **`src/modules/shop/WooCommerceClient.php`**: dünner REST-Wrapper (system_status/getProdukt/listeProdukte/erstelleProdukt/aktualisiereProdukt), Auth über Consumer-Key/Secret als Query-Parameter, curl-basiert
- **Einstellungen → Kanäle**: `wc_key`/`wc_secret`-Felder ergänzt (fehlten bisher, nur `wc_url` war editierbar) — sowohl beim Bearbeiten bestehender Shops als auch beim Neuanlegen
- **Wichtig für Kind-Artikel**: `artikel_shops` bekommt eine Zeile PRO Artikel-Zeile (auch Kind-Artikel/Varianten), nicht nur Väter — WooCommerce vergibt eigene IDs für Variable-Product UND jede einzelne Variation

## Testshop (Jacky, 2026-07-19)

WordPress+WooCommerce auf `https://indra-design.at` installiert (Haupt-, nicht Subdomain — Domain war leer, kein Problem). REST-API-Key mit Lesen/Schreiben erzeugt, in `shops.id=1` ("mealana"-Zeile, nicht als neuer Kanal — für Dev-Zwecke unkritisch, aber falls das aus Versehen war: Shop 1 wird sonst für Logo/Absender auf echten Kassenbons verwendet) eingetragen.

**Stolperstein unterwegs**: erster Verbindungstest → 404 (generische Hosting-Fehlerseite, keine WordPress-Antwort). Ursache: WordPress-Permalinks standen auf "Einfach" — REST-API (`/wp-json/...`) braucht eine "schöne" Permalink-Struktur, sonst fehlen die Rewrite-Regeln. Fix: Einstellungen → Permalinks → andere Option wählen → Speichern (erzwingt `.htaccess`-Neuschreiben). Nach dem Fix: Verbindung erfolgreich (WordPress 7.0.2, WooCommerce 10.9.4).

**Alle drei Grundoperationen live verifiziert**: GET (system_status, Produktliste — leer, kein Demo-Content), POST (Testprodukt #14 als `status=draft` angelegt, nicht öffentlich sichtbar), PUT (Preis erfolgreich geändert). Testprodukt #14 liegt noch als Entwurf auf dem Shop, kann jederzeit gelöscht werden.

## ✅ Phase 1 Sync-Logik FERTIG + End-to-End getestet (2026-07-19, gleicher Tag)

`src/modules/shop/ShopSyncRepository.php` + `ShopSyncService.php`: findet fällige Standard-Artikel (kein Vater/Kind, das kommt später), baut WooCommerce-Payload (Name/SKU/Beschreibung/Endkunden-Bruttopreis/Kategorien/Status publish-oder-draft je nach `artikel.aktiv`), POST bei erstem Sync (leere `external_id`), PUT bei Wiederholung.

**Kompletter Testlauf gegen den echten Testshop** (Artikel #150 "DROPS Baby Merino"):
1. Erst-Sync → WooCommerce-Produkt #15 neu angelegt, alle Felder korrekt (Name/SKU/Preis 3,75€/Status publish) — per GET gegengeprüft
2. Änderung simuliert (`kurzbeschreibung` geändert) → zweiter Sync → **kein neues Produkt**, dieselbe `external_id=15` aktualisiert (Update-Pfad korrekt)
3. Fehler simuliert (falsches `wc_secret`) → sauberer Fehler, `artikel_shops.sync_status='error'` + Fehlermeldung gespeichert, `aktivitaeten`-Log-Eintrag mit `stufe='error'` — genau der Fall der bei JTL nur durch Kundennachfrage aufgefallen ist, hier sofort sichtbar

**🔴 Echter Bug gefunden + gefixt:** `ShopSyncService` rief `Logger::log(..., stufe: 'error')` ohne explizite `benutzerId` auf — funktioniert nur mit aktiver Session, crasht aber (`aktivitaeten.benutzer_id NOT NULL`) in jedem Cron-/CLI-Kontext, also GENAU dem Kontext in dem der Sync später laufen soll. Gleiches Bug-Muster wie schon bei `cron/mahnwesen.php` und `LagerService::wareneingang()` (siehe [[project_installationsanleitung]]). Fix: Jarvis-ID im Konstruktor per `username='system'` auflösen, explizit an jeden `Logger::log()`-Aufruf durchreichen. **Lehre bestätigt sich zum dritten Mal:** jede neue Service-Klasse die potenziell aus einem Cron laufen könnte, braucht das von Anfang an, nicht erst wenn's das erste Mal ohne Session crasht.

## ✅ Kanal-Chips + Vater/Kind-Gating + Kanal-Filter FERTIG (2026-07-20)

Kompletter Bau + End-to-End-Test gegen echte Dev-DB (Artikel #150/#172/#251), danach aufgeräumt (Test-Isolation).

- **Einzelartikel** (`public/artikel/detail.php`): der bisher tote Actionbar-Button "Im Shop ▼" ist jetzt ein Dropdown mit einem Chip pro Shop (grün=an, grau=aus, orange="wartet auf Vater"). Klick toggelt sofort per neuem `public/artikel/kanal_ajax.php` (JSON-Body, `action=toggle`) → `ShopSyncRepository::upsertZuweisung()`. JS-Rendering in `public/js/artikel_detail.js` (`kanalToggle()`/`renderKanalPanel()`), CSS `.kanal-panel-zeile` in `components.css`.
- **Vater/Kind-Regel (Jackys Vorgabe):** Kind kann nur effektiv aktiv sein, wenn der Vater es im selben Shop auch ist; Vater aktiv erzwingt aber NICHT alle Kinder aktiv. Gelöst ganz ohne neue Spalte/kaskadierendes Überschreiben: jede Zeile (auch Kind) behält ihren eigenen `artikel_shops.aktiv`-"Wunsch", der effektive Status wird zur Laufzeit als `eigener_status AND vater_status` berechnet — neue Methode `ShopSyncRepository::findKanalStatusFuerArtikel()`. Kind-Wunsch bleibt beim kurzzeitigen Vater-Deaktivieren erhalten und greift automatisch wieder, sobald der Vater erneut an ist (verifiziert per Testskript).
- **Artikelliste** (`public/artikel/liste.php`): der schon vorhandene Platzhalter `renderShopChips()`/`.kc`-CSS ist jetzt mit echten Daten befüllt — `ArtikelRepository::findAll()` (Vater/Standalone, eigene Zuweisung) und `::findKinderFuerListe()` (Kind, mit Vater-Gating via LEFT JOIN) liefern beide ein `shop_kanaele`-Feld (`S{shop_id}`-Codes, comma-separiert). Shop-Legende unten ist jetzt dynamisch aus der `shops`-Tabelle gerendert (vorher hartcodiert S1/S2/S3 mit falscher Zuordnung zu den echten Shop-IDs).
- **Massenaktion "Kanal zuweisen"**: neuer Punkt im Aktion-Dropdown, Modal analog zum Bulk-Kategorie-Modal (ein Shop pro Durchlauf + Aktivieren/Deaktivieren-Radio, Jackys Entscheidung gegen Mehrfach-Shop-Modal). Neuer Endpunkt `public/artikel/bulk_shop_speichern.php`. Kein Propagations-Write nötig (siehe Gating-Logik oben) — Umschalten am Vater wirkt automatisch auf alle Kinder, ohne dass deren Zeilen angefasst werden.
- **Kanal-Filter in der Suchzeile**: war bisher `disabled` mit hartcodierten/falschen S1-S3-Labels — jetzt aktiv, dynamisch aus `shops`-Tabelle, filtert `ArtikelRepository::findAll()`/`::countAll()` über `EXISTS`-Check auf die eigene Vater/Standalone-Zuweisung (Kind-Prüfung nicht nötig, da ein Kind laut Gating-Regel nie effektiv aktiv sein kann wenn der Vater es nicht ist). K1/K2 (Kassen) bleiben als Optionen sichtbar aber disabled, da sie immer für alle Artikel gelten.

## ✅ `kategorie_shops` befüllen FERTIG (2026-07-20, gleicher Tag)

`ShopSyncService::syncShop()` synct jetzt vor jedem Artikel-Push dessen Kategorie(n) + alle Vorfahren nach WooCommerce (voller Pfad über `parent`, siehe `db_design_entscheidungen.md`). Neue Methoden: `WooCommerceClient::erstelleKategorie()`, `ShopSyncRepository::findKategorieIdsFuerArtikel()`/`findKategorieMitVorfahren()`/`findKategorieShopZuweisung()`/`upsertKategorieZuweisung()`.

**Live getestet** gegen `indra-design.at` mit Artikel #150s echtem 3-Ebenen-Pfad (Wolle und Garne → Hersteller → Garnstudio DROPS): alle drei Ebenen korrekt mit richtiger Eltern-Verkettung angelegt (per GET gegengeprüft), zweiter Lauf hat nichts doppelt angelegt (Idempotenz bestätigt über gespeicherte `externe_kategorie_id`), danach aufgeräumt (WC-Kategorien gelöscht, `kategorie_shops` geleert, Testshop unverändert).

**Bewusst NICHT gebaut (Jacky, 2026-07-20): Umbenennung/Update-Sync.** Aktuell reines Erstanlegen — wenn eine Kategorie im ERP umbenannt wird, zieht das NICHT automatisch in WooCommerce nach (keine `aktualisiereKategorie()`-Methode, `kategorie_shops` hat auch keine Status/Fehler-Spalten wie `artikel_shops` für Change-Detection). **Zusammen mit `cron/shop_sync.php` zurückgestellt, bis das System auf Live gespielt wird** — dann beides in einem Rutsch nachziehen, nicht vorher isoliert bauen.

## ✅ Kanal-Chips im Kategoriebaum (Sidebar) FERTIG (2026-07-20, gleicher Tag)

Letzter offener Punkt aus der alten "Kanal-Chips an Kategorien"-Entscheidung (`db_design_entscheidungen.md`, 2026-06-21) — Jacky hatte ein Mockup mit Chips im Sidebar-Kategoriebaum + kompakter Kanal-Legende darunter (nur Shops, keine Kassen, analog zur bereits bereinigten Legende in `liste.php`).

- `KategorieRepository::findAllMitEltern()`: neue Subquery liefert `eigene_shop_codes` pro Kategorie (welche Shops haben dort direkt zugewiesene, aktive Artikel)
- `ArtikelService::getKategorienBaum()` + neue private `berechneShopChips()`: rekursive Bottom-up-Vererbung — leere Elternkategorien erben von Kindkategorien, exakt wie in der alten Design-Entscheidung festgelegt, ganz ohne manuelle Pflege
- `shell_top.php` (`renderKatKnoten()`): rendert `.kc`-Chips unter jedem Kategorienamen + neue `.sidebar-kanal-legende` unterhalb des Baums (nur S1/S2/S3, dynamisch aus `shops`-Tabelle)
- Rein lesend gegen Dev-DB getestet (Artikel #150 → Garnstudio DROPS → Shop 1 aktiv): S1-Chip erscheint korrekt bei der Blatt-Kategorie und vererbt sich nach oben zu "Hersteller" und "Wolle und Garne", Geschwister-Kategorien ohne aktive Artikel bleiben leer. Von Jacky im Browser bestätigt.

## Offen für die nächste Session

1. **`cron/shop_sync.php` + Kategorie-Umbenennung-Sync** — beide bewusst zusammen zurückgestellt bis zum Live-Rollout (siehe oben), nicht einzeln vorziehen
2. **Vater/Kind-Artikel (Variable Products) — eigentlicher WooCommerce-Sync**: `findFaelligeArtikel()` filtert weiterhin bewusst nur Standard-Artikel ohne `vaterartikel_id`. Die Kanal-ZUWEISUNG (an/aus, Gating) ist jetzt startklar für Kinder, aber das Kind-Artikel→WooCommerce-Variations-Mapping selbst (Achsen→Attribute, siehe `db_design_entscheidungen.md`) ist noch nicht gebaut und weiterhin auf eine eigene Session verschoben
3. **Phase 2 (Bestand)**, **Phase 3 (Bestellungen-Webhook + Polling-Sicherheitsnetz)**, **Phase 4 (Kunden-Merge)** — noch nicht begonnen, siehe Phasenplan oben in dieser Session besprochen
4. **JTL-Anreicherungs-Import** — eigenständige, kleinere Idee (siehe [[project_roadmap_reihenfolge]]), nicht Teil dieser Sync-Arbeit, aber gleichzeitig vorgemerkt

## Test-Rückstände (Dev-DB, harmlos aber zur Kenntnis)
`artikel_shops` hat eine echte Zeile für Artikel #150 (DROPS Baby Merino) → Shop 1, `sync_status='synced'`, `external_id=15`. Auf dem echten Testshop (`indra-design.at`) liegen dadurch zwei echte Produkte: #14 (Entwurf, reiner REST-Client-Test) und #15 (veröffentlicht, aus dem Sync-Testlauf, mit echten MeaLana-Artikeldaten). Beide können gelöscht werden, sobald nicht mehr als Referenz gebraucht.

**How to apply:** Bei Wiedereinstieg diese Datei UND `db_design_entscheidungen.md` (Abschnitt "Multi-Shop-Architektur"/"WooCommerce Kategorie-Sync") zusammen lesen — letztere hat die inhaltlichen Design-Entscheidungen (Achsen→Variations-Mapping etc.), diese Datei den tatsächlichen Baufortschritt.
