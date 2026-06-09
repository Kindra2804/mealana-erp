<?php

require_once __DIR__ . '/ArtikelRepository.php';

class ArtikelController
{
    private ArtikelRepository $repo;

    public function __construct()
    {
        $this->repo = new ArtikelRepository();
    }

    public function index(array $filter = []): array
    {
        return $this->repo->findAll($filter);
    }

    public function detail(int $id): array|false
    {
        if ($id <= 0) return false;
        return $this->repo->findByIdMitKindern($id);
    }

    public function findFuerBearbeitung(int $id): array|false
    {
        if ($id <= 0) return false;
        return $this->repo->findById($id);
    }

    public function deactivate(int $id): bool
    {
        if ($id <= 0) return false;
        return $this->repo->deactivate($id);
    }

    // public function search(string $q): array
    // {
    //     if (strlen($q) < 2) return [];
    //     return $this->repo->search($q);
    // }
}
