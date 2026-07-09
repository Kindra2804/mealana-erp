# MeaLana ERP — Installationsanleitung

**Zielgruppe:** Wer eine neue Instanz aufsetzt — der Server-Admin bei einer Neuinstallation, aber auch ein Tester/Interessent, der die Software einmal ausprobieren will.
**Stand:** 2026-07-09
**Umgebung:** Windows + XAMPP (Apache, MariaDB, PHP). Auf Linux läuft dasselbe Prinzip mit Apache/Nginx + PHP-FPM statt XAMPP — die Schritte 4–14 sind identisch.

---

## Vorab: Was diese Anleitung NICHT abdeckt

Es gibt (Stand heute) **keinen Installer und kein Migrations-Skript** — die Einrichtung ist ein manueller, aber gut abgrenzbarer Ablauf. Wer die Software öfter weitergeben will, sollte diesen Prozess irgendwann automatisieren (ein `migrate.php`, das alle SQL-Dateien der Reihe nach einspielt, wäre der naheliegende erste Schritt). Für jetzt: der manuelle Weg unten funktioniert zuverlässig, dauert aber ca. 30–45 Minuten.

---

## Voraussetzungen

| Komponente | Version | Hinweis |
|---|---|---|
| PHP | ≥ 8.1 | siehe Extension-Liste unten |
| MariaDB / MySQL | aktuell | XAMPP bringt MariaDB mit |
| Composer | aktuell | für PHP-Pakete (Twig, Dompdf, ...) |
| Webserver | Apache (über XAMPP) | siehe Abschnitt 3 zum URL-Pfad |

**Benötigte PHP-Erweiterungen** (in `php.ini` aktivieren, XAMPP hat die meisten schon aktiv):
`pdo_mysql`, `gd`, `curl`, `simplexml`, `openssl`, `mbstring`, `fileinfo`, `json`

---

## 1. XAMPP installieren

1. XAMPP für Windows herunterladen und installieren (Apache + MySQL/MariaDB + PHP ≥ 8.1 auswählen).
2. In `php.ini` (über das XAMPP-Control-Panel → Apache → Config) prüfen, dass obige Extensions nicht auskommentiert sind (`;extension=...` → `extension=...`).
3. Apache + MySQL im XAMPP-Control-Panel starten.

---

## 2. Projekt-Dateien auf den Server bringen

Per Git-Clone oder ZIP-Übertragung an einen beliebigen Ort, z.B. `D:\ERP\mealana`. Das Projekt braucht **nicht** in `htdocs` zu liegen — die Web-Anbindung erfolgt in Schritt 3 separat.

---

## 3. URL-Pfad einrichten (wichtiger Punkt!)

Im gesamten Code sind interne Links fix auf **`/mealana/`** codiert (Navigation, CSS, JS, TinyMCE, Bild-Pfade — nicht konfigurierbar). Es gibt keine `.htaccess` und kein Rewriting nötig, aber der Pfad `/mealana/` muss exakt auf den Ordner `erp\public` zeigen.

**Empfohlener Weg (Windows, ohne Admin-Rechte-Ärger):** Junction anlegen —

```powershell
cmd /c mklink /J "C:\xampp\htdocs\mealana" "D:\ERP\mealana\erp\public"
```

**Alternative:** Apache-Alias in `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apacheconf
Alias /mealana "D:/ERP/mealana/erp/public"
<Directory "D:/ERP/mealana/erp/public">
    AllowOverride All
    Require all granted
</Directory>
```

Danach Apache neu starten. Test (nach Schritt 4–7): `http://localhost/mealana/` sollte den Login-Screen zeigen.

---

## 4. Datenbank anlegen

In phpMyAdmin oder per Kommandozeile:

```sql
CREATE DATABASE mealana_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

---

## 5. Composer-Pakete installieren

```powershell
cd D:\ERP\mealana\erp
composer install
```

Installiert: Twig (Templates), Dompdf (PDF-Erzeugung), picqer/php-barcode-generator, PHPMailer, endroid/qr-code (RKSV-QR-Code). `vendor/` ist nicht im Git — das muss auf jedem Server neu laufen.

---

## 6. Konfigurationsdateien anlegen

Aus Sicherheitsgründen sind diese zwei Dateien **nicht** im Git-Repo (enthalten Passwörter/Schlüssel) und müssen manuell angelegt werden.

**`erp\config\database.php`:**
```php
<?php
return [
    'host'     => 'localhost',
    'dbname'   => 'mealana_erp',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4'
];
```

**`erp\config\encryption.php`** — braucht zwei zufällige 32-Byte-Schlüssel (für die AES-256-GCM-Verschlüsselung der Kundendaten). Erzeugen mit:

```powershell
C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32));"
```

Zweimal ausführen (einmal für `master_key`, einmal für `search_key`) und einsetzen:

```php
<?php
return [
    'master_key' => 'HIER_ERSTEN_GENERIERTEN_KEY_EINFUEGEN',
    'search_key' => 'HIER_ZWEITEN_GENERIERTEN_KEY_EINFUEGEN',
];
```

⚠️ Diese Datei niemals per Mail/Chat verschicken oder ins Git aufnehmen — wer sie hat, kann die verschlüsselten Kundendaten lesen.

---

## 7. Datenbank-Schema einspielen

Es gibt jetzt ein Migrations-Tool: `erp\database\migrate.php`. Für eine **neue** Installation reicht das aber allein nicht — Hintergrund: Die Migrationsdateien beginnen bei `004_...sql` (001–003 existieren nicht mehr/nie versioniert), setzen also schon einige Basis-Tabellen (u.a. `artikel`) voraus, die in keiner Migrationsdatei erzeugt werden. Ein reines Abspielen von `004` bis `104` auf einer wirklich leeren Datenbank bricht deshalb mitten drin ab (getestet — Fehler ab Migration `006`, Foreign Key auf eine nie erzeugte Tabelle).

**Deshalb der zuverlässige Weg — Baseline-Dump + Migrations-Tracker:**

1. Baseline importieren (enthält alle 94 Tabellen im aktuellen Endstand, **plus** die universellen System-Stammdaten, die keiner Installation fehlen dürfen — Rollen/Berechtigungen, Artikeltypen, Einheiten, Steuerklassen, Länder, Zahlungsbedingungen, Standard-Versandklassen sowie die fixen Seed-Datensätze Jarvis-Systembenutzer, Diverses-Artikel `99-9999`, Laufkunde und ein neutraler Standard-Shop-Kanal "Hauptkanal". **Keine** Geschäfts-/Testdaten wie echte Artikel, Kunden, Kategorien oder Hersteller):
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root mealana_erp < "D:\ERP\mealana\erp\database\baseline_schema.sql"
   ```
2. Den Migrations-Tracker "scharf schalten" (trägt alle vorhandenen Migrationsdateien als *bereits enthalten* ein, ohne sie nochmal auszuführen — die Baseline hat ihren Inhalt ja schon):
   ```powershell
   cd D:\ERP\mealana\erp\database
   C:\xampp\php\php.exe migrate.php bootstrap
   ```
   (Mit `j` bestätigen.)
3. Kontrolle:
   ```powershell
   C:\xampp\php\php.exe migrate.php status
   ```
   sollte `103 Migration(en) angewendet, 0 offen` zeigen.

**Ab jetzt, für jede künftige neue Migration (124, 125, ...):** einfach `php migrate.php` ohne Argumente aufrufen — es wendet automatisch nur die noch fehlenden Dateien an, egal ob auf Dev oder Live. Kein manuelles Zählen mehr nötig.

⚠️ **Nicht** `schema_current.sql` verwenden — das ist ein Snapshot der echten MeaLana-Produktivdaten (Kunden, Firmenname, Admin-Zugang). `baseline_schema.sql` enthält nur universelle System-Stammdaten, keine Geschäftsdaten, und ist dafür extra angelegt.

**Update 2026-07-09:** Frischer Baseline-Schnitt (Stand nach Migration 123) samt Aufräumen — reine Seed-Migrationen, deren Effekt jetzt Teil der Baseline ist, wurden gelöscht: `005` (Rollen/Berechtigungen-Erstseed inkl. des alten fest verdrahteten Admin-Accounts, siehe Anhang B), `011`, `013`, `032`, `046`, `064`, `078`, `097`, `105`, `109`, `110`. Grund: Der alte Baseline+Bootstrap-Weg importierte zwar die Tabellenstruktur, aber `bootstrap` führt Migrationen 004+ nie wirklich aus — jede reine Stammdaten-Migration in diesem Bereich (Rollen, Artikeltypen, Einheiten, Steuerklassen, Länder, ...) wurde bei einer echten Neuinstallation stillschweigend übersprungen. Die neue Baseline enthält diese Stammdaten jetzt direkt.

*Für Technik-Interessierte:* `migrate.php` unterstützt auch `php migrate.php status` (zeigt offene Migrationen ohne etwas zu tun) — nützlich um vor einem Live-Deploy kurz zu prüfen, was sich seit dem letzten Mal geändert hat.

---

## 8. Erste Benutzer anlegen

**a) System-Benutzer "Jarvis"** — kommt automatisch mit der Baseline (Teil von Schritt 7). Keine manuelle Aktion nötig. Wird von Cronjobs und automatischen Buchungen für Log-Einträge gebraucht, hat ein absichtlich ungültiges Passwort (`'!'`) und kann sich nie einloggen. Wichtig: es gibt bewusst **keine feste ID** dafür — überall im Code wird Jarvis per `username = 'system'` nachgeschlagen, damit keine bestimmte `benutzer.id` bei der Installation erzwungen werden muss.

**b) Erster Admin-Benutzer** — interaktives Skript, kein manuelles Hash-Basteln mehr nötig:
```powershell
cd C:\ERP\mealana\erp\database
C:\xampp\php\php.exe create_admin.php
```
Fragt nacheinander Benutzername, Anzeigename und Passwort ab und legt den Benutzer inkl. Rolle `superadmin` an.

⚠️ Bewusst **kein** fix eingebauter Admin-Account mit gleichbleibendem Passwort über alle Installationen hinweg — das wäre ein geteilter Generalschlüssel für jede Installation gleichzeitig. Jede Installation bekommt ihr eigenes, frei gewähltes Admin-Passwort.
Danach ggf. die passende Rolle zuweisen (siehe `benutzer_rollen`-Tabelle, seedet in Migration 005 die Rollen `superadmin`/`admin`/`mitarbeiter`).

---

## 9. TinyMCE (Editor für Artikelbeschreibungen)

Ist bereits fertig im Repo enthalten unter `erp\public\js\tinymce\` — kein separater Download nötig. Nur prüfen, dass die Datei `erp\public\js\tinymce\tinymce.min.js` nach der Übertragung tatsächlich vorhanden ist (manche ZIP-Tools lassen große verschachtelte Ordner mal aus).

---

## 10. Schreibrechte prüfen

Der Webserver-Prozess braucht Schreibzugriff auf:

| Pfad | Zweck |
|---|---|
| `erp\public\uploads\artikel\` | Artikelbilder |
| `erp\public\img\hersteller\` | Hersteller-Logos |
| `erp\public\img\logos\` | Firmen-/Shop-Logo |
| `erp\storage\dokumente\` | Rechnungen/Lieferscheine als PDF |
| `erp\storage\picklisten\` | Kommissionierlisten als PDF |
| `erp\storage\bons\` | Kassenbons als PDF |

Unter Windows/XAMPP ist das i.d.R. automatisch der Fall (kein IIS-Berechtigungsmodell). Die Ordner werden bei Bedarf automatisch angelegt, solange der übergeordnete Ordner beschreibbar ist.

---

## 11. Firmenstammdaten erfassen

Einloggen → **Einstellungen → Firma**. Firmenname, Adresse, UID-Nummer, IBAN/BIC ausfüllen — **bevor** die erste Rechnung erstellt wird (steht so auch im Handbuch, Kapitel 09).

---

## 12. Cronjobs einrichten (Windows Task Scheduler)

| Skript | Intervall | Zweck |
|---|---|---|
| `erp\cron\bfr_nachsignierung.php` | alle 5 Minuten | RKSV-Nachsignierung offener Kassenbelege (nur relevant wenn Kasse+RKSV aktiv genutzt wird) |
| `erp\cron\mahnwesen.php` | täglich, 06:00 Uhr | Zahlungserinnerungen (14 Tage) + Auto-Stornierung (30 Tage) |

Einrichtung z.B. per `schtasks` oder GUI (Aufgabenplanung), Programm: `C:\xampp\php\php.exe`, Argument: voller Pfad zum jeweiligen Skript.

---

## 13. Kasse / RKSV (nur falls die Kassen-Funktion genutzt wird)

Pro Kasse in **Einstellungen → Kassen** die BFR-Gerät-URL (`bfr_url`) und die Kassen-ID hinterlegen. Ohne gesetzte `bfr_url` läuft die Kasse ganz normal, nur ohne Signierung — das ist also optional und bricht nichts, wenn (noch) keine RKSV-Hardware angeschlossen ist.

Die eigentliche FinanzOnline-Anmeldung und der Startbeleg laufen **nicht** über diese Software, sondern über das BFR-eigene Admin-Tool (siehe `import\BFR_Installationsanleitung.pdf`).

**Netzwerkkassen (ERP-Server und BFR auf unterschiedlichen Rechnern):** Läuft die ERP-Software auf einem anderen Rechner als BFR (Server/Client-Architektur, wie bei uns Standard), muss im BFR-Tool unter **Service** das Häkchen **"Zugriff für Netzwerkkassen erlauben"** gesetzt sein — sonst nimmt BFR nur Verbindungen von `127.0.0.1` an, auch wenn Firewall/Port offen sind. `bfr_url` muss dann die tatsächliche Netzwerk-IP des BFR-Rechners sein, nicht `127.0.0.1` (das würde sonst auf den Server selbst zeigen). Läuft BFR direkt auf demselben Rechner wie die ERP-Software (z.B. Offline-Messe-Kasse), bleibt `127.0.0.1` korrekt — dafür sorgt die Software inzwischen sogar selbst (siehe Kassen-Verwaltung → automatische bfr_url-Selbstheilung).

---

## 14. Sicherheits-Checkliste vor dem ersten "scharfen" Betrieb

- [ ] `erp\public\test.php` löschen, falls vorhanden (Debug-Datei, nicht für den Echtbetrieb gedacht)
- [ ] Standard-Admin-Passwort geändert
- [ ] `erp\config\database.php` und `erp\config\encryption.php` sind **nicht** öffentlich abrufbar (liegen außerhalb von `erp\public`, also unkritisch — nur bei Kopieraktionen aufpassen)
- [ ] Backup-Strategie eingerichtet (DB-Dump + `storage/`-Ordner)
- [ ] Bei Zugriff von außerhalb des lokalen Netzes: VPN statt offenem Port-Forwarding verwenden

---

## Anhang A — Testphase vor echtem Go-Live

Wenn Aufträge/Rechnungen erstmal nur zum Testen angelegt werden (bevor der Echtbetrieb beginnt): vor dem eigentlichen Start alle Test-Datensätze löschen (`auftraege`, `rechnungen`, zugehörige Positionen/Zahlungen/Lagerbewegungen) und die Nummernkreise zurücksetzen:

```sql
UPDATE dokument_nummern SET letzt_nr = 0 WHERE jahr = 2026;
```

Das ist unproblematisch, solange in der Testphase keine Belege real versendet, signiert oder gemeldet wurden.

---

## Anhang B — Hinweise für Weitergabe an Dritte / Tester

Die Software ist noch aktiv in Entwicklung. Vor einer Weitergabe an einen fremden Betrieb bitte im Kopf behalten:

- **Keine Benutzer-/Rechteverwaltung:** Jeder eingeloggte Benutzer hat aktuell vollen Zugriff auf alles — eine granulare Rollenvergabe ist geplant, aber noch nicht gebaut.
- **Kein Lizenzsystem aktiv:** Die Software prüft aktuell keine Lizenz-/Instanzgrenzen (auch wenn das Datenmodell dafür schon vorbereitet ist).
- **Fix auf "MeaLana" verdrahtet:** Der URL-Pfad `/mealana/` sowie einiges an Branding ist im Code hart hinterlegt, nicht pro Installation konfigurierbar. Für eine andere Firma müsste das noch generalisiert werden (kein Show-Stopper für eine reine Testinstallation, aber für eine "weiße" Weitergabe an andere Betriebe ein offener Punkt).
- **Multi-Shop/WooCommerce-Sync:** teilweise vorbereitet, aber noch nicht fertig.
- ~~Migration `005_seed_rollen_berechtigungen.sql` legt am Ende einen echten Admin-Account an~~ — **erledigt (2026-07-09):** Die Datei wurde beim Baseline-Neuschnitt gelöscht, ihr generischer Teil (Rollen/Berechtigungen) ist jetzt Teil von `baseline_schema.sql`, der fest verdrahtete Admin-Account (fixer bcrypt-Hash + private E-Mail) ist damit komplett weg statt nur "nie ausgeführt".
- **Empfehlung:** Testinstallationen bei Dritten klar als Testversion kommunizieren, nicht mit echten Kunden-/Zahlungsdaten produktiv laufen lassen, solange Rechteverwaltung und Lizenzsystem fehlen.

---

## Anhang C — VPN-Zugriff (WireGuard) für Remote-Zugriff ohne AnyDesk

Ersetzt Port-Forwarding direkt auf die Anwendung — nur ein einziger WireGuard-Port wird am Router freigegeben, nicht Port 80 direkt ins Internet.

**Einmalig am Server:**
1. WireGuard für Windows installieren (https://www.wireguard.com/install/).
2. "Leeren Tunnel hinzufügen" → generiert automatisch ein Schlüsselpaar. Ergänzen:
   ```
   [Interface]
   PrivateKey = <automatisch erzeugt>
   Address = 10.13.13.1/24
   ListenPort = 51820
   ```
3. Am Router: Portweiterleitung **UDP 51820** → feste lokale IP des Servers, Port 51820.
4. Windows-Firewall auf dem Server öffnen (sonst Timeout trotz funktionierendem Tunnel):
   ```powershell
   netsh advfirewall firewall add rule name="ICMP Ping erlauben" protocol=icmpv4:8,any dir=in action=allow
   netsh advfirewall firewall add rule name="Apache HTTP erlauben" protocol=TCP dir=in localport=80 action=allow
   ```

**Für jeden weiteren PC, der Zugriff bekommen soll (eigenes Schlüsselpaar + eigene Adresse — niemals dieselben Client-Daten auf zwei Geräten, siehe unten warum):**

1. Auf dem jeweiligen PC: WireGuard installieren, "Leeren Tunnel hinzufügen", ergänzen:
   ```
   [Interface]
   PrivateKey = <automatisch erzeugt>
   Address = 10.13.13.X/24
   
   [Peer]
   PublicKey = <Server-Public-Key>
   Endpoint = EUER-NOIP-HOSTNAME:51820
   AllowedIPs = 10.13.13.1/32
   PersistentKeepalive = 25
   ```
   `X` = nächste freie Nummer (zweiter PC: 2, dritter PC: 3, ...).
2. Am Server im Tunnel `wg-erp-server` einen weiteren Block ergänzen:
   ```
   [Peer]
   PublicKey = <Public-Key des neuen Clients>
   AllowedIPs = 10.13.13.X/32
   ```
3. Beide Tunnel aktivieren, dann `http://10.13.13.1/mealana/` im Browser des Client-PCs aufrufen.

**Warum eigene Schlüssel pro Gerät, nicht ein geteilter "Standard-Client":** WireGuard identifiziert Peers über ihren Public Key. Zwei Geräte mit demselben Key + derselben Adresse konkurrieren um dieselbe Route auf dem Server — die Verbindung wird für eines der beiden Geräte unzuverlässig, je nachdem wer zuletzt verbunden hat. Außerdem lässt sich einem einzelnen kompromittierten/verlorenen Gerät so nicht gezielt der Zugriff entziehen. Der **Tunnel-Name** in der App (z.B. "wg-erp-client") ist dagegen rein kosmetisch und darf überall gleich lauten.

**Typische Stolpersteine (beide beim Ersteinsatz aufgetreten):**
- Ping/Verbindung timeout trotz sichtbarem Datenverkehr in der WireGuard-App → Windows-Firewall blockt ICMP/TCP auf dem neuen virtuellen Adapter standardmäßig (siehe Firewall-Befehle oben).
- Router-Modelle ohne eigenes "VPN"-Menü sind kein Problem — WireGuard läuft komplett als Software auf dem Server, der Router muss nur einen einzelnen UDP-Port stur weiterleiten (klassische Portweiterleitung reicht).
