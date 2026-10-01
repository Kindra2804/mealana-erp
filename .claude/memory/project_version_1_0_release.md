---
name: project-version-1-0-release
description: "Jackys Plan (2026-10-01): nach allen großen Baustellen (inkl. Modul-Aktivieren) eine auslieferbare 1.0 bauen = Konsolidierung aller Migrationen; Live-Systeme starten erst auf 1.0, danach nur noch Updates per Update-Strategie"
metadata:
  type: project
---

Jacky am 2026-10-01: Sobald alle größeren Baustellen fertig sind (ausdrücklich auch das Modul-Aktivieren/Lizenz-Thema), wird eine **Major-Version 1.0** gebaut:
- enthält alle bisherigen Änderungen/Migrationen konsolidiert (analog zum Baseline-Neuschnitt 0.2.0, siehe [[project_installationsanleitung]]) und ist **auslieferbar** (frische Installation ohne Migrationskette),
- die **Live-Systeme laufen dann auf 1.0 und gehen damit erst richtig in Betrieb** — bis dahin ist alles Dev-DB gegen Testshop, keine echten Live-Daten,
- **ab 1.0 laufen alle Änderungen nur noch über die Update-Strategie**, die vorher nochmal genau angeschaut wird (Ausgangsidee: [[project_update_mechanismus]]).

**Why:** Saubere Startlinie für den echten Betrieb statt einer über Monate gewachsenen Migrationskette auf den Live-Systemen.
**How to apply:** Noch ein weiter Weg — nicht von selbst starten. Bei "was fehlt noch bis 1.0?"-Fragen oder wenn große Baustellen abgeschlossen werden, daran erinnern. Bis dahin Migrationen weiter normal fortlaufend nummerieren. Auch [[project_backup_strategie]] wird erst mit dem 1.0-Live-Gang dringend.
