---
name: project-partner-modul
description: "Partner-Modul Design + Implementierungsstand (Mietfächer, Kommission, Spende)"
metadata: 
  node_type: memory
  type: project
  originSessionId: 1d6af759-efaf-424e-9b61-6578c5cf2dd1
---

# Partner-Modul (Stand 2026-06-20)

## Konzept

Externe Partner in zwei Richtungen:
- **Kommission** (inbound): Mietfach-Inhaber — ihre Ware bei MeaLana, wir verkaufen, rechnen ab
- **Spende** (inbound): Yarnpride e.V. — Artikel gegen Mindestspende, Geld geht 1:1 weiter (Steuerberater klärt RKSV)
- **Händler** (outbound): Befreundete Betriebe verkaufen MeaLana-Ware → abgebildet als **externes Lager** (lager.typ='extern_haendler'), NICHT im Partner-Modul → kommt nach Kern-Verkauf

## Datenbank (Migrationen 049–052, eingespielt 2026-06-20)

- `049_partner_stamm.sql` → partner + mietfaecher
- `050_partner_belege.sql` → dokument_nummern (alle Rechnungskreise) + miet_rechnungen + kommissions_abrechnungen
- `051_spenden_log.sql` → spenden_log (Yarnpride-Dokumentation, weitergeleitet-Flag)
- `052_artikel_partner.sql` → ALTER artikel: partner_id + partner_modus ENUM(eigen|kommission|spende)

## Abrechnungs-Modi (pro Partner konfigurierbar)

| Modus | Belege |
|-------|--------|
| Getrennt | Mietrechnung + Kommissions-Abrechnung separat |
| Gegenverrechnung | Ein Dokument: Erlöse - Miete = Saldo |

| Belegtyp | Wer erstellt |
|----------|-------------|
| Gutschrift | MeaLana erstellt im Namen des Partners |
| Fremdrechnung | Partner stellt eigene Rechnung, wir buchen sie ein |
| Info-Abrechnung | Formloser Beleg (für KU-Partner ohne Steuerbeleg) |

## Fachmieter mit Fremdrechnung (Klärung 2026-06-21)

Viele Fachmieter sind **Fremdrechnung**: MeaLana hat Rechnungsblöcke des Fachieters auf Lager,
verkauft in deren Namen und auf deren Rechnung. Kein MeaLana-Ausgangsbeleg.

**Lagerverwaltung:** Identisch zu Yarnpride/Händler-Konsignation — eigenes "Lager" (extern_partner),
Lagerbewegungen werden vollständig gebucht (Eingang, Verkauf, Rücklauf).

**Korrektur 2026-07-05:** Die externe Lagerverwaltung dafür läuft NICHT über einen eigenen `typ='extern_partner'`, sondern über das im Zug der Lagerverwaltungs-UI-Planung finalisierte Schema: `lager.lager_beziehung='partner_bestand'` + `lager.partner_id` (siehe [[project-lager-konzept]]). Zuweisung welches Lager zu welchem Partner gehört passiert im Partner-Formular (Dropdown auf Lager mit `lager_beziehung='partner_bestand' AND partner_id IS NULL`), nicht im Lager-Formular selbst.

**DB-Erweiterung (noch nicht migriert):**
```sql
ALTER TABLE partner
  ADD COLUMN abrechnung_typ VARCHAR(30) NOT NULL DEFAULT 'eigen_rechnung';
  -- Werte: 'eigen_rechnung' | 'fremd_rechnung' | 'info_abrechnung'
```

`fremd_rechnung` → kein MeaLana-Beleg, nur Lagerbewegung + optionale Notiz (welcher Rechnungsblock-Nr.)
`eigen_rechnung` → MeaLana erstellt Gutschrift (bisheriger Modus)
`info_abrechnung` → KU-Partner, formloser Beleg

**Timing:** Migration + Feld im Partner-Formular einbauen WENN public/partner/ gebaut wird.

## PHP-Schicht (Stand 2026-06-20)

- `src/modules/partner/PartnerRepository.php` ✅
  - findAll() mit Filter (typ, aktiv) + LEFT JOIN anzahl_faecher
  - findById(), insert(), update(), setAktiv()
  - findMietfaecherByPartner(), insertMietfach(), updateMietfach()
- `src/modules/partner/PartnerService.php` ✅
  - save(), aktualisiere(), setAktiv()
  - saveMietfach(), aktualisiereMietfach()
  - Validierung: Name/Typ Pflicht, E-Mail-Format, Provision ≥ 0, Mietende > Mietbeginn

## Status: ✅ VOLLSTÄNDIG (2026-06-21)

### Gebaut
- `public/partner/liste.php` — Partner-Liste, Neu+Bearbeiten Modal, Typ-Chips, Auto-Beleg-Typ
- `public/partner/mietfaecher.php` — Physische Fächer-Übersicht, Status Frei/Belegt, Vermieten/Kündigen
- `public/partner/speichern.php`, `aktualisieren.php`, `status_setzen.php`
- `public/partner/fach_speichern.php`, `fach_aktualisieren.php`
- `public/partner/vertrag_speichern.php`, `vertrag_beenden.php`
- `src/modules/partner/MietfachRepository.php` + `MietfachService.php`
- shell_top.php: Partner + Mietfächer in Sidebar + Top-Nav

### Noch offen
- `public/partner/abrechnung/` — Monatsabschluss + PDF (braucht Verkauf/Kasse als Datenquelle)

**Why:** Abrechnung erst sinnvoll wenn Verkaufsmodul Daten liefert (welche Artikel wurden pro Partner verkauft).

**How to apply:** Nächster Partner-Schritt = Abrechnung, nach Auftragsmodul/Kasse.

## Stand + Entscheidungen 2026-10-01 (Jacky)
**Befund:** Partner-/Händler-Lager wurden NIE gebaut (nur Schema: lager.lager_beziehung/partner_id/kunde_id, artikel.partner_id/partner_modus ohne UI; Lager-Formular kann Beziehung wählen, aber nicht mit Partner/Kunde verknüpfen). Nichts verloren — lager max id 3, keine Buchungen auf andere Lager. Partner-Abrechnung ebenfalls nicht gebaut.
**Entscheidungen:**
- Kasse erkennt Partner-Artikel und bucht sie automatisch vom Partner-Lager ab.
- EIN Lager pro Partner; die Mietfächer sind die Lagerplätze darin (z.B. Seifenfabrik · R1-F1). Ein Mietfach gehört immer nur einem Partner.
- Händler: Lieferschein bei Lieferung; Rechnung erst nach Verkaufsmeldung, gesammelt über einen Zeitraum, zu Kundengruppen-Preisen; die Lagerkorrektur im Händler-Lager passiert erst mit dieser Rechnung.
- Abrechnung (eigene Rechnung/Gutschrift, Fremdrechnung, Info-Abrechnung) muss "nochmal genau angeschaut" werden — eigene Planungsrunde, nicht nebenbei bauen.
**Reihenfolge:** 1. Partner-Lager → 2. Händler-Außenlager → 3. Partner-Abrechnung.

## ✅ Partner-Lager GEBAUT 2026-10-01 (nicht committed)
Jacky-Entscheidungen dazu: Belege für Übernahme+Rückgabe + Nachverfolgung + Verkaufsliste je Zeitraum; Artikelnummer `XP<Partner-ID 2-stellig>-<frei>` (eigene Artikel mit ^XP\d werden abgelehnt); Partnerware getrennt vom Sortiment (nur auf Partnerseite pflegen, nicht in Artikelliste, nie im Shop); RKSV/"im Namen und auf Rechnung von" bleibt offen → eigener Punkt mit der Abrechnung.
- **Migration 189:** lagerplaetze.mietfach_id (UNIQUE), lager UNIQUE(partner_id), dokument_nummern-Typen partner_uebernahme (US) / partner_rueckgabe (RS), Tabellen partner_belege + partner_beleg_positionen, Artikelgruppe "Partnerware" mit Platzhalter-Konto 'PARTNER' (echtes Konto klärt die Abrechnung mit Babsi — Kontenplan-Seite warnt).
- `src/modules/partner/PartnerLagerService.php`: lagerAnlegen, mietfaecherSichern (Fach→Platz, wandert beim Mieterwechsel nur wenn leer, sonst Hinweis), getArtikel/artikelSpeichern (Anlage über ArtikelService::save mit `_partnerware`-Flag, Update gezielt per SQL), belegBuchen (LagerService::wareneingang/warenausgang, Referenz "Übernahme US-…"/"Rückgabe an Partner RS-…"), getBewegungen, getVerkaeufe (nur typ=verkauf & nicht storniert), dokumentBasis.
- MietfachService::vertragStarten ruft mietfaecherSichern. KassenService::lagerFuerArtikel() → Scan-Bestand, Verkauf, Storno aus/in Partner-Lager. RuecklagerungRepository schlägt Partner-Lager vor. ShopSyncRepository: findFaelligeArtikel `a.partner_id IS NULL` + schreibeZuweisungsZeile verweigert Partnerware. ArtikelRepository findAll/countAll: Partnerware nur mit `mit_partnerware` (Liste: Checkbox "inkl. Partnerware").
- UI: `partner/detail.php` (Reiter Bestand/Artikel/Bewegungen/Belege & Verkaufsliste/Mietfächer), `beleg_neu.php` (Scan +1), `beleg_pdf.php` (storage/partner_belege, gitignored), `verkaufsliste_pdf.php`, Templates `dokumente/partner/beleg|verkaufsliste.html.twig`. Partnername in liste.php verlinkt.
- **Falle:** shell_top.php benutzt `$typ` und `$titel` als eigene Variablen → in Seiten NICHT vor dem Include setzen und danach verwenden (beleg_neu.php hatte dadurch "Ungültiger Belegtyp").
- Handbuch 08_partner.md: falsch beschriebene "Kommission-Tab-Abrechnung" (nie gebaut) ersetzt.
- Getestet: Rollback-Harness 28 Punkte + Playwright mit Testpartner 2 (danach alles gelöscht, Belegzähler auf 0 zurück).
- **Offen:** Partnerware in Aufträgen/Packplatz (bucht dort weiter vom Auftragslager), Mitgeben/offene Auswahl, Messe-Offline; Abrechnung; RKSV-Trennung; Händler-Außenlager (Schritt 2 der Reihenfolge).

## Klärungen Jacky 2026-10-01 (nach Bau Partner-Lager)
- Partnerware ist IMMER nur Kassenverkauf → in Aufträgen gesperrt (Suche + AuftragService::pruefePartnerware), GEBAUT.
- **Abrechnungsmodus** gemeint als: werden Verkaufserlöse eines Partners mit der Fachmiete **gegenverrechnet** oder immer **getrennt** betrachtet. Daraus Belegtypen: **Gutschrift** wenn gegenverrechnet, **Fremdrechnung** = zweiter Beleg mit den Daten des Partners, **Info-Beleg** für Spendenzahlungen.
- **Provision** = was MeaLana beim Verkauf einbehält. Kleinunternehmer-Flag des Partners wichtig für Liste/Steuer.
- **Spende (Yarnpride)** hat eine EIGENE Kassenlade → vermischt sich nicht mit Kassenstand/Kassenbuch; System soll nur dokumentieren/nachverfolgen und bei Spendenübergabe eine Liste liefern.
- Bon: Jacky tendiert zu **2 Belegen** (eigener + Partner), kennt aber das Tankstellen-Modell (Vignette "im Namen und auf Rechnung der ASFINAG" auf derselben Rechnung). Offen bis Babsi/Steuerberater antworten.
- Offene Steuerfrage: Partner ist Kleinunternehmer + wir verkaufen auf Kommission → wir schulden USt, er nicht — wie behandeln?
- Fragenblatt für Babsi/Steuerberater erstellt (2026-10-01): https://claude.ai/artifact/PCVngy3WkBwTbWQG6axqjA (10 Fragen: Bon 1 vs 2 Belege, RKSV Fremdumsatz, Partnerbeleg-Pflichtangaben, Bargeld/Kassenbuch, Kommission=eigener Umsatz?, Spende eigene Lade, KU+Kommission, KU+Fremdrechnung, Fachmiete/Gegenverrechnung, Konten, Unterlagen). Antworten abwarten BEVOR Abrechnung/Bon/RKSV gebaut wird.

## ✅ Antworten Steuerberatung (Jacky, 2026-10-26) — Grundlage für die Abrechnung
- **A1 Bon:** EIN Beleg für Kunde+Zahlung (Bankomat). Partnerware in eigenem Abschnitt "Folgende Artikel wurden verkauft im Namen und auf Rechnung von … (Partnerangaben)".
- **A2 RKSV:** Fremdrechnung (fremder Name/Rechnung) = nie unser Umsatz → NICHT in RKSV. Kommission → normal RKSV, normaler Erlös je Erlösgruppe.
- **A3** erübrigt sich.
- **A4 Bargeld:** "Bareingang aus Beleg xx zu Mietfachpartner yy"; später Barausgabe ODER Überweisung an Partner; Tagesabschluss zeigt Partnerumsätze getrennt.
- **A5** Kommission = eigener Umsatz wie Eigenbestand ✅.
- **A6** Spende: eigene Aufzeichnung + Liste reicht (keine RKSV).
- **B7** Provision immer auf NETTO; Preisgestaltung = Vertragssache, nicht Teil von Kasse/ERP.
- **B8** Partner-Abschnitt am Bon zusätzlich mit Kleinunternehmer-Text des Partners (falls Firma Firmenrechnung braucht).
- **B9 NIE gegenverrechnen!** Fremdrechnung: wir dürfen KEINE Rechnung/Gutschrift erstellen — Partner bekommt nur Verkaufsaufstellung + ggf. Zahlungsaviso (sein Geld, wir verwahren nur). Fachmiete = normale eigene Ausgangsrechnung an den Partner, unabhängig von Verkäufen.
- **C10 Konten:** Fachmiete = normale 4er Mieterlöse; Fremdumsätze = keine Erlöse; Kommission = normale Erlöskonten beim VK, später wie Einkauf bei einem Händler.
- **C11** Unterlagen monatlich vorbereitet, auf Abruf auch untermonatig.
→ Damit überholt: "Gegenverrechnung"-Modus + Belegtyp-Auswahl Gutschrift/Fremdrechnung/Info aus den Klärungen 2026-10-01.

## Klärung 2 (Jacky 2026-10-26) + Konzept-Entwurf
- Partner = ENTWEDER Kommission ODER Fremdrechnung (bzw. Spende); Mietfach zusätzlich möglich, unabhängig.
- Fremdrechnung: KEINE Provision (nur Fachmiete).
- Kommission: wie normaler Einkauf — Partner schickt Rechnung mit vorab definierten EK (= netto − Provision).
- Spende: kann an Kasse gescannt/eingegeben werden (Mischeinkauf/Bankomat) — am Bon eigener Abschnitt.
- Konzept vorgeschlagen (Abnahme ausständig): partner.verkaufsart + Adresse + KU-Text + kunde_id (Miete) + lieferant_id (Kommission); Bon-Abschnitte, RKSV nur Eigen+Kommission, Fremd-Bar in Lade (durchlaufend), Spende-Bar in Spendenlade (nicht Kassenstand); Partner-Konto (Saldo, Aufstellung+Zahlungsaviso, Auszahlung bar/Überweisung, Spendenliste, Kommission-Kontrollliste); Fachmiete-Monatslauf als Auftrag+Rechnung (rechnungen hängen an Aufträgen); Export: Konten einstellbar (Nummern von Babsi). Bau-Reihenfolge 1 Stammdaten → 2 Kasse/Bon → 3 Partner-Konto → 4 Fachmiete → 5 Export. Offline/Messe-Kasse + Aufträge bewusst ohne Partnerware.

## ✅ Schritt 1 Stammdaten GEBAUT 2026-10-26 (nicht committed, Klicktest offen)
- Konzept von Jacky abgenommen ("ja passt so"). Migration 205 (Dev angewendet): partner.typ = ENUM(fremdrechnung|kommission|spende) (mietfach→fremdrechnung, beides→kommission), + strasse/plz/ort/land, ku_text, kunde_id (Debitor Fachmiete, FK kunden), lieferant_id (Kreditor Kommission, FK lieferanten); abrechnungs_modus + abrechnungs_beleg_typ GEDROPPT; artikel.partner_modus + 'fremdrechnung', per UPDATE synchron mit partner.typ.
- PartnerService: VERKAUFSARTEN, KU_STANDARDTEXT; aktualisiere() merged mit altem Datensatz (kunde_id bleibt); Provision/Lieferant nur bei Kommission. PartnerRepository::update zieht artikel.partner_modus mit. PartnerLagerService setzt partner_modus = partner.typ. mietfaecher.php: jeder Partner mietbar.
- liste.php/partner_liste.js: Adresse, Verkaufsart+Hinweistext, Provision+Lieferant (nur Kommission), KU-Text (nur KU), Spalte Ort/"Adresse fehlt". Beide Handbücher.
- Test: Rollback-Harness 11/11. UI nicht im Browser getestet (keine Login-Daten) → Jacky-Klicktest.
- Nebenbei: schema_migrations auf Dev fehlten 203+204 (von Hand eingespielt) → nachgetragen. Live-Deploy: 201–205.
- ⏭️ Nächster Schritt 2: Kasse/Bon (RKSV-Trennung, Bon-Abschnitte, Kassenstand, Z-Bon, Storno/Retoure).

## ✅ Schritt 2 Kasse/Bon GEBAUT 2026-10-26 (committed mit Schritt 1, Klicktest offen)
- Migration 206: kassen_bon_positionen.partner_id + partner_modus (Snapshot), kassen_bons.fremd_betrag/spende_betrag/spende_bar. Alt-Positionen mit Partnerware → 'kommission' (waren damals signiert).
- `src/modules/kasse/PartnerBon.php` = zentrale Logik: anreichern() (serverseitig aus artikel+partner; Spende + KU-Fremdrechnung → 0 %), eigene(), summen(), spendeBar(), abschnitte(), SQL_EIGEN.
- KassenService::erstelleBon: Steuergruppen + Signatur nur eigen (eigenBetrag = brutto − fremd − spende), ohne eigene Positionen KEINE Signatur; Spiegel-Auftrag ohne Fremd/Spende. storniereBon analog (negativ, nur eigen signiert). getKassenstand − spende_bar. Tages-/Periodenkennzahlen: umsatz_gesamt = eigen, + partner_fremd/partner_spende(/_bar); Steuer-/Gruppen-Auswertung nur SQL_EIGEN. sammleAbschlussDaten → partner_umsaetze.
- bon_speichern: RKSV-Negativcheck mit eigenem Anteil, K1-Extras ohne Fremd. bon_druck (80mm) + BonA4Renderer: Partner-Abschnitte, Steuer nur eigen, "davon MEALANA", Hinweis ohne RKSV bei Nur-Partner-Bon. abschluss_druck/_mail: Partnerumsätze. Dashboard: eigener Umsatz. bon.php: Badge "im Namen von"/"Spende für" (findArtikelByCode/sucheArtikel liefern partner_modus/partner_name).
- Test: Integrationsskript 22/22 (Mischbon, Nur-Partner-Bon Bankomat, Storno, Kassenstand, Z-Bon, A4+80mm-Render) mit vollständigem Aufräumen. Nicht getestet: echter BFR (K3), Retoure über bon_speichern (HTTP), abschluss_periode zeigt umsatz_gesamt jetzt eigen ohne Partnerzeile.
- Offen für Schritt 5: BuchhaltungExportService bucht Fremd/Spende noch als Erlös!
- ⏭️ Schritt 3: Partner-Konto (Saldo, Aufstellung + Zahlungsaviso, Auszahlung bar/Überweisung, Spendenliste, Kontrollliste Kommission).

## ✅ Schritt 3 Partner-Konto GEBAUT 2026-10-26 (committed, Klicktest offen)
- Migration 207: partner_auszahlungen (nummer ZA-…, art bar_kasse|spendenlade|ueberweisung, kassenbuch_id, storniert + storno_kassenbuch_id), dokument_nummern-Typ partner_auszahlung (Präfix ZA).
- `PartnerKontoService`: getVerkaeufe/getKonto (Saldo = Fremd/Spende-Positionen − Auszahlungen; Spendenlade = spende_bar anteilig − Übergaben), auszahlungBuchen (≤ Saldo, Spendenlade ≤ Inhalt, bar_kasse → Kassenbuch-Entnahme), auszahlungStornieren (Einlage-Gegenbuchung), getKommissionKontrolle (Menge × artikel_lieferanten.netto_ek beim partner.lieferant_id).
- PartnerLagerService: EK netto für Kommissions-Artikel (ekSpeichern → artikel_lieferanten, brutto nur ohne KU), getArtikel liefert ek_netto. Modal: Feld EK + "aus Provision".
- UI: detail.php Reiter "Konto & Auszahlung" bzw. "Kontrollliste" (Kommission), js/partner_konto.js; abrechnung_pdf.php + Template partner/abrechnung.html.twig (Verkaufsaufstellung/Spendenliste/Kontrollliste, ?aviso=id = Zahlungsaviso mit Unterschrift bei bar). Zugriffsregeln ergänzt.
- Test: 22/22 inkl. 3 echte PDFs (visuell geprüft), vollständig aufgeräumt (ZA-Zähler zurück auf 0).
- ⏭️ Schritt 4 Fachmiete-Monatslauf (Auftrag+Rechnung über partner.kunde_id), dann Schritt 5 Export/Konten.

## Nachträge 2026-10-26 abends (committed 05fdaac, 336b46d)
- Ware übernehmen/zurückgeben: Suchfeld filtert nach Name/Nummer (auch ohne XP-Präfix)/EAN, Enter = +1.
- Partner-Artikel ohne Nummer → XPnn-0001 auto; "+ Neuer Artikel" direkt auf der Übernahme-Seite; normale Artikelanlage lehnt Gruppe "Partnerware" ab. Jackys Test-Artikel 27490 "Seife Green Light - verpackt" (ART-1325092016) wurde über die normale Anlage gemacht → ist KEINE Partnerware.
- **Fachmiete-Antworten Babsi:** Verrechnung im Voraus, anteilig immer wochenweise, Mietdauer immer 4 Wochen, erste Miete 8 Wochen. Detailfragen an Jacky gestellt (Betrag je 4 Wochen? Rechnungszeitpunkt/Vorlauf? Kündigung mitten in verrechneter Periode?).

## ✅ Schritt 4 Fachmiete GEBAUT 2026-10-26 (committed, Klicktest offen)
- Regeln (Babsi/Jacky): monatlich im Voraus, Rechnung 2 Tage vor Monatsende für den Folgemonat, Mietbeginn immer Monatserster, Kündigung immer zum Monatsletzten → keine Anteile. (Früher Kalenderwochen/4 Wochen — verworfen.)
- Migration 208: Konto 4800 "Mieterlöse Fachmiete" (Nummer mit Babsi bestätigen!), Artikelgruppe "Fachmiete", Leistungsartikel FACHMIETE (keine Lagerführung, kein Shop), miet_rechnungen + vertrag_id/auftrag_id/rechnung_id + UNIQUE(vertrag_id, periode) (rechnungs_nr-Unique gedroppt), Einstellung fachmiete_vorlauf_tage=2.
- `MietRechnungService`: zielMonat (Stichtag + Vorlauf + 1 Tag), offene() (alle Monate ab Beginn bis Ziel/Ende ohne Rechnung, holt nach), verrechnen() → je Partner+Monat Auftrag (Kunde automatisch aus Partner, Snapshot Kunde+Rechnungsadresse, Positionen als erbracht + preisbasis netto, lieferstatus abgeschlossen) + Vollrechnung, Leistungsdatum = Monatserster (PDF neu gerendert). MietfachService: Validierung Monatserster/-letzter, Kündigung nicht vor verrechnetem Monat.
- UI: Karte "Fachmiete verrechnen" auf mietfaecher.php (offen + zuletzt verrechnet), Endpoint fachmiete_verrechnen.php, cron/fachmiete.php (täglich, Task Scheduler noch EINTRAGEN).
- Test 22/22 + PDF visuell geprüft (erst 3 Fehler gefunden: leerer Empfänger, "Teilrechnung"+"noch ausständig", Leistungsdatum → behoben). Kosmetisch offen: Rechnungsvorlage zeigt "Versandart Selbstabholung"/Abholhinweis/"Danke für Ihre Bestellung".
- ⏭️ Schritt 5: Buchhaltungs-Export (Fremd/Spende als Durchlauf/Verbindlichkeit statt Erlös, Konten einstellbar).

## ✅ Schritt 5 Buchhaltungs-Export GEBAUT 2026-10-28 (nicht committed, Klicktest offen)
- Migration 209 (Dev angewendet): Konten 4080 "Erlöse Kommissionsware" (erloes), 3710 "Verrechnung Partner Fremdumsätze", 3720 "Durchlaufende Spenden" (verbindlichkeit) + system_einstellungen konto_partner_kommission/_fremd/_spende. Nummern = Vorschläge, mit Babsi bestätigen.
- BuchhaltungExportService: partnerKonten()/partnerSql() — Kassenbon-Positionen nach partner_modus: fremd → 3710 ohne USt, spende → 3720 ohne USt (Bar-Anteil spende_bar = Spendenlade wird NICHT gebucht, Faktor kürzt), kommission → 4080 + USt. zahlungsAnteile zieht spende_bar vom Baranteil ab (gemischte Bons + Kontrollliste). Block 6 partnerAuszahlungen: 3710/3720 Soll an Kassa (bar_kasse) bzw. Bank (ueberweisung); spendenlade + storniert nicht. Nicht-numerisches Konto (Platzhalter 'PARTNER') → Hinweis statt Exportzeile. Leeres Partner-Konto → Hinweis.
- Gruppe "Partnerware" behält konto_nr 'PARTNER' (dient als Kennung in ArtikelService/PartnerLagerService) — Kommission-Konto kommt aus der Einstellung, nicht aus der Gruppe.
- UI: export.php Karte "Partner-Konten" (Selects aus Kontenplan nach Typ), export_einstellungen_speichern.php speichert nur mitgeschickte Felder.
- Test: Rollback-Skript 10/10 (bar/Karte/Kombi mit Spende, Kommission, Auszahlungen) + Leer-Konto-Fall; Export auf echten Daten Jan–Okt vorher/nachher identisch (Hash).
- Handbücher: 12_buchhaltung.md, 08_partner.md, bedienungsanleitung.php (veralteten "Noch nicht gebaut"-Satz ersetzt).
- Jackys falscher Test-Artikel 27490 gelöscht (2026-10-28).
- Nachtrag 2026-10-28: Cron fachmiete im Task Scheduler eingetragen; Mietrechnung ohne Versandart/Abholhinweis/Bestell-Dank (ist_fachmiete). Alles committed.

## Stand 2026-10-28 (Jacky)
- Kontonummern 3710/3720/4080: keine Freigabe nötig — Babsi kann sie bei Bedarf selbst im Kontenplan / Export-Einstellungen "Partner-Konten" ändern.
- Test-Partner "TEST_Partner_Fix_Check" (Spende) bleibt bewusst in der Dev-DB → für Tests im Dev verwenden, nicht löschen.
