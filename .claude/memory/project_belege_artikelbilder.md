---
name: project-belege-artikelbilder
description: "Artikelbilder auf Belegen + Mails — Einstellung je Belegtyp, gebaut 2026-10-28 (Migration 211)"
metadata:
  node_type: memory
  type: project
  originSessionId: 476d92e2-9f11-4d78-aa23-41d250c27a6f
  modified: 2026-10-28T20:17:58.339Z
---

Jacky 2026-10-28: Einstellung je Belegtyp "Artikelbilder anzeigen" (Idee Claude, Jacky: "gefällt mir gut"). Entscheidungen: **feste Bildgröße** (nicht einstellbar — sonst bei jeder Vorlagenänderung nachziehen), **kleine 80-mm-Bons nie**, Standard an: Rechnung, Lieferschein, Pickliste, AB-Mail.

## ✅ GEBAUT 2026-10-28 (Klicktest offen)
- Migration 211: system_einstellungen beleg_bilder_<typ> für rechnung, gutschrift, auftragsbestaetigung, lieferschein, pickliste, abholzettel, kassenbon_a4, mail_auftragsbestaetigung, mail_abholung, mail_versand.
- `src/modules/dokumente/BelegBilder.php`: BELEGE/MAILS-Listen, aktiv(), anreichern() (setzt pos.bild_base64, braucht artikel_id; Kind ohne Bild → Vaterbild), vorschau() = 150×150 JPEG mit weißem Rand, Cache storage/bild_vorschau/<artikel_id>/<datei>_150.jpg (gitignored, Original-Dateiname im Cache-Namen → neues Hauptbild = neue Vorschau).
- PDF: _positionen-Partial Spalte mit_bild (34pt ≈ 12 mm) für RG/AB/LS/GS; DokumentService::mitBildern() vor jedem generiere; Abholzettel + Pickliste eigene Spalte; BonA4Renderer Bild float links (28pt).
- Mails: templates/mails/_artikelbild.html.twig (48 px) in AB/Abholung/Versand; Positionen mit artikel_id in dokument_erstellen.php + packplatz abschliessen.php.
- **Mailer::bilderEinbetten()**: alle data:-Bilder (auch Logo!) werden beim Versand zu CID-Einbettungen — vorher zeigte Gmail das data:-Logo vermutlich nicht.
- UI: Einstellungen → System → Karte "Artikelbilder auf Belegen" (speichern.php tab system).
- Tests: Vorschau < 20 KB, 150×150; AB/LS/RG/AZ/Pickliste/Bon A4 als PDF visuell geprüft; Mail-Einbettung (keine data: mehr, CID-Anhänge). Bon A4 mit Bildern wurde 2-seitig (vorher evtl. 1) — beobachten.
- Jacky 2026-10-28: Auftragsbestätigung mit Bildern angeschaut → passt, Länge unverändert. Echte Mail kommt mit Bildern an → abgenommen.
- Siehe [[project_dokumente_system]].
