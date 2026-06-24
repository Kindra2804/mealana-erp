<?php

require_once __DIR__ . '/LagerRepository.php';

/**
 * LagerController – Dünner Controller für Lager-Datenzugriff
 *
 * Wrapper um LagerRepository mit ID-Validierung.
 * Schreiboperationen (Wareneingang buchen, Charge nachtragen)
 * laufen über LagerService — nicht über diesen Controller.
 *
 * TODO: bestandByVariante(), chargenByVariante() und bewegungByVariante()
 * rufen Repository-Methoden auf, die noch nicht existieren. Diese drei
 * Methoden sind aktuell nicht funktional — sie wurden aus einer früheren
 * Varianten-Struktur übernommen und müssen noch implementiert werden.
 */
class LagerController
{
    private LagerRepository $repo;

    public function __construct()
    {
        $this->repo = new LagerRepository();
    }

    /** Gibt alle Lager mit Bestandszeilen zurück. */
    public function index(): array
    {
        return $this->repo->findAll();
    }

    /** Gibt den Bestand eines Artikels nach Variante zurück. */
    public function bestandByVariante(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findBestandByArtikelVarianteId($id);
    }

    /** Gibt alle Lagerbestand-Zeilen für ein bestimmtes Lager zurück. */
    public function bestandByLager(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findBestandByLager($id);
    }

    /** Gibt alle Chargen eines Artikels zurück. */
    public function chargenByVariante(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findChargenByVarianteId($id);
    }

    /** Gibt alle Lagerbestand-Zeilen zurück wo charge_status = 'nachzutragen'. */
    public function nachzutragendeChargen(): array
    {
        return $this->repo->findNachzutragendeChargen();
    }

    /** Gibt das Bewegungslog für einen Artikel zurück. */
    public function bewegungByVariante(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repo->findBewegungByVarianteId($id);
    }
}
