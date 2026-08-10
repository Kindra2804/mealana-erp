---
name: project-fuenf-abendaufgaben-0809
description: "Fünf Abendaufgaben 2026-08-09/10: ALLE FÜNF Punkte gebaut (Kontrollliste, UVP-Streichpreis, Hersteller-als-Marke, Labels/Basis-Badges, Download-Artikeltyp) — nur Jackys eigener Upload-Test bei Punkt 5 steht noch aus"
metadata: 
  node_type: memory
  type: project
  originSessionId: d1aef7f2-7ecd-41ea-b428-cf0b1d692643
  modified: 2026-08-10T10:03:38.598Z
---

## Ausgangslage (Jacky, 2026-08-09 Abend)

Fünf Punkte auf einmal genannt, Claude hat vorgeschlagen sie einzeln durchzugehen statt parallel. Jacky: "der Reihe nach, einzeln besprechen". Reihenfolge (von Claude vorgeschlagen, nicht widersprochen): Kontrollliste → UVP-Streichpreis → Hersteller-als-Marke → Labels → Download-Artikeltyp (komplexestes Thema zuletzt).

## 1. Kontrollliste (Artikeltyp/Artikelgruppe/Einheit/Hersteller) — ✅ FERTIG 2026-08-09

Für die Buchhaltung: alle Vaterartikel durchschauen können, ob Typ/Gruppe/Einheit/Hersteller korrekt zugeordnet sind.

**Fund beim Draufschauen:** Die bestehende `artikel/liste.php` hatte schon fast alles — Hersteller/Typ/Einheit waren bereits optionale Spalten im Spalten-Picker, nur "Artikelgruppe" fehlte als Spalte (gab nur den Qualitätsfilter "Keine Artikelgruppe", keine Anzeige). Kein neues Modul nötig.

**Umsetzung:**
- Neue Spalte `artikelgruppe` im Spalten-Picker (`artikel/liste.php`), inkl. `LEFT JOIN artikel_gruppen` + `ag.name AS artikelgruppe` in **beiden** Queries von `ArtikelRepository` (`findAll()` für Vater-Zeilen UND `findKinderFuerListe()` für aufgeklappte Kind-Zeilen — Parität war hier wichtig, siehe [[project_spalten_picker]])
- Jacky hat Teil 1 selbst geschrieben (Trainer-Ansatz, siehe [[feedback_trainer]]), zwei Fehler gefunden+erklärt: fehlendes Semikolon (Parse-Error) und fehlende zweite Query für Kind-Zeilen
- **Neue Datei `artikel/liste_export.php`** (CSV-Export) — auf Jackys Wunsch von Claude direkt gebaut statt vorgegeben, da neues Muster (kein Copy-Paste-Fall wie Teil 1). Folgt exakt dem bestehenden `DatevFormatter::alsCsv()`-Muster (`;`-getrennt, UTF-8-BOM, CRLF, `csvFeld()`-Escaping) statt eine neue Formatter-Klasse einzuführen. Respektiert die aktuellen Filter der Bildschirmansicht (z.B. Export nur "Kein Hersteller"-Treffer möglich), da `$_GET` 1:1 weitergereicht wird (außer `seite`)
- Der bereits vorbereitete, deaktivierte "⬆ Export"-Button in der Action-Bar wurde aktiviert (verlinkt jetzt auf `liste_export.php?<aktuelle-Filter>`)
- Von Jacky im Browser getestet und bestätigt ("passt und funktioniert")

## 2. UVP/Streichpreis — ✅ FERTIG 2026-08-09

**Fund beim Draufschauen:** Das Feld `artikel.uvp` existierte schon (Migration 029), war im Preise-Tab editierbar (`uvp_speichern.php`), und der JTL-Import füllte es sogar schon (Vater UND Kind, `JtlVaterKindImportService.php`) — Jackys Annahme "nirgends gesetzt" stimmte nicht ganz: 411 von 3171 Hauptartikeln hatten schon einen echten UVP aus JTL. Der Rest hatte in der JTL-Quelle schlicht nie einen UVP gepflegt (gleiches Muster wie der Herkunftsland-Datenqualitäts-Fund, siehe [[project_roadmap_reihenfolge]]).

**Umgesetzt (alle drei Teile direkt von Claude, auf Jackys Wunsch statt Trainer-Ansatz — Preislogik zu heikel für einen ersten Versuch):**
1. **Neu-Formular:** `artikel/neu.php` hat jetzt ein UVP-Feld, defaultet in `ArtikelService::save()` auf den eingegebenen Brutto-VK falls leer gelassen (UVP soll nie zufällig NULL bleiben)
2. **Einmaliger Nachtrag** (`scripts/backfill_uvp.php`, `--dry-run`-fähig): UVP = aktueller Standard-VK bei allen Artikeln (Vater UND Kind) mit `uvp IS NULL OR uvp = 0`. Live gelaufen: 16.587 von 17.484 aktualisiert, 897 ohne jeden Preis blieben leer (Divers-Platzhalter etc., Liste hat Jacky). Von Jacky stichprobenartig im Preise-Tab gegengecheckt, bestätigt ("UVP jetzt drin")
3. **Shop-Sync-Fix** (`ShopSyncService`/`ShopSyncRepository`) — **größerer Fund als der ursprüngliche Punkt:** der Preis-Sync nach WooCommerce las bisher direkt+ungefiltert aus `artikel_preise`, komplett unter Umgehung der bestehenden 4-stufigen Preis-Priorität (`PreisService::getEffektiverPreis()`: SALE-Override → Aktionsmodul → KG-Preis → Standard). Das heißt: ein aktiver SALE-Override oder eine Aktionsmodul-Aktion kam bisher NIE im WooCommerce-Shop an, obwohl beide Features im ERP selbst längst fertig sind. Jetzt: `regular_price` = UVP, `sale_price` = Effektivpreis NUR wenn `quelle` `sale`/`aktion` ist und niedriger als UVP, sonst explizit `''` (WooCommerce lässt bei PUT-Updates einen alten `sale_price` sonst stehen, gleiches Muster wie der bereits dokumentierte `manage_stock`-Fall im selben File). `findEndkundenPreis()` komplett entfernt (unused nach dem Umbau), neue `ShopSyncRepository::findUvp()`.
   - **Bug direkt beim Testen gefunden+gefixt:** `uvp = 0.00` (explizit, nicht NULL) wurde anfangs als "echter UVP von 0€" behandelt statt als "nicht gesetzt" — hätte falsche Nullpreise an WooCommerce geschickt. Jetzt zählt 0 wie NULL (`findUvp()`), Backfill deckt beide Fälle in einem Lauf ab.
   - Mit einem temporären SALE-Override live durchgetestet und sauber wieder entfernt (Test-Isolation, siehe [[feedback_test_isolation]]): ohne Aktion `regular_price=3.50/sale_price=''`, mit 2,50€-Override `regular_price=3.50/sale_price=2.50` — beides korrekt.

**Noch offen:** Echter End-to-End-Test gegen den WooCommerce-Testshop mit einer ECHTEN, nicht-künstlichen Aktion — aktuell läuft keine, Jacky testet das später sobald wieder eine Aktion ansteht.

## 3. Hersteller als Marke/Menüpunkt — ✅ FERTIG 2026-08-09

**Fund beim Draufschauen — drei parallele Hersteller-Mechanismen im Shop, nur einer davon der richtige:**
- **"Hersteller"** in wp-admin = native WC-Taxonomie `product_manufacturer` (GPSR-Feature) — reiner Backend-Datenspeicher (Adresse/Verantwortliche Person), KEIN Yoast-SEO-Panel, KEIN "Anzeigen"-Link → keine öffentliche Kundenseite. Von unserem Sync bereits befüllt, aber für ein Menü ungeeignet.
- **"Marken"** in wp-admin = die "echte" native WC-Taxonomie `product_brand` (Bilder/Hierarchie/eigene Widgets, explizit für "Markenarchive" gedacht) — komplett leer, unser Sync schreibt dort nie hin. Kein Plugin (Perfect Brands o.ä.) nötig gewesen, aber auch `product_brand` wurde am Ende NICHT verwendet (siehe unten).
- **Das WC-Produktattribut "Hersteller"** (`pa_hersteller`, `has_archives=true`, aus [[project_hersteller_shop_filter]] vom 21.7.) = der Treffer: volles WordPress-Editor-Feld + aktives Yoast-SEO-Panel → echte, öffentlich indexierbare Archivseite, UND schon mit allen Herstellern befüllt (inkl. GPSR-Beschreibungstext). **Keine neue ERP-Sync-Arbeit nötig** — nur die bereits vorhandenen Archivseiten mussten noch ins Mega-Menü.

**Umsetzung — WPCode-Snippet (WordPress-seitig, nicht im ERP-Repo) + Max Mega Menu:**
- Shortcode `[hersteller_liste]` (PHP-Snippet in WPCode, da Jacky kein Child-Theme hat und Code Snippets laut Herstellerangabe nicht auf WP 7 getestet ist) listet `pa_hersteller`-Terms per `get_terms()` + `get_term_link()` auf
- Im Mega-Menü über den gleichen Block+Shortcode-Weg wie bei Kategorien eingebunden (siehe [[project_shop_theme]])
- **Zwei echte Max-Mega-Menu-Fallstricke gefunden, beide in [[project_shop_theme]] als eigener Abschnitt festgehalten** (wichtig für jede künftige Custom-Shortcode-Arbeit im Menü): (1) Mega Menu entfernt bei "Block"-Widgets das umschließende `<ul>` von Listen-Output und lässt nackte `<li>` stehen — CSS-Selektoren, die über `ul` gehen, laufen ins Leere. (2) Mega Menu setzt eigene `float:left`/`width:100%`-Regeln direkt auf `<li>`-Elemente (JS-Höhenberechnung fürs Flyout dahinter) — `!important`-Overrides dagegen haben einmal die GANZE Seite zerlegt (Menüzeile → große Lücke → Mobilmenü → erst dann der Seiteninhalt). **Lösung, die funktioniert hat:** gar keine `<li>`-Elemente mehr ausgeben, stattdessen reine `<a>`-Tags in einem `<div>` mit `column-count` fürs Spalten-Layout — Mega Menu hat dann nichts, worauf seine Spezialbehandlung greifen könnte.
- Von Jacky live getestet und bestätigt ("jetzt passts")

**Nebenthema dabei aufgekommen:** Jacky überlegt **Woostify Pro** als Theme-Alternative zu Blocksy Pro/WoodMart (siehe [[project_shop_theme]] für die bisherige Recherche) — vanilla JS (potenziell schneller), Whitelabel verfügbar, Lizenzstufen sollen (ungeprüft) mehrere/unbegrenzte Seiten abdecken, was die bei WoodMart gefundene Pro-Domain-Lizenzfalle vermeiden würde. Noch nicht recherchiert/bestätigt, nur als Kandidat vorgemerkt — Preise/Pläne vor einer Empfehlung frisch prüfen.

## 4. Labels (Woo-Artikellabels) — ✅ Basis-Badges FERTIG 2026-08-10, Rest bewusst vertagt

Recherche ergab: kein Grund, auf die Theme-Entscheidung zu warten — alle Basis-Badges sind Blocksy/Woostify-unabhängig.
- **Sale-Badge:** natives WooCommerce-Core-Feature, lief automatisch nach dem UVP-Fix (Punkt 2) mit — nichts zu bauen.
- **"Neu"-Badge:** rein zeitbasiert im Theme (Tage seit Veröffentlichung), keine Artikeldaten nötig.
- **Featured-Badge:** natives WC-Feld `featured`, jetzt ERP-gesteuert: Migration 162 (`artikel.ist_hervorgehoben`), neues Häkchen "Hervorheben (Featured-Badge im Shop)" in `artikel/bearbeiten.php` (nur Edit-Formular, wie `ist_auslaufartikel`/`ueberverkauf_erlaubt` auch nicht in `neu.php`), `ShopSyncService::baueProduktPayload()` setzt `featured` nur beim Vater/Standalone (Variationen haben in WooCommerce kein eigenes Featured). Mit Testartikel per Reflection durchgespielt (`featured: true` korrekt im Payload), sauber zurückgesetzt.
- **Frei wählbare Text-Labels** (z.B. "Handgemacht", "Limitiert") — bewusst vertagt: braucht ein dediziertes Badge-Plugin (kein Theme kann das nativ), Jackys Entscheidung: erst bei aktiviertem Woostify Pro anschauen, kann bis Live-Gang warten.

## 5. Download-Artikeltyp — ✅ gebaut 2026-08-10, Jackys eigener Upload-Test steht noch aus

Reference-Check ergab: WooCommerce kann fast alles davon schon nativ (Zugriffsrechte, Download-Zähler, "Mein Konto"-Anzeige, auch bei 0,-€-Bestellungen über einen echten Checkout-Abschluss) — nichts davon selbst nachgebaut, nur die ERP-Seite drumherum.

**Vier Teile, alle von Claude gebaut (Jackys Wunsch, wie beim UVP-Punkt):**
1. **DB + Upload-UI:** Migration 163 (`artikel.download_dateiname`/`download_limit`, neue Tabelle `artikel_downloads_shops` fürs Sync-Tracking). Neuer "Download"-Tab in `artikel/detail.php` (nur bei Artikeltyp Download sichtbar), Drag&Drop für eine Datei (PDF/ZIP, max. 30 MB, ersetzt automatisch die alte). Jacky wollte bewusst nur EINE Datei pro Artikel (nicht wie bei Bildern mehrere) — reicht laut ihm, notfalls als ZIP bündeln.
   - **Nebenbei echten Bug gefunden+gefixt:** das Featured-Häkchen aus Punkt 4 wurde nirgends aus der DB gelesen (nur geschrieben) — wäre beim Laden immer leer erschienen und hätte sich bei jeder Bearbeitung selbst zurückgesetzt. Außerdem hat `detail.php` ein EIGENES, zweites Stammdaten-Formular (Duplikat zu `bearbeiten.php`, beide posten zu `aktualisieren.php`) — das Featured-Häkchen fehlte dort, nachgezogen.
2. **Globale Vorbelegung:** neues Feld "Download-Limit" in Einstellungen → System → Downloads. Jackys Präzisierung: Limit ist PRO ARTIKEL einstellbar (`artikel.download_limit`, NULL = folgt der globalen Vorbelegung dynamisch, Zahl = individuelle Überschreibung) — nicht rein global wie ursprünglich vorgeschlagen.
3. **Shop-Sync:** Datei-Byte-Upload in die WP-Mediathek über den bereits bestehenden Bild-Upload-Mechanismus (`WooCommerceClient::ladeBildHoch()` ist generisch, keine Änderung nötig). `virtual`/`downloadable`/`downloads`/`download_limit`-Payload-Felder nur beim Vater/Standalone (nie bei Variationen). Change-Detection via `dateiname_synced`-Vergleich, gleiches Muster wie `syncKategorieBild()`. Mit simuliertem Sync-Zustand getestet (kein echter API-Call): globale Vorbelegung + individuelle Überschreibung beide korrekt geprüft.
4. **Kundenkonto im ERP:** neuer "Downloads"-Tab auf der Kunden-Detail-Seite, zeigt bestellte Download-Artikel mit Zahlungsstatus + direktem Datei-Link (löst genau das ab, was bisher händisch per Mail gemacht wurde, siehe die Altbeschreibung bei `MAnl-123Hase`).

**Bewusster Trade-off, transparent an Jacky kommuniziert:** Datei liegt wie Bilder in der normalen WP-Mediathek (kein separates geschütztes Verzeichnis) — WooCommerce sperrt den Zugriff über Bestellrechte, aber die rohe Datei-URL wäre bei Kenntnis/Erraten technisch erreichbar. Für Anleitungen/Muster als ausreichend eingeschätzt, kein Hard-Blocking auf Dateisystem-Ebene. Bei Bedarf später ein geschützter Auslieferungsweg nachrüstbar.

**Noch offen:** Jacky lädt gerade echte Anleitungsdateien hoch, um den Upload-Teil selbst zu testen — Ergebnis steht noch aus.

## Nebenbei erledigt: Kunden-Detail zeigt jetzt Shop-Zugehörigkeit (2026-08-10)

Jacky bemerkte beim Multi-Shop-Nachdenken: Kunden-Detail-Seite zeigt nirgends, in welchem Shop ein Kunde registriert ist/bestellt hat, obwohl die Daten (`kunden_shops`) längst da sind. Kleiner Chip-Zusatz im Kunden-Header (🛒 Shopname pro Zeile aus `kunden_shops`), analog zu den bestehenden Status-/Kundengruppen-Chips. Dabei zwei bestehende Sorgen von Jacky im Code verifiziert und als bereits korrekt/erledigt bestätigt (kein neuer Code nötig):
- `ShopBestellungSyncService::ermittleOderErstelleKunde()` lehnt nie einen Kunden wegen bereits existierender E-Mail ab — sucht per externer Shop-ID, dann per E-Mail-Hash über alle Shops hinweg, verknüpft nur die neue Shop-Zuordnung statt neu anzulegen.
- Auftragsliste zeigt den Shop-Kanal bereits als Chip mit Filter (aus der Session vom 21.7.).

## Erklärung: "Sync läuft wieder alle Artikel durch" (2026-08-10, nach dem UVP-Backfill)

Jacky beobachtete beim manuellen Abgleich einen riesigen Rückstau. Ursache: der UVP-Nachtrag (Punkt 2) hatte am Vorabend 16.587 Artikel auf einmal aktualisiert — der normale 15-Minuten-Cron (nur ~20 Artikel/Lauf) hätte Tage gebraucht, das abzuarbeiten (7.973 Artikel waren zum Zeitpunkt der Frage noch offen). Kein Zusammenhang mit den Hersteller-Änderungen vom Vortag (andere, viel kleinere Tabelle, war schon komplett durch). Jackys manueller Abgleich war der richtige Move, um den Rückstau zügig aufzuholen — reines Backlog-Abarbeiten, kein neuer Bug.

## Nebenbei erledigt: GPSR-Hersteller-Daten im Shop veraltet (2026-08-09, nach Punkt 3)

Jacky bemerkte, dass die live angezeigten GPSR-Kontaktdaten (fast alle Hersteller außer Addi) nicht mit dem ERP übereinstimmten. Ursache + Fix in [[project_infrastruktur]] dokumentiert (Live→Dev-DB-Import hatte alte `aktualisiert_am`-Zeitstempel mitgebracht, Sync-Fälligkeit dadurch blind) — 61 Hersteller-Zuweisungen zurückgesetzt + Cron manuell angestoßen, von Jacky live bestätigt ("jetzt passts").

**How to apply:** Shop-Sync-Preis-Fix (Punkt 2) bei Gelegenheit mit einer echten Aktion gegentesten, nicht von selbst vorschlagen. Bei künftigen Mega-Menu-Shortcodes: die beiden Fallstricke aus Punkt 3 (li-Stripping, float/width-Override) als Ausgangspunkt nehmen, nicht neu entdecken. Custom-Text-Labels erst wieder aufgreifen, wenn Woostify Pro aktiviert ist — nicht von selbst vorschlagen.

## Session-Ende 2026-08-09 (Abend) / Fortsetzung 2026-08-10

Abend: drei von fünf Punkten fertig (Kontrollliste, UVP-Streichpreis, Hersteller-als-Marke) plus GPSR-Nebenfund. Vormittag 2026-08-10: Punkt 4 (Labels, Basis-Badges) und Punkt 5 (Download-Artikeltyp) fertig gebaut, dazu Kunden-Shop-Chip und die JTL-Kunden+Aufträge-Machbarkeitsprüfung (siehe [[project_jtl_kunden_auftraege_import]]). **Alle fünf ursprünglichen Punkte sind durch** — offen bleibt nur Jackys eigener Live-Test des Download-Uploads mit echten Anleitungsdateien.
