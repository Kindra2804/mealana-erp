---
name: project-rksv-bfr
description: "RKSV/BFR-Integration — FERTIG 2026-07-02: BfrService, Verkauf+Storno-Signierung, Nachsignierung, Nullbeleg, Umsatzzähler-Sperre, Nacherfassungs-Seite, Kassen-Registrierung mit Aktiv-seit-Stichtag, Cronjob, echter QR-Code; X/Z-Bon nicht signaturpflichtig"
metadata: 
  node_type: memory
  type: project
  originSessionId: eefd559b-9c02-443d-a0cb-164e3dadf876
---

## Status: Kernstück fertig 2026-07-02

Hersteller-Feedback zum Fallback-Verhalten ist eingetroffen und bestätigt: "BFR nicht erreichbar" = "Sicherheitseinrichtung ausgefallen" ist RKSV-konform, Beleg wird trotzdem gespeichert und muss nachsigniert werden sobald der BFR wieder da ist.

**Bewusst zuerst begrenzter Umfang** (Jackys Wahl): `BfrService` + Verkaufsbon (`typ='verkauf'`), Storno kam später am selben Tag dazu (siehe unten). X-Bon/Z-Bon sind bestätigt NICHT signaturpflichtig (siehe unten) — bleiben also für immer außen vor, nicht nur aufgeschoben. Noch offen: eigene Nacherfassungs-Seite (manueller Trigger/Übersicht), Cronjob für Nachsignierung in Ruhephasen.

## Nachsignier-Logik (gemeinsam entworfen, von Jackys Vorschlag ausgehend)

Kernidee: Kein Unterschied zwischen "aktueller Beleg" und "Nachsignierung" — `signiereAusstehende($kasseId)` holt sich bei jedem Aufruf einfach ALLE `bfr_status='ausstehend'` Verkaufsbons der Kasse (`ORDER BY id ASC`), der gerade erstellte Bon ist automatisch der letzte Eintrag in der Liste. Ablauf:

1. `pruefeVerbindung()` (GET `/state`) zuerst — nicht erreichbar → sofortiger Abbruch, kein einziger Signierversuch, nichts wird als "Lauf" protokolliert.
2. Erreichbar → offene Liste laden. Nur bei **mehr als einem** offenen Beleg wird ein `bfr_nachsignierungs_laeufe`-Eintrag angelegt (normale Einzelsignierung braucht kein Sammelprotokoll).
3. Sequenziell signieren, `usleep(200ms)` zwischen den Aufrufen. Bei Fehlschlag: sofort `break` — kein Vorbeispringen an einem gescheiterten/offenen früheren Beleg (Reihenfolge ist RKSV-Pflicht, nicht nur Kosmetik).
4. Zwei Fehlerarten: reiner Verbindungsabbruch → bleibt `ausstehend` (Auto-Retry beim nächsten Bon); von BFR abgelehnt (falsche Daten etc.) → `fehler` (braucht manuellen Blick, würde bei Retry nicht von selbst besser werden).

**Datenmodell-Entscheidung:** Ursprünglich war eine separate `kassen_bfr_log`-Tabelle geplant — stattdessen wurden die Felder direkt an `kassen_bons` gehängt (`steuer_a..e`, `bfr_status`, `signiert_am`, `nachsignierungs_lauf_id`), weil `bon_nr`/`erstellt_am`/`bruttobetrag` dort schon existieren und `rksv_signatur`/`rksv_qr` (aus einer früheren Session, nie wirklich angebunden) schon als Platzhalter vorhanden waren. `steuer_a..e` wird beim Bon-Anlegen fix gespeichert (nicht erst beim Signieren berechnet), damit auch Wochen später noch korrekt nachsigniert werden kann.

**Migration 100:** `bfr_nachsignierungs_laeufe` (id, kasse_id, ausgeloest_durch, gestartet_am, beendet_am, anzahl_signiert, anzahl_fehlgeschlagen), `kassen.bfr_url`, `kassen_bons` ALTER (steuer_a-e, bfr_status, signiert_am, nachsignierungs_lauf_id FK).

**`BfrService.php`** (`src/modules/kasse/`): `signiereAusstehende()`, `pruefeVerbindung()`, `steuerGruppenAusPositionen()` (statisch, TaxG-Mapping 20%→A/10%→B/13%→C/0%→D/Rest→E), Rest privat (XML bauen, curl GET/POST, Status-Updates).

**`KassenService::erstelleBon()`:** Steuergruppen vor dem Insert berechnet und mitgespeichert; `BfrService::signiereAusstehende()` wird NACH dem `commit()` aufgerufen, außerhalb des Transaktions-Try/Catch — ein BFR-Ausfall darf einen bereits abgeschlossenen Verkauf nie mehr rückgängig machen (try/catch schluckt jeden Fehler, nur `error_log`).

**Druckvorlagen** (`bon_druck.php`, `bon_a4.php`): zeigten vorher nur einen Debug-Platzhalter "[RKSV-Signatur: ausstehend]" und einen toten HTML-Kommentar für den QR-Code (nie gerendert). Jetzt: bei `bfr_status='signiert'` echter Link-Text + Code als Klartext; sonst der RKSV-Pflichttext "Sicherheitseinrichtung ausgefallen". QR als **Bild** (z.B. via endroid/qr-code) ist bewusst noch nicht gebaut — Klartext-Pflicht ist erfüllt, RKSV erlaubt das.

**Kassen-Einstellungen** (`einstellungen/kasse_edit.php` + `kasse_speichern.php`): neues Feld `bfr_url` pro Kasse. Leer = keine RKSV-Signatur für diese Kasse (z.B. Test/Dev-Kassen).

**Getestet mit Mock-BFR-Server** (PHP built-in server, `/state` + `/register` XML-Antworten simuliert): Normalfall, Verbindungsausfall-Fallback, und durch bereits vorhandene Alt-Testdaten sogar ein echter Nachsignierungslauf über 23 offene Verkaufsbons in korrekter aufsteigender Reihenfolge — sauber im Lauf-Protokoll erfasst. Ein vorhandener X-Bon-Datensatz wurde vom `typ='verkauf'`-Filter korrekt übersprungen.

## Nullbeleg (2026-07-02, gleicher Tag ergänzt)

Ursprünglicher Plan sah täglichen Nullbon bei Kassenöffnung + Jahresbeleg 31.12. vor — Jacky hat das bewusst durch **nur monatlich** ersetzt (kein RKSV-Pflichtfall täglich), plus ein jederzeit verfügbarer manueller Trigger. Idee dahinter: wenn vor der ersten Buchung jeden Monats ein Nullbeleg sichergestellt ist, ist auch der Jahresendbeleg automatisch abgedeckt.

**Eigene Tabelle `bfr_nullbelege`** (Migration 101) statt Integration in `kassen_bons` — ein Nullbeleg ist kein echter Verkaufsbeleg (kein Kunde, kein Betrag, keine Positionen), hätte in `kassen_bons` nur sinnlose NULL-Spalten erzeugt. Felder: kasse_id, monat (CHAR7 'YYYY-MM'), beleg_nr, ausgeloest_durch (manuell/automatisch), bfr_status, rksv_signatur/rksv_qr, benutzer_id (nur bei manuell), erstellt_am/signiert_am. Bewusst **kein UNIQUE** auf (kasse_id, monat) — der manuelle Trigger darf jederzeit einen weiteren anlegen, nur die automatische Seite ist auf "einen pro Monat" begrenzt (per Query-Check, nicht per Constraint).

**`BfrService::erstelleNullbeleg()`** — legt Datensatz an + versucht sofort zu signieren (eigener Belegnummernkreis `NullbelegK1<Timestamp>`, TT-Attribut statt TaxA im XML, kein Betrag). Wird von der manuellen Route und intern von der automatischen aufgerufen.

**`BfrService::sicherstelleMonatsNullbeleg()`** — von `KassenService::erstelleBon()` ganz am Anfang aufgerufen (vor der Verkaufs-Transaktion, eigenes try/catch). Prüft: gibt's für (kasse_id, aktueller Monat) schon einen `signiert`-Datensatz? Wenn ja, nichts tun. Wenn ein `ausstehend`/`fehler`-Datensatz existiert, den erneut versuchen (gleiche beleg_nr, kein neuer Datensatz — wichtig, sonst hätte jeder Verkauf während eines BFR-Ausfalls einen weiteren Nullbeleg-Versuch angelegt). Nur wenn gar keiner existiert, wird neu angelegt.

**Manueller Trigger:** Klick auf die "RKSV aktiv"-Anzeige in der Kassen-Kopfzeile (`bon.php`) öffnet ein Bestätigungs-Popup ("Nullbon erstellen?"), das `ajax_nullbon.php` aufruft (POST, `kasse_id` + eingeloggter Benutzer als `ausgeloest_durch='manuell'`).

**Getestet:** manueller Trigger, automatischer Skip wenn Monat schon erledigt, Fehlschlag+Retry-Fall (BFR down → bleibt ausstehend, kein Duplikat; BFR wieder da → derselbe Datensatz wird nachsigniert) — alles über den Mock-BFR-Server verifiziert.

**Jahresendbeleg-Timing — GEKLÄRT 2026-07-02:** Jacky bestätigt: Der Jahresendbeleg darf bis 15.02. erstellt werden, ist also unbedenklich, dass unsere Monats-Logik ihn erst Anfang Jänner (beim ersten Verkauf) erzeugt. Zusätzliche Absicherung: in diesem Zeitraum (zwischen Weihnachten und Neujahr) wird ohnehin die große Inventur gemacht, und Jacky macht dabei explizit selbst einen manuellen Nullbon — siehe [[project_inventur_hinweis]] für die geplante Erinnerung im künftigen Inventur-Modul ("Jahresendbeleg nicht vergessen" zwischen 15.12. und 10.01.).

## Storno-Bons + Gesamtumsatzzähler (2026-07-02, gleicher Tag ergänzt)

Hersteller-Antwort: Storno läuft ganz normal über negative Werte (kein eigenes Flag nötig) — einzige Vorgabe: der **Gesamtumsatzzähler darf nie negativ werden** (mehr signierte Stornos als Verkäufe). Der Zähler steckt nur verschlüsselt in der Signatur, ist nicht auslesbar — wir führen ihn deshalb selbst mit.

- **Migration 102:** `kassen.bfr_umsatzzaehler` DECIMAL(12,2), beim Anlegen aus bereits signierten Belegen zurückgerechnet (Backfill).
- `BfrService::signiereAusstehende()` verarbeitet jetzt `typ IN ('verkauf','storno')`, führt den Zähler beim Durchlaufen der Liste lokal mit (`$zaehler`) und persistiert ihn nach jeder erfolgreichen Signatur.
- `signiereEinzelbeleg()` prüft VOR dem Senden zusätzlich als Sicherheitsnetz: würde ein negativer Betrag den Zähler unter Null drücken? Wenn ja → `grund='zaehler_negativ'`, gar kein Request an den BFR geschickt.
- `KassenService::storniereBon()` bekommt dieselbe Steuergruppen-Berechnung wie `erstelleBon()` (negative Menge → negative Beträge je Steuergruppe, läuft automatisch durch `steuerGruppenAusPositionen()`) und ruft nach dem Commit ebenfalls `signiereAusstehende()` auf.
- `markiereFehler()` umgestellt: nur noch reiner Verbindungsfehler (`grund='verbindung'`) bleibt `ausstehend` (Auto-Retry); alles andere (abgelehnt, Zähler-Sperre) → `fehler`.

**Nebenbefund/Bugfix:** `Logger::log()` fiel in `markiereFehler()` auf `$_SESSION` zurück, das in Hintergrund-Kontexten (Nachsignierung, künftiger Cronjob) nicht gesetzt ist → Warnung + fehlgeschlagener Insert (durch try/catch abgefangen, hat aber den Log-Eintrag verschluckt). Gefixt: `BfrService::SYSTEM_BENUTZER_ID` (= 2, "Jarvis") wird jetzt immer explizit an `Logger::log()` übergeben. **Gleicher Bug existiert unbehoben in `cron/mahnwesen.php`** (dort ruft `Logger::log()` auch ohne benutzerId und ohne Session-Setup) — nicht heute gefixt, nur entdeckt.

### Korrektur nach Rückfrage: Zähler-Sperre VOR statt NACH der Belegerstellung

Jacky hat zurecht eingewendet: würde ein Storno "erfolgreich" angelegt, aber die Signatur schlägt an der Zähler-Sperre fehl, hätte der gedruckte Beleg trotzdem den Text "Sicherheitseinrichtung ausgefallen" bekommen — obwohl der BFR dabei völlig funktionsfähig war und wir selbst aus eigener Business-Logik abgelehnt haben. Das wäre inhaltlich falsch und vermutlich nicht zulässig.

**Fix:** `BfrService::wuerdeUmsatzzaehlerNegativWerden(kasseId, betrag): bool` — neue öffentliche Methode, die denselben Zähler-Check VORAB durchführt (ohne DB-Schreibzugriff). `KassenService::storniereBon()` ruft das ganz am Anfang auf, noch vor `beginTransaction()`: wenn true, wird der Storno komplett abgelehnt (wie "Bon bereits storniert" — kein Datensatz, kein Druck, klare Fehlermeldung an der Kasse). Der Check in `signiereEinzelbeleg()` bleibt zusätzlich als Sicherheitsnetz bestehen (z.B. gegen seltene Wettlaufsituationen), sollte aber durch den Vorabcheck praktisch nie mehr greifen.

**Der zweite `fehler`-Fall bleibt vorerst unverändert:** Lehnt der BFR eine Anfrage aktiv ab (`Result RC != 'OK'`, Gerät erreichbar), lässt sich das nicht vorab prüfen — Beleg wird wie bei einem Ausfall mit "Sicherheitseinrichtung ausgefallen" gedruckt, `bfr_status='fehler'` für die Nacherfassungs-Seite. Pragmatische Übergangslösung bis geklärt ist, ob das rechtlich korrekt ist oder anders behandelt werden muss.

Live getestet: normaler Storno (Zähler korrekt reduziert, signiert), Vorabcheck mit künstlich niedrigem Zähler (Storno komplett abgelehnt — kein Beleg, kein Druck, Original-Bon bleibt unstorniert, Zähler unverändert).

**X-Bon/Z-Bon — GEKLÄRT 2026-07-02: NICHT signaturpflichtig.** Bestätigt von Jacky: nach österreichischer RKSV sind X-Bon/Z-Bon reine interne Berichte, keine eigenen Belege. Manche Kassenhersteller drucken beim Z-Bon zusätzlich einen Nullbeleg mit ab — bewusst NICHT übernommen, da die monatliche + manuelle Nullbeleg-Abdeckung schon ausreicht. Zusätzlich hängt der BFR ohnehin am Registrierkassen-FinanzOnline-Webservice mit Online-Backup — X-Bon/Z-Bon-Anbindung ist damit endgültig von der Liste, nicht nur aufgeschoben.

## Nacherfassungs-Seite (2026-07-02, gleicher Tag ergänzt) — FERTIG

`public/kasse/nacherfassung.php` (+ `nacherfassung_retry.php`), Link "🔏 RKSV" in der Kasse-Kopfnavigation (`shell_top.php`). Drei Abschnitte:

1. **Offene/fehlgeschlagene Belege** (`bfr_status IN ('ausstehend','fehler')`) — Bon-Nr, Kasse, Typ, Betrag, Status, Grund, "Nochmal versuchen"-Button bei `fehler`.
2. **Offene/fehlgeschlagene Nullbelege** — gleiche Struktur.
3. **Nachsignierungsläufe** (die Sammelbeleg-Liste) — Kasse, ausgelöst durch, gestartet/beendet, Anzahl signiert/fehlgeschlagen, mit Drill-down (`?lauf_id=X`) auf die einzelnen Belege dieses Laufs.

**Migration 103:** `bfr_fehlergrund` VARCHAR an `kassen_bons` und `bfr_nullbelege` — vorher stand der Fehlgrund nur verschachtelt im `aktivitaeten`-Log (JSON), nicht direkt am Beleg abfragbar. `BfrService::grundText()` wandelt den internen `grund`-Code in lesbaren Text, wird beim Fehlschlag gespeichert und bei erfolgreicher (Nach-)Signatur wieder auf NULL gesetzt.

**`BfrService::retryBeleg()`/`retryNullbeleg()`** — setzen den Datensatz zurück auf `ausstehend` und stoßen die ganz normale `signiereAusstehende()`-Logik erneut an (Reihenfolge-Regel bleibt automatisch gewahrt, kein Sonderpfad für den manuellen Retry).

**Nebenbei gefixt:** Nullbeleg-Fehlerstatus-Logik hatte noch den alten (inkonsistenten) `grund === 'abgelehnt'`-Vergleich wie ursprünglich bei Verkaufsbons — jetzt auf dieselbe Regel umgestellt wie bei `markiereFehler()` (nur `'verbindung'` bleibt `ausstehend`, alles andere `fehler`).

Live getestet: Seite zeigt offene Fehler-Belege + Nachsignierungslauf korrekt an, Retry-Button setzt zurück und signiert erfolgreich neu, Drill-down auf einen Lauf zeigt die zugehörigen Belege.

## Kassen-Registrierung + Aktiv-seit-Stichtag (2026-07-02, gleicher Tag ergänzt) — FERTIG

**Der eigentliche Auslöser:** Jacky ist beim Testen aufgefallen, dass `signiereAusstehende()` bei jedem Aufruf ALLE `bfr_status='ausstehend'` Belege einer Kasse aufgreift — unabhängig davon, ob zum Zeitpunkt der Beleg-Erstellung überhaupt schon die aktuelle BFR-Registrierung (Kassen-ID) aktiv war. Zwei problematische Fälle: (1) historische Belege von vor der BFR-Einführung, (2) Belege einer alten Kassen-ID nach einem Hardware-Wechsel (BFR/Kassen-ID ist immer hardwaregebunden — bei Tausch wird die alte bei der Finanz abgemeldet, eine neue vergeben, **der Umsatzzähler beginnt für die neue Kassen-ID wieder bei 0**).

**Wichtige Korrektur unterwegs — reale BFR-Installationsanleitung gelesen** (`D:\ERP\mealana\import\BFR_Installationsanleitung.pdf`, bonit.at Software BFR, für andere Kassensoftware aber technisch fast sicher übertragbar): Der **Startbeleg wird vom BFR selbst erstellt** (im BFR-Admin-Tool durch den Techniker, Schritt 7 der Anleitung) — **nicht** von unserer Kassensoftware über `/register`. Genauso erstellt BFR **Monats-/Jahres-Nullbelege automatisch selbst intern**, sobald der erste Beleg eines neuen Monats zur Signatur ankommt, und auch den "Sammelbeleg nach Ausfall" erstellt BFR eigenständig. Unsere eigene Nullbeleg-Logik (weiter oben) macht also etwas, das der BFR ohnehin schon selbst erledigt — laut Jacky bewusst als Redundanz akzeptiert ("besser doppelt als vergessen"), nicht rückgebaut.

**Datenmodell — Migration 104:**
- `kassen.bfr_aktiv_seit` DATETIME — der Stichtag. Alle Signier-/Auflistungs-Abfragen filtern zusätzlich `erstellt_am >= bfr_aktiv_seit` (in `signiereAusstehende()` und `offeneBelege()`). Ist `bfr_aktiv_seit` NULL, matcht die Bedingung nichts — sicherer Default.
- `bfr_kassen_registrierungen` — eigene Tabelle, **kein Workflow den unsere Software selbst ausführt**, sondern ein reines Protokoll/Backup-Formular: Jacky macht die eigentliche FinanzOnline-Meldung + Startbeleg-Prüfung im echten BFR-Admin-Tool, und hakt das bei uns zusätzlich ab (3 Checkboxen mit Zeitstempel: Zertifikat gemeldet, Kasse gemeldet, Startbeleg geprüft) — zweite unabhängige Aufzeichnung, nicht die Quelle der Wahrheit.

**`BfrService::leseZertifikatInfo()`** — liest `/state`, zerlegt das `SC`-Feld (z.B. `ATU65033000:AT1:5619064c`) automatisch in UID-Nummer/Vertrauensdiensteanbieter/Zertifikat-Seriennummer (Hex + daraus berechnet Dez) — spart manuelle Abtipperei, mit echten Beispielwerten aus der Anleitung gegengetestet (Dez 1444480588 / Hex 5619064c stimmen exakt).

**Ablauf auf `public/einstellungen/kasse_registrierung.php`** (+ `kasse_registrierung_speichern.php`): Kassen-ID/BFR-URL eintragen → "Von /state abrufen" befüllt UID/Zertifikat automatisch → 3 Checkboxen abhaken → "Speichern" (Zeitstempel bleiben beim erneuten Speichern erhalten, nicht auf "jetzt" zurückgesetzt) → "Abschließen" (nur wenn alle 3 gesetzt) überträgt Kassen-ID/BFR-URL auf `kassen`, setzt `bfr_aktiv_seit=NOW()` und **`bfr_umsatzzaehler=0.00`** zurück, sperrt die Registrierung. Ist eine Registrierung abgeschlossen, zeigt die Seite nur noch eine schreibgeschützte Zusammenfassung + Button "Neue Kassen-ID anfordern (Hardware-Wechsel)" — startet einen neuen Entwurf, die alte Registrierung bleibt unangetastet als Historie, die Kasse arbeitet bis zum Abschluss der neuen Registrierung mit der alten Kassen-ID weiter.

**`kasse_edit.php`/`kasse_speichern.php`:** `rksv_kassen_id`/`bfr_url` sind dort jetzt nur noch schreibgeschützte Anzeige + Link zur Registrierungs-Seite — nicht mehr über das normale Kassen-Speichern editierbar (hätte sonst bei jedem Speichern versehentlich überschrieben werden können).

**Live getestet (kompletter Durchlauf über die echten Web-Routen):** Registrierung anlegen → `/state` abrufen (Zertifikatsdaten korrekt geparst) → Abschließen ohne Checkboxen korrekt abgelehnt → mit allen 3 Checkboxen erfolgreich abgeschlossen, Kasse aktiviert, Zähler auf 0 → **Kernfix bestätigt:** zwei "historische" Test-Belege (einer 1 Monat alt, einer nur *4 Minuten* vor `bfr_aktiv_seit` erstellt) blieben beide korrekt `ausstehend` und tauchten auch auf der Nacherfassungs-Seite nicht auf, während ein Beleg nach dem Stichtag korrekt signiert wurde → Hardware-Wechsel-Flow ("Neue Kassen-ID anfordern") legt sauber einen neuen Entwurf an, alte Registrierung bleibt als Historie, Kasse läuft bis zum Abschluss unverändert weiter.

## Cronjob (2026-07-02, gleicher Tag ergänzt) — FERTIG

`cron/bfr_nachsignierung.php`, empfohlen alle 5 Minuten (Windows Task Scheduler / crontab, Doku-Header wie bei `cron/mahnwesen.php`). Pro aktiver Kasse mit gesetzter `bfr_url`: erst `sicherstelleMonatsNullbeleg()`, dann `signiereAusstehende($kasseId, 'cronjob')` — dieselbe Logik wie beim normalen Verkauf, nur eben proaktiv statt an einen Verkauf gekoppelt. Live getestet: Kasse ohne `bfr_url` wird korrekt übersprungen, mit BFR konfiguriert holt der Cron offene Belege + fehlenden Monats-Nullbeleg korrekt nach.

## QR-Code als echtes Bild (2026-07-02, gleicher Tag ergänzt) — FERTIG

Composer-Paket `endroid/qr-code` (^6.0, PHP-8.1-kompatible Version — die neueste 6.1.x braucht PHP 8.4) installiert. Neuer Helfer `src/core/QrCode.php`: `QrCode::dataUri($inhalt, $groesse)` erzeugt bei jedem Druck live einen PNG-Data-URI aus dem schon gespeicherten `rksv_qr`-Inhalt.

**Bewusst keine Bilddatei gespeichert** — Jacky hatte gefragt ob das "overdone" wäre: Erzeugung dauert nur Millisekunden, eigenes Datei-Handling (Pfade, Aufräumen, Speicherplatz) hätte keinen echten Vorteil gebracht. Live neu rendern aus dem gespeicherten Inhalt ist die einfachere Lösung.

`bon_druck.php` (80mm) zeigt jetzt ein eingebettetes `<img>` mit dem QR-Code statt der reinen Textzeile; `bon_a4.php` genauso (Dompdf rendert `data:`-URIs problemlos, auch mit `isRemoteEnabled=false`, da kein externer Fetch nötig ist). Getestet: beide Druckansichten liefern ein valides PNG (Magic-Bytes geprüft) korrekter Größe.

**Damit ist die komplette ursprüngliche RKSV/BFR-Liste durch:** BfrService-Kern, Verkauf+Storno-Signierung, Nachsignierung mit Sammelbeleg-Protokoll, Nullbeleg (monatlich+manuell), Umsatzzähler-Sperre, Nacherfassungs-Seite, Kassen-Registrierung mit Aktiv-seit-Stichtag, Cronjob, echter QR-Code auf beiden Druckvorlagen.

## Noch offen (kleinere Restpunkte, kein Blocker)

- Rückfrage an Hersteller: ist "Sicherheitseinrichtung ausgefallen" die korrekte Behandlung, wenn der BFR erreichbar ist aber eine Anfrage aktiv ablehnt (RC != 'OK')? Aktuell nur pragmatisch wie ein Ausfall behandelt.
- `cron/mahnwesen.php`: gleicher Logger/$_SESSION-Bug wie oben beschrieben, bei Gelegenheit fixen

**Why:** RKSV verlangt lückenlose, aufsteigend signierte Belege; bei Geräteausfall muss trotzdem weiterverkauft werden dürfen (§8), aber die Nachsignierung muss korrekt und nachvollziehbar ablaufen.

**How to apply:** Bei Fortsetzung (Storno/X-Z-Bon/Cronjob/Nacherfassungs-Seite) dieses Dokument als Grundlage nehmen — die Kernlogik in `BfrService` ist bereits vollständig wiederverwendbar, es fehlen nur weitere Aufrufer.
