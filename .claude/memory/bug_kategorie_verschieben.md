---
name: bug-kategorie-verschieben
description: "KRITISCHER BUG: Vater-Artikel in neue Kategorie verschieben löscht Achsen, Kategorien und Kinder"
metadata: 
  node_type: memory
  type: project
  originSessionId: 67975378-b895-458a-91e6-f323166dde3b
---

## Bug (entdeckt 2026-06-18, morgen als ERSTES angehen)

Wenn ein Vater-Artikel mit Achsen und erstellten Kind-Varianten in eine neue Kategorie verschoben wird:
- Nur der Vater-Artikel selbst wird in die neue Kategorie eingetragen
- Alle Achsen-Zuweisungen des Vaters gehen verloren
- Alle anderen Kategorie-Zuweisungen des Vaters gehen verloren
- Alle Kind-Artikel verschwinden / verlieren ihre Verknüpfung zum Vater

**Why:** Der Kategorie-Verschieben-Code aktualisiert wahrscheinlich nur `artikel_kategorien` für `artikel_id = $vaterId`, ohne die Kinder mitzunehmen und ohne `artikel_achsen` anzufassen.

**How to apply:** Als allererstes in der nächsten Session fixen — vor allen anderen Features. Betrifft `artikel_kategorien`-Update-Logik (wahrscheinlich in ArtikelRepository oder kategorien_verwalten AJAX-Endpoint). Kinder müssen dieselbe Kategorie-Verschiebung bekommen, Achsen dürfen nicht angefasst werden.
