---
name: feedback-eod-updates
description: CLAUDE.md und Memory am Ende jedes Arbeitstages aktualisieren und Rückmeldung geben
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 19d72ef3-0a51-4231-ba3f-eb2443d7edfb
  modified: 2026-11-04T21:33:38.824Z
---

Am Ende jedes Arbeitstages CLAUDE.md mit aktuellem Stand aktualisieren und Karl kurz rückmelden was geändert wurde.

**Why:** Karl hat das explizit so gewünscht damit zukünftige Sessions immer aktuellen Kontext haben.

**How to apply:**
- Trigger: Karl sagt "genug für heute", "bis morgen", "Feierabend" o.ä.
- Reihenfolge: 1) Commit-Vorschlag machen, 2) CLAUDE.md aktualisieren, 3) Memory aktualisieren
- Commit-Vorschlag: passende Message nach Konvention (feat/fix/chore/docs/refactor) vorschlagen
- CLAUDE.md aktualisieren: Data Model, What's Implemented, What's Missing
- Memory-Dateien (project_status.md) aktualisieren
- Kurze Rückmeldung geben
- Vollautomatisch ohne Trigger nicht möglich
- **Dateiname im Repo: `claude.md` (klein!)** unter `D:\ERP\mealana\claude.md`. Windows öffnet sie auch als `CLAUDE.md`, aber Git unterscheidet: `git add CLAUDE.md` erfasst sie NICHT → immer `git add claude.md` verwenden. (Jacky 2026-11-04: „schreibst du fast jeden Tag“.)
- Memory-Sync ins Repo: `.claude/memory/` (siehe [[feedback_memory_backup_bei_commit]]).
