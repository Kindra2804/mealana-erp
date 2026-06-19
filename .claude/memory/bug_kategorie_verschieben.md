---
name: bug-kategorie-verschieben
description: "BEHOBEN 2026-06-19: Vater-Kind Vererbung vollständig implementiert"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

## ✅ BEHOBEN (2026-06-19)

**Was gefixed wurde:**
- `KategorieRepository::syncKategorienZuKindern()` — neue Methode, propagiert Kategorien an alle Kinder wenn Vater gespeichert wird
- `ArtikelRepository::propagiereZuKindern()` — neue Methode, ein UPDATE propagiert alle 22 gemeinsamen Felder (Beschreibungen, Logistik, Zoll, Grundpreis) an alle Kinder
- `ArtikelService::saveKategorien()` — ruft jetzt syncKategorienZuKindern() auf
- `ArtikelService::update()` — ruft jetzt propagiereZuKindern() auf
- `ArtikelService::kopiereVaterRelationenZuKindern()` — neue Methode für frisch erstellte Kinder
- `VariantenService::erstelleKombinationen()` — erbt jetzt alle ~25 Felder statt nur 4, gibt IDs zurück
- `varkombi_erstellen.php` — ruft kopiereVaterRelationenZuKindern() nach Erstellung auf

**Commit:** 94632d2

**Noch offen:** Aktions-Kategorie Bug → siehe [[bug-aktionskategorie-zuweisung]]
