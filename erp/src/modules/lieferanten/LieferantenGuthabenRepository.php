<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * LieferantenGuthabenRepository – Kontoauszug für Lieferanten-Guthaben (DROPS-Modell)
 *
 * Saldo pro Lieferant = Summe aller Bewegungen (positiv = Gutschrift erhalten,
 * negativ = bei einer Bestellung verrechnet). Siehe Migration 133.
 */
class LieferantenGuthabenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getSaldo(int $lieferantId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(betrag), 0) FROM lieferanten_guthaben_bewegungen WHERE lieferant_id = :id
        ");
        $stmt->execute(['id' => $lieferantId]);
        return (float)$stmt->fetchColumn();
    }

    /** Gibt alle Bewegungen eines Lieferanten zurück, neueste zuerst. */
    public function findBewegungen(int $lieferantId): array
    {
        $stmt = $this->db->prepare("
            SELECT g.*, b.formularname AS erfasst_von_name
            FROM lieferanten_guthaben_bewegungen g
            LEFT JOIN benutzer b ON b.id = g.erfasst_von
            WHERE g.lieferant_id = :id
            ORDER BY g.datum DESC, g.erfasst_am DESC
        ");
        $stmt->execute(['id' => $lieferantId]);
        return $stmt->fetchAll();
    }

    public function insertBewegung(
        int $lieferantId,
        float $betrag,
        string $typ,
        ?int $bestellungId,
        ?string $notiz,
        string $datum,
        int $benutzerId
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO lieferanten_guthaben_bewegungen
                (lieferant_id, bestellung_id, betrag, typ, datum, notiz, erfasst_von)
            VALUES
                (:lieferant_id, :bestellung_id, :betrag, :typ, :datum, :notiz, :erfasst_von)
        ");
        $stmt->execute([
            'lieferant_id'  => $lieferantId,
            'bestellung_id' => $bestellungId,
            'betrag'        => $betrag,
            'typ'           => $typ,
            'datum'         => $datum,
            'notiz'         => $notiz,
            'erfasst_von'   => $benutzerId,
        ]);
        return (int)$this->db->lastInsertId();
    }
}
