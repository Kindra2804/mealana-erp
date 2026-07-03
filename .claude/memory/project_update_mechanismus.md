---
name: project-update-mechanismus
description: "Konzept fürs Update-/Patch-System (wie JTL-Patches) — zurückgestellt bis Lizenz-Thema angegangen wird"
metadata:
  node_type: memory
  type: project
  originSessionId: db02ffa8-aab5-44a1-a954-8cc195e7d369
---

## Idee (2026-07-03): Update-Pakete ohne Versions-Tracking pro Installation

Jacky will Updates künftig wie JTL-Patch-Pakete ausliefern: ein Paket, das drübergespielt wird, ohne dass man den genauen Stand jeder einzelnen Installation kennen muss.

**Kernidee, mit der bereits vorhandenen Infrastruktur:**
- `erp/database/migrate.php` + `schema_migrations`-Tabelle lösen das Versionsproblem für die DB bereits: merkt sich pro Installation welche Migrationen gelaufen sind, führt bei jedem Aufruf nur die fehlenden nach. Keine Versionserkennung nötig, einfach `migrate.php run` nach jedem Update-Copy aufrufen.
- Deployment läuft schon per `git archive` (siehe [[project_installationsanleitung]]) — der Baustein "sauberes Paket vom aktuellen Stand exportieren" existiert also bereits.
- **Empfehlung (noch nicht final, nur Diskussionsstand):** statt echter Delta-Patches (bräuchten Kenntnis vom Stand jeder Installation — genau das was vermieden werden soll) lieber immer den **kompletten aktuellen Stand** von `public/`+`src/`+`database/migrations/` als ein ZIP ausliefern und drüberkopieren. Unveränderte Dateien werden 1:1 überschrieben (No-op). Bei der Projektgröße einfacher als echte Diffs — die lohnen sich erst bei echtem Datei-/Traffic-Volumen.
- Ausnahme: alles Installations-Individuelle (config/database.php, Uploads, Logos, encryption.php) darf nicht überschrieben werden — größtenteils schon über `.gitattributes export-ignore` + `.gitignore` abgedeckt.

**Why:** Wird relevant sobald das Lizenz-/Pakete-Thema angegangen wird (siehe [[project_rechte_rollen]] — Lizenz-Pakete-Tabelle, Weitergabe an andere Betriebe). Bis dahin bewusst zurückgestellt.
**How to apply:** Nicht von selbst umsetzen. Erst wieder aufgreifen wenn Jacky das Lizenz-Thema aktiv anspricht. Bis dahin: das "drüberkopieren + migrate.php run"-Prinzip einfach am eigenen Live-System (siehe [[project_infrastruktur]]) mittesten, wenn ohnehin Updates eingespielt werden — keine gesonderte Test-Infrastruktur nötig.
