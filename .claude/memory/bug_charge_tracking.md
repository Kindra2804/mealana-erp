---
name: bug-charge-tracking
description: "Chargen-Tracking: BEHOBEN 2026-06-29 — Kasse/Packplatz/Umlagerung alle mit Charge"
metadata: 
  node_type: memory
  type: project
  originSessionId: ded531ce-e1a8-48e5-ad0b-4b4646922226
---

## ✅ BEHOBEN (2026-06-29, Session 19)

### Was implementiert wurde:
- **Kasse bon.php**: Charge-Dialog Overlay (ov-charge) — zeigt alle verfügbaren Chargen, "Nachzutragen", "Ohne Charge"
  - `zeigeKasseChargePopup(a, menge)` → `kasseChargeWaehlen(idx)` / `kasseChargeNtInput()` / `kasseChargeOhne()`
  - `_artikelEinfuegen()`: chargeNeu aus `a._gewaehltCharge` oder `fifo_charge`
  - Warenkorb enthält `nachzutragen_lagerbestand_id`
- **bon_speichern.php**: Rückbuchung (Storno-Flow) liest `charge` aus `auftrag_positionen` statt NULL
- **KassenService::erstelleBon()**: ruft `chargeNachtragen()` wenn nachzutragen_lagerbestand_id vorhanden
- **Packplatz intern/index.php**: Lagerumbuchung + Zustandsumbuchung beide mit Charge-Dropdown
  - `vonLagerGewaehlt()` / `zsVonLagerGewaehlt()`: async Fetch aus chargen_ajax.php → Dropdown befüllen
  - `umbuchenSpeichern()` + `zustandAnlegenUmbuchen()` senden `charge` im POST
- **umbuchen.php** + **zustand_anlegen_umbuchen.php**: lesen und übergeben `$charge`

### Noch offen:
- Race Condition im Lagerlog (3x gleicher Eintrag bei parallel laufenden Aufträgen) — niedrige Priorität
- Inventur: pro Charge zählen — kommt mit Inventur-Modul
- warenSchwund() — charge hardcoded NULL — wird bei Inventur-Modul mitgemacht

**Why:** Österreichische Lebensmittelkennzeichnung + MHD; Garne: Kunden wollen gleiche Färbecharge.
[[project-chargen-konzept]]
