<?php
require_once __DIR__ . '/../../core/Database.php';

class HerstellerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(bool $mitInaktiven = false): array
    {
        $where = $mitInaktiven ? '' : 'WHERE aktiv = 1';
        return $this->db->query("
            SELECT id, name, handelsname, webseite, land, email, logo_pfad,
                   strasse, plz, ort,
                   reo_name, reo_strasse, reo_plz, reo_ort, reo_land, reo_email,
                   notizen, aktiv
            FROM hersteller
            $where
            ORDER BY name
        ")->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT id, name, handelsname, webseite, land, email, logo_pfad,
                   strasse, plz, ort,
                   reo_name, reo_strasse, reo_plz, reo_ort, reo_land, reo_email,
                   notizen, aktiv
            FROM hersteller
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findByName(string $name, ?int $excludeId = null): array|false
    {
        $sql = "SELECT id FROM hersteller WHERE name = :name";
        if ($excludeId) $sql .= " AND id != :exclude_id";
        $stmt = $this->db->prepare($sql);
        $params = ['name' => $name];
        if ($excludeId) $params['exclude_id'] = $excludeId;
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO hersteller
                (name, handelsname, webseite, land, email, logo_pfad,
                 strasse, plz, ort,
                 reo_name, reo_strasse, reo_plz, reo_ort, reo_land, reo_email,
                 notizen, aktiv)
            VALUES
                (:name, :handelsname, :webseite, :land, :email, :logo_pfad,
                 :strasse, :plz, :ort,
                 :reo_name, :reo_strasse, :reo_plz, :reo_ort, :reo_land, :reo_email,
                 :notizen, :aktiv)
        ");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function update(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE hersteller SET
                name        = :name,
                handelsname = :handelsname,
                webseite    = :webseite,
                land        = :land,
                email       = :email,
                logo_pfad   = :logo_pfad,
                strasse     = :strasse,
                plz         = :plz,
                ort         = :ort,
                reo_name    = :reo_name,
                reo_strasse = :reo_strasse,
                reo_plz     = :reo_plz,
                reo_ort     = :reo_ort,
                reo_land    = :reo_land,
                reo_email   = :reo_email,
                notizen     = :notizen,
                aktiv       = :aktiv
            WHERE id = :id
        ");
        $stmt->execute($data);
        return $stmt->rowCount() >= 0;
    }

    public function updateLogo(int $id, string $pfad): void
    {
        $stmt = $this->db->prepare("UPDATE hersteller SET logo_pfad = :pfad WHERE id = :id");
        $stmt->execute(['pfad' => $pfad, 'id' => $id]);
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE hersteller SET aktiv = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
