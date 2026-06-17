---
name: feedback-modul-vorgehen
description: "Wie jedes neue Modul begonnen wird: erst Referenz-Check, dann Design"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 989bc64e-f828-4b52-985f-502aa46f3404
---

Jedes neue Modul startet mit einem Referenz-Check bevor Design oder Code beginnt:
1. Was machen große WAWIs (JTL, Shopware, Sage, etc.) als Basis?
2. Was brauchen wir extra oder anders (MeaLana-spezifisch, quelloffen)?
3. Dann erst Design + Implementierung.

**Why:** Verhindert dass Features vergessen werden die später teuer nachzurüsten sind. Geplante Verbesserungen (Varianten-System, artikeltyp-Tabelle, Multi-Shop etc.) im Hinterkopf behalten und bei passenden Modulen einfließen lassen.

**How to apply:** Beim Start jedes neuen Moduls (Kasse, Kundendatenbank, Bestellwesen, Inventur usw.) zuerst kurze Analyse: Referenz-Features + MeaLana-Extras. Erst dann Schema + Code.
