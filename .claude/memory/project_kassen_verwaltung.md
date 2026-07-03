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

## 🔴 Zwei Lücken bei A4-Rechnung/Mailversand gefunden 2026-07-03 (Jackys Verdacht bestätigt)

- **Kein nachträglicher Zugriff:** `bon_a4.php` ist nur direkt nach Bon-Erstellung per Button aus `bon.php` heraus verlinkt (`window.open(...bon_a4.php?id=' + _letzterBonId)`). Im Bon-Journal (`bon_journal.php`, die Such-/Verlaufsseite) gibt es **keinen Link** zu `bon_a4.php` für ältere Bons — technisch über die URL erreichbar, aber kein UI-Zugang.
- **Mailversand nutzt nicht die A4-Version:** In `bon_speichern.php` (Abholbestätigungs-Mail, ~Zeile 410) wird ein eigens inline gebautes **68mm-Thermobon-PDF** (Courier New, 68mm Breite, Bondrucker-Optik) als Mail-Anhang erzeugt — nicht die vorhandene, deutlich bessere A4-Rechnung aus `bon_a4.php`.

**How to apply:** Beide sind kleine, klar umrissene Fixes: (1) Link/Button in `bon_journal.php` pro Zeile auf `bon_a4.php?id=X` ergänzen. (2) `bon_speichern.php` beim Mailanhang auf die A4-Rendering-Logik aus `bon_a4.php` umstellen statt der doppelt gepflegten 68mm-Variante.

**Why:** Vermeidet dauerhafte Doppelpflege zweier SQL-Dialekte; nutzt BFRs eigene Offline-Fähigkeit direkt statt sie hinter einer zusätzlichen Server-Schicht zu verstecken.
**How to apply:** Bei der Implementierung: Server-Seite (Pre-/Post-Sync-API in `MesseSyncService`/`ajax_messe.php`) ist bereits fertig und wird unverändert wiederverwendet — nur der Client ist neu zu bauen.
