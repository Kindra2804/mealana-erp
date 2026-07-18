---
name: project-aktionen-modul
description: "Aktions-Modul: globales Modul, kategorie-basierte Auto-Preissetzung, SALE-Override — ✅ FERTIG (im Code verifiziert 2026-07-18, der vermutete Blocker war bereits behoben)"
metadata: 
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
  modified: 2026-07-18T18:00:35.859Z
---

## Warum

DROPS Fabel allein = 61+ Kind-Artikel. Ohne dieses Feature = 61 manuelle Preiseinträge.
Mit diesem Feature = Barbara trägt Preise pro Sub-Achsen-Dimension ein (z.B. UNI/PRINT/LONGPRINT), Kinder erben automatisch.

Sub-Achsen wurden GENAU dafür gebaut — die Dimensions-Labels kommen direkt aus den Sub-Achsen-Namen, kein freies Eingabefeld.

---

## Finales DB-Design (abgestimmt 2026-06-18)

### Neue Tabellen

```sql
aktionen
  id, name VARCHAR(100), beschreibung TEXT, erstellt_am TIMESTAMP
  -- kein lieferant_id: globales Modul

aktionen_kategorien
  id, aktion_id FK, kategorie_id FK
  gueltig_ab DATE NOT NULL, gueltig_bis DATE NOT NULL
  -- UNIQUE: (aktion_id, kategorie_id)

aktionen_artikel_preise
  id, aktion_id FK, artikel_id FK (nur Väter!)
  sub_achse_id INT NULL FK → varianten_achsen  -- NULL = Artikel ohne Sub-Achsen (Einheitspreis)
  brutto_vk DECIMAL(8,2), netto_vk DECIMAL(8,2)
  -- Kinder erben über ihre varianten_achsen-Zuordnung zur Sub-Achse
```

### Erweiterte Tabellen

```sql
kategorien: + ist_aktions_kategorie TINYINT(1) DEFAULT 0

preis_aktionen_positionen (nur noch SALE-Overrides):
  + gueltig_ab   DATETIME NULL   -- NULL = sofort
  + gueltig_bis  DATETIME NULL   -- NULL = kein festes Ende
  + bis_lagerstand_null TINYINT(1) DEFAULT 0  -- endet wenn bestand = 0
  -- Wenn alle drei inaktiv: aktiv bis manuell deaktiviert
  -- typ immer 'sale'
```

### Migrations-Nummern
Nächste freie Migration prüfen (zuletzt 041) — wahrscheinlich 042–045.

---

## Prioritäts-Kette (Effektivpreis)

```
1. SALE-Override (preis_aktionen_positionen, typ='sale', zeitlich aktiv)  →  höchste Prio
2. Kategorie-Aktionspreis (aktionen_artikel_preise, Aktion zeitlich aktiv)
3. KG-Festpreis (artikel_preise für Kundengruppe)
4. artikel.brutto_vk  →  Fallback
```

Kategorie-Aktionspreise gelten **nur für Endkunden** (Standard-Kundengruppe).
Staffelpreise und andere Kundengruppen bleiben von allem unberührt.

---

## Regel: Zeitliche Überschneidung

Ein Vater-Artikel kann in mehreren Aktions-Kategorien sein (z.B. Frühjahrs-Sale + Herbst-Sale).
**ABER:** Nie in zwei Aktions-Kategorien mit überschneidenden Zeiträumen gleichzeitig.
→ Validierung beim Speichern: prüfen ob aktionen_kategorien für diese kategorie_id + aktion_id Überschneidung hat.

---

## SALE-Override — 3 Modi

| Modus | gueltig_ab | gueltig_bis | bis_lagerstand_null |
|---|---|---|---|
| Sofort + manuell deaktivieren | NULL | NULL | 0 |
| Zeitraum (von–bis) | gesetzt | gesetzt | 0 |
| Von jetzt bis Lagerstand=0 | NULL | NULL | 1 |

Beim Lazy-Check in PreisService: wenn `bis_lagerstand_null=1` und `bestand<=0` → SALE automatisch deaktivieren + Logger.
Wenn SALE deaktiviert wird und Kategorie-Aktionspreis noch aktiv → dieser greift sofort.

---

## Visuelle Indikatoren

### Kategorien-Liste
- `⏰` grau + gedimmte Schrift = Aktions-Kategorie, aber außerhalb Zeitraum (geplant/abgelaufen)
- `⏰` bunt = Aktions-Kategorie, gerade aktiv (innerhalb Zeitraum)
- Keine normale Kategorie = kein Symbol

### Artikel-Liste (Status-Spalte)
- `⏰` bunt = Kategorie-Aktionspreis ist aktiv für diesen Artikel
- `SALE` farbig + `⏰` grau = SALE-Override aktiv (Aktion ist da, Sale hat Vorrang)
- Nur geplante Aktion (nicht aktiv) = kein Symbol

### Artikel-Detail (Preise-Tab)
- Sektion "AKTIONSPREISE": zeigt alle Kategorie-Aktionen (geplant + aktiv) die diesen Artikel betreffen
- Sektion "SALE-OVERRIDE": Eingabe mit optionalem Zeitraum / bis-Lagerstand=0

---

## Dimension-Labels im Preiseingabe-Screen

Kommen automatisch aus `varianten_achsen` (Sub-Achsen des Vater-Artikels).
- Artikel mit Sub-Achsen: eine Preisspalte pro Sub-Achse (z.B. UNI / PRINT / LONGPRINT)
- Artikel ohne Sub-Achsen: ein Preisfeld (Einheitspreis)
- Barbara sieht also: Vater-Name + Spalten = Sub-Achsen-Namen

---

## Bauplan (Reihenfolge)

| Schritt | Was | Wo |
|---|---|---|
| 1 | DB Migrations 042–045 | migrations/ |
| 2 | Kategorien: ist_aktions_kategorie Checkbox + ⏰-Anzeige | kategorien/liste.php |
| 3 | Aktions-Modul: liste.php + bearbeiten.php | public/aktionen/ |
| 4 | Preiseingabe-Screen (Teil von bearbeiten.php) | public/aktionen/ |
| 5 | Artikel-Detail: Aktions-Sektion im Preise-Tab + SALE-Override | detail.php |
| 6 | Artikel-Liste: ⏰ und SALE-Chips | liste.php |
| 7 | PreisService: Prioritätskette + pruefPendingAktionen() | src/modules/preise/ |

---

## Aktivierung

- **Jetzt:** Manueller "Aktion starten"-Button
- **Wenn Shop angebunden:** Cronjob, DROPS-Vorgabe = exakt 0:00 Uhr Start/Ende
- **API-Vorbereitung:** eigener Shop + WooCommerce (Preise übertragen/planen) — kommt mit Shop-Modul

---

## Status (2026-06-18)
- ✅ Achsen-System fertig (Voraussetzung erfüllt)
- ⏳ Aktions-Modul: Design abgeschlossen, Bau steht aus

## ✅ Status-Korrektur 2026-07-18: komplett fertig, im Code verifiziert

Auf der Roadmap stand fälschlich "Blocker: Wert-Ebenen-Abhängigkeit + VarKombi-Update" — Jacky erinnerte sich an keinen offenen Punkt außer einem Bug (nur aktive, nicht geplante Aktionen lösten die Preisabfrage beim Kategorie-Zuweisen aus), von dem er annahm dass er behoben ist. Im Code verifiziert: **stimmt, ist behoben.**

`KategorieRepository::updateArtikelKategoriezuweisungen()` holt für das Preiseingabe-Modal explizit "Aktive + geplante (nicht abgelaufene) Aktionen" — der SQL-Filter ist nur `ak.gueltig_bis >= CURDATE()`, keine Einschränkung auf `gestartet`/heute-aktiv. Alle Bauplan-Schritte von oben (DB-Migrationen, Kategorien-Checkbox+⏰-Anzeige, `public/aktionen/` Liste+Bearbeiten+Preiseingabe, Artikel-Detail-Sektion, Artikel-Liste-Chips, `PreisService`-Prioritätskette) existieren im Code, keine TODO/Platzhalter-Reste gefunden.

**Der ursprüngliche "Wert-Ebenen-Abhängigkeit + VarKombi-Update"-Blocker-Vermerk hatte offenbar nie eine dokumentierte technische Begründung** (nur das Label wurde je in Roadmap/Index gespeichert) — vermutlich veraltet oder mit dem oben genannten, längst behobenen Bug verwechselt. Kein Hinweis im Code auf einen noch bestehenden Zusammenhang mit abhängigen Achsen oder dem VarKombi-Generator.

**How to apply:** Aktions-Modul gilt als fertig, kein aktiver Blocker mehr. Von der "zwischendurch"-Liste in [[project_roadmap_reihenfolge]] gestrichen.
