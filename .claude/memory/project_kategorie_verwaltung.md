---
name: project-kategorie-verwaltung
description: "Kategorie-Verwaltung (kategorien_verwalten.php): 'Einordnen nach'-Positionierung beim Neu/Bearbeiten FERTIG (2026-07-29), Kategoriebild FERTIG (siehe project_shop_sync.md)"
metadata:
  node_type: memory
  type: project
  originSessionId: 90944ba5-8049-44b1-96c3-29acb1265131
  modified: 2026-07-29T14:33:22.557Z
---

## Ausgangslage (Jacky, 2026-07-29)

Beim Nachtragen der Live-Änderungen + Neuanlegen vieler Kategorien in Dev störte: neue Kategorien landen immer ganz oben (kein `sortierung`-Wert bei `insert()`), danach mussten sie Zeile für Zeile per ▲/▼-Pfeil verschoben werden — bei vielen Kategorien, die ans Ende oder mittendrin gehören, sehr mühsam. Jeder Pfeil-Klick lädt zudem die komplette Seite neu (`window.location.reload()`), was zusätzlich wie ein Sprung nach oben wirkte.

**Abgewogene Optionen:** Drag & Drop / Nummernwert direkt eingeben / "Einordnen nach [Kategoriename]". Entscheidung für Option 3 — ähnlich wenig Code wie ein Nummernfeld (nutzt dieselbe Renumerierungs-Logik wie die bestehenden Pfeile), aber ohne dass Jacky Zahlen jonglieren muss.

## ✅ "Einordnen nach"-Positionierung FERTIG (2026-07-29)

- **`KategorieRepository::positioniereNach($id, $nachId, $parentId)`** (neu): holt Geschwister (`getSiblingsWithSort()`), entfernt sich selbst, fügt an der gewünschten Stelle ein, nummeriert alle sequentiell neu durch (10, 20, 30…) — gleiches Muster wie das bestehende Pfeil-Sortieren in `kategorie_sort_ajax.php`.
- `ArtikelService::createKategorie()`/`updateKategorie()` bekommen einen neuen optionalen `$nachId`-Parameter, rufen danach `positioniereNach()` auf. Rückwärtskompatibel (bestehender Aufrufer `kategorie_neu.php` mit nur 2 Argumenten funktioniert unverändert weiter — landet dann wie bisher am Anfang).
- Modal (`kategorien_verwalten.php`) hat eine neue Dropdown "Einordnen" neben "Oberkategorie". Wird per JS (`katvAktualisierePositionsDropdown()`) live befüllt, abhängig von der gewählten Oberkategorie (`window.KATEGORIEN_FLACH`, aus `flattenBaum()` mit ergänztem `parent_id`).
- **Vorbelegung:** beim Bearbeiten die bisherige Position (kein ungewolltes Verschieben, wenn man das Feld nicht anfasst); bei neuen Kategorien oder nach Oberkategorie-Wechsel ans Ende (statt wie bisher automatisch an den Anfang).

**🔴 Bug beim ersten Testen (durch die Kategoriebild-Session am selben Tag gefunden, siehe [[project_shop_sync]]):** Die Vorbelegungs-Logik verwechselte "kein Vorgänger, weil die Kategorie schon an erster Stelle steht" mit "kein Vorgänger, weil unbekannt" (z.B. bei neuen Kategorien) — beide Fälle lieferten denselben leeren Wert, der Code fiel dann fälschlich auf "ans Ende stellen" zurück. Jede bisher erste Kategorie sprang beim nächsten Speichern (z.B. weil nebenbei ein Bild zugewiesen wurde) ans Ende. Fix: drei klar unterschiedene Fälle (`eigenerIndex > 0` / `=== 0` / `=== -1`) statt eines zweideutigen leeren Sentinel-Werts.

**Bewusst nicht verändert:** die bestehenden ▲/▼-Pfeile bleiben für die Feinjustierung um eine Position erhalten, kein Umbau nötig.

**How to apply:** Bei Problemen mit Kategorie-Reihenfolge künftig zuerst hier nachsehen (die drei Fälle in `katvAktualisierePositionsDropdown()` in `kategorien_verwalten.js`) statt neu zu diagnostizieren.
