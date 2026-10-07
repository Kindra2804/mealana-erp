<?php
require_once __DIR__ . '/../../core/Database.php';

/**
 * RuecklagerungRepository – Warteschlange "zurückgekommene Ware noch nicht geprüft/
 * eingelagert" (Migration 121, erweitert 179). Befüllt von der Kasse (bon_speichern.php,
 * block='retour'-Positionen) und von der ERP-Gutschrift mit "Lager zurückbuchen"
 * (DokumentService::erstelleGutschrift); abgearbeitet in packplatz/ruecklagerungen.php,
 * wo Zustand, Lager und Charge entschieden werden (Buchung über RetourService).
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
        // Partnerware gehört zurück ins Lager ihres Partners -- als Vorschlag beim Einlagern
        if (empty($daten['lager_vorschlag_id']) && !empty($daten['artikel_id'])) {
            $pl = $this->db->prepare("
                SELECT l.id FROM artikel a
                JOIN lager l ON l.partner_id = a.partner_id AND l.lager_beziehung = 'partner_bestand'
                WHERE a.id = ?
            ");
            $pl->execute([(int)$daten['artikel_id']]);
            $daten['lager_vorschlag_id'] = ($id = $pl->fetchColumn()) ? (int)$id : null;
        }

        $stmt = $this->db->prepare("
            INSERT INTO packplatz_ruecklagerungen
                (quelle, kassen_bon_id, bon_nr, gutschrift_nr, auftrag_id, auftrag_nr, auftrag_position_id,
                 artikel_id, bezeichnung, menge, charge, lager_vorschlag_id, kasse_id)
            VALUES
                (:quelle, :kassen_bon_id, :bon_nr, :gutschrift_nr, :auftrag_id, :auftrag_nr, :auftrag_position_id,
                 :artikel_id, :bezeichnung, :menge, :charge, :lager_vorschlag_id, :kasse_id)
        ");
        $stmt->execute([
            'quelle'              => $daten['quelle'] ?? 'kasse',
            'kassen_bon_id'       => $daten['kassen_bon_id'] ?? null,
            'bon_nr'              => $daten['bon_nr'] ?? null,
            'gutschrift_nr'       => $daten['gutschrift_nr'] ?? null,
            'auftrag_id'          => $daten['auftrag_id'] ?? null,
            'auftrag_nr'          => $daten['auftrag_nr'] ?? null,
            'auftrag_position_id' => $daten['auftrag_position_id'] ?? null,
            'artikel_id'          => $daten['artikel_id'],
            'bezeichnung'         => $daten['bezeichnung'],
            'menge'               => $daten['menge'],
            'charge'              => $daten['charge'] ?? null,
            'lager_vorschlag_id'  => $daten['lager_vorschlag_id'] ?? null,
            'kasse_id'            => $daten['kasse_id'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findOffene(): array
    {
        $stmt = $this->db->query("
            SELECT r.*, k.name AS kasse_name, a.charge_pflicht, a.artikelnummer
            FROM packplatz_ruecklagerungen r
            LEFT JOIN kassen k ON k.id = r.kasse_id
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

    public function markiereErledigt(int $id, int $lagerId, string $zustand, int $benutzerId, ?string $charge = null, ?int $artikelId = null): void
    {
        $this->db->prepare("
            UPDATE packplatz_ruecklagerungen SET
                status = 'erledigt', erledigt_am = NOW(), charge = :charge,
                erledigt_von = :benutzer_id, erledigt_lager_id = :lager_id, erledigt_zustand = :zustand,
                erledigt_artikel_id = :artikel_id
            WHERE id = :id
        ")->execute([
            'benutzer_id' => $benutzerId,
            'lager_id'    => $lagerId,
            'zustand'     => $zustand,
            'charge'      => $charge,
            'artikel_id'  => $artikelId,
            'id'          => $id,
        ]);

        // Eingelagert -> "Retoure offen" am Auftrag kann sich erledigt haben (Belege-Umbau 2026-10-07)
        $aid = $this->db->prepare("SELECT auftrag_id FROM packplatz_ruecklagerungen WHERE id = ?");
        $aid->execute([$id]);
        if ($auftragId = (int)$aid->fetchColumn()) {
            require_once __DIR__ . '/../auftraege/AuftragAbschluss.php';
            AuftragAbschluss::pruefe($auftragId, $benutzerId);
        }
    }

    /** Anzahl offener Rücklagerungen — für ein Badge/Hinweis im Packplatz-Menü. */
    public function zaehleOffene(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM packplatz_ruecklagerungen WHERE status = 'offen'")->fetchColumn();
    }
}
