---
name: project-mietfach-artikel-import
description: Idee Jacky 2026-11-25 — Mietfach-Inhaber mit eigenem NAHTLOS exportiert Artikel, MeaLana importiert sie als Partnerware (XP-Nummern); Entscheidungen + Bauvorschlag, noch nicht gebaut
metadata:
  type: project
---

**Idee (Jacky 2026-11-25):** Mietfach-Inhaber mit eigenem NAHTLOS exportiert Artikel → im ERP des Mietfach-Betreibers (MeaLana) werden sie beim Partner angelegt. Spart Eintippen, Fehler, Bildersuche. Logging muss passen, **Wareneingang bleibt „Ware übernehmen“ wie gehabt**. Erster echter Testfall: Jackys Direktvertrieb „Karl Indra“ (Mietfach bei MeaLana, Lizenz L-100004-10).

**Entscheidungen Jacky:**
- Varianten gibt es (Geschmack, Geruch …) → müssen mit.
- Preis: der des Partners gilt, aber MeaLana kann einen eigenen Fachpreis setzen (der bleibt dann beim erneuten Import, neuer Partnerpreis nur als Hinweis).
- Exportieren kann jeder (Grundmodul); Import nur mit Modul **Partner & Mietfächer**.

**Ist-Stand Partnerware (geprüft 2026-11-25):** `PartnerLagerService` legt NUR flache Artikel an (Name, Preis, EAN, Steuer, EK, Stammplatz, Gruppe PARTNER, Nummer `XP<PartnerID 2-stellig>-<4-stellig>`), keine Vater/Kind, keine Bilder. Dev hat 1 XP-Artikel.

**Bauvorschlag (Claude):** Paket wie Datentransfer (.tar, daten.json + Bilder, Format-Version, Absender Firma+Lizenznr) · Massenaktion „Für Mietfach exportieren“ · Partner → Artikel → „Aus NAHTLOS-Datei übernehmen“ mit Vorschau neu/geändert/unverändert · Zuordnungstabelle (partner_id, quell_artikelnummer → artikel_id, letzter Partnerpreis, eigener_preis) für idempotenten Re-Import · Log je Übernahme · Lizenzprüfung · beide Handbücher · Demo nachziehen ([[feedback-demo-nachziehen]]). **Entschieden 2026-11-25: Variante A** — Varianten werden beim Import zu flachen XP-Artikeln („Vater Ausprägung“, eigene EAN); Partnerware bleibt flach. Bekommt ein Partner einen Shop, dann in SEINEM ERP, nicht voll integriert wie Indra-Design. Später: Stückzahlen als Lieferschein, Rückweg Verkaufsmeldung.

Verwandt: [[project-partner-modul]], [[project-datentransfer]], [[project-lizenzierung-preise]].

## ✅ GEBAUT 2026-11-25 (d9df0c6 Export, 57d574b Import, + Handbücher)
- **Migr. 264** `partner_artikel_quelle` (partner_id+quell_nummer UNIQUE, artikel_id UNIQUE, partner_preis, eigener_preis) → **nächste Migr. 265**. Muss mit dem nächsten Paket auf Live.
- Export: `src/modules/artikel/MietfachExport.php` (sammle/paket), Seite `artikel/mietfach_export.php` (Recht artikel.anzeigen, Grundmodul), Massenaktion `mietfach_export` in artikel/liste.php. Format daten.json `nahtlos-mietfach` v1 + bilder/<n>.jpg (GD, max 800 px). Absender = Lizenz::firmendaten(). Log `artikel.mietfach_export`.
- Import: `src/modules/partner/MietfachImport.php` (lesen/vorschau/uebernehmen/preisVonHand), Seite `partner/mietfach_import.php` (partner.bearbeiten; Lizenz Partner über 'partner' => '*'), Upload in storage/mietfach_import/<token>.tar (Token in Session). Button auf Partner → Artikel. Legt über PartnerLagerService::artikelSpeichern an (XP + Quellnummer wenn frei, sonst laufend), Status neu/geaendert/gleich/ohne_preis, EAN-Fallback-Verknüpfung, EAN-Kollision → ohne EAN, Bild nur wenn keins da. Hook in artikelSpeichern-Update → eigener_preis. Log `partner.mietfach_import`.
- Tests: CLI 16/16 (scratchpad, Testpartner + Aufräumen) + Klicktest `.claude-browser-tools/mietfach_import_test.js <partnerId> <ordner>` (Export → Download → Upload → Vorschau → 3 übernehmen) ✓.
- Offen: echter Test Direktvertrieb „Karl Indra“ → MeaLana (eigene Installation nötig), Teaser-Webseite evtl. Satz bei Partner-Modul ergänzen, Grundpreis/Inhalt wird exportiert aber beim Import (noch) nicht gesetzt.
