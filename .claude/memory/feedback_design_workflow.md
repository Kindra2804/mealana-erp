---
name: feedback-design-workflow
description: "Design-Planung läuft in 3 Stufen: ASCII → SVG → HTML, nie direkt in HTML springen"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: c55c1aca-b514-4e20-98fa-732e6e1149b3
---

Beim UI/Layout-Design immer diesen 3-Stufen-Workflow einhalten:

1. **ASCII-Wireframe** — direkt im Chat, für Grob-Struktur-Diskussion mit Karl
2. **SVG-Datei** — farbiges Mockup, öffnet im Browser, für Karls Frau (visueller Typ)
3. **HTML-Umsetzung** — erst wenn beide (Karl + Frau) mit dem Design zufrieden sind

**Why:** Karls Frau hat Mitspracherecht beim Design und ist ein visueller Typ — sie tut sich mit ASCII schwer, aber SVG ist für sie gut lesbar. Direkt in HTML zu springen kostet viel Zeit bei Änderungen.

**How to apply:** Bei jeder neuen Seite/Komponente immer mit ASCII starten, nie sofort HTML schreiben. SVG-Schritt nicht überspringen wenn Karls Frau involviert ist.

Verwandte Memories: [[project-ui-redesign]], [[user-karl]]
