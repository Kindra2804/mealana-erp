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
    public function speichern(int $auftragId, string $typ, string $dateiname, int $benutzerId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO auftrag_dokumente (auftrag_id, typ, dateiname, erstellt_von, erstellt_am)
            VALUES (:auftrag_id, :typ, :dateiname, :erstellt_von, NOW())
        ");
        $stmt->execute([
            ':auftrag_id'  => $auftragId,
            ':typ'         => $typ,
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
    public function naechsteNummer(string $typ, int $jahr): string
    {
        // Zeile für dieses Jahr anlegen falls noch nicht vorhanden
        $this->db->prepare("
            INSERT IGNORE INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
            SELECT typ, praefix, :jahr, 0 FROM dokument_nummern
            WHERE typ = :typ AND jahr = (SELECT MAX(jahr) FROM dokument_nummern WHERE typ = :typ2)
        ")->execute([':typ' => $typ, ':jahr' => $jahr, ':typ2' => $typ]);

        $this->db->prepare("
            UPDATE dokument_nummern SET letzt_nr = letzt_nr + 1
            WHERE typ = :typ AND jahr = :jahr
        ")->execute([':typ' => $typ, ':jahr' => $jahr]);

        $row = $this->db->prepare("
            SELECT letzt_nr, praefix FROM dokument_nummern WHERE typ = :typ AND jahr = :jahr
        ");
        $row->execute([':typ' => $typ, ':jahr' => $jahr]);
        $data = $row->fetch(PDO::FETCH_ASSOC);

        $nr     = (int)($data['letzt_nr'] ?? 1);
        $prefix = $data['praefix'] ?? strtoupper($typ[0]);

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
}
