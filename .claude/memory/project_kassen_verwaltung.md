---
name: project-kassen-verwaltung
description: "Kassen-Verwaltung: konfigurierbare Kassen-Instanzen, Messe-Workflow, Offline-Sync"
metadata: 
  node_type: memory
  type: project
  originSessionId: e4188803-2e7d-4016-91d8-7d02ca894fa1
---

## Kernkonzept: Kasse = konfigurierbare Instanz

Jede Kasse ist ein Datensatz in der DB, nicht hardgecoded. Das ermöglicht flexiblen Betrieb.

```
kassen (
  id, name,           -- "Kasse 1 (Laden)", "Kasse 2 (Messe-Laptop)"
  lager_id FK,        -- welches Lager wird bedient (K1, K2, ...)
  modus ENUM(online, offline),  -- offline = SQLite-Sync-fähig
  rksv_kassen_id,     -- an physisches Gerät gebunden, ändert sich nie
  aktiv BOOL
)
```

**Why:** ersetzt den bisherigen JTL-Umweg (Auftrag als Messe-Workaround). Ein Code, keine separate App.
**How to apply:** Das Lager-Label im Kassen-Header kommt aus dieser Konfiguration. Admin stellt ein, Kassiererin sieht nur das Ergebnis.

## Kassen-Verwaltung Admin-Interface

Einfache Liste aller Kassen, pro Zeile:
- Name / Lager-Zuweisung / Modus / RKSV-ID / Status (aktiv/inaktiv)
- Bearbeiten-Button: Lager und Modus änderbar, RKSV-ID nur lesend (an Gerät gebunden)
- Neue Kasse anlegen (für zukünftige Kasse 3 usw.)

Gehört ins Admin-Bereich des ERP, nicht in die Kasse selbst.

## Messe-Workflow

### Vorbereitung (Vorabend, alle Geräte im Heimnetz)

**Schritt 1 — Hauptkasse:**
⚙ Menü → "Zur Messe vorbereiten"
→ Scan-Screen (wie vereinfachter Bon, aber kein Verkauf)
→ Artikel scannen + Menge → Umbuchung: Hauptlager → K2
→ Artikel damit sofort weg für Shops + normale Kasse
→ Status: "Messe-Lager bereit, X Positionen"

**Schritt 2 — Messe-Laptop (noch im Heimnetz):**
Browser → http://erp.local/kasse → "Messe-Sync holen"
→ zieht von /api/messe/pre-sync:
  - Artikel + Preise
  - K2-Lagerstand (was gerade umgebucht wurde)
  - Gutscheine (gültige)
→ speichert in lokale SQLite
→ Sync-Button erst aktiv wenn Umbuchung auf K2 abgeschlossen ist (serverseitig geprüft)

### Während Messe (vollständig offline)
- Messe-Laptop läuft auf SQLite
- RKSV: BFR-BONit + Signaturkarte lokal am Laptop
- Freier Artikel (99-9999) funktioniert auch offline

### Rückkehr (zurück im Heimnetz)

**Messe-Laptop:** "Messe-Daten hochladen" → POST /api/messe/post-sync
→ abgeschlossene Bons + Lagerabgänge

**Hauptkasse:** ⚙ Menü → "Von Messe zurück"
→ zeigt was rausgegangen ist
→ BEIDE Optionen anbieten:
  A) **Rückscan** — einzelne Artikel scannen was zurückkommt
  B) **Mengeneingabe** — manuelle Mengen pro Position (Pflicht bei großen Messen, viele Knäuel)
→ Bestätigen:
  - verkaufte Menge: bleibt weg (in Messe-Bons bereits)
  - zurückgekommen: Umbuchung K2 → Hauptlager
  - Differenz: Buchungsart "Schwund/Verlust" (eigene Buchung, nicht stilles Verschwinden)

## RKSV-Besonderheit

RKSV-Kassen-ID ist an das **physische Gerät** gebunden — nicht an den Modus.
Messe-Laptop = immer K002, egal ob Messe-Modus oder Normalbetrieb.
Belegkette läuft gerätebezogen, das ist korrekt so.

## Kasse 2 im Normalbetrieb

Messe-Laptop kann jederzeit als zweite In-House-Kasse verwendet werden:
- Admin stellt Lager K1 ein, Modus = Online
- Laptop verbindet direkt gegen erp.local (kein SQLite)
- Nützlich bei Stoßzeiten (Weihnachtsgeschäft, lokale Märkte)

## POS-Bildschirm (bon.php) — Stand 2026-06-27

bon.php ist **standalone** (kein shell_top.php), eigener Header + vollständiges POS-Layout.

**Implementiert:**
- 2-Spalten-Layout: Links = Bon-Liste (550px), Rechts = Scan+Numpad
- Schnellwahl: 9 Slots aus `kassen_schnellwahl` (Migration 081), konfigurierbar per Admin
- Numpad: Giffern + × Mal + % Rabatt + ⌫ + STORNO + ⊟ Lade + + Artikel
- Aktive Zeile: [−][+][€ Preis] Buttons, Preis-Override via Numpad-Buffer
- Rabatt-Dialog: Tab-Toggle % Prozent ↔ € Neuer Gesamtpreis (implizit %-Berechnung, steuerkonform)
- Bar-Overlay: alle 7 Scheine (5–500€) immer sichtbar, Akkumulation (50+20=70€), C-Taste
- Kunden-Suche: ajax_kunden_suche.php → KundenService::getAll (PHP-seitig wegen AES-Enc.)
- Parken: ~~sessionStorage (1 Bon pro Kasse)~~ → seit Commit 45cfabd persistent in DB (`kassen_geparkte_bons`, Migration 093), mehrere Bons gleichzeitig, `ajax_parken.php` (speichern/liste/laden/loeschen)
- Textsuche als Fallback wenn EAN nicht gefunden
- Reservierungs-Warnung mit Overrule bei bestand_verkaufbar-Unterschreitung

**Kassenlade:**
- `ajax_kassenlade.php` — ESC/POS Sequenz (0x1B 0x70 0x00 0x19 0xFA)
- Benötigt `KASSENLADE_PORT` in `config/config.php` (z.B. `'LPT1'`, `'/dev/usb/lp0'`)
- Bis dahin: Platzhalter-Response + Logger-Eintrag

**RKSV Nullbon (TODO — kommt mit RKSV-Modul):**
- Automatischer Nullbon beim **Monatswechsel** vor erster Buchung im neuen Monat
- Warnung beim **Jahreswechsel** vor erster Buchung (manueller Nullbon nötig)
- Einplanen wenn RKSV/BFR-BONit implementiert wird

**Barbara-Feedback (2026-06-27):**
- Keine großen Design-Änderungen gewünscht
- 9 Schnellwahl-Slots reichen (Tragetasche per Konfig eintragen, sobald Artikel angelegt)
- Kassenlade-Button in Menü + Numpad gewünscht ✓
- Nullbon vorgemerkt für RKSV-Phase

**shell_top.php (alle anderen Kasse-Seiten):**
- Corporate Design: #1e3a5f Header, #f1f5f9 BG, #2563eb Akzente (kein dunkles Theme mehr)

## Messe-Sync Server-Seite bereits gebaut (entdeckt 2026-07-03, nicht in Memory dokumentiert gewesen)

Beim Prüfen für die nächste Session aufgefallen: die komplette **MariaDB-seitige** Hälfte des Messe-Workflows existiert schon im Code, war aber in keiner Memory festgehalten:
- Migration 080: `kassen_messe_sync` + `kassen_messe_umbuchungen`
- `src/modules/kasse/MesseSyncService.php`: `umbuchungZurMesse()`, `preSyncExportieren()`, `postSyncVerarbeiten()`, `rueckkehrVerarbeiten()` — alle vier Kernoperationen aus dem oben beschriebenen Workflow sind implementiert
- `public/kasse/ajax_messe.php`: AJAX-Endpunkt für alle vier Aktionen (`umbuchung_zur_messe`, `pre_sync_export`, `post_sync`, `rueckkehr`, `offene_syncs`)

**Was noch fehlt:** die **Offline-Kasse selbst** — `bon.php` zeigt aktuell nur ein `MESSEBETRIEB`-Badge basierend auf `kassen.modus`, macht aber weiterhin normale AJAX-Calls gegen den MariaDB-Server. Kein SQLite, kein IndexedDB, kein Service Worker, kein "lokal cachen + später hochladen" existiert bisher.

## Architektur-Entscheidung Offline-Client — ENTSCHIEDEN 2026-07-03

Zwei Optionen abgewogen: (a) dieselbe PHP-Codebasis am Messe-Laptop gegen lokale SQLite statt MariaDB, oder (b) eigenständiger JS/IndexedDB-Client ohne lokalen Server.

**Entscheidung: Option (b).** Ausschlaggebend: Option (a) hätte bedeutet, für immer zwei SQL-Dialekte parallel zu pflegen (MariaDB nutzt schon jetzt `ON DUPLICATE KEY UPDATE`, `JSON_TABLE`/`->>`-Pfadsyntax, `DATEDIFF()` — alles ohne 1:1-Äquivalent in SQLite), bei über 100 Migrationen eine dauerhafte Wartungslast statt einer einmaligen. Jacky: "wenn wir auf ewig bei jeder Änderung an 2 Stellen aufpassen müssen, dann wird das nix — muss auch in ein paar Jahren mit Updates und Erweiterungen handlebar bleiben."

**Wie es technisch funktioniert (ohne Server-DB):**
- **IndexedDB** im Browser ist die lokale Datenbank — echt persistent (Festplatte, übersteht Neustart), kein RAM-Provisorium. Speichert offline erstellte Bons inkl. Signatur-Ergebnis.
- **RKSV-Signatur:** Browser ruft `fetch('http://127.0.0.1:8787/register', ...)` **direkt** auf, keine PHP-Zwischenschicht nötig — BFR ist bewusst als eigenständiges XML-über-HTTP-Gerät gebaut. Gleiche Reihenfolge-Disziplin wie online (sofort nach Bon-Erstellung signieren). Fällt BFR kurz aus: Bon wird trotzdem mit "Sicherheitseinrichtung ausgefallen" gespeichert, landet beim Post-Sync in der schon bestehenden Nachsignierungs-Logik (kein neuer Mechanismus nötig).
- **Lagerbuchungen:** keine Live-Bestandsbuchung während der Messe nötig. Offline-Client zieht nur lokal (IndexedDB) für die Kassiererin mit ("nur noch 2 Stück"). Die echte Buchung passiert gesammelt bei der Rückkehr — macht `rueckkehrVerarbeiten()` schon heute genau so (verkauft/zurück/Schwund als eine Buchung je Artikel, nicht pro Einzelbon).
- **Kundendaten bewusst ausgeschlossen:** AES-Verschlüsselung läuft serverseitig in PHP, Schlüssel verlässt den Server nie — ein JS-Client könnte echte Kundendaten technisch gar nicht entschlüsseln. Passt zur bestehenden "Kunden = Laufkunde"-Entscheidung, kein Kompromiss.
- **Parken:** bei dieser Architektur praktisch kostenlos — ein unfertiger Warenkorb braucht keine Signatur, ist einfach ein weiterer Eintrag in derselben IndexedDB.
- **Funktionsumfang bewusst reduziert:** Messe-Kasse bedient sich nur aus dem eigenen Messe-Lager, Rest über Freitext-Artikel (99-9999, existiert schon) — kein Kundenbezug, keine Retouren/Chargen-Komplexität nötig.

**Noch offen (nächster Schritt):** konkrete Implementierung — eigene Offline-Variante von `bon.php` (oder Modus-Umschaltung darin), IndexedDB-Schema, Sync-Flow (Pre-Sync laden → offline arbeiten → Post-Sync hochladen), Fehlerbehandlung bei abgebrochenem Upload.

## ✅ Kompletter Messe-Workflow gebaut (2026-07-04) — bereit für BFR-Hardware-Test

Alle drei fehlenden UI-Teile jetzt gebaut, End-to-End getestet (Service-Layer-Tests, kein echter Browser verfügbar):

**1. `public/kasse/messe_vorbereiten.php`** (+ `js/kasse_messe_vorbereiten.js`) — Scan-Seite: Offline-Kasse + Ziel-Lager wählen, Artikel scannen/eingeben (nutzt bestehendes `ajax_artikel.php`), Mengen editierbar, Warnung bei Menge > Bestand. Ruft `ajax_messe.php?aktion=umbuchung_zur_messe` auf. Zeigt darunter alle bereits vorbereiteten offenen Sync-Pakete mit Link zur Offline-Kasse.

**2. `public/kasse/bon_offline.php`** (+ `js/kasse_bon_offline.js`) — der eigentliche Offline-Client:
- IndexedDB-Schema: `konfig` (Sync-Metadaten, Kassenkonfig inkl. `bfr_url`/`kasse_nr`, laufender Belegnummernzähler, `diversArtikelId`), `artikel` (Pre-Sync-Kopie inkl. `chargen`-Array), `bons` (Warteschlange fertiger Belege)
- Lädt einmalig `ajax_messe.php?aktion=pre_sync_export` während noch Serververbindung besteht, danach komplett ohne Server lauffähig
- Verkaufsbildschirm: Scan **und Textsuche** (Artikelname, min. 2 Zeichen, Dropdown mit bis zu 8 Treffern) gegen lokale Artikel-Kopie, Warenkorb, Bar/Karte-Zahlung mit Rückgeld-Berechnung
- **Direkte RKSV-Signierung**: baut dieselbe XML-Struktur wie `BfrService::baueSignierXml()` (per Test 1:1 abgeglichen, nur der optionale XML-Prolog ergänzt) und ruft `fetch(bfr_url + '/register')` direkt aus dem Browser auf. BFR nicht erreichbar → Bon läuft trotzdem durch, `bfr_status='ausstehend'`, landet nach Upload in der bestehenden Nachsignierungs-Logik
- Belegnummer offline: `{kasse_nr}-{jahr}-{6-stellig}`, Startzähler kommt aus `preSyncExportieren()` (`bon_nr_jahr`/`bon_nr_zaehler`)
- Quittungsanzeige on-screen + druckbar, "Bons hochladen"-Button ruft `ajax_messe.php?aktion=post_sync` sobald wieder online

**3. `public/kasse/messe_rueckkehr.php`** (+ `js/kasse_messe_rueckkehr.js`) — zeigt alle Sync-Pakete mit Status `abgeschlossen` (Bons schon hochgeladen), **pro Charge eine eigene Zeile** (nicht mehr pro Artikel), Eingabe "Zurück" + "Schwund" mit live berechnetem "Verkauft", ruft `ajax_messe.php?aktion=rueckkehr` auf. `MesseSyncService` ergänzt: `getSyncsFuerRueckkehr()` (neu, öffentlich), `getUmbuchungenBySyncId()` (von private auf public gestellt).

**Navigation:** neuer "🎪 Messe"-Punkt in der Kasse-Kopfzeile (`shell_top.php`), führt zu `messe_vorbereiten.php`.

## Korrekturrunde 2026-07-04 (nach Jackys Praxis-Einwänden) — alle fünf Punkte behoben

Jacky hat die erste Version zurecht abgelehnt: Freier Artikel/Textsuche fehlten (Muss für Messen), Chargen waren nur Freitext (Risiko für falsche Lagerstände), der Browser-Tab durfte nicht geschlossen werden (in der Praxis unhaltbar), Z-Bon war nur für "heute" möglich.

**1. Chargen jetzt durchgängig statt Freitext — größter Umbau:**
- Migration 106: `kassen_messe_umbuchungen.charge` (neue Spalte) — eine Zeile pro Artikel **+ Charge**-Kombination statt nur pro Artikel
- `umbuchungZurMesse()`: nimmt jetzt echte Charge pro Position entgegen, gibt sie an `LagerService` durch (der konnte das schon immer, wurde bisher nur nie genutzt)
- `preSyncExportieren()`: liefert pro Artikel ein `chargen`-Array (echte, tatsächlich mitgenommene Chargen + Mengen) — der Offline-Client kennt dadurch nur die real vorhandenen Chargen, keine Erfindung möglich
- `messe_vorbereiten.php`: bei `charge_pflicht`-Artikeln öffnet sich jetzt ein Chargen-Auswahl-Overlay (nutzt das bestehende `packplatz/warenausgang/chargen_ajax.php`) statt den Artikel blind hinzuzufügen
- `bon_offline.php`: Chargen-Auswahl ist jetzt ein Dropdown der echten, mitgenommenen Chargen mit lokal berechneter Restmenge (`obChargeBestandVerbleibend()` — Original minus bereits im Warenkorb) — kein Verkauf über die mitgenommene Menge hinaus möglich
- `rueckkehrVerarbeiten()`: verarbeitet Rückgabe/Schwund jetzt **pro Charge** (Schlüssel `artikel_id|charge`), nicht mehr aggregiert — sonst wäre bei der Rückbuchung die Chargen-Zuordnung im Lagerbestand zerstört worden
- `messe_rueckkehr.php`: eigene Spalte + Zeile pro Charge

**2. Freier Artikel ergänzt** (`bon_offline.php`): "➕ Freier Artikel"-Button → Overlay (Bezeichnung, Preis, Steuersatz) → Position mit dem `divers_artikel_id` (99-9999-Platzhalter, jetzt auch in `preSyncExportieren()` mitgeliefert). **Nebenbefund:** der 99-9999-Artikel fehlte in der Dev-DB komplett — Migration 078 ist zwar als "angewendet" markiert, hat aber wegen `INSERT IGNORE` beim ersten Lauf still nichts eingefügt (vermutlich lief sie vor den Einheiten-Seed-Daten). Manuell nachgetragen; für andere Installationen im Hinterkopf behalten falls dieselbe stille Lücke auftritt.

**3. Textsuche ergänzt** (`bon_offline.php`): Eingabefeld filtert ab 2 Zeichen die lokale Artikelliste nach Bezeichnung (Substring, case-insensitive), reine Ziffern-Eingaben werden nicht als Textsuche behandelt (Scanner-Kompatibilität).

**4. Service Worker für echte Offline-Resilienz** — der wichtigste Fix:
- `bon_offline.php` ist jetzt eine **rein statische Seite** — keine PHP-Werte mehr im Output außer beim allerersten Laden (`auth_check.php`). `sync_id` wird clientseitig aus der URL gelesen (`URLSearchParams`), `BASE_PATH` clientseitig aus `location.pathname` berechnet — dadurch ist die Seite unabhängig vom Installationspfad cachebar
- `preSyncExportieren()` vereinfacht: braucht `lager_id` nicht mehr als Parameter (steht schon im Sync-Datensatz) — reduziert das, was der Client wissen muss, auf nur noch `sync_id`
- Neuer `public/kasse/sw_bon_offline.js`: Service Worker, cached `bon_offline.php` + `kasse_bon_offline.js` beim ersten (Online-)Laden, liefert sie bei Serverausfall aus dem Cache. Fängt bewusst NUR GET-Requests auf diese beiden Dateien ab — `ajax_messe.php`-Aufrufe und die direkte BFR-Signierung werden nicht abgefangen, müssen ehrlich funktionieren oder fehlschlagen
- **Damit gelöst:** Laptop daheim herunterfahren, zum Hersteller/zur Messe fahren, Browser dort neu öffnen (auch nach Absturz) — funktioniert jetzt ohne jede Serververbindung vor Ort

**5. Z-Bon pro Tag nachträglich möglich:**
- `KassenService::sammleAbschlussDaten()`/`erstelleXBon()`/`erstelleZBon()` bekommen einen optionalen `$datum`-Parameter (Default: heute, unverändertes Verhalten wenn nicht angegeben)
- `kassensturz.php`: neues Datumsfeld beim Z-Bon-Formular ("Für Tag — leer = heute")
- Für eine mehrtägige Messe: nach dem Post-Sync-Upload aller Tage kann pro Tag einzeln ein korrekter Z-Bon nachträglich erzeugt werden

**Getestet (Service-Layer, PHP-CLI, kein echter Browser verfügbar):** kompletter Kreislauf inkl. echter Charge (Artikel 245, Charge `59f47f`) durch alle vier Phasen (Umbuchung → Pre-Sync-Export mit Chargen-Array → simulierter Bon mit Charge → Post-Sync → Rückkehr) — Lagerbestand wanderte korrekt pro Charge zwischen Hauptlager und Messestand. `getTagesKennzahlen()` mit unterschiedlichen Datumswerten liefert korrekt unterschiedliche Ergebnisse.

**Für den echten Test beim BFR-Hersteller weiterhin offen:**
- Echter Browser-Test (IndexedDB, DOMParser, fetch, Service Worker) wurde in dieser Session nicht durchgeführt — nur die zugrundeliegende Logik verifiziert. Insbesondere der Service Worker sollte einmal bewusst getestet werden (Seite laden, DevTools → Network → Offline aktivieren, Seite neu laden)
- Test-Kasse "Messe-Laptop (Test)" (id=2) wurde für die Tests direkt per SQL angelegt — vor dem echten Einsatz eine echte Kasse über Einstellungen→Kassen anlegen (inkl. `bfr_url` + RKSV-Kassen-ID-Registrierung)

**Weiterhin bewusst nicht gebaut** (siehe `docs/offline_kasse_anleitung.md` für die vollständige Liste): Bon parken, Schnellwahl-UI, Kombi-Zahlung, Rabatt-UI, Storno, Kundensuche, Gutschein (Modul existiert nicht).

**Why:** Jackys Einwände waren alle sachlich berechtigt — v.a. Chargen-Korrektheit ist nicht verhandelbar, und "Browser darf nicht geschlossen werden" ist für einen mehrtägigen Messe-Einsatz schlicht untauglich.
**How to apply:** Vor dem echten BFR-Termin: echte Kasse anlegen, Test-Kasse (id=2) ggf. löschen, kompletten Ablauf einmal im echten Browser durchspielen inkl. bewusstem Offline-Test des Service Workers.

## ✅ Zwei A4-Rechnung/Mailversand-Lücken behoben (2026-07-04)

Beide am 2026-07-03 gefundenen Lücken sind jetzt gefixt:
1. **Nachträglicher Zugriff**: `bon_journal.php` hat jetzt pro Zeile (bei `typ` verkauf/storno) einen zusätzlichen "A4"-Button neben dem bisherigen 🖨-Button, verlinkt auf `bon_a4.php?id=X`.
2. **Mailversand nutzt jetzt A4 statt 68mm**: Die komplette Bon-A4-Logik wurde aus `bon_a4.php` in `src/modules/kasse/BonA4Renderer.php` extrahiert (`BonA4Renderer::render(int $bonId, bool $fuerPdf): ?string`) — eine einzige Quelle für Browser-Ansicht UND Mail-PDF, keine doppelt gepflegte Vorlage mehr. `$fuerPdf=true` blendet die Druck-/Schließen-Buttons aus. `bon_speichern.php` (Abholbestätigungs-Mail) generiert den PDF-Anhang jetzt über diesen Renderer + Dompdf auf A4-Papierformat statt der bisherigen inline gebauten 68mm-Thermobon-Vorlage.
3. Getestet: Renderer liefert für einen echten Bon in beiden Modi korrektes HTML (Druckleiste nur im Screen-Modus), Dompdf erzeugt daraus ein valides PDF (Magic-Bytes geprüft).

## ✅ Rechnung-Mail zeigt jetzt korrekten Zahlungsstatus (2026-07-04)

Jacky aufgefallen: `templates/mails/rechnung_mail.html.twig` zeigte **immer** "Bitte überweisen Sie den Betrag bis..." — auch wenn die Rechnung schon (ganz oder teilweise) bezahlt war. Betraf beide Auslöser: Auto-Mail nach Packplatz-Abschluss (`packplatz/warenausgang/abschliessen.php`, läuft nur wenn `zahlungsstatus='bezahlt'` — zeigte aber trotzdem den Zahlungshinweis!) und den manuellen "Rechnung per Mail"-Button (`auftraege/dokument_erstellen.php`).

**Fix:** Template bekommt jetzt `zahlungsstatus`, `zahlungen` (Liste aus `auftrag_zahlungen`) und `offener_betrag` übergeben (Zahlungsdaten in `dokument_erstellen.php` per Direktabfrage, in `abschliessen.php` über die schon instanziierte `AuftragRepository::findZahlungen()`). Drei Zustände:
- `bezahlt`: "Vielen Dank, bereits bezahlt" + Tabelle aller verzeichneten Zahlungen, kein Zahlungshinweis mehr
- `teilbezahlt`: Tabelle der bisherigen Zahlungen + Resthinweis mit verbleibendem Betrag + Fälligkeitsdatum
- sonst (offen/ausstehend): unverändertes Verhalten wie zuvor

Getestet: alle drei Zustände per Standalone-Twig-Render verifiziert (kein Rendering-Fehler, korrekte Beträge/Zeilen je Zustand).

## ✅ A4-Druck auch in auftraege/detail.php für Kassen-Aufträge (2026-07-04)
Im gesperrten Kassen-Auftrag-Bereich (`$istKasse`-Block) gibt's jetzt neben "Kassenbon drucken" (80mm, `bon_druck.php`) auch "Als A4 drucken" (`bon_a4.php`) — beide nutzen dieselbe `$kasseBon['id']`. Normale (nicht-Kasse) Aufträge unverändert, die haben ihre reguläre A4-Rechnung schon über die Dokumente-Buttons.

**Why:** Vermeidet dauerhafte Doppelpflege zweier SQL-Dialekte; nutzt BFRs eigene Offline-Fähigkeit direkt statt sie hinter einer zusätzlichen Server-Schicht zu verstecken.
**How to apply:** Bei der Implementierung: Server-Seite (Pre-/Post-Sync-API in `MesseSyncService`/`ajax_messe.php`) ist bereits fertig und wird unverändert wiederverwendet — nur der Client ist neu zu bauen.

## Korrekturrunde 3 (2026-07-04, nach echtem Browser-Test von Jacky) — Offline-Kasse jetzt browser-getestet

Nach dem Service-Layer-Test kam der erste echte Klick-Test im Browser — dabei kamen fünf reale Bugs zum Vorschein, die reine PHP-CLI-Tests nicht gefangen hatten:

1. **`kasse/shell_top.php` fehlte `window.BASE_PATH`** — beim BASE_PATH-Umbau (Session 23) wurde nur `includes/shell_top.php` angepasst, die separate Kasse-eigene Shell übersehen. Externe JS-Dateien (Messe-Vorbereitung/-Rückkehr) bauten dadurch URLs wie `.../undefined/kasse/ajax_artikel.php` → 404 → "Fehler bei der Suche". Jetzt behoben.
2. **`ajax_messe.php` rief `Auth::requireLogin()`/`Auth::getUserId()`** — beide Methoden existieren nicht (Projekt-Konvention: `require auth_check.php` + `$_SESSION['benutzer']['id']`). PHP-Fatal-Error → HTML statt JSON → "Netzwerkfehler bei der Umbuchung" im Frontend, obwohl serverseitig (vor dem Fehler) teils schon reale Bestandsbuchungen gelaufen waren.
3. **`umbuchungZurMesse()` legte bei jedem Klick ein neues Sync-Paket an** — Jacky bemerkte: "mit jedem Umbuchung durchführen Klick wird neue Offline-Kasse laden angelegt?" Jetzt: offenes (`status='vorbereitet'`) Paket pro Kasse+Lager wird wiederverwendet, gleiche Artikel+Charge werden addiert statt dupliziert (wichtig, da `rueckkehrVerarbeiten()` von genau einer Zeile pro Artikel+Charge pro Sync ausgeht).
4. **Quell-Lager war hart auf 1 codiert** (`kasse_messe_vorbereiten.js`) — Jacky: "aus welchem Lager wir die Messe befüllen wollen wird auch nicht hinterfragt". Echtes Problem, da im System bereits zwei normale Lager existieren (Ladengeschäft + Privathaus-Keller). Jetzt eigenes "Von Lager"-Dropdown in `messe_vorbereiten.php`.
5. **Chargen-Auswahl-UX**: der einzelne "+"-Button war eigentlich ein "Übernehmen"-Button (Menge erst eintippen, dann klicken), ohne Möglichkeit etwas wieder zu entfernen. Umgebaut zu echtem Stepper (−/Anzahl/+) mit Live-Anzeige "verfügbar"/"im Warenkorb", Buttons deaktivieren sich am Limit.

**Wichtige Lektion (Testkontamination):** Beim Debuggen von Punkt 1 wurden Scratch-Testskripte direkt gegen echte Artikel (u.a. Artikel 245) und Jackys echte Test-Kasse "Messe-Laptop (Test)" (id=2) ausgeführt, ohne danach aufzuräumen — Jacky bemerkte die Kontamination selbst ("Lagerstand falsch, Artikel irgendwo, Chargen wieder beim Teufel"). Musste rückwirkend per SQL bereinigt werden (Lagerbestand zurückgerechnet, Test-Sync-Datensätze gelöscht). Siehe [[feedback_test_isolation]].

## ✅ Chargen-Sichtbarkeit im Artikel-Lagerbestand behoben (2026-07-04)

Nebenbefund beim Testen: `artikel/detail.php` (Tab Lager) zeigte "Gesamtbestand: 3" aber nur Chargen, die in Summe 2 ergaben — 1 Einheit lag in einer `charge=NULL`-Zeile (`charge_status='nachzutragen'`), die `LagerRepository::findBestandChargeProLager()` bewusst aus der Chargen-Liste gefiltert hatte (Kommentar: "Zeilen ohne Charge fließen nur in die Summe"), aber weiterhin mitzählte. Jetzt: bei chargenpflichtigen Artikeln wird diese Zeile sichtbar ("— ohne Charge —", rot), bei normalen Artikeln bleibt sie ausgeblendet (Regelfall, keine Warnung nötig). Zusätzlich: Chargen mit Bestand 0 werden jetzt grundsätzlich weder gezählt noch angezeigt (Jackys Einwand: sonst sammeln sich bei gut laufenden Artikeln nach einem Jahr zig längst leere Chargen an).

## ✅ Chargen-Filter im Bewegungslog (Artikel-Detailseite) — 2026-07-04

`artikel/detail.php` (Tab Lager, "Letzte Lagerbewegungen") hat jetzt ein Dropdown mit allen historischen Chargen des Artikels. Auswahl lädt per AJAX (`artikel/bewegungslog_ajax.php`, HTML-Fragment via `artikel/bewegungslog_tabelle.php`) die **vollständige** Bewegungshistorie dieser einen Charge (EK bis letzter Verkauf) — ohne die sonst übliche 10er-Anzeigegrenze. Das ist die artikel-eigene Variante von Jackys Chargen-Nachverfolgbarkeit-Wunsch; die zentrale, artikelübergreifende Version ist separat vorgemerkt, siehe [[project_chargen_nachverfolgung]].

## ✅ Arbeitsplätze-Thema komplett gebaut (2026-07-07)

Ausgangspunkt war eigentlich nur die Resync-Sperre (siehe [[bug_offline_resync_kollision]]), aber die brauchte eine funktionierende "welche Kasse bin ich"-Erkennung, die es vorher gar nicht gab. Jacky entschied, gleich das ganze GEPLANT-Konzept aus der CLAUDE.md (`arbeitsplaetze`-Tabelle, Geräte-UUID-Token) mitzunehmen, um nicht später nochmal an dieselben Stellen zu müssen. Migration 118 dabei entdeckt: die alte Migration 112 (BFR-Ausfallerkennung-Umbau vom 6.7.) war durch einen zwischenzeitlich neu gezogenen `baseline_schema.sql`-Dump bereits überflüssig — gelöscht, siehe [[project_installationsanleitung]] (dort auch der Plan für einen kompletten Baseline-Neuschnitt nach Abschluss dieses Themas).

**Session-Lifecycle** (`src/core/Auth.php`, existierte vorher gar nicht): `sessions`-Tabelle war zwar im Schema vorhanden, wurde aber von keiner Code-Stelle beschrieben. Jetzt: `Auth::login()` legt eine Zeile an, `Auth::heartbeat()` (aufgerufen aus `auth_check.php` bei jedem Request) hält `letzte_aktivitaet` aktuell, `Auth::logout()` löscht sie.

**`src/modules/arbeitsplatz/`** (neu, `ArbeitsplatzRepository`/`ArbeitsplatzService`): Geräte-Token lebt im Browser-`localStorage`, serverseitig verknüpft über `arbeitsplaetze` + `sessions.arbeitsplatz_id`.
- **Freie Auswahl** ("Welcher Arbeitsplatz bist du?", Overlay in `kasse/index.php`, `js/kasse_arbeitsplatz.js`, `kasse/ajax_arbeitsplatz.php`) — nur für Kassen OHNE aktives BFR (`bfr_aktiv_seit IS NULL`) sowie für typ lager/buero/mobil (Typen vorbereitet, aber keine eigene Auswahl-UI außerhalb dieses Screens gebaut).
- **BFR-Sonderregel** (Jackys Verschärfung): Sobald `bfr_aktiv_seit` gesetzt ist, KEINE freie Auswahl mehr — die Bindung entsteht automatisch als Nebeneffekt von `BfrService::schliesseRegistrierungAb()` (Hook in `kasse_registrierung_speichern.php`), weil der Browser der die Registrierung abschließt zwangsläufig das physische Gerät ist (`bfr_url` ist immer `127.0.0.1`). Bei Hardware-Wechsel ("Neue Kassen-ID anfordern") wird die alte Bindung vorher gelöst (`ArbeitsplatzService::loeseBindungFuerKasse()` — deaktiviert statt löscht, wegen FK-Sperre von `sessions.arbeitsplatz_id` und für Nachvollziehbarkeit).
- **Kollisions-Sperre** (Jackys Verschärfung, Hybrid-Variante gewählt): Ist ein Arbeitsplatz schon durch eine andere Session belegt UND deren `letzte_aktivitaet` jünger als 10 Minuten → Sperr-Overlay mit Manager-PIN (`Auth::pruefeManagerPin()`, gleiches Muster wie die Kasse-Auszahlung). Kein automatisches Kicken, keine harte Sperre ohne Ausweg — nach 10 Min gilt die alte Session als tot und der neue Login geht ohne Nachfrage durch.
- **`getKasse(1)`-Hardcodes ersetzt**: alle 7 Stellen (`bon.php`, `index.php`, `kassenbuch.php`, `kassensturz.php`, `bon_journal.php`, `offene_auswahl.php`, `abschluss_liste.php`) nutzen jetzt `ArbeitsplatzService::aktuelleKasseId()` (Fallback auf 1, wenn kein Arbeitsplatz gebunden — kein Fehler, das eigentliche Gate ist die Resync-Sperre).

**Getestet:** alle Backend-Teile isoliert gegen die echte Dev-DB (Auswahl/Bindung/Kollision-beidseitig/PIN-Ablehnung/BFR-Bindung/Hardware-Wechsel/Fallback-Kasse-1, siehe [[bug_offline_resync_kollision]] für die Resync-Sperre selbst) — jeweils mit künstlichen Test-Sessions, vollständig aufgeräumt. **Nicht getestet:** echter Browser-Durchlauf (Overlay-Anzeige, `localStorage`-Verhalten, kompletter Login→Auswahl→Kollision-Flow).

**Noch offen / bewusst nicht gebaut:** eigene Auswahl-UI für Lager/Büro/Mobil-Arbeitsplätze außerhalb des Kasse-Moduls; Session-Limits/`max_sessions` (Spalte existiert, bewusst für später/Lizenzthema reserviert, siehe [[project_rechte_rollen]]).

## ✅ Nachbesserungen beim Browser-Testen gefunden + behoben (2026-07-07, noch am selben Tag)

Drei echte Lücken, die reine Backend-Tests nicht gefangen hätten — alle von Jacky selbst beim ersten Klick-Durchlauf entdeckt:

1. **`findAuswaehlbareKassen()` blendete bereits gebundene Kassen nicht aus der freien Auswahl aus** — ein zweites Gerät bekam eine schon vergebene Kasse trotzdem angeboten, lief dann aber in `waehle()`s Schutz vor Doppel-Bindung (Fehlermeldung statt Kollisions-PIN-Dialog, weil das ein anderer Sperrmechanismus ist: "fremdes Gerät will Kasse erobern" vs. "gleiches Gerät, andere Session"). Fix: Query filtert jetzt `NOT EXISTS (... arbeitsplaetze ... aktiv=1)`.
2. **Overlay ohne Ausweg**: Das Auswahl-/Kollisions-Overlay auf `kasse/index.php` blockierte die komplette Seite inkl. alle Navigation — kein Weg zu den Kassen-Einstellungen, um z.B. eine Bindung zu lösen. Fix: beide Overlays haben jetzt einen "⚙ Zu den Kassen-Einstellungen"-Link.
3. **"Bindung lösen" in `kassen_einstellungen.php` warnte nicht vor aktiver Nutzung** — Jackys eigene Nachfrage: ein Admin könnte damit eine gerade arbeitende Kassiererin unbemerkt auf die Fallback-Kasse umleiten. Fix: `ArbeitsplatzService::istAktivInVerwendung()` (neu) zeigt jetzt eine gelb/rote Warnung + schärferen Bestätigungstext, wenn dort noch eine "lebendige" Session (< 10 Min Heartbeat) hängt.

**Vierte, gravierendere Lücke — von Jacky selbst durchdacht, nicht beim Klicken gefunden:** `aktuelleKasseId()` fiel bei fehlender Arbeitsplatz-Bindung blind auf `?? 1` (Hauptkasse) zurück — Jackys Einwand: wenn Kasse 1 BFR-aktiv ist, dürfte ein komplett unbekanntes Gerät NICHT unter deren Identität/Signaturkarte Belege erzeugen (RKSV-Risiko, falsche Hardware signiert). **Fix:** `aktuelleKasseId(): ?int` gibt jetzt `NULL` zurück, wenn kein Arbeitsplatz gebunden ist UND die Fallback-Kasse (1) BFR-aktiv ist — kein automatischer Fallback mehr in diesem Fall. Alle 8 betroffenen Kasse-Seiten behandeln `NULL` jetzt explizit: `bon_speichern.php` blockt den Verkauf hart (JSON-Fehler), die übrigen 6 Seiten leiten auf `kasse/index.php` um, nur `kasse/index.php` selbst degradiert graceful (kein Redirect auf sich selbst) und lässt den JS-Overlay-Flow die Bindung herstellen.

**Getestet:** alle vier Punkte isoliert gegen die echte Dev-DB (inkl. Kasse 1 testweise mit `bfr_aktiv_seit` versehen, `aktuelleKasseId()` lieferte korrekt NULL, danach exakt auf den Originalwert NULL zurückgesetzt). **Weiterhin nicht getestet:** kompletter Redirect-Flow im echten Browser für die neuen NULL-Fälle.

**Commit `71e91dd`** (2026-07-07), gepusht nach `origin/master`.

## Geplant: Echter BFR-Hardware-Test "morgen" (voraussichtlich 2026-07-08)

Jacky bekommt eine Dummy-BFR-Kassenhardware ans Dev-System angeschlossen — dann volltest: BFR-Aktivierung/Kassen-ID-Vergabe, Buchungen, alle Übernahme-Versuche (Auswahl/Kollision/Hardware-Wechsel) live durchspielen, nicht nur isoliert gegen die DB wie bisher.

**Jackys Kern-Anforderung nochmal bestätigt (2026-07-07), code-seitig gegengeprüft, nicht nur behauptet:**
- `kasse_nr` (Nummernkreis) ist nach dem Anlegen nie mehr änderbar — `kasse_speichern.php` lässt die Spalte im Update-Zweig komplett weg.
- `rksv_kassen_id` ist nach `schliesseRegistrierungAb()` gesperrt, einzige Änderung über "Neue Kassen-ID anfordern" — löst automatisch die Arbeitsplatz-Bindung (unser Hook).
- Verschiedene Kassierer dürfen sich am selben gebundenen Gerät abwechseln (Geräte-Bindung und Benutzer-Session sind komplett getrennte Konzepte) — Timeout+PIN nur für die Session-Kollision, nie für die Geräte-Kasse-Zuordnung selbst.

**Kleine bekannte Unschönheit (kein Sicherheitsproblem):** ✅ behoben 2026-07-09 — `ArbeitsplatzService::bindeAnKasseBeiBfrAbschluss()` prüft jetzt vorab per `findByToken()`, ob der Geräte-Token schon an eine ANDERE Kasse gebunden ist, und wirft eine `RuntimeException` mit Klartext-Meldung statt die rohe PDOException (UNIQUE-Constraint auf `geraete_token`) durchfallen zu lassen. `kasse_registrierung_speichern.php` fängt das ab, Registrierung selbst bleibt abgeschlossen (nur die Geräte-Bindung schlägt fehl), Fehlermeldung landet in `$_SESSION['fehler']`.

**How to apply:** Nach dem Hardware-Test die heute erarbeitete BFR/Nicht-BFR-Ablauf-Erklärung (siehe Chat-Verlauf 2026-07-07) in `docs/handbuch/` UND `bedienungsanleitung.php` übernehmen (siehe [[feedback_beide_handbuecher]]) — bewusst erst danach, damit die Erklärung an den echten Tests noch geschärft werden kann.

## ✅ Echter BFR-Hardware-Test durchgeführt (2026-07-08) — Startbeleg-Registrierung erfolgreich, mehrere echte Bugs gefunden+gefixt

Laptop mit A-Trust-Signaturkarte (Demo-UID, Belege gehen NIE an echtes FinanzOnline — bewusst isolierte Testumgebung) + BFR installiert. Kompletter Ablauf laut `BFR_Installationsanleitung.pdf` (Schritt 1-9) durchgespielt: Karte getestet, Kassen-ID `DEMOvNahtlOS` vergeben, Startbeleg im BFR-Tool erstellt, danach bei uns in `kasse_registrierung.php` (Kasse id=4, neu angelegt) `/state` abgerufen und Registrierung abgeschlossen.

**Doku-Fund:** Läuft die ERP-Software auf einem anderen Rechner als BFR (Server/Client-Architektur, wie bei uns Standard), MUSS im BFR-Tool unter "Service" das Häkchen **"Zugriff für Netzwerkkassen erlauben"** gesetzt sein — sonst nimmt BFR nur Verbindungen von echtem `127.0.0.1` an, auch wenn Firewall/Port offen sind. `bfr_url` muss dann die tatsächliche Netzwerk-IP des BFR-Rechners sein (nicht `127.0.0.1`, das zeigt sonst auf den Server selbst). **TODO:** in `docs/installation.md` Abschnitt 13 nachtragen.

**Arbeitsplatz-Bindung zum ersten Mal live getestet** (siehe oben, 2026-07-07 nur Backend getestet): funktionierte beim ersten echten Durchlauf sofort — Browser der die Registrierung abschließt wird automatisch an Kasse 4 gebunden (Token-Sync via `window.AP_TOKEN_SYNC` → localStorage), kein manueller Auswahl-Dialog nötig.

**UI-Lücke notiert:** Kasse-Name/-Nummer wird im Kasse-Header nirgends angezeigt — auf welcher Kasse man gerade ist, ist nicht erkennbar. Für später vorgemerkt.

### Vier echte Bugs gefunden und gefixt (alle erst durch die allererste echte `bfr_url` sichtbar geworden — vorher lief kein Kasse-Datensatz je durch den entsprechenden Codepfad)

1. **`KassenService::erstelleBon()` — `$steuer`-Variable doppelt verwendet:** Zeile 231 füllt `$steuer` als Array (Steuergruppen a-e für die BFR-Signatur), Zeile 337 überschreibt dieselbe Variable für den Auftrag-Spiegel-Eintrag als reine Zahl (Akkumulator). Die spätere BFR-Signierung (Zeile 440f) griff dadurch auf `null` statt der echten Steuergruppen zu. **Folge real beobachtet:** zwei echte, signierte Belege gingen mit korrektem Gesamtbetrag (`T`, unbetroffen) aber Steuergruppen=0,00 an den BFR raus — auf einer echten (nicht Demo-)Karte wäre das ein Fall für eine komplette Neu-Registrierung der Kassen-ID gewesen (DEP mit falscher Steueraufteilung ist nicht nachträglich reparierbar). **Fix:** zweite Variable in `$steuerBetrag` umbenannt.
2. **`bon_speichern.php` — `kasse_id` kam trotz gegenteiligem Kommentar aus dem Client-Payload**, nicht aus der server-geprüften `$aktuelleKasseId` (Arbeitsplatz-Bindung). Fix: `$bonDaten['kasse_id'] = $aktuelleKasseId`.
3. **Negativ-Zähler-Sperre (`wuerdeUmsatzzaehlerNegativWerden`) fehlte im Retour-Pfad** — existierte bisher nur in `storniereBon()`. Jackys Einwand: eine Bestellung kann auf Kasse A bezahlt und auf Kasse B zurückgegeben werden, jede Kasse hat aber ihren eigenen Umsatzzähler — ein Retour-Betrag über dem lokalen Zähler dieser Kasse hätte bisher unsigniert/mit falschem "ausgefallen"-Status durchgebucht, NACH bereits erfolgter Barauszahlung und Lagerkorrektur. Fix: gleicher Vorab-Check jetzt auch in `bon_speichern.php` vor `erstelleBon()`, bei negativem `bruttobetrag`.
4. **`zeileMinus()` in `bon.php` löschte die Zeile komplett, sobald Menge 1 erreicht war** (auch bei Auftrags-Positionen) — dadurch war eine **vollständige** Rückgabe eines Postens mit ursprünglicher Menge 1 unmöglich (Zeile verschwand aus dem Warenkorb, `berechneAbrechnungsModus()` sah sie dann gar nicht mehr, Klick auf "Bezahlen" tat scheinbar nichts). Fix: bei `vonAuftrag`-Zeilen bleibt die Zeile mit `menge=0` sichtbar; `zeilePlus()` kann das jetzt bis `original_menge` rückgängig machen (vorher für Auftrags-Zeilen komplett gesperrt).

**Zusätzlicher Workflow-Fix:** `auftragWaehlen()` zeigte bei JEDEM Lieferstatus außer `abholbereit` den "Ware wird mitgenommen / nur Zahlung"-Dialog — bei bereits `versendet`/`abgeschlossen` Aufträgen (Kunde bringt eine verschickte Bestellung persönlich zur Kasse zurück) passt keine der beiden Optionen. Jetzt: bei `versendet`/`abgeschlossen` wird der Dialog übersprungen, Feedback-Hinweis "Menge reduzieren um Rückgabe zu erfassen" stattdessen.

**Alles live verifiziert:** 3 Test-Bons auf Kasse 4 (davon 2 mit dem Steuergruppen-Bug), beide sauber über den normalen Storno-Weg korrigiert (Lagerbestand, Auftragsstatus, Umsatzzähler stimmen danach wieder), künstlich niedrig gesetzter Zähler (5,00€) zum Testen der Retour-Sperre verwendet.

**Update 2026-07-08, gleicher Tag:** Redesign Auftrag-Lade-Flow wurde noch am selben Tag umgesetzt — siehe [[project_kasse_bon_design]], Abschnitt "✅ Redesign Auftrag-Lade-Flow FERTIG". Dabei den bisher größten Einzelfund des Tages gemacht: `kassen_bon_positionen.block` ENUM fehlte 'retour' komplett (seit 2026-06-29), jede Retour-Zeile landete lautlos mit leerem block-Wert in der DB — Migration 119 behoben.

**Korrektur:** die vermutete Lücke "Dokumente-System protokolliert Rechnungen nicht zuverlässig" (aus `auftrag_dokumente` ohne 'rechnung'-Zeile geschlossen) war ein Fehlschluss — echte Rechnungen laufen über eine eigene `rechnungen`-Tabelle (`DokumentService`), `auftrag_dokumente` ist nur für Auftragsbestätigung/Lieferschein/Gutschrift. Kein offener Punkt.

**Weiterhin offen:** Freitext-Retour + Packplatz-Benachrichtigung + Mehrere-Aufträge-pro-Bon (wartet auf Barbara) (siehe [[project_kasse_bon_design]]).

**✅ Erledigt 2026-07-09 (alle drei kleinen Nachträge):** Doku-Nachtrag in `docs/installation.md` Abschnitt 13 (Netzwerkkassen-Häkchen + Hinweis auf die neue bfr_url-Selbstheilung); Kasse-Name/-Nummer fehlte tatsächlich nur in `bon.php`s eigenem Header (`shell_top.php` zeigte `kasse_nr` schon seit 2026-07-04 — die Lücke betraf nur den POS-Bildschirm selbst, wo Kassiererinnen die meiste Zeit verbringen), jetzt in der bestehenden `ph-sub`-Zeile ergänzt (`Name (Nr.) · Lager: X`), keine neue UI-Komponente, passt zu Barbaras "keine großen Design-Änderungen"-Vorgabe.

**2026-07-08, Abschluss:** Jackys Extremtest (2 Aufträge kombiniert an der Kasse mit ERP+Freitext-Artikeln) plus vollständige Doppel-Gutschrift-Sperre (Migration 120, `menge_retourniert`) — siehe [[project_kasse_bon_design]], Abschnitt "✅ Extremtest + Doppel-Gutschrift-Sperre FERTIG".
