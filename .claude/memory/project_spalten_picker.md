---
name: project-spalten-picker
description: "Spalten-Picker in der Artikelliste — Konfiguration, alle Spalten, Platzhalter für zukünftige Module; ✅ 2026-08-05 Kind-Zeilen zeigen jetzt volle Spalten-Parität mit Vater-Zeilen"
metadata:
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
  modified: 2026-08-05T10:14:25.490Z
---

Stand: 2026-06-17

## Konzept

User-spezifische Spalten-Konfiguration in der Artikelliste (liste.php). Jeder User kann sehen welche Spalten er will und in welcher Reihenfolge.

**Speicherung:** `benutzer_einstellungen` Tabelle (Migration 036), Schlüssel `artikel_liste.spalten`, Wert = JSON-Array mit geordneten Spalten-Schlüsseln.

**Picker-UI:** ⚙-Button im actionbar-right → Dropdown-Panel mit Checkboxen + ↑↓-Buttons + "Zurück zum Standard"-Button.

## Fixe Spalten (immer sichtbar, nicht konfigurierbar)

- Checkbox (Massenauswahl)
- Thumbnail
- ART.-NR.
- ARTIKELNAME
- Aktionen (hover-reveal)

## Togglebare Spalten

| Schlüssel | Label | Default | Status |
|---|---|---|---|
| `status` | Status | ✅ an | ✅ baubar |
| `shops` | Shops | ✅ an | ✅ baubar (nur S-Kanäle, K-Kanäle raus) |
| `bestand` | Bestand | ✅ an | ✅ baubar |
| `preis` | Preis | ✅ an | ✅ baubar |
| `hersteller` | Hersteller | ❌ aus | ✅ baubar |
| `ean` | EAN | ❌ aus | ✅ baubar |
| `einheit` | Einheit | ❌ aus | ✅ baubar |
| `kategorie` | Kategorie | ❌ aus | ✅ baubar (truncate + title-tooltip) |
| `geaendert_am` | Geändert am | ❌ aus | ✅ baubar (a.geaendert_am) |
| `ek` | EK-Preis | ❌ aus | ✅ baubar (JOIN artikel_lieferanten WHERE ist_standard=1) |
| `marge` | Marge % | ❌ aus | ✅ baubar (berechnet aus EK/VK) |
| `charge` | Charge-Pfl. | ❌ aus | ✅ baubar (a.charge_pflicht) |
| `merkmale` | Merkmale | ❌ aus | ✅ baubar (aktiviert 2026-07-09, GROUP_CONCAT über artikel_merkmale/merkmal_werte/merkmale) |
| `lagerplatz` | Lagerplatz | ❌ aus | ⏳ Platzhalter — zeigt "–" bis Lagerplätze gebaut (lagerplaetze-Tabelle fehlt noch) |
| `letzte_inventur` | Letzte Inventur | ❌ aus | ⏳ Platzhalter — zeigt "–" bis Inventur-Modul gebaut |

**WICHTIG:** Wenn Merkmale-UI, Lagerplätze oder Inventur-Modul gebaut werden → Platzhalter-Spalten in liste.php aktivieren (die Slots sind schon da).

## Default-Reihenfolge

```json
["status", "shops", "bestand", "preis"]
```

(Fixe Spalten kommen immer davor/danach)

## Kanäle-Cleanup (gleichzeitig umgesetzt)

- K1/K2-Chips aus SHOPS-Spalte entfernt (K-Kassen = immer alle Artikel, kein eigenes Flag nötig)
- Spalte umbenannt: "KANÄLE" → "SHOPS"
- Legend-Bar: K1/K2-Chips entfernt, Infotext bleibt

## Backend

- `benutzer_einstellungen` (Migration 036): UNIQUE KEY auf (benutzer_id, schluessel) — INSERT ... ON DUPLICATE KEY UPDATE
- `spalten_einstellung_speichern.php`: JSON aus POST, speichert wert für Schlüssel `artikel_liste.spalten`
- `liste.php` lädt beim Start: SELECT wert FROM benutzer_einstellungen WHERE benutzer_id = $aktuellerUser AND schluessel = 'artikel_liste.spalten'

## How to apply

Wenn ein neues Modul (Merkmale, Lagerplätze, Inventur) fertig gebaut wird:
1. In liste.php die Platzhalter-Spalte aktivieren (SQL-JOIN ergänzen, "–" ersetzen)
2. In diesem Memory-Eintrag Status auf ✅ baubar setzen

## ✅ GELÖST 2026-08-05: Kind-Zeilen zeigen jetzt volle Spalten-Parität mit Vater-Zeilen

Vorgeschichte: Jacky fiel auf, dass bei eingeblendeter Hersteller-Spalte jede Kind-Zeile leer blieb, obwohl die Daten korrekt waren -- reines Anzeige-Design (`spalteKindTd()` kannte nur `status`/`shops`/`bestand`/`preis`/`ean`/`charge`).

**Vorfrage geklärt, bevor entschieden wurde:** Performance-Unterschied zwischen "alle Spalten laden" vs. "nur einzelne"? Antwort: bei der Vater-Zeile praktisch keiner (die Haupt-Query lädt ohnehin IMMER alles, der Picker steuert nur die Anzeige). Bei Kind-Zeilen teilweise -- `hersteller`/`geaendert_am` waren schon in `findKinderFuerListe()` mitgeladen (nur ungenutzt), `kategorie`/`einheit`/`ek`/`marge`/`merkmale` brauchten neue JOINs. Bei MeaLanas Datenmenge (paginierte Liste, wenige Dutzend Kind-Zeilen pro Seite) spürbar irrelevant -- Jacky hat sich daraufhin bewusst für "immer alles, keine Lücken" entschieden.

**Jackys Begründung:** Erspart unschöne Lücken bei aufgeklappten Kindern + erleichtert Fehlersuche (fehlende Einzeldaten bei einem Kind, wie kurz zuvor der fehlende Hersteller bei 2 Kindern, fallen jetzt sofort auf statt erst bei gezielter Suche).

**Gebaut:** `ArtikelRepository::findKinderFuerListe()` um dieselben JOINs/Subqueries wie die Vater-Query erweitert (`artikel_typen`, `steuerklassen`, `einheiten`, `artikel_lieferanten` Standard-EK, Kategorien- und Merkmale-Subquery, `letzte_inventur_am`). `spalteKindTd()` in `liste.php` hat jetzt für JEDEN Spalten-Schlüssel denselben `case` wie `spalteTd()` (Vater), nur mit `$k` statt `$a`. Getestet: Query direkt gegen echte DB (Vater 3889, 141 Kinder) -- alle neuen Felder kommen korrekt mit.

