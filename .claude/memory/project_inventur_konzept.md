---
name: project-inventur-konzept
description: "Vollständiges Design für das Inventur-Modul (Lagerplätze, Zähl-Läufe, Sperren, Chargen-Abgleich) — Design 2026-07-18; Lagerplätze + Inventur-Lauf-Kern Slice 1 (Kopf+Scope+Lebenszyklus) FERTIG, Zählliste als Nächstes"
metadata: 
  node_type: memory
  type: project
  originSessionId: 1208232f-9b1f-41ae-ae93-bb91abe26d76
  modified: 2026-07-18T16:29:04.605Z
---

Stand: 2026-07-18, komplette Design-Absprache mit Jacky vor Baubeginn (wie von ihm gewünscht, siehe [[feedback_modul_vorgehen]]). Ersetzt/ergänzt die verstreuten Einzelnotizen in [[project_inventur_hinweis]] und den Lagerplätze-Abschnitt in [[project_lager_konzept]] — dies hier ist das verbindliche Konzept.

## Grundprinzip: EIN Inventur-Lauf-Mechanismus, nicht mehrere Module

Statt getrennter "große Inventur" / "rollierende Inventur" / "Einzelartikel-Nachzählung"-Bausteine: ein einziger Inventur-Lauf mit frei wählbarem **Scope**:
- Ganzes Lager
- Ein Lagerplatz
- Eine Kategorie/Artikelgruppe
- Ein einzelner Artikel

Deckt damit sowohl die große Jahresinventur als auch spontane Anlässe ab — z.B. das bereits vorgemerkte Warndreieck-Flag bei manuellen Mengenkorrekturen ([[project_inventur_hinweis]]) oder eine Kassa-Überbuchung (Artikel zeigt Bestand ≤0 obwohl verkauft) triggert einfach einen Lauf mit Scope=1 Artikel.

## Lagerplätze (neue Tabelle, Voraussetzung)

- `lagerplaetze (id, lager_id, bezeichnung, aktiv)` — Regal/Fach-Struktur unterhalb eines Lagers.
- `lagerbestand.lagerplatz_id` (FK NULL) — ein Artikel/eine Charge kann auf **mehreren** Lagerplätzen im selben Lager liegen (z.B. Verkaufsregal + Überlauf-Lager dahinter). Summe über alle Lagerplätze = Gesamtbestand im Lager (wie bisher).
- Bestehende `lagerbestand`-Zeilen ohne Lagerplatz bleiben mit `lagerplatz_id = NULL` gültig (nicht jeder Artikel muss sofort einem Platz zugeordnet werden).

## Blind-Modus

Konfigurierbar (vermutlich `system_einstellungen`, evtl. pro Lauf überschreibbar) — Soll-Bestand beim Zählen ein-/ausblendbar. **Charge wird immer angezeigt**, unabhängig vom Blind-Modus (sonst weiß der Zähler nicht, welches Los er vor sich hat — bei Garn kritisch).

## Zähler-Zuteilung: First-come + Live-Sperre

Keine Vorab-Zuweisung von Bereichen zu Personen (Jacky: "jeder hat so seine Lieblings-Regale"). Stattdessen: sobald jemand einen Lagerplatz zum Zählen öffnet, wird er live als "wird gerade gezählt von X, seit HH:MM" markiert. Öffnet eine zweite Person denselben Platz, bekommt sie eine sichtbare Warnung statt unbemerkt parallel zu zählen. Kein Hard-Lock nötig, nur eine sichtbare Info + ggf. Bestätigung "trotzdem übernehmen" (z.B. wenn der erste Zähler abgebrochen hat).

## Buchungssperre — zwei Stufen je nach Scope

- **Teil-Scope** (Lagerplatz/Kategorie/Einzelartikel): nur die konkret betroffenen Artikel/Lagerplätze werden für Kasse/Wareneingang gesperrt, der Rest läuft normal weiter.
- **Voll-Scope** (ganzes Lager): 
  - Alle Kassen, deren `kassen.lager_id` zum inventierten Lager gehört, werden komplett gestoppt (nicht systemweit alle Kassen — eine Messe-Kasse an einem anderen Lager bleibt unberührt, falls z.B. nur das Hauptlager inventiert wird).
  - Shop-Abgleich pausiert automatisch (**Vorgriff im Datenmodell** — Online-Shop-Anbindung ist noch 0% Code, dieser Mechanismus greift erst sobald der Sync existiert; aktuell macht Jacky das manuell).
  - In der Praxis ist das Geschäft während der großen Inventur ohnehin geschlossen — deckt sich mit dem heutigen manuellen Vorgehen, nur jetzt technisch erzwungen statt nur diszipliniert.

## Chargen beim Zählen: frei eingebbar, nicht nur Dropdown

Der Zähler muss beim Erfassen **neue** Chargen eintragen können (nicht nur aus bereits bekannten wählen) — sowohl für Artikel/Lagerplatz-Kombinationen, die es in `lagerbestand` noch gar nicht gibt (komplett neuer Fund), als auch für bereits erfasste Positionen mit bisher unbekannter Charge.

### Auflösung von "nachzutragen"-Chargen bei Abschluss

Regel (Jacky, 2026-07-18): Vergleich ist ein **Summenvergleich pro Artikel**, nicht Charge-für-Charge.
- Bisheriger Bestand z.B. 6 Stk., davon 3 Stk. mit `charge_status='nachzutragen'` (Rest mit bekannter Charge).
- Zähler meldet z.B. 5 Stk. Charge A + 1 Stk. Charge B = 6 Stk. gesamt.
- **Gezählte Summe ≥ vorherige Gesamtsumme (inkl. der unklaren Anteile)** → die `nachzutragen`-Einträge gelten als aufgelöst und werden entfernt/durch die neu erfassten echten Chargen ersetzt. Passt.
- **Gezählte Summe < vorherige Gesamtsumme** → echter Fehlbestand, wird auffällig markiert / muss nachkontrolliert werden (nicht einfach stillschweigend als Schwund durchgehen, siehe Begründungspflicht unten).

## Lagerplatz-Reallokation beim Rückbuchen

Wird ein Artikel an einem anderen Lagerplatz gefunden als im Soll verzeichnet (Beispiel Jacky: Soll Fach 3 = 5 Stk., tatsächlich Fach 3 = 0, dafür Fach 12 = 6 Stk.), bucht der Abschluss das automatisch um — faktisch eine implizite Umlagerung innerhalb des Laufs, keine zusätzliche manuelle Umlagerungsbuchung nötig.

## Begründungspflicht bei Abweichung — rollenabhängig

Kein fester Schwellwert, sondern an den bestehenden Rollen-Rang gekoppelt (analog Manager-Override-PIN, das schon rang-basiert funktioniert): unterhalb eines konfigurierbaren Rangs (z.B. Aushilfen/Praktikanten) ist die Notiz bei jeder Abweichung Pflicht, ab Manager-Rang optional. Keine neue Berechtigung nötig, nur ein Rang-Schwellwert-Check wie beim Manager-PIN.

## Manager+: Artikel direkt aus der Zählung als "Auslaufartikel" markierbar

Ab Manager-Rang (gleicher Rang-Mechanismus wie oben) kann der Zähler einen Artikel direkt aus der Zählliste heraus als Auslaufartikel markieren (Shortcut zum bestehenden `artikel.ist_auslaufartikel`-Flag, keine neue Logik — nur ein zusätzlicher Button/Direktzugriff in der Zähl-UI für ausreichend berechtigte Nutzer).

## UI-Anforderungen

- **Zählseite**: Tablet/Laptop-tauglich, gleiches Mobile-First-Muster wie Kasse/Packplatz (großer EAN-Scan als primäre Eingabe, große Buttons, Cursor immer im Scan-Feld).
- **Druckversion der Zählliste**: PDF-Export (Dompdf, wie die anderen Dokumente im System), Filter wählbar — alles / bestimmte Lagerplätze / bestimmter Artikel. Für Papier-Fallback bzw. als Begleitliste neben der digitalen Erfassung.

## Abschluss-Buchungslogik

Keine neuen `lager_bewegungen`-Typen nötig — bereits vorhanden (Migration 083):
- Ist > Soll → Zugang, `bewegungstyp='inventur'`
- Ist < Soll → Abgang, `bewegungstyp='schwund'` (über `LagerService::warenSchwund()`, siehe [[project_inventur_hinweis]])

## Bereits vorgemerkte Detailpunkte aus [[project_inventur_hinweis]] (weiterhin gültig)

- RKSV-Reminder-Popup "Jahresendbeleg nicht vergessen" beim Anlegen einer **großen** (Voll-Scope) Zählliste, wenn Datum zwischen 15.12. und 10.01. liegt.
- Warndreieck-Flag bei manueller Mengenkorrektur triggert sinngemäß einen Scope=1-Artikel-Lauf (siehe "Grundprinzip" oben — jetzt technisch eingeordnet).

## Referenz-Check (aus [[project_wawi_gaps]], 2026-06-08)

JTL/Shopware/Sage/LS-POS bieten typischerweise: Blind-Inventur (HOCH), permanente/rollierende Inventur (MITTEL), mehrere Zähler gleichzeitig (MITTEL), Inventur-Sperre (MITTEL, Entscheidung stand aus — jetzt getroffen: sperren), Differenzliste mit Begründungspflicht (MITTEL), Zählliste nach Lagerplatz (MITTEL, jetzt Voraussetzung). Alle Punkte im Konzept oben abgedeckt.

## Nachträge 2026-07-18 (kurz nach der ersten Design-Runde eingefallen)

- **Mietfächer optional als Scope anbieten**: Neben Lager/Lagerplatz/Kategorie/Artikel soll auch ein einzelnes **Mietfach** (siehe [[project_partner_modul]] — physische Einheit pro Partner, `mietfaecher`-Tabelle, Status Frei/Belegt) als Inventur-Scope wählbar sein. Fachmieter-Ware liegt technisch auf einem eigenen `lager` mit `lager_beziehung='partner_bestand'` — der Scope "Mietfach X" zählt dann exakt dieses eine Partner-Lager, nicht das ganze Hauptlager. Betrifft nur die Scope-Auswahl-UI (Dropdown/Reiter "Mietfach" zusätzlich zu "Lager"), keine neue Tabelle nötig.
- **Fortschritts-Anzeige**: Bei laufenden Inventur-Läufen (ganzes Lager/Lagerplatz) eine %-Anzeige, wie viel vom Scope schon gezählt ist (gezählte Positionen / Gesamtpositionen im Scope).
- **Inventur vorzeitig beendbar + Fortsetzung**: Ein Lauf muss nicht zwingend am Stück fertig gezählt werden — abbrechen möglich, Zwischenstand (bereits gezählte Positionen) bleibt gespeichert. Eine spätere neue Inventur kann dann gezielt "nur die beim letzten Mal fehlenden/nicht gezählten Teile" als Scope anbieten, statt wieder bei Null anzufangen.
- **Artikel-Detailseite: "Letzte Inventur"-Datum anzeigen** — wann wurde dieser Artikel zuletzt tatsächlich gezählt (nicht nur bestellt/bewegt). **Wichtig:** Der Spalten-Picker in der Artikelliste hat dafür schon einen Platzhalter ("letzte_inventur", siehe [[project_spalten_picker]]) — der wartet exakt auf dieses Feature und kann beim Bau des Inventur-Lauf-Kerns direkt aktiviert werden (gleiches Muster wie die "merkmale"-Spalte, die beim Merkmale-Modul aktiviert wurde).

## 🟢 FERTIG 2026-07-18: Lagerplätze (erster Baustein)

- Migration 134: `lagerplaetze (id, lager_id, bezeichnung, aktiv, erstellt_am)`.
- **Bewusst NUR die Stammdaten-Tabelle in diesem Schritt** — `lagerbestand.lagerplatz_id` kommt noch nicht. Grund: das würde die bestehende `UNIQUE(artikel_id, lager_id, charge)`-Regel berühren, die Kasse/Wareneingang/Umlagerung aktuell produktiv nutzen (`LagerRepository::upsertBestand()`/`getBestand()`/`reduziereBestand()` etc.). Diese Verknüpfung — inkl. der nötigen Anpassung an den bestehenden Buchungsmethoden und der Erweiterung des UNIQUE-Index — gehört gezielt in den **Inventur-Lauf-Kern**-Schritt, nicht "nebenbei" hier mit rein.
- `LagerRepository`/`LagerService` um CRUD erweitert (`findAlleLagerplaetze`/`saveLagerplatz`/`aktualisiereLagerplatz`/`setLagerplatzAktiv`), gleiche Struktur wie die bestehende Lager-Stammdaten-Sektion.
- `public/lager/lagerplaetze.php` + Handler + `js/lagerplaetze.js` — Liste+Modal, exakt das Muster von `lager/verwaltung.php` (2026-07-05) übernommen: Filter (Lager-Dropdown + Aktiv-Status), Bearbeiten-Modal, 🗑️-Deaktivieren mit Bestätigung. **Keine Bestands-Sperre beim Deaktivieren** (anders als bei Lagern selbst) — ergibt aktuell noch keinen Sinn, weil noch nichts auf einen Lagerplatz verweist; kommt automatisch dazu sobald `lagerbestand.lagerplatz_id` existiert.
- Nav-Link "📍 Lagerplätze" im Lager-Sidebar, Rechte über bestehende `lager.anzeigen`/`lager.anlegen`/`lager.bearbeiten` (keine neue Berechtigung nötig).
- End-to-end getestet: CLI (Anlegen/Lesen/Bearbeiten/Deaktivieren/Validierungsfehler, danach aufgeräumt) + simuliertes Seiten-Rendering (Nav, Filter, leerer Zustand) — beides sauber.
- Handbuch Kapitel 03 + Bedienungsanleitung ergänzt.

## 🟢 FERTIG 2026-07-18: Inventur-Lauf-Kern Slice 1 (Kopf + Scope-Auswahl + Lebenszyklus)

**Architektur-Entscheidung dabei:** Statt `lagerbestand.lagerplatz_id` direkt einzubauen (siehe Alternative unten) — eine **separate** Tabelle `lagerbestand_lagerplaetze` (Lagerbestand-Zeile ↔ Lagerplatz ↔ Menge) ist der bessere Weg: Kasse/Wareneingang/Umlagerung bleiben komplett unberührt, nur die Inventur liest/schreibt zusätzlich diese Tabelle, Summe über alle Lagerplätze = weiterhin der bekannte Gesamtbestand. **Diese Tabelle ist noch nicht gebaut** — kommt mit Slice 2 (Zählliste), wenn tatsächlich pro Lagerplatz gezählt wird.

- **Migration 135**: `inventur_laeufe` (Kopftabelle) — `scope_tabelle`/`scope_id` polymorph (wie `aktivitaeten.referenz_tabelle`/`referenz_id`), `scope_bezeichnung` als Namens-Snapshot (wie `kunden_snapshot`), `blind_modus`, `status` ENUM(laufend/pausiert/abgeschlossen/abgebrochen), `vorgaenger_lauf_id` (self-FK für Fortsetzungen).
- **Berechtigungen bereits vorhanden** (Überraschungsfund): `inventur.anzeigen/anlegen/bearbeiten/loeschen` + `inventurpositionen.*` waren schon beim Rollen/Rechte-Modul-Bau (2026-07-05) mit-geseedet und den richtigen Rollen zugewiesen (superadmin/admin/assistent/manager/lager = voll, readonly = nur anzeigen) — obwohl das Inventur-Modul selbst 0% Code war. Einfach wiederverwendet, keine neue Berechtigung nötig.
- `InventurRepository`/`InventurService` (neu, `src/modules/inventur/`): Scope-Auflösung (Name des Ziels je nach scope_tabelle), `starten()`/`pausieren()`/`abbrechen()`/`fortsetzen()`. "Abgeschlossen" gibt es in Slice 1 bewusst noch nicht — ohne echte Zählpositionen gäbe es nichts sinnvoll abzuschließen.
- **UI**: `inventur/liste.php` (Übersicht + Status-Aktionen) + `inventur/neu.php` (Scope-Auswahl: 4 einfache Dropdowns + Artikel-Typeahead-Suche, analog Bestellmodul-Muster) + Handler (`starten.php`/`pausieren.php`/`abbrechen.php`/`fortsetzen.php`/`artikel_suche_ajax.php`).
- Nav-Link "🔢 Inventur" im Lager-Sidebar (eigenes Modul, aber unter `activeModule='lager'` eingehängt wie Lagerplätze).
- **Nebenfund beim Bauen**: Die JSON-Endpunkt-Whitelist (`Zugriffsregeln::$jsonEndpunkte`) für die Lagerplätze-Handler vom Vortag war unvollständig — nachgetragen (`lagerplaetze_speichern.php` etc. fehlten, hätten bei fehlender Berechtigung ein HTML-Redirect statt JSON zurückgegeben und den Fetch-Handler im JS kaputt gemacht).
- End-to-end getestet: kompletter Lebenszyklus per CLI (Start mit gültigem/ungültigem/nicht-existierendem Scope, Pausieren, Doppel-Pausieren-Ablehnung, Fortsetzen mit korrektem `vorgaenger_lauf_id`, Abbrechen), danach aufgeräumt. Seiten-Rendering simuliert geprüft (Scope-Dropdowns korrekt befüllt, Nav-Link da, leerer Listen-Zustand).
- Handbuch: neues Kapitel `13_inventur.md` (als "In Arbeit" markiert) + README-Inhaltsverzeichnis ergänzt (dabei auch das fehlende Kapitel 12 Buchhaltung nachgetragen, war nie eingetragen worden). Bedienungsanleitung ergänzt.
- **Bewusst noch nicht gemacht**: JS-Auslagerung (`neu.php` hat noch Inline-`<script>`) — laut [[feedback_js_auslagern]] gehört das zur Modul-Abschluss-Checkliste, aber das Modul ist noch mitten im Bau (mehr JS kommt mit der Zählliste in Slice 2) — wird gebündelt beim tatsächlichen Modul-Abschluss gemacht, nicht schon jetzt für ein einzelnes Inline-Script.

## 🟢 FERTIG 2026-07-18: Inventur-Lauf-Kern Slice 2 (Zählliste)

- **Migration 136**: `lagerbestand_lagerplaetze` (Lagerbestand-Zeile ↔ Lagerplatz ↔ Menge, additiv, siehe Architektur-Entscheidung oben).
- **Migration 137**: `inventur_positionen` (Zeile pro Artikel/Charge/Lager/Lagerplatz innerhalb eines Laufs). Bewusst **kein** DB-UNIQUE-Constraint auf den Schlüssel (lagerplatz_id + charge sind beide nullable, NULL≠NULL-Problem wie bei `lagerbestand`) — stattdessen prüft `InventurRepository::findPosition()` per SELECT vor jedem Insert, ob die Position schon existiert (sauberer als ein fragiler Index, da neue Tabelle ohne Legacy-Zwang).
- **Soll-Liste-Auflösung je Scope** (`InventurRepository::findSollListe*()`):
  - `lager` → alle `lagerbestand`-Zeilen dieses Lagers (Soll = `bestand`).
  - `lagerplaetze` → nur bereits zugeordnete Mengen aus `lagerbestand_lagerplaetze` — **bewusst leer beim ersten Zählgang eines Platzes** (Jacky-Entscheidung 2026-07-18), freies Erfassen per Scan/Suche statt Vorschlagsliste.
  - `kategorien`/`artikel` → **über alle Lager** (Scope legt kein Lager fest, Jacky-Entscheidung 2026-07-18), Lager-Spalte zur Orientierung.
  - `mietfaecher` → noch keine Auflösung (Semantik weiterhin offen), leere Liste.
- **`InventurService::bucheZaehlung()`**: Upsert-Logik (findPosition → update, sonst insert), löst `lager_id` automatisch aus `lagerplatz_id` auf wenn nötig, lehnt fehlendes Lager ab (relevant bei Kategorie-/Artikel-Scope, wo das UI dafür ein Lager-Dropdown einblendet).
- **UI** `inventur/zaehlen.php` + `js/inventur_zaehlen.js` (diesmal von Anfang an ausgelagert, nicht inline) + `zaehlung_speichern.php` (AJAX/JSON, kein Seiten-Reload beim Speichern einer Zeile). Freie Artikel-Erfassung per Typeahead oben, mit bedingtem Lager-Dropdown je nach Scope.
- End-to-end getestet: alle drei relevanten Scope-Pfade (Lager/Kategorie/Lagerplatz) mit echten Lagerbestand-Daten, Upsert-Verhalten (zweimal buchen → gleiche ID, kein Duplikat), Validierungsfehler (Kategorie-Scope ohne Lager), automatische Lager-Auflösung aus Lagerplatz, Seiten-Rendering — alles grün, Testdaten vollständig aufgeräumt.
- **Bewusst noch nicht Teil dieser Slice**: Live-Sperre (Info-Warnung bei Kollision zweier Zähler am selben Lagerplatz), Buchungssperre für Kasse/Wareneingang, Abschluss-Logik (Chargen-Summenabgleich, Lagerplatz-Reallokation, echte Differenzbuchung in `lagerbestand`/`lager_bewegungen`) — kommt mit Slice 3/4.
- Handbuch Kapitel 13 + Bedienungsanleitung ergänzt.

## 🟢 FERTIG 2026-07-18: Inventur-Lauf-Kern Slice 3 (Live-Sperre + Buchungssperre)

- **Migration 138**: `inventur_zaehl_sperren` — Claim pro (Lauf, Lagerplatz), `UNIQUE(inventur_lauf_id, lagerplatz_id)`, informativ (first-come, kein Hard-Block). `aktiv_seit` bleibt erhalten wenn derselbe Benutzer erneut beansprucht, wird zurückgesetzt bei Benutzerwechsel; Claims gelten als abgelaufen nach 10 Minuten Inaktivität.
- **`InventurService::lagerplatzWaehlen()`**: claimt + gibt eine Warnung zurück wenn kurz zuvor ein anderer Benutzer aktiv war. Bei Scope='lagerplaetze' automatisch beim Öffnen der Zählseite ausgelöst; bei Scope='lager' über ein neues Dropdown "Ich zähle gerade an" (nur dort sichtbar, da nur dort mehrere Lagerplätze zur Wahl stehen). Der gewählte Lagerplatz wird clientseitig gemerkt und taggt anschließend alle Zählungen dieser Session (`window.AKTUELLER_LAGERPLATZ_ID` in `inventur_zaehlen.js`) — genau so entsteht die Lagerplatz-Zuordnung in `lagerbestand_lagerplaetze` beim ersten Durchzählen.
- **`InventurService::gibtEsLaufendeVollinventur(lagerId)`**: zentrale Prüfung, ob für ein Lager gerade eine `scope_tabelle='lager'`-Inventur mit `status='laufend'` existiert.
- **Buchungssperre eingebaut an drei Stellen** (Jacky-Entscheidung 2026-07-18: auch Wareneingang sperren, nicht nur Kasse+Shop):
  - `KassenService::erstelleBon()` — Gate ganz am Anfang, vor dem RKSV/BFR-Gate (gleiches Muster wie die bestehenden Gates dort).
  - `kasse/bon.php` — zusätzliche UX-Vorabprüfung (Redirect mit Fehlermeldung), damit man gar nicht erst in der leeren Kasse landet; die eigentliche Sperre sitzt in `erstelleBon()`.
  - `WareneingangService::bucheMenge()` — Gate direkt nach Ermittlung von `$lagerId`.
  - Sperre ist **pro Lager**, nicht systemweit — eine Messelager-Inventur blockiert z.B. nicht die Hauptladen-Kasse (Jacky-Bestätigung vom Design-Gespräch).
  - Shop-Abgleich-Pause bleibt weiterhin nur Vorgriff im Datenmodell (Shop-Sync existiert noch nicht).
- End-to-end getestet: Live-Sperre (kein Warnung bei gleichem Benutzer, korrekte Warnung mit Namen+Zeit bei anderem Benutzer), Vollinventur-Gate (true für das inventierte Lager, false für andere), Kassen-Gate + Wareneingang-Gate lehnen korrekt ab — die beiden riskanten Buchungsaufrufe dabei bewusst in einer Transaktion gekapselt und zurückgerollt, damit selbst ein hypothetischer Gate-Bug keine echten Daten anfasst. Seiten-Rendering der neuen Lagerplatz-Auswahl geprüft. Alles aufgeräumt.
- Handbuch Kapitel 13 + Bedienungsanleitung ergänzt.

## 🟢 FERTIG 2026-07-18: Inventur-Lauf-Kern Slice 4 (Abschluss — echte Bestandskorrektur)

**Workflow-Erweiterung während des Baus** (Jacky, 2026-07-18): keine stille Buchung — vor jeder echten Änderung steht immer eine **Vorschau-Seite** (`inventur/abschluss_vorschau.php`), erreichbar über einen einzigen "Prüfen …"-Link sowohl bei laufenden als auch pausierten Läufen (ersetzt die früheren direkten Pausieren-/Abbrechen-Buttons in der Liste). Zeigt: Abweichungs-Tabelle (Soll≠Ist, egal ob mehr oder weniger) + Unvollständig-Liste, mit drei Aktionen: **Jetzt buchen & abschließen**, **Ohne Buchung pausieren**, **Verwerfen ohne Buchung** — nur die erste bucht tatsächlich.

- **`InventurService::berechneAbgleich()`** (privat, gemeinsame Basis für Vorschau UND Abschluss): gruppiert gezählte Positionen nach Artikel+Lager, prüft Vollständigkeit gegen die aktuelle Soll-Liste.
- **`vorschauAbschluss()`**: reine Lesefunktion, bucht nichts.
- **`abschliessen()`**: validiert zuerst ALLE Gruppen (Schwund ohne Notiz → kompletter Abbruch, nicht nur die eine Gruppe übersprungen), bucht dann pro Charge (`LagerRepository::upsertBestand()` + `insertBewegung()` Typ `inventur`/`schwund`), reallokiert Lagerplätze (`lagerbestand_lagerplaetze`), setzt Lauf-Status auf `abgeschlossen`.
- **Begründungspflicht** direkt in `bucheZaehlung()` eingebaut (nicht erst beim Abschluss): Abweichung + leere Notiz + `rolle_rang < 70` → Fehler. Ab Manager-Rang optional.
- Neue Helper in `LagerRepository`: `findLagerbestandIdByKey()`, `upsertLagerbestandLagerplatz()`.

**Zwei echte Bugs beim Testen gefunden + behoben** (siehe auch [[feedback_test_isolation]] — Testartikel 174 ohne bestehenden Lagerbestand in Lager 1 verwendet, komplett isoliert):
1. **Komplett unberührte Soll-Zeilen fehlten in der Gruppierung**: `berechneAbgleich()` baute Gruppen ursprünglich nur aus gezählten Positionen — eine Soll-Zeile, die NIE angefasst wurde, tauchte dadurch gar nicht als "unvollständig" auf (sie hätte schlicht gefehlt). Fix: Gruppen werden jetzt aus der Vereinigung von Soll-Liste UND gezählten Positionen gebildet.
2. **Lagerplatz-Tag verhinderte Vollständigkeits-Erkennung**: der Vergleichsschlüssel enthielt ursprünglich `lagerplatz_id` — aber die Soll-Liste (aus `lagerbestand`) kennt bei Scope=Lager/Kategorie/Artikel gar keinen Lagerplatz, der wird erst beim Zählen als Zusatzinfo angehängt ("Ich zähle gerade an"). Eine mit Lagerplatz-Tag gezählte Position passte dadurch nie zur lagerplatzlosen Soll-Zeile → Gruppe fälschlich als unvollständig markiert, keine Buchung. Fix: `lagerplatz_id` aus dem Vergleichsschlüssel entfernt (nur artikel|lager|charge); zusätzlich `summeVorher` jetzt direkt aus der Soll-Liste berechnet statt aus den Positionen-Snapshots, um Doppelzählung zu vermeiden falls dieselbe Charge auf zwei Lagerplätze aufgeteilt gezählt wird.
- End-to-end getestet (isolierter Test-Artikel 174, kein Risiko für echte Daten): vollständige Gruppe mit Zugang+Schwund+Notiz-Pflicht (inkl. korrekter Ablehnung ohne Notiz und Bestands-Unveränderheit danach), unvollständige Gruppe bleibt komplett unangetastet, Lagerplatz-Reallokation korrekt, Begründungspflicht nach Rang (Praktikant/Manager) — alle 4 Testszenarien grün, vollständig aufgeräumt.
- Handbuch Kapitel 13 + Bedienungsanleitung ergänzt.

## How to apply beim Weiterbauen

**Nächster Schritt: Slice 5 (letzte geplante Slice)** — Druckversion der Zählliste (PDF, Dompdf, Filter alles/Lagerplätze/Artikel), Manager-Auslauf-Shortcut (Artikel direkt aus der Zählung als `ist_auslaufartikel` markierbar, Rang-Schwelle wie Begründungspflicht), Fortschritts-%-Anzeige (gezählte/gesamt Positionen im Scope), "Letzte Inventur"-Datum am Artikel (aktiviert den vorhandenen Spalten-Picker-Platzhalter, siehe [[project_spalten_picker]] — Datum lässt sich aus `MAX(inventur_positionen.gezaehlt_am)` pro Artikel ableiten, keine neue Spalte nötig).

Kern-Workflow (Lagerplätze → Lauf-Kopf/Scope → Zählliste → Live-/Buchungssperre → Abschluss) ist damit komplett und produktiv nutzbar — Slice 5 sind nur noch Komfort-Ergänzungen, kein Blocker mehr.

Referenz-Check ist mit diesem Dokument erledigt — nicht nochmal wiederholen, direkt in die Design-Detailarbeit je Baustein gehen.
