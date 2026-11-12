---
name: project_preisschilder
description: "Preisschilder-Werkstatt — Entwurf 1 (D:\ERP\preisschilder_mockup.svg) wartet auf Babsi; Merkmal-Grundlage gebaut 2026-11-12 (Migr. 233 + JTL-Merkmal-Import), Live offen; nächster Schritt Messe-Schild"
metadata:
  node_type: memory
  type: project
  originSessionId: 75ec7183-986d-47ce-871c-d2765ed5c06b
  modified: 2026-11-12T19:21:09.248Z
---

**Entscheidungen Jacky 2026-11-12** (Entwurf 1: `D:\ERP\preisschilder_mockup.svg` + `.png`, Generator im Scratchpad, wartet auf Babsis Feedback; noch nichts gebaut):
- **Grundpreis je kg** wie im Shop (GARN + inhalt_einheit g → 1 kg), nicht „100g=“ wie auf den alten Handschildern.
- Eine **Schilder-Werkstatt** mit Vorlagen; das Kuvert-Etikett [[project_kuvert_etikett]] wird Vorlage Nr. 1 (Mehrfachauswahl, Start-Position, Schnittlinie für alle).
- **A Messe-Schild** 105×74, 8/A4, wie die alte Vorlage (Name, Hersteller, Zusammensetzung, Lauflänge, Nadelstärke, Preis, kg-Preis), **je Garnqualität (Vater)**. Knopf nach „Messe vorbereiten“, alle zugebuchten Qualitäten vorab angehakt. Werden jede Messe neu gedruckt (mit Haltern/Tixo am Regal, nicht laminiert). **Messepreis** selten, aber nötig (durchgestrichen „statt“). QR „Farbe nicht da? online“ optional.
- **B Laden-Schild** 105×74, 8/A4: Hausfarben-Streifen, Bild optional, Maschenprobe, Pflegesymbole, Badges.
- **Badges:** Aktion (mit „statt“ + „bis“), Auslauf, Letzte Chance, Neu, BIO, Handgefärbt, GOTS (GOTS nur mit Zertifikat). Aktion/Auslauf/Neu automatisch, die anderen als Merkmal.
- **Pflegesymbole** (Waschanleitung) als neues Merkmal „Pflege“ mit Mehrfachauswahl → Icons, für alle Garn-Schilder.
- **C Tischaufsteller** (A5 gefaltet, 2/A4) mit großem QR „Alle Farben ansehen“ → Artikelseite im Shop des Kanals.
- **D IVAR-Streifen** (Ikea IVAR auf Messen, seitlich am Holm), 40×150 mm (IVAR-Holm 44 mm, gemessen 2026-11-12).
- **E Anhänger mit Loch** 50×90: für ausgestellte Schilder (mit Code 128) und **Babsis Strickstücke** (nicht im ERP → eigene Freitext-Maske mit Pflegesymbolen, damit sie immer gleich aussehen).
- **F „Schilder veraltet“**: ERP merkt sich je gedrucktem Schild den Preis/Badge-Stand; Preis-/Aktions-/Auslaufänderung → Liste + Dashboard-Kachel, „Alle nachdrucken“ (für Babsi/Praktikanten).

**Merkmale (Grundlage der Garn-Schilder) — gebaut 2026-11-12, Migration 233, Commit siehe git log:**
- Slugs: wollqualitaet, zusammensetzung, gewicht_laenge, lauflaenge_bereich (auto), nadelstaerke (mehrfach), maschenprobe, maschenprobe_10cm (auto, Babsi: bleibt neben Drops-Garngruppe), garngruppe, auszeichnung (BIO/GOTS/Handgefärbt -> Badges), pflege (mehrfach, merkmal_werte.symbol = Pflegesymbol-Schlüssel). Code spricht Merkmale immer über slug an.
- `MerkmalService`: speichern (Vater -> alle Kinder), bereicheAbleiten, zusammenfuehren, nutzung. Artikel → Merkmale: Nutzung je Wert + ⇢ Zusammenführen; Auswahlfenster mit Suche + neuer Wert.
- JTL-Import `scripts/jtl_merkmale_import.php <csv> [--dry-run]` (Ameise-Export „Artikelmerkmale“, liegt in import/): Dev 9.753 von 11.893 Artikeln gefunden, 9 uneinheitliche Väter, JTL-„Pflegeanleitung“ enthält nur Garnnamen -> ignoriert. Wiederholbar.
- **Live offen:** Paket 1.0.9 (Notizen docs/versionen/1.0.9.md) + Import mit aktuellem Export am Live laufen lassen. Pflege-Merkmale müssen Babsi/Jacky neu pflegen.

**Why:** Preisauszeichnungspflicht inkl. Grundpreis [[project_grundpreis_rechtslage_wolle]]; Messen + Laden auszeichnen ohne Handarbeit.
**How to apply:** Nach Babsis Feedback Vorlagen bauen (Twig + Dompdf wie Kuvert-Etikett, [[project_dokumente_system]]). Merkmal-Import vor Live-Nutzung klären.
