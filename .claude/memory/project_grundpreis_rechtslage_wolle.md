---
name: project-grundpreis-rechtslage-wolle
description: Grundpreis-Bezugsmenge fuer Wolle/Garne/Zwirne ist gesetzlich 1kg (bzw. 1000m bei Zwirnen), NICHT 100g -- unser System rechnet aktuell falsch, Korrektur noch offen
metadata:
  type: project
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
