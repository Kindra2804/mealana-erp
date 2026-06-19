---
name: bug-aktionskategorie-zuweisung
description: "2 offene Bugs bei Aktions-Kategorien: kein Auto-Aktionspreis bei Zuweisung + Symbol im Kategoriebaum verschwunden"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

## Bug 1: Keine Aktionspreise bei Aktions-Kategorie-Zuweisung

Wenn ein Artikel einer Aktions-Kategorie zugewiesen wird (über `saveKategorien`), werden keine `aktionen_artikel_preise`-Einträge angelegt. Der Artikel ist in der Kategorie drin, aber die laufende Aktion greift nicht automatisch.

**Ursache:** `saveKategorien` / `updateArtikelKategoriezuweisungen` prüft nicht ob neue Kategorien aktive Aktionen haben.

**Was fehlt:** Nach der Kategorie-Zuweisung prüfen ob die neuen Kategorien aktive Aktionen haben → falls ja, Artikel in `aktionen_artikel_preise` eintragen (wie beim Aktion-Starten-Cronjob).

## Bug 2: Aktions-Kategorie-Symbol im Kategoriebaum verschwunden

Das visuelle Symbol/Indikator das eine Kategorie als "Aktions-Kategorie" markiert (`ist_aktions_kategorie = 1`) ist im Kategoriebaum nicht mehr sichtbar.

**Nicht kritisch für Barbara-Test** — kosmetisches Problem.

**Why:** Vermutlich CSS-Klasse oder Icon-Render-Logik in `kategorien_verwalten.php` verloren gegangen.

**How to apply:** Beide Bugs nach der QA-Runde angehen, vor Barbara-Test.
