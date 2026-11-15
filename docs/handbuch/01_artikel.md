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
| Einheit | z.B. "Stück", "Knäuel" (Vorgabe: Stück) |

> **Grundpreis:** Ist beim neuen Artikel erst einmal aus. Bei Garn wird er automatisch eingeschaltet (Bezugsmenge 100), sobald Inhalt/Menge und Einheit eingetragen sind. Bei Meterware selbst einschalten.

---

## Artikel kopieren

**Navigation:** Artikel öffnen → **Kopieren**

1. Neuen **Namen** und neue **Artikelnummer** eintragen (vorbelegt mit „-KOPIE“)
2. Auswählen, was mitkommen soll: Preise, Kategorien, Merkmale, Lieferanten, Überverkauf
3. → **Artikel kopieren** — die Kopie ist sofort **aktiv**

Übernommen werden alle Stammdaten inkl. Hersteller, Steuerklasse, **Artikelgruppe**, Einheit, Inhalt, Gewicht, Maße, Grundpreis und Mindestabnahme.

> **Nicht mitkopiert** (bei Bedarf in der Kopie selbst eintragen): UVP, „Konfigurierbar“, „Keine Lagerbestandsführung“, „Hervorgehoben“, Plattenmaß (Schilder), Bilder, EAN, Lagerbestand und Shop-Kanäle.

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
6. **Alt-Text** (Bildbeschreibung) eingeben — wichtig für Barrierefreiheit und Google. **Leer lassen ist okay:**
   der Shop-Abgleich schickt dann automatisch den Artikelnamen (bei Varianten mit Farbe/Größe). Das gilt auch für
   Bilder aus dem JTL-Bilder-Import — bei neuen Artikeln/Bildern ist nichts extra zu tun. Einen eigenen Alt-Text nur,
   wenn das Bild mehr zeigt als den Artikel (z.B. „Mütze aus Karisma, gestrickt, Farbe natur“).
   Nur einmalig für Bilder, die vor Version 1.0.19 schon im Shop waren: `php scripts/alt_texte_nachziehen.php <shop-slug>`.

> **Erlaubte Formate:** JPG, PNG, GIF, WebP  
> **Größe:** Das System verkleinert automatisch — Originaldatei darf groß sein.

---

## Merkmale (technische Eigenschaften)

Merkmale sind zusätzliche Eigenschaften eines Artikels. Bei Garnen kommen sie auch auf die Preisschilder.

| Merkmal | Wofür | Beispiel |
|---|---|---|
| Wollqualität | Shop-Filter (mehrere möglich) | Schurwollmischung, Sockenwolle |
| Zusammensetzung | genauer Wert fürs Schild | 75% Schurwolle, 25% Polyamid |
| Gewicht/Länge | genauer Wert fürs Schild | 50 g = 125 m |
| Lauflänge je 100 g | Shop-Filter, **wird automatisch** aus Gewicht/Länge gesetzt | 151–250 m |
| Nadelstärke | Shop-Filter (mehrere möglich), Schild zeigt kleinste–größte | 3, 3,5, 3-3,5 |
| Maschenprobe | genauer Wert fürs Schild | 10 x 10 cm = 21 M x 28 R |
| Maschenprobe/10 cm | Shop-Filter (klassische Abstufung), **wird automatisch** aus der Maschenprobe gesetzt | 21–25 M |
| Garngruppe | Shop-Filter nach Drops-Garngruppen A–F | B 22 - 20 Maschen |
| Auszeichnung | Badges am Schild | BIO, GOTS, Handgefärbt |
| Pflege | Pflegesymbole am Schild (mehrere) | Handwäsche, Nicht bleichen |

**Merkmale setzen:**
1. Artikel-Detailseite → Tab **Merkmale**
2. Beim Merkmal auf **Wählen** klicken
3. Im Fenster suchen und anhaken. Gibt es den Wert noch nicht, einfach eintippen und **„… als neuen Wert anlegen“** klicken. Bei Einzelwerten entfernt **„– keine Angabe –“** den Wert.
4. **Merkmale speichern**

> Beim **Vater-Artikel** gespeichert, übernehmen alle Farben (Kind-Artikel) dieselben Merkmale. Am besten immer beim Vater pflegen.

**Viele Artikel auf einmal (z.B. Pflegesymbole):** Artikelliste → Garnqualitäten (Väter) anhaken → Aktion **„Merkmal setzen (z.B. Pflege)“** → Ausführen. Im Fenster das Merkmal wählen (Pflege ist vorgewählt), Werte anhaken (Suchfeld hilft) und:
- **hinzufügen**: ergänzt die Werte, vorhandene bleiben
- **ersetzen**: nur noch die gewählten Werte
- **entfernen**: nimmt die angehakten Werte weg (nichts angehakt = Merkmal ganz weg)

Gewählte Väter nehmen alle ihre Farben mit, die übrigen Merkmale bleiben unberührt.
**Werte aufräumen (Artikel → Merkmale):** Neben jedem Wert steht, wie viele Artikel ihn nutzen („unbenutzt“ in Orange). Gibt es denselben Wert in zwei Schreibweisen (z.B. „Acryl“ und „Polyacryl“), beim falschen auf **⇢** klicken, den richtigen Wert suchen und **Zusammenführen**: Alle Artikel bekommen den richtigen Wert, der falsche wird gelöscht. Bei vielen Werten gibt es ein Suchfeld über der Liste.

**Aus JTL übernehmen:** In der JTL-Ameise den Export „Artikelmerkmale“ ziehen und am Server einspielen:
`php scripts/jtl_merkmale_import.php <datei.csv> --dry-run` (Probelauf, zeigt nur an), danach ohne `--dry-run`. Der Bericht (nicht gefundene Artikel, Farben mit abweichenden Werten) liegt neben der CSV. Der Import ist wiederholbar; Pflege und Handgefärbt werden nicht überschrieben.

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
- **nur intern** – der Wert kommt nicht in den Shop, ist aber beim Anlegen fertiger Schilder und an der Kasse wählbar (z.B. „Sonderlackierung“ mit +5 € Aufpreis im Layout).
- **Text / max.** – Personalisierung: Wählt der Kunde diesen Wert, muss er einen Text eingeben (höchstens „max.“ Zeichen, leer = 30). Personalisierbare Designs: Achse „Personalisierung“ **nur bei diesen Layouts** anlegen, mit genau **einem** Wert, den das Design vorgibt („Metallfeld“ oder „Name aufgeklebt“, z.B. +10 €), dort „Text“ anhaken. Eine Achse mit nur einem Wert ist im Shop und an der Kasse automatisch vorgewählt, der Text ist damit Pflicht. Der Text erscheint im Shop als Pflichtfeld, im Warenkorb, in der Bestellung und im ERP-Auftrag (Pickliste, Rechnung). An der Kasse gibt es dasselbe Textfeld, der Text steht dann in der Bon-Zeile.

Rohmaterial leer → im Shop ist der Wert ausgegraut („derzeit nicht verfügbar“) und wird auch serverseitig abgelehnt; an der Kasse nur Hinweis „Material leer“. Nach dem Einbuchen von Material gibt der nächste Sync den Wert frei.

**Fertiges Schild anlegen / einbuchen** (im Layout-Artikel, Karte „Konfigurator“):
1. Werte des gebauten Schilds wählen, Menge + Lager, beim ersten Mal die Kategorie (z.B. „Fertige Schilder“, wird gemerkt).
2. Artikelnummer = Layout-Nummer + Kürzel, z.B. `ID-W01-20-2F-BL-WS-FI-TU` (max. 30 Zeichen).
3. Gibt es die Kombination schon → **nur einbuchen** (und wieder aktivieren). Neu → eigener Lagerartikel mit Hersteller, Gruppe, Steuer, Shop-Kanälen vom Layout und dem Konfigurator-Preis.
4. Foto des fertigen Schilds danach im Artikel hochladen.

**Sonder-Eintrag:** Jede Auswahl hat zusätzlich „Sonder … (Freitext)“, z.B. für Sonderfarbe Moosgummi, Filz, Lack, Sondergröße oder Holzdicke. Dann Text, Kürzel (leer = `S`) und Aufpreis eintragen. Der Text steht im Artikelnamen, im Aufbau, auf Kuvert-Etikett und Preisschild. Ein Schild mit Sonder-Eintrag wird **immer als eigener Artikel** angelegt; ist die Nummer schon vergeben, hängt das ERP `-2`, `-3` … an.

**Personalisierte fertige Schilder:** Hat das Layout einen Wert mit Texteingabe (z.B. „Metallschild“), erscheint darunter ein Textfeld (Pflicht). Gleiche Kombination mit gleichem Text = derselbe Artikel (nur einbuchen), anderer Text = neuer Artikel (Nummer mit `-2`, `-3` …). Achsen mit nur einem Wert sind vorgewählt.

**Kuvert-Etikett drucken** (A4 mit 2 Etiketten je 210 × 148 mm, zum Aufkleben auf Kuvert/Tüte/Karton):
- Links das Bild, rechts Name, Größe (Ø), Aufbau, Preis und die **Artikelnummer als Strichcode**. Die Kasse findet den Artikel über diesen Strichcode, ein EAN ist nicht nötig.
- **Wo:** nach dem Anlegen/Einbuchen in der grünen Meldung · 🖨 je Zeile bei „Fertige Schilder aus diesem Layout“ · im fertigen Schild oben „🖨 Kuvert-Etikett“ · **mehrere auf einmal:** Artikelliste → Kategorie „Fertige Schilder“ → Häkchen setzen (oder alle) → Aktion „🖨 Kuvert-Etiketten drucken“ → Ausführen.
- Im Fenster wählen: **Oben** (neues Blatt) oder **Unten** (obere Hälfte des Blatts ist schon verbraucht) und ob die **Schnittlinie** gedruckt wird (wird gemerkt). Das PDF öffnet in einem neuen Tab.
- Preis = Standardpreis (kein Aktionspreis). Kein eigenes Foto → Foto des Layouts. Vater-Artikel und Layouts selbst bekommen kein Etikett (werden übersprungen).
- Scanner tippt „ß“ statt „-“? Das gleicht die Kasse automatisch aus (Scanner mit US-Tastaturbelegung).

**Optionen auf andere Layouts übertragen** (im fertig gepflegten Layout, Karte „Konfigurator“): Ziel-Layouts anhaken → **Probelauf** → „Jetzt übertragen“. Die Ziele bekommen dieselben Achsen, Werte, Reihenfolge, Aufpreise und Bedingungen. Gleichnamige Werte werden aktualisiert (Shop-Verknüpfung bleibt), fehlende angelegt, überzählige entfernt. Der Grundpreis je Layout bleibt. Werte, die schon in Aufträgen stehen, bleiben erhalten und werden in der Liste gemeldet.

Ein Wert ohne Kürzel fällt aus der Nummer (z.B. Glitzer „nein, Danke“), aber nur, wenn er in seiner Achse der einzige ohne Kürzel ist.

---

## Preise vieler Artikel ändern (z.B. Preiserhöhung eines Herstellers)

Jeder Artikel, auch jede Farbe, hat einen **eigenen Preis**. Ändert man den Preis beim Vater, ändern sich die Kinder **nicht** mit. Für viele Artikel auf einmal:

1. Artikelliste → Artikel oder Väter anhaken (z.B. nach Hersteller filtern) → Aktion **„💶 Preise ändern“** → Ausführen. Es öffnet sich ein neuer Tab. Gewählte Väter nehmen ihre Kinder mit.
2. Einstellen:
   - **Welcher Preis wird geändert?** Standardpreis (vorgewählt), eine andere Kundengruppe (z.B. Händler) oder UVP.
   - **Gerechnet von (Basis):** geht mit dem Ziel mit, kann aber umgestellt werden. Beispiel Händlerpreis = Standardpreis − 30 %.
   - **Änderung:** + / − Prozent, + / − Euro oder fester neuer Preis. Minus = billiger.
   - **Rundung:** immer **aufwärts** auf 10 Cent, ,00/,50, ,90, ,95, ,99 oder volle Euro. So wird eine Erhöhung nie durch Rundung zur Senkung (8,95 + 5 % = 9,40 → 9,90).
3. **Vorschau:** Tabelle Basis, alt, neu, Änderung. Noch ist nichts gespeichert.
   - Kinder sind eingeklappt (Knopf **▸ Anzahl**).
   - Kinder mit **eigenem Preis** (anders als der Vater, z.B. größere Größe) haben ein blaues **!**, bleiben sichtbar und sind **nicht** angehakt. Mit „auch alle mit ! ändern“ oder einzeln anhaken.
   - Jeden neuen Preis kann man **von Hand überschreiben** (Feld wird gelb). Beim Vater ziehen die Kinder mit gleichem Preis mit.
   - Artikel ohne Ausgangspreis (fehlt oder 0,00 €) werden ausgelassen.
4. **Übernehmen.** Netto wird aus der Steuerklasse neu gerechnet. Der **Shop-Abgleich** überträgt die neuen Preise. Gedruckte Schilder erscheinen unter **Schilder veraltet**.

> Unten auf der Seite stehen die **letzten Preisänderungen** (wann, wer, was). Jede einzelne Änderung mit Artikelnummer, Name, alt → neu steht im Aktivitäten-Protokoll unter `preis.massen`.

## Mengen-Aktionen (4 für 12 €, 3 für 2, Paketpreise)

Mengenrabatt über **gemischte** Artikel, an der Kasse und im Shop gleich gerechnet. Artikel → Preise / Aktionen → **🛒 Mengen-Aktionen** → **+ Neue Mengen-Aktion**.

| Art | Beispiel | So wird gerechnet |
|-----|----------|-------------------|
| X Stück für Y € | 4 für 12 € | Die teuersten Stücke kommen zuerst ins Paket |
| Kauf X, zahl Y | 3 für 2 | Je Paket ist das günstigste Stück gratis |
| Paketpreise | Raunzmarker: 2 für 4,50 · 4 für 8 · 8 für 15 · 12 für 20 € | Die Menge wird aus Paketen zusammengesetzt, sodass der Preis am günstigsten ist, der Rest einzeln (5 Stk = 4er + 1 = 10,50 €) |

- **Gilt für:** Kategorien (Unterkategorien zählen mit) und einzelne Artikel. Dazu in der Artikelliste anhaken → Aktion **„🛒 Zu Mengen-Aktion hinzufügen“**. Väter nehmen ihre Varianten mit, gemischt wird beliebig.
- **Wo:** Kasse und/oder einzelne Shops anhaken, Zeitraum von/bis (leer = ohne Ende). „aktiv“ aus = pausiert.
- Die Aktion **wiederholt sich** (9 Stk bei 4 für 12 € = 2 Pakete + 1 Stück normal).
- Der Rabatt wird **gleichmäßig auf alle Stücke im Paket verteilt**. Schickt ein Kunde eines zurück, bekommt er den anteiligen Preis, auch bei „3 für 2“.
- **Kein doppelter Rabatt:** Artikel mit Sale-/Aktionspreis und Zeilen mit Kassen-Rabatt nehmen nicht teil. Gilt nur für Endkunden.
- **Kasse:** Im Warenkorb stehen der alte Preis durchgestrichen, „Aktion …“ und unten die Ersparnis sowie „noch x Stk → …“. Auf dem Bon stehen der alte Preis durchgestrichen und „Ersparnis durch Aktion“.
- **Shop** (NAHTLOS Connector ab 1.7.0): Badge neben dem Preis, Hinweis auf der Produktseite. Im Warenkorb wird der alte Preis durchgestrichen und darunter steht „noch x Stück …“. Das ERP überträgt die Aktionen beim Speichern und laufend, wenn sich etwas ändert. Den Stand je Shop siehst du unten auf der Seite.

> Sale- und Aktionspreise gelten jetzt auch an der Kasse (vorher nur der Standardpreis). Die Bon-Zeile zeigt „Sale · statt € …“.

## Preisschilder drucken

**Messe-Schild** (8 pro A4, je 95 × 60 mm, wie die alte Vorlage): Name, Hersteller, Zusammensetzung, Gewicht/Lauflänge, Nadelstärke, Preis und Grundpreis. Eines je **Garnqualität**: Wählt man Farben, werden sie zu ihrem Vater zusammengefasst. Die Texte kommen aus den **Merkmalen** (siehe oben), fehlt etwas, steht in der Auswahl „keine Merkmale“.

**Wo:**
- **Messe:** Kasse → Messe vorbereiten → bei der Messe (Papier-Messe oder Sync-Paket) auf **🏷 Preisschilder**. Alle zugebuchten Qualitäten sind schon angehakt.
- **Beliebige Artikel:** Artikelliste → Häkchen setzen → Aktion **🏷 Preisschilder drucken** → Ausführen.
- Artikel → **🖨 Preisschilder** (Seitenleiste) erklärt beide Wege.

**Auf der Auswahlseite:**
1. Häkchen prüfen (Alle/Keine). Die Spalte „Zuletzt gedruckt“ zeigt, wann und mit welchem Preis das Schild zuletzt gedruckt wurde, „Preis geändert“ = Nachdruck nötig.
2. **Start bei Feld** 1–8: Felder sind von oben links nummeriert (1 2 / 3 4 / 5 6 / 7 8), damit ein angefangenes Blatt aufgebraucht wird.
3. **Messepreis** (optional): „x % billiger“ auf den Normalpreis. Das Schild zeigt „statt € …“ und den Messepreis in Rot. Läuft eine günstigere Aktion, gilt die.
4. **QR „Farbe nicht da? Online bestellen“** (optional): Shop wählen. Der QR führt zur Artikelseite in diesem Shop und erscheint nur bei Artikeln, die dort online sind.
5. **Schnittlinien** an/aus → **🖨 Drucken**. Das PDF öffnet in einem neuen Tab.

> Der **Grundpreis** steht bei Garn immer **je kg** (gesetzliche Bezugsmenge in Österreich), wie im Shop. Haben die Farben unterschiedliche Preise, steht „ab“ davor. Laufende Aktionen werden mit „statt“ gedruckt.
>
> Jeder Druck wird gemerkt (Datum, Preis, Texte, Badges).

**Schilder veraltet** (Artikel → 🔁 Schilder veraltet, auch als Hinweis am Dashboard): alle gedruckten Laden-Schilder, bei denen sich seither **Preis, Aktion, Grundpreis, Texte oder Badges** geändert haben oder deren Artikel inaktiv ist. In der Spalte „Was hat sich geändert“ steht der Grund (z.B. „Preis 3,20 → 3,75 · weg: NEU“).
- Haken setzen → **🖨 Ausgewählte nachdrucken** öffnet die Druckseite mit genau diesen Schildern. Nach dem Druck verschwinden sie aus der Liste.
- **✓ ist erledigt**: ohne Druck abhaken (Schild abgenommen oder von Hand korrigiert).
- Messe-Schilder werden je Messe neu gedruckt und sind deshalb ausgeblendet. „auch Messe-Schilder zeigen“ blendet sie ein.

**Weitere Vorlagen** (oben auf der Druckseite umschalten, die Auswahl bleibt):

| Vorlage | Format | Besonderheit |
|---|---|---|
| ⛺ Tischaufsteller | A4 quer, 2 Stück | In der Mitte falten, steht als Zelt; beide Seiten gleich (obere Hälfte steht im Ausdruck auf dem Kopf). Großer QR „Alle x Farben ansehen & bestellen“ |
| 📏 IVAR-Streifen | 40 × 150 mm, 5 pro A4 | Für die Seite der IVAR-Regale (Holm 44 mm); unten Preis und QR |
| 🔖 Anhänger | 50 × 90 mm, 12 pro A4 | Mit Loch zum Anbinden und Strichcode der Artikelnummer (Kasse). Fertige Schilder zeigen Größe und Aufbau |

Tischaufsteller und IVAR-Streifen haben dieselben Badges, Pflegesymbole und Hausfarben wie das Laden-Schild; der QR führt zur Artikelseite im gewählten Shop.

**Anhänger ohne Artikel** (z.B. Babsis Strickstücke): Druckseite → **✂ Anhänger ohne Artikel** → **+ Neuer Anhänger**: Kopfzeile (Vorgabe „handgestrickt von Babsi“), Name, Material, Größe, Zusatz, Preis, Badge (Vorgabe „UNIKAT“) und Pflegesymbole anhaken → Speichern. Die Anhänger bleiben gespeichert: anhaken, **Anzahl** wählen → **🖨 Angehakte drucken**. Verkaufte Stücke **ausblenden** (lassen sich wieder einblenden).**Laden-Schild** (8 pro A4, je 95 × 68 mm): oben auf der Auswahlseite auf **🏪 Laden-Schild** umschalten, die Auswahl bleibt erhalten.
- Farbiger Streifen in der **Hausfarbe** (MeaLana-Blau, Sockenwolle-Weinrot, bio-wolle-Grün oder grau), Name in derselben Farbe.
- Zusammensetzung, Lauflänge, Nadelstärke in mm, Maschenprobe, **Pflegesymbole** (Merkmal „Pflege“), optional **mit Bild** (erstes Artikelbild, sonst eines der Farben).
- **Badges** oben rechts, automatisch:

| Badge | Wann |
|---|---|
| AKTION −x % | Aktion oder SALE läuft (mit „statt“ und „Aktion bis …“) |
| AUSLAUF | Artikel ist Auslaufartikel (beim Vater: alle aktiven Farben) |
| LETZTE CHANCE | Auslauf und höchstens 3 Stück im eigenen Lager (ohne Messe) |
| NEU | Artikel jünger als 60 Tage (nicht bei Auslauf) |
| HANDGEFÄRBT / BIO / GOTS | Merkmal „Auszeichnung“ am Artikel |

- In der Auswahl zeigt die Spalte **Badges**, was gedruckt wird; „keine Pflege“ heißt: Pflegesymbole fehlen noch.---

## Google & Suchmaschinen (SEO)

Was der Shop-Abgleich für Google mitschickt:

- **EAN als GTIN** — die EAN (GTIN-13) des Artikels kommt in WooCommerce ins Feld „GTIN, UPC, EAN oder ISBN“
  (wichtig für Google Shopping). Nur wenn die EAN im ERP genau **einem** Artikel gehört — doppelte EANs
  (Qualitätsliste „Doppelte EAN“) werden nicht gesendet, sonst würde WooCommerce den ganzen Artikel ablehnen.
- **Meta-Titel und Meta-Beschreibung** (Artikel → Reiter SEO) → Yoast. Leer = Yoast nimmt Name bzw. Textanfang.
  Max. 160 Zeichen; lohnt sich vor allem für die wichtigsten Artikel.
- **Alt-Texte der Bilder** — ist beim Bild kein eigener Alt-Text eingetragen, schickt der Abgleich den Artikelnamen
  (bei Varianten mit Farbe/Größe). Für Bilder, die schon im Shop sind:
  `php scripts/alt_texte_nachziehen.php <shop-slug> --probelauf`, danach ohne `--probelauf` (max. 500 je Lauf, abends).
- **Marke** = Attribut „Hersteller“ (mit eigenen Hersteller-Seiten). Für Google Shopping im Plugin
  „Google for WooCommerce“ die Marke aus dem Attribut „Hersteller“ zuordnen — keine zusätzlichen WooCommerce-„Marken“,
  das wären doppelte Seiten.

Die Felder kommen bei jedem Abgleich eines Artikels mit. Damit alle bestehenden Artikel sie bekommen:
Einstellungen → Kanäle → **Komplettabgleich** (am besten abends).

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| "Artikelnummer bereits vergeben" | Eine andere Artikelnummer wählen — z.B. Suffix `-V2` |
| Bild lädt nicht hoch | Dateiformat prüfen (JPG/PNG/GIF/WebP) · Dateigröße max. 10 MB |
| Kind-Artikel hat falschen Preis | Jedes Kind hat einen eigenen Preis: im Kind ändern, oder für viele auf einmal Artikelliste → Aktion „💶 Preise ändern“ |
| EAN wird als "schon vorhanden" abgelehnt | EAN ist einem anderen Artikel zugeordnet — suchen und bereinigen |

## GPSR-Texte bereinigen (Artikel → GPSR-Texte)

Aus JTL stehen bei vielen Artikeln (KnitPro, TOPP, Addi, ProLana …) die Herstellerangaben „Kontaktinformation / Verantwortliche Person gem. Art. 19 EU GPSR“ in der Beschreibung. Im Shop stand das doppelt: Die Pflichtangaben zeigt der Shop ohnehin im Reiter **Produktsicherheit** aus den Hersteller-Stammdaten.

1. Seite öffnen: Sie zeigt die Zahl der betroffenen Artikel (nach Hersteller) und ob danach eigener Text bleibt. Es wird noch nichts geändert.
2. Steht oben ein blaues **!** „Adresse fehlt“: zuerst unter Artikel → Hersteller die Adresse nachtragen.
3. **„GPSR-Texte bei … Artikeln entfernen“** schneidet ab der ersten GPSR-Überschrift ab (Väter und Kinder, Lang- und Kurzbeschreibung) und merkt die Artikel für den Shop-Abgleich vor.

Die alten Texte werden vorher in `erp/storage/gpsr_sicherung/` gesichert (JSON mit Artikelnummer und altem Text).

