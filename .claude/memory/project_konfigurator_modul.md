---
name: project-konfigurator-modul
description: "Geplantes Konfigurator-Modul (Schilder/Buttons/Anhänger, später ggf. Anleitungspakete mit Stückliste) — Optionen mit Aufpreis statt Kind-Artikel-Explosion, eigenständiges lizenzierbares Modul"
metadata: 
  node_type: memory
  type: project
  originSessionId: bf21b7a8-0044-4fd4-869f-1ae811833787
  modified: 2026-08-29T11:51:38.007Z
---

## 🟢 BEHOBEN 2026-08-29: Erste echte Testbestellung verlor 5 von 7 Konfigurationswerten

**Auslöser:** Jacky bestellte testweise ein konfiguriertes Schild (Durchmesser/Farbschema/Grundfarbe/2.Farbe/Glitzereffekt/Hintergrund/Holzauswahl) über den Shop. Im Auftragsdetail waren nur 2 der 7 gewählten Werte sichtbar ("Farbschema: zweifarbig · Hintergrund: Holz").

**Root Cause (mehrstufig, per Playwright/curl direkt gegen WC-API + DB diagnostiziert, kein Server-Log nötig):**
1. Der Shop (Snippet 34170 "Options-Picker", entgegen einer veralteten Notiz **doch aktiv**) sendete alle 7 wert_ids korrekt — sie stimmten exakt mit der Preis-Matrix übernommen beim LETZTEN Produkt-Sync überein.
2. Die ERP-eigene `varianten_achse_werte`-Tabelle hatte sich seither geändert: `VariantenService::speichereAchsenUndWerte()` löscht/erneuert beim Speichern nicht-"in_use"-Werte (auch bei reiner Text-Korrektur, z.B. "18 cm"→"18cm") mit NEUEN Auto-Increment-IDs. `findWertIdsInUse()` schützt zwar bereits real bestellte Werte (`position_konfiguration`-Join), aber VOR der ersten Bestellung ist noch nichts geschützt.
3. Diese Werte-Änderung löste bisher NIE einen erneuten Produkt-Sync aus — die im Shop gecachte Preis-Matrix (`_mealana_konfigurator`-Meta) blieb dadurch für 5 von 7 Achsen (die nicht zufällig unverändert gebliebenen) still veraltet, bis ein Kunde genau diese IDs bestellte und `KonfiguratorService::speichereAuswahl()`→`findWerteByIds()` sie nicht mehr fand.

**Fix (vierteilig):**
1. **Klartext-Fallback, IMMER unabhängig von ID-Auflösung** — Migration 172: `auftrag_positionen.konfig_freitext` (TEXT). `ShopBestellungSyncService::leseKlartextAusMetaData()` friert JEDE nicht-Underscore-Meta (Snippet 34170 schreibt pro gewählter Achse einen Klartext-Key fürs Kunden-/Admin-Display) als "Achse: Wert"-Zeilen ein — komplett unabhängig davon, ob die technischen wert_ids später noch existieren. `position_konfiguration.wert_id` blieb bewusst NOT NULL (von Preis-Nachrechnung/Kasse abhängig) -- der Freitext lebt stattdessen direkt an der Position.
2. **Root-Cause-Fix:** `VariantenService::speichereAchsenUndWerte()` markiert am Ende, falls `artikel.ist_konfigurierbar=1`, den Artikel per `ShopSyncRepository::markiereFuerErneutenSync()` für erneuten Sync -- jede künftige Werte-Änderung hält die Shop-Preis-Matrix automatisch aktuell.
3. **Testbestellung (Auftrag A-2026-00039) nachgetragen** (per PDO, nicht Shell -- CP850-Mojibake-Falle bei "ü" umgangen, siehe [[project_infrastruktur]]) + betroffener Artikel #27473 einmalig neu synct, Matrix jetzt wieder deckungsgleich mit der DB.
4. **UI/Dokumente erweitert:** `auftraege/detail.php`, Pickliste (`lager/pickliste_erstellen.php` + `pickliste/standard.html.twig`) und Rechnung/Auftragsbestätigung/Lieferschein (gemeinsames `_positionen.html.twig`-Partial über `DokumentService::ladePositionen()`) zeigen jetzt alle den Konfigurations-Klartext (Freitext bevorzugt, strukturierte `position_konfiguration` als Fallback für Kasse/Alt-Bestellungen). Per PDF-Testgenerierung (CLI, `DokumentService`/`PdfGenerator` direkt aufgerufen) verifiziert -- dabei auch bemerkt: das 🔧-Emoji rendert in Dompdf/DejaVu Sans als kaputte Glyphen, durch Klartext "Konfiguration: " ersetzt. **Nebenbefund (nicht behoben, nicht Teil dieser Session):** dieselbe Emoji-Einschränkung betrifft offenbar auch die BEREITS BESTEHENDEN 📋/🏪/📦-Icons im Pickliste-Header/Auftragszeile -- rendern im PDF ebenfalls kaputt, war schon vor dieser Session so.

**Noch offen:** Separater "Konfigurationszettel" als eigenes Dokument (Jackys "wenn nicht zu komplex"-Idee) bewusst NICHT gebaut -- der jetzt überall vorhandene Freitext deckt den eigentlichen Bedarf (Fertigungs-Info verfügbar, ohne in der Bestellbestätigung suchen zu müssen) bereits ab. Bei Bedarf könnte er analog zum bestehenden `abholzettel`-Dokumenttyp ergänzt werden. Migration + Code committed, `git push` steht noch aus (siehe [[project_shop_sync]]).

## Auslöser (2026-08-27)
Jacky will Schilder (viele Layouts als eigene Artikel) mit konfigurierbaren Optionen anbieten: Durchmesser (5), Farbe 1 (10+2 Glitzer), optional Farbe 2 (8+2 Glitzer), Hintergrund (Moosgummi 8 / Filz 5 / Holz 3 / Furnier 3), alle mit Aufpreisen. Vollkombinatorik wäre ~12.500 Kind-Artikel PRO Layout — bei "unzähligen" Layouts technisch nicht sinnvoll über den bestehenden VarKombi-Generator abbildbar.

**Fertigung: Mischform** — ein paar Standard-Kombis liegen fertig auf Lager, der Rest wird individuell aus Rohmaterial (Moosgummi-/Filz-/Holz-/Furnier-Platten in Lagerfarben) angefertigt.

## Architektur-Entscheidung
- **Standard-Kombis (auf Lager):** weiterhin normale Kind-Artikel über den bestehenden VarKombi-Generator — aber gezielt nur die tatsächlich vorproduzierten Kombinationen anlegen, nicht die volle Kombinatorik.
- **Individuell gefertigte Kombis:** neues Konzept "Konfigurationsartikel" — Layout = ein Artikel mit Optionsgruppen (Durchmesser/Farbe1/Farbe2/Hintergrund), Preis = Basispreis + Summe der gewählten Aufpreise, berechnet zur Bestellzeit. **Keine automatische Kind-Artikel-Generierung.** Nutzt konzeptionell dieselbe Aufpreis-Idee wie `varianten_achse_werte.aufpreis` (Migration 074), aber ohne kartesische Kombinationsexplosion.
- **Rohmaterial** (Moosgummi/Filz/Holz/Furnier je Farbe) als normale Lagerartikel führen, damit der Materialbestand stimmt. Verbrauch beim individuellen Fertigen bleibt vorerst manuell — siehe Stückliste unten.

## Korrektur einer alten Entscheidung
`db_design_entscheidungen.md` (Session 2026-06-11) hatte notiert: *"Konfigurator: eigenes Modul für später, NICHT Teil des Varianten-Systems. freitext/pflichtfreitext in Variationen deckt Schilder-Usecase ab, kein Konfigurator nötig."* — Das war zu einfach gedacht, bevor die tatsächliche Komplexität (mehrfache Optionsgruppen mit je eigenen Aufpreisen, ~12.500 Kombis/Layout) bekannt war. Ein echter Konfigurator ist jetzt doch nötig.

## Geplant als eigenständig lizenzierbares Modul
Jacky will das Modul von Anfang an sauber abgegrenzt bauen (eigene Tabellen, eigener Namespace, keine harte Verflechtung mit dem Achsen/Vater-Kind-System), damit es später bei der Lizenzierung/Weitergabe an andere Installationen einen eigenen Eintrag in der bereits geplanten `modul_lizenzen`-Tabelle bekommt (siehe [[db_design_entscheidungen]], Abschnitt "Lizenzierung & Deployment-Modell", `modul_code` PRIMARY KEY).

## Zukünftige Wiederverwendung: Anleitungspakete + Stückliste
Jacky will den Konfigurator später auch für Anleitungspakete nutzen (unterschiedliche Größen/Farben → unterschiedlicher Materialbedarf). Dafür bräuchte es eine Stückliste/BOM-Logik. `db_design_entscheidungen.md` hat unter "Priorität 4: Bei Bedarf" bereits eine `stueckliste`-Tabellenskizze (für Artikeltyp SET) — das Konzept lässt sich vermutlich wiederverwenden.

**Empfehlung (Stand 2026-08-27, noch nicht final entschieden):** Konfigurator-Engine jetzt generisch genug bauen, dass sie später erweiterbar ist — aber die Stückliste selbst nicht spekulativ mitbauen, erst wenn Anleitungspakete real ansteht. Siehe [[feedback_scope_ohne_bedarf]].

## Stückliste bewusst zurückgestellt (2026-08-27)
Jacky: Stückliste ist nicht "nur Anzahl" — Chargen müssen mitgedacht werden (spätestens am Packplatz, wo Chargen-Tracking zentral ist, siehe [[project_chargen_konzept]]). Das braucht eigene, gute Planung. Wird explizit erst angegangen, wenn die Anleitungspakete anstehen oder zwischendurch Luft ist — nicht jetzt mitbauen. Bestätigt [[feedback_scope_ohne_bedarf]].

## Konfigurator-Frontend: Selbstbau bestätigt
Jacky hat sich für die Selbstbau-Variante (kein WooCommerce-Add-on-Plugin) entschieden — kein Abo, passt besser zum Weitergabe-Modell.

## Referenz-Check (2026-08-27) — siehe [[feedback_modul_vorgehen]]
- **JTL-Wawi**: eigener kostenpflichtiger "Konfigurator" (~239€ Erweiterung), zentral in Wawi gepflegt + zu Shop synced, schrittweise Gruppenauswahl (wie PC-Zusammenbau), getrennt von normalen Variationsartikeln.
- **Shopware 6**: unterscheidet explizit **Varianten** (starre SKU-Kombinationen, eigener Bestand) vs. **"Custom Products"** (kostenpflichtige Erweiterung, modulare dynamisch berechnete Optionen, kein SKU pro Kombi) — bestätigt unsere Trennung Standard-Kombis vs. Konfigurationsartikel.
- **Odoo** (Open Source, am besten dokumentiert): zwei Ideen direkt übertragbar:
  1. Varianten-Erzeugung "Instantly" (alle Kombis sofort, unser bestehender VarKombi-Generator) vs. "Dynamically" (Variante erst bei tatsächlicher Bestellung angelegt) — genau unsere Mischform.
  2. **Eine Stückliste pro Produkt-Vorlage** mit Zeilen, die nur für bestimmte Attributwerte gelten (nicht eine Stückliste pro Kombination).
- **SAP LO-VC** (Enterprise-Referenz für genau dieses Problem): **Super-BOM**-Prinzip — eine Stückliste deckt alle Varianten ab, **Dependencies** (Regelwerk) wählen zur Konfigurationszeit die passenden Zeilen aus UND verhindern ungültige Kombinationen.

**Architektur-Konsequenz für später (Stückliste, noch nicht gebaut):** Das Super-BOM-Prinzip lässt sich auf unser bestehendes `artikel_achsen.bedingungs_achse_id`/`bedingungs_wert_id`-Muster (bedingte Achsenanzeige) abbilden — eine künftige Stücklisten-Zeile bekommt optional `achse_id`+`wert_id` und gilt nur bei dieser Auswahl. Damit reicht **eine** Stückliste pro Layout statt einer pro Kombination; "Konfiguration → Stückliste" wird beim Bestellen anhand der gewählten Werte aufgelöst (BOM-Explosion). Kein neues Bedingungs-Konzept nötig, nur Erweiterung des bestehenden Musters.

**Architektur-Konsequenz für den Konfigurator jetzt:** Bestehende Achsen/Werte/Aufpreis-Tabellen bleiben Basis (deckt sich mit Odoos "Value Price Extra", schon gebaut). Neu: Flag am Vater-Artikel "Kombinationen sofort generieren (Standard-Kombis, bestehender Generator) vs. dynamisch/nicht generieren (Konfigurationsartikel, Preis zur Bestellzeit berechnet)" — analog Odoos Instantly/Dynamically.

## ✅ BEHOBEN 2026-08-29: Rundnadeln — WooCommerce-Variations-Schwellwert
Bei manchen Rundnadel-Vätern bis zu 140 echte Kind-Kombinationen (DMC-Garnfarben sogar bis 499). WooCommerce filtert Dropdowns nur bei ≤30 Variationen automatisch dynamisch (`data-product_variations`-JSON) — darüber statischer Fallback (jede Auswahl per AJAX-Nachladung statt sofort aus dem Seiten-HTML), das erklärte das beobachtete JTL/Shop-Verhalten.

**Fix:** Snippet 34174 "WooCommerce Variations-Schwellwert anheben (30 → 1000)" -- `add_filter('woocommerce_ajax_variation_threshold', fn() => 1000)`. Live aktiv gesetzt, per direktem HTML-Diff verifiziert (DOMDocument-Parse, nicht nur Textsuche -- eine erste manuelle Byte-Offset-Prüfung lieferte falsche Werte): DMC Mouline Special Sticktwist bettet danach 304 Variationen (alle mit Bestand) inline ein statt vorher `data-product_variations="false"`.

**Nebenbefund beim Testen (kein Bug, nur Beobachtung):** Symfonie Rundstricknadel + HiyaHiya Sharp Rundnadel zeigten nach dem Fix zunächst nur 1 eingebettete Variation -- lag NICHT am Threshold, sondern daran, dass laut WC-API 140 von 141 Kombinationen aktuell `stock_status=outofstock` sind (nur 1 Länge/Stärke-Kombination hat Bestand). WooCommerce blendet Variationen ohne Bestand aus der Auswahl aus -- korrektes Verhalten. Nicht weiter untersucht, ob das eine echte Bestandslücke ist oder erwartet (viele Rundnadel-Kombis werden vermutlich nicht auf Lager gehalten) -- bei Bedarf mit Jacky abklären, nicht von selbst als Bug behandeln.

**Kopie im Repo:** `shop/wp-snippets/variations_threshold_anheben.php`.

## Baustufe 1: DB-Grundlage ✅ FERTIG 2026-08-27
Migrationen 169+170 live eingespielt (gemeinsam mit Jacky Schritt für Schritt geschrieben, Trainer-Ansatz — [[feedback_trainer]]):
- `artikel.ist_konfigurierbar TINYINT(1)` — Flag am Vater/Layout
- `varianten_achse_werte.rohmaterial_artikel_id` — optionale FK auf Rohmaterial-Lagerartikel (nur Bestands-Ampel, keine Mengen — das bleibt der späteren Stückliste vorbehalten)
- `position_konfiguration` (referenz_tabelle+referenz_id polymorph wie bei `reservierungen`, + achse_id/wert_id) — strukturierte Auswahl pro Kassenbon-/Auftragsposition

**Wichtige Korrektur unterwegs:** Der ursprünglich angenommene `position_typ`-Mechanismus aus der alten 2026-06-12-Planung existiert in der echten DB gar nicht — `auftrag_positionen.artikel_id` ist NOT NULL (kein Freitext-Konzept dort), Kasse nutzt für Freitext-Zeilen stattdessen `artikel_id=NULL` direkt bzw. beim Spiegeln in den Auftrag einen Platzhalter-Artikel (99-9999 "Diverses"). Für Konfigurationsartikel ist das ohnehin einfacher: `artikel_id` zeigt direkt auf den echten Layout-Vater-Artikel (kein Platzhalter nötig), `bezeichnung` trägt die automatisch generierte Auswahlbeschreibung. Kein Schema-Eingriff an den Positions-Tabellen nötig.

## Baustufe 2: Konfigurierbar-Toggle im Artikel-Formular ✅ FERTIG 2026-08-28
Checkbox "Konfigurierbar" im Varianten-Tab von `detail.php` (eigene Card oberhalb der Achsen-Card), sofort per AJAX gespeichert — analog zum bestehenden Kanal-Toggle-Muster:
- `ArtikelRepository::setKonfigurierbar()` (UPDATE, kein INSERT — Jacky hatte anfangs `INSERT ... WHERE` geschrieben, syntaktisch ungültig, gemeinsam korrigiert), `findById()` liefert `ist_konfigurierbar` jetzt mit (fehlte vorher in der expliziten Spaltenliste)
- Neuer Endpunkt `public/artikel/konfigurator_ajax.php` (Jacky hat den eigenständig nach dem Vorbild von `kanal_ajax.php` geschrieben, sehr sauber — nur zwei kleine Korrekturen: Rückgabewert sollte den neuen Zustand liefern statt nochmal die Artikel-ID, Fehlermeldung im `default`-Zweig war irreführend)
- `artikel_detail.js`: `konfiguratorToggle()`, setzt Checkbox bei Fehler zurück
- Von Claude direkt umgesetzt (Jacky war müde/nicht fit), nicht im vollen Trainer-Schritt-für-Schritt-Modus wie Baustufe 1

**Nächster Schritt:** Optionsgruppen-Zuweisung fürs Konfigurator-Frontend + Preisberechnung + Befüllung von `position_konfiguration` beim Bestellen (Kasse + Auftrag/Shop), danach Rohmaterial-Bestandsampel im Shop-Sync.

## Bedingte Achsenanzeige ("Farbe 2 nur wenn Farbschema=Zweifärbig") ✅ FERTIG 2026-08-28
Beim Besprechen der Optionsgruppen fiel auf: `artikel_achsen.bedingungs_achse_id`/`bedingungs_wert_id` existierte zwar seit Migration 024, hatte aber NIE ein UI — `achsen_zuweisen_ajax.php`/`achsen_speichern.php` überschrieben `artikel_achsen` bei jedem Speichern komplett neu, ohne diese Felder je zu setzen. Jetzt nachgebaut, direkt (nicht im vollen Trainer-Schritt-für-Schritt-Modus, Jacky wollte es zügig):
- `VariantenRepository::updateAchseBedingung()` (UPDATE), `VariantenService::updateAchseBedingung()` validiert serverseitig (Bedingungs-Achse muss selbst zugewiesen sein, nicht sich selbst referenzieren, Wert muss wirklich zur Bedingungs-Achse gehören — sonst wird verworfen statt kaputte FK-Referenz zu speichern)
- `achsen_speichern.php`: Bedingung wird NACH dem Werte-Speichern validiert/gespeichert (damit auch gerade neu angelegte Werte als gültiges Ziel zählen)
- `achsen_zuweisen.php`/`achsen_zuweisen.js`: pro Achse eine Zeile "Nur anzeigen wenn [Achse]=[Wert]" — erstes Dropdown listet dynamisch die anderen gerade angehakten Achsen (analog zum bestehenden "in andere Achse verschieben"-Muster), zweites befüllt sich aus `WERTE_PRO_ACHSE`-JSON. Auf Jackys Wunsch zusätzlich ein Klartext-Hinweis daneben ("→ wird im Shop nur angezeigt, wenn „X" = „Y" gewählt ist") — die Achse selbst muss im ERP-Editor NICHT dynamisch versteckt werden, nur die Info reicht.
- **Wichtig:** Das speichert nur die Einstellung. Nirgends im Code (VarKombi-Generator, Shop-Sync, Kasse) wird `bedingungs_achse_id` aktuell gelesen — es gibt noch keine Stelle, die dadurch tatsächlich etwas versteckt. Reine Dateneingabe-Vorbereitung für den späteren Konfigurator/Shop-Sync.
- Getestet: Backend per isoliertem CLI-Skript (gültige/ungültige Kombinationen), UI per neu installiertem Playwright (siehe [[reference_browser_testing_tools]]) — dabei einen echten Bug gefunden+gefixt: Hinweistext blieb beim Reset auf "keine Bedingung" stehen (Early-Return übersprang den Hinweis-Reset).
- Nebenfund beim Testen: Jacky hat in der DB schon eine Achse "Farbschema" mit Unterachsen "einfarbig"/"zweifarbig" angelegt — eigener Vorbau für den Schilder-Testfall.

### Nachtrag 2026-08-28 (Abend): zwei echte Persistenz-Bugs beim Live-Einsatz gefunden+gefixt
Jacky hat die Bedingung selbst über die UI gesetzt — verschwand nach Reload wieder. **Root Cause:** Freie (nicht in Kombination/Konfigurator-Bestellung verwendete) Werte werden bei JEDEM Speichern der Achsen-Seite komplett gelöscht und mit neuer ID neu angelegt (`VariantenService::speichereAchsenUndWerte()`), auch wenn sich am Text nichts ändert. Die Bedingung speicherte aber die alte Wert-ID — durch denselben Speichervorgang bereits ungültig, noch bevor sie geschrieben wurde. Kein Anzeigebug, echter Datenverlust.
- **Fix 1:** Bedingung wird jetzt über den Wert-**Text** aufgelöst statt über die ID (Text bleibt stabil). `achsen_zuweisen.php`/`achsen_zuweisen.js`: Options-`value` im Bedingungs-Wert-Dropdown ist jetzt der Wert-Text, `data-initial` liefert PHP jetzt ebenfalls als Text (`$wertTextById`-Lookup). `achsen_speichern.php` löst über `$wertIdByAchseText[$achseId][$text]` NACH dem Werte-Speichern zur frischen ID auf.
- **Fix 2 (Folgefund):** Beim Testen mit MEHREREN gleichzeitigen Bedingungen crashte das Speichern hart mit `PDOException ... Cannot delete or update a parent row (fk_artAchs_bedingungs_wert_id)` — `findWertIdsInUse()` schützte Werte nur bei Verwendung in Kombinationen/Konfigurator-Bestellungen, nicht wenn sie als Bedingungs-**Ziel** einer anderen Achse dienen. Dritter `UNION`-Zweig in `VariantenRepository::findWertIdsInUse()` ergänzt (JOIN gegen `artikel_achsen.bedingungs_wert_id`). Damit bleiben Bedingungs-Ziel-Werte jetzt auch über mehrere Speichervorgänge mit stabiler ID erhalten (kein Delete+Reinsert mehr für sie).
- Beide Fixes mit Jackys genauem Reproduktionsszenario getestet (zwei gleichzeitige Bedingungen setzen+speichern, dann ein drittes unabhängiges Speichern) — läuft jetzt sauber durch.

## Achsen-Reihenfolge pro Artikel + UX-Card ✅ FERTIG 2026-08-28
Jackys UX-Beschwerde: bei vielen globalen Achsen (>20) war Umsortieren in der langen Gesamtliste mühsam (viele Klicks, Seite springt beim Reload immer an den Anfang). Neue Card "Für diesen Artikel aktive Achsen" oben in `achsen_zuweisen.php` — zeigt NUR die für diesen Artikel angehakten Achsen, sortierbar per ▲▼ **per AJAX ohne Neuladen** (`achse_artikel_sort_ajax.php`, neues File, Normalisieren+Tauschen-Muster wie das bestehende `achse_sort_tree_ajax.php`, aber auf `artikel_achsen.sort_order` statt der globalen `varianten_achsen.sort_order`). Entscheidung mit Jacky: Reihenfolge ist **pro Artikel** (nicht global), Card kann nur anzeigen+sortieren (Entfernen bleibt über die Checkbox unten).
- **Dabei gefundener Bug:** Das normale "Speichern" hätte jede manuelle Umsortierung beim nächsten Speichern (z.B. neuen Wert hinzufügen) sofort wieder überschrieben — `speichereAchsenUndWerte()` setzte `sort_order` für JEDE Achse basierend auf der Checkbox-Reihenfolge der großen Liste neu. Gefixt: bestehende Achsen behalten ihre `sort_order` beim normalen Speichern unangetastet, nur neu hinzugefügte Achsen bekommen `max(sort_order)+1`.
- Getestet: AJAX-Swap ohne Reload, Persistenz nach echtem Reload, UND dass ein nachfolgendes normales Speichern die Reihenfolge nicht mehr zurücksetzt.

## VarKombi-Generator-Sperre bei Konfigurierbar ✅ FERTIG 2026-08-28
Beim echten Aufbau des Test-Schilds (Achse "Durchmesser" × "Farbschema" × "2. Farbe" × "Filz-Farben" × "Glitzereffekt" × "Grundfarbe" × "Hintergrund" × "Holzauswahl" ...) lief `detail.php` auf >92.000 Kombinationen in der Vorschau — genau die Explosion, die der Konfigurator ja vermeiden sollte. Ursache: `kartesischesProdukt()` lief bei JEDEM Laden von `detail.php` unconditional für jeden Vater mit Achsen, unabhängig von `ist_konfigurierbar`. Fix: Wenn `artikel.ist_konfigurierbar=1`, wird die komplette Berechnung übersprungen (kein PHP-Rechnen, kein Rendern der Tabelle), `varkombi_erstellen.php` lehnt zusätzlich serverseitig ab falls doch mal ein alter Tab/POST durchkommt. Getestet gegen den echten Testartikel (id 27473, "Wollzimmer" — Jackys Arbeitstitel fürs Test-Schild): Ladezeit 2,3s statt Browser-Hänger, DB-Check bestätigte dass nichts tatsächlich einexplodiert war (nur Browser-/PHP-Vorschau, keine Kind-Artikel entstanden).

## Wert-Aufpreis-Feld (additiv zum Achsen-Aufpreis) ✅ FERTIG 2026-08-28
Auslöser: Durchmesser sollte je nach gewähltem Wert unterschiedlich viel kosten (z.B. 38cm teurer als 15cm) — der bestehende Achsen-weite Preis-Toggle (`artikel_achsen.preis_modus`/`preis_wert`) gilt aber für ALLE Werte einer Achse gleich, kann das nicht abbilden. Lösung: die bereits vorhandene, aber bisher nirgends im UI editierbare Spalte `varianten_achse_werte.aufpreis` jetzt tatsächlich nutzbar gemacht:
- Neuer `€`-Button an jedem Wert-Chip in `achsen_zuweisen.php` (neben `✎`) — Klick öffnet Inline-Zahlenfeld, bei Wert>0 erscheint ein grünes Badge direkt am Chip
- `VariantenRepository::updateWertAufpreis()`, `VariantenService::speichereAchsenUndWerte()` erweitert um Aufpreis-Änderungserkennung für geschützte/in-use Werte (analog zur bestehenden Text-Korrektur-Logik); freie Werte laufen ohnehin über den bestehenden delete+reinsert-Pfad, `insertWert()` kannte `aufpreis` schon
- **Rechenregel (mit Jacky abgestimmt): additiv, nicht überschreibend** — Gesamtaufpreis = Achsen-Aufpreis (falls Modus=Aufpreis) + Wert-Aufpreis; bei Direktpreis-Modus kommt der Wert-Aufpreis obendrauf. 0€ am Wert = unverändertes Alt-Verhalten.
- Nebenfund dabei: das "Aufpreis"-Eingabefeld im VarKombi-Generator (`detail.php`) ist nur ein Vorschlagswert — was der Nutzer dort einträgt, wird beim Generieren aktuell gar nicht übernommen (`varkombi_erstellen.php`/`erstelleKombinationen()` liest `$kombi['aufpreis']` nie). Nicht angefasst, nur notiert — die neue additive Logik gilt bisher nur für die noch zu bauende Bestellzeit-Preisberechnung des Konfigurators.
- Getestet per Playwright gegen beide Speicherpfade (freier Wert bei Testartikel 27473, gesperrter/in-use Wert bei D-1059) — beide persistieren korrekt über Reload, danach sauber auf 0 zurückgesetzt.

## Phase 1 (ERP-Seite) ✅ KOMPLETT FERTIG 2026-08-28
Kompletter Plan (`C:\Users\indy1\.claude\plans\zesty-moseying-meerkat.md`) in einer Session durchgebaut, jeder Abschnitt einzeln getestet (CLI gegen Wegwerf-Testdaten + Playwright + ein echter Sync-Lauf gegen `indra-design.at`). Testartikel 27473 ("Wollzimmer") ist jetzt live im Shop 1 (externe Produkt-ID 34168), bewusst so gelassen.

**Zwei Vorab-Fixes** (von den Recherche-Agenten gefunden, hätten sonst später real zugeschlagen):
- `VariantenRepository::findWertIdsInUse()` kannte nur `varianten_kombination_werte` (Vater/Kind) — sobald die erste `position_konfiguration`-Zeile existiert hätte, wäre der Achsen-Editor beim nächsten Speichern mit FK-Verletzung gecrasht. UNION-Erweiterung ergänzt.
- Neues **generisches Artikel-Flag `artikel.keine_lagerbestandsfuehrung`** (Migration 171, Checkbox in `detail.php` neben "Konfigurierbar", AJAX-Toggle `lager_flag_ajax.php`) — nicht Konfigurator-spezifisch, für jeden Artikel ohne eigenen Bestand nutzbar. Wirkt in Kasse (kein Lagerabzug), Shop-Sync (`manage_stock=false`), Reservierungen (übersprungen).

**Neues Modul `src/modules/konfigurator/`** (`KonfiguratorRepository`+`KonfiguratorService`, bewusst eigenständig statt in `VariantenService` — siehe Docblock dort):
- `getKonfiguration()` — Achsen als Dimensionen (nutzt `VariantenService::baueAchsenDimensionen()`, sonst Bug-Typ vom 2026-07-29 in neuem Gewand) inkl. Bedingung + Aufpreis pro Achse/Wert
- `berechnePreis()` — additive Preisregel (Basispreis via `PreisService::getEffektiverPreis()` + Achsen-Aufpreis + Wert-Aufpreis), harte Validierung gegen fremde `wert_id`s, **erster echter Konsument** von `bedingungs_achse_id`/`bedingungs_wert_id` (vorher nur Dateneingabe ohne Auswertung)
- `speichereAuswahl()`/`ladeAuswahl()` — polymorph wie `reservierungen`
- `bauePreisMatrix()` — JSON-Struktur fürs künftige Shop-Frontend, `gesamt_aufpreis` wird im ERP vorberechnet (Vertrag mit dem Frontend bleibt trivial: `Preis = basis_brutto + Σ gesamt_aufpreis der gewählten Werte`)

**Kasse** (`bon.php`/`bon_speichern.php`/`KassenService`): neues Options-Overlay (Scan **und** Namenssuche erkennen Konfigurator-Artikel), Live-Preisvorschau immer vom Server, bedingte Achsen blenden sich dynamisch ein/aus, zwei unterschiedlich konfigurierte Artikel verschmelzen im Warenkorb nicht. `erstelleBon()` schreibt `position_konfiguration` für Bon- UND gespiegelte Auftragsposition (lastInsertId() SOFORT nach dem INSERT lesen, sonst überschreibt der Lager-Block ihn).

**Bestellungs-Rückweg** (`ShopBestellungSyncService::leseKonfigurationAusLineItem()`): liest `_mealana_konfig`-JSON aus WC-Line-Item-`meta_data` (Präfix-Fallback falls das Frontend die JSON-Variante mal verliert), plus Preis-Abweichungs-Warnung im Logger. **Nebenbefund + gefixt:** `AuftragService::bearbeiten()` hätte durch die neue `konfig_wert_ids` in `berechnePositionen()` bei JEDEM Auftrag-Bearbeiten (nicht nur Konfigurator!) mit PDO-Fehler gecrasht — Key wird jetzt auch dort vor `insertPosition()` gestrippt (Konfiguration geht beim Bearbeiten aktuell verloren, bewusst zurückgestellt, s.u.).

**Shop-Sync** (`ShopSyncService::baueProduktPayload()`): dritter Zweig über `$istKonfig`/`$istVariable`-Flags (nicht über einen zweiten `empty($achsen)`-Check — genau der Tippfehler-Bug-Typ vom 2026-08-01). Konfigurationsartikel bekommen Attribute (`variation:false`, `type` bleibt normal) + `manage_stock=false` + die Preis-Matrix als `_mealana_konfigurator`-Meta (IMMER gesendet, auch leer beim Abschalten). **Echter Fund:** `$payload += $this->baueMindestabnahmeFelder(...)` funktionierte nur, weil das bisher der einzige `meta_data`-Produzent war — PHPs `+=` überschreibt keine vorhandenen Keys, ein zweiter Produzent wäre stillschweigend verschluckt worden. Neuer `mergeMetaData()`-Helfer, an allen Stellen nachgezogen (auch `baueVariationPayload()`, dort aktuell nur vorsorglich).

**Leseseite:** `auftraege/detail.php` zeigt die gespeicherte Auswahl jetzt dezent unter der Artikelbezeichnung an (🔧-Zeile), Batch-Query gegen N+1.

## Bekannte, bewusst zurückgestellte Lücke
`AuftragService::bearbeiten()` löscht+schreibt Positionen komplett neu — eine bestehende Konfigurator-Auswahl geht beim Bearbeiten eines Auftrags verloren (nur der Klartext in `bezeichnung` bleibt). `KonfiguratorRepository::deleteAuswahl()` steht schon bereit, aber ob/wie `bearbeiten()` das nachziehen soll ist eine eigene kleine Entscheidung für später.

## Für später vorbereitet: Rohmaterial-Bestandsampel
`varianten_achse_werte.rohmaterial_artikel_id` existiert (Migration 169). Hook-Punkte für später: `KonfiguratorService::getKonfiguration()` (pro Wert zusätzlich `verfuegbar: bool` aus `LagerService`-Bestand des verknüpften Rohmaterial-Artikels) und `bauePreisMatrix()` (gleiches Feld im JSON). Kein Umbau nötig, nur additive Erweiterung derselben zwei Methoden. Bewusst nicht in Phase 1 mit eingebaut (Jackys Entscheidung 2026-08-28).

## Phase 2: WordPress-Shop-Frontend — Entwurf steht, wartet auf Freigabe (Stand 2026-08-28 Abend)
Nicht Teil des `mealana-erp`-Git-Repos — WordPress/WooCommerce-Code (WPCode-Plugin) auf `indra-design.at`.

**Zugang:** WP-Admin-Account "claude" (Rolle Administrator) — Zugangsdaten liegen in `D:\ERP\mealana\import\zugang Woo Claude.txt` (von Jacky angelegt, nicht in diese Memory kopiert). Login unter `https://indra-design.at/wp-login.php`.

**Plugin-Fund:** Code-Snippets-Verwaltung läuft über **WPCode Lite** (nicht Code Snippets Pro) — PHP-Snippets sind in der kostenlosen Lite-Version bereits enthalten (nur Block-Snippets/SCSS/Code-Revisionen sind Pro-gated). Mehrere eigene PHP-Snippets von Jacky/Vorentwickler liefen schon aktiv auf der Seite (u.a. "Mindestabnahme" — passt zu [[project_meterware_mindestabnahme]]).

**UI-Eigenheit für künftige Sessions:** Der "Neues Snippet"-Screen (`wp-admin/admin.php?page=wpcode-snippet-manager&custom=1`) ist eine Art SPA — die Typ-Auswahl-Karten (HTML/PHP/CSS/...) sind `<li>`-Elemente ohne href, dahinter aber ein normales `<select name="wpcode_snippet_type">`. Zuverlässig klickbar über `page.locator('h3', {hasText:'PHP-Snippet'}).first().click()`. Der Code-Editor ist CodeMirror über einer `textarea#wpcode_snippet_code` — Text per `page.keyboard.insertText()` nach Klick+Strg+A einfügen, nicht `.fill()` (wird von CodeMirror nicht übernommen).

**Entwurf gebaut und gespeichert:** Snippet-ID 34170 "Konfigurator: Options-Picker (Entwurf, noch nicht aktiv)", Typ PHP, **bewusst INAKTIV gespeichert** (Toggle aus) — noch nicht live, wartet auf Jackys Review. Inhalt (ein Snippet, mehrere Hooks):
- `woocommerce_before_add_to_cart_button` — rendert pro Achse ein `<select>` aus der `_mealana_konfigurator`-Matrix (liest `bedingung` fürs Ein-/Ausblenden), plus Live-Preis-Anzeige per Inline-JS (`Preis = basis_brutto + Σ gesamt_aufpreis` der sichtbaren gewählten Werte, "In den Warenkorb" bleibt disabled bis vollständig)
- `woocommerce_add_cart_item_data` — nimmt die gewählten Wert-IDs vom Hidden-Input entgegen, berechnet den Preis **serverseitig neu** (eigene PHP-Funktion `mealana_konfig_berechne_preis()`, identische additive Regel wie `KonfiguratorService::berechnePreis()`)
- `woocommerce_before_calculate_totals` — setzt den Cart-Item-Preis hart auf den serverseitig berechneten Wert (Manipulationsschutz — der Client-Preis wird nie übernommen)
- `woocommerce_get_item_data` — zeigt die Auswahl in Warenkorb/Checkout an
- `woocommerce_checkout_create_order_line_item` — schreibt `_mealana_konfig`-JSON (genau das Schema, das `ShopBestellungSyncService::leseKonfigurationAusLineItem()` erwartet: `{version,werte,preis_brutto}`) + Klartext-Achse-Keys ohne Unterstrich (für Kunde/Admin) auf die Bestellzeile

**Noch nicht gemacht:** kein echter End-to-End-Test (Produktseite ansehen, Warenkorb, Checkout) — bewusst nicht während Jacky abwesend war, da erste unerprobte Live-Storefront-Änderung. Vor Aktivierung: Produktseite von Testartikel 27473 (`/product/holzschild-wollzimmer/`) ansehen, Options-Picker durchklicken, Preis prüfen, dann Testbestellung + ERP-Sync-Rücklauf verifizieren.

## Offen
- WooCommerce-Anbindung: offizielle "Product Add-ons"-Erweiterung ist kostenpflichtig — überholt durch die Selbstbau-Entscheidung (s.o.), reine Historie.

## Nebenthema 2026-08-28 (Abend): Zahlungsart "Bar bei Abholung" — nicht Teil des Konfigurators, aber gleicher Shop
Jacky bemerkte beim Testbestellen, dass "Bar bei Abholung" im Checkout fehlt. Zwei echte Funde dabei, siehe [[project_shop_sync]] für Details zur Umsetzung — hier nur der Kontext-Link, damit die nächste Session nicht wieder bei null anfängt: **WooCommerce hat das klassische "Lokale Abholung"-Versandart-Modul aus neueren Versionen entfernt**, das neue "Abholung vor Ort"-Feature (unter Versand-Einstellungen, bei euch schon aktiviert) **funktioniert nur mit dem blockbasierten Checkout** — indra-design.at läuft aber noch auf dem klassischen Shortcode-Checkout, dort greift es nicht. Deshalb eigene Versandart + Zahlungsart per Snippet gebaut statt Bordmittel zu nutzen.

## ✅ 2026-10-02: "WP-Session-Cookie-Problem beim Warenkorb" geprüft — kein Fehler
Playwright gegen indra-design.at: Holzschild (34168) mit Aufpreis-Optionen → Produktseite 49,00 € (15+23+0,5+0,5+5+5 korrekt), Block-Warenkorb + Block-Checkout zeigen alle 7 Optionen + 49,00 € (inkl. 8,17 € MwSt). Store-API-Warenkorb korrekt. Der scheinbar leere Warenkorb war ein **Testskript-Fehler**: `Promise.all([page.waitForLoadState('load'), btn.click()])` löst sofort auf (Seite war schon geladen) → Sprung zu /cart/ bricht den Formular-POST ab, Session-Cookie kommt nie an. Richtig: `waitForResponse` auf den POST. Vermutlich war das alte "Cookie-Problem" vom 2026-08-28 derselbe Testartefakt. Kosmetisch: Screenreader-Text in der Kasse zeigt `&#8222;` (doppelt kodierte Anführungszeichen im Produktnamen) — unwichtig. Block-Warenkorb braucht ~3 s bis die Zeilen gefüllt sind (normales WC-Blocks-Verhalten).
