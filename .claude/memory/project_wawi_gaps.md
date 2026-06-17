---
name: project-wawi-gaps
description: "Systemweiter WAWI-Benchmark (JTL/Shopware/Sage/LS-POS) — was noch nicht geplant ist, Stand 2026-06-08"
metadata: 
  node_type: memory
  type: project
  originSessionId: 2201806f-a656-4f8c-9f4f-9cf04a3cdd71
---

Referenz-Check 2026-06-08. Verglichen mit: JTL-WAWI, Shopware, Sage, LS-POS.
Fokus: was haben die Großen, was wir noch NICHT geplant haben.
Was bereits in db_design_entscheidungen oder project_status als geplant steht ist hier NICHT nochmal aufgelistet.

---

## Artikel + Varianten

| Feature | Priorität | Notiz |
|---|---|---|
| **Artikel-Texte** | HOCH | kurzbeschreibung TEXT, beschreibung LONGTEXT, technische_details TEXT — ohne das kein Shop |
| **Gewicht + Maße** | HOCH | gewicht_gramm, laenge_mm, breite_mm, hoehe_mm auf artikel — Pflicht für Versandkostenberechnung |
| **Versandklasse** | HOCH | versandklasse_id FK → versandklassen — steuert Versandkosten (Standard, Sperrgut, Download) |
| **Meldebestand** | HOCH | Auslöser für Bestellvorschlag — auf artikel (global) oder lagerbestand (pro Lager, JTL-Stil) |
| **Sicherheitsbestand** | MITTEL | Puffer der nie unterschritten werden soll |
| **Standardbestellmenge** | MITTEL | Wie viel normalerweise nachbestellt wird — Basis für Bestellvorschlag |
| **Lagerplatz** | MITTEL | Regal/Fach: `lagerplaetze (id, lager_id, bezeichnung, aktiv)` + lagerplatz_id FK NULL auf lagerbestand |
| **Tags / Schlagwörter** | MITTEL | Für interne Suche + Shop-Filterung — artikel_tags + artikel_tag_zuordnung |
| **Artikel-Zustand** | MITTEL | Neu / B-Ware / Sonderposten — VARCHAR auf artikel |
| **MPN** (Herstellerartikelnummer) | MITTEL | Google Shopping, Preisvergleich — Feld auf artikel |
| **Verfügbar-ab-Datum** | NIEDRIG | Pre-order Vorbereitung im Shop |
| **Max. Bestellmenge** | NIEDRIG | Shop-seitig: Limit pro Bestellung |
| **Farbcode des Herstellers** | MeaLana | z.B. Schachenmayr "Farbe 00125" — nicht EAN, nur deren Bezeichnung. Feld auf artikel_lieferanten oder artikel_codes? |
| **Nadel-Kompatibilität** | MeaLana | Welche Nadelstärke passt zu welchem Garn → Cross-Selling-Basis |

**Migrations-Vorschau Artikel:**
- `artikel`: + kurzbeschreibung, beschreibung, technische_details, gewicht_gramm, laenge_mm, breite_mm, hoehe_mm, versandklasse_id, meldebestand, sicherheitsbestand, standardbestellmenge, max_bestellmenge, verfuegbar_ab, mpn, artikel_zustand
- Neue Tabelle: `versandklassen (id, name, beschreibung, aktiv)`
- Neue Tabellen: `artikel_tags (id, name)` + `artikel_tag_zuordnung (artikel_id, tag_id)`

---

## Lager + Bestände

| Feature | Priorität | Notiz |
|---|---|---|
| **Lagerplätze** | MITTEL | `lagerplaetze (id, lager_id, bezeichnung, aktiv)` + `lagerbestand.lagerplatz_id FK NULL` |
| **Reservierungen** | HOCH | Durch Bestellungen gebundener Bestand — `lagerbestand.reserviert INT DEFAULT 0`. Verfügbar = bestand - reserviert |
| **Verfügbarer Bestand** | HOCH | Anzeige überall: nicht physisch, sondern was wirklich verkaufbar ist |
| **Umbuchungen** | MITTEL | Transfer zwischen Lagern — eigener Bewegungstyp 'umbuchung' + UI |
| **Sperrlager / Quarantäne** | NIEDRIG | Beschädigte Ware, Retouren in Prüfung — eigenes lager.typ Feld oder Flag |
| **Meldebestand Entscheidung** | HOCH | JTL: global auf `artikel` + optionaler Override auf `lagerbestand` pro Lager |

---

## Bestellwesen (Lieferantenbestellungen)

Noch nicht implementiert, DB-Kern skizziert in [[db-design-entscheidungen]].

| Feature | Priorität | Notiz |
|---|---|---|
| **Lieferschein-Nr.** | MITTEL | Vom Lieferanten — Feld auf bestellungen |
| **Rechnung-Nr.** | MITTEL | Vom Lieferanten — für Buchhaltungsabgleich |
| **Mahnwesen / Überfälligkeit** | MITTEL | Liste: "Diese Bestellungen sind überfällig" — einfache Abfrage |
| **Bestellvorschläge** | HOCH | Automatisch aus Meldebestand + Lieferzeit — braucht Meldebestand auf artikel |
| **Direktbestellung aus Wareneingang** | NIEDRIG | "Artikel eingebucht → direkt Nachbestellung?" |
| **Mehrere Angebote vergleichen** | NIEDRIG | Lieferant A vs. B vs. C |

---

## Lieferanten

| Feature | Priorität | Notiz |
|---|---|---|
| **Zahlungsbedingungen** | MITTEL | "30 Tage netto", "14 Tage 2% Skonto" — eigene Tabelle `zahlungsbedingungen`, FK auf lieferanten |
| **Lieferbedingungen / Incoterms** | NIEDRIG | EXW, CIF, DDP — einfaches Textfeld reicht |
| **Währung des Lieferanten** | MITTEL | CHF, USD, GBP — für EK-Preis in Fremdwährung (Wechselkurs-Tabelle folgt) |
| **Bankverbindung / IBAN** | MITTEL | Für Zahlungsverkehr — verschlüsselt speichern! |
| **UID-Nummer** | MITTEL | Für Buchhaltung / EU-Rechnung ohne MwSt |
| **Bewertung / interne Notiz** | NIEDRIG | Eigene Einschätzung — einfaches Notizfeld |
| **Lieferanten-Portal-URL + Login** | NIEDRIG | Wo man online bestellt — Login-Daten verschlüsselt |

---

## Kundendatenbank

Noch gar nichts implementiert. Geplante Grundstruktur:

| Feature | Priorität | Notiz |
|---|---|---|
| **Mehrere Adressen** | HOCH | Haupt, Lieferadresse, Rechnungsadresse — je mehrere pro Kunde |
| **Ansprechpartner** | MITTEL | Pro Firma mehrere Kontakte (analog lieferanten_vertreter) |
| **Kreditlimit** | MITTEL | B2B: wie viel Offenposten erlaubt |
| **Zahlungsart + Zahlungsbedingungen** | MITTEL | Stammkunde zahlt immer auf Rechnung etc. |
| **Rabatt pro Kunde** | MITTEL | On-top zur Kundengruppe (VIP -5% immer) |
| **DSGVO-Einwilligungen** | HOCH | Newsletter-Opt-in + Datum + Quelle — Pflicht! Consent-Log mit Timestamp |
| **Kundenstatus** | MITTEL | Aktiv / Gesperrt / VIP |
| **Geburtstag** | NIEDRIG | Marketing (Geburtstags-Gutschein) |
| **Kundenherkunft** | NIEDRIG | Shop / Messe / Empfehlung / Walk-in |
| **UID-Nummer** | MITTEL | B2B Pflicht für EU-Rechnung ohne MwSt |
| **Notizfeld** | MITTEL | Interne Kundennotizen |
| **Letzter Kauf / Letzter Kontakt** | MITTEL | Für Inaktiv-Analyse, abgeleitet aus Transaktionen |

---

## Kasse (JTL POS + LS-POS)

| Feature | JTL | LS | Priorität | Notiz |
|---|---|---|---|---|
| **RKSV / Fiskaly** | ✅ | — | PFLICHT | Österreich-Gesetz, bereits geplant |
| **Cash Management** | ✅ | ✅ | HOCH | Anfangsbestand, Einlagen, Entnahmen, Zählprotokoll |
| **Tagesabschluss / Z-Bon** | ✅ | ✅ | HOCH | Gesetzlich vorgeschrieben |
| **Bon parken** | ✅ | ✅ | HOCH | Kunde wartet, nächster dran |
| **Offline-Fähigkeit** | teilw. | ✅ stark | KRITISCH | Messen ohne Internet! Sync nach Wiederverbindung |
| **Retoure an der Kasse** | ✅ | ✅ | HOCH | Rückgabe direkt am POS |
| **Mitarbeiter-Tracking** | ✅ | ✅ | MITTEL | Wer hat was verkauft |
| **Shift Management** | — | ✅ | MITTEL | Schichtwechsel mit Übergabezählung (LS-spezifisch) |
| **Kundendisplay** | ✅ | ✅ | NIEDRIG | Zweiter Bildschirm — optional |
| **Charge-Auswahl beim Verkauf** | ✅ | — | HOCH | FIFO-Vorschlag, Kasse zeigt welche Charge — kritisch für Farbkonsistenz! |
| **Seriennummer-Zuweisung** | ✅ | — | MITTEL | Pflichtfeld wenn seriennummer_pflicht gesetzt |
| **Barcode-Druck / Preisetiketten** | ✅ | ✅ | MITTEL | Etikettendruck für neue Waren |
| **Gutscheine ausgeben + einlösen** | ✅ | ✅ | MITTEL | Eigenes Gutschein-Modul nötig |
| **Anzahlung / Deposit** | ✅ | ✅ | MITTEL | Für Strickaufträge — siehe Auftragsfertigung |

**MeaLana-spezifisch Kasse:**
- Offline bei Messen ist absolut kritisch — kein stabiles Internet, Kasse muss laufen, später sync'en
- Charge-Auswahl: Kunde kauft 5 Knäuel Merino Rot → müssen aus derselben Charge sein (Farbkonsistenz ist Verkaufsargument)

---

## Inventur

| Feature | Priorität | Notiz |
|---|---|---|
| **Blind-Inventur** | HOCH | Mitarbeiter sieht Soll-Bestand NICHT — verhindert unbewusste Bestätigung |
| **Permanente / rollierende Inventur** | MITTEL | Nicht alle auf einmal, laufend nach ABC-Kategorien |
| **Mehrere Zähler gleichzeitig** | MITTEL | Verschiedene Personen, verschiedene Bereiche |
| **Inventur-Sperre** | MITTEL | Während Inventur: andere Buchungen sperren oder erlauben? Entscheidung steht aus |
| **Differenzliste mit Begründungspflicht** | MITTEL | Große Abweichungen müssen kommentiert werden |
| **Zählliste nach Lagerplatz** | MITTEL | Erst sinnvoll wenn Lagerplätze implementiert |

---

## Neues Modul: Auftragsfertigung / Strickaufträge

Noch nie besprochen, kam 2026-06-08 auf.

MeaLana nimmt Strickaufträge entgegen (Make-to-Order). Vor Fertigungsstart zahlt der Kunde eine **Anzahlung (Deposit)**. Restbetrag bei Fertigstellung / Abholung.

| Feature | Notiz |
|---|---|
| **Auftragserfassung** | Kunde + Beschreibung + Materialien + Geschätzter Preis |
| **Deposit / Anzahlung** | Betrag + Datum + Zahlungsart — wird mit Endrechnung verrechnet |
| **Auftragsstatus** | Anfrage → Angebot → Anzahlung erhalten → In Arbeit → Fertig → Abgeholt/Bezahlt |
| **Materialzuordnung** | Welche Artikel/Garne gehen in diesen Auftrag |
| **Materialreservierung** | Beim Auftrag: Bestand reservieren (→ verfügbarer Bestand sinkt) |
| **Endrechnung** | Deposit abziehen, Restzahlung buchen |

**Why:** Spätestens beim Aufbau des Kassenmoduls (Retouren, Deposits, Teilzahlungen) brauchen wir das Konzept. Deposit-Buchung muss RKSV-konform sein.
**How to apply:** Als eigenes Modul nach Kasse planen. Kasse braucht "Anzahlung einlösen" als Zahlungsart.

---

## Noch nicht zugeordnet / offen

- **Wechselkurse** — wenn Lieferant in CHF/USD fakturiert: `wechselkurse (id, waehrung, kurs, datum)` — bei Bedarf
- **Versandmodul Details** — Österr. Post/PLC-Schnittstelle, DHL/DPD/GLS — bereits in Roadmap, kein Gap
- **Treuepunkte / Loyalty** — LS hat das, für MeaLana vielleicht Gutschein-System reicht
