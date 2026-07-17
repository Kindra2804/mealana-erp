---
name: feedback-achsen-modal
description: "achsen/bearbeiten.php soll ein Modal werden, nicht eine separate Seite"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: a5ab2709-392e-4372-b46a-0264312a1035
---

`achsen/bearbeiten.php` geht aktuell noch auf eine eigene Seite. Das soll ein Modal werden — analog zur Kategorie-Bearbeitung.

**Why:** Design stimmig halten. Modale Bearbeitung ist das etablierte Pattern im Projekt (Kategorie-Modal als Referenz).

**How to apply:** Vor dem Bau der abhängigen Achsen-UI diesen Umbau machen, damit alles konsistent ist. Achsen-CRUD dann komplett modal (neu + bearbeiten).

## ✅ War beim Nachsehen (2026-07-10) bereits fertig — nur die Notiz war veraltet

`achsen/liste.php` hat längst ein komplettes Kategorie-Modal-Pattern (`#edit-modal`/`#del-modal`, `modal-backdrop`/`.modal`-Klassen aus `css/components.css`) + `js/achsen_liste.js` + vier AJAX-Endpunkte (`achse_speichern_ajax.php`, `achse_aktualisieren_ajax.php`, `achse_loeschen_ajax.php`, `achse_sort_tree_ajax.php`). Die alten Seiten `achsen/neu.php`, `bearbeiten.php`, `speichern.php`, `aktualisieren.php` waren dabei komplett verwaist (0 Referenzen im ganzen Code) — vermutlich beim Modal-Umbau ersetzt, aber nie gelöscht. Am 2026-07-10 entfernt (inkl. der zugehörigen, jetzt sinnlosen Einträge in `Zugriffsregeln.php`).

**Getestet:** kompletter Save→Update→Delete-Zyklus per CLI gegen die echte Dev-DB (inkl. echtem Session-Kontext simuliert, da `AchsenService::save()` ohne Session-Fallback für `Logger::log()` sonst crasht — reines CLI-Testartefakt, im echten Browser via `auth_check.php` immer eine Session vorhanden), danach aufgeräumt.

**Ausdrücklich NICHT mitgeprüft/gebaut:** die "Werte"-Unterebene einer Achse (z.B. Rot/Blau bei Farbe) — das war nicht Teil von Jackys ursprünglicher Anfrage (nur die Achse selbst), hängt mit dem separaten Aktions-Modul-Blocker "Wert-Ebenen-Abhängigkeit" zusammen, siehe [[project_aktionen_modul]].
