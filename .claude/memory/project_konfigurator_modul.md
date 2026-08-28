---
name: project-konfigurator-modul
description: "Geplantes Konfigurator-Modul (Schilder/Buttons/Anhänger, später ggf. Anleitungspakete mit Stückliste) — Optionen mit Aufpreis statt Kind-Artikel-Explosion, eigenständiges lizenzierbares Modul"
metadata: 
  node_type: memory
  type: project
  originSessionId: bf21b7a8-0044-4fd4-869f-1ae811833787
  modified: 2026-08-28T15:21:21.379Z
---

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

## Separater Nebenfund: Rundnadeln — WooCommerce-Variations-Schwellwert
Bei manchen Rundnadel-Vätern bis zu 140 echte Kind-Kombinationen. WooCommerce filtert Dropdowns nur bei ≤30 Variationen automatisch dynamisch (`data-product_variations`-JSON, das schon aus dem bestehenden Vater/Kind→Variation-Sync kommt) — darüber statischer Fallback ("Auswahl nicht möglich" erst nach Klick), das erklärt das beobachtete JTL/Shop-Verhalten. **Reine Shop-Sache, kein ERP-Code:** `woocommerce_ajax_variation_threshold`-Filter per Snippet anheben. Noch nicht umgesetzt, vorgemerkt.

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

## Phase 2 (als Nächstes): WordPress-Shop-Frontend
Nicht Teil dieses Repos — WordPress/WooCommerce-Code (Code-Snippets-Plugin) auf `indra-design.at`. Jacky richtet dafür einen eigenen WP-Admin-Account für Claude ein (Rolle Administrator nötig, Code-Snippets braucht das). Inhalt: Options-Picker auf der Produktseite (liest `_mealana_konfigurator`-Matrix + Attribute), Live-Preis-JS (`Preis = basis_brutto + Σ gesamt_aufpreis`), Warenkorb-Integration (gewählte Werte als Line-Item-Meta gemäß `_mealana_konfig`-Schema, Preis serverseitig in WooCommerce festschreiben gegen Manipulation). Noch nicht begonnen, wartet auf den Zugang.

## Offen
- WooCommerce-Anbindung: offizielle "Product Add-ons"-Erweiterung ist kostenpflichtig — überholt durch die Selbstbau-Entscheidung (s.o.), reine Historie.
