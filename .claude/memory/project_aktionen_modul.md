---
name: project-aktionen-modul
description: "Geplantes Aktions-Modul: Lieferanten-Kampagnen mit kategorie-basierter Auto-Preissetzung"
metadata: 
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

## Konzept

Lieferanten (v.a. DROPS) machen regelmäßig Kampagnen mit zeitlich limitierten Sonderpreisen auf bestimmten Qualitäten. Ziel: Barbara muss nicht mehr jeden Artikel einzeln anfassen.

**Why:** DROPS Fabel allein = 61+ Kind-Artikel. Ohne dieses Feature = 61 manuelle Preiseinträge. Mit diesem Feature = 3 Preisfelder (Uni/Print/LongPrint), Rest propagiert automatisch.

## Architektur (geplant)

1. **`aktionen`-Tabelle** (global, wie `varianten_achsen`):
   - id, name ("DROPS Frühjahr 2026"), beschreibung, lieferant_id

2. **`kategorien`-Erweiterung**:
   - `aktion_id FK → aktionen`
   - `anzeigen_ab DATE`, `anzeigen_bis DATE`
   - Kategorie bekommt ⏰-Emoji in Liste, grau wenn außerhalb Zeitraum

3. **`preis_aktionen`** (Migration 031, Placeholder) — richtig ausbauen

4. **Aktionspreis-Screen**: zeigt alle Väter in der Kategorie, gruppiert nach Achsen-Werten (Typ), Barbara trägt Preise ein → Kinder erben

5. **Aktivierung**: manuell per "Aktion starten"-Button (Barbara hat Kontrolle, kein Mitternachts-Autostart)

## DROPS Fabel Spezialfall

Derzeit 1 Achse (Farbe), Typ (Uni/Print/LongPrint) in Farbnamen kodiert — JTL-Workaround.

**Entscheidung (2026-06-18):** Abhängige Achsen, NICHT preis_gruppe.
- Achse 1 (Eltern): Farbe — hat Werte wie "Royalblau", "Lachs", etc. ODER ist Gruppenachse für Unterachsen
- Achse 2 (Kind, abhaengig_von=Farbe): z.B. UNI — hat eigene Werte

**Finales Design (abgestimmt 2026-06-18, implementiert 2026-06-18):**
- `varianten_achsen.ist_gruppe` (Migration 041): Gruppenachse kann Unterachsen haben UND eigene Werte
- Gruppenachse z.B. Farbe: direkte Werte (für einfache Garne) ODER Sub-Achsen Uni/Print
- `achsen_zuweisen.php` komplett neu: Baumstruktur, Chip-Input, ↔ Wert verschieben, ✎ Achse global bearbeiten
- `achsen_speichern.php` und `VariantenService` vereinfacht — kein two-pass mehr, kein bedingungs_wert_id

**Voraussetzungen für Aktions-Modul:**
- ✅ Abhängige Achsen UI fertig + korrekt funktionierend (2026-06-18)
- VarKombi-Generator muss Abhängigkeiten kennen (aktuell: flaches kartesisches Produkt) — noch offen

## Aktivierung

- **Jetzt:** Manueller "Aktion starten"-Button (Barbara hat Kontrolle)
- **Wenn erster Shop angebunden:** Cronjob — DROPS-Vorgabe: Aktion muss exakt um 0:00 Uhr starten UND enden

## Kategorie-Konfig ASCII

War mal da, ist beim Blackout verloren gegangen. Muss neu erstellt werden.
Inhalt: Wie eine Kategorie eine Aktion bekommt (`aktion_id` FK + `anzeigen_ab/bis` Felder).

## Status (2026-06-18)
- ✅ Achsen-UI fertig: achsen_zuweisen.php, liste.php, AJAX-Endpoints alle updated
- Nächster Blocker: VarKombi-Generator (aktuell flaches kartesisches Produkt, kennt keine Achsen-Hierarchie)
- Danach: Aktions-Modul als eigenes Modul
