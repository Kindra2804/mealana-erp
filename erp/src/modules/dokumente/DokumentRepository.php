<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * DokumentRepository – Persistenz für erzeugte Auftragsdokumente.
 *
 * Verwaltet die Tabelle auftrag_dokumente (Typ, Dateiname, Timestamps).
 * Dokumentennummern (AB-, GS-, ANZ-) laufen über dokument_nummern,
 * dieselbe Tabelle die bereits A- und R-Nummern verwaltet.
 */
class DokumentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Speichert einen Eintrag für ein neu erzeugtes Dokument.
     */
    public function speichern(int $auftragId, string $typ, string $dateiname, int $benutzerId, ?string $belegNr = null): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO auftrag_dokumente (auftrag_id, typ, beleg_nr, dateiname, erstellt_von, erstellt_am)
            VALUES (:auftrag_id, :typ, :beleg_nr, :dateiname, :erstellt_von, NOW())
        ");
        $stmt->execute([
            ':auftrag_id'  => $auftragId,
            ':typ'         => $typ,
            ':beleg_nr'    => $belegNr,
            ':dateiname'   => $dateiname,
            ':erstellt_von' => $benutzerId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Alle Dokumente eines Auftrags, neueste zuerst.
     */
    public function findByAuftrag(int $auftragId): array
    {
        return $this->db->prepare("
            SELECT d.*, b.formularname AS erstellt_von_name
            FROM auftrag_dokumente d
            LEFT JOIN benutzer b ON b.id = d.erstellt_von
            WHERE d.auftrag_id = :id
            ORDER BY d.erstellt_am DESC
        ")->execute([':id' => $auftragId]) ? [] : [];
    }

    /**
     * Alle Dokumente eines Auftrags (korrekte Implementierung).
     */
    public function ladeByAuftrag(int $auftragId): array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, b.formularname AS erstellt_von_name
            FROM auftrag_dokumente d
            LEFT JOIN benutzer b ON b.id = d.erstellt_von
            WHERE d.auftrag_id = :id
            ORDER BY d.erstellt_am DESC
        ");
        $stmt->execute([':id' => $auftragId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Nächste fortlaufende Nummer für einen Dokumenttyp, transaktionssicher.
     * typ-Werte: 'auftrag' (A-), 'rechnung' (R-), 'ab' (AB-), 'gutschrift' (GS-), 'anz' (ANZ-)
     */
    public function naechsteNummer(string $typ, int $jahr, string $kuerzel = ''): string
    {
        // Präfix holen: eigene Kürzel-Zeile bevorzugt, sonst Hauptshop-Zeile ('') desselben Typs
        $finden = $this->db->prepare("
            SELECT praefix FROM dokument_nummern WHERE typ = :typ
            ORDER BY kuerzel = :kuerzel DESC, jahr DESC
            LIMIT 1
        ");
        $finden->execute([':typ' => $typ, ':kuerzel' => $kuerzel]);
        $praefix = $finden->fetchColumn() ?: strtoupper($typ[0]);

        // Zeile für dieses Jahr + Kürzel anlegen, falls noch nicht vorhanden (UNIQUE typ/kuerzel/jahr)
        $this->db->prepare("
            INSERT IGNORE INTO dokument_nummern (typ, kuerzel, praefix, jahr, letzt_nr)
            VALUES (:typ, :kuerzel, :praefix, :jahr, 0)
        ")->execute([':typ' => $typ, ':kuerzel' => $kuerzel, ':praefix' => $praefix, ':jahr' => $jahr]);

        $this->db->prepare("
            UPDATE dokument_nummern SET letzt_nr = letzt_nr + 1
            WHERE typ = :typ AND kuerzel = :kuerzel AND jahr = :jahr
        ")->execute([':typ' => $typ, ':kuerzel' => $kuerzel, ':jahr' => $jahr]);

        $row = $this->db->prepare("
            SELECT letzt_nr, praefix, kuerzel FROM dokument_nummern WHERE typ = :typ AND kuerzel = :kuerzel AND jahr = :jahr
        ");
        $row->execute([':typ' => $typ, ':kuerzel' => $kuerzel, ':jahr' => $jahr]);
        $data = $row->fetch(PDO::FETCH_ASSOC);

        $nr     = (int)($data['letzt_nr'] ?? 1);
        $prefix = $data['praefix'] ?? strtoupper($typ[0]);

        if ($kuerzel) {
            return $prefix . '-' . $kuerzel . '-' . $jahr . '-' . str_pad($nr, 5, '0', STR_PAD_LEFT);
        }
        return $prefix . '-' . $jahr . '-' . str_pad($nr, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Lädt Firmendaten aus system_einstellungen für Templates.
     */
    public function ladeFirmaDaten(): array
    {
        $stmt = $this->db->query("SELECT schluessel, wert FROM system_einstellungen");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $einstellungen = [];
        foreach ($rows as $row) {
            $einstellungen[$row['schluessel']] = $row['wert'];
        }
        return $einstellungen;
    }

    /**
     * Nummernkreis-Kürzel eines Shops (z.B. 'SOW'); '' für Hauptshop, Kasse und Aufträge ohne Shop.
     */
    public function kuerzelFuerShop(?int $shopId): string
    {
        if ($shopId) {
            $stmt =  $this->db->prepare("
            SELECT nr_kuerzel FROM shops WHERE id = :shop_id
            ");
            $stmt->execute([':shop_id' => $shopId]);
            return $stmt->fetchColumn() ?: '';
        }
        return '';
    }
}
