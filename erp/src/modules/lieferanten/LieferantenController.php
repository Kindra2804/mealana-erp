<?php

require_once __DIR__ . '/LieferantenRepository.php';

class LieferantenController
{
    private LieferantenRepository $repo;

    public function __construct()
    {
        $this->repo = new LieferantenRepository();
    }

    // public function index(): array
    // {
    //     return $this->repo->findAll();
    // }

    public function index(bool $mitInaktiven = false): array
    {
        return $this->repo->findAll($mitInaktiven);
    }

    public function search(string $q): array
    {
        if (strlen($q) < 2) return [];
        return $this->repo->search($q);
    }

    public function detail(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findByIdMitVertretern($id);
    }
}
