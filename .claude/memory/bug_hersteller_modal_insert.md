---
name: bug-hersteller-modal-insert
description: "BEHOBEN 2026-07-02: Hersteller-Neuanlage warf 'Netzwerkfehler' — PDO-Bug bei extra id-Parameter in insert()"
metadata: 
  node_type: memory
  type: project
  originSessionId: eefd559b-9c02-443d-a0cb-164e3dadf876
---

**Behoben 2026-07-02.** Babsi wollte einen Hersteller anlegen — das Modal (Neu+Bearbeiten gemeinsames Formular) schickte immer ein `id`-Feld mit (bei Neuanlage leer, da `<input type="hidden" name="id">` immer Teil des Formulars ist). `HerstellerService::save()` reichte das ungefiltert an `HerstellerRepository::insert()` durch, dessen SQL aber keinen `:id`-Platzhalter hat → PDO warf `SQLSTATE[HY093]: Invalid parameter number` → PHP Fatal Error → kaputtes JSON → Browser zeigte generisch "Netzwerkfehler – bitte nochmal versuchen".

**Warum das nur bei Neu auffiel, nicht bei Bearbeiten:** `update()`s SQL hat `:id` im WHERE — dort passt der extra Key zufällig, deshalb funktionierte Bearbeiten die ganze Zeit.

**Fix:** `unset($data['id'])` in `HerstellerService::save()` vor dem `insert()`-Aufruf.

**Beim Debuggen bestätigt (Reproduktion via rohem multipart-Request, exakt wie der Browser ihn schickt):** Der Fehlertext im Browser war identisch mit dem *aktuellen* JS-Code — das widerlegte meine erste Vermutung (Browser-Cache von vor dem Modal-Umbau). Lehre: bei "Netzwerkfehler"-artigen Meldungen zuerst prüfen ob der Fehlertext wirklich aus dem aktuellen Code stammt, bevor man Richtung Cache/alte Version sucht.

**Gleiches PDO-Verhalten (extra Array-Key ohne passenden Platzhalter → Fatal Error) systemweit geprüft:**
- `ArtikelRepository::insert()` — bereits sicher, filtert explizit per `array_intersect_key()` (offenbar früher schon mal an genau diesem Bug vorbeigeschrammt)
- `LieferantenRepository`, `AchsenRepository` — sicher, bauen ihr Parameter-Array explizit selbst
- `PartnerRepository::insert()` — reicht `$data` roh durch (technisch gleiche Schwachstelle), aber aktuell nicht betroffen weil "Neuer Partner" ein komplett eigenes `<form>` ganz ohne `id`-Feld ist (kein gemeinsames Modal wie bei Hersteller). **Nicht gefixt, nur vermerkt** — falls das Partner-Neu/Bearbeiten-Modal mal zusammengelegt wird, hier zuerst dieselbe Absicherung einbauen.

**How to apply:** Bei jedem neuen "gemeinsames Modal für Neu+Bearbeiten mit verstecktem id-Feld"-Umbau (z.B. wenn Achsen das noch bekommt, siehe [[feedback_achsen_modal]]) prüfen, ob die zugehörige `insert()`-Methode extra Array-Keys verträgt — entweder durch `unset($data['id'])` im Service vor dem Insert, oder durch explizites Key-Filtering in der Repository wie bei Artikel.

## Zweiter Fall bestätigt: LagerRepository::updateLager() (2026-07-05)

Gleiches Muster, diesmal beim **Update statt Insert**: `LagerService::bereinigeLager()` reichert `$data` um `partner_id`/`kunde_id` an (für den späteren Insert gebraucht), aber `updateLager()`s SQL kennt diese Platzhalter nicht → PDO warf beim Speichern im Bearbeiten-Modal `SQLSTATE[HY093]`, Frontend zeigte gar keine Fehlermeldung (Modal blieb einfach offen, `res.json()` scheiterte lautlos an der HTML-Fehlerseite). Siehe [[project_lager_konzept]] für Details. Bestätigt damit: das PDO-Verhalten ist **kein Insert-spezifisches Problem**, sondern gilt für jede `execute()`-Aufruf mit überzähligen Array-Keys, egal ob INSERT oder UPDATE.

**Fix-Muster, das sich bewährt hat:** In der Repository-Methode das gebundene Array explizit selbst bauen (nur die Keys, die die Query auch wirklich referenziert), statt `$data`/`$_POST` roh durchzureichen — robuster als einzelne `unset()`-Aufrufe im Service, weil es nicht vergessen werden kann wenn später neue Felder zum Formular dazukommen.
