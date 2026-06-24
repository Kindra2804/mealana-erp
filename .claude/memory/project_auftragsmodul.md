---
name: project-auftragsmodul
description: "Auftragsmodul Design: Status-Workflow, DB-Tabellen, Nummerierung, Templates, WooCommerce-Sync"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9a44da56-fbce-4da5-b4f6-17b472024d63
---

## Grundentscheidungen

- **Zahlungsstatus + Lieferstatus getrennt** (wie JTL) — unabhängig voneinander änderbar
- **Keine Kanal-Prefixes** in Nummern — Kanal-Chip + Filterbox in der Liste reichen
- **Auftragsnummer**: A-2026-00001 (pro Jahr neu, lückenlos)
- **Rechnungsnummer**: R-2026-00001 (eigene Sequenz, getrennt von Auftrag, AT UStG §11)
- **Template-System**: Twig + Dompdf — Weitergabefähigkeit an andere Betriebe

## Status-Workflow

```
ZAHLUNGSSTATUS: ausstehend → bezahlt | erstattet | storniert
                (teilbezahlt nur für Deposits/Strickaufträge später)

LIEFERSTATUS:
neu → in_bearbeitung → versandbereit → versendet → abgeschlossen
                     → teilgeliefert → abgeschlossen
                     → zurueckgestellt → in_bearbeitung (nach WE)
    → storniert (jederzeit)
```

## Mahnwesen

**Vorkasse** (Zahlung kommt nicht):
- 14 Tage ohne Zahlungseingang → automatische Erinnerungsmail
- 30 Tage → erscheint in Dashboard-Liste "Zum Stornieren" → manueller Klick → Stornomail + Lagerbestand freigeben

**Rechnungszahler** (wenige Stammkunden):
- Mahnstufen 1 + 2 — wird gebaut wenn erste Rechnungszahler im System

## DB-Tabellen (Migration 060-063)

```sql
auftraege (
  id, auftrag_nr VARCHAR(20) UNIQUE,    -- A-2026-00001
  kunden_id INT FK NULL,                -- NULL = Laufkunde
  kunden_snapshot JSON,                 -- Adresse einfrieren!
  lieferadresse_snapshot JSON,
  rechnungsadresse_snapshot JSON,
  kanal ENUM(woocommerce, manuell, kasse),
  kanal_auftrag_id INT NULL,            -- WC Order-ID
  zahlungsstatus ENUM(ausstehend, bezahlt, teilbezahlt, erstattet, storniert),
  lieferstatus ENUM(neu, in_bearbeitung, versandbereit, teilgeliefert,
                    zurueckgestellt, versendet, abgeschlossen, storniert),
  zahlungsart ENUM(vorkasse, paypal, rechnung, bar, gutschein, gemischt),
  zahlungsbedingung_id INT FK NULL,
  gutschein_id INT FK NULL,
  gutschein_betrag DECIMAL(10,2) DEFAULT 0,
  versandkosten DECIMAL(10,2) DEFAULT 0,
  rabatt_gesamt DECIMAL(10,2) DEFAULT 0,
  nettobetrag DECIMAL(10,2),
  steuerbetrag DECIMAL(10,2),
  bruttobetrag DECIMAL(10,2),
  bezahlt_am DATETIME NULL,
  mahnung_stufe TINYINT DEFAULT 0,      -- 0/1/2
  mahnung_gesendet_am DATETIME NULL,
  tracking_nr VARCHAR(100) NULL,
  versanddienstleister VARCHAR(50) NULL,
  notiz_intern TEXT,
  notiz_versand TEXT,                   -- aufs Packerl
  erstellt_am, aktualisiert_am, erstellt_von INT FK benutzer
)

auftrag_positionen (
  id, auftrag_id INT FK, artikel_id INT FK,
  chargen_id INT FK NULL,               -- Farbkonsistenz!
  bezeichnung VARCHAR(255),             -- eingefroren
  ean VARCHAR(20),                      -- eingefroren
  menge INT, menge_geliefert INT DEFAULT 0,
  einzelpreis_netto DECIMAL(10,2),
  steuer_prozent DECIMAL(5,2),
  rabatt_prozent DECIMAL(5,2) DEFAULT 0,
  gesamtpreis_netto DECIMAL(10,2),
  sort_order INT
)

rechnungen (
  id, rechnung_nr VARCHAR(20) UNIQUE,   -- R-2026-00001
  auftrag_id INT FK,
  nettobetrag DECIMAL(10,2),
  steuerbetrag DECIMAL(10,2),
  bruttobetrag DECIMAL(10,2),
  faellig_am DATE NULL,
  storniert BOOL DEFAULT 0,
  storno_von INT FK NULL,               -- bei Gutschrift: welche Rechnung
  erstellt_am, erstellt_von INT FK benutzer
)

auftrag_dokumente (
  id, auftrag_id INT FK,
  typ ENUM(auftragsbestaetigung, lieferschein, rechnung, gutschrift, mahnung),
  dateiname VARCHAR(255),
  erstellt_am, erstellt_von INT FK benutzer
)

auftrag_statuslog (
  id, auftrag_id INT FK,
  felder_geaendert JSON,
  notiz TEXT,
  erstellt_am, erstellt_von INT FK benutzer
)
```

## WooCommerce-Import

Beide Modi geplant, pro Kanal konfigurierbar:
- **Manuell**: Button "Jetzt importieren" + Timestamp letzter Import
- **Automatisch**: Cronjob alle X Minuten (Standard: 10 Min.)
- Cronjob macht beides: Aufträge holen (Pull) + Lagerbestand pushen (Push)
- Bestand-Push ist kritisch für Kassen-Warnung "Artikel online gekauft"

## Packplatz

Eigene Seite `public/packplatz/` (analog Wareneingang als eigenes Modul).
Vollbild, Tablet/Touch-freundlich, Charge-Auswahl, Abschluss → "versandbereit".

## Template-System (Dokumente)

```
erp/templates/dokumente/
├── rechnung/standard.html.twig
├── lieferschein/standard.html.twig
├── auftragsbestaetigung/standard.html.twig
└── mahnung/standard.html.twig
```
Variablen: {firma}, {auftrag}, {positionen}, {kunde}, {summen}
Engine: Twig + Dompdf (reines PHP, kein externes Binary)

## Fehlbestand-Flow

Auftrag eingeht → Bestand < Menge → lieferstatus='zurueckgestellt'
→ Einkauf sieht Fehlbestand → Bestellung beim Lieferanten
→ Wareneingang → ERP löst zurueckgestellte Aufträge auf (FIFO nach erstellt_am)

## Auftragsliste-Features

- Kanal-Chip (woocommerce / manuell / kasse) sichtbar in der Liste
- Filterbox: Kanal, Zahlungsstatus, Lieferstatus, Datum, Kunde
- Spalten-Picker (wie Artikelliste)

## Rabatt-Design (offen — nächste Session)

- `auftrag_positionen.rabatt_prozent` ist vorhanden, aber nur %-Rabatt
- Erweiterung geplant: `rabatt_typ ENUM('prozent','betrag')` + `rabatt_betrag DECIMAL(10,2)`
- Typ 'prozent': bestehende Logik; Typ 'betrag': Fixbetrag vom Gesamtpreis abziehen
- Anzeige in detail.php + neu.php anpassen wenn gebaut
- **Why:** B2C-Kunden bekommen oft pauschale €-Nachlässe (z.B. "5€ Rabatt"), nicht %-Rabatte

## Versandklassen-Features (offen)

- **Teillieferung als Versandoption**: eigene Versandklasse mit Aufpreis (z.B. „Versand + Teillieferung AT"), wählbar im Shop/Auftrag
- **Versandkostenfrei ab X**: in system_einstellungen, pro Shop aktivier-/deaktivierbar + Betrag einstellbar
