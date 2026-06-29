---
name: bug-charge-tracking
description: "BUG: Packplatz und Kasse ignorieren Chargen → bucht charge=NULL aus die nicht existiert"
metadata: 
  node_type: memory
  type: project
  originSessionId: 73181c3f-e7cd-42b8-a31d-8d2abc7282f3
---

## Problem (Stand 2026-06-28)

Wenn der Lagerbestand eines Artikels NUR in charge-spezifischen Zeilen liegt
(keine charge=NULL Zeile in lager_bestand), dann:

**Packplatz** (`warenausgang()`):
- `getTotalBestand()` liest korrekte Summe (z.B. 45)
- `reduziereBestand()` sucht charge=NULL Zeile → findet nichts → Fallback auf höchsten Bestand
  (UPDATE auf eine Charge-Zeile, NICHT auf charge=NULL)
- Log zeigt alle concurrent ops mit gleichem Wert 45→42 (Race Condition)
- REAL: Bestand wird korrekt reduziert, aber aus der falschen Charge-Zeile

**Kasse Rückbuchung** (wenn weniger als bestellt abgeholt):
- `wareneingang()` liest `getBestand(charge=null)` → 0 (kein NULL-Row)
- Legt neue charge=NULL Zeile mit 1 an → Log zeigt 0→1 statt 36→37
- Gesamt-Bestand stimmt, aber Charge-Tracking ist kaputt

## Lagerlog-Beispiel

```
21:13 Eingang 1  0→1   Kasse Rückgabe A-2026-00016
21:09 Ausgang 3  45→42 Packplatz A-2026-00016  (alle 3 identisch!)
21:08 Ausgang 3  45→42 Packplatz A-2026-00015
21:08 Ausgang 3  45→42 Packplatz A-2026-00014
```

## Geplante Fixes

**Packplatz (`warenausgang`):**
- Wenn Artikel charge-pflichtig und mehrere Chargen vorhanden → Auswahl-Dialog
- Wenn nur eine Charge → automatisch aus dieser Charge abbuchen
- `reduziereBestand` muss charge-bewusst sein (nicht blind charge=NULL dann höchste)

**Kasse (`warenausgangKasse` + Rückbuchung):**
- Bei Artikel mit Chargen an der Kasse: Charge-Auswahl Dialog
- Rückbuchung (bon_speichern.php `wareneingang`): muss wissen welche Charge gepackt wurde
  (Packplatz setzt die Charge in auftrag_positionen.charge → daraus lesen)
- Wenn Artikel mit Charge an der Kasse gescannt wird: vor dem Buchen Charge abfragen

## Warum kritisch

Österreichische Lebensmittelkennzeichnung + MHD-Tracking erfordert exaktes Charge-Tracking.
Wolle-Chargen: Kunden wollen gleiche Färbecharge für Farbkonsistenz.
Aktuell: Chargenbestand stimmt nicht mehr → Inventur / Reporting falsch.

**Why:** [[feedback-modul-vorgehen]] — Charge-Tracking war von Anfang an als Kernfeature geplant.
**How to apply:** Als nächstes großes Feature nach Kasse-Phase-2-Test angehen.
