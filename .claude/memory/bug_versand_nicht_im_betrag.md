---
name: bug-versand-nicht-im-betrag
description: "2026-09-30 behoben (nicht committed) — Versandkosten fehlten in Auftragsbetrag + Rechnungs-Gesamtbetrag; B2C-Dokumente zeigten pro Position 0,00 seit 25.06."
metadata:
  node_type: memory
  type: project
  originSessionId: 3204dc86-54bf-4800-9ff8-19338a0b843e
  modified: 2026-09-30T14:23:15.256Z
---

Gefunden beim Gutschein-auf-Rechnung-Bau ([[project_gutscheine]]), beide Fehler seit dem Dokumente-Modul (25.06.):

1. **Versand nie mitgerechnet:** `auftraege.netto/steuer/bruttobetrag` (AuftragService::berechneSummen) und Rechnungs-GESAMTBETRAG (DokumentService::berechneSummen) summierten nur Positionen; "Versandkosten"-Zeile wurde nur angezeigt. Folge: offener Betrag, Zahlung buchen, Mahnungen, rechnungen.bruttobetrag zu niedrig. Shop-Import übernahm zudem Versand NETTO (shipping_total ohne shipping_tax).
2. **B2C-Positionspreise 0,00:** einzelpreis_brutto/gesamtpreis_brutto wurden in berechneSummen() auf einer KOPIE berechnet, kamen nie ins Template (Rechnung/AB/Gutschrift B2C). Jetzt in `ladePositionen()`.

**Regel (Jacky 2026-09-30):** Versand trägt den Steuersatz der überwiegenden Leistung (Bruttosumme je Satz, Gleichstand → höherer Satz, 0-%-Positionen zählen nicht, keine → 20 %). Einzige Stelle: `src/modules/auftraege/Versandsteuer.php` (satz/aufteilen), genutzt von AuftragService + DokumentService. Versandkosten im ERP IMMER brutto; Shop-Import jetzt shipping_total+shipping_tax.

**Dokumente:** Versand geht in den Steuerblock seines Satzes + Gesamtbetrag, Zeile "Versandkosten (20 % MwSt)" (B2B netto, B2C brutto) steht über den Netto/MwSt-Zeilen; Gutschriften ohne Versand (summen.versand = null).

**Migration 181:** 5 von 8 Aufträgen mit Versand nachgerechnet (nur ohne gültige Rechnung und nur wo bruttobetrag == Positionssumme). A-2026-00008 übersprungen (bruttobetrag 32,50 passte schon vorher nicht zur Positionssumme 5,00 — alte Testdaten?). A-00002/00018/00026 haben Rechnungen → bleiben, Korrektur nur per Gutschrift+Neu-Rechnung.

**Nachprüfung 2026-10-01:** Versand im Export erledigt (260cf29). Zahlungsstatus: A-00001/00005/00007 stehen auf "bezahlt", obwohl Zahlungen jetzt unter dem Betrag liegen (Kunde zahlte damals ohne Versand) — alles Juni-Testdaten, bewusst so gelassen (Mahnwesen greift nur bei 'ausstehend'). A-2026-00043 (Gutschein 35,90) hatte 35,91 aus alter Netto-Rechnung → auf 35,90 korrigiert.
