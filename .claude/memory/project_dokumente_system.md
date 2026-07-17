---
name: project-dokumente-system
description: "Vollständiger Dokument-Katalog: Typen, Technik, Prioritäten, WooCommerce-Verhalten"
metadata: 
  node_type: memory
  type: project
  originSessionId: 3bbaa246-5729-4e26-8fb8-4785822652ed
---

## Technik-Grundlage

- **Twig + Dompdf** für alle A4-PDFs (reines PHP, kein externes Binary)
- **Partials**: `erp/templates/dokumente/_partials/` — _header, _positionen, _steuerblock wiederverwendbar
- **Speicherort fertige PDFs**: `erp/storage/dokumente/{auftrag_id}/`
- **DB-Tracking**: Tabelle `auftrag_dokumente` (Typ, Dateiname, erstellt_am, erstellt_von)
- **Code**: `erp/src/modules/dokumente/` — DokumentService, PdfGenerator, DokumentRepository

## Gruppe A: Standard A4 — Kaufmännisch/Legal (Twig + Dompdf)

| Dokument | Kürzel | Anmerkung |
|----------|--------|-----------|
| Auftragsbestätigung | AB-2026-XXXXX | |
| Rechnung | R-2026-XXXXX | AT UStG §11; Kleinunternehmer-Variante ohne Steuerausweis |
| Gutschrift | GS-2026-XXXXX | = Rechnungskorrektur (gleiches Dokument, Titel konfigurierbar) |
| Anzahlungsrechnung | ANZ-2026-XXXXX | für Strickaufträge; Restbetrag-Hinweis; eigene Nummer |
| Mahnung Stufe 1+2 | — | kommt wenn erste Rechnungszahler im System |
| Abrechnung Mietfach | — | aus Partner-Modul heraus; monatlich/quartalsweise |
| Spendenübersicht Yarnpride | — | einfache Liste, einmal im Jahr |

**Gutschrift = Rechnungskorrektur**: In AT dasselbe Dokument (§11 UStG). Interner Typ gleich, Titel konfigurierbar je nach Kontext.

## Preisanzeige auf Dokumenten

- **B2C** (kein UID-Nummer beim Kunden): E-Preis + Gesamt in **Brutto** in der Positionstabelle
- **B2B** (Kunde hat UID-Nummer): E-Preis + Gesamt in **Netto** in der Positionstabelle
- **Steuerblock Footer**: immer Netto + MwSt + Brutto (AT UStG §11 Pflicht), egal ob B2C oder B2B
- **Gemischte Steuersätze**: je Block pro Steuersatz (10% + 20% separat)
- **Kleinunternehmer-Variante**: kein Steuerblock, kein MwSt-Ausweis (eigenes Template)
- Erkennung B2B: `kunden.uid_nummer IS NOT NULL`

## Abholzettel (Gruppe B — eigener Dokumenttyp!)

Kein Missbrauch der Rechnung mehr. Eigenes Dokument wenn `lieferart = abholung` + `lieferstatus = abholbereit`.

Inhalt: Logo, Kundendaten, Positionsliste (Brutto), Gesamtbetrag, Zahlungsstatus-Box (BEREITS BEZAHLT / Bitte an der Kasse bezahlen), **Code-128 Barcode der Auftragsnummer**.

Barcode-Logik: POS scannt Barcode → erkennt Prefix "A-" → öffnet Auftrag direkt. Kein Farb-Druck nötig (Toner sparen).

Technik: `picqer/php-barcode-generator` → PNG → Dompdf `<img>`.

## Gruppe B: Listen/Formulare A4 (Twig + Dompdf, Lager/Logistik)

| Dokument | Anmerkung |
|----------|-----------|
| Lieferschein | kommt mit Auftragsmodul |
| Pickliste | Checkboxen, nach Auftrag; kommt mit Auftragsmodul |
| Inventurliste | alle Artikel + Ist-Leerfeld; parallel zur mobilen Inventur-App |
| Adressetiketten | A4-Layout wenn kein Sichtkuvert (Päckchen zu klein) |
| Preisliste | optional, nach Kategorie gefiltert |

## Gruppe C: Etiketten — eigenes Modul, Technik-Entscheidung offen

| Dokument | Anmerkung |
|----------|-----------|
| Artikeletikett Regal | Regalbeschriftung: Name + Preis + EAN, mehrere pro A4 |
| Klebeetiketten (div. Größen) | z.B. 30×20mm, 50×30mm — mm-genau |

**Offen**: Label-Drucker (ZPL/ESC-POS) vs. A4-Etikettenbogen (Avery-Format, Dompdf kann mm-genaue Layouts).
Dieses Modul wird separat geplant — nicht mit dem Auftragsmodul.

## Gruppe D: Gutschein-Design — kommt mit Gutschein-Modul

- Kunde wählt Design (mehrere Twig-Templates: blumen, minimalist, ...)
- Freitextfeld für persönliche Nachricht
- Kein kaufmännisches Dokument → eigene Logik im Gutschein-Modul

## Zentrales Dokumentenarchiv

Geplant: "..." Menü → **Dokumente** — globale Liste über alle Aufträge hinweg.
Filter: Typ (AB / Rechnung / GS / Mahnung / Alle) + Zeitraum (wichtig für Buchhalter/DATEV-Export).

```
[ AB ] [ Rechnung ] [ Gutschrift ] [ Mahnung ] [ Alle ]   Zeitraum: [Monat▼]

Datum       Nummer          Kunde           Auftrag       Typ           
2026-06-25  R-2026-00042    Muster Maria    A-2026-00041  Rechnung     [PDF] [↗]
2026-06-24  AB-2026-00041   Muster Maria    A-2026-00041  Auftr.best.  [PDF]
2026-06-23  GS-2026-00003   Huber Franz     A-2026-00038  Gutschrift   [PDF]
```

## Partner-Dokumente

Mietfach-Abrechnung + Konsignations-Abrechnung: werden im **Partner-Modul** abgelegt (nicht in auftrag_dokumente).
Eigene Tabelle oder Erweiterung partner_dokumente wenn gebaut.

## Gutschrift-Workflow (implementiert 2026-06-25)

- **Auslöser**: Button "Gutschrift erstellen" neben der gesperrten Rechnungszeile in `auftraege/detail.php`
- **Formular**: `auftraege/gutschrift_erstellen.php` — Vollstorno oder Teilgutschrift (Positionen + Mengen)
- **Service**: `DokumentService::erstelleGutschrift()` — GS-Nummer (GS-2026-XXXXX), PDF, DB-Eintrag
- **Vollstorno**: setzt `rechnungen.storniert=1` + `auftraege.zahlungsstatus='erstattet'`; danach ist Rechnung-Button wieder aktiv
- **Lager**: optionales Rückbuchen in `lager_bewegungen` + `lagerbestand` (Standard-Lager id=1)
- **Kassa-Wiederverwendung**: Kassa ruft denselben `DokumentService` auf — nur anderes UI-Frontend (kein neues Repo/Service nötig)
- Für Rückgabe ohne ERP-Auftrag (Barkauf): `erstelleRueckgabeOhneAuftrag()` kommt später im Kassa-Modul

## Lücken gefunden 2026-07-04 (Bestandsaufnahme aller Vorlagen)

Alle bestehenden Dokumente/Mails geprüft (17 Templates unter `erp/templates/`) — jedes wird tatsächlich irgendwo im Code ausgelöst, keine toten Vorlagen. Zwei echte Lücken aber gefunden:

1. ✅ **BEHOBEN 2026-07-10** — Einkaufsseite hatte gar kein Dokument/Mail: `public/bestellungen/` (Lieferanten-Bestellungen) hatte weder PDF-Erzeugung noch Mailversand. Jetzt gebaut: `BestellDokumentService` (eigenständig, nicht über `DokumentService` — dort ist die Richtung fest auf "Kunde empfängt" ausgelegt) erzeugt eine Bestellungs-PDF (`templates/dokumente/bestellung/standard.html.twig`), neue Tabelle `bestellung_dokumente` (Migration 124). Zwei bewusst getrennte Aktionen in `bestellungen/detail.php`: "PDF erstellen" (öffnet nur zum Ansehen/Drucken, kein Mail-Zwang — wichtig für Lieferanten, bei denen über deren B2B-Portal bestellt wird) und pro Dokument "Per Mail senden" (eigene Vorschau-Seite mit editierbarem Empfänger/Betreff/Text vor dem tatsächlichen Versand, nicht automatisch wie bei Auftragsbestätigung/Rechnung). Mehrfach-Erstellung bewusst erlaubt (Bestellung ändert sich oft noch vor Wareneingang). Getestet: PDF-Erzeugung end-to-end gegen echte Bestellung mit Positionen (Twig-Rendering, Summen), Testartefakte aufgeräumt; tatsächlicher Mail-Versand bewusst nicht ausgelöst.
2. **Manueller Storno ohne Kundenmail**: `auftraege/stornieren.php` (Mitarbeiter storniert von Hand) sendet keine Mail an den Kunden. Nur die automatische Mahnwesen-Stornierung (30+ Tage unbezahlt, `mahnwesen/stornierung.html.twig`) hat eine Vorlage — ein Auftrag der aus anderen Gründen (Lagerausfall, Kundenwunsch) manuell storniert wird, bekommt keine Benachrichtigung.

**Why:** Beim Rundum-Check der A4-Rechnung/Mailversand-Themen (2026-07-04) aufgefallen, auf Jackys Bitte hin systematisch alle Vorlagen durchgeschaut.
**How to apply:** Punkt 2 weiterhin nicht von selbst angehen — vorgemerkt bis Jacky es aktiv anspricht.

## Lücke gefunden 2026-07-04: Nummernkreise nirgends konfigurierbar/einsehbar

Beim Testen der Offline-Kasse aufgefallen (Jacky fragte nach den Kassenbon-Nummernkreisen). Zwei verschiedene Mechanismen, beide ohne jede Admin-Oberfläche:
- **Kassenbons** (`K1-2026-000024` etc.): `KassenService::naechsteBonNr()` zählt `COUNT(*) FROM kassen_bons WHERE kasse_id=X` hoch — korrekt pro Kasse getrennt, aber theoretisches Race-Condition-Risiko bei exakt zeitgleichen Verkäufen auf derselben Kasse (kein atomarer Zähler).
- **Dokumente** (Rechnung/AB/Gutschrift, `R-2026-XXXXX` etc.): `DokumentRepository::naechsteNummer()` nutzt eine echte atomare Zählertabelle `dokument_nummern` (typ+jahr+praefix+letzt_nr) — sicherer als die Kassenbon-Variante, aber **komplett global pro Dokumenttyp**, nicht pro Shop/Kanal, und ebenfalls nirgends einsehbar oder konfigurierbar.

**Why:** Für Buchhaltung/DATEV und Mehr-Kanal-Betrieb (mehrere Shops) könnte eine Trennung/Konfigurierbarkeit relevant werden.
**How to apply:** Kassenbons ggf. später auf dasselbe atomare Muster umstellen statt COUNT(*) — noch offen.

## ✅ Nummernkreise-Verwaltungsseite FERTIG (2026-07-09)

Neuer Tab "Nummernkreise" in `einstellungen/index.php` (gleicher Stil wie der bestehende Kassen-Tab, keine eigene Repository-Schicht — reine SQL-Queries direkt im Tab, passend zur Einfachheit der Tabelle):
- **Dokument-Nummernkreise**: Liste aller `dokument_nummern`-Zeilen (Typ, Jahr, Präfix, letzte Nr., berechnete nächste Nummer), Bearbeiten-Modal für Präfix + letzte Nr. mit Warnhinweis. Handler: `nummernkreis_aktualisieren.php`.
- **Kassenbon-Nummernkreise**: nur informativ (nicht editierbar), da COUNT(*)-basiert statt Tabellen-gestützt — zeigt aktuellen Jahres-Stand pro Kasse.

Getestet: Update-Logik per CLI gegen echte Dev-DB (Zeile temporär geändert, verifiziert, zurückgesetzt).

## WooCommerce und Rechnungen

**WC macht KEINE Rechnungen** (vanilla). WC-Bestellbestätigung ist keine gültige Rechnung nach UStG §11.

Unser Ansatz:
- WC sendet automatisch seine Bestellbestätigung (kein Eingriff)
- Wir importieren Auftrag ins ERP
- WIR generieren Rechnung + senden als PDF-Anhang (Versandbestätigung-Mail)
- WC bekommt nur Status-Update (bezahlt/versendet)
- WC-Rechnungs-Plugins NICHT installieren (würde zweites Rechnungssystem erzeugen)

**Why:** Single Source of Truth für Buchhaltung — alle Rechnungsnummern kommen aus dem ERP.
**How to apply:** Bei WC-Integration-Diskussionen: WC = Auftragseingang, ERP = Buchaltung + Dokumente.
