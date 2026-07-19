---
name: project-installationsanleitung
description: "Installationsanleitung + Baseline-Neuschnitt (2026-07-09 fertig): Server-Setup, Composer, Migrations, Cronjobs, Live-Upgrade-Weg"
metadata: 
  node_type: memory
  type: project
  originSessionId: 3bbaa246-5729-4e26-8fb8-4785822652ed
  modified: 2026-07-19T11:03:18.988Z
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

## Neuer Baseline-Schnitt geplant — NACH Abschluss des Kassenthemas (2026-07-07)

Jacky will, sobald das aktuelle Kassenthema (Arbeitsplätze/Geräte-Erkennung, Kollisions-Sperre, Resync-Gate, siehe [[project_kassen_verwaltung]]) fertig ist, einen kompletten Baseline-Neuschnitt machen: `baseline_schema.sql` frisch dumpen, die dann fast 120 Migrationsdateien aufräumen (Seed-Migrationen wie Jarvis-Systembenutzer und der 99-9999-Freitext-Artikel — siehe Abschnitt oben — direkt in die Baseline/Seed-Daten wandern lassen statt als eigene Migration), und dabei die App-Versionsnummer (`erp/VERSION`, aktuell 0.1.0) anheben.

**Warum jetzt vormerken:** Migration 112 wurde am 2026-07-07 gelöscht, weil sie durch einen zwischenzeitlich neu gezogenen Baseline-Dump (nach dem BFR-Ausfallerkennung-Umbau vom 2026-07-06) bereits überflüssig war — genau das Muster, das bei einem allgemeinen Aufräumschnitt systematisch für alle inzwischen überholten Migrationen passieren sollte, statt einzeln bei jedem Stolpern entdeckt zu werden.

**How to apply:** Nicht von selbst anfangen — erst wenn Jacky das Kassen/Arbeitsplätze-Thema als abgeschlossen markiert. Dann: neuer `mysqldump --no-data`-Baseline-Dump, Seed-Daten (Jarvis, Freitext-Artikel, ggf. weitere reine Stammdaten-Migrationen) in die Baseline integrieren, alte Migrationsdateien die dadurch überholt sind entfernen (wie bei 112 vorexerziert — nicht nur als "angewendet" markieren, sondern löschen, wenn ihr Effekt schon Teil der neuen Baseline ist), `erp/VERSION` hochzählen, `docs/installation.md` entsprechend aktualisieren.

## ✅ Baseline-Neuschnitt ERLEDIGT (2026-07-09) — beide Spuren durch

**Track 1 — leere DB / Neuinstallationen:** `baseline_schema.sql` neu geschnitten (Stand Migration 123). Enthält jetzt nicht mehr nur Struktur, sondern auch Volldaten für echte System-Stammdaten (Rollen/Berechtigungen, Artikeltypen, Einheiten, Steuerklassen, Länder, Zahlungsbedingungen, Versandklassen) plus fixe Seed-Zeilen (Jarvis, Diverses-Artikel `99-9999`, Laufkunde, neutraler "Hauptkanal"-Shop) — alle mit niedriger/vorhersagbarer ID (1), da `mysqldump`s AUTO_INCREMENT-Startwerte aus der Dev-DB rausgestrippt wurden (sonst hätte z.B. der Diverses-Artikel wieder eine zufällige hohe ID wie früher bekommen). Dadurch überholte Seed-Migrationen gelöscht: 005 (inkl. des alten, fest verdrahteten Jacky-Admin-Accounts — jetzt komplett weg statt nur "nie ausgeführt"), 011, 013, 032, 046, 064, 078, 097, 105. `erp/VERSION` → 0.2.0(beta).

**🔴 Dabei entdeckter Kernfund:** Der alte Baseline+Bootstrap-Weg importierte zwar die Tabellenstruktur, aber `migrate.php bootstrap` führt Migrationen 004+ nie wirklich aus — jede reine Stammdaten-Migration in dem Bereich (Rollen, Artikeltypen, Einheiten, Steuerklassen, Länder, Zahlungsbedingungen, Versandklassen) wurde bei einer echten Neuinstallation stillschweigend übersprungen. Betraf nicht nur künftige Neuinstallationen, sondern **schlug auf der eigenen Live-Umgebung real zu** (siehe unten).

**Track 2 — Live-Umgebung (192.168.178.222):** Barbara hatte zum Zeitpunkt der Session noch keinen WireGuard-Zugriff, auf Live war "noch so gut wie nix" — die ursprüngliche Sorge um bereits-korrekte/abweichende Live-Daten (eigene Kassen-Registrierung, eigene Artikel-IDs) war zu diesem Zeitpunkt hinfällig. Tatsächlicher Bedarf: Kategorien (86) + Hersteller (73) von Dev nach Live exportieren, damit Barbara mit Artikel-Anlage starten kann (`erp/database/export_kategorien_hersteller_dev_2026-07-09.sql`, im Repo). Bilder (Hersteller-Logos) hat Jacky separat per Ordner-Kopie rübergespielt (die sind bewusst `.gitattributes export-ignore`, nicht Teil von `git archive`).

**Update-Mechanismus live erstmals getestet** (siehe [[project_update_mechanismus]]): `git archive HEAD` als ZIP, drüberkopieren auf Live (config/vendor/storage/uploads/logos bleiben automatisch unberührt), `composer install`, `php migrate.php`. Funktioniert grundsätzlich.

**🔴 Live-Vorfall beim Nachziehen:** Live stand auf Migration 105 — genau der oben beschriebene Bootstrap-Bug hatte dort real zugeschlagen: `rollen`/`berechtigungen` waren nie geseedet, `benutzer_rollen` komplett leer (Jackys eigener `admin`-Login hatte gar keine Rolle zugewiesen!). Migration 109 brach deshalb mittendrin mit FK-Fehler ab (`rolle_id` wurde durch NULL→0-Koerzierung bei non-strict SQL-Mode ungültig). Da `migrate.php` pro Datei ohne Transaktion arbeitet, blieb der Teilzustand stehen. Korrektur-Skript `live_fix_109.sql` gebaut (lokal gegen simulierten Live-Zustand getestet, idempotent), auf Live eingespielt — Ergebnis danach 1:1 identisch mit Devs Rollen/Rechte-Matrix (72/71/71/69/24/7/5/6/17). Migration 109 manuell in `schema_migrations` vermerkt, Rest (110–123) lief danach über `php migrate.php` sauber durch. Live ist jetzt auf Stand 123, Rollensystem inkl. `admin`-Login-Zuweisung repariert.

**Wichtige Lektion für künftige Migrations-Aufräumaktionen:** Beim ersten Cleanup-Versuch wurden 109+110 fälschlich mitgelöscht (weil ihr Effekt in der neuen Baseline steckt) — das gilt aber nur für den Fresh-Install-Pfad. Für eine bestehende Installation, die noch nicht so weit migriert ist (wie Live bei 105), müssen diese Dateien als echte inkrementelle Migrationen erhalten bleiben. Vor dem Löschen einer Migration also immer prüfen: ist sie auf **jeder** relevanten Umgebung (nicht nur Dev) schon real gelaufen?

**Why:** Ohne dieses Zusammenspiel (Baseline-Fix + Live-Nachziehen) wäre Barbara auf einer Live-Instanz gelandet, in der praktisch niemand irgendeine Berechtigung hat.
**How to apply:** Bei künftigen Baseline-Neuschnitten dieselbe Prüfung: welche Migrationen sind auf JEDER environment (nicht nur Dev) schon real durchgelaufen, bevor Dateien gelöscht werden. `live_fix_109.sql` liegt in `D:\ERP\live_fix_109.sql` als Referenz falls nochmal eine Umgebung denselben Bootstrap-Bug zeigt.

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

## ✅ Live-Update auf 0.3.0 (2026-07-19) — Bootstrap-Skip-Bug betraf VIEL mehr Tabellen als bekannt

Ausgangspunkt: Live (Stand Migration 125) auf aktuellen Dev-Stand bringen (Migrationen 126–141: Buchhaltung, Inventur, Lagerplätze, Logger-UI, Packplatz-Teillieferung). Ablauf: `git archive HEAD` (committeter Stand, 3 saubere Commits vorher nachgeholt) → per AnyDesk auf Live kopieren → entpacken → `composer install` (no-op) → `php migrate.php run` (alle 16 sauber durchgelaufen) → Version auf 0.3.0(beta).

**Dabei Kernfund, deutlich größer als der Rollen/Berechtigungen-Vorfall vom 09.07.:** Live wurde am 03.07. aus einem **struktur-only** `baseline_schema.sql` + `migrate.php bootstrap` aufgesetzt (markiert alte Migrationen als "erledigt" OHNE sie auszuführen). Jede reine Seed-Migration im Bereich 004–104 wurde dadurch stillschweigend übersprungen — betraf nicht nur Rollen (schon 09.07. gefixt), sondern auch:
- `steuerklassen` (nur 2 von 5 Zeilen — die 2 stammten von Migration 128, die heute lief; 20%/10%/0% fehlten komplett)
- `artikel_typen`, `einheiten`, `laender`, `zahlungsbedingungen` — **komplett leer**, hätte Artikel-Anlage/Lieferanten-Formular auf Live sofort blockiert
- `kundengruppen`, `dokument_nummern` — komplett leer
- Diverses-Artikel (`99-9999`, Kasse-Freitext-Platzhalter) fehlte
- `bfr_ausfaelle` + `bfr_ausfall_ereignisse` (RKSV-Ausfallerkennung, neue Architektur seit 06.07.) **existierten als TABELLEN gar nicht** — nur in `baseline_schema.sql`, nie als eigene Migrationsdatei (beim Baseline-Neuschnitt 09.07. gelöscht, weil "schon in der neuen Baseline"). Live hatte stattdessen noch die alte, abgelöste `bfr_nachsignierungs_laeufe`-Tabelle — heutiger Code-Deploy hätte die Kassen-Ausfallerkennung ohne diesen Fix live gebrochen.

**Methodik, die den Fund ermöglicht hat:** systematischer Zeilenzahl-Vergleich ALLER 107 Tabellen (Dev vs. Live per generierter UNION-ALL-Query), nicht nur der vermuteten Kandidaten — reines Stichproben-Prüfen hätte das nicht gefunden. Query liegt als Vorlage unter `D:\ERP\tabellen_counts_query.sql` für künftige Live-Syncs.

**Fix-Dateien** (alle ohne `USE`/`CREATE DATABASE`, sicher für Direktimport): `D:\ERP\live_fix_bfr_tabellen_20260719.sql` (2 CREATE TABLE, keine Dev-Testdaten), `D:\ERP\live_fix_stammdaten_20260719.sql` (artikel_typen/einheiten/laender/zahlungsbedingungen + Diverses-Artikel), `D:\ERP\live_fix_kundengruppen_nummern_20260719.sql` (kundengruppen + dokument_nummern mit `letzt_nr=0`, NICHT Devs Testzähler).

**Bewusst NICHT kopiert:** `kassen`/`lager` (auf Live noch nicht angelegt — kein Bug, Jacky richtet die mit echten Live-Daten selbst ein, nicht mit Devs Testeinträgen). `system_einstellungen` (17 fehlende Schlüssel, u.a. SMTP-Passwort/PLC-Vorlagen/Bankdaten) — bewusst NICHT automatisch kopiert, da sensible/Live-spezifische Werte; Code hat überall sichere Fallbacks (`?? '0'`/`?? 'default'`), nichts bricht dadurch. Jacky trägt die bei Bedarf selbst über die Einstellungsseiten ein. `shops` (Live bewusst bei 1, die 2 Zusatz-Shops sind Platzhalter für die noch nicht gestartete Online-Shop-Anbindung). `bfr_nachsignierungs_laeufe` (alte, verwaiste Tabelle) bewusst nicht gelöscht — Live-Kasse hatte laut Jacky noch nie BFR aktiviert, also sicher leer, aber Löschen war nicht eilig genug um es unter Zeitdruck zu riskieren.

**How to apply:** Bei JEDEM künftigen Live-Sync (nicht nur beim nächsten) den vollständigen Tabellen-Zeilenvergleich fahren, nicht nur die Migrationen laufen lassen und "fertig" annehmen — der Bootstrap-Skip-Bug kann noch an weiteren, bisher unentdeckten Stellen stecken (alles was ursprünglich in 004–104 seedete UND vor dem 09.07.-Baseline-Recut lag). `live_fix_109.sql`, die drei neuen Fix-Dateien und `tabellen_counts_query.sql` liegen alle unter `D:\ERP\` als Referenz.

## Zielgruppe

- Jacky selbst (beim Umzug auf Produktiv-Server)
- Weitergabe an andere Betriebe (MeaLana ERP als Produkt)

**Why:** Composer-Pakete (Twig, Dompdf, Barcode-Generator) müssen auf jedem Server neu via `composer install` installiert werden — vendor/ wird nicht im Git eingecheckt.
**How to apply:** Beim Produktiv-Umzug daran erinnern; Anleitung rechtzeitig vor dem Umzug schreiben.
