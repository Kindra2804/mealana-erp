---
name: project-1-0-check
description: "1.0-Check gestartet 2026-11-01: Entscheidungen (Steuerprofil → 1.1, Live komplett neu auf 1.0, Offline-Kasse light nach 1.0), Shop-Reihenfolge indra-design → SOW → Bio → MeaLana, Arbeitsliste A–E"
metadata:
  node_type: memory
  type: project
  originSessionId: ef009f0b-163c-454c-9dcc-cdd9cc4fc4bf
  modified: 2026-11-04T12:26:02.393Z
---

**Entscheidungen Jacky 2026-11-01:**
1. **Steuerprofil** (KU/EA/Ist, [[project-steuerprofil]]) kommt in **1.1, vor dem ersten echten Fremdkunden**, nicht in 1.0.
2. **Live wird komplett zurückgesetzt** und frisch auf 1.0 installiert (kein Migrieren von 0.4.0): Live war bisher nur eine Instanz gegen eine simulierte Seite. Der Woo-Shop **indra-design.at** wird ebenfalls zurückgesetzt (gemeinsam machen) und wird ein Sub-Shop (Untersetzer, Schilder, Schlüsselanhänger).
   **Shop-Reihenfolge Live:** 1) indra-design (Artikel neu) → 2) **sockenwolle-online** (neuer Woo-Shop, erster JTL-Echtshop der abgedreht wird, liefert bei JTL eh zu wenig) → 3) bio-wolle → 4) **MeaLana ganz zum Schluss**, wenn die Performance der 3 anderen überzeugt. Ergänzt [[project-soft-golive-sockenwolle]].
   Folge: Live-Deploy 201–220 auf 0.4.0 entfällt → stattdessen 1.0-Neuinstallation + Hauslizenz L-100001-19 + Paperless-URL/Schlüssel.
3. **Offline-Kasse light (Sunmi)** nach 1.0.

**Arbeitsliste 1.0 (Vorschlag Claude, so abgenommen):**
- A Technik: Onboarding komplett (Entscheidung 7 aus [[project-lizenzierung-preise]]) · Update-Weg + Kundenpaket ohne tools/ und lizenzgenerator ([[project-update-mechanismus]]) · Backup-Speicherort ([[project-backup-strategie]]) · zuletzt Baseline-Neuschnitt 1.0 + Installationsanleitung + Testinstallation ([[project-installationsanleitung]])
- C Klicktests: Offline-Divers (Messe), Dropshipping Versendet+Tracking, Inventur am Live, Gutschein-Kauf im Shop (Checkout-Snippet fehlt)
- D Daten (Babsi/Jacky): Startinventur JTL-Export kurz vor Go-Live, Gutschein-Motive (Tabelle leer, Entwurf in docs/entwurf_gutschein_vorlagen/), Gutschein-Anzahlungskonto, Herkunftsland/GPSR
- E nach 1.0: Offline-Kasse light, Konfigurator-Shopfrontend, Google Shopping, Importer-Modul/Lang-Importer, Kassenbon-Nr. atomar (unkritisch, bon_nr UNIQUE)

**How to apply:** Reihenfolge Technik: Onboarding → Update-Weg → Backup → Baseline 1.0 (Baseline zuletzt, weil die anderen noch Migrationen erzeugen) → Live neu aufsetzen gemeinsam.

## ✅ Onboarding gebaut 2026-11-01 (Commits 54f6bef Logo, 3f1bf04 Dienst, caf1cf2 Assistent)
- Jacky-Vorgaben: Pflicht 1–4 + Lager (auto „Standardlager“), Assistent nach jedem Login bis alles (je Lizenz) erledigt, Lizenz-Upgrade → neue Schritte; Abholorte nur mit Shop-Lizenz; Benutzer vorschlagen (sonst arbeiten alle als Superadmin) mit „Ich arbeite allein“.
- `src/modules/einrichtung/OnboardingService.php`, `public/einrichtung/index.php|speichern.php`, Sperre in Auth::pruefeSeite (Session-Cache onboarding_pflicht_ok), Login-Weiterleitung, Banner + ···-Menüpunkt (shell_top), Migration 221 (onboarding_* für bestehende Installationen).
- Hauptlogo: neuer Helfer `src/core/Firmenlogo.php` (haupt/fuerShop/hochladen) — Einstellungen → Firma → Hauptlogo braucht keinen Kanal mehr, alle Laden-Belege lesen es (vorher immer shops.id=1).
- Jacky will das Live-Onboarding nach dem Zurücksetzen mit Babsi durchspielen (echte User-Erfahrung).
- Testinstallation-Skript: scratchpad nahtlos_test/neu_aufsetzen.sh (DB nahtlos_test, Junction web/mealana, php -S :8097) — Browser-Test 18/18.

## ⚠️ Funde aus der Testinstallation → für den Baseline-1.0-Schritt merken
- Baseline 0.2/Stand 123 hat **keine Kundengruppen** → 1.0-Baseline muss „Endkunden“ (ist_standard=1, typ endkunde) seeden.
- Einheit heißt in der Baseline „Stk“, auf Dev „Stück“ (208/219 jetzt auf Kürzel abgesichert) → in der 1.0-Baseline vereinheitlichen.
- 210 (Abholort Wollboutique) + 219 (NAHTLOS-Lizenzartikel LZ-*/BFR-*, Kategorie/Gruppe Lizenzen, Konto 4600) laufen nur noch bei firmenname LIKE 'MEALANA%'. Bei einer frischen 1.0-Installation (auch Live MeaLana!) laufen keine Migrationen → Lizenzartikel für Jackys Verkaufs-Installation per eigenem Skript in tools/lizenz/ anlegen (nicht im Kundenpaket); Abholort über den Assistenten.
- DB immer mit COLLATE utf8mb4_general_ci anlegen (unicode_ci → Collation-Fehler in 219).

## ✅ Update-Weg gebaut 2026-11-01 → [[project-update-mechanismus]]
Nächster Punkt laut Reihenfolge: **Backup** ([[project-backup-strategie]]), dann Baseline 1.0.

## ✅ Baseline 1.0 geschnitten 2026-11-04 (Jacky: „ja“, indra-design-Kürzel **IND**)
- Quelle = frische DB (alte Baseline 123 + 124–222), NICHT Dev → keine Firmendaten. Struktur = Dev (nur Kommentar-Mojibake auf Dev).
- Ergänzt: Kundengruppen Endkunden (Standard) + Händler; **7 Haupt-Nummernkreise** (A/R/GS/LS/MR/AB/PL) — fehlten in der alten Baseline, Fallback wäre 1. Buchstabe gewesen (L-, G-, abrechnung = A- kollidiert mit Auftrag!); Einheit „Stück“ + Paar/Packung/Rolle/Strang/Bollen/Spiel/Liter/Zentimeter.
- Migrationsdateien 004–222 gelöscht (.gitkeep bleibt, UpdateService prüft den Ordner), nächste = **223** → vergeben 2026-11-04 an `223_shop_wurzelkategorie.sql`, nächste **224**. VERSION 1.0.0, docs/versionen/1.0.0.md, installation.md Schritt 7.
- Test: Import identisch zur Quelle, Onboarding-Browsertest 18/18, Seitenrundgang ohne PHP-Fehler, Nummern inkl. A-IND-2026-00001 per Rollback geprüft. Testinstallation im Scratchpad dieser Session (DB b10_test, php -S :8097).
- Plan Live: Sichern → indra-design Woo zurücksetzen (Produkte/Kategorien/Bestellungen/Kunden weg, Theme/Plugins/Snippets bleiben, neue API-Keys) → Live-ERP neu mit Baseline 1.0 → Assistent mit Babsi → Kanäle: Hauptkanal = MeaLana (ohne Kürzel, ohne Keys), indra-design mit IND → Paperless → Cron-Aufgaben → tägliches Backup.

## ✅ indra-design.at zurückgesetzt 2026-11-04
- Sicherungen vorher: Live-ERP (E:\ERP-Sicherung\2026-11-04_vor_1.0), Plesk-Sicherung WP+DB (Jacky), UpdraftPlus 3,43 GB lokal bei Jacky. UpdraftPlus bleibt installiert, die Sicherungen am Server sind gelöscht.
- Gelöscht: 1.813 Produkte, 245 Kategorien, 38 Attribute, 18.879 Medien (Logo 93 + Favicon 94 bleiben), 7 Bestellungen, 8 Gutscheine, 5 Seiten, 1 Beitrag, 7 Menüs, Plugins Akismet/Smart Swatches/Stripe. Woo-REST-Schlüssel „MeaLana ERP Dev-Sync“ + App-Passwort „Bildersync“ widerrufen. **Dev shops.id=1 hat sync_pausiert=1** (zeigt noch auf indra-design, die Schlüssel sind ungültig).
- Übrig: Seiten Shop/Cart/Checkout/My account + 8 Rechtsseiten, Theme Blocksy, Elementor, Germanized, PayPal, Yoast, Jetpack, WP Super Cache. Offen fürs Design: Seitentitel steht noch auf „MEALANA“, Logo/Favicon sind noch MeaLana, die 7 MeaLana-WPCode-Snippets (Bar bei Abholung, Mindestabnahme, Konfigurator, Gutscheine, Zahlungsmail, Swatch-Scroll, Variations-Schwellwert) prüfen.
- Lehre: Medien per REST einzeln ~1,7 s (WP-Boot), parallel bremst der Server → temporäres WPCode-PHP-Snippet mit eigenem REST-Endpoint (400/Aufruf) war in Minuten fertig. WPCode-Typ beim Anlegen per `.wpcode-code-type[data-code-type=php]` wählen, sonst wird das Snippet als CSS gespeichert. Helfer `.claude-browser-tools/wp_lib.js` (Login + Cookie-Nonce-REST).

## ⏭️ Nächste Session (Jacky 2026-11-01 abends: „morgen mit frischem Kopf“)
Reset Woo-Shop indra-design.at + Live-DB, frisch einspielen, **Backup-Strategie direkt am Live-Server** — gemeinsam. Vorbereitung/Checkliste:
1. Vorher am Live-Server: alles sichern (DB-Dump + erp-Ordner), auch wenn die Daten nur Test sind.
2. Installationsweg heute: Baseline (Stand 123) + Migrationen 124–222 — auf frischer DB getestet (DB mit **utf8mb4_general_ci**!). Danach von Hand Kundengruppe „Endkunden“ (ist_standard=1, typ endkunde) anlegen, solange die Baseline 1.0 sie noch nicht enthält. ODER vorher Baseline 1.0 schneiden — mit Jacky entscheiden (Vorschlag: zuerst Baseline 1.0 bauen, dann Live direkt damit = echte Startlinie).
3. Superadmin per `create_admin.php`, dann Einrichtungs-Assistent mit Babsi (Firma MEALANA KG/ATU68078304, Hauptlogo, Hauslizenz L-100001-19_A1.lic, Steuer, Lager, Benutzer, Mail, Kasse, Shop indra-design, Abholort Wollboutique von Hand).
4. Paperless: URL https://beleg.mealana.at + Schlüssel aus beleg-portal/config.php, Kundenanzeige-Haken.
5. Generator NICHT auf Live aktivieren; Lizenzartikel (LZ-*) auf Live nicht nötig (Verkauf aus Dev) — sonst Skript in tools/lizenz/.
6. Backup-Strategie: Speicherort klären ([[project-backup-strategie]]), Windows-Aufgabe mysqldump + Ordner, Test-Wiederherstellung.
7. Danach: Cronjobs/Windows-Aufgaben am Server prüfen (Shop-Sync, Fachmiete, Z-Bon-Auto, Lagerwert).
