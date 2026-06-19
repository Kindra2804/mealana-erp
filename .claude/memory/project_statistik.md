---
name: project-statistik
description: "Statistik-Konzept: kein eigener Artikel-Sidebar-Link, kommt aus Dashboard + Verkauf"
metadata: 
  node_type: memory
  type: project
  originSessionId: 455414e8-da96-4301-bb1d-33d964dd2133
---

## Statistik: Wo und wann

Der `📊 Statistik → #` Link aus der Artikel-Sidebar wurde entfernt (2026-06-19) — war ein toter Platzhalter ohne Plan.

Statistiken entstehen aus zwei Richtungen:

**1. Dashboard (globale Übersicht)**
- Lagerwert (Bestand × EK)
- Low-Stock-Warnungen (Meldebestand unterschritten)
- Tagesübersicht: Umsatz, Bestellungen, offene Lieferungen

**2. Verkauf-Modul (erst wenn Verkaufsdaten vorhanden)**
- Topseller nach Zeitraum
- Umsatz / Marge pro Artikel
- Preishistorie

**Was heute schon vorhanden ist (ohne Statistik-Seite):**
- Lagerbewegungen pro Artikel → im Lager-Tab von detail.php
- Marge pro Artikel → im Preise-Tab von detail.php
- Artikel nach Bestand sortierbar → via liste.php Filter

**Why:** Statistik ohne Verkaufsdaten ist leer. Erst Verkauf-Modul bauen, dann Statistik sinnvoll.

**How to apply:** Wenn Verkauf-Modul steht → Statistik-Link im Artikel-Sidebar reaktivieren mit echtem Target. Dashboard-Statistiken separat planen.
