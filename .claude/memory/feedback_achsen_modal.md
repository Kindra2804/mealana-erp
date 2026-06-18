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
