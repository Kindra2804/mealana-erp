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
