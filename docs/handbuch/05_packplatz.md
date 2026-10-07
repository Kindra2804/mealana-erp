# 05 — Packplatz

## Was ist der Packplatz?

Eine eigene Oberfläche — **extra für den Scan-Arbeitsplatz** (Touchscreen / Tablet + Barcode-Scanner). Sie ist dunkler gestaltet und hat keine Ablenkungen vom Haupt-ERP.

**Adresse:** `http://localhost/mealana/packplatz/`

---

## Hauptmenü

```
┌──────────────┬──────────────┬──────────────┬──────────────┐
│  Warenausgang│  Wareneingang│  Intern      │  Retoure     │
│  (Versand)   │  (Lieferung) │  (geplant)   │  (geplant)   │
└──────────────┴──────────────┴──────────────┴──────────────┘
```

Aktuell fertig: **Warenausgang** und **Wareneingang**.

---

## Warenausgang — Paket versenden

### Voraussetzung:
- Auftrag ist im ERP angelegt und Zahlungsstatus = bezahlt (oder Zahlungsart = Rechnung/Nachnahme)

### Rechnung beim Abschließen (automatisch)
Beim Abschließen entsteht für **jede Lieferung** automatisch eine (Teil-)Rechnung über genau die verschickten Artikel — egal ob schon bezahlt. Nicht gelieferte Artikel stehen als „Noch ausständig" darunter. Die Rechnung geht per eigener Mail an den Kunden; die Versandmail bekommt den Lieferschein.
Hat der Kunde **keine E-Mail-Adresse**, erscheint nach dem Abschließen „🖨 Rechnung drucken" — ausdrucken und ins Paket legen.
Ware, die schon an der Kasse bezahlt wurde (Bon), kommt nicht nochmal auf die Rechnung.

**Welche Aufträge?** Online- und manuelle Aufträge — auch Abholungen, die an der Kasse bezahlt wurden: dort ist der **Kassenbon** der Originalbeleg, die Rechnungskorrektur bezieht sich auf ihn, die Rückzahlung läuft danach im Auftrag über „Rückerstattung buchen" (oder bar an der Kasse). **Reine Kassenverkäufe (K1-…)** werden nur an der Kasse zurückgenommen und erscheinen hier nicht.

**Restbetrag offen:** Ist ein Vorkasse-/PayPal-Auftrag noch nicht voll bezahlt (z.B. nach Umstellung Abholung → Versand), steht rechts „Restbetrag offen: € …" und beim Verpacken kommt die Frage **„Wirklich versenden?"** — mit „Abbrechen" bleibt der Auftrag liegen, bis bezahlt ist.
- Artikel haben EAN eingetragen (sonst kann der Scanner sie nicht erkennen)

### Ablauf:

**Packplatz → Warenausgang**

**Schritt 1: Auftrag wählen**

*Option A: Über Pickliste*
- Links erscheinen offene Picklisten (von Babsi erstellt)
- Picklisten-Nummer scannen oder anklicken

*Option B: Direkteingabe*
- Rechts: Auftragsnummer eintippen oder scannen
- Aus der Liste der offenen Aufträge wählen

---

**Schritt 2: Artikel scannen**

Die Tabelle zeigt alle Positionen des Auftrags.

| Farbe der Zeile | Bedeutung |
|-----------------|-----------|
| Grau | Noch nicht gescannt |
| Blau (aktiv) | Gerade gescannt, Menge noch nicht vollständig |
| Grün ✓ | Menge vollständig gescannt |
| Rot ✗ | Zu viele gescannt! |

**Scan-Vorgang:**
1. Barcode-Scanner auf das Feld "EAN scannen" richten
2. Artikel-Barcode scannen → Zeile wird aktualisiert
3. Bei Artikeln ohne Barcode: Artikelnummer manuell eingeben + Enter
4. Rechts wird das Bild des gescannten Artikels angezeigt

**Vorwahl (Menge vorwählen):**
- Wenn z.B. 5 Stück des gleichen Artikels kommen: Zahl "5" eingeben, dann einmal scannen → 5 werden gutgeschrieben

**EAN direkt beim Picken nachtragen:**
Fehlt einem Artikel noch der Barcode, muss man dafür nicht extra ins Artikelmodul wechseln: Doppelklick auf die EAN-Zelle der Zeile (oder Klick auf "⚠ Kein EAN — nachtragen") öffnet ein Eingabefeld direkt auf dem Scan-Bildschirm. Neuen EAN eintippen/scannen → speichert sofort im Artikel und kann direkt weitergescannt werden, ohne die Seite neu zu laden.

---

**Schritt 3: Verpacken**

Wenn alle Zeilen **grün** sind, wird der Button **"Verpacken"** aktiv.

1. **"Verpacken"** klicken
2. Overlay erscheint:
   - **Gewicht:** Bereits vorausgefüllt (aus Artikelgewichten berechnet) — bei Bedarf korrigieren
   - **Trackingnummer:** Scanner auf das aufgedruckte Label halten → Barcode vom Label scannen
3. → **Abschließen**

> Das System setzt den Auftrag auf "versendet" und sendet automatisch die Versandbestätigung per E-Mail an den Kunden.

> Wenn ein PLC-Ordner konfiguriert ist, wird automatisch eine EasyPak-XML-Datei für den Paketdrucker erzeugt.

---

**Schritt 4: Nächster Auftrag**

Bei Picklisten: Das System springt automatisch zum nächsten Auftrag der gleichen Pickliste.  
Bei Einzelaufträgen: Zurück zur Übersicht.

---

## Teillieferung

Wenn nicht alle Artikel lieferbar sind (z.B. einer ist gerade nicht auf Lager):

1. Statt "Verpacken": Button **"Teillieferung"** klicken
2. Gleiches Overlay: Gewicht + Tracking eingeben
3. System versendet was gescannt wurde — Auftrag bleibt offen mit Status "teilgeliefert"

---

## Wichtige Hinweise

> **EAN immer im Artikel eintragen!** Ohne EAN muss die Artikelnummer manuell eingetippt werden — das kostet Zeit und ist fehleranfälliger.

> **Gewicht prüfen!** Das vorausgefüllte Gewicht kommt aus den Artikel-Stammdaten. Wenn Verpackungsmaterial das Gewicht deutlich erhöht, manuell korrigieren.

> **Escape** schließt das Overlay ohne zu versenden.

---

## Zustand zurückgekommener Ware — gilt für Retoure UND Rücklagerungen

| Zustand | Wohin wird gebucht |
|---------|--------------------|
| **Neu** | Originalartikel (normal verkaufbar, auch online) |
| **Retour / Gebraucht / Beschädigt** | **Zustandsartikel** = Artikelnummer mit Anhang (`D-101071-RET`, `-GEB`, `-BSC`). Wird beim ersten Mal automatisch angelegt (übernimmt Gruppe, Chargenpflicht und den aktuellen Preis als Startwert — B-Ware-Preis danach am Artikel anpassen). **Zählt nie für den Onlineshop.** |
| **Defekt** | Nicht in den Bestand — wird als Retoure-Eingang und sofort als **Schwund** ausgebucht, steht also in der Lagerverfolgung (Bewegungslog) des Artikels |

Zustandsartikel finden: Artikelliste → Filter **Status / Qualität → Zustand (B-Ware)**, oder direkt nach der Nummer mit Anhang suchen.

## Rücklagerungen — Ware aus Kassen-Retoure oder Rechnungskorrektur einbuchen

Wenn an der Kasse eine Retoure verarbeitet wird (egal ob bar oder als Gutschein erstattet, zu einem Auftrag oder als Freitext-Retour) oder im ERP eine Rechnungskorrektur mit **"Ware zur Prüfung an den Packplatz"** erstellt wird, ist **nur der finanzielle Ausgleich** erledigt — die Ware ist noch nicht im Lagerbestand. Diese Liste zeigt genau das.

**Packplatz → Rücklagerungen** (Badge zeigt die Anzahl offener Einträge)

1. Zeile suchen — zeigt Artikel, Menge, Charge und Herkunft (Bon oder Rechnungskorrektur, ggf. Auftragsnummer). Bei einer Rechnungskorrektur sind Charge und Lager schon aus dem ursprünglichen Verkauf vorbefüllt; wurden mehrere Chargen verkauft, gibt es eine Zeile pro Charge.
2. **Einbuchen** klicken
3. Ziel-Lager prüfen/wählen
4. **Zustand der Ware** wählen (siehe Tabelle oben)
5. Bei chargenpflichtigen Artikeln: **Charge prüfen/eintragen**, sonst lässt sich nicht einbuchen (außer "Defekt")
6. **✓ Einbuchen** — Eintrag verschwindet aus der Liste

> Anders als bei der normalen Retoure (unten) gibt es hier keine Korrektur-/Mail-Optionen — das ist bereits erledigt, hier geht es nur noch um Prüfung und Einlagerung.

## Retoure (Rücksendung per Post)

**Packplatz → Retoure** → Auftrag suchen → Positionen anhaken.

- Pro Position **Menge · Charge · Zustand**. Die Charge ist mit der verkauften vorbefüllt ("verkauft: A123 (3), B456 (2)"). Kamen Stücke aus mehreren Chargen oder in unterschiedlichem Zustand zurück: **＋ Charge** für eine weitere Zeile.
- **Schutz vor Doppelbuchung:** Es geht nur die Menge, die noch nicht zurückgekommen ist ("max. X"). Wurde schon an der Kasse retourniert oder eine Rechnungskorrektur erstellt, steht das an der Position ("↩ 2 schon zurück", "€ 2 gutgeschrieben") — eine Rechnungskorrektur ist nur für die verrechnete, noch nicht korrigierte Menge möglich.

---

## Wareneingang am Packplatz

Wenn eine Lieferung direkt am Packplatz eingebucht werden soll:

**Packplatz → Wareneingang** → öffnet das normale Lager-Wareneingang-Interface.

Details: siehe [03 Lager](03_lager.md).

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Scanner erkennt Artikel nicht | EAN im Artikel-Stammdaten eingetragen? Strichcode leserlich? |
| "Verpacken"-Button bleibt grau | Noch nicht alle Positionen grün — rot markierte Zeilen prüfen (zu viel gescannt?) |
| Tracking-Feld akzeptiert nichts | Mindestlänge 3 Zeichen — Label-Barcode korrekt eingescannt? |
| Auftrag erscheint nicht in der Liste | Zahlungsstatus "bezahlt"? Lieferstatus "neu" oder "in_bearbeitung"? |
| EasyPak-Datei wurde nicht erstellt | Einstellungen → System → PLC-Ordner konfiguriert? Ordner existiert? |
