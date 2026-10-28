---
name: project-abholorte
description: Abholorte-Modul (2026-10-28 beschlossen) — eigene Abholadressen mit Lager-Zuordnung statt Firmenadresse auf Abholzettel/Mails
metadata:
  node_type: memory
  type: project
  originSessionId: 476d92e2-9f11-4d78-aa23-41d250c27a6f
  modified: 2026-10-28T12:18:16.287Z
---

Problem (Jacky 2026-10-28): Abholzettel + Abholmail zeigen die Firmenadresse (Fliegergasse 49), Abholung ist aber in der Wollboutique.

**Beschlossenes Konzept (Jacky: "klingt super"):**
- Neue Seite Einstellungen → Abholorte: Name, Straße, PLZ/Ort, Telefon, Öffnungszeiten (Freitext), Hinweistext, Haken "Standard", zugeordnete Lager.
- Ein Abholort ↔ mehrere Lager; ein Lager höchstens einem Abholort. Lager ohne Abholort (z.B. Privathaus-Keller) → Standard-Abholort. Kein Abholort angelegt → Firmenadresse wie bisher.
- auftraege bekommt abholort_id: automatisch gesetzt bei "fertig zur Abholung" aus dem Lager der Ausbuchung (bei mehreren Lagern das mit den meisten Positionen), in Auftragsdetail von Hand änderbar — Auswahlfeld NUR bei Lieferart Selbstabholung sichtbar, sonst versteckt (Jacky 2026-10-28). Dokumente lesen den Abholort vom Auftrag.
- Verwenden in: Mail fertig_zur_abholung (Block Abholadresse), Abholzettel (neuer Block "Abholung bei:", Fußzeile Firma bleibt), Auftragsbestätigung bei Abholung, Rechnungshinweis Selbstabholung.
- Hintergrund: aufträge haben KEIN Lager-Feld; Lager kommt erst über Packplatz-Ausbuchung. kassen.lager_id existiert.

**Erste Daten:** Mealana Wollboutique, Bahngasse 13, 2700 Wiener Neustadt. Öffnungszeiten: Mo, Mi–Fr 09:00–12:00 und 14:00–18:00; Di 09:00–12:00 und 14:00–16:00; Sa 09:00–13:00.

## ✅ GEBAUT 2026-10-28 (SVG D:\ERP\abholorte_mockup.svg von Jacky abgenommen; nicht committed, Klicktest offen)
- Migration 210 (Dev angewendet): Tabelle abholorte, lager.abholort_id + auftraege.abholort_id (FK ON DELETE SET NULL), Wollboutique als Standard mit Lager 1 Ladengeschäft.
- src/modules/lager/AbholortService.php: alle/speichern (genau ein Standard, Lager wandern)/loeschen (nicht Standard, nicht verwendet)/idFuerLager/beimPackenSetzen (lager_bewegungen referenz=auftrag_nr, ausgang, meiste DISTINCT artikel; Handwahl bleibt)/setzen/anzeigeFuerAuftrag (→ Standard → Firma; Telefon-Fallback Firma; Öffnungszeiten als Zeilen).
- Packplatz abschliessen.php setzt Abholort vor Abholzettel+Mail. DokumentService::ladeDaten liefert abholort (nur bei Abholung). Templates: Abholzettel-Kasten, Mail fertig_zur_abholung, Auftragsbestätigung (dokument_erstellen.php), Rechnungshinweis.
- UI: Einstellungen-Reiter Abholorte + Fenster (js/einstellungen_abholorte.js, abholort_speichern.php), nur eigene Lager; Auftrag detail.php Auswahlfeld nur bei Selbstabholung + nicht Kasse (abholort_setzen.php, js/auftraege_detail.js). Zugriffsregeln ergänzt.
- Tests: Service 18/18 (Rollback), Abholzettel-PDF visuell ok, Playwright-Klicktest mit temporärer lokaler Session (danach gelöscht). Packplatz bucht derzeit fest aus Lager 1 → praktisch immer Wollboutique.
- Nebenbefund: Auftragsdetail lädt 404 bild_1784477610_4543.jpg (alter Artikelbild-Pfad, nicht von hier).
