<?php
require_once __DIR__ . '/../../core/Database.php';

/**
 * RuecklagerungRepository – Warteschlange "physische Ware aus Kasse-Retoure
 * noch nicht eingelagert" (siehe Migration 121). Wird von bon_speichern.php
 * befüllt (block='retour'-Positionen ohne automatische Rücklagerung) und von
 * packplatz/ruecklagerungen.php abgearbeitet.
 */
class RuecklagerungRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function insert(array $daten): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO packplatz_ruecklagerungen
                (kassen_bon_id, bon_nr, auftrag_id, auftrag_nr, artikel_id, bezeichnung, menge, charge, kasse_id)
            VALUES
                (:kassen_bon_id, :bon_nr, :auftrag_id, :auftrag_nr, :artikel_id, :bezeichnung, :menge, :charge, :kasse_id)
        ");
        $stmt->execute([
            'kassen_bon_id' => $daten['kassen_bon_id'],
            'bon_nr'        => $daten['bon_nr'],
            'auftrag_id'    => $daten['auftrag_id'] ?? null,
            'auftrag_nr'    => $daten['auftrag_nr'] ?? null,
            'artikel_id'    => $daten['artikel_id'],
            'bezeichnung'   => $daten['bezeichnung'],
            'menge'         => $daten['menge'],
            'charge'        => $daten['charge'] ?? null,
            'kasse_id'      => $daten['kasse_id'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findOffene(): array
    {
        $stmt = $this->db->query("
            SELECT r.*, k.name AS kasse_name, a.charge_pflicht
            FROM packplatz_ruecklagerungen r
            JOIN kassen k ON k.id = r.kasse_id
            JOIN artikel a ON a.id = r.artikel_id
            WHERE r.status = 'offen'
            ORDER BY r.erstellt_am ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, a.charge_pflicht
            FROM packplatz_ruecklagerungen r
            JOIN artikel a ON a.id = r.artikel_id
            WHERE r.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function markiereErledigt(int $id, int $lagerId, string $zustand, int $benutzerId, ?string $charge = null): void
    {
        $this->db->prepare("
            UPDATE packplatz_ruecklagerungen SET
                status = 'erledigt', erledigt_am = NOW(), charge = :charge,
                erledigt_von = :benutzer_id, erledigt_lager_id = :lager_id, erledigt_zustand = :zustand
            WHERE id = :id
        ")->execute([
            'benutzer_id' => $benutzerId,
            'lager_id'    => $lagerId,
            'zustand'     => $zustand,
            'charge'      => $charge,
            'id'          => $id,
        ]);
    }

    /** Anzahl offener Rücklagerungen — für ein Badge/Hinweis im Packplatz-Menü. */
    public function zaehleOffene(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM packplatz_ruecklagerungen WHERE status = 'offen'")->fetchColumn();
    }
}
