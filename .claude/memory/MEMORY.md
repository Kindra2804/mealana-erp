# Memory Index

## Aktuell / Steuerung
- [💶 Zahlungseingänge](project_zahlungseingaenge.md) — ✅ 2026-11-21 gebaut (Verkauf, nutzt zahlung_buchen.php, Rückgängig = 8 s Verzögerung), mit 1.0.28; Babsi-Klicktest + Live offen
- [🎭 Demo-DB + Teaser-Webseite](project_demo_datenbank.md) — Garnstube Muster GmbH; ✅ Schritte 1–3 (Schalter, Sortiment/Kunden, 12 Monate Verlauf) lokal + LIVE https://nahtlos.indranet.at/demo/ (2026-11-21); frisch ohne Cron (Neuaufbau beim 1. Aufruf je Tag, Nachholen alle 10 min); offen: Teaser-Webseite
- [📒 Katalog-Werkstatt](project_katalog_werkstatt.md) — ✅ E:\Laser\Katalog-Werkstatt (start.bat), Formgruppen W/A/Q/H/V/E; ✅ 2026-11-18 Indra-Artikelnummern LIVE umnummeriert (1.0.25, 171 Artikel), Werkstatt-Codes = ERP
- [🧶 Lang-Yarns-Import](project_lang_import.md) — ✅ 2026-11-16 gebaut (bdd367a, 0dd1a24, Migr. 254+255 → nächste 256; Fix Varianten …
- [🧬 Varianten-Muster + Vater-Bestand](project_varianten_muster.md) — ✅ 2026-11-16 gebaut (86d79e9, c4f4afc): Bestand an Variante übergeben, Massenaktion …
- [Belege + Abschluss-Umbau](project_belege_abschluss.md) — ✅ 2026-10-07 komplett gebaut, Klicktest B1–B7 durch, committed+gepusht (Teilrechnungen, Zahlbeleg, …
- [Kasse-Punkte 1–3 + Papier-Messe](project_kasse_naechste_punkte.md) — ✅ 2026-11-15 Offline-Kasse light = Mobil-Messe Sunmi (ohne Signatur, Nacherfassung, Migr. 248), Test am …
- [✅ 1.0-Check](project_1_0_check.md) — Paket-Verlauf 1.0.1–1.0.24 + offene Live-Schritte (Details in der Datei); 1.0.24 gebaut; 1.0.26 LIVE 2026-11-18; **1.0.27 LIVE 2026-11-19** + Lieferanten/Kontenplan + JTL-Kunden/Aufträge am Live importiert; wartet auf Babsis Ausschlussliste (dann zusammen: Filter + Bareingang JTL-Rechnung + Umstellungs-Checkliste, siehe Parallelbetrieb; Migr. 256+257, Kassen-Liste, JTL-Import Browser, Datentransfer Lieferanten/Kontenplan); 2026-11-19: Jacky prüft, was von Dev nach Live übertragen werden soll (z.B. Lieferanten) …
- [✅ Woo-Shop-Checkliste](project_woo_shop_checkliste.md) — für MeaLana & jeden neuen Shop: Übersetzungen, Germanized-Texte, Seiten/Endpunkte …
- [🧪 Shop-Tests über Dev](project_shop_tests_ueber_dev.md) — ✅ SOW-Runde 2026-11-13 durch, Fixes fürs Paket 1.0.11 gelistet; Live-Shops für Tests …
- [🏛️ Indra-Design = EU von Babsi](project_indra_design_eu.md) — ✅ 2026-11-14 ONLINE als EU (1.0.12 live, Rechtstexte, KU, Texte bereinigt); offen: 1. echte Bestellung …
- [🎨 indra-design Shop](project_indra_design_shop.md) — Logo, Farben, Snippets; 2026-11-04: Wurzelkategorie (Migr. 223, auf Live setzen!), Saisonales/Sonstiges, Menü …
- [🔁 JTL-Parallelbetrieb](project_jtl_parallelbetrieb.md) — Lager → JTL-Abgleich (Migr. 231); 2026-11-19 Plan Kasse aufs ERP bei MeaLana-Online auf JTL: machbar, Bareingang zu JTL-Rechnung + Checkliste/Ablaufplan bauen, sobald Babsis Liste da ist; Ameise-Chargen ✅
- [🧦 SOW-Umstellung JTL → Woo](project_sow_umstellung.md) — ✅ 2026-11-14 ONLINE (Wartung 25 aus, 1.0.13 live); offen: erste echte Bestellung prüfen, Startbestand …
- [🧦 Sockenwolle Logo + Layout](project_sow_shop_design.md) — Hausfarben: MeaLana blau, SOW weiches Rot, bio-wolle grün; Logo fertig: Schrift A Weinrot + Babsis rote …
- [🌿 bio-wolle WP-Aufbau](project_bio_wolle_aufbau.md) — ✅ 2026-11-14 ONLINE (Kurztest, 1.0.13 Steuerklassen live); offen: erste echte Bestellung prüfen; nächste …
- [🌿 bio-wolle Logo + Startseite](project_bio_wolle_shop_design.md) — ✅ 2026-11-05 Logo 1b Tanne hell #3b6b52 als Vektor (shop/branding/bio-wolle/), Startseiten-Entwurf …
- [⚖️ Widerrufsbutton Woo-Shops](project_widerrufsbutton.md) — Germanized ≥4.0 kann es gratis; indra-design ✅ eingestellt + SMTP-Plugin; Widerruf …
- [💙 MeaLana neues Outfit](project_mealana_shop_design.md) — 2026-11-05 Entwurf 1 (Mega-Menü, Bestseller/Sale/Neu, Akzent-Boxen, Blau großzügig) wartet auf Babsi; …
- [📦 Datentransfer ERP → ERP](project_datentransfer.md) — 2026-11-09 gebaut + getestet (scripts/datentransfer.php), für Indra Dev → Live; Woo-Shop …
- [✉️ Mail-Absender je Shop](project_mail_absender_je_shop.md) — ✅ gebaut 2026-11-11 (Migr. 229, Mailer::fuerShop, Einstellungen → Mail → Absender je Kanal); Live-Einspielen …
- [🧭 Shop-Menü aus dem ERP](project_shop_menue.md) — 2026-11-05 gebaut (Migr. 226, Plugin 1.4.0), live auf indra-design; Hersteller-Spalten …
- [🏷️ Indra-Sortiment + Preise](project_indra_sortiment.md) — Motivcodes W/A/K/N/P/S/C/E, Preisliste, Untersetzer = Motiv-Vater × Material; 2026-11-04 Weihnachtsanhänger …
- [💡 Newsletter + Slider aus dem ERP](project_newsletter_slider_ideen.md) — Slider ✅ (Migr. 224/225, Plugin 1.3.1); Newsletter Schritt 1–5 ✅ (Migr. 249–253, Cron newsletter_versand alle …
- [🎯 Version 1.0 = Live-Start](project_version_1_0_release.md) — Live erst auf konsolidierter 1.0, bis dahin Dev-DB gegen …
- [🗺️ Roadmap-Reihenfolge](project_roadmap_reihenfolge.md) — 2026-11-14: Massenaktion Standardpreis → X-für-Y/BOGOF → Offline-Kasse light → …
- [Klicktest-Fixes 2026-10-29](project_klicktest_fixes_20261029.md) — Mietfach/MR-Nummernkreis/Z-Bon eigener Kreis K1-Z-00001 (Migr. …
- [📋 Klicktests 21–35](project_offene_klicktests.md) — Lagerplätze + Händler ✅ bestanden 2026-10-30
- [💶 Lizenzierung + Preise](project_lizenzierung_preise.md) — Kauf+Wartung; Grundmodul 790 inkl. Verkauf+Packplatz; 2026-11-01 Lizenz-Bau-Entscheidungen 1–8 (nur …
- [🚀 Soft-Go-Live Sockenwolle](project_soft_golive_sockenwolle.md) — 2026-11-04: Start jetzt mit indra-design.at (Demoshop zurücksetzen), SOW danach; …
- [Dropshipping-Modul](project_dropshipping.md) — gebaut 2026-11-01 (Migr. 215), Klicktest Jacky offen, noch nicht committed
- [Projekt: MeaLana ERP Status](project_status.md) — Implementierungsstand

- [✉️ Kuvert-Etikett Schilder](project_kuvert_etikett.md) — ✅ gebaut 2026-11-12 (A4 2×A5, Code 128 Artikelnummer, Kasse ß-tolerant); Scanner-Test + …
- [🧾 Kassen-Liste + Mini-Anhänger](project_kassenliste.md) — ✅ 2026-11-19 gebaut (Migr. 256 Kurznamen; nächste Migr. 258), Notiz 1.0.27; Ränder + Kategorie-Häkchen → 1.0.28; Scan-Test + Babsi + Live offen
- [🏷️ Preisschilder-Werkstatt](project_preisschilder.md) — ✅ komplett 2026-11-12 (Messe/Laden/Aufsteller/IVAR/Anhänger+Strickstücke, …

- [🟢 Schilder fehlten in Artikelliste](bug_schilder_partnerware_liste.md) — ✅ behoben 2026-11-14 (12d8371): Einklapp-Regel nur, wenn Layout in derselben Kategorie; Live mit nächstem …

## User + Feedback
- [User Profile](user_karl.md) — Jacky (Indranet), Anfänger, Claude ist Trainer; Barbara schaut bei UI mit
- [Feedback: Trainer-Ansatz](feedback_trainer.md) — erklären, selbst schreiben lassen
- [Feedback: Immer Deutsch](feedback_sprache_deutsch.md) — auch kurze Zwischenmeldungen zwischen Arbeitsschritten auf Deutsch
- [Feedback: End-of-Day Updates](feedback_eod.md) — claude.md am Tagesende aktualisieren; im Repo heißt sie **claude.md (klein)** → `git add …
- [Feedback: Memory-Backup bei Commit](feedback_memory_backup_bei_commit.md) — Memory nach .claude/memory/ im Repo syncen bei …
- [Feedback: Commit je Schritt](feedback_commit_je_schritt.md) — bei mehrstufigen Bauten nach jedem fertigen Schritt committen+pushen, ohne zu …
- [Feedback: Modul-Vorgehen](feedback_modul_vorgehen.md) — Referenz-Check große WAWIs + MeaLana-Extras
- [Feedback: CSS-Strategie](feedback_css_strategie.md) — inline style jetzt, extern beim Refactor
- [Feedback: Design-Workflow](feedback_design_workflow.md) — ASCII → SVG → HTML, SVG für Barbara
- [Feedback: Banner Auto-Hide](feedback_banner_autohide.md) — Banner nach ~3s ausblenden
- [Feedback: Barbara UI](feedback_barbara_ui.md) — blaues "!" statt ⚠
- [Feedback: Modul-Abschluss-Checkliste](feedback_js_auslagern.md) — JS auslagern, SQL-Kommentare, Anleitung
- [Feedback: Emoji CSS](feedback_emoji_css.md) — filter:grayscale(1) statt color
- [Feedback: Beide Handbücher](feedback_beide_handbuecher.md) — docs/handbuch + bedienungsanleitung.php synchron
- [Feedback: Flaggen-Emoji](feedback_flag_emoji.md) — rendern unter Windows nicht
- [Feedback: Test-Isolation](feedback_test_isolation.md) — Testskripte nie ohne Cleanup gegen echte Daten
- [Feedback: Scope ohne validierten Bedarf](feedback_scope_ohne_bedarf.md) — erst Bedarf prüfen
- [📱 Tablet per WireGuard ans Live-ERP](project_tablet_wireguard.md) — Idee Jacky 2026-11-15, zwischendurch probieren (Android-App, Anhang …
- [Feedback: Demo + Webseite nachziehen](feedback_demo_nachziehen.md) — bei neuen Seiten/Funktionen Demo-Generator + Teaser-Webseite anpassen, bei Kleinigkeiten nicht
- [Feedback: Dedup Intra-Gruppe prüfen](feedback_dedup_intra_gruppe_pruefen.md) — gegen autoritative Quelle prüfen

## Referenzen
- [Dev-ERP Browser-Login](reference_dev_erp_login.md) — Benutzer claude, PW in import/zugang Woo Claude.txt; Kasse nicht (Arbeitsplatz …
- [WordPress-Zugang für Claude](reference_wp_claude_zugang.md) — wp-admin "claude" auf indra-design.at
- [WP-Snippets im Repo](reference_wp_snippets_repo.md) — Kopien in mealana\shop\wp-snippets\
- [Browser-Testing-Tools (Playwright)](reference_browser_testing_tools.md) — unter .claude-browser-tools/
- [RKSV: BFR BONit API](reference_bfr_api.md) — POST XML /register, TaxG A-E, offline

## Verkauf / Aufträge / Dokumente
- [Auftragsmodul Design](project_auftragsmodul.md) — Zahlungs+Lieferstatus getrennt, Zeitraum-Filter
- [Verkauf Workflows](project_verkauf_workflows.md) — Mahnstufen Rechnungskunden (Migration 193), Vorkasse-Auto-Storno-Fix
- [Zahlung buchen Umbau](project_zahlung_buchen.md) — Betrag+Datum, Teil-/Überzahlung
- [Dokumente-System](project_dokumente_system.md) — Twig+Dompdf, Nummernkreise, Lieferanten-Bestellung PDF
- [Retouren-Zusammenführung](project_retouren_zusammenfuehrung.md) — gemeinsame Zähler retourniert/gutgeschrieben
- [Gutschein-Modul](project_gutscheine.md) — fertig; wartet auf Babsis Motiv-Vorlagen
- [Zahlung+Versand ERP → Shop](project_zahlung_erp_an_shop.md) — processing/completed per Cron
- [Konfigurator-Modul](project_konfigurator_modul.md) — 2026-11-13: „Konfiguration wählen“ in Shop-Übersicht, Bausteine nur intern, …
- [Händler-Konsignation](project_haendler_konsignation.md) — Außenlager, Verkaufsmeldung → Netto-Rechnung
- [Partner-Modul](project_partner_modul.md) — Steuerberatungs-Antworten 2026-10-26; Schritte 1–5 gebaut (inkl. Export 2026-10-28), Konten änderbar durch …
- [Artikelbilder auf Belegen](project_belege_artikelbilder.md) — Einstellung je Belegtyp, gebaut 2026-10-28 (Migration 211), Mail-Test …
- [Abholorte](project_abholorte.md) — eigene Abholadressen + Lager-Zuordnung, Wollboutique Bahngasse 13; gebaut 2026-10-28 …
- [Sammelabholung](project_sammelabholung_auftraege.md) — mehrere Aufträge an Kasse, Klicktest offen
- [Packplatz-Modul](project_packplatz.md) — fertig inkl. Teillieferung-Positions-Split
- [PLC / EasyPak Versand](project_plc_versand.md) — Nachnahme bei Teillieferung gefixt
- [Paperless — Beleg per QR](project_paperless_rechnung_modul.md) — fertig + Klicktest 2026-11-01, läuft auf beleg.mealana.at; „Ohne Druck“ entfernt; Live-ERP: URL+Schlüssel …

## Kasse / RKSV
- [🧾 RKSV-Exporte + Badge Fremdumsatz](project_rksv_exporte_fremdumsatz.md) — 2026-11-15 ✅ Badge „Fremd“ + Fremdumsatz aus Dashboard/Statistik (4a78804) → ✅ …
- [Kassen-Bon Design](project_kasse_bon_design.md) — Retoure-Redesign, Doppel-Gutschrift-Sperre, A4-Bon
- [Kassen-Verwaltung](project_kassen_verwaltung.md) — BFR-Hardwaretest erfolgreich
- [RKSV/BFR Implementierung](project_rksv_bfr.md) — Hardwaretest 2026-07-19 ok
- [Kundenanzeige-Modul](project_kundenanzeige_modul.md) — läuft auf Fully Kiosk
- [🟢 BUG: Offline-Resync-Kollision](bug_offline_resync_kollision.md) — behoben 2026-07-07
- [🟢 BUG: Kasse Chargen-Popup leer](bug_kasse_charge_unbekannt.md) — behoben 2026-09-30

## Buchhaltung / Statistik
- [Buchhaltungsmodul](project_buchhaltung.md) — Export (3230/4090/Kombi-Bons), Zahlungs-Kontrolle, Kontenplan
- [Statistik Konzept](project_statistik.md) — Lagerwert gebaut (Migration 192), Cron eintragen
- [Kleinunternehmer-Modus](project_kleinunternehmer.md) — globaler Schalter (wirkt nur auf PDF-Belege, siehe Steuerprofil)
- [⚖️ Steuerprofil-Audit](project_steuerprofil.md) — 2026-11-01: KU halb, Bilanz/EA + Soll/Ist fehlen, Export fest KG/Soll → Punkt …
- [🟢 BUG: Versand nicht im Betrag](bug_versand_nicht_im_betrag.md) — behoben 2026-09-30

## Artikel / Lager
- [Artikel-Features Roadmap](project_artikel_features.md) — Merkmale + Bilder fertig
- [Merkmale-Modul Design](project_merkmale.md) — 2 Ebenen, Modal
- [Preise-Modul Design](project_preise.md) — Effektivpreis, Aktionen, Grundpreis; TODO X-für-Y gemischt + BOGOF (Kasse+Shop)
- [💶 Massenaktion Preise ändern](project_idee_massenaktion_preis.md) — ✅ gebaut 2026-11-14 (2205453), ✅ fertig + Klicktest 2026-11-14 (67e2be5), Live mit …
- [🛒 Mengen-Aktionen X für Y / 3 für 2](project_mengen_aktionen.md) — Schritte 1–5 ✅ (Migr. 241–244, Plugin 1.7.0 auf indra; SOW/Bio offen; Snippet 34685 bleibt bis …
- [Aktions-Modul](project_aktionen_modul.md) — fertig
- [Bilder-Modul](project_bilder_modul.md) — fertig, Logo-Bugkette gelöst
- [Spalten-Picker](project_spalten_picker.md) — Kind-Zeilen-Parität
- [Kategorie-Verwaltung](project_kategorie_verwaltung.md) — Filter + Massenaktion
- [Lager Konzept](project_lager_konzept.md) — Lagerplätze Variante A gebaut
- [Chargen-Konzept](project_chargen_konzept.md) — 3 Typen, alle Bewegungsstellen
- [Chargen-Tracking](bug_charge_tracking.md) — Kasse/Packplatz/Umlagerung mit Charge
- [Chargen-Nachverfolgung](project_chargen_nachverfolgung.md) — lager/chargen_nachverfolgung.php
- [Inventur-Modul](project_inventur_konzept.md) — fertig, Live-Akzeptanztest offen
- [Inventur-Einzelnotizen](project_inventur_hinweis.md) — RKSV-Popup, Warndreieck
- [Bestellmodul Design](project_bestellmodul.md) — PO-Workflow, EAN-Scan
- [Lieferanten-Erweiterung](project_lieferanten_erweiterung.md) — Länder, UStID, Bank
- [Grundpreis-Rechtslage Wolle](project_grundpreis_rechtslage_wolle.md) — 1kg-Basis umgesetzt
- [Meterware Mindestabnahme](project_meterware_mindestabnahme.md) — fertig + live
- [Druck: Qualitätslisten](project_druck_listen.md) — für Druck-Modul vorgemerkt
- [Datenqualität 2026-08-12](project_datenqualitaet_20260812.md) — 3 Aktions-/Grundpreis-Bugs behoben
- [Datenqualität 2026-08-11](project_datenqualitaet_20260811.md) — Vater→Kind-Vererbung nachgezogen
- [Fünf Abendaufgaben 2026-08-09](project_fuenf_abendaufgaben_0809.md) — nächstes: Download-Artikeltyp
- [WAWI-Benchmark Gaps](project_wawi_gaps.md) — Lückenvergleich

## Shop / Import
- [Online-Shop-Anbindung](project_shop_sync.md) — Baufortschritt Sync
- [WooCommerce Sync Design](db_design_entscheidungen.md) — Kategorie-Sync, Kanal-Chips (+ DB-Design allgemein)
- [Shop-Theme/UX](project_shop_theme.md) — Swatch-Scroll-Fix wartet auf Babsi, Theme-Kauf pausiert
- [Hersteller-Shop-Filter + GPSR](project_hersteller_shop_filter.md) — Attribut fertig, GPSR offen
- [Google Shopping + Search Console](project_google_shopping_search_console.md) — Plan 2026-11-15; ERP→Woo GTIN/Yoast/Alt gebaut (1.0.19), Search Console + Merchant Center (SOW zuerst) offen, …
- [Kundendatenbank Design](project_kundendatenbank.md) — AES-256-GCM, DSGVO
- [JTL Kunden+Aufträge-Import](project_jtl_kunden_auftraege_import.md) — ✅ 2026-11-19 im Browser (Kunden → ⬇ JTL-Import, Migr. 257, 365-Tage-Regel + Prüfliste); Dev: 6.775 Kunden + 39.191 Archiv-Aufträge
- [JTL Vater+Kind-Import](project_jtl_vater_kind_import.md) — Achsenerkennung, abgenommen
- [JTL Bilder-Import](project_jtl_bilder_import.md) — fertig
- [JTL-Import Wissen](project_jtl_import.md) — CSV-Struktur, Mapping

## Infrastruktur / UI
- [Infrastruktur / Server-Setup](project_infrastruktur.md) — MySQL-Absturz 2026-08-29 behoben
- [Backup-Strategie](project_backup_strategie.md) — tägl. DB-Dump am Live seit 2026-11-10 (E:\ERP-Sicherung\taeglich), Außer-Haus …
- [Installationsanleitung](project_installationsanleitung.md) — Baseline-Neuschnitt
- [🔄 Update-Weg](project_update_mechanismus.md) — gebaut 2026-11-01: tools/paket/bauen.php + ··· → Updates (DB-Sicherung Pflicht, …
- [Whitelabel/Branding](project_whitelabel_branding.md) — NAHTLOS-Logo
- [UI Redesign Plan](project_ui_redesign.md) — JTL-Layout, Barbara Mitsprache; 2026-11-17 Seitenleisten-Gruppen + Import-Klappmenü + Kanalfarben je Shop …
- [Rechte & Rollen](project_rechte_rollen.md) — fertig, Lizenzserver offen
- [Logger UI](project_logger_ui.md) — fertig

## Behobene Bugs (Archiv)
- [Kategorie-Verschieben](bug_kategorie_verschieben.md) · [Aktions-Kategorie](bug_aktionskategorie_zuweisung.md) · [Achsen Modal](feedback_achsen_modal.md) · [PDO extra Key](bug_hersteller_modal_insert.md) · [PDFs in Git](bug_storage_pdfs_in_git.md) · [Kind-Vater-Button](bug_kind_zurueck_vater_button.md)
- [Massenaktion Flash](bug_artikelliste_massenaktion_flash.md) · [PNG-Endung](bug_bild_upload_png_endung.md) · [EAN Excel](bug_ean_excel_notation_repariert.md) · [Term-Pagination](bug_shop_sync_term_pagination.md) · [Kategorienbaum-Performance](bug_kategorienbaum_performance.md) · [Vater-Bestand WE](bug_vater_artikel_bestand_wareneingang.md)
