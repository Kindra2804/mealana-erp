---
name: project-paperless-rechnung-modul
description: "Geplantes, extra buchbares Lizenz-Modul: Rechnung als QR-Code statt Papierbeleg, nur bei mind. 1 aktivem Shop buchbar — Konzept von Jacky 2026-07-07, Feindesign steht noch aus"
metadata: 
  node_type: memory
  type: project
  originSessionId: 1018675b-06b0-4bee-b923-24fdc5ebd59a
---

## Idee (Jacky, 2026-07-07)

Neues, **extra buchbares** Lizenz-Modul (siehe Lizenzmodell in [[db_design_entscheidungen]]) — Voraussetzung: **mindestens 1 Shop aktiv** (braucht Webspace zum Hosten des Rechnungsinhalts).

**Ablauf:** Rechnungsinhalt wird auf den Webspace geladen, an der Kasse erscheint ein QR-Code (Inhalt = Link auf den jeweiligen Eintrag). Scannt der Kunde den Code:
- Rechnung bleibt **1 Monat** online gespeichert, von dort als A4 herunterladbar.
- Wird der Code **nicht** gescannt, wird der Eintrag nach **24h** automatisch gelöscht (kein Interesse unterstellt).

**Rechtlicher Hintergrund:** Österreich kennt eine Belegerteilungspflicht — das macht diesen QR-Weg zur einzigen Möglichkeit, ganz ohne Papierbeleg auszukommen. Aktuelle Druck-Auswahl an der Kasse ist `Bondruck | A4-Druck | Keiner`. Ist das Modul aktiv, ersetzt der QR-Code die Option `Keiner` (die dann verschwindet); ohne das Modul bleibt alles wie bisher.

## GEKLÄRT (Jacky, 2026-07-07): "Keiner" ist schon heute nicht erlaubt

Bestätigt: die Druckoption `Keiner` verstößt bereits jetzt gegen die Belegerteilungspflicht. **Unkritisch nur solange die Kasse noch nicht produktiv im Einsatz ist** (aktuell der Fall) — muss aber **vor dem ersten produktiven Kasseneinsatz in jedem Fall** ausgeblendet bzw. durch das Paperless-Modul ersetzt werden. Das ist damit ein härterer, zeitkritischerer Punkt als der Rest des Moduls: selbst wenn das volle Paperless-Feature noch nicht gebaut ist, muss `Keiner` spätestens beim Go-Live entfernt werden (Belegerteilungspflicht duldet keine Ausnahme mehr, sobald echte Kunden bedient werden).

**Why:** Barbara/Kunden wollen vermutlich zunehmend papierlose Belege; Lizenzmodell soll das als Zusatzfeature verkaufbar machen, nicht als Standard.

**How to apply:** Zeitliche Einordnung (Jacky, 2026-07-07): Paperless-Rechnung + Kundenanzeige ([[project_kundenanzeige_modul]]) sind beides Kassen-Themen — sollen NACH erfolgreichem BFR-Hardware-Test eingeschoben werden, vorausgesetzt es gibt für die Kasse dann keine weiteren offenen Punkte außer BFR. Das "Keiner"-Ausblenden selbst ist aber ein **Blocker vor dem ersten produktiven Kasseneinsatz**, unabhängig davon ob das volle Modul bis dahin fertig ist — im Zweifel reicht auch ein Minimal-Fix (Option einfach entfernen), bis das Modul selbst dran ist.
