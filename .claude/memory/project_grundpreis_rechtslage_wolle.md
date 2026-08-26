---
name: project-grundpreis-rechtslage-wolle
description: "Grundpreis-Bezugsmenge fuer Wolle/Garne 1kg statt 100g -- 2026-08-13 UMGESETZT (ShopSyncService rechnet zur Sync-Zeit um, DB bleibt in Gramm), Kosmetik/Nadel bewusst ausgenommen; Meterware cm->m ebenfalls korrigiert; F-Mer120 Einheitsbug am 2026-08-26 von Jacky behoben"
metadata: 
  node_type: memory
  type: project
  originSessionId: c9c5b016-f30a-42cf-9c6e-b1d797b48f58
  modified: 2026-08-26T10:50:25.375Z
---

Jacky hat auf der WKO/Land-NÖ-Seite gefunden (2026-08-12): Nach dem österreichischen **Preisauszeichnungsgesetz** + der **Grundpreisauszeichnungsverordnung** gilt für Wolle, Garne und Zwirne als gesetzliche Bezugsmenge **1 Kilogramm** (bei Zwirnen alternativ 1.000 Meter). Die **100-g-Ausnahme gilt im Gesetz nur für spezielle Lebensmittel** (Wurst, Käse, Schokolade) — NICHT für Textilien/Garn.

**Betrifft:** Unser komplettes Grundpreis-System rechnet aktuell durchgängig auf 100g-Basis (`artikel.grundpreis_bezugsmenge` = 100, `inhalt_einheit` = 'g'), sowohl in der ERP-Anzeige (`artikel/detail.php`) als auch im WooCommerce/Germanized-Sync (`ShopSyncService::baueGrundpreisFelder()`/`baueGrundpreisVaterFelder()`, siehe [[project_datenqualitaet_20260812]]). Das ist für Garn/Wolle/Zwirn gesetzlich falsch — muss vor Live-Gang auf 1kg (bzw. 1000m bei Zwirn) umgestellt werden.

**Für die nächste Session, wenn das angegangen wird:**
- Betrifft vermutlich NUR Artikel mit `inhalt_einheit = 'g'` (Garn/Wolle) bzw. Zwirn-Artikel — Zubehör mit anderen Einheiten (Stück, etc.) ist nicht betroffen, das genau abgrenzen.
- Zwei Wege denkbar: (a) `grundpreis_bezugsmenge` von 100 auf 1000 ändern (Anzeige bliebe "€/1000g", unüblich) oder (b) `inhalt_einheit`-Bezug auf 'kg' umstellen und `grundpreis_bezugsmenge` auf 1 (Anzeige "€/kg", die übliche/erwartete Form) — vermutlich (b) die sinnvollere Lösung.
- WooCommerce/Germanized kennt 'kg' bereits als native Einheit (`findeEinheitId()` sucht per Slug in einer festen, vorinstallierten Liste g/kg/m/l/...) — sollte technisch unproblematisch sein.
- Betrifft potenziell tausende Artikel (Massenkorrektur nötig, kein Einzelfall) — Umfang vorher abklären (wie viele Artikel/Väter mit `inhalt_einheit='g'` und `grundpreis_anzeigen=1`).
- Nach der Korrektur: erneuter Sync nötig, damit die neuen Werte auch in WooCommerce ankommen.
- Noch nicht begonnen, nur der rechtliche Hinweis für die Zukunft festgehalten (Jacky: "da müssen wir alle Gewichte, Grundpreise usw. dementsprechend anpassen").

## ✅ UMGESETZT 2026-08-13

**Ansatz gewählt: nur der Shop-Sync rechnet um, die DB bleibt für Personal in Gramm.** Jacky wollte "Produkt enthält" weiter in g zeigen (Kunden fragen nach 25g/50g, nicht 0,05kg), aber die Grundpreis-Bezugsgröße rechtlich korrekt auf 1kg. Live gegen den Testshop geprüft: Germanized koppelt `unit_price.product` (Inhalt) und `unit_price.base` (Bezugsmenge) zwingend an dieselbe `unit` — eine Trennung (Inhalt in g, Bezugsgröße in kg) ist über die REST-API technisch nicht abbildbar (`GET /products/units` liefert g/kg als unabhängige, unverbundene Einträge ohne Umrechnungsfaktor). Endgültige Entscheidung: ehrlich auf kg umstellen (`unit=kg`, `base=1`, `product` in kg umgerechnet) — Barbara schaut sich das Ergebnis an, evtl. wird die "Produkt enthält"-Zeile im Shop danach ganz ausgeblendet (steht eh in der Beschreibung).

**Code:** `ShopSyncService::ermittleGrundpreisBasis()` (+ `ShopSyncRepository::findGrundpreisFelder()` liefert jetzt zusätzlich `artikeltyp_code`) — bei Artikeltyp GARN + `inhalt_einheit='g'` wird zur Sync-Zeit immer fix gegen 1000g gerechnet (ignoriert bewusst die gespeicherte `grundpreis_bezugsmenge`, damit alte Dateneingabefehler dort automatisch mit korrigiert werden). Andere `g`-Artikel (Kosmetik/Seifen unter Typ STANDARD, 88 Stück, dürfen laut Jacky bei 100g/100ml bleiben) und der eine NADEL-Artikel bleiben unverändert — Scope bewusst auf Artikeltyp GARN begrenzt, nicht auf `inhalt_einheit='g'` pauschal.

**Datenqualitäts-Nebenfunde beim Review (zwei komplette Export-Listen gezogen, `nicht_garn_grundpreis.tsv` + `garn_inhaltsmenge.tsv`, liegen unter `D:\ERP\mealana\exports\grundpreis_2026-08-13\`):**
- `11012` Bastelwatte 1kg — stand mit `inhalt_menge=1g` (Grundpreis ~100× zu hoch) — von Jacky selbst behoben.
- `LY-698-0199`/`-0209` MOHAIR LUXE — Komma-Fehler `0,25g` statt eines sinnvollen Werts — von Jacky auf 25g korrigiert.
- **`F-Mer120`/`F-Mer120-M413` Merino 120 — `inhalt_einheit` stand auf der Ziffer "5" statt "g".** ✅ Von Jacky am 2026-08-26 behoben.
- `ART-001` Testartikel_autoscan — wirkt wie liegengebliebener Testartikel, kein echter Verkaufsartikel, noch nicht aufgeräumt.
- `CL-3158` "Elastisches Garn" ist als Typ STANDARD statt GARN einsortiert — preislich unauffällig (m/100), aber evtl. Fehlkategorisierung, nicht vertieft.
- Meterware/Webband (26 Artikel, Typ STANDARD+METERWARE, `inhalt_einheit='cm'`) hatte dieselbe Art Problem (z.T. `€/1cm`-Basis) — direkt per SQL auf `m`/Bezugsmenge 1 umgestellt (Wert mitkonvertiert, gleiches Prinzip wie bei Garn).

**Sync ausgelöst:** alle heute inhaltlich geänderten Artikel (335 Zeilen: Kammzüge/Ramie/Leinen/ANNE/DROPS-Serien/Eucalan/Filzseife/Bastelwatte/Mohair Luxe/Webband) + zusätzlich alle 8.390 aktiven Garn-Kanal-Zuweisungen wegen der Logik-Änderung auf `sync_status='pending'` gesetzt (`markiereFuerErneutenSync()`-Mechanismus, SQL-Äquivalent). Jacky lässt den Shopabgleich selbst laufen. Live-Ergebnis auf `indra-design.at` von Barbara noch zu bestätigen.
