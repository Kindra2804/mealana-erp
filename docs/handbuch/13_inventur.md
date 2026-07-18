# 13 — Inventur

> **In Arbeit.** Aktuell gebaut: Lagerplätze + Inventur-Lauf starten/pausieren/fortsetzen/abbrechen. Die eigentliche Zählliste (Erfassen der gezählten Mengen) folgt in einem späteren Schritt.

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

## Was noch fehlt (geplant)

- Die eigentliche Zählliste (Artikel/Chargen erfassen, Soll/Ist-Abgleich)
- Live-Sperre pro Lagerplatz während der Zählung
- Buchungssperre für Kasse/Wareneingang bei Voll-Lager-Inventur
- Fortschritts-Anzeige, Druckversion der Zählliste
- "Letzte Inventur"-Datum auf der Artikel-Detailseite
