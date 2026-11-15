---
name: project-rksv-exporte-fremdumsatz
description: "Auf die Liste (Jacky 2026-11-14): RKSV-Exporte §131 + §7 (DEP über BFR) und Badge „Fremdumsatz“ für Partner-Aufträge/-Bons in der Auftragsliste"
metadata:
  node_type: memory
  type: project
  originSessionId: 45fa858c-4d70-4cd6-a73f-aea781e36d6e
  modified: 2026-11-15T07:28:38.461Z
---

**1. Prüfer-Exporte Kasse (Jacky 2026-11-14 nachgefragt):**
- **Nullbeleg:** gibt es schon. Manuell (Kasse → RKSV-Symbol → `ajax_nullbon.php`) und automatisch als Monats-Nullbeleg (`BfrService::sicherstelleMonatsNullbeleg`). Offen bleibt zu prüfen, ob der **Jahresbeleg** (Dezember) extra gedruckt bzw. mit der BMF-App geprüft werden muss.
- **§7-Export (DEP, Datenerfassungsprotokoll):** noch nicht gebaut. Der BFR liefert ihn direkt über `http://[EFR-IP]:8787/control/export` als DEP.json, immer für den kompletten Zeitraum. Eingrenzen geht nur direkt im BFR. Idee: Knopf in Kasse → RKSV, der das DEP.json holt und zum Download anbietet. Die Abfrage muss aus dem Netz der Kasse kommen, wie bei der bfr_url.
- **§131-Export (BAO, Datenexport für die Betriebsprüfung):** noch nicht gebaut. Gemeint ist der Export der Kassendaten (Bons/Positionen/Zahlungen) in lesbarer Form. Inhalt und Format vor dem Bau klären.

**Entscheidungen 2026-11-15 (Jacky):** Reihenfolge 1. Badge „Fremd“ nur für A-IND-Aufträge, 2. DEP-Knopf, 3. §131-Export. Partner-Bons bekommen weiterhin KEINEN Spiegel-Auftrag. Jahresbeleg erledigt der BFR selbst, Nullbeleg ist schon drin (Klick auf „RKSV aktiv“) → kein Thema mehr. §131 nach Vorbild BONit (Handbuch bfp id=65): alle Kassentabellen 1:1 als CSV, TAB getrennt, + DEP; bei uns **ohne Artikelstammdaten** (zu groß), mit Zeitraum, als ZIP.
**Fund 2026-11-15:** Kopfzeile „RKSV aktiv“ in `kasse/bon.php` prüft nur `kassen.rksv_kassen_id`; signiert wird aber nur mit `bfr_url`. Dev-K1 hat `RKSV-K1` aus dem alten Freitextfeld (vor 2026-07-02 entfernt), ohne bfr_url/Registrierung → zeigt fälschlich „aktiv“. Auf Live nicht möglich (nur Registrierungs-Abschluss schreibt das Feld), Anzeige trotzdem an bfr_url + bfr_aktiv_seit knüpfen.

**2. Badge „Fremdumsatz“ in der Auftragsliste (Jacky 2026-11-14):**
- Kassen-Bons nur mit Partnerware (Fremdrechnung/Spende) bekommen heute bewusst keinen Spiegel-Auftrag, weil das nie KG-Umsatz ist (`KassenService`, Kommentar „Fremdrechnung/Spende ebenfalls nicht“). Deshalb fehlen sie in der Auftragsliste; das Kassen-Journal ist vollständig.
- Wunsch: Diese Bons (Auftrag mit Kanal Kasse + partner_id) und die A-IND-Aufträge in der Liste mit Badge **„Fremd“** unter der Nummer zeigen, wie „Widerrufen“. Kommen beide zusammen, nebeneinander und kurz („Fremd“ · „Widerr.“), damit die Zeile nicht zu hoch wird. Nicht in KG-Umsatz/Statistik zählen.

**How to apply:** Gehört nach der Reihung in [[project-roadmap-reihenfolge]] auf die Liste. Siehe auch [[project-rksv-bfr]] und [[project-partner-modul]].

**Stand 2026-11-15:** Schritt 1 fertig (4a78804): Badge „Fremd“ bei auftraege.partner_id (A-IND-00001/2 vom 05.11. noch KG, zu Recht ohne), Dashboard + Statistik ohne Partner-Aufträge (Shop-Zeile „+ x € Fremd“), RKSV-Anzeige an bfr_url + bfr_aktiv_seit. Live mit nächstem Paket. Nächster Schritt: DEP-Knopf.
**Schritt 2 fertig 2026-11-15:** DEP-Knopf in Kasse → 🔏 RKSV (`kasse/dep_export.php`, `BfrService::holeDepExport`, Recht kasse.stoppen): /state mit RN-Abgleich, dann GET /control/export (180 s), Download `DEP_<KassenID>_<Datum>.json`, Protokoll nur Größe (endpunkt export). Nicht erreichbar → Hinweis auf http://127.0.0.1:<port>/control/export am BFR-PC. Mit Schein-BFR getestet; echter Test an K3/Laden offen. Nächster Schritt: §131-Export (DEP wiederverwenden).
