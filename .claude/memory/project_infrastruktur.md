---
name: project-infrastruktur
description: "Geplantes Server/Netzwerk-Setup MeaLana (lokaler Server, VPN, Messe-Kasse Offline)"
metadata: 
  node_type: memory
  type: project
  originSessionId: 7c2206d0-2966-4b33-8077-f725a9bdff96
---

## Aktuelles Setup (Ist-Stand)

- **Server-PC**: Windows, MS SQL Server Express (10GB-Limit!), per Port-Forwarding + no-ip aus Internet erreichbar
- **Büro-PC (Babsi)**: lokales Netz, ERP-Arbeit
- **Packplatz-PC**: lokales Netz, Picklisten/Labels
- **Kasse 1 (Luwosoft)**: lokales Netz, eigenständig
- **Kasse 2 / Messe**: lokales Netz ODER eigener Internetanschluss, Luwosoft
- **Homeoffice (Jacky)**: Vollzugriff/Superadmin via Port-Forwarding
- **3 Webspaces**: WooCommerce-Shops, je eigene DB

## Ziel-Setup

**Server-PC (derselbe Windows-PC):**
- XAMPP + MariaDB (statt MS SQL) + PHP
- ERP läuft lokal auf Port 80
- Alle lokalen PCs: Browser → `http://erp.local`

**Remote-Zugang (Homeoffice Jacky):**
- WireGuard VPN ins lokale Netz → Browser → erp.local
- Ersetzt Port-Forwarding + no-ip komplett → sicherer (DSGVO!)

**Shops:**
- Bleiben auf Webspace mit eigenen DBs
- ERP synct via WooCommerce REST-API (kein direkter DB-Zugriff)

**Kasse 1 (Laden):**
- Eigenständig lokal, BFR-Dienst + Signaturkarte am Kassen-PC

**Messe-Kasse (Variante B — Offline):**
→ siehe unten

## Offline-Resilienz (Ziel = gleich wie heute)

| Szenario | Heute | Zukunft |
|---|---|---|
| Internetausfall | Kasse/Büro/Packplatz laufen ✓ | gleich ✓ |
| Homeoffice bei Ausfall | kein Zugang ✗ | kein VPN → kein Zugang ✗ |
| Shops bei Ausfall | laufen, kein Sync ✗ | gleich ✗ |

## Messe-Kasse: Variante B (Vollständig Offline)

**Warum machbar:** Messe-Kasse bedient sich ausschließlich vom Messe-Lager (K2, umschaltbar). Dadurch keine Sync-Konflikte mit dem Hauptlager.

**Ablauf:**
1. Tag vorher (noch im lokalen Netz):
   - Ware → Umlagerung Hauptlager → Messe-Lager (K2)
   - Pre-Sync: Artikelkatalog + Messe-Lager-Stand + Preise → lokal im Browser (IndexedDB)
2. Während Messe (vollständig offline):
   - Kasse arbeitet auf lokaler IndexedDB (kein SQLite, kein lokaler Server nötig — siehe [[project_kassen_verwaltung]] für die Architekturentscheidung 2026-07-03)
   - RKSV: Browser ruft BFR-Dienst direkt per `fetch()` an (127.0.0.1:8787), Signaturkarte am Messe-Laptop (kein Internet nötig!)
   - Nur Abgänge aus Messe-Lager
   - Kunden = Laufkunde (kein Kundendatensatz nötig — Verschlüsselungskey verlässt den Server ohnehin nie)
3. Nach Messe (zurück im lokalen Netz):
   - Post-Sync: Kassenbuchungen → ERP Umsatz, Lagerabgänge → Messe-Lager-Buchungen (Server-API dafür bereits fertig: `MesseSyncService::postSyncVerarbeiten()`/`rueckkehrVerarbeiten()`)
   - RKSV-Belegkette → archivieren
   - Restbestand → Umlagerung zurück ins Hauptlager

**Sync-Konflikte:** minimal bis keine, weil Messe-Lager isoliert ist und niemand parallel darauf bucht.
**Korrektur 2026-07-03:** ursprünglich war "lokale SQLite" geplant — bewusst verworfen zugunsten von IndexedDB + direktem Browser→BFR-Call, um dauerhafte Pflege zweier SQL-Dialekte (MariaDB vs. SQLite) zu vermeiden. Details siehe [[project_kassen_verwaltung]].

## Datenschutz-Gewinn

- WireGuard VPN statt Port-Forwarding → kein öffentlicher Endpunkt am ERP
- Kundendaten bleiben lokal (AES-256-GCM in DB, wie gebaut)
- Backups: lokaler externer Datenträger + verschlüsselt Cloud (z.B. Backblaze B2 + rclone, ~2€/Monat)
- MS SQL Express → MariaDB: kein 10GB-Limit mehr

**Why:** Diskussion 2026-06-23: Sorge über Offline-Resilienz + Datenschutz beim Umstieg auf eigenes ERP.
**How to apply:** Kasse-Modul mit SQLite-Offline-Modus + Pre/Post-Sync planen; BFR immer lokal am Gerät installiert.

## Update 2026-07-03: WireGuard VPN tatsächlich umgesetzt (nicht mehr nur geplant)

Server-PC (192.168.178.222, statische lokale IP — dort läuft auch der JTL-WAWI-Server mit eigener Portfreigabe) hat jetzt XAMPP+MariaDB (siehe [[project_installationsanleitung]]) UND WireGuard produktiv laufen.

- Adressschema: Server = `10.13.13.1/24`, Clients fortlaufend ab `10.13.13.2`. Port `51820/UDP` am Router (UPC/Magenta Fiber Box — nur klassische Portweiterleitung, kein eigenes VPN-Menü nötig) auf die Server-IP weitergeleitet. Bestehender no-ip-DDNS-Hostname wiederverwendet.
- Vollständige Schritt-für-Schritt-Anleitung inkl. "weiteren Client hinzufügen" steht in `docs/installation.md` Anhang C.
- **Zwei Stolpersteine beim Ersteinsatz, für nächstes Mal:** Windows-Firewall blockt auf dem neuen virtuellen WireGuard-Adapter standardmäßig sowohl ICMP (Ping) als auch TCP/80 (Apache) — beides braucht eine explizite `netsh advfirewall`-Freigabe auf dem Server, sonst Timeout trotz technisch funktionierendem Tunnel (sent/received zählt in der WireGuard-App schon hoch).
- Jeder weitere PC (z.B. Barbaras Büro-PC) braucht ein **eigenes** Schlüsselpaar + eigene `10.13.13.X`-Adresse, nie geteilte Client-Daten (Begründung siehe Anhang C in der Anleitung).
**How to apply:** Bei jedem weiteren VPN-Client diese Memory + Anhang C konsultieren statt von Null zu recherchieren.

## Update 2026-07-16: Neuer Dev-PC im Aufbau

Jacky baut gerade einen neuen Homeoffice-Dev-PC auf (i9-11900K, 32GB RAM, RTX2080, Win11 Pro). Der alte/aktuelle Dev-PC ist **nicht** der Server-PC (10.13.13.1) — nur ein Client, der zusätzlich per WireGuard auf den Live-Server zugreift. Also kein Produktiv-Risiko beim Umzug, aber:

- Neuer PC braucht **eigenen** WireGuard-Client-Key + eigene `10.13.13.X`-Adresse (Anhang C in `docs/installation.md`), alte Client-Config wird nicht wiederverwendet/exportiert.
- Projekt muss auf dem neuen PC exakt wieder unter `D:\ERP\mealana` liegen (Git-Clone), sonst verliert Claude Code den Zugriff auf dieses ganze Memory (Ordnername `d--ERP` hängt am Pfad).
- `erp\config\encryption.php` (Kunden-AES-Key) + `database.php` + `erp\public\img\hersteller\` + `erp\storage\` sind gitignored und müssen manuell vom alten PC mitgenommen werden.
- XAMPP-Symlink `C:\xampp\htdocs\mealana → D:\ERP\mealana\erp\public` muss auf dem neuen PC neu angelegt werden.

**Why:** Jacky hat sich neue Hardware gekauft, wollte wissen was für den Umzug nötig ist.
**How to apply:** Wenn Jacky sich mit "neuer PC steht" meldet, diese Checkliste als Ausgangspunkt nehmen statt neu zu explorieren.

## Update 2026-07-17: Umzug auf neuen Dev-PC abgeschlossen

Alle Checklisten-Punkte oben verifiziert und erledigt: Projekt liegt unter `D:\ERP\mealana`, Symlink `C:\xampp\htdocs\mealana → …\erp\public` gesetzt, `config\database.php`/`encryption.php`/`public\img\hersteller\`/`storage\` mitgenommen, DB `mealana_erp` vorhanden mit Migrationen aktuell (bis 125), eigener WireGuard-Client (`wg-erp-Client`, 10.13.13.3/24) aktiv und verbunden.

**Einziger echter Stolperstein:** frische XAMPP-`php.ini` auf dem neuen PC hatte `extension=gd` standardmäßig auskommentiert — exakt die Bug-Klasse aus [[project_bilder_modul]] (Live-Vorfall 2026-07-11). Aktiviert + Apache neu gestartet, per HTTP verifiziert (korrekter 302-Redirect auf `login.php`).

**How to apply:** Bei jedem künftigen PC-Neuaufsatz `extension=gd` in `php.ini` als ersten Check mit einplanen, nicht erst wenn ein Bild-Upload fehlschlägt.

## Update 2026-07-17: DB-Mojibake beim Umzug (CP850) — behoben, 175 Zeilen

Beim DB-Dump/Restore für den PC-Umzug wurden Umlaute in 26 Spalten (Artikel, Kategorien, Lieferanten, Länder, Merkmale, Varianten-Werte, Berechtigungen, Auftragsnotizen, Lagerbewegungen ...) korrumpiert — korrektes UTF-8 wurde beim Transport durch die Windows-Konsole fälschlich als **Codepage 850** (deutsche/österreichische DOS-OEM-Codepage, NICHT 437 — das war ein erster Fehlversuch bei der Diagnose) gelesen und erneut als UTF-8 gespeichert. Erkennbar an "ä" → "├ñ", "ö" → "├Â" usw.

**Fix:** `iconv('UTF-8', 'CP850', $korrupterText)` liefert exakt die ursprünglichen UTF-8-Bytes zurück (reversibel, kein Datenverlust). Alle 175 Zeilen per Vorschau-Skript (0 Anomalien) verifiziert, dann in einer Transaktion aktualisiert. Vorher Vollbackup nach `D:\ERP\backups\`.

**Why:** Windows-Konsole (cmd/PowerShell) nutzt beim Umleiten von mysqldump-Output ohne `chcp 65001` die System-OEM-Codepage (850 bei deutschem/österreichischem Windows) statt UTF-8.
**How to apply:** Bei künftigen DB-Dumps/Restores auf Windows IMMER `chcp 65001` vor mysqldump/mysql-Aufrufen setzen, oder Dump/Restore direkt über Bash-Tool (git-bash, byte-transparent) statt PowerShell/cmd laufen lassen. Falls trotzdem Mojibake auftaucht: erst CP850 probieren (nicht CP437), Testfall ist `iconv('UTF-8','CP850','ö')` sollte `├Â` ergeben.

## Update 2026-07-17: Umzug-Checkliste erweitert — Composer + Cronjobs auch vergessen

Nach dem GD-Fund kamen beim selben Umzug noch zwei weitere vergessene Schritte ans Licht (gleiches Muster: gitignored/außerhalb-Git-liegende Konfiguration, die beim reinen Git-Clone nicht mitkommt):
- **`composer install` nie ausgeführt** — `vendor/` fehlte komplett, dadurch Fehler bei Kunden-Ansicht + Mailer (beide hängen an Composer-Paketen: PHPMailer, Twig, Dompdf, QR-Code).
- **Windows Task Scheduler leer** — `cron/mahnwesen.php` (täglich 06:00, Zahlungserinnerungen/Storno) lief nirgends, weil Scheduled Tasks reine Windows-Konfiguration sind, die kein Git-Clone/keine Datei-Kopie automatisch mitnimmt. Task `MeaLana Mahnwesen` jetzt neu angelegt (`C:\xampp\php\php.exe`, täglich 06:00). `bfr_nachsignierung.php` (alle 5 Min, RKSV) bewusst NICHT eingerichtet — laut Jacky durch die letzte BFR-Session hinfällig.

**Ausführlichere Encoding-Nachlese:** Der erste CP850-Scan (Marker `E294`/├) fand nur einen Teil der Korruption — ein zweites Korruptionsmuster (`ÔÇ`-Präfix, betrifft Gedankenstrich/Anführungszeichen/€, alles was im Original mit E2 80 beginnt statt C3) blieb zunächst unentdeckt und tauchte erst auf, als Jacky manuell eine Auftragsposition mit "—" im Namen fand. Zusätzlich hatte `kunden`/`kunden_adressen` (verschlüsselte BLOB-Spalten) eine GANZ ANDERE Korruption als die Text-Spalten — nicht CP850-Mojibake, sondern schlicht kaputte Binärdaten (Auth-Tag-Fehler bei AES-GCM), behoben durch Zurückspielen der `_enc`-Spalten aus einem sauberen Dump vom 06.07. (`Z:\ERP_dumps\full_dump_20260706_171204.sql`).
**Lehre:** Ein einzelner Byte-Marker-Scan reicht bei Mojibake nicht — je nachdem welche Unicode-Blöcke betroffen sind (Latin-1 Supplement vs. General Punctuation), sehen die korrumpierten Bytes komplett anders aus. Für vollständige Abdeckung mehrere bekannte Sonderzeichen (ä ö ü ß — – ' " € ° ² à é è) einzeln durchrechnen und alle resultierenden Muster gleichzeitig scannen.

**Gefährlicher eigener Fehler bei der Untersuchung:** Beim testweisen Import eines alten Dumps (`Z:\ERP_dumps\...sql`) in eine isolierte Test-DB übersehen, dass die Datei ein fest eingebautes `USE \`mealana_erp\`;` enthielt — dadurch wurde kurzzeitig die LIVE-Datenbank mit dem 11 Tage alten Dump überschrieben (Migration 125→111, Datenverlust wäre real gewesen). Sofort per vorher gezogenem Backup rückgängig gemacht, kein bleibender Schaden, aber reiner Zufall dass ein Backup existierte.
**How to apply:** Vor JEDEM testweisen Import einer fremden `.sql`-Datei IMMER zuerst `grep -a "^USE\|^CREATE DATABASE" datei.sql` prüfen. Falls Treffer: nur einzelne Tabellen per `awk '/DROP TABLE IF EXISTS \`tabelle\`/,/UNLOCK TABLES/'` extrahieren statt die ganze Datei zu pipen. Eigene mit `mysqldump -uroot dbname > datei.sql` (ohne `--databases`) erzeugte Dumps haben dieses Problem nicht (kein USE-Statement), fremde/vollständige Dumps (mit `--all-databases` oder `--databases`) fast immer.
**Weitere Lehre:** Terminal-/Tool-Output von `mysql -e` ist beim Anzeigen von Umlauten/Sonderzeichen nicht vertrauenswürdig (hat mich zweimal getäuscht — korrupter Text wurde lesbar dargestellt). Bei Verdacht auf Encoding-Probleme immer `HEX(spalte)` zur Verifikation nutzen, nie der Bildschirmausgabe trauen.
