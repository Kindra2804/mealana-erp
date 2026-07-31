---
name: project-jtl-vater-kind-import
description: "JTL Vater+Kind-CSV-Import mit Achsenerkennung — LIVE GETESTET 2026-07-31 (83/87 Väter erfolgreich), Resume-Fähigkeit + Kategorie-Baum-Dropdown nachgezogen"
metadata: 
  node_type: memory
  type: project
  originSessionId: 85efc9a3-c1f8-4d31-89d2-a10e99128244
  modified: 2026-07-31T20:07:58.295Z
---

## Status 2026-07-31: Implementiert + verifiziert

Gebaut: `src/modules/import/JtlVaterKindImportService.php` (Parsing/Vorschau/Commit), `public/artikel/jtl_import.php` (Formular + Kontrollliste), `public/artikel/jtl_import_commit.php` (Ergebnisseite), Link in `artikel/liste.php`.

**Getestet** mit synthetischen ZZTEST-Daten (2 Achsen Stärke+Farbe, 3 Kinder, abweichender Kind-Preis, unbekannte Verkaufseinheit) direkt gegen die echte DB (PHP-CLI, danach vollständig aufgeräumt — kein Rest in der DB). Bestätigt: Preis-Override pro Kind funktioniert, Mehrachsen-Fall funktioniert, bestehende Achse "Farbe" wird wiederverwendet statt dupliziert, Kategorie propagiert korrekt auf Vater+Kinder, Fehlerpfad (fehlendes Einheiten-Mapping) bricht sauber ab ohne Datenreste.

**Zwei echte Bugs beim Testen gefunden+behoben:**
1. `ArtikelRepository::insert()` braucht `inhalt_einheit` + `herkunftsland` explizit im Array (auch als `null`) — sonst PDO "Invalid parameter number" (Keys fehlten komplett statt nur leer zu sein).
2. `VariantenService::erstelleKombinationen()` setzt `hersteller_id` bei Kindern NICHT (auch im bestehenden manuellen VarKombi-Generator-Flow nicht — vorbestehende Lücke, nicht von diesem Import verursacht). Für den JTL-Import im Nachbearbeitungs-Update ergänzt, da Hersteller-Filter in der Artikel-Liste sonst bei Kindern leer bliebe.

**Trockenlauf mit den echten Dateien (2026-07-31, nur Parsen+Vorschau, kein DB-Write):** 87 Väter, 2.146 Kinder korrekt erkannt, alle Hersteller-Treffer, alle Achsen erkannt, keine fehlenden Stammdatenzeilen. Einheiten-Mapping nötig für: `Paar`, `Stk.`, `Spiel`, `Pkg.`, `(leer)`.

**Dabei noch zwei weitere Bugs gefunden+behoben (eigene Fehler, nicht bestehender Code):**
3. Leere Verkaufseinheit-Werte wurden in `baueVorschau()` fälschlich NICHT als "unbekannt" markiert (nur nicht-leere ungemappte Werte) — hätte bei ca. 100 betroffenen Zeilen (z.B. "Royale Jackennadeln"-Familie) beim Commit zu einem unsichtbaren Fehlschlag geführt, da Vater-Einheit kein Fallback hat. Fix: leere Werte jetzt auch in der Kontrollliste als "(leer)" zum Mappen angezeigt.
4. HTML-Formular-Array-Key `einheit_mapping[]` bei leerem Rohwert wäre von PHP als numerischer Index statt als leerer String interpretiert worden (Mapping für "(leer)" wäre beim Zurücklesen verlorengegangen). Fix: Keys werden im Formular base64-kodiert, in `jtl_import_commit.php` wieder decodiert.

**Nebenbefund (nicht Teil dieses Imports, separat vorgemerkt):** `artikel.herkunftsland` ist bei 371/525 Live-Artikeln leer, bei den restlichen 154 (nur DROPS Design) hart "NO" aus dem alten Demo-Import-Skript — kein laufender Service befüllt das. Siehe [[project_roadmap_reihenfolge]] "Kleinere Punkte".

## Live-Test durch Jacky (2026-07-31, Browser, echte Dateien)

**Ergebnis:** 83 von 87 KnitPro-Vätern erfolgreich importiert. 4 Väter (u.a. `KP-SES`) initial abgebrochen wegen leerer Verkaufseinheit, die im Einheiten-Mapping nicht zugeordnet wurde — Vater bricht bewusst VOR jedem DB-Write ab (kein Halb-Datensatz), das ist beabsichtigtes Verhalten.

**Zwei echte Funde beim Live-Test, beide behoben:**
1. **Kategorie-Dropdown zeigte keine Hierarchie** — nutzte `getAlleKategorien()` (flache Liste), dadurch bei gleichnamigen Unterkategorien keine Auswahl möglich. Fix: `getKategorienBaum()` mit Einrückung (`renderKategorieOptionen()` in `jtl_import.php`), z.B. "— — Rundnadeln" unter "Nadeln → Nadelart".
2. **Kein Resume bei abgebrochenen Vätern** — ein Re-Lauf mit denselben Dateien hätte bereits erfolgreiche Väter als "Artikelnummer existiert bereits"-Fehler angezeigt (harmlos, aber verwirrend) und nicht geprüft ob bei ihnen Kinder fehlen. Fix in `JtlVaterKindImportService::fuehreImportDurch()`: prüft jetzt zuerst `findByArtikelnummer()` — existiert der Vater schon, wird er übernommen (keine Neuanlage) und Kategorie/Achsen/Kinder trotzdem durchlaufen (alles idempotent) statt als Fehler zu erscheinen. Per Testlauf (Anlage → identischer Re-Lauf) verifiziert: keine Duplikate, `erfolg=true` beim zweiten Durchlauf.

**Wichtige Lehre beim Testen (siehe [[feedback_test_isolation]] Nachtrag):** Globale Stammdaten wie Achsen werden NICHT pro Testlauf isoliert — ein synthetischer Test-Achsen-Code ("staerke") wurde nach Jackys echtem Import automatisch wiederverwendet (`findByCode()`). Ein routinemäßiges `DELETE FROM varianten_achsen WHERE code=...` im Cleanup-Skript hätte fast eine von 83 echten Vater-Artikeln genutzte Achse gelöscht — nur eine FK-Constraint-Sperre hat das verhindert. Nie wieder pauschal nach Code/Name auf globalen Stammdaten löschen, immer erst per LEFT JOIN auf Verwaisung prüfen.

**Achsenwerte-Format-Frage vertagt (2026-07-31):** Jacky hat für Garne (Mix/Uni/Farbe) ein etabliertes Format "Nummer Name" (z.B. "34 kräftig rosa"), Mix/Uni wird per Schlüsselwort im JTL-Rohtext erkannt. Braucht eine echte Garn-Export-Datei um die genaue Transformationsregel abzuleiten — bewusst NICHT blind in den generischen Import eingebaut (würde eine Garn-spezifische Regel in ein kategorie-übergreifendes Tool verdrahten). **Bei Wiedereinstieg:** Jacky bringt "morgen" (Bezug: 2026-08-01) den ersten Garn-Export mit, dann gemeinsam entscheiden zwischen (a) automatischem Korrektur-Skript nach dem Import oder (b) editierbaren Achsenwert-Feldern in der Kontrollliste.

Code committed 2026-07-31 zusammen mit [[project_jtl_bilder_import]] und dem Shop-Sync-Fix ([[bug_shop_sync_term_pagination]]).

## Ausgangslage (2026-07-31)

Jacky hat drei neue Testdaten-Exporte unter `D:\ERP\mealana\import\` abgelegt:
- `JTL-Export-TestdatenERP_31072026.csv`
- `JTL-Export-TestdatenERP_mitKindern31072026.csv` (2.448 Zeilen: Vater+Kind-Stammdaten, Flag `Ist Vaterartikel`, Windows-1252-kodiert)
- `JTL-Export-TestdatenERP_mitKindernVariationskombinationen31072026.csv` (2.146 Zeilen: Vater↔Kind-Zuordnung MIT strukturierten Achsen als Name/Wert-Paare `Variationsname 1-6`/`Variationswertname 1-6`, 87 Väter, teils 2 Achsen gleichzeitig)

Frage war: kann daraus automatisch ein Vater+Kind-Import mit Achsenerkennung gebaut werden, oder muss es Handarbeit bleiben? **Antwort: automatisierbar** — die Achsen liegen bereits strukturiert vor, kein Freitext-Parsing nötig. Bestehender Code (`VariantenService::erstelleKombinationen()`, `speichereAchsenUndWerte()`) ist direkt wiederverwendbar.

**Voller Plan liegt in `C:\Users\indy1\.claude\plans\sequential-hatching-papert.md`** (bestätigt, Bau noch nicht begonnen). Kurzfassung:

- Väter werden neu angelegt (nicht nur gematcht gegen Handarbeit-Väter).
- Batch-weite Vorab-Auswahl per Dropdown (gilt für alle Väter+Kinder des Imports): Kategorie, Artikeltyp (`artikel_typen`, fehlt in CSV), Artikelgruppe (`artikel_gruppen`, Buchhaltungs-Kontenzuordnung, fehlt ebenfalls in CSV).
- Vor dem echten DB-Import: editierbare Kontrollliste (Artikelnummern/Kindernamen korrigierbar), erst nach Bestätigung läuft der Import — Absicherungs-Zwischenschritt (Session-basiert).
- Hersteller-Matching NUR gegen bestehende `hersteller`-Einträge, kein Auto-Anlegen (Hersteller tragen Pflegedaten wie GPSR-Angaben, die ein blind erzeugter Datensatz nicht hätte) — unbekannte Hersteller werden in der Kontrollliste sichtbar markiert.
- Einheiten-Mapping: `Verkaufseinheit`-Werte aus CSV (`Paar`, `Stk.`, `Pkg.`, `Spiel` u.a.) matchen nicht 1:1 gegen `einheiten.kuerzel` → User mappt distinct-Werte einmalig auf bestehende Einheiten, kein Auto-Anlegen (Repository unterstützt das nicht).
- Preise/Maße/UVP/EAN werden NICHT über die Achsen-Aufpreis-Mechanik vererbt, sondern direkt aus der jeweiligen Kind-CSV-Zeile übernommen — JTL liefert bereits die exakten Endwerte pro Kind (z.B. `KP-20407` hat abweichenden Preis ggü. Geschwistern).
- Neuer Service `src/modules/import/JtlVaterKindImportService.php`, UI `public/artikel/jtl_import.php` + `jtl_import_commit.php` (Vorbild: `achsen_zuweisen.php`/`achsen_speichern.php` und `public/artikel/import.php` fürs Encoding-Handling).

**Bewusst nicht Teil dieses Imports:** HTML-Bereinigung der Beschreibungstexte (eigenes zurückgestelltes Thema, siehe [[project_jtl_import]]), Merkmale-Import (eigenes Thema, Dedup nötig).

**Bei Wiedereinstieg:** Plan-Datei lesen, dann mit Schritt 0 (falls noch nicht erledigt) bzw. direkt mit der Implementierung (Service + UI) weitermachen.
