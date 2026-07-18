# 13 — Inventur

> **In Arbeit.** Aktuell gebaut: Lagerplätze, Inventur-Lauf starten/pausieren/fortsetzen/abbrechen, Zählliste (Mengen erfassen). Es fehlen noch: Live-Sperre bei mehreren Zählern, Buchungssperre für Kasse/Wareneingang, Abschluss mit Differenzbuchung.

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

## Was noch fehlt (geplant)

- Live-Sperre pro Lagerplatz während der Zählung (Info-Warnung bei mehreren gleichzeitigen Zählern)
- Buchungssperre für Kasse/Wareneingang bei Voll-Lager-Inventur
- Abschluss-Logik: Chargen-Summenabgleich, Lagerplatz-Reallokation, Differenzbuchung (echte Bestandskorrektur)
- Fortschritts-Anzeige, Druckversion der Zählliste
- "Letzte Inventur"-Datum auf der Artikel-Detailseite
