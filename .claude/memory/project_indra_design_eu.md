---
name: project-indra-design-eu
description: "Indra-Design = EU von Babsi (KU), verkauft online im eigenen Namen über das KG-ERP — 2026-11-13 gebaut (Migr. 237, Partner online_verkauf), Stichtag 15.11., Live-Einspielen offen"
metadata:
  node_type: memory
  type: project
  originSessionId: 349ed156-6550-4575-9112-f33373ee65da
  modified: 2026-11-13T09:32:01.948Z
---

**Ausgangslage (Jacky 2026-11-13):** Gewerbeschein „Kunsthandwerk“ liegt bei Babsi persönlich → Indra-Design wird **Einzelunternehmen (EU) von Babsi**, **Kleinunternehmerin**, Stichtag **15.11.2026**. Produkte bleiben im ERP der MEALANA KG, das EU ist dort Partner (Fremdrechnung, fremder Name/fremde Rechnung). Babsi ist auf **Live schon als Partner angelegt** (Fremdrechnung, KU).

**Jackys Entscheidungen:**
1. ALLES Indra gehört dem EU (auch Metalldosen — veredelt, keine Handelsware).
2. Geld vorerst auf das KG-Konto; Kontodaten stehen beim Partner (Felder iban/bic/bank_name) → Belege/Mails zeigen sie.
3. **Versand/Porto bekommt die KG — Gegenverrechnung** mit dem EU (später evtl. eigenes Paketmarken-Konto des EU). Damit entfällt die geplante Monatsrechnung „Versand“.
4. Shop schickt Rechnung im Layout des EU, eigener Nummernkreis; Kürzel in den Partner-Einstellungen (5–10 Zeichen, eindeutig, ab 1. Beleg gesperrt) → `R-INDRA-2026-00001`.
5. **Gutscheine** (nur MeaLana-Seite) dürfen für Partnerware eingelöst werden; die KG zahlt den eingelösten Betrag an das EU aus.
6. SOW verkauft kein Indra; MeaLana-Shop vielleicht (klärt Jacky). Indra-Design hat keine Gutscheine.
7. Konfigurator soll generell wählen können: Artikel der Hauptfirma oder eines Partners (auch für „echte“ Partner später).

**Gebaut 2026-11-13 (Commits 404f3aa … 085654c), Migration 237:**
- Partner: `online_verkauf`, `nr_kuerzel`, bic/bank_name/website/steuernummer; Fix: abgehakte Kontrollkästchen (KU) wurden beim Bearbeiten überschrieben.
- `ArtikelEigentumService` + Massenaktion „Eigentümer setzen“ + Konfigurator-Karte „Gehört: … ändern“; Kinder + fertige Schilder wechseln mit, Bestand per `umbucheZwischenLager` ins Partner-Lager. FertigesSchildService erbt Eigentümer/Lager. Online-Partner-Artikel normal in der Artikelliste.
- Shop-Sync: Online-Partnerware erlaubt, Bestand inkl. Partner-Lager.
- Aufträge: `auftraege.partner_id`; nur EIN Eigentümer je Auftrag; KU → Positionen + Versand 0 % (Versandsteuer::aufteilen $ohneUst).
- Belege: DokumentService::partnerDaten/belegKuerzel, Kopf ohne „Ein Unternehmen der MEALANA KG“, KU-Text des Partners, Fußzeile ohne leere Felder. Mails: `Mailer::fuerPartnerAuftrag()`.
- Packplatz Ab-/Rückbuchung Partner-Lager (vorher fest Lager 1).
- Export: Partner-Belege kein Erlös; Versand 3710 an Versanderlös + USt; Zahlungen/Gutschein-Einlösungen gegen 3710. Partner-Konto: + online bezahlt − Versand.
- Tests: CLI mit Rollback (Kürzel, Eigentümer hin/zurück, Auftrag KU, Rechnungs-PDF R-TINDR-…, Export + Saldo). Handbücher 08_partner.md + bedienungsanleitung #partner-online.

**Offen / Grenzen:**
- Live: Paket mit Migration 237 einspielen; bei Babsi „verkauft online“ + Kürzel INDRA + Adresse/Bank; Indra-Artikel per Massenaktion übergeben (zum Stichtag); Woo indra-design auf Kleinunternehmer (Germanized, Steuer 0 %), Impressum/AGB/Widerruf/Datenschutz auf das EU (Texte von Jacky/Babsi).
- **Steuerberatung bestätigen:** Gegenverrechnung Versand (widerspricht der früheren Antwort B9 „nie gegenverrechnen“ bei Fachmiete), Übergang des KG-Bestands an das EU (Verkauf/Entnahme?), Gutschein-Auszahlung.
- An der Kasse bezahlte Partner-Aufträge laufen als RKSV-Zahlbeleg der KG — nicht angepasst. scan.php prüft Chargen nur in Lager 1 (Indra ohne Chargen).
- Verkaufsaufstellung (Abrechnung-PDF) listet nur Kassenverkäufe, Online-Aufträge nur als Summe.

Verwandt: [[project_partner_modul]], [[project_konfigurator_modul]], [[project_indra_design_shop]], [[project_kleinunternehmer]], [[project_steuerprofil]].

**⏸ ZWISCHENSTAND 2026-11-13 (Abbruch wegen API-Fehler, Commit „wip“) — HIER WEITERMACHEN:**
Jacky-Korrekturen: Versandkosten NICHT gegenverrechnen, sondern **Monatsrechnung KG → Partner wie Fachmiete** (Betrag = Versand brutto der Partner-Rechnungen des Vormonats minus Korrekturen, 20 %, normale KG-Rechnung R-…); Fachmiete wie gehabt (auch 0 €); Gutschein-Einlösung = Durchlauf (schon so); Bestandsübergang = Entnahme/wie Falscheingabe (Umbuchung reicht); gemischte Bestellung (MeaLana-Shop) → **2 Aufträge** mit getrennten Rechnungen (erst beim MeaLana-Umstieg bauen); reiner Indra-Auftrag hat keinen Abholort.
- Erledigt: Migration 238 (Tabelle partner_versand_rechnungen UNIQUE partner+periode, Artikel VERSAND-PARTNER Gruppe Versandkosten 4090, auf Dev eingespielt); MietRechnungService::kundeSicherstellen/kundenSnapshot public.
- Offen: 1) PartnerVersandService (offene Monate + verrechnen wie MietRechnungService::rechnungFuerPartner: Auftrag mit je Partner-Rechnung einer Position, erbracht/abgeschlossen, erstelleVollrechnung, Leistungsdatum Monatsende, Zeile in partner_versand_rechnungen); DokumentService ist_fachmiete auch für partner_versand_rechnungen. 2) Knopf im Partner-Konto + Aufruf in cron/fachmiete.php. 3) Gegenverrechnung ENTFERNEN: BuchhaltungExportService Block 3 Partner-Zweig nur `continue` (keine Versandbuchung), PartnerKontoService versand-Abzug + Anzeige (detail.php, abrechnung.html.twig) raus. 4) Test, Handbuch anpassen (08_partner + bedienungsanleitung sagen noch „Gegenverrechnung“), Paket 1.0.10.

