---
name: inventur-schwund-typ
description: "bewegungstyp 'schwund' in lager_bewegungen bereits vorhanden — bei Inventur-Modul nutzen"
metadata: 
  node_type: memory
  type: project
  originSessionId: ca0c5951-aedc-432f-be05-4f481a4956fc
---

`lager_bewegungen.bewegungstyp` hat seit Migration 083 den Wert `'schwund'` (neben eingang/ausgang/korrektur/inventur).

**Warum:** Beim Messe-Sync wurde Schwund (Verlust/Beschädigung) eingeführt, damit er im Lagerprotokoll direkt filterbar ist — nicht nur über die referenz-Spalte.

**How to apply:** Beim Inventur-Modul KEINEN neuen Typ anlegen — `'schwund'` über `LagerService::warenSchwund()` verwenden für Inventurdifferenzen (Ist < Soll). Für Inventur-Zählungen selbst den Typ `'inventur'` (bereits vorhanden) nutzen.

## RKSV-Erinnerung beim Erstellen der großen Inventur-Zählliste

Beim Anlegen der **großen** Inventur-Zählliste (nicht Zwischeninventur) soll ein Popup erscheinen: "Jahresendbeleg erstellen nicht vergessen", wenn das aktuelle Datum zwischen 15.12. und 10.01. liegt. Der Nullbon selbst läuft über den bestehenden manuellen Trigger in der Kasse (RKSV-Anzeige in der Kopfzeile → `ajax_nullbon.php`), siehe [[project_rksv_bfr]].

**Why:** Doppelte Absicherung für den Jahresendbeleg — die große Inventur fällt bei Jacky ohnehin zwischen Weihnachten und Neujahr, ein Popup zu diesem Zeitpunkt ist ein zusätzlicher Reminder neben dem monatlich-automatischen Nullbon.

**How to apply:** Beim Bau der Inventur-Zählliste-Erstellung diesen Datumscheck einbauen (15.12.–10.01.), reiner Hinweis-Popup, kein Blocker.
