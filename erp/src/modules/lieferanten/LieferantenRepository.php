<?php

require_once __DIR__ . '/../../core/Database.php';

class LieferantenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(bool $mitInaktiven = false): array
    {
        $where = $mitInaktiven ? '' : 'WHERE l.aktiv = 1';
        $stmt = $this->db->query("
            SELECT 
                l.id,
                l.name,
                l.land,
                l.website,
                l.email,
                l.telefon,
                l.aktiv,
                l.erstellt_am
            FROM lieferanten l
            $where
        ");

        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
        SELECT 
            l.id,
            l.name,
            l.land,
            l.website,
            l.email,
            l.telefon,
            l.aktiv,
            l.erstellt_am
            FROM lieferanten l
            WHERE l.id = :id
        ");

        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findByName(string $name, ?int $excludeId = null): array|false
    {
        $sql = "SELECT id FROM lieferanten WHERE name = :name";
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->db->prepare($sql);
        $params = ['name' => $name];
        if ($excludeId) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function findVertreterByLieferantId(int $lieferantId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            vorname,
            nachname,
            telefon,
            email,
            mobil,
            notizen,
            erstellt_am,
            geaendert_am
        FROM lieferanten_vertreter
        WHERE lieferant_id = :lieferant_id
        AND aktiv = 1
        ORDER BY nachname ASC
    ");

        $stmt->execute(['lieferant_id' => $lieferantId]);
        return $stmt->fetchAll();
    }

    public function findByIdMitVertretern(int $id): array|false
    {
        $lieferanten = $this->findById($id);

        if ($lieferanten === false) {
            return false;
        }

        $lieferanten['vertreter'] = $this->findVertreterByLieferantId($id);

        return $lieferanten;
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO lieferanten (name, land, website, email, telefon, aktiv)
            VALUES (:name, :land, :website, :email, :telefon, :aktiv)
        ");

        $stmt->execute([
            'name' => $data['name'],
            'land' => $data['land'] ?? null,
            'website' => $data['website'] ?? null,
            'email' => $data['email'] ?? null,
            'telefon' => $data['telefon'] ?? null,
            'aktiv' => isset($data['aktiv']) ? (int) $data['aktiv'] : 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lieferanten SET
                name = :name,
                land = :land,
                website = :website,
                email = :email,
                telefon = :telefon,
                aktiv = :aktiv,
                geaendert_am = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $data['id'],
            'name' => $data['name'],
            'land' => $data['land'] ?? null,
            'website' => $data['website'] ?? null,
            'email' => $data['email'] ?? null,
            'telefon' => $data['telefon'] ?? null,
            'aktiv' => isset($data['aktiv']) ? (int) $data['aktiv'] : 1
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE lieferanten SET aktiv = 0 WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function search(string $q): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                l.id,
                l.name,
                l.land,
                l.website,
                l.email,
                l.telefon,
                l.aktiv,
                l.erstellt_am
            FROM lieferanten l
            WHERE (l.name LIKE :q OR l.land LIKE :q)
        ");

        $stmt->execute(['q' => '%' . $q . '%']);
        return $stmt->fetchAll();
    }

    public function insertVertreter(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO lieferanten_vertreter (lieferant_id, vorname, nachname, telefon, email, mobil, notizen, aktiv)
            VALUES (:lieferant_id, :vorname, :nachname, :telefon, :email, :mobil, :notizen, :aktiv)
        ");

        $stmt->execute([
            'lieferant_id' => $data['lieferant_id'],
            'vorname' => $data['vorname'],
            'nachname' => $data['nachname'],
            'telefon' => $data['telefon'] ?? null,
            'email' => $data['email'] ?? null,
            'mobil' => $data['mobil'] ?? null,
            'notizen' => $data['notizen'] ?? null,
            'aktiv' => isset($data['aktiv']) ? (int) $data['aktiv'] : 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateVertreter(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lieferanten_vertreter SET
                vorname = :vorname,
                nachname = :nachname,
                telefon = :telefon,
                email = :email,
                mobil = :mobil,
                notizen = :notizen,
                aktiv = :aktiv,
                geaendert_am = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $data['id'],
            'vorname' => $data['vorname'],
            'nachname' => $data['nachname'] ?? null,
            'telefon' => $data['telefon'] ?? null,
            'email' => $data['email'] ?? null,
            'mobil' => $data['mobil'] ?? null,
            'notizen' => $data['notizen'] ?? null,
            'aktiv' => isset($data['aktiv']) ? (int) $data['aktiv'] : 1
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deactivateVertreter(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE lieferanten_vertreter SET aktiv = 0 WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function findVertreterById(int $id): array|false
    {
        $stmt = $this->db->prepare("
        SELECT id, lieferant_id, vorname, nachname,
               telefon, email, mobil, notizen, aktiv
        FROM lieferanten_vertreter
        WHERE id = :id
    ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
