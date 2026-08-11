---
name: project-jtl-kunden-auftraege-import
description: "JTL-Kunden+Aufträge-Archiv-Import KOMPLETT FERTIG 2026-08-11 auf Dev-DB: 6.775 Kunden + 39.191 Archiv-Aufträge (01.09.2013–11.08.2026), Dedup/Idempotenz über zwei Export-Größen verifiziert, 4 echte Bugs gefunden+gefixt; nur Browser-Test von Jacky + Live-Deploy noch offen"
metadata: 
  node_type: memory
  type: project
  originSessionId: d1aef7f2-7ecd-41ea-b428-cf0b1d692643
  modified: 2026-08-11T19:30:37.049Z
---

## Ausgangslage (Jacky, 2026-08-10)

Zwei neue JTL-Exporte in `import/` abgelegt: `JTL-Export-Kundendaten-10082026.csv` und `JTL-Export-Aufträge-10082026.csv`. Wunsch: erstmal nur schauen, ob sich damit vernünftig arbeiten lässt ("Gewaltakt oder nicht"), der eigentliche Import kommt separat später. Wichtige Anforderung dabei: importierte Alt-Aufträge müssen im ERP klar als "Archivdaten" erkennbar sein, nicht mit echten neuen ERP-Aufträgen verwechselbar.

## Machbarkeits-Check (Claude, 2026-08-10) — Ergebnis: unproblematisch

**Kundendaten** (6.775 Zeilen): klassisches flaches JTL-Format (~50 Spalten), Windows-1252/ISO-8859-1-kodiert — der bestehende `JtlCsvReader` (aus dem Artikel-Import, siehe [[project_jtl_import]]) liest es direkt korrekt ein, kein neuer Parser nötig. Nur **2 von 6.775** sind schon als ERP-Kunde vorhanden (E-Mail-Hash-Abgleich gegen die echte Live-Verschlüsselung getestet) — ERP hat aktuell nur 3 Testkunden insgesamt, der Rest wäre komplett neu.

**Aufträge** (12.174 Positionszeilen, eine Zeile pro Artikel-Position wie beim Artikel-Import): **4.372 einzelne Bestellungen** von 312 verschiedenen Kunden, Zeitraum 01.01.2025 bis 10.08.2026 (aktueller Export-Tag). Verknüpfungsschlüssel zwischen beiden Dateien ist die Spalte "Kundennummer" (Format `Kd-XXXX`) — passt zu **100%** (alle 312 in den Aufträgen vorkommenden Kundennummern finden sich in der Kundendatei; NICHT die `Debitorennummer`-Spalte, die matcht nur zu 12/312). **0 der 4.372 Auftragsnummern existieren schon im ERP** (`auftraege.auftrag_nr`) — kein Duplikat-Risiko mit den bisherigen WooCommerce-Sync-Bestellungen.

## Archiv-Markierung — technisch einfacher Weg gefunden

`auftraege.kanal` ist aktuell ein ENUM (`woocommerce`/`manuell`/`kasse`). Ein vierter Wert `jtl_archiv` wäre eine kleine Migration. Die Kanal-Chip-Anzeige + der Kanal-Filter in der Auftragsliste existieren bereits (Session 2026-07-21, siehe CLAUDE.md "Kanal-Anzeige in Auftragsliste") — bräuchten nur einen weiteren `match`-Fall für `jtl_archiv` (z.B. "Archiv"-Chip statt Shop-Logo). Keine neue UI-Infrastruktur nötig, nur ein Enum-Wert + ein Chip-Fall.

## Offene Design-Fragen für den eigentlichen Import (bewusst noch nicht entschieden)

- **Artikelnummer-Matching in den Bestellpositionen:** Jacky schätzt die Trefferquote als hoch ein (Artikelnummern kommen aus derselben JTL-Quelle wie der bereits gelaufene Artikel-Import, nur wenige geändert). **Bekannte Lücke:** Jacky hat beim Artikel-Import nur AKTIVE Artikel übernommen — Bestellpositionen mit inzwischen aus dem Shop entfernten/inaktiven Artikeln hätten daher keine Entsprechung in unserer `artikel`-Tabelle. Erwartung: überschaubare Liste, kein Showstopper.
  - **Claude-Vorschlag, noch nicht abgestimmt:** für nicht auffindbare Artikelnummern das bereits im Kassen-Modul etablierte Freitext-/Divers-Artikel-Muster wiederverwenden (Platzhalter-`artikel_id`, echter Name/Preis als Freitext auf der Position) — dieselbe Lösung, die schon für "Artikel ohne Stammdaten" beim Verkauf existiert. Würde das Problem unabhängig von der Zeitspanne des Imports abfangen.
- **Kunden-Dedup:** wie werden die 312 Bestell-Kunden (die alle auch in den 6.775 Kundendaten stecken) mit künftigen ECHTEN WooCommerce-Sync-Kunden zusammengeführt? Gleiches Muster wie das bereits bestehende `ShopBestellungSyncService::ermittleOderErstelleKunde()` (externe_id → E-Mail-Hash → neu anlegen) sollte direkt wiederverwendbar sein.
- **Preise/Steuersätze historisch einfrieren**, nicht neu berechnen — analog zum bereits laufenden Bestellungs-Sync (Bestellzeitpunkt-Preis muss eingefroren bleiben, siehe [[project_shop_sync]]).

## Offene Scope-Frage: längerer Zeitraum importieren?

Jacky hat überlegt, ob ein LÄNGERER Auftrags-Export (mehr als der aktuelle 01.01.2025–heute-Zeitraum) sinnvoll wäre — mit der Einschränkung, dass ein längerer Zeitraum vermutlich mehr "Artikel nicht gefunden"-Fälle bringt (mehr Zeit = höhere Wahrscheinlichkeit, dass ein referenzierter Artikel inzwischen aus dem aktiven Sortiment geflogen ist).

**Claude-Einschätzung (noch nicht mit Jacky final abgestimmt):** Der Freitext-Fallback oben würde das Problem unabhängig von der Zeitspanne abfangen — spricht also grundsätzlich nichts gegen einen längeren Export, wenn der Wert (vollständigere Kundenhistorie) das für Jacky rechtfertigt. Endgültige Entscheidung erst wenn der Import konkret geplant wird, nicht vorher recherchieren.

**How to apply:** Dies ist NUR eine Machbarkeitsprüfung, kein Code wurde geschrieben. Eigenständiges Thema, das NACH den "Fünf Abendaufgaben" ([[project_fuenf_abendaufgaben_0809]]) drankommt, sobald Jacky den eigentlichen Import anstoßen will — dann hier weiterlesen statt neu zu analysieren, insbesondere die Zahlen (100% Kundennummer-Match, 0% Auftragsnummer-Überschneidung, 2/6775 Kunden-Überschneidung) als Ausgangspunkt nehmen.

## Design-Entscheidungen für den eigentlichen Import (2026-08-11, noch nicht gebaut)

**Idempotenz/Dedup fürs erneute Ziehen kurz vor Live-Gang:** JTL-Kundennummer (`Kd-XXXX`) und JTL-Auftragsnummer müssen als Referenzfelder am Kunden- bzw. Auftrags-Datensatz mitgespeichert werden (analog zum bestehenden externe_id/E-Mail-Hash-Muster in `ShopBestellungSyncService::ermittleOderErstelleKunde()`). Damit erkennt ein zweiter Import-Lauf bereits vorhandene Zeilen und legt nur neue an — muss von Anfang an mit eingeplant werden (Migration), nicht nachträglich reparierbar ohne Datenverlust-Risiko.

**Auswirkung auf bestehende Umsatz-Berechnungen (im Code verifiziert):**
- `KundenRepository::findStatistik()` (Kunden-Detailseite) hat KEINEN Kanal-Filter, nur `lieferstatus != 'storniert'` — Archiv-Aufträge (`kanal='jtl_archiv'`) laufen automatisch in Bestellanzahl/Umsatz/letzte Bestellung mit, sobald korrekt verknüpft und `lieferstatus` ≠ storniert.
- `StatistikRepository` (Dashboard/Auswertungen) kennt in `kanalBedingung()` nur `kasse`/`woocommerce`/`manuell`. Gesamt-Summen zählen Archiv-Umsatz automatisch mit, aber die Kanal-Aufschlüsselung in `findUmsatzZeitverlauf()` (Balken Kasse/Online/Manuell) hat keinen vierten Fall für `jtl_archiv` — würde eine Lücke zwischen Summe und Einzelkanälen erzeugen. Muss beim Bauen ergänzt werden (vierter Bucket).

**Archiv-Umfang (Jacky, 2026-08-11):** EIN durchgehendes Archiv (`kanal='jtl_archiv'`), nicht mehrere Zeitscheiben — offizieller JTL-Start war **01.09.2013**, das ist der früheste sinnvolle Datumshorizont für den großen Export.

**Testablauf vor dem echten Import, von Jacky vorgeschlagen (2026-08-11):**
1. Kleiner Export (z.B. letzte 18 Monate) → importieren → prüfen ob Kunden-Match/Archiv-Kennzeichnung/Umsatz-Anzeige korrekt.
2. Großer Export mit allem seit 01.09.2013 → nochmal importieren → prüft ob die Dedup-Logik (siehe oben) die bereits importierten 18-Monats-Aufträge korrekt erkennt/überspringt und nur die älteren wirklich neuen hinzufügt.

Kundendaten-Export gilt laut Jacky bereits als vollständig (keine weitere Abgrenzung nötig wie bei den Aufträgen).

## Status 2026-08-11: Daten für Schritt 1 bereit

Kundendaten-Export + der 18-Monate-Aufträge-Export liegen schon in `mealana/import/` (`JTL-Export-Kundendaten-10082026.csv`, `JTL-Export-Aufträge-10082026.csv` — identisch mit den Dateien aus dem Machbarkeits-Check, Zeitraum 01.01.2025–10.08.2026, also die ~18 Monate). Der große Voll-Export seit 01.09.2013 läuft bei Jacky im Hintergrund auf JTL-Seite, dauert noch — Schritt 2 des Testplans (Dedup-Test) muss darauf warten.

## ✅ Schritt 1 GEBAUT + GELAUFEN 2026-08-11: 18-Monats-Import komplett, Dev-DB

Direkt gebaut (Jackys Wunsch, kein Trainer-Ansatz), Plan-Mode mit 3 parallelen Explore-Agents zur Recherche (Kasse-Freitext-Muster, Auftrags/Kanal-Schema, Kunden-Dedup/Krypto-Muster) vorab.

**Neue Dateien:**
- `erp/database/migrations/164_jtl_archiv_import.sql` + `165_jtl_kundennummer_referenzen.sql`
- `erp/src/modules/import/JtlArchivImportService.php` (Kern-Service, zwei Methoden `importiereKunden()`/`importiereAuftraege()`)
- `erp/scripts/jtl_archiv_import_kunden.php` + `jtl_archiv_import_auftraege.php` (CLI, `--dry-run`-fähig, Muster wie `backfill_uvp.php`)

**Geänderte Dateien:** `AuftragRepository.php` (neue `insertArchiv()`), `KundenRepository.php` (neue `findByJtlKundennummer()`/`addJtlReferenz()`), vierter Kanal-Fall `jtl_archiv`/"Archiv" in `auftraege/liste.php`, `detail.php`, `statistik.php`, `StatistikRepository.php` (inkl. neuer `umsatz_archiv`-Bucket in `findUmsatzZeitverlauf()`), `kasse/ajax_auftrag_laden.php` (Archiv-Aufträge von der Kassen-Retourensuche ausgeschlossen).

**Zwei echte Bugs beim ersten Lauf gefunden + gefixt, bevor der Voll-Export drüberläuft:**
1. **`kunden.jtl_kundennummer` als einzelne UNIQUE-Spalte war falsch angelegt** — mehrere alte JTL-Kundennummern zeigen teils auf denselben Menschen (gleiche E-Mail, mehrere Alt-Datensätze aus verschiedenen JTL-Jahren). Eine 1:1-Spalte führte beim wiederholten Import zu Flip-Flopping (letzter gesehener Wert überschreibt den vorherigen) statt sauberem "schon vorhanden, überspringen". Migration 165 hat das durch eine echte n:1-Zuordnungstabelle `kunden_jtl_referenzen` ersetzt (mehrere JTL-Nummern → ein `kunde_id`, `jtl_kundennummer` bleibt der UNIQUE-Dedup-Schlüssel). Nach dem Fix: zweiter Lauf zeigt sauber 0 neu / alle "bereits vorhanden" — Idempotenz bestätigt.
2. **6 Kunden mit kaputter E-Mail in JTL-Rohdaten** (`-`, Tippfehler wie Leerzeichen/Komma/Semikolon statt @/.) ließen `KundenService::validiere()` scheitern und blockierten die ganze Kundenanlage. Fix: ungültige E-Mail wird jetzt als "keine E-Mail" behandelt (nur geloggt), Kunde wird trotzdem angelegt — Dedup läuft ja über `jtl_kundennummer`, nicht zwingend über E-Mail.
3. **Kleinerer Nebenfund:** Dry-Run zeigte 373 falsche "keine gültigen Positionen"-Fehler, weil Platzhalter-Artikel im Dry-Run bewusst nicht angelegt wurden und dadurch ganze Positionslisten leer liefen. Gefixt: Dry-Run zählt jetzt korrekt mit (Fake-ID statt Skip), ohne echte Inserts zu machen.

**Ergebnis (Dev-DB, nach beiden Fixes):**
- Kunden: 6.563 neu angelegt, 212 per E-Mail zu Bestandskunden nachverknüpft (echte JTL-Datenqualität: mehrere alte Kundennummern für dieselbe Person), 0 Fehler. Zweiter Lauf: 0 neu, alle 6.775 korrekt übersprungen.
- Aufträge: 4.372 neu angelegt (0 Angebote gefiltert, 0 Fehler), 164 Platzhalter-Artikel automatisch angelegt (`JTL-<Original>`, inaktiv). Zweiter Lauf: 0 neu, alle 4.372 korrekt übersprungen.
- Stichproben-Verifikation direkt gegen die Repository-Methoden (kein Browser-Zugriff in dieser Session): Auftragsdetail mit Platzhalter-Position lädt korrekt (`AuftragRepository::findPositionen()`/`findById()`), Kunden-Statistik (`findStatistik()`) zieht Archiv-Umsatz/letzte-Bestellung automatisch mit rein wie erwartet, `StatistikRepository::findTopseller()` mit `kanal='archiv'` funktioniert.

**Noch offen:**
- **Jackys Browser-Test steht aus** (Auftragsliste-Archiv-Chip/Filter, Kunden-Detail-Umsatz, Statistik-Seite "Nur Archiv").
- **Schritt 2 (Dedup-Stresstest):** sobald der große Voll-Export seit 01.09.2013 fertig ist, dieselben zwei Skripte nochmal drüberlaufen lassen — muss die 18-Monats-Daten korrekt überspringen und nur die älteren wirklich neu hinzufügen. Beide Skripte sind resume-fähig (mehrfach mit `import/JTL-Export-*.csv` aufrufbar), das ist jetzt echt getestet, nicht nur behauptet.
- Live-Deploy (Migrationen 164+165 + Code) noch nicht gemacht, dies war nur der Dev-Testlauf.

## ✅ Nachfund 2026-08-11 (nach dem 18-Monats-Lauf): Download-Aufträge fälschlich "ausstehend"

Jacky bemerkte beim Draufschauen: unter den 159 archivierten Aufträgen ohne `Datum Zahlungseingang` in JTL waren Shop-Aufträge stark überrepräsentiert (32% der offenen Liste, obwohl nur 7% aller Archiv-Aufträge). Beim genaueren Hinsehen (Stichprobe von 12 Aufträgen exportiert) stellte sich heraus: JTL hatte bei diesen Aufträgen nicht nur das Datum, sondern auch Zahlungsart UND Betrag komplett leer gelassen — kein Parsing-Fehler auf unserer Seite (0 Datumswerte scheitern am Parser, direkt verifiziert). Jackys Erklärung: das sind überwiegend **Download-Artikel mit 0,00€/0,01€ Bruttobetrag** — JTL hat bei diesen nie eine Zahlung nachgetragen, sie gelten aber inhaltlich als erledigt.

**Bestätigt:** 64 von 159 offenen Archiv-Aufträgen hatten `bruttobetrag` 0,00 oder 0,01 — Positionen mit `artikeltyp_id=4` (DOWNLOAD) dominieren diese Stichprobe.

**Fix:**
- `JtlArchivImportService::importiereAuftraege()`: Aufträge mit `bruttobetrag` zwischen 0,00 und 0,01€ gelten jetzt automatisch als `zahlungsstatus='bezahlt'` (auch ohne `Datum Zahlungseingang`), `bezahlt_am` fällt auf `Auftragsdatum` zurück falls kein echtes Zahlungsdatum da ist. Greift automatisch beim großen Voll-Import mit.
- Rückwirkend per direktem SQL-Update auf die bereits importierten 4.372 Aufträge angewendet: 64 aktualisiert, `ausstehend` sank von 159 auf **95**.

**Noch offen:** die verbleibenden 95 "ausstehend"-Aufträge sind noch nicht einzeln durchgesehen — plausibel als echte offene/unbezahlte historische Bestellungen, aber nicht verifiziert. Falls beim großen Voll-Export ein ähnliches Muster aus einer anderen Ursache auftaucht, hier zuerst nachsehen.

## ✅ Schritt 2 GEBAUT + GELAUFEN 2026-08-11: großer Voll-Export seit 01.09.2013, Dedup-Test bestanden

Jacky hat `Komplettexport-Aufträge-11082026.csv` (168.565 Zeilen, 39.202 distinct Aufträge, 92 MB) bereitgestellt — deutlich mehr Wertevielfalt bei Zahlungsart/Positionsart als im 18-Monats-Fenster, deshalb vor dem echten Lauf noch zwei technische Anpassungen:

1. **`ZAHLUNGSART_MAP` erweitert** (`JtlArchivImportService.php`) — der Voll-Export brachte viele neue Zahlungsart-Werte (Überweisung/SofortÜberweisung-Varianten, Gutschrift/Gutschein/Guthaben, Umbuchung/Rücküberweisung, Kreditkarte, "Nachnahme AT" etc.), die vorher alle auf den 'rechnung'-Fallback gelaufen wären. Jetzt sauber auf vorkasse/bar/gutschein/nachnahme/paypal/gemischt gemappt.
2. **`memory_limit` auf 4096M gesetzt** (`jtl_archiv_import_auftraege.php`) — `JtlCsvReader::lese()` hält die komplette Datei als Array im Speicher, 92 MB CSV brauchten beim Test ~1,7 GB RAM, das PHP-CLI-Default (1024M) reichte nicht.

**Dedup-Test (der eigentliche Zweck von Schritt 2) — bestanden:** Dry-Run gegen den großen Export erkannte **4.371 von 4.372** bereits importierten 18-Monats-Aufträgen korrekt als "bereits vorhanden" und hätte sie übersprungen. Der eine fehlende (`A-40266072026`) taucht im großen Export schlicht gar nicht auf (Zeitpunkt-Differenz zwischen den beiden JTL-Exporten, kein Bug) — blieb unangetastet in der DB.

**Echter Lauf:** 34.819 neue Aufträge angelegt, 4.371 korrekt übersprungen, 0 Fehler. Datenbank-Endstand:
- **39.191 Archiv-Aufträge gesamt**, Zeitraum 01.09.2013–11.08.2026 (genau der gewünschte volle Zeitraum)
- Zahlungsstatus: 38.641 bezahlt / 550 ausstehend (nur 1,4% offen — die Download-Kleinstbetrag-Regel aus dem vorigen Fund griff automatisch mit, 0 der 550 verbleibenden haben 0,00/0,01€ Bruttobetrag, keine erkennbare weitere systematische Ursache)
- 3.803 Platzhalter-Artikel (`JTL-<Original>`) automatisch angelegt
- 6.260 distinct Kunden mit mindestens einem Archiv-Auftrag verknüpft

**Damit ist der komplette JTL-Kunden+Aufträge-Archiv-Import fertig und funktionsfähig**, inkl. bestätigter Idempotenz über zwei unabhängige Export-Größen hinweg. Browser-Test durch Jacky steht noch aus (nicht blockierend). Live-Deploy (Migrationen 164+165 + Code) noch nicht gemacht.

## ✅ Nachfund 2026-08-11 (Muster bei den 550 offenen Aufträgen): LS-POS/JTL-Systembruch, nicht echte offene Zahlungen

Jacky wunderte sich, dass manche Aufträge 4.695+ Tage "offen" sind. Analyse der 550 `ausstehend`-Aufträge (CSV-Zusatzfelder Shop/Zahlungsart/Externe Belegnummer gegen `Komplettexport-Aufträge-11082026.csv` gejoint) zeigte ein klares Muster:
- **79% Zahlungsart "Barzahlung"** (432/550), **57% älter als 5 Jahre** (313/550), nur 10 jünger als 30 Tage.
- Jackys Erklärung: JTL und LS-POS (Kassensystem) sind zwei getrennte Produkte ohne durchgehenden Rückkanal — im Shop mit Abholung bestellt + an der Kasse bezahlt, oder manueller JTL-Auftrag + Zahlung über LS-POS erfasst. Die Zahlung ist real passiert, wird aber im JTL-Auftragsfeld nie nachgetragen.

**Export (aktualisiert):** `D:\ERP\mealana\exports\jtl_archiv_offene_zahlungen_2026-08-11.csv` — Auftrag_Nr, Datum, Alter_Tage, Kunde, Betrag, Zahlungsart_ERP, Zahlungsart_JTL, Shop, Externe_Belegnr, Versandart, Steuerbehandlung, nach Alter absteigend sortiert.

**Jackys Entscheidung (2026-08-11, erster Schritt, nicht als generelle Import-Regel im Code eingebaut):** Alle offenen Archiv-Aufträge **älter als 365 Tage** direkt per SQL auf `zahlungsstatus='bezahlt'` gesetzt (`bezahlt_am` fällt auf `erstellt_am` zurück) — 481 von 550 betroffen. Verbleibend: **69 offene Aufträge** (alle aus den letzten 12 Monaten). Die Liste der letzten 12 Monate hat Jacky an Babsi zur genaueren Durchsicht gegeben — viele davon vermutlich ebenfalls längst erledigt, aber bewusst noch nicht automatisch angepasst.

**Bewusst NICHT gemacht:** Die "Barzahlung ohne Zahlungseingang"-Erkenntnis wurde NICHT als dauerhafte Regel in `JtlArchivImportService` eingebaut (anders als der Download-Kleinstbetrag-Fix) — war explizit Jackys manueller Ad-hoc-Cleanup-Schritt "fürs erste", noch keine abschließende Richtlinie. Erst nachfragen/umsetzen, falls Jacky das als generelle Regel bestätigt.

**How to apply:** Bei künftigen Datenqualitäts-Fragen zu offenen JTL-Beträgen: Alter + Zahlungsart als erste Analyse-Dimension nehmen (Barzahlung + hohes Alter = fast sicher LS-POS/JTL-Synclücke, kein echtes Problem). Die verbleibenden 69 (<365 Tage) sind der eigentlich interessante Rest.

## ✅ Nebenbei erledigt 2026-08-11: JTL "Eigener Export" — Lieferanten-EK-Preise + Lagerbestand Ladengeschäft

Anderes Thema als der Kunden/Aufträge-Import, aber gleicher Tag/gleiche Quelle (JTL-Wawi-Export). Jacky wollte aus einem selbst konfigurierten JTL-Export (`import/JTL-Export-Eigener Export-11082026.csv`, 26.567 Zeilen, 58 Spalten) Lieferanten-EK-Preise und Lagerbestand/Charge fürs Ladengeschäft ziehen.

**Wichtige Erkenntnis aus der Datei:** "Warenlager" war bei 86% der Zeilen leer (nur 3.588 "Ladengeschäft" + 1 "Mealana-Hauptlager") — der Export deckt NICHT das Hauptlager ab. Jackys Entscheidung: Warenlager-Spalte ignorieren, alle Bestände aus dieser Liste pauschal auf `lager_id=1` (Ladengeschäft) buchen.

**Neuer Lieferant vs. Hersteller-Verwechslung:** "Hersteller-Preise" aus Jackys ursprünglicher Frage stellten sich als Lieferanten-EK-Preise heraus (JTL trennt das nicht) — bestätigt, richtig eingeordnet in `artikel_lieferanten`, nicht `hersteller`.

**8 neue Lieferanten angelegt** (mit Jacky einzeln durchgesprochen, Details/Notizen direkt am Lieferanten-Datensatz):
- Aktiv: Lang Garn & Wolle GmbH (deutscher LangYarns-Lieferant/Dropshipping), Witzgall GmbH & Co KG
- Inaktiv (Statusgrund in `interne_notizen`, nur für historische EK-Daten): Rosy Green Wool (verkauft an Pascuali, dort noch keine Bestellung), Wollgarnspinnerei Ferner GmbH (nicht mehr im Sortiment), Marianne Hobby (insolvent), Venne (verkauft), A. Hausmann GmbH (insolvent), Elizza (verstorben)
- "Strickimicki / HEITERE AUSSICHTEN - Jens Aldag" → mit bestehendem Lieferanten "Strickimicki" (id=33) verknüpft, kein neuer Datensatz
- "Mineraliengroßhandel.com" (1 Zeile) → bewusst ignoriert

**Neues Skript:** `erp/scripts/jtl_eigener_export_import.php` (`--dry-run`-fähig, idempotent per Upsert). Zwei Ziele pro Zeile: `lagerbestand` (immer `lager_id=1`, NULL-sicherer Abgleich über `artikel_id+lager_id+charge <=>`, da MySQLs UNIQUE-Constraint NULL-Chargen sonst nie als gleich behandelt) und `artikel_lieferanten` (EK-Preise/VPE/Lieferzeit, `standard_lieferant` wird nur gesetzt wenn der Artikel noch KEINEN anderen Standard-Lieferanten hat — überschreibt nie eine bestehende Marge-relevante Zuordnung).

**Ein echter Bug beim ersten Lauf gefunden+gefixt:** `array_merge()` reichte bei UPDATE-Statements zu viele Parameter (`aid`/`lid` waren im INSERT aber nicht im UPDATE enthalten) — PDO warf `SQLSTATE[HY093]`. Gefixt durch gezieltes Herausfiltern der pro Statement tatsächlich gebrauchten Keys. Skript ist idempotent, der Neustart nach dem Fix hat sauber ergänzt statt dupliziert (keine Datenkorruption durch den Teil-Lauf vorher).

**Ergebnis:** 20.974 Lagerbestand-Zeilen für Ladengeschäft (40.333,6 Stück gesamt, 1.442 mit Charge), 17.702 Lieferanten-EK-Verknüpfungen. 5.608 von 26.492 distinct Artikelnummern hatten keinen Treffer in unserer `artikel`-Tabelle — Jacky bestätigt: uninteressant, das sind vermutlich inaktive/ausgelistete JTL-Altartikel ohne relevanten Bestand/EK.

**How to apply:** Eigenständiges Thema, nicht Teil des Kunden/Aufträge-Archivs. Falls nochmal ein "Eigener Export" mit denselben 58 Spalten kommt, `jtl_eigener_export_import.php` direkt wiederverwenden (ist idempotent) — `LIEFERANT_MAP_OVERRIDES`/`LIEFERANT_IGNORIEREN` im Skript ergänzen, falls neue unbekannte Lieferantennamen auftauchen.

**Geplante Wiederholung vor Go-Live (Jacky, 2026-08-11):** Jacky zieht denselben "Eigenen Export" nochmal kurz vor dem Live-Gang — als Basis für die **Startinventur** (siehe [[project_inventur_konzept]]). Das Skript ist idempotent, kann also direkt nochmal drüberlaufen. Wichtig: bis dahin ändert sich der Lagerbestand im Ladengeschäft natürlich weiter (Verkäufe/Wareneingänge) — der zweite Export ersetzt/aktualisiert dann die hier importierten Werte, keine Neuentscheidung zum Vorgehen nötig, nur der neue CSV-Pfad.

**How to apply:** Falls nochmal ein JTL-Export importiert werden soll (z.B. laufende Aktualisierung), diese Datei zuerst lesen. Alle vier gefundenen Probleme sind behoben (n:1-Kundennummer-Mapping, ungültige E-Mails, Download-Aufträge fälschlich ausstehend, Zahlungsart-Abdeckung). Bei Auffälligkeiten in der Zahlungsstatus-Verteilung zuerst nach Bruttobetrag/Artikeltyp gruppieren — war beide Male der schnellste Weg zur echten Ursache.
