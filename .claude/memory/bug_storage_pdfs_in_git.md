---
name: bug-storage-pdfs-in-git
description: "Echte Kassenbons/Picklisten-PDFs steckten in der Git-Historie auf GitHub — BEHOBEN 2026-07-09 (History-Rewrite + Force-Push)"
metadata:
  type: project
  originSessionId: 6a936472-c44a-429d-81c6-e25437a813f9
---

## ✅ BEHOBEN (2026-07-09)

Repo ist privat (von Jacky bestätigt) — mit seinem OK History bereinigt: `git filter-branch --index-filter "git rm -r --cached --ignore-unmatch erp/storage/bons erp/storage/picklisten" --prune-empty -- --all` über alle 225 Commits (dauerte ~3 Min. im Hintergrund, `git filter-repo`/BFG waren nicht installiert, `filter-branch` reichte für den kleinen Umfang). Vorher Sicherheits-Mirror angelegt (`git clone --mirror`, liegt als Fallback unter `D:\ERP\mealana_backup_vor_history_rewrite.git`). Danach `refs/original/*` gelöscht, `git gc --prune=now --aggressive`, `git push --force origin master`. Verifiziert: weder lokal noch auf GitHub sind die PDFs noch in der Historie, echte Dateien blieben unangetastet auf der Platte (nur aus Git-Tracking raus), `.gitignore` greift wieder normal.

**Wichtige Nebenwirkung:** Alle 225 Commit-Hashes haben sich geändert (jeder Commit-Hash hängt vom Parent ab, daher ändert sich die gesamte Kette ab dem ersten betroffenen Commit). Falls irgendwo alte Commit-Hashes notiert/verlinkt sind (z.B. in Notizen), zeigen die nicht mehr auf GitHub.

**Why:** Nur relevant weil Repo aktiv als Vorlage für Weitergabe an Dritte gedacht ist ([[project_installationsanleitung]] Anhang B) — bei einem rein internen Repo wäre das Risiko geringer gewesen.
**How to apply:** Erledigt, keine weitere Aktion nötig. Backup-Mirror kann bei Bedarf gelöscht werden, sobald Jacky sicher ist dass alles passt.

## Fund (2026-07-09, beim Bauen des ersten Update-ZIPs für Live)

5 echte Kassenbons (`erp/storage/bons/12.pdf` etc.) + 2 Picklisten (`erp/storage/picklisten/P-2026-00001.pdf`, `PL-2026-00002.pdf`) wurden vor der `.gitignore`-Regel für `erp/storage/*` committet (Commits `9f133fa` und `b9f7923`, siehe `git log --all -- erp/storage/...`). `.gitignore` verhindert nur künftige neue Dateien, keine rückwirkende Entfernung — diese 7 Dateien stecken weiterhin in der Historie und liegen bereits auf `origin` (GitHub, `Kindra2804/mealana-erp`).

**Sofort erledigt:** `.gitattributes` um `export-ignore` für `erp/storage/bons/**`, `erp/storage/picklisten/**`, `erp/storage/dokumente/**` ergänzt — künftige `git archive`-Exporte (Update-ZIPs, siehe [[project_update_mechanismus]]) enthalten diese Dateien nicht mehr.

**Noch offen:** Die 7 Dateien liegen weiterhin in der Git-Historie selbst (nicht nur im aktuellen Checkout) und damit auf GitHub. Eine Bereinigung (z.B. `git filter-repo` / BFG Repo-Cleaner) würde Commits umschreiben und einen Force-Push erfordern — das ist eine Entscheidung, die Jacky treffen muss, nicht automatisch ausgeführt werden.

**Why:** Kassenbons/Picklisten können Kundendaten enthalten (RKSV-Belege) — nicht ideal in einer Historie, die potenziell auch für Weitergabe an Dritte (siehe Anhang B der Installationsanleitung, [[project_installationsanleitung]]) relevant werden könnte.
**How to apply:** Nicht von selbst eine History-Bereinigung starten. Erst wenn Jacky das Thema aktiv anspricht: klären ob das GitHub-Repo privat ist, dann History-Rewrite + koordinierten Force-Push planen (betrifft auch alle, die schon geklont haben).
