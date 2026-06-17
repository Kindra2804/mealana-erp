---
name: project-logger-ui
description: "Geplantes Logger-UI: Zeile in Shell-Bottom + Admin-Seite für Aktivitäten-Log"
metadata: 
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

## Logger-Zeile in Shell-Bottom (aus dashboard_mockup.svg)

Das Mockup zeigt unten im Shell-Layout eine **Logger-Zeile** — eine kompakte Status-Zeile die die letzte(n) Systemaktivität(en) anzeigt.

**Anforderungen noch zu klären:**
- Sortierung/Filterung: warn / kritisch / ok — oder nach Typ (Artikel, Lager, Preis, ...)?
- Nur letzte 1 Zeile? Oder mini-Liste (3-5 Einträge)?
- Klickbar → führt zur Admin-Logger-Seite?

**How to apply:** Beim Bau der Shell-Bottom-Erweiterung: `aktivitaeten`-Tabelle abfragen (letzte N Einträge), kompakt darstellen. Admins sehen mehr Details als normale User.

## Admin-Logger-Seite (geplant)

Eigene Seite `public/admin/aktivitaeten.php` für Admins:
- Vollständige Aktivitätsliste
- Filter: nach Benutzer, Aktion, Tabelle, Datum
- Suche in `aktion` und `details` (JSON)
- Sortierung nach Datum (Standard: neueste zuerst)
- Spalten: Datum/Zeit | Benutzer | Aktion | Referenz | Details

**Why:** Nachvollziehbarkeit — wer hat wann was geändert. Wichtig für Fehlersuche und Compliance. Kommt nach dem ersten vollständigen Modul (Artikel) wenn genug Log-Einträge da sind um es sinnvoll zu testen.
