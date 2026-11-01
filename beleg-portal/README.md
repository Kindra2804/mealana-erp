# Belegseite (Paperless) — beleg.&lt;domain&gt;

Kleine, eigenständige Seite für digitale Kassenbelege: An der Kasse erscheint ein QR-Code, der Kunde
scannt ihn und lädt den Beleg herunter. Kein WordPress/WooCommerce nötig. Gespeichert wird nur der
Beleg selbst (PDF, Bon-Nr., Betrag, Datum, Firma, Logo), keine Kundendaten.

Rechtsgrundlage: seit 1. Oktober 2026 darf der Beleg digital übergeben werden, u.a. „mit einem
Endgerät vor Ort ausgelesen … etwa in Form eines QR-Codes“ (BMF-Pressemeldung 29.09.2026).

## Einrichten (einmalig, z.B. beleg.mealana.at)
1. Beim Hoster die Subdomain `beleg.<domain>` anlegen (mit SSL/HTTPS)
2. Inhalt dieses Ordners per FTP in das Verzeichnis der Subdomain laden
3. `config.example.php` nach `config.php` kopieren und ausfüllen:
   - `api_schluessel`: langer Zufallswert, **derselbe** Wert im ERP (Einstellungen → System → Paperless)
   - Datenbank: SQLite (nichts einzurichten) oder die WordPress-Datenbank mit Präfix `nahtlos_`
4. Im ERP „Verbindung testen“: zeigt Datenbank und ob der Ordner `daten/` beschreibbar ist

## Aufbewahrung
Nicht abgerufene Belege werden nach 24 h gelöscht, abgerufene nach 30 Tagen (einstellbar in config.php).
Das Aufräumen läuft bei jedem Aufruf mit, ein Cron am Webspace ist nicht nötig.

## Dateien
- `index.php`: Abrufseite (`?t=…`) und PDF-Download
- `api.php`: Schnittstelle fürs ERP (Header `X-Nahtlos-Schluessel`): test, upload, status
- `lib.php`, `config.php`: per `.htaccess` gesperrt; `daten/`: PDFs + SQLite-Datei, gesperrt
