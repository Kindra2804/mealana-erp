---
name: feedback-barbara-ui
description: "Barbara's UI-Feedback: Indikatoren, Icons, Verständlichkeit — direkte Wünsche der zweiten Nutzerin"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e92b8de5-2100-45b7-b6b1-0eeacfcb09d5
---

## Warn-Indikator: Kein ⚠, sondern blaues "!"

Kein gelbes Warndreieck (⚠) für den Kind-Abweichungs-Indikator.

**Why:** Barbara assoziiert ⚠ mit "etwas ist kaputt/defekt". Das stimmt nicht — die Abweichung ist oft bewusst (Sonderpreis auf ein Kind). Ein blaues "!" signalisiert "Hinweis/Info", nicht "Fehler".

**How to apply:** `.warn-badge` → blaues rundes Abzeichen mit "!" statt gelbem ⚠. Gilt für alle Indikatoren dieser Art systemweit.

## Konfigurierbare Abweichungs-Liste

Barbara möchte eine globale Einstellung: welche Abweichungen zwischen Kind und Vater den "!"-Indikator auslösen.

Aktuelle Abweichungstypen (2026-06-14):
- Preis (brutto_vk Kind ≠ Vater)
- Auslauf (ist_auslaufartikel unterschiedlich)
- Inaktiv (Kind inaktiv, Vater aktiv)
- Überverkauf (ueberverkauf_erlaubt unterschiedlich)

**Geplante Einstellung:** Tabelle `system_einstellungen` oder eigene `indikator_einstellungen`-Tabelle mit je einem Boolean pro Abweichungstyp. UI: Einstellungsseite mit Checkboxen.

**How to apply:** Beim Bau der System-Einstellungsseite (vor oder mit erstem externem Release).
