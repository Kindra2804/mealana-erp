---
name: project-lager-konzept
description: "Lagerstruktur, Kassa-Modi, Messe-Lager, Umlagerung"
metadata: 
  node_type: memory
  type: project
  originSessionId: c55c1aca-b514-4e20-98fa-732e6e1149b3
---

## Lagerstruktur

- **Standardlager** (Wien) — Hauptlager, alle Kanäle außer Messe können darauf zugreifen
- **Lager Messe** — Sonderlager für Messebetrieb, eigene Regeln

## Kassa-Modi

**K1 (Kassa Wollboutique):**
- Immer im normalen Ladenbetrieb
- Zugriff auf Standardlager
- Hat immer den kompletten Artikelstamm

**K2 (Kassa Messe):**
- Zwei Betriebsmodi (umschaltbar in Einstellungen):
  1. **Normalbetrieb** → greift auf Standardlager zu (wie K1)
  2. **Messebetrieb** → greift auf Lager Messe zu statt Standardlager
- Hat immer den kompletten Artikelstamm (wie K1)

## Lager Messe — Regeln

- Artikel im Lager Messe sind NICHT verfügbar für: K1, Shop S1, S2, S3
- Nur K2 im Messebetrieb kann auf Lager Messe zugreifen
- Nach der Messe: Ware muss via **Umlagerung** zurück ins Standardlager
- Shops sehen diese Ware erst wieder nach der Umlagerung

## Umlagerung (noch zu bauen)

Funktion zum Verschieben von Beständen zwischen Lagern.
Wichtigstes Szenario: Lager Messe → Standardlager nach Messebetrieb.
Gehört ins Lager-Modul (oder Handyseite für schnelles Scannen).

**How to apply:** Bei der Lager-Modul-Planung und Handyseiten-Planung berücksichtigen. Berechtigungskonzept: K2-Messe-Umschaltung braucht eigene Berechtigung (nicht jeder Mitarbeiter darf das).

## Lagerbestand-Zustand (noch zu bauen)

Per-Einheit Zustandsverfolgung für konkrete Lagerbestände (z.B. "diese 3 Einheiten sind beschädigt").
Entsteht beim Wareneingang oder Rückgabe-Workflow.
Unterschied zum Artikel-`zustand` (statisches Attribut des Artikelstamms, z.B. Neu/Gebraucht).

**How to apply:** Beim Aufbau des Rückgabe-Moduls einplanen. Separates Feld in der Lagerbestand-Tabelle, nicht in `artikel`.

## Kanal-Chips in Artikel-Liste

K1 und K2 haben IMMER den kompletten Artikelstamm → keine Chips in der Artikelliste nötig.
Nur S1/S2/S3 bekommen Chips (weil diese ein Teilsortiment haben).
K1/K2 werden in der Kanallegende informativ erklärt, aber nicht als Badges an Artikeln gezeigt.

## Lager-Tab in detail.php (Spec 2026-06-14)

Entschieden:
- Pro Lager eine Zeile: Lager-Name | Bestand | Reserviert | Verfügbar | Mindestbestand | [Schnell-WE]
- Chargen: aufklappbar, **standardmäßig AUFGEKLAPPT** wenn mind. eine Charge vorhanden
  - Sub-Tabelle: Charge | Menge | Status | Letzte Bewegung
- Bewegungslog: letzte 10 Einträge (Datum | Typ | Menge | Vorher→Nachher | Lager | Referenz | User)
- Wareneingang: **Minimal-Ausführung** direkt im Tab (Schnell-Buchung, z.B. Korrektur bei Inventur)
  - Großer WE kommt ins Einkaufsmodul (mit PO-Abgleich, EAN-Scan, Lieferant)
- Umlagerung: Button → separate Seite (auch als Handyseite geplant — siehe Roadmap)

## Roadmap-Ergänzungen (2026-06-14)

- **Packplatz/Pick-Liste Modul**: Auf der Planungsliste. Soll EK-Bestellungen abrufen, beim Scan abgleichen, Vollständigkeitsprüfung. Kommt nach Einkaufsmodul.
- **Mobile Umlagerungsseite**: Handyoptimierte Seite für Lager↔Lager Transfers via EAN-Scan. Kommt mit/nach dem Lager-Modul-Ausbau.
- **Großer Wareneingang**: Im Einkaufsmodul integriert (PO-Referenz, Lieferant, Mengenabgleich, EK-Buchung).

**How to apply:** Beim Planen des Einkaufsmoduls: WE als Kernfunktion einplanen. Packplatz als eigenes Modul danach. Mobile-Seiten parallel dazu als PWA-fähige Varianten.
