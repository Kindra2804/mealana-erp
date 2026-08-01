---
name: bug_vater_artikel_bestand_wareneingang
description: Vater-Artikel bekamen fälschlich Lagerbestand bei Wareneingang (Lager + Packplatz) statt dass das Kind gebucht wird — BEHOBEN 2026-08-01
metadata: 
  node_type: memory
  type: project
  originSessionId: 66d7f6e5-73a2-4fbe-ae8f-903ff8a00d54
  modified: 2026-08-01T08:43:04.311Z
---

## ✅ BEHOBEN 2026-08-01

**Root Causes gefunden:**
1. `public/js/lager_wareneingang.js:28` — Operatorvorrang-Bug (`+` bindet stärker als `||`) erzeugte nie geschlossenes HTML (`<div><strong>NR` ohne `</strong>`/`</div>`). Bei mehreren Kind-Treffern verschachtelten sich die Ergebnis-`<div>`s ineinander ("Kästchen ineinander") — dadurch bubbelte jeder Klick durch alle `onclick`-Handler nach oben, am Ende gewann immer das äußerste/erste Element unabhängig vom tatsächlichen Klick.
2. `public/packplatz/intern/artikel_ajax.php` hatte keinerlei Filter auf Vater-Artikel in der Such-Query.
3. **Gemeinsame Wurzel:** `LagerService::wareneingang()`/`validiere()` prüfte nie, ob die `artikel_id` ein Vater ist — einzige Stelle die tatsächlich in `lagerbestand` schreibt, wird von 3 Frontends aufgerufen (Lager-WE, Packplatz-WE, Schnell-WE-Modal in `artikel/detail.php`).
4. **Wichtiger Nebenfund:** Das `artikel.ist_vater`-Flag ist bei Altbeständen unzuverlässig — 16 echte Väter (u.a. AD-CS/Carosello, AD-DM/Doremi, alle DROPS-Achsen-Artikel aus der Zeit VOR dem JTL-Vater-Kind-Import) hatten `ist_vater=0`, weil der alte VarKombi-Generator (Achsen-System) das Flag nie gesetzt hat — nur `JtlVaterKindImportService` tut das. Reine `ist_vater=1`-Prüfung hätte diese 16 Fälle NICHT abgefangen.

**Fix:**
- `ArtikelRepository::istVater()` (neu) prüft jetzt `ist_vater=1 OR EXISTS(Kinder mit vaterartikel_id=diese_id)` — robust gegen das Flag-Problem.
- `LagerService::validiere()` lehnt Buchung ab wenn `istVater()` true → schützt automatisch alle 3 Aufrufer.
- `packplatz/intern/artikel_ajax.php`: erkennt Vater-Treffer (Flag ODER Kinder-EXISTS), liefert statt Buchungsdaten eine Kinderliste zurück (`gefunden:false, ist_vater:true, kinder:[...]`).
- `packplatz/wareneingang/frei.php`: neuer Auswahl-Bereich (`#kinder-auswahl-bereich`) — bei Vater-Treffer erscheint ein `<select>` mit den Kindern, Auswahl triggert automatisch eine neue Suche mit der Kind-Artikelnummer.
- `lager/variante_suche.php`: Standalone-Query bekam zusätzlich `AND NOT EXISTS (Kinder)`, damit ein fälschlich ungeflaggter Vater nicht mehr als eigenständig buchbarer Treffer erscheint.
- **Datenkorrektur:** Die 16 betroffenen Altväter bekamen `ist_vater=1` per UPDATE nachgezogen (einmalige Bereinigung, kein laufender Job nötig). AD-CS' fälschliche `lagerbestand`-Zeile (25) gelöscht, Korrektur-Eintrag in `lager_bewegungen` protokolliert (Referenz "Datenkorrektur AD-CS-Bug"), alle 6 Kinder (je 5) unangetastet.
- Funktional getestet: `LagerService::wareneingang()` lehnt Vater-Buchung mit klarer Fehlermeldung ab, Kind-Buchung funktioniert weiterhin (Testbuchung + Rollback verifiziert), `istVater()` isoliert für Vater (true) und Kind (false) bestätigt.

## ✅ Nachtrag 2026-08-01: Root Cause im PRODUKTIVEN Erzeugungsweg gefixt (nicht nur Altlast!)

**Wichtige Korrektur von Jacky:** Der VarKombi-Generator ist NICHT "alt" — er ist im Normalbetrieb der Standardweg um neue Vater/Kind-Artikel anzulegen. Der JTL-Massenimport ist nur ein einmaliger/sporadischer Vorgang, kein laufender Prozess. Die 16 Altfälle waren also nur die Spitze — ohne Fix hätte JEDER künftig neu angelegte Vater-Artikel dasselbe Problem gehabt (Flag nie gesetzt → Wareneingang-Guard hätte ihn nicht erkannt).

**Gefixt an BEIDEN produktiven Erzeugungsstellen:**
- `VariantenService::erstelleKombinationen()` (der VarKombi-Generator selbst, `artikel/detail.php` Tab "Varianten") — ruft jetzt `VariantenRepository::setIstVater()` sobald mindestens ein Kind erstellt wurde.
- `ArtikelService::saveKind()` (manuelle Einzelvariante über `artikel/variante_neu.php`) — ruft jetzt `ArtikelRepository::setIstVater()` direkt nach dem Insert.
- Beide Repository-Methoden sind simple `UPDATE artikel SET ist_vater=1 WHERE id=:id` (gleiches Muster wie `setArtikelAktiv()`/`setAuslaufartikelAktiv()`).

**Funktional getestet** (Wegwerf-Testartikel "ZZTEST-VATER"/"ZZTEST-VATER2", danach vollständig gelöscht): beide Pfade setzen das Flag jetzt korrekt, kein Nebeneffekt auf bestehende Logik. Damit ist die Wurzel behoben, nicht nur die 16 historischen Symptome — jeder künftig neu angelegte Vater bekommt das Flag sofort richtig, der `ArtikelRepository::istVater()`-Guard bleibt trotzdem als zweite Absicherung (EXISTS-Kinder-Check) bestehen, falls doch mal ein dritter Weg auftaucht.

## Ursprünglicher Bug-Report (2026-08-01)

**Fundort 1 — Lager/Wareneingang:** Kinder eines Vater-Artikels sind zwar selektierbar, aber die Auswahl-UI sah kaputt aus ("lauter Kästchen die ineinander sind"). Trotz Auswahl eines Kindes wurde die Buchung auf den Vater-Artikel vorgenommen, nicht auf das gewählte Kind.

**Fundort 2 — Packplatz/Wareneingang:** Dort waren Vater-Artikel überhaupt erst auswählbar.

**Grundregel:** Ein Vater-Artikel darf NIE selbst einen Lagerbestand bekommen — sein Bestand ist rein die Summe seiner Kinder (Variantenlogik, siehe [[project_lager_konzept]]).

**Konkreter Datenschaden:** Vater-Artikel "AD-CS" hatte einen Lagerbestand von 25, obwohl er null haben durfte. Die 6 Kinder hatten je 5 Lagerbestand (korrekt, unangetastet gelassen).
