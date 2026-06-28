---
name: project-buchhaltung
description: "Buchhaltungsmodul-Planung: DATEV-Schnittstelle, Kontenplan, Österreich-spezifisch; Dashboard Card 5 aktivieren!"
metadata: 
  node_type: memory
  type: project
  originSessionId: 2201806f-a656-4f8c-9f4f-9cf04a3cdd71
---

Stand: 2026-06-28

## ⚡ Beim Buchhaltungs-Start erledigen
- Dashboard Card 5 "Offene Lieferantenrechnungen" aktivieren (TODO BUCHHALTUNG in dashboard.php)
- Mahnwesen für Rechnungszahler ausbauen (siehe unten)

## Mahnwesen — zwei Ebenen

### Bereits vorhanden (Vorkasse, einfach)
Cronjob in `cron/mahnwesen.php`: 14 Tage → Erinnerungsmail, 30 Tage → Vorschlag zur manuellen Stornierung.
Tabelle `mahnungen`: nur `typ` (erinnerung/stornierung), kein Stufen-System.

### Noch zu bauen (Rechnungszahler, mit Buchhaltung)
Echte Mahnstufen mit eigenem DB-Ausbau:
- **Stufe 1** — Zahlungserinnerung (freundlich, 0 Mahngebühr)
- **Stufe 2** — 1. Mahnung (kleine Mahngebühr, Verzugszinsen ab Fälligkeit)
- **Stufe 3** — 2. Mahnung (höhere Mahngebühr, Androhung Inkasso)
- **Stufe 4** — Inkasso-Übergabe (manuell, Buchungssatz)

Erfordert: `mahnungen.stufe INT`, `mahnungen.mahngebuehr DECIMAL`, Verzugszins-Berechnung, DATEV-Buchungssatz pro Mahnstufe, eigene Mahnbrief-Vorlage (Twig/Dompdf).

**Why:** Rechnungskunden haben echte Zahlungsziele (14/30/60 Tage), Mahngebühren sind steuerlich buchungspflichtig — das gehört in den Buchhaltungskontext, nicht in den einfachen Cronjob.

---

## ⚡ Dashboard-Aktivierung beim Buchhaltungs-Start
Dashboard Card 5 "Offene Lieferantenrechnungen" ist als Platzhalter gebaut (dashboard.php).
Beim Buchhaltungsmodul: `lieferanten_rechnungen`-Tabelle anlegen und Card-5-Block in dashboard.php aktivieren (Kommentar `/* TODO BUCHHALTUNG */` suchen).

---

## Grundsatz

Kein eigenes Buchhaltungssystem bauen. Ziel: **DATEV-Schnittstelle** (Export) damit der Steuerberater die Daten in DATEV importieren kann. Das machen alle seriösen WAWIs so (JTL, Sage, Shopware).

## DATEV-Schnittstelle

DATEV ist ein geschlossenes System für Steuerberater. Wir bauen einen **Export** im DATEV-Format.

- Format: CSV, gut dokumentiert (DATEV veröffentlicht die Spezifikation)
- Felder: Umsatz, Soll/Haben-Kennzeichen, Konto, Gegenkonto, Buchungstext, Belegdatum, Belegnummer
- Export-Zeitraum wählbar (Monat, Quartal, Jahr)
- Aufwand: Überschaubar — ein Export-Button der Buchungssätze als DATEV-CSV ausgibt

**Referenz:** DATEV Buchungsdatenschnittstelle (öffentlich zugänglich)

## Kontenplan (Österreich)

Österreich: **Österreichischer Einheitskontenrahmen** (ähnlich deutschem SKR03/SKR04).

Geplante Tabelle:
```sql
kontenplan (
    id            INT PK AUTO_INCREMENT,
    kontonummer   VARCHAR(10) NOT NULL UNIQUE,   -- '3000', '2700'
    name          VARCHAR(100) NOT NULL,          -- 'Warenerlöse 20%', 'Vorsteuer'
    typ           VARCHAR(20),                   -- 'erloes', 'aufwand', 'steuer', 'bank', 'kasse'
    aktiv         TINYINT(1) DEFAULT 1
)
```

Seed-Daten (österreichische Pflichtkonten für Einzelhandel):
- 2700 Vorsteuer
- 3000 Warenerlöse 20% MwSt
- 3100 Warenerlöse 10% MwSt
- 3300 Warenerlöse 0% (innergemeinschaftlich)
- 2500 Umsatzsteuer
- 1600 Bankkonto
- 1500 Kassa

## Mappings (Basis für automatische Kontierung)

```sql
steuerklassen_konten (
    steuerklasse_id   INT FK → steuerklassen.id,
    erloes_konto_id   INT FK → kontenplan.id,
    steuer_konto_id   INT FK → kontenplan.id
)

zahlungsart_konten (
    zahlungsart       VARCHAR(30),    -- 'bar', 'karte', 'ueberweisung'
    konto_id          INT FK → kontenplan.id
)
```

So wird jede Kassen-Buchung und jede Rechnung automatisch den richtigen Konten zugeordnet → DATEV-Export schreibt sich fast von selbst.

## Odoo als Referenz

Odoo hat ein vollständiges Doppik-System + DATEV-Export. Für **Auftragsfertigung** (Strickaufträge) und **Buchhaltung** ist Odoo ein besseres Referenz-System als JTL/Sage.
Odoo Manufacturing-Modul als Referenz für das Strickauftrags-Modul verwenden.

**Why:** Steuerberater in Österreich nutzen fast ausnahmslos DATEV oder ein kompatibles System. Ohne Export kein Jahresabschluss. Kontenplan ist die Basis damit Buchungssätze korrekt zugeordnet werden.
**How to apply:** Beim Aufbau des Kassenmoduls sofort Kontenplan + Mappings anlegen. DATEV-Export als erstes Feature im Buchhaltungsmodul.
