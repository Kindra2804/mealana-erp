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

## Konfigurator: Schild-Bausteine und fertige Schilder

**Artikel → Schild-Bausteine:** Jeder Wert der Schild-Layouts bekommt hier **einmal für alle Layouts**:
- **Kürzel** – Baustein der Artikelnummer fertiger Schilder (Buchstaben/Ziffern, max. 12 Zeichen, je Achse eindeutig).
- **Rohmaterial** – Artikelnummer des Lagerartikels (z.B. Filzplatte rot). Die Ampel zeigt den Bestand (grün / gelb ≤ Mindestbestand / rot leer).

Rohmaterial leer → im Shop ist der Wert ausgegraut („derzeit nicht verfügbar“) und wird auch serverseitig abgelehnt; an der Kasse nur Hinweis „Material leer“. Nach dem Einbuchen von Material gibt der nächste Sync den Wert frei.

**Fertiges Schild anlegen / einbuchen** (im Layout-Artikel, Karte „Konfigurator“):
1. Werte des gebauten Schilds wählen, Menge + Lager, beim ersten Mal die Kategorie (z.B. „Fertige Schilder“, wird gemerkt).
2. Artikelnummer = Layout-Nummer + Kürzel, z.B. `ID-W01-20-2F-BL-WS-FI-TU` (max. 30 Zeichen).
3. Gibt es die Kombination schon → **nur einbuchen** (und wieder aktivieren). Neu → eigener Lagerartikel mit Hersteller, Gruppe, Steuer, Shop-Kanälen vom Layout und dem Konfigurator-Preis.
4. Foto des fertigen Schilds danach im Artikel hochladen.

**Optionen auf andere Layouts übertragen** (im fertig gepflegten Layout, Karte „Konfigurator“): Ziel-Layouts anhaken → **Probelauf** → „Jetzt übertragen“. Die Ziele bekommen dieselben Achsen, Werte, Reihenfolge, Aufpreise und Bedingungen. Gleichnamige Werte werden aktualisiert (Shop-Verknüpfung bleibt), fehlende angelegt, überzählige entfernt. Der Grundpreis je Layout bleibt. Werte, die schon in Aufträgen stehen, bleiben erhalten und werden in der Liste gemeldet.

Ein Wert ohne Kürzel fällt aus der Nummer (z.B. Glitzer „nein, Danke“), aber nur, wenn er in seiner Achse der einzige ohne Kürzel ist.

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| "Artikelnummer bereits vergeben" | Eine andere Artikelnummer wählen — z.B. Suffix `-V2` |
| Bild lädt nicht hoch | Dateiformat prüfen (JPG/PNG/GIF/WebP) · Dateigröße max. 10 MB |
| Kind-Artikel hat falschen Preis | Vater-Preis ändern → wird automatisch an Kinder propagiert |
| EAN wird als "schon vorhanden" abgelehnt | EAN ist einem anderen Artikel zugeordnet — suchen und bereinigen |

## GPSR-Texte bereinigen (Artikel → GPSR-Texte)

Aus JTL stehen bei vielen Artikeln (KnitPro, TOPP, Addi, ProLana …) die Herstellerangaben „Kontaktinformation / Verantwortliche Person gem. Art. 19 EU GPSR“ in der Beschreibung. Im Shop stand das doppelt: Die Pflichtangaben zeigt der Shop ohnehin im Reiter **Produktsicherheit** aus den Hersteller-Stammdaten.

1. Seite öffnen: Sie zeigt die Zahl der betroffenen Artikel (nach Hersteller) und ob danach eigener Text bleibt. Es wird noch nichts geändert.
2. Steht oben ein blaues **!** „Adresse fehlt“: zuerst unter Artikel → Hersteller die Adresse nachtragen.
3. **„GPSR-Texte bei … Artikeln entfernen“** schneidet ab der ersten GPSR-Überschrift ab (Väter und Kinder, Lang- und Kurzbeschreibung) und merkt die Artikel für den Shop-Abgleich vor.

Die alten Texte werden vorher in `erp/storage/gpsr_sicherung/` gesichert (JSON mit Artikelnummer und altem Text).

