---
name: project-jtl-vater-kind-import
description: "JTL Vater+Kind-CSV-Import mit Achsenerkennung — LIVE GETESTET 2026-07-31 (83/87 Väter erfolgreich); 2026-08-02 um 'gemischte Kategorien' erweitert; 🔴 2026-08-03/04 KOMPLETTE Grundpreis-Aufräumaktion bei Bestandsartikeln (Einheit-Bug, Kind-Vererbung, Hersteller-Lücken) + Eingabehilfe/Secure-Line für künftige Artikel gebaut — FERTIG, von Jacky abgenommen"
metadata: 
  node_type: memory
  type: project
  originSessionId: 85efc9a3-c1f8-4d31-89d2-a10e99128244
  modified: 2026-08-04T07:06:51.347Z
---

## ✅ ABSCHLUSS 2026-08-04: Grundpreis-Aufräumaktion bei Bestandsartikeln von Jacky abgenommen

Nach den beiden Datenlücken-Fixes (Einheit + Kind-Vererbung, siehe unten) und der neuen Eingabehilfe/Secure-Line (siehe [[project_shop_sync]] bzw. `ArtikelService::wendeGrundpreisDefaultAn()`) blieben bei einer finalen Kontrolle 52 von ursprünglich 142 Garn-Artikeln ohne funktionierenden Grundpreis übrig. **Von Jacky geprüft und bestätigt: alles legitime Ausnahmen** (Strickpakete/Sets/Anleitungen mit "Menge=1" statt echtem Gewicht, dazu ein paar Einzelstücke) -- kein weiterer Handlungsbedarf, keine Bugs mehr offen.

**Auffälliger Nebenfund bei der finalen Kontrolle:** 4 Artikel (`V-BW-multi`, `V-Merino 36/2`, `V-Merino GOTS 28/2`, `Venne`) hatten `grundpreis_anzeigen=1` (Häkchen "an", sieht auf den ersten Blick erledigt aus) aber `inhalt_menge` komplett leer -- Grundpreis wäre trotz aktiviertem Flag nie berechnet worden. Jacky wollte hier nichts gesondert vorziehen, fällt unter dieselbe "Strickpakete/Einzelstücke passt so"-Freigabe.

**Im selben Aufwasch: Hersteller-Lücken bei Bestandsartikeln behoben** (Jackys Beobachtung: "alle Lang Yarn Garne haben keinen Hersteller" -- stimmte nur teilweise, 88 von 129 `LY-*` waren schon korrekt). Präfix-Mapping von Jacky bestätigt: `LY-`=Lang Yarns(3), `PL-`=ProLana(54), `MEA-`=MEALANA(45), `ST-`=Stenli (noch nicht als Hersteller angelegt), `OP-`=Opal (noch nicht angelegt). Bulk-Update mit Kaskade auf bereits existierende Kinder (Kind erbt `hersteller_id` normalerweise nur bei Neuanlage vom Vater, ein nachträgliches Vater-Update über rohes SQL löst `propagiereZuKindern()` nicht aus -- daher zusätzlicher JOIN-Kaskaden-Schritt): **63 Väter/Standalone + 625 Kinder korrigiert** (38+590 LY, 1+11 PL, 24+24 MEA). Sonderfall gegengeprüft: 3 `LY-1000/1004/1014a`-Artikel ("FIRE"/"EARTH#"/"SUNSHINE") hatten schon `hersteller_id`, aber auf "WOOLADDICTS" statt "Lang Yarns" -- von Jacky bestätigt: WOOLADDICTS ist eine echte Lang-Yarns-Submarke, kein Fehler, unangetastet gelassen. Opal und Stenli müssen als Hersteller erst angelegt werden, bevor die verbleibenden 12 (`OP-*`) + 1 (`ST-CJ`) Artikel zugeordnet werden können -- wartet auf Jacky.

## 🔴 BEHOBEN 2026-08-04: 8.852 Kind-Artikel (Farbvarianten) hatten NIE Grundpreis-Felder vom Vater geerbt

## 🔴 BEHOBEN 2026-08-04: 8.852 Kind-Artikel (Farbvarianten) hatten NIE Grundpreis-Felder vom Vater geerbt

**Auslöser:** Nachdem der `inhalt_einheit`-Fix + Backfill vom Vortag lief, meldete Jacky beim laufenden `komplettabgleich.php`: auch neu im Shop erscheinende Artikel zeigen weiterhin keine Grundpreisangabe. Erste Vermutung (neue DB-Zeilen ohne den Fix) widerlegt -- `MAX(erstellt_am)` in der ganzen `artikel`-Tabelle ist 2026-08-02, es kamen während der Session gar keine neuen Datensätze dazu. "Neu" bezog sich auf neu im SHOP erscheinende (= neu vom Komplettabgleich erreichte), nicht neu in der DB erstellte Artikel.

**Root Cause (anderer Fehler als der `inhalt_einheit`-Bug vom Vortag):** Stichprobe der zuletzt synchten Kind-Artikel (`DB-1710-963`) zeigte: der VATER (`DB-1710`) hat korrekt `grundpreis_anzeigen=1, inhalt_menge=100, grundpreis_bezugsmenge=100, inhalt_einheit='g'` -- das KIND selbst hatte `grundpreis_anzeigen=0, inhalt_menge=NULL, grundpreis_bezugsmenge=NULL`. `ShopSyncService::baueGrundpreisFelder()` liest diese Felder aber von der Kind-Zeile selbst (`baueVariationPayload()` ruft es mit `$kind['artikel_id']` auf, nicht mit der Vater-ID) -- ein Kind ohne eigene Werte zeigt also nie einen Grundpreis, egal wie korrekt der Vater ist.

**Code-Pfad geprüft, aktuell korrekt:** `VariantenService::erstelleKombinationen()` (gemeinsam genutzt von `detail.php`s VarKombi-Generator UND dem JTL-Import) vererbt `grundpreis_bezugsmenge`/`grundpreis_anzeigen`/`inhalt_menge`/`inhalt_einheit` bereits korrekt vom übergebenen `$vater`-Array an jedes neue Kind (Zeilen 176-186), und `ArtikelRepository::insert()` hat beide Felder sowohl im INSERT-Statement als auch in der `erlaubteKeys`-Allowlist. Auch `ArtikelService::saveKind()` (der manuelle Einzel-Kind-Pfad) macht es richtig. **Kein aktiver Code-Bug gefunden** -- da seit 2026-08-02 keine neuen Artikel angelegt wurden, konnte das nicht frisch gegengetestet werden, aber die Code-Lese-Analyse spricht klar dafür, dass NEU erstellte Kinder ab jetzt korrekt vererbt bekommen.

**Wahrscheinliche Erklärung (wie beim `inhalt_einheit`-Fund):** Diese Bestandsartikel wurden vermutlich angelegt, bevor die Grundpreis-Spalten/-Vererbung im Code existierten, und nie nachträglich befüllt -- reine Altlast, keine aktuell aktive Lücke.

**Backfill (direkte SQL-UPDATE, kein Extra-Skript nötig -- reines 1:1-Copy-Join):** Vorher geprüft, dass unter den 8.852 betroffenen Kindern **keine einzige** einen abweichenden (nicht nur fehlenden) `inhalt_menge`-Wert vom Vater hatte -- alle waren komplett leer, kein Risiko echte abweichende Daten zu überschreiben. `UPDATE artikel k JOIN artikel v ON v.id=k.vaterartikel_id SET k.grundpreis_anzeigen=v.grundpreis_anzeigen, k.grundpreis_bezugsmenge=v.grundpreis_bezugsmenge, k.inhalt_menge=v.inhalt_menge, k.inhalt_einheit=v.inhalt_einheit, k.aktualisiert_am=NOW() WHERE v.grundpreis_anzeigen=1 AND (k.grundpreis_anzeigen=0 OR NULL) AND k.inhalt_menge IS NULL AND k.grundpreis_bezugsmenge IS NULL`. **8.852 Kinder aktualisiert, 0 Lücken danach übrig** (verifiziert). `aktualisiert_am` gebumpt -- der laufende `komplettabgleich.php` zieht die Korrektur automatisch nach, sobald er zu diesen Artikeln kommt.

**Zwei getrennte, inzwischen behobene Datenlücken bei Grundpreis, zur Übersicht:**
1. `inhalt_einheit` bei Vätern/Standalone-Artikeln (Vortag, 9.316 Artikel, Ursache: Import-Skript las die CSV-Spalte nie)
2. Grundpreis-Vererbung Vater→Kind (heute, 8.852 Kinder, Ursache: vermutlich reine Altlast vor Einführung der Grundpreis-Spalten)

**Noch offen:** Kein frischer Testimport seit heute, der bestätigt, dass NEU angelegte Kinder das Problem nicht erneut zeigen -- Code-Analyse spricht dafür, aber unverifiziert.

## 🔴 BEHOBEN 2026-08-03: `inhalt_einheit` (Grundpreis-Einheit) wurde beim Import nie gesetzt

**Auslöser:** Jacky bemerkte, dass alle über dieses Skript importierten Artikel im Shop keine Grundpreisangabe zeigen (gesetzlich vorgeschrieben für Garn in AT). Grundpreis wird von uns selbst berechnet und über die WooCommerce `unit`/`unit_price`-Felder gepusht (siehe [[project_shop_sync]], Grundpreis-Sync-Automatisierung 2026-07-23) -- `ShopSyncService::baueGrundpreisFelder()` bricht aber sofort mit `[]` ab, sobald `inhalt_einheit` leer ist.

**Root Cause:** Der JTL-Export liefert die Grundpreis-Einheit sehr wohl mit -- eigene Spalte `"Einheit Bezugsmenge"` (z.B. `"g"`), direkt neben `"GP-Bezugsmenge"`. `JtlVaterKindImportService.php` hat diese Spalte an keiner Stelle gelesen und `inhalt_einheit` an zwei Stellen (Vater-Zweig + normale-Artikel-Zweig) hart auf `null` gesetzt -- vermutlich beim Bau dieses (neueren) Import-Skripts übersehen, denn der ältere, einfachere CSV-Import (`public/artikel/import.php`) liest dieselbe Spalte schon lange korrekt.

**Fix:** `inhalt_einheit` wird jetzt beim Parsen aus `Einheit Bezugsmenge` gelesen (analog zu den Nachbarfeldern `Inhalt/Menge`/`GP-Bezugsmenge`) und bei Vater- wie Normal-Artikeln durchgereicht. Kind-Artikel brauchten keine eigene Änderung -- sie erben `inhalt_einheit` bereits über die bestehende Vater→Kind-Vererbung (`ArtikelService`/`VariantenService`).

**Backfill für bereits importierte Artikel** (`scripts/backfill_inhalt_einheit.php`, neu): liest alle Artikelstammdaten-CSVs unter `import/erledigt/` (per Spalten-Fingerprint erkannt, nicht Dateiname), baut eine Artikelnummer→Einheit-Zuordnung, trägt sie nur bei noch leeren `inhalt_einheit`-Werten nach (idempotent) und bumpt `aktualisiert_am` (damit der nächste Sync/[[project_shop_sync]]-Komplettabgleich den Grundpreis automatisch nachzieht). Mit `--dry-run` vorab geprüft.

**Überraschender Fund beim Dry-Run:** Nicht nur die 624 Artikel mit aktivem `grundpreis_anzeigen=1` waren betroffen, sondern 9.316 von 13.877 Artikeln insgesamt (JTL befüllt "Einheit Bezugsmenge" offenbar unabhängig vom "Grundpreis ausweisen"-Häkchen). Mit Jacky abgestimmt: alle 9.316 Treffer backfilled (harmlos, `baueGrundpreisFelder()` prüft `grundpreis_anzeigen` zuerst, der Rest ist reine Datenkorrektur). **Live ausgeführt, 9.316 Artikel aktualisiert.**

**12 echte Artikel blieben ohne Treffer** (keine passende Zeile in den vorhandenen CSVs gefunden, z.B. `LY-112-0003` BABY COTTON hellgrau melange, `272665-07490` Tweed Color 4-fädig, `CB-Fauve-272`, `BEL-GE6580`, `DU-010-71A` -- vollständige Liste im Skript-Output) -- müssen von Hand im Artikel-Formular nachgetragen werden, kein Automatismus dafür gebaut (zu wenige Einzelfälle).

**How to apply:** Bei künftigen Import-Sessions mit diesem Skript ist der Bug behoben, kein erneuter Backfill nötig. Falls doch nochmal Artikel mit leerer `inhalt_einheit` aus einem Import auftauchen: erst prüfen ob eine neue CSV-Quelle eine andere Spaltenbenennung nutzt, bevor an der Logik selbst gezweifelt wird.

## ✅ Erweiterung 2026-08-02: Gemischte Kategorien (Vater+Kind + normale Artikel + bereits existierend)

Jacky brachte einen neuen Fall: Kategorien, die sowohl Vater+Kind-Artikel als auch "normale"
Einzelartikel enthalten, wobei viele der Artikel bereits (unter einer ANDEREN Kategorie)
in der DB existieren (Testdaten: `Sale-Artikelstammdaten-02082026.csv` + `sale-Variationskombinationen-02082026.csv`).

**Kritischer Fund (bestehender Code, nicht Teil des ursprünglichen Imports getestet):**
`saveKategorien($vaterId, [$kategorieId])` in `fuehreImportDurch()` hat die Kategorie
IMMER ERSETZT statt ergänzt (`updateArtikelKategoriezuweisungen()` löscht erst alle
bestehenden Zuweisungen). Live-Stichprobe bestätigt: F-LaDoro (id=11633) hatte bereits
Kategorie "Ferner Wolle" — ein Lauf hätte diese durch die neue Kategorie ERSETZT (via
`syncKategorienZuKindern` auch bei allen 18 Kindern). Bisher nie aufgefallen, weil frühere
Imports (DROPS, BorgoDePazzi etc.) immer komplett neue Artikel ohne Vorkategorien waren.

**Zweiter Fund:** Bei Kindern, die in der jeweiligen Sale-CSV keine eigene Stammdatenzeile
hatten (in den Testdaten 221 von 465, davon 196 bereits in der DB vorhanden), hätte der
Code Gewicht/Maße/UVP/Einheit/Hersteller/Auslauf-Flag unbedingt mit NULL überschrieben.

**Jackys Entscheidung (bewusst, nicht spekulativ):** Bereits existierende Artikel (Vater,
Kind, normal) bekommen beim erneuten Import-Lauf NUR die zusätzliche Kategorie — Preis
bleibt IMMER unangetastet, auch wenn es fachlich ein "Sale"-Preis wäre ("das müssen wir
über die Aktions-Schleife manuell machen, beim Import wäre die Laufzeit nicht abbildbar").
Wichtig: der Fall ist NICHT auf "Sale" beschränkt — jede gemischte Kategorie (auch z.B.
Amigurumi) kann betroffen sein.

**Gebaut in `JtlVaterKindImportService.php`:**
- `fuegeKategorieHinzu()`: lädt bestehende Kategorien, merged mit der neuen, ruft
  `saveKategorien()` mit der VOLLEN Liste auf (additiv statt ersetzend) — ersetzt den
  direkten `saveKategorien($vaterId, [$kategorieId])`-Aufruf.
- Kind-Verarbeitung: `_war_vorhanden` wird VOR `erstelleKombinationen()` per
  `findByArtikelnummer()` geprüft. War das Kind schon da, werden Preis-Update
  (`updatePreis`), das rohe Attribut-UPDATE (Gewicht/Maße/UVP/Einheit/Hersteller/Auslauf)
  UND `passeKindPreiseAn()` (Achsen-Aufpreis) übersprungen — nur Kategorie via
  `kopiereVaterRelationenZuKindern()`s additive `copyKategorien()` (INSERT IGNORE) läuft.
- `baueVorschauNormaleArtikel()` (neu): erkennt Stammdaten-Zeilen ohne Vater-Flag UND ohne
  Kind-Zuordnung (`Identifizierungsspalte Vaterartikel` leer) als eigenständige Artikel.
  Zeilen mit gesetzter Vater-Kennung, aber ohne passende Kombi-Zeile (Achsen unbekannt,
  JTL-Exportlücke), werden bewusst NICHT als normal behandelt, sondern gezählt
  (`verwaiste_kinder_anzahl`) und in der UI als Warnung angezeigt, aber übersprungen.
- `fuehreNormaleImportDurch()` (neu): existiert die Artikelnummer schon → nur
  `fuegeKategorieHinzu()`; sonst Neuanlage über `ArtikelService::save()` (gleiche Felder
  wie der bestehende Vater-Neuanlage-Zweig, ohne Achsen/Kinder).
- UI (`jtl_import.php`/`jtl_import_commit.php`): neue Sektion "Normale Artikel" in der
  Kontrollliste + "bereits vorhanden → nur Kategorie"-Badges bei Vater/Kind/normal +
  Ergebnisseite zeigt "nur Kategorie zugewiesen" statt "importiert".

**Verifiziert (echter DB-Test mit Cleanup, siehe [[feedback_test_isolation]]):** F-LaDoro
(Vater, bereits vorhanden) + alle 18 echten Kinder + ein neuer normaler Artikel
(ZPA-MI-0004) gegen eine temporäre Test-Kategorie importiert: alte Kategorie "Ferner
Wolle" blieb erhalten, neue Kategorie kam dazu, Preis/Gewicht/Einheit/Hersteller von
F-LaDoro-001 blieben exakt unverändert, neuer Artikel korrekt angelegt + kategorisiert.
Danach vollständig zurückgebaut (Test-Kategorie + Test-Artikel gelöscht) — keine Reste.

**Trockenlauf-Zahlen der echten Sale-Testdaten:** 43 Väter (32 bereits vorhanden), 465
Kinder (356 bereits vorhanden), 20 normale Artikel (alle neu), 0 verwaiste Kind-Zeilen.

**Noch offen:** Live-Test durch Jacky im Browser mit den echten Sale-Dateien steht aus.

## ✅ Nachtrag 2026-08-02: Variationskombinationen-CSV optional + Einheiten-Verwaltung

Zwei Jacky-Fragen direkt danach:
1. **Kategorie NUR mit normalen Artikeln (keine Vater/Kind überhaupt)** — vorher war die
   Kombi-CSV im Formular `required`, das Formular ließ sich ohne sie gar nicht absenden.
   Jetzt optional: `csv_kombinationen` kein Pflichtfeld mehr, `parseVariationskombinationen()`
   wird nur aufgerufen wenn eine Datei hochgeladen wurde (sonst leeres Array). Vater-Zeilen
   (Flag "Ist Vaterartikel"), für die dann keine Kombi-Daten vorliegen, werden NICHT
   fälschlich als normaler Artikel behandelt, sondern gezählt (`vater_ohne_kombidatei_anzahl`)
   und als Warnung in der Kontrollliste angezeigt, aber übersprungen. Getestet: Trockenlauf
   ohne Kombi-Datei gegen die echten Sale-Daten → 0 Väter erkannt, 43 korrekt als
   "übersprungen" gemeldet, die 20 normalen Artikel unverändert korrekt erkannt.
2. **Einheiten-Verwaltung fehlte komplett** (Tabelle `einheiten` nur lesbar über
   `EinheitenRepository::findAll()`, keine UI) — Jacky bemerkte fehlende Werte (Strang,
   Bollen, Paar) beim Einheiten-Mapping im Import. Neu: `EinheitenRepository` um
   `insert()`/`update()`/`delete()`/`zaehleVerwendung()`/`findAllMitVerwendung()` erweitert,
   neuer Tab "Einheiten" in `einstellungen/index.php` (Liste + Neu-Anlegen-Form + inline
   Bearbeiten/Löschen pro Zeile), Handler in `einstellungen/speichern.php`. Löschen ist
   gesperrt (🔒 + serverseitige Prüfung), solange mind. 1 Artikel die Einheit verwendet
   (FK `artikel.einheit_id → einheiten.id`). UI bewusst als div/grid-Zeilen wie der
   bestehende "Kanäle"-Tab gebaut, NICHT als `<table><tr>` mit eingebettetem `<form>`
   (wäre ungültiges HTML — `<form>` darf laut Spec kein Kind von `<tr>` sein und Browser
   foster-parenten es unvorhersehbar). Getestet: Insert/Update/Delete-Zyklus per CLI
   gegen die echte DB, sauber aufgeräumt.

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

## ✅ Nachtrag 2026-08-02 (später): Artikeltyp/Artikelgruppe pro Zeile statt global

Jacky bemerkte beim Durchschauen des echten Sale-Exports: gemischte Kategorien können auch
gemischten Artikeltyp UND gemischte Artikelgruppe enthalten (z.B. Garn/4000-Wolle UND
Standard/4040-Sonstiges-Zubehör in derselben Kategorie). Die ursprüngliche Batch-weite
Vorab-Auswahl (siehe Plan oben, "gilt für alle Väter+Kinder des Imports") war dafür zu grob.

**Umgebaut:** Artikeltyp + Artikelgruppe im Upload-Formular sind jetzt nur noch eine
**Vorauswahl zum Vorbefüllen** (nicht mehr Pflichtfeld, nicht mehr global bindend). In der
Kontrollliste bekommt JEDER neu anzulegende Vater UND jeder neu anzulegende normale Artikel
sein eigenes Artikeltyp-/Artikelgruppe-Dropdown (vorbefüllt mit der Vorauswahl, änderbar).
Bereits existierende Artikel (Vater/normal) zeigen die Dropdowns gar nicht, da bei ihnen
ohnehin nichts außer der Kategorie angefasst wird.

`JtlVaterKindImportService::fuehreImportDurch()`/`fuehreNormaleImportDurch()`: Parameter
`$artikeltypCode`/`$artikelGruppeId` entfernt, kommen jetzt aus `$korrekturen[...]['artikeltyp_code']`
bzw. `['artikel_gruppe_id']` (nur im "neu anlegen"-Zweig gelesen, mit Fehlermeldung
"Artikeltyp und/oder Artikelgruppe nicht ausgewählt" falls leer). Kinder brauchen keine eigene
Auswahl — sie erben `artikeltyp_id`/`einheit_id` etc. weiterhin vom (jetzt korrekt befüllten)
Vater über die bestehende Vererbungslogik in `VariantenService::erstelleKombinationen()`.

## Nachtrag 2026-08-02: "Keine Artikel erkannt" bei alpacaparty-Dateien — Datenproblem, kein Bug

Jacky meldete "Keine Artikel erkannt" bei `alpacaparty-Artikelstammdaten-02082026.csv` +
`alpacaparty-Variationskombinationen-02082026.csv`, obwohl beide Dateien befüllt waren.
Analyse: Stammdaten enthält 17 echte Väter (D-1076, D-1098, D-1028 usw., alles DROPS-Alpaca),
die Kombinationen-Datei enthält aber 23 KOMPLETT ANDERE Vater-Artikelnummern (Pl-278041,
F-Lung-Sowo-4f, F-LaceV-D, F-Mer420 usw. — Seile/Zubehör/andere Marken, teils dieselben wie
in der Sale-Kombi-Datei). **0 Überschneidung.** Die beiden Dateien gehören nicht zusammen —
vermutlich falsche/veraltete Kombinationen-CSV hochgeladen (Export einer anderen Kategorie).
Jacky muss die Variationskombinationen-CSV gezielt für alpacaparty neu aus JTL exportieren.

**UI-Verbesserung dabei:** Die generische "Keine Artikel erkannt"-Meldung unterschied bisher
nicht zwischen "wirklich leer/falsches Dateipaar vertauscht" und "Väter erkannt, aber keine
einzige Kombi-Zeile passt dazu" — beides zeigte denselben Text, ohne dass die eigentlich schon
vorhandene `vater_ohne_kombidatei_anzahl`-Zahl in der Fehlermeldung selbst auftauchte (die Warnbox
in der Kontrollliste erscheint ja nur, wenn überhaupt eine Kontrollliste gerendert wird — bei 0
Vätern UND 0 normalen Artikeln bricht der Code aber schon vorher mit der generischen Fehlermeldung
ab, die Warnbox kam also nie zur Anzeige). Fix in `jtl_import.php`: bei `vater_ohne_kombidatei_anzahl > 0`
jetzt eine spezifische Meldung mit Zahl + Hinweis auf falsches/altes Dateipaar.

## 🟢 BUG behoben 2026-08-02: Leere Verkaufseinheit-Mapping wurde ignoriert

Jacky meldete: Mapping für "(leer)" im Formular ausgefüllt, trotzdem "Verkaufseinheit ... konnte
nicht zugeordnet werden". Ursache: `loeseEinheitAuf()` hatte `if ($roh === '') return null;` als
ALLERERSTE Zeile — brach also ab, bevor die User-Mapping-Auswahl überhaupt geprüft wurde. Fix:
früher Return entfernt, Auto-Match-Zweig nur noch für nicht-leere Rohwerte (Reihenfolge sonst
unverändert). Mit 4 Fällen per Reflection getestet (leer ohne/mit Mapping, unbekannt mit Mapping,
bekannt per Auto-Match) — alle korrekt.

**Getestet** (echter DB-Test mit vollständigem Cleanup): Vater `P-Nat.-S` (2 Kinder, echt neu)
ohne Typ/Gruppe → korrekt abgelehnt; mit Typ=GARN/Gruppe=1 → Vater+beide Kinder korrekt angelegt
mit den richtigen IDs. Normaler Artikel `ZPA-MI-0004` ebenso ohne/mit Auswahl getestet. Beim
Cleanup einen bereits bekannten Stolperstein aus [[feedback_test_isolation]] nochmal bestätigt:
`varianten_achse_werte`-Zeilen sind PRO VATER-ARTIKEL eigene Zeilen (kein geteiltes globales
Wörterbuch wie zunächst angenommen) — vor dem Löschen einzelner Werte-IDs erst per
`varianten_kombination_werte`-Join auf Verwaisung geprüft, nicht pauschal nach Werttext gelöscht.
