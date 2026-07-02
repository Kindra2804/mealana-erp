---
name: feedback-flag-emoji
description: Unicode-Flaggen-Emoji (Regional Indicator Symbols) unzuverlässig auf Windows-Browsern — nicht verwenden
metadata: 
  node_type: memory
  type: feedback
  originSessionId: eefd559b-9c02-443d-a0cb-164e3dadf876
---

Länder-Flaggen als Unicode-Emoji (z.B. 🇦🇹 aus zwei Regional-Indicator-Symbolen zusammengesetzt) NICHT verwenden. Auf Jackys Windows-11-Setup zeigen Chrome/Edge/Firefox nur die zwei rohen Buchstaben-Symbole an statt der kombinierten Flagge, obwohl Windows selbst die Emoji-Flaggen unterstützt.

**Why:** Beim Lieferanten-Land-Dropdown (2026-07-02) eingebaut und getestet — Dropdown selbst hat funktioniert, aber die erhofften Flaggen wurden nicht gerendert, nur Länderkürzel sichtbar. Auf Jackys Wunsch wieder entfernt statt mit einer Bild-Bibliothek nachgerüstet.

**How to apply:** Für Länder-Anzeigen nur Klartext-Name (+ optional ISO-Code) verwenden. Falls doch mal echte Flaggen gewünscht sind: nur über eine lokal gehostete SVG/PNG-Icon-Bibliothek (z.B. flag-icons), nicht über Unicode-Emoji. Das braucht einmaligen Internetzugriff zum Herunterladen der ca. 250 Dateien plus Projekt-Speicherplatz — bisher von Jacky als "nicht nötig" abgelehnt (Dropdown allein löst das eigentliche Problem, Tippfehler bei Länderkürzeln).
