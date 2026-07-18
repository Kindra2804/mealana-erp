# 13 — Inventur

> **Fertig.** Lagerplätze, Inventur-Lauf starten/pausieren/fortsetzen/abbrechen, Zählliste, Live-Sperre bei mehreren Zählern, Buchungssperre für Kasse/Wareneingang, Abschluss mit echter Bestandskorrektur, "Letzte Inventur"-Datum am Artikel, Fortschritts-Anzeige, Manager-Auslauf-Shortcut, Druckversion der Zählliste.

## Konzept

Statt getrennter Module für "große Inventur", "rollierende Inventur" und "Einzelartikel-Nachzählung" gibt es **einen** Inventur-Lauf-Mechanismus mit frei wählbarem **Scope**:

- Ganzes Lager
- Ein Lagerplatz
- Eine Kategorie
- Ein einzelner Artikel
- Ein Mietfach

Das deckt sowohl die große Jahresinventur als auch spontane Anlässe ab (z.B. ein Artikel zeigt nach einer Kassa-Überbuchung einen unplausiblen Bestand → schnelle Nachzählung mit Scope = 1 Artikel, statt das ganze Lager anzufassen).

---

## Lagerplätze

**Navigation:** Lager → Lagerplätze

Regal/Fach-Struktur unterhalb eines Lagers, Grundlage für die Inventur (mehrere gleichzeitige Zähler brauchen eine Orts-Aufteilung). Verwaltung wie bei Herstellern/Lagern: Liste + Modal, Filter nach Lager und Aktiv-Status.

## Inventur-Lauf starten

**Navigation:** Lager → Inventur → "+ Neue Inventur starten"

1. Scope wählen (Lager/Lagerplatz/Kategorie/Artikel/Mietfach), passendes Ziel auswählen.
2. **Blind zählen**: Standardmäßig aktiviert — der Zähler sieht den Soll-Bestand nicht (verhindert unbewusstes Bestätigen statt echter Zählung).
3. Optional Notiz.
4. "Inventur starten" → Lauf erscheint in der Liste mit Status "Läuft".

## Status-Verlauf

- **Läuft** → kann pausiert oder abgebrochen werden.
- **Pausiert** → Zwischenstand bleibt erhalten, kann später fortgesetzt oder endgültig abgebrochen werden.
- **Fortsetzen** startet einen neuen Lauf mit demselben Scope, referenziert den ursprünglichen Lauf (sichtbar als "Fortsetzung von #X" in der Liste).
- **Abgebrochen** → endgültig, kein Fortsetzen aus diesem einen Lauf mehr möglich (aber ein neuer Lauf mit gleichem Scope kann jederzeit gestartet werden).

## Zählliste

**Navigation:** Inventur-Liste → "Zählen" bei einem laufenden Lauf

Zeigt die **Soll-Liste** passend zum Scope des Laufs:

- **Ganzes Lager**: alle Artikel/Chargen, die aktuell in diesem Lager geführt werden.
- **Lagerplatz**: nur was diesem Platz bereits zugeordnet ist — beim allerersten Zählgang eines Platzes bewusst **leer**, da es noch keine Zuordnung gibt. Einfach über "Artikel erfassen" oben frei eintragen, was tatsächlich dort liegt.
- **Kategorie / Einzelner Artikel**: über **alle Lager** hinweg (der Scope legt kein einzelnes Lager fest), mit Lager-Spalte zur Orientierung.

Pro Zeile: Ist-Menge eintragen, optional Notiz, 💾 speichern — läuft per AJAX ohne Seiten-Neuladen. Ein zweites Speichern derselben Zeile **aktualisiert** die Erfassung, statt eine doppelte Position anzulegen.

**Artikel erfassen** (oberer Bereich): für alles, was nicht auf der Soll-Liste steht — neue Chargen, unerwartete Funde. Bei Kategorie-/Artikel-/Mietfach-Scope muss dabei zusätzlich das Lager gewählt werden (ergibt sich sonst automatisch aus dem Scope).

**Blind-Modus:** Ist die Inventur blind gestartet, wird die Soll-Spalte komplett ausgeblendet.

## Mehrere Zähler gleichzeitig

Bei Scope "Ganzes Lager" kann oben auf der Zählseite ein Lagerplatz als "Ich zähle gerade an" gewählt werden — informativ, kein Zwang. Wählt eine zweite Person denselben Platz, erscheint eine Warnung ("wird gerade gezählt von ... seit ..."), die Zählung ist aber trotzdem möglich (z.B. falls die erste Person abgebrochen hat). Bei Scope "Lagerplatz" wird der Platz automatisch beim Öffnen der Zählseite beansprucht.

## Buchungssperre während einer Voll-Lager-Inventur

Läuft für ein Lager eine Inventur mit Scope "Ganzes Lager", werden für dieses Lager automatisch gesperrt:

- **Kasse**: Kassen mit diesem Lager hinterlegt können keine Verkäufe mehr buchen (Meldung "Kasse ist bis zum Abschluss gesperrt").
- **Wareneingang**: Buchungen in dieses Lager werden abgelehnt, bis die Inventur abgeschlossen/abgebrochen ist.

Andere Lager (z.B. Messelager) sind davon nicht betroffen. Bei Teil-Scopes (Lagerplatz/Kategorie/Artikel/Mietfach) gibt es keine Buchungssperre — der Betrieb läuft normal weiter.

## Abschluss (Prüfen & Buchen)

**Navigation:** "Prüfen …"-Link bei einem laufenden oder pausierten Lauf

Vor jeder echten Bestandsänderung steht immer eine **Vorschau-Seite** — sie bucht noch nichts. Die Differenzliste stützt sich dabei **auf den Gesamtbestand pro Artikel+Lager** (Summe alt vs. Summe neu über alle Chargen) — Chargen selbst spielen für die Frage "gibt es eine Abweichung" keine Rolle. Wie die 13 Stück auf welche Chargen verteilt sind, ist egal, solange die Summe passt.

**Sonderfall Scope "Lagerplatz":** Hier bezieht sich "Vorher" bewusst nur auf das, was bisher an GENAU DIESEM Platz hinterlegt war — nicht auf den Gesamtbestand des ganzen Lagers. Wurde ein Artikel z.B. auf einen anderen Platz umgelagert und ist am alten Platz nicht mehr auffindbar, ist das für den Zähler dort eine echte (lokale) Unterschreitung → Notiz Pflicht ("Artikel nicht an diesem Platz gefunden"), aber der Gesamtbestand des Artikels wird beim Abschluss nur um den tatsächlich fehlenden Anteil korrigiert — was an anderen (nicht gezählten) Plätzen weiterhin liegt, bleibt unangetastet. Findet der Zähler an diesem Platz stattdessen einen anderen Artikel, kann der ganz normal als neuer Fund gezählt/gebucht werden.

- **Abweichungen-Tabelle**: jeder gezählte Artikel, bei dem Gesamt-Vorher ≠ Gesamt-Nachher ist ODER sich die Chargen-/Lagerplatzverteilung geändert hat (auch bei gleicher Summe — z.B. Chargen zusammengelegt oder an andere Plätze umgezogen).
- **Nicht gezählte Artikel werden einfach übersprungen** — kein Blocker, keine Fehlermeldung, bleiben schlicht unverändert (genau wie bei JTL). Ein Link führt zurück zur Zählliste, falls noch weitergezählt werden soll.
- Drei Aktionen zur Wahl:
  - **✅ Jetzt buchen & abschließen** — führt die Korrektur durch (siehe unten) und schließt den Lauf ab.
  - **⏸ Ohne Buchung pausieren** — nur bei laufenden Läufen, bucht nichts, Zwischenstand bleibt für später erhalten.
  - **✕ Verwerfen ohne Buchung** — Lauf wird abgebrochen, alle bisher gezählten Mengen bleiben unverbucht.

**Was beim Buchen passiert** (pro Artikel+Lager mit mindestens einer gezählten Position):

1. **Fehlbestand ohne Notiz wird komplett verweigert** — sinkt die gezählte Gesamtsumme unter den vorherigen Bestand und keine der betroffenen Positionen hat eine Notiz, bricht der gesamte Abschluss mit einer Fehlermeldung ab (welcher Artikel betroffen ist). Erst auf der Zählliste die Notiz nachtragen, dann erneut versuchen.
2. **Passt alles genau** (Summe gleich, Chargen/Lagerplätze unverändert) → es wird **nichts gebucht**, nur das Inventurdatum am Artikel gesetzt.
3. **Verteilung geändert** (Chargen zusammengelegt/umbenannt, Lagerplatz gewechselt) → die neuen Chargen-Zeilen werden eingetragen; bei Scope "Ganzes Lager"/"Kategorie"/"Artikel" werden dabei alte, nicht mehr vorkommende Chargen auf 0 gesetzt (dort wird ja der komplette Artikelbestand betrachtet) — bei Scope "Lagerplatz" bleiben nicht gezählte alte Chargen an diesem Platz bewusst unangetastet (könnten schlicht übersehen worden sein). Auch wenn die Gesamtsumme gleich bleibt, gibt es dabei keine Lagerbewegung (kein Zugang/Schwund-Ereignis, nur Umverteilung).
4. **Echte Mengenabweichung** → zusätzlich eine Lagerbewegung für die Netto-Differenz (Zugang → Typ "inventur", Fehlbestand → Typ "schwund").
5. Der Lauf wird auf "Abgeschlossen" gesetzt, das Inventurdatum an allen gezählten Artikeln aktualisiert.

**Rollenabhängige Notizpflicht** (bereits beim Zählen selbst, nicht erst beim Abschluss): weicht die eingegebene Menge vom Soll ab, ist die Notiz für alle unterhalb Manager-Rang Pflicht — ab Manager-Rang optional.

## Letzte Inventur

Jeder Artikel merkt sich das Datum seiner letzten Inventur (gesetzt beim Abschluss eines Laufs, für alle dabei gezählten Artikel). Sichtbar auf der Artikel-Detailseite (Tab Lager, in der Bestandsübersicht) sowie als optionale Spalte in der Artikellliste (Spalten-Picker → "Letzte Inventur").

## Fortschritts-Anzeige

In der Inventur-Übersicht zeigt jeder laufende/pausierte Lauf, wie viele der Soll-Positionen schon gezählt wurden (Balken + Prozent). Bei Scope "Lagerplatz" ist die Soll-Liste beim allerersten Zählgang bewusst leer (siehe oben) — dort gibt es keinen sinnvollen Prozentwert, stattdessen nur die Anzahl bereits frei erfasster Funde ("X erfasst").

## Manager-Auslauf-Shortcut

Ab Manager-Rang gibt es in der Zählliste bei jeder Soll-Zeile einen 🏁-Button, um den Artikel direkt (mit Sicherheitsabfrage) als Auslaufartikel zu markieren — nützlich, wenn beim Zählen auffällt, dass ein Artikel nicht mehr nachbestellt werden soll. Wirkt wie das Auslauf-Häkchen im Artikel-Formular (inkl. Vererbung auf Kinder bei Vater-Artikeln).

## Druckversion der Zählliste

**Navigation:** "🖨 Druckversion"-Button auf der Zählseite (öffnet in neuem Tab)

Druckoptimierte HTML-Seite (Browser-Druck bzw. "Als PDF speichern" im Druckdialog — kein separater Dompdf-Export nötig, gleiches Prinzip wie X-/Z-Bon-Druck). Bei Scope "Ganzes Lager" gibt es drei Ansichten:

- **Gesamte Zählliste**: alle Soll-Positionen wie in der digitalen Erfassung, bereits erfasste Ist-Mengen/Notizen vorbefüllt.
- **Bestimmter Artikel**: Textsuche (Name/Nummer) filtert die gedruckte Liste auf Treffer.
- **Ein Lagerplatz (Blanko-Liste)**: reine Vorlage mit 30 leeren Zeilen zum handschriftlichen Ausfüllen — für die allererste Zählung eines Platzes, wo es (siehe oben) noch keine digitale Soll-Vorbelegung gibt.

Bei den anderen Scopes (Lagerplatz/Kategorie/Artikel/Mietfach) gibt es keine Filterleiste — die Soll-Liste ist dort durch den Scope selbst schon klein genug.
