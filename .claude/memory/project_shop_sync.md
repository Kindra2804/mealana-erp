---
name: project-shop-sync
description: "Online-Shop-Anbindung (WooCommerce): Phase 1 Artikel/Kategorien-Sync im Bau, Testshop live verbunden"
metadata:
  node_type: memory
  type: project
  originSessionId: b67547bf-d9a0-405b-832f-e145eff451fa
  modified: 2026-07-19T16:27:56.605Z
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

## Offen für die nächste Session

1. **`ShopSyncRepository`/`ShopSyncService`** — die eigentliche Sync-Logik: fällige Artikel finden (`aktualisiert_am` > `synced_at` oder `sync_status='pending'`), Artikel→WooCommerce-Payload-Mapping bauen (Name/Beschreibung/Preis/Kategorien/Bilder/Attribute aus Achsen+Merkmalen), Fehler mit `Logger::log(..., stufe='error')` protokollieren
2. **Kanal-Chips im Artikel-Formular** — UI zum Ein-/Ausschalten pro Shop (befüllt `artikel_shops`)
3. **Phase 2 (Bestand)**, **Phase 3 (Bestellungen-Webhook + Polling-Sicherheitsnetz)**, **Phase 4 (Kunden-Merge)** — noch nicht begonnen, siehe Phasenplan oben in dieser Session besprochen
4. **JTL-Anreicherungs-Import** — eigenständige, kleinere Idee (siehe [[project_roadmap_reihenfolge]]), nicht Teil dieser Sync-Arbeit, aber gleichzeitig vorgemerkt

**How to apply:** Bei Wiedereinstieg diese Datei UND `db_design_entscheidungen.md` (Abschnitt "Multi-Shop-Architektur"/"WooCommerce Kategorie-Sync") zusammen lesen — letztere hat die inhaltlichen Design-Entscheidungen (Achsen→Variations-Mapping etc.), diese Datei den tatsächlichen Baufortschritt.
