# Memory Index

## Aktuell / Steuerung
- [Belege + Abschluss-Umbau](project_belege_abschluss.md) — ✅ 2026-10-07 komplett gebaut, Klicktest B1–B7 durch, committed+gepusht (Teilrechnungen, Zahlbeleg, Rechnungskorrektur, Belege-Spalte, Versandart ändern)
- [⏭️ Nächste Session: Kasse-Punkte](project_kasse_naechste_punkte.md) — Schnellwahl/Divers→Artikelgruppe, Offline-Kasse neu (vorbereiteter Auftrag), K3-Signatur-Kasse lief als K1 → absichern
- [🎯 Version 1.0 = Live-Start](project_version_1_0_release.md) — Live erst auf konsolidierter 1.0, bis dahin Dev-DB gegen Testshop
- [🗺️ Roadmap-Reihenfolge](project_roadmap_reihenfolge.md) — bei "was als Nächstes" IMMER hier nachsehen
- [📋 Offene Klicktests 21–35](project_offene_klicktests.md) — Lagerplätze + Händler, Jacky testet bei echter Einrichtung
- [Projekt: MeaLana ERP Status](project_status.md) — Implementierungsstand

## User + Feedback
- [User Profile](user_karl.md) — Jacky (Indranet), Anfänger, Claude ist Trainer; Barbara schaut bei UI mit
- [Feedback: Trainer-Ansatz](feedback_trainer.md) — erklären, selbst schreiben lassen
- [Feedback: End-of-Day Updates](feedback_eod.md) — CLAUDE.md am Tagesende aktualisieren
- [Feedback: Memory-Backup bei Commit](feedback_memory_backup_bei_commit.md) — Memory nach .claude/memory/ im Repo syncen bei commit/push
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
- [Feedback: Dedup Intra-Gruppe prüfen](feedback_dedup_intra_gruppe_pruefen.md) — gegen autoritative Quelle prüfen

## Referenzen
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
- [Konfigurator-Modul](project_konfigurator_modul.md) — Konfig-Werte auf allen Belegen
- [Händler-Konsignation](project_haendler_konsignation.md) — Außenlager, Verkaufsmeldung → Netto-Rechnung
- [Partner-Modul](project_partner_modul.md) — Partner-Lager gebaut, Abrechnung offen
- [Sammelabholung](project_sammelabholung_auftraege.md) — mehrere Aufträge an Kasse, Klicktest offen
- [Packplatz-Modul](project_packplatz.md) — fertig inkl. Teillieferung-Positions-Split
- [PLC / EasyPak Versand](project_plc_versand.md) — Nachnahme bei Teillieferung gefixt
- [Paperless-Rechnung (geplant)](project_paperless_rechnung_modul.md) — QR statt Papier, mit Shop-Server

## Kasse / RKSV
- [Kassen-Bon Design](project_kasse_bon_design.md) — Retoure-Redesign, Doppel-Gutschrift-Sperre, A4-Bon
- [Kassen-Verwaltung](project_kassen_verwaltung.md) — BFR-Hardwaretest erfolgreich
- [RKSV/BFR Implementierung](project_rksv_bfr.md) — Hardwaretest 2026-07-19 ok
- [Kundenanzeige-Modul](project_kundenanzeige_modul.md) — läuft auf Fully Kiosk
- [🟢 BUG: Offline-Resync-Kollision](bug_offline_resync_kollision.md) — behoben 2026-07-07
- [🟢 BUG: Kasse Chargen-Popup leer](bug_kasse_charge_unbekannt.md) — behoben 2026-09-30

## Buchhaltung / Statistik
- [Buchhaltungsmodul](project_buchhaltung.md) — Export (3230/4090/Kombi-Bons), Zahlungs-Kontrolle, Kontenplan
- [Statistik Konzept](project_statistik.md) — Lagerwert gebaut (Migration 192), Cron eintragen
- [Kleinunternehmer-Modus](project_kleinunternehmer.md) — globaler Schalter
- [🟢 BUG: Versand nicht im Betrag](bug_versand_nicht_im_betrag.md) — behoben 2026-09-30

## Artikel / Lager
- [Artikel-Features Roadmap](project_artikel_features.md) — Merkmale + Bilder fertig
- [Merkmale-Modul Design](project_merkmale.md) — 2 Ebenen, Modal
- [Preise-Modul Design](project_preise.md) — Effektivpreis, Aktionen, Grundpreis
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
- [Google Shopping + Search Console](project_google_shopping_search_console.md) — 0% Konzept
- [Kundendatenbank Design](project_kundendatenbank.md) — AES-256-GCM, DSGVO
- [JTL Kunden+Aufträge-Import](project_jtl_kunden_auftraege_import.md) — 6.775 Kunden + 39.191 Archiv-Aufträge
- [JTL Vater+Kind-Import](project_jtl_vater_kind_import.md) — Achsenerkennung, abgenommen
- [JTL Bilder-Import](project_jtl_bilder_import.md) — fertig
- [JTL-Import Wissen](project_jtl_import.md) — CSV-Struktur, Mapping

## Infrastruktur / UI
- [Infrastruktur / Server-Setup](project_infrastruktur.md) — MySQL-Absturz 2026-08-29 behoben
- [Backup-Strategie](project_backup_strategie.md) — wartet auf 1.0
- [Installationsanleitung](project_installationsanleitung.md) — Baseline-Neuschnitt
- [Update-Mechanismus](project_update_mechanismus.md) — zurückgestellt bis Lizenz
- [Whitelabel/Branding](project_whitelabel_branding.md) — NAHTLOS-Logo
- [UI Redesign Plan](project_ui_redesign.md) — JTL-Layout, Barbara Mitsprache
- [Rechte & Rollen](project_rechte_rollen.md) — fertig, Lizenzserver offen
- [Logger UI](project_logger_ui.md) — fertig

## Behobene Bugs (Archiv)
- [Kategorie-Verschieben](bug_kategorie_verschieben.md) · [Aktions-Kategorie](bug_aktionskategorie_zuweisung.md) · [Achsen Modal](feedback_achsen_modal.md) · [PDO extra Key](bug_hersteller_modal_insert.md) · [PDFs in Git](bug_storage_pdfs_in_git.md) · [Kind-Vater-Button](bug_kind_zurueck_vater_button.md)
- [Massenaktion Flash](bug_artikelliste_massenaktion_flash.md) · [PNG-Endung](bug_bild_upload_png_endung.md) · [EAN Excel](bug_ean_excel_notation_repariert.md) · [Term-Pagination](bug_shop_sync_term_pagination.md) · [Kategorienbaum-Performance](bug_kategorienbaum_performance.md) · [Vater-Bestand WE](bug_vater_artikel_bestand_wareneingang.md)
