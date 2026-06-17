---
name: project-preise
description: "Vollständiger Preise-Tab Plan: DB-Design, Logik, Aktionen, Marge — alles was vor dem Bau bekannt ist"
metadata: 
  node_type: memory
  type: project
  originSessionId: e92b8de5-2100-45b7-b6b1-0eeacfcb09d5
---

Stand: 2026-06-14

## Was schon in der DB steht

**`artikel`:**
- `brutto_vk` DECIMAL(8,2) — Basis-Anzeige-Preis
- `inhalt_menge` DECIMAL(8,3) — z.B. 50 (für 50g-Knäuel)
- `inhalt_einheit` VARCHAR(20) — z.B. 'g'
- `grundpreis_bezugsmenge` DECIMAL(8,3) — z.B. 100 (für "je 100g")
- `grundpreis_anzeigen` TINYINT(1) — Schalter

**`artikel_preise`:** id, artikel_id, kundengruppen_id, brutto_vk, netto_vk, gueltig_ab, gueltig_bis, erstellt_am

**`kundengruppen`:** Endkunden (0%), Händler (15%), Kleingewerblich-Künstler (10%), Endkunden-Rechnung (0%)
- `rabatt_prozent` bleibt als Info-Feld, wird aber NICHT für Preisberechnung genutzt — echte Festpreise pro KG stattdessen

## Fehlende DB-Felder / Migrations

### 028 — `artikel.uvp` + `artikel.preise_vererben`
```sql
ALTER TABLE artikel
    ADD COLUMN uvp              DECIMAL(8,2) NULL    AFTER brutto_vk,
    ADD COLUMN preise_vererben  TINYINT(1)   NOT NULL DEFAULT 0 AFTER uvp;
```
- `uvp` = Unverbindliche Preisempfehlung / Streichpreis ("war X€"). Hauptverwendung: Shop.
- `preise_vererben` = Flag auf Vater-Artikeln: Kinder erben KG-Preise + Staffeln (können dennoch eigene haben → "!"-Indikator)

### 029 — `artikel_staffelpreise`
```sql
CREATE TABLE artikel_staffelpreise (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artikel_id       INT UNSIGNED NOT NULL,
    kundengruppen_id INT UNSIGNED NOT NULL,
    menge_ab         DECIMAL(10,3) NOT NULL,
    brutto_vk        DECIMAL(8,2) NOT NULL,
    netto_vk         DECIMAL(8,2) NOT NULL,
    erstellt_am      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (artikel_id) REFERENCES artikel(id),
    FOREIGN KEY (kundengruppen_id) REFERENCES kundengruppen(id)
);
```
Staffelpreise immer pro Kundengruppe. Selten genutzt bei MeaLana, aber vorhanden.

### 030 — `preis_aktionen` + `preis_aktionen_positionen`
```sql
CREATE TABLE preis_aktionen (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    typ         ENUM('sale','lieferant_aktion') DEFAULT 'sale',
    gueltig_ab  DATETIME NOT NULL,
    gueltig_bis DATETIME NULL,   -- NULL = kein festes Ende (für Sale-Artikel)
    aktiv       TINYINT(1) DEFAULT 1,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE preis_aktionen_positionen (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aktion_id            INT UNSIGNED NOT NULL,
    artikel_id           INT UNSIGNED NOT NULL,
    kundengruppen_id     INT UNSIGNED NULL,      -- NULL = gilt für alle KG
    brutto_vk            DECIMAL(8,2) NOT NULL,
    netto_vk             DECIMAL(8,2) NOT NULL,
    preis_vorher_brutto  DECIMAL(8,2) NULL,      -- für automatisches Zurücksetzen
    FOREIGN KEY (aktion_id) REFERENCES preis_aktionen(id),
    FOREIGN KEY (artikel_id) REFERENCES artikel(id)
);
```

**Typen:**
- `sale`: einzelner Artikel, spontan, gueltig_bis kann NULL sein
- `lieferant_aktion`: Lieferanten-Sonderaktion, mehrere Artikel, Start+Ende Pflicht, VK vom Lieferanten vorgegeben

## Effektivpreis-Logik (dreistufig)

```
1. Aktive preis_aktionen_position für Artikel + KG?  →  Aktionspreis
2. Eintrag in artikel_preise für KG?                 →  Kundengruppen-Festpreis
3. Fallback                                           →  artikel.brutto_vk
```

Implementiert in `PreisService::getEffektiverPreis(int $artikelId, int $kgId): float`

## Jarvis — Automatische Aktivierung/Deaktivierung

Kein echter Cron (XAMPP lokal). Lazy-Check beim Laden von detail.php / liste.php:
`PreisService::pruefPendingAktionen()`:
- Aktionen wo `gueltig_ab <= NOW()` und noch nicht aktiviert → Logger-Eintrag als Jarvis
- Aktionen wo `gueltig_bis < NOW()` und noch aktiv → deaktivieren + Logger
- Gleiche Architektur wie Auslaufartikel-Check in LagerService

## Grundpreisangabe

**Hat NICHTS mit EK zu tun.** Formel:
```
Effektiver VK brutto ÷ inhalt_menge × grundpreis_bezugsmenge
Beispiel: 5,99€ ÷ 50g × 100g = 11,98€/100g
```

Bei aktivem Aktionspreis muss der Grundpreis vom **reduzierten** VK berechnet werden (gesetzliche Pflicht AT/DE).

Einheiten-Umrechnung (EK kommt in kg, VK-Einheit 50g, Grundpreis per 100g):
- Das ist eine **Kalkulations-Hilfe** für die Marge, nicht für die Grundpreisanzeige
- Grundpreisanzeige rechnet immer aus VK + inhalt_menge + bezugsmenge

## Marge-Berechnung

**EK-Quelle:** Standard-Lieferant (`artikel_lieferanten.standard = 1`) → dessen `netto_ek`
- Mehrere Lieferanten mit verschiedenen EKs → Standard-Lieferant ist Kalkulationsbasis (wie JTL)
- Kein Standard gesetzt → Marge zeigt "–"
- Gewichteter Durchschnitt: Schritt 2, kommt mit Einkaufsmodul + echten WE-Daten

**Formel:**
```
Marge% = (netto_vk - netto_ek) / netto_vk × 100
```

**Preisvorschlag bei neuem Artikel:**
- Gewünschte Marge% eingeben → schlägt netto_vk + brutto_vk vor
- Als Block im Preise-Tab (kein separates Modul nötig)

## Was wo gebaut wird

| Was | Wo |
|---|---|
| PreisRepository + PreisService | neu: `src/modules/preise/` |
| Preise-Tab in detail.php | KG-Festpreise, Staffeln, UVP, Grundpreis-Vorschau, Marge-Block |
| Preis-Aktionen-Modul | neue Seiten: `public/preisaktionen/` |
| Sale-Chip in liste.php | nach Migration 030 — preis_aktionen_positionen prüfen |
| Jarvis-Check | PreisService, aufgerufen von detail.php + liste.php |

## Preise-Tab UI-Logik

### Checkboxen / Feature-Toggles
Oben im Tab: Checkboxen die Sektionen ein-/ausblenden.
- "Staffelpreise" — keine eigene DB-Spalte nötig: wenn `artikel_staffelpreise`-Einträge vorhanden → Checkbox gecheckt + Tabelle sichtbar. Wenn keine Einträge → nur Checkbox (unchecked), Tabelle ausgeblendet.
- "Sonderpreise / Aktionen" — analog: Einträge in `preis_aktionen_positionen` vorhanden → sichtbar.
- Spart Platz bei Artikeln ohne diese Features.
- Beim Aktivieren (Checkbox anklicken): Tabelle erscheint mit "Ersten Eintrag anlegen"-Button.

### Vererbung Vater → Kinder
- Neues DB-Flag: `artikel.preise_vererben TINYINT(1) DEFAULT 0` — auf Vater-Artikel setzen
- Wenn aktiv: Kinder erben KG-Preise, Staffeln und Aktionen vom Vater (gelesen aus Vater, nicht kopiert)
- Kinder können trotzdem **eigene Preise** haben — diese überschreiben die Vererbung
- Wenn Kind eigene Preise hat die vom Vater abweichen → **"!"-Symbol** beim Vater-Tab und beim Kind
- Gleiche Logik wie der bestehende Varianten-Abweichungs-Indikator in liste.php

### Kinder — "Preise vom Vater übernehmen" Button
- Button im Preise-Tab bei Kind-Artikeln (nur sichtbar wenn `vaterartikel_id` gesetzt)
- Aktion: löscht eigene `artikel_preise`-Einträge des Kinds + eigene Staffeln → Kind fällt auf Vater-Vererbung zurück
- Logger-Eintrag
- "!"-Symbol verschwindet danach (Kind hat keine Abweichung mehr)

### Artikel kopieren (kopieren_speichern.php)
Muss die vollständige Preisstruktur mit übernehmen:
- `artikel_preise` → alle Zeilen für neue artikel_id kopieren
- `artikel_staffelpreise` → alle Zeilen für neue artikel_id kopieren
- `preis_aktionen_positionen` → NICHT kopieren (Aktionen sind zeitgebunden, kein Auto-Copy)
- `uvp` → wird direkt mit `artikel`-Kopie übernommen (ist Spalte auf artikel)

## Staffelpreise — Kinder-übergreifende Mengenzählung

**Anforderung:** Staffelpreisschwellen sollen über alle Kinder eines Vaters hinweg gelten.

**Beispiel:** Anhänger mit Motiv-Varianten, Staffel ab 3 Stk → 4€:
- Kunde kauft 2× Motiv-A + 2× Motiv-B = 4 Stk gesamt → "ab 3 Stk"-Staffel gilt

**Strategie-Flag auf Vater-Artikel:**
- Neues Feld `staffel_kinder_zusammen TINYINT(1) DEFAULT 1` auf `artikel` (kommt in spätere Migration)
- DEFAULT 1 = Kinder-Mengen werden zusammengezählt (Normalfall bei MeaLana)
- 0 = Staffel gilt nur je Kind-Artikel einzeln (Sonderfall, kann vorkommen)
- Im Vater-Artikel Preise-Tab: Checkbox "Staffelmengen über alle Varianten gemeinsam zählen"

**Umsetzung:** Relevant erst beim Shop-Modul / Warenkorb-Logik. Die Staffelpreise-Tabelle selbst braucht keine Änderung — nur die Preisberechnung im Warenkorb muss bei `staffel_kinder_zusammen=1` die Gesamtmenge aller Kinder des gleichen Vaters summieren.

**Why:** In bisheriger WAWI war das nicht abbildbar → Staffeln galten nur pro SKU, nicht pro Produktfamilie. Hauptfall bei MeaLana sind Zubehörartikel mit Varianten (Motive, Farben) wo Mengenrabatt über alle Varianten gelten soll.

## Offene Frage (noch nicht entschieden)
- `grundpreis_bezugseinheit` fehlt noch auf `artikel` — brauchen wir ein explizites Feld dafür oder reicht `inhalt_einheit` als Bezugseinheit? (Bei Garn fast immer 'g', aber bei Meter-Ware wäre es 'm')
