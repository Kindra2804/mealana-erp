---
name: project-kleinunternehmer
description: "Major-Feature: Kleinunternehmer-Modus — steuerliche Unterschiede die viele Module betreffen"
metadata: 
  node_type: memory
  type: project
  originSessionId: e92b8de5-2100-45b7-b6b1-0eeacfcb09d5
---

## Hintergrund

MeaLana ERP soll auch für Kleinunternehmer (AT: § 6 Abs. 1 Z 27 UStG, DE: § 19 UStG) nutzbar sein. Barbara hat das aufgebracht wegen der EK-Brutto-Eingabe beim Lieferanten.

**Why:** Kleinunternehmer haben keine Vorsteuerabzugsberechtigung — für sie sind Brutto- und Nettopreise identisch. EK-Preise werden brutto bezahlt und brutto bewertet. Kunden bekommen Rechnungen ohne Steuerausweis.

## Globaler Schalter (system_einstellungen)

```sql
besteuerungsart VARCHAR(20) DEFAULT 'normal'
-- Werte: 'normal' | 'kleinunternehmer'
-- Evtl. später: 'pauschal' (Landwirtschaft etc.)
```

## Auswirkungen pro Modul

### Artikel / Preise
- `normal`: brutto_vk ≠ netto_vk (Steuer dazwischen)
- `kleinunternehmer`: brutto_vk = netto_vk (kein Steueranteil)
- **Heute schon:** EK beim Lieferanten brutto eingeben können (Feld `brutto_ek` ergänzen oder Umrechnung via Flag)

### Shop-Anzeige
- `normal B2C`: Preise mit "inkl. X% MwSt." anzeigen
- `normal B2B`: Netto-Preise + "zzgl. MwSt."
- `kleinunternehmer`: Ein Preis, kein Steuerhinweis (gesetzlich verpflichtend KEIN Steuerausweis)

### Lagerbewertung
- `normal`: Lagerwert = Summe(netto_ek × bestand) — Steuer ist durchlaufend
- `kleinunternehmer`: Lagerwert = Summe(brutto_ek × bestand) — volle Kosten, kein Vorsteuerabzug

### Rechnungen / Kassabons
- `normal`: Steuerausweis mit % und Betrag pro Steuersatz (gesetzlich Pflicht)
- `kleinunternehmer`: Hinweistext "Kein Steuerausweis gem. § 6 (1) Z 27 UStG" statt Steueraufschlüsselung

### RKSV (Österreich Registrierkassenpflicht)
- Bleibt verpflichtend unabhängig vom Modus
- Steuerbeträge in RKSV-Format sind bei Kleinunternehmer = 0 (alles in Steuersatz 0)

## Was jetzt schon tun (ohne vollständigen Umbau)

1. ~~`artikel_lieferanten.brutto_ek` Feld ergänzen~~ ✅ Migration 038, Commit 5922ccc (2026-06-17)
2. ~~`system_einstellungen.besteuerungsart` anlegen (Migration)~~ ✅ Migration 038
3. ~~detail.php Lieferanten-Tab: brutto_ek Eingabefeld mit Auto-Kalkulation~~ ✅
4. In jedem neuen Modul: Besteuerungsart aus Settings lesen, entsprechend rendern

## How to apply

Vor jedem neuen Modul (Kassa, Auftrag, Shop-Sync) prüfen: funktioniert das auch im Kleinunternehmer-Modus? Steuer-Logik immer über Helper-Funktion (nicht inline) damit ein Schalter alles ändert.

Beim Kassa-Modul: RKSV-Testmodus zuerst mit `normal`, dann Kleinunternehmer-Modus verifizieren.
