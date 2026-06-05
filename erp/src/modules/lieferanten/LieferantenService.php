<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/LieferantenRepository.php';

class LieferantenService
{
    private LieferantenRepository $repo;

    public function __construct()
    {
        $this->repo = new LieferantenRepository();
    }

    public function save(array $data): array
    {
        // 1. Validieren
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        // 2. Speichern
        $id = $this->repo->insert($data);

        Logger::log('lieferant.anlegen', 'lieferanten', $id, ['name' => $data['name']]);
        return ['erfolg' => true, 'id' => $id];
    }

    private function validiere(array $data): array
    {
        $fehler = [];

        if (empty($data['name'])) {
            $fehler[] = 'Name ist Pflichtfeld';
        } else {
            // Prüfen ob Lieferant mit gleichem Namen bereits existiert
            if ($this->repo->findByName($data['name'], $data['id'] ?? null) !== false) {
                $fehler[] = 'Lieferant mit diesem Namen existiert bereits!';
            }
        }
        return $fehler;
    }

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


    public function delete(int $id): array
    {
        if ($this->repo->findById($id) === false) {
            return ['erfolg' => false, 'fehler' => 'Lieferant nicht gefunden'];
        }
        $this->repo->deactivate($id);
        Logger::log('lieferant.loeschen', 'lieferanten', $id);
        return ['erfolg' => true];
    }

    public function findByIdMitVertretern(int $id): array|false
    {
        return $this->repo->findByIdMitVertretern($id);
    }

    public function findAll(bool $mitInaktiven = false): array
    {
        return $this->repo->findAll($mitInaktiven);
    }

    public function findVertreterByLieferantId(int $lieferantId): array
    {
        return $this->repo->findVertreterByLieferantId($lieferantId);
    }

    public function findById(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }
        return $this->repo->findById($id);
    }

    public function search(string $q): array
    {
        if (strlen($q) < 2) return [];
        return $this->repo->search($q);
    }

    public function saveVertreter(array $data): array
    {
        // 1. Validieren
        if (empty($data['nachname'])) {
            return ['erfolg' => false, 'fehler' => 'Nachname ist ein Pflichtfeld'];
        };

        // 2. Speichern
        $id = $this->repo->insertVertreter($data);

        Logger::log('vertreter.anlegen', 'lieferanten_vertreter', $id, ['nachname' => $data['nachname']]);
        return ['erfolg' => true, 'id' => $id];
    }

    public function updateVertreter(array $data): array
    {
        // 1. Validieren
        if (empty($data['nachname'])) {
            return ['erfolg' => false, 'fehler' => 'Nachname ist ein Pflichtfeld'];
        };

        // 2. Speichern
        $this->repo->updateVertreter($data);

        Logger::log('vertreter.bearbeiten', 'lieferanten_vertreter', $data['id'], ['nachname' => $data['nachname']]);
        return ['erfolg' => true];
    }

    public function deleteVertreter(int $id): array
    {
        // 1. validieren
        if ($this->repo->findVertreterById($id) === false) {
            return ['erfolg' => false, 'fehler' => 'Vertreter nicht gefunden'];
        }
        $this->repo->deactivateVertreter($id);
        Logger::log('vertreter.loeschen', 'lieferanten_vertreter', $id);
        return ['erfolg' => true];
    }

    public function findVertreterById(int $id): array
    {
        return $this->repo->findVertreterById($id);
    }
}
