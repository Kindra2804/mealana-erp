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

**Neue Seite oder Funktion im ERP → hier passende Demo-Daten ergänzen** (und Teaser-Webseite).

## Demo-Lizenz neu ausstellen (z.B. neues Modul)

    php tools/lizenz/ausstellen.php --nr=L-DEMO-0001 --firma="Garnstube Muster GmbH" --uid=ATU12345678 ^
        --module=grundmodul,kasse:2,shop:3,gutscheine,plc,lagerplaetze,partner,haendler,dropshipping,buchhaltung,konfigurator,kundenanzeige,paperless ^
        --wartung=2035-12-31 --datei=erp/scripts/demo/demo.lic
