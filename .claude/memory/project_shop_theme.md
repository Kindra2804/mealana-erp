---
name: project-shop-theme
description: "WooCommerce-Theme/UX-Anpassung fuer den Shop-Look: bewusst zurueckgestellt bis der technische Sync-Teil komplett fertig ist"
metadata: 
  node_type: memory
  type: project
  originSessionId: bcf52b92-a756-4c54-8a41-faaebdece89e
  modified: 2026-07-20T18:19:12.662Z
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
