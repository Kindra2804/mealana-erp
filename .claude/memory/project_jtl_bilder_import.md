---
name: project-jtl-bilder-import
description: "JTL Bilder-Import (Artikel-Bilder aus JTL-Bildexport per Artikelnummer zuordnen) — FERTIG gebaut + End-to-End getestet 2026-07-31, noch nicht committed"
metadata: 
  node_type: memory
  type: project
  originSessionId: 85efc9a3-c1f8-4d31-89d2-a10e99128244
  modified: 2026-07-31T19:13:26.253Z
---

## Status 2026-07-31: Implementiert + verifiziert

Eigenständiges, wiederholbares Tool (nicht an [[project_jtl_vater_kind_import]] gekoppelt) — matcht Artikelnummer aus einem JTL-Wawi-Bildexport-Ordner gegen bereits existierende Artikel in der DB.

Gebaut:
- `src/modules/import/JtlCsvReader.php` — CSV-Einlese-Logik (BOM/Windows-1252/Delimiter) aus `JtlVaterKindImportService` extrahiert, jetzt von beiden Import-Services geteilt.
- `src/modules/artikel/BildVerarbeitung.php` — GD-Resize-Logik aus `bild_upload.php` extrahiert (verhalten unverändert: max 1920px, JPEG 85%, PNG-Transparenz), `bild_upload.php` ruft sie jetzt nur noch auf.
- `src/modules/import/JtlBilderImportService.php` — parseBilderCsv/baueVorschau/fuehreImportDurch.
- `public/artikel/jtl_bilder_import.php` + `jtl_bilder_import_commit.php` — Formular fragt nur einen **Ordnerpfad auf dem Server** ab (kein Datei-Upload der Bilder über den Browser, da Webserver+Dateien auf demselben lokalen XAMPP-Rechner liegen), CSV im Ordner wird automatisch erkannt.
- Link in `artikel/liste.php`.

**Bestätigtes Verhalten (mit Jacky abgestimmt 2026-07-31):** Artikel mit bereits vorhandenen Bildern werden beim (ggf. wiederholten) Import übersprungen, nicht erneut befüllt.

**Analyse der echten Jacky-Datei (`D:\ERP\mealana\import\Bilder\JTL-Wawi-Bildexport-31072026.csv`):** 2.226 Zeilen, 2.348 Bilddateien (davon nur 395 inhaltlich unterschiedlich — MD5-Duplikate durch Foto-Wiederverwendung über Größenvarianten, kein Datenfehler), Formate: 2.288 jpg, 27 png, 33 gif. **GIF wird bewusst nicht unterstützt** (matcht die bestehende MIME-Whitelist von `bild_upload.php`: nur jpeg/png/webp) — wird in der Kontrollliste pro Bild als "Format nicht unterstützt" markiert, Rest der Zeile trotzdem importiert.

**End-to-End getestet** (synthetische ZZTEST-Artikel + generierte Testbilder, danach vollständig aufgeräumt inkl. `public/uploads/artikel/{id}/`-Ordner): Mehrfachbilder pro Artikel (Position 0=Hauptbild, 1=zweites Bild) korrekt, PNG behält `.png`-Endung, GIF korrekt übersprungen mit Fehlermeldung, fehlende Datei korrekt übersprungen, unbekannte Artikelnummer korrekt als "nicht gefunden" markiert, zweiter Lauf überspringt Artikel mit bereits vorhandenen Bildern korrekt (aber NICHT den Artikel, bei dem alle Bilder vorher fehlgeschlagen sind — der bleibt zurecht weiterhin "ok" für einen erneuten Versuch).

**Trockenlauf gegen die echte Datei:** alle 2.226 Zeilen aktuell "artikel_nicht_gefunden", da der [[project_jtl_vater_kind_import]]-Import noch nicht real gelaufen ist — erwartungsgemäß, Reihenfolge: erst Vater+Kind-Import, dann Bilder-Import.

**Noch offen:** echter Web-UI-Test im Browser (bisher nur Service-Ebene direkt getestet). Code ist NICHT committed.
