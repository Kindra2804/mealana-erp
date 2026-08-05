---
name: bug-kind-zurueck-vater-button
description: "BEHOBEN 2026-08-05: 'zurück zum Vater'-Button bei Kind-Artikeln hing an URL-Parameter statt echter DB-Verknüpfung, verschwand nach dem Speichern"
metadata:
  node_type: memory
  type: project
  originSessionId: 52c4dc34-cf31-47b8-a90a-52b7eaeeddfa
  modified: 2026-08-05T10:15:17.352Z
---

Jacky bemerkte: öffnet man ein Kind über den Vater (Reiter Varianten), zeigt `detail.php`
links oben "zum Vater-Artikel". Ändert man etwas am Kind und speichert, ist der Button weg,
obwohl die Verknüpfung selbst nie verloren geht.

**Root Cause:** Der Button (+ "Abbrechen"-Button + `we_rueckkehr`-Hidden-Field für die
Speichern-Weiterleitung) hing komplett am URL-Parameter `$_GET['von_vater']`, nicht an
`artikel.vaterartikel_id`. Öffnet man das Kind direkt (Suche, Liste, Lesezeichen) oder geht
der Parameter bei der Weiterleitung nach dem Speichern verloren, fehlt `von_vater` -- Button
weg, obwohl die echte DB-Verknüpfung die ganze Zeit korrekt da ist.

**Fix:** Alle drei Stellen in `public/artikel/detail.php` (Sidebar-Back-Button, Abbrechen-Button,
`we_rueckkehr`-Hidden-Field) verwenden jetzt `$istKind`/`$vaterId` (abgeleitet aus
`$artikel['vaterartikel_id']`, das ohnehin schon geladen wird) statt `$_GET['von_vater']`.
`$vonVater`-Variable komplett entfernt.

**Nebeneffekt (mit Jacky abgestimmt, er fand's gut):** Weil `we_rueckkehr` jetzt immer gesetzt
ist, wenn ein Kind gespeichert wird, landet man nach dem Speichern eines Kindes jetzt IMMER
beim Vater (Reiter Varianten) -- auch wenn man das Kind direkt geöffnet hatte, nicht nur wenn
man über den Vater kam. Bewusst akzeptiert, bei Bedarf später änderbar.

**Getestet:** `getDetailArtikel()` direkt gegen echte DB aufgerufen (Kind 3890, Vater 3889) --
`$istKind`/`$vaterId` berechnen korrekt. Kein Browser-Klicktest möglich (kein Browser-Tool
verfügbar), aber Jacky hat die Logik nach Erklärung akzeptiert.
