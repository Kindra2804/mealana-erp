---
name: project-offene-klicktests
description: "Offene Klicktests 21–35 (Lagerplätze + Händler) aus der Checkliste vom 2026-10-02 — Jacky macht sie, sobald Regale/Händler echt eingerichtet sind"
metadata:
  node_type: memory
  type: project
  originSessionId: 1c9e550a-0e72-4363-9039-913d77dd7e1e
  modified: 2026-10-02T13:49:13.481Z
---

Checkliste "A" vom 2026-10-02. **A1 Sammelabholung (1–13) und A2 Retouren (14–20) hat Jacky am 2026-10-02 getestet** (dabei gefundene Fehler alle behoben: Teilabholung bezahlt/unbezahlt mit "später/will er nicht", Abholfach, Liste erstattet/Gutschrift, Kundenanzeige-Zahlbetrag, Bon parken — siehe [[project_sammelabholung_auftraege]], [[project_kundenanzeige_modul]]).

**Noch offen — Jacky macht sie, wenn das Lager echt eingerichtet ist (2026-10-02: noch keine Regale angelegt).** Wenn er "Klicktests Lagerplätze/Händler" oder ähnlich sagt, diese Liste wieder vorlegen:

### A3 – Lagerplätze ([[project_lager_konzept]])
| # | Aktion | Erwartet |
|---|---|---|
| 21 | Lager → Lagerplätze → „+ Regal mit Fächern“ (z.B. R9, 4 Fächer) | Kürzel R9-F1 … R9-F4 |
| 22 | Etiketten-PDF auf dem **echten Etikettenbogen** drucken | **Maße prüfen** (A4 3×8, 70×37 mm) — sitzen die Etiketten? |
| 23 | QR mit Handy/Scanner scannen | `inventur/fach.php` öffnet das richtige Fach |
| 24 | Artikel → Reiter Lager → Stammplatz setzen (Vater-Artikel) | Alle Varianten übernehmen ihn |
| 25 | Artikelliste: Massenaktion „Lagerplatz zuweisen“ + Spalte + Filter | Funktioniert |
| 26 | Pickliste drucken | Spalte „Platz“, nach Laufweg sortiert |
| 27 | Packplatz-Scan | 📍 wird angezeigt |
| 28 | Wareneingang | „Gehört in …“, „+ Platz“ funktioniert |
| 29 | Inventur: Artikel im falschen Fach zählen | Hinweis „gezählt – gehört in …“ |

### A4 – Händler ([[project_haendler_konsignation]])
| # | Aktion | Erwartet |
|---|---|---|
| 30 | Verkauf → Händler: **echten Rabatt setzen** | gespeichert |
| 31 | Kunde → „🏬 Als Händler einrichten“ | Händlerlager angelegt |
| 32 | Liefern: 2 Artikel per Scan | LS-PDF mit Händlerpreis netto **und** empfohlenem VK |
| 33 | Verkaufsmeldung „verkauft“ | Rechnung **netto**, Text „Kommissionsware“ |
| 34 | Verkaufsmeldung „Restbestand“ + Schwund | Schwund ausgebucht, nicht verrechnet |
| 35 | Rücknahme | Ware zurück im eigenen Lager |

**How to apply:** Bei Fehlern aus diesen Tests wie bei A1/A2 vorgehen (Daten prüfen, Ursache finden, beheben, Testdaten aufräumen).
