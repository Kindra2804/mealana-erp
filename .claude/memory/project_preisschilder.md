---
name: project_preisschilder
description: "Idee 2026-11-11 (Jacky) — Preisschilder-Vorlagen mit Mehrfachauswahl, mehrere je A4-Seite; Messe-Lager mit einem Klick auszeichnen"
metadata:
  node_type: memory
  type: project
  originSessionId: 7f8fbe8b-7883-4d92-8028-9fb07effaffc
  modified: 2026-11-11T21:32:30.908Z
---

**Wunsch Jacky 2026-11-11, für später vorgemerkt (noch nichts gebaut):**
- Preisschilder-Vorlagen in **mehreren Varianten** (Größen/Layouts).
- Artikel per **Mehrfachauswahl** wählen (z.B. aus der Artikelliste), Ausdruck auf **A4 mit mehreren Schildern je Seite**.
- Inhalt: **Bild, Preis, kg-Preis-Auszeichnung (Grundpreis), Artikelinfos, teilweise Barcode** (EAN).
- **Messe-Lager:** mit einem Klick Preisschilder für **alle Artikel, die im Messelager liegen** (auf Messen muss ausgezeichnet werden).

**Why:** Preisauszeichnungspflicht inkl. Grundpreis (siehe [[project_grundpreis_rechtslage_wolle]]); auf Messen wird viel Ware ausgezeichnet.
**How to apply:** Vor dem Bau Referenz-Check (große WAWIs: Etikettendruck) nach [[feedback_modul_vorgehen]]. Technik wie die übrigen Belege: Twig + Dompdf ([[project_dokumente_system]]), Barcode über die vorhandene Etiketten-/Lagerplatz-Logik (lagerplaetze_etiketten.php). Verwandt: [[project_druck_listen]].
