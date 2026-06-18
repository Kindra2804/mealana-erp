<?php

require_once __DIR__ . '/../../core/database.php';

class AchsenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                va.id,
                va.name,
                va.code,
                va.darstellungsform,
                va.abhaengig_von_achse_id,
                va2.name        AS abhaengig_von_name,
                va.sort_order,
                IF(COUNT(aa.id) > 0, 1, 0) AS in_use
            FROM varianten_achsen va
            LEFT JOIN varianten_achsen va2 ON va2.id = va.abhaengig_von_achse_id
            LEFT JOIN artikel_achsen aa ON aa.achse_id = va.id
            GROUP BY va.id
            ORDER BY va.sort_order, va.name
        ");

        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT
                va.id,
                va.name,
                va.code,
                va.darstellungsform,
                va.abhaengig_von_achse_id,
                va.sort_order
            FROM varianten_achsen va
            WHERE va.id = :id
        ");

        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findByCode(string $code, ?int $excludeId = null): array|false
    {
        $sql = "SELECT id FROM varianten_achsen WHERE code = :code";
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->db->prepare($sql);
        $params = ['code' => $code];
        if ($excludeId) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO varianten_achsen (name, code, darstellungsform, abhaengig_von_achse_id, sort_order)
            VALUES (:name, :code, :darstellungsform, :abhaengig_von_achse_id, :sort_order)
        ");

        $stmt->execute([
            'name'                  => $data['name'],
            'code'                  => $data['code'],
            'darstellungsform'      => $data['darstellungsform'],
            'abhaengig_von_achse_id'=> $data['abhaengig_von_achse_id'] ?? null,
            'sort_order'            => $data['sort_order'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE varianten_achsen SET
                name                   = :name,
                code                   = :code,
                darstellungsform       = :darstellungsform,
                abhaengig_von_achse_id = :abhaengig_von_achse_id,
                sort_order             = :sort_order
            WHERE id = :id
        ");

        $stmt->execute([
            'id'                    => $data['id'],
            'name'                  => $data['name'],
            'code'                  => $data['code'],
            'darstellungsform'      => $data['darstellungsform'],
            'abhaengig_von_achse_id'=> $data['abhaengig_von_achse_id'] ?? null,
            'sort_order'            => $data['sort_order'],
        ]);

        return $stmt->rowCount() > 0;
    }

    public function isInUse(int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT
                va.id
            FROM varianten_achsen va
            JOIN artikel_achsen aa ON aa.achse_id = va.id
            WHERE va.id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);

        return $stmt->fetch() !== false;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE
            FROM varianten_achsen
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateSortOrder(int $id, int $order): void
    {
        $stmt = $this->db->prepare("UPDATE varianten_achsen SET sort_order = :order WHERE id = :id");
        $stmt->execute(['order' => $order, 'id' => $id]);
    }
}
