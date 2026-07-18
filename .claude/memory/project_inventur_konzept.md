---
name: project-inventur-konzept
description: "Vollständiges Design für das Inventur-Modul (Lagerplätze, Zähl-Läufe, Sperren, Chargen-Abgleich) — Design-Session 2026-07-18, noch nicht gebaut"
metadata: 
  node_type: memory
  type: project
  originSessionId: 1208232f-9b1f-41ae-ae93-bb91abe26d76
  modified: 2026-07-18T11:07:09.493Z
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

## How to apply beim Weiterbauen

Nächster Schritt: **Inventur-Lauf-Kern** (Kopftabelle + Scope-Auswahl + Zählliste). Dabei gleich mit erledigen: `lagerbestand.lagerplatz_id` + UNIQUE-Index-Erweiterung + Anpassung der bestehenden `LagerRepository`-Buchungsmethoden (siehe Hinweis oben) — sorgfältig testen, weil das in die produktiven Kasse/Wareneingang/Umlagerung-Pfade eingreift. Danach Live-Sperre/Buchungssperre, dann Abschluss-Logik (Chargen-Summenabgleich + Lagerplatz-Reallokation + Differenzbuchung), zuletzt Druckversion + Manager-Auslauf-Shortcut + Fortschritts-%-Anzeige + "Letzte Inventur"-Datum am Artikel (aktiviert den vorhandenen Spalten-Picker-Platzhalter). Referenz-Check ist mit diesem Dokument erledigt — nicht nochmal wiederholen, direkt in die Design-Detailarbeit je Baustein gehen.
