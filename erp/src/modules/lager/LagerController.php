<?php

require_once __DIR__ . '/LagerRepository.php';

class LagerController
{
    private LagerRepository $repo;

    public function __construct()
    {
        $this->repo = new LagerRepository();
    }

    public function index(): array
    {
        return $this->repo->findAll();
    }

    public function bestandByVariante(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findBestandByArtikelVarianteId($id);
    }

    public function bestandByLager(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findBestandByLager($id);
    }

    public function chargenByVariante(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findChargenByVarianteId($id);
    }

    public function nachzutragendeChargen(): array|false
    {
        return $this->repo->findNachzutragendeChargen();
    }

    public function bewegungByVariante(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findBewegungByVarianteId($id);
    }
}
