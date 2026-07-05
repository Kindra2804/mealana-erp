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

## Korrektur 2026-07-05: Händler = Kunde bestätigt, Schema-Feldnamen aktualisiert

Alte Planung oben (`lager.typ='extern_haendler'`, `haendler_kunden_id`) ist überholt — im Zug der Lagerverwaltungs-UI-Planung ([[project-lager-konzept]]) wurde das finale Schema festgelegt: `lager.lager_beziehung ENUM('eigen','partner_bestand','haendler_aussenlager')` + `lager.kunde_id` (statt `haendler_kunden_id`). Kein neuer `typ`-Enum-Wert, `typ` bleibt wie bisher (ladengeschaeft/messe/extern/lager).

**Bestätigt:** Kundengruppe "Händler" existiert bereits (`kundengruppen.id=2`, seit dem allerersten Seed) — das Preise-Modul war von Anfang an für dieses Szenario mitgedacht. Kein neues `ist_haendler`-Flag auf `kunden` nötig — die Existenz einer `lager`-Zeile mit `kunde_id=X` reicht als Kennzeichnung.

**Verkaufsmeldungs-Workflow (Jackys Konzept 2026-07-05, noch nicht gebaut):**
- Button "Händler-Verkaufsmeldung" auf der (künftigen) Händler-Lager-Detailseite
- Zeigt aktuellen Bestand je Artikel im Händler-Lager, daneben +/- Stepper zur Eingabe der vom Händler gemeldeten Verkaufsmenge
- Diese Menge ist Basis für die Rechnung (zum Kundengruppen-Preis des Händlers) — normaler VK-Ablauf, nur zeitversetzt, mit Händler-Kundengruppen-Preis statt Standard-VK, und Buchung gegen das Händler-Lager statt Hauptlager
- Lagerstand wird entsprechend der Rechnung reduziert
- **Beendigung/Komplettretoure:** Ist/Soll-Abgleich ähnlich [[project-kassen-verwaltung]]s Messe-Rückkehr-Logik — Differenz zwischen gemeldetem und tatsächlich zurückgekommenem Bestand wird entweder nachverrechnet oder als Schwund gebucht
- **Rechnungs-Timing bewusst anders als normaler Kundenauftrag:** keine Rechnung bei Einlagerung/Umbuchung ins Händler-Lager, sondern erst bei der Verkaufsmeldung — beim Bau der Umlagerungs-Funktion ins Händler-Lager keinen Auto-Rechnungs-Trigger einbauen

**How to apply:** Beim Bau der Händler-Übersichtsseite (kommt nach Kern-Verkauf) diesen Workflow 1:1 umsetzen, Wiederverwendung der Messe-Rückkehr-Abgleichslogik prüfen.
