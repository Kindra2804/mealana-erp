# 01 — Artikel

## Wofür?

Hier werden alle Produkte verwaltet: Stammdaten, Preise, Bilder, Varianten (Farben/Größen), Kategorien und Merkmale.

---

## Artikel-Liste

**Navigation:** Artikel → Artikelliste

Die Liste zeigt alle aktiven Artikel. Oben gibt es Filter:
- **Suche:** Artikelnummer, Name, EAN
- **Kategorie:** nur Artikel dieser Kategorie anzeigen
- **Typ:** Standard, Varianten-Vater, Varianten-Kind
- **Status:** Aktiv / Inaktiv / Auslauf

**Spalten auswählen:** Rechts oben → Spalten-Picker (Zahnrad-Icon)

> **Tipp:** Mit Klick auf einen Artikel-Namen kommt man direkt zur Detailseite.

---

## Neuen Artikel anlegen

**Navigation:** Artikel → Neuer Artikel

### Schritt für Schritt:

1. **Artikelnummer** eingeben (z.B. `DROPS-LIMA-50`) — muss eindeutig sein
2. **Name** eingeben (erscheint so im Shop und auf Dokumenten)
3. **Artikeltyp** wählen: Standard (normales Produkt) oder Varianten-Vater (wenn es Farben/Größen gibt)
4. **Hersteller** und **Einheit** auswählen
5. **Steuerklasse** wählen (meistens 20% Standard)
6. **EAN/GTIN** eingeben — wenn vorhanden, unbedingt eintragen (für Packplatz-Scan wichtig!)
7. **Brutto-VK** eingeben (Verkaufspreis mit Steuer)
8. **Kategorien** über den Button "Kategorien bearbeiten" zuweisen
9. → **Speichern**

> **Wichtig:** Ohne EAN kann der Barcode-Scanner am Packplatz den Artikel nicht erkennen. EAN immer eintragen!

### Pflichtfelder:

| Feld | Warum |
|------|-------|
| Artikelnummer | Eindeutige Kennung im System |
| Name | Anzeige im Shop und auf Dokumenten |
| Steuerklasse | Für korrekte Steuern |
| Einheit | z.B. "Stück", "Knäuel" |

---

## Artikel bearbeiten

1. Artikel in der Liste anklicken
2. Oben rechts: **Bearbeiten**-Button
3. Felder ändern
4. **Speichern**

> **Tipp:** Bei Varianten-Vater-Artikeln werden viele Felder automatisch an alle Kinder-Artikel (Farben/Größen) weitergegeben. Einzelne Kinder kann man separat bearbeiten.

---

## Varianten (Farben, Größen) {#varianten}

Varianten-Artikel bestehen aus:
- **Vater-Artikel** (z.B. "DROPS Lima") — enthält Stammdaten
- **Kind-Artikel** (z.B. "DROPS Lima Farbe 01", "DROPS Lima Farbe 02") — je eine Kombination

### Variante anlegen:

**Voraussetzung:** Artikel muss als Typ "Varianten-Vater" angelegt sein.

1. Zum Vater-Artikel → Tab **Varianten**
2. **Achsen zuweisen** (z.B. "Farbe" oder "Größe")
3. Werte für jede Achse eingeben (z.B. "Rot", "Blau", "Grün")
4. Speichern
5. Tab **Varianten** → **Kombinationen erstellen**
6. Artikelnummern und Namen für jede Kombination vergeben
7. **Erstellen**

> Das System legt automatisch alle Kombinationen an und übernimmt Stammdaten, Kategorien und Preise vom Vater.

---

## Bilder

1. Artikel-Detailseite → Tab **Bilder**
2. Bild per Drag & Drop ins Feld ziehen (oder klicken → Datei auswählen)
3. Das System verkleinert das Bild automatisch auf max. 1920px
4. **Hauptbild:** Stern ☆ neben dem Bild anklicken
5. **Reihenfolge:** Pfeile ↑ / ↓ verwenden
6. **Alt-Text** (Bildbeschreibung) eingeben — wichtig für Barrierefreiheit und Google

> **Erlaubte Formate:** JPG, PNG, GIF, WebP  
> **Größe:** Das System verkleinert automatisch — Originaldatei darf groß sein.

---

## Merkmale (technische Eigenschaften)

Merkmale sind zusätzliche Eigenschaften eines Artikels (z.B. Nadelstärke, Fadenstärke, Material).

1. Artikel-Detailseite → Tab **Merkmale**
2. Merkmal auswählen (aus der globalen Liste)
3. Wert eingeben oder aus vorhandenen Werten wählen
4. Speichern

> Merkmale werden im WooCommerce-Shop als Produktattribute angezeigt.

---

## Kategorien

Kategorien organisieren die Artikel im Shop und im ERP.

- **Zuweisen:** Im Artikel-Formular → Button "Kategorien bearbeiten" → Checkboxen setzen → Übernehmen
- **Verwalten:** Artikel → Kategorien verwalten (Baum, Drag-Drop Sortierung)
- **Neue Kategorie:** Im Kategorie-Modal → "Neue Kategorie" → Name eingeben → Anlegen

> Ein Artikel kann mehreren Kategorien zugeordnet sein.

---

## Artikel deaktivieren / reaktivieren

- **Deaktivieren:** Artikel-Detail → Button "Deaktivieren" (Artikel bleibt im System, wird nur ausgeblendet)
- **Reaktivieren:** In der Liste auf "Inaktiv" filtern → Artikel öffnen → Button "Reaktivieren"

> Deaktivierte Artikel werden im Shop nicht angezeigt und können nicht bestellt werden.

---

## Artikel als Auslaufartikel markieren

Wenn ein Artikel nicht mehr nachbestellt wird:

1. Artikel-Detail → Checkbox "Auslaufartikel" aktivieren
2. Der Artikel bleibt aktiv bis der Bestand 0 erreicht
3. Bei Wareneingang auf Null-Bestand: System deaktiviert Auslauf automatisch

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| "Artikelnummer bereits vergeben" | Eine andere Artikelnummer wählen — z.B. Suffix `-V2` |
| Bild lädt nicht hoch | Dateiformat prüfen (JPG/PNG/GIF/WebP) · Dateigröße max. 10 MB |
| Kind-Artikel hat falschen Preis | Vater-Preis ändern → wird automatisch an Kinder propagiert |
| EAN wird als "schon vorhanden" abgelehnt | EAN ist einem anderen Artikel zugeordnet — suchen und bereinigen |
