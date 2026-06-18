---
name: feedback-js-auslagern
description: Modul-Abschluss-Checkliste: JS auslagern, SQL-Kommentare entfernen, Bedienungsanleitung besprechen
metadata: 
  node_type: memory
  type: feedback
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

Am **Ende jedes Moduls** diese Checkliste abarbeiten:

1. **JS auslagern** — alle `<script>`-Blöcke aus den PHP-Views in `public/js/{modul}.js` sammeln (wie `artikel.js`)
2. **SQL-Kommentare bereinigen** — erklärende Kommentare aus Migrations-Dateien entfernen (Beispiel: `039_wert_abhaengigkeit.sql` hat noch Kommentare drin)
3. **Bedienungsanleitung besprechen** — beim ersten Mal: kurz Machbarkeit einer Bedienungsanleitung für das ERP diskutieren (noch nicht entschieden, nur Gespräch)

**Why:** Inline-Blöcke werden unübersichtlich; Kommentare in Migrations-SQL gehören nicht in den Code-Stil des Projekts; Bedienungsanleitung ist ein offenes Thema das noch nicht adressiert wurde.

**How to apply:** Nicht mitten im Aufbau — erst wenn das Modul stabil ist. Beim ersten Modul-Abschluss ans Bedienungsanleitung-Gespräch erinnern.
