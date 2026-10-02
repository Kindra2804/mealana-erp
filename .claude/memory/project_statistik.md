---
name: project-statistik
description: "Statistik-Seite FERTIG 2026-07-23 (Topseller/Umsatz-Zeitverlauf/Marge/Jahresvergleich) unter Verkauf-Sidebar; Lagerwert-Snapshot + Umsatz-Vorhersage bewusst zurückgestellt"
metadata: 
  node_type: memory
  type: project
  originSessionId: 455414e8-da96-4301-bb1d-33d964dd2133
  modified: 2026-07-23T13:00:01.674Z
---

## Statistik: Wo und wann

Der `📊 Statistik → #` Link aus der Artikel-Sidebar wurde entfernt (2026-06-19) — war ein toter Platzhalter ohne Plan.

Statistiken entstehen aus zwei Richtungen:

**1. Dashboard (globale Übersicht)**
- Lagerwert (Bestand × EK)
- Low-Stock-Warnungen (Meldebestand unterschritten)
- Tagesübersicht: Umsatz, Bestellungen, offene Lieferungen

**2. Verkauf-Modul (erst wenn Verkaufsdaten vorhanden)**
- Topseller nach Zeitraum
- Umsatz / Marge pro Artikel
- Preishistorie

**Was heute schon vorhanden ist (ohne Statistik-Seite):**
- Lagerbewegungen pro Artikel → im Lager-Tab von detail.php
- Marge pro Artikel → im Preise-Tab von detail.php
- Artikel nach Bestand sortierbar → via liste.php Filter

**Why:** Statistik ohne Verkaufsdaten ist leer. Erst Verkauf-Modul bauen, dann Statistik sinnvoll.

**How to apply:** Wenn Verkauf-Modul steht → Statistik-Link im Artikel-Sidebar reaktivieren mit echtem Target. Dashboard-Statistiken separat planen.

## ✅ Statistik-Seite FERTIG (2026-07-23)

Verkauf-Modul steht längst, Vorbedingung oben erfüllt. Jacky wollte ursprünglich sechs Bausteine (Topseller, Umsatz-Zeitverlauf, Marge, Jahresvergleich, Lagerwert-Snapshot, Umsatz-Vorhersage/"Ziel") — bewusst auf die ersten vier eingegrenzt, da die letzten zwei eigene, größere Themen sind (Lagerwert gehört ans Inventur-Modul als Abschluss-Snapshot, die Vorhersage hat Jacky selbst als "Lernkurve erforderlich" bezeichnet — braucht historische Datenbasis + eigenes Konzeptgespräch).

**Neu:** `src/modules/statistik/StatistikRepository.php` + `public/auftraege/statistik.php` (Sidebar-Link unter Verkauf). Zeitraum-Filter (Heute/7T/30T/Monat/Jahr/frei) + Kanal-Filter (Kasse/Online/Manuell/alle).

**Wichtiger Architektur-Fund:** Jeder Kassenverkauf wird beim Bon-Speichern 1:1 als `auftraege`-Zeile gespiegelt (`kanal='kasse'`, siehe `KassenService::erstelleBon()`) — dadurch deckt `auftraege`+`auftrag_positionen` ALLEIN sowohl Kasse als auch Online (`kanal='woocommerce'`) ab, keine zwei parallelen Datenquellen nötig.

**Echter Fund beim Testen:** `auftraege.kanal` hat einen DRITTEN Wert (`'manuell'`, 29 Testzeilen/400€ in der Dev-DB) — vermutlich Telefon-/Laden-Bestellungen ohne Kassenbon. Der erste Entwurf des Umsatz-Zeitverlaufs zeigte nur Kasse+Online als Balken, wodurch die Summe der Balken NICHT mit der angezeigten Gesamtsumme übereinstimmte (Lücke von ~325€ in einem Testmonat). Nachgezogen: dritter Balken/Filter "Manuell".

**Gleicher Fund auch im Dashboard bestätigt + behoben (noch am selben Tag):** `dashboard.php`s "Umsatz Heute/Monat"-Kacheln lasen nur `kassen_bons` + online `auftraege` mit `kanal='woocommerce'`, `kanal='manuell'` fehlte tatsächlich auch dort. Nachgezogen: eigene 📞-Zeile in beiden Kanal-Aufschlüsselungen (KPI-Karte + Detailkarte), in Kopfsummen/Trend-% eingerechnet. Render-Test bestätigt.

**Getestet:** Alle vier Repository-Methoden direkt gegen echte Dev-Daten (Topseller/Marge/Zeitverlauf/Jahresvergleich liefern plausible Werte). Volles Seiten-Rendering per CLI-Harness mit simulierter Session verifiziert (21KB HTML, keine PHP-Warnings, alle vier Sektionen vorhanden) — echter Stolperstein dabei: `session_start()` in `auth_check.php` überschreibt ein zuvor manuell gesetztes `$_SESSION` wieder mit leer, wenn man nicht selbst zuerst `session_start()` aufruft.

**How to apply:** Bei Wiedereinstieg (Lagerwert-Snapshot ans Inventur-Modul koppeln, Umsatz-Vorhersage eigenes Konzeptgespräch) diesen Abschnitt als Ausgangspunkt nehmen. Dashboard-`manuell`-Lücke bei nächster Gelegenheit mit Jacky klären.

## ✅ Lagerwert GEBAUT 2026-10-02 (nicht committed, Migration 192)
Jacky-Entscheidungen: Schnappschuss am Monatsende (Cron) + bei Inventur-START + Inventur-ABSCHLUSS (kein manueller Knopf); Bewertung = echter EK aus letztem Wareneingang (lager_bewegungen 'eingang' mit ek_preis), sonst Standardlieferant; ohne EK → 0 € + Warnliste; eigene Lager + Händler-Außenlager getrennt, Partnerware nie.
- Eigene Seite **Lager → Lagerwert** (`public/lager/lagerwert.php`, nicht in der Verkaufs-Statistik), Recht `buchhaltung.anzeigen` (EK = Finanzdaten); Sidebar-Items können jetzt `'recht' => '...'` tragen (shell_top blendet aus).
- `src/modules/statistik/LagerwertService.php`: berechne() (EK-Kette: wareneingang → standardlieferant → günstigster lieferant → vater/original → keiner), zusammenfassen(), festhalten() (transaktionsbewusst), festhaltenOhneAbbruch() (Inventur darf nie daran scheitern, loggt error), listenIdsOhneEk().
- Tabellen lagerwert_snapshots / _lager / _positionen (bewertete Liste je Artikel+Lager, CSV-Export `lager/lagerwert_csv.php?id=` bzw. ohne id = aktuell).
- `cron/lagerwert.php` (täglich ~23:30, wirkt nur am Monatsletzten; `--jetzt` zum Testen) — **muss noch im Windows-Aufgabenplaner eingetragen werden (Dev + Live)**.
- Artikelliste: Qualitätsfilter `kein_ek` ("Kein EK (mit Bestand)"), ~2 s.
- Dev-Stand: ~24.500 € netto, 3.401 Artikel, 734 ohne EK, nur 4 mit echtem Wareneingangs-EK (Wareneingänge kaum genutzt). EK überall netto — Kleinunternehmer-EK-brutto nirgends umgesetzt (auch nicht in Marge).
- Getestet: CLI-Probelauf, Cron --jetzt (2× → nur 1 Wert/Monat), Playwright (Seite, CSV 3.405 Zeilen, Listenfilter), Inventur starten+abschliessen in zurückgerollter Transaktion. Testwerte gelöscht (0 Schnappschüsse).
