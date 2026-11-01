# Update-Pakete bauen (nur NAHTLOS)

Dieser Ordner kommt nie ins Kundenpaket.

## Neue Version ausliefern

1. **Version festlegen:** `1.0.x` = Fehlerbehebungen, `1.x.0` = neue Funktionen (z.B. 1.1 Steuerprofil).
2. **„Was ist neu“ schreiben:** `docs/versionen/<version>.md`, eine Zeile je Punkt, für Kunden verständlich:
   ```
   - **Kasse:** Beleg per QR-Code
   - Fehler beim Drucken der Pickliste behoben
   ```
3. **Alles committen** — gebaut wird immer der letzte Commit, nicht committete Änderungen brechen ab.
4. **Bauen:**
   ```
   C:\xampp\php\php.exe tools\paket\bauen.php 1.0.1
   ```
   → `D:\ERP\pakete\nahtlos-1.0.1.zip` (signiert mit `D:\ERP\lizenz_schluessel\nahtlos_lizenz_privat.pem`).
5. **Zuerst bei MeaLana einspielen** (··· → 🔄 Updates) und kurz durchklicken — erst dann an Kunden geben.
6. Kunden bekommen den Download-Link; Einspielen machen sie selbst (Superadmin) oder als gebuchte Dienstleistung.

## Was im Paket ist

Letzter Commit (`git archive`) + `erp/vendor` aus dem Arbeitsordner, `erp/VERSION` = neue Version,
`nahtlos-manifest.json` (Version, Datum, Commit, Was ist neu, SHA-256 je Datei, signiert, typ `update_paket`).

**Nicht drin:** `tools/`, `erp/src/modules/lizenzgenerator/`, `.claude/`, `import/`, `exports/`, `shop/`, `CLAUDE.md`,
Dev-Dumps, und nie, was der Installation gehört (`UpdateService::GESCHUETZT`: `config/database.php`, `config/encryption.php`,
`storage/`, Uploads, Logos, Belegseiten-`config.php`/`daten/`). Von `docs/` nur Handbuch, Installationsanleitung, Versionen.

## Wichtig beim Weiterentwickeln

- **Migrationen** müssen auf jedem Stand laufen (`IF NOT EXISTS`, `WHERE NOT EXISTS`) — sie werden beim Kunden
  automatisch nach dem Kopieren ausgeführt. Keine Firmen-/Geschäftsdaten in Migrationen (siehe 210/219).
- **Sicherungsformat nie ändern** (`erp/storage/updates/<ordner>/dateien/`, `neue_dateien.json`, `datenbank.sql`):
  zurückgespielt wird mit dem Code der *neuen* Version.
- Dateien, die in einer neuen Version wegfallen, bleiben beim Kunden liegen (harmlos) — bei Bedarf in einer Migration
  bzw. im Code berücksichtigen.
- Später geplant: Online-Hinweis „Update verfügbar“ + Download direkt aus dem ERP (Jacky 2026-11-01).
