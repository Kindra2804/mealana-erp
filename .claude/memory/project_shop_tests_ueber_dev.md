---
name: project-shop-tests-ueber-dev
description: "Live-Shop-Tests (Bestellung/Rechnung/Gutschrift/Widerruf) über das Dev-ERP statt Live — Ablauf, Schutzmaßnahmen, Aufräumen (Jacky-Idee 2026-11-13)"
metadata:
  type: project
---

**Idee Jacky 2026-11-13:** Solange SOW/Bio hinter der Wartungsseite stehen (indra kurz mit eigener Wartungsseite), den Shop für Tests ans **Dev-ERP** hängen statt ans Live-ERP → keine Testbelege/Nummern im Live-ERP. Dev = 1.0.10 = der Code, der live kommt.

**Grenzen:** Live-Kanal-Einstellungen (Zahlungsarten-Zuordnung, Bank, Mail-Absender, Versandklassen) werden so nicht getestet → erste echte Live-Bestellung trotzdem kontrollieren. Bestellungen werden über die **SKU** zugeordnet → SOW/Bio-Artikel müssen erst per `scripts/datentransfer.php export --shop=SOW` (auf dem Live-Server, Jacky) → Import auf Dev. Achtung: Artikel mit gleicher Artikelnummer bleiben auf Dev unverändert (MeaLana-JTL-Artikel) → Probelauf prüfen.

**Schutz (Dev schreibt sonst Artikel/Gutschein-Codes/Slider/Menü in den Live-Shop):**
1. Windows-Aufgabe **„Shop-Sync-Cronjob“ auf Jackys Dev-PC** läuft alle 15 min gegen die Dev-DB. Seit Fix a252d86 (Slider/Menü-Cron respektiert `sync_pausiert`) überspringt sie pausierte Kanäle komplett → Abschalten nicht nötig (Disable bräuchte Admin-Rechte).
2. Dev-Kanal: `sync_pausiert = 1` UND `bulk_import_aktiv = 1`.
3. Nur `php erp/scripts/shop_test_bestellungen.php --shop=SOW [--seit=…]` → holt Bestellungen + meldet Status, sonst nichts (prüft 2.).
4. Eigener Woo-Schlüssel „ERP Dev-Test“ + App-Passwort, danach widerrufen.

**Stand 2026-11-13 13:40:** Dev gesichert (`D:/ERP/backups/dev_vor_sow_bio_import_20261113.sql`), SOW-Paket importiert (18 fehlende Artikel; 1.482 gab es schon aus MeaLana-JTL → SKU-Suche ist global, reicht), Bio nicht nötig (alle 481 da). Dev-Kanäle 2/3/4 auf sync_pausiert=1 + bulk_import_aktiv=1. Fix a252d86 muss mit ins nächste Paket (1.0.11).

**Ablauf je Shop:** Live-Kanal pausieren → Dev-Kanal Zugang eintragen → testen → aufräumen: Testbestellungen + Testkunden in Woo endgültig löschen, Schlüssel widerrufen, **im Live-ERP `bestellungen_letzter_sync` auf nach dem Test setzen** (sonst holt Live die Testbestellungen nach), Live-Kanal fortsetzen, Bestand neu übertragen (Testbestellungen haben Woo-Bestand gesenkt). Dev-Kanal-Zugang wieder leeren.

**indra-design Wartungsseite:** WPCode **35329** „INDRA: Wartungsseite (kurz)“, angelegt INAKTIV; schalten mit `node .claude-browser-tools/indra_wartung_schalten.js an|aus` (leert WP Super Cache). Kopie `shop/wp-snippets/indra_wartungsseite.php`. Zeitfenster auch für Jackys Umstellung auf das EU nutzen ([[project-indra-design-eu]]).

Verwandt: [[project-datentransfer]], [[project-sow-umstellung]], [[project-bio-wolle-aufbau]], [[project-shop-sync]].

**✅ SOW-Testrunde 2026-11-13 abgeschlossen** (A-SOW-2026-00001–00003, Woo #3538/3539/3542 danach endgültig gelöscht, Woo-Bestand zurückgesetzt, Dev-Zugang SOW geleert). Getestet: Bestellung, Zahlung → processing, Packplatz → Rechnung + Versandmail → completed, Voll-Widerruf (unversendet → Storno), Teilwiderruf (versendet → Erwartete Retouren → vorbelegte Retoure → GS → Rückerstattung → Woo-Erstattung im Kundenkonto).
Fixes daraus (alle committet, **gehören ins Paket 1.0.11**): a252d86 Slider/Menü-Cron bei Pause, b936f3f Kundennr./Lieferanschrift/pa_farbe/Post-Name, 5d6e655 Anrede + schlanker Belegkopf, 7ddd1c0 Erwartete Retouren + Storno erledigt, 435d4f3 Retoure vorbelegt, 9512e4f Mail-Kopf/Logo/Kontaktadresse + Dateiname = Belegnr., ae5256a Erstattung an Woo, 230be3a Packplatz Charge nachtragen bei Bestand ohne Charge + Warte-Layer, 0ab8c6f „Retoure erwartet“ in der Auftragsliste.
Shops (direkt, kein Paket): Germanized-Texte SOW, Übersetzungen, Lager-Mails aus, Seiten/Endpunkte deutsch (+indra-Weiterleitung WPCode 35330), leerer Warenkorb deutsch – siehe [[project-woo-shop-checkliste]].
Offen: Jacky widerruft Woo-Schlüssel „ERP Dev-Test“ bei SOW + setzt Live-SOW-Kanal fort; Bio/indra-Testrunde bei Bedarf gleich ablaufend.
