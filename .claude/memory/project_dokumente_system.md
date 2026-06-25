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
