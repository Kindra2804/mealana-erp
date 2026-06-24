<?php
require_once __DIR__ . '/../../core/Database.php';

/**
 * HerstellerRepository – CRUD für Hersteller-Stammdaten
 *
 * Hersteller sind Produktionsfirmen hinter den Artikeln (z.B. Drops, Lang Yarns).
 * Enthält GPSR-Felder (EU-Produktsicherheitsverordnung 2023/988):
 * Name + Adresse + E-Mail des Herstellers und — bei Nicht-EU-Herstellern —
 * des europäischen Responsible Economic Operators (REO).
 *
 * Logo wird als Dateiname (z.B. "42.jpg") in logo_pfad gespeichert;
 * Bild liegt in public/img/hersteller/.
 *
 * Löschen = Soft-Delete (aktiv = 0), niemals DELETE FROM hersteller.
 */
class HerstellerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gibt alle Hersteller zurück, alphabetisch sortiert.
     *
     * @param bool $mitInaktiven Wenn false (Standard), werden deaktivierte Hersteller übersprungen
     */
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

    /** Gibt einen Hersteller anhand seiner ID zurück, oder false wenn nicht gefunden. */
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

    /**
     * Prüft ob ein Hersteller mit diesem Namen bereits existiert.
     * excludeId wird beim Update übergeben damit der Hersteller sich selbst nicht sperrt.
     */
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

    /** Legt einen neuen Hersteller an und gibt die neue ID zurück. */
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

    /** Aktualisiert alle Felder eines Herstellers. Gibt true zurück (rowCount >= 0). */
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
        // rowCount() >= 0 statt > 0, weil ein Update ohne Änderung auch Erfolg ist
        return $stmt->rowCount() >= 0;
    }

    /**
     * Aktualisiert nur den Logo-Pfad (nach erfolgreichem GD-Upload).
     * Getrennte Methode weil Logo-Upload async nach dem eigentlichen Insert passiert.
     */
    public function updateLogo(int $id, string $pfad): void
    {
        $stmt = $this->db->prepare("UPDATE hersteller SET logo_pfad = :pfad WHERE id = :id");
        $stmt->execute(['pfad' => $pfad, 'id' => $id]);
    }

    /** Soft-Delete: setzt aktiv = 0 statt den Datensatz zu löschen. */
    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE hersteller SET aktiv = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
