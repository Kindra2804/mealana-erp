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

Regal/Fach-Struktur unterhalb eines Lagers (z.B. "Regal 8 / Fach 3"). Grundlage für das kommende Inventur-Modul (mehrere gleichzeitige Zähler brauchen eine Orts-Aufteilung, damit niemand versehentlich denselben Bereich doppelt zählt). Verwaltung wie bei Herstellern/Lagern: Liste + Modal, Filter nach Lager und Aktiv-Status, Deaktivieren statt Löschen.

**Aktuell rein informativ** — die eigentliche Verknüpfung mit dem Lagerbestand (welcher Artikel liegt auf welchem Platz) kommt erst mit dem Inventur-Modul selbst.

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
