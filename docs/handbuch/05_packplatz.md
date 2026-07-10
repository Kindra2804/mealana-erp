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
- Auftrag ist im ERP angelegt und Zahlungsstatus = bezahlt (oder Zahlungsart = Rechnung)
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

## Rücklagerungen — Ware aus Kassen-Retoure einbuchen

Wenn an der Kasse eine Retoure verarbeitet wird (egal ob zu einem Auftrag oder als Freitext-Retour ohne Auftrag), bucht die Kasse **nur den finanziellen Ausgleich** — die Ware liegt danach physisch am Tresen, ist aber noch nicht im Lagerbestand. Diese Liste zeigt genau das.

**Packplatz → Rücklagerungen** (Badge zeigt die Anzahl offener Einträge)

1. Zeile mit der zurückgenommenen Ware suchen — zeigt Artikel, Menge, Herkunfts-Bon (und Auftragsnummer, falls vorhanden)
2. **Einbuchen** klicken
3. Ziel-Lager wählen
4. **Zustand der Ware** wählen (Neu / Gebraucht / Beschädigt / Defekt) — wichtig, da zurückgenommene Ware nicht automatisch wieder als "neu" gilt
5. Bei chargenpflichtigen Artikeln (⚠ "fehlt (Pflicht)" in der Liste): **Charge eintragen**, sonst lässt sich nicht einbuchen
6. **✓ Einbuchen** — Lagerbestand wird erhöht, Eintrag verschwindet aus der Liste

> Anders als bei der normalen Retoure (unten) gibt es hier keine Gutschrift/Mail-Optionen mehr — das ist an der Kasse bereits erledigt, hier geht es nur noch um die physische Einlagerung.

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
