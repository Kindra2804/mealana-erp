---
name: project-zahlungseingaenge
description: "Verkauf → 💶 Zahlungseingänge für Babsis Kontencheck (Zeilen-Buchung offener Aufträge); ✅ gebaut 2026-11-21, kommt mit 1.0.28, Klicktest Babsi + Live offen"
metadata:
  node_type: memory
  type: project
  originSessionId: 65ed2551-8447-4149-9e7e-963e5751c7e0
  modified: 2026-11-21T17:03:33.485Z
---

✅ 2026-11-21 gebaut nach Entwurf `docs/design/zahlungseingaenge_mockup.svg` (Babsi/Jacky: „Mockup passt“). Kommt mit Paket **1.0.28** (Notiz in docs/versionen/1.0.28.md). Keine Migration.

Code:
- `public/auftraege/zahlungseingaenge.php` + `public/js/zahlungseingaenge.js`
- `AuftragRepository::findOffeneZahlungseingaenge()` / `findZahlungenHeuteVon()`, `AuftragService::getOffeneZahlungseingaenge()` (Fälligkeit, Tage, Weg-Vorschlag, Auto-Storno-Tage)
- Menü Verkauf (Badge = Anzahl, gleiche WHERE wie Repository), Recht `auftraege.bearbeiten` in Zugriffsregeln
- Handbuch 04_auftraege.md + bedienungsanleitung.php (#zahlungseingaenge)
- Klicktest `C:\Users\indy1\.claude-browser-tools\zahlungseingaenge_test.js` (fängt zahlung_buchen.php ab — echte Buchung auf Dev würde echte Mail über Dev-SMTP schicken!)

Festgelegt:
- Buchung über bestehendes `zahlung_buchen.php` (Zahlungsmail + Woo-Rückmeldung wie im Auftrag).
- **Rückgängig = 8 s verzögertes Absenden im Browser** (keine Zahlung-löschen-Funktion; Mail/Shop sonst schon raus). beforeunload warnt.
- Offen = brutto + offene Mahngebühren − Zahlungen (wie setzeZahlungsstatus). Fällig = MIN(rechnungen.faellig_am), sonst Rechnungsdatum+14, sonst Bestellung+14.
- Zahlarten vorkasse/rechnung/paypal/nachnahme/gemischt; nicht Kasse, jtl_archiv, bar/karte/gutschein, storniert.
- Suche Text + Betrag (offen oder brutto), Chips je Zahlart + überfällig, Sortierung Fälligkeit/Bestellung/Zahlart/Betrag, „Buchungsdatum vorbelegen“.
- Verwendungszweck enthält bei ~80 % die Auftragsnummer.

Offen: Babsi-Klicktest mit echter Buchung, Live mit 1.0.28. „Gesamt offen“-Fußzeile aktualisiert sich nach dem Buchen nicht (erst nach Neuladen) — bewusst einfach gelassen.

Stufe 2 (später): Sparkassen-CSV hochladen → Zuordnungs-Vorschläge. Jacky klärt, ob die Sparkasse CSV exportieren kann.

Danach (Jackys Reihenfolge): [[project-demo-datenbank]] → Teaser-Webseite.
