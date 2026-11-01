---
name: feedback-commit-je-schritt
description: "Bei mehrstufigen Bauvorhaben nach jedem erledigten Schritt committen + pushen, ohne extra zu fragen"
metadata:
  node_type: memory
  type: feedback
  originSessionId: 9207fa6f-ba69-4236-b91f-bac650dd7a5a
  modified: 2026-11-01T11:20:17.216Z
---

Jacky 2026-11-01 (Lizenz-Bau): "ja bitte nach jedem erledigten Schritt committen".

**Why:** Er will bei längeren Bauten (z.B. Lizenz-Schritte 1–5) Zwischenstände gesichert haben und nicht jedes Mal gefragt werden.

**How to apply:** Sobald ein vereinbarter Schritt gebaut + getestet ist: Memory-Backup nach `.claude/memory/` kopieren ([[feedback-memory-backup-bei-commit]]), committen, pushen, kurz melden. Nur bei ungetesteten/halbfertigen Ständen vorher nachfragen.
