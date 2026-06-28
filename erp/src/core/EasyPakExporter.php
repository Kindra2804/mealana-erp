<?php

/**
 * EasyPakExporter
 *
 * Erzeugt eine XML-Datei für den PLC (Packet Label Creator) der Österreichischen Post.
 * PLC übernimmt Layout + Zebra-Druck + Post-API-Kommunikation.
 * Wir schreiben nur die Daten als XML in den konfigurierten Polling-Ordner.
 *
 * PLC-Einstellung: Einstellungen → System → "PLC Polling-Ordner"
 */
class EasyPakExporter
{
    public function __construct(private PDO $db) {}

    public function exportiere(int $auftragId, float $gewichtKg, string $zielOrdner, ?float $nachnahmeBetrag = null): string
    {
        $auftrag = $this->db->prepare("SELECT * FROM auftraege WHERE id = ?");
        $auftrag->execute([$auftragId]);
        $auftrag = $auftrag->fetch(PDO::FETCH_ASSOC);
        if (!$auftrag) throw new RuntimeException("Auftrag {$auftragId} nicht gefunden");

        $firma = $this->db->query("SELECT schluessel, wert FROM system_einstellungen")
                          ->fetchAll(PDO::FETCH_KEY_PAIR);

        // Fallback-Kette: Lieferadresse → Rechnungsadresse → Kunden-Snapshot
        if (!empty($auftrag['lieferadresse_snapshot'])) {
            $lieferAdr = json_decode($auftrag['lieferadresse_snapshot'], true);
        } elseif (!empty($auftrag['rechnungsadresse_snapshot'])) {
            $lieferAdr = json_decode($auftrag['rechnungsadresse_snapshot'], true);
        } else {
            $lieferAdr = json_decode($auftrag['kunden_snapshot'] ?? '{}', true);
        }
        $land      = $this->landISO($lieferAdr['land'] ?? 'Österreich');
        $istEU     = $this->istEU($land);
        $nachnahme = $auftrag['zahlungsart'] === 'nachnahme';

        $itemAt          = $firma['plc_item_at']            ?? '430101';
        $itemEU          = $firma['plc_item_eu']            ?? '430106';
        $itemIntl        = $firma['plc_item_international'] ?? '430104';

        // Item-ID + Contract nach Lieferland
        [$itemId, $contract] = match(true) {
            $land === 'AT' => [$itemAt,   'Paket Österreich'],
            $istEU         => [$itemEU,   'Paket Premium International'],
            default        => [$itemIntl, 'Paket International'],
        };

        $name1 = $lieferAdr['firma'] ?: trim(($lieferAdr['vorname'] ?? '') . ' ' . ($lieferAdr['nachname'] ?? ''));
        $name2 = $lieferAdr['firma'] ? trim(($lieferAdr['vorname'] ?? '') . ' ' . ($lieferAdr['nachname'] ?? '')) : '';
        $refNr = $auftrag['auftragsnummer'];

        $xml  = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
        $xml .= "<Polling>\n";
        $xml .= "  <Set>\n";
        $xml .= "    <ShipmentData>\n";
        $xml .= "      <SenderRefNo>" . $this->x($refNr) . "</SenderRefNo>\n";
        $xml .= "      <ClientRefNo>" . $this->x($refNr) . "</ClientRefNo>\n";
        $xml .= "      <IBAN>" . $this->x($firma['iban'] ?? '') . "</IBAN>\n";
        $xml .= "      <Contract>" . $this->x($contract) . "</Contract>\n";
        $xml .= "      <ShipmentRefNo>L" . $this->x($refNr) . "</ShipmentRefNo>\n";
        $xml .= "      <ProductsAndServices>\n";
        $xml .= "        <shipment><items>\n";
        $xml .= "          <item id=\"" . $itemId . "\"/>\n";
        if ($nachnahme) {
            $xml .= "          <item id=\"430124\">\n";
            $xml .= "            <StateValue><Amount>" . number_format($nachnahmeBetrag ?? (float)$auftrag['bruttobetrag'], 2, '.', '') . "</Amount></StateValue>\n";
            $xml .= "            <CODReceiver>\n";
            $xml .= "              <UseSenderAsCODReceiver>false</UseSenderAsCODReceiver>\n";
            $xml .= "              <BankName>" . $this->x($firma['bank_name'] ?? '') . "</BankName>\n";
            $xml .= "              <IBAN>" . $this->x($firma['iban'] ?? '') . "</IBAN>\n";
            $xml .= "              <BIC>" . $this->x($firma['bic'] ?? '') . "</BIC>\n";
            $xml .= "              <Name>" . $this->x($firma['firmenname'] ?? '') . "</Name>\n";
            $xml .= "            </CODReceiver>\n";
            $xml .= "          </item>\n";
        }
        $xml .= "        </items></shipment>\n";
        $xml .= "      </ProductsAndServices>\n";
        $xml .= "      <Weight>" . number_format($gewichtKg, 3, '.', '') . "</Weight>\n";
        $xml .= "    </ShipmentData>\n";
        $xml .= "    <AddressData>\n";
        $xml .= "      <Name1>" . $this->x($name1) . "</Name1>\n";
        $xml .= "      <Name2>" . $this->x($name2) . "</Name2>\n";
        $xml .= "      <Street>" . $this->x($lieferAdr['strasse'] ?? '') . "</Street>\n";
        $xml .= "      <HomeNr>" . $this->x($lieferAdr['hausnummer'] ?? '') . "</HomeNr>\n";
        $xml .= "      <ZIP>" . $this->x($lieferAdr['plz'] ?? '') . "</ZIP>\n";
        $xml .= "      <City>" . $this->x($lieferAdr['ort'] ?? '') . "</City>\n";
        $xml .= "      <Country>" . $this->x($land) . "</Country>\n";
        $xml .= "    </AddressData>\n";
        $xml .= "  </Set>\n";
        $xml .= "</Polling>\n";

        // ISO-8859-1 konvertieren (Post-Anforderung)
        $xmlEncoded = mb_convert_encoding($xml, 'ISO-8859-1', 'UTF-8');

        $dateiname = rtrim($zielOrdner, '/\\') . DIRECTORY_SEPARATOR
            . 'easypak_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $refNr) . '_' . time() . '.xml';

        file_put_contents($dateiname, $xmlEncoded);
        return $dateiname;
    }

    private function x(string $val): string
    {
        return htmlspecialchars(str_replace('"', '', $val), ENT_XML1, 'ISO-8859-1');
    }

    private function landISO(string $land): string
    {
        $map = [
            'Österreich' => 'AT', 'Austria' => 'AT',
            'Deutschland' => 'DE', 'Germany' => 'DE',
            'Schweiz' => 'CH', 'Switzerland' => 'CH',
            'Italien' => 'IT', 'Italy' => 'IT',
            'Frankreich' => 'FR', 'France' => 'FR',
            'Niederlande' => 'NL', 'Netherlands' => 'NL',
            'Belgien' => 'BE', 'Belgium' => 'BE',
            'Tschechien' => 'CZ', 'Czech Republic' => 'CZ',
            'Slowakei' => 'SK', 'Slovakia' => 'SK',
            'Ungarn' => 'HU', 'Hungary' => 'HU',
            'Polen' => 'PL', 'Poland' => 'PL',
            'Slowenien' => 'SI', 'Slovenia' => 'SI',
            'Kroatien' => 'HR', 'Croatia' => 'HR',
            'Spanien' => 'ES', 'Spain' => 'ES',
            'Portugal' => 'PT',
            'Griechenland' => 'GR', 'Greece' => 'GR',
            'Schweden' => 'SE', 'Sweden' => 'SE',
            'Norwegen' => 'NO', 'Norway' => 'NO',
            'Großbritannien' => 'GB', 'United Kingdom' => 'GB', 'UK' => 'GB',
            'USA' => 'US', 'United States' => 'US',
        ];
        // Wenn schon 2-stelliger ISO-Code übergeben
        if (strlen($land) === 2) return strtoupper($land);
        return $map[$land] ?? strtoupper(substr($land, 0, 2));
    }

    private function istEU(string $iso): bool
    {
        return in_array($iso, [
            'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI',
            'FR','GR','HR','HU','IE','IT','LT','LU','LV','MT',
            'NL','PL','PT','RO','SE','SI','SK',
        ]);
    }
}
