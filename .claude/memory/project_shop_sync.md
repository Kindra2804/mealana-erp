---
name: project-shop-sync
description: "Online-Shop-Anbindung (WooCommerce): Phase 1-4 + cron/shop_sync.php + Kategorie/Hersteller-Update-Sync + Hersteller-GPSR-Beschreibung + FTP-Bulk-Bild + Live-Deploy 0.4.0beta alle fertig (2026-07-22); Kategoriebild-Sync + Rate-Limit-Erkennung + Achsen-Dimensionen-Fix FERTIG + Karisma End-to-End verifiziert (2026-07-30); ✅ 429-Sperre GELÖST (fehlender User-Agent war Ursache); ✅ Achsenwerte-Umbenennung-Sync BEHOBEN + 404-Altlast-Fallback (2026-07-31); ✅ scripts/komplettabgleich.php gebaut + Live-Test mit 50er-Batch erfolgreich (2026-08-03); 🔴 ECHTER Bug in baueAchsenDimensionen() (sort_order-abhängiger Sub-Achsen-Datenverlust) BEHOBEN + 314 Artikel resynct + Fortschrittsanzeige nachgerüstet (2026-08-03)"
metadata:
  node_type: memory
  type: project
  originSessionId: b67547bf-d9a0-405b-832f-e145eff451fa
  modified: 2026-08-03T18:48:37.966Z
---

## 🔴 BEHOBEN 2026-08-03: `baueAchsenDimensionen()` verlor Sub-Achsen-Werte, wenn die Sub-Achse VOR ihrer Parent-Achse sortiert war

**Auslöser:** Erster echter `komplettabgleich.php mealana 50`-Testlauf -- Batch selbst lief sauber durch (`[Durchlauf 1] 48 erfolgreich, 0 Fehler`), danach aber mehrfach `PHP Warning: Trying to access array offset on value of type bool in ShopSyncService.php on line 505`.

**Erste Diagnose war FALSCH:** Erste Vermutung war ein Freitext-Achsen-Leck (`findKombinationFuerKind()` filtert Freitext nicht mit) -- Jacky fragte zurecht nach, ob es überhaupt Freitext-Artikel gibt. DB-Check: **keine einzige** Achse mit `darstellungsform IN ('freitext','pflichtfreitext')` existiert im System. Diese Theorie war schlicht falsch, verworfen.

**Echte Root Cause** (per Reflection-Skript gegen die reale DB gefunden, alle Väter durchgetestet): `VariantenService::baueAchsenDimensionen()` markiert eine Sub-Achse (z.B. "[Uni]", `abhaengig_von_achse_id=7`="Farbe") beim Erreichen in der `foreach`-Schleife als `$verarbeitet[$aId]=true`, wenn ihre Parent-Achse ("Farbe") ebenfalls direkt zugewiesen ist -- mit dem Kommentar "wird beim Parent-Durchlauf eingebaut". Das Problem: wird die Parent-Achse ("Farbe") SPÄTER in derselben Schleife erreicht (abhängig von `artikel_achsen.sort_order` -- bei den betroffenen Vätern stand "Farbe" hinter "[Uni]"/"[Mix]"/"[Print]"), prüft der Root-Achse-Zweig `if (isset($verarbeitet[$subId])) continue;` -- sieht das *bereits* gesetzte Flag der Sub-Achse und überspringt sie, OHNE ihre Werte einzusammeln. Das `$verarbeitet`-Flag wurde also für zwei unterschiedliche Bedeutungen wiederverwendet ("bekommt keine eigene Dimension" vs. "wurde schon einer Dimension zugeordnet") -- reihenfolgeabhängiger Bug, der bei "Sub-Achse zuerst" Werte komplett verschluckt.

**Tatsächliche Auswirkung, mit einem Reflection-Skript gegen die komplette DB verifiziert (nicht nur vermutet):** 6 Väter betroffen -- **150, 165, 178 (Karisma!), 179, 184, 2859** -- mit zusammen **294 Kind-Artikeln**, deren Farbwert (aus "[Uni]"/"[Mix]"/"[Print]") beim Shop-Sync in eine nie synchte, rohe achse_id fiel -- exakt der Pfad, der `findAchseShopZuweisung()` `false` liefern ließ und die Warnung auslöste.

**Wichtig:** Karisma (#178) war laut Memory-Eintrag vom 2026-07-30 "End-to-End verifiziert" -- der damalige Test lief aber vor der letzten Umsortierung/einem erneuten Resync und deckte diesen speziellen sort_order-Fall offenbar nicht ab. Lehre: ein einzelner erfolgreicher Testartikel beweist nicht, dass die Sortier-Reihenfolge bei ALLEN Artikeln gleich ist.

**Fix:** `baueAchsenDimensionen()` berechnet jetzt VORAB (eigener Durchlauf, unabhängig von der Iterationsreihenfolge) eine `$subsumiert`-Menge -- alle Sub-Achsen, deren Parent zugewiesen ist. Der Haupt-Durchlauf überspringt diese von vornherein als eigenständiges Ziel, und der Root-Achse-Zweig sammelt ihre Werte jetzt IMMER ein (kein `$verarbeitet`-Check mehr auf die Sub-ID nötig, da sie durch den Vorab-Filter nie ein zweites Mal als Hauptziel auftauchen kann). Zusätzlich bleibt die defensive `array_filter` in `ShopSyncService::baueVariationPayload()` (verwirft Kombi-Werte ohne bekannte Dimension) als Sicherheitsnetz bestehen -- schadet nicht, greift jetzt aber nur noch bei echten Zukunfts-Fällen (z.B. falls doch mal eine Freitext-Achse dazukommt).

**Verifiziert:** Reflection-Skript nach dem Fix erneut über ALLE Väter der DB gelaufen -- 0 von vorher 294 Mismatches übrig. Alle 6 betroffenen Väter liefern jetzt genau EINE korrekt zusammengeführte "Farbe"-Dimension (87/90/63/91/65/6 Werte je nach Vater). **314 Artikel (6 Väter + 298 bereits synchte Kinder) `aktualisiert_am` gebumpt**, damit der nächste Sync die korrigierten Attribute automatisch nachzieht (gleiches Muster wie beim Achsenwerte-Umbenennung-Fix vom 2026-07-31).

**Noch offen:** Kein echter Cron-/Komplettabgleich-Lauf gegen `indra-design.at` seit dem Fix -- nur die reine DB-Logik verifiziert (kein WC-API-Call). Nächster Lauf sollte diese 6 Väter+298 Kinder korrekt nachziehen und keine Warnungen mehr zeigen. Bei WooCommerce selbst noch nicht gegengeprüft, ob die betroffenen Variationen dort aktuell tatsächlich ohne Farbauswahl dastehen (plausibel, aber nicht bestätigt).

## ✅ NEU 2026-08-03: Fortschrittsanzeige für `komplettabgleich.php`

Jacky bemängelte: bei einem großen Batch (viele Bilder pro Artikel, je 0,4s Pause + Upload) blieb die Konsole minutenlang ohne jede Ausgabe -- unklar ob das Skript noch lebt.

**Gebaut:** `ShopSyncService::syncShop()` bekommt einen optionalen `?callable $fortschritt`-Parameter, wird in der Haupt-Artikel-Schleife per `finally`-Block nach JEDEM verarbeiteten Artikel aufgerufen (auch bei übersprungenen Kindern ohne fertigen Vater UND beim finalen Rate-Limit-Abbruch -- `finally` läuft in PHP auch bei `continue`/`break` innerhalb des `try`). Eigener Zähler `$verarbeiteteAnzahl` statt `$erfolg+$fehler`, damit der Fortschritt auch bei übersprungenen Artikeln weiterzählt. `komplettabgleich.php` druckt alle 5 Artikel eine Zeile (`FORTSCHRITT_SCHRITT`-Konstante) + `ob_implicit_flush(true)` gesetzt (sonst hätte PHPs Output-Puffer die Zeilen erst am Ende des Batches rausgelassen, nicht live).

**Live bestätigt (2026-08-03, derselbe 50er-Testlauf):** Batch komplett durchgelaufen, 48 erfolgreich/0 Fehler, `bulk_import_aktiv` korrekt gesetzt/wieder freigegeben.

## ✅ NEU 2026-08-03: `scripts/komplettabgleich.php` gebaut -- löst den 20-Artikel/15-Minuten-Flaschenhals bei großem Rückstau

**Auslöser:** Jacky synct erstmals die Garne (Teilmenge der späteren 23.000 Artikel) und beobachtete, dass der Abgleich seit 1,5 Tagen läuft und noch nicht mal die Hälfte durch ist. Hochrechnung bestätigt: `cron/shop_sync.php` synct bewusst nur 20 Artikel pro 15-Minuten-Takt (`ShopSyncRepository::findFaelligeArtikel()`-Default) -- das war für den laufenden Normalbetrieb gedacht, nicht für einen Massen-Ersteinstieg. Bei 23.000 Artikeln wären das rechnerisch ~12 Tage.

**Jackys Wunsch (JTL-Vorbild):** ein "Komplettabgleich"-Modus, der durchläuft bis WooCommerce eine Sperre setzt, dann automatisch pausiert und weitermacht -- plus eine Scope-Auswahl (nur Stammdaten/nur Kategorien/nur Bilder wie bei JTL). Mit Jacky abgestimmt: **zweistufig**, Scope-Auswahl bewusst zurückgestellt (siehe unten), zuerst nur das akute Tempo-Problem lösen.

**Gebaut (Schritt 1):**
- `ShopSyncService::syncShop()` bekommt einen optionalen `$limit`-Parameter (Default weiterhin 20, der normale Cron bleibt unverändert). Rückgabe-Array um `rate_limitiert` (bool) + `retry_after` (Sekunden aus dem `RateLimitException::$retryAfterSekunden`, an allen 5 Catch-Stellen in der Methode jetzt mitgegeben) erweitert -- rein additiv, `cron/shop_sync.php` liest weiterhin nur `erfolg`/`fehler` und ist unberührt.
- Neues `scripts/komplettabgleich.php <shop-slug> [batch-groesse=200]`: gleiches Muster wie `erstbefuellung_bilder.php` (setzt `bulk_import_aktiv`-Sperre, try/finally). Ruft `syncShop()` in einer `while(true)`-Schleife mit dem großen Limit auf, bis ein Durchlauf `erfolg=0` UND nicht rate-limitiert zurückgibt (= fertig ODER nur noch dauerhaft fehlerhafte Reste, die sonst eine Endlosschleife erzeugen würden). Bei erkanntem Rate-Limit: schläft exakt `retry_after` Sekunden (Fallback 90s falls der Server keinen Header mitschickt), dann automatisch weiter -- **kein Warten mehr auf den nächsten 15-Minuten-Cron-Tick**.
- Wichtige Design-Falle vermieden: ein `continue` in einer `do...while`-Schleife hätte die `while`-Bedingung trotzdem ausgewertet und die Pause-dann-weiter-Logik nach einem Rate-Limit mit `erfolg=0` fälschlich beendet -- deshalb bewusst `while(true)` mit explizitem `break` nur im Nicht-rate-limitiert-Zweig.

**Noch nicht gemacht:** Kein echter Testlauf gegen `indra-design.at` mit dem tatsächlichen Rückstau -- nur `php -l` Syntax-Check. Nächster Schritt: Jacky lässt es einmal laufen und bestätigt Tempo/Fehlerbild.

**Schritt 2 (zurückgestellt, auf Merkliste):** JTL-artige Scope-Auswahl (nur Artikelstammdaten / nur Kategorien / nur Bilder separat anstoßen). Bewusst nicht jetzt gebaut -- `syncShop()` ist aktuell ein einziger verzahnter Durchlauf (Kategorien→Hersteller→Achsenwerte→Artikel), eine saubere Aufteilung wäre größerer Umbau ohne zusätzlichen Nutzen fürs akute 23k-Tempo-Problem. Bei Bedarf hier weitermachen.

## ✅ NEU 2026-08-02: Kategorie-Ausschluss pro Shop (fehlende Möglichkeit, Blatt-Kategorie im Shop zu unterdrücken)

Jacky bemerkte einen Denkfehler im "Blatt-Kategorie geht in den Shop"-Konzept: ein Artikel kann
in mehreren Kategorien stehen (auch rein internen, die NICHT im Shop erscheinen sollen) — bisher
wurden ALLE Kategorien eines Artikels ungefiltert gepusht, ohne Möglichkeit eine Kategorie gezielt
für einen Shop auszuschließen. Bestätigt im Code: `ShopSyncRepository::findKategorieIdsFuerArtikel()`
lieferte alle `artikel_kategorien`-Einträge ungefiltert, `findWcKategorieIds()` (für den Produkt-
Payload) genauso. Die vorher dokumentierten "Kanal-Chips an Kategorien" (Design-Entscheidung
2026-06-21) sind rein berechnet/lesend — kein manueller Schalter existierte dafür in der UI.

**Entscheidung (Jacky):** Ausschluss **pro Kategorie UND pro Shop einzeln** (nicht global) —
es gibt 3 echte Shops (MEALANA/bio-wolle.at/sockenwolle-online.at), eine Kategorie kann je nach
Shop unterschiedlich passend sein.

**Gebaut:**
- Migration 156: `kategorie_shops.ausgeschlossen TINYINT(1) DEFAULT 0`.
- `ShopSyncRepository`: `istKategorieAusgeschlossen()`, `setKategorieAusschluss()` (Upsert, legt die
  Zeile bei Bedarf neu an — Ausschluss kann VOR dem ersten Sync gesetzt werden), `findAusschluesseFuerKategorie()`
  (für die UI). `findWcKategorieIds()` UND `findFaelligeKategorien()` filtern jetzt `ausgeschlossen = 0`.
- `ShopSyncService::syncShop()`: die "Kategorie vor Artikel anlegen"-Schleife überspringt für diesen
  Shop ausgeschlossene Kategorien jetzt komplett (kein unnötiger WC-Term).
- **Wichtig (gleiche Lehre wie bei Kategorie-/Achsenwert-Umbenennungen):** reines Setzen des Flags
  reicht nicht — `setKategorieAusschluss()` bumpt zusätzlich `artikel.aktualisiert_am` für ALLE
  Artikel dieser Kategorie, sonst würde der (Aus-)Schluss erst beim ohnehin nächsten fälligen Sync
  des Artikels wirksam.
- UI: neuer Bereich "Shop-Sichtbarkeit" im Bearbeiten-Modal (`kategorien_verwalten.php`/`.js`,
  analog zum bestehenden Kategoriebild-Bereich) — Checkbox pro aktivem Shop, sofort per AJAX
  (`kategorie_shop_ausschluss_ajax.php`) gespeichert, kein Extra-Klick auf "Speichern". Nur bei
  bereits existierenden Kategorien sichtbar (braucht eine echte kategorie_id).
- **Bewusst NICHT gemacht:** Ausschluss wirkt nur auf die exakte (Blatt-)Kategorie am Artikel,
  nicht rekursiv auf Unterkategorien eines ausgeschlossenen Astes — passt zum gemeldeten
  Anwendungsfall (einzelne gemischte Kategorien), eine "ganzen Ast ausschließen"-Funktion wäre
  eine spätere Erweiterung falls gebraucht. Ein bereits in WooCommerce angelegter Kategorie-Term
  wird beim Ausschließen NICHT selbst gelöscht (nur die Produkt-Zuordnung entfällt beim nächsten
  Sync) — unbenutzte Terms schaden nicht, gleiche Haltung wie bei den verwaisten Uni/Mix-Attributen.

**Getestet:** Isolierter DB-Test (synthetische Test-Kategorie + Test-Artikel, kein echter Shop-Call)
— `findWcKategorieIds()` liefert die simulierte externe ID vor Ausschluss, leer nach Ausschluss,
wieder befüllt nach Aufheben; `aktualisiert_am` des betroffenen Artikels wurde korrekt auf "jetzt"
gebumpt (vorher künstlich auf 2020 gesetzt). Vollständig aufgeräumt. **Ein echter Cron-Lauf gegen
einen echten Shop steht noch aus** (nächster sinnvoller Schritt: eine Kategorie für einen Shop
ausschließen, die einen bereits gesyncten Artikel betrifft, dann Cron laufen lassen und bei
WooCommerce prüfen, dass die Kategorie-Zuordnung dort tatsächlich verschwindet).

### 🔴 Nachtrag gleicher Tag: Zwei echte Folgebugs beim ersten Live-Test durch Jacky gefunden + behoben

Jacky sperrte testweise ein paar WURZEL-Kategorien für einzelne Shops — **es passierte gar nichts**,
und der bestehende (aus 2026-06-21 stammende, rein berechnete) Kanal-Chip in der Sidebar
(`includes/shell_top.php`, `ArtikelService::berechneShopChips()`) blieb unverändert stehen.

**Bug 1 (der eigentliche Kern):** Sowohl die Ausschluss-PRÜFUNG (`istKategorieAusgeschlossen`) als
auch die Fällig-MARKIERUNG (`setKategorieAusschluss()`s `UPDATE artikel ... WHERE ak.kategorie_id = :kid`)
prüften nur die EXAKTE Kategorie-ID. Da Artikel aber nie direkt an einer Wurzel hängen (nur an
Blatt-Kategorien, siehe `artikel_kategorien`), hatte das Sperren einer Wurzel dadurch buchstäblich
NULL Wirkung: kein Artikel hatte je exakt diese ID, also wurde auch keiner als fällig markiert.

**Fix:**
- Neue `ShopSyncRepository::istKategoriePfadAusgeschlossen()`: prüft die Kategorie UND alle ihre
  Vorfahren (nutzt bestehende `findKategorieMitVorfahren()`). Ersetzt die reine Selbst-Prüfung an
  beiden Sync-Stellen (`ShopSyncService::syncShop()`-Loop, `findWcKategorieIds()`).
- Neue private `findKategorieIdsMitUnterkategorien()`: liefert eine Kategorie-ID + ALLE
  Unterkategorien (PHP-Traversierung, Baum ist klein genug -- bewusst kein rekursives SQL, um
  MariaDB-Versionskompatibilität nicht vorauszusetzen, obwohl `KategorieRepository::findAlleKinderIds()`
  an anderer Stelle bereits `WITH RECURSIVE` verwendet). `setKategorieAusschluss()` markiert damit
  jetzt Artikel der GESAMTEN betroffenen Unterkategorien als fällig, nicht nur exakte ID-Treffer.

**Bug 2 (unabhängiger zweiter Fund, gleiche Ursache "nur exakte ID geprüft"):** Der Kanal-Chip
(`ArtikelService::berechneShopChips()`, komplett unabhängiger Code von obigem) kannte
`kategorie_shops.ausgeschlossen` überhaupt nicht -- weder an der gesperrten Kategorie noch
vererbt auf Unterkategorien. Fix: neue `KategorieRepository::findAusschluesseAlsShopCodes()`
(EINE Query für den ganzen Baum, kategorie_id => ['S1',...] -- bewusst keine Sub-Query pro
Baumknoten, siehe die dokumentierte 640x-Performance-Lehre bei `findAllMitEltern()` in
derselben Datei). `berechneShopChips()` bekommt jetzt einen "geerbte Ausschlüsse"-Akkumulator
beim Abstieg von der Wurzel mit (Shop-Code einmal auf dem Pfad gesperrt = bleibt für den ganzen
Unterbaum gesperrt, auch wenn eine tiefere Kategorie selbst nicht explizit gesperrt ist).

**Beide Fixes mit isolierten Testdaten verifiziert** (Wurzel+Blatt+Test-Artikel mit echter
`artikel_shops`-Zuweisung): Chip zeigt "S1" vor Sperre an Wurzel UND Blatt, verschwindet an BEIDEN
nach Sperre der Wurzel, kommt an beiden zurück nach Aufheben. Vollständig aufgeräumt.

### UI-Nachschliff gleicher Tag: Kanal-Chips im Sidebar-Kategoriebaum kompakter

Jacky bemerkte: bei vielen Herstellern (Screenshot) macht das Chip-unter-jeder-Zeile-Layout die
Kategorieliste unübersichtlich lang. Umgebaut in `includes/shell_top.php` + `css/components.css`:
Chips jetzt INLINE zwischen Pfeil und Kategoriename (statt eigene Zeile darunter), verkleinert auf
reine Shop-Nummer ("1"/"2"/"3" statt "S1"/"S2"/"S3", Farbe bleibt Haupt-Unterscheidungsmerkmal, Legende
unten erklärt die Zahlen), mit `title`-Tooltip pro Chip (voller Shop-Name) UND einem Tooltip auf der
ganzen Zeile (voller Kategoriename, für lange abgeschnittene Namen). Neue CSS-Klassen `.kat-chips-inline`
+ `.kc-inline` (kompaktere Variante von `.kc`, die Legende unten behält die größere Standard-`.kc`-Größe).
Reine CSS/HTML-Änderung, von Jacky selbst im Browser bestätigt ("perfekt").

**Bewusst nicht behoben:** `findFaelligeKategorien()` (Change-Detection für Text-Updates bereits
synchter Kategorien) prüft weiterhin nur die exakte Kategorie, nicht den Vorfahren-Pfad -- eine
über einen Vorfahren ausgeschlossene, aber selbst nicht direkt gesperrte Kategorie würde also
weiterhin (unnötig, aber harmlos) auf reine Text-/Namensänderungen hin geprüft. Reine
Effizienzfrage, keine Korrektheitsfrage (die Kategorie wird ja über `findWcKategorieIds()` trotzdem
nicht mehr einem Artikel zugewiesen) -- nicht angefasst, um den Fix-Umfang klein zu halten.

---

## ✅ BEHOBEN 2026-08-01: Vater-Artikel im Shop immer "ausverkauft" trotz Kinderbestand

Jacky meldete: Vater-Artikel (Variable Products) zeigen im Shop immer "ausverkauft", auch wenn Kinder Bestand haben — erinnerte sich an eine frühere Diskussion, wie man das "überlisten" wollte, aber es war nirgends in Memory dokumentiert.

**Root Cause (echter Tippfehler, kein Konzeptfehler):** `ShopSyncService::baueProduktPayload()` Zeile 408 hatte `if (empty($achsen))` — diese Variable existiert in dieser Methode gar nicht (nur `$dimensionen` wird dort berechnet, der Kommentar direkt darüber beschreibt korrekt die Absicht). `empty()` auf eine undefinierte Variable ist in PHP immer `true` (keine Warnung), die Bedingung war also PERMANENT wahr. Dadurch bekam JEDER Vater (auch Variable Products mit Achsen) zusätzlich `baueBestandsFelder($vaterId)` aufgedrückt — und weil ein Vater seit dem [[bug_vater_artikel_bestand_wareneingang]]-Fix (und auch schon vorher der Absicht nach) NIE eigenen Lagerbestand hat, lieferte das immer `manage_stock=true, stock_quantity=0` direkt am Elternprodukt → WooCommerce zeigte den gesamten Vater als ausverkauft, unabhängig vom tatsächlichen Bestand der Kinder/Variationen.

**Fix:** Bedingung korrigiert zu `if (empty($dimensionen))`. Für Variable Products (Achsen vorhanden) wird jetzt im `else`-Zweig explizit `manage_stock=false` gesetzt — bewusst nicht nur weggelassen, weil WooCommerce bei PUT-Updates weggelassene Felder unverändert lässt (ein bereits falsch gesetzter Vater wäre sonst dauerhaft kaputt geblieben, das reine Weglassen hätte den Bestandsfehler nicht rückwirkend korrigiert).

**Wichtiger Befund beim Live-Testen:** Nur `manage_stock=false` zu setzen reicht — WooCommerce leitet `stock_status` dann korrekt aus den Variationen ab. Explizites Setzen von `stock_status=instock` von unserer Seite war NICHT nötig und wurde von der API sogar ignoriert (WooCommerce berechnet das selbst aus den Variationen, sobald `manage_stock=false` am Parent steht).

**Alle 18 bereits gesyncten Väter live repariert** (einmaliger manueller API-Call `manage_stock=false` pro Vater, kein Full-Resync nötig): Ergebnis passt exakt zum tatsächlichen Kinderbestand — AD-CS (30 gesamt) und D-1010 (60 gesamt) korrekt `instock`, alle 16 anderen (Kinderbestand tatsächlich 0, u.a. Bio Shetland GOTS/BC-011010119 — bestätigt beim Debuggen des [[project_google_shopping_search_console]]-unabhängigen 3092-Sync-Fehlers am selben Tag) korrekt `outofstock`. Gegen echte DB-Summen verifiziert, keine Annahme.

**Wie lange bestand der Bug:** Vermutlich seit Phase 2 (Bestand/Lagerstand-Sync, 2026-07-21) — seitdem hätte JEDER neu gesyncte Vater denselben Fehler bekommen. Zukünftige Väter werden ab jetzt beim ersten Sync bereits korrekt behandelt.

## ✅ Einzelfall: Artikel 3092 dauerhaft "invalid_sku"-Fehler — verwaister Sync-Erfolg (2026-08-01)

Jacky meldete: Artikel 3092 (BC-011010119-45, Kind von Bio Shetland GOTS/3047) bekam bei jedem Cron-Lauf seit heute morgen 07:16 den Fehler "WooCommerce-API-Fehler (400, product_invalid_sku): Ungültige oder doppelte Artikelnummer."

**Root Cause:** Direkte Live-Abfrage bei WooCommerce (`GET /products?sku=BC-011010119-45`) zeigte: die Variation existiert dort bereits (id 760, parent_id 670 = korrekter Vater, Status publish, `date_created` 2026-07-31 20:17). Unsere `artikel_shops`-Zeile für 3092 hatte aber `external_id=NULL` — die Variation wurde also serverseitig erfolgreich angelegt, aber die Erfolgsantwort kam bei uns nie an oder wurde nicht verarbeitet (klassisches "Server hat's gemacht, Client denkt nein"-Szenario, vermutlich Timeout/Verbindungsabbruch am Vorabend). Jeder Cron-Lauf seither rief `erstelleVariation()` erneut auf (da `external_id` bei uns leer war), WooCommerce lehnt das korrekt als Duplikat ab — Endlosschleife.

**Fix:** Vor der Reparatur Inhalte gegengeprüft (WC-Variation: Preis 6,90€, Attribut "45 schwarzgrau" — beides exakt identisch zu ERP-Daten). Dann nur die lokale Verknüpfung nachgetragen (`ShopSyncRepository::markiereSynced(263, '760')`), kein Schreibzugriff auf WooCommerce nötig. Geprüft: kein systemisches Problem — keine weiteren `artikel_shops`-Zeilen mit `external_id IS NULL AND fehler_meldung LIKE '%invalid_sku%'` gefunden, war ein Einzelfall.

**Nicht behoben (bewusst, da nur 1 Einzelfall):** Der zugrundeliegende Robustheits-Fall ("Create-Request schlägt bei uns fehl, ist aber remote schon passiert") ist strukturell nicht abgesichert — bei einem erneuten Vorkommen würde derselbe Loop wieder auftreten. Ein genereller Fix wäre z.B. bei `product_invalid_sku`-Fehlern automatisch per SKU-Suche nachzuschauen, ob das Objekt schon existiert, und dann die Verknüpfung selbst zu reparieren statt nur zu loggen. Nicht umgesetzt, da bisher nur dieser eine Fall aufgetreten ist — bei Wiederholung würde sich eine generische Lösung lohnen.

## 🔴 Achsenwerte-Umbenennung wurde im Shop nie nachgezogen — BEHOBEN (2026-07-31)

**Auslöser:** Babsis "Nummer Name"-Sortierumstellung (siehe [[project_farbwerte_migration]] falls vorhanden, sonst: 518 `varianten_achse_werte.wert` + 377 Kindartikel-Namen von "Name (Nr.)" auf "Nr. Name" umgestellt). Jacky fragte danach nach, warum das Sync-Log seit Stunden still war UND ob die Uni/Mix-unter-Farbe-Zusammenführung noch funktioniert.

**Fund 1 (Log-Stille war harmlos):** Der komplette Migrations-Rückstau (122 Artikel) war schon in den ersten 7 Cron-Läufen (09:48–11:16 Uhr, `findFaelligeArtikel()`-Limit 20/Lauf) durchsynct — Stille danach war das erwartete "kein Leerlauf-Spam"-Verhalten, kein Bug.

**Fund 2 (echter Bug, selbst eingebaut):** Ein separat am selben Tag gebauter Fix für Achsenwert-Umbenennungen (`aktualisiereAttributTerm` bei geänderten Werten, Migration 155: `varianten_achse_werte.aktualisiert_am` + `varianten_achse_werte_shops.synced_at`) wurde erst NACH dem obigen Rückstau scharf geschaltet. Der Rename-Aufruf hing komplett am Artikel-Fälligkeits-Loop (`syncAchsenFuerVater()` wurde nur innerhalb `foreach ($faelligeArtikel...)` aufgerufen) — exakt dasselbe Bug-Muster wie schon bei Kategorien (2026-07-22) und Herstellern (2026-07-22): eine reine Werte-Umbenennung OHNE gerade fälligen Artikel wird nie nachgezogen. Weil der Artikel-Rückstau schon VOR dem Fix leergeräumt war, griff der neue Code seither nie.

**Fix:** `ShopSyncRepository::findFaelligeVaterFuerAchsenwerte()` (eigenständige Query: Vater-Artikel mit `varianten_achse_werte_shops.synced_at IS NULL ODER wert.aktualisiert_am > synced_at`) + eigener, unabhängiger Durchlauf in `syncShop()` (gleiches Muster wie `findFaelligeKategorien()`/`findFaelligeHersteller()`).

**Fund 3 (beim Testen des Fixes, weitere Altlast):** "DROPS Alaska" (Vater #172, 20 Farbwerte) hatte `externe_term_id`s aus einem niedrigen ID-Bereich (71–108), die in WooCommerce gar nicht mehr existierten (404 `woocommerce_rest_term_invalid`) — vermutlich Reste aus der Zeit VOR dem Achsen-Dimensionen-Fix vom 2026-07-29 (siehe unten), nie nachträglich bereinigt (nur Karisma wurde damals end-to-end verifiziert/repariert, andere Altbestände nicht). Fix: neue `WooCommerceNotFoundException` (analog `RateLimitException`, in `WooCommerceClient.php`) bei HTTP 404 — `syncWerteFuerDimension()` fängt sie beim Umbenennen ab und behandelt den Wert dann wie "noch nie synct" (neuen Term anlegen statt den ganzen Vater-Durchlauf abzubrechen).

**End-to-End gegen `indra-design.at` verifiziert:** Nach zwei manuellen Cron-Läufen `varianten_achse_werte_shops` komplett clean (0 von 165 mit `synced_at IS NULL`). Stichproben direkt aus der WC-API bestätigt: `#188 "53 anthrazit [Mix]"` (Rename-Pfad), `#299 "37 graublau [Uni]"` (404-Fallback-Neuanlage-Pfad).

**Offen, bewusst nicht angefasst:** Zwei leere, ungenutzte WC-Attribute "[Uni]"/"[Mix]" existieren noch im Shop (Reste aus der Zeit vor der Farbe-Zusammenführung) — reine Kosmetik, keine Funktionsauswirkung. Jacky wollte noch entscheiden ob/wann aufräumen.

**Lehre für künftigen Sync-Code:** Jede neue Change-Detection, die NICHT direkt an `artikel.aktualisiert_am` hängt (Kategorien, Hersteller, jetzt Achsenwerte), braucht einen EIGENEN unabhängigen Durchlauf in `syncShop()` — Piggybacking auf den Artikel-Fälligkeits-Loop reicht nicht, weil der jederzeit leer sein kann während trotzdem was anderes fällig ist.

## 🔴 Achsen-Dimensionen-Bug (Sub-Achsen als eigene WC-Attribute) BEHOBEN (2026-07-29)

**Von Jacky gemeldet:** Artikel "Karisma" hat 2 Achsen "Mix" und "Uni" — wählt man im Shop erst eine Mix-Farbe, dann eine Uni-Farbe, versucht WooCommerce eine Kombination aus beiden zu bilden, die es nie geben kann.

**Root Cause:** "Mix" und "Uni" sind fachlich Sub-Achsen einer gemeinsamen (bei Karisma selbst nicht direkt zugewiesenen) Gruppenachse "Farbe" (`varianten_achsen.abhaengig_von_achse_id`). Der VarKombi-Generator (`artikel/detail.php`) kennt diese Regel schon lange ("Sub-Achsen-Werte immer UNION in eine Dimension") — der Shop-Sync-Code (`ShopSyncService::syncAchsenFuerVater()`) kannte sie NICHT und hat für jede rohe Achse ein eigenes WooCommerce-Attribut angelegt. Ergebnis: zwei unabhängige wählbare Attribute im Shop statt einem gemeinsamen "Farbe"-Dropdown.

**Fix:** Die Union-Logik aus `detail.php` in `VariantenService::baueAchsenDimensionen()` ausgelagert (reine Grouping-Funktion, kein DB-Zugriff) — jetzt EINZIGE Quelle für beide Stellen:
- `detail.php` ruft die Methode jetzt auf statt die Logik zu duplizieren (Downstream-Anpassung: `kartesischesProdukt(array_column($dimensionen,'werte'))`; ein `$assignedAchseIdSet` für die reine Baum-ANZEIGE musste lokal nachgebaut werden, da es aus dem entfernten Block kam).
- `ShopSyncService`: neue `holeDimensionenFuerVater()` (gecacht pro Sync-Lauf) + `baueAchseZuDimensionMap()` (flacher Lookup rohe achse_id → [dimension_achse_id, suffix], gebraucht weil ein Kind seine Werte immer über die ROHE Achse referenziert, das WC-Attribut aber unter der Dimensions-Achse läuft). `syncAchsenFuerVater()`/`syncWerteFuerDimension()` (vormals `syncWerteFuerAchse`) arbeiten jetzt auf Dimensionen. `baueVariationPayload()` bekam einen neuen `$vaterId`-Parameter für die Auflösung. Term-Namen bei unionierten Werten bekommen den Sub-Achsen-Namen als Suffix (z.B. "anthrazit [Mix]"), damit z.B. "Rot" aus Mix und "Rot" aus Uni nicht auf denselben Term kollabieren.
- Neue Repository-Methoden: `ShopSyncRepository::findAlleWerteFuerVater()` (alle Werte eines Vaters, nicht nach Achse gefiltert), `findAchsenFuerArtikel()` liefert jetzt zusätzlich `abhaengig_von_achse_id`.

**Verifiziert gegen echte Karisma-Daten** (Artikel #178, Achsen "[Mix]"=9 und "[Uni]"=8, beide `abhaengig_von_achse_id`=7="Farbe"): Vorher hätte der Sync 2 Attribute erzeugt, jetzt korrekt 1 Dimension "Farbe" mit allen 51 Werten (je mit [Mix]/[Uni]-Suffix).

**🔍 Wichtiger Nebenfund beim Verifizieren:** Karisma war mit dem alten Code bereits synct — `varianten_achsen_shops` hatte für achse 8/9 bereits EIGENE (falsche) `externe_attribut_id`s (8 und 9), UNABHÄNGIG von der schon existierenden korrekten "Farbe"-Zuordnung (achse 7 → externe_attribut_id 10, von anderen Artikeln, die "Farbe" direkt nutzen, ohne Mix/Uni-Split). Das heißt: Karismas Varianten zeigen auf `indra-design.at` aktuell noch auf die falschen, jetzt verwaisten Attribute — der Code-Fix behebt das nicht rückwirkend von selbst, ein echter Resync ist nötig.

**Karisma-Resync lokal vorbereitet (2026-07-29, noch NICHT live getestet wegen der offenen Rate-Limit-Sperre, siehe unten):**
- `varianten_achse_werte_shops` für alle 51 Karisma-Werte (shop 1) gelöscht (sonst würden beim Resync die ALTEN falschen Term-IDs wiederverwendet statt frische unter dem korrekten "Farbe"-Attribut anzulegen)
- `varianten_achsen_shops` für achse 8/9 (shop 1) gelöscht (verwaist, wird nach dem Fix nie mehr direkt referenziert -- nur noch achse 7 "Farbe")
- `artikel.aktualisiert_am` für Karisma Vater+alle 50 Kinder auf NOW() gesetzt (51 Zeilen) -- macht sie beim nächsten Sync-Lauf wieder "fällig"
- **Bewusst NICHT gemacht:** die alten verwaisten "Mix"/"Uni"-Attribute selbst in WooCommerce löschen -- das ist ein Live-Schreibzugriff, sollte Jacky selbst im wp-admin machen oder gemeinsam erledigen, sobald der nächste Sync bestätigt hat dass alles korrekt ankommt (unbenutzte Attribute schaden fürs Erste nicht).

**How to apply:** Sobald die Rate-Limit-Sperre (siehe unten) bestätigt behoben ist: `cron/shop_sync.php` einmal laufen lassen, Karisma sollte automatisch mit der korrekten "Farbe"-Dimension nachgezogen werden (kein manueller Extra-Schritt mehr nötig, alles ist schon vorbereitet). Danach bei WooCommerce gegenprüfen (Produkt/Variationen ansehen), und erst dann die alten verwaisten Attribute aufräumen.

### ✅ Karisma End-to-End verifiziert + zweiter Bugfix (2026-07-30)

**🔴 Dritte Stelle mit derselben rohen Achsen-Logik übersehen:** Jacky bekam beim eigenen Testlauf `PHP Warning: Trying to access array offset on value of type bool` in `ShopSyncService.php:346` — `baueProduktPayload()` (baut den PAYLOAD DES VATERS selbst, das `attributes[].options`-Array für WooCommerce) war beim ersten Fix übersehen worden und griff noch direkt auf `findAchseShopZuweisung(rohe achse_id)` zu, die für Mix/Uni (8/9) nach der Bereinigung nicht mehr existiert (nur noch achse 7 "Farbe"). Fix: gleiche Umstellung auf `holeDimensionenFuerVater()` wie in `syncAchsenFuerVater()`/`baueVariationPayload()`.

**Lehre:** Der isolierte Test von `baueAchsenDimensionen()` allein (Vortag) hat den Bug nicht aufgedeckt, weil er nur die reine Grouping-Funktion prüfte, nicht den kompletten Payload-Aufbau. Nächstes Mal beim Ändern von Sync-Code: alle drei Stellen (`syncAchsenFuerVater`, `baueProduktPayload`, `baueVariationPayload`) explizit gegenchecken, nicht nur eine.

**Nach dem Fix real gegen `indra-design.at` verifiziert** (Karisma musste dafür ein zweites Mal als "fällig" markiert werden -- Jackys eigener Testlauf mit dem alten Bug hatte `synced_at` bereits auf "heute" gesetzt, dadurch war `aktualisiert_am` von gestern nicht mehr "neuer" und die Fälligkeits-Prüfung hatte Karisma übersprungen):
- WC-Produkt #66 hat jetzt genau EIN "Farbe"-Attribut (id=10) mit 51 Optionen (vorher wäre das zwei getrennte "Mix"/"Uni"-Attribute gewesen)
- Stichprobe von 3 Variationen bestätigt: jede hat genau eine Farbe-Zuordnung mit korrektem Suffix (z.B. "natur (01) [Uni]"), keine unmöglichen Kombinationen mehr wählbar

### 🔍 Rate-Limit: Basis-Sperre ist 60s, eskaliert vermutlich bei wiederholten Verstößen (korrigiert 2026-07-30)

**Erste Theorie (falsch, siehe oben):** WAF an/aus würde die Sperrdauer von 3600s auf 60s ändern. **Von Jacky korrigiert:** Die 60-Sekunden-Sperre trat schon AM VORABEND bei noch eingeschalteter WAF auf -- die WAF-Theorie war also ein Zufallstreffer, kein echter Zusammenhang.

**Wahrscheinlichere Erklärung:** 60 Sekunden ist die Basis-Sperrzeit; wird sie innerhalb dieses Fensters durch weitere Versuche erneut ausgelöst, eskaliert die Sperrdauer (klassisches Verhalten vieler Rate-Limiter/Fail2ban-artiger Systeme). Der vorabendliche 1-Stunden-Fund kam vermutlich daher, dass mehrere Testläufe kurz hintereinander (beim Debuggen des Codes) die Sperre wiederholt neu ausgelöst und dadurch eskaliert haben. An diesem Tag (30.07.) waren die Testläufe zeitlich weiter auseinander -- blieb bei der Basis-Sperre.

**How to apply:** Nach einem erkannten `shop.rate_limit`-Log-Eintrag NICHT sofort wieder testen -- ein paar Minuten Abstand lassen, sonst droht wieder eine Eskalation auf eine lange Sperre. Gilt sowohl für Jacky als auch für mich (Claude) beim Debuggen. Der normale 15-Minuten-Cron ist davon nicht betroffen (Abstand ohnehin groß genug). Ob überhaupt noch eine echte "Ursache" beim Hosting-Support zu finden ist, ist damit unklar -- 60s Basis-Sperre ist für den Produktivbetrieb ohnehin unkritisch, muss also nicht zwingend weiterverfolgt werden.

### 🔍 Echter, fixbarer Mit-Auslöser gefunden: fehlender User-Agent (2026-07-30)

Jacky hat das rohe `access_ssl_log` seines Hostings geteilt (Plesk/Apache Combined Log Format). Auffällig: WordPress' eigener `wp-cron.php`-Aufruf identifiziert sich brav mit `User-Agent: WordPress/7.0.2; https://indra-design.at` -- **alle unsere eigenen Anfragen (sowohl die funktionierenden `/wc/v3/`- als auch die blockierten `/wp/v2/`-Aufrufe) hatten GAR KEINEN User-Agent** (`"-"` im Log). Grund: `WooCommerceClient.php` setzte nirgends `CURLOPT_USERAGENT` -- PHP/curl schickt dann standardmäßig gar keinen mit (anders als ein Browser).

Fehlender User-Agent ist ein sehr verbreitetes Bot-/Missbrauchs-Erkennungsmerkmal bei Security-Systemen/Rate-Limitern -- plausibler Mit-Auslöser der 429-Sperre, unabhängig vom Ausgang der Hosting-Abklärung ohnehin guter REST-API-Stil (Client identifiziert sich selbst).

**Fix:** `WooCommerceClient::USER_AGENT = 'MealanaERP-ShopSync/1.0'`, gesetzt via `CURLOPT_USERAGENT` in `request()` UND `ladeBildHoch()` (beide curl-Aufrufe der Klasse).

**✅ Bestätigt gelöst (2026-07-30, gleicher Tag):** Zwei echte Sync-Läufe danach durchgeführt, einer mit wieder eingeschalteter WAF -- beide komplett ohne `shop.rate_limit`/`shop.bild_sync_fehler`. Carosello (7 Artikel) und Doremi (bis auf 1 Bild, war knapp außerhalb des 20er-Batches) vollständig nachgezogen, bei WooCommerce gegengeprüft. Jacky hat dem Hosting-Support Bescheid gegeben, dass nicht weiter gesucht werden muss.

**Fazit der ganzen 429-Geschichte:** Der fehlende User-Agent war der eigentliche Auslöser (oder zumindest der entscheidende Faktor) -- nicht die WAF (die Escalating-Ban-Theorie von vorhin war vermutlich auch nur ein Nebeneffekt der häufigen Testläufe, nicht die Kernursache). Rate-Limit-Erkennung + saubere Abbruch-Logik im Code (siehe oben) bleiben trotzdem drin -- gute Absicherung für den Fall, dass es doch nochmal auftritt.

## ✅ Kategoriebild-Sync FERTIG (2026-07-29)

WooCommerce kann pro Produktkategorie ein Thumbnail anzeigen (Mega-Menü, Kategorie-Grid, Kategorieseite) — Jacky fragte danach, war vorher nicht vorgesehen. Migration 153: `kategorien.bild_pfad` + `kategorie_shops.bild_pfad_synced`/`bild_external_id` (Change-Detection wie beim bestehenden Kategorie-Text-Sync). Upload direkt im Neu/Bearbeiten-Modal (`kategorien_verwalten.php`), sofort per AJAX wie beim Artikel-Bilder-Modul (kein Extra-Klick auf Speichern), aber erst nach dem ersten Anlegen einer Kategorie verfügbar (Ordner `uploads/kategorien/{id}/` braucht eine existierende ID). `.gitignore`/`.gitattributes` um `uploads/kategorien/` ergänzt (gleiche Regel wie Artikelbilder).

**Nebenbei gefundener Bug (beim Nachbauen des Artikel-Bild-Uploads entdeckt):** `bild_upload.php` (Artikel) speichert PNG-Bilder intern mit `.png`-Endung, aber der in der DB gespeicherte Dateiname endet immer auf `.jpg` (Umbenennung passierte nur in einer lokalen Variable innerhalb der Hilfsfunktion, nie im zurückgegebenen Dateinamen) — bei jedem PNG-Upload zeigt der gespeicherte Pfad auf eine nicht existierende Datei. Bei der neuen Kategoriebild-Version von Anfang an richtig gemacht (Endung schon vor dem Verkleinern bestimmt). **Der alte Bug in `bild_upload.php` ist noch nicht gefixt** — Jacky wollte das noch entscheiden, stand zuletzt offen.

**End-to-End gegen `indra-design.at` verifiziert:** 4 Testkategorien mit Bild versehen, `cron/shop_sync.php` gelaufen, direkt bei WooCommerce nachgeprüft (nicht nur eigene DB) — alle 4 Bilder inkl. des PNG korrekt angekommen.

**Kategorie-Sortier-Bugfix dabei gefunden (siehe auch [[project_kategorie_verwaltung]]):** Beim Testen fiel auf, dass Bild-Zuweisen+Speichern eine Kategorie ans Ende der Geschwister-Liste verschob, wenn sie vorher an erster Stelle stand — Verwechslung zwischen "kein Vorgänger, weil ganz vorne" und "kein Vorgänger, weil unbekannt" in der Positions-Dropdown-Vorbelegung. Behoben.

## 🔴 Rate-Limit-Erkennung FERTIG (2026-07-29) — Ursache auf indra-design.at noch offen

Beim Testen des Kategoriebild-Syncs kam wiederholt `429 Rate limit exceeded` beim Bild-Hochladen (`/wp-json/wp/v2/media`). Erste Vermutung (zu viele Uploads zu schnell hintereinander) als Hauptursache verworfen, nachdem auch mit 0,4s-Pause zwischen Uploads (`WooCommerceClient::MEDIEN_UPLOAD_PAUSE_SEKUNDEN`, bleibt drin, schadet nicht) die Sperre weiter auftrat.

**Neue `RateLimitException`** (in `WooCommerceClient.php`, gemeinsam mit `WooCommerceClient` in einer Datei, da kein Autoloader für `src/` existiert): erkennt HTTP 429 getrennt von normalen API-Fehlern, liest `Retry-After`-Header aus. `ShopSyncService::syncShop()` bricht bei einer erkannten Sperre den kompletten Durchlauf für den Shop sofort ab (`$rateLimitiert`-Guard in allen 4 Schleifen: Kategorie-über-Artikel, Kategorie-eigenständig, Hersteller, Haupt-Artikel-Schleife) statt bei jedem weiteren fälligen Artikel erneut dagegen zu rennen — ein Eintrag `shop.rate_limit` (warn) statt hunderter `shop.bild_sync_fehler`. Gleiche Behandlung in `erstbefuellungBilderPerUrl()` (FTP-Bulk-Skript) ergänzt.

**Eigener Bug beim ersten Testlauf:** `syncBilderFuerArtikel()` hatte ihr eigenes inneres try/catch (Throwable) pro Bild (damit ein kaputtes Bild nicht die anderen blockiert) — das hat die RateLimitException mit abgefangen, bevor sie `syncShop()` erreichen konnte (Lauf dauerte weiter 2m38s statt sofort abzubrechen). Fix: `catch (RateLimitException $e) { throw $e; }` VOR dem generischen `catch (Throwable)`, damit sie gezielt durchgereicht statt geschluckt wird. Nach dem Fix: Abbruch nach 4 Sekunden statt 2m38s, sauberer Log-Eintrag mit Retry-After-Angabe.

**Diagnose-Verlauf zur eigentlichen 429-Ursache (noch nicht abgeschlossen):**
1. Erste Vermutung: komplette IP-Sperre (nginx-Header, Retry-After:3600, reiner Text statt JSON) — **widerlegt**: `/wp-json/wc/v3/...`-Aufrufe (Produkte/Varianten/Bestellungen) liefen die ganze Zeit über normal durch, exakt zeitgleich mit den 429ern auf `/wp-json/wp/v2/media`. Bestätigt über Jackys rohe Hosting-Zugriffslogs (Plesk).
2. Auffälliges Muster: **nur** `/wp-json/wp/v2/*`-Aufrufe (Media-Upload, `users/me`-Verbindungstest) scheitern, **nie** `/wp-json/wc/v3/*`. Beide betroffenen Aufrufe nutzen HTTP Basic Auth mit dem WordPress-Application-Password — `/wc/v3/` läuft dagegen über Consumer-Key/Secret im Query-String.
3. Jackys Hosting hat eine Plesk-WAF (ModSecurity 2.9, Modus war "Ein"). Test: Modus auf "Nur Erkennung" (blockiert nichts mehr, nur Logging) → **429 kam trotzdem** → ModSecurity/Plesk-WAF als Ursache **widerlegt**.
4. Aktuelle Arbeitshypothese: Rate-Limit auf **nginx-Ebene**, vermutlich vom Hosting-Provider serverseitig konfiguriert (nicht über Plesk-UI einstellbar) — evtl. eine generische Schutzregel gegen Basic-Auth-Zugriffe auf `/wp/v2/*` (Username-Enumeration-Härtung ist dafür ein bekanntes Muster, hier aber offenbar zu breit gefasst und trifft auch den Media-Upload).
5. **Jacky hat den Hosting-Support kontaktiert** (2026-07-29, konkrete Anfrage mit den beobachteten Pfaden/Codes) — **Antwort steht aus**.

**How to apply:** Bei Wiedereinstieg: zuerst nachsehen, ob vom Hosting-Support eine Antwort da ist, dann von hier weitermachen (nicht neu diagnostizieren). Die Erkennung/der saubere Abbruch im Code ist unabhängig von der eigentlichen Ursache bereits fertig und bleibt so, egal wie die Support-Antwort ausfällt.

## ✅ cron/shop_sync.php + Kategorie-Update-Sync + FTP-Bulk-Bild-Erstbefüllung + Bulk-Import-Sperre FERTIG (2026-07-22)

Vier Bau-Punkte in einer Session, ausgelöst durch den Aufbau der Gratis-Theme-Basis (siehe [[project_shop_theme]]) — beim Testen des Grundpreis-Felds kam die Frage nach der Kategorie-Beschreibung auf, danach ergab sich die ganze restliche Liste.

**Kategorie-Beschreibung** (Migration 148, `kategorien.beschreibung`) — neues Feld nur für die WC-Kategorieseite, Modal-Hinweis "wird nur im Shop angezeigt". Einziger Nutzen: Sync-Payload (`erstelleKategorie()`) schickt es als `description` mit.

**`cron/shop_sync.php`** — erster echter Auslöser (bisher nur manuelle Testskripte). Läuft beide Richtungen (`ShopSyncService::syncShop()` + `ShopBestellungSyncService::syncBestellungen()`) pro aktivem Shop, je eigenes try/catch (ein kaputter Shop blockiert nicht die anderen). Lauf-Zusammenfassung (`erfolg`/`fehler`) landet jetzt als `shop.sync_lauf`-Eintrag im Logger (`info` bei 0 Fehlern, `warn` sonst) — aber NUR bei tatsächlicher Aktivität, sonst würde die Aktivitäten-Seite beim 15-Minuten-Takt zumüllen. Empfohlenes Intervall: alle 15 Minuten.

**Kategorie-Umbenennung/Update-Sync** (Migration 149, `kategorien.aktualisiert_am` + `kategorie_shops.synced_at`) — `WooCommerceClient::aktualisiereKategorie()` neu. **🔴 Echter Fund beim End-to-End-Test:** der bestehende Kategorie-Sync lief nur "mitgeschleppt" innerhalb der Artikel-Fälligkeits-Schleife (`syncKategorieMitVorfahren()` wurde nur für Kategorien fälliger Artikel aufgerufen) — eine reine Beschreibungs-/Namensänderung ohne gerade fälligen Artikel wäre NIE nachgezogen worden, obwohl die Change-Detection selbst korrekt war. Fix: neue eigenständige `ShopSyncRepository::findFaelligeKategorien()` + zweiter, unabhängiger Durchlauf in `syncShop()`. Ohne den echten Testlauf (Cron zeigte 0/0 trotz geänderter Kategorie) wäre das unbemerkt geblieben.

**FTP-Bulk-Bild-Erstbefüllung** — Jackys Sorge: bei ~20.000 Artikeln mit je min. 1 Bild wäre der bestehende Byte-Upload-Weg (`ladeBildHoch()`, CURLFile über die VPN-Leitung) beim Erstimport eine Challenge (schon 2 Testbilder brauchten spürbar lang). Lösung: Jacky kopiert `uploads/artikel/{artikel_id}/{dateiname}` 1:1 per FTP direkt auf den WordPress-Server; `ShopSyncService::erstbefuellungBilderPerUrl()` verknüpft die Bilder dann per `images:[{src:URL, alt:...}]`-Payload an bereits existierende Produkte — WooCommerce sideloaded von der EIGENEN Domain (schnell, kein Byte-Transfer über unsere Leitung mehr). Kern-Mechanismus live verifiziert: `aktualisiereProdukt()` mit `images:[{src:url}]` liefert tatsächlich eine neue Medien-ID in der Antwort zurück.
- Voraussetzung: Artikel muss schon eine `external_id` haben (normaler Text-Sync läuft zuerst, legt alle Artikel OHNE Bilder an)
- Neue Repository-Abfrage `findArtikelMitOffenenBildernUndExternalId()` mit echter ID-Cursor-Pagination (`WHERE a.id > :letzte_id ORDER BY a.id`, nicht LIMIT/OFFSET) — wichtig, weil die wiederverwendete `findFaelligeArtikel()` (Standard-Limit 20, fürs 15-Minuten-Cron gedacht) bei tausenden Artikeln in derselben "vorderen" Auswahl hängen bleiben könnte, wenn dort dauerhaft übersprungene/fehlerhafte Zeilen sitzen
- Neues `erp/scripts/`-Verzeichnis (bisher nur `cron/` für Wiederkehrendes) — `scripts/erstbefuellung_bilder.php` als CLI-Tool: `php scripts/erstbefuellung_bilder.php <shop-slug> <bilder-basis-url>`

**Bulk-Import-Sperre** (Migration 150, `shops.bulk_import_aktiv`) — Jackys Vergleich zum JTL-Komplettabgleich ("funktioniert nur wenn der Standard-Worker aus ist, sonst grätscht der alle 15 Min. rein"): gleiches Prinzip nachgebaut. `scripts/erstbefuellung_bilder.php` setzt die Sperre selbst (try/finally, wird auch bei Fehlern wieder freigegeben), `cron/shop_sync.php` überspringt einen gesperrten Shop komplett. Bei hartem Abbruch (Strg+C) bleibt die Sperre hängen — manueller Reset per SQL im Skript-Kommentar dokumentiert. Live getestet: Cron übersprang den Shop korrekt während die Sperre aktiv war, lief danach normal weiter.

## ✅ Grundpreis-Sync-Automatisierung FERTIG (2026-07-23)

War als Nice-to-have seit 2026-07-22 vorgemerkt (siehe [[project_shop_theme]]): Germanized-Gratisversion hat "Grundpreis automatisch berechnen" mit [PRO] gesperrt, ERP berechnet den Grundpreis aber längst selbst (siehe [[project_preise]]). Lösung: fertigen Wert direkt pushen statt für PRO zu zahlen.

**Gefundene Felder** (Live-API-Introspection, OPTIONS-Request auf `/wc/v3/products`): `unit` (Einheit, `{id,name,slug}`) + `unit_price` (`{base, product, price_auto, price, price_regular, price_sale, price_html}`) — beide auch auf dem Variations-Endpunkt vorhanden. Passendes WP-Admin-Panel heißt "Preisauszeichnung" mit Feldern Einheit/Produkteinheiten/Grundpreiseinheiten (von Jacky per Screenshot bestätigt). Extra-Endpunkt `/wc/v3/products/units` liefert eine feste, vorinstallierte Einheitenliste (g/kg/m/l/... — kein "erst nachsehen dann anlegen" wie bei Attributen nötig, nur ein Name→ID-Lookup, gecacht pro Shop-Durchlauf).

**Umsetzung:** `WooCommerceClient::listeEinheiten()`. `ShopSyncRepository::findGrundpreisFelder()` (inhalt_menge/inhalt_einheit/grundpreis_bezugsmenge/grundpreis_anzeigen — nicht Teil von `findFaelligeArtikel()`, gleiches Muster wie `findEndkundenPreis()`). `ShopSyncService::baueGrundpreisFelder()` + `findeEinheitId()` — gleiche Formel wie `artikel/detail.php` (effektiver VK ÷ inhalt_menge × grundpreis_bezugsmenge), nutzt bewusst denselben `$preis` wie `regular_price` (nicht `artikel.brutto_vk` direkt), damit Grundpreis und angezeigter VK im Shop nie auseinanderlaufen. Nur bei Standalone-Artikeln/Variationen gesetzt (gleiches `empty($achsen)`-Gating wie Bestandsfelder) — ein Vater mit Achsen bekommt seinen Grundpreis nicht selbst, jede Variation ihren eigenen.

**End-to-End gegen `indra-design.at` verifiziert** (Artikel D-1059/WC-Produkt 15, das Jacky selbst schon als Grundpreis-Test angelegt hatte): errechnete Werte (100/50/7,50€ aus 3,75€ ÷ 50g × 100g) stimmten exakt mit dem manuell gesetzten Live-Wert überein. Schreibpfad zusätzlich mit einem bewusst abweichenden Testwert (base=99) verifiziert, dann korrekt zurückgesetzt — echter Rundlauf bestätigt, nicht nur zufällige Wertegleichheit.

## ✅ Dashboard: Online-Kanäle eingebunden (2026-07-23)

`dashboard.php` hatte seit dem Kasse-Umbau zwei bewusste Platzhalter ("🌐 Online — kommt mit Shop-Sync", gestreift/ausgegraut) — jetzt mit echten Daten befüllt, da Phase 3 (Bestellungen-Sync) längst steht (`auftraege.kanal='woocommerce'` + `shop_id`, Migration 067).

Neue Queries (Umsatz Heute + Monat, je pro Shop via `shop_id`-Join, storniert zählt nicht): analog zu den bestehenden Kasse-Queries. Die drei Karten "Umsatz Heute"/"Umsatz Monat" + die "Umsatz Heute nach Kanal"/"Monatsvergleich"-Detailkarten zeigen jetzt Kasse+Online kombiniert (Trend-% entsprechend auch auf Basis der kombinierten Summe), die einzelnen Kanal-Balken bleiben pro Kasse UND pro Online-Shop aufgeschlüsselt sichtbar.

Getestet mit einem temporären Test-Auftrag (`kanal='woocommerce', shop_id=1`, danach wieder gelöscht) — Query ordnet den Umsatz korrekt dem richtigen Shop zu, andere Shops bleiben bei 0.

## ✅ Hersteller-GPSR-Kontaktbeschreibung FERTIG (2026-07-22, gleicher Tag)

Jacky fand bei einem Mitbewerber (Screenshot: "ChiaoGoo"-Markenseite) ein funktionierendes GPSR-Muster: Kontaktinformation (Hersteller-Adresse) + "Verantwortliche Person"-Block direkt auf der Hersteller-Archivseite. Idee: dasselbe Muster wie die heutige Kategorie-Beschreibung, nur für Hersteller-Attribut-Terms.

**Umsetzung:** Migration 151 (`hersteller_shops.synced_at`), `WooCommerceClient::aktualisiereAttributTerm()` neu. `syncHerstellerFuerArtikel()` baut jetzt eine GPSR-Kontaktbeschreibung aus den bestehenden Hersteller-Feldern (`strasse`/`plz`/`ort`/`webseite`/`email` + `reo_*`) und schickt sie als `description` mit. Eigenständige `findFaelligeHersteller()`-Prüfung (gleiches Muster wie bei Kategorien) — Hersteller-Sync war vorher genau wie der alte Kategorie-Sync nur an Artikel-Fälligkeit gekoppelt.

**Entscheidung (Jacky, 2026-07-22):** Rechtsfrage bewusst pragmatisch als "für uns erledigt" behandelt (Mitbewerber-Vergleich zeigt entweder dieses Muster oder gar nichts). **"Verantwortliche Person"-Block nur bei Nicht-EU-Herstellern** mit ausgefüllten REO-Daten.

**Wichtiger Fund beim Bauen:** Es gab schon eine EU-Länder-Prüfung (`HerstellerService::istEuLand()`, hartcodierte 27-Länder-Konstante, inkl. desselben DROPS/Lang-Yarns-Beispiel-Kommentars wie im neuen Code!) — ursprünglich hätte ich eine zweite, eigene Prüfung über `laender.ist_eu_mitglied` gebaut. Korrigiert: nur noch die bestehende Quelle verwendet, keine zwei divergierenden EU-Listen.

**🔴 Echter Fund beim End-to-End-Test:** `<p>`/`<br>`-Tags überleben das Speichern der Attribut-Term-Beschreibung NICHT (WordPress filtert sie beim Term-Update heraus) — nur `<strong>` bleibt erhalten. Echte Zeilenumbrüche (`\n`) überleben dagegen problemlos. Format entsprechend umgebaut (Titel/Adresse mit `<strong>` + `\n`, kein HTML-Grundgerüst). Komplett gegen `indra-design.at` verifiziert: Neuanlage, Update-Sync bei Adressänderung, EU-Fall (Schachenmayr/DE) unterdrückt REO-Block korrekt, Nicht-EU-Fall (DROPS Design/NO) zeigt ihn korrekt. Alle Testdaten danach bereinigt.

## 🔍 Offen für morgen, ALS ERSTES: Separate Germanized-"Hersteller"-Funktion prüfen

**Wichtiger Fund ganz am Ende der Session (2026-07-22):** In WordPress gibt es unter "Produkte" **zwei unterschiedliche, unabhängige** Dinge, die beide "Hersteller" heißen:
1. **Produkte → Attribute → "Hersteller"** — unser eigenes, heute gebautes WC-Produktattribut (technisch identisch zu Farbe/Nadelstärke, siehe oben) — reine Filter-Facette
2. **Produkte → "Hersteller"** (eigener Sidebar-Punkt, unterhalb von "Attribute") — ein **separates Formular** mit Feldern **"Herstelleradresse"** und **"Verantwortliche Person (EU)"**, sehr wahrscheinlich von Germanized selbst bereitgestellt (passt zum gestern gefundenen "Hersteller"-Dropdown im Produkt-Editor unter "Produktsicherheit"). Liste war beim Entdecken noch komplett leer (kein Eintrag angelegt).

**Verdacht:** Das ist vermutlich die eigentlich "richtige", strukturierte GPSR-Lösung dieses Plugin-Stacks (Germanized), auf die unser heute gebauter Attribut-Beschreibungs-Hack eigentlich hätte zielen sollen. Muss geprüft werden: welche Datenstruktur/Taxonomie steckt dahinter, gibt es eine REST-API dafür (WooCommerce `/wc/v3/...` oder WordPress-Kern `/wp/v2/...`), lohnt sich ein Umstieg oder bleibt der heutige Attribut-Weg als Ergänzung bestehen.

**Jackys Entscheidung:** "Schlimmstenfalls haben wir das an 2 Stellen — kann auch nicht schaden" — kein Zwang, den heutigen Weg wieder rückgängig zu machen, falls sich die Germanized-Lösung als der bessere/zusätzliche Weg herausstellt. Als **ersten** Punkt für die nächste Session vorgemerkt, vor Grundpreis-Sync/Dashboard/Statistik/Anreicherungs-Import.

## ✅ Versionssprung + Live-Deploy FERTIG (2026-07-22, gleicher Tag)

Direkt im Anschluss doch noch gemacht — Jacky war schon per AnyDesk am Live-Server. `erp/VERSION` → 0.4.0(beta), `git archive HEAD`-Paket gebaut (geprüft: `config`/`vendor`/Uploads/Storage-Geheimnisse korrekt ausgeschlossen), auf Live entpackt, `composer install` (no-op), `php database/migrate.php` — alle 9 offenen Migrationen (142–150) sauber durchgelaufen. `migrate.php status` zeigt auf Dev UND Live identisch "141 angewendet" (150 Dateien minus 9 beim Baseline-Neuschnitt gelöschte — reine Rechnerei, kein Fehler).

**🔴 Echter Lücken-Fund beim Einrichten:** Jacky wollte in Einstellungen → Kanäle die WordPress-Zugangsdaten (`wp_username`/`wp_app_password`, für den Bilder-Upload) eintragen — es gab dafür **gar kein Formularfeld**. Migration 146 hatte die Spalten schon seit 2026-07-21, aber auf Dev wurden die Werte damals nur direkt per SQL eingetragen, nie über die UI. Ohne diesen Fix hätte Jacky auf Live gar nicht weiterkommen können. Nachgezogen in `public/einstellungen/index.php`+`speichern.php` (beide Formulare: Kanal anlegen + bestehenden Kanal bearbeiten), analog zu Consumer-Key/-Secret. Kleines Nachreich-Deploy-Paket (nur diese 2 Dateien) gebaut und übertragen.

**Verbindungstest von Live aus bestätigt** (eigenes kleines Test-Skript, da `0 erfolgreich/0 Fehler` beim ersten Cron-Lauf NICHT beweist, dass die Verbindung funktioniert — `findFaelligeArtikel()` liefert leer, solange kein Artikel diesem Shop zugewiesen ist, die WooCommerce-API wird dann gar nicht erst angefragt): sowohl WooCommerce-Consumer-Key/Secret (`systemStatus()`) als auch WordPress-Application-Passwort (`/wp-json/wp/v2/users/me`) funktionieren von Live aus einwandfrei gegen `indra-design.at`. Live-Shop hat dort zufällig `id=4` (Dev: `id=1`) — eigene Auto-Increment-Historie, unkritisch, Code arbeitet überall mit `slug`, nicht mit fixer ID.

**Entscheidung (Jacky, 2026-07-22):** Damit ist der Punkt für heute abgeschlossen. Barbara arbeitet auf Live normal weiter (Artikel/Kategorien einspielen). Ein echter Artikel-Zuweisung-Test (Kanal-Chips + kompletter Cron-Durchlauf mit echtem Sync) steht noch aus, aber nicht heute nötig.

**How to apply:** Bei Wiedereinstieg: Live ist jetzt technisch voll auf Dev-Stand (Code+DB+Zugangsdaten), nur noch kein Artikel dem Testshop zugewiesen. Nächster sinnvoller Schritt wäre ein echter Test-Sync mit einem Live-Artikel, dann irgendwann der "echte" Go-Live (wartet laut Jacky auf die Basisinventur + Kundenkommunikation, siehe [[project_roadmap_reihenfolge]]).

## ✅ Phase 4 (eingegrenzt): Bestellungen mit echten Kunden verknüpfen FERTIG (2026-07-21)

**Scope-Entscheidung (Jacky, 2026-07-21):** Nur die direkte Ergänzung zu
Phase 3 -- eingehende Shop-Bestellungen bekommen einen echten `kunden`-Datensatz
statt nur `kunden_snapshot`. NICHT Teil davon (bewusst zurückgestellt, siehe
[[project_kundendatenbank]] für das volle Szenario): ERP→Shop
WooCommerce-Account-Anlegen, DSGVO-Löschung Richtung WooCommerce, automatische
Fuzzy-Merge-Erkennung (Name/Adresse ohne exakten E-Mail-Match) -- Letzteres
bleibt bewusst manuelles Admin-Thema über `kunden_merge_queue` für später,
nicht von diesem Sync-Pfad automatisch befüllt.

**Wichtiger Fund:** Das komplette Datenmodell dafür existierte schon seit
Migration 047 (2026-06-19) -- `kunden`, `kunden_shops`,
`kunden_merge_queue`, `KundenService::anlegen()` mit fertigem
E-Mail-Hash-Duplikat-Check (`Encryption::hash()`/`findByEmailHash()`). Kein
neues Datenmodell nötig, nur die fehlende Verknüpfungslogik.

**Reihenfolge in `ShopBestellungSyncService::ermittleOderErstelleKunde()`:**
1. Schon verknüpfte WC-Kunden-ID (`kunden_shops.external_id`) -- schnellster,
   sicherster Pfad für wiederkehrende registrierte Kunden
2. Exakter E-Mail-Hash-Match (`KundenRepository::findByEmailHash()`) --
   deckt sowohl Gäste mit bekannter E-Mail als auch neue WC-Accounts ab,
   deren E-Mail schon im ERP existiert (z.B. Laden-Stammkunde bestellt erstmals
   online)
3. Neu anlegen via `KundenService::anlegen()` (`kundenherkunft='shop'`),
   danach `kunden_shops`-Verknüpfung falls eine echte WC-Kunden-ID vorhanden war

**🔴 Fünfter Fund desselben wiederkehrenden Bug-Musters** (nach
cron/mahnwesen.php, LagerService, ShopSyncService, AuftragService × 2):
`KundenService::anlegen()` hatte ebenfalls ein `Logger::log()` ohne
`benutzerId` -- gefixt (optionaler `?int $erstelltVon`-Parameter, gleiches
Muster). Die anderen `Logger::log()`-Stellen in `KundenService`
(`bearbeiten`, `adresse_anlegen` etc.) sind NICHT gefixt -- werden von diesem
Sync-Pfad nicht aufgerufen, aber bei künftiger Cron-Nutzung dort zuerst
nachsehen.

**Kleinerer Fund:** `KundenRepository::verschluesseln()` nutzt `?:` statt
`?? null` für optionale Felder (`kundengruppe_id` etc.) -- wirft PHP-Warnungen
(nicht fatal) wenn der Aufrufer diese Keys komplett weglässt statt sie explizit
auf `null` zu setzen. Nicht selbst gefixt (nicht dieser Sync-Code, sondern
bestehendes Repository-Verhalten) -- im Sync-Code stattdessen alle optionalen
Keys explizit mit `null` befüllt.

**End-to-End getestet** gegen `indra-design.at` (4 Test-Bestellungen +
1 echter WC-Testkunde): Gast mit neuer E-Mail → neuer Kunde; registrierter
WC-Kunde mit neuer E-Mail → neuer Kunde + `kunden_shops`-Verknüpfung; zweite
Bestellung desselben WC-Kunden → korrekt derselbe Kunde über die
externe ID wiederverwendet (kein Duplikat); Gast-Bestellung mit
bereits bekannter E-Mail → korrekt über E-Mail-Hash gematcht (kein Duplikat).
Kompletter Cleanup (Test-Orders + Test-WC-Kunde gelöscht, Aufträge/Kunden/
Zuordnungen/Reservierungen aus Dev-DB entfernt).

## ✅ Phase 3: Bestellungen aus WooCommerce (Polling) FERTIG (2026-07-21)

**Architektur-Korrektur gegenüber der Vorrecherche vom 2026-07-19:** Damals
"Hybrid: Webhook Echtzeit + Polling Sicherheitsnetz" geplant. Beim
Bilder-Sync (gleicher Tag) kam raus: ERP hat keinen öffentlichen Endpunkt
(VPN-only). Ein Webhook ist Push VON WooCommerce ZU uns -- geht damit nicht.
**Jackys Entscheidung: reines Polling**, exakt wie JTLs eigener
Connector-Worker auch nur in Intervallen abgleicht. Öffentlicher
Webhook-Empfänger bleibt als "Nice to have" vorgemerkt, keine Eile.

**Wichtiger Fund:** `auftraege.kanal` hatte bereits `'woocommerce'` im ENUM
und `kanal_auftrag_id` war laut Code-Kommentar explizit für die
WooCommerce-Order-ID vorgesehen (seit Migration 060) -- kein neues
Datenmodell für die Kern-Zuordnung nötig. Nur `shops.bestellungen_letzter_sync`
(Migration 147) als Polling-Cursor neu dazu (WC-REST-API-Parameter
`modified_after` live gegen die aktuelle v3-Doku verifiziert, nicht die alte
Legacy-API mit `filter[updated_at_min]`).

**🔴 Vierter Fund desselben wiederkehrenden Bug-Musters** (nach
cron/mahnwesen.php, LagerService::wareneingang(), ShopSyncService-Jarvis):
`AuftragService::anlegen()` UND `statusAktualisieren()` lasen
`$_SESSION['benutzer']['id']` direkt -- crasht ohne Session. Fix: beide
Methoden bekommen einen neuen optionalen letzten Parameter (`?int
$erstelltVon`/`?int $benutzerId`, Default weiterhin `$_SESSION` für alle
bestehenden Aufrufer unverändert). **Blieb dabei sogar EIN drittes,
verstecktes `Logger::log()` innerhalb von `anlegen()` unentdeckt**, das keinen
`$benutzerId` übergab und deshalb trotz des ersten Fixes noch gecrasht ist
(NOT-NULL-Verletzung an `aktivitaeten.benutzer_id`) -- erst beim echten
End-to-End-Test aufgefallen, nicht beim Code-Lesen. Zwei WEITERE
`Logger::log()`-Stellen mit demselben Muster (`stornieren()` Zeile ~222,
`bearbeiten()` Zeile ~429, `zahlung_buchen` Zeile ~484) sind bewusst NICHT
gefixt -- werden von diesem Sync-Pfad nicht aufgerufen, aber falls diese
Methoden mal aus einem Cron/CLI-Kontext gebraucht werden, hier zuerst nachsehen.

**`auftraege.shop_id`** wurde bisher nirgends beim Insert gesetzt (Spalte
existierte seit Migration 067, aber `AuftragRepository::insert()` band sie
nie) -- für Phase 3 jetzt ergänzt (Spalte + Platzhalter + Bindung), harmlos
rückwärtskompatibel (Default NULL für alle bestehenden Aufrufer).

**Entscheidungen (mit Jacky abgestimmt):**
- Kunde nur als `kunden_snapshot`-JSON (wie Kasse-Laufkunde), kein `kunden_id`
  -- echtes Anlegen/Abgleichen ist bewusst Phase 4 (Kunden-Merge)
- Zahlungsart: `bacs`/`cheque`→vorkasse, `cod`→nachnahme, `paypal`/`ppcp`→paypal,
  unbekannt (z.B. Stripe/Kreditkarte, aktuell nicht geplant)→Fallback vorkasse
  + Warn-Log statt Absturz
- Preise 1:1 aus WC-Line-Items übernommen (nicht aus unseren `artikel_preise`
  neu berechnet) -- der zum Bestellzeitpunkt bezahlte Preis muss eingefroren
  bleiben, passt zur bestehenden "bezeichnung/ean eingefroren"-Philosophie
- SKU ohne Treffer → Divers-Platzhalter-Artikel (99-9999, gleicher Mechanismus
  wie `KassenService::getDiversArtikelId()`)
- Bei Update einer schon importierten Bestellung: `zahlungsstatus` immer
  nachgezogen, `lieferstatus` NUR bei `cancelled`→`storniert` überschrieben
  (Rest ist unser eigener Versand-Workflow, soll nicht zurückgesetzt werden)

**End-to-End getestet** gegen `indra-design.at` (echte Testbestellung #36 via
REST erstellt, Produkt #15/SKU D-1059 = Artikel #150): Insert-Pfad
(`processing`→ bezahlt/in_bearbeitung, Reservierung angelegt), Update-Pfad
ohne relevante Änderung (`completed`→ lieferstatus bewusst unverändert),
Update-Pfad mit Zahlungsänderung (`refunded`→ zahlungsstatus korrekt auf
erstattet, lieferstatus unverändert), Stornierung (`cancelled`→ beide Status
auf storniert, Reservierung korrekt auf `erledigt` freigegeben,
`schliesseReservierungen()` wiederverwendet), Idempotenz über 5 Durchläufe
(nie ein zweiter Auftrag für dieselbe WC-Order-ID), Cursor-Mechanismus
bestätigt (nach vollständigem Sync liefert ein erneuter Poll `erfolg:0`,
keine unnötige Wiederverarbeitung). Kompletter Cleanup (Test-Order gelöscht,
Auftrag/Position/Reservierung aus Dev-DB entfernt, Cursor zurückgesetzt).

## ✅ Phase 2: Bestand/Lagerstand FERTIG (2026-07-21)

**Business-Entscheidung (Jacky, 2026-07-21):** Shop-Verfügbarkeit zählt NUR
eigene, nicht-Messe-Lager (`lager_beziehung='eigen' AND typ != 'messe'`) --
Partner-Bestand/Händler-Außenlager zählen NICHT mit (nicht ohne Weiteres aus
dem Shop heraus versandfähig). Deckt sich mit der schon dokumentierten Regel
"Messe-Lager nicht für Shops verfügbar" (`project_lager_konzept.md`) -- die
bestehenden Artikel-Listen-Queries im Admin-Bereich filtern das allerdings
NICHT (summieren über alle Lager), das ist für die interne Ansicht ok, wurde
aber bewusst NICHT für den Shop-Sync übernommen.

**Umsetzung:**
- `ShopSyncRepository::findBestandInfo()`: `gesamtbestand` nur aus
  qualifizierenden Lagern, `reserviert` = offene `reservierungen` (gleiches
  Muster wie überall sonst im Code), `hat_lagerstand` aus `artikel_typen`
  (z.B. Download-Artikel = 0 → kein Bestandsfeld im Payload, immer kaufbar)
- `ueberverkauf_erlaubt` → WooCommerce `backorders: 'notify'`, sonst `'no'`
  (WooCommerce leitet `stock_status` daraus selbst ab, kein eigenes Feld nötig)
- Bestand wird NUR gesetzt bei: Standalone-Artikel (direkt am Produkt) ODER
  Kind-Artikel (an der Variation) -- NICHT am Vater eines Variable Products
  (`manage_stock` bleibt dort `false`), weil WooCommerce Bestand bei
  Variable Products pro Variation verwaltet, nicht am Elternprodukt
- `hat_eigenen_lagerstand` (Kind bucht auf Vater-Bestand) bewusst NICHT
  extra behandelt -- Flag ist zwar in der DB, aber nirgends im System
  tatsächlich verdrahtet (nur 1 Zeile in der ganzen Dev-DB hat es auf 0),
  jeder Artikel wird darum gleich behandelt (eigener Bestand pro Zeile)

**🔴 Echter Bug gefunden + gefixt, BEVOR Jacky ihn treffen konnte:** Gleiches
Muster wie beim Bilder-Fund vorhin -- eine Lagerbuchung/Reservierung ändert
`lagerbestand.geaendert_am`/`reservierungen.geaendert_am`, NICHT
`artikel.aktualisiert_am`. Ohne Fix hätte ein längst synced Artikel bei
reiner Bestandsänderung (Verkauf, Wareneingang, neue Reservierung) NIE
nachgezogen. Fix: zwei weitere `EXISTS`-Bedingungen in `findFaelligeArtikel()`
(nur gegen qualifizierende Lager, um irrelevante Messe-Buchungen nicht
unnötig einen Resync auszulösen).

**End-to-End getestet** gegen `indra-design.at` (Test-Vater/Kind #2852/#2853,
Testbestand 15 minus Reservierung 4 = 11): Variation korrekt mit
`manage_stock=true`, `stock_quantity=11`, `backorders=no`,
`stock_status=instock`; Vater (Variable Product) korrekt `manage_stock=false`;
Nachzieh-Fall (Bestand nach Sync auf 20 geändert, kein artikel-UPDATE) durch
den Fix korrekt erfasst (→ 16 nach Reservierungsabzug); `ueberverkauf_erlaubt`
korrekt auf `backorders=notify` gemappt; `hat_lagerstand=0` (Download-Typ,
testweise umgeschaltet) liefert korrekt kein Bestandsfeld. Kompletter Cleanup
(WC-Produkt/Attribut gelöscht, alle DB-Testzeilen inkl. Lagerbestand/
Reservierung/ueberverkauf_erlaubt zurückgesetzt).

## ✅ Bilder-Sync (Vater UND Kind) FERTIG (2026-07-21)

**Wichtige technische Hürde, VOR dem Bauen geklärt:** WooCommerce kennt zwei
Wege, ein Produktbild zu setzen -- öffentliche URL (WordPress lädt selbst
runter/"sideload") oder direkter Byte-Upload. Weg 1 fällt bei uns weg: das ERP
hat laut [[project_infrastruktur]] bewusst KEINEN öffentlichen Endpunkt (nur
VPN), `indra-design.at` könnte unsere Bild-URLs also nie erreichen. Bleibt nur
direkter Upload -- der läuft aber über die WordPress-KERN-REST-API
(`/wp-json/wp/v2/media`), NICHT über WooCommerce (`/wc/v3/...`) und braucht
darum eine ZWEITE Art Zugangsdaten: ein WordPress-**Application-Password**
(Benutzername + generiertes App-Passwort), unabhängig vom bestehenden
WC-Consumer-Key/Secret. Migration 146: `shops.wp_username`/`wp_app_password`.

**Stolperstein beim Einrichten:** Jacky hatte zuerst den LABEL-Namen des
Application-Passwords ("Bildersync") als Benutzernamen geschickt -- das ist
aber nur die Bezeichnung des Credentials selbst, nicht der WordPress-Login-Name.
Richtig ist der tatsächliche Anmeldename (bei Hosting-generierten WP-Installs
oft kryptisch, z.B. `karlindra_ee1c0z1a`). Fehlerbild bei Verwechslung:
HTTP 401 "Unbekannter Benutzername".

**Umsetzung:**
- `WooCommerceClient::ladeBildHoch()` -- multipart/form-data-Upload (CURLFile)
  mit Basic-Auth (Username:App-Passwort), inkl. `alt_text` im selben Request
- `artikel_bilder_shops` (Sync-Tracking-Tabelle) existierte bereits SEIT
  Migration 045 (2026-06-19), aber komplett ungenutzt -- kein neuer Code nötig
  für das Datenmodell selbst, nur die fehlenden Repository-Methoden ergänzt
- **Keine Vater→Kind-Vererbung** (Entscheidung aus [[project_bilder_modul]]):
  jede Artikel-Zeile (Vater UND jedes Kind) hat eigene Bilder, `syncBilderFuerArtikel()`
  läuft darum mit der jeweils EIGENEN `artikel_id`, nicht mit `$vaterId`
- Produkt bekommt `images` (Plural, ganze Galerie in Positions-Reihenfolge),
  Variation bekommt `image` (Singular, nur das Hauptbild/Position 0) -- exakt
  dasselbe Singular/Plural-Muster wie schon bei `option`/`options`
- Wasserzeichen bewusst NICHT eingebaut (Feature existiert laut
  [[project_bilder_modul]] noch gar nicht) -- Bilder gehen vorerst unmarkiert
  raus, unkritisch für den Testshop, vor echtem Live-Gang nachziehen

**🔴 Echter Bug beim Testen gefunden + gefixt:** `findFaelligeArtikel()` prüfte
nur `artikel_shops`/`artikel.aktualisiert_am` auf "fällig" -- ein Bild, das
NACH dem letzten Produkt-Sync hochgeladen wird, hätte NIE nachgezogen werden
können, weil der Artikel selbst schon `synced` war und sich nicht mehr
ändert. Fix: neue `EXISTS`-Bedingung prüft zusätzlich, ob irgendein Bild
dieses Artikels noch `pending`/`error` in `artikel_bilder_shops` steht.

**End-to-End getestet** gegen `indra-design.at` (gleiches Test-Vater/Kind-Paar
#2852/#2853, Testbilder aus vorhandenen Artikel-Bildern kopiert): Vaterbild
korrekt in der Produkt-Galerie, Kindbild korrekt als Variation-Bild, Nachzieh-
Fall (Bild nach Artikel-Sync hinzugefügt) durch den Fix korrekt erfasst,
danach 0/0 im Leerlauf (kein Endlos-Retry). Kompletter Cleanup (WP-Medien +
WC-Produkt/Attribute gelöscht, DB-Testzeilen + kopierte Testbilddateien entfernt).

## ✅ Vater/Kind-Artikel (Variable Products/Variations) FERTIG (2026-07-21)

Vorher übersprang `findFaelligeArtikel()` jeden Artikel mit `vaterartikel_id`
komplett -- jetzt werden Vater UND Kind gesynct.

**Referenz-Vergleich vor dem Bau:** WooCommerce hatte bis vor sechs Wochen
KEINE native Swatch/Dropdown-Unterscheidung pro Attribut (reine Plugin-Domäne),
anders als JTL-Shop und Shopware 6, die das seit Jahren nativ haben. WC 10.9
(Beta seit 2026-06-08) bringt jetzt einen `wc-visual`-Attributtyp für
Color/Image, aber nur hinter Feature-Flag und mit undokumentierter REST-API
(kein offizieller Parameter für Swatch-Hex/Label). **Entscheidung:** `swatches`/
`dropdown`/`radiobutton` werden alle drei als normales globales WC-Attribut
gesynct (visuelle Optik ist Theme-Sache, passt zur bestehenden
"Shop-Theme erst nach dem Sync-Teil"-Entscheidung, siehe [[project_shop_theme]]).
`freitext`/`pflichtfreitext` werden bewusst NICHT als Variations-Attribut
gesynct (WC-Variationen brauchen abzählbare Werte, kein Freitext) -- unkritisch,
da aktuell keine einzige Achse mit diesem Typ produktiv einem Vater zugewiesen
ist (in der Dev-DB geprüft).

**Datenmodell:** Migration 143 -- `varianten_achsen_shops` (achse_id, shop_id,
externe_attribut_id) + `varianten_achse_werte_shops` (wert_id, shop_id,
externe_term_id), analog zum `kategorie_shops`-Muster. `artikel_shops.external_id`
enthält bei Kind-Zeilen jetzt tatsächlich die WooCommerce-**Variation**-ID (war
in Migration 142 schon als Kommentar vorgesehen).

**Wichtiger technischer Unterschied zu Kategorien:** Bei Kategorie-Duplikaten
gibt WooCommerce die ID der bestehenden Kategorie im Fehler-Body zurück
(catch-and-reuse funktioniert). Bei **Attributen** (nicht bei Terms!) macht WC
das NICHT -- deshalb "erst nachsehen, dann anlegen" (`listeAttribute()` vor
`erstelleAttribut()`), nicht Try/Catch. Bei Terms funktioniert Catch-and-reuse
zwar technisch (Fehlercode `term_exists` + `resource_id`), wurde hier aber aus
Konsistenzgründen auch auf "erst nachsehen" umgestellt (`listeAttributTerms()`
einmal pro Achse laden, nicht Try/Catch pro Wert).

**Sync-Reihenfolge:** `findFaelligeArtikel()` liefert Väter per `ORDER BY`
immer vor ihren Kindern. Ein Kind ohne bereits synced Vater wird im aktuellen
Durchlauf übersprungen (`vater_external_id` NULL → `continue`, bleibt
`pending`) -- kein Sonderfall nötig, greift beim nächsten Cron-Lauf automatisch.
`syncAchsenFuerVater()` läuft für JEDE fällige Zeile (Vater wie Kind), ist aber
pro Achse/Wert über die neuen Zuweisungstabellen idempotent.

**Wichtige Falle beim Payload:** Der Vater bekommt IMMER alle am Vater
deklarierten Werte einer Achse als `options` (nicht nur die, deren Kind gerade
shop-aktiv ist) -- sonst würde WooCommerce eine Variation ablehnen, deren Wert
nicht in der Options-Liste des Elternprodukts steht. Bei Variationen heißt das
Attribut-Feld `option` (Singular), beim Elternprodukt `options` (Plural) --
leicht zu verwechseln.

**End-to-End getestet** gegen `indra-design.at` mit echtem Vater/Kind-Paar aus
der Dev-DB (Artikel #2852 "Doremi" + Kinder #2853/#2854, Achse "Farbe"/Werte
579-584): Vater wurde korrekt als `type=variable` mit 6 Options angelegt,
beide Kinder als Variationen mit korrektem SKU/Preis/`option`-Wert, zweiter
Durchlauf (nachdem Vater eine external_id hatte) hat die zuvor übersprungenen
Kinder nachgezogen, dritter Durchlauf (Idempotenz-Check) hat nichts dupliziert.
Alles danach wieder aufgeräumt (WC-Produkt+Attribut gelöscht, Test-Zeilen aus
`artikel_shops`/`varianten_achsen_shops`/`varianten_achse_werte_shops` entfernt).

**Bewusst nicht gebaut:** Bestand/Lagerstand für Kinder (kommt mit Phase 2),
Bilder pro Kind (Vater-Stimmungsbild/Kind-Einzelbild, siehe altes Design in
`db_design_entscheidungen.md` -- eigenes Thema, noch nicht angegangen).

## Referenz-Check (2026-07-19)

- **JTL-Connector** (Jacky kennt nur JTL↔JTL-Shop, nicht JTL↔WooCommerce): vollautomatischer Echtzeit-Abgleich, **ERP ist führend** (deckt sich mit unserer Hub-and-Spoke-Entscheidung), granular pro Datentyp konfigurierbar (z.B. "nur Bestand raus, nur Bestellungen rein").
- **JTL-Lektion von Jacky**: Sync-Worker fiel nach Server-Neustart mal aus, bemerkt erst als Kunde nach seiner Bestellung fragte — reiner Monitoring-Gap. Bei uns über Logger-UI (`stufe='error'`) abgedeckt: Sync-Fehler landen sofort in Shell-Zeile + Aktivitäten-Log, nicht erst auf Kundennachfrage. **Vorgemerkt für "ganz am Ende"**: automatisierter Neustart+Health-Check des Servers nach Stromausfall/Reboot (Jackys Idee, niedrige Priorität).
- **WooCommerce-Best-Practice** (aktuelle Websuche): Hybrid-Ansatz — Webhooks für Bestellungen (Echtzeit), Polling als Sicherheitsnetz (WooCommerce deaktiviert Webhooks automatisch nach 5 fehlgeschlagenen Zustellungen, still). Webhook-Handler soll WooCommerce nicht warten lassen (Event entgegennehmen, Verarbeitung async).

## Business-Entscheidung (Jacky, 2026-07-19)

Shops sind **immer B2C**. Nur der Endkunden-Preis geht in den Shop, kein Kundengruppen-Mapping nötig für den Start. Falls später B2B-Web-Bestellungen kommen: **eigener Shop unter eigener Subdomain** statt Multi-Preis-Logik in einem Shop — passt zur bestehenden Multi-Shop-Architektur (`shops`-Tabelle unterstützt beliebig viele unabhängige Instanzen), kein Umbau nötig, nur eine neue Zeile + späteres "welcher Preis für welchen Shop"-Flag.

## Ist-Stand vs. altem Design-Dokument (`db_design_entscheidungen.md`, war 5 Wochen alt)

Die alte Design-Session skizzierte `shops`/`artikel_shops`/`kategorie_shops`/`sync_konfiguration`/`sync_log` — real umgesetzt war nur eine einfachere `shops`-Tabelle (id/slug/name/logo_pfad/sub_marke/wc_url/wc_key/wc_secret/ist_aktiv) plus das Sync-Tracking-Pattern schon zweimal woanders gebaut (`artikel_bilder_shops`, `kunden_shops`: external_id/sync_status enum pending-synced-error/synced_at/fehler_meldung). `artikel_shops`/`kategorie_shops` existierten NICHT, genauso wenig wie jeglicher Sync-Code (verifiziert per grep über src/ — nichts gefunden).

## ✅ Phase 1 Grundgerüst FERTIG (2026-07-19)

- **Migration 142**: `artikel.aktualisiert_am` (fehlte komplett — ohne das kein Change-Detection fürs Sync-Cron möglich), neue Tabellen `artikel_shops` (gleiches Pattern wie kunden_shops/artikel_bilder_shops, PLUS `aktiv`-Flag: 0 = beim nächsten Sync im Shop auf Entwurf setzen statt löschen), `kategorie_shops` (1:1 aus dem alten Design übernommen)
- **`src/modules/shop/WooCommerceClient.php`**: dünner REST-Wrapper (system_status/getProdukt/listeProdukte/erstelleProdukt/aktualisiereProdukt), Auth über Consumer-Key/Secret als Query-Parameter, curl-basiert
- **Einstellungen → Kanäle**: `wc_key`/`wc_secret`-Felder ergänzt (fehlten bisher, nur `wc_url` war editierbar) — sowohl beim Bearbeiten bestehender Shops als auch beim Neuanlegen
- **Wichtig für Kind-Artikel**: `artikel_shops` bekommt eine Zeile PRO Artikel-Zeile (auch Kind-Artikel/Varianten), nicht nur Väter — WooCommerce vergibt eigene IDs für Variable-Product UND jede einzelne Variation

## Testshop (Jacky, 2026-07-19)

WordPress+WooCommerce auf `https://indra-design.at` installiert (Haupt-, nicht Subdomain — Domain war leer, kein Problem). REST-API-Key mit Lesen/Schreiben erzeugt, in `shops.id=1` ("mealana"-Zeile, nicht als neuer Kanal — für Dev-Zwecke unkritisch, aber falls das aus Versehen war: Shop 1 wird sonst für Logo/Absender auf echten Kassenbons verwendet) eingetragen.

**Stolperstein unterwegs**: erster Verbindungstest → 404 (generische Hosting-Fehlerseite, keine WordPress-Antwort). Ursache: WordPress-Permalinks standen auf "Einfach" — REST-API (`/wp-json/...`) braucht eine "schöne" Permalink-Struktur, sonst fehlen die Rewrite-Regeln. Fix: Einstellungen → Permalinks → andere Option wählen → Speichern (erzwingt `.htaccess`-Neuschreiben). Nach dem Fix: Verbindung erfolgreich (WordPress 7.0.2, WooCommerce 10.9.4).

**Alle drei Grundoperationen live verifiziert**: GET (system_status, Produktliste — leer, kein Demo-Content), POST (Testprodukt #14 als `status=draft` angelegt, nicht öffentlich sichtbar), PUT (Preis erfolgreich geändert). Testprodukt #14 liegt noch als Entwurf auf dem Shop, kann jederzeit gelöscht werden.

## ✅ Phase 1 Sync-Logik FERTIG + End-to-End getestet (2026-07-19, gleicher Tag)

`src/modules/shop/ShopSyncRepository.php` + `ShopSyncService.php`: findet fällige Standard-Artikel (kein Vater/Kind, das kommt später), baut WooCommerce-Payload (Name/SKU/Beschreibung/Endkunden-Bruttopreis/Kategorien/Status publish-oder-draft je nach `artikel.aktiv`), POST bei erstem Sync (leere `external_id`), PUT bei Wiederholung.

**Kompletter Testlauf gegen den echten Testshop** (Artikel #150 "DROPS Baby Merino"):
1. Erst-Sync → WooCommerce-Produkt #15 neu angelegt, alle Felder korrekt (Name/SKU/Preis 3,75€/Status publish) — per GET gegengeprüft
2. Änderung simuliert (`kurzbeschreibung` geändert) → zweiter Sync → **kein neues Produkt**, dieselbe `external_id=15` aktualisiert (Update-Pfad korrekt)
3. Fehler simuliert (falsches `wc_secret`) → sauberer Fehler, `artikel_shops.sync_status='error'` + Fehlermeldung gespeichert, `aktivitaeten`-Log-Eintrag mit `stufe='error'` — genau der Fall der bei JTL nur durch Kundennachfrage aufgefallen ist, hier sofort sichtbar

**🔴 Echter Bug gefunden + gefixt:** `ShopSyncService` rief `Logger::log(..., stufe: 'error')` ohne explizite `benutzerId` auf — funktioniert nur mit aktiver Session, crasht aber (`aktivitaeten.benutzer_id NOT NULL`) in jedem Cron-/CLI-Kontext, also GENAU dem Kontext in dem der Sync später laufen soll. Gleiches Bug-Muster wie schon bei `cron/mahnwesen.php` und `LagerService::wareneingang()` (siehe [[project_installationsanleitung]]). Fix: Jarvis-ID im Konstruktor per `username='system'` auflösen, explizit an jeden `Logger::log()`-Aufruf durchreichen. **Lehre bestätigt sich zum dritten Mal:** jede neue Service-Klasse die potenziell aus einem Cron laufen könnte, braucht das von Anfang an, nicht erst wenn's das erste Mal ohne Session crasht.

## ✅ Kanal-Chips + Vater/Kind-Gating + Kanal-Filter FERTIG (2026-07-20)

Kompletter Bau + End-to-End-Test gegen echte Dev-DB (Artikel #150/#172/#251), danach aufgeräumt (Test-Isolation).

- **Einzelartikel** (`public/artikel/detail.php`): der bisher tote Actionbar-Button "Im Shop ▼" ist jetzt ein Dropdown mit einem Chip pro Shop (grün=an, grau=aus, orange="wartet auf Vater"). Klick toggelt sofort per neuem `public/artikel/kanal_ajax.php` (JSON-Body, `action=toggle`) → `ShopSyncRepository::upsertZuweisung()`. JS-Rendering in `public/js/artikel_detail.js` (`kanalToggle()`/`renderKanalPanel()`), CSS `.kanal-panel-zeile` in `components.css`.
- **Vater/Kind-Regel (Jackys Vorgabe):** Kind kann nur effektiv aktiv sein, wenn der Vater es im selben Shop auch ist; Vater aktiv erzwingt aber NICHT alle Kinder aktiv. Gelöst ganz ohne neue Spalte/kaskadierendes Überschreiben: jede Zeile (auch Kind) behält ihren eigenen `artikel_shops.aktiv`-"Wunsch", der effektive Status wird zur Laufzeit als `eigener_status AND vater_status` berechnet — neue Methode `ShopSyncRepository::findKanalStatusFuerArtikel()`. Kind-Wunsch bleibt beim kurzzeitigen Vater-Deaktivieren erhalten und greift automatisch wieder, sobald der Vater erneut an ist (verifiziert per Testskript).
- **Artikelliste** (`public/artikel/liste.php`): der schon vorhandene Platzhalter `renderShopChips()`/`.kc`-CSS ist jetzt mit echten Daten befüllt — `ArtikelRepository::findAll()` (Vater/Standalone, eigene Zuweisung) und `::findKinderFuerListe()` (Kind, mit Vater-Gating via LEFT JOIN) liefern beide ein `shop_kanaele`-Feld (`S{shop_id}`-Codes, comma-separiert). Shop-Legende unten ist jetzt dynamisch aus der `shops`-Tabelle gerendert (vorher hartcodiert S1/S2/S3 mit falscher Zuordnung zu den echten Shop-IDs).
- **Massenaktion "Kanal zuweisen"**: neuer Punkt im Aktion-Dropdown, Modal analog zum Bulk-Kategorie-Modal (ein Shop pro Durchlauf + Aktivieren/Deaktivieren-Radio, Jackys Entscheidung gegen Mehrfach-Shop-Modal). Neuer Endpunkt `public/artikel/bulk_shop_speichern.php`. Kein Propagations-Write nötig (siehe Gating-Logik oben) — Umschalten am Vater wirkt automatisch auf alle Kinder, ohne dass deren Zeilen angefasst werden.
- **Kanal-Filter in der Suchzeile**: war bisher `disabled` mit hartcodierten/falschen S1-S3-Labels — jetzt aktiv, dynamisch aus `shops`-Tabelle, filtert `ArtikelRepository::findAll()`/`::countAll()` über `EXISTS`-Check auf die eigene Vater/Standalone-Zuweisung (Kind-Prüfung nicht nötig, da ein Kind laut Gating-Regel nie effektiv aktiv sein kann wenn der Vater es nicht ist). K1/K2 (Kassen) bleiben als Optionen sichtbar aber disabled, da sie immer für alle Artikel gelten.

## ✅ `kategorie_shops` befüllen FERTIG (2026-07-20, gleicher Tag)

`ShopSyncService::syncShop()` synct jetzt vor jedem Artikel-Push dessen Kategorie(n) + alle Vorfahren nach WooCommerce (voller Pfad über `parent`, siehe `db_design_entscheidungen.md`). Neue Methoden: `WooCommerceClient::erstelleKategorie()`, `ShopSyncRepository::findKategorieIdsFuerArtikel()`/`findKategorieMitVorfahren()`/`findKategorieShopZuweisung()`/`upsertKategorieZuweisung()`.

**Live getestet** gegen `indra-design.at` mit Artikel #150s echtem 3-Ebenen-Pfad (Wolle und Garne → Hersteller → Garnstudio DROPS): alle drei Ebenen korrekt mit richtiger Eltern-Verkettung angelegt (per GET gegengeprüft), zweiter Lauf hat nichts doppelt angelegt (Idempotenz bestätigt über gespeicherte `externe_kategorie_id`), danach aufgeräumt (WC-Kategorien gelöscht, `kategorie_shops` geleert, Testshop unverändert).

**Bewusst NICHT gebaut (Jacky, 2026-07-20): Umbenennung/Update-Sync.** Aktuell reines Erstanlegen — wenn eine Kategorie im ERP umbenannt wird, zieht das NICHT automatisch in WooCommerce nach (keine `aktualisiereKategorie()`-Methode, `kategorie_shops` hat auch keine Status/Fehler-Spalten wie `artikel_shops` für Change-Detection). **Zusammen mit `cron/shop_sync.php` zurückgestellt, bis das System auf Live gespielt wird** — dann beides in einem Rutsch nachziehen, nicht vorher isoliert bauen.

## ✅ Kanal-Chips im Kategoriebaum (Sidebar) FERTIG (2026-07-20, gleicher Tag)

Letzter offener Punkt aus der alten "Kanal-Chips an Kategorien"-Entscheidung (`db_design_entscheidungen.md`, 2026-06-21) — Jacky hatte ein Mockup mit Chips im Sidebar-Kategoriebaum + kompakter Kanal-Legende darunter (nur Shops, keine Kassen, analog zur bereits bereinigten Legende in `liste.php`).

- `KategorieRepository::findAllMitEltern()`: neue Subquery liefert `eigene_shop_codes` pro Kategorie (welche Shops haben dort direkt zugewiesene, aktive Artikel)
- `ArtikelService::getKategorienBaum()` + neue private `berechneShopChips()`: rekursive Bottom-up-Vererbung — leere Elternkategorien erben von Kindkategorien, exakt wie in der alten Design-Entscheidung festgelegt, ganz ohne manuelle Pflege
- `shell_top.php` (`renderKatKnoten()`): rendert `.kc`-Chips unter jedem Kategorienamen + neue `.sidebar-kanal-legende` unterhalb des Baums (nur S1/S2/S3, dynamisch aus `shops`-Tabelle)
- Rein lesend gegen Dev-DB getestet (Artikel #150 → Garnstudio DROPS → Shop 1 aktiv): S1-Chip erscheint korrekt bei der Blatt-Kategorie und vererbt sich nach oben zu "Hersteller" und "Wolle und Garne", Geschwister-Kategorien ohne aktive Artikel bleiben leer. Von Jacky im Browser bestätigt.

## Wichtig für den Umstieg Testshop → echte Domain (geklärt 2026-07-29, noch nicht gebaut)

Jackys Frage: Dev UND Live hängen aktuell beide am selben Testshop (`indra-design.at`, Dev=shop id 1, Live=shop id 4). Wenn Dev irgendwann für den ersten echten Shop komplett fertig ist — reicht es, den Kanal/die URL auf die endgültige Domain zu "korrigieren", bleiben Bild-Verknüpfung etc. dabei korrekt?

**Antwort: Nein, nicht durch bloßes Ändern der URL auf derselben `shops`-Zeile.** Im Code bestätigt (`ShopSyncService::syncShop()`): ist `artikel_shops.external_id` bereits gesetzt, wird immer `aktualisiereProdukt($external_id, ...)` (PUT) aufgerufen, nie neu angelegt. Diese IDs (Produkt/Variation/Kategorie/Attribut-Term/Medien) sind aber fest an die konkrete WooCommerce-Installation (Testshop) gebunden — auf einer frischen/leeren finalen Domain existieren diese IDs nicht (404) oder gehören dort zu etwas komplett anderem.

**Richtiger Weg beim Go-Live auf die echte Domain:**
1. NEUE Zeile in `shops` für die echte Domain anlegen (eigene wc_url/wc_key/wc_secret/wp_username/wp_app_password) — NICHT die bestehende Testshop-Zeile umbiegen.
2. Kanal für die gewünschten Artikel auf diesen neuen Shop zuweisen (Einzeln oder Massenaktion "Kanal zuweisen") — da für diesen neuen shop_id noch keine `artikel_shops`-Zeilen existieren, ist automatisch alles "pending" → sauberer Erst-Sync (POST, frische IDs), kein Update-Konflikt.
3. Bereits vorhandene Werkzeuge für den großen Erstimport wiederverwenden: FTP-Bulk-Bild-Erstbefüllung (`scripts/erstbefuellung_bilder.php`) + Bulk-Import-Sperre (`shops.bulk_import_aktiv`) — beide wurden genau für dieses Szenario (großer Erstabgleich zu einer neuen/leeren Seite) gebaut.
4. Alte Testshop-Zeile danach deaktivieren (`ist_aktiv=0`) statt löschen, falls weiter als Testumgebung gebraucht.

**Nebenpunkt, im Hinterkopf behalten:** Solange Dev UND Live beide aktiv gegen denselben Testshop syncen, sollte auf Live kein echter Kanal für dieselben Artikel zugewiesen werden wie in Dev — sonst entstehen doppelte WooCommerce-Produkte für dieselbe Ware (unabhängige `artikel_shops`-Zeilen in getrennten DBs). Aktuell unkritisch, da laut Stand 2026-07-22 auf Live noch kein Artikel einem Kanal zugewiesen ist.

**How to apply:** Bei Wiedereinstieg in den Domain-Umstieg diesen Abschnitt zuerst lesen, nicht neu herleiten. Noch nicht gebaut/entschieden: ob das ein manueller Schritt bleibt oder ein kleines Hilfsskript/UI-Flow dafür gebaut wird.

**Entscheidung (Jacky, 2026-07-29):** Statt eines Merge-Skripts (Artikelnummer-Abgleich Dev↔Live) reicht Jacky die bisherigen Live-Änderungen von Hand in Dev nach, danach werden Artikel + Bilder nur noch in Dev nach und nach eingegeben. Dev wird damit zur alleinigen Quelle für Artikel/Kategorien/Bilder — kein automatischer Zwei-Wege-Abgleich nötig, die spätere Übertragung Richtung Live/finale Domain vereinfacht sich dadurch (kein Konflikt mit eigenständigen Live-Artikeln mehr).

## Offen für die nächste Session

1. **Echter Artikel-Test-Sync von Live** — Live ist technisch komplett bereit (Code+DB+Zugangsdaten, Verbindung bestätigt), aber noch kein Artikel dem Testshop zugewiesen. Kein aktiver Bedarf laut Jacky, kann jederzeit nachgeholt werden.
2. **Hersteller-Filter (WC-Produktattribut)** ✅ FERTIG 2026-07-21. **GPSR-Herstellerangaben** — vielversprechender Fund 2026-07-22 (Germanized-"Produktsicherheit"-Felder, siehe [[project_shop_theme]]), aber weiterhin bewusst zurückgestellt bis Jacky Rechts-Detailantworten hat, siehe [[project_hersteller_shop_filter]].
3. **Grundpreis-Sync-Automatisierung** (Nice-to-have, 2026-07-22 vorgemerkt) — ERP-Grundpreis direkt in Germanized' `Regulärer Grundpreis (€)`-Feld pushen, spart die PRO-Version. Nicht blockierend, siehe [[project_shop_theme]].
4. **JTL-Anreicherungs-Import** — eigenständige, kleinere Idee (siehe [[project_roadmap_reihenfolge]]), nicht Teil dieser Sync-Arbeit, aber gleichzeitig vorgemerkt

## Test-Rückstände (Dev-DB, harmlos aber zur Kenntnis)
`artikel_shops` hat eine echte Zeile für Artikel #150 (DROPS Baby Merino) → Shop 1, `sync_status='synced'`, `external_id=15`. Auf dem echten Testshop (`indra-design.at`) liegen dadurch zwei echte Produkte: #14 (Entwurf, reiner REST-Client-Test) und #15 (veröffentlicht, aus dem Sync-Testlauf, mit echten MeaLana-Artikeldaten). Beide können gelöscht werden, sobald nicht mehr als Referenz gebraucht.

**How to apply:** Bei Wiedereinstieg diese Datei UND `db_design_entscheidungen.md` (Abschnitt "Multi-Shop-Architektur"/"WooCommerce Kategorie-Sync") zusammen lesen — letztere hat die inhaltlichen Design-Entscheidungen (Achsen→Variations-Mapping etc.), diese Datei den tatsächlichen Baufortschritt.
