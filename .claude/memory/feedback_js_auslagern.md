---
name: feedback-js-auslagern
description: Am Ende jedes Moduls überlegen ob JS in externe Datei ausgelagert wird (wie artikel.js)
metadata: 
  node_type: memory
  type: feedback
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

Am **Ende jedes neuen Moduls** überlegen ob das JS ausgelagert werden soll — wie `public/js/artikel.js` beim Artikel-Modul.

**Why:** Inline-Script-Blöcke in PHP-Views werden mit der Zeit unübersichtlich. Externe JS-Dateien sind besser wartbar, cachebar, und konsistent mit der CSS-Strategie (keine `<style>`-Blöcke).

**How to apply:** Nach Abschluss eines Moduls (z.B. Kunden, Bestellwesen, Kasse): alle `<script>`-Blöcke aus den PHP-Views sammeln und in `public/js/{modul}.js` auslagern. Nicht mitten im Aufbau — erst wenn das Modul stabil ist.
