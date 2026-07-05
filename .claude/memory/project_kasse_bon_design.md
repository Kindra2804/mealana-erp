---
name: project-kasse-bon-design
description: "Kassen-Bon Design: Blocks (auftrag/addon/storno/retour), K1-Split-Logik, Web-Auftrag-Flow inkl. Abholbereit+bezahlt"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9a44da56-fbce-4da5-b4f6-17b472024d63
---

## Kernentscheidung: Rechnung erst am Bon (nicht vorab)

Bei Abholaufträgen wird KEINE Rechnung vorab ausgestellt.
Der Kassenbon IST die Rechnung (erzeugt Rechnungsnummer).
Steuer-Ereignis passiert EINMAL am Bon — keine Doppelberechnung.

## Maximalfall (alles auf einmal)

Auftragszahlung + Add-On Verkauf + Rückgabe + Gutschein-Einlösung gleichzeitig:
- Block 'auftrag':  Positionen aus Auftrag A-2026-XXXXX
- Block 'addon':    zusätzlich gescannte Artikel (neue K1-Auftrag)
- Block 'retour':   zurückgegebene Ware (negative Menge, kein_lagerabzug=true)
- Gutschein:        als Zahlungsart (gemischt: bar + gutschein)

## Bon-Layout (Kasse UI + Ausdruck)

In der Kasse-Zeilen-Liste:
```
📦 A-2026-00001         ← blauer Header über Auftrag-Block
  2× Merino Rot 100g   8,50 €
  1× Rundnadel 4,0mm  12,00 €
─── weitere Artikel ─── ← Trennlinie
  1× Strickbuch Anfänger  15,00 €
```

Auftrag-Zeilen: blaue Linksrand-Border, 📦-Badge, kein + Button.
Extra-Zeilen: kein Badge, + Button verfügbar.

## K1 Split-Logik (bon_speichern.php)

`erstelleBon()` wird NUR mit Bon-relevanten Positionen aufgerufen (keine vonAuftrag-Positionen bei bezahlt-Fall).
Danach in bon_speichern.php:

| Szenario | Web-Auftrag | K1 |
|---|---|---|
| Genau bestellt | bezahlt/abgeschlossen | gelöscht, bon→webAuftrag |
| Weniger genommen | teilbezahlt/teilgeliefert | gelöscht |
| Extra-Artikel dabei | bezahlt/abgeschlossen | überlebt mit nur Extras, korrigiertem Betrag |

Wenn K1 überlebt:
- `kassen_bons.auftrag_id → K1`
- `kassen_bons.web_auftrag_id → webAuftragId`
- `auftraege.kassen_bon_id → bonId` auf BEIDEN Aufträgen (sperrt Rechnung)
- K1 erhält nur die Extra+Retour-Positionen, neu berechneten brutto/netto/steuer

**Wichtig**: Web-Auftrag wird NICHT mit Extra-Artikeln verändert.
Extras/Retour → eigene K1. Beide Aufträge verweisen über den Bon aufeinander.

## Kein Merge von gescanntem Artikel in Auftrag-Zeile

`_artikelEinfuegen()` in bon.php sucht nur in nicht-vonAuftrag-Zeilen:
`findIndex(p => p.artikel_id == a.id && !p.istDivers && !p.vonAuftrag)`

Menge ändern bei Auftrag-Zeile:
- `−` Button: erlaubt (weniger nehmen) — original_menge wird gespeichert für Retour-Berechnung
- `+` Button: **gesperrt** (kein Mehr als bestellt via Auftrag-Zeile)
- Weniger nehmen → Rückbuchung ins Lager in bon_speichern.php

## Verknüpfung Auftrag ↔ Bon (Migration 092)

- `auftraege.kassen_bon_id INT NULL` — gesetzt wenn an Kasse bezahlt → sperrt Rechnung
- `kassen_bons.web_auftrag_id INT NULL` — Referenz auf Original-Webauftrag

## Flow Abholung (Phase 2 — implementiert 2026-06-28)

1. Auftrag angelegt (lieferart='abholung')
2. Ware gepickt → lieferstatus='abholbereit' → Mail an Kunden
3. Kasse lädt Auftrag → Block 'auftrag' befüllt, 📦-Header mit Auftragsnummer
4. Optional: Extra-Artikel scannen → eigene Zeile unter Trennlinie
5. Bon speichern → K1-Split-Logik → Web-Auftrag abschließen
6. Mail an Kunden wenn alleGeliefert=true

## Flow: Abholbereit + bereits bezahlt ✅ IMPLEMENTIERT (2026-06-29)

Sonderfall: `lieferstatus='abholbereit'` UND `zahlungsstatus='bezahlt'`
Kasse erkennt: `geladenerAuftragZahlungsstatus === 'bezahlt'` → andere Dialog-Logik.

| Fall | Client-Modus | Server-Pfad | Zahlungsstatus danach |
|------|-------------|------------|----------------------|
| Exakt wie bestellt | exakt | nur_abschliessen=true → früher Exit | bleibt 'bezahlt' |
| Kd. nimmt weniger | retour | retour-Positionen (neg. menge, block='retour') | 'erstattet' |
| Extra-Artikel dazu | extra | nur Extra-Positionen an erstelleBon | bleibt 'bezahlt' |
| Mix weniger+mehr, netto+ | extra | Extra+Retour-Positionen | 'bezahlt' oder 'erstattet' |
| Mix weniger+mehr, netto- | retour | Extra+Retour-Positionen | 'erstattet' |
| Exakt 0,-Bon (retour==extra) | extra (net≈0) | direkt bonSpeichern | 'bezahlt' |

### Technische Details

**JS (bon.php):**
- `geladenerAuftragZahlungsstatus` — gesetzt beim auftragWaehlen
- `original_menge` — je Warenkorb-Position gespeichert (original Auftrags-Menge)
- `aktuellerZahlBetrag` — Netto-Zahlbetrag; `_zahlBetrag()` gibt diesen oder getGesamt() zurück
- `zusatzPositionen` — Array mit Retour-Positionen (negative menge, block='retour')
- `berechneAbrechnungsModus()` → { modus: 'exakt'|'retour'|'extra', ... }
- `berechneZusatzPositionen()` → füllt zusatzPositionen für retour-Fälle
- `bezahlenDialog()` — brancht je nach Modus auf ov-bezahlt-info / ov-retour-bar / ov-bezahlen
- `abschliessenOhneBon()` — für exakt-Fall: POST mit nur_abschliessen=true
- `retourBestaetigen()` — für retour-Fall: berechneZusatzPositionen() + bonSpeichern()
- `_resetKasseState()` — DRY-Helper für alle Reset-Pfade nach Erfolg

**Server (bon_speichern.php):**
- `$nurAbschliessen` — früher Exit: menge_geliefert + Status + Mail ohne Bon
- `$webAuftragBezahlt` — filtert bonErstellungPositionen auf nur Extras+Retour
- Retour block-Items: `kein_lagerabzug=true` (Packplatz hat schon abgebucht)
- Zahlung: kein INSERT wenn bezahlt; negativer INSERT ($retourBetrag) wenn Erstattung
- Status: 'erstattet' wenn Retour, sonst 'bezahlt' (nicht doppelt buchen)

**Bon-Druck (bon_druck.php):**
- block='retour' → eigener "↩ RÜCKGABE" Abschnitt mit negativen Beträgen
- bruttobetrag < 0 → "RÜCKGABE" statt "GESAMT"
- Steuer-Totale: Retour-Positionen mit signed menge (reduzieren die Nettowerte)

**Erstattungsweg:**
- Derzeit: **Barauszahlung** (Kd. bekommt Differenz bar zurück)
- Zukünftig (wenn Gutschein-Modul fertig): Gutschein-Button in ov-retour-bar hinzufügen
  → `berechneZusatzPositionen()` + `bonSpeichern({ zahlungsart: 'gutschein', ... })`

## Rechtliches (AT)

- Bon mit allen Pflichtfeldern = vereinfachte Rechnung bis €400 (UStG §11 Abs. 6)
- Über €400: vollständige Rechnung mit Kundendaten (aus kunden_snapshot)
- RKSV: Bon signiert mit Fiskaly/BFR-BONit, QR-Code Pflicht

## Offene Punkte

- [ ] Auftrag-Detail-Seite: Hinweis wenn Begleit-Auftrag (K1↔Web-Auftrag) vorhanden
- [ ] Bon-Ausdruck: "AUFTRAG A-2026-xxx" Block-Header auf Print
- [ ] Firmenlogo fehlt am Bon-Ausdruck (Jacky-Notiz 2026-07-05: wird aktuell nicht angezeigt, obwohl RKSV-Pflichtfelder + Layout sonst stehen)
- [x] **Abholbereit+bezahlt Flow** ✅ FERTIG 2026-06-29
- [ ] Gutschein-Erstattungsoption in Kasse wenn Gutschein-Modul fertig
- [ ] Chargen-Bug (nächste Session — quer durch alle Module)
