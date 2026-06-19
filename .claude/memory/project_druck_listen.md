---
name: project-druck-listen
description: "Geplant: Qualitätslisten (fehlende EAN, doppelte EAN, fehlende Bilder) für Druck aufbereiten"
metadata:
  node_type: memory
  type: project
  originSessionId: 455414e8-da96-4301-bb1d-33d964dd2133
---

## Qualitätslisten Druckvorbereitung

Wenn das Druck/Ausgaben-Modul kommt: Qualitätslisten auch als druckbare Ansicht vorbereiten.

**Was gemeint ist:**
- Keine-EAN-Liste → druckbar als Checkliste für Wareneingangsteam
- Doppelte-EAN-Liste → druckbar für Korrekturlauf
- Keine-Bilder-Liste → druckbar als Aufgabenliste für Fotoshooting

**Why:** Jacky/Barbara arbeiten teils auch offline/physisch — eine druckbare Liste ist praktischer als ein Monitor.

**How to apply:** Wenn Druckmodul / Export-Funktionen gebaut werden, diese Listen als erstes miteinbeziehen. Wahrscheinlich via `?druck=1` GET-Param der CSS-Klassen umschaltet (kein Sidebar, kein Header).
