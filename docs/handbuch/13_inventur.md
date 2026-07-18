# 13 — Inventur

> **Kernfunktionen fertig.** Lagerplätze, Inventur-Lauf starten/pausieren/fortsetzen/abbrechen, Zählliste, Live-Sperre bei mehreren Zählern, Buchungssperre für Kasse/Wareneingang, Abschluss mit echter Bestandskorrektur. Es fehlt noch: Fortschritts-Anzeige, Druckversion der Zählliste, "Letzte Inventur"-Datum am Artikel.

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

Vor jeder echten Bestandsänderung steht immer eine **Vorschau-Seite** — sie bucht noch nichts:

- **Abweichungen-Tabelle**: jede gezählte Position, bei der Soll ≠ Ist ist (egal ob mehr oder weniger gefunden wurde), mit Artikel, Lager, Lagerplatz, Charge, Soll/Ist/Differenz und Notiz.
- **Unvollständig-Liste**: Artikel/Lager-Kombinationen, bei denen noch mindestens eine Soll-Position nicht gezählt wurde — diese werden beim Abschluss **komplett unangetastet gelassen** (kein Teilbuchen). Link direkt zurück zur Zählliste, um das Fehlende nachzutragen.
- Drei Aktionen zur Wahl:
  - **✅ Jetzt buchen & abschließen** — führt die Korrektur durch (siehe unten) und schließt den Lauf ab.
  - **⏸ Ohne Buchung pausieren** — nur bei laufenden Läufen, bucht nichts, Zwischenstand bleibt für später erhalten.
  - **✕ Verwerfen ohne Buchung** — Lauf wird abgebrochen, alle bisher gezählten Mengen bleiben unverbucht.

**Was beim Buchen passiert** (nur für vollständige Artikel/Lager-Gruppen):

1. **Fehlbestand ohne Notiz wird komplett verweigert** — sinkt die gezählte Summe unter den vorherigen Bestand und keine der betroffenen Positionen hat eine Notiz, bricht der gesamte Abschluss mit einer Fehlermeldung ab (welcher Artikel betroffen ist). Erst auf der Zählliste die Notiz nachtragen, dann erneut versuchen.
2. Für jede gezählte Charge wird der Lagerbestand auf die gezählte Menge gesetzt und die Differenz als Lagerbewegung gebucht (Zugang → Typ "inventur", Fehlbestand → Typ "schwund").
3. Wurde beim Zählen ein Lagerplatz mit angegeben ("Ich zähle gerade an"), wird diese Zuordnung in der Lagerplatz-Verteilung gespeichert.
4. Der Lauf wird auf "Abgeschlossen" gesetzt.

**Rollenabhängige Notizpflicht** (bereits beim Zählen selbst, nicht erst beim Abschluss): weicht die eingegebene Menge vom Soll ab, ist die Notiz für alle unterhalb Manager-Rang Pflicht — ab Manager-Rang optional.

## Was noch fehlt (geplant)

- Fortschritts-Anzeige (wie viel % einer Zählung schon erfasst ist)
- Druckversion der Zählliste
- "Letzte Inventur"-Datum auf der Artikel-Detailseite
