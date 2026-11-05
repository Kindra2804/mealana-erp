# 16 Shop-Slider

Der große Bildwechsler oben auf der Startseite des Shops (Hero-Slider) und die Banner oben auf Kategorieseiten werden **im ERP gepflegt**, nicht in WordPress. Jeder Shop hat seine eigenen Slides.

Menü: **Artikel → Shop-Slider** (Recht „Slider verwalten“, haben Admin, Assistent und Manager).

---

## Einmalig im Shop einrichten

1. WordPress-Plugin **NAHTLOS Connector** installieren und aktivieren (Datei aus dem Repo `shop/wp-plugin/nahtlos-connector/`, als ZIP hochladen)
2. Auf der Startseite den Platzhalter **`[nahtlos_slider]`** dort einfügen, wo der Slider stehen soll (Block „Shortcode“)
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
| **Bild Handy** | optional, Hochformat ca. 900 × 1100 px. Ohne eigenes Bild wird das Desktop-Bild zugeschnitten. Am Handy steht der Text **unter** dem Bild |
| **Kleiner Titel / Titel / Text** | Kleiner Titel = Zeile in Großbuchstaben über der Überschrift |
| **Button 1 / 2** | Beschriftung + Ziel: Kategorie, Artikel, Hersteller oder eigene Adresse (`https://…` oder `/seite/`). Button 1 ist der farbige Hauptbutton, Button 2 ein Textlink |
| **Text-Position** | links, Mitte oder rechts, je nachdem, wo das Bild „Platz“ hat |
| **Schrift** | *dunkel* für helle Bilder, *hell* für dunkle Bilder. Ein leichter Schleier hinter dem Text sorgt für Lesbarkeit |
| **Sichtbar** | *immer*, *im Zeitraum* (von/bis mit Uhrzeit) oder *solange eine Preis-Aktion läuft* (Zeitraum kommt aus der Aktion) |
| **eingeschaltet** | ausgeschaltete Slides bleiben gespeichert, erscheinen aber nicht im Shop |

Kategorie-, Artikel- und Hersteller-Ziele zeigen nur, was es im Shop schon gibt. Für einen Shop, der noch nicht befüllt ist, werden alle angeboten, damit man Slides schon vorbereiten kann.

---

## Liste

- **Status:** ● sichtbar · ◷ geplant (Zeitraum beginnt später) · abgelaufen · ○ aus · *Bild fehlt*
- **Reihenfolge:** am Griff ≡ ziehen (nur innerhalb derselben Gruppe)
- **Kopieren:** auch in einen anderen Shop. Die Kopie ist zuerst ausgeschaltet
- **Wechsel alle … Sekunden:** gilt für alle Slider des Shops

---

## An den Shop übertragen

Änderungen gehen erst mit **An Shop übertragen** online. Bis dahin steht oben „Änderungen noch nicht übertragen“.

- Bilder werden dabei in die WordPress-Mediathek geladen (nur neue oder geänderte)
- **Zeitsteuerung macht der Shop selbst.** Eine Weihnachts-Slide von 1.12. bis 6.1. kann man also schon im November übertragen. Sie erscheint pünktlich und verschwindet wieder, auch wenn das ERP nicht läuft. Der Seiten-Cache wird zu diesen Zeitpunkten automatisch geleert
- Slides mit „solange Aktion läuft“ überträgt der nächtliche Abgleich einmal täglich neu, falls sich der Aktionszeitraum geändert hat (nur wenn keine anderen Änderungen offen sind)
- Hinweise nach dem Übertragen: z. B. „Kategorie ist noch nicht im Shop“. Diese Slide oder dieser Button wird dann weggelassen

**Fehlermeldung „Plugin nicht installiert“:** NAHTLOS Connector im WordPress unter Plugins aktivieren.
