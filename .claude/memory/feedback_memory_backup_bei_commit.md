---
name: ""
metadata: 
  node_type: memory
  originSessionId: dc6556bb-1ad0-4e04-9a7c-e81f5d515268
---

Wenn Jacky "commit, push" (oder "memory, save, commit, push") sagt, IMMER zuerst den aktuellen Stand von `C:\Users\indy1\.claude\projects\d--ERP\memory\*.md` nach `D:\ERP\mealana\.claude\memory\` kopieren und mitcommitten — nicht nur den Code.

**Why:** Nach dem PC-Umzug (2026-07-17) fiel auf, dass das Memory-Backup im Repo seit 05.07. nicht aktualisiert worden war (12 Tage/mehrere Sessions Lücke) — obwohl das laut Jacky schon nach einem früheren "Erinnerungs-Desaster" (Gedächtnislücken-Vorfall) als feste Regel ausgemacht worden war. Diese Regel war mir schlicht nicht (mehr) present. Das eigentliche Live-Memory unter `C:\Users\indy1\.claude\projects\d--ERP\memory\` liegt außerhalb des Repos (Windows-Profil) und wird nie automatisch von Git erfasst.

**How to apply:**
1. `cp -f "C:/Users/indy1/.claude/projects/d--ERP/memory/"*.md "D:/ERP/mealana/.claude/memory/"`
2. `git add .claude/memory/` zusammen mit den Code-Änderungen (oder als eigener Commit, falls Code + Memory getrennt sinnvoller ist)
3. Committen + pushen wie gewohnt

Das gilt für JEDEN commit/push-Zyklus in diesem Projekt, nicht nur wenn explizit nach Memory gefragt wird — Jacky hat das ausdrücklich als Standard-Verhalten gewünscht (2026-07-17), nicht als Einzelaktion.
