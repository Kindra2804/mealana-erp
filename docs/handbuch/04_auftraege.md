# 04 — Auftragsmodul

## Konzept

Jeder Auftrag hat zwei getrennte Status:

| Status | Was er bedeutet |
|--------|-----------------|
| **Zahlungsstatus** | Wurde bezahlt? (ausstehend / bezahlt / teilbezahlt / storniert) |
| **Lieferstatus** | Wurde versendet? (neu / in Bearbeitung / versandbereit / teilgeliefert / versendet / abgeschlossen / Retoure offen) |

Diese zwei sind absichtlich getrennt — ein Auftrag kann bezahlt aber noch nicht versendet sein, oder umgekehrt.

### Wann ist ein Auftrag „Abgeschlossen"? {#abgeschlossen}

Das setzt das System **automatisch** — nur wenn alles erfüllt ist:

1. alles ist ausgeliefert (versendet bzw. an der Kasse abgeholt),
2. alles Ausgelieferte steht auf einem **Beleg** (Rechnung oder Kassenbon),
3. keine Retoure ist offen,
4. der Auftrag ist ausgeglichen: **Saldo aus den Belegen = 0** (Rechnungen/Bons − Rechnungskorrekturen − Zahlungen).

Fehlt etwas, bleibt der Auftrag auf „versendet" bzw. „teilgeliefert".

**Retoure offen:** Kommt Ware zurück, öffnet sich ein abgeschlossener Auftrag automatisch wieder. Er bleibt auch dann auf „Retoure offen", solange dem Kunden nach einer Rechnungskorrektur/Stornorechnung noch Geld zurückzuzahlen ist — im Auftrag steht dann „**Rückerstattung offen** … € Guthaben" mit dem Knopf **↩ Rückerstattung buchen** (Überweisung/PayPal; bar geht an der Kasse: Auftrag laden → bar oder als Gutschein). Danach: abgeschlossen, Zahlungsstatus „erstattet". Im Auftrag zeigt ein blauer Kasten **„Retoure offen — noch zu erledigen"**, was konkret fehlt: Rechnungskorrektur für zurückgenommene, bezahlte Ware (mit Knopf), Einbuchen am Packplatz (Rücklagerungen) oder die Rückerstattung. Er steht auf „Retoure offen", bis die Ware am Packplatz eingelagert **und** gutgeschrieben bzw. erstattet ist — dann schließt er sich von selbst wieder. In der Auftragsliste kann man nach „Retoure offen" filtern.

### Belege: Rechnung, Teilrechnung, Bon

Grundregel: **Jede Ware, die das Haus verlässt, steht auf genau einem Beleg** — nie auf Rechnung UND Bon.

| Fall | Beleg |
|------|-------|
| Versand (auch Teillieferung) | Teilrechnung über genau diese Lieferung — entsteht automatisch am Packplatz |
| An der Kasse bezahlt | Kassenbon (= Rechnung), A4-Version hängt an der Abholmail |
| Vorab bezahlt (Vorkasse/PayPal) und abgeholt | Rechnung, hängt an der Abholmail |
| Gutschein-Bestellung (Shop) | Rechnung mit 0 % (Gutschein = Anzahlung) |
| Retoure / Erstattung | Rechnungskorrektur (teilweise) bzw. Stornorechnung (komplett) — oder an der Kasse ein Bon mit Minus-Zeilen |

Eine **Teilrechnung** enthält nur die gelieferten Artikel; darunter steht „Noch ausständig" (ohne Preise). Die Versandkosten stehen auf der ersten Rechnung. Unter dem Betrag zeigt jede Rechnung die **Zahlungen zum Auftrag** (z.B. „per Überweisung 30,00" oder „bar an Hauptkasse (Zahlbeleg K1-…)") und den offenen Betrag.

Im Auftrag (Bereich Dokumente) stehen alle Rechnungen mit 🖨 = **Nachdruck mit aktueller Zahlungsinfo** (Nummer, Datum und Inhalt bleiben gleich). Der Knopf **„(Teil-)Rechnung erstellen"** erscheint nur, wenn es ausgelieferte, noch nicht verrechnete Ware gibt.

### Rechnungskorrektur / Stornorechnung (statt „Gutschrift") {#rechnungskorrektur}

Eine „Gutschrift" stellt in Österreich nur der **Leistungsempfänger** aus — wir als Verkäufer korrigieren mit einer **Rechnungskorrektur** (einzelne Positionen) bzw. einer **Stornorechnung** (alles Offene). Nummernkreis bleibt **GS-…**.

Auftrag öffnen → Dokumente → **Rechnungskorrektur / Storno**. Korrigierbar ist nur, was schon auf einem Beleg stand (Rechnung **oder Kassenbon**, z.B. an der Kasse bezahlte Abholung) und noch nicht korrigiert/erstattet wurde.

Der Beleg enthält (Pflichtangaben laut finanz.at): eigene fortlaufende Nummer, Name/Anschrift von uns und dem Kunden, **Originalrechnung(en) mit Nummer und Datum**, Bezeichnung der Waren, **Mengen, Entgelte und Steuerbeträge mit Minus**. Der Text im Feld „Grund" steht auf dem Beleg und in der Mail. **Versandkosten:** bei der Stornorechnung werden sie automatisch mit erstattet, bei einer Rechnungskorrektur per Häkchen „Versandkosten erstatten". Nach dem Erstellen bleibt man im Auftrag, das PDF öffnet sich im neuen Tab.

> **Auftrag bearbeiten:** Mengen, die schon auf einer Rechnung oder einem Bon stehen, können nicht mehr verringert werden — Korrektur dann nur über eine Rechnungskorrektur.

---

## Auftrags-Liste

**Navigation:** Aufträge → Auftragsübersicht

Filter oben:
- **Suche:** Auftragsnummer, Kundename, E-Mail
- **Zahlungsstatus:** z.B. nur "ausstehend"
- **Lieferstatus:** z.B. nur "versandbereit"
- **Zeitraum:** Standard „Dieser Monat“ (schnell). „Alle Zeiträume“ bewusst wählen — mit dem JTL-Archiv sind das ~39.000 Aufträge. Suche, Status-Filter und Klick auf eine Kachel zeigen automatisch alle Zeiträume.

**Spalte „Belege":** zeigt je Auftrag die Belege als Kürzel — **AB** Auftragsbestätigung · **LS** Lieferschein · **AZ** Abholzettel · **RG** Rechnung · **RK** Rechnungskorrektur/Storno · **Bon** Kassenbon (= Rechnung).
- farbig = vorhanden, grau = noch keiner; eine kleine Zahl = mehrere (z.B. Teillieferungen)
- Klick: ein Beleg → PDF öffnet sich; mehrere → Liste klappt auf, Klick auf einen Eintrag öffnet das PDF
- **blaues !** = ausgeliefert, aber noch auf keinem Beleg → Auftrag öffnen und Rechnung erstellen

**Übersicht „offene Werte"** (Kacheln oben auf Buchhaltung → Zahlungs-Kontrolle und im Dashboard), Klick öffnet die passend gefilterte Liste:

| Kachel | Bedeutung |
|--------|-----------|
| Auftragsbestand | bestellt, noch nicht geliefert — „erwarteter Umsatz", zählt nicht für Steuer/Buchhaltung |
| Geliefert, nicht verrechnet | sollte immer 0 sein — sonst fehlt eine Rechnung |
| Offene Rechnungen | verrechnet, noch nicht bezahlt (davon überfällig) |
| Retoure offen | Ware zurück, Einlagerung bzw. Rechnungskorrektur/Erstattung ausständig |

---

## Auftrag manuell anlegen

**Navigation:** Aufträge → Neuer Auftrag

### Schritt für Schritt:

1. **Kunden** suchen (Name, E-Mail) oder neu anlegen — **Pflicht**: jeder Auftrag braucht ein Kundenkonto (Debitor) für die Buchhaltung. Anonyme Käufer gibt es nur an der Kasse.
2. **Zahlungsart** wählen (Vorkasse, Rechnung, Bar, PayPal, Nachnahme)
3. **Lieferart** wählen (Versand oder Abholung)
4. **Artikel hinzufügen:**
   - EAN scannen oder Artikelnummer/Name eingeben
   - Menge anpassen
   - Preis (wird automatisch aus dem Preissystem befüllt, kann überschrieben werden)
5. Optional: **Notiz intern** (wird nur intern gesehen) und **Notiz Versand** (erscheint auf Lieferschein)
6. → **Auftrag speichern**

> Das System generiert automatisch eine Auftragsnummer (A-2026-00001).

---

## Zahlungseingang buchen {#zahlungseingang}

Wenn eine Überweisung eingegangen ist:

1. Auftrag öffnen
2. Bereich **"Zahlung buchen"**
3. Betrag, Datum und **Zahlungsweg** (Überweisung, PayPal, Nachnahme, Sonstige) eintragen — der Zahlungsweg erscheint in der Zahlungsinfo auf der Rechnung
4. → Zahlungsstatus wird auf "bezahlt" bzw. "teilbezahlt" gesetzt

> **Bar oder Karte?** Nicht hier buchen, sondern an der Kasse (Menü → „💶 Rechnung bezahlen") — Barzahlungen sind RKSV-pflichtig und brauchen einen Zahlbeleg.
5. System sendet automatisch Auftragsbestätigung per Mail (wenn konfiguriert)

> **Vorkasse-Aufträge:** Lager-Abgang wird erst bei Zahlungseingang gebucht.

---

## Versandart ändern (Abholung ↔ Versand) {#versandart}

Kunde hat online „Abholung" gewählt, will aber doch geschickt bekommen — oder umgekehrt:

1. Auftrag öffnen → neben dem Lieferstatus **⇄ Versandart ändern**
2. Bei Versand: **Versandklasse** wählen (Versandkosten werden vorgeschlagen, änderbar). Bei Abholung: Versandkosten 0
3. **Umstellen**

| Was passiert | Abholung → Versand | Versand → Abholung |
|---|---|---|
| Versandkosten | kommen dazu | fallen weg |
| Schon gepackte Ware | liegt im Abholfach → wird ins Lager zurückgebucht (gleiche Charge), Auftrag zurück auf „in Bearbeitung" → normal über den Packplatz verschicken (Ware aus dem Abholfach nehmen) | schon Verschicktes gilt als übergeben |
| Schon bezahlt | Status **teilbezahlt**, Kunde bekommt eine **Mail mit dem Restbetrag** + Bankdaten. Der Packplatz zeigt „Restbetrag offen" und fragt vor dem Versand **„wirklich versenden?"** — so kann man auch erst nach Zahlung schicken | Kunde hat **Guthaben** — die Kasse fragt bei der Abholung: **bar auszahlen oder als Gutschein** |

Nicht möglich, wenn der Auftrag schon ganz verschickt/abgeholt ist, oder wenn die Versandkosten schon auf einer Rechnung stehen (dann Rechnungskorrektur). Der Online-Shop wird nicht automatisch umgestellt; die Änderung steht im Verlauf des Auftrags.

---

## Status manuell ändern

> „Abgeschlossen" und „Retoure offen" lassen sich nicht von Hand setzen — das macht das System (siehe oben).

1. Auftrag öffnen → Bereich "Status"
2. Gewünschten Zahlungs- oder Lieferstatus wählen
3. Optional: Notiz hinzufügen
4. Speichern

---

## Auftrag bearbeiten

Positionen und Stammdaten können geändert werden, solange der Auftrag noch nicht "versendet" oder "abgeschlossen" ist.

1. Auftrag öffnen → **Bearbeiten**-Button
2. Artikel hinzufügen / entfernen / Menge ändern
3. Speichern → Gesamtbetrag wird neu berechnet

---

## Auftrag stornieren

1. Auftrag öffnen → **Stornieren**-Button
2. Bestätigung
3. System setzt beide Status auf "storniert"
4. Bei bereits ausgebuchtem Lagerstand: Ware wird automatisch zurückgebucht

> **Achtung:** Wenn die Ware bereits auf dem Weg ist (versendet), erscheint eine Warnung. In dem Fall muss die Retoure manuell über den Packplatz abgewickelt werden.

---

## Mahnwesen

Der Cronjob `cron/mahnwesen.php` läuft täglich.

**Vorkasse** (gerechnet ab Bestelldatum):

| Zeitraum | Aktion |
|----------|--------|
| 14 Tage offen | Zahlungserinnerung per Mail |
| 30 Tage offen | Automatische Stornierung (Reservierungen werden frei, Kunde bekommt eine Mail) |

**Rechnung** (gerechnet ab **Fälligkeit** der Rechnung — sie ergibt sich aus der Zahlungsbedingung von Auftrag oder Kunde, sonst 14 Tage):

| Wann | Stufe | Wie |
|------|-------|-----|
| 7 Tage nach Fälligkeit | Zahlungserinnerung (ohne Gebühr) | automatisch per Mail |
| 14 Tage nach der Erinnerung | 1. Mahnung (Gebühr 5 €) | **Vorschlag** → Freigabe |
| 14 Tage nach der 1. Mahnung | 2. Mahnung (Gebühr 10 €) | **Vorschlag** → Freigabe |
| Frist der 2. Mahnung abgelaufen | — | Liste „Manuell klären“ (anrufen, Inkasso, Anwalt) |

Tage und Gebühren: **Einstellungen → System → Mahnwesen**. Bei Rechnung gibt es nie einen automatischen Storno — die Ware ist meist schon beim Kunden.

**Verkauf → Mahnwesen** (die Zahl im Menü = Mahnungen zur Freigabe):
- **Freigeben** erzeugt das Mahnungs-PDF (landet im Dokumentenarchiv des Auftrags) und schickt es per Mail. Hat der Kunde keine E-Mail-Adresse, kommt ein Hinweis — PDF dann ausdrucken und per Post schicken (Verlauf → PDF).
- **Verwerfen**: diese Stufe wird für den Auftrag nicht mehr vorgeschlagen.
- **Gebühr erlassen** (Kulanz): die Mahngebühr wird nicht mehr verlangt.

Die **Mahngebühr gehört zum offenen Betrag**: Zahlt der Kunde nur den Rechnungsbetrag, bleibt der Auftrag „teilbezahlt“, bis auch die Gebühr bezahlt oder erlassen ist. Die Auftragsseite zeigt „Mahngebühren (offen)“ im Zahlungsverlauf.

---

## Händler-Außenlager (Kommission)

Händler verkaufen eure Ware in ihrem Geschäft. Die Ware bleibt **euer Bestand**, bis der Händler den Verkauf meldet; erst dann wird verrechnet.

**Einrichten:** Kunde öffnen → **🏬 Als Händler einrichten**. Der Kunde kommt in die Kundengruppe „Händler“ und bekommt ein eigenes Außenlager. Übersicht aller Händler: Verkauf → **Händler**.

**Händlerpreis:**
- Standard: **Rabatt in %** auf den Endkunden-Verkaufspreis (netto) — einstellen unter Verkauf → Händler.
- Ein eigener Preis für die Kundengruppe „Händler“ im Preise-Reiter des Artikels hat Vorrang.
- Der Preis wird **bei der Lieferung** festgehalten. Ändert sich euer VK später, gilt für bereits gelieferte Ware weiter der alte Preis.

**Ablauf:**
1. **🚚 Ware liefern** — Artikel scannen oder suchen, Mengen eintragen → Umbuchung ins Außenlager + **Lieferschein (HL-Nummer)** mit Händlerpreis netto und empfohlenem Endkunden-VK.
2. **🧾 Verkauf melden & abrechnen** — Händler meldet entweder **was verkauft wurde** oder **was noch da ist** (dann rechnet das ERP die Differenz). Preise je Zeile vor der Rechnung korrigierbar; Spalte **Schwund** für verlorene/beschädigte Ware (wird nicht verrechnet, Grund in die Notiz). → Auftrag (Kanal „Händler“) + **Rechnung mit Netto-Preisen + USt**; erst jetzt wird der Bestand im Außenlager abgebucht.
3. **↩ Rücknahme** — Ware geht zurück ins eigene Lager (Rücknahmeschein HR-Nummer).
4. **⚠ Schwund** — auch einzeln buchbar (HS-Nummer, Grund Pflicht).

Wurde Ware zu verschiedenen Preisen geliefert, wird beim Abrechnen die **älteste Lieferung zuerst** verrechnet (jeweils mit ihrem Preis).

Belege, Rechnungen und alle Lagerbewegungen stehen auf der Händler-Seite in den Reitern **Belege & Rechnungen** und **Bewegungen**. Zahlung, Mahnung und Buchhaltungsexport laufen über den normalen Auftrag.

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Auftragsnummer fehlt | Wurde der Auftrag tatsächlich gespeichert? F5 drücken und in Liste suchen |
| Preis im Auftrag ist falsch | Bearbeiten → Position-Preis manuell korrigieren |
| Mahnung wurde nicht gesendet | Mail-Einstellungen prüfen (Einstellungen → Mail/SMTP → Test-Mail) |
| Storno geht nicht | Auftrag schon "abgeschlossen"? Dann muss Storno manuell mit Notiz vermerkt werden |
