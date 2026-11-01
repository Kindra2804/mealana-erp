---
name: project-steuerprofil
description: "Audit 2026-11-01 (→ 1.0-Check, Fragenblatt an Steuerberatung offen): KU-Schalter wirkt nur auf PDF-Belege, Bilanz/EA + Soll/Ist fehlen komplett, Export fest auf KG/Bilanz/Soll — Vorschlag 'Steuerprofil' für den 1.0-Check"
metadata:
  node_type: memory
  type: project
  originSessionId: 9207fa6f-ba69-4236-b91f-bac650dd7a5a
  modified: 2026-11-01T10:07:03.864Z
---

Jacky 2026-11-01: ERP soll für Regelbesteuerte UND Kleinunternehmer, Bilanzierer UND EA-Rechner funktionieren, einstellbar im (geplanten) Onboarding und später umstellbar (KU → regelbesteuert, EA → Bilanz). Frage: ist das durchgezogen?

**Befund (Claude-Audit per Code-Suche):**
- KU-Schalter `system_einstellungen.kleinunternehmer` (Einstellungen → System → Steuerrecht) wirkt NUR in `DokumentService` (Rechnung/Korrektur/AB/LS-PDF: kein Steuerausweis + §6-Hinweis). Alter Schlüssel `besteuerungsart` (Migr. 038) wird nirgends gelesen → aufräumen.
- KU wirkt NICHT auf: Kassenbon 80mm/A4, RKSV/BFR (TaxG aus Artikel-Steuersatz), Buchhaltungs-Export (bucht USt), Mails (MwSt-Texte), Shop-Sync (inkl. MwSt), Lagerwert/Marge (netto_ek statt brutto), Auftrags-Netto/Brutto. Ursache: Steuersatz kommt je Position aus der Artikel-Steuerklasse, KU wird dort nie gefragt.
- Bilanz/EA und Soll/Ist gibt es gar nicht. `BuchhaltungExportService` ist ausdrücklich "KG, Soll-Versteuerung" (Rechnung gegen Debitor zum Rechnungsdatum). EA bräuchte Zufluss/Ist-Variante.
- Onboarding-Seite existiert noch nicht.

**Vorschlag (noch nicht von Jacky entschieden):** eigener Punkt "Steuerprofil" im 1.0-Check, nicht vor dem Lizenzserver (Soft-Go-Live SOW = KG/Bilanz/Soll braucht es nicht, Verkauf an Dritte schon): 1) Profil KU · Gewinnermittlung · Versteuerung mit "gültig ab"-Datum (Umstellung mitten im Jahr!) 2) zentraler Steuersatz-Helfer (KU → 0 %) für Kasse/RKSV/Auftrag/Shop/Export 3) EA/Ist-Export-Variante 4) KU-Texte Bon+Mails, Brutto-EK-Bewertung 5) Fragen an Steuerberatung (zulässige Kombinationen, RKSV bei KU, Stichtag/Eröffnungsbilanz).

**Entscheidung Jacky 2026-11-01:** Steuerprofil kommt in den 1.0-Check (nicht vorher). Fragenblatt an die Steuerberatung (15 Fragen: Kombinationen, KU inkl. RKSV/Umsatzgrenze/Brutto-Bewertung/OSS, EA-Export, Umstellung mit Stichtag): https://claude.ai/artifact/XJw2XuyspfaZ3qTm1LDqM6 — Antworten abwarten, bevor das Steuerprofil gebaut wird.

**How to apply:** Beim 1.0-Check ([[project-version-1-0-release]]) aufgreifen; bei jedem neuen Steuer-relevanten Code nicht noch mehr KG/Bilanz/Soll-Annahmen hart einbauen. Ältere Notizen: [[project-kleinunternehmer]].
