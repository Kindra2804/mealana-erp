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

## Warndreieck bei manueller Mengen-Korrekturbuchung (Jacky-Notiz 2026-07-05)

Wenn ein Artikel bei Kasse oder sonstigen Abläufen manuell **mengenmäßig** korrekturgebucht wird (nicht Charge-Tausch, sondern echte Zu-/Abbuchung der Gesamtmenge) → Artikel bekommt ein Warndreieck-Flag:
- In der Artikelliste beim betroffenen Artikel anzeigen
- Im Dashboard (unterer Bereich, dort ist noch Platz laut Jacky) ebenfalls ein Dreieck-Widget
- Klick/Dropdown darauf → Liste der betroffenen Artikel + Text "Zwischeninventur empfohlen"
- Flag bleibt bestehen bis Zwischeninventur ODER große Inventur für den Artikel gemacht wurde

**Why:** Manuelle Mengenkorrekturen sind eine typische Fehlerquelle (Tippfehler, falsche Menge) — sichtbares Signal statt stillem Vertrauen in die Korrektur, bis der Bestand durch eine echte Zählung bestätigt wurde.

**How to apply:** Beim Bau des Inventur-Moduls einplanen. Braucht vermutlich ein Flag/Zeitstempel pro Artikel (z.B. `artikel.manuelle_korrektur_am` oder eigene Tabelle `artikel_inventur_hinweise`), das bei jeder manuellen Mengenkorrektur (nicht Charge-Wechsel) gesetzt und bei Inventur-Abschluss für den betroffenen Artikel wieder gelöscht wird. Siehe auch [[project_wawi_gaps]] (Blind-Inventur, Differenzliste) und `bewegungstyp='korrektur'` in `lager_bewegungen` als vermutlicher Trigger-Punkt.
