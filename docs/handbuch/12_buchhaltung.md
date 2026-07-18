# 12 — Buchhaltung

## Wofür?

MeaLana führt keine eigene Buchhaltung (kein Journal, keine Bilanz) — das macht wie bei den meisten WAWIs weiterhin das externe Buchhaltungsprogramm des Steuerberaters. MeaLana liefert stattdessen einen **Export** (DATEV-Format oder generisches CSV), den der Steuerberater importiert.

---

## Artikelgruppen

**Navigation:** Buchhaltung → Artikelgruppen

Jede Warengruppe (Wolle, Nadeln, Stoffe, ...) hat eine eigene Kontonummer für den Erlös. Diese Kontonummer muss mit dem echten Kontenrahmen des Steuerberaters übereinstimmen — Änderungen hier wirken sich direkt auf den Export aus.

---

## Kontenplan

**Navigation:** Buchhaltung → Kontenplan

Zentrale Liste aller Konten (Erlös, Steuer, Bank, Kasse, Aufwand). Neue Konten über „+ Neues Konto“ anlegen, bestehende über „Bearbeiten“ ändern. Ein Konto wird nie gelöscht, nur deaktiviert (z.B. wenn ein Steuerberater-Wechsel die Nummern ändert) — so bleiben alte Buchungen nachvollziehbar.

---

## Debitoren (Kunden) und Kreditoren (Lieferanten)

- **Debitorennummer** (Kunde-Detailseite, neben der Kundennummer): wird bei neuen Kunden automatisch vorgeschlagen (`2` + 5-stellige Kundennummer, z.B. KD-00042 → 200042), ist aber überschreibbar — wichtig bei Bestandskunden, die in der bisherigen Buchhaltung schon eine andere Nummer haben. Zum Ändern einfach auf die Debitorennummer klicken.
- **Kreditorennummer** (Buchhaltung → Kreditoren): wird **nicht** automatisch vergeben, sondern manuell pro Lieferant eingetragen — meist vom Steuerberater vorgegeben.

---

## Lieferantenrechnungen (Kreditoren-Übersicht)

**Navigation:** Buchhaltung → Lieferantenrechnungen

Zeigt alle Einkaufsbestellungen, bei denen im Bestellungsmodul eine Rechnungsnummer erfasst wurde — als zentrale Liste statt "jede Bestellung einzeln aufmachen". Die Rechnungsdaten selbst (Rechnungs-Nr., Betrag, Datum) werden weiterhin auf der jeweiligen Bestellung eingetragen (Bestellungen → Detail).

- **Fällig am**: automatisch berechnet aus Rechnungsdatum + Zahlungsziel des Lieferanten (Lieferanten-Detailseite). Überfällige Rechnungen sind rot markiert.
- **Skonto**: falls beim Lieferanten ein Skonto-Prozentsatz + Skonto-Tage hinterlegt sind, zeigt die Spalte die verbleibende Frist an.
- **Status** (offen/teilbezahlt/bezahlt) wird aus den tatsächlich gebuchten Zahlungen berechnet, nicht aus einem einzelnen Flag — siehe Zahlungsverlauf unten.
- **"Zahlung buchen"** führt direkt zur Bestellung, wo die eigentliche Buchung passiert.
- Filter oben: Offen / Teilbezahlt / Bezahlt / Alle.

Das Dashboard zeigt in der Karte "Lieferanten-Rechnungen" die Summe und Anzahl aller offenen Rechnungen (offener Restbetrag, nicht der volle Rechnungsbetrag), inkl. Warnung bei überfälligen.

### Zahlungsverlauf + Lieferanten-Guthaben (DROPS-Modell)

**Navigation:** Bestellungen → Detail (Card "Zahlungsverlauf", erscheint sobald eine Rechnung erfasst ist)

Für Lieferanten wie DROPS (Vorkasse, Teillieferung, Rest bleibt als Gutschrift stehen statt Rückzahlung) reicht ein einzelnes "bezahlt"-Datum nicht — der tatsächlich überwiesene Betrag kann kleiner sein als die Rechnungssumme, wenn ein Teil aus bestehendem Guthaben verrechnet wird.

- **Jede Zahlung** wird einzeln gebucht: Betrag, Art (**Überweisung** oder **Guthaben-Verrechnung**), Datum, Notiz. Der Status ergibt sich aus der Summe aller Zahlungen gegen den Rechnungsbetrag — genau wie bei Teilzahlungen auf Kundenaufträgen.
- **Guthaben entsteht automatisch**, wenn eine Bestellung im Wareneingang mit "Rest streichen" abgeschlossen wird und dabei ein Gutschriftbetrag eingetragen wird — das landet als Zugang auf dem Lieferanten-Guthaben-Konto (nicht mehr nur als Freitext-Notiz).
- **Guthaben-Verrechnung ist gedeckelt**: es kann nie mehr verrechnet werden als tatsächlich als Guthaben zur Verfügung steht — das System weist das sonst ab.
- Der aktuelle **Guthaben-Saldo** eines Lieferanten steht auf der Lieferanten-Detailseite (Karte "Konditionen").

**Beispiel:** Bestellung 1 (500 €, Vorkasse) wird nur zu 400 € geliefert, die restlichen 100 € werden beim Abschließen als Gutschrift erfasst → Guthaben-Saldo steigt auf 100 €. Bestellung 2 (300 € Wert) wird mit 200 € neuer Überweisung + 100 € Guthaben-Verrechnung beglichen → Saldo wieder bei 0 €, beide Rechnungen korrekt als "bezahlt" verbucht.

---

## Zahlungsart-Konten und Steuerklassen-Konten

**Navigation:** Buchhaltung → Zahlungsart-Konten / Steuer-Konten

Legt fest, welches Konto beim Export für welche Zahlungsart (bar, Bank, PayPal, ...) bzw. welchen Steuersatz (20 %, 10 %, 13 %, 0 %) verwendet wird. "Rechnung" hat bewusst kein einfaches Konto — dort wird stattdessen Erlös + individuelles Kundenkonto gebucht (siehe unten).

---

## Export (DATEV / CSV)

**Navigation:** Buchhaltung → DATEV/CSV-Export

1. Einmalig **DATEV-Einstellungen** eintragen (Berater-Nr., Mandanten-Nr., Wirtschaftsjahr-Beginn — vom Steuerberater erfragen). Ohne diese Angaben funktioniert nur der CSV-Export vollständig.
2. **Zeitraum** wählen (Von/Bis, oder Schnellwahl "Dieser Monat/Quartal/Jahr").
3. Vorschau prüfen — **Hinweise** (gelber Kasten) zeigen Positionen, die NICHT automatisch gebucht werden konnten (z.B. Artikel ohne Warengruppe, oder Zahlungsart ohne Konto-Zuordnung) und von Hand nachgebucht werden müssen.
4. **CSV herunterladen** (funktioniert immer, für jedes Buchhaltungsprogramm lesbar) oder **DATEV herunterladen** (offizielles DATEV-Buchungsstapel-Format).

**Buchungslogik kurz erklärt:**
- Kassenverkäufe + "einfache" Auftrags-Zahlarten (bar, Karte, PayPal, Vorkasse, Nachnahme): Erlös + Umsatzsteuer werden sofort gegen das Zahlungsmittel-Konto gebucht.
- Rechnung: Erlös + Umsatzsteuer werden zum Auftragsdatum gegen das individuelle Kundenkonto (Debitorenkonto) gebucht. Der spätere Zahlungseingang ist eine eigene, zweite Buchung (Bank gegen Kundenkonto).
- Gutschein, gemischte Zahlarten: bisher zu selten für eine verlässliche automatische Buchung — tauchen als Hinweis auf und werden von Hand gebucht.

**Wichtig beim allerersten Export:** Vor dem ersten "scharfen" DATEV-Import unbedingt mit dem Steuerberater eine Testdatei abstimmen — DATEV-Programmversionen unterscheiden sich in Detail-Spalten, die hier bewusst nicht alle abgedeckt sind.
