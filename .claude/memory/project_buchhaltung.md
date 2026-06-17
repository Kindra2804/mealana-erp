---
name: project-buchhaltung
description: "Buchhaltungsmodul-Planung: DATEV-Schnittstelle, Kontenplan, Österreich-spezifisch"
metadata: 
  node_type: memory
  type: project
  originSessionId: 2201806f-a656-4f8c-9f4f-9cf04a3cdd71
---

Stand: 2026-06-08

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
