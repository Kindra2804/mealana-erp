<?php

require_once __DIR__ . '/LieferantenRepository.php';

/**
 * LieferantenController – Dünner Controller für Lieferanten-Lesezugriff
 *
 * Wrapper um LieferantenRepository mit ID-Validierung und Suchlogik.
 * Schreiboperationen (Anlegen, Bearbeiten, Löschen von Lieferanten
 * und Vertretern) laufen über LieferantenService.
 */
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

    /**
     * Gibt alle Lieferanten zurück.
     *
     * @param bool $mitInaktiven Wenn true, werden auch deaktivierte Lieferanten mitgeliefert
     */
    public function index(bool $mitInaktiven = false): array
    {
        return $this->repo->findAll($mitInaktiven);
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
     * Gibt einen Lieferanten mit allen aktiven Vertretern zurück.
     * Gibt false zurück bei ungültiger oder nicht gefundener ID.
     */
    public function detail(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findByIdMitVertretern($id);
    }
}
