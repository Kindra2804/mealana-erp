---
name: project-sammelabholung-auftraege
description: "✅ 2026-10-01 GEBAUT (nicht committed): mehrere Online-Abholungen EINES Kunden auf einem Kassenbon, Teilabholung je Auftrag; Rollback-Test 6 Szenarien + Playwright-UI geprüft; echter Kassen-Klicktest offen"
metadata:
  node_type: memory
  type: project
  originSessionId: 3c350eb2-8eb3-43e3-bac5-de17c4ce7718
---

## Bedarf bestätigt (2026-07-10)

Barbara sind ad hoc 3 Kunden eingefallen, die mehrere Online-Bestellungen mit Abholung machen und dann alle gemeinsam abholen — genau der Sammelabholungs-Fall. Damit ist der Bedarf laut [[feedback_scope_ohne_bedarf]] validiert, kein rein spekulatives Feature.

**Bedeutet für uns:** Wird gebaut — mehrere Aufträge sollen auf einem gemeinsamen Bon/Beleg abgeholt werden können, statt pro Bestellung ein eigener Beleg.

**Zeitplan:** Bewusst zurückgestellt bis wir mit dem Entwurf der Online-Shop-Anbindung beginnen (die Bestellungen kommen von dort, macht als eigenständiges Kassen-Feature vorher wenig Sinn). Siehe auch [[project_paperless_rechnung_modul]] und [[project_kundenanzeige_modul]] — beide ebenfalls an den Start der Online-Shop-Anbindung gekoppelt.

**Noch offen:** Konkretes Design (wie werden mehrere Aufträge an der Kasse zu einem Bon zusammengeführt — RKSV-Belegnummer, Zahlungsstatus je Einzelauftrag, Teilabholung eines der mehreren Aufträge?) — noch nicht entworfen, erst beim eigentlichen Start dran.

## Entscheidungen Jacky 2026-10-01 (Start)
1. **Nur Aufträge EINES Kunden** auf einem Sammel-Bon.
2. **Teilabholung** muss für jeden Auftrag der Gruppe gehen (pro Position Menge reduzieren, Rest bleibt offen → teilgeliefert).
3. **Retoure im Sammel-Bon:** nicht in V1, falls zu aufwändig. Geprüft: Heute geht mit EINEM geladenen Auftrag entweder Abholung+Regalartikel ODER Retoure (versendet/abgeschlossen)+Neukauf aus dem Regal. Abholung Auftrag B + Retoure aus Auftrag A in einem Bon geht heute NICHT (zweites Laden ersetzt den ersten); Ausweg wäre nur Freitext-Retour (ohne Auftragsbezug, Zähler von A bleiben unberührt). Jacky hatte das als "geht schon" in Erinnerung.

**Entwurf:** `D:\ERP\sammelabholung_mockup.svg` (3 Schritte: Gruppen-Popup nach Laden → Bon mit Blöcken je Auftrag + Teilabholung → Ergebnis je Auftrag). Wartet auf Barbaras Antwort zu 3 Fragen (Auto-Hinweis vs. Knopf, nicht gepackte Aufträge nicht vorausgewählt, Auftragsnummern auf Druck-Bon).

**Technik-Befund:** Einzelauftrag-Annahme steckt in bon.php (`geladenerAuftragId/Nr/Status/Mitnehmen/Zahlungsstatus` als Einzelwerte, `auftragWaehlen()`), bon_speichern.php (`$webAuftragId`-Block ~Z.154–830: Vorab-Prüfung bezahlt, nurAbschliessen, K1-Auftrag für Extras, Zahlung, Status, Gutschein-Erstattung) und `kassen_bons.web_auftrag_id` (1:1). Plan: Liste geladener Aufträge im Client, Zeilen mit `web_auftrag_id`, serverseitig Pro-Auftrag-Logik in Schleife (Funktion extrahieren), neue Zuordnungstabelle Bon↔Aufträge (web_auftrag_id für Einzelfall/Altbestand behalten). Danach: Lagerplätze (Jackys Reihenfolge).

## ✅ GEBAUT 2026-10-01 (nicht committed)
Barbara: automatisch hinweisen ✓, nicht gepackte nicht vorausgewählt ✓, Auftragsnummern auf dem Bon ✓.
- **Migration 186:** `kassen_bon_auftraege` (bon_id, auftrag_id, vorher_bezahlt) + `kassen_bon_positionen.web_auftrag_id`; Altbestand übernommen. `kassen_bons.web_auftrag_id` bleibt = erster Auftrag.
- **bon_speichern.php:** Payload `web_auftraege:[{id,mitnehmen}]` (alter Einzel-Payload weiter verstanden); Status/Zahlstatus aus DB; Server prüft gleichen Kunden (kunden_id bzw. E-Mail); Positionen über auftrag_position_id/retour_von_position_id aus DB dem Auftrag zugeordnet; Pro-Auftrag-Schleife (Zahlung, menge_geliefert, Rückbuchung, Status, Mail, kassen_bon_id nur wenn vorher unbezahlt). Auftrag mit allen Mengen 0 in Sammelabholung → bleibt unverändert. Zeilen mit Menge 0 nicht mehr auf dem Beleg. Zahlung 0 € wird nicht mehr gebucht (früher Fallback = voller Auftragsbetrag).
- **bon.php:** `geladeneAuftraege[]` (geladenerAuftragId & Co. spiegeln den ersten), Popup `ov-weitere-auftraege` nach dem Laden (`ajax_auftrag_laden.php?weitere_zu=`), Mitnehmen-Frage als Warteschlange je nicht gepacktem Auftrag, Blöcke je Auftrag mit bezahlt/unbezahlt-Chip + ✕, "x von y mitgenommen", Footer zeigt echten Zahlbetrag wenn bezahlte Aufträge dabei. Abrechnungsmodus: nur Zeilen BEZAHLTER Aufträge zählen als schon bezahlt.
- **Druck** (bon_druck 80mm + BonA4Renderer): Block je Auftrag mit Nummer, "Abgeholt, bereits bezahlt: …", Kopf "Aufträge: …".
- detail.php Zusatz-Bons + RetourService::verkaufteChargen berücksichtigen kassen_bon_auftraege.
- Handbuch 11_kasse.md + bedienungsanleitung.php `#kasse-sammelabholung`.
- **Nebenbei gefunden+behoben:** (1) Kasse-Auftragssuche mit Suchtext crashte (k.name/k.email gibt es seit Kunden-Verschlüsselung nicht mehr) → sucht jetzt im kunden_snapshot. (2) bonParken() nutzte undefinierte Variable `kundenId` → Parken warf JS-Fehler; korrigiert.
- **Test:** Rollback-Harness (TestPDO mit SAVEPOINTs, FakeMailer) 6 Szenarien: gemischt+Teilabholung+Extra, alles bezahlt ohne Bon, fremder Kunde abgelehnt, Auftrag ganz 0, alter Einzel-Payload, Erstattung bezahlter Auftrag + unbezahlter. Playwright mit Test-Session-Datei (xampp/tmp/sess_*) + 3 Testaufträgen (danach gelöscht).
- **Offen:** echter Klicktest an K1/K3 (mit BFR), Kundenanzeige nicht gesondert geprüft, linker Footer (USt/Artikelzahl) zählt bezahlte Aufträge noch mit (wie bisher beim Einzelauftrag). Retoure im Sammel-Bon bewusst nicht V1.
- **Nachtrag gleicher Tag (Jackys Frage "Gutschein statt Auszahlung geht noch?"):** Ja (ov-retour-bar unverändert). Dabei 2 ALTE Bugs aus Gutschein-Baustufe 2 gefunden+behoben: (1) Retoure+Extra+Gutschein: Gutschein wurde über den vollen Retourbetrag ausgestellt statt über den Netto-Rest (6,90 statt 3,00 → Kunde +3,90) — jetzt rechnet bon_speichern die gutschein_verkauf-Zeile selbst (= −Summe übriger Bon-Zeilen) und stellt EINEN Gutschein pro Bon aus; Aufträge bekommen je ihren Retourwert als negative Zahlung mit Code. (2) block-ENUM kannte gutschein_kauf/gutschein_verkauf nicht → als '' gespeichert → Gutschein-Zeile fehlte auf 80mm- UND A4-Druck. Migration 187 + Drucker zeigen alles außer auftrag/retour. Außerdem: "nichts mitgenommen" wird jetzt VOR dem Bon entfernt (inkl. Retour-Zeilen), Client rechnet gleich (auftragNichtsMitgenommen) — sonst hätte Kasse 25,40 statt 6,90 Auszahlung angezeigt. 8 Rollback-Szenarien + Browser-Check grün.
