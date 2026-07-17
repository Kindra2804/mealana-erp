---
name: project-kasse-bon-design
description: "Kassen-Bon Design: Blocks (auftrag/addon/storno/retour), K1-Split-Logik, Web-Auftrag-Flow inkl. Abholbereit+bezahlt"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9a44da56-fbce-4da5-b4f6-17b472024d63
---

## 🟢 BUG behoben (2026-07-09): A4-Kassenbon-PDF im Mailanhang ohne Ränder

`BonA4Renderer.php` setzte die Seitenränder nur über `@page { margin: 15mm 18mm; }` — Dompdf wendet `@page`-Margin beim PDF-Export (Mailanhang, `fuerPdf=true`) unzuverlässig an, die Browser-Vorschau hatte zusätzlich ein `@media screen`-Padding und sah deshalb fälschlich unauffällig aus. Rechnungs-PDFs (`rechnung/standard.html.twig`) waren nie betroffen, weil die von Anfang an **Body-Padding statt `@page`-Margin** nutzen — robuster bei Dompdf.
**Fix:** `@page` nur noch für die Papiergröße, `body { padding: 15mm 18mm; }` unconditional (gilt für Screen UND Dompdf-Export gleichermaßen), Screen-Media-Query ergänzt nur noch Rahmen/Schatten/Zentrierung, ohne eigenes Padding mehr zu duplizieren.
**Getestet:** Echtes PDF aus einem realen Kassenbon erzeugt und visuell geprüft (sauberer Rand auf allen Seiten) — von Jacky bestätigt.
**How to apply:** Bei künftigen Dompdf-Templates immer Body-Padding statt `@page`-Margin verwenden, falls das Template sowohl für Screen-Vorschau als auch als Mail-/Datei-Anhang gerendert wird.

## ✅ BUG behoben (2026-07-10): unnötige "Warenkorb wird geleert"-Warnung beim Auftrag-Laden

Jacky bemerkte beim Kundenanzeige-Test: `auftragWaehlen()` warnte + löschte den Warenkorb IMMER, sobald schon Artikel drin lagen — auch wenn noch gar kein Auftrag geladen war. Dabei unterstützt das System genau das umgekehrte Mischen längst (📦-Header + "weitere Artikel"-Trennlinie, siehe Bon-Layout oben) — nur eben nicht in dieser Reihenfolge (erst Laufkunden-Artikel scannen, dann Auftrag laden).

**Fix:** Bedingung von `warenkorb.length > 0` auf `geladenerAuftragId !== null` umgestellt. Schon gescannte Artikel werden jetzt automatisch als "weitere Artikel" neben dem geladenen Auftrag weitergeführt (funktioniert ohne Änderung an `berechneAbrechnungsModus()`/`renderBon()`, weil die schon immer pro Position auf `vonAuftrag` unterscheiden, nie auf einen globalen Modus). Die Warnung kommt jetzt nur noch, wenn **schon ein anderer Auftrag geladen ist** und gewechselt werden soll — das würde zwei Aufträge mischen, was bewusst erst mit [[project_kundenanzeige_modul]]s Sammelabholung-Idee (Online-Shop-Anbindung) kommt, nicht vorher.

**Why:** Die alte Bedingung prüfte den falschen Zustand (Warenkorb-Inhalt statt Auftrag-Ladezustand) — ein Relikt aus der Zeit vor dem Extra-Artikel-Mischen.
**How to apply:** Kein Show-Stopper-Bug, nur UX — nicht separat live nachgetestet (Logik ist dieselbe wie beim bereits bewährten umgekehrten Weg), nächster Praxistest sollte trotzdem einmal den genauen Ablauf bestätigen.

## Kernentscheidung: Rechnung erst am Bon (nicht vorab)

Bei Abholaufträgen wird KEINE Rechnung vorab ausgestellt.
Der Kassenbon IST die Rechnung (erzeugt Rechnungsnummer).
Steuer-Ereignis passiert EINMAL am Bon — keine Doppelberechnung.

## Maximalfall (alles auf einmal)

Auftragszahlung + Add-On Verkauf + Rückgabe + Gutschein-Einlösung gleichzeitig:
- Block 'auftrag':  Positionen aus Auftrag A-2026-XXXXX
- Block 'addon':    zusätzlich gescannte Artikel (neue K1-Auftrag)
- Block 'retour':   zurückgegebene Ware (negative Menge, kein_lagerabzug=true)
- Gutschein:        als Zahlungsart (gemischt: bar + gutschein)

## Bon-Layout (Kasse UI + Ausdruck)

In der Kasse-Zeilen-Liste:
```
📦 A-2026-00001         ← blauer Header über Auftrag-Block
  2× Merino Rot 100g   8,50 €
  1× Rundnadel 4,0mm  12,00 €
─── weitere Artikel ─── ← Trennlinie
  1× Strickbuch Anfänger  15,00 €
```

Auftrag-Zeilen: blaue Linksrand-Border, 📦-Badge, kein + Button.
Extra-Zeilen: kein Badge, + Button verfügbar.

## K1 Split-Logik (bon_speichern.php)

`erstelleBon()` wird NUR mit Bon-relevanten Positionen aufgerufen (keine vonAuftrag-Positionen bei bezahlt-Fall).
Danach in bon_speichern.php:

| Szenario | Web-Auftrag | K1 |
|---|---|---|
| Genau bestellt | bezahlt/abgeschlossen | gelöscht, bon→webAuftrag |
| Weniger genommen | teilbezahlt/teilgeliefert | gelöscht |
| Extra-Artikel dabei | bezahlt/abgeschlossen | überlebt mit nur Extras, korrigiertem Betrag |

Wenn K1 überlebt:
- `kassen_bons.auftrag_id → K1`
- `kassen_bons.web_auftrag_id → webAuftragId`
- `auftraege.kassen_bon_id → bonId` auf BEIDEN Aufträgen (sperrt Rechnung)
- K1 erhält nur die Extra+Retour-Positionen, neu berechneten brutto/netto/steuer

**Wichtig**: Web-Auftrag wird NICHT mit Extra-Artikeln verändert.
Extras/Retour → eigene K1. Beide Aufträge verweisen über den Bon aufeinander.

## Kein Merge von gescanntem Artikel in Auftrag-Zeile

`_artikelEinfuegen()` in bon.php sucht nur in nicht-vonAuftrag-Zeilen:
`findIndex(p => p.artikel_id == a.id && !p.istDivers && !p.vonAuftrag)`

Menge ändern bei Auftrag-Zeile:
- `−` Button: erlaubt (weniger nehmen) — original_menge wird gespeichert für Retour-Berechnung
- `+` Button: **gesperrt** (kein Mehr als bestellt via Auftrag-Zeile)
- Weniger nehmen → Rückbuchung ins Lager in bon_speichern.php

## Verknüpfung Auftrag ↔ Bon (Migration 092)

- `auftraege.kassen_bon_id INT NULL` — gesetzt wenn an Kasse bezahlt → sperrt Rechnung
- `kassen_bons.web_auftrag_id INT NULL` — Referenz auf Original-Webauftrag

## Flow Abholung (Phase 2 — implementiert 2026-06-28)

1. Auftrag angelegt (lieferart='abholung')
2. Ware gepickt → lieferstatus='abholbereit' → Mail an Kunden
3. Kasse lädt Auftrag → Block 'auftrag' befüllt, 📦-Header mit Auftragsnummer
4. Optional: Extra-Artikel scannen → eigene Zeile unter Trennlinie
5. Bon speichern → K1-Split-Logik → Web-Auftrag abschließen
6. Mail an Kunden wenn alleGeliefert=true

## Flow: Abholbereit + bereits bezahlt ✅ IMPLEMENTIERT (2026-06-29)

Sonderfall: `lieferstatus='abholbereit'` UND `zahlungsstatus='bezahlt'`
Kasse erkennt: `geladenerAuftragZahlungsstatus === 'bezahlt'` → andere Dialog-Logik.

| Fall | Client-Modus | Server-Pfad | Zahlungsstatus danach |
|------|-------------|------------|----------------------|
| Exakt wie bestellt | exakt | nur_abschliessen=true → früher Exit | bleibt 'bezahlt' |
| Kd. nimmt weniger | retour | retour-Positionen (neg. menge, block='retour') | 'erstattet' |
| Extra-Artikel dazu | extra | nur Extra-Positionen an erstelleBon | bleibt 'bezahlt' |
| Mix weniger+mehr, netto+ | extra | Extra+Retour-Positionen | 'bezahlt' oder 'erstattet' |
| Mix weniger+mehr, netto- | retour | Extra+Retour-Positionen | 'erstattet' |
| Exakt 0,-Bon (retour==extra) | extra (net≈0) | direkt bonSpeichern | 'bezahlt' |

### Technische Details

**JS (bon.php):**
- `geladenerAuftragZahlungsstatus` — gesetzt beim auftragWaehlen
- `original_menge` — je Warenkorb-Position gespeichert (original Auftrags-Menge)
- `aktuellerZahlBetrag` — Netto-Zahlbetrag; `_zahlBetrag()` gibt diesen oder getGesamt() zurück
- `zusatzPositionen` — Array mit Retour-Positionen (negative menge, block='retour')
- `berechneAbrechnungsModus()` → { modus: 'exakt'|'retour'|'extra', ... }
- `berechneZusatzPositionen()` → füllt zusatzPositionen für retour-Fälle
- `bezahlenDialog()` — brancht je nach Modus auf ov-bezahlt-info / ov-retour-bar / ov-bezahlen
- `abschliessenOhneBon()` — für exakt-Fall: POST mit nur_abschliessen=true
- `retourBestaetigen()` — für retour-Fall: berechneZusatzPositionen() + bonSpeichern()
- `_resetKasseState()` — DRY-Helper für alle Reset-Pfade nach Erfolg

**Server (bon_speichern.php):**
- `$nurAbschliessen` — früher Exit: menge_geliefert + Status + Mail ohne Bon
- `$webAuftragBezahlt` — filtert bonErstellungPositionen auf nur Extras+Retour
- Retour block-Items: `kein_lagerabzug=true` (Packplatz hat schon abgebucht)
- Zahlung: kein INSERT wenn bezahlt; negativer INSERT ($retourBetrag) wenn Erstattung
- Status: 'erstattet' wenn Retour, sonst 'bezahlt' (nicht doppelt buchen)

**Bon-Druck (bon_druck.php):**
- block='retour' → eigener "↩ RÜCKGABE" Abschnitt mit negativen Beträgen
- bruttobetrag < 0 → "RÜCKGABE" statt "GESAMT"
- Steuer-Totale: Retour-Positionen mit signed menge (reduzieren die Nettowerte)

**Erstattungsweg:**
- Derzeit: **Barauszahlung** (Kd. bekommt Differenz bar zurück)
- Zukünftig (wenn Gutschein-Modul fertig): Gutschein-Button in ov-retour-bar hinzufügen
  → `berechneZusatzPositionen()` + `bonSpeichern({ zahlungsart: 'gutschein', ... })`

## Rechtliches (AT)

- Bon mit allen Pflichtfeldern = vereinfachte Rechnung bis €400 (UStG §11 Abs. 6)
- Über €400: vollständige Rechnung mit Kundendaten (aus kunden_snapshot)
- RKSV: Bon signiert mit Fiskaly/BFR-BONit, QR-Code Pflicht

## Offene Punkte

- [x] Auftrag-Detail-Seite: Hinweis wenn Begleit-Auftrag (K1↔Web-Auftrag) vorhanden ✅ erledigt 2026-07-08 (neue "Zusätzliche Kassenbons"-Sektion)
- [x] Bon-Ausdruck: "AUFTRAG A-2026-xxx" Block-Header auf Print — bereits vorhanden (`BonA4Renderer.php` nutzt `web_auftrag_nr` schon für den 'auftrag'-Block), nicht live nachgetestet
- [x] Firmenlogo fehlt am Bon-Ausdruck ✅ erledigt 2026-07-08 (siehe unten, `shops.logo_pfad`)
- [x] **Abholbereit+bezahlt Flow** ✅ FERTIG 2026-06-29
- [ ] Gutschein-Erstattungsoption in Kasse wenn Gutschein-Modul fertig
- [ ] Chargen-Bug — Rest (Race Condition, warenSchwund) kommt mit Inventur-Modul, nicht Kasse-spezifisch mehr
- [x] **Redesign Auftrag-Lade-Flow** ✅ FERTIG 2026-07-08 (siehe unten)
- [x] **Doppel-Gutschrift-Sperre (menge_retourniert)** ✅ FERTIG 2026-07-08 (siehe unten)
- [x] **Freitext-Retour für JTL-Altbestand** ✅ FERTIG 2026-07-09 (siehe unten) — noch nicht live getestet
- [x] **Packplatz-Benachrichtigung bei Kassen-Retour** ✅ FERTIG 2026-07-09 (siehe unten) — noch nicht live getestet
- [ ] **Mehrere Aufträge gleichzeitig in einem Bon** — auf "nice to have" zurückgestuft 2026-07-09 (Jacky: "wenn alles andere fertig ist"), kein Show-Stopper fürs Live mehr. Wartet weiter auf Barbara-Feedback.
- [ ] **Freitext-Artikel fehlen im K1-Auftrag-Spiegel** (K1-Split-Filter verlangt artikel_id, Bon selbst korrekt)
- [x] **`bfr_url` Offline-127.0.0.1-Fix + Selbstheilung** ✅ FERTIG 2026-07-09 — siehe [[project_rksv_bfr]], noch kein Hardware-Test

## Redesign Auftrag-Lade-Flow (vorgemerkt 2026-07-08, nach echtem BFR-Hardware-Test)

Beim ersten echten Live-Test (siehe [[project_kassen_verwaltung]]) fiel auf: der aktuelle Ansatz, den Modus (Retour/Extra/Mix) HINTERHER aus der Mengen-Differenz im Warenkorb zu erraten, ist die Quelle mehrerer Bugs (Zeile verschwindet bei Menge 0, Dialog passt nicht bei `versendet`-Status). Jackys Vorschlag: Modus direkt aus `lieferstatus`+`zahlungsstatus` beim Laden ableiten, kein Rätselraten:

1. **`versendet` (+ bezahlt, das ist bei `versendet` immer der Fall)** → automatisch ein eigener Abschnitt "Retoure zu A-xxx" statt des bisherigen Mitnehmen-Dialogs. Wichtig: Eingabe soll sein **"wie viel kommt zurück"**, nicht "wie viel wird behalten" (umgekehrt zur aktuellen Logik, die auf `original_menge - menge` beruht) — inkl. Chargen-Auswahl für die zurückgenommene Ware (welche Charge kommt zurück, nicht nur wie viel).
2. **`abholbereit` + `bezahlt`** → automatisch "nur abholen" einstellen, KEIN Auswahl-Popup mehr. Mengen bleiben aber änderbar: weniger als bestellt → Überzahlung/Teilrückgabe, mehr als bestellt → zusätzliche Bonpositionen (Extra). Laut Jacky sollte das im Kern schon funktionieren (Abholbereit+bezahlt Flow von 2026-06-29), nur eben in die neue, explizitere Struktur einordnen.
3. **`abholbereit` + offen/nicht bezahlt** → wie 2., aber Zahlbetrag kommt frisch aus dem Auftrag (noch nichts vorausbezahlt).

**Offene Rückfrage (nicht geklärt):** `teilgeliefert` (teils versandt, teils noch offen) — vierte Variante nötig, oder Sonderfall? Bei Redesign klären.

**Warum das den aktuellen Ansatz nicht verwirft:** Das kombinierte "Auftrag+Extra+Retour+Gutschein in einem Bon"-Ziel bleibt bestehen (bewusst besser als JTL) — nur die Modus-ERKENNUNG wird von "aus Mengen-Differenz erraten" auf "aus Auftragsstatus ableiten" umgestellt. Kein Rebuild, gezielte Schärfung der wunden Stelle.

## ✅ Redesign Auftrag-Lade-Flow FERTIG (2026-07-08, gleicher Tag umgesetzt)

`teilgeliefert` wurde wie `versendet` behandelt (nur Retoure, Menge-Obergrenze `menge_geliefert` statt `menge` — Ausnahme: bei `versendet` gilt die volle `menge` als Obergrenze, da `menge_geliefert` bei einfacheren Status-Setz-Pfaden — z.B. reine Tracking-Nr.-Eingabe in `auftraege_detail.js` — oft gar nicht gepflegt wird).

**`bon.php`:** neuer State `retourePositionen` (parallel zu `warenkorb`, NICHT vermischt) — eigene "↩ Retoure zu A-xxx"-Sektion mit Stepper pro Position (0..maxMenge) und Charge-Anzeige aus dem Warenausgang (`auftrag_positionen.charge`, jetzt auch von `ajax_auftrag_laden.php` mitgeliefert). `auftragWaehlen()` leitet bei `versendet`/`teilgeliefert` in diese Sektion um (kein Mitnehmen-Popup mehr); alle anderen Status unverändert. `berechneAbrechnungsModus()`/`berechneZusatzPositionen()` erweitert, damit Retour-Positionen aus der neuen Sektion in die bestehende Extra/Retour-Berechnung einfließen — **wichtig:** genau wie beim alten Mechanismus MUSS `auftrag_position_id` dabei explizit `null` bleiben (nicht die echte ID durchreichen), sonst filtert `bon_speichern.php`s Positions-Filter die Zeile als "schon bezahlt" komplett raus.

### Fünf echte Bugs beim Testen gefunden (davon einer >4 Wochen alt, nie aufgefallen)

1. **`kassen_bon_positionen.block` ENUM fehlte 'retour' komplett** (`ENUM('auftrag','addon','storno')` — seit Feature-Bau 2026-06-29 so). MySQL speichert einen ungültigen ENUM-Wert im non-strict Modus lautlos als leeren String statt Fehler — **jede** Retour-Zeile, jemals, hatte dadurch nie wirklich `block='retour'` in der DB, egal ob alter oder neuer Flow. Erklärt rückwirkend, warum Retour-Kennzeichnung auf Bons nie funktioniert haben kann. **Migration 119**: Enum um 'retour' erweitert + bestehende Fehlbuchungen (`menge<0 AND block=''`) automatisch korrigiert.
2. **Drei Overlays mit falschen CSS-Klassen** (`ov-bezahlt-info`, `ov-retour-bar`, `ov-manager-pin`): nutzten `class="overlay"/"overlay-box"/"overlay-header"` statt der im Rest der Datei gültigen `ov`/`ov-box`/`ov-title` — dafür existiert keine CSS-Regel, die sie sichtbar macht. Vermutlich seit ihrem jeweiligen Bau nie funktional benutzbar gewesen (auch die Buttons darin nutzten nicht-existente `.btn`-Klassen). Gefixt inkl. Button-Klassen auf `ov-btn ov-btn-sec/prim/red`.
3. **`BonA4Renderer.php` fragte `kunden`/`kunden_adressen` mit falschen, nicht-verschlüsselten Spaltennamen ab** (`k.vorname` statt `k.vorname_enc` etc.) — Fatal Error bei jedem A4-Druck für einen Bon mit echtem `kunden_id`. Nie aufgefallen, weil A4-Tests bisher nur mit Laufkunde liefen. Fix: über `KundenRepository::findById()`/`findAdressen()` (entschlüsselt), keine rohe SQL mehr.
4. **`auftraege.kassen_bon_id` wurde bei JEDER Kasse-Berührung eines Web-Auftrags gesetzt**, auch wenn der Auftrag schon vorher anderweitig fakturiert war (z.B. PayPal) — verdrängte die echte, bestehende Rechnung. Fix: `kassen_bon_id` nur setzen wenn `!$webAuftragBezahlt` (Auftrag wird durch DIESE Transaktion erstmals fakturiert). Zusätzlich neue Sektion "Zusätzliche Kassenbons zu diesem Auftrag" auf `auftraege/detail.php` (Query über `kassen_bons.web_auftrag_id`), damit ein Gutschriftsbon trotzdem auffindbar bleibt.
5. **Zahlungsverlauf-Retourbetrag war strukturell immer 0**: `$retourBetrag = bruttobetrag - auftragAnteil`, aber `auftragAnteil` wird aus Positionen mit `auftrag_position_id` abgeleitet — Retour/Extra-Zeilen haben die aber bewusst `null` (siehe oben). Fix: `$retourBetrag` jetzt direkt aus den `block==='retour'`-Positionen summiert.

**Zusätzlich (nicht Bug, sondern Print-Verbesserung):** beide Druckvorlagen (`bon_druck.php`, `BonA4Renderer.php`) zeigen jetzt "↩ Rückgabe aus Auftrag A-xxx" statt nur generisch "↩ Rückgabe" (nutzt bereits vorhandenes `$bon['web_auftrag_nr']`).

**RKSV-Nachfrage von Jacky beantwortet:** gemischte Steuersätze bei einer Retoure werden korrekt behandelt — `BfrService::steuerGruppenAusPositionen()` bucketet jede Position einzeln nach ihrem eigenen `steuer_prozent`, negative Retour-Mengen fließen korrekt (negativ) in die jeweils richtige Gruppe. Strukturell unabhängig von der `$steuer`/`$steuerBetrag`-Variablenkollision vom Vormittag (siehe [[project_kassen_verwaltung]]).

**Live getestet (echte Kasse 4, echte BFR-Signatur):** Retoure (2 Positionen, verschiedene Chargen) + gleichzeitiger Neukauf in einem Bon, negative Zähler-Sperre korrekt ausgelöst als der Kassenzähler zu niedrig war, nach Zähler-Auffüllung erneut erfolgreich durchgespielt — Druck zeigt jetzt korrekt Retour-Betrag, Auftragsherkunft und Charge.

**Nicht behoben, nur gefunden (eigenständiges Thema):** `auftrag_dokumente` hat für einen tatsächlich fakturierten Test-Auftrag keine `'rechnung'`-Zeile — das Dokumente-System scheint erstellte Rechnungen nicht zuverlässig zu protokollieren. Nicht Kasse-spezifisch, siehe [[project_dokumente_system]].

**Why:** Erst der echte Hardware-Test mit einer neuen BFR-Kasse UND einem realistischen Testfall (bereits anderweitig fakturierter, versendeter Auftrag) hat diese Lücken aufgedeckt — der ENUM-Bug und die Overlay-CSS-Bugs bestanden vermutlich schon seit dem ursprünglichen Bau, aber der Retour-Pfad wurde seither nie wirklich end-to-end durchgeklickt.
**How to apply:** Bei künftigen Kasse-Änderungen: `block`-Werte gegen die tatsächliche ENUM-Definition prüfen (nicht nur den PHP-Code lesen), und Overlays im Zweifel per Klick verifizieren, nicht nur am Code.

## ✅ Extremtest + Doppel-Gutschrift-Sperre FERTIG (2026-07-08, Abschluss desselben Tages)

Jackys eigener Extremtest (Auftrag anlegen → bezahlt → versendet, zweiter Auftrag → Abholung, beides an der Kasse mit ERP-Artikel + Freitext-Artikel kombiniert, danach alle Tabellen/Lagerstände/Chargen kontrolliert) deckte zwei weitere Ebenen echter Bugs auf.

### Fund 1: `versendet`+`bezahlt` landet nie sichtbar bei `versendet`
`packplatz/warenausgang/abschliessen.php` hat eine Auto-Logik: ist ein Auftrag beim Versand-Abschluss schon bezahlt, springt er SOFORT weiter auf `abgeschlossen`. Der `versendet`-Zwischenzustand ist für den (häufigsten) Praxisfall "vorausbezahlt dann verschickt" praktisch nie beobachtbar. Fix: `abgeschlossen` überall dort ergänzt, wo bisher nur `versendet`/`teilgeliefert` als Retoure-Kandidat galt — `ajax_auftrag_laden.php` (Suchfilter, vorher explizit ausgeschlossen!) und `bon.php` (`istRetoure`-Erkennung).

### Fund 2: Retoure ohne Cross-Check konnte doppelt gutgeschrieben werden — an VIER Stellen geschlossen
Ursprünglich hatte die Kasse-Retoure keinerlei Rückverfolgung zur Original-Position (bewusst, `auftrag_position_id=null` um nicht aus dem Bon rausgefiltert zu werden — siehe oben). Dadurch:
- **Zahlungsverlauf** zeigte nach einer Retoure weiterhin "Offen: X€" (falsche Formel `bruttobetrag − Zahlungen`, ignorierte dass die Retoure den Auftrag effektiv verkleinert)
- **"Gutschrift erstellen"** bot pro Position immer die volle ursprüngliche Menge an — eine bereits über die Kasse retournierte Menge hätte ein zweites Mal gutgeschrieben werden können
- **Vollstornierung** bot immer den vollen ursprünglichen Rechnungsbetrag, ignorierte jede Teilretoure komplett
- **Die Kasse selbst** hätte beim zweiten Laden desselben Auftrags erneut die volle Menge als retournierbar angeboten (kein Schutz gegen wiederholtes Zurücknehmen)

**Lösung — neue Spalte `auftrag_positionen.menge_retourniert`** (Migration 120), bewusst GETRENNT von `menge_geliefert` (das bleibt reine Liefer-Fortschritts-Historie, unverändert — kein Snapshot nötig, da nichts überschrieben wird, nur additiv ergänzt). Retour-Zeilen tragen jetzt zusätzlich `retour_von_position_id` (nur zur Rückverfolgung, umgeht nicht den bestehenden Filter). Damit an allen vier Stellen geschlossen:
1. `bon_speichern.php`: `menge_retourniert` wird bei jeder Kasse-Retoure hochgezählt (Positions-Abgleich-Schleife)
2. `bon_speichern.php`: neuer Vorabcheck (vor `erstelleBon()`, analog zur Umsatzzähler-Sperre) lehnt eine Retoure hart ab, wenn die angefragte Menge das noch offene Restkontingent übersteigt — **serverseitig**, nicht nur die Kasse-UI (die client-seitige Stepper-Grenze allein wäre per direktem POST umgehbar gewesen)
3. `gutschrift_erstellen.php`/`gutschrift_speichern.php`: Teilgutschrift-Obergrenze auf `menge − menge_retourniert`, inkl. serverseitiger Deckelung (gleiches Muster: Client-`max` allein reicht nicht)
4. `DokumentService::erstelleGutschrift()`: Vollstorno kreditiert jetzt nur noch die tatsächlich offene Menge je Position (läuft intern über dieselbe Positions-Berechnung wie die Teilgutschrift), Label zeigt bei Differenz einen Hinweis warum der Betrag kleiner ist als die Original-Rechnung
5. `auftraege/detail.php`: `$offenBetrag`-Formel korrigiert (`bruttobetrag − retourGesamtbetrag − Zahlungen`), Positionstabelle zeigt "↩ X retourniert" direkt unter der betroffenen Zeile (auf den ersten Blick sichtbar, nicht erst beim Gutschrift-Dialog), Zahlungsverlauf-Vorzeichen-Anzeige gefixt (zeigte `+-2,50` bei Erstattungen)

**Nebenbefund, nicht behoben:** Freitext-Artikel (kein `artikel_id`) fehlen im K1-Auftrag (`auftraege`-Spiegel-Datensatz für Kasse-Extras) — die K1-Split-Filterlogik verlangt `artikel_id`. Bon selbst ist korrekt, nur die interne Auftrags-Spiegelung unvollständig (aktuell ohne sichtbare Auswirkung, da K1-Aufträge praktisch nie einzeln angeschaut werden).

**Live end-to-end getestet:** zwei komplette, unabhängige Durchläufe (Abholung+Extras, Retoure+Extras), danach alle Tabellen/Lagerbewegungen/Chargen von Jacky UND mir gegengeprüft. Zweite Runde des gleichen Auftrags an der Kasse zeigt korrekt nur noch die verbleibende retournierbare Menge.

**Why:** Jackys eigener Instinkt beim Testen ("das ist ein Risiko für doppelte Gutschriften, echtes Geld") — zu Recht nicht auf später verschoben, obwohl spät am selben Tag gefunden.
**How to apply:** Jede künftige Änderung an Retour/Gutschrift-Logik muss `menge_retourniert` respektieren, nicht nur `menge`/`menge_geliefert`. Client-seitige Grenzen (HTML `max`, JS-Stepper) sind NIE ausreichend — serverseitige Prüfung ist Pflicht, dieses Muster zieht sich jetzt durch Umsatzzähler-Sperre, Retour-Mengen-Sperre und Gutschrift-Mengen-Sperre gleichermaßen.

## ✅ Logo + QR-Code-Größe auf Kassen-Belegen (2026-07-08, ganz zum Schluss)

Zwei kleine, letzte Funde desselben Tages:
- **QR-Code auf A4** war unscharf/zu klein für Handykameras (25mm bei nur 100px Quellauflösung) — jetzt 30mm bei 300px. Der 80mm-Bon war schon vorher akzeptabel (120px/CSS-Skalierung), unverändert.
- **Firmenlogo fehlte komplett auf beiden Vorlagen** (`BonA4Renderer.php`, `bon_druck.php`) — hing nie an einem echten Rendering-Fehler, sondern daran, dass beide einen nie befüllten Einstellungs-Schlüssel `firmen_logo` (system_einstellungen) abfragten, der mit gar nichts verknüpft war. Das echte, längst funktionierende Logo liegt in `shops.logo_pfad` (Shop 1 = Ladengeschäft, MEALANA KG) — derselbe Mechanismus, den `DokumentService::ladeDaten()` für die normalen Web-Auftrags-Dokumente schon nutzt. Beide Dateien jetzt darauf umgestellt.
- **Nebenfehler beim ersten Fix:** beim Kopieren des Musters von `BonA4Renderer.php` (liegt in `erp/src/modules/kasse/`) nach `bon_druck.php` (liegt in `erp/public/kasse/`, eine Verzeichnisebene höher) den relativen Pfad nicht angepasst — `../../../public/` zeigte auf ein nicht-existentes `mealana/public/` statt `mealana/erp/public/`. `file_exists()`-Check schlug dadurch still fehl (kein Fehler, einfach kein Bild). Immer bei Pfad-Mustern die tatsächliche Verzeichnistiefe der Zieldatei neu prüfen, nicht blind übertragen.

**Damit war "Firmenlogo fehlt" tatsächlich noch nie behoben** — die Notiz vom 5.7. war korrekt als offen markiert, wurde nur vermutlich mit der unabhängigen "A4-Rechnungsdruck komplett"-Session vom 4.7. verwechselt (die betraf nur die Zusammenlegung Browser-/Mail-PDF-Rendering, nicht das Logo).

## Freitext-Retour für JTL-Altbestand (vorgemerkt 2026-07-08)

Während der JTL→ERP-Übergangsphase (siehe [[project_jtl_import]]) kommen häufig Rückgaben von Artikeln, die NIE als Auftrag im neuen ERP existieren (Kauf lief noch komplett über JTL). Für diese braucht es einen eigenen "Freitext-Retour"-Weg an der Kasse: Artikel wählen, Menge als Rückgabe (z.B. -1, -2) OHNE verknüpften Auftrag, Barauszahlung. Bewusst getrennt vom Auftrag-gebundenen Retour-Flow oben (Nummer 1) — dort bleibt die Mengen-Basis der Auftrag, hier gibt's gar keinen. Design noch offen: eigener Button/Modus in der Kasse, oder Teil des allgemeinen "Divers-Artikel"-Mechanismus mit negativer Menge?

## Mehrere Aufträge gleichzeitig in einem Bon (vorgemerkt 2026-07-08, wartet auf Barbara)

Beim Extremtest-Vorschlag (Retoure aus Auftrag 1 + Abholung von Auftrag 2 + Extras, alles ein Bon) aufgefallen: die aktuelle Architektur kann nur **einen** Web-Auftrag gleichzeitig laden (`geladenerAuftragId` & Co. sind Einzelwerte in `bon.php`, `$webAuftragId` einzeln in `bon_speichern.php`, `kassen_bons.web_auftrag_id` ist eine 1:1-Spalte). Mehrere unabhängige Aufträge in einem Bon zu kombinieren würde bedeuten:
- Client: Liste statt Einzelwert für geladene Aufträge, jede Warenkorb-/Retoure-Zeile braucht eine Zuordnung "zu welchem Auftrag", mehrere 📦-Gruppen/Retoure-Abschnitte in der UI
- Server: der komplette "Web-Auftrag abschließen"-Block (K1-Split, Zahlung buchen, Status setzen, Rechnung-Sperre) müsste zu einer Schleife über mehrere Aufträge werden
- Datenmodell: `kassen_bons.web_auftrag_id` bräuchte eine echte n:m-Verknüpfungstabelle statt der 1:1-Spalte
- Neue Geschäftsregel nötig: gleicher Kunde erzwingen, oder verschiedene Kunden pro Bon erlauben?

**Jackys Entscheidung:** Das widerspricht zwar dem ursprünglichen "alles in einem Bon"-Grundsatz (bisher aber nur auf EINEN Auftrag + Extras/Retour/Gutschein bezogen, nie auf mehrere unabhängige Aufträge) — bewusst NICHT sofort einplanen. Erst mit Barbara abklären ob das im echten Tagesgeschäft überhaupt gebraucht wird, bevor mehrtägiger Aufwand investiert wird. Siehe [[feedback_scope_ohne_bedarf]].

**Stattdessen für den heutigen Abschlusstest gewählt:** zwei separate Durchläufe — (1) Retoure aus Auftrag 1 + Freitext-Artikel + ERP-Artikel in einem Bon, (2) Abholung von Auftrag 2 + Freitext-Artikel + ERP-Artikel in einem zweiten Bon. Deckt den kompletten heutigen Funktionsumfang ab, ohne die Mehr-Auftrag-Frage vorwegzunehmen.

## Packplatz-Benachrichtigung bei Kassen-Retour (vorgemerkt 2026-07-08)

Aktuell (`block='retour'` → `kein_lagerabzug=true`) bucht eine Kassen-Retour NIE automatisch Lagerbestand zurück — Kommentar im Code verweist auf eine separate Packplatz-Retoure mit Sichtprüfung. Für den Fall "Kunde bringt eine `versendet`-Bestellung persönlich zurück" (siehe Redesign oben) steht die Ware aber JETZT physisch am Tresen. Jackys Entscheidung (2026-07-08): Kasse bucht weiterhin NUR den finanziellen Ausgleich, aber Packplatz muss ein Signal bekommen ("hier liegt physische Retour-Ware, noch nicht eingebucht") — z.B. eine offene Retoure-Warteschlange, ähnlich der bestehenden `packplatz/retoure/`-Funktion, nur von der Kasse ausgelöst statt manuell angelegt. Verhindert, dass zurückgenommene Ware unkontrolliert ohne Bestandsbuchung ins Regal wandert. Design noch offen.

**Why (alle drei Punkte):** Erst der echte Hardware-Test mit einer neuen, ersten BFR-Kasse hat diese Lücken sichtbar gemacht — bisher lief nie ein Kassen-Datensatz mit aktivem BFR durch diese Codepfade.
**How to apply:** Vor dem Weiterbauen: alle drei Punkte zusammen als eine Design-Session behandeln (hängen zusammen: Auftrag-Lade-Flow bestimmt WANN Retour passiert, Freitext-Retour deckt den Fall OHNE Auftrag, Packplatz-Benachrichtigung ist die fehlende Lagerbuchungs-Konsequenz in beiden Fällen).

## ✅ Freitext-Retour + Packplatz-Rücklagerung FERTIG (2026-07-09) — Show-Stopper für Live behoben

Jacky stufte beide als Pflicht vor Live-Gang ein ("mehrere Aufträge" dagegen auf nice-to-have zurückgestuft). Wichtiger Fund beim Code-Lesen vor dem Design: der `abholbereit`-Teilrücknahme-Fall (Kunde nimmt beim Abholen weniger mit) bucht in `bon_speichern.php` (Zeile ~420-434) schon LÄNGST automatisch zurück — die Lücke betraf ausschließlich den `versendet`/`teilgeliefert`/`abgeschlossen`-Fall (Ware hat das Haus schon verlassen, kommt jetzt physisch zurück) und Rückgaben ganz ohne Auftrag.

**Migration 121** — neue Tabelle `packplatz_ruecklagerungen`: eine Zeile pro zurückgenommener Position (Bon-Ref, optional Auftrag-Ref, Artikel, Menge, Charge, Kasse), `status` offen/erledigt, `erledigt_zustand` (neu/gebraucht/beschädigt/defekt — Zustandserfassung passiert bei Packplatz beim Einbuchen, NICHT an der Kasse, konsistent mit dem bestehenden `packplatz/retoure/`-Workflow und auf Jackys Hinweis hin: "könnte ja sein dass die Ware nicht mehr als neu durchgeht").

**`src/modules/packplatz/RuecklagerungRepository.php`** (neu): insert/findOffene/markiereErledigt/zaehleOffene.

**`bon_speichern.php`** — zwei neue Hooks, beide nur wenn `$result['erfolg']`:
1. Ohne `$webAuftragId` (Freitext-Retour): jede `block==='retour'`-Position ohne `retour_von_position_id` → Queue-Eintrag ohne Auftrag-Ref.
2. Mit `$webAuftragId` UND `$webAuftragStatus !== 'abholbereit'` (echte physische Rückgabe einer versendeten Bestellung): jede `block==='retour'`-Position MIT `retour_von_position_id` → Queue-Eintrag mit Auftrag-Ref. Der abholbereit-Fall wird bewusst ausgenommen, weil der schon separat automatisch zurückbucht.

**Neue Packplatz-Seite `packplatz/ruecklagerungen.php`** + `ruecklagerungen_speichern.php`: eigene schlanke Liste statt Integration in `packplatz/retoure/` (Entscheidung mit Jacky abgestimmt) — dort ist finanziell nichts mehr zu tun (Erstattung lief schon an der Kasse), nur noch Lager-Dropdown + Zustand-Dropdown + `LagerService::wareneingang()` (exakt dasselbe Repository-Pattern wie die bestehende manuelle Retoure, inkl. Zustand in der Bewegungs-Notiz). Badge mit Anzahl offener Einträge auf der Packplatz-Startseite.

**`bon.php`** — Freitext-Retour-UX (neuer Menüpunkt "↩ Freitext-Retour" im ⚙-Menü, eigener Zwei-Schritt-Dialog: Artikelsuche → Menge+Preis → Übernehmen):
- Freitext-Retour-Zeilen landen bewusst im normalen `warenkorb` (nicht im separaten `retourePositionen`/`zusatzPositionen`-Mechanismus, der exklusiv für den Auftrag-gebundenen Fall gebaut ist und `zusatzPositionen` bei jedem Aufruf komplett neu berechnet — ein manuell hinzugefügtes Freitext-Element wäre dort sofort wieder verschwunden). `warenkorb`-Summen (`getGesamt()`, Footer, Bezahlen-Button-Freischaltung) funktionieren bereits generisch mit negativen Mengen, dadurch kein Umbau an fünf verschiedenen Stellen nötig, nur an dreien: `renderBon()` (↩-Badge + rote Einfärbung analog zum bestehenden 📦-Badge-Muster), `zeileMinus()`/`zeilePlus()` (Sonderfall für `block==='retour' && !vonAuftrag`, weil Vorzeichen bei Retour-Mengen umgekehrt zur normalen Kauf-Logik ist).
- Schutz eingebaut: Vater-Artikel (mit Varianten) werden in der Freitext-Retour-Suche abgelehnt mit Hinweis, die konkrete Variante zu suchen — sonst würde versehentlich gegen die Vater-ID gebucht.
- Zahlungsabwicklung (Bar/Karte/Gutschein) läuft über den bereits bestehenden generischen `ov-bezahlen`-Pfad (nicht den speziellen `ov-retour-bar`, der nur für den Auftrag-gebundenen Fall gebaut ist) — rein rechnerisch bereits negativ-total-fähig (`abschliessenBar()` verlangt nur `geg > 0`, sonst wird ohne gegeben/rückgeld gebucht), aber die Labels "Gegeben"/"Rückgeld" sind für einen reinen Auszahlungs-Bon semantisch etwas unglücklich formuliert — funktional aber korrekt.

**CLI-Test 2026-07-09** (kein Browser-Tool in dieser Session verfügbar, daher PHP-CLI gegen echte Dev-DB, komplett in einer Transaktion mit Rollback): `RuecklagerungRepository` (Insert freitext+auftrag-gebunden, findOffene inkl. JOIN, markiereErledigt) + kompletter Einbuchen-Pfad über `LagerService::wareneingang()` — alles korrekt, keine Testdaten zurückgeblieben. Alle 5 neuen/geänderten Seiten antworten unauthentifiziert sauber mit 302 (kein Fatal Error).

**Dabei echten, vorbestehenden Bug gefunden+gefixt:** `LagerService::wareneingang()` reichte `benutzer_id` nie an `Logger::log()` durch (Zeile ~168) — unauffällig, weil bisher jeder der 10 Aufrufer innerhalb einer echten Login-Session lief (Logger fällt sonst auf `$_SESSION` zurück). Der CLI-Test war der erste Aufruf ganz ohne Session und crashte prompt am NOT-NULL-Constraint von `aktivitaeten.benutzer_id`. Fix: `$data['benutzer_id'] ?? null` jetzt explizit als 5. Parameter übergeben — bestehendes Web-Verhalten unverändert, aber jetzt auch aus Cron/CLI-Kontext sicher (gleiche Fehlerklasse wie der alte `cron/mahnwesen.php`-Bug).

**Weiterhin nicht getestet** (braucht echten Browser, den Jacky selbst durchklicken muss): der komplette Freitext-Retour-UI-Flow (Dialog, ↩-Badge, +/− auf der Retour-Zeile), Bar-Zahlung mit negativem Gesamtbetrag, RKSV-Negativ-Zähler-Sperre bei reiner Freitext-Retour ohne Zusatzverkauf, Regressionscheck normaler Verkauf (da renderBon/zeileMinus/zeilePlus angefasst wurden).

**Why:** Ohne diese beiden Stücke bucht eine Kassen-Retoure Ware, die physisch am Tresen liegt, NIE zurück in den Lagerbestand — das hätte auf Dauer zu einer stillen Bestandsdrift geführt (Kasse sagt "verkauft/retourniert", Lager weiß nichts davon).
**How to apply:** Nächster Praxis-Test: einmal beide Wege durchklicken (Freitext-Retour ohne Auftrag UND eine echte `versendet`-Retoure mit Auftrag), danach in `packplatz/ruecklagerungen.php` einbuchen und Lagerbestand/Bewegungslog gegenprüfen.
