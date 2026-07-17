---
name: project-bilder-modul
description: "Bilder-Modul: FERTIG (2026-06-19) — Upload, GD-Resize, Hauptbild-Swap, Reihenfolge, Alt-Text, Multi-Shop-Sync-Tabelle"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

## Status: FERTIG (2026-06-19)

## Was gebaut wurde

### DB (Migration 045)
- `artikel_bilder` — id UNSIGNED, artikel_id UNSIGNED FK, dateiname, alt_text, position, erstellt_am
- `artikel_bilder_shops` — bild_id + shop_id → external_id VARCHAR, sync_status ENUM, synced_at, fehler_meldung
- UNIQUE KEY (bild_id, shop_id) — ein Bild kann in mehreren Shops unterschiedliche external_ids haben

### Backend
- `BilderRepository.php` — findByArtikelId, insert, updateAltText, delete, setzeHauptbild, verschiebePosition
  - verschiebePosition schützt Position 0 (Hauptbild): ↑ nur wenn $pos > 1
- `bild_upload.php` — PHP GD Resize (max 1920px, JPEG 85%), MIME-Check, mkdir recursive
- `bild_ajax.php` — Sammel-Handler: aktion=alt_text|position|hauptbild
- `bild_loeschen.php` — unlink + DB delete

### Frontend
- `detail.php` Tab "Bilder": Drop-Zone, Bild-Grid (.bild-karte), Anzahl, Status
- `bilder.js` — Event Delegation auf #bild-grid (kein per-Karte Binding!)
  - aktualisiereAlleKarten() baut Overlay + Steuerbereich nach jeder Aktion komplett neu → kein DOM-Stapeln
  - ↑↓ Buttons: Karte bei Index 1 hat kein ↑ (direkt unter Hauptbild), letzte hat kein ↓
  - ☆ Hauptbild → setzt Karte an Position 0, alle anderen bekommen ☆-Button zurück

### Entscheidungen
- ERP speichert nur Original (clean, kein Wasserzeichen)
- Wasserzeichen: wird beim Shop-Sync aufgedrückt (PHP GD on-the-fly), konfigurierbar pro Shop im Admin-Menü
- Thumbnails: keine im ERP — WooCommerce generiert eigene Thumbnail-Größen nach Import
- Keine Vater→Kind-Vererbung bei Bildern (jeder Artikel hat eigene Bilder)
- WooCommerce-Sync: Bilder per API hochladen → external_id (WC attachment_id) in artikel_bilder_shops speichern → inkrementeller Sync (nur neue/geänderte)

## 🟢 BUG behoben (2026-07-11): fehlende PHP-Erweiterung konnte JSON-Antwort zerstören

Ausgangspunkt war ein Live-Vorfall beim Hersteller-Logo-Upload (siehe [[project_status]]-Chatverlauf 2026-07-11): dort warf ein defektes Bild bzw. eine fehlende GD-Erweiterung einen unbehandelten Fatal Error, der die JSON-Antwort zerstörte — Frontend zeigte nur "Netzwerkfehler". Auf Jackys Bitte alle anderen Bild-Upload-Stellen im Code mitgeprüft (systemweite Suche nach `imagecreatefrom`):

- **`artikel/bild_upload.php`**: gleiches Risiko bestätigt (fehlende GD- oder Fileinfo-Erweiterung hätte einen "Call to undefined function"-Fatal-Error ausgelöst) — vorsorglich `extension_loaded('gd')`/`extension_loaded('fileinfo')`-Check ganz am Anfang ergänzt, liefert jetzt eine klare JSON-Fehlermeldung statt abzustürzen. Ansonsten war dieser Endpunkt schon vorher robuster als der Hersteller-Code (prüft `imagecreatefromjpeg()`-Rückgabewert korrekt auf `false`, keine unsichere Array-Destrukturierung).
- **`einstellungen/speichern.php`** (Shop-Logo-Upload, alle drei Formulare: Hauptshop/Neuer Shop/Bestehender Shop): **kein GD im Spiel**, nur `move_uploaded_file()` — dadurch von der GD-Bug-Klasse selbst nicht betroffen.

**How to apply:** Nur diese zwei Stellen (`artikel/bild_upload.php`, `hersteller/HerstellerService.php`) nutzen GD im ganzen Projekt (bestätigt per Repo-Grep) — beide jetzt abgesichert. Falls künftig eine neue Bild-Upload-Stelle mit GD dazukommt, denselben `extension_loaded('gd')`-Guard direkt mit einbauen, nicht erst nach einem Live-Vorfall nachziehen.

**Root Cause Hersteller-Vorfall bestätigt (2026-07-11):** GD war auf dem Live-Server tatsächlich nicht aktiviert — ein Apache-Neustart (nach `extension=gd` in `php.ini`) hat das behoben, genau wie vermutet.

## 🟢 BUG behoben (2026-07-11, direkter Folgefund): Firmenlogo-Upload scheiterte still ohne jede Fehlermeldung

Trotz aktiviertem GD funktionierte das **Firmenlogo** (Einstellungen → Firma-Tab) weiterhin nicht — anderer Endpunkt, anderer Bug, gleiches Symptom-Muster ("stiller Fehlschlag"): `speichereShopLogo()` in `einstellungen/speichern.php` gab bei zu großer Datei (>2MB), falschem Format oder Schreibrechte-Problem nur `null` zurück — die Seite zeigte trotzdem "Firmenangaben gespeichert", das Logo blieb aber bei "Kein Logo" hängen, ganz ohne Fehlermeldung. Betraf alle drei Aufrufer (Hauptlogo, Kanal bearbeiten, neuer Kanal). Gleiches Fix-Muster wie beim Hersteller: Funktion wirft jetzt `RuntimeException` mit konkreter Meldung statt still `null`, an allen drei Stellen abgefangen und angezeigt statt der irreführenden Erfolgsmeldung. Per isoliertem Logik-Test (3 Pfade: gültig/zu groß/falsches Format) verifiziert — `move_uploaded_file()` selbst kann aus einem echten Upload-Kontext heraus nicht per CLI nachgestellt werden (PHP lehnt das aus Sicherheitsgründen ab), daher nur die Kontrollfluss-Logik isoliert getestet, der unveränderte `move_uploaded_file()`-Aufruf selbst bleibt vom bisherigen (funktionierenden) Verhalten unangetastet.

**Nebenfund:** in `kanaele_update` eine wirkungslose Doppel-Abfrage entfernt (gleiches Muster wie zuvor in `artikel_gruppen_loeschen.php` gefunden — vermutlich ein wiederkehrendes Copy-Paste-Artefakt in diesem Codebereich).

**Lehre:** "Stiller Fehlschlag ohne Fehlermeldung" ist jetzt zweimal am selben Tag in unterschiedlichen Upload-Endpunkten aufgetaucht (Hersteller-Logo GD-Crash, jetzt Firmenlogo Silent-Null-Return) — bei jedem File-Upload-Code künftig direkt beim Bauen prüfen: kann diese Funktion aus irgendeinem Grund `null`/leise fehlschlagen, ohne dass der Aufrufer das dem Anwender zeigt?

## 🟢 Dritter Fund selber Tag (2026-07-11): `post_max_size` — Firmenlogo weiterhin ohne jede Reaktion trotz Fix #2

Nach dem Silent-Null-Return-Fix meldete Jacky: Firmenlogo-Upload bleibt weiterhin komplett wirkungslos — Dateiname wird im Browser angezeigt, nach "Speichern" aber **gar keine** Reaktion (weder Erfolg noch Fehler), Button springt auf "keines ausgewählt" zurück. Das war das entscheidende Symptom: **totale Stille** statt einer (auch falschen) Meldung deutet auf PHPs bekanntes `post_max_size`-Verhalten hin — überschreitet die Gesamtgröße eines multipart-Requests `post_max_size` (php.ini), leert PHP `$_POST` **und** `$_FILES` komplett, ganz ohne Fehlercode, bevor das eigene Script überhaupt anfängt. `$tab = $_POST['tab'] ?? ''` ist dann einfach leer — keiner der `if ($tab === ...)`-Blöcke greift, kein Fehler wird gesetzt, kein Erfolg — exakt das beobachtete Verhalten.

**Fix:** Alle vier Upload-Endpunkte (`einstellungen/speichern.php`, `artikel/bild_upload.php`, `hersteller/speichern.php`, `hersteller/aktualisieren.php`) prüfen jetzt explizit `empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0` ganz am Anfang und melden die echte Ursache statt stiller Leere bzw. einer irreführenden "Pflichtfeld fehlt"-Meldung. Per CLI-Simulation der drei Kombinationen (leer+Content-Length / leer+kein Content-Length / normale Daten) verifiziert. Kein False-Positive-Risiko geprüft: alle vier Aufrufer senden ausschließlich `multipart/form-data` per `FormData`, nie rohes JSON (das hätte ebenfalls leeres `$_POST` mit vorhandenem Content-Length, wäre aber legitim) — daher an diesen vier Stellen sicher anwendbar.

**Korrektur (2026-07-11, noch am selben Tag):** `post_max_size`-Theorie war falsch — Jacky schickte die echte Live-`php.ini`: `post_max_size=40M`, `upload_max_filesize=40M`, GD aktiv. Trotzdem weiterhin keine Reaktion beim Firmenlogo. Beim genaueren Nachlesen des eigenen Fixes den echten Rest-Bug gefunden: **alle** Aufrufer prüften nur exakt `error === UPLOAD_ERR_OK` und ignorierten jeden ANDEREN Fehlercode (`UPLOAD_ERR_CANT_WRITE`, `UPLOAD_ERR_NO_TMP_DIR`, `UPLOAD_ERR_PARTIAL`, ...) komplett stillschweigend — kein Fehler, keine Meldung, Logo blieb einfach weg. Betraf `einstellungen/speichern.php` (Firmenlogo + beide Kanal-Logo-Stellen) UND `HerstellerService::save()`/`update()` gleichermaßen (dort war es vorher nur zufällig nicht aufgefallen, weil GD-Fehler den Absturz VOR diesem Punkt auslösten). Neue `uploadFehlerText(int $code): string`-Hilfsfunktion (in beiden Dateien dupliziert, kein gemeinsamer Helper-Ort zwischen `public/einstellungen/` und `src/modules/hersteller/` vorhanden) übersetzt jetzt jeden PHP-Upload-Fehlercode in eine verständliche Meldung. Per Reflection + simulierten Fehlercodes getestet (`UPLOAD_ERR_CANT_WRITE` korrekt bis zur Fehlermeldung durchgereicht), Testdaten aufgeräumt.

**Noch nicht bestätigt, was die tatsächliche Live-Ursache war** — Jacky muss nach diesem dritten Fix nochmal testen; jetzt sollte in jedem Fall entweder das Logo funktionieren, oder eine konkrete Fehlermeldung mit dem echten Grund erscheinen (nicht mehr Stille).

**Lehre:** Bei mehreren aufeinanderfolgenden Fixes für dasselbe gemeldete Problem nicht vorschnell eine Theorie als bestätigt annehmen, nur weil sie plausibel klingt — hier hätte ein Blick auf die tatsächliche `php.ini` VOR dem post_max_size-Fix die falsche Fährte vermieden. Zweite Lehre: bei "keine Fehlermeldung, aber auch kein sichtbarer Erfolg" immer prüfen, ob der eigene Code überhaupt JEDEN Fehlerfall abdeckt (hier: nur der Erfolgsfall `=== UPLOAD_ERR_OK` wurde geprüft, alle Fehlerfälle liefen stillschweigend ins Leere).

## 🔴 Noch UNGELÖST (2026-07-11, Session unterbrochen): Firmenlogo weiterhin ohne jede Reaktion auf Live — trotz drei Fixes

Nach GD-Fix (bestätigt behoben, Apache-Neustart), Silent-Null-Return-Fix und Upload-Fehlercode-Fix meldet Jacky: **immer noch keine Reaktion** beim Firmenlogo speichern auf Live — auf Dev funktioniert es einwandfrei. Session musste unterbrochen werden, bevor die Ursache gefunden wurde.

**Auffälliges Muster:** Jeder Code-Fix hat am beobachteten Live-Verhalten NICHTS geändert — drei unterschiedliche, jeweils plausible Bugs wurden behoben, aber das Symptom blieb exakt gleich. Das spricht dafür, dass das Problem möglicherweise **gar nicht im inzwischen reparierten Code liegt, sondern die Fixes auf Live nie ankommen**.

**Hypothese für den Wiedereinstieg (noch nicht geprüft):**
1. **Deploy-Frage zuerst klären:** Läuft auf Live wirklich der aktuelle Git-Stand (`241daa7` oder neuer)? Wurde nach den Fixes ein `git pull`/Deploy gemacht, oder nur der Apache-GD-Fix eingespielt? Ohne Deploy würden alle Code-Änderungen wirkungslos bleiben, unabhängig davon wie oft am Code selbst gearbeitet wird.
2. **PHP-Opcache:** Falls Opcache aktiv ist und nach einem Deploy nicht geleert wird, könnte Live weiterhin alte PHP-Bytecode-Versionen ausführen, selbst wenn die Dateien am Server schon aktuell sind.
3. Falls Deploy + Opcache beide bestätigt aktuell sind: dann tatsächlich zurück in die Anwendungslogik — evtl. ein völlig anderer Pfad wird auf Live durchlaufen (z.B. anderer PHP-Handler/FastCGI/IIS-Konfiguration die den Request schon vor PHP abfängt, z.B. eigenes Upload-Limit am Webserver/Reverse-Proxy statt php.ini).

**How to apply:** Bei Wiederaufnahme der Session ZUERST Punkt 1 (Deploy-Stand) klären, bevor weiter am Anwendungscode gesucht wird — das Muster "jeder Fix wirkt sich nicht aus" ist der stärkste Hinweis, den wir bisher haben.

## ✅ GELÖST (2026-07-11, gleicher Tag, nach Session-Fortsetzung): tatsächliche Ursache gefunden

Deploy war aktuell (Composer + migrate.php bestätigten "nichts Neues"). Die echte Ursache: **auf Live existierte noch gar kein Kanal** (`shops`-Tabelle leer) — Jacky hatte das selbst geliefert ("die Live hat noch keine Kanäle"). Der Code suchte beim Hauptlogo-Speichern nach `shops.slug = 'mealana'`, fand nichts, und lief bisher lautlos ins Leere (dieselbe Lücke wie die vorherigen Funde, nur eine Ebene weiter oben — kein `shopRow`, keine Fehlermeldung). Erklärt auch, warum Hersteller-Bilder auf Live längst funktionierten: die hängen an einer komplett anderen Tabelle ohne jeden Kanal-Bezug.

**Zwei Fixes:**
1. `!$shopRow`-Fall meldet jetzt explizit "Es ist noch kein Kanal angelegt".
2. **Wichtigerer Design-Fix (Jackys eigener Einwand):** Die Suche nach `slug='mealana'` war ohnehin hart auf DIESE Installation verdrahtet — bei einer Weitergabe an einen anderen Betrieb (siehe [[project_whitelabel_branding]] / [[project_installationsanleitung]]) hätte deren Kanal nie "mealana" geheißen, das Hauptlogo wäre dort für immer kaputt gewesen. Umgestellt auf "erster angelegter Kanal" (`ORDER BY id LIMIT 1`, kein Slug-Vergleich mehr) — sowohl beim Speichern (`einstellungen/speichern.php`) als auch bei der Vorschau-Anzeige (`einstellungen/index.php`). Passt jetzt zum selben Muster, das `kundenanzeige/index.php` (`WHERE id = 1`) und `DokumentService`/`Mailer` (`shop_id ?? 1`-Fallback) schon vorher verwendet haben — vorher war `einstellungen/speichern.php` die einzige Stelle mit dem Slug-Sonderfall.

**Praktische Lösung für Jacky:** Kanal mit Slug `mealana` entweder über Einstellungen → Kanäle → "+ Kanal hinzufügen" oder direkt per SQL-Insert anlegen (beide Wege genannt) — danach Hauptlogo erneut hochladen.

**✅ Von Jacky bestätigt (2026-07-11): funktioniert jetzt auf Live.** Damit ist die komplette Kette dieses Tages (GD-Extension fehlte → Silent-Null-Return → Upload-Fehlercodes ignoriert → fehlender Kanal-Datensatz → hartcodierter Slug) durchgearbeitet und abgeschlossen.

**Lehre (bestätigt sich zum vierten Mal am selben Tag):** "Stiller Fehlschlag ohne jede Meldung, egal welche Ursache" zieht sich durch praktisch den kompletten Datei-Upload-Code dieses Projekts — GD-Fehler, Silent-Null-Return, Upload-Fehlercodes, fehlende Fremdschlüssel-Zeile. Jede Stelle, an der ein Wert `null`/`false` zurückgeben oder eine Bedingung einfach nicht zutreffen kann, MUSS explizit dem Anwender gemeldet werden — "if (erfolgsfall) { ... }" ohne einen expliziten else-Zweig für den Fehlerfall ist der wiederkehrende Fehler-Archetyp.

## Noch offen
- WooCommerce API-Sync implementieren (braucht echten WC-Server zum Testen)
- Wasserzeichen-Upload im Admin-Menü
- "Keine Bilder" Warn-Chip in Artikelliste (gemerkt für später)
- Komplettabgleich im Admin-Menü (Bilder/Artikel/Kategorien pro Shop) — gemerkt für später
