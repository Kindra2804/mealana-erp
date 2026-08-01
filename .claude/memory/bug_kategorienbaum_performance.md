---
name: bug-kategorienbaum-performance
description: "getKategorienBaum() brauchte 9,66s (bei 2661 Artikeln) — BEHOBEN 2026-08-01, jetzt 0,015s (~640x)"
metadata:
  node_type: memory
  type: project
  originSessionId: 85efc9a3-c1f8-4d31-89d2-a10e99128244
  modified: 2026-08-01T08:53:33.853Z
---

## ✅ BEHOBEN 2026-08-01

**Präzise Root Cause (per EXPLAIN gefunden, genauer als die ursprüngliche Vermutung):** `KategorieRepository::findAllMitEltern()` jointe `kategorien → artikel_kategorien → artikel(Vater) → artikel(Kind)` in einem Statement. Die JOIN-Bedingung auf die zweite `artikel`-Kopie (`a`) enthielt ein OR über zwei verschiedene Spalten: `(a.id=vater.id AND vater.ist_vater=0) OR (a.vaterartikel_id=vater.id)`. EXPLAIN zeigte dafür `type: ALL, key: NULL, Extra: Range checked for each record` — die OR-Bedingung verhinderte JEDE Index-Nutzung. MySQL scannte dadurch bei JEDER der ~224 Kategorie-Zuweisungen die komplette Artikeltabelle (~2327 Zeilen) neu = ~521.000 geprüfte Zeilen. Das allein erklärt die 9,66s vollständig (bestätigt per `SET profiling=1`).

**Fix:** `artikel_anzahl` wird jetzt direkt über die eigene `artikel_kategorien`-Zeile des Artikels gezählt (`a.ist_vater=0`, kein Vater-Umweg mehr). Verifiziert per Query, dass **jedes** Kind durch `syncKategorienZuKindern()` ohnehin immer eine eigene direkte Zuweisungs-Zeile bekommt (0 Kinder ohne eigene Zeile gefunden) — der Vater-JOIN war für die Zählung komplett redundant. Alle vier Werte (`artikel_anzahl`, `aktion_aktiv`, `aktion_zukunft`, `aktion_info`) plus der bereits vorher als Subquery vorhandene `eigene_shop_codes`-Wert laufen jetzt als unabhängige korrelierte Subqueries statt eines gemeinsamen JOINs mit GROUP BY — jede nutzt den bestehenden Index auf `kategorie_id`.

**Wichtige Zwischenfalle beim Umbau:** Ein erster Versuch (zwei unabhängige `COUNT(DISTINCT)`-Subqueries summiert, eine für Standalone-Artikel + eine für über-Vater-erreichte Kinder) lieferte fast doppelte Zahlen — weil Kinder durch die Sync-Propagierung IMMER beide Pfade gleichzeitig erfüllen (eigene Zeile UND Vater hat auch eine Zeile), was zu Doppelzählung führte. Erst die empirische Prüfung (0 Kinder ohne eigene Zeile) zeigte, dass der Vater-Pfad komplett weggelassen werden kann.

**Verifiziert:**
- Ergebnis byte-identisch zur alten Query (Diff über alle 194 aktiven Kategorien, sortiert nach `k.id`)
- Laufzeit: 9,66s → 0,015s auf reiner SQL-Ebene (`SET profiling=1`), End-to-End über `ArtikelService::getKategorienBaum()` (PHP-CLI): 0,0085s
- Alle 12 Aufrufer (`artikel/liste.php`, `kategorien_verwalten.php`, `jtl_import.php`, `aktionen/*`, `achsen/*`, etc.) brauchen keine Anpassung, da Spaltenstruktur/Reihenfolge unverändert

**Nicht angefasst:** Ob bei 25.000 Artikeln (Jackys Zielgröße) noch weitere Engpässe auftreten — die jetzige Lösung skaliert linear mit Anzahl Kategorien × durchschnittlicher Artikel-pro-Kategorie (kein Cross-Join-Fan-Out mehr), sollte aber bei Bedarf erneut profiliert werden. Noch nicht von Jacky im Browser bestätigt (nur CLI/SQL-seitig verifiziert).
