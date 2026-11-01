---
name: project-soft-golive-sockenwolle
description: "Soft-Go-Live-Plan (Jacky + Babsi 2026-10-30): V1.0 live nur mit Sockenwolle-online (reduzierter Artikelstamm, Shop JTL→Woo), Rest bleibt vorerst auf JTL, MeaLana zuletzt; Nummernkreise brauchen Shop-Kürzel (nicht gebaut)"
metadata:
  type: project
---

**Update 2026-11-01:** Live wird komplett neu auf 1.0 aufgesetzt; zuerst indra-design.at als Sub-Shop (Woo zurücksetzen, gemeinsam), dann sockenwolle-online, dann bio-wolle, MeaLana zuletzt — siehe [[project-1-0-check]].

**Plan (Modulgespräch 2026-10-30):**
- Sobald V1.0 steht ([[project-version-1-0-release]]), wird sie am Live-Server eingespielt. **Soft-Go-Live nur mit den Daten von Sockenwolle-online** (reduzierter Artikelstamm).
- Der Shop **sockenwolle-online.at wechselt von JTL auf WooCommerce**. Alles, was über Sockenwolle-online kommt, läuft ab dann im ERP. Alle anderen Kanäle bleiben vorübergehend auf JTL. **MeaLana wird als letzter Shop umgestellt.**
- Dazu nötig: einen **Woo-Shop im Sockenwolle-online-Look bauen** (Basis Theme-Arbeit [[project_shop_theme]]) und an die Live-ERP anbinden ([[project_shop_sync]]).
- Damit läuft **JTL parallel zum ERP**. Die Belegnummern dürfen nicht kollidieren.

**Nummernkreise: Ist-Stand (Code-Check 2026-10-31): Kürzel je Shop gibt es NICHT.**
- `dokument_nummern` (typ, praefix, jahr, letzt_nr) ist ein globaler Zähler je Belegtyp, ohne Shop-Bezug. `DokumentRepository::naechsteNummer()` → `R-2026-00001` usw.
- Auftragsnummer: `AuftragRepository::insert()` (ca. Z. 355–371) hat `'A-'` **hart codiert** und ignoriert sogar das Präfix aus `dokument_nummern`. `auftraege.shop_id` existiert aber schon.
- Rechnungen werden in `DokumentService::erstelleRechnung()` über den Typ-String gezogen, Dateiname ebenfalls mit hartem `'R-'`.

**Jackys Vorgabe:** Der Hauptshop behält die Nummern ohne Kürzel. Jeder weitere Shop bekommt einen Marker: `R-BIO-xxx`, `A-BIO-xxx`, `R-SOW-xxx`, `A-SOW-xxx` … Das trennt die Shops und verhindert zusätzlich, dass altes JTL und neues ERP im Parallelbetrieb im selben Nummernkreis fischen.

**✅ Entschieden (Jacky 2026-10-31):**
1. **Eigener Zähler je Shop** (z. B. R-SOW-2026-00001 beginnt bei 1).
2. Kürzel bekommt **alles, was direkt mit dem Shop zusammenhängt** (Auftrag, Rechnung, Rechnungskorrektur, Lieferschein …). Laden-Sachen bleiben wie gehabt (Kasse/Bon, Z-Bon, Mietrechnung MR, Partner-Abrechnung AB).
3. **MeaLana wird von Anfang an als Hauptshop (ohne Kürzel) eingerichtet**, aber zunächst ohne API-Keys. Sockenwolle läuft sofort mit Kürzel.
4. JTL-Formate (Kollision ausgeschlossen): Bio-Wolle Rechnung `R-BIO-xx[MM][JJ]`, Auftrag `Bio-W-xx-[MM][JJ]`; Sockenwolle Rechnung `R-SW-xx[MM][JJ]`, Auftrag `Sowo-xx-[MM][JJ]`. ERP-Format `X-KÜRZEL-JJJJ-00001` unterscheidet sich davon. Kürzel laut Jackys Vorgabe BIO / SOW (SOW ≠ JTL-„SW“).
5. Telefon- und Händler-Aufträge gehören zum Hauptshop (ohne Kürzel), bestätigt von Jacky 2026-10-31.

**MeaLana-JTL-Format geklärt (Jacky-Screenshot 2026-10-31):** Rechnung `R-40550102026`, Auftrag `A-41009102026` = Präfix + Laufnummer + MM + JJJJ ohne weitere Trennstriche. Das ERP-Format `R-2026-00001` hat einen zweiten Bindestrich → **keine Kollision**, Hauptshop ohne Kürzel ist unbedenklich.

Pflege des Kürzels: Vorschlag Einstellungen → Kanäle, Feld „Nummernkreis-Kürzel“.

**How to apply:** Muss vor dem Soft-Go-Live gebaut sein, idealerweise vor der 1.0-Konsolidierung. Siehe auch [[project-roadmap-reihenfolge]].

**Baufortschritt Kürzel (2026-10-31, Jacky schreibt selbst, Claude als Trainer):**
- ✅ Migration 214 `214_nummernkreis_kuerzel.sql` (shops.nr_kuerzel BIO/SOW, dokument_nummern.kuerzel NOT NULL DEFAULT '', UNIQUE typ/kuerzel/jahr). In der Dev-DB eingespielt.
- ✅ `DokumentRepository::naechsteNummer($typ, $jahr, $kuerzel = '')`: Präfix aus der eigenen Kürzel-Zeile, sonst aus der Hauptshop-Zeile; danach INSERT IGNORE VALUES. Getestet per Rollback-Transaktion (Hauptshop zählt weiter, SOW/BIO starten bei 1, Jahreswechsel ok, alte Aufrufe ok).
- ✅ `AuftragRepository::insert()` nutzt naechsteNummer mit Kürzel aus shops.nr_kuerzel (NULL = Hauptshop), getestet per Rollback.
- ✅ `DokumentRepository::kuerzelFuerShop(?int)` als gemeinsamer Helfer; DokumentService Rechnung (nur bei $nummernkreis 'rechnung', MR bleibt ohne Kürzel) + GS mit Kürzel. Getestet per Rollback + PDF-Aufräumen.
- ✅ Einstellungen → Kanäle Feld „Nummernkreis-Kürzel“ (Claude gebaut): A-Z/0-9, max. 5, eindeutig je Shop (`pruefeNrKuerzel()` in speichern.php), gesperrt ab dem ersten Auftrag des Shops (disabled + serverseitig geprüft); auch beim Anlegen eines neuen Kanals. CLI-getestet (Render + 6 Speicherfälle mit Rollback). Beide Handbücher nachgezogen. Committed 2026-10-31.
- Sicherheits-Check 2026-10-31: kein Code zerlegt Auftrags-/Rechnungsnummern per Muster (nur LIKE-Suchen, DATEV substr 36). Spalten varchar(20) reichen (längste mögliche Nummer `GS-XXXXX-2026-00001` = 19). Kanalfilter läuft über shop_id, Retouren/Packplatz/Buchhaltung über IDs. Restrisiko: PLC/EasyPak ShipmentRefNo `L`+auftrag_nr (17 Zeichen bei SOW), Längenlimit unbekannt → beim ersten SOW-Versand prüfen.
- ✅ Nachbesserung nach Jackys Screenshot: Kürzel-Feld lief aus der Karte (`.erp-input` hat feste 220px) → max 110px, Platzhalter nicht mehr in Großbuchstaben (`.erp-input-kuerzel` in components.css), Hinweis zeigt eigenes Kürzel. Tab „Nummernkreise“ zeigt jetzt die Spalte Shop-Kürzel und die richtige Vorschau (sonst doppelte „Rechnung 2026“-Zeilen).
- ⏭️ Offen: Live-Deploy 214 (mit 201–213).
