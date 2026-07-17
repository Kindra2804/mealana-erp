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

## ⚠️ Ganzes Modul fehlt komplett — Einplanung + Reihenfolge (2026-07-10)

Bei der Roadmap-Bestandsaufnahme aufgefallen (und von Jacky selbst als "komplett übersehen" bestätigt): es gibt **keinen einzigen Code** für ein Inventur-Modul — keine Zählliste, keine Blind-Inventur, kein Differenzabgleich. Alles bisher Notierte in dieser Datei sind nur Anknüpfungspunkte (bestehender `bewegungstyp='inventur'`/`'schwund'`) für ein Modul, das noch nicht existiert.

**Warum jetzt wichtig (Jacky):** Spätestens bei der Übernahme der echten Ist-Bestände in den Live-Betrieb wird eine funktionierende Inventur gebraucht.

**Reihenfolge (Jackys Entscheidung 2026-07-10):** Direkt nach dem Buchhaltungsthema einplanen (siehe [[project_roadmap_reihenfolge]]) — noch vor den kleineren Ad-hoc-Punkten.

**Neue Anforderung, dazugekommen:** Inventur soll von Anfang an **mehrere gleichzeitige Zähler** unterstützen (Notebook/Tablet, mehrere Helfer parallel) — nicht nur eine Person nacheinander. Deckt sich mit der schon vorgemerkten "Mehrere Zähler gleichzeitig"-Zeile in [[project_wawi_gaps]] (dort nur MITTEL priorisiert, jetzt von Jacky aktiv bestätigt als Muss-Anforderung).

**Lagerplätze — Status geklärt: NICHT gebaut.** Jacky erinnerte sich richtig, dass das mal vermerkt war (siehe [[project_wawi_gaps]] "Lagerplätze" MITTEL, und [[project_spalten_picker]] wo die Spalte als reiner Platzhalter markiert ist, "Modul existiert noch nicht"). Code-Check bestätigt: keine `lagerplaetze`-Tabelle, keine `lagerplatz_id`-Spalte irgendwo im Schema — komplett 0%. **Relevanz für Inventur:** ohne Lagerplätze ist bei mehreren gleichzeitigen Zählern nicht sauber steuerbar, wer welchen Bereich schon gezählt hat — Gefahr von Doppel- oder Lücken-Zählung. Vermutlich echte Voraussetzung für die "mehrere Zähler gleichzeitig"-Anforderung, nicht nur ein Nice-to-have daneben.

**Wichtig — vor dem Bau, nicht danach:** Jacky will eine **richtige Absprache/Design-Session** machen, bevor das Inventur-Modul gebaut wird (nicht einfach direkt loslegen) — passt zum etablierten Vorgehen ([[feedback_modul_vorgehen]]: Referenz-Check + Design vor Code). Diese Datei hier ist nur die Sammlung der bisherigen Einzel-Notizen, kein fertiges Konzept.

**How to apply:** Wenn das Thema drankommt: zuerst klären ob Lagerplätze als Voraussetzung mitgebaut werden müssen oder ob eine einfachere Zähler-Zuordnung reicht (z.B. nur "wer hat wann zuletzt an diesem Artikel gezählt" ohne echte Orts-Struktur). Referenz-Check gegen JTL/Shopware/Sage-Inventurfunktionen machen (siehe [[feedback_modul_vorgehen]]), dann erst Design.
