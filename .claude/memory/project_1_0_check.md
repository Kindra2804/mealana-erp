---
name: project-1-0-check
description: "1.0-Check gestartet 2026-11-01: Entscheidungen (Steuerprofil → 1.1, Live komplett neu auf 1.0, Offline-Kasse light nach 1.0), Shop-Reihenfolge indra-design → SOW → Bio → MeaLana, Arbeitsliste A–E"
metadata:
  node_type: memory
  type: project
  originSessionId: ef009f0b-163c-454c-9dcc-cdd9cc4fc4bf
  modified: 2026-11-09T19:16:44.573Z
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

## ⚠️ Hosting-Limits (hosttech/Plesk, CloudLinux LVE) — vor Live klären (Jacky 2026-11-05)
Alle Domains liegen auf **einem Server/Konto** (185.229.88.64): mealana.at (JTL), indra-design, sockenwolle-online, bio-wolle, beleg.mealana.at. Die Limits gelten für **das ganze Konto**: SPEED 150 %, 1 GB RAM, I/O 20 MB/s, **Entry Processes 40**, NPROC 100. Am 2026-11-05 lieferte mealana.at **HTTP 508** an automatische Abrufe; das Panel meldete in 24 h **1.338 CPU-, 269 IO- und 405 EP-Fehler** (= abgewiesene Anfragen, auch echte Kunden). Es gibt **keine Benachrichtigung** → unter Ressourcennutzung → Optionen einschalten.
- Auswertung „Aktuelle Nutzung“: Die CPU liegt **rund um die Uhr** bei ca. 120 % von 150 %, auch **nachts 0–6 Uhr** (50–120 Fehler pro Stunde) → **Dauerlast, nicht unsere Tests**. Schnappschuss 17:19: php-fpm-Pool „meala…“ mit 20–58 % CPU je Prozess. 20–21 Uhr: I/O-Spitze 9,4 MB/s + EP 17 (Sicherung/Abgleich?).
- Je Domain unterscheiden sich die Grafiken: **sockenwolle-online (JTL) hat nachts bis ca. 10 Uhr erhöhte Last**, indra-design (frisch neu) erst ab 10:30 (= unsere Tests). → Vermutlich Crawler/KI-Bots auf den alten JTL-Shops (Filterseiten). HTTP-Abfragen im Schnappschuss sind fast immer leer (nur bei Limit-Treffern).
- **✅ Ursache gefunden (Zugriffsprotokolle 05.11. ca. 3:45–20 Uhr, in `import/`):** **ClaudeBot** (Anthropic, IP 216.73.217.x) = **79 % auf sockenwolle-online (97.225 von 122.534)** und **78 % auf mealana.at (181.638 von 232.247)**, fast nur Filter-URLs (`/…__…`, `?Sortierung=`, `ed=`, `qf=`, `mf=`, `availability=`), Spitze >20.000/h. Dazu auf SOW ein **getarnter Scraper**: 20.061 Filter-Aufrufe mit identischer Mac-Chrome-Kennung von tausenden IPs (max. 6 je IP, Residential-Proxies); auf mealana „GeedoShopProductFinder“ (937) + Netz 83.99.206.x. robots.txt beider JTL-Shops erlaubt **alles**. indra-design: kein Crawler-Problem.
- **Maßnahmen:** (1) robots.txt in beiden JTL-Shops: Filter-URLs für alle sperren + ClaudeBot/GPTBot etc. bremsen oder aussperren; (2) getarnter Scraper: robots.txt hilft nicht → hosttech/Imunify um Rate-Limit bitten oder JTL-Filterlinks nofollow/noindex; (3) die neuen Woo-Shops von Anfang an mit derselben robots.txt (Woo-Filter: `?filter_`, `?orderby=`, `?add-to-cart=`, `?min_price=`).
- ✅ 2026-11-05 abends: robots.txt auf sockenwolle-online.at und mealana.at von Jacky angepasst (Filter für alle gesperrt, ClaudeBot: Crawl-delay 10 + alle `?`-URLs und `__` gesperrt), online geprüft. **Offen:** in 1–3 Tagen Ressourcennutzung (CPU-Fehler) und ein neues Zugriffsprotokoll vergleichen; getarnten Scraper ggf. bei hosttech melden.
- Früherer Schritt: Zugriffsprotokoll (`access_ssl_log`) von sockenwolle-online.at und mealana.at aus Plesk → `import/` (gitignored) → auswerten (Top-User-Agents/IPs/URLs je Stunde) → Bots sperren (robots.txt, Imunify). Früher: Im Schnappschuss den Reiter **HTTP-Abfragen** um ca. 3 Uhr nachts ansehen (welche Domain, welche URLs: Bots/Crawler am JTL-Shop? xmlrpc/wp-login-Angriffe? Cron?). Danach z. B. Bots drosseln (robots.txt/Crawl-Delay, Imunify/Firewall), WP-Cron auf echten Cron umstellen, Last je Shop prüfen.
- **Für Live:** Wenn drei Woo-Shops + MeaLana + beleg.mealana.at auf einem Konto laufen und das ERP alle 15 Min. alle Shops abfragt (Bestellungen, Bestand, Widerrufe), reicht das Paket vermutlich nicht → bei hosttech nach einem **größeren Paket** bzw. **getrennten Konten** fragen (Limits je Shop). Bis dahin beim Testen schonend arbeiten (wenig wp-admin-Logins per Skript, keine Sync-Läufe von Hand direkt hintereinander).

## ✅ Antworten Teil A (Jacky 2026-11-09)
- A1: Indra-Artikel per **Export/Import** (①). Startbestand: Artikel liegen bei Jacky → durchzählen und mit echtem Bestand einbuchen, die wenigen in der Firma manuell. Live-Tag: **2026-11-10 abends** (Babsi bis 16:00), Shop darf zur Not kurz leer sein.
- A2: Firmendaten/Logo/Abholort/Paperless (beleg@mealana.at für alle Kassen) aus Dev bekannt. Benutzer: Jacky Superadmin, Babsi Admin, Eva + Killian Kassier, **Kiara Kassier + Packplatz** (ein Benutzer = eine Rolle → Packplatz-Rolle + Kasse-Rechte in der Matrix bzw. später). **Kassen vorerst keine** (Start vermutlich 2027-01-01). Mail: indra-design + mealana haben Zugangsdaten, jeder Shop bekommt bestellung@.
- A3: Testbestellungen im Woo-Papierkorb (Sync überspringt trash; Germanized-Widerrufsanträge extra prüfen). Hosting-Log schickt Jacky.

## 📋 Ablaufplan Live 1.0 für Jacky + Babsi (2026-11-05)
`docs/live/Live_1.0_Ablaufplan.pdf` (+ HTML-Quelle), 5 Seiten zum Ausdrucken: A Vorbereiten (Entscheidung indra-Artikel: ① Dev-Export → Live-Import + Woo-Produkte leeren [empfohlen] oder ② 1:1 mit Mapping; Unterlagen; Paket bauen = 1.0 + Migrationen 223–228; Testbestellungen #34748/#34751 in indra-design löschen; hosttech prüfen) → B Sichern → C Neuinstallation (Baseline + migrate.php = 6 Migrationen, neuer encryption-Schlüssel) → D Assistent mit Babsi → E indra-design anbinden (**Dev-Kanal indra-design vorher pausieren!**, neue Woo-Keys, Kürzel IND, Kategorien ab IndraDesign) → F 7 Cron-Aufgaben (shop_sync 15 min, bfr 5 min, z_bon 00:05, mahnwesen/gutschein_versand/fachmiete 06:00, lagerwert 23:30) + tägliche Sicherung + Test-Wiederherstellung → G Probelauf. Jacky geht ihn nach dem Veranstaltungs-Wochenende mit Babsi durch. Hinweis: installation.md listet shop_sync, gutschein_versand und lagerwert bei Schritt 12 noch nicht.


## ✅ Live-Tag 2026-11-10 abends (Teil 1)
- **Paket gebaut:** `D:\ERP\pakete\nahtlos-1.0.1.zip` (Commit 811e738, Baseline 1.0 + Migr. 223–228 + datentransfer.php, ohne tools/Lizenzgenerator/Configs). 1.0.0-Zip vom 04.11. ist veraltet (ohne 223–228).
- **Dev-Kanal 4 indra-design pausiert** (sync_pausiert=1) — hatte bis 17:46 noch gesynct. Dev-woo_ids für indra-design sind jetzt ungültig.
- **indra-design.at geleert** (Skript `.claude-browser-tools/wp_shop_leeren.js`, Probelauf ohne `--los`): 119 Produkte, 8 Kategorien (Uncategorized bleibt), 12 Attribute, 249 Produktbilder. Übrig: 39 Medien (Logos, Kacheln, Kopf), 0 Bestellungen, keine Kunden außer den 2 Admins. Startseite geprüft: alle Bilder laden; Kacheln verlinken bis zum Import auf gelöschte Kategorien.
- Noch offen am Woo: alter Woo-Schlüssel „NAHTLOS ERP Dev (indra-design, Test)“ + App-Passwort „NAHTLOS ERP Dev Bildersync“ → beim Anbinden des Live-ERP widerrufen und neu anlegen.

## 📝 Funde Live-Assistent 2026-11-10 (Babsi + Jacky) → Version 1.0.2
1. Superadmin-Schritt fühlt sich doppelt an, wenn man den Zugang selbst per create_admin.php angelegt hat → Knopf „Passt so“ oder Frage in create_admin.php „für dich selbst?“.
2. Superadmin-Passwortfelder: **„Passwort anzeigen“** (Auge) zum Vergleichen/Vertippen.
3. Schritt Steuer: Knopftext „Passt schon …“ → **„Standard speichern + weiter“** (kommt sowieso neu mit dem Steuerprofil 1.1, trotzdem ändern).
Sonst: Assistent lief bei Babsi ohne Probleme durch.

## ⚠️ Erster Live-Sync indra-design 2026-11-10 ~23 Uhr: Hosting-Fehler bestätigt
- Komplettabgleich Batch 50: **502 Bad Gateway (nginx)** auf Woo-Batch mit 42 neuen Produkten → Woo hat trotzdem angelegt, ERP hielt es für Fehler → Folgeläufe „Ungültige oder doppelte Artikelnummer“ (42×). Bilder: **503 capacity problems**.
- Lösung: „Fehler abgleichen“ (Reconcile per SKU) + Komplettabgleich mit **Batch 10**. Im Shop waren nach dem Lauf 114/119 Produkte angelegt.
- Hinweis: „Automatischen Sync pausieren“ stoppt nur den Cron, NICHT den laufenden Komplettabgleich (der bricht erst bei 0 Erfolgen ab) → Prozess php.exe beenden.
- Idee für später: Komplettabgleich bei 502/503/504 automatisch wie Rate-Limit behandeln (warten + kleiner werden + vor Neuanlage per SKU prüfen); Live-Cron mit kleiner Batch-Größe. **Vor SOW (>1.000 Artikel) Hosting-Paket bei hosttech klären.**

## ✅ Live-Cron-Aufgaben 2026-11-10 (Server, Ordner „NAHTLOS“ in der Aufgabenplanung, /ru SYSTEM)
Shop-Sync 15 min · Mahnwesen 06:00 · Gutschein-Versand 06:05 · Fachmiete 06:10 · Lagerwert 23:30. Alte Aufgaben (inkl. alte BFR-Nachsignierung) gelöscht.
**Beim Kassenstart (vorauss. 2027-01-01) nachziehen:** `NAHTLOS\BFR-Nachsignierung` (alle 5 min, cron\bfr_nachsignierung.php) + `NAHTLOS\Z-Bon automatisch` (00:05, cron\z_bon_auto.php), gleiche schtasks-Form.
Offen: tägliche Sicherung (DB-Dump + storage/uploads) + Test-Wiederherstellung; Startbestand einbuchen (bis dahin zeigt indra-design nur die Schilder, „Nicht vorrätige ausblenden“ = ja).

## ✅ 2026-11-11: Überlastung im Shop-Abgleich gebaut (Commit 5093cf2, für Paket 1.0.2)
- `ServerUeberlastetException` (502/503/504/508 + curl-Timeout) ist Unterklasse von RateLimitException → jeder Lauf (Cron + Komplettabgleich) hält an. Batch-Senden überlastet → Zeilen 'error' (Stand unklar); neue Artikel mit Status error werden vor dem Anlegen per SKU gesucht (`findeVorhandeneIdBeiUnklaremStand`, Log `shop.sync_sku_gefunden`).
- komplettabgleich.php: Pause 60→120→…→600 s, WC-Batch halbieren bis 5, Abbruch nach 6× in Folge, Stopp per „Automatischen Sync pausieren“ (nur Wechsel während des Laufs), WC-Batch ≤ UI-Limit, Schluss zeigt echten Fehlerstand.
- Fix: „ohne Bilder“ lief endlos (findFaelligeArtikel/zaehleFaellige mit `$mitBildern`).
- Test: Fake-Woo `scratchpad/fakewoo/router.php` (php -S :8099, modus.json = Anzahl 502 je Batch-Art), Test-Kanal testueberlast danach gelöscht, Dev-Backup D:\ERP\backups\mealana_erp_vor_ueberlastungstest_20261111.sql.
- ✅ **Paket gebaut 2026-11-11:** `D:/ERP/pakete/nahtlos-1.0.2.zip` (Commit e97322f, 2.081 Dateien, 8,3 MB, Migr. 229 wiederholbar mit IF NOT EXISTS). ✅ **Auf Live eingespielt 2026-11-11** (Jacky). Absender je Kanal je Shop eintragen.
- **Hosting (Jacky 2026-11-11):** Die Woo-Shops bekommen voraussichtlich eigene Server, die Limits sollten dann kaum noch eine Rolle spielen. Genaue Antwort von hosttech kommt.

**2026-11-11 Lücke beim SOW-Import:** Die 1.0-Baseline hat nur Sondergruppen (Messe/Workshops/Partner/Mahn/Fachmiete). Die Warengruppen + Gutscheine 3230 + Versandkosten 4090 fehlen auf Live, sodass der JTL-Import keine Gruppe zur Auswahl hatte. Behelf: `erp/database/artikelgruppen_mealana.sql` (INSERT IGNORE). Für 1.0.x: im Onboarding/JTL-Import warnen, wenn keine Warengruppe existiert; Gutscheine+Versandkosten evtl. in die Baseline (der Export sucht sie über den Namen).

**2026-11-11 max_input_vars:** Der SOW-Import auf Live lieferte nur die ersten 8 Väter, alle weiteren kamen mit „Artikeltyp/Artikelgruppe nicht ausgewählt“. Ursache: PHP-Standard max_input_vars=1000; die Kontrollliste hat rund 3.600 Felder und PHP schneidet still ab (Dev hat 20000). Gebaut: Feld `formular_vollstaendig` als letztes Formularfeld, fehlt es, bricht der Import mit Hinweis ab (Commit ebfa45f). **In die Installationsanleitung aufnehmen: php.ini max_input_vars = 20000.** Außerdem neu: `scripts/import_zuruecknehmen.php` (--shop=slug|keiner --seit, Probelauf/--ausfuehren).

- ✅ **Paket 1.0.4 gebaut 2026-11-11:** `D:/ERP/pakete/nahtlos-1.0.4.zip` (Commit c4769d4, 2.097 Dateien, 8,3 MB). Enthält: JTL-Lagerabgleich (Migr. 231), Schild-Bausteine + fertige Schilder (Migr. 232), Optionen übertragen, Import-Sperre max_input_vars, import_zuruecknehmen.php, artikelgruppen_mealana.sql. Beim Einspielen auf Live: php.ini max_input_vars=20000 prüfen; ✅ 1.0.4 auf Live eingespielt 2026-11-11 (Jacky), Picker-Snippet 34170 aktualisiert. Offen: JTL-Export „Lagerabgleich_ERP“ anlegen + Erstabgleich. Nächste Migration: 233.

**2026-11-14 abends: Paket 1.0.14 gebaut** (D:/ERP/pakete/nahtlos-1.0.14.zip, Commit 072d3af, Migr. 241–244): Preis-Massenaktion, Mengen-Aktionen (Kasse + Shop), Kasse mit Sale-/Aktionspreisen, Fertige-Schilder-Fix. Nach dem Einspielen auf Live: Connector 1.7.0 auf SOW + Bio installieren (indra hat ihn schon), Mengen-Aktion „Weihnachtsanhänger 3 für 12 €“ auf Live anlegen (indra), dann Snippet 34685 aus, Raunzmarker-Paketpreise anlegen, sobald der Artikel im ERP ist.

**2026-11-14 spät: 1.0.14 LIVE, Paket 1.0.15 gebaut** (Commit 661b434, Migr. 245): Shop-Sync schickte regular_price = **UVP** statt Verkaufspreis (Anhänger VK 4,50 / UVP 3,50 → Shop 3,50; auf Dev 1.402 Artikel UVP < VK, 107 UVP > VK). Jetzt regular = Normalpreis (Standard-KG), sale nur bei Sale/Aktion. Außerdem: Preis-/Sale-/Aktionsänderungen setzten aktualisiert_am nicht → Komplettabgleich „keine Änderungen“. Jetzt PreisService::fuerShopMarkieren / aktionFuerShopMarkieren, und Cron `PreisService::preiswechselMarkieren()` für Beginn/Ende mit Zeitraum. Migr. 245 markiert alle Artikel mit UVP ≠ VK. UVP wird im Shop nicht mehr angezeigt; ob sie als „UVP“-Text (Preisauszeichnung) zurückkommen soll, offen.
- Mengen-Aktion „Weihnachtsanhänger 3 für 12 €“ ist auf Live angelegt + übertragen, **Snippet 34685 ist AUS** (2026-11-14 abends). Connector **1.7.1** auf indra (Badge nur, wenn es spart). SOW + Bio: Connector 1.7.1 installiert ✅ (2026-11-14 20:50).

**2026-11-14 nachts: Paket 1.0.16 gebaut** (Commit 81e216a, Migr. 246): „± Bestand korrigieren“ im Artikel → Lager (LagerService::bestandKorrigieren, Bewegungstyp 'korrektur', Gründe KORREKTUR_GRUENDE, Recht inventur.anlegen – Jacky: wer Inventur darf, darf korrigieren). Einzelartikel-Inventur: Lager wählbar (inventur_laeufe.lager_id), Bug „Bitte ein Ziel für den Scope wählen“ behoben (Browser-Autovervollständigung/Enter löschten scope_id_artikel → autocomplete=off, Enter = erster Treffer, Server-Fallback über exakte Nummer/Namen, Formular bleibt bei Fehler erhalten). Auf Dev im Browser getestet und zurückgesetzt. **1.0.15 + 1.0.16 LIVE (Jacky 2026-11-14 abends).**

**2026-11-17 Paket 1.0.24 gebaut** (D:\ERP\pakete\nahtlos-1.0.24.zip, keine Migration, nächste Migr. bleibt 256): Seitenleisten-Gruppen + Import-Klappmenü, Kanalfarben S1–S4, Artikelliste schneller, Filter „Keine EAN“/„Fehlbestand“ repariert, Bildvorschau, Lagerübersicht-Status, Lang EAN-Umzug. Live-Einspielen offen, danach Lang-Import auf Live.

## 2026-11-18 Fix Artikel löschen/deaktivieren (a621b4b, ohne Migration)
Knöpfe in artikel/detail.php waren tot. Jetzt status_setzen.php: Deaktivieren/Aktivieren; Löschen endgültig (inkl. Woo) nur wenn nie benutzt (ArtikelRepository::findLoeschSperren), sonst Hinweis. Inaktive Kinder in Liste nur mit „Auch inaktive“ (Jacky-Wunsch). Live mit nächstem Paket (1.0.25); Anlass: Fehleingabe Kind auf Live.


## Früherer Index-Eintrag (aus MEMORY.md verschoben 2026-11-18)
- [✅ 1.0-Check](project_1_0_check.md) — 2026-11-18 Fix Löschen/Deaktivieren (a621b4b) fürs nächste Paket; 2026-11-17 Paket 1.0.24 gebaut (ohne Migr.: Seitenleisten-Gruppen, Kanalfarben, Listen-Tempo, Filter-Fixes, Lang EAN-Umzug), Live offen; 2026-11-14 1.0.14 LIVE; 1.0.15 (Migr. 245 Shop-Preis = VK statt UVP) + 1.0.16 (Migr. 246 Bestand korrigieren, Inventur-Lagerwahl) LIVE; 2026-11-15 1.0.17 LIVE (Fremd-Badge, DEP, §131, Update-Weg, Messe-Aktionen, Migr. 247); 1.0.18 gebaut (Mobil-Messe, Migr. 248); Paket 1.0.19 gebaut (Commit 6957e19: GTIN/Yoast/Alt, Komplettabgleich abbrechen, Gutschein-Betreff, Assistent), nach Live: Komplettabgleich je Shop + alt_texte_nachziehen.php; 2026-11-15 Paket 1.0.20 gebaut (Newsletter komplett, Migr. 249–253, Commit 0e0baa2; Live: Belegseite-Dateien FTP, Aufgabe newsletter_versand alle 5 Min, Connector 1.8.0, Kanäle Website/Farbe, JTL-Import); 2026-11-15 Paket 1.0.21 gebaut (Commit 2882208, ohne Migration: Artikel neu Grundpreis aus + Einheit Stk, Kopie aktiv + Artikelgruppe/Maße); nächste Migr. 254; 2026-11-11 1.0.4 LIVE (JTL-Abgleich, Schilder; Migr. 231+232), Erstabgleich offen; 2026-11-11: 1.0.2 LIVE (Mail je Kanal + Überlastung); Woo-Shops bekommen eigene Server; 2026-11-10 LIVE-TAG: Paket 1.0.1, Live neu, indra-design angebunden (119 Produkte), Cron + tägl. Sicherung; offen: Startbestand, 1.0.2-Liste; 2026-11-01: Steuerprofil → 1.1, Live komplett neu auf 1.0, Shops indra-design → SOW → Bio → MeaLana; Onboarding ✅ → Update-Weg ✅ → Baseline 1.0 ✅ 2026-11-04 (Kürzel indra-design = IND) → Live neu + Backup; ⚠️ Hosting-Limits: ein Konto für alle Shops, CPU dauerhaft am Limit, 508-Fehler (2026-11-05) → vor Live klären


## Früherer Index-Eintrag (aus MEMORY.md verschoben 2026-11-18)
- [✅ 1.0-Check](project_1_0_check.md) — Paket-Verlauf 1.0.1–1.0.24 + offene Live-Schritte (Details in der Datei); 1.0.24 gebaut, Live offen; Fix Löschen/Deaktivieren (a621b4b) fürs nächste Paket

**2026-11-18 Paket 1.0.25 gebaut** (D:\ERP\pakete\nahtlos-1.0.25.zip, Commit cae0592, keine Migration, nächste Migr. bleibt 256): Fix Löschen/Deaktivieren + Indra-Umnummerierung (scripts/indra_umnummerierung.php + mitgelieferte indra_umnummerierung_zuordnung.json, Werkstatt auf Live nicht nötig). Enthält auch alles aus 1.0.24. Nach dem Einspielen: Umnummerierung Probelauf → Phase 1 → Sync → Phase 2 → Sync (siehe [[project-katalog-werkstatt]]).

## 2026-11-18 Paket 1.0.26 (8cdd87a, ohne Migration)
Nur Fix: 🗑️ im Artikel-Tab Lieferanten rief artikel/delete.php mit der artikel_lieferanten-ID auf und deaktivierte einen fremden Artikel samt Kindern. Jetzt artikel_lieferant_loeschen.php. Lösch/Deaktivieren-Fix (a621b4b) steckt schon in 1.0.25. Live-Einspielen offen.

**1.0.26 LIVE 2026-11-18.** Live hat noch keine Lieferanten → alter 🗑️-Fehler kann dort nichts angerichtet haben. Nächster Schritt (Jacky, 19.11.): durchsehen, was von Dev nach Live übertragen werden soll (Lieferanten u.a.).

## 2026-11-19 Paket 1.0.27 gebaut (cbe4ada, Migr. 256 + 257, nächste 258)
D:\ERP\pakete
ahtlos-1.0.27.zip: Kassen-Liste/Preisliste/Mini-Anhänger (Kurznamen), JTL-Import Kunden+Aufträge im Browser (365-Tage-Regel + Prüfliste), Fix Archiv-Import (konfig_freitext), Datentransfer-Teile lieferanten + kontenplan. Reihenfolge Live: Paket einspielen → Datentransfer lieferanten,kontenplan (Probelauf zuerst) → JTL-Exporte frisch → Kunden → Aufträge → Prüfliste. Buchhaltungs-Check Jacky 19.11.: Partner-Belege (R-INDRA) nicht im Export, Zahlungen darauf Bank an 3710 (Geld liegt auf KG-Konto, BACS-Inhaber KG), Fremd/Spende Kasse 3710/3720 ohne USt, Kommission 4080, Archiv + Kasse-Aufträge ausgeschlossen. Geklärt: PayPal indra-design = MEALANA KG → passt; kein Bestandsübergang (Indra-Artikel auf Live von Anfang an bei Babsi, nie in JTL).

**1.0.27 LIVE 2026-11-19**, Datentransfer (Lieferanten/Kontenplan) + JTL-Import Kunden/Aufträge am Live abgeschlossen (Jacky). Offen: Prüfliste offener Archiv-Aufträge mit Babsi (falls noch nicht), Scan-Test Kassen-Liste/Mini-Anhänger; Babsis Ausschlussliste (Original: import/WAWI_JTL-Export-Artikelstammdaten-11112026.csv) → Filter für JTL-Artikelimporte.

## 2026-11-22 Paket 1.0.28 gebaut (Commit 9f2c30d, Migr. 258–263, nächste 264)
D:\ERP\pakete\nahtlos-1.0.28.zip (2.269 Dateien, 9,0 MB; website/ ab jetzt ausgeschlossen). Inhalt: Zahlungseingänge, Kassen-Liste-Ränder/Kategorie-Häkchen, Firmenname im Kassenkopf, Linux-Groß/Klein-Fix, **JTL-Übernahme** (Herstellerkürzel + weitere Namen, Vorschau „JTL: neue Nummern“, Import mit Babsis Liste, JTL-Nummer überall, VarKombi-Generator nach Schema, Sperre doppelte/zu lange Nummern), **Kasse 🧾 JTL-Rechnung bezahlen** (Migr. 263, Konto jtl_verrechnung), Datentransfer-Teil jtl_liste. Live-Reihenfolge + Tagesablauf: `docs/umstellung/JTL_Laden_aufs_ERP.md` (Checkliste). Nach dem Einspielen: Datentransfer jtl_liste (Probelauf → echt), Gegenkonto jtl_verrechnung eintragen (Steuerberatung), dann Artikelimport je JTL-Kategorie. Klicktest Jacky offen: Kasse JTL-Rechnung bezahlen (Arbeitsplatz gebunden, nur Hintergrund getestet), Scan alter JTL-Etiketten (Kasse + Sunmi).
