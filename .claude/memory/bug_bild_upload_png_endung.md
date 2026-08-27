---
name: bug-bild-upload-png-endung
description: PNG-Bilder-Upload zeigte broken link — Dateiname-Mismatch zwischen tatsächlich gespeicherter Datei und DB/URL
metadata: 
  node_type: memory
  type: project
  originSessionId: bf21b7a8-0044-4fd4-869f-1ae811833787
  modified: 2026-08-27T09:09:43.122Z
---

## Bug (gemeldet 2026-08-27, Artikel ID-W01)
`BildVerarbeitung::verkleinereUndSpeichere()` bekam einen Zielpfad mit `.jpg`-Endung übergeben, benannte ihn bei PNG-Bildern aber intern lokal auf `.png` um (`preg_replace`) und speicherte dort — die Änderung verließ die Methode nie (Rückgabetyp war nur `bool`). `bild_upload.php` schrieb weiterhin den ursprünglichen `.jpg`-Dateinamen in die DB und in die JSON-URL. Ergebnis: Datei liegt als `.png` auf der Platte, DB/Frontend zeigen auf `.jpg` → broken link. Nur PNG betroffen, JPG/WEBP nicht.

## Fix (gemeinsam mit Jacky durchgegangen, Trainer-Ansatz — [[feedback_trainer]])
- `verkleinereUndSpeichere()` Rückgabetyp `bool` → `string|false`, gibt bei Erfolg `basename($zielpfad)` zurück (den tatsächlich verwendeten Namen)
- `bild_upload.php`: `if ($verkleinert === false)` statt truthy-Check, danach `$dateiname = $verkleinert;` — DB-Insert und URL nutzen jetzt den korrekten Namen
- Jacky hat beide Änderungen selbst geschrieben, ich habe nur Fragen gestellt/verifiziert

**Why:** Interne Umbenennung einer lokalen Variable in einer Methode ist für den Aufrufer unsichtbar, wenn der Rückgabewert das nicht transportiert — Klassiker-Falle bei "Methode ändert Pfad je nach Inhalt".

**How to apply:** Bei ähnlichen Mustern (Methode passt intern einen übergebenen Pfad/Namen an) immer prüfen, ob der Aufrufer den ggf. geänderten Wert zurückbekommt statt weiter mit der alten Variable zu arbeiten.
