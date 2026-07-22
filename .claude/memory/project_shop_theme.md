---
name: project-shop-theme
description: "WooCommerce-Theme/UX-Anpassung: Gratis-Basis (Blocksy+Elementor+Max Mega Menu+Germanized) 2026-07-22 fertig gebaut als Barbara-Testbasis; WoodMart/Blocksy-Pro-Kaufentscheidung weiterhin pausiert (Budget-Gespräch mit Barbara)"
metadata: 
  node_type: memory
  type: project
  originSessionId: bcf52b92-a756-4c54-8a41-faaebdece89e
  modified: 2026-07-22T15:11:42.945Z
---

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
