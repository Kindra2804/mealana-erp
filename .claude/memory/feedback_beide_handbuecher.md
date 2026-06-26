---
name: feedback-beide-handbuecher
description: Beide Handbücher immer synchron halten wenn neue Module fertig werden
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 3bbaa246-5729-4e26-8fb8-4785822652ed
---

Es gibt ZWEI Handbücher die beide aktuell gehalten werden müssen:

1. `docs/handbuch/*.md` — Markdown-Dateien für Entwickler/Offline-Lesen
2. `erp/public/bedienungsanleitung.php` — PHP direkt aus der ERP-Oberfläche erreichbar

**Why:** Der User hat beim Anschauen der fertigen Markdown-Handbücher darauf hingewiesen dass es auch das PHP-Handbuch gibt — das war veraltet (viele Placeholder).

**How to apply:** Bei jedem neuen fertiggestellten Modul BEIDE Dateien updaten:
- Neues Kapitel in `docs/handbuch/XX_modulname.md` anlegen
- Gleiches Kapitel in `bedienungsanleitung.php` ergänzen (Badge: Fertig/In Arbeit/Geplant)
- TOC in bedienungsanleitung.php aktualisieren
- README.md im handbuch-Ordner aktualisieren
