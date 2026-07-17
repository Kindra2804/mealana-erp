---
name: project-lieferanten-erweiterung
description: "Lieferanten-Modul Erweiterung – FERTIG 2026-07-02: Länder-Tabelle, Firma/UStID/Steuerregel, Bankverbindung, Vertreter-Anrede"
metadata: 
  node_type: memory
  type: project
  originSessionId: eefd559b-9c02-443d-a0cb-164e3dadf876
---

Jacky hat beim ersten echten Dateneintrag von Lieferanten Lücken im Formular gefunden. **FERTIG umgesetzt 2026-07-02** (Migrationen 097–099, Repository/Service/Views):

**Neue Tabelle `laender`:** iso_code (CHAR2, PK), name_de, `ist_eu_mitglied` (TINYINT) – Land-Dropdown im Lieferanten-Formular statt Freitext. EU-Flag ist bewusst mitgedacht, damit später Steuerregel-Vorschläge (IGL bei EU-Ausland, Einfuhr bei Drittland) automatisch ableitbar sind. Flaggen-Emoji-Idee verworfen, siehe [[feedback_flag_emoji]] — Dropdown zeigt nur Klartext-Ländernamen.

**`lieferanten` ALTER (neue Felder):** `firma` + `firmenzusatz` (bestehendes Feld `name` bleibt Such-/Kurzbezeichnung), `ustid`, `steuerregel` ENUM('inland','eu_igl','drittland_einfuhr','reverse_charge'), `standard_lieferkosten` (Vorbelegung für Bestellung, dort überschreibbar), Bankverbindung `iban`/`bic`/`bank_name`/`kontoinhaber` (unverschlüsselt, B2B-Geschäftsdaten wie bei `partner.iban`).

**`lieferanten_vertreter` ALTER:** `anrede` Feld. Vertreter-Anlage jetzt als Repeatable-Row-Sektion direkt im Lieferanten-Anlageformular (`neu.php` + `public/js/lieferanten_neu.js`), kein Umweg mehr über "erst speichern, dann +neuer Vertreter".

**Stolperstein bei der Migration:** `laender` wurde initial mit falscher Collation angelegt (utf8mb4_unicode_ci statt Projekt-Standard utf8mb4_general_ci), dazu zwei Alt-Datensätze mit ungültigem Landescode 'SW' (statt 'SE') — verhinderte den FK. Repariert über Migration 099. Bei künftigen neuen Referenztabellen: Collation der Zieltabelle vorher prüfen (`SHOW CREATE TABLE`), nicht blind von anderen Migrationen kopieren.

**Zurückgestellt für andere Module:**
- Kreditorennummer/DATEV-Konto-Zuordnung gehört ins [[project_buchhaltung]] (eigene Liste dort, wo man Lieferanten Kreditorenkonten zuweist) — nicht an den Lieferanten selbst.
- bevorzugte Bestellart (E-Mail/Portal/Fax), Korrespondenz-Sprache (analog `kunden.sprache`) — noch unentschieden, für später.

**✅ Doku-Schuld behoben 2026-07-10:** `bedienungsanleitung.php` und `docs/handbuch/07_bestellungen.md` (jetzt "07 — Einkauf: Lieferanten & Bestellungen") beschreiben das eigenständige Modul (`public/lieferanten/`) jetzt korrekt inkl. Stammdaten/Konditionen/Bankverbindung/Vertreter/Zugänge-Tab. War beim direkten Code-Check zusätzlich reichhaltiger als in Erinnerung — u.a. ein "Zugänge/Händlerportale"-Tab mit Login-Daten (Passwort per Klick einblendbar, bewusst unverschlüsselt, Warnhinweis in der Doku ergänzt), der vorher nirgends dokumentiert war.

**Why:** Erste Praxis-Eingabe von echten Lieferanten hat gezeigt, dass Land/Firma/UStID/Bankdaten/Lieferkosten fehlen.

**How to apply:** Als Referenz für ähnliche Stammdaten-Erweiterungen (z.B. Kunden-Modul später) — gleiches Muster für Land-Dropdown, Firma vs. Kurzname, Bankverbindung.
