---
name: project-demo-datenbank
description: "Geplante Demo-DB für Präsentationen (Wolle+Stoff, Generator relativ zu heute, Reset 0:00, Demo-Schalter) + Teaser-Webseite auf Indranet; Reihenfolge nach Zahlungseingängen"
metadata:
  node_type: memory
  type: project
  originSessionId: 65ed2551-8447-4149-9e7e-963e5751c7e0
  modified: 2026-11-21T16:48:41.743Z
---

2026-11-21 besprochen, noch nicht gebaut. Reihenfolge (Jacky ok): [[project-zahlungseingaenge]] → Demo-DB → Teaser-Webseite.

**Demo-DB** (Vorschlag):
- Erfundene Firma Wolle + Stoffe (zeigt Chargen, Grundpreis UND Meterware).
- PHP-Generator erzeugt alles neu mit Datum **relativ zu heute** (12 Monate, Saisonkurve, 3 Shop-Kanäle, Messe, Kasse, Händler, offene/bezahlte Aufträge, Mahnungen, Retouren, Widerrufe), Namen aus Listen, keine echten Daten.
- Cron 0:00 leeren + neu erzeugen; optional alle 30–60 Min eine neue „Shop-Bestellung“.
- Demo-Schalter: keine Mails (nur Log), kein Shop-Sync/PLC/BFR (Kasse ohne Signatur), Updates/Benutzer/Einstellungen gesperrt, Banner „wird täglich zurückgesetzt“, Login demo/demo.
- Ziel: Webspace mit PHP 8 + MySQL + Cron.

**Teaser-Webseite** (Unterseite Indranet, Vorbild bonitnexus.at): statisches HTML/CSS; Kopf NAHTLOS + „in Entwicklung, im Echtbetrieb“, Für wen, Modul-Kacheln, Screenshots aus der Demo (per Playwright automatisch), vorläufige Preise (Grundmodul 790 €, siehe [[project_lizenzierung_preise]]), Kontakt/Interesse.
