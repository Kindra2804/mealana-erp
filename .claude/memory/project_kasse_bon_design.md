---
name: project-kasse-bon-design
description: "Kassen-Bon Design: Blocks (auftrag/addon/storno), RKSV-konform, besser als JTL"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9a44da56-fbce-4da5-b4f6-17b472024d63
---

## Kernentscheidung: Rechnung erst am Bon (nicht vorab)

Bei Abholaufträgen wird KEINE Rechnung vorab ausgestellt.
Der Kassenbon IST die Rechnung (erzeugt Rechnungsnummer).
Steuer-Ereignis passiert EINMAL am Bon — keine Doppelberechnung.

**Warum besser als JTL:**
JTL stellt Rechnung vorab aus → Kassenbon zeigt nur "Auftragszahlung ohne Steuer"
→ Add-Ons am Bon nicht mischbar.
Unser Ansatz: alles auf einem Bon, ein Steuertopf, volle Flexibilität.

## Maximalfall (alles auf einmal)

Auftragszahlung + Add-On Verkauf + Rückgabe + Gutschein-Einlösung gleichzeitig:
- Block 'auftrag':  Positionen aus Auftrag A-2026-XXXXX
- Block 'addon':    zusätzlich gescannte Artikel
- Block 'storno':   Rückgabe (negative Mengen/Beträge)
- Gutschein:        als Zahlungsart (gemischt: bar + gutschein)
→ Eine gemeinsame Steueraufschlüsselung am Ende des Bons

## Bon-Layout

```
MeaLana · Datum · Bon-Nr · Kasse-ID · UID
─────────────────────────────────────────
AUFTRAG A-2026-00001
2× Merino Rot 100g         2× 8,50 €
1× Rundnadel 4,0mm            12,00 €
──────────────────────────   29,00 €
─────────────────────────────────────────
ZUSÄTZLICH
1× Strickbuch Anfänger        15,00 €
──────────────────────────   15,00 €
─────────────────────────────────────────
RÜCKGABE
1× Nadel-Set (Rückgabe)      -8,00 €
──────────────────────────   -8,00 €
─────────────────────────────────────────
Netto  10%:    X,XX €
MwSt   10%:    X,XX €
Netto  20%:   XX,XX €
MwSt   20%:    X,XX €
─────────────────────────────────────────
GESAMT:       36,00 €
GUTSCHEIN:   -10,00 €
BAR:          26,00 €
─────────────────────────────────────────
[QR-Code RKSV-Signatur]
```

## Rechtliches (AT)

- Bon mit allen Pflichtfeldern = vereinfachte Rechnung bis €400 (UStG §11 Abs. 6)
- Über €400: vollständige Rechnung mit Kundendaten (aus kunden_snapshot)
- RKSV: Bon signiert mit Fiskaly/BFR-BONit, QR-Code Pflicht

## DB-Tabellen (kommt mit Kasse-Modul)

```sql
kassen_bons (
  id, bon_nr,
  kasse_id, benutzer_id,
  auftrag_id FK NULL,          -- verknüpfter Auftrag (Abholung)
  zahlungsart ENUM(bar, karte, gutschein, gemischt),
  bar_betrag, karte_betrag, gutschein_betrag, rueckgeld,
  gesamtbetrag, nettobetrag, steuerbetrag,
  rksv_signatur, rksv_kassen_id, rksv_qr,
  erstellt_am
)

kassen_bon_positionen (
  id, bon_id FK,
  block ENUM('auftrag','addon','storno'),   -- steuert Bon-Layout
  auftrag_pos_id FK NULL,                  -- Referenz auf Auftragsposition
  artikel_id, bezeichnung, menge,
  einzelpreis_brutto, steuer_prozent, gesamtpreis_brutto,
  sort_order
)
```

## Verknüpfung Auftrag ↔ Bon

- `auftraege.kassen_bon_id FK NULL` — wird gesetzt wenn Bon erstellt
- Nach Bon-Erstellung: Auftrag → zahlungsstatus='bezahlt', lieferstatus='abgeschlossen'
- Rechnungsnummer wird aus Bon generiert (R-2026-XXXXX) → auch auf Bon gedruckt

## Flow Abholung

1. Auftrag angelegt (lieferart='abholung', zahlungsart kommt von Kasse)
2. Ware gepickt → lieferstatus='abholbereit' → Mail an Kunden
3. Kasse lädt Auftrag → Block 'auftrag' befüllt
4. Optional: Add-Ons scannen → Block 'addon'
5. Optional: Rückgabe erfassen → Block 'storno'
6. Optional: Gutschein einlösen → Zahlungsart 'gemischt'
7. Bon signieren → Auftrag abschließen
