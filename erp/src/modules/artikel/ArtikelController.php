<?php

require_once __DIR__ . '/ArtikelRepository.php';

/**
 * ArtikelController – Dünner Controller für Artikel-Datenzugriff
 *
 * Wrapper um ArtikelRepository mit Eingabevalidierung (id > 0).
 * Wird von Views über $controller->index() / $controller->detail()
 * aufgerufen. Die eigentliche Logik liegt im ArtikelService.
 *
 * Hinweis: Schreiboperationen (neu, bearbeiten, löschen) laufen
 * direkt über ArtikelService — nicht über diesen Controller.
 */
class ArtikelController
{
    private ArtikelRepository $repo;

    public function __construct()
    {
        $this->repo = new ArtikelRepository();
    }

    /**
     * Gibt eine gefilterte und paginierte Artikelliste zurück.
     * Delegiert an ArtikelRepository::findAll() mit allen Filteroptionen.
     */
    public function index(array $filter = [], int $limit = 25, int $offset = 0): array
    {
        return $this->repo->findAll($filter, $limit, $offset);
    }

    /**
     * Gibt die Gesamtanzahl der Artikel für Paginierung zurück.
     * Verwendet dieselben Filter wie index() für konsistente Seitenzahl-Berechnung.
     */
    public function count(array $filter = []): int
    {
        return $this->repo->countAll($filter);
    }

    /**
     * Gibt einen Artikel mit allen Kind-Artikeln zurück (für Detail-View).
     * Gibt false zurück wenn ID ungültig oder Artikel nicht gefunden.
     */
    public function detail(int $id): array|false
    {
        if ($id <= 0) return false;
        return $this->repo->findByIdMitKindern($id);
    }

    /**
     * Gibt einen einzelnen Artikel für das Bearbeiten-Formular zurück.
     * Gibt false zurück wenn ID ungültig.
     */
    public function findFuerBearbeitung(int $id): array|false
    {
        if ($id <= 0) return false;
        return $this->repo->findById($id);
    }

    /** Deaktiviert einen Artikel (setzt aktiv = 0). Gibt false zurück bei ungültiger ID. */
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
