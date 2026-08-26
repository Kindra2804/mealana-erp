---
name: bug-ean-excel-notation-repariert
description: "BEHOBEN 2026-08-26: 1202 artikel_codes-Zeilen hatten woertlich '7,07172E+12' als EAN (Excel-Unfall im Quell-Import), verfaelschte 'doppelte EAN'-Filter massiv (3170 Vaeter + 120s-Timeout) -- Daten repariert (100% rekonstruiert aus anderen Importdateien) + Query-Performance-Bug in ArtikelRepository behoben (0,02s statt Timeout) + doppelte EAN-Werte werden jetzt in der Artikelliste rot hervorgehoben"
metadata:
  node_type: memory
  type: project
  originSessionId: 1fe37890-2d54-4c96-bcae-65d709049cbe
  modified: 2026-08-26T14:17:28.809Z
---

Jacky filterte die Artikelliste auf Status "doppelte EAN" und wunderte sich: 3170 Väter (+ Kinder) wurden angezeigt, obwohl manche davon selbst gar keine EAN haben.

**Root Cause (kein Code-Bug, echtes Datenproblem):** 1202 `artikel_codes`-Zeilen (typ=GTIN13, überwiegend Kind-Artikel von DROPS "Baby Merino"-Farbvarianten) hatten wortwörtlich den String `"7,07172E+12"` als Code stehen -- ein klassischer Excel-Wissenschaftsnotation-Unfall. Die Duplikat-Erkennung (`ArtikelRepository::findAll()`/`countAll()`, `doppelte_ean`-Zweig) arbeitet korrekt und meldet zu Recht "1202-fach doppelt" für diesen einen (falschen) Wert -- inkl. 54 Vätern, die selbst keine eigene EAN haben, aber ein Kind mit diesem kaputten Wert besitzen (genau Jackys beobachtetes Symptom).

**Ursache im Quell-Import gefunden:** `import/erledigt/DROPSMitKindern.csv` (die tatsächlich importierte Datei) enthält die kaputte Notation bereits selbst -- der Fehler ist NICHT beim ERP-Import entstanden, sondern schon vorher (vermutlich beim Erstellen/Bearbeiten dieser CSV in Excel).

**Rekonstruktion + Reparatur (2026-08-26):** Andere, unabhängig davon erstellte Importdateien im selben Archiv (`100schur-Artikelstammdaten-05082026.csv`, `merinomania-Artikelstammdaten-02082026.csv`, u.a.) enthalten für dieselben Artikelnummern die korrekte, unbeschädigte 13-stellige EAN (z.B. D-105901 -> `7071723002650`). Automatisiert über alle `import/erledigt/*.csv`-Dateien gesucht: **alle 1141 betroffenen Artikelnummern gefunden, jede mit über mehrere Dateien hinweg exakt übereinstimmendem (kein einziger Widerspruch) EAN-Wert** -- hohe Konfidenz, keine Ratewerte. Per Skript repariert (1202 Zeilen aktualisiert, 0 übrig mit dem kaputten Wert). Übrige echte Duplikate danach realistisch klein (2-4 Vorkommen je Wert, plausible echte Datenpflege-Fälle, nicht weiter untersucht).

**Kein Shop-Sync-Nachzug nötig:** EAN/GTIN wird aktuell gar nicht an WooCommerce/Germanized übertragen (`ShopSyncService` hat keinen GTIN13-Payload-Zweig) -- reine ERP-interne Korrektur.

**Nebenbefund:** Jacky bemerkte, der "doppelte EAN"-Filter selbst braucht spürbar lange. Die SQL-Struktur (`EXISTS` mit verschachteltem `IN`-Subquery + korrelierter `COUNT`-Subquery pro Zeile, siehe `ArtikelRepository::findAll()`/`countAll()`, `doppelte_ean`-Zweig) ist grundsätzlich pro Zeile teuer -- nicht behoben, nur festgehalten. Bei Bedarf separat als Performance-Thema angehen (ähnlich [[bug_kategorienbaum_performance]]), aber nicht spekulativ ohne Jackys Priorisierung.

## ✅ NACHGEZOGEN 2026-08-26: Performance-Fix + EAN-Hervorhebung in der Artikelliste

Der "doppelte EAN"-Filter selbst warf danach einen echten **Fatal Error ("Maximum execution time of 120 seconds exceeded")** in `ArtikelRepository.php` -- kein Datenproblem mehr, sondern die Query-Struktur selbst: die alte `doppelte_ean`-Bedingung war eine pro Zeile korrelierte `EXISTS(...COUNT(*)...)`-Subquery, die nach der Vater/Kind/Lagerbestand-JOIN-Kreuzung (vor dem `GROUP BY`) für JEDE Zeile einzeln lief -- Kosten unabhängig davon, wie viele echte Treffer am Ende rauskommen. **Fix:** neue `ArtikelRepository::holeVaterIdsMitDoppelterEan()` berechnet die betroffenen Vater-IDs EINMALIG über eine kleine Aggregation (`GROUP BY code HAVING COUNT(*)>1`), die Hauptabfrage prüft dann nur noch simpel `a.id IN (...)`. Zwei Fundorte in `ArtikelRepository.php` (`findAll()` UND `countAll()`) -- der erste Fix-Versuch traf nur `findAll()`, weil beide Codestellen einen leicht unterschiedlichen Kommentar hatten und die Textersetzung dadurch nur einmal griff; beim zweiten Anlauf beide erwischt.

**Nebenfund während des Debuggens:** Mehrere Zombie-MySQL-Queries liefen im Hintergrund weiter (eine schon 11+ Minuten), obwohl der PHP-Request längst mit Fatal Error abgebrochen war -- der MySQL-Client-Disconnect killt eine laufende Query nicht zuverlässig sofort. Per `KILL QUERY` manuell beendet, kein struktureller Fix nötig (reines Debugging-Artefakt dieser Session).

**Ergebnis:** Filter lief vorher auf ~3170 (praktisch der komplette Katalog) mit Timeout, jetzt 32 echte Treffer in 0,02s.

**Komfort-Wunsch (Jacky, gleicher Tag):** Beim Aufklappen einer Vater/Kind-Familie mit vielen EANs war das manuelle Vergleichen mühsam. Neue `ArtikelRepository::findDoppelteEanCodes()` liefert die tatsächlichen doppelten EAN-**Werte** (nicht nur Artikel-IDs), `artikel/liste.php` hat eine neue `istDoppelteEanWert()`-Helper-Funktion (lazy + statisch gecacht, lädt nur wenn die EAN-Spalte aktiv gerendert wird) und hebt betroffene EAN-Zellen jetzt rot+fett mit ⚠-Symbol hervor (Vater- UND Kind-Zeilen).

**Getestet:** `php -l`, Repository-Methoden read-only gegen echte Daten (84 doppelte EAN-Werte, alte kaputte Excel-Notation korrekt nicht mehr dabei). **Kein Browser-Test** dieser Session (kein Login-Zugriff) -- Jacky bestätigt den Performance-Fix + sollte die neue Hervorhebung einmal ansehen.

**How to apply:** Bei künftigen JTL/Excel-Importen auf Wissenschaftsnotation bei rein numerischen Feldern (EAN, Artikelnummern) achten -- betrifft potenziell auch andere Felder, nicht nur EAN. Kein systematischer Import-Validierungs-Check dafür gebaut, war ein einmaliger Repair.
