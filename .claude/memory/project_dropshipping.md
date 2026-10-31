---
name: project-dropshipping
description: "Dropshipping-Modul (eigenes Lizenzmodul 190 €, entschieden 2026-10-30) — noch NICHT gebaut, JTL-Ablauf als Basis, darf verbessert werden"
metadata:
  type: project
---

Entschieden im Modulgespräch Jacky + Babsi am 2026-10-30: Dropshipping (Lieferant liefert direkt an den Endkunden, Streckengeschäft) wird ein **eigenes Lizenzmodul um 190 €**, siehe [[project-lizenzierung-preise]].

Stand 2026-10-31: **nichts gebaut** (kein Code und keine Migration mit "dropship"/"strecke" im Repo).

**Vorgabe Jacky:** JTL-Ablauf als Basis nehmen, gerne verbessern.
JTL-Grundidee zum Nachprüfen vor dem Konzept: Artikel/Lieferant als Dropshipping markiert → Auftrag erzeugt automatisch eine Lieferanten-Bestellung mit Kunden-Lieferadresse → Lieferant meldet Versand (Tracking) → Auftrag gilt als geliefert, ohne eigene Lagerbewegung → Rechnung an den Kunden normal.

**Why:** eigenes Erlös-Modul. Andockpunkte gibt es schon: Bestellmodul [[project_bestellmodul]], Lieferanten [[project_lieferanten_erweiterung]], Packplatz/Lieferstatus [[project_packplatz]].
**How to apply:** Vor dem Bau wie immer einen Referenz-Check machen (JTL, Xentral, Weclapp) und die MeaLana-Extras abfragen ([[feedback_modul_vorgehen]]). Bei Jacky klären: Teil-Dropshipping (gemischte Aufträge Lager + Strecke), Bestellweg zum Lieferanten (PDF-Mail vs. Portal), Tracking-Rückmeldung (manuell vs. Import), Einordnung vor oder nach 1.0.
