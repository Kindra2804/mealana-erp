---
name: project-lager-konzept
description: "Lagerstruktur, Kassa-Modi, Messe-Lager, Umlagerung"
metadata: 
  node_type: memory
  type: project
  originSessionId: c55c1aca-b514-4e20-98fa-732e6e1149b3
  modified: 2026-07-18T11:07:34.684Z
---

## Lagerstruktur

- **Standardlager** (Wien) — Hauptlager, alle Kanäle außer Messe können darauf zugreifen
- **Lager Messe** — Sonderlager für Messebetrieb, eigene Regeln

## Lagerplätze — 🟢 Stammdaten-Grundlage FERTIG (2026-07-18)

Design-Session hat Lagerplätze als Voraussetzung fürs Inventur-Modul bestätigt (siehe [[project_inventur_konzept]]). Migration 134: `lagerplaetze (id, lager_id, bezeichnung, aktiv)` + Verwaltungs-UI (`lager/lagerplaetze.php`, gleiches Muster wie `lager/verwaltung.php`). **Bewusst noch OHNE** `lagerbestand.lagerplatz_id` — das würde die bestehende `UNIQUE(artikel_id, lager_id, charge)`-Regel berühren und gehört gezielt in den nächsten Baustein (Inventur-Lauf-Kern), nicht "nebenbei" hier mit rein. Details siehe [[project_inventur_konzept]].

## Kassa-Modi

**K1 (Kassa Wollboutique):**
- Immer im normalen Ladenbetrieb
- Zugriff auf Standardlager
- Hat immer den kompletten Artikelstamm

**K2 (Kassa Messe):**
- Zwei Betriebsmodi (umschaltbar in Einstellungen):
  1. **Normalbetrieb** → greift auf Standardlager zu (wie K1)
  2. **Messebetrieb** → greift auf Lager Messe zu statt Standardlager
- Hat immer den kompletten Artikelstamm (wie K1)

## Lager Messe — Regeln

- Artikel im Lager Messe sind NICHT verfügbar für: K1, Shop S1, S2, S3
- Nur K2 im Messebetrieb kann auf Lager Messe zugreifen
- Nach der Messe: Ware muss via **Umlagerung** zurück ins Standardlager
- Shops sehen diese Ware erst wieder nach der Umlagerung

## Umlagerung (noch zu bauen)

Funktion zum Verschieben von Beständen zwischen Lagern.
Wichtigstes Szenario: Lager Messe → Standardlager nach Messebetrieb.
Gehört ins Lager-Modul (oder Handyseite für schnelles Scannen).

**How to apply:** Bei der Lager-Modul-Planung und Handyseiten-Planung berücksichtigen. Berechtigungskonzept: K2-Messe-Umschaltung braucht eigene Berechtigung (nicht jeder Mitarbeiter darf das).

## Lagerbestand-Zustand (noch zu bauen)

Per-Einheit Zustandsverfolgung für konkrete Lagerbestände (z.B. "diese 3 Einheiten sind beschädigt").
Entsteht beim Wareneingang oder Rückgabe-Workflow.
Unterschied zum Artikel-`zustand` (statisches Attribut des Artikelstamms, z.B. Neu/Gebraucht).

**How to apply:** Beim Aufbau des Rückgabe-Moduls einplanen. Separates Feld in der Lagerbestand-Tabelle, nicht in `artikel`.

## Kanal-Chips in Artikel-Liste

K1 und K2 haben IMMER den kompletten Artikelstamm → keine Chips in der Artikelliste nötig.
Nur S1/S2/S3 bekommen Chips (weil diese ein Teilsortiment haben).
K1/K2 werden in der Kanallegende informativ erklärt, aber nicht als Badges an Artikeln gezeigt.

## Lager-Tab in detail.php (Spec 2026-06-14)

Entschieden:
- Pro Lager eine Zeile: Lager-Name | Bestand | Reserviert | Verfügbar | Mindestbestand | [Schnell-WE]
- Chargen: aufklappbar, **standardmäßig AUFGEKLAPPT** wenn mind. eine Charge vorhanden
  - Sub-Tabelle: Charge | Menge | Status | Letzte Bewegung
- Bewegungslog: letzte 10 Einträge (Datum | Typ | Menge | Vorher→Nachher | Lager | Referenz | User)
- Wareneingang: **Minimal-Ausführung** direkt im Tab (Schnell-Buchung, z.B. Korrektur bei Inventur)
  - Großer WE kommt ins Einkaufsmodul (mit PO-Abgleich, EAN-Scan, Lieferant)
- Umlagerung: Button → separate Seite (auch als Handyseite geplant — siehe Roadmap)

## Roadmap-Ergänzungen (2026-06-14)

- **Packplatz/Pick-Liste Modul**: Auf der Planungsliste. Soll EK-Bestellungen abrufen, beim Scan abgleichen, Vollständigkeitsprüfung. Kommt nach Einkaufsmodul.
- **Mobile Umlagerungsseite**: Handyoptimierte Seite für Lager↔Lager Transfers via EAN-Scan. Kommt mit/nach dem Lager-Modul-Ausbau.
- **Großer Wareneingang**: Im Einkaufsmodul integriert (PO-Referenz, Lieferant, Mengenabgleich, EK-Buchung).

**How to apply:** Beim Planen des Einkaufsmoduls: WE als Kernfunktion einplanen. Packplatz als eigenes Modul danach. Mobile-Seiten parallel dazu als PWA-fähige Varianten.

## Korrektur 2026-07-04: K2-Umschaltmodell so nicht umgesetzt, tatsächliches Design ist besser

Diese Datei beschreibt ein älteres Konzept ("K2 mit zwei Betriebsmodi, umschaltbar"). Tatsächlich umgesetzt (siehe [[project_kassen_verwaltung]]) ist ein saubereres Modell: **jede Kasse ist ein eigener DB-Datensatz** mit festem `lager_id` + `modus` (online/offline) — keine Laufzeit-Umschaltung eines einzelnen K2-Datensatzes, sondern z.B. eine dedizierte "Messe-Laptop"-Kasse mit `modus=offline`. Umlagerung Hauptlager↔Messe-Lager ist über `MesseSyncService::umbuchungZurMesse()`/`rueckkehrVerarbeiten()` fertig gebaut, inkl. UI (`messe_vorbereiten.php`, `messe_rueckkehr.php`). Der komplette Offline-Kassen-Workflow ist fertig, siehe `docs/offline_kasse_anleitung.md`.

## Lücke gefunden 2026-07-04: Keine Lager-Verwaltungs-UI

Es gibt keine einzige Seite, die in die `lager`-Tabelle schreibt — neue Lager anlegen/bearbeiten geht nur per SQL. Wird spätestens gebraucht, wenn Lagerplätze eingeführt werden.
**Jackys Vorschlag:** ein Flag "für Offline-Kassen auswählbar" auf `lager` einführen, statt sich (wie aktuell in `messe_vorbereiten.php`) auf `typ='messe'` zu verlassen — flexibler, falls es mal mehrere Messe-taugliche Lager oder andere Nutzungsarten gibt.
**How to apply:** Beim Bau der Lager-Verwaltungs-UI: Grunddaten (Name, Typ) + das Offline-Flag gleich mit einplanen.

## Lagerverwaltungs-UI — Design fertig besprochen (2026-07-05), ASCII bestätigt, SVG bewusst übersprungen

Kein Admin-Backend-Screen mit Barbara-Relevanz — Jacky hat den SVG-Schritt hier explizit für unnötig erklärt, ASCII reicht.

**Muster:** Liste + Modal (wie Hersteller/Achsen), keine eigene Bearbeiten-Seite.

**Liste:** 3 Cards/Abschnitte — "Eigene Lager", "Partner-Bestand bei uns", "Unsere Ware bei Händlern" (siehe [[project_haendler_konsignation]], [[project_partner_modul]] für die Beziehungs-Typen dahinter).

**Migration:**
```sql
ALTER TABLE lager
  ADD COLUMN fuer_offline_kasse_waehlbar TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN lager_beziehung ENUM('eigen','partner_bestand','haendler_aussenlager') NOT NULL DEFAULT 'eigen',
  ADD COLUMN partner_id INT UNSIGNED NULL,
  ADD COLUMN kunde_id INT UNSIGNED NULL,
  ADD CONSTRAINT fk_lager_partner FOREIGN KEY (partner_id) REFERENCES partner(id),
  ADD CONSTRAINT fk_lager_kunde  FOREIGN KEY (kunde_id)  REFERENCES kunden(id);
```

**Modal-Felder:** Name, Typ (bestehender Enum ladengeschaeft/messe/extern/lager), Beziehung (Eigenes Lager/Partner-Bestand/Händler-Außenlager), Aktiv-Checkbox, Offline-Kassen-Checkbox. Partner/Kunde-Zuweisung passiert NICHT hier, sondern im jeweiligen Partner- bzw. Kunden-Formular (dort später ein Dropdown auf unzugewiesene Lager mit passender `lager_beziehung`).

**Soft-Delete-Regel:** Deaktivieren (`aktiv=0`) nur erlaubt wenn `SUM(bestand)` aus `lagerbestand WHERE lager_id=X` = 0 ist. Sonst Fehlermeldung "Lager hat noch Bestand — erst umlagern". Listen filtern standardmäßig `aktiv=1`, mit Toggle "auch inaktive anzeigen" (wie Hersteller/Partner).

**✅ Gebaut (2026-07-05):** Migration 107, `LagerRepository`/`LagerService` (findAlleMitDetails/getAlleGruppiert/saveLager/aktualisiereLager/setLagerAktiv), `public/lager/verwaltung.php` + Modal + `verwaltung_speichern.php`/`verwaltung_aktualisieren.php`/`verwaltung_status_setzen.php` + `js/lager_verwaltung.js`, Sidebar-Eintrag "Lagerverwaltung". Getestet: Migration lief, PHP-Syntax sauber, CLI-Test (Insert/Validierung/Deaktivieren) gegen echte Dev-DB grün, Deaktivieren-Sperre gegen Lager 1 (echter Bestand 9) korrekt verweigert, authentifizierter HTML-Fetch zeigt alle 3 Cards + Modal-Felder fehlerfrei. Kein visueller Screenshot möglich (fehlendes Playwright/Node auf der Maschine, Headless-Chrome verliert Session-Cookie zwischen Prozessaufrufen) — Jacky sollte einmal selbst durchklicken (Modal öffnen/schließen, Speichern, Deaktivieren-Button) zur visuellen Bestätigung.
Partner-Zuweisung (Dropdown im Partner-/Kunden-Formular auf unzugewiesene Lager) noch nicht gebaut — kommt wenn diese Formulare erweitert werden.

**Bugfix-Runde 2026-07-05 (nach Jackys Browser-Test):** Zwei echte Bugs gefunden und behoben:
1. **Speichern im Bearbeiten-Modal tat nichts** (kein Fehler, Modal blieb offen, Änderungen weg nach Neu-Öffnen): `LagerRepository::updateLager()` bekam `$data` mit mehr Keys (`aktiv`, `partner_id`, `kunde_id`) als die SQL-Query Platzhalter hatte → PDO warf `SQLSTATE[HY093]` (HTML-Fehlerseite statt JSON, `res.json()` im Frontend schluckte den Fehler lautlos). Gleiches Bug-Muster wie [[bug_hersteller_modal_insert]]. Fix: `updateLager()` bindet Parameter jetzt explizit als eigenes Array statt `$data` durchzureichen, `aktiv` ist jetzt auch Teil der SET-Klausel.
2. **Falsches Icon-Vorbild:** Ich hatte mich am Partner-Modul orientiert (⏸/▶-Toggle), Jacky erwartete das Hersteller-Muster (🗑️ + `confirm()`, Reaktivierung nur über das Aktiv-Häkchen im Bearbeiten-Modal, kein separater Aktivieren-Button). Umgestellt: `aktionen()` zeigt 🗑️ nur bei aktiven Lagern, JS-Funktion `statusDeaktivieren()` (vorher `statusToggle()`) fragt vorher nach Bestätigung.
3. **Nebenbei-Fix:** Die Bestands-Sperre (Deaktivieren nur bei Bestand=0) griff bisher nur beim 🗑️-Button (`setLagerAktiv()`). Da Reaktivieren/Deaktivieren jetzt auch über das Aktiv-Häkchen im Bearbeiten-Formular geht, prüft `aktualisiereLager()` dieselbe Sperre jetzt auch (gemeinsame private Methode `pruefeDeaktivierungErlaubt()`).

Alle vier Fälle (Update normal, Update mit Aktiv-Häkchen weg ohne Bestand, Update mit Aktiv-Häkchen weg MIT Bestand → blockiert, Lager 1 unverändert) per CLI-Test gegen echte Dev-DB verifiziert. **Von Jacky im Browser bestätigt (2026-07-05): passt.** Lagerverwaltungs-UI damit fertig.

**Wichtige Lektion:** PDO in dieser App wirft bei zusätzlichen, nicht in der Query referenzierten Array-Keys einen Fehler (keine stille Toleranz) — bei jeder neuen `update()`/`insert()`-Methode explizit nur die tatsächlich gebundenen Keys übergeben, nicht den rohen `$data`/`$_POST`-Array durchreichen.

## Online-Messelager ohne Offline-Kasse — funktioniert bereits (2026-07-05, keine Code-Änderung nötig)

Für Messen mit fixem Internet: `kassen.lager_id` und `kassen.modus` sind unabhängige Felder, `bon.php`/`KassenService` lesen `lager_id` schon jetzt aus der Kassen-Konfiguration (verifiziert im Code, nicht hart codiert). Ablauf: Bestand per normaler Umlagerung (`packplatz/intern/index.php`, kein Messe-Sync nötig) ins Messelager bringen → Kassen-Zeile mit `lager_id`=Messelager + `modus`='online' anlegen → normales `bon.php` bucht live. Rückkehr: normale Umlagerung Restbestand zurück, der spezielle `messe_rueckkehr.php`-Abgleich ist nur für den echten Offline-Fall nötig (dort wird während der Messe nichts live gebucht).
