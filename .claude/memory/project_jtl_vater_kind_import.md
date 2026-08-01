---
name: project-jtl-vater-kind-import
description: "JTL Vater+Kind-CSV-Import mit Achsenerkennung — LIVE GETESTET 2026-07-31 (83/87 Väter erfolgreich), Resume-Fähigkeit + Kategorie-Baum-Dropdown nachgezogen"
metadata: 
  node_type: memory
  type: project
  originSessionId: 85efc9a3-c1f8-4d31-89d2-a10e99128244
  modified: 2026-08-01T18:13:48.931Z
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

## ✅ Achsenwerte-Normalisierung FERTIG gebaut + verifiziert (2026-08-01)

Jacky lieferte `DROPS_Varkombis.csv` (1450 Zeilen) + `DROPSMitKindern.csv` + `Bilder/JTL-Wawi-Bildexport-01082026.csv` (1450 Bilder) unter `D:\ERP\mealana\import\`. Enthält gezielt: Garne mit `[Uni]`/`[Mix]`, Garne mit Nummer hinten in Klammern, bereits korrekte Vergleichsfälle (Karisma/Alaska, Vater #172/#178).

**Gebaut in `JtlVaterKindImportService.php`:**
- `normalisiereFarbwert()` (neu): erkennt bei der Achse "Farbe" die Nummer unabhängig von ihrer Position (Klammer am Ende, schon vorne, mit Bindestrich) und baut sie einheitlich als "Nummer Name" neu auf (z.B. "18 braun"). Andere Achsen (Größe etc.) bleiben unangetastet.
- **Wichtige Erkenntnis (von Jacky korrigiert):** `[Uni]`/`[Mix]` sind KEINE Text-Tags im Wert, sondern eigene Achsen (`varianten_achsen` id 8/9, `abhaengig_von_achse_id`=7="Farbe"). Ein erkanntes Tag steuert daher die Achsen-Zuordnung, nicht nur den Text.
- **Generische Tag-Erkennung (Nachbesserung, nachdem Jacky `[Print]`/`[Long Print]` fehlend meldete):** Barbara markiert JEDEN farblichen Sonderfall in eckigen Klammern (bestätigt von Jacky), nicht nur Uni/Mix — daher erkennt der Code jetzt JEDEN Klammer-Inhalt generisch (`\[([^\]]+)\]`), nicht nur eine feste Liste. Bereits bekannte Sub-Achsen (`ladeFarbAchsenTags()`, per `AchsenRepository::findByParentId()`) werden mit ihrem exakten Namen wiederverwendet, unbekannte neue Tags werden 1:1 übernommen. `findeOderErstelleAchse()` setzt jetzt `abhaengig_von_achse_id` automatisch, wenn ein neuer Klammer-Tag zum ersten Mal eine Achse braucht (vorher fehlte diese Verknüpfung komplett bei neu angelegten Tags).
- **Auslauf-Erkennung:** Barbaras "#"-Markierung (Position uneinheitlich: in Nummer, Kind-Artikelnummer oder -Name) wird erkannt, aus allen drei Quellen entfernt, und mapped auf `artikel.ist_auslaufartikel=1` beim Kind. In der Kontrollliste als 🔶-Chip sichtbar.
- **Kind-Name wird neu zusammengesetzt** (Vatername + Achse (falls ≠"Farbe") + Wert) statt den rohen CSV-Namen zu übernehmen — bleibt in der Kontrollliste editierbar.
- `[Print]`-Achse (id 22) manuell vorab angelegt (abhängig von Farbe, gleiches Muster wie Uni/Mix id 8/9) — damit auch die 7 unbeklammerten "print"-Bare-Word-Fälle bei "DROPS Baby Merino" gleich beim ersten Lauf korrekt erkannt werden, nicht erst nachträglich. `[Long Print]`/`[Recycled Denim]` brauchen das nicht (kommen in dieser Datei nur geklammert vor, werden automatisch beim Import mit korrekter `abhaengig_von_achse_id` neu angelegt).

**Verifiziert (Trockenlauf gegen die echte Datei, kein DB-Write):** 38 Väter, 1450 Kinder erkannt, alle 1450 Achsenwerte korrekt verteilt (622 Uni, 475→467 Farbe, 267 Mix, 75 Print, 12 Long Print, 4 Recycled Denim, 3 Größe), 240 Auslauf-Flags korrekt gesetzt. Karisma/Alaska-Vergleich: generierter Name ("Drops Alaska [Uni] 53 rubinrot") matcht exakt die bereits in der DB stehende Konvention.

**Bewusst nicht (weiter) gelöst:** Hersteller-Mismatch CSV="DROPS" vs. DB="DROPS Design" — Jacky will den korrekten DB-Namen "DROPS Design" NICHT wegen des Imports ändern, gleicht das nach dem Import manuell pro Artikel ab (alle 38 Väter sind DROPS-Garne). Bewusste Entscheidung, kein Bug.

## ✅ Nachtrag 2026-08-01: Crash beim echten Import + 3 weitere Nachbesserungen

**Echter Import-Versuch, echter Crash:** "DROPS Baby Merino" (D-1059) existierte schon vorher in der DB (Vergleichsfall) — im "Vater existiert bereits"-Zweig von `fuehreImportDurch()` wurden `$einheitId`/`$herstellerId` nie gesetzt (nur im "neu anlegen"-Zweig), aber weiter unten bei der Kinder-Anreicherung unbedingt gelesen → Fatal Error (FK-Constraint) beim ersten Kind. **Kein Datenverlust:** alle 68 Baby-Merino-Kinder wurden vorher schon korrekt mit Namen/Achsen angelegt (das passiert VOR der abgestürzten Stelle), nur Gewicht/UVP/Einheit/Auslauf-Flag fehlten noch. Fix: bei bereits bestehendem Vater dessen eigene aktuelle `einheit_id`/`hersteller_id` per `findById()` laden statt undefiniert zu lassen.

**Zweiter echter Fund:** PHP `max_input_vars` (Default 1000) reichte bei diesem Batch (1450 Kinder × 2 Formularfelder + Väter) nicht — PHP verwirft überzählige POST-Felder committed OHNE Fehler (nur eine Warnung). Auf 20000 erhöht in `C:\xampp\php\php.ini`. Ich konnte Apache nicht selbst neu starten (Zugriff verweigert, XAMPP läuft wohl mit Admin-Rechten) — Jacky musste manuell über XAMPP Control Panel neu starten.

**Drei Format-Nachbesserungen (auf Jackys Wunsch, direkt im Code statt manuell in der Kontrollliste):**
1. **"DROPS "-Präfix entfernen** (`kuerzeDropsName()`): macht Vater- UND Kind-Namen unnötig lang. AUSNAHME: "DROPS loves You"-Serie behält den Namen (dort ist "DROPS" Teil des Produktnamens). Case-insensitive Erkennung der Ausnahme (Rohdaten schreiben teils "loves You", teils "Loves You").
2. **Dritte Nummer-Position erkannt** (BorgoDePazzi Giza: "Weiß 1", "Creme 2" — Nummer HINTEN ohne jede Klammer): dritter Fallback-Regex `/\s+(\d+)\s*$/` in `normalisiereFarbwert()`, nach Klammer- und Vorne-Fällen.
3. **Farbnamen einheitlich klein geschrieben** (`mb_strtolower($name)`): Rohdaten waren uneinheitlich ("Weiß" vs. "weiß"), nur der Namensteil wird kleingeschrieben, Achsen-Tag ("[Uni]" etc.) bleibt Title-Case.

**Alle drei mit echten Dateien verifiziert** (DROPS_Varkombis.csv 1450 Zeilen + BorgoDePazzi_Varkombis.csv 185 Zeilen), keine Regression: Summen stimmen weiterhin exakt überein, Malformed-Klammer-Tippfehler in Barbaras Daten (`[Mix}`, `{Uni]`) werden über die Bare-Wort-Erkennung trotzdem korrekt aufgefangen.

**Nächster Schritt:** Jacky startet Apache neu, lädt die Dateien erneut hoch (Kontrollliste zeigt jetzt automatisch die korrigierten Namen), startet den Import. Baby Merino wird per Resume erkannt und nur ergänzt, alle 37 restlichen Väter + BorgoDePazzi laufen neu durch. Claude reviewed danach die DB.

## ✅ Import erfolgreich durchgelaufen (2026-08-01) + zwei weitere Funde

Jacky bestätigt: Import hat komplett funktioniert. Danach auf Jackys Bitte ("kontrolliere ob alle Variantenwerte lowercase sind") DB-weite Prüfung gemacht, dabei zwei echte Dinge gefunden:

**1. Giza-Format-Bug (BorgoDePazzi_Varkombis.csv):** dritte, bisher unbehandelte Nummer-Position entdeckt — Nummer am Ende OHNE jede Klammer ("Weiß 1", "Creme 2"). Dritter Fallback-Regex `/\s+(\d+)\s*$/` in `normalisiereFarbwert()` ergänzt (nach Klammer-Fall und Vorne-Fall). Zusätzlich auf Jackys Wunsch Farbnamen einheitlich klein geschrieben (`mb_strtolower($name)`, nur der Namensteil, nicht das Achsen-Tag). Beide mit echten Dateien verifiziert, keine Regression (DROPS 1450 + BorgoDePazzi 185 Zeilen).

**2. Echter Datenintegritäts-Bug beim Re-Import bereits bestehender Väter (Alaska/Karisma/Nord):** Weil die neue Kleinschreibung/Normalisierung den Text bereits vorhandener Achsenwerte änderte, aber `fuehreImportDurch()` beim Speichern IMMER `'id' => 0` für jeden Wert übergibt (nie die echte bestehende ID), erkannte `speichereAchsenUndWerte()` die alten Werte nicht als "schon vorhanden" (Text passt nicht mehr exakt) und legte STATT eines Updates eine zweite, neue Wert-Zeile an — das betroffene Kind hing danach an ZWEI Werten gleichzeitig für dieselbe Achse (z.B. Kind #213 sowohl an "39 Altrosa, dunkel" ALS AUCH an "39 altrosa,dunkel"). 14 Kinder betroffen (9× Karisma, 3× Alaska, 2× Nord). Manche Differenzen waren reine Formatierung, manche echte Umbenennungen von DROPS zwischen altem und neuem Export (z.B. "zitronengelb"→"sonnengelb", "aschbraun"→"hellbraun") — mit Jacky abgestimmt: neuer Name gewinnt immer.

**Fix (bewusst NICHT "alt löschen, neu behalten"):** Alte, bereits synced Werte hatten schon echte WooCommerce-`externe_term_id`s (`varianten_achse_werte_shops`) — um die Verknüpfung nicht zu verlieren, wurde stattdessen die ALTE Zeile in-place auf den neuen Text aktualisiert (+`aktualisiert_am=NOW()`, damit die bestehende Rename-Sync-Logik aus [[project_shop_sync]] den Namenswechsel automatisch zu WooCommerce nachzieht), die Duplikat-Zeile + ihre Kombinationsverknüpfung gelöscht, und der Kind-Name entsprechend nachgezogen. Alles in einer Transaktion, danach verifiziert: keine Kinder mit Mehrfachwerten pro Achse mehr, `aktualisiert_am` > `synced_at` bei allen 12 bereits gesyncten Fällen (Nord war noch nie gesynct, läuft normal neu).

**Nicht behoben (nicht Teil des heutigen Themas):** Ein paar KnitPro-Nadelgrößen-Wertepaare mit Leerzeichen-Varianten ("2.75mm" vs. "2.75 mm") existieren als zwei getrennte, unabhängig verwendete Zeilen (nicht doppelt an ein Kind gehängt, also keine akute Störung) — nicht durch heutige Arbeit verursacht, out of scope gelassen.

**Bewusst NICHT angefasst:** Achse 15/18/19/20 (Stärke/Art/Version/Variante — KnitPro-Zubehör wie Griffe/Knöpfe/Nadelspitzen) behalten ihre Groß-/Kleinschreibung wie bisher — Jacky bestätigte, dass Großschreibung dort (z.B. "Griffe Kunstleder") absichtlich und richtig ist, die Kleinschreibungs-Regel gilt nur für die Farbe-Konvention.

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
