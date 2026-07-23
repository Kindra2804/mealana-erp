---
name: project-jtl-import
description: "JTL-Export CSV-Struktur, ProduktTyp-Werte, Kategorie-Mapping, Demo-Artikel-Skript; Anreicherungs-Import-Scope mit Jacky abgestimmt 2026-07-23, Bau noch nicht gestartet"
metadata: 
  node_type: memory
  type: project
  originSessionId: e92b8de5-2100-45b7-b6b1-0eeacfcb09d5
  modified: 2026-07-23T13:09:25.269Z
---

## JTL-Export CSV-Datei

Datei: `D:\ERP\mealana\import\JTL-Export-Eigener Export-28052026_2.csv`  
Format: Semikolon-getrennt, Anführungszeichen, UTF-8, 59 Spalten, 26.425 Zeilen

**Wichtige Spalten-Indizes:**
| # | Feldname |
|---|----------|
| 0 | InterneArtikelID |
| 1 | Artikelnummer |
| 2 | Artikelname |
| 3 | EAN |
| 5 | KategorieIDs (JTL-intern, pipe-getrennt) |
| 6 | Kategorien (Namen, pipe-getrennt) |
| 7 | ProduktTyp |
| 8 | IstVaterartikel (1/0) |
| 9 | Vaterartikel_InterneArtikelID |
| 39 | VK_Netto |
| 40 | VK_Brutto |
| 42 | Steuersatz (z.B. "20,00") |
| 44 | MassMenge (z.B. "50,00") |
| 45 | MassEinheitID (JTL-interne ID, NICHT der Unit-Name!) |
| 55 | BestandAktuell |
| 56 | ArtikelAktiv (Y/N) |

## ProduktTyp-Werte (kritisch!)

| Wert | Bedeutung | Anzahl |
|------|-----------|--------|
| VATER | Eltern-Artikel mit KIND-Varianten | 1.332 |
| NORMAL | Standalone-Artikel (keine Kinder) | 3.140 |
| KIND | Variante eines VATER-Artikels | 22.046 |

**Fallstrick:** Filter für "Eltern" muss `ProduktTyp != 'KIND'` sein, NICHT `== 'NORMAL'`. VATER-Artikel werden sonst übersehen!

KIND-Artikel: `f[9]` (Vaterartikel_InterneArtikelID) enthält die JTL-ID des VATER-Artikels.

## Dezimalzahlen

JTL verwendet deutsches Komma-Format: "12,90" → `.Replace(',','.')` für SQL-Werte.  
MassEinheitID ist eine JTL-interne Zahl (nicht "g"!). Für Garn `inhalt_einheit='g'` hardcoden.

## Kategorie-Mapping: JTL-Namen → unsere DB-IDs

Die JTL-Kategorien (Feld 6) sind Flat-Listen, NICHT hierarchische Pfade.  
Ein VATER-Artikel hat oft NUR die Hersteller-/Hauptkategorie, KIND-Artikel haben alle Subkategorien.

| JTL-Kategoriename | Unsere DB-Kat-ID | Hinweis |
|-------------------|------------------|---------|
| Garnstudio DROPS | 73 | Verwende 'Garnstudio DROPS', NICHT 'DROPS' – 'DROPS' ist mehrdeutig (auch Nadeln, Bücher) |
| Lang Yarns | 75 | |
| Scheepjes | 79 | Enthält auch Garnschalen (Zubehör) |
| Cheval Blanc | 83 | Exclude 'Kataloge' für reine Garn-Auswahl |
| BC Garn | 86 | Nur VATER+KIND, keine NORMAL-Artikel |
| Regia | 77 | |
| Opal | 76 | |
| Rundnadeln | 97 | |
| Nadelspiele | 98 | |
| Häkelnadeln | 99 | |
| Addi | 107 | Nur Nadeln (auch Knooking) |
| KnitPro | 109 | |
| Knöpfe | 115 | |
| Taschenzubehör | 119 | |

**Schachenmayr:** In diesem JTL-Export NICHT als Kategoriename vorhanden → Kat-ID 74 bleibt leer.

## Import-Skript

**Generator:** `D:\ERP\mealana\import\gen_demo_artikel.ps1`  
**Output:** `D:\ERP\mealana\import\demo_artikel_import.sql` (gitignored)

Ausführen:
```powershell
& "D:\ERP\mealana\import\gen_demo_artikel.ps1"
# Dann:
& "C:\xampp\mysql\bin\mysql.exe" -u root mealana_erp < D:\ERP\mealana\import\demo_artikel_import.sql
# ACHTUNG: Bash verwenden für UTF-8! PowerShell-Pipe konvertiert zu CP1252.
```

## Importierter Stand (2026-06-14)

90 Demo-Artikel importiert, 120 Artikel gesamt in DB.  
Je 3–4 Artikel pro Kategorie, Garn-Eltern mit je 3 Farbvarianten als KIND.  
Preise: Endkunden (kundengruppen_id=1) aus JTL VK_Brutto/VK_Netto.

## Für mehr/andere Artikel

1. `$ziel.MaxEltern` und `$ziel.MaxKinder` in gen_demo_artikel.ps1 erhöhen
2. Neue Kategorien hinzufügen (Required/Excluded/DbKat anpassen)
3. Script neu ausführen + SQL-Datei importieren
4. Zuerst mit `DELETE FROM artikel_kategorien WHERE artikel_id > 51` + `DELETE FROM artikel WHERE id > 51` aufräumen, falls neu importiert werden soll

**Why:** Vollständiger Re-Import der 26K JTL-Artikel kommt erst am Ende des Projekts.  
**How to apply:** Immer dieses Skript als Ausgangspunkt nehmen, nicht neu analysieren.

## JTL-Anreicherungs-Import — Scope-Gespräch (2026-07-23)

**Ziel (unverändert seit project_roadmap_reihenfolge.md):** Kein voller Produktimport — gezielt Felder per Artikelnummer-Match in bereits von Hand angelegte Vater-Artikel nachziehen, damit Jacky nur noch Vater-Artikel + Achsenzuweisung selbst machen muss.

**Bild-Frage geklärt (2026-07-23):** Alle 5 aktuell auf der Platte liegenden JTL-Exporte (`D:\ERP\mealana\import\*.csv`, Stand 04.-28.06.2026) haben KEINE Bild-Spalte — weder der Haupt-Export noch Artikelattribute/-kategorien/-merkmale/Gebinde. Per direktem Header-Check aller 5 Dateien bestätigt (nicht geraten). **Jacky kann den JTL-Export selbst um Bilder erweitern** (JTL kann das offenbar exportieren) — das macht er vor dem nächsten Anlauf, dann gehen Bilder eventuell doch in Phase 1 rein.

**Echter Fund, größer als die alte Spaltenliste oben:** Der Haupt-Export (`JTL-Export-Eigener Export-28052026_2.csv`) hat eine `Artikelbeschreibung`-Spalte (Index 5, fehlte in der alten Tabelle oben — die war nicht vollständig). Zusätzlich existiert ein **separater** `JTL-Export-Artikelmerkmale-04062026.csv` mit sauber strukturierten Spalten `Merkmalgruppe`/`Merkmal`/`Merkmalwert` (+ SEO-Felder pro Merkmalwert) — passt direkt auf unser bestehendes Merkmale-Modul. Und `JTL-Export-Artikelkategorien-04062026.csv` mit bis zu 10 hierarchischen Kategorie-Ebenen + SEO-Feldern (URL-Pfad/Title-Tag/Meta-Description/Meta-Keywords) pro Kategorie.

**Von Jacky gewünschte Felder (2026-07-23), absteigend nach Aufwandsersparnis:**
- Kurz-/Langbeschreibung (`artikel.kurzbeschreibung`/`beschreibung` — Felder existieren schon)
- SEO (`artikel.meta_titel`/`meta_description` — existieren schon)
- Lieferant-Zuordnung (`artikel_lieferanten`) — **braucht Dedup**, viele Lieferanten in Live schon angelegt, Matching gegen bestehende `lieferanten`-Tabelle nötig statt blind neu anzulegen
- UVP (`artikel.uvp`, Migration 028 — existiert schon)
- Lieferzeit (`artikel_lieferanten.lieferzeit_tage` — existiert schon)
- EAN (`artikel_codes`)
- Aktiv-Status (`artikel.aktiv`)
- Überverkauf erlaubt (`artikel.ueberverkauf_erlaubt`)
- Chargenpflicht (`artikel.charge_pflicht`)
- Bilder — siehe oben, abhängig von Jackys Re-Export
- Merkmale — **eigenes größeres Thema**, siehe unten

**Merkmale bewusst zurückgestellt:** Jackys eigene Einschätzung: "in der Live sind sehr viele [Merkmale] schon vorhanden, da müsste eine solide Duplikatserkennung mit rein, sonst ist das mehr Aufwand als Nutzen." Braucht eigenes Konzeptgespräch (Matching-Strategie Merkmalgruppe/Merkmal/Merkmalwert gegen bestehende `merkmal_gruppen`/`merkmale`-Tabellen) bevor das gebaut wird — NICHT einfach mitbauen, wenn die anderen Felder angegangen werden.

**Wichtig — fast alle gewünschten Felder existieren schon im Schema.** Das ist überwiegend ein Import-Matching-Skript (Artikelnummer → bestehenden Artikel finden → Felder befüllen falls leer/gewünscht), keine neue Migration nötig — außer für die Lieferanten- und Merkmale-Dedup-Logik, die eigene Sorgfalt braucht.

**Noch nicht gebaut, nur Scope abgestimmt.** Bei Wiedereinstieg: 1) Jacky hat hoffentlich neu mit Bildern exportiert — Datei-Stand in `import/` prüfen. 2) Lieferanten-Dedup-Strategie festlegen (Name-Fuzzy-Match? Interner-Schlüssel-Match?). 3) Merkmale weiterhin separat/zurückgestellt behandeln, nicht ungefragt mit reinziehen.
