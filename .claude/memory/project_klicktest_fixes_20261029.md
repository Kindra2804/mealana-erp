---
name: project-klicktest-fixes-20261029
description: Klicktest-Runde 2026-10-29 (Mietfach, X/Z-Bon, Nummernkreise) — Fixes gebaut, offen: Windows-Aufgabe z_bon_auto + Nachtest
metadata:
  type: project
---

Jackys erste Klicktest-Ergebnisse 2026-10-29, alles gebaut, von Jacky abgenommen ("kann so bleiben am Dev"), committed+gepusht 2026-10-29:
- Mietfach-Modal: "Standard-Mietpreis netto"; Hinweis bei belegtem Fach — "aktiv" abhaken sperrt nur neue Vermietungen, Vertrag wird weiter verrechnet (MietRechnungService prüft fach.aktiv nicht). Liste: "Inaktiv · noch vermietet".
- Mietrechnung nutzte Nummernkreis 'rechnung' statt 'mietrechnung' (MR) → `erstelleRechnung/erstelleVollrechnung(..., $nummernkreis)`. Händler HL/HR/HS waren schon richtig ('haendler_'.$typ); Händler-Verkaufsmeldung = normale R-Rechnung + Beleg "VM-<Auftragsnr>". Alte Test-Rechnungen R-2026-00018/19 bleiben R.
- Z-Bon-Reihenfolge: `KassenService::zReihenfolgeFehler()` — kein Tag vor dem letzten Z, keine offenen früheren Tage, nicht in Zukunft (Stand Runde 4 unten). Nachträglicher Z-Bon: Kassenstand am Ende des Zeitraums (`getKassenstand($kasse, $bisZeitpunkt)`, vorher war es der aktuelle!).
- `cron/z_bon_auto.php` (--dry-run): Jarvis macht fehlende Z-Bons ab dem letzten Z der Reihe nach. Windows-Aufgabe "MeaLana Z-Bon automatisch" auf Dev angelegt 2026-10-29 (00:05, Interactive — S4U braucht Admin; ein Lauf am Server für alle Kassen). Erster Lauf erzeugt 7 alte Test-Z-Bons (K002, K3).
- Runde 2 (Jacky): Z-Bon eigener Nummernkreis je Kasse ohne Jahr `K1-Z-00001` (`naechsteZBonNr`), X-Bon keine Nummer `K1-X-JJJJMMTT-HHMMSS` (`xBonKennung`) — Grund: X/Z verbrauchten Verkaufsbelegnummern (Lücken, Finanz). Verkaufszähler jetzt MAX der Laufnummer über typ verkauf/storno (`letzteBelegLaufnummer`, auch Offline-Messe-Startwert) statt COUNT(*). Migration 212 hat bestehende Z/X umbenannt (Z in Tagesreihenfolge).
- Messe: Papier-Nacherfassung = Bon zum Nacherfassungszeitpunkt auf aktueller Kasse → Z-Bon dieses Tages (von Jacky so gewünscht). Elektronische Offline-Messe = eigene Kasse (modus offline), Z je Messetag.
- Jacky bestätigt: Druckdialog stand wirklich auf "Ränder: Keine".
- A4 X/Z-Bon (`abschluss_druck.php`): Ursache vermutlich Druckdialog "Ränder: Keine" vom 80-mm-Bon → @page margin 0 + Body-Padding seitlich + Druckrahmen-Tabelle mit wiederholtem thead/tfoot-Abstand, h2 break-after avoid. Gleiches Muster ggf. für abschluss_periode.php.
- Runde 3: Kassensturz-Übersicht zeigt Kassiert gesamt → je Partner Fremdrechnung/Spende → Eigener Umsatz + Zeile stornierte Bons (`getPartnerUmsaetze()` public, auch vom Z-Bon genutzt). **Bug gefunden+gefixt:** getTagesKennzahlen/getPeriodeKennzahlen zählten stornierte Bons in Umsatz + Zahlungsarten mit (alle anderen Stellen storniert=0) — K1 07.10. 66,65 statt 62,65. Gespeicherte alte Z-Bons (JSON) bleiben falsch.
- Entschieden (Jacky): Bon-Storno nur am selben Tag und vor dem Z-Bon (`KassenService::stornoSperrgrund`, Bon-Journal-Knopf grau), sonst normale Retoure — RKSV. Storno-Bon K1-S-… wird BFR-signiert (negativ, senkt Umsatzzähler), außer reine Partnerware-Bons.
- Runde 4: Z-Bon = Zeitraum statt Tag. `kassen_abschluesse.bis_zeitpunkt` (Migr. 213), `KassenService::zBereich()` = [Ende letzter Z (frühestens Tagesbeginn), jetzt+1s bzw. Tagesende). Zweiter Z-Bon am selben Tag erlaubt, wenn seither gebucht (`hatOffeneBuchungen`); `offeneZTage` ab letzterZBis (Cron holt Nach-Z-Buchungen nach). Storno-Sperre jetzt per Bon-Zeitpunkt < letzterZBis. Kassensturz zeigt nach Z nur offenen Teil. Getestet mit Test-Bon (gelöscht).
