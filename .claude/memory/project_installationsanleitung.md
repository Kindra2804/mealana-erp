---
name: project-installationsanleitung
description: "Geplante Installationsanleitung: Server-Setup von 0, Composer, Migrations, Cronjobs — für Jacky + Weitergabe"
metadata: 
  node_type: memory
  type: project
  originSessionId: 3bbaa246-5729-4e26-8fb8-4785822652ed
---

## Status: GESCHRIEBEN 2026-07-03

Liegt unter `D:\ERP\mealana\docs\installation.md` (im Git-Repo). Anlass: Barbara beginnt Live-Testdaten einzugeben, Jacky wollte während eines Einkaufs-Trips eine Anleitung vorbereitet haben, um danach direkt mit dem Live-Setup auf dem Server (Zugriff via AnyDesk) zu starten. Bewusst so geschrieben, dass sie auch an Tester/Interessenten weitergegeben werden kann (siehe Anhang B dort zu offenen Punkten: keine Rechteverwaltung, kein Lizenzsystem, "/mealana/"-Pfad hart verdrahtet).

Recherchierte Fakten (Stand 2026-07-03, per Code-Grep verifiziert, nicht nur aus altem Plan übernommen):
- Kein Migrations-Runner vorhanden — 101 SQL-Dateien (004–104) müssen manuell der Reihe nach eingespielt werden. `schema_current.sql` NICHT für Neuinstallation verwenden (enthält echte Produktivdaten).
- Keine `.env.example`/`config.example.php` — `erp/config/database.php` + `erp/config/encryption.php` müssen von Hand neu angelegt werden (nicht im Git, siehe `.gitignore`).
- Kein Setup-Assistent für den ersten Benutzer — Admin + System-User "Jarvis" (`id=2`, zwingend für Cron-Logging) müssen per SQL-INSERT angelegt werden.
- TinyMCE liegt schon fertig im Repo (`erp/public/js/tinymce/`) — der alte Plan "6.8.6 selbst herunterladen" ist überholt, kein Download-Schritt mehr nötig.
- URL-Pfad `/mealana/` ist überall hart codiert (Navigation, JS, TinyMCE) — Webserver muss diesen Pfad exakt auf `erp/public` mappen (Junction oder Apache-Alias), sonst brechen alle Links.
- Cronjobs: `bfr_nachsignierung.php` (alle 5 Min), `mahnwesen.php` (täglich 06:00) — Pfadbeispiele als Kommentar in den Dateien selbst.
- `erp/public/test.php` existiert im aktuellen Checkout (nicht versioniert) und sollte vor jedem Go-Live gelöscht werden.

**Update 2026-07-03 (später am selben Tag): `migrate.php`-Runner gebaut + getestet, bevor der eigentliche Live-Umzug losging.**

- `erp\database\migrate.php`: CLI-Tool mit Tracking-Tabelle `schema_migrations`. `php migrate.php` wendet nur offene Migrationen an, `php migrate.php status` zeigt den Stand, `php migrate.php bootstrap` trägt vorhandene Dateien als "schon angewendet" ein ohne sie auszuführen (für bereits migrierte DBs wie Jackys Dev-Stand).
- **Wichtiger Fund beim Testen (auf Wegwerf-Test-DB, nicht der echten):** Migrationen 004–104 lassen sich NICHT von einer wirklich leeren DB weg komplett durchspielen — Migration 006 bricht mit Foreign-Key-Fehler ab, weil Basis-Tabellen (u.a. `artikel`) nie in einer vorhandenen Migrationsdatei erzeugt werden (001–003 fehlen/wurden nie versioniert). Reines `migrate.php run` von Null ist also für Neuinstallationen aktuell **nicht** der richtige Weg.
- **Lösung:** `erp\database\baseline_schema.sql` — ein Struktur-only-Dump (kein Daten, per `mysqldump --no-data`, 89 Tabellen) des aktuellen Dev-Standes. Neuer empfohlener Ablauf für jede Neuinstallation: `baseline_schema.sql` importieren → `php migrate.php bootstrap` → fertig. Ab jetzt (Migration 105+) läuft `php migrate.php` normal inkrementell, auf Dev genauso wie auf Live.
- **Nebenbei gefundener Bug (behoben):** `005_seed_rollen_berechtigungen.sql` hatte ein fehlendes Semikolon nach der ersten INSERT-Anweisung (Zeile 1) — dadurch verschmolzen zwei Statements beim Multi-Statement-Ausführen zu ungültigem SQL. Ergänzt.
- **Nebenbei gefunden, NICHT behoben (Absicht mit Jacky nicht abgestimmt):** Dieselbe Datei (`005_...sql`, letzter Block) legt einen echten Admin-Benutzer mit fixem bcrypt-Hash + Jackys privater E-Mail (`indy1@gmx.at`) an — historischer Rest, kein generischer Seed. Betrifft aktuelle Installationen nicht (Bootstrap führt das INSERT nie aus), sollte aber irgendwann rausgenommen werden, bevor diese Migration je wieder per `migrate.php run` von Grund auf durchläuft. Vermerkt in `docs/installation.md` Anhang B.
- Jackys lokale Dev-DB wurde bereits erfolgreich gebootstrapped (`schema_migrations` mit allen 101 Einträgen).

## Was rein muss

- PHP-Version + Extensions (GD, PDO, mbstring, intl, zip ...)
- MariaDB/MySQL Setup + Datenbank anlegen
- XAMPP für Windows-Lokalbetrieb vs. Apache/Nginx auf Linux-Server
- Composer installieren + `composer install` im Projektverzeichnis
- Alle Migrations ausführen (`php migrate.php` o.ä.)
- `storage/`-Verzeichnisse anlegen + Schreibrechte setzen
- Cronjobs einrichten (Mahnwesen, WC-Sync, Aktionen-DROPS)
- WireGuard VPN-Konfiguration (Remote-Zugriff)
- system_einstellungen Basis-Konfiguration (Firmenname, UID, IBAN ...)
- RKSV/BFR-BONit Registrierung + Kassen-Konfiguration (AT-Pflicht)
- Erster Benutzer (superadmin) anlegen
- **TinyMCE 6.x self-hosted** einrichten: Version 6.8.6 (MIT, NICHT v7!) von tiny.cloud/get-tiny/self-hosted herunterladen, entpacken nach `public/js/tinymce/` — wird für Artikel-Beschreibungen (kurzbeschreibung + beschreibung) benötigt; kein API-Key nötig

## System-Stammdaten die automatisch bei Erstinstallation angelegt werden müssen

Analog zu **Jarvis** (Benutzer id=2, username='system') gibt es System-Stammdaten die fix vorhanden sein müssen:

| Was | Artikelnummer | Zweck | Referenz im Code |
|---|---|---|---|
| Diverses (Kasse) | `99-9999` | FK-Platzhalter in auftrag_positionen für freie Kassen-Positionen (Divers-Artikel ohne echtem Artikel-Datensatz) | `KassenService::getDiversArtikelId()` sucht via artikelnummer, keine hardcodierte ID |

**Warum:** `auftrag_positionen.artikel_id NOT NULL` — Divers-Positionen an der Kasse brauchen einen echten Artikel-FK, sonst fehlen sie in der Auftrags-Übersicht.

**Wichtig für Installation:** Dieser Artikel muss VOR der ersten Kassenbuchung existieren. `getDiversArtikelId()` fällt graceful zurück (überspringt die Position wenn nicht gefunden), aber das ist nur ein Fallback.

**Korrektur Jacky 2026-07-05:** Ursprünglich per Migration 078 nachträglich in die Dev-DB eingefügt — dort dadurch mit einer hohen, zufälligen ID (2957) gelandet, weil zu diesem Zeitpunkt schon tausende Demo-Artikel existierten. Für zukünftige Auslieferungen soll das **nicht mehr über eine Migration** laufen, sondern der Artikel soll direkt Teil der Baseline-/Seed-Daten sein, die bei einer Neuinstallation als Erstes eingespielt werden (analog Jarvis-Systembenutzer id=2, siehe oben) — dadurch bekommt er auf einer frischen Installation eine niedrige, vorhersagbare ID (idealerweise `1`) statt einer zufälligen hohen wie in der Dev-DB.
**How to apply:** Beim nächsten Überarbeiten von `baseline_schema.sql`/dem Neuinstallations-Ablauf ([[project_installationsanleitung]] oben) diesen Artikel als festen Seed-Datensatz mit fixer ID mit aufnehmen, nicht mehr über eine der 004–104-Migrationen laufen lassen.

## Zielgruppe

- Jacky selbst (beim Umzug auf Produktiv-Server)
- Weitergabe an andere Betriebe (MeaLana ERP als Produkt)

**Why:** Composer-Pakete (Twig, Dompdf, Barcode-Generator) müssen auf jedem Server neu via `composer install` installiert werden — vendor/ wird nicht im Git eingecheckt.
**How to apply:** Beim Produktiv-Umzug daran erinnern; Anleitung rechtzeitig vor dem Umzug schreiben.
