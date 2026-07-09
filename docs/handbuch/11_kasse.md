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
| **Gutschein** | Gutschein-Code eingeben → Betrag wird abgezogen |

**Schritt 4: Bon speichern**

→ **Bon erstellen** drücken  
→ Bon wird gedruckt (80mm Thermodrucker)  
→ Lagerabgang wird automatisch gebucht

---

## Divers-Artikel (freier Preis)

Für Positionen ohne Stammdatensatz (Sonderpositionen, Spenden, Verpackung):

1. **Divers** klicken
2. Beschreibung eingeben + Betrag eingeben
3. Steuerklasse wählen (20% / 10%)
4. → Wird als freie Position in den Bon eingefügt

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
