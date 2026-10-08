# 11 — Kasse (POS)

## Was ist die Kasse?

Die Kasse ist das Point-of-Sale-System für das Ladengeschäft. Sie ist eine **eigene Oberfläche** — getrennt vom normalen ERP, optimiert für Touchscreen und Barcode-Scanner.

**Adresse:** `http://localhost/mealana/kasse/`

> Die Kasse bucht automatisch Lagerabgänge und erzeugt Bons für den 80mm Thermodrucker.

---

## Bon erstellen — Normaler Verkauf

### Schritt für Schritt:

**Schritt 1: Artikel erfassen**

- **EAN scannen** — Barcode-Scanner auf die Ware richten → Artikel erscheint sofort im Warenkorb
- **Namenssuche** — Lupe-Symbol oder Taste: Artikelname/Nummer eingeben → Auswahl aus Liste
- **Varianten-Artikel:** Wenn der Artikel Farben/Größen hat (z.B. DROPS Lima) → Varianten-Modal öffnet sich → Kind-Artikel wählen

> **Tipp:** Mehrfachmengen — Zahl eintippen und dann einmal scannen → Menge wird sofort eingetragen.

**Schritt 2: Rabatt (optional)**

Auf eine Position klicken → Rabatt % eingeben → Preis wird neu berechnet.

**Schritt 3: Zahlart wählen**

| Zahlart | Was passiert |
|---------|-------------|
| **Bar** | Gegeben-Betrag eingeben → Rückgeld wird berechnet angezeigt |
| **Karte extern** | SumUp/Bankomat — Betrag extern bestätigen, hier nur dokumentieren |
| **Gutschein** | Code eingeben → Prüfen → Guthaben wird angezeigt; reicht es nicht, Rest bar oder mit Karte. Details: [14 Gutscheine](14_gutscheine.md) |

**Schritt 4: Bon speichern**

→ **Bon erstellen** drücken  
→ Bon wird gedruckt (80mm Thermodrucker)  
→ Lagerabgang wird automatisch gebucht

---

## Divers-Artikel (freier Preis)

Für Positionen ohne Stammdatensatz (Sonderpositionen, Workshops, Messe-Ware):

1. Optional zuerst die **Menge** am Numpad tippen + **× Mal** (z.B. `3 × Mal`)
2. **+ Artikel** (bzw. "+ Freier Artikel") klicken
3. **Artikelgruppe** als Kachel antippen — sie bestimmt das Erlöskonto in der Buchhaltung.
   Bezeichnung und Steuersatz werden aus der Gruppe vorbelegt, beides ist änderbar.
4. **Preis pro Stück** über das Numpad im Dialog (oder die Tastatur) eingeben
5. **✓ Hinzufügen** → Position landet mit der vorgewählten Menge im Bon

Der Preis bleibt danach im Warenkorb änderbar (Zeile antippen → Preis tippen → Preis).

**Welche Gruppen als Kachel erscheinen**, wird unter Buchhaltung → Artikelgruppen festgelegt
("Als Kachel wählbar" + "Standard-Steuer"). Neue Gruppen erscheinen automatisch.

### Gruppen-Tasten in der Schnellwahl

Ein Schnellwahl-Slot kann statt eines Artikels auch eine **Gruppe** sein (gelbe Taste).
Ein Druck öffnet den Dialog mit fertig gewählter Gruppe — nur noch Preis tippen.
Einrichten: ⚙ neben "SCHNELLWAHL" im Kassenschirm (Recht Kassen-Verwaltung) bzw.
Einstellungen → Kassen → Bearbeiten → Schnellwahl-Tasten → "oder Gruppen-Taste".

---

## Freitext-Retour (Rückgabe ohne Auftrag)

Für Rückgaben von Artikeln, die **nicht** als Auftrag im ERP existieren (z.B. alte JTL-Verkäufe von vor der Umstellung).

1. **⚙ Menü → ↩ Freitext-Retour**
2. Artikel suchen (Name, Nummer oder EAN)
3. Menge und Rückerstattungs-Preis pro Stück eintragen
4. Bei chargenpflichtigen Artikeln (z.B. Garn): **Charge eintragen** oder **"Charge unbekannt"** anhaken — eine der beiden Optionen ist Pflicht
5. **↩ Zurücknehmen** — Zeile erscheint rot mit ↩-Symbol im Warenkorb, Menge negativ
6. Normal weiter zu **Bezahlen** — bei reiner Retoure wird der Betrag bar ausgezahlt

> Die Ware muss danach am Packplatz unter **Rücklagerungen** wieder eingebucht werden (siehe [05 Packplatz](05_packplatz.md)) — die Kasse bucht nur den finanziellen Ausgleich, nicht den Lagerbestand.

---

## Chargen-Dialog

Bei Garnen und anderen Artikel mit Chargen-Pflicht öffnet sich nach dem Scannen automatisch ein Dialog:

| Option | Wann verwenden |
|--------|---------------|
| **Charge auswählen** (Liste) | Wenn die Charge bekannt ist (älteste wird zuerst vorgeschlagen — FIFO) |
| **Neue Charge eintragen** | Wenn es eine neue Lieferung gibt die noch nicht eingetragen wurde |
| **Ohne Charge** | Nur wenn die Charge wirklich unbekannt ist |

> **Wichtig für Wolle:** Die Charge sichert Farbkonsistenz. Immer die richtige Partie eintragen!

---

## Abholbereit+bezahlt — Aufträge übergeben

Wenn ein ERP-Auftrag auf "Abholbereit" gesetzt und bereits bezahlt ist, erscheint er in der Kasse unter **Offene Auswahl**.

**Ablauf:**

1. Kasse → **Offene Auswahl**
2. Auftrag aus der Liste wählen (Auftragsnummer sichtbar)
3. Tatsächlich mitgenommene Mengen eingeben (kann vom Auftrag abweichen)
4. → System erkennt automatisch den Fall:

| Fall | Was passiert |
|------|-------------|
| **Exakt** — Mengen stimmen | Kein Bon nötig, Auftrag direkt abgeschlossen |
| **Retour** — Kunde nimmt weniger | Retour-Bon wird erstellt, Differenz in Bar zurückgezahlt |
| **Extra** — Kunde nimmt mehr | Extra-Bon nur für die Zugaben, Zusatzbetrag einzahlen |
| **Mix** — teils retour, teils extra | Retour-Bon + Extra-Bon werden erstellt |

---

## Sammelabholung — mehrere Online-Bestellungen eines Kunden

Hat ein Kunde mehrere Bestellungen zur Abholung, holt er sie mit **einem** Bon ab.

**Ablauf:**

1. **📦 Auftrag** → eine der Bestellungen suchen und anklicken
2. Hat derselbe Kunde weitere offene Abholungen, fragt die Kasse automatisch nach:
   - Abholbereite Aufträge sind schon angehakt
   - Noch nicht gepackte Aufträge stehen dabei, sind aber **nicht** angehakt
3. **Ausgewählte mitladen** (oder **Nur diesen**)
4. Der Bon zeigt jeden Auftrag als eigenen Block mit Auftragsnummer und „bezahlt/unbezahlt“
5. Nimmt der Kunde etwas nicht mit: Zeile antippen → **−** (Anzeige „0 von 1 mitgenommen“)
6. Zusätzliche Artikel aus dem Regal einfach dazuscannen (Block „weitere Artikel“)
7. **Bezahlen** — kassiert wird nur, was noch nicht bezahlt ist

| Situation | Was passiert |
|-----------|--------------|
| Auftrag schon online bezahlt | Steht nicht im Kassenbetrag, wird nur abgeschlossen |
| Auftrag unbezahlt | Wird mit dem Bon bezahlt (nur die mitgenommenen Mengen) |
| Teilabholung | Beim Bezahlen fragt die Kasse je Zeile: **„holt er später“** (Rest bleibt gepackt im Abholfach, Auftrag „teilgeliefert“, beim nächsten Laden steht der Rest im Bon) oder **„will er nicht“** (Rest geht in die Rücklagerung am Packplatz, bei bezahlten Aufträgen Geld zurück, Auftrag dafür erledigt) |
| Von einem Auftrag gar nichts mitgenommen | Auftrag bleibt unverändert liegen (✕ im Block nimmt ihn ganz vom Bon) |
| Alle Aufträge bezahlt, alles mitgenommen | Kein Bon nötig, alle Aufträge werden direkt abgeschlossen |

**Gut zu wissen:**
- Nur Aufträge **desselben Kunden** (gleiches Kundenkonto bzw. gleiche E-Mail) lassen sich zusammenfassen. Ein Auftrag eines anderen Kunden ersetzt nach Rückfrage den Bon.
- Auf dem Bon (80 mm und A4) steht jeder Auftrag mit seiner Nummer; bereits bezahlte Aufträge werden als „Abgeholt, bereits bezahlt: …“ genannt.
- Eine **Retoure** aus einem älteren Auftrag geht nicht im selben Sammel-Bon — dafür einen eigenen Bon machen.

**Belege bei der Abholung** (jede Ware steht auf genau einem Beleg):
- Hier kassierte Ware → der **Bon** ist die Rechnung; die A4-Version hängt an der Abholmail.
- Schon vorab bezahlte Ware (Vorkasse/PayPal) → es entsteht automatisch eine **Rechnung**, sie hängt an der Abholmail (zusammen mit dem Bon, falls Extras kassiert wurden).
- „Will er nicht" bei einem bezahlten Auftrag → Rückzahlung erscheint auf dem Bon als **„Rückzahlung (nicht abgeholt)" mit 0 %** — diese Ware war nie verrechnet, es wird eine Anzahlung zurückgezahlt, kein Umsatz gemindert.
- **Guthaben** (Kunde hat mehr bezahlt als der Auftrag jetzt kostet, z.B. Versand auf Abholung umgestellt): beim Bezahlen fragt die Kasse automatisch **„Bar auszahlen" oder „Als Gutschein ausstellen"**. Auf dem Bon steht „Rückzahlung Guthaben zu Auftrag …" mit 0 %. Wie jede Auszahlung braucht das ggf. die Manager-PIN.

---

## Rechnung bezahlen (Zahlbeleg)

Ein Kunde zahlt eine **offene Rechnung** (z.B. Rechnungskauf, verschickte Ware) bar oder mit Karte im Geschäft.

1. Menü (☰) → **💶 Rechnung bezahlen**
2. Rechnungs- oder Auftragsnummer (auch nur ein Teil, z.B. „0045") oder Kundenname eintippen — die Suche läuft beim Tippen. Ein Auftrag mit Abholung, der noch **keine Rechnung** hat (z.B. Zahlart „Rechnung"), wird angezeigt und per Klick ganz normal als Abholung in die Kasse geladen
3. Treffer anklicken — der offene Betrag ist vorausgefüllt (Teilzahlung möglich, mehr als offen geht nicht)
4. **+ Hinzufügen** → Zeile „Zahlung zu Auftrag A-… (Rechnung R-…)" mit **0 %** im Bon
5. Normal bezahlen (bar/Karte)

Der Bon ist ein **Zahlbeleg**: RKSV-signiert, zählt in Kassenbuch und Tagesabschluss, aber **kein neuer Umsatz** — die Umsatzsteuer steht schon auf der Rechnung. Die Zahlung wird beim Auftrag gebucht und erscheint beim Rechnungs-Nachdruck in der Zahlungsinfo („bar an Hauptkasse (Zahlbeleg K1-…)").

- Weitere Artikel dürfen auf demselben Bon sein; eine **Abholung oder Retoure nicht** — dafür einen eigenen Bon.
- Wird der Zahlbeleg storniert, wird die Zahlung beim Auftrag automatisch zurückgenommen.

---

## Bon stornieren

Wenn ein Bon fehlerhaft war:

1. Kasse → **Bon-Journal**
2. Bon suchen (Bon-Nr. oder Datum)
3. → **Stornieren**
4. Storno-Bon wird gedruckt
5. Lagerabgang wird automatisch rückgebucht

> Nur der Kassierer oder Admin darf Bons stornieren!

---

## Kassensturz / Tagesabschluss

### X-Bon (Zwischenbericht)

Zeigt den aktuellen Stand ohne Abschluss — gut für Zwischenkontrollen.

### Z-Bon (Tagesabschluss)

1. Kasse → **Kassensturz**
2. **Zählhilfe:** Scheine und Münzen einzeln eingeben → Summe wird berechnet
3. → **Z-Bon erstellen** — echter Tagesabschluss
4. Z-Bon wird gedruckt (Zusammenfassung des Tages)
5. Eintrag ins Kassenbuch

> **Nach dem Z-Bon:** Restgeld im Kassenfach lassen (Wechselgeld für nächsten Tag). Überschuss entnehmen.

---

## Kassenbuch

Das Kassenbuch protokolliert alle Einlagen und Entnahmen:

- Kasse → **Kassenbuch**
- Einlage: + Betrag, Zweck (z.B. "Wechselgeld zu Beginn")
- Entnahme: − Betrag, Zweck (z.B. "Tageseinnahme entnommen")

---

## Druckerkonfiguration

Der 80mm Thermodrucker muss als **Windows-Standarddrucker** gesetzt sein.  
Der Bon öffnet dann automatisch den Druck-Dialog.

> **Tipp:** Im Browser die Einstellung "Rand: Keine" setzen und "Kopf-/Fußzeile: Aus". Dann passt der Bon perfekt auf 80mm Papier.

---

## RKSV / Signaturprüfung bei Störungen

Ist die Signatureinrichtung (BFR) kurz nicht erreichbar, verkauft die Kasse trotzdem weiter — der Bon zeigt dann "Sicherheitseinrichtung ausgefallen" statt der echten Signatur. Das ist gesetzlich erlaubt und kein Grund zur Sorge.

- **Kasse → 🔏 RKSV** zeigt offene und vergangene Störungen (Ausfall-Historie)
- Für Admins/Technik: dort verlinkt "Rohdaten-Protokoll" — zeigt exakt, was an BFR geschickt wurde und was zurückkam (für Fehlermeldungen an den BFR-Hersteller)

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Artikel wird nicht gefunden | EAN im Artikel-Stammdaten eingetragen? Artikel aktiv? |
| Chargen-Dialog erscheint nicht | Artikel hat charge_pflicht=1? Lagerbestand vorhanden? |
| Bon wird nicht gedruckt | 80mm Drucker als Standarddrucker gesetzt? Browser-Druckdialog erlaubt? |
| Abholbereit-Auftrag nicht in Liste | Auftrag: lieferstatus='abholbereit' UND zahlungsstatus='bezahlt'? |
| Storno geht nicht | Bon bereits storniert? Bon-Journal prüfen |
| Lagerbestand nach Bon falsch | Admin: Lager → Bewegungen → Bon-ID suchen → Buchung prüfen |

---

## Messe — Papier-Messe (Strichliste + händische Belege)

Für Messen ohne Gerät/Strom/Internet (und für Deutschland: kein elektronisches
Aufzeichnungssystem → keine TSE-Frage). Daneben gibt es weiterhin die
**elektronische Messe-Kasse** (Offline-Kasse mit Signatur am Laptop).

**1. Vorbereiten** — Kasse → 🎪 Messe
1. Variante **📝 Papier-Messe** wählen, Messe-Lager + Quell-Lager wählen
2. Artikel scannen, Mengen eintragen → **Umbuchung durchführen**
3. Unter "Offene Papier-Messen" → **🖨 Strichliste** drucken (nach Artikelgruppe sortiert,
   mit leeren Freitext-Zeilen). Nachbuchen geht jederzeit — Liste dann neu drucken.

**2. Auf der Messe** — pro Verkauf Stricherl machen und einen **händischen Beleg mit
Durchschrift** ausstellen (in Österreich Pflicht). Ware ohne Lagerstand / Werbe-Zugaben
in die Freitext-Zeilen.

**3. Lager zurückbuchen** — 🎪 Messe → ↩ Von Messe zurück → Papier-Messe wählen
1. Pro Zeile **verkauft** (Summe Stricherl) und gezählt **zurück** eintragen
2. **Schwund** rechnet sich selbst (mit − verkauft − zurück); rot = Zählfehler, wird nicht gebucht
3. Freitext-Zeilen übertragen (Info-Liste, keine Buchung)
4. **✓ Lager zurückbuchen** — geht nur einmal

**4. Belege nacherfassen** — an der **Signatur-Kasse** (Einzelaufzeichnungspflicht)
1. Pro händischem Beleg: **Beleg-Nr.**, **Belegdatum**, **Bar/Bankomat**
2. Zeilen **Artikelgruppe + Betrag** (Steuer kommt aus der Gruppe, änderbar)
3. **Enter** bzw. ✓ Nacherfassen → eigener signierter Bon mit Vermerk
   „Nacherfassung Messe-Beleg Nr. … vom …". Nr. zählt automatisch weiter.
4. Bar-Belege erhöhen den Kassenstand dieser Kasse → Messe-Bargeld einlegen
   oder danach als Entnahme buchen.

Im Buchhaltungs-Export zählt das **Belegdatum** (nicht der Tag der Nacherfassung).
Ein falsch erfasster Beleg wird im Bon-Journal storniert und kann dann neu erfasst werden.

**5. Messe-Abschluss** — 🖨 Messe-Abschluss drucken und zu den Durchschriften legen:
Belege von–bis, bar/Bankomat, Lager je Zeile, Abgleich Belegsumme ↔ Strichliste-Wert.

Ein Messe-**Auftrag** entsteht bewusst nicht — der Umsatz kommt allein über die Belege.
