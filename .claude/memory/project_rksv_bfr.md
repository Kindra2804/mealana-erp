---
name: project-rksv-bfr
description: "RKSV/BFR-Integration — 2026-07-06 komplett umgebaut: State-Check-Gate ('kein Dienst, keine Kasse') ersetzt die alte Nachsignierungs-Warteschlange; Ausfall-Episoden statt Nachsignierungsläufe; Kassen-Registrierung/QR-Code/X-Z-Bon-Regeln unverändert"
metadata:
  node_type: memory
  type: project
  originSessionId: eefd559b-9c02-443d-a0cb-164e3dadf876
---

## Status: Grundarchitektur am 2026-07-06 komplett neu gebaut (nach echtem Hardware-Test)

Der reale BFR-Hardware-Test deckte ein Reihenfolge-Risiko der ursprünglichen Logik auf (siehe [[bug_charge_tracking]]-ähnliche Kategorie, aber RKSV-spezifisch): Sobald ein Beleg beim alten Nachsignierungs-Modell in den Zustand `fehler` geriet, fiel er aus der automatischen Retry-Abfrage (`WHERE bfr_status='ausstehend'`) raus — spätere Belege konnten dadurch am gescheiterten vorbeigezogen werden und dem BFR eine kleinere Belegnummer NACH einer größeren schicken. RKSV verlangt aber eine strikt aufsteigende Belegfolge. Komplette Historie der alten Architektur (Nachsignierungsläufe, `bfr_status`, Migrationen 100-104) ist nicht mehr relevant — siehe git-History der Migrationen 100-103 falls das Archäologie-Interesse besteht.

## Neues Modell: State-Check-Gate ("kein Dienst, keine Kasse")

Empfehlung des BFR-Herstellers, von Jacky übernommen: VOR jeder Bon-/Storno-/Nullbeleg-Erstellung wird `/state` geprüft. Ist der Dienst nicht erreichbar, wird die Buchung **gar nicht erst zugelassen** — kein unsignierter Beleg entsteht mehr, keine Warteschlange nötig.

**Warum das reicht:** Laut echter Hersteller-API-Doku (`BFR_API.html`, von Jacky nachgeliefert) ist `RC` bei `/register` **immer** `"OK"` — das einzige Unterscheidungsmerkmal ist `<Link>`: entweder die Steuerkennung (echte Signatur) oder der Text `"Sicherheitseinrichtung ausgefallen"`. Ist der Dienst also erreichbar (State-Check bestanden), kommt von `/register` **garantiert** eine der beiden Antworten — nie ein drittes "unentschieden". Und: fällt nur die Signaturkarte aus (Dienst läuft, Karte kaputt), kümmert sich der BFR laut Installationsanleitung selbst vollständig um den späteren Sammelbeleg — das ist explizit NICHT unser Problem, wir müssen so einen Beleg nie erneut senden.

**Zwei-Stufen-Popup an der Kasse** (ASCII-Wireframe gemeinsam entworfen, dann direkt umgesetzt — SVG-Zwischenschritt bewusst übersprungen, da reine Diagnose-/Sperr-Anzeige, keine Merchandising-Fläche):
1. Stiller Kurz-Retry (2× mit 300ms Pause, Connect-Timeout 400ms) — im Normalfall unsichtbar.
2. Scheitert das: Vollbild-Overlay "Dienst nicht erreichbar!" mit Button "Erneut versuchen".
3. Scheitert der erneute Versuch auch: eskalierte Ansicht "Dienst immer noch nicht erreichbar" mit Diagnose-Hinweisen (Taskleiste/Kartenleser/Windows-Update), Kasse bleibt gesperrt bis erfolgreich (Schleife).
4. Kassenstart bekommt denselben Gate-Check; ist dabei eine Ausfall-Episode offen, wird still ein Recovery-Nullbeleg versucht (kein eigenes Popup dafür).

**Wichtiger Timing-Fund beim Testen:** Ein toter lokaler Port antwortet auf diesem Windows-Dev-Rechner NICHT sofort mit "connection refused", sondern erst nach ~2 Sekunden (vermutlich Windows-Sicherheitssoftware) — ohne explizites `CURLOPT_CONNECTTIMEOUT_MS` hätte der stille Kurz-Retry-Burst ~7s statt der geplanten <1s gedauert. Fix: `CONNECT_TIMEOUT_MS=400` separat vom normalen Request-Timeout (`TIMEOUT_SEKUNDEN=5`) gesetzt. **Ob das auf der echten Live-Maschine mit BFR genauso auftritt, ist nicht getestet** — falls das Popup dort spürbar langsamer reagiert als erwartet, ist das der erste Verdächtige.

## Ausfall-Episoden statt Nachsignierungsläufe

**Migration 112:** `bfr_nachsignierungs_laeufe` gedroppt, `kassen_bons.bfr_status`/`bfr_fehlergrund`/`nachsignierungs_lauf_id` und `bfr_nullbelege.bfr_status`/`bfr_fehlergrund` entfernt (waren unter dem neuen Modell nur noch Konstanten — jeder existierende Bon hat zwangsläufig ein Signatur-Ergebnis). Ersatz: **`bfr_ausfaelle`** (eine Zeile pro durchgehender Störung, `geloest_am IS NULL` = noch aktiv) + **`bfr_ausfall_ereignisse`** (Einzelvorfälle: Typ `dienst_nicht_erreichbar`/`sicherheitseinrichtung_ausgefallen`, betroffene `bon_nr`, Zeitstempel).

**Wie erkannt wird, ob ein Bon "signiert" oder "ausgefallen" ist:** kein Status-Feld mehr nötig — `rksv_signatur` ist entweder die echte Steuerkennung, der Text "Sicherheitseinrichtung ausgefallen", oder (nur wenn `bfr_url` für die Kasse leer ist) NULL. Druckvorlagen (`bon_druck.php`, `BonA4Renderer.php`) prüfen jetzt nur noch `!empty($bon['rksv_signatur'])` — **dabei nebenbei einen echten Vorab-Bug gefunden und gefixt**: die alten Vorlagen zeigten "Sicherheitseinrichtung ausgefallen" sogar bei Kassen OHNE jede BFR-Anbindung (Dev/Test-Kassen), weil nie geprüft wurde ob überhaupt eine `bfr_url` konfiguriert ist.

**24h/48h-FON-Meldepflicht (von Jacky als sicher bestätigt, aus Erfahrung, nicht extra nachgeprüft):** Läuft eine Episode länger als 24h, zeigt `nacherfassung.php` (jetzt "Ausfall-Historie" statt "Nacherfassung") eine Warnung — ein Tag Puffer bevor mit 48h durchgehendem Ausfall die FinanzOnline-Meldepflicht greift. Die 48h-Uhr läuft schon ab dem ersten fehlgeschlagenen `/state`-Check, nicht erst ab dem ersten betroffenen Bon (Jacky bestätigt: "ab Kenntnis des Ausfalls").

**`cron/bfr_nachsignierung.php`** (Name beibehalten, Inhalt komplett neu): läuft weiterhin alle 5 Min, macht aber nur noch zwei Dinge — Monats-Nullbeleg absichern (`sicherstelleMonatsNullbeleg()`) und für jede Kasse mit offener Episode denselben Kassenstart-Recovery-Versuch auslösen (`pruefeKassenstart()`), falls eine Störung tagelang läuft ohne dass jemand die Kasse neu startet.

**Nullbeleg-Fehlschläge hinterlassen keinen Datensatz mehr** (anders als vorher): schlägt `sicherstelleMonatsNullbeleg()` fehl (State-Check negativ), wird einfach nichts angelegt — der nächste Versuch bekommt automatisch eine neue Belegnummer. Sicher, weil Nullbeleg-Nummern nie mit echten Bon-Nummern kollidieren und mehrere Nullbelege pro Monat unproblematisch sind (RKSV verbietet keine Extras).

## Was UNVERÄNDERT geblieben ist

- **Kassen-Registrierung + Aktiv-seit-Stichtag** (Migration 104): komplett unangetastet, `bfr_aktiv_seit`-Filter, Hardware-Wechsel-Flow, `bfr_kassen_registrierungen`-Protokoll — siehe unten im Detail.
- **Umsatzzähler-Sperre bei Storno**: `wuerdeUmsatzzaehlerNegativWerden()` als Vorabcheck vor `beginTransaction()` unverändert übernommen (bewährtes Muster, jetzt auch Vorbild für den neuen State-Check-Vorabcheck).
- **QR-Code als echtes Bild** (`endroid/qr-code`, `QrCode::dataUri()`): unverändert.
- **X-Bon/Z-Bon nicht signaturpflichtig**: unverändert, bleiben außen vor.
- **Monatlicher statt täglicher Nullbeleg**: Jackys ursprüngliche Entscheidung unverändert, nur der technische Unterbau (kein `bfr_status` mehr) ist neu.
- **Offline-Messe-Kasse** (`kasse_bon_offline.js`, direkter Browser→BFR-Call ohne Server-Umweg): bekam dieselbe State-Check-Gate-Logik nachgezogen (JS-seitig repliziert, da eigener Codepfad ohne PHP-Backend-Aufruf zur Verkaufszeit).

## Umsatzzähler zählt IMMER mit — auch bei "ausgefallen" (von Jacky korrigiert, 2026-07-06)

Erste Version hatte das falsch: Zähler wurde nur bei echter Signatur erhöht. Jackys Korrektur: Der Betrag wird auch bei "Sicherheitseinrichtung ausgefallen" an den BFR übermittelt und dort gespeichert — genau das ist ja die Grundlage für den späteren Sammelbeleg (der laut Installationsanleitung inhaltlich einem Nullbeleg mit dem dann aktuellen Umsatzzähler-Stand entspricht). Es fehlt nur die eigentliche kryptografische Signatur, nicht die Erfassung des Umsatzes selbst. `BfrService::signiereBeleg()` erhöht `bfr_umsatzzaehler` deshalb jetzt unabhängig vom `ausgefallen`-Flag, direkt nach jedem `/register`-Aufruf.

## ✅ Rohdaten-Kommunikationsprotokoll für Hersteller-Fehlermeldungen (2026-07-09)

Jacky machte gezielte Ausfall-Tests mit BFR (mehrfacher Absturz, zwei Episoden 09:12-09:16 und 09:51-10:01) und wollte danach das exakte Request-/Response-XML für eine präzise Fehlermeldung an den Hersteller — gab es bisher NICHT: `BfrService::httpGet()`/`httpPost()` gaben nur die geparste Antwort zurück, nirgends wurden die rohen Bytes gespeichert (nur `bfr_ausfaelle`/`bfr_ausfall_ereignisse` mit Typ/Zeitstempel/Bon-Nr, kein XML).

**Migration 122**: neue Tabelle `bfr_kommunikation_log` (kasse_id, endpunkt state/register, request_body, response_body, curl_fehler, dauer_ms, erstellt_am). `BfrService::httpGet()`/`httpPost()` protokollieren jetzt JEDEN Aufruf automatisch (neue private Methode `protokolliereKommunikation()`, fängt eigene Fehler ab — darf nie eine echte Buchung zum Scheitern bringen). `kasse_id` wird durch alle 6 Aufrufer-Methoden durchgereicht (`pruefeVorBuchungIntern`, `versucheRecoveryNullbeleg`, `signiereBeleg`, `erstelleNullbeleg`, `sicherstelleMonatsNullbeleg`, `leseZertifikatInfo` — letztere auch von `kasse_registrierung_speichern.php` aus mit `$kasseId` versorgt).

**Neue Ansichtsseite `kasse/bfr_log.php`** (verlinkt von `nacherfassung.php`): Filter nach Kasse + Anzahl, pro Aufruf Zeitstempel/Dauer/Erfolg-Badge + vollständiges Request- und Response-XML in kopierbaren `<pre>`-Blöcken.

**Wichtig — nicht rückwirkend:** Die beiden Ausfälle von heute Vormittag sind nur noch als grobe Episode rekonstruierbar (Zeitstempel, Typ, Bon-Nr aus `bfr_ausfall_ereignisse`), nicht mit exaktem XML. Für die Hersteller-Meldung müsste Jacky den Ausfall-Test mit dem jetzt aktiven Logging wiederholen.

**CLI-getestet** (Transaktion mit Rollback): simulierter Verbindungsfehler zu totem Port protokolliert korrekt (`curl_fehler` gesetzt, `response_body` NULL, `dauer_ms` erfasst).

**Why:** Ohne die exakten Bytes kann der Hersteller einen Absturz nicht nachvollziehen — die bisherige Ausfall-Episode ist für die interne FON-Meldepflicht gedacht, nicht für technischen Support.
**How to apply:** Bei jedem künftigen BFR-Problem zuerst `kasse/bfr_log.php` prüfen, bevor man den Hersteller kontaktiert — die letzten N Aufrufe sind dort immer vollständig einsehbar.

## ✅ Gemeinsam durchgeführter Ausfalltest (2026-07-09, direkt nach dem Logging-Bau) — Bericht an Hersteller raus, wartet auf Antwort

Kontrollierter Test (Karte während Betrieb gezogen), live von mir per DB-Query mitverfolgt, Ergebnis siehe unten. **Kernbefund, an den Hersteller gemeldet:** `/state` antwortete normal (1 ms, gültiges XML) unmittelbar bevor `/register` für denselben Vorgang komplett unbeantwortet blieb (Timeout nach 5003 ms, 0 Bytes) — widerspricht der Doku-Zusage "nach erfolgreichem State-Check kommt von /register garantiert eine Antwort". Genau das Szenario, vor dem das State-Check-Gate-Modell eigentlich schützen sollte, trat also in abgeschwächter Form trotzdem auf (nicht dass unsigniert verkauft wurde — das lief korrekt als "ausgefallen" — sondern dass der Zustandscheck selbst kein zuverlässiger Vorbote für den Absturz von `/register` war).

**Ablauf (Kasse 4, RN DEMOvNahtlOS):**
1. Normalverkauf 10€ (Bon K3-2026-000019) — echte Signatur, Zähler 302,85→312,85
2. Karte gezogen → nächster Verkauf 10€ (Bon K3-2026-000020): `/state` OK, `/register` Timeout/0 Bytes, Bon korrekt als "ausgefallen" gebucht, Zähler trotzdem 312,85→322,85 (zählt wie vorgesehen mit), neue Ausfall-Episode eröffnet
3. BFR neu gestartet, Karte wieder rein → beim nächsten Kassenstart lief automatisch ein Recovery-Nullbeleg (echte Signatur), Episode dadurch automatisch geschlossen
4. Normalverkauf 10€ (Bon K3-2026-000021) — echte Signatur, Zähler 322,85→332,85

**Alles funktionierte wie vorgesehen** (Umsatzzähler korrekt bei jedem Schritt, Episode korrekt eröffnet/automatisch geschlossen, kein Beleg verloren oder doppelt gezählt) — der einzige offene Punkt ist rein herstellerseitig (warum crasht `/register` trotz gesundem `/state`).

**Fehlerbericht** (vollständiges Zeitprotokoll + exaktes XML für den kritischen Moment) an Jacky übergeben, der ihn an den BFR-Hersteller weiterleitet. Datei lag nur im Session-Scratchpad (nicht dauerhaft im Projekt abgelegt) — falls die Herstellerantwort wichtige neue Erkenntnisse bringt, hier nachtragen.

**How to apply:** Bei der nächsten Session nachfragen, ob vom Hersteller schon eine Antwort kam — falls ja, hier ergänzen und ggf. Architektur-Anpassungen ableiten (z.B. falls der Hersteller einen anderen Timeout/Retry-Wert empfiehlt).

## Offene Punkte / nicht heute geklärt

- Timing-Fund (siehe oben, ~2s Verbindungsverzögerung zu totem Port) nicht auf der echten BFR-Maschine verifiziert.
- Storno läuft weiterhin über eine simple Redirect-Seite (`bon_stornieren.php`), bekam nur den Gate-Check + generische Fehlermeldung — NICHT das volle Zwei-Stufen-Popup wie beim Verkauf (das würde eine Umstellung auf AJAX brauchen, aus Zeitgründen nicht gemacht).

**Why:** RKSV verlangt lückenlose, aufsteigend signierte Belege; bei Geräteausfall muss trotzdem weiterverkauft werden dürfen (§8), aber die Reihenfolge darf dabei nie durchbrochen werden — genau das hat die alte Nachsignierungs-Warteschlange nicht zuverlässig genug garantiert.

**How to apply:** Bei jeder künftigen BFR-Änderung von diesem (neuen) Modell ausgehen, nicht von den alten Konzepten `bfr_status`/Nachsignierungslauf/Nacherfassung — die sind mit Migration 112 vollständig entfernt.

## ✅ Echter Hardware-Test durchgeführt (2026-07-08)

Erste echte Kasse mit `bfr_url` je live registriert (Demo-A-Trust-Karte, Kassen-ID `DEMOvNahtlOS`) — deckte vier reale Bugs auf, die dieses Architektur-Dokument nicht vorhersehen konnte, weil vorher nie ein Kasse-Datensatz durch diese Codepfade lief (u.a. `$steuer`-Variable in `erstelleBon()` überschrieben → falsche Steuergruppen im DEP; Negativ-Zähler-Sperre fehlte im Retour-Pfad). Volle Details in [[project_kassen_verwaltung]].

## ✅ `bfr_url` — Offline-Fix + Selbstheilung FERTIG (2026-07-09, am Folgetag umgesetzt wie entworfen)

Beide am 2026-07-08 entworfenen Fixe gebaut, noch nicht live auf echter Hardware getestet (kein BFR-Gerät in der Dev-Umgebung verfügbar — muss auf Jackys Testrechner verifiziert werden):

1. **Offline-Fix**: `kasse_bon_offline.js` hat jetzt `obBfrUrlLokal()` — liest nur den Port aus der (LAN-)`obKonfig.kasse.bfr_url` und baut daraus immer `http://127.0.0.1:<port>`. Ersetzt die beiden direkten `obKonfig.kasse.bfr_url`-Zugriffe in `obBfrErneutVersuchen()` und `obVerkaufAbschliessen()`.
2. **Selbstheilung**: `BfrService::heileUrlFuerKasse(kasseId, gemeldeteRn, beobachteteIp)` (neu) — vergleicht die gemeldete RN gegen `kassen.rksv_kassen_id`, aktualisiert bei Übereinstimmung `bfr_url` auf `http://<beobachteteIp>:<bisheriger Port>`. Neuer Endpunkt `kasse/ajax_bfr_heilung.php` (POST `rn`, ermittelt Kasse über `ArbeitsplatzService::aktuelleKasseId()`, IP kommt aus `$_SERVER['REMOTE_ADDR']` — nie vom Client behauptet). JS-seitig: `kasse_arbeitsplatz.js` → neue Funktion `apHeileBfrUrl()`, läuft beim Laden von `kasse/index.php` (= Kasse-Start), fragt lokal `127.0.0.1:<port aus window.KASSE_BFR_URL>/state` ab, meldet nur die RN. `window.KASSE_BFR_URL` wird von `kasse/index.php` nur gesetzt wenn `$kasseInfo['bfr_url']` nicht leer ist. Kein Popup, keine Fehlerbehandlung nötig — schlägt der lokale Check fehl, bleibt einfach alles wie es ist.

**Noch zu tun:** echter Test auf Hardware mit laufendem BFR (lokalen State-Check + Heilung live beobachten, v.a. dass die IP nach einem simulierten Netzwechsel wirklich aktualisiert wird).

**Entscheidung 2026-07-10:** Hardwaretest bewusst pausiert, bis die Herstellerantwort zum `/register`-Timeout-Bug (siehe oben, Bericht vom 2026-07-09) da ist — dann Offline-Fix/Selbstheilung + der gemeldete Bug in einem Durchgang gemeinsam testen, statt zweimal auf echte Hardware zu müssen.

## (Ursprünglicher Entwurf, 2026-07-08, zur Referenz)

Beim heutigen Test musste `bfr_url` für Kasse 4 auf eine 10er-LAN-IP gesetzt werden (nicht `127.0.0.1`), weil die Online-Signierung server-seitig läuft (`BfrService::signiereBeleg()` aus `KassenService::erstelleBon()`, PHP läuft auf dem Server, nicht auf der Kasse). Das widerlegt die alte Annahme in den Arbeitsplätze-Notizen ("bfr_url ist immer 127.0.0.1") — und deckt zwei echte, noch offene Probleme auf, die Jacky beide sofort erkannt hat:

**Problem A — Offline/Messe-Kasse würde bei echter Netztrennung scheitern.** `MesseSyncService::preSyncExportieren()` gibt die komplette `kassen`-Zeile inkl. `bfr_url` unverändert an den Offline-Client weiter; `kasse_bon_offline.js` (Zeilen ~499, ~526, ~548, ~598) ruft diese URL direkt per Browser-`fetch()` auf — **keine Sonderbehandlung, kein `127.0.0.1`-Override irgendwo im Offline-Pfad** (geprüft: 0 Treffer für `127.0.0.1`/`localhost` in `MesseSyncService.php`, `bon_offline.php`, `kasse_bon_offline.js`, `messe_vorbereiten.php`, `ajax_messe.php`). Bei einer echten Messe ohne Netzverbindung zum Heimnetz wäre die dort eingetragene LAN-IP unerreichbar, obwohl BFR direkt daneben auf demselben Gerät läuft. **Fix:** Offline-Client soll für seine eigenen BFR-Aufrufe immer `127.0.0.1` verwenden (Host fix, Port aus der konfigurierten `bfr_url` übernehmen) — architektonisch immer richtig, weil Browser und BFR bei Offline-Nutzung per Definition auf demselben Gerät laufen.

**Problem B — Online-`bfr_url` als feste IP ist brüchig bei DHCP/WLAN-Wechsel.** Jede IP-Änderung der Kasse (Router-Neustart, WLAN statt LAN, o.ä.) würde BFR fälschlich als "nicht erreichbar" erscheinen lassen, obwohl es lokal einwandfrei läuft. MAC-basierte DHCP-Reservierung wurde erwogen, aber verworfen: WLAN- und LAN-Interface derselben Kasse haben unterschiedliche MACs, beide auf dieselbe IP zu reservieren riskiert Kollisionen falls versehentlich beide Interfaces gleichzeitig aktiv sind.

**Lösung (Jackys Idee, gemeinsam verfeinert) — Selbstheilender `bfr_url` per lokalem State-Check + server-beobachteter IP:**
1. Browser (an der Kasse, sobald ein Arbeitsplatz gebunden ist) ruft lokal `http://127.0.0.1:8787/state` auf — das geht immer, unabhängig vom aktuellen Netzwerk.
2. Browser schickt nur die daraus gelesene RN (Kassen-ID) an einen neuen kleinen Endpunkt (POST).
3. Server prüft: passt die gemeldete RN zur `rksv_kassen_id`, die für die über `ArbeitsplatzService::aktuelleKasseId()` bereits gebundene Kasse hinterlegt ist? (Kein `IP`-Vergleich nötig — die Arbeitsplatz-Bindung + RN-Übereinstimmung ist der Vertrauensanker, dieselbe Hürde wie die bestehende Kollisions-Sperre.)
4. Bei Übereinstimmung: `kassen.bfr_url` automatisch auf `http://<REMOTE_ADDR>:<Port aus bisheriger URL>` aktualisieren, falls abweichend. Server braucht den Client dafür nichts über seine eigene IP sagen zu lassen — `$_SERVER['REMOTE_ADDR']` liefert das server-seitig beobachtet, nicht client-behauptet.
5. Auslöse-Zeitpunkt: beim Kasse-Start (sobald Arbeitsplatz gebunden), evtl. huckepack auf den bestehenden Heartbeat.

**Sicherheitsüberlegung:** Ein Fremdgerät könnte das nicht missbrauchen, weil es zusätzlich schon über den Arbeitsplatz-Token an genau diese Kasse gebunden sein müsste — dieselbe Hürde wie bei der Kollisions-Sperre (Manager-PIN nötig, um eine aktiv gebundene Kasse zu übernehmen).

**Verwandte Frage, mitbeantwortet:** Gibt es sonst noch eine Schwachstelle, die eine Kasse dazu bringen könnte sich als andere KassenID auszugeben, unabhängig von IP? Ja, in engem Rahmen — die gesamte "welche Kasse bin ich"-Logik hängt ausschließlich am UUID-Token in `localStorage`, keine Stelle vergleicht zusätzlich die anfragende IP. Wer den Token kopiert (z.B. Browser-Konsole an der echten Kasse, kompromittiertes Gerät) UND die echte Kasse gerade inaktiv ist (>10 Min. kein Heartbeat, sonst greift die Kollisions-Sperre+Manager-PIN), könnte sich als diese Kasse ausgeben. Token ist 128-Bit-Zufall (praktisch nicht erratbar) — Risiko besteht nur bei physischem/Software-Zugriff aufs Gerät selbst. Ein IP-Abgleich als zusätzliche Absicherung wäre wegen Problem B (DHCP-Wechsel) kontraproduktiv/brüchig — für eine Einzelstandort-Kasse mit physischer Zugriffskontrolle als akzeptables Restrisiko eingestuft, nicht gehärtet.

**Why:** Jacky bemerkte selbst: "ich glaube das gibt weniger Probleme, auch im Offline-Betrieb, wenn lokal immer auf 127.0.0.1 geschaut wird und der Server sich seine Abfragen quasi selbst anhand der Anfrage bauen kann" — ein einziger, konsistenter Mechanismus statt Spezialfall-Flickwerk.
**How to apply:** Nächste Session gemeinsam durchgehen und bauen: (1) Offline-JS-Fix (127.0.0.1-Override), (2) neuer Melde-Endpunkt + kleiner JS-Aufruf beim Kasse-Start für die Selbstheilung.
