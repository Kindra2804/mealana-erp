---
name: project-chargen-konzept
description: "Vollständiges Chargen-Tracking Konzept: 3 Typen, alle Lagerbewegungsstellen, UX pro Modul, Klärungen 2026-06-29"
metadata: 
  node_type: memory
  type: project
  originSessionId: ded531ce-e1a8-48e5-ad0b-4b4646922226
---

## Chargen-Typen (3 Stufen)

1. **Keine Charge** — Artikel hat `charge_pflicht=0` und keine Chargen im Lager → völlig ignorieren, safe
2. **Charge optional** — Artikel kann Chargen haben, muss aber nicht; wenn Chargen vorhanden → Auswahl anbieten
3. **Charge Pflicht** (`charge_pflicht=1`) — Muss immer Charge haben; wenn beim ersten Wareneingang keine eingetragen → `charge='nachzutragen'` gesetzt; spätestens an Kasse/Packplatz MUSS nachgetragen werden

**Why:** Österreichische Lebensmittelkennzeichnung + MHD-Tracking + Garne (Kunden wollen gleiche Färbecharge für Farbkonsistenz). Inventurliste hat 700+ Seiten — jedes Knäuel wird einzeln in der Hand gehalten!

---

## Wareneingang (ERP + Packplatz-Wareneingang)

- **EINZIGE Stelle** wo `charge='nachzutragen'` entstehen darf
- Wenn `charge_pflicht=1` und keine Charge eingegeben → `charge='nachzutragen'` schreiben
- Bei Chargenpflicht-Artikel: beim Verlassen ohne Charge-Eingabe Bestätigungsdialog "Keine Charge eingetragen - sicher?" anzeigen
- Zwei aufeinanderfolgende Lieferungen ohne Chargenangabe landen im gleichen Pool (UNIQUE KEY verhindert zwei 'nachzutragen'-Zeilen) → OK, Ausnahme in der Praxis
- Bereits fertig implementiert ✅

---

## Kasse (scan-basiert, touch-optimiert)

**Ablauf nach Artikel-Scan:**
1. Prüfe: Hat Artikel `charge_pflicht=1` ODER gibt es in `lagerbestand` Zeilen mit `charge IS NOT NULL`?
2. **Nein** → direkt buchen, kein Popup
3. **Ja** → Charge-Popup anzeigen mit:
   - Liste aller vorhandenen Chargen dieses Artikels (Chargennummer + Lagerbestand-Menge)
   - Für `charge='nachzutragen'`-Zeilen: Input-Field für echte Chargennummer + Mengen-Input
   - Für bestehende Chargen: Mengen-Input mit aktueller Lagerstand-Anzeige
   - Rechts neben jedem Input: Touch-taugliche `+` / `−` Buttons
   - Maximum: Lagerbestand (kann aber auch darüber = Überverkauf erlaubt)
   - Aufteilung auf mehrere Chargen möglich

**Charge am Bon:**
- Chargennummer(n) am Bon anzeigbar
- Per Systemeinstellung aktivier/deaktivierbar

---

## Packplatz (mengengesteuert, aus Auftrag/Pickliste)

**Ablauf nach Artikel-Scan:**
1. Gleiche Charge-Prüfung wie Kasse
2. **Nein** → buchen
3. **Ja** → Charge-Popup mit:
   - Gleiche Darstellung wie Kasse (Chargen + Mengen + `+/-`)
   - Maximum: Menge aus Pickliste (nicht Gesamtbestand)
   - Input für 'nachzutragen'-Chargen um echte Nummer einzutragen
4. **Gebuchte Charge(n) MÜSSEN in `auftrag_positionen.charge` gespeichert werden** → Basis für Rückgabe-Logik

---

## Retoure / Rückgabe (Ware kommt zurück)

- Artikel war bereits am Packplatz → `auftrag_positionen.charge` ist befüllt
- Beim Retoure-Dialog: Zeige die benutzten Chargen aus `auftrag_positionen`
- User wählt welche Chargen in welcher Menge wieder ins Lager gehen
- → Lagerbewegung "eingang" mit der ursprünglichen Charge

## Storno (Auftrag nicht bezahlt, noch nicht verpackt)

- Nur Reservierung, noch keine Lagerbewegung → kein Charge-Problem, einfach Reservierung aufheben

---

## Umlagerung (Lager→Lager)

- Charge-Auswahl wenn Chargen vorhanden
- Maximum: vorhandener Lagerbestand dieser Charge im Quelllager
- Gleiche Charge wird im Ziellager angesetzt (Charge wandert mit dem Bestand)

---

## Shop-Bestellungen (WooCommerce → ERP Auftragseingang)

- Shopbestellungen kommen ohne Charge rein (Chargen werden nicht an Shops übermittelt)
- Charge wird erst beim Packplatz zugewiesen

---

## Manuelle Aufträge (ERP-interne Erfassung)

- Nach Artikelwahl → optionaler Popup mit verfügbaren Chargen
- Warnung wenn nicht mit einer einzigen Charge erfüllbar
- Chargenauswahl ist OPTIONAL (KANN, nicht MUSS)
- Wenn gewählt: Vorschlag in Pickliste/Packplatz übernehmen, dort jederzeit überschreibbar

---

## Inventur (Modul noch offen)

- Charge-Artikel werden **pro Charge** gezählt (jede Zeile in lagerbestand = eine Zähl-Einheit)
- Mobile Endgeräte + Tablets geplant (statt 700-Seiten-Liste)
- Differenz-Buchung muss chargenspezifisch sein
- → Beim Inventur-Modul-Bau dieses Konzept als Anforderung mitführen

---

## Stellen mit Lagerbewegung — Charge-Checkliste

| Stelle | Charge-Quelle | Verpflichtend? | Status |
|--------|--------------|---------------|--------|
| Wareneingang ERP | Manuell eingegeben | Nein → 'nachzutragen' | ✅ fertig |
| Packplatz Wareneingang | Manuell eingegeben | Nein → 'nachzutragen' | ✅ fertig |
| Nachtragsliste UI | Modal / chargeNachtragen() | Ja | ✅ weitgehend fertig |
| Kasse Verkauf | Popup Auswahl | Ja wenn vorhanden | ❌ fehlt |
| Kasse Rückbuchung (Retoure) | aus auftrag_positionen | Ja | ❌ fehlt |
| Packplatz Warenausgang | Popup Auswahl | Ja wenn vorhanden | ❌ fehlt |
| Umlagerung | Popup Auswahl | Ja wenn vorhanden | ❌ fehlt (hardcoded NULL) |
| Schwund | Angabe nötig | Ja wenn vorhanden | ❌ fehlt (hardcoded NULL) |
| Inventur-Ausgleich | Direkt pro Charge | Ja | ⏳ Modul noch offen |

---

## Kern-Fix: LagerService charge-bewusst machen

`reduziereBestand($artikelId, $menge, $lagerId, $charge=null)` bekommt Charge als Parameter:
- Wenn `$charge` angegeben → direkt auf diese Zeile buchen
- Wenn kein `$charge` und keine Chargen vorhanden → charge=NULL Zeile (charge-freie Artikel, wie bisher)
- Wenn kein `$charge` aber Chargen vorhanden → FIFO automatisch (`getFifoCharge()` existiert bereits!) + Warnung loggen

## FIFO-Automatik (Fallback)

`getFifoCharge()` existiert bereits in LagerService. Nutzen wenn kein expliziter Charge-Parameter übergeben wird aber Chargen vorhanden sind.

## Implementierungs-Reihenfolge

1. `LagerService::reduziereBestand()` + alle Caller mit Charge-Parameter (Kern-Fix)
2. Packplatz Charge-Dialog + `auftrag_positionen.charge` befüllen
3. Kasse Charge-Dialog + Retoure liest aus `auftrag_positionen.charge`
4. Nachtragsliste UI (prüfen ob fertig)
5. `umbucheZwischenLager()` + `warenSchwund()` Charge-Fix (kleine Einzeiler)
6. Inventur (wenn Modul drankommt)

**How to apply:** Bei jedem Bugfix und jeder Neuimplementierung an Lagerbewegungsstellen: Charge muss als Parameter durchgereicht werden. `getFifoCharge()` als Fallback wenn keine explizite Charge bekannt.
