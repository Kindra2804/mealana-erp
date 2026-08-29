---
name: project-shop-theme
description: "WooCommerce-Theme/UX-Anpassung: Gratis-Basis (Blocksy+Elementor+Max Mega Menu+Germanized) 2026-07-22 fertig gebaut als Barbara-Testbasis; WoodMart/Blocksy-Pro-Kaufentscheidung weiterhin pausiert (Budget-Gespräch mit Barbara); Performance-Fund 2026-07-29 (kalter Seiten-Cache, TTFB-dominiert) zurückgestellt bis Shop voll befüllt; 2026-08-02: Mega-Menü aktualisiert sich nicht automatisch + Breadcrumbs-Anleitung; 2026-08-09: Max-Mega-Menu-Fallstricke bei eigenen Shortcodes (li-Stripping, float/width-Override) + Woostify-Pro-Kandidat"
metadata: 
  node_type: memory
  type: project
  originSessionId: bcf52b92-a756-4c54-8a41-faaebdece89e
  modified: 2026-08-29T12:35:36.524Z
---

## 🟢 GELÖST 2026-08-29: Viele Farb-Swatches schoben "In den Warenkorb" bildschirmhöhenweit nach unten

**Auslöser:** Jacky bemerkte bei DMC Mouline Special Sticktwist (499 Garnfarben als Image-Swatches) — der "In den Warenkorb"-Button lag ca. 4 Bildschirmhöhen tiefer als gewohnt.

**Live geprüft (wp-admin):** Zwei Swatch-Plugins gleichzeitig aktiv (**Smart Swatches** von ShapedPlugin UND **Variation Swatches for WooCommerce** von Emran Ahmed, v2.4.0) -- Doppelbelegung nicht weiter untersucht, evtl. überflüssig, bei Gelegenheit mit Jacky klären. Das Farbe-Attribut ist global (Produkte → Attribute) als Typ "Image" gesetzt -- gilt für ALLE Produkte, die dieses Attribut nutzen. Ein Produkt-individuelles Umschalten Swatches↔Dropdown ("Individual Product Basis Attribute Variation Swatches Customization") ist im Plugin explizit als **Pro-only** markiert (freie Version zeigt nur den Upsell-Screen im "Swatches Settings"-Produkt-Tab) -- kostenlos also nicht direkt lösbar.

**Fix (Jackys Vorgabe, mit Babsi abgestimmt, noch nicht final freigegeben):** reine CSS-Lösung statt Pro-Kauf oder Datenmodell-Split. Swatch-Container bleibt Swatches, wird aber ab mehr als 45 Werten (Jackys Praxis-Faustregel: 9 Swatches/Reihe × 5 Reihen sehen noch gut aus) auf `max-height:340px` (= 5 Reihen à 68px, live vermessen: 60px Swatch + 4px Rand oben/unten) mit `overflow-y:auto` begrenzt. `:has(.variable-item:nth-child(46))` sorgt dafür, dass kleine Attribute (2-3 Farben, z.B. Rundnadeln) komplett unverändert bleiben -- selbes `:has()`-Muster wie beim Produktkarten-Fix oben.

**Umgesetzt:** WPCode-CSS-Snippet 34175 "MeaLana: Swatch-Container Scroll ab 45 Werten", aktiv gesetzt, Kopie in `shop/wp-snippets/swatch_container_scroll.css`. Live verifiziert: DMC (499 Werte) zeigt `max-height:340px`/`overflow-y:auto`/tatsächlich scrollbar; Symfonie Rundstricknadel (8 Werte je Achse) zeigt `max-height:none` -- Schwelle greift korrekt selektiv.

**Noch offen:** Babsi soll die Optik absegnen (Jackys Wunsch) -- bei Bedarf schnell wieder änderbar (Schwelle/Zeilenzahl im Snippet).

## 🟢 GELÖST 2026-08-10: Produktkarten-Layout in der Shop-Übersicht (Blocksy) — Button-Ausrichtung + Grundpreis-Reihenfolge + Abstände

**Auslöser:** Babsi störte sich an "zerrupft" wirkenden Produktkarten im Shop (`ul.products li.product`, Blocksy-Grid) — Artikel mit fehlendem Preis/Grundpreis hatten den "In den Warenkorb"/"Ausverkauft"-Button auf unterschiedlicher Höhe, und der Grundpreis (Germanized, z.B. "4,90 € / 100 g") stand vor dem Button statt danach unter dem Preis.

**Root Cause:** Blocksys eigener Ausrichtungs-Mechanismus (`.ct-woo-card-actions[data-alignment="equal"]` mit `margin-top:auto`) geht davon aus, dass nach dem Button-Wrapper IMMER gleich viel Inhalt folgt (Preis+MwSt-Zeile) — fehlt der komplett (kein Preis gesetzt), rutscht der Button bis ganz nach unten. Germanized hängt den Grundpreis zudem VOR den Add-to-Cart-Button (eigener Hook, früher als der Warenkorb-Button), WooCommerce selbst den normalen Preis danach — dadurch falsche visuelle Reihenfolge.

**Fix, komplett per "Zusätzliches CSS" im WordPress-Customizer (kein PHP/Theme-Datei nötig), vier Teile:**
1. Blocksys `margin-top:auto` auf `.ct-woo-card-actions` mit `!important` auf 0 überschrieben.
2. Titel (`.woocommerce-loop-product__title`) bekommt feste Höhe (`-webkit-line-clamp:2` + `min-height`) — Button startet danach IMMER an derselben Stelle, unabhängig davon was danach folgt.
3. Visuelle Reihenfolge per Flex `order` erzwungen (Bild→Titel→Button→Preis→Grundpreis→MwSt→Versand→Inhalt) — `li.product` ist bereits ein Flex-Container (bestätigt, weil `margin:auto` überhaupt griff), kein DOM-Umbau nötig.
4. Fehlt der Grundpreis komplett (kein `.price-unit`-Element im DOM bei Artikeln ohne Grundpreisangabe): `li.product:not(:has(.price-unit))::after` mit `order:5` (gleiche Position wie `.price-unit`) fügt eine leere Platzhalter-Zeile ein, damit MwSt./Versand-Zeile bei allen Karten auf gleicher Höhe bleiben. Nutzt CSS `:has()` (seit 2023 in allen gängigen Browsern verfügbar).
5. Abstände zwischen den Zeilen einzeln verkleinert (Flex-Kinder haben kein Margin-Collapsing) — Preis→Grundpreis besonders eng.

**Wichtiger Fallstrick beim Testen:** Erste Runde sah im Customizer richtig aus, änderte sich live aber nicht — Ursache war schlicht "Veröffentlichen" nicht geklickt, nur Vorschau offen gelassen. Bei jeder künftigen CSS-Änderung dran denken, das zu prüfen, bevor man nach einem Code-Fehler sucht.

**Von Babsi/Jacky live am echten Shop bestätigt** (Startseite "Neuheiten"-Karussell + Kategorieseite). Betrifft nur den aktuellen Blocksy-Aufbau — falls doch auf Woostify gewechselt wird (siehe unten, noch unrecherchiert), muss das Grid-Verhalten dort neu geprüft werden, die Denkarbeit (welche Elemente/Reihenfolge/Lücken) bleibt aber übertragbar.

**🔴 Nebenfund, selbst verursacht + behoben:** Die ersten CSS-Regeln nutzten `ul.products li.product ...` als Selektor -- zu breit gefasst. WooCommerce gibt bei `[product_categories]`-Kacheln (genau das Hersteller-als-Marke-Mega-Menü, siehe [[project_fuenf_abendaufgaben_0809]]) `<li class="product-category product ...">` aus -- die reine Klasse `product` steckt da zufällig mit drin. Dadurch griffen Titel-Höhe/`order`/Abstände auch im Hersteller-Menü und zerlegten dessen bis dahin sauber ausgerichtetes Icon-Raster. **Fix:** überall `li.product.type-product` statt `li.product` -- echte Produkte tragen immer zusätzlich `type-product` in der Klassenliste, Kategorie-/Marken-Kacheln nie. **Wichtig für jede künftige CSS-Regel an der Shop-Produktübersicht:** IMMER `.type-product` mit angeben, sonst besteht dieselbe Kollisionsgefahr mit Kategorie-/Marken-Grids.

## Ausgangslage (Jacky, 2026-07-20)

Ziel: Der WooCommerce-Shop soll für Kunden ein "look & feel" bekommen, das nahe an das gewohnte Bild von `mealana.at` (aktuell JTL-Shop-Engine) herankommt, gerne mit UX-Verbesserungen. Der aktuelle Testshop (`indra-design.at`) läuft komplett unkonfiguriert auf dem WooCommerce-Standard-Theme (Storefront) und sieht dementsprechend nach Baustelle aus — kein Vergleichsmaßstab für das Endergebnis.

## Einschätzung (Claude, 2026-07-20)

Das mealana.at-Aussehen ist reines Theme/Template-Handwerk, keine JTL-Spezialität — mit einem ordentlichen WooCommerce-Theme (Premium mit Page-Builder z.B. Flatsome/Woodmart+Elementor, oder freies Theme + eigenes Child-Theme) ist ein ähnliches oder besseres Ergebnis technisch problemlos erreichbar (Mega-Menü, Slider, Kategorie-Promo-Sektionen, Grundpreis-Anzeige etc.).

**Gegen einen eigenen (headless) Shop-Frontend** abgewogen: Warenkorb/Checkout/Zahlungsanbindung/Versandberechnung/Steuerlogik + die ganze Sicherheits-/Compliance-Seite müsste dann komplett selbst gebaut werden — WooCommerce bringt das ausgereift mit. Deutlich mehr Aufwand für vergleichsweise wenig zusätzlichen optischen Gewinn. Empfehlung: bei WooCommerce bleiben, in ein echtes Theme investieren.

## Entscheidung: zurückgestellt (Jacky, 2026-07-20)

**Bewusst zurückgestellt, bis der komplette technische Sync-Teil der Online-Shop-Anbindung fertig ist** (siehe [[project_shop_sync]] für den Gesamtstand — Phase 1 ist fertig, Phase 2-4 + Variable-Products-Sync + Hersteller-Filter + GPSR noch offen, siehe auch [[project_hersteller_shop_filter]]). Erst wenn technisch alles steht, soll das Aussehen angegangen werden.

**Für Jackys Anspruch reicht fürs Erste:** eine ähnliche oder bessere Ansicht für Kunden gewährleisten können — kein Anspruch auf 1:1-Nachbau.

**How to apply:** NICHT von selbst mit Theme-Recherche/Umsetzung anfangen. Erst wenn Jacky den technischen Sync-Teil als fertig markiert und explizit an dieses Thema zurückkehrt: dann Theme-Kandidaten recherchieren (Premium mit Page-Builder vs. freies Theme + Child-Theme) und gemeinsam die Richtung festlegen.

## Ausnahme: Variation-Swatches-Plugin vorgezogen (2026-07-21)

Jacky bemerkte beim Testen, dass Storefront Variationen nur als Dropdown zeigt (WooCommerce-Standard ohne Plugin — native Swatches gibt es erst seit WC 10.9 als Beta und nur mit Block-Theme, siehe [[project_shop_sync]]). Er wollte Swatches als Default, unabhängig vom eigentlichen Theme-Thema. Entscheidung: **kostenloses Plugin "Variation Swatches for WooCommerce" (Emran Ahmed)** jetzt schon selbst installieren — läuft mit jedem Theme inkl. Storefront, keine Code-Änderung an unserem Sync nötig, arbeitet auf den schon vorhandenen Attributen/Terms. Kein Widerspruch zur Zurückstellung oben: das ist ein gezielter Funktions-Fix (Darstellung von Variationen), kein Theme/Look&Feel-Projekt.

**Offen für später:** unser `wert_zusatz`-Feld (Hex-Code bei Farbe-Achsen) wird aktuell NICHT automatisch an das Plugin durchgereicht — Jacky trägt Swatch-Farben vorerst manuell in wp-admin ein. Automatisches Pushen wäre möglich, sobald klar ist, welche Meta-Felder/Taxonomie-Struktur das Plugin für Swatch-Farbe/Bild genau verwendet (noch nicht recherchiert, nicht blockierend).

## Theme-Recherche durchgeführt (2026-07-21) — Kaufentscheidung pausiert

Technischer Sync-Teil (Phase 1-4) war zu diesem Zeitpunkt komplett fertig, siehe [[project_shop_sync]] — damit war die Voraussetzung für dieses Thema erfüllt.

**mealana.at angeschaut (Referenz):** klassischer, aufgeräumter Wollhandel-Look — Mega-Menü nach vielen Warengruppen, Slider mit Aktions-Bannern, Produkt-Raster (Neu/Sonderangebote/Bestseller), Grundpreis-Angabe (€/100g). Nichts Exotisches, mit jedem brauchbaren WooCommerce-Theme + Page-Builder erreichbar.

**Kandidaten verglichen:**
- **WoodMart** (Premium, Page-Builder inklusive) — eingebauter Mega-Menü-Builder, Marken-Filter, 80+ fertige Shop-Demos. Passt strukturell am besten zu mealanas vielen Warengruppen, "alles aus einer Hand". **Nur in USD zahlbar** (ThemeForest/Envato), kein EUR-Checkout.
- **Flatsome** — meistverkauftes WC-Theme, einfacher, aber schwächeres Mega-Menü/Filter-System als WoodMart. Nach dem WoodMart/Blocksy-Vergleich nicht weiter verfolgt.
- **Blocksy Pro** (kostenlose Basis + Pro-Erweiterung) — modern, schnell, aber mehr Eigenarbeit beim Mega-Menü/Seitenaufbau (z.B. via Elementor) statt fertigem Shop-Baukasten.

**Lizenz-Falle gefunden (wichtig, nicht offensichtlich):** ThemeForest-Lizenzen (WoodMart/Flatsome) gelten pro **einzelner Live-Domain** (nur zusätzliche Staging-Kopie derselben Seite erlaubt, keine zweite eigenständige Live-Seite). Bei MeaLanas 3 eigenen Shops (mealana/bio-wolle/sockenwolle, je eigene WordPress-Installation) heißt das **3 separate Lizenzen**, nicht eine — Jackys ursprüngliche Annahme (eine Lizenz reicht, "da die Quelle ja die gleiche ist") war falsch, wurde vor dem Kauf richtiggestellt. Bei künftiger Weitergabe der ERP-Software an andere Betriebe (siehe [[project_whitelabel_branding]]) müsste JEDER dieser Betriebe ebenfalls seine eigene(n) Theme-Lizenz(en) kaufen — kein Mitliefern/Bundling möglich.

**Blocksy Pro Lizenzmodell dagegen mehrseiten-freundlich:** Jacky hat selbst recherchiert und gefunden: Blocksy Pro Lifetime **299€ netto für 10 Seiten** (EUR-Zahlung, einmalig) — deckt alle 3 MeaLana-Shops komfortabel ab plus Reserve. Preislich mit WoodMart (~3× Einzellizenz in USD) mindestens gleichauf, eher günstiger, dazu einfachere Zahlung.

**Empfehlung (Claude):** angesichts der ×3-Lizenz-Realität eher Blocksy Pro als WoodMart — aber Jackys Entscheidung, kein technisches Muss.

## Entscheidung (Jacky, 2026-07-21): Kauf pausiert, Budget-Gespräch mit Barbara zuerst

Es geht um echtes Geld (~180-300€), das wird zuerst mit Barbara besprochen. **Angedachter Plan, falls sie zustimmen:** erstmal NUR eine WoodMart-Lizenz kaufen (ein Shop), ausprobieren wie gut das Ergebnis wird und wie leicht/schwer Barbara mit dem Page-Builder (Slider erstellen usw.) zurechtkommt — erst danach entscheiden, ob weitere WoodMart-Lizenzen für die anderen 2 Shops dazukommen, ganz auf Blocksy umgestiegen wird, oder eine andere Richtung gewählt wird.

**How to apply:** NICHT von selbst weitermachen (kein Lizenzkauf, keine Installation) bis Jacky sich nach dem Barbara-Gespräch zurückmeldet. Bei Wiedereinstieg diesen Abschnitt + die Lizenz-Falle oben als Ausgangspunkt nehmen, nicht neu recherchieren.

## Gratis-Basis gebaut, während das Budget-Gespräch noch aussteht (2026-07-22)

Jackys Idee: bis das Theme-Budget-Gespräch durch ist, mit reinen Gratis-Boardmitteln eine Basis bauen, auf der Barbara sich schon ausprobieren kann — Upgrade auf Blocksy Pro oder Umstieg auf WoodMart bleibt jederzeit möglich (Blocksy free→Pro ist nahtlos nachrüstbar).

**Stack:** Blocksy (kostenlose Basis, nicht Pro) + Elementor Free + Max Mega Menu (Plugin) + WooCommerce Germanized (Plugin) + native WooCommerce-Shortcodes für Produkt-Raster.

**Gebaut + live gegen `indra-design.at` verifiziert:**
- **Grundpreis** (Germanized): funktioniert korrekt (7,50€/100g aus 3,75€/50g). Wichtiger Fund: "Grundpreis automatisch berechnen" ist in der Gratis-Version mit [PRO] gesperrt — man müsste den Grundpreis sonst manuell pro Produkt eintragen. Da unser ERP den Grundpreis aber schon selbst berechnet (siehe [[project_preise]]), ist die Lösung: den Wert per Sync direkt ins Feld pushen statt für die PRO-Version zu zahlen — als Nice-to-have vorgemerkt, nicht blockierend, siehe [[project_shop_sync]].
- **GPSR-Fund:** Germanized hat unter "Produktsicherheit" bereits Felder für Hersteller/Sicherheitshinweise/Produktsicherheitsdokumente — könnte das seit Wochen offene GPSR-Herstellerangaben-Thema (siehe [[project_hersteller_shop_filter]]) lösen. Bewusst NICHT vertieft (eigenes, größeres rechtliches Thema), nur als vielversprechender Ansatzpunkt vermerkt.
- **Mega-Menü** (Max Mega Menu): WordPress-natives verschachteltes Dropdown reicht bereits (Hersteller als Flyout unter der Top-Kategorie) — Jacky fand keine Spalten-Option im Flyout, aktuelle Optik akzeptiert, Feinschliff kann warten.
- **Startseite** (Elementor): Bild-Karussell mit echtem MeaLana-Branding (aus mealana.at-Assets übernommen) + drei Produkt-Raster über native WC-Shortcodes (`[products]`/`[sale_products]`/`[best_selling_products]`) — kein Zusatz-Plugin nötig.
- **Footer** (Blocksy Footer-Builder): vier Spalten (Informationen/Unsere Shops/Unsere Veranstaltungen/Ladenlokal), Inhalte 1:1 von mealana.at übernommen. Rechtstexte (AGB/Datenschutz/Widerruf) bestehen laut Jacky schon, Germanized-Legaltexte-Generator wollte er sich noch selbst ansehen (nicht abschließend geklärt, ob genutzt).
- Einzelner externer Footer-Link braucht kein WP-Menü — reicht als normaler Hyperlink im Text-Widget.

**Bewusst nicht Teil dieser Basis:** Wasserzeichen (Feature existiert noch nicht), Bild-Sync-Performance bei großem Erstimport (siehe [[project_shop_sync]] — FTP-Bulk-Lösung dafür separat gebaut).

**How to apply:** Diese Gratis-Basis ist eigenständig nutzbar und unabhängig von der oben beschriebenen Kaufentscheidung — Barbara kann jetzt schon damit arbeiten. Bei Wiedereinstieg ins Theme-Thema (Kaufentscheidung oder Feinschliff) diesen Abschnitt als aktuellen Ist-Stand nehmen.

## Performance-Fund: langsamer Seitenaufbau (2026-07-29) — zurückgestellt bis Shop voll befüllt

Jacky bemerkte ~10s Ladezeit auf `indra-design.at`, davon laut F12 ca. 6s "nichts sichtbar". Chrome-Lighthouse-Analyse gemeinsam durchgeführt:

**Diagnose:** Fast 100% Time-to-First-Byte (TTFB), nicht Assets/JS/CSS (die brauchten nur ~300ms zusammen). Drei-Stufen-Test bestätigte die Ursache eindeutig:
- Wartungsseite aktiv: LCP 1,73s (kleine statische Seite, kein Problem)
- Shop freigeschaltet, 1. Aufruf einer Seite: LCP 5,41s (TTFB 6.933ms) — kompletter PHP-Neuaufbau, Cache leer
- Dieselbe Seite nochmal aufgerufen: LCP 0,29s — Cache-Treffer, sehr schnell

Jacky bestätigte: **jede neu angeklickte Seite (nicht nur die Startseite) zeigt dieses Muster** — erster Aufruf pro URL langsam (kalter Cache), danach schnell. Klassisches Page-Cache-Verhalten (Plugin oder Hosting-seitig), aber es wurde noch nicht geprüft, welcher Cache-Mechanismus genau läuft.

**Risiko:** Der jeweils erste Besucher einer noch nie aufgerufenen Seite (z.B. nach Cache-Leerung oder bei selten besuchten Produktseiten) bekäme 5+ Sekunden Ladezeit ab.

**Entscheidung (Jacky, 2026-07-29):** Vorerst zurückgestellt, Wartungsseite bleibt wieder aktiv. Wird erneut angeschaut, wenn der Shop mal "voll" ist (mehr Inhalt/Traffic, näher am echten Go-Live).

**How to apply:** Bei Wiedereinstieg hier ansetzen, nicht neu diagnostizieren:
1. Prüfen, welcher Cache aktiv ist (Hosting-Panel oder Plugin-Liste durchsehen)
2. Cache-Vorwärmen einrichten (Skript das nach Veröffentlichung/Cache-Leerung die wichtigsten URLs einmal selbst aufruft, bevor echte Kunden reinkommen)
3. Separat testen: verhalten sich echte WooCommerce-Produkt-/Warenkorbseiten gleich, oder werden die (wegen Sitzungs-/Warenkorb-Fragmenten) bewusst vom vollen Seiten-Cache ausgenommen? — noch nicht getestet, nur Startseite/allgemeine Seiten bisher geprüft.

## Mega-Menü aktualisiert sich nicht automatisch mit neuen Kategorien (2026-08-02)

Jacky bemerkte: neu angelegte/gesynct Kategorien tauchen im Max-Mega-Menu nie automatisch auf, es
zeigt seit Tagen dieselben 3 Menüpunkte. **Kein ERP-Bug** — WordPress-Menüs (auch mit Max Mega Menu)
sind grundsätzlich eine manuell gepflegte Liste, unser API-Sync legt Kategorien zwar in WooCommerce
an, trägt sie aber nie in ein bestehendes Menü ein (das ist bei jedem WAWI/Shop-System so, nicht
MeaLana-spezifisch).

**Zwei Ansatzpunkte besprochen:**
1. **"Neue Kategorien automatisch hinzufügen"-Checkbox** unter Design → Menüs im Kategorien-Meta-Box
   (WordPress-Bordmittel) — fügt aber vermutlich nur neue TOP-LEVEL-Kategorien hinzu, keine tieferen
   Ebenen (z.B. neue Hersteller unter "Hersteller"). Nicht abschließend getestet.
2. **Dynamische Anzeige statt statischer Menüpunkte** (letztlich umgesetzt): im Max-Mega-Menu-Editor
   (Zahnrad-Symbol am Menüpunkt) ein Feld vom Typ **"Block"** wählen (NICHT "Individuelles HTML" --
   das führt Shortcodes bewusst nicht aus, reiner Klammertext als Ergebnis), darin einen
   **"Shortcode"-Block** einfügen mit:
   ```
   [product_categories parent="87" hierarchical="1" hide_empty="1"]
   ```
   (`87` = echte Kategorie-ID, z.B. von "Hersteller" -- sichtbar beim Hovern über "Bearbeiten" in
   Produkte → Kategorien, `tag_ID=` in der Link-Vorschau). `parent` legt den Startpunkt fest,
   `hierarchical="1"` zeigt darunter ALLE Ebenen verschachtelt (nicht nur die nächste) -- damit
   bleibt die Anzeige automatisch aktuell, ohne jemals manuell nachgepflegt zu werden.

**Bestätigt funktionierend** (Jacky, 2026-08-02): "mit Block funktioniert es" -- Feinschliff bei der
Optik (Spalten/Styling) macht Jacky selbst weiter, kein Blocker mehr.

**Offener Nebenpunkt (nicht abgeschlossen):** Alternative "Produktkategorien"-Widget (natives
WooCommerce-Widget, kein Shortcode) wäre auch eine Option gewesen, hat aber vermutlich keine
"Start bei Kategorie X"-Einschränkung (zeigt dann vermutlich den kompletten Kategoriebaum) --
deshalb nicht weiterverfolgt, Shortcode-Lösung war zielführender.

## Breadcrumbs (Pfadanzeige) in Blocksy aktivieren (2026-08-02)

Jacky wollte eine Pfadansicht wie im alten JTL-Shop ("Startseite / Wolle und Garne / ... / Produktname").
Blocksy hat das als Theme-Feature eingebaut (kein Plugin nötig): Design → Anpassen (Customizer) →
"Breadcrumbs"-Bereich im Seitenmenü. **Wichtig:** allgemeines Aktivieren reicht oft nicht -- Blocksy
hat meist einen SEPARATEN Schalter extra für Shop-/Produktseiten (eigene WooCommerce-Integration),
der zusätzlich angehakt werden muss. Noch nicht rückgemeldet ob es bei Jacky sichtbar wurde.

**Nebenbefund (nicht vertieft):** Wenn ein Artikel in mehreren Kategorien steht, zeigt die Breadcrumb
vermutlich nur EINEN Pfad (den Navigationskontext oder einen Standard-Pfad) -- lässt sich normalerweise
nicht direkt beeinflussen, für später vormerken falls es doch mal störend auffällt.

## Max Mega Menu: zwei Fallstricke bei eigenen Shortcodes in "Block"-Widgets (2026-08-09)

Beim Bau der Hersteller-Liste fürs Menü (siehe [[project_fuenf_abendaufgaben_0809]], Punkt 3) gefunden — gilt für JEDEN künftigen eigenen Shortcode, der über den Block+Shortcode-Weg (siehe Abschnitt oben, "Mega-Menü aktualisiert sich nicht automatisch") ins Menü eingebunden wird, nicht nur für Hersteller:

1. **`<ul>` wird entfernt:** Gibt der Shortcode eine `<ul><li>`-Struktur aus (z.B. `wp_list_categories()`), entfernt Mega Menu beim Rendern in einem "Block"-Widget das umschließende `<ul>` und lässt nackte `<li>`-Elemente stehen. Eigenes CSS, das über `.wrapper ul { ... }` geht, läuft dadurch ins Leere — Selektoren müssen direkt auf `li` zielen.
2. **`float:left` + `width:100%` auf `<li>`:** Mega Menu setzt eigene Layout-Regeln direkt auf jedes `<li>` (vermutlich für seine JS-Höhenberechnung des Flyout-Panels). Diese mit `!important` überschreiben zu wollen hat einmal die GANZE Seite zerlegt (Menüzeile → große leere Lücke → Mobilmenü → erst danach der eigentliche Seiteninhalt) — vermutlich weil Mega Menu für Desktop/Mobil-Umschaltung auf genau diese Werte angewiesen ist.

**Robuste Lösung, die funktioniert hat:** gar keine `<li>`-Elemente ausgeben. Stattdessen reine `<a>`-Tags direkt in einem eigenen `<div>` mit `column-count` fürs Spalten-Layout (`get_terms()` + `get_term_link()` statt `wp_list_categories()`). Mega Menu hat dann keine `<li>`-Elemente, auf die seine Spezialbehandlung greifen könnte — kein `!important`-Kampf nötig, kein Seitenlayout-Bruch.

**How to apply:** Bei jedem künftigen eigenen Menü-Shortcode (z.B. Punkt 4 "Labels" aus [[project_fuenf_abendaufgaben_0809]], falls dafür auch eine Liste ins Menü soll) von Anfang an mit reinen `<a>`-Tags statt `<ul><li>` arbeiten, nicht das `<li>`-Problem neu entdecken.

## Neuer Kandidat: Woostify Pro (2026-08-09, noch nicht recherchiert)

Jacky brachte Woostify Pro als möglichen Live-Shop-Theme-Kandidaten ins Gespräch (Kontext: [[project_fuenf_abendaufgaben_0809]], Punkt 3 Hersteller-als-Marke/Labels). Vanilla JS statt jQuery (potenziell schneller als Blocksy/WoodMart), bietet Whitelabel, Lizenzstufen sollen laut Jacky mehrere/unbegrenzte Seiten abdecken -- das würde die bei WoodMart gefundene Pro-Domain-Lizenzfalle (siehe oben) vermeiden. **Ungeprüft:** aktuelle Preise/Plan-Grenzen, ob Labels/Marken-Feature wirklich so tief eingebaut ist wie erhofft. Nicht von selbst recherchieren -- erst wenn Jacky das Theme-Thema wieder aktiv aufgreift (gleiche Zurückstellungs-Regel wie oben).
