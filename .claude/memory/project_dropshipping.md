---
name: project-dropshipping
description: "Dropshipping-Modul (Lizenzmodul 190 €) — GEBAUT 2026-11-01 (Migr. 215), Klicktest Jacky offen, nicht committed; Plan/Entscheidungen unten"
metadata:
  node_type: memory
  type: project
  originSessionId: fcbfc84b-090a-49f4-9be5-4527b0b6f009
  modified: 2026-10-31T18:13:47.379Z
---

Entschieden im Modulgespräch Jacky + Babsi am 2026-10-30: Dropshipping (Lieferant liefert direkt an den Endkunden, Streckengeschäft) wird ein **eigenes Lizenzmodul um 190 €**, siehe [[project-lizenzierung-preise]]. Vorgabe Jacky: JTL-Ablauf als Basis, gerne verbessern.

## ✅ Stand 2026-11-01: gebaut, Klicktest offen
- Migration 215: `lieferanten.dropshipping_aktiv` + `dropship_pauschale`; `artikel_lieferanten.dropshipping` (NULL=wie Lieferant, 0=ausgenommen, 1=ja — Jacky weiß nicht, ob alle Lang-Artikel gehen, deshalb beides); `bestellungen.typ/auftrag_id/lieferadresse_snapshot/dropship_pauschale`; `auftrag_lieferungen.bestellung_id`. Lieferant 36 "Lang Garn & Wolle GmbH" auf REO-Daten des Herstellers Lang Yarns korrigiert (Korschenbroich DE, eu_igl, 10 Tage) — auch in der Migration, Live bekommt es beim Deploy
- Migration 214 war auf Dev eingespielt, aber nicht in schema_migrations vermerkt → nachgetragen
- Code: `src/modules/dropshipping/DropshippingService.php` (pruefen/bestellen/stornieren/versendetMelden/streckeBereitIds), Karte `public/auftraege/_dropshipping_karte.php` + `dropshipping_aktion.php` + `js/dropshipping.js`; Versandmail als `src/modules/auftraege/VersandMail.php` herausgelöst (Packplatz nutzt sie jetzt auch)
- Auftragsliste Filter Lieferung: "Strecke bereit" / "bestellt, Tracking offen"; ausgeblendet in Pickliste, Packplatz-Direktliste, Scan gesperrt, Wareneingang (b.typ='normal'); Bestellungen-Liste/Detail zeigen "Strecke" + Link zum Auftrag, kein PDF/Wareneingang
- Reservierungen werden beim Bestellen geschlossen, beim Storno wieder geöffnet; Versendet: kein Lieferschein (Lang-Layout), Rechnung + Versandmail an den Kunden wie beim Packplatz
- Kunden-Lieferadresse: erste Adresse mit Straße (Liefer → Rechnung → Kunde)
- Service-Test mit Wegwerfauftrag ok (bestellen/doppelt/storno/neu bestellen/ohne Tracking), aufgeräumt; **"Versendet + Tracking" noch nie echt durchgelaufen → Klicktest Jacky** (verbraucht Rechnungsnummer)
- Pickliste: Badge "Strecke möglich" (Link zum Auftrag), nur Info. Jacky 2026-11-01: Strecke ist für Exoten-Garne, die selten bestellt werden; häufige Artikel bestellen wir selbst und verschicken sie, also ist es ok, dass mögliche Strecken-Aufträge in der Pickliste bleiben
- Handbücher: docs/handbuch/15_dropshipping.md + bedienungsanleitung.php #dropshipping
- Offen: Lizenz-Sperre kommt mit Lizenzserver; Wareneinsatz-Buchung mit Steuerberatung; Lang-Importer ganz zum Schluss

Reihenfolge danach: Lizenzserver → 1.0-Check, siehe [[project-roadmap-reihenfolge]].

## Referenz-Check (2026-10-31)
**JTL-Wawi** (Claude-Wissen, offizielle Guide-Seiten nur teilweise gefunden → mit Jackys JTL-Praxis abgleichen):
- Dropshipping-Haken je **Artikel-Lieferant-Zuordnung** (guide: "enabling drop shipping for items"), beim Lieferanten Dropshipping-Länder in den Lieferbedingungen
- Lieferantenbestand kann in die Verfügbarkeit / den Shop-Bestand einfließen
- Aus dem Auftrag wird eine **Dropshipping-Bestellung** an den Lieferanten erzeugt (manuell oder per Workflow), mit der **Kunden-Lieferadresse**. Gemischte Aufträge: Lagerware normal, Dropship-Positionen separat
- Lieferant liefert, dann wird in der Wawi „geliefert“ gebucht → Auftrag gilt als versendet (ohne Lagerbuchung), Tracking von Hand, Rechnung normal
- Schwäche laut Praxisblog (ebakery): der Rückweg vom Lieferanten (Import/Status) ist mühsam
**Xentral** (help.xentral.com): Modul „Dropshipping-Lieferant“ übergibt Aufträge direkt an externe Lieferanten; **vom Lieferanten kommt kein Status zurück**, Tracking nur über eigenes Übertragungsmodul (SFTP/XML-Import) oder API (Tracking-Nr. an Versand-Endpunkt). Option: Shop-Bestellung gleich auf „versendet“ setzen.
**Gemeinsame Erkenntnis:** Versand an den Lieferanten ist überall einfach, die Rückmeldung (versendet + Tracking) ist überall die Schwachstelle → dort können wir besser werden.

## Was es im ERP schon gibt (Andockpunkte)
- `artikel_lieferanten` (EK, Lieferzeit, standard_lieferant …) → hier kommt das Dropshipping-Flag hin
- `lieferanten` (Adresse, E-Mail, Zahlungsziel …), Lieferanten-Bestellung inkl. PDF ([[project_dokumente_system]], [[project_bestellmodul]])
- `bestellungen` (Status entwurf/offen/teilgeliefert/erledigt/storniert), `bestellung_positionen`
- Teillieferungen: `auftrag_lieferungen` + `auftrag_lieferung_positionen` ([[project_packplatz]]), Rechnung aus `menge_geliefert` → Dropship-Lieferung kann eine Lieferung **ohne Lagerbuchung** sein, die Rechnung läuft dann unverändert
- Shop-Sync Versandstatus ERP→Woo ([[project_zahlung_erp_an_shop]]), Tracking-Mail-Logik von PLC ([[project_plc_versand]])

## Konzeptentwurf (Claude, noch nicht abgenommen)
1. **Stammdaten:** `artikel_lieferanten.dropshipping` (0/1); `lieferanten.dropshipping_email` + Option „neutraler Lieferschein mitschicken“
2. **Auftrag:** Position mit Dropship-Artikel → Hinweis/Badge „Strecke“; Button „Dropshipping bestellen“ (später optional automatisch beim Auftragseingang/Zahlungseingang)
3. **Dropship-Bestellung** = normale `bestellungen`-Zeile mit `auftrag_id` + `lieferadresse_snapshot` + Typ 'dropshipping'; PDF an Lieferant mit Kundenadresse + optional neutraler Lieferschein im Shop-Design (Kürzel/Logo je Shop vorhanden)
4. **Rückmeldung (Verbesserung ggü. JTL/Xentral):** Bestellung „versendet“ melden mit Tracking-Nr. (manuell, ein Klick) → erzeugt Auftrags-Lieferung ohne Lagerbuchung → Lieferstatus/Teillieferung, Shop-Sync „completed“ + Tracking-Mail an Kunden, danach Rechnung wie gewohnt. Später: Tracking-Link für den Lieferanten (Formular ohne Login) oder CSV-Import
5. **Lager:** keine Lagerbewegung; Bestand im Shop: optional Lieferantenbestand (offen)
6. **Packplatz:** Dropship-Positionen nicht in der Pickliste, Auftrag mit nur Strecke gar nicht
7. **Buchhaltung:** Lieferanten-Rechnung wie normale Bestellung (Kreditor), Wareneinsatz statt Lagerzugang (mit Steuerberatung klären)

## Jackys Antworten (2026-10-31) — gelten vor dem Konzeptentwurf oben
1. **Nur Lang Yarns.** Bestellt wird im **Lang-Händlerportal** (Dropshipping ist dort ein eigener Menüpunkt) → wir schicken KEIN PDF/Mail an Lang. Abrechnung anders als normale Bestellung: **eigene Preise (Bearbeitungsgebühr) + Versand wird weiterverrechnet**
2. Absender = wir, aber **Lang-Layout** → wir brauchen KEINEN eigenen neutralen Lieferschein
3. Kein Rückkanal: im Portal wird **unsere eigene E-Mail** angegeben → wir bekommen die Versandmail (die sonst der Kunde bekäme) und tragen Tracking bei uns ein → manueller „Versendet + Tracking“-Klick ist genau richtig
4. **Keine gemischten Aufträge:** wenn nicht alles per Strecke geht → normal bestellen (normaler EK) und gemeinsam verschicken. Strecke nur, wenn der GANZE Auftrag Lang-Dropship-fähig ist
5. Bestellzeitpunkt: ~90 % **nach Vorkasse-Zahlungseingang**; Ausnahme Ladenbestellung mit Zahlungsart Rechnung (z.B. Kunde aus Wien) → sofort
6. Kein Lagerstand von Lang → Shop wie bisher „Lieferzeitverzögerung möglich“, nichts Neues
7. **Retouren kommen zu uns** → normaler Retouren-Ablauf
- Später (ganz zum Schluss): **Importer für Langs Artikelliste** bauen, „einige Dinge zu beachten/automatisieren“ → Liste dann gemeinsam anschauen

## Angepasster Plan (nach den Antworten)
- Stammdaten: `artikel_lieferanten.dropshipping`; Lang-Dropship-Konditionen (Gebühr, Versand) beim Lieferanten
- Auftrag: Badge „Strecke möglich“ nur wenn ALLE Positionen bei Lang dropship-fähig; Liste „Strecke bereit“ = bezahlt ODER Zahlungsart Rechnung
- „Dropshipping bestellen“ = Bestellung (Typ dropshipping, auftrag_id) anlegen + Ansicht zum Abtippen/Kopieren ins Portal (Kundenadresse, Artikelnr., Mengen) — kein Versand an Lang
- „Versendet + Tracking“ → Lieferung ohne Lagerbuchung, Shop-Sync, Tracking-Mail, Rechnung
- Packplatz: Strecken-Aufträge komplett ausblenden
- Lieferanten-Rechnung: Dropship-EK + Gebühr + Versand → Wareneinsatz (Steuerberatung)
- Lang verschickt (laut Jacky) **aus Deutschland** → EU-Lieferung, kein Zoll; Lieferanten-Rechnung = innergemeinschaftlicher Erwerb (Reverse Charge) wie bei anderen DE-Lieferanten
- Lang-CSV: sehr viele (teils unnötige) Spalten, enthält Zolltarifnummern u.ä., teils ungünstig formatiert → Thema für den Importer ganz am Schluss
- **Lang-Dropship-Rechnung (Jacky 2026-10-31):** Artikel zum **normalen EK** (kein eigener Dropship-EK nötig!) + **ein Posten pro Lieferung** ca. 11 € netto (Bearbeitung + Versandanteil zusammen). Rechnung aus DE, **IGL** (innergemeinschaftliche Lieferung, Reverse Charge), **10 Tage netto**
  → Stammdaten: nur `lieferanten.dropship_pauschale` (Default ~11 €, auf der Bestellung änderbar); Pauschale als Zusatzposition auf der Dropship-Bestellung, damit Bestellwert = Lieferanten-Rechnung
- Alle Fragen geklärt, Bau kann starten
