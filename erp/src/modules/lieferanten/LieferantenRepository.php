<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * LieferantenRepository – CRUD für Lieferanten und ihre Vertreter
 *
 * Lieferanten sind Großhändler/Marken von denen MeaLana einkauft
 * (z.B. DROPS, Lang Yarns, Schachenmayr).
 * Jeder Lieferant kann mehrere Vertreter (lieferanten_vertreter) haben.
 *
 * Löschen = Soft-Delete (aktiv = 0) sowohl für Lieferanten als auch Vertreter.
 * Artikel-Lieferanten-Verknüpfungen (EK-Preis, VPE, Bestellnummer) sind
 * in artikel_lieferanten gespeichert — nicht hier.
 */
class LieferantenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gibt alle Lieferanten zurück.
     *
     * @param bool $mitInaktiven Wenn false (Standard), nur aktive Lieferanten
     */
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

    /** Gibt einen Lieferanten anhand ID zurück, oder false wenn nicht gefunden. */
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

    /**
     * Prüft ob ein Lieferant mit diesem Namen bereits existiert.
     * excludeId wird beim Update übergeben damit der Lieferant sich selbst nicht sperrt.
     */
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

    /**
     * Gibt alle aktiven Vertreter eines Lieferanten zurück, alphabetisch nach Nachname.
     * Vertreter sind Ansprechpartner beim Lieferanten (Außendienstmitarbeiter, etc.).
     */
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

    /**
     * Gibt einen Lieferanten mit all seinen aktiven Vertretern zurück.
     * Kombiniert findById() + findVertreterByLieferantId() in einem Aufruf.
     */
    public function findByIdMitVertretern(int $id): array|false
    {
        $lieferanten = $this->findById($id);

        if ($lieferanten === false) {
            return false;
        }

        $lieferanten['vertreter'] = $this->findVertreterByLieferantId($id);

        return $lieferanten;
    }

    /** Legt einen neuen Lieferanten an und gibt die neue ID zurück. */
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

    /** Aktualisiert alle Felder eines Lieferanten und setzt geaendert_am = NOW(). */
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

    /** Soft-Delete: setzt aktiv = 0. */
    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE lieferanten SET aktiv = 0 WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Volltextsuche nach Name oder Land.
     * Wird für AJAX-Typeahead in Bestellungen verwendet.
     */
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

    /** Legt einen neuen Vertreter für einen Lieferanten an. */
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

    /** Aktualisiert alle Felder eines Vertreters und setzt geaendert_am = NOW(). */
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

    /** Soft-Delete für einen Vertreter (aktiv = 0). */
    public function deactivateVertreter(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE lieferanten_vertreter SET aktiv = 0 WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Gibt einen einzelnen Vertreter anhand ID zurück. */
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
