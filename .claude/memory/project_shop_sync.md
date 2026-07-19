---
name: project-shop-sync
description: "Online-Shop-Anbindung (WooCommerce): Phase 1 Artikel/Kategorien-Sync im Bau, Testshop live verbunden"
metadata:
  node_type: memory
  type: project
  originSessionId: b67547bf-d9a0-405b-832f-e145eff451fa
  modified: 2026-07-19T16:35:40.871Z
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

## Offen für die nächste Session

1. **Kanal-Chips im Artikel-Formular** — UI zum Ein-/Ausschalten pro Shop (befüllt `artikel_shops` über `ShopSyncRepository::upsertZuweisung()`, existiert schon) — aktuell nur per SQL testbar, keine Oberfläche
2. **`cron/shop_sync.php`** — dünner Wrapper der `ShopSyncService::syncAlleShops()` per Windows Task Scheduler aufruft (analog `cron/mahnwesen.php`)
3. **`kategorie_shops` befüllen** — aktuell leer, Kategorie-Sync selbst (Kategorie im Shop anlegen + `externe_kategorie_id` speichern) ist noch nicht gebaut, nur die Zuordnungstabelle
4. **Vater/Kind-Artikel (Variable Products)** — `findFaelligeArtikel()` filtert aktuell bewusst nur Standard-Artikel ohne `vaterartikel_id`. Kind-Artikel→WooCommerce-Variations-Mapping ist deutlich komplexer (Achsen→Attribute, siehe `db_design_entscheidungen.md`) und bewusst auf eine eigene Session verschoben
5. **Phase 2 (Bestand)**, **Phase 3 (Bestellungen-Webhook + Polling-Sicherheitsnetz)**, **Phase 4 (Kunden-Merge)** — noch nicht begonnen, siehe Phasenplan oben in dieser Session besprochen
6. **JTL-Anreicherungs-Import** — eigenständige, kleinere Idee (siehe [[project_roadmap_reihenfolge]]), nicht Teil dieser Sync-Arbeit, aber gleichzeitig vorgemerkt

## Test-Rückstände (Dev-DB, harmlos aber zur Kenntnis)
`artikel_shops` hat eine echte Zeile für Artikel #150 (DROPS Baby Merino) → Shop 1, `sync_status='synced'`, `external_id=15`. Auf dem echten Testshop (`indra-design.at`) liegen dadurch zwei echte Produkte: #14 (Entwurf, reiner REST-Client-Test) und #15 (veröffentlicht, aus dem Sync-Testlauf, mit echten MeaLana-Artikeldaten). Beide können gelöscht werden, sobald nicht mehr als Referenz gebraucht.

**How to apply:** Bei Wiedereinstieg diese Datei UND `db_design_entscheidungen.md` (Abschnitt "Multi-Shop-Architektur"/"WooCommerce Kategorie-Sync") zusammen lesen — letztere hat die inhaltlichen Design-Entscheidungen (Achsen→Variations-Mapping etc.), diese Datei den tatsächlichen Baufortschritt.
