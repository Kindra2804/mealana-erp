---
name: project-indra-design-eu
description: "Indra-Design = EU von Babsi (KU), verkauft online im eigenen Namen über das KG-ERP — 2026-11-13 gebaut (Migr. 237+238, Versand als Monatsrechnung), Paket 1.0.10, Stichtag 15.11., Live-Einspielen offen"
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

**Korrekturen Jacky 2026-11-13 (Nachmittag) — umgesetzt, Commit 0f9ff57, Paket 1.0.10 (Migr. 236–238):**
- Versand NICHT gegenverrechnen → **Monatsrechnung KG → Partner wie Fachmiete**: `PartnerVersandService` (je Partner + abgeschlossenem Monat eine normale R-Rechnung, 20 %, Artikel VERSAND-PARTNER, eine Zeile je Partner-Rechnung, Gutschrift-Versand als Minuszeile, Leistungsdatum Monatsende, `partner_versand_rechnungen` UNIQUE partner+periode; Summe ≤ 0 → keine Rechnung). Knopf Partner → Konto & Auszahlung, Lauf in `cron/fachmiete.php`. Rechnung ohne Versandart/Abholhinweis (DokumentService ist_fachmiete).
- Gegenverrechnung entfernt (Export: Partner-Belege komplett übersprungen; Partner-Konto/Abrechnungs-PDF ohne Versand-Abzug). Handbücher angepasst.
- Fachmiete wie gehabt (auch 0 €); Gutschein-Einlösung = Durchlauf; Bestandsübergang = Umbuchung reicht; reiner Indra-Auftrag ohne Abholort.
- Getestet per CLI (Savepoint-PDO, Rollback): 2 Partner-Rechnungen Okt. → KG-Rechnung 11,40, Export 4090 + USt, kein Doppellauf. Gutschrift-Minuszeile nur per SQL-Logik, nicht echt getestet.
- **Später (MeaLana-Umstieg):** gemischte Bestellung → 2 Aufträge mit getrennten Rechnungen.
- **Offen:** 1.0.10 auf Live einspielen, dann Babsi-Partner einstellen (online + Kürzel INDRA + Adresse/Bank), Indra-Artikel per Massenaktion übergeben (Stichtag 15.11.), Woo KU/Rechtstexte, Steuerberatung (Bestandsübergang, Gutschein-Auszahlung).

**Stand 2026-11-13 abends:** Paket **1.0.11** gebaut (enthält 1.0.10, Migr. 236–238) – Jacky spielt es auf Live ein, ändert für indra noch nichts. Gewerbeschein Babsi besteht schon **über 10 Jahre** (15.11. = Start Online-Verkauf über das EU, nicht Gewerbestart). **Wartet auf Babsis Antworten:** Umschalt-Zeitpunkt (Vorschlag: alles vorbereiten, am 15.11. umschalten), Daten fürs Impressum (Firmenwortlaut, Anschrift, GISA-Zahl, Behörde, Gewerbe-Wortlaut, Kontakt, Widerrufsadresse, Kammer). Dann: Entwürfe Impressum/AGB/Widerruf/Versandseite (betroffen: indra-Seiten 53 Impressum, 55 AGB, 56 Widerrufsbelehrung, 57 Versandarten – alle nennen MEALANA KG/FN/UID), Woo KU (Germanized), Wartungsseite 35329 fürs Umschalten, Partner Babsi „verkauft online“ + Kürzel INDRA, Artikel per Massenaktion übergeben.
- **Fürs nächste Paket (1.0.12) schon committet:** 4bb07e9 Kuvert-Etikett Dichte-Stufen (Preis überlagerte bei vielen Optionen); 274367b Artikelliste: fertige Schilder eingeklappt unter ihrem Layout (wie Kinder, Bestand = Summe). 1.0.11 ist auf Live.
- **Babsis Daten (2026-11-13):** kein Firmenwortlaut (nur „Barbara Indra“), Fliegergasse 49, 2700 Wr. Neustadt; Gewerbe „Erzeugung von kunstgewerblichen Gegenständen“, GISA 11922407 (Behörde: Magistrat Wr. Neustadt – angenommen, bestätigen lassen); kein Telefon; office@indra-design.at; Widerruf an Fliegergasse 49; WKNÖ Kunsthandwerke. **Entwürfe** in `shop/rechtstexte/indra/entwurf/` (Impressum neu, AGB 13 Stellen + KU-Satz bei Preisen + Gewährleistung Holz statt Wolle = Vorschlag, Widerruf, Versand, Datenschutz + Verantwortlicher), Übersicht `uebersicht.html`, Original in `aktuell/`. Nach Freigabe per REST auf Seiten 53/55/56/57/52 schreiben. Datenschutz bei SOW/Bio vermutlich auch ohne Verantwortlichen – prüfen.
- **Freigegeben 2026-11-13 abends:** Behörde Magistrat Wr. Neustadt ✓, Gewährleistung Holz ✓. **Umschalten am 14.11. (tagsüber)**, damit Sonntag frei ist. Reihenfolge: Wartungsseite 35329 an (`indra_wartung_schalten.js an`) → Rechtstexte aus `shop/rechtstexte/indra/entwurf/` auf Seiten 53 Impressum/55 AGB/56 Widerruf/57 Versand/52 Datenschutz → Woo Kleinunternehmer (Germanized, Steuer) → Paket 1.0.12 (Kuvert + Artikelliste) → Live-ERP: Babsi „verkauft online“ + Kürzel INDRA + Adresse/Bank → Artikel per Massenaktion „Eigentümer setzen“ → Testbestellung → Wartungsseite aus. KG-Fassung abgelegt in `shop/rechtstexte/indra/kg_fassung/` (LIESMICH).

**Umschalt-Tag 2026-11-14 (vormittags):** Wartungsseite 35329 AN. Rechtstexte EU auf Seiten 53/55/56/52/57 (Skript `indra_rechtstexte_einspielen.js`, alter Stand in `shop/rechtstexte/indra/live_vor_umstellung_20261114/`). Woo: `woocommerce_gzd_small_enterprise=yes`, Text „Umsatzsteuerbefreit – Kleinunternehmer gem. § 6 Abs. 1 Z 27 UStG“ (statt dt. §19), `woocommerce_calc_taxes=no`. Fuß-Widget „…– handgemacht in Wiener Neustadt.“, Copyright „© {Jahr} INDRA DESIGN · Barbara Indra“; BACS-Kontoinhaber bleibt MEALANA KG (Geld aufs KG-Konto). Produkttexte „– ein Label der MEALANA KG.“ per SQL im Live-ERP (`shop/rechtstexte/indra/live_artikeltexte_ohne_kg.sql`): Live 226 betroffen, **25 Rest-Treffer „MEALANA KG“ offen – mit Jacky ansehen**.
Dev-Test (Partner 47 „Barbara Indra INDRA-DESIGN“ auf Dev): Abholung bar (Kassenbon im fremden Namen ok) + Versand/Vorkasse → R-INDRA-2026-00001 ok. Fixes 75c7076 (Mail-Absender/Gruß → Partner), 9a5814c (Mails ohne MwSt. bei KU, Zahlungsmail Versandzeile + echte MwSt. – betraf auch KG, Belegfuß 15 mm statt 0). Aufgeräumt: Woo #35357/35358 gelöscht, Schlüssel Dev-Test widerrufen, Dev-Kanal 4 geleert. **Paket 1.0.12** gebaut (Migr. 239). Offen: Woo-Datum in Mails englisch („November 14, 2026“); Live-Schritte (Paket, Babsi-Partner, Mail-Absender Kanal, Massenaktion, Plattenmaße + Snippet 34170 = `shop/wp-snippets/konfigurator_options_picker.php`, Bestellabruf-Zeit setzen, Kanal fortsetzen, Bestand), dann Wartung aus; Bio-Kurztest.
**✅ 2026-11-14 ~11:45 indra-design ist als EU von Barbara Indra ONLINE** (Wartung aus, Besucher 200, Impressum ohne KG). Live: 1.0.12 eingespielt, Partner + Massenaktion + Plattenmaße + Snippet 34170 erledigt (Jacky), Artikeltexte bereinigt (226 + 25 mit &ndash;, Rest 0), Bestand der 3 Testartikel wieder 1. Mail-Snippet #34191 auf aktuelle Fassung (keine „Hinweis“-Mails mehr). Offen: erste echte Bestellung genau ansehen; Woo-Datum englisch in Mails; Steuerberatung (Bestandsübergang, Gutschein-Auszahlung).
