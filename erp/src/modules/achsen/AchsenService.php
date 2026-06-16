<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/AchsenRepository.php';

class AchsenService
{
    private AchsenRepository $repo;

    public function __construct()
    {
        $this->repo = new AchsenRepository();
    }

    public function findAll(): array
    {
        return $this->repo->findAll();
    }

    private function validiere(array $data): array
    {
        $fehler = [];

        if (empty($data['name'])) {
            $fehler[] = 'Name ist Pflichtfeld';
        }

        if (empty($data['code'])) {
            $fehler[] = 'Code ist Pflichtfeld';
        } else {
            if ($this->repo->findByCode($data['code'], $data['id'] ?? null) !== false) {
                $fehler[] = 'Code ist bereits angelegt !';
            }
        }

        $erlaubt = ['swatches', 'dropdown', 'radiobutton', 'freitext', 'pflichtfreitext'];
        if (!in_array($data['darstellungsform'], $erlaubt)) {
            $fehler[] = 'ungültige Darstellungsform !';
        }

        return $fehler;
    }

    public function findById(int $id): array|false
    {
        if ($id > 0) {
            return $this->repo->findById($id);
        }

        return ['erfolg' => false, 'fehler' => ['Id kann nicht 0 sein']];
    }

    public function save(array $data): array
    {
        $fehler = $this->validiere($data);

        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $id = $this->repo->insert($data);

        Logger::log('achse.anlegen', 'varianten_achsen', $id, ['name' => $data['name'], 'code' => $data['code'], 'darstellungsform' => $data['darstellungsform']]);
        return ['erfolg' => true, 'id' => $id];
    }

    public function update(array $data): array
    {
        $fehler = $this->validiere($data);

        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $this->repo->update($data);

        Logger::log('achse.updaten', 'varianten_achsen', $data['id'], ['name' => $data['name'], 'code' => $data['code'], 'darstellungsform' => $data['darstellungsform']]);
        return ['erfolg' => true, 'id' => $data['id']];
    }

    public function delete(int $id): array
    {
        $fehler = $this->repo->isInUse($id);

        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => ['Achse wird noch verwendet']];
        }

        $this->repo->delete($id);

        Logger::log('achse.geloescht', 'varianten_achsen', $id);
        return ['erfolg' => true, 'id' => $id];
    }

    public function updateSortOrder(int $id, int $order): void
    {
        $this->repo->updateSortOrder($id, $order);
    }
}
