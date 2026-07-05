---
name: project-rechte-rollen
description: "Rollen, Rechte, Lizenz-Instanzierung, Manager-Override — vollständiges Design (2026-06-27)"
metadata: 
  node_type: memory
  type: project
  originSessionId: c2be1558-2ea4-4ebb-8128-43fd08ac683b
---

## Zwei getrennte Konzepte

| Konzept | Tabelle | Bedeutung |
|---|---|---|
| **Lizenz** | `modul_lizenzen` | Was darf diese *Installation* (per Kunde gekauft) |
| **Rechte** | `benutzer_rollen` + `rollen_rechte` | Wer darf was *innerhalb* der Installation |

---

## Rollen (Hierarchie von oben nach unten)

| Rolle | Kernrechte | Geldgeschäfte | Besonderheit |
|---|---|---|---|
| **Super-Admin** | alles + Lizenzierung + Branding + Setup-Wizard | ✓ alles | Erster User bei Neuinstallation |
| **Admin** | alles außer Lizenzierung/Branding | ✓ alles | |
| **Manager** | alles außer Einstellungen | ✓ alles inkl. Gutschriften | Gibt Manager-Codes frei |
| **Kassier** | Kasse + Artikel lesen | ✓ Kasse (Verkauf/Rückgeld) — Auszahlungen nur mit Manager-Override | |
| **Lager** | Lager + Bestellwesen + Artikel lesen | ✗ | |
| **Packplatz** | Packplatz scan/versenden + Retouren erfassen | ✗ — Gutschrift braucht Manager-Bestätigung | Retoure anlegen ≠ Gutschrift auslösen |
| **Praktikant** | Artikel CRUD + Bilder hochladen (Datenwartung) | ✗ | Kein Dashboard-Zugriff; alles geloggt |
| **Readonly** | alle Module nur lesend | ✗ | |

**Why:** Alles was Geld bewegt ist heikel → extra Freigabe-Ebene.

---

## Manager-Override (Popup-Freigabe)

Überall wo Geld zurückfließt und der aktuelle Benutzer kein Manager/Admin ist:
- **Kasse: Auszahlung nach Retoure** (Bargeld raus)
- **Packplatz: Gutschrift auslösen**

**Ablauf (wie Lidl-Kassensystem):**
1. Kassier/Packplatz-User löst Aktion aus
2. Popup erscheint: "Manager-Freigabe erforderlich — Manager-Code eingeben"
3. Manager gibt seinen persönlichen PIN/Code ein
4. System prüft: Code gehört einem User mit Rolle ≥ Manager?
5. Ja → Aktion wird durchgeführt + Log-Eintrag mit beiden User-IDs (Auslöser + Freigebender)
6. Nein → Popup bleibt, Fehlermeldung, erneut versuchen

**Log-Eintrag:** `{ aktion: 'manager_override', ausgeloest_von: user_id, freigegeben_von: manager_id, kontext: 'kasse_auszahlung' }`

**How to apply:** Beim Bauen der Kasse-Retoure und Packplatz-Gutschrift: vor der Ausführung Manager-Override-Check einbauen. Separates Modal, eigener PHP-Endpunkt zur Code-Validierung.

---

## Atomare Rechte (ca. 25-30 Stück)

Werden **nicht einzeln vergeben** — nur über Rollen. Aber als Fangpunkte im Code:

```
artikel.lesen / artikel.erstellen / artikel.bearbeiten / artikel.loeschen
lager.lesen / lager.bewegung / lager.umbuchung
bestellwesen.lesen / bestellwesen.erstellen / bestellwesen.wareneingang
kasse.zugriff / kasse.auszahlung (→ Manager-Override)
auftraege.lesen / auftraege.erstellen / auftraege.stornieren
packplatz.zugriff / packplatz.retoure / packplatz.gutschrift (→ Manager-Override)
kunden.lesen / kunden.bearbeiten
einstellungen.lesen / einstellungen.bearbeiten
lizenz.verwalten (nur Super-Admin)
dashboard.zugriff (Praktikant: explizit NEIN)
```

**Fangpunkte:** Überall wo heute geloggt wird (`aktivitaeten`-Tabelle) ist ein natürlicher Ort für eine Rechteprüfung. Log-Kategorie und Recht haben dieselbe Struktur (`modul.aktion`).

---

## Lizenz-Instanzierung

`modul_lizenzen` bekommt eine zusätzliche Spalte:

```sql
ALTER TABLE modul_lizenzen ADD COLUMN max_instanzen INT NULL;
-- NULL = unbegrenzt, 1 = eine Instanz, 2 = zwei, usw.
```

| Modul-Code | max_instanzen | Bedeutung |
|---|---|---|
| `kasse` | 1 | nur K1 aktiv schaltbar |
| `kasse` | 3 | bis zu 3 Kassen |
| `shop_sync` | 1 | nur 1 Shop sync_aktiv |
| `shop_sync` | NULL | unbegrenzt viele Shops |

**Prüflogik:** Beim Aktivieren einer weiteren Instanz (Kasse aktivieren, Shop aktivieren):
- COUNT aktive Instanzen < max_instanzen? → OK
- Sonst: Fehlermeldung "Lizenz erlaubt nur X Instanzen"

---

## Lizenz-Pakete (Verkaufsmodell)

| Paket | Inhalt | max_instanzen |
|---|---|---|
| **Core** | Artikel, Lager, Lieferanten, Bestellwesen | — |
| **Verkauf** | Auftragsmodul, Dokumentenarchiv, Mahnwesen | — |
| **Kasse** | POS + Kassenbuch + RKSV | 1 Kasse |
| **Kasse Plus** | wie Kasse | 3 Kassen |
| **Partner** | Mietfächer, Kommission | — |
| **Shop-Sync** | WooCommerce-Adapter | 1 Shop |
| **Shop-Sync Plus** | WooCommerce-Adapter | unbegrenzt |
| **Buchhaltung** | DATEV-Export | — |

---

## Implementierungsreihenfolge (wenn es soweit ist)

1. `modul_lizenzen.max_instanzen` Migration
2. Rollen-Tabellen + Rechte-Tabellen (DB)
3. Login/Logout UI (Shell)
4. Benutzer-Profil UI
5. Rollen-Zuweisung im Admin
6. Rechteprüfung als PHP-Middleware / Service
7. Manager-Override Modal
8. Lizenzserver (wenn erste externe Installation)

**Why:** Reihenfolge so weil: ohne Login kein Rechtecheck; ohne Rechtecheck kein Manager-Override sinnvoll testbar.

## Korrektur 2026-07-03 (präzisiert, nach Jackys Rückfrage): Tabellen existieren, aber FAKTISCH KEINE Durchsetzung

Erste Korrektur war zu großzügig formuliert ("Basis-RBAC existiert schon") — Jacky hat zurecht nachgefragt, weil sein Erinnerungsbild (Zugriffssteuerung zu Artikel-bearbeiten usw. kommt erst noch) näher an der Wahrheit war. Genauer nachgeschaut: `Auth::kann()` wird im **gesamten Code nur an exakt einer Stelle** aufgerufen — `shell_top.php`, für einen deaktivierten "Lizenzverwaltung"-Menüpunkt (`href="#"`, Titel "Kommt bald"). Es gibt **keine einzige** Stelle, die prüft, ob ein Benutzer Artikel bearbeiten, Lager buchen, Preise ändern etc. darf. Tabellen `rollen`/`berechtigungen`/`rollen_berechtigungen`/`benutzer_rollen` (Migrationen 004/005) + 3 seed-Rollen existieren als reines DB-Gerüst, ohne jede funktionale Auswirkung. Login/Logout (`login.php`/`logout.php`) funktionieren, sind aber unabhängig von der Berechtigungsfrage (jeder eingeloggte Benutzer kann aktuell alles).

**Fazit:** Die von Jacky beschriebene Vision (Gruppen mit Berechtigungs-Pool, Admin weist zu, Superadmin schaltet neue Admins frei, granulare Rollen wie Kassier/Datenbearbeiter/Praktikant) ist **komplett ungebaut** — nur besprochen. Einzig vorhanden: das DB-Schema als möglicher Ausgangspunkt, kein funktionierendes Feature.
**Weiterhin fehlt:** feingranulare Rollen-Struktur, Manager-Override-Popup, Lizenz-Instanzierung (`max_instanzen`), Admin-UI zum Benutzer-Anlegen/Rollen-Zuweisen (nur `erp/database/create_admin.php` CLI existiert), UND jegliche tatsächliche Rechteprüfung im Code.

## ✅ "Neuen Benutzer anlegen"-UI gebaut (2026-07-05)

Admin-UI jetzt vollständig: `public/benutzer/liste.php` (Liste + Neu/Bearbeiten-Modal, Nav-Eintrag "👤 Benutzerverwaltung" im "···"-Dropdown neben Kasse/Packplatz), `BenutzerRepository`/`BenutzerService` (`src/modules/benutzer/`). Reservierter Username `system` wird abgelehnt (case-insensitive), genau **eine Rolle pro Benutzer** (bewusst vereinfacht, obwohl `benutzer_rollen` technisch n:m ist — bei jedem Speichern wird die alte Zuweisung gelöscht + neu eingefügt).

**Passwort-Setzen-Link (statt Admin gibt Klartext-Passwort ein):**
- Admin wählt beim Anlegen: "Link per E-Mail senden" (Default) ODER "Direkt setzen" (zwei Passwortfelder, Fallback falls kein Mailserver konfiguriert)
- Neue Tabelle `benutzer_passwort_tokens` (Migration 108): Token wird nur als SHA-256-Hash gespeichert (nie Klartext), 24h gültig, `verwendet_am` verhindert Zweitnutzung
- `PasswortResetService` (`src/modules/benutzer/`) ist der gemeinsame Mechanismus für **zwei** Einstiegspunkte: Admin-Neuanlage/"Link erneut senden" UND die öffentliche "Passwort vergessen"-Seite (`public/passwort_vergessen.php`, verlinkt von `login.php`) — beide landen auf derselben Zielseite `public/passwort_setzen.php?token=X`
- **Sicherheit:** `angefordertFuerEmail()` verrät nie ob eine E-Mail existiert (immer dieselbe generische Meldung, verhindert User-Enumeration); Rate-Limiting 1 Token pro Benutzer alle 5 Minuten
- Mail-Template: `templates/mails/passwort_setzen.html.twig` (erste Mail, die einen Link **zurück ins eigene ERP** enthält statt nur Firmendaten — Link wird zur Laufzeit aus `$_SERVER['HTTP_HOST']` + `BASE_PATH` gebaut, da bisher keine "absolute Basis-URL"-Konfiguration nötig war)

**Getestet (CLI gegen echte Dev-DB):** reservierter Username abgelehnt, doppelter Username abgelehnt, Login vor Passwort-Setzen schlägt fehl (Platzhalter-Hash unbrauchbar), Rate-Limit greift, unbekannte E-Mail wirft keinen Fehler, ungültiger Token abgelehnt, kompletter Erfolgspfad (Setzen → Login klappt → Token kann nicht zweimal verwendet werden) grün.

**Stolperstein:** `laeuft_ab_am TIMESTAMP NOT NULL` ohne expliziten DEFAULT scheiterte an der `NO_ZERO_DATE`-sql_mode dieser Installation ("Invalid default value") — auf `TIMESTAMP NULL` umgestellt (Wert wird ohnehin immer explizit beim Insert mitgegeben). Bei künftigen Migrationen mit einer zweiten NOT-NULL-TIMESTAMP-Spalte ohne `CURRENT_TIMESTAMP`-Default daran denken.

**Noch offen:** "Passwort vergessen" hat noch kein IP-basiertes Rate-Limiting (nur pro-Benutzer/E-Mail). Rollen-Dropdown im Benutzer-Anlegen-Formular zeigt aktuell alle Rollen ungefiltert (auch Superadmin) — keine Rang-Einschränkung beim Zuweisen selbst, nur bei der Rechte-Matrix (siehe unten). Sollte nachgezogen werden sobald `benutzer/liste.php` selbst rechtegeprüft wird.

## ✅ Rollen auf Zielvision erweitert + Matrix-UI gebaut (2026-07-05)

**Wichtiger Fund vor dem Bau:** Die DB hatte schon ein volleres RBAC-Grundgerüst als hier dokumentiert — 45 Berechtigungen (Namenskonvention `modul.anzeigen/anlegen/bearbeiten/loeschen`, nicht `lesen/erstellen` wie in der ursprünglichen Planung oben) mit konkreten Zuweisungen an alle 3 alten Rollen. Nur `benutzer_id=1` ("admin", tatsächlich der Rolle **superadmin** zugewiesen) hatte real eine Rolle — "mitarbeiter" war komplett ungenutzt, daher gefahrlos ersetzbar.

**Migration 109:**
- `rollen.rang` (INT) neu — bestimmt Bearbeitungsrecht in der Matrix-UI (siehe unten)
- Rolle `mitarbeiter` gelöscht, sechs neue: `assistent`(80), `manager`(70), `kassier`(50), `lager`(50), `packplatz`(50), `praktikant`(30), `readonly`(10) — `superadmin`=100, `admin`=90
- 27 neue Berechtigungen für bisher unabgedeckte Module: `kunden.*`, `auftraege.*`, `partner.*`, `bestellwesen.*`, `einstellungen.*`, `lizenz.verwalten`, `dashboard.zugriff`, `kasse.auszahlung`, `kasse.verwaltung`, `packplatz.retoure`, `packplatz.gutschrift`, `versand.*`, `buchhaltung.anzeigen`, `benutzer.anzeigen` — macht 72 gesamt
- **Bugfix nebenbei:** Admin hatte bisher fälschlich NICHT `benutzer.loeschen`/`api.zugriff`/`shopabgleich.*` — jetzt korrigiert (Admin = alles außer `lizenz.verwalten`, wie in der Vision beschrieben)
- Komplette Rechte-Zuweisung je Rolle nach der besprochenen Matrix (Kassier ohne `kasse.auszahlung`, Packplatz mit `packplatz.retoure` aber ohne `packplatz.gutschrift`, Praktikant ohne Löschrechte + ohne Dashboard, Readonly = alle `*.anzeigen` + `dashboard.zugriff`)
- **Stolperstein:** `TIMESTAMP NOT NULL` ohne Default (irrelevant hier, war beim Benutzer-Feature) — bei dieser Migration keine TIMESTAMP-Probleme

**Neue Rolle "Assistent" — Jackys Konzept für einen zweiten Admin, der ihn nicht aussperren kann:**
Operative Rechte identisch zu Admin (alles außer `lizenz.verwalten`), aber niedrigerer Rang (80 statt 90). Die Matrix-Bearbeitungsregel ("nur echt niedrigerer Rang als der eigene") sorgt automatisch dafür: Admin kann Assistent jederzeit Rechte entziehen, Assistent kann Admin nicht anrühren, niemand kann die eigene Rolle bearbeiten (verhindert Selbst-Hochstufung). Kein Sonderfall-Code nötig — reines Rang-Vergleich-Prinzip.

**Lizenzierung bestätigt (Jacky 2026-07-05):** Nur Superadmin. Das eigentliche Lizenzverwaltungs-Modul wird bewusst NICHT an Kunden mitgeliefert — bleibt eigenständiges Tool nur auf Jackys eigenen Rechnern. Kundeninstallationen bekommen Modul-/Kanal-/Kassen-Slot-Freischaltung über ein Lizenzkey-System (noch zu bauen, siehe Implementierungsreihenfolge Schritt 8 oben). `lizenz.verwalten` ist deshalb in der Matrix-UI für JEDE Rolle (auch von Superadmin selbst an andere) fix gesperrt — kein Rang reicht aus, um sie zu vergeben.

**Matrix-UI (`public/rollen/matrix.php` + `src/modules/rollen/`):**
- Tabelle: Zeilen = Berechtigungen (gruppiert nach Modul-Präfix), Spalten = Rollen (sortiert nach Rang absteigend)
- `Auth::login()` lädt jetzt zusätzlich `rolle_id`/`rolle_rang` in die Session (`$_SESSION['benutzer']['rolle_rang']`) — bestehende Sessions vor diesem Feature brauchen einmal Neu-Login
- Checkbox-Toggle per AJAX (`rollen/berechtigung_setzen.php`), Server prüft bei jedem Toggle: Ziel-Rolle-Rang < eigener Rang UND Berechtigung ≠ `lizenz.verwalten` — beides serverseitig, nicht nur UI-Sperre
- Getestet (CLI gegen echte Dev-DB): alle Zähler stimmen exakt mit der Planung überein (Superadmin 72, Admin/Assistent je 71, Manager 69, Kassier 5, Lager 24, Packplatz 7, Praktikant 6, Readonly 17); Rang-Sperre in beide Richtungen korrekt (Assistent kann Admin nicht bearbeiten, Admin kann Assistent bearbeiten); Lizenz-Sperre blockt auch Superadmin selbst; Gleichrang-Sperre (Kassier kann Lager nicht bearbeiten) korrekt; authentifizierter Seitenaufruf zeigt korrekte Sperr-Zustände (Superadmin-Spalte disabled, alles darunter editierbar für den eingeloggten Superadmin-Benutzer)

**~~Weiterhin komplett offen~~ → Durchsetzung gebaut (2026-07-05, Grund: übermorgen/spätestens Mittwoch steht der BFR-Hardware-Test an, danach soll dieser Punkt fertig sein):**

## ✅ Rechteprüfung scharf geschaltet (2026-07-05)

**Architektur-Entscheidung:** NICHT 260 Einzel-Checks in jede Modul-Seite eingebaut, sondern eine zentrale Zuordnungstabelle: `erp/src/core/Zugriffsregeln.php` — ein Array `[Verzeichnis][Dateiname] => Berechtigungsname`. `Auth::pruefeSeite()` (neu in `Auth.php`) schlägt dort beim Laden jeder Seite nach (aufgerufen aus `auth_check.php`, direkt nach `Auth::check()`). Kein Eintrag für eine Datei → kein Block (nur Login-Pflicht wie bisher) — verhindert, dass eine vergessene neue Seite versehentlich alle aussperrt.

**Warum zentral statt verteilt:** Bei ~230 betroffenen Dateien wäre die Fehlerquote bei 230 Einzel-Edits hoch gewesen (falsche Stelle, vergessene Datei, Tippfehler in Berechtigungsnamen) und Review praktisch unmöglich. Eine Tabelle ist an einer Stelle überschaubar, korrekturfreundlich und der natürliche Ort für künftige neue Seiten.

**Zwei Antwortformen bei fehlender Berechtigung:**
- Normale Seiten → Redirect auf neue Seite `public/zugriff_verweigert.php` (Login-Card-Stil wie `login.php`, Button zurück zu `start.php`)
- AJAX/JSON-Endpunkte (per grep nach `Content-Type: application/json` identifiziert, 83 Dateien, Liste in `Zugriffsregeln::$jsonEndpunkte`) → `{erfolg:false, fehler:"Keine Berechtigung..."}`, passt zur bestehenden JS-Konvention (`js/artikel*.js` liest genau dieses Format)

**Mapping-Entscheidungen, die Jacky gegenprüfen sollte (keine eigene Berechtigung in der DB vorhanden, daher unter naheliegendes Modul gefaltet):**
- **Hersteller + Preis-Aktionen (`aktionen/`)** → unter `artikel.*` (beide hängen im Sidebar-Menü unter "Artikel", siehe `shell_top.php`)
- **Achsen (`achsen/` + `artikel/achsen_zuweisen*.php`)** → unter `varianten.*` (fachlich Teil des Varianten-Systems, nicht der Artikel-Stammdaten selbst)
- **Kasse:** laufender Verkaufsbetrieb (bon.php, alle ajax_*.php etc.) → `kasse.starten`; Tagesabschluss/Kassenbuch/Kassensturz/Nullbon → `kasse.stoppen`; `einstellungen/kasse_*.php` (Kassen-Instanzen anlegen/registrieren) → `kasse.verwaltung`
- **Picklisten (`lager/picklisten*.php`)** → unter `lager.*` (kein eigenes Berechtigungs-Paar vorhanden)

**Getestet (curl gegen echten Apache, nicht nur CLI-Simulation):** Fake-Superadmin-Session kommt überall durch (12 Stichproben-URLs quer durch alle Module, alle 200). Fake-Praktikant-Session (nur artikel.*/varianten.*) bekommt bei artikel/achsen 200, bei lager/kunden/kasse einen 302 auf `zugriff_verweigert.php` — exakt wie erwartet. Test-Dateien waren temporär unter `public/_temp_test_*.php`, sofort wieder gelöscht.

**Dashboard-Redirect-Frage von heute früh gleich mitgelöst:** `dashboard.php` prüft jetzt selbst `dashboard.zugriff` (bewusst NICHT über die generische Zugriffsregeln-Tabelle, weil hier kein Fehler, sondern ein Redirect passender ist) und leitet bei fehlendem Recht über `Auth::startseiteFuerBenutzer()` auf das erste Modul weiter, das der Benutzer wirklich darf (Priorität: Artikel→Lager→Kunden→Aufträge→Bestellwesen→Partner→Buchhaltung→Einstellungen→Benutzer, letzter Fallback immer `benutzer/profil.php`, das braucht keine Berechtigung). Live getestet: Praktikant-ähnliche Session landet korrekt auf `artikel/liste.php` statt auf der Fehlerseite.

**Bewusst noch NICHT angefasst:** Kasse/Packplatz-Berechtigungen wurden trotzdem mit ausgerollt (nicht zurückgestellt) — Risiko wurde durch den curl-Smoketest abgefedert, Superadmin (Jackys echter Account) bleibt so oder so unbeeinflusst. Vor dem BFR-Hardware-Test trotzdem einmal echt im Browser mit dem eigenen Login durchklicken, bevor Fremdpersonal/andere Rollen involviert sind.

## ✅ Manager-Override per PIN gebaut (2026-07-05)

Jacky-Entscheidung zur Ausweis-Frage: **für jetzt ein kurzer PIN** (schnellste Eingabe an der Kasse). Für später vorgemerkt und NICHT vergessen: ein "i"-Button könnte künftig NFC/iButton nutzen, oder ein Mitarbeiterausweis mit Barcode gescannt werden — dafür müsste ein Barcode-Präfix gefunden werden, der sich von normalen EAN-Codes unterscheiden lässt (noch zu klären, welches Format). Beides bewusst zurückgestellt, PIN ist der Einstieg.

**Migration 110:** `benutzer.manager_pin_hash VARCHAR(255) NULL` — bcrypt-Hash, nie Klartext.

**`Auth::pruefeManagerPin(string $pin): ?array`** (in `Auth.php`): sucht NUR über den PIN (kein Benutzername nötig — an der Kasse soll niemand erst sich selbst auswählen), loopt über alle aktiven Benutzer mit Rolle ≥ Rang 70 (Manager+) die einen PIN gesetzt haben, `password_verify()` pro Kandidat. Gibt bei Treffer `{id, formularname}` zurück (für Logging, wer freigegeben hat).

**PIN setzen:** `benutzer/profil.php` — neue Karte "Manager-PIN", nur sichtbar wenn `rolle_rang >= 70`. Self-Service (jeder setzt nur seinen eigenen PIN), 4-6 Ziffern, gleiche Formular-Mechanik wie "Passwort ändern" nebenan.

**Zwei Einsatzstellen, beide serverseitig VOR jeder Buchung geprüft (nicht erst danach), damit bei fehlender Freigabe wirklich nichts passiert — kein halb gebuchter Bon, keine Ware ohne Gutschrift-Entscheidung:**

1. **Kasse-Auszahlung** (`kasse/bon_speichern.php`): Retour eines bereits bezahlten Web-Auftrags (Barrückerstattung an der Kasse, siehe [[project_kasse_bon_design]]). Neuer Block direkt nach der `$webAuftragBezahlt`-Ermittlung berechnet den Retourbetrag VORAB (dieselbe Formel wie die bestehende Buchung weiter unten, bewusst dupliziert statt die alte Stelle umzubauen — die alte, bewährte Logik bleibt unverändert, nur ein zusätzliches frühes Gate davor). Bei Retourbetrag > 0 und fehlendem `kasse.auszahlung`-Recht: JSON-Antwort `{erfolg:false, braucht_manager_pin:true, fehler:"..."}`.
   - **Client (`bon.php`):** neues Overlay `ov-manager-pin`. `bonSpeichern()` erkennt `braucht_manager_pin` in der Antwort, öffnet das Popup, merkt sich die zahlDaten in `_managerPinPendingZahlDaten`. `managerPinBestaetigen()` sendet denselben Request erneut mit `manager_pin` ergänzt. Falsche PIN → Server schickt wieder `braucht_manager_pin`, Popup bleibt/öffnet erneut mit Fehlertext (kein Extra-Code nötig, derselbe Pfad greift einfach nochmal).

2. **Packplatz-Gutschrift** (`packplatz/retoure/speichern.php`): Gate ganz am Anfang, bevor irgendetwas gebucht wird — bei `$ergebnis === 'gutschrift'` und fehlendem `packplatz.gutschrift`-Recht. Da dieses Formular ein normaler Full-Page-POST ist (keine Fetch/JSON-Architektur wie die Kasse), gibt es hier **kein JS-Popup**, sondern ein normales PIN-Eingabefeld direkt im Formular (`detail.php`, im `gs-bereich`-Block, nur gerendert wenn `!Auth::kann('packplatz.gutschrift')`). Bei falscher/fehlender PIN: Redirect zurück auf `detail.php` mit Fehlermeldung wie gehabt.

**Logging:** beide Stellen rufen `Logger::log('manager_override', 'auftraege', $auftragId, ['ausgeloest_von'=>..., 'freigegeben_von'=>$manager['id'], 'kontext'=>'kasse_auszahlung'|'packplatz_gutschrift', ...])` — exakt das Format aus dem ursprünglichen Design oben.

**Getestet:** `Auth::pruefeManagerPin()` isoliert gegen echte Dev-DB (temporärer Test-Benutzer mit Rolle Manager + PIN 1234, danach vollständig entfernt — richtige PIN erkannt, falsche/leere PIN korrekt abgelehnt). PHP-Lint auf allen geänderten Dateien sauber. **Noch NICHT** end-to-end im Browser durchgeklickt (bräuchte einen echten bezahlten Web-Auftrag im Abholbereit-Zustand bzw. eine echte Retoure mit Rechnung — bewusst nicht mit Testdaten gegen die echte Dev-DB simuliert, siehe [[feedback_test_isolation]]). Sollte einmal echt durchgeklickt werden, ist aber kein Blocker für den BFR-Test (Kernverkauf/Normalbetrieb ist davon nicht betroffen, nur der seltene Retour-mit-Auszahlung-Fall).

**Damit ist "Rechteverwaltung: echte Durchsetzung" aus project_status.md komplett** — Checks + Manager-Override beide gebaut.

## Offline-Kasse + Manager-Override: geprüft, nicht anwendbar (2026-07-05)

Jacky-Nachfrage: funktioniert der Manager-Override auch bei der Messe-Offline-Kasse, sonst dort rausnehmen. Antwort nach Codesuche (grep über `bon_offline.php`, `kasse_bon_offline.js`, `MesseSyncService.php`, `ajax_messe.php` nach retour/auszahl/gutschrift/manager_pin — null Treffer): **der Override hängt ausschließlich an `kasse/bon_speichern.php`, das die Offline-Kasse nie aufruft.** Die Offline-Kasse hat laut Architektur ohnehin keinen Kundenbezug und keine Retouren-Funktion (siehe [[project_kassen_verwaltung]] — "kein Kundenbezug, keine Retouren/Chargen-Komplexität nötig"), der Retour-mit-Auszahlung-Fall kann dort also strukturell gar nicht auftreten. Nichts rauszunehmen, nichts kaputt — der Override ist implizit korrekt auf den Online-Pfad beschränkt.

**Härtung 2026-07-05:** `Auth::kann()` gibt jetzt für Superadmin (rolle_rang ≥ 100) immer `true` zurück, unabhängig von `rollen_berechtigungen` — Code-Invariant statt Daten-Abhängigkeit. Grund: sonst müsste bei jeder künftigen neuen Berechtigung explizit daran gedacht werden, sie auch per Migration dem Superadmin zuzuweisen, sonst hätte er sie schlicht nicht. `lizenz.verwalten` bleibt davon unberührt exklusiv (die Kann-Prüfung sagt nur "darf grundsätzlich", die Matrix-UI-Sperre für `lizenz.verwalten` ist ein unabhängiger zweiter Mechanismus).

## Lizenzverwaltung — Zwei-Ebenen-Architektur besprochen (2026-07-05, NOCH NICHT GEBAUT, nächste Session)

Jackys Konzept für das Lizenzserver-Thema (Implementierungsschritt 8 oben):

**Ebene 1 — wird mit jeder Kundeninstallation mitgeliefert:**
Schlanke Seite hinter dem bestehenden "🔐 Lizenzverwaltung"-Menüpunkt (`shell_top.php`, aktuell noch `href="#"` mit "Kommt bald", sichtbar nur bei `Auth::kann('api.zugriff')` — dieser Gate-Check ist vermutlich falsch/Platzhalter und sollte durch eine eigene Berechtigung ersetzt werden, sobald die Seite existiert). Inhalt: Lizenzkey-Eingabefeld + Anzeige der aktuell freigeschalteten Module/Kanäle/Kassen-Slots inkl. Gültig-bis-Datum. Reiner Anzeige-/Eingabe-Client — **keine** Erzeugungs- oder Verwaltungslogik für Lizenzen selbst.

**Ebene 2 — NIE an Kunden ausgeliefert, nur auf Jackys eigenen Rechnern:**
Das echte Lizenz-Verwaltungswerkzeug (Lizenzen erzeugen, Kunden/Installationen zuordnen, Module/Kanäle/Kassen-Slots + Ablaufdatum festlegen). Bewusst **physisch getrennt** vom ERP-Repository/-Auslieferungspaket — nicht nur durch eine Berechtigung im selben Code versteckt, sondern ein eigenständiges Tool. Vorteil: ein Bug oder eine falsche Freigabe in Ebene 1 kann nie versehentlich Zugriff auf die echte Lizenzerzeugung geben, weil der Code dafür in der Kundenauslieferung schlicht nicht vorhanden ist.

**Erster Benutzer:** `create_admin.php` weist bereits heute automatisch die Rolle Superadmin zu (verifiziert im Code, `SELECT id FROM rollen WHERE name = 'superadmin'` + Zuweisung) — passt bereits zum Konzept, dass genau dieser erste Benutzer Zugriff auf die Lizenzkey-Eingabe (Ebene 1) bekommt.

**Offene technische Frage für die Umsetzung (von Claude aufgeworfen, noch nicht entschieden):** Online-Validierung (Ebene 1 fragt bei jedem Check Jackys Server ab) vs. Offline-Signaturprüfung (Ebene 2 signiert das Lizenzpaket mit privatem Schlüssel, Ebene 1 hat nur den öffentlichen Schlüssel fix eingebaut und prüft rein lokal). Offline-Signatur passt besser zur sonstigen Projekt-Philosophie (WireGuard statt offener Endpunkte, Messe-Kasse-Offline-Architektur — siehe [[project_kassen_verwaltung]], [[project_infrastruktur]]) — ein Kunde bräuchte dann nicht mal Internetzugang, um seine Lizenz zu validieren, nur einmalig den erhaltenen Key.

**How to apply:** Nicht von selbst umsetzen. Erst wieder aufgreifen wenn Jacky das Thema aktiv anspricht (wie schon in [[project_update_mechanismus]] vermerkt, das am selben Meilenstein hängt). Bei der Umsetzung: Implementierungsreihenfolge oben (Schritt 8) + diese Zwei-Ebenen-Trennung + die Offline-Signatur-Frage als Ausgangspunkt nehmen.
