---
name: project-datenqualitaet-20260812
description: "Kettenreaktion 2026-08-12 - Tabs-Bug -> mindestbestand-NULL -> Preis-Duplikate -> Achsenpreis-Differenzierung verloren, alles behoben inkl. Wiederherstellung aus JTL-CSVs"
metadata:
  type: project
  originSessionId: c9c5b016-f30a-42cf-9c6e-b1d797b48f58
  modified: 2026-08-12T19:47:25.190Z
---

Session 2026-08-12 deckte eine Kette von vier zusammenhängenden Bugs auf, alle Folgen der JTL-Importe der Vortage. Alle behoben und verifiziert, siehe [[project_jtl_kunden_auftraege_import]] für den Import-Kontext.

## 1. Artikel-Tabs kaputt (zeigeTab is not defined)
Kein JS-Bug: `LagerRepository::findBestandChargeProLager()` gab `mindestbestand` roh zurück, `detail.php` rief `formatBestand($lg['mindestbestand'])` ohne Null-Schutz auf. Der JTL-"Eigener Export"-Import (2026-08-11) hatte beim Anlegen der `lagerbestand`-Zeilen `mindestbestand` nie gesetzt (Spalte `DEFAULT NULL`) → 20.954 von 20.975 Zeilen NULL → PHP-Fatal-Error brach die Seite mitten im Lager-Tab ab, bevor das schließende `<script src="artikel_detail.js">` je ausgegeben wurde. Fix: `COALESCE(lb.mindestbestand, 0)` in der Query + Backfill bestehender NULL-Zeilen auf 0 + Import-Skript setzt jetzt explizit 0.

## 2. Doppelte Preiszeilen im Preise-Tab (z.B. BP-GI-0010)
`ArtikelRepository::copyPreise()`/`copyLieferanten()` nutzten `INSERT IGNORE`, aber `artikel_preise`/`artikel_lieferanten` haben keinen UNIQUE-Key dafür — jeder erneute Generator-/Import-Lauf über denselben Vater fügte jedem bereits existierenden Kind eine weitere Preiszeile hinzu (bis zu 7 Duplikate). Fix: `NOT EXISTS`-Check (NULL-sicher mit `<=>`) statt `INSERT IGNORE`. Erste Bereinigung: "neueste Zeile pro Gruppe behalten" — 25.730 doppelte Preiszeilen + 465 doppelte Lieferanten-Zeilen gelöscht.

## 2b. Wichtige Lektion aus der ersten Bereinigung — siehe [[feedback_dedup_intra_gruppe_pruefen]]
Die "neueste Zeile gewinnt"-Regel war bei normalen Artikeln richtig, hat aber bei Achsen-differenzierten Vater/Kind-Artikeln (Farbtyp-Achsen wie Fabel Uni/Print/Long Print, Nadelspitzen-Stärke) die **echte Preisdifferenzierung gelöscht** — weil ein späterer VarKombi-Generator-Lauf (nicht der JTL-Import, der re-appliziert danach den korrekten JTL-Preis) jedem Kind eine neue Dublette mit dem flachen Vater-Preis hinzugefügt hatte, die dann als "neueste" galt.

## 3. Achsenpreis-Differenzierung verloren (Fabel, Nadelspitzen, insgesamt 177 Väter)
Wiederhergestellt aus den unangetasteten Original-JTL-CSVs in `import/erledigt/` (169 Dateien, 19.292 Artikel, Artikelnummer→Brutto-VK/Netto-VK). Gezielt NUR die Väter korrigiert, bei denen das eindeutige Fehler-Muster vorlag (CSV zeigt ≥2 unterschiedliche Kind-Preise, DB zeigt nur noch 1) — 177 Väter, 4.261 Kind-Preise. Bewusst NICHT angerührt: ~2.869 andere DB/CSV-Abweichungen ohne dieses Muster (vermutlich legitime spätere Preispflege).

## 4. Root-Cause-Fix: Aufpreis-Modus war nicht idempotent
`ArtikelRepository::passeKindPreiseAn()` (Aufpreis-Zweig) addierte bisher auf den **aktuellen Kind-Preis** statt auf den **Vater-Preis** — jeder erneute Lauf hätte den Aufpreis nochmal draufaddiert (Aufschaukeln). Fix: Aufpreis wird jetzt immer `Vater-Preis (aktuell) + preis_wert` gesetzt, per JOIN auf `artikel_preise` des Vaters (gleiche kundengruppen_id). Mit Test verifiziert (3x derselbe Aufruf → gleiches Ergebnis, in Transaktion getestet + zurückgerollt).

Migration 166: `artikel_achsen.preis_modus` Standard von `aufpreis` auf `direktpreis` geändert (Jackys Wunsch — Aufpreis ist die Ausnahme, Direktpreis der Normalfall bei JTL-Ware mit bekannten Zielpreisen). Betrifft nur den Fallback für noch nicht konfigurierte Achsen, bestehende gespeicherte Zuweisungen bleiben unverändert (Fabel zeigt in der UI weiterhin "Aufpreis" markiert bis Jacky es manuell auf Direktpreis umstellt und die Werte einträgt).

## Bestätigt (Jacky, 2026-08-12): Achsen-Preis-Speichern rührt bestehende Artikel nicht an
`achsen_speichern.php` → `updateAchsePreis()` schreibt NUR in `artikel_achsen` (Konfiguration), fasst `artikel_preise` nie an. Die Konfiguration wirkt erst, wenn der Varianten-Generator tatsächlich Kombinationen anlegt (angehakte Checkboxen). Jacky kann also jederzeit Direktpreise für Fabel/Nadelspitzen eintragen, ohne bestehende Preise zu verändern.

## Alles FERTIG 2026-08-12
- Alle vier Fixes verifiziert + committed + gepusht.
- `achsen_speichern.php` hatte noch eine dritte übersehene `'aufpreis'`-Fallback-Stelle (Zeile 42-44) — beim Commit mitgefixt auf `'direktpreis'`.
- Erkannt, nicht behoben (bewusst zurückgestellt): Achsen-Preis-Mechanismus unterstützt nur EINEN Preis pro Achse, keine gestaffelten Preise pro Wert innerhalb einer Achse (relevant für Nadelspitzen-Stärke mit vielen Durchmessern) — bräuchte Feature-Erweiterung falls das mal UI-seitig gepflegt werden soll. Aktuell kein Bedarf (Nadeln ändern sich selten, Jacky pflegt neue Werte manuell mit korrektem Preis).

## Echtbetrieb-Test der Aktion "DROPS Super Sale" (2026-08-12 Nachmittag) — drei weitere echte Bugs gefunden + behoben
Erster echter Aktions-Test (Kategorie 424, 5 Väter/222 Artikel) deckte auf:
1. **`PreisRepository::findAktionsPreis()`** verglich `aktionen_artikel_preise.artikel_id` direkt gegen die übergebene Artikel-ID — bei Varianten-Artikeln liegt der Aktionspreis aber unter der VATER-ID + `sub_achse_id`. Kein Kind bekam je einen Aktionspreis. Fix: Auflösung über Kind→Vater + Achsen-Zugehörigkeit (`varianten_kombination_werte`/`varianten_achse_werte`), NULL-sicher mit Achsen-Vorrang vor globalem Fallback.
2. **`findFaelligeAktionskategorien()`** verglich einen Zeitstempel (`aktion_sichtbarkeit_synced_am`) direkt gegen ein reines Datum (`gueltig_bis`) — bei einem Sync am selben Kalendertag wie das Ablaufdatum wäre die Übergangs-Erkennung nie ausgelöst (Zeit > 00:00:00). Fix: `DATE()`-Cast auf beiden Seiten des Vergleichs.
3. **Grundpreis bei Aktionspreisen falsch**: `baueGrundpreisFelder()` befüllte nur EIN Germanized-Feld (mit dem bereits reduzierten Preis statt getrennt Regulär/Angebot) — analog zum späten Fund, dass der Vater bei Variable Products gar keinen Grundpreis-Zahlenwert bekam (WooCommerce/Germanized verwirft `price`/`price_regular`/`price_auto` dort komplett, nur `unit`/`base`/`product` werden akzeptiert — echte Plattform-Grenze, mit `price_auto=true` gegengetestet, weiterhin verworfen). Fix: Regulär-/Angebotsgrundpreis wie beim normalen VK sauber getrennt (`price_regular`/`price_sale`), am Vater wird zusätzlich der günstigste Kind-Grundpreis mitgeschickt (best-effort, wird aber am Vater selbst ignoriert). Live gegen die echte WooCommerce-Variante verifiziert (korrektes `<del>20,20€</del> <ins>14,20€</ins>`).

Alle 222 Artikel mehrfach neu synced und live verifiziert (direkte API-GETs gegen die echten WooCommerce-Daten, nicht nur unsere DB). Committed + gepusht.

## Rechtlicher Nachtrag (Jacky, 2026-08-12 Abend) — siehe [[project_grundpreis_rechtslage_wolle]]
Grundpreis-Bezugsmenge für Wolle/Garn/Zwirn ist laut österreichischem Preisauszeichnungsgesetz gesetzlich 1kg (bzw. 1000m bei Zwirn), nicht 100g — die 100g-Ausnahme gilt nur für bestimmte Lebensmittel. Unser System rechnet aktuell durchgängig auf 100g. Noch nicht angegangen, nur festgehalten für die nächste Session.
