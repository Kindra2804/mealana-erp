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

4. **Aktionspreis-Screen**: zeigt alle Väter in der Kategorie, gruppiert nach `preis_gruppe` (Achsen-Wert-Feld), Barbara trägt Preise ein → Kinder erben

5. **Aktivierung**: manuell per "Aktion starten"-Button (Barbara hat Kontrolle, kein Mitternachts-Autostart)

## DROPS Fabel Spezialfall

Derzeit 1 Achse (Farbe), Typ (Uni/Print/LongPrint) in Farbnamen kodiert — JTL-Workaround.

**Kurzfristig (beim Aktions-Modul):** `preis_gruppe VARCHAR(50)` auf `varianten_achse_werte` — gruppiert Farbwerte nach Typ für Preissetzung ohne Achsen-Umbau.

**Langfristig (beim Bau von bedingten Achsen):** DROPS Fabel auf 2 Achsen migrieren:
- Achse 1: Typ (Uni / Print / Long Print)
- Achse 2: Farbe (bedingt — welche Farben verfügbar hängt vom Typ ab)
- Daten kommen großteils aus JTL-Import → Zuordnung wenn möglich daraus extrahieren
- Andernfalls: Neuanlage beim Daten-Import ohnehin nötig, dann sauber aufbauen

**How to apply:** Beim Bau der bedingten Achsen-UI daran denken, dass DROPS Fabel der primäre Testfall ist. [[feedback-js-auslagern]]

## Auslauf-Override

Artikel die zusätzlich Auslaufartikel sind können UNTER den Aktionspreis gesetzt werden (Lagerbereinigung). Seltener Fall, bleibt manueller Override pro Artikel.

## Status
- Wartet auf Feedback von Barbara (2026-06-17)
- Voraussetzungen: bedingte Achsen UI, preis_aktionen ausgebaut, aktionen-Tabelle
- Kommt nach Massenauswahl + Spalten-Picker als eigenes Modul
