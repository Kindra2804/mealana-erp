<?php

require_once __DIR__ . '/MerkmalRepository.php';

/**
 * MerkmalController – Controller für Merkmal-Datenzugriff
 *
 * Wrapper um MerkmalRepository mit ID-Validierung.
 * Merkmale sind Produkteigenschaften (z.B. "Material", "Nadelstärke")
 * die einem Artikel über artikel_merkmale zugewiesen werden.
 *
 * Merkmale können filterbar sein — diese erscheinen im Shop als
 * Filteroptionen auf Kategorieseiten.
 */
class MerkmalController
{
    private MerkmalRepository $repo;

    public function __construct()
    {
        $this->repo = new MerkmalRepository();
    }

    /** Gibt alle Merkmale mit Gruppen-Namen zurück. */
    public function index(): array
    {
        return $this->repo->findAll();
    }

    /**
     * Gibt alle aktiven Merkmale einer bestimmten Merkmal-Gruppe zurück.
     * Gibt false zurück bei ungültiger ID.
     */
    public function merkmalByGroup(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findMerkmaleByGroupId($id);
    }

    /**
     * Gibt alle Merkmale zurück, die einem Artikel zugewiesen sind
     * (mit den eingetragenen Werten: wert_text, wert_zahl, wert_bool).
     * Gibt false zurück bei ungültiger ID.
     */
    public function merkmalByArtikel(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findMerkmaleByArtikelId($id);
    }

    /**
     * Gibt nur die filterbaren Merkmale eines Artikels zurück.
     * Subset von merkmalByArtikel — nur Merkmale mit filterbar = 1.
     * Gibt false zurück bei ungültiger ID.
     */
    public function filterbareByArtikel(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findFilterbareByArtikelId($id);
    }
}
