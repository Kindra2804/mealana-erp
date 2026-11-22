---
name: project-update-mechanismus
description: "Update-Weg GEBAUT 2026-11-01: signierte Pakete (tools/paket/bauen.php) + ··· → Updates (prüfen, DB-Sicherung Pflicht, Wartungsmodus, einspielen, migrieren, zurückspielen); später Online-„Update verfügbar“"
metadata:
  type: project
---

## Entscheidungen Jacky 2026-11-01
1. Lieferweg 1.0: **ZIP hochladen im ERP** (Download-Link von NAHTLOS). **Später merken:** Online-Hinweis „Update verfügbar“ + Download direkt aus dem ERP — „schick, verbessert die Chancen auf Wartungspakete“.
2. **Ohne .lic = Testphase → keine Updates.** Mit Lizenz (auch nur Grundmodul): Pakete, deren Datum ≤ wartung_bis (null = unbegrenzt).
3. Einspielen macht der **Superadmin des Kunden selbst**; wer nicht will, bucht es als **Dienstleistung** (wie JTL) → Dienstleistungs-Artikel bei Gelegenheit anlegen.
4. **Ohne erfolgreiche DB-Sicherung kein Update** (mysqldump; XAMPP Standard bzw. Installationsvorgabe).

## Gebaut (Commits 0fd9156, dd6ed90, 51f6730, fe9a894 + Doku)
- `erp/src/core/Migrator.php` (migrate.php nutzt ihn), `tools/paket/bauen.php <version>` → D:/ERP/pakete/nahtlos-<v>.zip: letzter Commit (git archive, sauberer Baum Pflicht) + erp/vendor, ohne tools/lizenzgenerator/.claude/import/exports/shop/Dev-Dumps/geschützte Pfade; `docs/versionen/<v>.md` = „Was ist neu“ (Pflicht, committet); Manifest `nahtlos-manifest.json` signiert (LizenzSigner::signierePaket, typ update_paket, SHA-256 je Datei). ZIP per **PharData** (zip-Erweiterung ist in XAMPP aus!). Anleitung `tools/paket/README.md`.
- `erp/src/modules/update/UpdateService.php`: pruefen, einspielen (Verlauf → mysqldump → alte Dateien als ORDNER dateien/ + neue_dateien.json → storage/wartung.json → kopieren → Migrator), zurueckspielen (Dateien zurück, neue löschen, DB leeren + Dump einspielen). GESCHUETZT: config/database.php, config/encryption.php, storage/, uploads, img/logos, img/hersteller, beleg-portal/config.php + daten/ (außer .htaccess/.gitkeep).
- Wartungsmodus-Sperre in Auth::pruefeSeite (nur rang≥100 arbeitet), Migration 222 update_verlauf (Service legt sie auch selbst an), Seite `public/update/` (index + aktion), Menü ··· → 🔄 Updates.
- Tests: CLI 24/24 (Update 0.4.0→0.5.0, Ablehnungen, Zurückspielen, kaputte Migration), Browser 13/13. Dauer ~15–20 s je Update.

## Lehren
- **PharData addFile ist O(n²)** (schreibt Archiv jedes Mal neu, auch mit startBuffering ~87 s) → Sicherung als Ordner.
- **Zurückspielen läuft mit dem Code der NEUEN Version** → Sicherungsformat nie ändern; Testpakete immer aus dem aktuellen Commit bauen.
- Heredoc/Python in Bash verschluckt Backslashes in PHP/Pfaden → Dateien mit Write-Werkzeug anlegen.
- Testumgebung: scratchpad nahtlos_test (update_vorbereiten.sh, update_test.php, Browser-Test), DB nahtlos_test.

**How to apply:** MeaLana-Live ab 1.0 nur noch über Pakete aktualisieren (vorher dort testen, dann Kunden). Verwandt: [[project-1-0-check]], [[project-lizenzierung-preise]].

**Klarstellung 2026-11-12:** Versionen können übersprungen werden (1.0.0 → 1.0.8 geht): Paket = kompletter Stand, Migrator holt fehlende Migrationen über schema_migrations nach, Prüfung nur „neuer als installiert“ + Lizenz/Wartungsdatum.
**✅ ERLEDIGT 2026-11-15 (1.0.17):** veraltet = in einem früheren Manifest (Wurzel-nahtlos-manifest.json + storage/updates/*/paket.zip, nur gültig signiert), nicht im Ziel-Manifest, nicht geschützt, existiert. Sicherung in dateien/ (Format unverändert), entfernte_dateien.json. Übergang: 1.0.17 spielt noch alter Code ein → Karte „🧹 Veraltete Dateien entfernen“ auf der Update-Seite (UpdateService::aufraeumen). Getestet in Spiel-Installation. Alte Notiz: **TODO (Jacky: „kommt auf die Liste“, vor Verkauf an Kunden):** Update löscht keine Dateien → beim Einspielen in erp/src + erp/public Programmdateien entfernen, die nicht mehr im Manifest stehen (Uploads, storage, geschützte Pfade ausnehmen; vorher in die Datei-Sicherung). Sonst bleiben entfernte Seiten über ihre URL erreichbar.
**✅ ERLEDIGT 2026-11-15:** Manifest `neu_versionen` (bauen.php), `UpdateService::neuSeit()`; Erfolgsmeldung liest docs/versionen/*.md (wirkt schon beim Update auf 1.0.17). Alte Notiz: **TODO 2 (Jacky 2026-11-12, auf die Liste):** „Was ist neu“ bei Sprüngen über mehrere Versionen auch für alle Zwischenversionen zeigen. Machbar: bauen.php nimmt alle docs/versionen/*.md ins signierte Manifest (nicht nur die eigene), die Update-Seite zeigt alle Einträge mit Version > installiert, neueste oben.


## Früherer Index-Eintrag (aus MEMORY.md verschoben 2026-11-18)
- [🔄 Update-Weg](project_update_mechanismus.md) — gebaut 2026-11-01: tools/paket/bauen.php + ··· → Updates (DB-Sicherung Pflicht, Zurückspielen); ✅ 2026-11-15 veraltete Dateien entfernen + „Was ist neu“ über Zwischenversionen (ab 1.0.17); später Online-„Update verfügbar“

## 🔴→🟢 2026-11-22 Live-Update 1.0.28: Database.php/Logger.php gelöscht
Ursache: c9df167 hat `erp/src/core/database.php`→`Database.php` und `logger.php`→`Logger.php` nur in Groß/Klein umbenannt. Update kopiert erst, dann `entferneDateien(veraltet)`: alte Manifeste kennen database.php, neues nicht → „veraltet“ → unlink löscht unter Windows dieselbe, gerade kopierte Datei → ERP startet nicht (Fatal in Auth.php). Reparatur Live: die zwei Dateien aus dem Paket-ZIP nach `C:\ERP\mealana\erp\src\core\` kopieren; „Veraltete Dateien entfernen“ NICHT klicken (gleicher Fehler).
Fix (eb9aa1d, Paket **1.0.29**): `veralteteDateien` vergleicht ohne Groß/Klein; `tools/paket/bauen.php` liefert die alte Schreibweise aus früheren Paket-Manifesten zusätzlich mit gleichem Inhalt mit (Aliase), damit auch ein Update mit altem Code nichts löscht. **Regel: nie Dateien nur in Groß/Klein umbenennen, ohne an Aliase zu denken** (bauen.php macht das jetzt automatisch).
