---
name: feedback-emoji-css
description: CSS color hat keine Wirkung auf Emojis — filter:grayscale(1) verwenden
metadata: 
  node_type: memory
  type: feedback
  originSessionId: bcd4b392-6bb7-4866-b164-b44f4cf3cbcf
---

CSS `color:#aaa` hat keine Wirkung auf Farb-Emojis (⏰🟠✅ etc.) in modernen Browsern. Emojis sind Bild-Glyphen, keine Textzeichen.

**Why:** Entdeckt beim Aktions-Kategorie-Wecker: `color:#aaa` auf dem ⏰-Span zeigte trotzdem orange Emoji.

**How to apply:** Für gedimmte/graue Emojis immer `filter:grayscale(1)` verwenden, optional kombiniert mit `opacity` für zusätzliche Transparenz:
```html
<span style="filter:grayscale(1);opacity:.5">⏰</span>
```
Für normale Farb-Emojis kein `filter` nötig — sie zeigen ihre Eigenfarbe automatisch.
