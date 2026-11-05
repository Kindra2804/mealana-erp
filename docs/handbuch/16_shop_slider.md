# 16 Shop-Slider, Kacheln und Menü

Der große Bildwechsler oben auf der Startseite des Shops (Hero-Slider), die **Kategorie-Kacheln** auf der Startseite und die Banner oben auf Kategorieseiten werden **im ERP gepflegt**, nicht in WordPress. Jeder Shop hat seine eigenen.

Menü: **Artikel → Shop-Slider** (Recht „Slider verwalten“, haben Admin, Assistent und Manager).

---

## Einmalig im Shop einrichten

1. WordPress-Plugin **NAHTLOS Connector** installieren und aktivieren (Datei aus dem Repo `shop/wp-plugin/nahtlos-connector/`, als ZIP hochladen)
2. Auf der Startseite den Platzhalter **`[nahtlos_slider]`** dort einfügen, wo der Slider stehen soll, und **`[nahtlos_kacheln]`** dort, wo die Kacheln stehen sollen (jeweils Block „Shortcode“)
3. Im ERP muss beim Shop die WordPress-Anmeldung eingetragen sein (Einstellungen → Kanäle → WP-Benutzer + Application-Password, wie für den Bilder-Upload)

Die Kategorie-Banner erscheinen ohne Platzhalter automatisch oben auf der jeweiligen Kategorieseite.

---

## Slide anlegen

**+ Neue Slide** → das Fenster hat eine Vorschau, die sich beim Tippen mitändert.

| Feld | Bedeutung |
|---|---|
| **Wo** | *Startseite* (Hero-Slider) oder *Kategorieseite* (dann die Kategorie wählen) |
| **Art** | *Bild + Text*: Text und Buttons legt der Shop über das Bild, scharf und von Google lesbar. *Fertiges Bild*: der Text ist schon im Bild (z. B. aus Canva), die ganze Slide ist ein Link |
| **Bild Desktop** | Querformat, ideal **1920 × 700 px**. Wichtiges nicht ganz an den Rand, der Shop schneidet je nach Bildschirm etwas ab |
| **Bild Handy** | optional, quadratisch ca. 1080 × 1080 px, wird am Handy in seinem Format gezeigt. Ohne eigenes Bild wird das Desktop-Bild auf 4:3 zugeschnitten (Ausschnitt zur Seite gegenüber dem Text). Am Handy steht der Text **unter** dem Bild |
| **Kleiner Titel / Titel / Text** | Kleiner Titel = Zeile in Großbuchstaben über der Überschrift |
| **Button 1 / 2** | Beschriftung + Ziel: Kategorie, Artikel, Hersteller oder eigene Adresse (`https://…` oder `/seite/`). Button 1 ist der farbige Hauptbutton, Button 2 ein Textlink |
| **Text-Position** | links, Mitte oder rechts, je nachdem, wo das Bild „Platz“ hat |
| **Schrift** | *dunkel* für helle Bilder, *hell* für dunkle Bilder. Ein leichter Schleier hinter dem Text sorgt für Lesbarkeit |
| **Sichtbar** | *immer*, *im Zeitraum* (von/bis mit Uhrzeit) oder *solange eine Preis-Aktion läuft* (Zeitraum kommt aus der Aktion) |
| **eingeschaltet** | ausgeschaltete Slides bleiben gespeichert, erscheinen aber nicht im Shop |

Kategorie-, Artikel- und Hersteller-Ziele zeigen nur, was es im Shop schon gibt. Für einen Shop, der noch nicht befüllt ist, werden alle angeboten, damit man Slides schon vorbereiten kann.

---

## Kacheln

Kacheln sind die Bild-Karten auf der Startseite, die in eine Kategorie (oder zu einem Artikel, Hersteller, einer Seite) führen.

**+ Neue Slide** → bei *Wo* **Kachel (Startseite)** wählen. Das Fenster zeigt dann nur, was eine Kachel braucht:

| Feld | Bedeutung |
|---|---|
| **Bild** | quadratisch, ca. 800 × 800 px. **Leer lassen** und als Ziel eine Kategorie wählen → das Kategoriebild aus dem ERP wird genommen |
| **Kleiner Titel** | Zeile über dem Titel, z. B. „Kork · Schiefer · Leder“ |
| **Titel** | z. B. „Untersetzer“ |
| **Ziel** | wohin ein Klick auf die Kachel führt |

Am Computer stehen 4 Kacheln nebeneinander (eine angefangene Reihe wird mittig gesetzt), am Tablet 3, am Handy 2. Zeitraum und „solange Aktion läuft“ funktionieren wie bei den Slides, z. B. eine Weihnachts-Kachel nur im Dezember.

---

## Liste

- **Status:** ● sichtbar · ◷ geplant (Zeitraum beginnt später) · abgelaufen · ○ aus · *Bild fehlt*
- **Gruppen:** Startseite (Hero-Slider), Kacheln auf der Startseite, Banner auf Kategorieseiten
- **Reihenfolge:** am Griff ≡ ziehen (nur innerhalb derselben Gruppe), gilt auch für die Kacheln
- **Kopieren:** auch in einen anderen Shop. Die Kopie ist zuerst ausgeschaltet
- **Wechsel alle … Sekunden:** gilt für alle Slider des Shops

---

## An den Shop übertragen

Änderungen gehen erst mit **An Shop übertragen** online. Bis dahin steht oben „Änderungen noch nicht übertragen“.

- Bilder werden dabei in die WordPress-Mediathek geladen (nur neue oder geänderte)
- **Zeitsteuerung macht der Shop selbst.** Eine Weihnachts-Slide von 1.12. bis 6.1. kann man also schon im November übertragen. Sie erscheint pünktlich und verschwindet wieder, auch wenn das ERP nicht läuft. Die Seite enthält kommende Slides schon (versteckt), und der Browser blendet sie zur Uhrzeit ein. Das klappt auch, wenn die Seite aus dem Zwischenspeicher (Cache) kommt
- Slides mit „solange Aktion läuft“ überträgt der nächtliche Abgleich einmal täglich neu, falls sich der Aktionszeitraum geändert hat (nur wenn keine anderen Änderungen offen sind)
- Hinweise nach dem Übertragen: z. B. „Kategorie ist noch nicht im Shop“. Diese Slide oder dieser Button wird dann weggelassen

**Fehlermeldung „Plugin nicht installiert“:** NAHTLOS Connector im WordPress unter Plugins aktivieren.

---

## Shop-Menü

Das **Hauptmenü** des Shops (oben bzw. das Handy-Menü) wird im ERP gepflegt: **Artikel → Shop-Menü**, gleiches Recht wie der Slider. Footer-Menüs (Informationen, Rechtliches) bleiben in WordPress.

**Schnellstart:** Bei leerem Menü **Aus Kategoriebaum vorschlagen** → legt die Hauptkategorien des Shops an, je mit „Unterkategorien automatisch“. Danach umbenennen, sortieren, ergänzen.

| Menüpunkt zeigt auf | Verhalten |
|---|---|
| **Kategorie** | optional **Unterkategorien automatisch**: neue Unterkategorien kommen von selbst ins Menü (beim Übertragen; der nächtliche Abgleich überträgt neu, wenn sich Kategorien geändert haben) |
| **Alle Hersteller (automatisch)** | der Shop listet alle Hersteller, die Artikel haben, auf Wunsch **in Spalten** (großes Aufklappfeld) |
| **Hersteller / Artikel** | ein bestimmtes Ziel |
| **Adresse** | `/seite/` oder `https://…`, z. B. „Über uns“ |

- **Bezeichnung** leer lassen = Name der Kategorie/des Herstellers/Artikels
- **nur zeigen, wenn Artikel drin sind** (Kategorie, Hersteller): z. B. „Sale“ erscheint nur, solange es Sale-Artikel gibt. Das prüft der Shop bei jedem Aufruf
- **Höchstens 3 Ebenen.** „+ Unterpunkt“ hängt einen Punkt darunter, im Fenster kann man den übergeordneten Punkt ändern
- **Reihenfolge:** am Griff ≡ ziehen (innerhalb derselben Ebene)
- **Löschen** löscht die Unterpunkte mit

**An Shop übertragen** baut im Shop das WordPress-Menü „NAHTLOS Hauptmenü“ und setzt es als Hauptmenü ein. Welche Stelle im Theme das ist, steht unten unter *Technik: Menü-Ort im Theme* (Blocksy: `menu_1` oben, `menu_mobile` Handy). Das vorherige, von Hand gebaute Menü bleibt in WordPress erhalten (Design → Menüs) und kann jederzeit wieder eingesetzt werden.
