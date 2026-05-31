<?php

require_once __DIR__ . '/MerkmalRepository.php';

class MerkmalController
{
    private MerkmalRepository $repo;

    public function __construct()
    {
        $this->repo = new MerkmalRepository();
    }

    public function index(): array
    {
        return $this->repo->findAll();
    }

    public function merkmalByGroup(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findMerkmaleByGroupId($id);
    }

    public function merkmalByArtikel(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findMerkmaleByArtikelId($id);
    }

    public function filterbareByArtikel(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findFilterbareByArtikelId($id);
    }
}
