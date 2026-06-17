---
name: feedback-css-strategie
description: "CSS-Strategie: derzeit inline style-Tags, später externe Stylesheets"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ddb2db19-4f9b-4e55-bc3a-9ddf0bb1637b
---

Seit dem Shell-Redesign (variables.css, layout.css, components.css) gilt: **KEINE `<style>`-Blöcke** mehr in PHP-Views. CSS gehört in die externen Stylesheets.

**Why:** Karl hat explizit angemerkt (2026-06-15), dass in den neuen Layouts keine `<style>`-Tags mehr vorkommen sollen — dafür gibt es die ausgegliederten CSS-Dateien.

**How to apply:**
- Neue CSS-Klassen → in `components.css` hinzufügen (oder `layout.css` für Layout-Utilities)
- Inline `style="..."` auf einzelnen Elementen ist OK (z.B. `style="width:100%"`)
- `<style>`-Block im `<head>` oder Body: verboten in neuen Views
- Bestehende CSS-Klassen verwenden: `.form-section`, `.form-section-header`, `.form-row`, `.form-label`, `.erp-input`, `.erp-select`, `.card`, `.btn`, `.chip`, `.versteckt` etc.

## Offene Punkte für Frontend-Refactor

- **Kategorie-Modal Baumstruktur**: `kategorien`-Tabelle hat bereits `parent_id`. Das Modal (bearbeiten.php) zeigt derzeit flache Liste. Beim Refactor: `findAll()` mit parent_id nutzen, Modal mit Einrückung rendern (Eltern → Kinder), "Neue anlegen" mit Eltern-Dropdown erweitern.
- **Checkbox-Ausrichtung im Kategorie-Modal**: Checkboxen erscheinen über dem Text statt daneben — CSS-Fix beim Refactor.
