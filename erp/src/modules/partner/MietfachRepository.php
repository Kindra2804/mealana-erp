<?php

require_once __DIR__ . '/../../core/Database.php';

class MietfachRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Physische Fächer
    // -------------------------------------------------------------------------

    public function findAll(): array
    {
        // Gibt alle Fächer zurück, jeweils mit aktivem Mietvertrag (falls vorhanden)
        $stmt = $this->db->query("
            SELECT f.*,
                   v.id              AS vertrag_id,
                   v.partner_id      AS mieter_id,
                   v.mietbetrag_monatlich,
                   v.mwst_satz,
                   v.mietbeginn,
                   v.mietende,
                   p.name            AS mieter_name
            FROM   mietfaecher f
            LEFT JOIN mietfach_mietvertraege v
                   ON  v.mietfach_id = f.id
                   AND v.mietende   IS NULL   -- aktiver Vertrag (unbefristet)
                   OR  (v.mietfach_id = f.id AND v.mietende >= CURDATE())
            LEFT JOIN partner p ON p.id = v.partner_id
            GROUP BY f.id
            ORDER BY f.fach_bezeichnung
        ");
        return $stmt->fetchAll();
    }

    public function findAllMitStatus(): array
    {
        // Sauberere Variante mit Subquery für aktuellen Vertrag
        $stmt = $this->db->query("
            SELECT f.*,
                   v.id                   AS vertrag_id,
                   v.partner_id           AS mieter_id,
                   v.mietbetrag_monatlich AS vertrag_preis,
                   v.mwst_satz            AS vertrag_mwst,
                   v.mietbeginn,
                   v.mietende,
                   p.name                 AS mieter_name
            FROM   mietfaecher f
            LEFT JOIN mietfach_mietvertraege v ON v.id = (
                SELECT id FROM mietfach_mietvertraege
                WHERE  mietfach_id = f.id
                  AND  (mietende IS NULL OR mietende >= CURDATE())
                ORDER BY mietbeginn DESC
                LIMIT  1
            )
            LEFT JOIN partner p ON p.id = v.partner_id
            ORDER BY f.fach_bezeichnung
        ");
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM mietfaecher WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findFreie(): array
    {
        $stmt = $this->db->query("
            SELECT f.*
            FROM   mietfaecher f
            WHERE  f.aktiv = 1
              AND  NOT EXISTS (
                  SELECT 1 FROM mietfach_mietvertraege v
                  WHERE  v.mietfach_id = f.id
                    AND  (v.mietende IS NULL OR v.mietende >= CURDATE())
              )
            ORDER BY f.fach_bezeichnung
        ");
        return $stmt->fetchAll();
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO mietfaecher (
                fach_bezeichnung, ort_beschreibung,
                laenge_cm, breite_cm, hoehe_cm,
                standard_preis, notiz, aktiv
            ) VALUES (
                :fach_bezeichnung, :ort_beschreibung,
                :laenge_cm, :breite_cm, :hoehe_cm,
                :standard_preis, :notiz, :aktiv
            )
        ');
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE mietfaecher SET
                fach_bezeichnung  = :fach_bezeichnung,
                ort_beschreibung  = :ort_beschreibung,
                laenge_cm         = :laenge_cm,
                breite_cm         = :breite_cm,
                hoehe_cm          = :hoehe_cm,
                standard_preis    = :standard_preis,
                notiz             = :notiz,
                aktiv             = :aktiv
            WHERE id = :id
        ');
        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Mietverträge
    // -------------------------------------------------------------------------

    public function findAktuellerVertrag(int $fachId): array|false
    {
        $stmt = $this->db->prepare('
            SELECT v.*, p.name AS partner_name
            FROM   mietfach_mietvertraege v
            JOIN   partner p ON p.id = v.partner_id
            WHERE  v.mietfach_id = :fach_id
              AND  (v.mietende IS NULL OR v.mietende >= CURDATE())
            ORDER BY v.mietbeginn DESC
            LIMIT  1
        ');
        $stmt->execute(['fach_id' => $fachId]);
        return $stmt->fetch();
    }

    public function findVertraege(int $fachId): array
    {
        $stmt = $this->db->prepare('
            SELECT v.*, p.name AS partner_name
            FROM   mietfach_mietvertraege v
            JOIN   partner p ON p.id = v.partner_id
            WHERE  v.mietfach_id = :fach_id
            ORDER BY v.mietbeginn DESC
        ');
        $stmt->execute(['fach_id' => $fachId]);
        return $stmt->fetchAll();
    }

    public function findFaecherByPartner(int $partnerId): array
    {
        $stmt = $this->db->prepare('
            SELECT f.fach_bezeichnung, f.ort_beschreibung,
                   v.mietbetrag_monatlich, v.mietbeginn, v.mietende
            FROM   mietfach_mietvertraege v
            JOIN   mietfaecher f ON f.id = v.mietfach_id
            WHERE  v.partner_id = :partner_id
              AND  (v.mietende IS NULL OR v.mietende >= CURDATE())
            ORDER BY f.fach_bezeichnung
        ');
        $stmt->execute(['partner_id' => $partnerId]);
        return $stmt->fetchAll();
    }

    public function insertVertrag(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO mietfach_mietvertraege (
                mietfach_id, partner_id, mietbetrag_monatlich,
                mwst_satz, mietbeginn, mietende, notiz
            ) VALUES (
                :mietfach_id, :partner_id, :mietbetrag_monatlich,
                :mwst_satz, :mietbeginn, :mietende, :notiz
            )
        ');
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function vertragBeenden(int $vertragId, string $mietende): bool
    {
        $stmt = $this->db->prepare('
            UPDATE mietfach_mietvertraege SET mietende = :mietende WHERE id = :id
        ');
        $stmt->execute(['mietende' => $mietende, 'id' => $vertragId]);
        return $stmt->rowCount() > 0;
    }

    public function isFachBelegt(int $fachId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM mietfach_mietvertraege
            WHERE  mietfach_id = :fach_id
              AND  (mietende IS NULL OR mietende >= CURDATE())
        ');
        $stmt->execute(['fach_id' => $fachId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
