---
name: project-jtl-kunden-auftraege-import
description: "JTL-Export Kundendaten+Aufträge (2026-08-10) auf Machbarkeit geprüft — unproblematisch, Details zu Struktur/Überschneidungen erfasst; eigentlicher Import bewusst noch NICHT gebaut, eigenständiges Thema nach den Fünf Abendaufgaben"
metadata: 
  node_type: memory
  type: project
  originSessionId: d1aef7f2-7ecd-41ea-b428-cf0b1d692643
  modified: 2026-08-10T10:02:24.047Z
---

## Ausgangslage (Jacky, 2026-08-10)

Zwei neue JTL-Exporte in `import/` abgelegt: `JTL-Export-Kundendaten-10082026.csv` und `JTL-Export-Aufträge-10082026.csv`. Wunsch: erstmal nur schauen, ob sich damit vernünftig arbeiten lässt ("Gewaltakt oder nicht"), der eigentliche Import kommt separat später. Wichtige Anforderung dabei: importierte Alt-Aufträge müssen im ERP klar als "Archivdaten" erkennbar sein, nicht mit echten neuen ERP-Aufträgen verwechselbar.

## Machbarkeits-Check (Claude, 2026-08-10) — Ergebnis: unproblematisch

**Kundendaten** (6.775 Zeilen): klassisches flaches JTL-Format (~50 Spalten), Windows-1252/ISO-8859-1-kodiert — der bestehende `JtlCsvReader` (aus dem Artikel-Import, siehe [[project_jtl_import]]) liest es direkt korrekt ein, kein neuer Parser nötig. Nur **2 von 6.775** sind schon als ERP-Kunde vorhanden (E-Mail-Hash-Abgleich gegen die echte Live-Verschlüsselung getestet) — ERP hat aktuell nur 3 Testkunden insgesamt, der Rest wäre komplett neu.

**Aufträge** (12.174 Positionszeilen, eine Zeile pro Artikel-Position wie beim Artikel-Import): **4.372 einzelne Bestellungen** von 312 verschiedenen Kunden, Zeitraum 01.01.2025 bis 10.08.2026 (aktueller Export-Tag). Verknüpfungsschlüssel zwischen beiden Dateien ist die Spalte "Kundennummer" (Format `Kd-XXXX`) — passt zu **100%** (alle 312 in den Aufträgen vorkommenden Kundennummern finden sich in der Kundendatei; NICHT die `Debitorennummer`-Spalte, die matcht nur zu 12/312). **0 der 4.372 Auftragsnummern existieren schon im ERP** (`auftraege.auftrag_nr`) — kein Duplikat-Risiko mit den bisherigen WooCommerce-Sync-Bestellungen.

## Archiv-Markierung — technisch einfacher Weg gefunden

`auftraege.kanal` ist aktuell ein ENUM (`woocommerce`/`manuell`/`kasse`). Ein vierter Wert `jtl_archiv` wäre eine kleine Migration. Die Kanal-Chip-Anzeige + der Kanal-Filter in der Auftragsliste existieren bereits (Session 2026-07-21, siehe CLAUDE.md "Kanal-Anzeige in Auftragsliste") — bräuchten nur einen weiteren `match`-Fall für `jtl_archiv` (z.B. "Archiv"-Chip statt Shop-Logo). Keine neue UI-Infrastruktur nötig, nur ein Enum-Wert + ein Chip-Fall.

## Offene Design-Fragen für den eigentlichen Import (bewusst noch nicht entschieden)

- **Artikelnummer-Matching in den Bestellpositionen:** Jacky schätzt die Trefferquote als hoch ein (Artikelnummern kommen aus derselben JTL-Quelle wie der bereits gelaufene Artikel-Import, nur wenige geändert). **Bekannte Lücke:** Jacky hat beim Artikel-Import nur AKTIVE Artikel übernommen — Bestellpositionen mit inzwischen aus dem Shop entfernten/inaktiven Artikeln hätten daher keine Entsprechung in unserer `artikel`-Tabelle. Erwartung: überschaubare Liste, kein Showstopper.
  - **Claude-Vorschlag, noch nicht abgestimmt:** für nicht auffindbare Artikelnummern das bereits im Kassen-Modul etablierte Freitext-/Divers-Artikel-Muster wiederverwenden (Platzhalter-`artikel_id`, echter Name/Preis als Freitext auf der Position) — dieselbe Lösung, die schon für "Artikel ohne Stammdaten" beim Verkauf existiert. Würde das Problem unabhängig von der Zeitspanne des Imports abfangen.
- **Kunden-Dedup:** wie werden die 312 Bestell-Kunden (die alle auch in den 6.775 Kundendaten stecken) mit künftigen ECHTEN WooCommerce-Sync-Kunden zusammengeführt? Gleiches Muster wie das bereits bestehende `ShopBestellungSyncService::ermittleOderErstelleKunde()` (externe_id → E-Mail-Hash → neu anlegen) sollte direkt wiederverwendbar sein.
- **Preise/Steuersätze historisch einfrieren**, nicht neu berechnen — analog zum bereits laufenden Bestellungs-Sync (Bestellzeitpunkt-Preis muss eingefroren bleiben, siehe [[project_shop_sync]]).

## Offene Scope-Frage: längerer Zeitraum importieren?

Jacky hat überlegt, ob ein LÄNGERER Auftrags-Export (mehr als der aktuelle 01.01.2025–heute-Zeitraum) sinnvoll wäre — mit der Einschränkung, dass ein längerer Zeitraum vermutlich mehr "Artikel nicht gefunden"-Fälle bringt (mehr Zeit = höhere Wahrscheinlichkeit, dass ein referenzierter Artikel inzwischen aus dem aktiven Sortiment geflogen ist).

**Claude-Einschätzung (noch nicht mit Jacky final abgestimmt):** Der Freitext-Fallback oben würde das Problem unabhängig von der Zeitspanne abfangen — spricht also grundsätzlich nichts gegen einen längeren Export, wenn der Wert (vollständigere Kundenhistorie) das für Jacky rechtfertigt. Endgültige Entscheidung erst wenn der Import konkret geplant wird, nicht vorher recherchieren.

**How to apply:** Dies ist NUR eine Machbarkeitsprüfung, kein Code wurde geschrieben. Eigenständiges Thema, das NACH den "Fünf Abendaufgaben" ([[project_fuenf_abendaufgaben_0809]]) drankommt, sobald Jacky den eigentlichen Import anstoßen will — dann hier weiterlesen statt neu zu analysieren, insbesondere die Zahlen (100% Kundennummer-Match, 0% Auftragsnummer-Überschneidung, 2/6775 Kunden-Überschneidung) als Ausgangspunkt nehmen.
