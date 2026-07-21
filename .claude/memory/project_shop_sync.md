---
name: project-shop-sync
description: "Online-Shop-Anbindung (WooCommerce): Phase 1+Vater/Kind+Hersteller+Bilder+Bestand+Bestellungen+Phase 4 Kunden-Verknüpfung fertig; offen: cron, Live-Rollout-Themen, ERP→Shop-Kunden-Push (Nice-to-have)"
metadata:
  node_type: memory
  type: project
  originSessionId: b67547bf-d9a0-405b-832f-e145eff451fa
  modified: 2026-07-21T18:05:52.963Z
---

## ✅ Phase 4 (eingegrenzt): Bestellungen mit echten Kunden verknüpfen FERTIG (2026-07-21)

**Scope-Entscheidung (Jacky, 2026-07-21):** Nur die direkte Ergänzung zu
Phase 3 -- eingehende Shop-Bestellungen bekommen einen echten `kunden`-Datensatz
statt nur `kunden_snapshot`. NICHT Teil davon (bewusst zurückgestellt, siehe
[[project_kundendatenbank]] für das volle Szenario): ERP→Shop
WooCommerce-Account-Anlegen, DSGVO-Löschung Richtung WooCommerce, automatische
Fuzzy-Merge-Erkennung (Name/Adresse ohne exakten E-Mail-Match) -- Letzteres
bleibt bewusst manuelles Admin-Thema über `kunden_merge_queue` für später,
nicht von diesem Sync-Pfad automatisch befüllt.

**Wichtiger Fund:** Das komplette Datenmodell dafür existierte schon seit
Migration 047 (2026-06-19) -- `kunden`, `kunden_shops`,
`kunden_merge_queue`, `KundenService::anlegen()` mit fertigem
E-Mail-Hash-Duplikat-Check (`Encryption::hash()`/`findByEmailHash()`). Kein
neues Datenmodell nötig, nur die fehlende Verknüpfungslogik.

**Reihenfolge in `ShopBestellungSyncService::ermittleOderErstelleKunde()`:**
1. Schon verknüpfte WC-Kunden-ID (`kunden_shops.external_id`) -- schnellster,
   sicherster Pfad für wiederkehrende registrierte Kunden
2. Exakter E-Mail-Hash-Match (`KundenRepository::findByEmailHash()`) --
   deckt sowohl Gäste mit bekannter E-Mail als auch neue WC-Accounts ab,
   deren E-Mail schon im ERP existiert (z.B. Laden-Stammkunde bestellt erstmals
   online)
3. Neu anlegen via `KundenService::anlegen()` (`kundenherkunft='shop'`),
   danach `kunden_shops`-Verknüpfung falls eine echte WC-Kunden-ID vorhanden war

**🔴 Fünfter Fund desselben wiederkehrenden Bug-Musters** (nach
cron/mahnwesen.php, LagerService, ShopSyncService, AuftragService × 2):
`KundenService::anlegen()` hatte ebenfalls ein `Logger::log()` ohne
`benutzerId` -- gefixt (optionaler `?int $erstelltVon`-Parameter, gleiches
Muster). Die anderen `Logger::log()`-Stellen in `KundenService`
(`bearbeiten`, `adresse_anlegen` etc.) sind NICHT gefixt -- werden von diesem
Sync-Pfad nicht aufgerufen, aber bei künftiger Cron-Nutzung dort zuerst
nachsehen.

**Kleinerer Fund:** `KundenRepository::verschluesseln()` nutzt `?:` statt
`?? null` für optionale Felder (`kundengruppe_id` etc.) -- wirft PHP-Warnungen
(nicht fatal) wenn der Aufrufer diese Keys komplett weglässt statt sie explizit
auf `null` zu setzen. Nicht selbst gefixt (nicht dieser Sync-Code, sondern
bestehendes Repository-Verhalten) -- im Sync-Code stattdessen alle optionalen
Keys explizit mit `null` befüllt.

**End-to-End getestet** gegen `indra-design.at` (4 Test-Bestellungen +
1 echter WC-Testkunde): Gast mit neuer E-Mail → neuer Kunde; registrierter
WC-Kunde mit neuer E-Mail → neuer Kunde + `kunden_shops`-Verknüpfung; zweite
Bestellung desselben WC-Kunden → korrekt derselbe Kunde über die
externe ID wiederverwendet (kein Duplikat); Gast-Bestellung mit
bereits bekannter E-Mail → korrekt über E-Mail-Hash gematcht (kein Duplikat).
Kompletter Cleanup (Test-Orders + Test-WC-Kunde gelöscht, Aufträge/Kunden/
Zuordnungen/Reservierungen aus Dev-DB entfernt).

## ✅ Phase 3: Bestellungen aus WooCommerce (Polling) FERTIG (2026-07-21)

**Architektur-Korrektur gegenüber der Vorrecherche vom 2026-07-19:** Damals
"Hybrid: Webhook Echtzeit + Polling Sicherheitsnetz" geplant. Beim
Bilder-Sync (gleicher Tag) kam raus: ERP hat keinen öffentlichen Endpunkt
(VPN-only). Ein Webhook ist Push VON WooCommerce ZU uns -- geht damit nicht.
**Jackys Entscheidung: reines Polling**, exakt wie JTLs eigener
Connector-Worker auch nur in Intervallen abgleicht. Öffentlicher
Webhook-Empfänger bleibt als "Nice to have" vorgemerkt, keine Eile.

**Wichtiger Fund:** `auftraege.kanal` hatte bereits `'woocommerce'` im ENUM
und `kanal_auftrag_id` war laut Code-Kommentar explizit für die
WooCommerce-Order-ID vorgesehen (seit Migration 060) -- kein neues
Datenmodell für die Kern-Zuordnung nötig. Nur `shops.bestellungen_letzter_sync`
(Migration 147) als Polling-Cursor neu dazu (WC-REST-API-Parameter
`modified_after` live gegen die aktuelle v3-Doku verifiziert, nicht die alte
Legacy-API mit `filter[updated_at_min]`).

**🔴 Vierter Fund desselben wiederkehrenden Bug-Musters** (nach
cron/mahnwesen.php, LagerService::wareneingang(), ShopSyncService-Jarvis):
`AuftragService::anlegen()` UND `statusAktualisieren()` lasen
`$_SESSION['benutzer']['id']` direkt -- crasht ohne Session. Fix: beide
Methoden bekommen einen neuen optionalen letzten Parameter (`?int
$erstelltVon`/`?int $benutzerId`, Default weiterhin `$_SESSION` für alle
bestehenden Aufrufer unverändert). **Blieb dabei sogar EIN drittes,
verstecktes `Logger::log()` innerhalb von `anlegen()` unentdeckt**, das keinen
`$benutzerId` übergab und deshalb trotz des ersten Fixes noch gecrasht ist
(NOT-NULL-Verletzung an `aktivitaeten.benutzer_id`) -- erst beim echten
End-to-End-Test aufgefallen, nicht beim Code-Lesen. Zwei WEITERE
`Logger::log()`-Stellen mit demselben Muster (`stornieren()` Zeile ~222,
`bearbeiten()` Zeile ~429, `zahlung_buchen` Zeile ~484) sind bewusst NICHT
gefixt -- werden von diesem Sync-Pfad nicht aufgerufen, aber falls diese
Methoden mal aus einem Cron/CLI-Kontext gebraucht werden, hier zuerst nachsehen.

**`auftraege.shop_id`** wurde bisher nirgends beim Insert gesetzt (Spalte
existierte seit Migration 067, aber `AuftragRepository::insert()` band sie
nie) -- für Phase 3 jetzt ergänzt (Spalte + Platzhalter + Bindung), harmlos
rückwärtskompatibel (Default NULL für alle bestehenden Aufrufer).

**Entscheidungen (mit Jacky abgestimmt):**
- Kunde nur als `kunden_snapshot`-JSON (wie Kasse-Laufkunde), kein `kunden_id`
  -- echtes Anlegen/Abgleichen ist bewusst Phase 4 (Kunden-Merge)
- Zahlungsart: `bacs`/`cheque`→vorkasse, `cod`→nachnahme, `paypal`/`ppcp`→paypal,
  unbekannt (z.B. Stripe/Kreditkarte, aktuell nicht geplant)→Fallback vorkasse
  + Warn-Log statt Absturz
- Preise 1:1 aus WC-Line-Items übernommen (nicht aus unseren `artikel_preise`
  neu berechnet) -- der zum Bestellzeitpunkt bezahlte Preis muss eingefroren
  bleiben, passt zur bestehenden "bezeichnung/ean eingefroren"-Philosophie
- SKU ohne Treffer → Divers-Platzhalter-Artikel (99-9999, gleicher Mechanismus
  wie `KassenService::getDiversArtikelId()`)
- Bei Update einer schon importierten Bestellung: `zahlungsstatus` immer
  nachgezogen, `lieferstatus` NUR bei `cancelled`→`storniert` überschrieben
  (Rest ist unser eigener Versand-Workflow, soll nicht zurückgesetzt werden)

**End-to-End getestet** gegen `indra-design.at` (echte Testbestellung #36 via
REST erstellt, Produkt #15/SKU D-1059 = Artikel #150): Insert-Pfad
(`processing`→ bezahlt/in_bearbeitung, Reservierung angelegt), Update-Pfad
ohne relevante Änderung (`completed`→ lieferstatus bewusst unverändert),
Update-Pfad mit Zahlungsänderung (`refunded`→ zahlungsstatus korrekt auf
erstattet, lieferstatus unverändert), Stornierung (`cancelled`→ beide Status
auf storniert, Reservierung korrekt auf `erledigt` freigegeben,
`schliesseReservierungen()` wiederverwendet), Idempotenz über 5 Durchläufe
(nie ein zweiter Auftrag für dieselbe WC-Order-ID), Cursor-Mechanismus
bestätigt (nach vollständigem Sync liefert ein erneuter Poll `erfolg:0`,
keine unnötige Wiederverarbeitung). Kompletter Cleanup (Test-Order gelöscht,
Auftrag/Position/Reservierung aus Dev-DB entfernt, Cursor zurückgesetzt).

## ✅ Phase 2: Bestand/Lagerstand FERTIG (2026-07-21)

**Business-Entscheidung (Jacky, 2026-07-21):** Shop-Verfügbarkeit zählt NUR
eigene, nicht-Messe-Lager (`lager_beziehung='eigen' AND typ != 'messe'`) --
Partner-Bestand/Händler-Außenlager zählen NICHT mit (nicht ohne Weiteres aus
dem Shop heraus versandfähig). Deckt sich mit der schon dokumentierten Regel
"Messe-Lager nicht für Shops verfügbar" (`project_lager_konzept.md`) -- die
bestehenden Artikel-Listen-Queries im Admin-Bereich filtern das allerdings
NICHT (summieren über alle Lager), das ist für die interne Ansicht ok, wurde
aber bewusst NICHT für den Shop-Sync übernommen.

**Umsetzung:**
- `ShopSyncRepository::findBestandInfo()`: `gesamtbestand` nur aus
  qualifizierenden Lagern, `reserviert` = offene `reservierungen` (gleiches
  Muster wie überall sonst im Code), `hat_lagerstand` aus `artikel_typen`
  (z.B. Download-Artikel = 0 → kein Bestandsfeld im Payload, immer kaufbar)
- `ueberverkauf_erlaubt` → WooCommerce `backorders: 'notify'`, sonst `'no'`
  (WooCommerce leitet `stock_status` daraus selbst ab, kein eigenes Feld nötig)
- Bestand wird NUR gesetzt bei: Standalone-Artikel (direkt am Produkt) ODER
  Kind-Artikel (an der Variation) -- NICHT am Vater eines Variable Products
  (`manage_stock` bleibt dort `false`), weil WooCommerce Bestand bei
  Variable Products pro Variation verwaltet, nicht am Elternprodukt
- `hat_eigenen_lagerstand` (Kind bucht auf Vater-Bestand) bewusst NICHT
  extra behandelt -- Flag ist zwar in der DB, aber nirgends im System
  tatsächlich verdrahtet (nur 1 Zeile in der ganzen Dev-DB hat es auf 0),
  jeder Artikel wird darum gleich behandelt (eigener Bestand pro Zeile)

**🔴 Echter Bug gefunden + gefixt, BEVOR Jacky ihn treffen konnte:** Gleiches
Muster wie beim Bilder-Fund vorhin -- eine Lagerbuchung/Reservierung ändert
`lagerbestand.geaendert_am`/`reservierungen.geaendert_am`, NICHT
`artikel.aktualisiert_am`. Ohne Fix hätte ein längst synced Artikel bei
reiner Bestandsänderung (Verkauf, Wareneingang, neue Reservierung) NIE
nachgezogen. Fix: zwei weitere `EXISTS`-Bedingungen in `findFaelligeArtikel()`
(nur gegen qualifizierende Lager, um irrelevante Messe-Buchungen nicht
unnötig einen Resync auszulösen).

**End-to-End getestet** gegen `indra-design.at` (Test-Vater/Kind #2852/#2853,
Testbestand 15 minus Reservierung 4 = 11): Variation korrekt mit
`manage_stock=true`, `stock_quantity=11`, `backorders=no`,
`stock_status=instock`; Vater (Variable Product) korrekt `manage_stock=false`;
Nachzieh-Fall (Bestand nach Sync auf 20 geändert, kein artikel-UPDATE) durch
den Fix korrekt erfasst (→ 16 nach Reservierungsabzug); `ueberverkauf_erlaubt`
korrekt auf `backorders=notify` gemappt; `hat_lagerstand=0` (Download-Typ,
testweise umgeschaltet) liefert korrekt kein Bestandsfeld. Kompletter Cleanup
(WC-Produkt/Attribut gelöscht, alle DB-Testzeilen inkl. Lagerbestand/
Reservierung/ueberverkauf_erlaubt zurückgesetzt).

## ✅ Bilder-Sync (Vater UND Kind) FERTIG (2026-07-21)

**Wichtige technische Hürde, VOR dem Bauen geklärt:** WooCommerce kennt zwei
Wege, ein Produktbild zu setzen -- öffentliche URL (WordPress lädt selbst
runter/"sideload") oder direkter Byte-Upload. Weg 1 fällt bei uns weg: das ERP
hat laut [[project_infrastruktur]] bewusst KEINEN öffentlichen Endpunkt (nur
VPN), `indra-design.at` könnte unsere Bild-URLs also nie erreichen. Bleibt nur
direkter Upload -- der läuft aber über die WordPress-KERN-REST-API
(`/wp-json/wp/v2/media`), NICHT über WooCommerce (`/wc/v3/...`) und braucht
darum eine ZWEITE Art Zugangsdaten: ein WordPress-**Application-Password**
(Benutzername + generiertes App-Passwort), unabhängig vom bestehenden
WC-Consumer-Key/Secret. Migration 146: `shops.wp_username`/`wp_app_password`.

**Stolperstein beim Einrichten:** Jacky hatte zuerst den LABEL-Namen des
Application-Passwords ("Bildersync") als Benutzernamen geschickt -- das ist
aber nur die Bezeichnung des Credentials selbst, nicht der WordPress-Login-Name.
Richtig ist der tatsächliche Anmeldename (bei Hosting-generierten WP-Installs
oft kryptisch, z.B. `karlindra_ee1c0z1a`). Fehlerbild bei Verwechslung:
HTTP 401 "Unbekannter Benutzername".

**Umsetzung:**
- `WooCommerceClient::ladeBildHoch()` -- multipart/form-data-Upload (CURLFile)
  mit Basic-Auth (Username:App-Passwort), inkl. `alt_text` im selben Request
- `artikel_bilder_shops` (Sync-Tracking-Tabelle) existierte bereits SEIT
  Migration 045 (2026-06-19), aber komplett ungenutzt -- kein neuer Code nötig
  für das Datenmodell selbst, nur die fehlenden Repository-Methoden ergänzt
- **Keine Vater→Kind-Vererbung** (Entscheidung aus [[project_bilder_modul]]):
  jede Artikel-Zeile (Vater UND jedes Kind) hat eigene Bilder, `syncBilderFuerArtikel()`
  läuft darum mit der jeweils EIGENEN `artikel_id`, nicht mit `$vaterId`
- Produkt bekommt `images` (Plural, ganze Galerie in Positions-Reihenfolge),
  Variation bekommt `image` (Singular, nur das Hauptbild/Position 0) -- exakt
  dasselbe Singular/Plural-Muster wie schon bei `option`/`options`
- Wasserzeichen bewusst NICHT eingebaut (Feature existiert laut
  [[project_bilder_modul]] noch gar nicht) -- Bilder gehen vorerst unmarkiert
  raus, unkritisch für den Testshop, vor echtem Live-Gang nachziehen

**🔴 Echter Bug beim Testen gefunden + gefixt:** `findFaelligeArtikel()` prüfte
nur `artikel_shops`/`artikel.aktualisiert_am` auf "fällig" -- ein Bild, das
NACH dem letzten Produkt-Sync hochgeladen wird, hätte NIE nachgezogen werden
können, weil der Artikel selbst schon `synced` war und sich nicht mehr
ändert. Fix: neue `EXISTS`-Bedingung prüft zusätzlich, ob irgendein Bild
dieses Artikels noch `pending`/`error` in `artikel_bilder_shops` steht.

**End-to-End getestet** gegen `indra-design.at` (gleiches Test-Vater/Kind-Paar
#2852/#2853, Testbilder aus vorhandenen Artikel-Bildern kopiert): Vaterbild
korrekt in der Produkt-Galerie, Kindbild korrekt als Variation-Bild, Nachzieh-
Fall (Bild nach Artikel-Sync hinzugefügt) durch den Fix korrekt erfasst,
danach 0/0 im Leerlauf (kein Endlos-Retry). Kompletter Cleanup (WP-Medien +
WC-Produkt/Attribute gelöscht, DB-Testzeilen + kopierte Testbilddateien entfernt).

## ✅ Vater/Kind-Artikel (Variable Products/Variations) FERTIG (2026-07-21)

Vorher übersprang `findFaelligeArtikel()` jeden Artikel mit `vaterartikel_id`
komplett -- jetzt werden Vater UND Kind gesynct.

**Referenz-Vergleich vor dem Bau:** WooCommerce hatte bis vor sechs Wochen
KEINE native Swatch/Dropdown-Unterscheidung pro Attribut (reine Plugin-Domäne),
anders als JTL-Shop und Shopware 6, die das seit Jahren nativ haben. WC 10.9
(Beta seit 2026-06-08) bringt jetzt einen `wc-visual`-Attributtyp für
Color/Image, aber nur hinter Feature-Flag und mit undokumentierter REST-API
(kein offizieller Parameter für Swatch-Hex/Label). **Entscheidung:** `swatches`/
`dropdown`/`radiobutton` werden alle drei als normales globales WC-Attribut
gesynct (visuelle Optik ist Theme-Sache, passt zur bestehenden
"Shop-Theme erst nach dem Sync-Teil"-Entscheidung, siehe [[project_shop_theme]]).
`freitext`/`pflichtfreitext` werden bewusst NICHT als Variations-Attribut
gesynct (WC-Variationen brauchen abzählbare Werte, kein Freitext) -- unkritisch,
da aktuell keine einzige Achse mit diesem Typ produktiv einem Vater zugewiesen
ist (in der Dev-DB geprüft).

**Datenmodell:** Migration 143 -- `varianten_achsen_shops` (achse_id, shop_id,
externe_attribut_id) + `varianten_achse_werte_shops` (wert_id, shop_id,
externe_term_id), analog zum `kategorie_shops`-Muster. `artikel_shops.external_id`
enthält bei Kind-Zeilen jetzt tatsächlich die WooCommerce-**Variation**-ID (war
in Migration 142 schon als Kommentar vorgesehen).

**Wichtiger technischer Unterschied zu Kategorien:** Bei Kategorie-Duplikaten
gibt WooCommerce die ID der bestehenden Kategorie im Fehler-Body zurück
(catch-and-reuse funktioniert). Bei **Attributen** (nicht bei Terms!) macht WC
das NICHT -- deshalb "erst nachsehen, dann anlegen" (`listeAttribute()` vor
`erstelleAttribut()`), nicht Try/Catch. Bei Terms funktioniert Catch-and-reuse
zwar technisch (Fehlercode `term_exists` + `resource_id`), wurde hier aber aus
Konsistenzgründen auch auf "erst nachsehen" umgestellt (`listeAttributTerms()`
einmal pro Achse laden, nicht Try/Catch pro Wert).

**Sync-Reihenfolge:** `findFaelligeArtikel()` liefert Väter per `ORDER BY`
immer vor ihren Kindern. Ein Kind ohne bereits synced Vater wird im aktuellen
Durchlauf übersprungen (`vater_external_id` NULL → `continue`, bleibt
`pending`) -- kein Sonderfall nötig, greift beim nächsten Cron-Lauf automatisch.
`syncAchsenFuerVater()` läuft für JEDE fällige Zeile (Vater wie Kind), ist aber
pro Achse/Wert über die neuen Zuweisungstabellen idempotent.

**Wichtige Falle beim Payload:** Der Vater bekommt IMMER alle am Vater
deklarierten Werte einer Achse als `options` (nicht nur die, deren Kind gerade
shop-aktiv ist) -- sonst würde WooCommerce eine Variation ablehnen, deren Wert
nicht in der Options-Liste des Elternprodukts steht. Bei Variationen heißt das
Attribut-Feld `option` (Singular), beim Elternprodukt `options` (Plural) --
leicht zu verwechseln.

**End-to-End getestet** gegen `indra-design.at` mit echtem Vater/Kind-Paar aus
der Dev-DB (Artikel #2852 "Doremi" + Kinder #2853/#2854, Achse "Farbe"/Werte
579-584): Vater wurde korrekt als `type=variable` mit 6 Options angelegt,
beide Kinder als Variationen mit korrektem SKU/Preis/`option`-Wert, zweiter
Durchlauf (nachdem Vater eine external_id hatte) hat die zuvor übersprungenen
Kinder nachgezogen, dritter Durchlauf (Idempotenz-Check) hat nichts dupliziert.
Alles danach wieder aufgeräumt (WC-Produkt+Attribut gelöscht, Test-Zeilen aus
`artikel_shops`/`varianten_achsen_shops`/`varianten_achse_werte_shops` entfernt).

**Bewusst nicht gebaut:** Bestand/Lagerstand für Kinder (kommt mit Phase 2),
Bilder pro Kind (Vater-Stimmungsbild/Kind-Einzelbild, siehe altes Design in
`db_design_entscheidungen.md` -- eigenes Thema, noch nicht angegangen).

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

0. **Hersteller-Filter (WC-Produktattribut) + GPSR-Herstellerangaben** — neue Design-Entscheidung 2026-07-20, siehe [[project_hersteller_shop_filter]] für Details. Beides noch nicht umgesetzt, GPSR-Teil bewusst zurückgestellt bis Jacky Detailantworten hat.
0b. **Shop-Theme/UX (Look & Feel)** — bewusst NICHT anfangen bis der komplette technische Sync-Teil fertig ist, siehe [[project_shop_theme]]. Erst danach Theme-Kandidaten (Premium vs. frei+Child) recherchieren.
1. **`cron/shop_sync.php` + Kategorie-Umbenennung-Sync** — beide bewusst zusammen zurückgestellt bis zum Live-Rollout (siehe oben), nicht einzeln vorziehen
2. ~~Vater/Kind-Artikel (Variable Products) — eigentlicher WooCommerce-Sync~~ ✅ FERTIG 2026-07-21, siehe Abschnitt oben. Bilder pro Kind/Vater sind dabei bewusst noch ausgeklammert geblieben (eigenes Thema).
3. **Phase 2 (Bestand)**, **Phase 3 (Bestellungen-Webhook + Polling-Sicherheitsnetz)**, **Phase 4 (Kunden-Merge)** — noch nicht begonnen, siehe Phasenplan oben in dieser Session besprochen
4. **JTL-Anreicherungs-Import** — eigenständige, kleinere Idee (siehe [[project_roadmap_reihenfolge]]), nicht Teil dieser Sync-Arbeit, aber gleichzeitig vorgemerkt

## Test-Rückstände (Dev-DB, harmlos aber zur Kenntnis)
`artikel_shops` hat eine echte Zeile für Artikel #150 (DROPS Baby Merino) → Shop 1, `sync_status='synced'`, `external_id=15`. Auf dem echten Testshop (`indra-design.at`) liegen dadurch zwei echte Produkte: #14 (Entwurf, reiner REST-Client-Test) und #15 (veröffentlicht, aus dem Sync-Testlauf, mit echten MeaLana-Artikeldaten). Beide können gelöscht werden, sobald nicht mehr als Referenz gebraucht.

**How to apply:** Bei Wiedereinstieg diese Datei UND `db_design_entscheidungen.md` (Abschnitt "Multi-Shop-Architektur"/"WooCommerce Kategorie-Sync") zusammen lesen — letztere hat die inhaltlichen Design-Entscheidungen (Achsen→Variations-Mapping etc.), diese Datei den tatsächlichen Baufortschritt.
