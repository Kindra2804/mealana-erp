---
name: project-haendler-konsignation
description: Händler-Konsignation über Lager-System abbilden — externe Lager für Partnerbetriebe
metadata: 
  node_type: memory
  type: project
  originSessionId: 1d6af759-efaf-424e-9b61-6578c5cf2dd1
---

# Händler-Konsignation via Lager-Erweiterung

Befreundete Unternehmen verkaufen MeaLana-Produkte (meist Eigenproduktion) in ihren Geschäften auf eigene Rechnung. Risiko bleibt bei MeaLana bis Verkaufsmeldung.

## Architektur-Entscheidung: Händler = Externes Lager

Statt eigener Konsignations-Tabellen wird das bestehende Lager-System erweitert:

```
lager.typ: intern | extern_haendler | messe   (neues Feld)
lager.haendler_kunden_id (nullable)            (Link zum Kunden-Datensatz)
```

**Buchungsregeln für typ = 'extern_haendler':**
- Einbuchen/Umbuchen ins Händler-Lager → automatisch Lieferschein generieren
- Ausbuchen aus Händler-Lager → Dialog: "Rücknahme ins Lager" ODER "→ Rechnung erstellen"

Kein separater `konsignation_bestand` nötig — Bestand = normaler lager_bestand des externen Lagers.

## Workflow

1. Ware geht raus → Umbuchen in Händler-Lager → LS wird auto-generiert
2. Händler meldet Verkäufe (monatlich oder auf Abruf bei Nachbestellung)
3. Ausbuchen aus Händler-Lager → Rechnung (normaler VK-Flow)
4. Rücknahme möglich → zurück in eigenes Lager

## Händler-Übersichtsseite (geplant, nach Kern-Verkauf)

Unterseite in Verkaufsmodul:
- Alle Händler-Lager mit aktuellem Bestand (was liegt wo)
- Meldung erfassen → direkt in Rechnung weiterleiten
- Statistik: welcher Händler hat in Zeitraum X was um wie viel verkauft

## Händler als Kunden

Händler sind normale Kunden mit:
- Kundengruppe "Händler" (Preismodul — bereits geplant)
- Flag `ist_haendler = true`
- Verknüpftes externes Lager

**Why:** Risiko bleibt bis Meldung bei MeaLana (Konsignation), Abrechnung frühestens monatlich oder bei Nachbestellung. Einfachste Architektur die bestehende Lager-Infrastruktur maximal wiederverwendet.

**How to apply:** Beim Lager-Modul lager.typ + haendler_kunden_id einbauen. Buchungsregeln als Business-Logic in Umlagerungs-Funktion. Händler-Übersichtsseite erst nach Kern-Verkauf.

Verwandt: [[project-lager-konzept]], [[project-verkauf-workflows]]
