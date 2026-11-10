# MeaLana ERP — Installationsanleitung

**Zielgruppe:** Wer eine neue Instanz aufsetzt — der Server-Admin bei einer Neuinstallation, aber auch ein Tester/Interessent, der die Software einmal ausprobieren will.
**Stand:** 2026-11-04 (Version 1.0)
**Umgebung:** Windows + XAMPP (Apache, MariaDB, PHP). Auf Linux läuft dasselbe Prinzip mit Apache/Nginx + PHP-FPM statt XAMPP — die Schritte 4–14 sind identisch.

---

## Vorab

Es gibt keinen grafischen Installer — die Einrichtung ist ein manueller, aber gut abgrenzbarer Ablauf (ca. 30–45 Minuten). Danach führt der Einrichtungs-Assistent im Browser durch die Firmendaten (Schritt 11), spätere Versionen kommen über ··· → Updates (Schritt 13a).

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

`baseline_schema.sql` enthält den kompletten Stand von Version 1.0: alle Tabellen **plus** die universellen System-Stammdaten — Rollen/Berechtigungen, Artikeltypen, Artikelgruppen, Einheiten, Steuerklassen + Konten, Kontenplan, Länder, Zahlungsbedingungen, Versandklassen, Kundengruppen (Endkunden = Standard, Händler), Nummernkreise (A, R, GS, LS, MR, AB, PL …) sowie die fixen Seed-Datensätze Jarvis-Systembenutzer, Diverses-Artikel `99-9999`, Fachmiete, Laufkunde und ein neutraler Kanal "Hauptkanal". **Keine** Geschäfts-/Testdaten wie Artikel, Kunden, Kategorien oder Hersteller.

1. Baseline importieren (Datenbank aus Schritt 4 muss mit `utf8mb4_general_ci` angelegt sein):
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root mealana_erp < "D:\ERP\mealana\erp\database\baseline_schema.sql"
   ```
2. Kontrolle:
   ```powershell
   cd D:\ERP\mealana\erp\database
   C:\xampp\php\php.exe migrate.php status
   ```
   sollte `0 Migration(en) angewendet, 0 offen` zeigen — alle Migrationen bis 222 stecken in der Baseline, der Ordner `migrations\` ist bei 1.0 leer.

**Spätere Versionen** bringen neue Migrationen (ab `223_...sql`) mit. Die spielt das Update unter ··· → Updates automatisch ein; per Kommandozeile geht es mit `php migrate.php` (wendet nur die noch fehlenden Dateien an).

⚠️ **Nicht** `schema_current.sql` verwenden — das ist ein Snapshot der echten MeaLana-Produktivdaten. `baseline_schema.sql` enthält nur universelle System-Stammdaten.

*Geschichte:* Baseline-Schnitte 2026-07-09 (Stand 123) und 2026-11-04 (Version 1.0, Stand 222). Beim 1.0-Schnitt wurde die Baseline aus einer frischen Installation (alte Baseline + alle Migrationen) erzeugt, nicht aus einer Arbeits-Datenbank, damit keine Firmendaten hineinrutschen.

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

## 11. Einrichtungs-Assistent

Mit dem in Schritt 8 angelegten Superadmin einloggen → der **Einrichtungs-Assistent** startet automatisch (Firma, eigenes Passwort, Lizenz, Steuer, Lager, danach je Lizenz Benutzer/Mail/Kasse/Shop). Ideal gemeinsam mit dem Kunden durchgehen. Danach unter **Einstellungen → Firma** noch IBAN/BIC ergänzen — **bevor** die erste Rechnung erstellt wird (Handbuch, Kapitel 09).

---

## 12. Cronjobs einrichten (Windows Task Scheduler)

| Skript | Intervall | Zweck |
|---|---|---|
| `erp\cron\bfr_nachsignierung.php` | alle 5 Minuten | RKSV-Nachsignierung offener Kassenbelege (nur relevant wenn Kasse+RKSV aktiv genutzt wird) |
| `erp\cron\mahnwesen.php` | täglich, 06:00 Uhr | Zahlungserinnerungen (14 Tage) + Auto-Stornierung (30 Tage) |
| `erp\cron\fachmiete.php` | täglich, 06:00 Uhr | Fachmiete-Rechnungen für den Folgemonat (ab 2 Tage vor Monatsende), verpasste Monate werden nachgeholt |
| `erp\cron\z_bon_auto.php` | täglich, 00:05 Uhr | Fehlende Z-Bons (Tagesabschluss) **aller Kassen** der Reihe nach nachholen — nur einmal am Server einrichten, nicht pro Kasse |

Einrichtung z.B. per `schtasks` oder GUI (Aufgabenplanung), Programm: `C:\xampp\php\php.exe`, Argument: voller Pfad zum jeweiligen Skript. Am Server "Unabhängig von der Benutzeranmeldung ausführen" wählen (braucht Admin-Rechte) und "Aufgabe so schnell wie möglich nach einem verpassten Start ausführen" anhaken — sonst laufen die Skripte nur, solange jemand angemeldet ist.

---

## 13. Kasse / RKSV (nur falls die Kassen-Funktion genutzt wird)

Pro Kasse in **Einstellungen → Kassen** die BFR-Gerät-URL (`bfr_url`) und die Kassen-ID hinterlegen. Ohne gesetzte `bfr_url` läuft die Kasse ganz normal, nur ohne Signierung — das ist also optional und bricht nichts, wenn (noch) keine RKSV-Hardware angeschlossen ist.

Die eigentliche FinanzOnline-Anmeldung und der Startbeleg laufen **nicht** über diese Software, sondern über das BFR-eigene Admin-Tool (siehe `import\BFR_Installationsanleitung.pdf`).

**Netzwerkkassen (ERP-Server und BFR auf unterschiedlichen Rechnern):** Läuft die ERP-Software auf einem anderen Rechner als BFR (Server/Client-Architektur, wie bei uns Standard), muss im BFR-Tool unter **Service** das Häkchen **"Zugriff für Netzwerkkassen erlauben"** gesetzt sein — sonst nimmt BFR nur Verbindungen von `127.0.0.1` an, auch wenn Firewall/Port offen sind. `bfr_url` muss dann die tatsächliche Netzwerk-IP des BFR-Rechners sein, nicht `127.0.0.1` (das würde sonst auf den Server selbst zeigen). Läuft BFR direkt auf demselben Rechner wie die ERP-Software (z.B. Offline-Messe-Kasse), bleibt `127.0.0.1` korrekt — dafür sorgt die Software inzwischen sogar selbst (siehe Kassen-Verwaltung → automatische bfr_url-Selbstheilung).

---

## 13a. Updates

Ab 1.0 kommen Änderungen nur noch als signiertes Paket (`nahtlos-x.y.z.zip`) über **··· → 🔄 Updates** (Handbuch Kapitel 09). Voraussetzungen, bei XAMPP schon erfüllt:
- `mysqldump` + `mysql` im MariaDB-Ordner (`C:\xampp\mysql\bin`) — ohne Datenbank-Sicherung verweigert das ERP das Update
- `upload_max_filesize` / `post_max_size` mindestens 40M (XAMPP-Standard)
- Apache darf in `erp\` schreiben (Programmdateien werden ersetzt)

`git pull` oder Kopieren von Hand bitte nicht mehr verwenden — sonst fehlen Sicherung, Prüfung und Verlauf.

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
- Ping geht, aber der Browser meldet „Zeitüberschreitung“ (2026-11-10, nach dem Hinzufügen eines Peers am Server) → Windows hat den Server-Tunnel-Adapter als **„Öffentliches Netzwerk“** eingestuft, Apache ist aber nur für private Netze freigegeben. Prüfen und beheben (Admin-PowerShell am Server):
  ```powershell
  Get-NetConnectionProfile | Select InterfaceAlias, NetworkCategory
  Set-NetConnectionProfile -InterfaceAlias "wg-erp-server" -NetworkCategory Private
  ```
- „Allgemeiner Fehler“ beim Ping am Client → Problem am Client selbst (Tunnel-Adapter nicht fertig eingerichtet, z. B. wegen eines halb installierten Windows-Updates) → Neustart, Tunnel wieder aktivieren.
- Router-Modelle ohne eigenes "VPN"-Menü sind kein Problem — WireGuard läuft komplett als Software auf dem Server, der Router muss nur einen einzelnen UDP-Port stur weiterleiten (klassische Portweiterleitung reicht).

## Anhang D — Daten von einem ERP in ein anderes übertragen

Mit `erp/scripts/datentransfer.php` lassen sich Stammdaten von einem NAHTLOS-ERP in ein anderes übernehmen, z. B. die Artikel eines Shops aus einer Test-Installation in die Live-Installation.

**Was übertragen werden kann** (frei kombinierbar über `--teile=`): `kategorien` (ganzer Baum oder ein Ast), `hersteller`, `artikel` (mit Codes, Preisen, Bildern, Kategorien, Varianten/Konfigurator, Merkmalen, Lieferanten-Zuordnung, Shop-Zuweisung), `slider` und `menue` eines Kanals.
**Nicht übertragen werden:** Lagerbestand, Aufträge, Kunden, Zugangsdaten, WooCommerce-IDs (das Ziel legt im Shop alles neu an).

```powershell
# im Quell-ERP: Paket erstellen
php scripts/datentransfer.php export --teile=kategorien,artikel,slider,menue --shop=IND --datei=C:\transfer\indra.tar

# im Ziel-ERP: zuerst Datenbank sichern, dann Probelauf (ändert nichts), dann echt
php scripts/datentransfer.php import --datei=C:\transfer\indra.tar --probelauf
php scripts/datentransfer.php import --datei=C:\transfer\indra.tar
```

- Der Kanal muss im Ziel **vorher** angelegt sein (gleicher Slug oder gleiches Kürzel), sonst werden Shop-Zuweisungen, Slider und Menü ausgelassen.
- Vorhandenes wird nie überschrieben: Kategorien, Hersteller und Achsen werden über den Namen wiederverwendet, Artikel mit gleicher Artikelnummer bleiben unverändert. Ein zweiter Lauf legt nichts doppelt an.
- Steuerklassen und Artikeltypen müssen im Ziel existieren (sind in jeder Installation vorhanden). Fehlende Artikelgruppen, Versandklassen und Einheiten werden angelegt; fehlende Lieferanten/Kundengruppen werden mit Hinweis ausgelassen.
- Alles läuft in einer Transaktion: bei einem Fehler bleibt das Ziel unverändert.
