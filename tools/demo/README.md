# Demo-Installation (Präsentationen, später Webspace)

Eigene Installation mit erfundenen Daten der **Garnstube Muster GmbH** (Wolle + Stoffe).
Eigener Ordner, eigene Datenbank, eigene Schlüssel – nie die echte Datenbank.

- Code: `src/core/Demo.php` (Schalter), `scripts/demo/` (Generator, Demo-Lizenz `demo.lic`)
- Schalter: Datei `erp/config/demo.php` mit `'aktiv' => true` (nicht im Git, nie in ein Kunden-Paket)
- Im Demo-Modus: keine Mails, keine Shop-/BFR-/Belegseiten-Verbindung, gesperrt sind Benutzer
  speichern, Updates, Einrichtung, Lizenz einspielen, Importe, Einstellungen speichern.
  Gelbes Banner, Login „demo / demo“ vorausgefüllt, eigenes Sitzungs-Cookie `NAHTLOSDEMO`.

## Lokal (dieser Rechner)

`http://localhost/nahtlos-demo/` → `D:\ERP\nahtlos-demo\erp\public` (Junction), Datenbank `nahtlos_demo`.

Code abgleichen + Daten neu erzeugen:

    powershell -ExecutionPolicy Bypass -File D:\ERP\mealana\tools\demo\lokal_aktualisieren.ps1
    ... -NurCode      (nur Code, Daten bleiben)

### Erste Einrichtung (schon erledigt am 2026-11-21)
1. Ordner `D:\ERP\nahtlos-demo\erp\config` mit `database.php` (dbname `nahtlos_demo`),
   `encryption.php` (eigene Schlüssel) und `demo.php`:

       <?php
       return ['aktiv' => true, 'login' => 'demo', 'passwort' => 'demo'];

2. `CREATE DATABASE nahtlos_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;`
3. `cmd /c mklink /J C:\xampp\htdocs\nahtlos-demo D:\ERP\nahtlos-demo\erp\public`
4. Skript oben ausführen.

## Generator

    php scripts/demo/demo_erzeugen.php

Löscht alle Tabellen, spielt `baseline_schema.sql` + Migrationen ein, leert Upload-/Belegordner und
füllt alles neu (Dauer ca. 1 Minute). Bricht ab, wenn `config/demo.php` fehlt. Datum immer relativ zu heute, fester
Zufalls-Startwert (jeden Tag dieselbe Geschichte).

**Kein Cronjob nötig** (`Demo::frischHalten`, aus `config/bootstrap.php`): Der erste Seitenaufruf eines
neuen Tages startet den Neuaufbau im Hintergrund (`php_cli` aus `config/demo.php`) und zeigt ca. 40 s
„Demo wird vorbereitet“; danach holt jeder Aufruf höchstens alle 10 Minuten die Bestellungen und
Kassenbons seit dem letzten Stand nach (`storage/demo_stand.json`, `demo_cache.json`). Scheitert ein
Neuaufbau, frühestens nach 15 Minuten erneut (bis dahin alte Daten). Manuell: `demo/demo_cron.php?k=…`.

## Webspace nahtlos.indranet.at (World4You)

    python tools/demo/webspace_aktualisieren.py              Code hochladen
    python tools/demo/webspace_aktualisieren.py --erzeugen   … und Daten sofort neu erzeugen

PHP darf dort nur in `web/` lesen (open_basedir) → alles liegt in `web/nahtlos/`, `erp/` per .htaccess
gesperrt, `demo` → `erp/public`. CLI-PHP: `/opt/alt/php85/usr/bin/php` (Standard-CLI ist 7.2).

**Neue Seite oder Funktion im ERP → hier passende Demo-Daten ergänzen** (und Teaser-Webseite).

## Demo-Lizenz neu ausstellen (z.B. neues Modul)

    php tools/lizenz/ausstellen.php --nr=L-DEMO-0001 --firma="Garnstube Muster GmbH" --uid=ATU12345678 ^
        --module=grundmodul,kasse:2,shop:3,gutscheine,plc,lagerplaetze,partner,haendler,dropshipping,buchhaltung,konfigurator,kundenanzeige,paperless ^
        --wartung=2035-12-31 --datei=erp/scripts/demo/demo.lic
