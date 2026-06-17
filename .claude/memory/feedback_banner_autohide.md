---
name: feedback-banner-autohide
description: Erfolgs-Banner soll nach 3 Sekunden automatisch verschwinden (Auto-Hide)
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ddec3cf9-d986-4777-a145-2a852e284888
---

Erfolgs- und Fehlermeldungen in detail.php sollen als Banner mit Auto-Hide gebaut werden.

**Why:** Jacky möchte keinen Banner der ewig stehen bleibt — Standard-Pattern wie JTL.

**How to apply:**
- Grüner Erfolgs-Banner (aus `$_SESSION['erfolg']`) erscheint nach Speichern
- Verschwindet automatisch nach ~3 Sekunden per JS (setTimeout + CSS transition)
- Roter Fehler-Banner analog
- Kommt wenn der allgemeine Erfolgs/Fehler-Banner in detail.php gebaut wird (Thema "Ungespeicherte Änderungen")
