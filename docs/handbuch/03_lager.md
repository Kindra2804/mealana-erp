# 03 — Lager

## Konzept

MeaLana hat mehrere Lager:
- **Standardlager** — das normale Hauptlager
- **Lager Messe** — für Messen, kann auf "Messe-Modus" umgestellt werden
- **Externe Lager** — bei Partner-Händlern (Konsignation)

Der **Lagerstand** setzt sich zusammen aus:
- **Ist-Bestand** = physisch vorhanden
- **Reserviert** = für offene Aufträge vorgemerkt (noch nicht versendet)
- **Verfügbar** = Ist minus Reserviert = kann noch verkauft werden

---

## Wareneingang buchen

Wenn eine Lieferung vom Lieferanten eintrifft:

**Navigation:** Lager → Wareneingang

1. Artikel suchen (Artikelnummer, Name oder EAN scannen)
2. **Menge** eingeben
3. **EK-Preis** eingeben (aktueller Einkaufspreis)
4. **Lager** auswählen (Standard oder Messe)
5. Optional: **Charge** eingeben (bei Garnen wichtig für Farbkonsistenz)
6. Optional: **Lieferschein-Nr.** des Lieferanten eintragen
7. → **Einbuchen**

> **Wichtig:** Den EK-Preis immer aktuell eintragen — er ist die Basis für die Margen-Berechnung.

> **Auslaufartikel:** Wenn ein Auslaufartikel auf Bestand 0 war und jetzt Ware eingebucht wird, entfernt das System automatisch das Auslauf-Flag und reaktiviert den Artikel.

---

## Lagerbestand prüfen

**Navigation:** Lager → Bestandsübersicht

Die Liste zeigt alle Artikel mit:
- Aktueller Bestand je Lager
- Reservierte Menge
- Verfügbare Menge

**Filtern nach:**
- Artikel mit Bestand = 0
- Artikel unter Mindestbestand
- Einzelnes Lager

---

## Lagerbewegungen / Protokoll

Jede Buchung wird gespeichert. So lässt sich nachvollziehen warum der Bestand so ist wie er ist.

**Navigation:** Lager → Bewegungen (oder im Artikel-Detail → Tab Lager)

Zu sehen:
- Datum und Uhrzeit der Buchung
- Menge (+ Zugang, − Abgang)
- Typ (Wareneingang, Verkauf, Storno, Umlagerung …)
- Wer gebucht hat

---

## Lagerplätze

**Navigation:** Lager → Lagerplätze

Regal/Fach-Struktur unterhalb eines Lagers. Jeder Platz hat ein **Kürzel** aus Bereich (optional), Regal und Fach:

| Eingabe | Kürzel |
|---------|--------|
| Regal 3, Fach 12 | `R3-F12` |
| Bereich K, Regal 1, Fach 4 (Keller/Nachfüller) | `K-R1-F4` |

Sortiert wird nach Laufweg (R2 vor R10, Fach numerisch).

**Anlegen:**
- **+ Neuer Lagerplatz** — ein einzelnes Fach
- **+ Regal mit Fächern** — ein ganzes Regal auf einmal (z.B. Regal 3, Fach 1–20); vorhandene Fächer werden übersprungen
- Gleiche Kürzel im selben Lager sind nicht möglich

**Etiketten mit QR-Code:** Plätze anhaken → **🏷 Etiketten drucken** (ohne Auswahl: alle angezeigten), oder 🏷 in der Zeile für ein einzelnes Etikett. A4-Bogen mit 3 × 8 Etiketten à 70 × 37 mm. Angebrochener Bogen: `&start=5` an die Adresse anhängen, dann bleiben die ersten 5 Etiketten frei. Der QR-Code öffnet die Zählung dieses Fachs (siehe Inventur) und bleibt gültig, auch wenn das Kürzel später geändert wird.

**Artikel zuordnen — Stammplatz + Nachfüllplatz:**
- **Stammplatz** = das Verkaufsfach, **Nachfüllplatz** = wo der Vorrat liegt (z.B. im Keller). Mehrere Chargen dürfen im selben Fach liegen — der Platz hängt am Artikel, nicht an der Charge.
- Artikel → Reiter **Lager** → Lagerplatz. Beim Vater-Artikel: „Für alle Varianten setzen“.
- Artikelliste → Artikel anhaken → Aktion **Lagerplatz zuweisen** (Vater = alle Varianten).
- Wareneingang → **+ Platz** bei Artikeln, die noch keinen haben.
- Spalte „Lagerplatz“ in der Artikelliste (über den Spalten-Picker einblenden); in der Lagerplatz-Liste führt die Artikelanzahl zur gefilterten Artikelliste.

**Wo der Platz angezeigt wird:** Pickliste (Spalte „Platz“, Positionen nach Laufweg sortiert), Packplatz (📍 unter dem Artikel, gleiche Sortierung), Wareneingang („Gehört in …“).

Der **Bestand** wird weiterhin je Lager geführt, nicht je Fach — Verkauf und Buchungen fragen also nie nach dem Platz.

---

## Umlagerung (geplant)

Ware zwischen Lagern verschieben — z.B. Standardlager → Messe-Lager.

> Diese Funktion ist noch in Entwicklung.

---

## Wichtige Hinweise

> **Bestände nie direkt in der Datenbank ändern!** Immer über den Wareneingang oder die Storno-Funktion im Auftragsmodul. Direkte DB-Änderungen zerstören das Bewegungsprotokoll.

> **Negativer Bestand:** Bei Artikeln mit "Überverkauf erlaubt" kann der Bestand unter 0 fallen. Das System zeigt eine Warnung — aber es ist gewollt.

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Bestand stimmt nicht mit der Realität | Bewegungsprotokoll prüfen — wann wurde zuletzt gebucht? |
| Artikel erscheint nicht im Wareneingang | Artikel aktiv? Artikelnummer korrekt? |
| Charge-Nummer fehlt | Im Wareneingang nachträglich nicht mehr änderbar — bei nächster Lieferung eintragen |
