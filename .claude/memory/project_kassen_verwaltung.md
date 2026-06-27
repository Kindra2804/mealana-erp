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
- Parken: sessionStorage (1 Bon pro Kasse)
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
