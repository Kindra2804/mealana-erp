---
name: bug-kategorienbaum-performance
description: "getKategorienBaum() braucht 9,5s (bei 2661 Artikeln) — identifizierter Engpass, noch nicht behoben, betrifft Artikel-Liste UND JTL-Import-Dropdown"
metadata: 
  node_type: memory
  type: project
  originSessionId: 85efc9a3-c1f8-4d31-89d2-a10e99128244
  modified: 2026-07-31T20:15:39.503Z
---

## Befund (2026-07-31, Ende der JTL-Import-Session)

Jacky meldete: Artikelliste braucht bei ~2500 Artikeln ca. 10 Sekunden zum Laden, Sorge wegen Skalierung auf geplante ~25.000 Artikel.

**Per PHP-CLI-Profiling präzise lokalisiert** (`ArtikelController::index()`, `count()`, `getPreisStatusFuerListe()`, `getKinderFuerListe()`, `getZustandsArtikelFuerListe()`, `findHauptbilderByArtikelIds()`, `getKategorienBaum()` einzeln getimt, bei pro_seite=12 und pro_seite=100):

- Alle Artikelliste-eigenen Queries zusammen: **< 100ms** (Haupt-Query 15-20ms, selbst mit 1509 Kind-Zeilen bei 100 Hauptartikeln).
- **`ArtikelService::getKategorienBaum()` → `KategorieRepository::findAllMitEltern()`: 9,5 Sekunden.** Unabhängig von pro_seite (läuft ja nur einmal pro Seitenaufruf) — das ist praktisch die gesamte Ladezeit.

**Root Cause:** `findAllMitEltern()` (`src/modules/artikel/KategorieRepository.php:47ff`) joint `kategorien → artikel_kategorien → artikel (Vater) → artikel (Kind, via a.vaterartikel_id = vater.id)` in einem einzigen JOIN, um `COUNT(DISTINCT a.id)` pro Kategorie zu berechnen (Vater ODER dessen Kinder zählen als "in der Kategorie"). Bei Artikeln mit vielen Varianten (z.B. manche KnitPro-Nadeln mit 50+ Kindern) multipliziert sich das JOIN-Ergebnis vor dem `GROUP BY k.id` massiv. Zusätzlich zwei korrelierte Subqueries/GROUP_CONCATs pro Kategorie-Zeile (Aktion-Status, Shop-Kanal-Chips).

**Skaliert mit der GESAMTEN Artikelmenge in der DB, nicht mit der angezeigten Seite** — bei 25.000 Artikeln (Jackys Zielgröße) wird das voraussichtlich deutlich schlimmer, nicht nur linear. Kein Naturgesetz, klar isolierter und behebbarer Engpass — nicht "damit leben müssen".

**Relevant auch für heute gebauten Code:** `public/artikel/jtl_import.php` nutzt seit dem heutigen Kategorie-Hierarchie-Fix ebenfalls `getKategorienBaum()` für den Kategorie-Dropdown — hat also denselben ~9,5s-Ladeverzug geerbt.

## Noch nicht behoben (bewusst — späte Uhrzeit, Query ist heikel)

`findAllMitEltern()` wird auch von `kategorien_verwalten.php` und vermutlich weiteren Stellen genutzt (Aktions-Status, Shop-Kanal-Chips-Anzeige) — ein Umbau braucht sorgfältiges Testen über mehrere Seiten hinweg, nicht spät abends im Vorbeigehen.

**Ansatzpunkt für den Fix (nächste Session):** `artikel_anzahl` (und ggf. `eigene_shop_codes`) statt über den JOIN-Fan-out per korrelierter Scalar-Subquery pro Kategorie berechnen (`SELECT COUNT(DISTINCT ...) FROM ... WHERE ak.kategorie_id = k.id`) statt eines einzigen großen JOINs über die komplette Tabelle — vermeidet die kombinatorische Explosion vor dem GROUP BY. Vor dem Umbau: alle Aufrufer von `getKategorienBaum()`/`findAllMitEltern()` auflisten (u.a. `artikel/liste.php`, `artikel/kategorien_verwalten.php`, `artikel/jtl_import.php`, evtl. `aktionen/*`) und nach dem Fix durchtesten, dass Aktion-Status und Shop-Chips weiterhin korrekt sind.

**How to apply:** Bei Wiedereinstieg diese Query zuerst profilen/EXPLAINen bevor Änderungen gemacht werden (Zahlen hier sind Stand 2026-07-31, 2661 Artikel in der DB) — dann gezielt umbauen und mit echten Seitenaufrufen (nicht nur CLI-Timing) verifizieren.
