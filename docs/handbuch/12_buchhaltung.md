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
