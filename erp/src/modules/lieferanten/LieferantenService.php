<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/LieferantenRepository.php';

/**
 * LieferantenService – Geschäftslogik für Lieferanten und Vertreter
 *
 * Validiert und speichert Lieferanten-Stammdaten und ihre Vertreter.
 * Löschen ist ein Soft-Delete (aktiv = 0) — Datensätze bleiben erhalten
 * für historische Referenzen in Bestellungen und Wareneingängen.
 */
class LieferantenService
{
    private LieferantenRepository $repo;

    public function __construct()
    {
        $this->repo = new LieferantenRepository();
    }

    /**
     * Legt einen neuen Lieferanten an.
     * Validiert: Name Pflichtfeld, kein Duplikat.
     */
    public function save(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $id = $this->repo->insert($data);

        Logger::log('lieferant.anlegen', 'lieferanten', $id, ['name' => $data['name']]);
        return ['erfolg' => true, 'id' => $id];
    }

    /** Validiert Name (Pflichtfeld + Eindeutigkeit). excludeId ermöglicht Selbst-Update. */
    private function validiere(array $data): array
    {
        $fehler = [];

        if (empty($data['name'])) {
            $fehler[] = 'Name ist Pflichtfeld';
        } else {
            // Duplikat-Check: beim Update die eigene ID ausschließen
            if ($this->repo->findByName($data['name'], $data['id'] ?? null) !== false) {
                $fehler[] = 'Lieferant mit diesem Namen existiert bereits!';
            }
        }
        return $fehler;
    }

    /**
     * Aktualisiert einen bestehenden Lieferanten.
     * Validierung identisch zu save().
     */
    public function update(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $erfolg = $this->repo->update($data);

        Logger::log('lieferant.bearbeiten', 'lieferanten', $data['id'], ['name' => $data['name']]);
        return ['erfolg' => true];
    }


    /**
     * Deaktiviert einen Lieferanten (Soft-Delete).
     * Gibt Fehler zurück wenn nicht gefunden.
     */
    public function delete(int $id): array
    {
        if ($this->repo->findById($id) === false) {
            return ['erfolg' => false, 'fehler' => ['Lieferant nicht gefunden']];
        }
        $this->repo->deactivate($id);
        Logger::log('lieferant.loeschen', 'lieferanten', $id);
        return ['erfolg' => true];
    }

    /** Gibt einen Lieferanten mit allen aktiven Vertretern zurück. */
    public function findByIdMitVertretern(int $id): array|false
    {
        return $this->repo->findByIdMitVertretern($id);
    }

    /** Gibt alle (aktiven) Lieferanten zurück. */
    public function findAll(bool $mitInaktiven = false): array
    {
        return $this->repo->findAll($mitInaktiven);
    }

    /** Gibt alle aktiven Vertreter eines Lieferanten zurück. */
    public function findVertreterByLieferantId(int $lieferantId): array
    {
        return $this->repo->findVertreterByLieferantId($lieferantId);
    }

    /** Gibt einen Lieferanten anhand ID zurück. Gibt false zurück bei ID <= 0. */
    public function findById(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }
        return $this->repo->findById($id);
    }

    /**
     * Sucht Lieferanten nach Name oder Land.
     * Gibt leeres Array zurück wenn Suchbegriff kürzer als 2 Zeichen.
     */
    public function search(string $q): array
    {
        if (strlen($q) < 2) return [];
        return $this->repo->search($q);
    }

    /**
     * Legt einen neuen Vertreter für einen Lieferanten an.
     * Nachname ist Pflichtfeld.
     */
    public function saveVertreter(array $data): array
    {
        if (empty($data['nachname'])) {
            return ['erfolg' => false, 'fehler' => ['Nachname ist ein Pflichtfeld']];
        };

        $id = $this->repo->insertVertreter($data);

        Logger::log('vertreter.anlegen', 'lieferanten_vertreter', $id, ['nachname' => $data['nachname']]);
        return ['erfolg' => true, 'id' => $id];
    }

    /**
     * Aktualisiert einen Vertreter.
     * Nachname ist Pflichtfeld.
     */
    public function updateVertreter(array $data): array
    {
        if (empty($data['nachname'])) {
            return ['erfolg' => false, 'fehler' => ['Nachname ist ein Pflichtfeld']];
        };

        $this->repo->updateVertreter($data);

        Logger::log('vertreter.bearbeiten', 'lieferanten_vertreter', $data['id'], ['nachname' => $data['nachname']]);
        return ['erfolg' => true];
    }

    /**
     * Deaktiviert einen Vertreter (Soft-Delete).
     * Gibt Fehler zurück wenn nicht gefunden.
     */
    public function deleteVertreter(int $id): array
    {
        if ($this->repo->findVertreterById($id) === false) {
            return ['erfolg' => false, 'fehler' => ['Vertreter nicht gefunden']];
        }
        $this->repo->deactivateVertreter($id);
        Logger::log('vertreter.loeschen', 'lieferanten_vertreter', $id);
        return ['erfolg' => true];
    }

    /** Gibt einen einzelnen Vertreter anhand ID zurück. */
    public function findVertreterById(int $id): array
    {
        return $this->repo->findVertreterById($id);
    }
}
