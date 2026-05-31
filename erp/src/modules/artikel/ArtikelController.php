<?php

require_once __DIR__ . '/ArtikelRepository.php';

class ArtikelController
{
    private ArtikelRepository $repo;

    public function __construct()
    {
        $this->repo = new ArtikelRepository();
    }

    public function index(bool $mitInaktiven = false): array
    {
        return $this->repo->findAll($mitInaktiven);
    }

    public function detail(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findByIdMitVarianten($id);
    }

    public function findFuerBearbeitung(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }
        return $this->repo->findById($id);
    }

    public function deactivate(int $id): bool
    {
        if ($id <= 0) return false;
        return $this->repo->deactivate($id);
    }

    public function findVarianteFuerBearbeitung(int $id): array|false
    {
        if ($id <= 0) return false;
        return $this->repo->findVarianteById($id);
    }
}
