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
