---
name: project-status
description: "Aktueller Implementierungsstand MeaLana ERP – was fertig, was als nächstes kommt"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

Stand: 2026-07-03 (Session 22)

## Git Repository
`D:/ERP/mealana/` — nicht in `D:/ERP` suchen!
Commit-Befehl: `git -C "D:/ERP/mealana" ...`

## Memory-Backup
Memory-Dateien werden in `D:/ERP/mealana/.claude/memory/` mitgepflegt (im Git-Repo).
Am Ende jeder Session syncen:
```powershell
Copy-Item "C:\Users\indy1\.claude\projects\d--ERP\memory\*.md" "D:\ERP\mealana\.claude\memory\" -Force
git -C "D:/ERP/mealana" add .claude/memory/ && git -C "D:/ERP/mealana" commit -m "Memory-Backup"
```

## Schema-Referenz
- 104 Migrations angewendet (001–104)
- Migration 097–099: Lieferanten-Erweiterung (laender-Tabelle, firma/ustid/steuerregel/Bankverbindung)
- Migration 100–104: RKSV/BFR komplett (Nachsignierung, Nullbeleg, Umsatzzähler, Nacherfassung, Kassen-Registrierung)
- Migration 087: auftrag_lieferungen (Tracking-History pro Auftrag für Teillieferungen)
- Wichtige neue Tabellen: auftrag_zahlungen (076), mahnungen (069), shops (067), auftraege/auftrag_positionen/rechnungen/auftrag_dokumente/auftrag_statuslog (060–062)
- Dump aktualisieren: `& "C:\xampp\mysql\bin\mysqldump.exe" --host=localhost --user=root --no-tablespaces --routines --skip-comments mealana_erp | Out-File -FilePath "D:\ERP\mealana\erp\database\schema_current.sql" -Encoding utf8`

## ✅ Fertige Module (Stand 2026-06-28)

### Artikel-Modul ✅ VOLLSTÄNDIG
- CRUD, 7 Tabs, Varianten, Preise, Bilder, Merkmale, Lieferanten, SEO
- lieferzeit_text: in detail.php + aktualisieren.php (erscheint auf Dokumenten wenn Lagerbestand=0)
- Vater-Kind Vererbung vollständig
- VarKombi Generator: EAN-Feld, Kindname = Vater + Achsenname + Wert
- Bulk-Kategorie-Zuweisung in liste.php (Mehrfachauswahl → Modal → INSERT IGNORE + Kinder)
- Fehlbest.-Chip: nur bei reserviert > gesamtbestand

### Achsen-Modul ✅ VOLLSTÄNDIG
- Aufpreis/Direktpreis pro Achse (Migration 074, Toggle-UI)
- sort_order wird beim Speichern korrekt gesetzt (INSERT + UPDATE)

### Varianten-System ✅ VOLLSTÄNDIG
### Lager-Modul ✅ VOLLSTÄNDIG
### Lieferanten-Modul ✅ VOLLSTÄNDIG + ERWEITERT (2026-07-02)
- Migrations 085+086: neue Felder (Adresse, Kundennr., Währung, Zahlungskonditionen, Lieferkonditionen, Notizen) + Tabelle lieferanten_zugaenge (AES-256-GCM Passwörter)
- **Migrations 097–099 (2026-07-02)**: `laender`-Referenztabelle (Land-Dropdown statt Freitext), `firma`/`firmenzusatz` (name bleibt Such-/Kurzbezeichnung), `ustid`, `steuerregel`-Enum, `standard_lieferkosten`, Bankverbindung (iban/bic/bank_name/kontoinhaber), Vertreter-`anrede`
- Vertreter-Anlage als Repeatable-Row direkt im Lieferanten-Neuformular (kein Umweg mehr über separate Seite)
- detail.php: 5 Tabs (Stammdaten | Vertreter | Artikel | Bestellungen | Zugänge)
- Artikel-Tab: aus artikel_lieferanten (korrekte Spalten: artikelnummer_lieferant, netto_ek, vpe_menge)
- Bestellungen-Tab: alle EK-Bestellungen mit Status-Chips
- Zugänge-Tab: Passwort-Manager mit Show/Hide Toggle
- Lager-Einstieg: Topnav "Lager" → picklisten.php (war wareneingang.php)
- Kunden-Modul: Tab "Bestellungen" aktiviert + Einwilligungstypen Telefon/WhatsApp/SMS + "Auftrag erstellen"-Button mit Adress-Vorausfüllung
- Offen: Kreditorennummer/DATEV-Zuordnung kommt als eigene Liste im Buchhaltungsmodul; Doku (bedienungsanleitung.php + Handbuch) noch nicht nachgezogen
### Aktions-Modul ✅ VOLLSTÄNDIG
### PreisService ✅ VOLLSTÄNDIG
- artikel_preise JOIN mit Datumsfilter: bevorzugt aktiven Sonderpreis über Basispreis

### Kunden-Modul ✅ VOLLSTÄNDIG (AES-256-GCM, DSGVO)
### Partner-Modul ✅ VOLLSTÄNDIG (Mietfächer, Vertragshistory)
### Hersteller-Modul ✅ VOLLSTÄNDIG (GPSR-Felder, aktualisiert_am)
- **Bugfix 2026-07-02**: Neuanlage über das Modal warf "Netzwerkfehler" (PDO HY093 durch mitgeschicktes leeres `id`-Feld in `insert()`) — `unset($data['id'])` in `HerstellerService::save()`. Gleiches Muster in `PartnerRepository::insert()` latent (aktuell nicht ausgelöst).
### Bestellwesen/Einkauf ✅ VOLLSTÄNDIG

### Auftragsmodul/Verkauf ✅ WEITGEHEND FERTIG (aktualisiert 2026-06-26)
- Migrations 060–068 eingespielt
- liste, neu, detail, bearbeiten, aktualisieren, stornieren
- Dokumente: Rechnung, Auftragsbestätigung, Lieferschein, Abholzettel, Gutschrift
- **Zahlung buchen** (NEU 2026-06-26):
  - Migration 076: auftrag_zahlungen (id, auftrag_id, betrag, buchungsdatum, notiz, erfasst_von)
  - detail.php: Zahlungsverlauf + Buchungsformular (Betrag vorausgefüllt, Datepicker)
  - Teilzahlung → Status 'teilbezahlt'; Vollzahlung → 'bezahlt'; Überzahlung → chip 'Überbezahlt'
  - liste.php: Chips Teilbezahlt/Überbezahlt + Filter für alle Zahlungsstatus

### Einstellungen-Modul ✅ NEU (2026-06-25)
### Mail-System ✅ VOLLSTÄNDIG ÜBERARBEITET (2026-06-28, Session 17)
- Tracking-IDs in auftraege/detail.php klickbar (carrier-spezifische URLs: Post AT / DHL / DPD / GLS)
- basis_layout.html.twig: Logo (per Shop, base64), Social-Links + Tel + Web im Footer
- versandbestaetigung.html.twig: persönliche Anrede (Sehr geehrte/r), versendete Positionen, Tracking-Card
- auftragsbestaetigung.html.twig: Komplett neu — Anrede, ÜV-Warnung (rote Box), Status-Nachricht ("sofort für Versand" wenn bezahlt+lagernd), Positionen mit Art.nr., Rechnungs-/Lieferadresse, volle Bankverbindung bei Vorkasse
- zahlungseingang.html.twig: NEU — Zahlungsbestätigung mit Bestellübersicht, Teilzahlung-Anzeige (offen/bezahlt)
- Mailer.php: ladeShopLogo(shopId), auto-inject Firma/Social-Daten in alle Templates, Fallback website→firma_web, telefon→firma_tel
- Migration 090: social_instagram/facebook/tiktok/youtube/pinterest/firma_web als Settings
- Einstellungen/Firma: Neue Karte "Online-Präsenz & Social Media" mit URL-Feldern für alle 5 Kanäle
- DokumentService: holeOderErstelleRechnung() mit neu_erstellt-Flag (verhindert Mail-Doppelversand)
- abschliessen.php (Packplatz): Auto-Rechnungserstellung + Auto-Rechnungsmail wenn bezahlt + vollständig
- zahlung_buchen.php: Zahlungseingangs-Mail nach manuellem Buchen (außer Bar/Karte/Nachnahme)
- auftraege/detail.php: Hinweis unter Dokument-Buttons welche Typen automatisch mailen

### Mail-Infrastruktur ✅ NEU (2026-06-25)
### Mahnwesen-Cronjob ✅ NEU (2026-06-25)

### Kasse/POS ✅ Phase 1+2 FERTIG (zuletzt 2026-07-02 — RKSV/BFR komplett)
- Migration 077: kassen, kassen_bons, kassen_bon_positionen, kassenbuch, offene_auswahl
- Migration 078: Divers-Platzhalter-Artikel 99-9999 (für auftrag_positionen FK)
- public/kasse/: 16 Dateien — index, bon, ajax_artikel, bon_speichern, bon_druck, kassenbuch(+speichern), kassensturz(+speichern), offene_auswahl(+speichern+verarbeiten), bon_journal, bon_stornieren
- KassenService: erstelleBon, storniereBon, findArtikelByCode(FIFO-Charge), X-Bon/Z-Bon, Kassenbuch, Offene Auswahl
- Features: EAN-Scan, Vater→Variante-Auswahl, Divers-Artikel, Rabatt, Bar+Rückgeld, Karte extern, Gutschein, Kombi, 80mm Druck, Zählhilfe
- **Jeder Bon erstellt automatisch Auftrag (kanal='kasse')** → erscheint in auftraege/liste.php
- **Korrekturbuchung bei 0-Bestand**: +Eingang vor Ausgang statt negativer Bestand (Log ehrlich)
- **Divers-Positionen in auftrag_positionen** via Platzhalter 99-9999 (getDiversArtikelId())
- **auftraege/detail.php**: kanal='kasse' → Dokumente gesperrt, nur "Kassenbon drucken" sichtbar
- Bugfixes: steuerklassen.satz (war prozentsatz), artikel_preise.kundengruppen_id (war kunden_gruppe_id)
- **Abholbereit+bezahlt Flow ✅ FERTIG (2026-06-29)**: exakt/retour/extra/mix — alle 4 Fälle; nur_abschliessen, Retour-Bon, neg. auftrag_zahlungen, Gutschein-Hook vorbereitet
- **K1-Bon Laufkunde Bug ✅ BEHOBEN (2026-06-29)**: kunden_snapshot vom Original-Auftrag immer auf K1 kopieren
- **RKSV/BFR-BONit ✅ FERTIG (2026-07-02)**: BfrService (Verkauf+Storno-Signierung, Nachsignierung mit Sammelbeleg-Protokoll, Nullbeleg monatlich+manuell, Gesamtumsatzzähler-Sperre), Nacherfassungs-Seite, Kassen-Registrierung mit Aktiv-seit-Stichtag (Hardware-Wechsel-sicher), Cronjob, echter QR-Code (endroid/qr-code) — Details: siehe project_rksv_bfr.md
- Phase 2 noch offen: Bon-Park, A4-Bon als Rechnung

## 🔴 Noch nicht gebaut (Reihenfolge = geplante Priorität)

| Modul | Priorität | Anmerkung |
|---|---|---|
| Kasse Phase 2 | HOCH | ~~RKSV/BFR BONit~~ ✅ 2026-07-02, Auftrag laden, Bon-Park |
| **Auth & Benutzer-Cluster** | **HOCH** | **Zusammenhängend, in dieser Reihenfolge bauen:** |
| ~~Login / Logout (Shell)~~ | ✅ FERTIG 2026-06-27 | login.php gestylt, Shell-Header mit Profil-Link + Abmelden |
| ~~Anmeldeschirm + Rollenauswahl~~ | ✅ FERTIG 2026-06-27 | start.php: Begrüßung + 3 Kacheln (ERP/Kasse/Packplatz) |
| ~~Benutzer-Profil UI~~ | ✅ FERTIG 2026-06-27 | benutzer/profil.php: Stammdaten + Passwort ändern; Barbara-Account Migration 084 |
| Rechteverwaltung | MITTEL | Admin-Seite: Rollen zuweisen; eher für Weitergabe |
| Anmeldekontrolle / Zwangsabmeldung | MITTEL | Session-Management; für Weitergabe (Praktikanten) |
| ~~Zentrales Dokumentenarchiv~~ | ✅ FERTIG 2026-06-27 | Kassenbons via UNION ALL integriert; X/Z-Bons in Einstellungen/Kassen |
| ~~Dashboard~~ | ✅ FERTIG 2026-06-28 | dashboard.php: 5 KPI-Cards (Card 5 Platzhalter bis Buchhaltung), Kanal-Balken, Fehlbestand-Greedy-Logik aus picklisten.php, Lieferhistory, Log-Bar |
| Log-Aufbereitung + Shell-Footer | MITTEL | info/warn/error Klassifizierung; Zeile in Shell-Bottom (siehe project_logger_ui.md) |
| Inventur | MITTEL | inkl. Inventurliste (Druck) + mobile App |
| Shop-Export / WooCommerce Sync | MITTEL | Design: db_design_entscheidungen.md |
| Gutschein-Modul | MITTEL | Design: project_gutscheine.md |
| Buchhaltung/DATEV | MITTEL | Design: project_buchhaltung.md |
| Etiketten-Modul | MITTEL | ZPL vs. Dompdf — Entscheidung offen |
| Adressetiketten | MITTEL | A4-Druck, Sichtkuvert |
| Installationsanleitung | MITTEL | Server-Setup, Composer, Migrations, Cronjobs (inkl. Mahnwesen), RKSV |
| Abrechnung Mietfach | NIEDRIG | monatlich/quartalsweise |
| Spendenübersicht Yarnpride | NIEDRIG | |
| Preisliste | NIEDRIG | |
| Anzahlungsrechnung | NIEDRIG | ANZ-2026-XXXXX |
| Kunden-Merge-UI | NIEDRIG | |
| Seriennummern | NIEDRIG | |

## Session 22 erledigt (2026-07-03) — erstes Live-Deployment + VPN

- **Merkmale/Bilder-Modul im Code verifiziert**: beide bereits vollständig fertig, Memory war veraltet ("in Arbeit") — korrigiert
- **Installationsanleitung geschrieben** (`docs/installation.md`) — auch für Tester-Weitergabe gedacht (siehe [[project_installationsanleitung]])
- **Migrations-Runner gebaut**: `erp/database/migrate.php` (run/status/bootstrap) + `baseline_schema.sql` (Struktur-Dump als Installationsgrundlage, da Migrationen 001–003 fehlen und `004`–`104` nicht von Null durchlaufen)
- **`create_admin.php`**: interaktive Admin-Anlage statt Hash-von-Hand; bewusst kein fixer Superadmin-Account über alle Installationen (Sicherheitsentscheidung nach Diskussion)
- **Migration 105**: Jarvis-Systembenutzer automatisch geseedet (idempotent, keine feste ID)
- **Drei Bugs gefunden + behoben** beim ersten echten Live-Test: fehlendes Semikolon in Migration 005, `BfrService.php` hart codierte Jarvis-ID (jetzt per Username wie `LagerService`), `mahnwesen.php`-Cronjob crashte an `Logger::log()` ohne Session (NOT-NULL-Verletzung), `dashboard.php` `TypeError` bei leerer Kassenumsatz-Liste (frische DB), fehlendes `erp/public/index.php` (Apache zeigte Verzeichnisliste)
- **Server-PC live aufgesetzt**: XAMPP+MariaDB, DB migriert, Admin+Jarvis angelegt, Cronjobs (Mahnwesen täglich 6 Uhr, BFR-Nachsignierung alle 5 Min) über Windows Task Scheduler
- **WireGuard-VPN produktiv**: Server `10.13.13.1` + erster Client `10.13.13.2`, Portfreigabe UDP 51820 am UPC/Magenta-Router, siehe [[project_infrastruktur]] + `docs/installation.md` Anhang C. Zwei Firewall-Stolpersteine dokumentiert (ICMP + Port 80 auf neuem virtuellen Adapter)
- **Backup-Strategie besprochen und geplant** (noch nicht gebaut) — siehe [[project_backup_strategie]]
- **Für nächste Session vorgemerkt**: `/mealana/`-Pfad konfigurierbar machen, Versionsnummer in Fußzeile, Logo-Konfiguration (siehe [[project_whitelabel_branding]])

## Session 21 erledigt (2026-07-02)
- **Lieferanten-Erweiterung** (Migrations 097–099): laender-Referenztabelle mit EU-Flag, firma/firmenzusatz, ustid, steuerregel-Enum, standard_lieferkosten, Bankverbindung, Vertreter-anrede; Vertreter-Anlage als Repeatable-Row im Neuformular
- **RKSV/BFR-Integration komplett** (Migrations 100–104): BfrService (Verkauf+Storno-Signierung mit strikter TN-Reihenfolge, Nachsignierung mit Sammelbeleg-Protokoll bfr_nachsignierungs_laeufe, Nullbeleg monatlich+manuell, Gesamtumsatzzähler-Sperre VOR statt NACH Belegerstellung), Nacherfassungs-Seite (public/kasse/nacherfassung.php), Kassen-Registrierung mit bfr_aktiv_seit-Stichtag (verhindert Nachsignierung historischer/Kassen-ID-fremder Belege bei Hardware-Wechsel), Cronjob (cron/bfr_nachsignierung.php), echter QR-Code via endroid/qr-code (composer) statt Klartext
  - Reale BFR-Installationsanleitung gelesen (D:\ERP\mealana\import\BFR_Installationsanleitung.pdf) — Startbeleg + Monats-/Jahres-Nullbelege macht BFR intern selbst, unsere Logik bleibt trotzdem als bewusste Redundanz
  - X-Bon/Z-Bon bestätigt NICHT signaturpflichtig nach österr. RKSV
  - Nebenbei-Bugfix: Logger::log() fiel in Hintergrund-Kontexten (kein $_SESSION) auf null zurück → SYSTEM_BENUTZER_ID (Jarvis) jetzt explizit übergeben; gleicher Bug in cron/mahnwesen.php noch offen
  - Details: siehe project_rksv_bfr.md
- **Bugfix Hersteller-Neuanlage**: Modal schickte immer ein leeres `id`-Feld mit, `insert()` hatte keinen `:id`-Platzhalter → PDO HY093 Fatal Error → "Netzwerkfehler" im Browser. Fix: `unset($data['id'])` in HerstellerService::save(). Systemweit geprüft — PartnerRepository::insert() hat dieselbe Schwachstelle, aktuell aber nicht ausgelöst (separates Formular ohne id-Feld)
- Memory-Backup nach D:\ERP\mealana\.claude\memory\ gesynct

## Session 20 erledigt (2026-07-01) — Bug-Fix Session
- **Picklisten**: 'abholbereit' Aufträge erscheinen nicht mehr in "offene" Picklisten-Liste
  - abschliessen.php: 'abholbereit' in NOT IN Ausschlussliste
  - picklisten.php: `<details>`-Abschnitt mit letzten 20 abgeschlossenen Picklisten (eingeklappt)
- **Punkt 2 (Lagerbewegungen vorher/nachher)**: Bestätigt als altes Relikt-Testdaten — kein Code-Fix nötig
- **Packplatz Chargen-Popup**: Vorgewählte Mengen beim erneuten Öffnen sichtbar; "+" gesperrt wenn total ≥ benötigt
  - packplatz_scan.js: `zeigeChargePopup` füllt `chargeEingaben` aus `chargenAuswahl[idx]` vor; `chargeUpdateGesamt` disabled alle Plus-Buttons bei Vollbelegung
- **Kassa Chargen-Popup**: +/- Buttons pro Charge-Zeile; Multi-Charge; Überbestand erlaubt
  - bon.php: `_kasseChargenDaten[]` Array (kein String in onclick); `kasseChargeBtn(rowIdx,delta)`; `_kasseChargeZeileAktualisieren(rowIdx,menge)`; `chargeKasseBestaetigen()` erstellt einen Bon-Eintrag pro Charge
  - **Bug Fix**: onclick HTML-Attribut-Quoting-Problem (charge-Strings mit Anführungszeichen brachen onclick) → Lösung: nur Integer rowIdx in onclick, Daten aus `_kasseChargenDaten[rowIdx]`
- **Kassa Zeile+ mit Charge**: `zeilePlus(i)` prüft `hat_chargen`/`charge_pflicht` und öffnet Charge-Popup statt blind +1
  - bon.php: `artnr`, `hat_chargen`, `charge_pflicht` werden im warenkorb-Objekt mitgespeichert
- **Auto-Abgeschlossen** (lieferart='versand', bezahlt + versendet → 'abgeschlossen'):
  - packplatz/warenausgang/abschliessen.php: nach logStatus prüfen ob zahlungsstatus='bezahlt' → UPDATE zu 'abgeschlossen'
  - AuftragService.php bucheZahlung(): prüfen ob lieferstatus='versendet' → UPDATE zu 'abgeschlossen'

## Session 19 erledigt (2026-06-29)
- **Chargen-Tracking vollständig** (bug_charge_tracking.md → BEHOBEN):
  - Kasse: Charge-Dialog Popup (Overlay ov-charge) — wählen/nachzutragen/ohne Charge
  - bon_speichern.php: Rückbuchung liest `charge` aus `auftrag_positionen` statt NULL zu buchen
  - Packplatz intern: Umlagerung + Zustandsumbuchung beide mit Charge-Dropdown (async Fetch aus chargen_ajax.php)
- **Kasse Namenssuche**: Artikel-Suche Modal (ov-artikelsuche) mit 250ms Debounce + ajax_artikel.php?suche=
  - PDO LIMIT-Bug behoben: `sucheArtikel()` nutzt jetzt `bindValue(':lmt', $limit, PDO::PARAM_INT)`
  - Such-Button: kompaktes 44×44 Icon (🔍), kein langer Textbutton
- **Wareneingang**: Multi-Search (Leerzeichen-getrennte Wörter), Kind-Name + Vater-Name getrennt anzeigen
- **Aufträge/Liste**: Abgeschlossene ausgeblendet by default, Checkbox "Abgeschlossene anzeigen", graue gedimmte Zeilen
- **Bestellvorschläge**: Einträge mit meldebestand=0 oder NULL ausgeblendet (HAVING-Klausel)
- **Kunden-Dropdown** in Aufträge/Neu: 3-Zeilen-Anzeige (Name fett, E-Mail, Adresse)
- **Nav-Trennlinien**: Hellblaue vertikale Divider (erp-nav-divider) nach Dashboard und vor Einkauf
- **Aufträge Artikel-Limit**: findArtikelFuerSuche() LIMIT 25→75; Artikel-Browser in neu.php breiter

## Session 18 erledigt (2026-06-29)
- **K1-Bon Laufkunde Bug**: kunden_snapshot vom Original-Auftrag immer auf K1 kopieren (bon_speichern.php K1-UPDATE)
- **Abholbereit+bezahlt Flow**: exakt (kein Bon) / retour (Retour-Bon + Barauszahlung) / extra (nur Extras) / mix — alle 4 Fälle implementiert
  - bon.php: 5 neue Funktionen, 3 neue State-Variablen, 2 neue Overlays, _zahlBetrag() Helper
  - bon_speichern.php: nur_abschliessen Pfad, bezahlt-Filter für bonErstellungPositionen, Retour-Zahlungsbuchung (negativ)
  - bon_druck.php: block='retour' Abschnitt "↩ RÜCKGABE", signed Steuer-Totale, GESAMT vs. RÜCKGABE
  - Gutschein-Hook vorbereitet (ov-retour-bar Button → wenn Gutschein-Modul fertig)

## Session 17 erledigt (2026-06-28)
- Mail-System Komplett-Überarbeitung (siehe Mail-System oben)
- Page-Loader Overlay global in shell_bottom.php (alle Views, verhindert Wild-Klicken bei langsamen Abfragen)
- Loader zeigt bei jedem Link-Klick / Form-Submit, versteckt sich bei pageshow (inkl. Zurück-Button)
- Ausnahmen: `target="_blank"`, Anker-Links, `data-no-loader`, AJAX-Formulare; 15s Sicherheits-Timeout

## Session 16 erledigt (2026-06-28)
- Dashboard gebaut (dashboard.php): KPIs, Fehlbestand-Greedy, Kanal-Balken, Log-Bar
- Shell "···" → Dropdown (Kasse/Packplatz/Lizenzverwaltung), Dashboard-Link in Nav
- start.php → ERP-Kachel zeigt auf dashboard.php
- Kasse/Packplatz: "→ Zurück zum ERP" auf start.php umgeleitet
- KassenService: versand_datum=NOW() bei Bon-Erstellung (= Lieferdatum/Rechnungsdatum)
- Pickliste: eine pro Auftrag statt alle zusammen
- Packplatz abschliessen.php: Index-Fix (gleicher Filter wie scan.php) → Teillieferung bucht jetzt korrekt
- Packplatz Overlays: Carrier-Dropdown (Post/DHL/DPD/GLS, Default Post AT)
- Tracking-Spalten vereinheitlicht: Packplatz schreibt tracking_nr + versanddienstleister
- Migration 087: auftrag_lieferungen (History-Tabelle für alle Lieferungen inkl. Teillieferungen)
- auftraege/detail.php: Tracking-Tabelle statt Einzelfeld, Auto-Migration alter Einträge

## Offene technische Punkte
- ~~Preis-Query Datums-Filter~~ ✅ behoben 2026-06-26
- ~~artikel_achsen.sort_order~~ ✅ behoben 2026-06-26
- ~~Hersteller: aktualisiert_am~~ ✅ war bereits vorhanden
