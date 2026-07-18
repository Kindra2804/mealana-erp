---
name: project-buchhaltung
description: "Buchhaltungsmodul-Planung: DATEV-Schnittstelle, Kontenplan, Österreich-spezifisch; Dashboard Card 5 aktivieren!"
metadata: 
  node_type: memory
  type: project
  originSessionId: 2201806f-a656-4f8c-9f4f-9cf04a3cdd71
  modified: 2026-07-18T10:24:07.629Z
---

Stand: 2026-07-01, Bestandsaufnahme aktualisiert 2026-07-10, Umsetzung begonnen 2026-07-17

## 🟢 FERTIG 2026-07-17: Kontenplan + Debitoren + Kreditoren

- **Migration 126** (`kontenplan`-Tabelle): kontonummer/name/typ(erloes|aufwand|steuer|bank|kasse)/aktiv. Befüllt mit den 11 bestehenden Artikelgruppen-Konten (4000-4900, typ=erloes) + 4 Platzhalter-Kernkonten. **Achtung:** die 4 Kernkonten (1500/1600/2500/2700) waren nur meine generische Annahme (Österreichischer Einheitskontenrahmen) — Babsis echte Antwort (siehe unten) widerspricht dem teilweise (Kassa ist bei ihr 2700, nicht 1500!). Diese 4 Zeilen in `kontenplan` müssen noch korrigiert werden, sobald die echten Nummern feststehen.
- **Migration 127**: `kunden.debitorennummer` (automatisch generiert, Formel `'2' + 5-stellige Kundennummer`, z.B. KD-00001 → 200001; `KundenService::anlegen()` generiert es live mit, `KundenRepository::insert()`/`findById()` angepasst; sichtbar in `kunden/detail.php` neben Kundennummer). `lieferanten.kreditorennummer` (manuell, kein Auto-Generate — bewusste Design-Entscheidung, siehe unten).
- **Neue Seite** `buchhaltung/kreditoren.php` + `kreditoren_speichern.php`: Liste aller Lieferanten mit editierbarem Kreditorennummer-Feld, ein Speichern-Button für alle Zeilen. Nav-Link in `shell_top.php` ergänzt.
- **Kontenplan-Verwaltungsseite (Liste/CRUD wie Artikelgruppen) ist noch NICHT gebaut** — Jacky hat explizit gesagt, die brauchen wir aber auf jeden Fall noch.

**Warum Debitoren automatisch, Kreditoren manuell:** JTL berechnet Debitorennummern schon automatisch aus der Kundennummer (Jacky hat Screenshot gezeigt: Kd-1837 → Debitorennummer 200837) — bei potenziell hunderten Kunden ist eine feste Formel praktisch. Bei Lieferanten sind es wenige, und der Steuerberater hat oft schon bestehende Kreditorennummern aus der bisherigen Buchhaltung, die 1:1 übernommen werden sollen statt neu berechnet — passt auch zur bereits am 2026-07-02 getroffenen Entscheidung (Kreditoren-Zuordnung über eigene Liste, nicht automatisch).

## 🟡 Offen: Babsis Antwort zu Steuer-/Zahlungsart-Mapping (Stand 2026-07-17)

Babsi hat auf die erste Nachfrage geantwortet, aber 3 Detailfragen sind noch offen:
1. **USt-Konten pro Steuersatz** — sie will "aufgeschlüsselt" (eigenes Konto je Satz 20%/10%/0%, nicht ein generisches), aber die konkreten Kontonummern fehlen noch.
2. **Gutschein-Anzahlungskonto** — sie bucht Gutscheine auf ein "3er-Konto für Anzahlung ohne Steuer", genaue Nummer fehlt.
3. Braucht das ERP überhaupt ein Vorsteuer-Konto (das wäre eher Einkaufs-/Lieferantenrechnungsseite, die noch 0% Code ist) — noch nicht geklärt ob das für den aktuellen Verkaufs-Export überhaupt relevant ist.

**Bereits bestätigt von Babsi (Zahlungsart→Konto):**
- Kassa (bar) → **2700**
- Bank / Bankomat (EC-Karte) → **2800**
- PayPal → aktuell auch 2800 mit Kennung "PP", soll aber eigenes Konto **2801** werden
- Rechnung → KEIN eigenes Zahlungskonto, sondern Kombi: Erlös (4er-Warengruppenkonto) + individuelles Kundenkonto (Debitorenkonto), bei Zahlungseingang dann Bank gegen Kundenkonto ausgeglichen
- Gutschein → Sonderfall, bucht auf das noch offene 3er-Anzahlungskonto (siehe Punkt 2 oben)

**Zwei unterschiedliche Zahlungsart-Wertemengen im Code, beide müssen im Mapping abgedeckt werden:**
- `auftraege.zahlungsart`: vorkasse, paypal, rechnung, bar, nachnahme, gutschein, gemischt
- `kassen_bons.zahlungsart`: bar, karte_extern, gutschein, kombi

## ✅ Geklärt 2026-07-17: 99er-Freitext-Artikel (siehe [[project_wawi_gaps]]) braucht KEINE Sonder-Kontenzuordnung

Jacky fragte sich beim Kreditoren-Zuweisen, wie der 99-9999-"Diverses"-Artikel (kann theoretisch jeden Steuersatz haben) kontiert wird. Im Code bestätigt: `steuer_prozent` wird pro Position (`kassen_bon_positionen`/`auftrag_positionen`) gespeichert, unabhängig vom fixen `steuerklasse_id` im Artikel-Stammsatz — der Kassierer wählt den Satz beim "Freier Artikel"-Eintrag manuell (`kasse_bon_offline.js`, `KassenService::getDiversArtikelId()`). Erlöskonto bleibt fix bei artikel_gruppe_id=7 (Sonstiges Zubehör, Konto 4400), USt-Konto wird ganz normal pro Zeile aus dem tatsächlichen `steuer_prozent` abgeleitet — DATEV hat dafür einen eigenen Steuerschlüssel (BU-Kennziffer) pro Buchungszeile, unabhängig vom Erlöskonto. Kein Sonderfall, keine Code-Änderung nötig.

## 🟢 FERTIG 2026-07-17: Verwaltungsoberflächen + DATEV/CSV-Export

- **Buchhaltung → Kontenplan**: volles CRUD (neu/bearbeiten), kein Löschen (nur `aktiv=0`, wegen möglicher FK-Referenzen aus zahlungsart_konten/steuerklassen_konten)
- **Buchhaltung → Zahlungsart-Konten / Steuer-Konten**: Zeilen kommen LIVE aus den echten ENUM-Werten (`auftraege.zahlungsart`/`kassen_bons.zahlungsart`) bzw. aus der `steuerklassen`-Tabelle — neue Zahlungsarten/Steuersätze im Code tauchen automatisch als offene Zeile auf, keine hartcodierte Liste
- **`BuchhaltungExportService`** (`src/modules/buchhaltung/`) sammelt Buchungszeilen aus Kassenbons + Auftrag-Positionen, aggregiert pro Tag×Warengruppe×Steuersatz×Zahlungsart; Rechnung separat pro Auftrag (individuelles Debitorenkonto), Zahlungseingänge auf Rechnung als dritter Block. Negative Beträge (Retouren/Gutschriften) werden auf positiv+Soll/Haben-Tausch normalisiert (DATEV erwartet nie negative Beträge).
- **`DatevFormatter`**: generisches CSV (immer nutzbar) + DATEV-EXTF-Buchungsstapel (Kernspalten, BU-Schlüssel bewusst leer — Steuer wird als eigene Buchungszeile gebucht statt DATEV-Steuerautomatik zu nutzen, weil unser Kontenrahmen ohnehin pro Satz eigene Konten hat)
- **DATEV-Einstellungen** (Berater-/Mandanten-Nr., WJ-Beginn) als leere Platzhalter in `system_einstellungen`, editierbar auf der Export-Seite — Jacky/Babsi tragen die später selbst ein
- **Zwei echte Bugs beim Testen gefunden+behoben:** (1) 99-9999-Freitext-Artikel bekommt bei DIREKTEN Kassenbuchungen keine `artikel_id` (nur beim Spiegeln nach `auftrag_positionen`) → Export ist jetzt mit Fallback auf die Diverses-Gruppe abgesichert; (2) `||` in einer SQL-Query ist in MySQL standardmäßig logisches ODER, nicht String-Verkettung (Bug vor dem ersten Testlauf selbst gefunden, war eh unbenutzter Code)
- **Nicht validiert**: das exakte DATEV-EXTF-Spaltenlayout ist öffentlich dokumentiert, aber es gibt viele optionale Spalten je DATEV-Programmversion — vor dem ersten echten Import mit dem Steuerberater eine Testdatei abstimmen, im Handbuch (`docs/handbuch/12_buchhaltung.md`) + `bedienungsanleitung.php` entsprechend vermerkt
- Handbuch-Kapitel 12 + Bedienungsanleitung synchron ergänzt

## 🔮 Für später vormerken: Soll- vs. Ist-Versteuerung konfigurierbar machen

`BuchhaltungExportService::auftragUmsaetzeRechnung()` bucht Rechnungs-Erlöse aktuell hart nach **Soll-Versteuerung** (Erlös+USt bei Auftragsdatum, unabhängig vom Zahlungseingang). Das passt für MEALANA KG, ist aber laut Jacky (2026-07-17) nicht für jede Rechtsform/jeden Betrieb korrekt — Soll- vs. Ist-Besteuerung hängt in Österreich von Rechtsform/Umsatzgrenze/Berufsgruppe ab (z.B. bei Weitergabe an andere Betriebe könnte Ist-Versteuerung nötig sein: Erlös+USt erst bei tatsächlichem Zahlungseingang, nicht bei Rechnungsstellung).
**How to apply:** Beim Weitergabe-/Whitelabel-Thema (siehe [[project_whitelabel_branding]]) einen Schalter `system_einstellungen` (z.B. `versteuerung_art` = 'soll'|'ist') einplanen, der in `auftragUmsaetzeRechnung()` entscheidet ob nach Auftragsdatum oder nach `auftrag_zahlungen.buchungsdatum` gebucht wird. Nicht dringend für MeaLana selbst.

## How to apply beim Wiedereinstieg: Sobald die 3 fehlenden Nummern da sind: (1) die 4 Kernkonten in `kontenplan` korrigieren (2700/2800/2801 + USt-Konten pro Satz + Gutschein-Konto), (2) `zahlungsart_konten`-Tabelle bauen die BEIDE Zahlungsart-Wertemengen abdeckt, (3) `steuerklassen_konten`-Tabelle (nur Steuer-Konto pro Satz, kein Erlös-Konto — das kommt ja schon aus artikel_gruppen).

## ✅ Code-verifizierte Bestandsaufnahme (2026-07-10) — eigene Session geplant, heute nicht weitergebaut

Auf Jackys Wunsch den kompletten Modul-Stand gegen den echten Code geprüft (nicht nur Notizen) + Referenz-Check gegen JTL/Shopware/Sage/Odoo.

**Fertig:** Artikelgruppen mit Kontonummer (`buchhaltung/artikel_gruppen.php`, aber Kontonummern hängen noch in der Luft — nichts konsumiert sie weiter), Steuerklassen (AT 20/10/0%), Kleinunternehmer-Modus (global, korrekt in Dokumenten verdrahtet), Nummernkreise-Verwaltung, Kassenbuch (reine Bargeld-Lade-Buchführung, keine Fibu).

**Nur rudimentär:** Mahnwesen — weiterhin nur einstufig (14 Tage Erinnerung, 30 Tage Auto-Storno bei Vorkasse / nur Hinweis bei Rechnung). Die für echte Mahnstufen vorgesehene Spalte `auftraege.mahnung_stufe` liegt **tot** im Code (wird nirgends mehr erhöht, nur noch aus einer nicht aufgerufenen Repository-Methode referenziert). Lieferantenrechnungen: nur Rechnungsnummer/Betrag/Datum-Freitextfelder pro einzelner Bestellung, keine zentrale Kreditoren-Übersicht.

**Komplett fehlend (0% Code, nur Konzept):** `kontenplan`-Tabelle existiert nicht, `steuerklassen_konten`/`zahlungsart_konten`-Mapping existiert nicht, DATEV-Export ist buchstäblich 0 Zeilen Code (kompletter Repo-Grep nach "datev" ergab nur Zufallstreffer in `updateVertreter`), Kreditoren-Konto-Zuordnung am Lieferanten existiert nicht (auch keine Spalte dafür), Dashboard-Karte "Offene Lieferantenrechnungen" ist ein toter grauer Platzhalter (`lieferanten_rechnungen`-Tabelle existiert nicht).

**Referenz-Check-Ergebnis:** Der "kein eigenes Buchhaltungssystem, nur DATEV-Export"-Grundsatz ist weiterhin richtig und branchenüblich — JTL macht es genauso (Sachkonten-Zuordnung + Export, keine eigene Fibu), Shopware hat gar keine eigene Buchhaltung. Odoo hat volle Fibu (Journal/Bankabgleich/Bilanz), das war aber laut Jackys eigenem Grundsatz nie das Ziel, nur als Referenz fürs Strickauftragsmodul relevant — hier nicht nachzubauen.

**How to apply beim Wiedereinstieg (eigene Session):** Von den drei Kern-Bausteinen für einen funktionierenden Export — Kontenplan, Kontierungsregeln, DATEV-CSV-Export selbst — ist buchstäblich keiner gebaut. Reihenfolge: Kontenplan zuerst (Basis für alles Weitere), dann Mappings (Steuerklasse/Zahlungsart→Konto), dann der eigentliche Export. Kreditoren-Übersicht könnte günstig aus den bereits vorhandenen `bestellungen.rechnung_*`-Feldern gebaut werden (Liste/Filter statt neuer Tabelle) statt gleich eine neue `lieferanten_rechnungen`-Tabelle wie ursprünglich geplant — Kosten/Nutzen beim Wiedereinstieg abwägen.

---

## ✅ Artikelgruppen-Modul (Migration 096) — FERTIG 2026-07-01

Warengruppen mit Kontozuordnung für Buchhaltungsberichte.

**🟢 BUG behoben (2026-07-11), am Ende insgesamt 4 Stellen:** `artikel_gruppen_speichern.php`/`_loeschen.php` UND `versand/versandklasse_speichern.php`/`_loeschen.php` hatten denselben Copy-Paste-Bug — falsch gesetztes schließendes `"` vor `{$name}` (statt nach dem schließenden „…“-Zeichen) beendete den PHP-String vorzeitig → Parse Error. Anlegen/Bearbeiten/Löschen war dadurch in beiden Modulen komplett kaputt (500er). Alle vier Stellen gefunden über systemweite Suche nach dem exakten Muster, gefixt + end-to-end getestet (Anlegen+Löschen für beide Module durchgespielt). Nebenbei eine wirkungslose Doppel-Abfrage in `artikel_gruppen_loeschen.php` entfernt.
**Lehre:** Bash-`grep` hat das Unicode-Anführungszeichen „ beim ersten Suchversuch nicht zuverlässig gematcht (0 Treffer trotz bekanntem Vorkommen) — beim zweiten Anlauf das robustere `Grep`-Tool (ripgrep-basiert) verwendet, das alle vier Stellen sofort fand. Bei Suchen nach Sonderzeichen/Unicode-Mustern künftig gleich das Grep-Tool statt Bash-grep nehmen.

**DB:** `artikel_gruppen` (id, konto_nr, name, aktiv, sortierung) + FK `artikel_gruppe_id` an `artikel` + `versandklassen`.
**Startwerte:** 4000 Wolle … 4900 Versandkosten (11 Gruppen).
**Vererbung:** VATER→KIND via `propagiereZuKindern()` (ArtikelRepository) + `saveKind()` (ArtikelService).
**UI Buchhaltung:** `public/buchhaltung/artikel_gruppen.php` — CRUD-Modal, Warnung getrennt (Väter vs. Kinder ohne Gruppe).
**UI Versand:** Versandklassen-CRUD in `versand/index.php` mit Artikelgruppen-Dropdown (unten, nicht in rechter Spalte).
**UI Artikel:** Pflichtfeld in `neu.php`, `bearbeiten.php`, `detail.php` (3er-Grid: Hersteller|Steuerklasse|Artikelgruppe). Warnung im Detail wenn leer. Filter "Keine Artikelgruppe" in liste.php.
**Berichte:** Kassen X/Z-Bon + Monats/Quartalsabschluss zeigen "Umsatz nach Artikelgruppe" mit Steuersatz-Aufschlüsselung (JOIN über artikel.artikel_gruppe_id).
**Buchhaltungs-Nav:** Topnav-Link war disabled → jetzt aktiv (`/mealana/buchhaltung/artikel_gruppen.php`).

**Wichtig — Warnung:** Zählt NUR Väter/Standalone (nicht Kinder, weil die beim nächsten Vater-Speichern erben). `vaterartikel_id IS NULL AND zustand_vater_id IS NULL`.

## 🟢 FERTIG 2026-07-18: Lieferantenrechnungen / Kreditoren-Übersicht + Dashboard Card 5

Jacky fragte morgens nach, ob das nicht eigentlich zur Buchhaltung gehört hätte — zurecht: war beim 17.07.-Sprint übersehen worden (Fokus lag auf Kontenplan/Mappings/Export), stand aber schon vorher als Lücke in diesem Dokument.

- **Migration 131**: `bestellungen.rechnung_bezahlt_am DATE NULL` — bewusst KEINE neue `lieferanten_rechnungen`-Tabelle, sondern die schon bestehenden `bestellungen.rechnung_nummer/_betrag/_datum` (Migration 056) weiterverwendet. **Achtung, noch am selben Tag überholt:** dieses simple Flag wurde direkt danach durch echte Teilzahlungen ersetzt (siehe nächster Abschnitt) — Migration 132 entfernt die Spalte wieder, `lieferantenrechnung_status.php` existiert nicht mehr.
- **Neue Seite** `buchhaltung/lieferantenrechnungen.php`: Liste aller Bestellungen mit erfasster Rechnungsnummer, Filter offen/bezahlt/alle.
- **Fälligkeit + Skonto direkt berechnet** aus `lieferanten.zahlungsziel_tage`/`skonto_prozent`/`skonto_tage` (Migration 085 — waren schon in der DB, aber bis dahin nirgends im Code verwendet!). Überfällige Rechnungen rot markiert, Skonto-Frist grün solange noch gültig.
- **Dashboard Card 5** aktiviert: Summe + Anzahl offener Lieferantenrechnungen, ⚠-Chip bei Überfälligkeit, Link zur Übersicht.
- Handbuch Kapitel 12 + `bedienungsanleitung.php` synchron ergänzt (siehe [[feedback_beide_handbuecher]]).
- End-to-end mit isoliertem Test-Datensatz getestet (Bestellung angelegt, alle Filter/Status-Übergänge durchgespielt, danach vollständig gelöscht + Lieferanten-Skonto-Felder zurückgesetzt — kein Rückstand in echten Daten, siehe [[feedback_test_isolation]]).

## 🟢 FERTIG 2026-07-18 (gleicher Tag, direkt danach): Lieferanten-Guthaben-Konto + Teilzahlungen (DROPS-Modell korrekt abgebildet)

Jacky erkannte selbst den Denkfehler im Vormittags-Feature: bei DROPS (Vorkasse, Teillieferung, Rest bleibt als Gutschrift stehen) ist der Bestellwert einer neuen Bestellung NICHT gleich dem tatsächlich zu zahlenden Betrag, weil ein Teil aus bestehendem Guthaben verrechnet wird. Ein einzelnes "bezahlt"-Flag hätte das verfälscht.

- **Migration 132**: `bestellung_zahlungen` — exakter Spiegel von `auftrag_zahlungen` (Migration 076), zusätzlich `art` ENUM('ueberweisung','guthaben_verrechnung'). `bestellungen.rechnung_bezahlt_am` (der Vormittags-Flag) wieder entfernt.
- **Migration 133**: `lieferanten_guthaben_bewegungen` — echtes Bewegungskonto pro Lieferant (positiv=Gutschrift erhalten, negativ=verrechnet), Saldo = SUM(betrag). Ersetzt `bestellungen.gutschrift_betrag`/`gutschrift_notiz` (bewusste Entscheidung: keine Doppelpflege, siehe Nutzerentscheidung).
- **`LieferantenGuthabenRepository`** (neu, `src/modules/lieferanten/`): `getSaldo()`, `insertBewegung()`, `findBewegungen()`.
- **`BestellungService::bucheZahlung()`**: bucht Zahlung, bei `art=guthaben_verrechnung` zusätzlich eine negative Guthaben-Bewegung — **gedeckelt auf den tatsächlich verfügbaren Saldo** (sonst Fehler).
- **`WareneingangService::abschliessenMitRest()`**: "Rest streichen" mit Gutschriftbetrag bucht jetzt automatisch eine `+Betrag`-Guthaben-Bewegung statt nur einer Freitext-Notiz auf der Bestellung.
- **`bestellungen/detail.php`**: neue Card "Zahlungsverlauf" (Tabelle der Zahlungen, Rechnung/Bezahlt/Offen-Summary, Formular Betrag+Art+Datum+Notiz) — analog zum Zahlung-buchen-Formular bei Aufträgen. Guthaben-Hinweis in der Kopfzeile wenn Saldo > 0. **Nebenfund:** `$fehler` wurde in dieser Seite eingelesen aber nie angezeigt — Anzeige-Block ergänzt.
- **`lieferanten/detail.php`**: Guthaben-Saldo in der Konditionen-Card.
- **`buchhaltung/lieferantenrechnungen.php`**: Status (offen/teilbezahlt/bezahlt) jetzt aus Zahlungssumme berechnet, "Zahlung buchen"-Link führt zur Bestellung statt eigenem Toggle-Button. Altes `lieferantenrechnung_status.php` gelöscht.
- Dashboard Card 5: Summe zeigt jetzt den offenen Restbetrag (Rechnung minus Zahlungen), nicht mehr den vollen Rechnungsbetrag.
- End-to-end mit realistischem DROPS-Szenario getestet (Bestellung 1: 400€ Vorkasse + 100€ Gutschrift beim Reststreichen → Saldo 100€; Bestellung 2: 300€ Wert, 200€ Überweisung + 100€ Guthaben-Verrechnung → Saldo 0€, beide Rechnungen korrekt "bezahlt"; dritter Versuch mit leerem Guthaben schlägt korrekt fehl) — danach vollständig aufgeräumt, keine Testspuren in echten Daten.
- Handbuch Kapitel 12 (neuer Abschnitt "Zahlungsverlauf + Lieferanten-Guthaben") + Bedienungsanleitung synchron ergänzt.

**Wichtig für später:** Das ist reine interne Nachverfolgung — der DATEV-Export bucht weiterhin nur die Verkaufsseite. Eine buchhalterisch sauberere Variante (Anzahlungskonto als echtes Aktivkonto im Kontenplan, damit auch der Einkauf irgendwann exportierbar wird) wurde mit Jacky besprochen, aber bewusst zurückgestellt — der Kontenplan kennt aktuell nur `erloes|aufwand|steuer|bank|kasse` als Kontotyp, kein Aktivkonto/Anzahlung (`database/migrations/126_kontenplan.sql`). Käme frühestens mit dem Einkaufs-Anteil am DATEV-Export.

## ⚡ Restpunkte (nicht dringend, wie Mahnwesen behandeln)
- Mahnwesen für Rechnungszahler ausbauen (siehe unten — eigenes, größeres Thema mit Mahnstufen/-gebühren, nicht Teil des 18.07.-Nachziehens)
- **Anzahlungskonto/Aktivkonto im Kontenplan** (für den DROPS-Guthaben-Mechanismus oben, damit auch der Einkauf sauber DATEV-buchbar wird): Jacky hat 2026-07-18 explizit entschieden, das wie Mahnwesen zu behandeln — kein Blocker, kommt entweder wenn Babsi den Kontenrahmen dafür umgestellt hat, oder früher falls akut gebraucht. Bis dahin bleibt das Guthaben-Konto ([[project_buchhaltung]] oben) rein interne Nachverfolgung ohne DATEV-Anbindung.

## ✅ Bestätigt 2026-07-18: Wareneingang-Rückstand-Workflow unverändert
Jacky fragte nach, ob "normale" Lieferanten weiterhin Teillieferungen als Rückstand offen stehen lassen können (Ware kommt später nach) statt wie DROPS sofort abzuschließen. Im Code bestätigt: `wareneingang_detail.js`/`abschliessen.php` — "Auf Nachlieferung warten" ist die Standardauswahl im Abschluss-Dialog, schließt die Bestellung NICHT ab (bleibt `teilgeliefert`, weitere Wareneingänge später möglich). "Rest streichen" (+ Gutschrift → Guthaben-Konto) ist die bewusste Ausnahme nur für DROPS-artige Lieferanten. Keine Code-Änderung nötig, war schon immer so.

## Mahnwesen — zwei Ebenen

### Bereits vorhanden (Vorkasse, einfach)
Cronjob in `cron/mahnwesen.php`: 14 Tage → Erinnerungsmail, 30 Tage → Vorschlag zur manuellen Stornierung.
Tabelle `mahnungen`: nur `typ` (erinnerung/stornierung), kein Stufen-System.

### Noch zu bauen (Rechnungszahler, mit Buchhaltung)
Echte Mahnstufen mit eigenem DB-Ausbau:
- **Stufe 1** — Zahlungserinnerung (freundlich, 0 Mahngebühr)
- **Stufe 2** — 1. Mahnung (kleine Mahngebühr, Verzugszinsen ab Fälligkeit)
- **Stufe 3** — 2. Mahnung (höhere Mahngebühr, Androhung Inkasso)
- **Stufe 4** — Inkasso-Übergabe (manuell, Buchungssatz)

Erfordert: `mahnungen.stufe INT`, `mahnungen.mahngebuehr DECIMAL`, Verzugszins-Berechnung, DATEV-Buchungssatz pro Mahnstufe, eigene Mahnbrief-Vorlage (Twig/Dompdf).

**Why:** Rechnungskunden haben echte Zahlungsziele (14/30/60 Tage), Mahngebühren sind steuerlich buchungspflichtig — das gehört in den Buchhaltungskontext, nicht in den einfachen Cronjob.

---

## ⚡ Dashboard-Aktivierung beim Buchhaltungs-Start
Dashboard Card 5 "Offene Lieferantenrechnungen" ist als Platzhalter gebaut (dashboard.php).
Beim Buchhaltungsmodul: `lieferanten_rechnungen`-Tabelle anlegen und Card-5-Block in dashboard.php aktivieren (Kommentar `/* TODO BUCHHALTUNG */` suchen).

---

## Grundsatz

Kein eigenes Buchhaltungssystem bauen. Ziel: **DATEV-Schnittstelle** (Export) damit der Steuerberater die Daten in DATEV importieren kann. Das machen alle seriösen WAWIs so (JTL, Sage, Shopware).

## DATEV-Schnittstelle

DATEV ist ein geschlossenes System für Steuerberater. Wir bauen einen **Export** im DATEV-Format.

- Format: CSV, gut dokumentiert (DATEV veröffentlicht die Spezifikation)
- Felder: Umsatz, Soll/Haben-Kennzeichen, Konto, Gegenkonto, Buchungstext, Belegdatum, Belegnummer
- Export-Zeitraum wählbar (Monat, Quartal, Jahr)
- Aufwand: Überschaubar — ein Export-Button der Buchungssätze als DATEV-CSV ausgibt

**Referenz:** DATEV Buchungsdatenschnittstelle (öffentlich zugänglich)

## Kontenplan (Österreich)

Österreich: **Österreichischer Einheitskontenrahmen** (ähnlich deutschem SKR03/SKR04).

Geplante Tabelle:
```sql
kontenplan (
    id            INT PK AUTO_INCREMENT,
    kontonummer   VARCHAR(10) NOT NULL UNIQUE,   -- '3000', '2700'
    name          VARCHAR(100) NOT NULL,          -- 'Warenerlöse 20%', 'Vorsteuer'
    typ           VARCHAR(20),                   -- 'erloes', 'aufwand', 'steuer', 'bank', 'kasse'
    aktiv         TINYINT(1) DEFAULT 1
)
```

Seed-Daten (österreichische Pflichtkonten für Einzelhandel):
- 2700 Vorsteuer
- 3000 Warenerlöse 20% MwSt
- 3100 Warenerlöse 10% MwSt
- 3300 Warenerlöse 0% (innergemeinschaftlich)
- 2500 Umsatzsteuer
- 1600 Bankkonto
- 1500 Kassa

## Mappings (Basis für automatische Kontierung)

```sql
steuerklassen_konten (
    steuerklasse_id   INT FK → steuerklassen.id,
    erloes_konto_id   INT FK → kontenplan.id,
    steuer_konto_id   INT FK → kontenplan.id
)

zahlungsart_konten (
    zahlungsart       VARCHAR(30),    -- 'bar', 'karte', 'ueberweisung'
    konto_id          INT FK → kontenplan.id
)
```

So wird jede Kassen-Buchung und jede Rechnung automatisch den richtigen Konten zugeordnet → DATEV-Export schreibt sich fast von selbst.

## Kreditoren-Zuordnung (Lieferanten)

Geplant: eigene Liste/Seite im Buchhaltungsmodul mit allen Lieferanten (siehe [[project_lieferanten_erweiterung]]), auf der man jedem Lieferanten ein Kreditorenkonto zuweist — nicht am Lieferanten-Stammdatensatz selbst. Jacky hat das am 2026-07-02 explizit so bestätigt: "quasi eine Liste wo alle Lieferanten drin stehen und dort kann man die Kreditorenzuweisung machen".

## Odoo als Referenz

Odoo hat ein vollständiges Doppik-System + DATEV-Export. Für **Auftragsfertigung** (Strickaufträge) und **Buchhaltung** ist Odoo ein besseres Referenz-System als JTL/Sage.
Odoo Manufacturing-Modul als Referenz für das Strickauftrags-Modul verwenden.

**Why:** Steuerberater in Österreich nutzen fast ausnahmslos DATEV oder ein kompatibles System. Ohne Export kein Jahresabschluss. Kontenplan ist die Basis damit Buchungssätze korrekt zugeordnet werden.
**How to apply:** Beim Aufbau des Kassenmoduls sofort Kontenplan + Mappings anlegen. DATEV-Export als erstes Feature im Buchhaltungsmodul.
