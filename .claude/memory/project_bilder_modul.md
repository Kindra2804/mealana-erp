---
name: project-bilder-modul
description: "Bilder-Modul: nächste Session — Tab ist Platzhalter, Backend fehlt komplett"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

## Status

Tab "Bilder" in `detail.php` existiert als Platzhalter — kein Backend, keine DB-Tabellen.

## Nächste Session: Bilder-Modul von Grund auf bauen

### Vor Implementierung: Referenz-Check (wie immer bei neuem Modul)
- Was machen JTL/Shopware/WooCommerce bei Artikel-Bildern?
- Was braucht MeaLana extra?

### Bekannte Anforderungen
- Mehrere Bilder pro Artikel
- Sortierung (Hauptbild / Galerie)
- Vererbung: Vater-Bilder vs. Kind-eigene Bilder (Variantenbilder z.B. je Farbe)
- Shop-Export: Bilder müssen zu WooCommerce übertragen werden können
- Upload via Formular (kein Drag-Drop erforderlich für erste Version)

### Offene Design-Fragen (in der Session klären)
- Speicherort: Filesystem (uploads/) oder DB (BLOB)? — fast sicher Filesystem
- Bildgrößen: Original behalten + Thumbnails generieren, oder nur Original?
- Naming-Konvention für Dateien
- Welche DB-Tabelle? (artikel_bilder mit position, alt_text, ist_hauptbild)
- Gilt die Vererbungslogik auch hier? (Vater-Bild → Kinder wenn keine eigenen)

### Workflow-Doku
- Nach Implementierung: `docs/workflows/artikel_workflows.md` um Workflow 13 "Bilder" erweitern

**How to apply:** Neue Session mit Referenz-Check starten, dann Design, dann Implementierung.
