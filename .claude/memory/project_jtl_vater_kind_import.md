---
name: project-jtl-vater-kind-import
description: "JTL Vater+Kind-CSV-Import mit Achsenerkennung — Plan bestätigt 2026-07-31, Bau noch nicht gestartet"
metadata: 
  node_type: memory
  type: project
  originSessionId: 85efc9a3-c1f8-4d31-89d2-a10e99128244
  modified: 2026-07-31T16:23:52.587Z
---

## Ausgangslage (2026-07-31)

Jacky hat drei neue Testdaten-Exporte unter `D:\ERP\mealana\import\` abgelegt:
- `JTL-Export-TestdatenERP_31072026.csv`
- `JTL-Export-TestdatenERP_mitKindern31072026.csv` (2.448 Zeilen: Vater+Kind-Stammdaten, Flag `Ist Vaterartikel`, Windows-1252-kodiert)
- `JTL-Export-TestdatenERP_mitKindernVariationskombinationen31072026.csv` (2.146 Zeilen: Vater↔Kind-Zuordnung MIT strukturierten Achsen als Name/Wert-Paare `Variationsname 1-6`/`Variationswertname 1-6`, 87 Väter, teils 2 Achsen gleichzeitig)

Frage war: kann daraus automatisch ein Vater+Kind-Import mit Achsenerkennung gebaut werden, oder muss es Handarbeit bleiben? **Antwort: automatisierbar** — die Achsen liegen bereits strukturiert vor, kein Freitext-Parsing nötig. Bestehender Code (`VariantenService::erstelleKombinationen()`, `speichereAchsenUndWerte()`) ist direkt wiederverwendbar.

**Voller Plan liegt in `C:\Users\indy1\.claude\plans\sequential-hatching-papert.md`** (bestätigt, Bau noch nicht begonnen). Kurzfassung:

- Väter werden neu angelegt (nicht nur gematcht gegen Handarbeit-Väter).
- Batch-weite Vorab-Auswahl per Dropdown (gilt für alle Väter+Kinder des Imports): Kategorie, Artikeltyp (`artikel_typen`, fehlt in CSV), Artikelgruppe (`artikel_gruppen`, Buchhaltungs-Kontenzuordnung, fehlt ebenfalls in CSV).
- Vor dem echten DB-Import: editierbare Kontrollliste (Artikelnummern/Kindernamen korrigierbar), erst nach Bestätigung läuft der Import — Absicherungs-Zwischenschritt (Session-basiert).
- Hersteller-Matching NUR gegen bestehende `hersteller`-Einträge, kein Auto-Anlegen (Hersteller tragen Pflegedaten wie GPSR-Angaben, die ein blind erzeugter Datensatz nicht hätte) — unbekannte Hersteller werden in der Kontrollliste sichtbar markiert.
- Einheiten-Mapping: `Verkaufseinheit`-Werte aus CSV (`Paar`, `Stk.`, `Pkg.`, `Spiel` u.a.) matchen nicht 1:1 gegen `einheiten.kuerzel` → User mappt distinct-Werte einmalig auf bestehende Einheiten, kein Auto-Anlegen (Repository unterstützt das nicht).
- Preise/Maße/UVP/EAN werden NICHT über die Achsen-Aufpreis-Mechanik vererbt, sondern direkt aus der jeweiligen Kind-CSV-Zeile übernommen — JTL liefert bereits die exakten Endwerte pro Kind (z.B. `KP-20407` hat abweichenden Preis ggü. Geschwistern).
- Neuer Service `src/modules/import/JtlVaterKindImportService.php`, UI `public/artikel/jtl_import.php` + `jtl_import_commit.php` (Vorbild: `achsen_zuweisen.php`/`achsen_speichern.php` und `public/artikel/import.php` fürs Encoding-Handling).

**Bewusst nicht Teil dieses Imports:** HTML-Bereinigung der Beschreibungstexte (eigenes zurückgestelltes Thema, siehe [[project_jtl_import]]), Merkmale-Import (eigenes Thema, Dedup nötig).

**Bei Wiedereinstieg:** Plan-Datei lesen, dann mit Schritt 0 (falls noch nicht erledigt) bzw. direkt mit der Implementierung (Service + UI) weitermachen.
