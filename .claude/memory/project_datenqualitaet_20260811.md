---
name: project-datenqualitaet-20260811
description: "2026-08-11: Fünf Datenqualitäts-Listen (Grundpreis/Einheit/Chargenpflicht bei Garn) FERTIG; Abend-Nachfund: Vater→Kind-Vererbung lief bei 841 Vätern/15.135 Kindern noch nie (charge_pflicht+grundpreis_anzeigen betroffen!), BEHOBEN + Spalten-Picker-Bug (Artikelgruppe nicht speicherbar) BEHOBEN"
metadata:
  type: project
  originSessionId: unknown
  modified: 2026-08-11T19:53:44.462Z
---

## Auftrag (Jacky, 2026-08-11 Vormittag)

Fünf Datenqualitäts-Listen angefragt: (1) Grundpreis aktiv aber unzureichende Daten, (2) Einheit=Meter (sollte oft cm sein), (3) Artikel ganz ohne Einheit, (4) Artikeltyp Garn ohne Grundpreisangabe, (5) "Gruppe Garn" ohne Chargenpflicht.

**Klärung nötig:** Es gibt keine Artikelgruppe die wörtlich "Garn" heißt (nur Artikeltyp), am ehesten "Wolle" als Buchhaltungsgruppe — aber nur bei ~30% der Garn-Artikel überhaupt gesetzt. Jacky hat bestätigt: Artikeltyp GARN ist gemeint, nicht die Buchhaltungsgruppe.

## Wichtige technische Erkenntnis: Vater→Kind-Propagation

`ArtikelRepository::propagiereZuKindern()` kopiert bei jedem Vater-Speichern automatisch `einheit_id`, `inhalt_menge`, `inhalt_einheit`, `grundpreis_bezugsmenge`, `grundpreis_anzeigen`, `charge_pflicht`, `artikel_gruppe_id` u.a. auf ALLE Kind-Varianten. Alle 5 Listen wurden deshalb bewusst nur auf Vater-/Standalone-Ebene gebaut (`vaterartikel_id IS NULL`) — Korrektur am Vater reicht, Kinder folgen automatisch beim Speichern über die UI.

Export-Skript (einmalig, scratchpad, nicht im Repo): 5 CSVs nach `D:\ERP\mealana\exports\datenqualitaet_2026-08-11\` — Format wie `artikel/liste_export.php` (`;`-getrennt, UTF-8-BOM, CRLF).

## Ergebnis je Liste

1. Grundpreis unzureichend: 16 Familien gefunden, Jacky manuell auf 1 reduziert.
2. Einheit Meter: 51 Familien (Einheit `Centimeter`/id=14 existiert in der DB bereits, nur noch keinem Artikel zugewiesen).
3. Ohne `inhalt_einheit` (nur Garn/Meterware relevant): 154→141 nach Jackys manueller Korrektur. Echte Verkaufseinheit (`einheit_id`) fehlt bei KEINEM Artikel (Pflichtfeld, 0 Treffer) — das war Jackys eigentliche Frage bei Punkt 3, beantwortet.
4. Garn ohne Grundpreisangabe: 150→141 nach manueller Korrektur.
5. Garn ohne Chargenpflicht: 557 gesamt, davon 420 "echtes" Garn (Grundpreis aktiv) vs. 137 Sets/Kits/Pakete/Anleitungen. Jacky wollte nur Knäuel/Strang-Einheit (339 Artikel) — Sets/Gramm/Stück/Packung bewusst ausgenommen.

## Bug/Anomalie beim Export gefunden (nicht abschließend geklärt)

Die ursprünglich generierte Liste 5 zeigte bei 216 von 557 Zeilen `Chargenpflicht=ja` obwohl die Liste explizit nach `charge_pflicht=0` filtert (nur bei Liste 5 aufgetreten, Listen 1-4 waren in der gleichen Spalte durchgehend korrekt). Live-DB war zum Prüfzeitpunkt für alle 557 nachweislich `nein`/`0`, kein Aktivitäten-Log-Eintrag (`artikel.bearbeiten`) für die betroffenen IDs an dem Tag, kein JTL-Import gelaufen — Ursache nicht gefunden, nicht reproduzierbar bei frischem Query-Rerun. Betraf auffällig fast nur Marken-Garne (Lang Yarns, DMC, Katia, ProLana, Laines du Nord, Kremke Soul Wool, Sesia, BC Garn, Adriafil, Rosy Green Wool, Durable, Rellana).

Jacky dazu: "Ich dachte ich hätte die Chargenpflicht eh nur bei Knäueln geändert" — er glaubte, das selbst schon in der App gesetzt zu haben, es stand aber nicht in der DB. Mögliche Erklärung: ein Speicher-Bug bei der Chargenpflicht-Checkbox in `artikel/bearbeiten.php` (Checkbox scheint zu sichern, Wert kommt aber nicht in der DB an) — **nicht untersucht**, Jacky hat sich für direkte Bulk-Korrektur statt Bug-Suche entschieden. Wenn das Chargenpflicht-Setzen über die UI Jacky nochmal "nicht hält", ist das hier der erste Ansatzpunkt (`artikel/bearbeiten.php` Checkbox-Handling + `ArtikelService::update()`/`ArtikelRepository::update()` charge_pflicht-Pfad prüfen).

## Umsetzung Liste 5

`erp/scripts/backfill_garn_chargenpflicht.php` (mit `--dry-run`, folgt Muster von `backfill_uvp.php`/`backfill_inhalt_einheit.php`): setzt `charge_pflicht=1` bei allen aktiven Garn-Vater/Standalone-Artikeln mit Einheit Knäuel oder Strang (`einheit_id IN (1,12)`), propagiert manuell auf alle Kind-Varianten (da Bulk-Lauf nicht über `ArtikelService::update()` geht, `propagiereZuKindern()` also nicht automatisch mitläuft), bumpt `aktualisiert_am` für den nächsten Shop-Sync. Live gelaufen: 339 Vater/Standalone + 5.502 Kind-Varianten aktualisiert.

**How to apply:** Bei künftigen Datenqualitäts-Listen für Vater/Kind-Artikel immer nur auf Vater-/Standalone-Ebene bauen (siehe Propagation oben) — spart Karl unnötige Kind-Zeilen beim manuellen Durchsehen. Vor jedem Bulk-Korrektur-Skript aus einer CSV-Vorlage: die CSV nochmal frisch gegen die Live-DB validieren, nicht blind übernehmen (siehe Anomalie-Fund oben).

## ✅ Nachfund + BEHOBEN 2026-08-11 (Abend): Vater→Kind-Vererbung lief bei 841 Vätern noch NIE

Jacky bemerkte beim JTL-Lagerbestand-Import (siehe [[project_jtl_kunden_auftraege_import]]), dass frisch mit Bestand sichtbar gewordene Artikel ihre Artikelgruppe verloren zu haben schienen. Verifiziert: **kein** Import-Bug (weder der JTL-Kunden/Aufträge- noch der Eigener-Export-Import schreiben `artikel_gruppe_id`) — echte, vorbestehende Lücke, nur durch den Bestand erstmals sichtbar geworden.

**Root Cause gefunden:** `ArtikelRepository::propagiereZuKindern()` läuft NUR, wenn ein Vater-Artikel einzeln über die UI gespeichert wird (`ArtikelService::update()`). 841 JTL-importierte Väter wurden seit ihrer ursprünglichen Anlage nie einzeln gespeichert — die Vererbung lief für ihre 15.135 Kind-Artikel also kein einziges Mal.

**Systematischer Audit aller 24 von `propagiereZuKindern()` vererbten Felder** (Kind- vs. Vater-Wert verglichen, `<=>` NULL-sicher): durchgehend dasselbe Muster — Kind leer/Default, Vater hat den Wert, **0 Fälle mit echtem inhaltlichem Konflikt** (bei Beschreibungen/Meta-Feldern extra geprüft: 100% "Kind leer, Vater hat Text", nie umgekehrt, nie beide unterschiedlich befüllt). Zwei Funde mit echter Business-Relevanz, nicht nur kosmetisch:
- **`charge_pflicht`**: 1.177 Kinder ohne Chargenpflicht, obwohl Vater sie verlangt (1.177 von 1.183 Abweichungen in diese Richtung, nur 6 umgekehrt) — Farbkonsistenz-Risiko beim Wareneingang, genau Jackys Verdacht bestätigt. Vermutlich derselbe Ursache-Typ wie die unten dokumentierte, nicht abschließend geklärte "216 von 557"-Anomalie vom selben Morgen — hier aber eindeutig auf fehlende Vererbung zurückgeführt, nicht auf einen Checkbox-Speicherbug.
- **`grundpreis_anzeigen`**: 1.565 Kinder zeigen den gesetzlich vorgeschriebenen Grundpreis nicht an, obwohl der Vater ihn zeigt (100% in diese Richtung).
- Weitere betroffene Felder (alle "Kind leer, Vater hat Wert"): `einheit_id` (153), `kurzbeschreibung` (12.577), `beschreibung` (6.730), `inhalt_menge`/`grundpreis_bezugsmenge` (je 7.053), `inhalt_einheit` (1.641), `gewicht_artikel` (2.348), `laenge`/`breite`/`hoehe` (je 1.705), `herkunftsland` (1.174).

**Fix:** `erp/scripts/backfill_vater_kind_sync.php` (`--dry-run`-fähig) — ruft für alle 841 betroffenen Väter die bestehende, produktiv genutzte `propagiereZuKindern()` direkt auf (keine eigene Feldliste nachgebaut, garantiert identisches Ergebnis zu einem normalen Vater-Speichern in der UI). Live gelaufen: alle 841 Väter propagiert, danach 0 Abweichungen bei allen 24 Feldern über den gesamten Katalog verifiziert.

**Nebenbei im selben Zug gefunden+gefixt:** Spalten-Picker in `artikel/liste.php` — "Artikelgruppe" ließ sich anhaken, wurde aber nie gespeichert/angezeigt. Ursache: `spalten_einstellung_speichern.php` hat eine EIGENE, hartcodierte Whitelist-Liste erlaubter Spalten, die beim Hinzufügen der Artikelgruppen-Spalte (Kontrollliste-Arbeit, 2026-08-09) nie mit aktualisiert wurde — `artikelgruppe` fehlte dort, wurde beim Speichern still rausgefiltert. Nachgetragen.

**How to apply:** Der Speicher-Bug-Verdacht bei der Chargenpflicht-Checkbox (oben, "Bug/Anomalie beim Export gefunden") bleibt weiterhin ungeklärt und ist NICHT dasselbe wie dieser Vererbungs-Fund — beide könnten zusammenhängen, aber unterschiedliche Mechanismen (Checkbox-Speichern vs. fehlende Propagation). Falls Jacky nochmal eine unerwartete Chargenpflicht-Abweichung nach einer UI-Bearbeitung meldet, zuerst dort nachsehen (`artikel/bearbeiten.php` Checkbox-Handling), nicht hier. — Bei künftigen "Vater/Kind laufen auseinander"-Verdachtsfällen: `propagiereZuKindern()`-Feldliste als Checkliste nehmen (siehe oben), Divergenz-Query-Muster (`NOT (a.feld <=> vater.feld)`) direkt wiederverwendbar.
