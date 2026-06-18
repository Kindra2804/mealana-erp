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

**Generator-Logik (implementiert 2026-06-18):**
- Sub-Achsen-Werte eines gemeinsamen Parents → IMMER UNION zu einer Dimension (nie Kreuzprodukt)
- Sub-Achsen-Name wird als Suffix angehängt: Wert "gelb (02)" von MIX → "gelb (02) MIX"
- Eigene Werte auf Gruppenachse + Sub-Achsen-Werte = alle in einer Dimension (UNION, kein Kreuz)
- Beispiel: FARBE(F1) + MIX(M1,M2) + UNI(U1,U2) + STÄRKE(3mm,4mm) = 5×2 = 10 Kind-Artikel

**Voraussetzungen für Aktions-Modul:**
- ✅ Abhängige Achsen UI fertig + korrekt funktionierend (2026-06-18)
- ✅ VarKombi-Generator kennt Achsen-Hierarchie + Suffix-Logik (2026-06-18)
- Nächster Schritt: Aktions-Modul als eigenes Modul

## Aktivierung

- **Jetzt:** Manueller "Aktion starten"-Button (Barbara hat Kontrolle)
- **Wenn erster Shop angebunden:** Cronjob — DROPS-Vorgabe: Aktion muss exakt um 0:00 Uhr starten UND enden

## Kategorie-Konfig ASCII

War mal da, ist beim Blackout verloren gegangen. Muss neu erstellt werden.
Inhalt: Wie eine Kategorie eine Aktion bekommt (`aktion_id` FK + `anzeigen_ab/bis` Felder).

## Status (2026-06-18)
- ✅ Achsen-UI fertig: achsen_zuweisen.php, liste.php, AJAX-Endpoints alle updated
- ✅ VarKombi-Generator: hierarchie-bewusst + Suffix-Logik (detail.php) — immer UNION
- ✅ Achsen-Display Bug fix: $werteProAchse[$a['achse_id']] statt $a['id']
- ✅ Sub-Achsen in Achsen-Card eingerückt mit ↳ und lila Chip
- ✅ Granulare Achsen-Sperrung: 🔒-Chips für in-use Werte, freie Werte/Achsen editierbar
- Nächster Schritt: Aktions-Modul als eigenes Modul
