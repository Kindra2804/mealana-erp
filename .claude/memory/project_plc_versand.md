---
name: project-plc-versand
description: "EasyPak XML-Format (Österr. Post) für Versandmodul — konfigurierbare Felder, Item-IDs, Ausgabepfad"
metadata: 
  node_type: memory
  type: project
  originSessionId: 73181c3f-e7cd-42b8-a31d-8d2abc7282f3
---

## Was ist EasyPak?
Österreichische Post "Label Creator" (PLC) liest eine XML-Datei ein (`export.xml`) und druckt daraus Paketscheine. Unser ERP generiert diese XML — PLC liest sie nur.

## Ausgabe-Einstellungen (konfigurierbar im ERP)
- **Ausgabepfad** (Zielordner): z.B. `\\nsa310\mealana\EasyPak_Export\`
- **Dateiname**: z.B. `export.xml`
- **Speicheroption**: `anfuegen` (an bestehende Datei anfügen) oder `ueberschreiben`

## Item-IDs & Contract-Namen (konfigurierbar)
| Versandart | Item-ID | Contract-Name |
|---|---|---|
| AT Standard | `430101` | Paket Österreich |
| AT Express | `430107` | Paket EMS Österreich |
| EU (Premium) | `430106` | Paket Premium International |
| International | `430104` | Paket International |

Logik: Land=AT → Standard; Land=AT+Express → EMS; Land=EU → Premium International; sonst → International.  
Nachnahme (COD): wenn aktiv → zusätzlicher `<item id="430124">` mit Bankdaten.

## Bankverbindung (für Nachnahme-COD)
Kommt aus Firma-Einstellungen (Bankverbindung): IBAN, BIC, BLZ, Kontonummer, Kontoinhaber, Bank.  
→ Kein eigenes Feld im Versand-Tab nötig, Firma-Tab liefert das.

## XML-Struktur (EasyPak-Format)
```xml
<?xml version="1.0" encoding="ISO-8859-1"?>
<Polling>
  <Set>
    <ShipmentData>
      <SenderRefNo>{{ Lieferscheinnummer }}</SenderRefNo>
      <ClientRefNo>{{ Lieferscheinnummer }}</ClientRefNo>
      <IBAN>{{ Firma.IBAN }}</IBAN>
      <Contract>{{ Easypak_Contract }}</Contract>
      <ShipmentRefNo>L{{ Lieferscheinnummer }}</ShipmentRefNo>
      <ProductsAndServices>
        <shipment><items>
          <item id="{{ Easypak_Item }}"/>
          <!-- Nachnahme: <item id="430124"> mit CODReceiver + Bankdaten -->
        </items></shipment>
      </ProductsAndServices>
      <Weight>{{ Gewicht in kg, 3 Dezimalstellen }}</Weight>
    </ShipmentData>
    <AddressData>
      <Name1>Firma ODER Vorname+Name</Name1>
      <Name2>Vorname+Name wenn Firma in Name1</Name2>
      <Street>Straße ohne Hausnummer</Street>
      <HomeNr>Nur Hausnummer</HomeNr>
      <ZIP>PLZ</ZIP>
      <City>Ort</City>
      <Country>ISO-Ländercode (AT, DE, ...)</Country>
    </AddressData>
  </Set>
</Polling>
```

## Gewichtsermittlung
- Wenn pro Paket ein Gewicht hinterlegt: direkt verwenden
- Sonst: Gesamtgewicht der Positionen + Versandart-Zusatzgewicht, durch Anzahl Pakete

## Was NICHT im ERP konfiguriert werden muss
- Post.at Kundennummer: wird im PLC-Software selbst konfiguriert, nicht im XML
- Empfängeradresse: kommt dynamisch aus dem Auftrag

## Noch offen / zu klären
- Hat Jacky noch weitere PLC-Einstellungen aus JTL (Kopfzeile der XML, Sender-Daten, sonstige Felder)?
- Bankverbindung: ist die schon im Firma-Tab erfasst? (IBAN, BIC, BLZ, Kontonummer, Kontoinhaber)

## ✅ Tatsächlich implementiert als `src/core/EasyPakExporter.php` — zwei echte Bugs gefunden+gefixt (2026-07-10)

Ausgangspunkt war Jackys Hinweis (bei Punkt "Packplatz" auf der ToDo-Liste): bei einer Teillieferung mit Nachnahme darf nicht der volle ursprüngliche Bestellwert eingehoben werden, sonst zahlt der Kunde bei der zweiten Teillieferung doppelt.

**Fix 1 — Nachnahme-Betrag bei Teillieferung (`packplatz/warenausgang/abschliessen.php`):** `EasyPakExporter::exportiere()` bekam schon vorher einen optionalen `$nachnahmeBetrag`-Parameter, wurde aber beim eigentlichen Aufruf nie befüllt (fiel immer auf `$auftrag['bruttobetrag']`, den vollen Original-Auftragswert, zurück). Jetzt wird vor dem Export aus `$gelieferteFuerPdf` (existierte schon für den Teillieferungs-Lieferschein, enthält nur die JETZT gescannten Positionen) der Brutto-Warenwert dieser einen Sendung berechnet und übergeben. **Versandkosten-Regel (Jacky, 2026-07-10):** nur bei der allerersten Lieferung eines Auftrags mitkassiert (Zählung über `auftrag_lieferungen`), jede weitere Teillieferung enthält nur noch den Warenwert.

**Fix 2 — echter, unabhängiger Bug entdeckt beim Testen:** `$refNr = $auftrag['auftragsnummer'];` — diese Spalte existiert gar nicht (heißt `auftrag_nr`). Dadurch war `$refNr` bei JEDEM EasyPak-Export (nicht nur Teillieferungen) immer `null`, was einen `TypeError` in der privaten `x()`-Methode auslöste (verlangt `string`, kein Nullable). Der Aufrufer fängt das per `try/catch` ab und schreibt nur `error_log()` — der Verkauf/Versand lief also augenscheinlich unauffällig weiter, aber **es wurde vermutlich noch nie eine einzige echte EasyPak-XML-Datei erfolgreich erzeugt**, seit das Feature existiert (2026-06-25, "FERTIG" markiert). Erklärt rückwirkend die alte Notiz "Kein PLC-Response-Parsing (hat nie funktioniert)" — es kam wohl nie überhaupt eine Datei im Polling-Ordner an. Fix: `auftrag_nr` statt `auftragsnummer`. Nebenbei zwei `?:`-auf-undefined-key-Warnings bei `firma` (Adressfeld) behoben (`??` statt direktem Array-Zugriff), gleiche Fehlerklasse wie der `kundenanzeige_willkommenstext`-Bug vom selben Tag.

**Getestet (2026-07-10):** `EasyPakExporter::exportiere()` direkt per CLI gegen einen isolierten Test-Auftrag (Nachnahme, Versandkosten 5€) aufgerufen — einmal mit explizitem Teil-Betrag (40€, korrekt im XML `<Amount>`), einmal ohne Override (korrekt voller `bruttobetrag`=120€ als Fallback). `SenderRefNo`/`ClientRefNo`/`ShipmentRefNo` zeigen jetzt korrekt die echte Auftragsnummer statt leer. Testdaten aufgeräumt.

**Verwandter Check (Picklisten-Rest bei Teillieferung):** Jackys ursprüngliche Erinnerung ("kein neue Pickliste mehr für den Rest") separat nachgestellt (siehe [[project_packplatz]]) — funktioniert bereits korrekt, kein Bug mehr.

**Why:** Nachnahme ist echtes Geld beim Kunden — ein doppelt kassierter Betrag bei der zweiten Teillieferung wäre ein handfester Kundenbeschwerde-Fall gewesen.
**How to apply:** `plc_polling_ordner` muss in Einstellungen → System gesetzt sein, damit der Export überhaupt läuft (stiller No-Op sonst). Bei künftigen EasyPak-Änderungen: Spaltennamen aus `auftraege` direkt gegen `SHOW COLUMNS` prüfen, nicht raten — dieser Bug wäre bei jedem echten Testlauf mit gesetztem Polling-Ordner sofort im Post-eigenen PLC als leere/fehlende Datei aufgefallen, aber nie im ERP selbst (nur stiller error_log-Eintrag).
