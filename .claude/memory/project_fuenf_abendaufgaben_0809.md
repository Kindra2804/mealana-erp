---
name: project-fuenf-abendaufgaben-0809
description: "Fünf Punkte von Jacky am Abend 2026-08-09 gesammelt, der Reihe nach abzuarbeiten: Kontrollliste, UVP-Streichpreis + Hersteller-als-Marke alle FERTIG; als Nächstes Labels, dann Download-Artikeltyp"
metadata: 
  node_type: memory
  type: project
  originSessionId: d1aef7f2-7ecd-41ea-b428-cf0b1d692643
  modified: 2026-08-09T19:32:01.892Z
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

## 4. Labels (Woo-Artikellabels) — offen, 0%

Ansteuerung vom ERP aus noch zu klären. Blocksy soll das können, evtl. kommt für die Live-Shops Woostify Pro (das ebenfalls Labels mitbringen soll) — siehe Punkt 3 oben zur Theme-Frage. Keine technische Recherche bisher.

## 5. Download-Artikeltyp — offen, 0%, komplexestes Thema

Artikeltyp "Download" existiert nur als Typ-Eintrag, keine Funktionalität dahinter. Anforderungen laut Jacky:
- Datei-Upload ähnlich den Artikelbildern (Drag&Drop), Ablage im Shop
- Freischaltung erst NACH Bestellung — auch bei 0,-€-Downloadartikeln (nicht einfach frei zugänglich)
- Globale Einstellung: wie oft ein Download pro Kauf abrufbar sein darf (sofern in WooCommerce nativ abbildbar — WC Downloadable Products hat dafür ein Limit-Feld, vermutlich direkt nutzbar)
- Anzeige im Kundenkonto sowohl im Shop ALS AUCH im ERP (Kunden-Detail-Seite)

**Wird zuletzt angegangen**, da bei weitem der größte Umbau der fünf Punkte.

## Nebenbei erledigt: GPSR-Hersteller-Daten im Shop veraltet (2026-08-09, nach Punkt 3)

Jacky bemerkte, dass die live angezeigten GPSR-Kontaktdaten (fast alle Hersteller außer Addi) nicht mit dem ERP übereinstimmten. Ursache + Fix in [[project_infrastruktur]] dokumentiert (Live→Dev-DB-Import hatte alte `aktualisiert_am`-Zeitstempel mitgebracht, Sync-Fälligkeit dadurch blind) — 61 Hersteller-Zuweisungen zurückgesetzt + Cron manuell angestoßen, von Jacky live bestätigt ("jetzt passts").

## Session-Ende 2026-08-09

Drei von fünf Punkten fertig (Kontrollliste, UVP-Streichpreis, Hersteller-als-Marke) plus der GPSR-Nebenfund. **Restliche Punkte (4. Labels, 5. Download-Artikeltyp) vertagt auf die nächste Session**, kein Grund/Blocker — Jacky wollte für heute Schluss machen.

**How to apply:** Bei nächster Session mit Punkt 4 (Labels) weitermachen, in dieser Reihenfolge, nicht neu improvisieren — siehe auch [[project_roadmap_reihenfolge]]. Shop-Sync-Preis-Fix (Punkt 2) bei Gelegenheit mit einer echten Aktion gegentesten, nicht von selbst vorschlagen. Für Punkt 4 (Labels) UND generell künftige Mega-Menu-Shortcodes: die beiden Fallstricke aus Punkt 3 (li-Stripping, float/width-Override) als Ausgangspunkt nehmen, nicht neu entdecken.
