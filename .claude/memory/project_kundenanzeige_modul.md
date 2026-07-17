---
name: project-kundenanzeige-modul
description: "Geplantes Modul: Kundenanzeige/Info-System für Tablet im Vollbild-Browser an der Kasse — Konzept von Jacky 2026-07-07, Feindesign steht noch aus"
metadata: 
  node_type: memory
  type: project
  originSessionId: 1018675b-06b0-4bee-b923-24fdc5ebd59a
---

## Idee (Jacky, 2026-07-07)

Eigenständige PHP/HTML-Seite, gedacht für ein Tablet das per Vollbild-Browser neben der Kasse steht (Kundenseite, klassisches Doppel-Display-POS-Konzept).

**Drei Zustände:**
1. **Ohne laufenden Verkauf:** mittig "Herzlich willkommen bei ..." (Text konfigurierbar).
2. **Während eines Verkaufs:** links Artikelbild + ein paar Infos zum aktuell gescannten Artikel (aus der Artikel-DB), rechts ein simulierter Kassenbon (Art.-Nr., Name, Einzelpreis, Gesamtpreis, Steuersatz, unten Gesamtsumme) — läuft synchron zur Kasse mit.
3. **Beim Abrechnen:** groß "zu zahlen" bzw. Gegeben/Rückgeld; danach evtl. QR-Code fürs papierlose Zahlen (Bezug zu [[project_paperless_rechnung_modul]] noch zu klären — eigener Zahlungs-QR oder derselbe Beleg-QR?); nach 30 Sek. oder mit Beginn der nächsten Buchung zurück zu "Herzlich willkommen".

**Why:** Klassisches POS-Feature, erhöht Kundenerlebnis/Preistransparenz an der Kasse.

## ✅ Feindesign-Konzept durchgesprochen (2026-07-10), Stufe 1 (ASCII) abgenommen — noch nicht gebaut

Referenz-Check vorher gemacht (JTL-Kasse/Shopware POS/Lightspeed/Square-Kundendisplays): Idle → Live-Warenkorb-Spiegel → Zahlungsbetrag/Rückgeld → zurück zu Idle ist branchenüblich, deckt sich mit Jackys Ursprungsidee. Keine fehlende Standard-Funktion gefunden, die ergänzt werden müsste.

**Sync-Mechanismus (entschieden):** Kasse schreibt Warenkorb-Stand bei jeder Änderung in eine kleine DB-State-Tabelle (`kassen_live_state` o.ä.), Kundenanzeige-Tablet pollt alle ~1s per AJAX. Bewusst gegen WebSocket entschieden (kein neuer dauerhaft laufender Serverprozess nötig) — passt zum bestehenden Muster (Arbeitsplatz-Heartbeat, `kassen_geparkte_bons`). Grund für DB statt reinem Server-RAM: der Warenkorb existierte bisher NUR im Browser-JS der Kasse (`bon.php`), nirgends serverseitig außer beim expliziten Parken — für die Anzeige muss die Kasse ihn also aktiv irgendwo ablegen.

**Pairing (entschieden):** Kein eigenes Pairing-UI wie beim Arbeitsplatz-Konzept (das bindet ein Gerät an *seine eigene* Kassen-Identität, nicht "ich bin Zweitbildschirm von Kasse X"). Stattdessen simple `kasse_id` in der URL (z.B. `kundenanzeige.php?kasse_id=4`), einmal beim Einrichten im Tablet-Browser gebookmarkt. Passt zur aktuellen Größe (1-2 Kassen), spart eine eigene Pairing-UI.

**Layout — vier Zustände, zwei Spalten-Skelette wiederverwendet:**

1. **Willkommen (Idle):** zentriert, nur Logo + konfigurierbarer Willkommenstext. Bewusst KEINE rotierende Werbefläche/Aktionsartikel-Anzeige — Jacky: Aufwand würde sich für den Nutzen aktuell nicht rechnen. **Nice-to-have-Backlog**, kommt vielleicht irgendwann wenn Zeit ist.
2. **Live-Warenkorb (während Scan):** zweispaltig — links Artikelbild + Name/Variante/Einzelpreis des zuletzt gescannten Artikels, rechts laufende Bon-Liste (Art.-Nr/Name/Menge/Einzelpreis/Gesamt) + Zwischensumme/MwSt/Gesamt unten.
3. **Abrechnen, Paperless-QR INAKTIV:** zentrierter Dialog (keine zwei Spalten) — "Zu zahlen: X €", nach Zahlung "Gegeben/Rückgeld", dann Dank-Text.
4. **Abrechnen, Paperless-QR AKTIV** (vorausschauend mitgeplant, obwohl [[project_paperless_rechnung_modul]] selbst erst mit Online-Shop-Anbindung kommt): gleiches Zwei-Spalten-Skelett wie Zustand 2 — links QR-Code statt Artikelbild ("Bon/Rechnung per QR abholen"), rechts unverändert Betrag/Gegeben/Rückgeld. Jackys Begründung, das jetzt schon mitzuplanen statt erst bei Paperless-Start: das Layout ist dann idealerweise schon getestet/freigegeben, kein zweites Redesign nötig, spart Zeit.

**Timing zurück zu Idle:** sobald an der Kasse ein neuer Kassiervorgang gestartet wird, ODER spätestens nach 30 Sekunden — je nachdem was zuerst eintritt.

ASCII-Wireframes aller vier Zustände liegen im Chat-Verlauf vom 2026-07-10 (nicht separat als Datei abgelegt).

**✅ Design freigegeben 2026-07-10 (SVG-Stufe bewusst übersprungen):** Jacky hält das ASCII-Konzept für klar genug ("sehen sehr gut aus") und zeigt es Barbara lieber live statt eine SVG-Datei zu bauen — siehe Ausnahme in [[feedback_design_workflow]]. Design gilt damit als abgenommen, nur die Implementierung fehlt noch.

## ✅ V1 gebaut und funktional getestet (2026-07-10, gleicher Tag)

**Migration 125:** `kassen_live_state` (kasse_id PK, zustand ENUM idle/warenkorb/abrechnen, payload JSON, aktualisiert_am) — eine Zeile pro Kasse, kein Verlauf.

**`src/modules/kasse/KundenanzeigeService.php`:** `schreibeStatus()` (Upsert von der Kasse), `leseStatus()` (fürs Tablet — reichert bei Zustand 'warenkorb' das Hauptbild des Artikels an, `artikel_bilder` join, da die Kasse selbst keine Bildpfade kennt).

**Kasse-seitige Anbindung (`kasse/bon.php`):** neue Funktion `kdSync(zustand, payload)` (fire-and-forget POST, blockiert den Kassiervorgang nie). Zentraler Hook in `renderBon(skipKdSync)` — deckt automatisch JEDE Warenkorb-Mutation ab (Scan/±Menge/Rabatt/Preis-Override), da alle diese Stellen schon vorher `renderBon()` aufriefen. `_resetKasseState()` ruft jetzt `renderBon(true)` (Sync übersprungen), weil der eigentliche Verkaufsabschluss (`bonSpeichern()`-Erfolg) VOR dem Reset explizit `kdSync('abrechnen', {..., abgeschlossen:true})` sendet — sonst hätte der interne renderBon()-Aufruf beim Leeren des Warenkorbs den gerade gesendeten "Danke/Rückgeld"-Screen sofort wieder mit "idle" überschrieben. `bezahlenDialog()` sendet beim Öffnen des Bezahlen-Popups `zustand='abrechnen'` (Betrag, noch ohne Gegeben/Rückgeld). `abschliessenOhneBon()` (Auftrag ohne Zahlung abschließen) sendet explizit `idle` danach, da dieser Pfad `_resetKasseState()`'s Sync-Skip sonst nie auslösen würde.

**Bewusst nicht abgedeckt (Scope-Disziplin):** die Sonderdialoge "exakt bezahlt"/"Retour bar auszahlen" (`ov-bezahlt-info`/`ov-retour-bar` in `bezahlenDialog()`) lösen noch keinen eigenen Abrechnen-Sync aus — Kundenanzeige zeigt in diesen seltenen Fällen weiter den letzten Warenkorb-Stand. Kann bei Bedarf nachgezogen werden.

**Pairing:** `kundenanzeige/index.php?kasse=K1` — löst die **Kassen-Nummer** (nicht die interne ID) zur `kasse_id` auf, weil die Nummer im Kasse-Header sichtbar und beim Tablet-Einrichten leichter abzulesen ist (Jackys Wunsch, 2026-07-10). `public/kundenanzeige/` bewusst OHNE `auth_check.php` (Kiosk-Tablet ist nie eingeloggt) — sowohl die Seite selbst als auch `ajax_status.php` sind offen erreichbar, geben aber nur den laufenden Warenkorb preis, keine Kundendaten.

**Polling:** `ajax_status.php?kasse_id=X` alle 1s. Lokale 30s-Idle-Logik läuft rein im Tablet-JS (kein Server-Push nötig): sobald `zustand='abrechnen'` mit `abgeschlossen:true` beobachtet wird, startet ein lokaler 30s-Timer zurück zu Idle; ändert sich `aktualisiert_am` vorher (= Kassiererin hat etwas Neues getan, z.B. nächster Scan), wird der Timer verworfen und normal weitergerendert — deckt exakt Jackys Regel "neuer Kassiervorgang ODER 30 Sekunden" ab, ohne dass die Kasse selbst einen Timer verwalten muss.

**QR-Layout vorausgebaut, echte QR-Erzeugung fehlt noch (bewusst, Paperless kommt separat):** neue Einstellung `kundenanzeige_qr_aktiv` (Checkbox, Einstellungen → System → Kundenanzeige) schaltet zwischen zentriertem Dialog und dem geteilten Layout (links Platzhalter-Box "▦" + Hinweistext, rechts Betrag/Gegeben/Rückgeld) um — Layout ist damit fertig getestet, nur der echte QR-Code-Inhalt fehlt bis zum Paperless-Modul.

**Getestet (2026-07-10):** `KundenanzeigeService::schreibeStatus()`/`leseStatus()` direkt per CLI gegen echte Dev-DB (Warenkorb-Zustand inkl. Bild-Lookup, Abrechnen-Zustand, QR-Toggle an/aus) — alle Antworten korrekt, danach vollständig aufgeräumt (Test-Zeile gelöscht, `kundenanzeige_qr_aktiv` zurück auf '0'). `ajax_status.php`/`index.php` per echtem HTTP-Request gegen den laufenden XAMPP-Server geprüft (Aufruf ohne Login funktioniert wie vorgesehen). **Nicht getestet:** echter Browser-Durchlauf (Polling-JS, Timer-Verhalten, Layout auf echtem Tablet), `ajax_kundenanzeige_sync.php` nicht separat mit echter Login-Session getestet (folgt aber 1:1 dem bereits bewährten Auth-Muster der übrigen `kasse/ajax_*.php`-Endpunkte).

**Noch offen:** echter Tablet-Test (Barbara-Demo als nächster echter Praxistest geplant), Feintuning nach deren Feedback, später echte QR-Erzeugung mit dem Paperless-Modul.

## Bugfixes am selben Tag (2026-07-10, Jackys erster echter Tablet-Test)

1. **PHP-Warning `Undefined array key` in `index.php`:** `?:` statt `??` bei `$einstellungen['kundenanzeige_willkommenstext']` — Key fehlte schlicht, solange die Einstellung nie gespeichert wurde. Fix: erst mit `??` auf `''` prüfen, dann Fallback-Text setzen.
2. **Tablet zeigte danach dauerhaft eine schwarze Seite ohne jede Anzeige, PC aber schon den Fix:** `kundenanzeige/`-Seiten hatten keine Cache-Header — Browser cachte die alte (kaputte) Antwort. Besonders kritisch, weil das nicht nur den einen Vorfall betraf: **auch das Live-Polling** (`ajax_status.php`) hätte ohne No-Cache-Header irgendwann auf einem eingefrorenen Stand hängen bleiben können, nicht nur beim ersten Laden. Fix: `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` (+ Pragma/Expires) jetzt auf `index.php` UND `ajax_status.php`, gleiches Muster wie `auth_check.php` es für die normale ERP-Shell schon macht.
3. **Vollbild-Versuch per Web-App-Manifest (`manifest.php`, `display:fullscreen`, dynamischer `start_url` mit `?kasse=`) hat auf Jackys altem Tablet NICHT funktioniert** — "Zum Startbildschirm hinzufügen" zeigte weiterhin Adressleiste/Tabs (vermutlich zu alte Chrome-/WebView-Version für `display:fullscreen`-Unterstützung). Der Code-Teil (Manifest + Meta-Tags) bleibt drin, schadet nicht und funktioniert eventuell auf neueren Geräten — **aber für den aktuellen Praxiseinsatz hat sich Jacky für Fully Kiosk Browser entschieden** (externe App, nicht von uns gebaut). Setup: Start-URL `.../kundenanzeige/?kasse=K1`, Vollbild/Statusleiste-ausblenden aktivieren, Motion Detection/Screensaver aus, kein Auto-Reload nötig (Seite pollt selbst). **✅ Läuft (2026-07-10, von Jacky bestätigt).**

**Why:** Erster echter Praxistest deckte typische "sieht in der Theorie fertig aus, scheitert am echten Gerät"-Probleme auf — Cache-Verhalten und Fullscreen-API-Support sind beides Dinge, die sich nicht am PC/per curl testen lassen.
**How to apply:** Bei künftigen kiosk-artigen Seiten (kein Login, Dauerbetrieb) von Anfang an No-Cache-Header mitbauen, nicht erst nachträglich. Fullscreen-Erwartung bei älteren Android-Tablets nicht voraussetzen — Fully Kiosk (oder ähnliche Kiosk-Launcher) als Standard-Empfehlung für echte Ladengeräte einplanen, Manifest-Trick nur als Bonus für neuere Geräte.

**How to apply:** Zeitliche Einordnung unverändert (Jacky, 2026-07-07): Kassen-Thema, ursprünglich NACH BFR-Hardware-Test eingeplant — der Hardware-Test lief zwar schon (2026-07-08), wartet aber noch auf die Herstellerantwort (siehe [[project_rksv_bfr]]), daher wurde das Konzept-Gespräch vorgezogen ohne auf den kompletten BFR-Abschluss zu warten. Implementierung kann jederzeit starten, Design ist fertig abgenommen.
