<?php

require_once __DIR__ . '/AktivitaetenRepository.php';
require_once __DIR__ . '/../benutzer/BenutzerRepository.php';

/**
 * AktivitaetenService – Pagination + Filter-Aufbereitung fürs Aktivitäten-Log.
 *
 * Reines Lese-Modul, keine Validierung/Business-Logik nötig (siehe
 * AktivitaetenRepository) — der Service übernimmt hier nur die Seiten-Mathematik,
 * damit die View (public/admin/aktivitaeten.php) schlank bleibt.
 */
class AktivitaetenService
{
    private AktivitaetenRepository $repo;
    private BenutzerRepository $benutzerRepo;

    public function __construct()
    {
        $this->repo = new AktivitaetenRepository();
        $this->benutzerRepo = new BenutzerRepository();
    }

    /** Für die Logger-Zeile in der Shell. */
    public function getLetzte(int $limit = 5): array
    {
        return $this->repo->findLetzte($limit);
    }

    /**
     * @param array $filter siehe AktivitaetenRepository::buildWhere()
     * @return array{items:array,gesamt:int,seiten:int,seite:int,pro_seite:int}
     */
    public function getGefiltert(array $filter, int $seite, int $proSeite): array
    {
        $seite = max(1, $seite);
        $proSeite = max(1, $proSeite);
        $offset = ($seite - 1) * $proSeite;

        $gesamt = $this->repo->countGefiltert($filter);
        $items  = $this->repo->findGefiltert($filter, $offset, $proSeite);
        $seiten = (int)ceil($gesamt / $proSeite);

        return [
            'items'     => $items,
            'gesamt'    => $gesamt,
            'seiten'    => $seiten,
            'seite'     => $seite,
            'pro_seite' => $proSeite,
        ];
    }

    public function getModule(): array
    {
        return $this->repo->findModule();
    }

    public function getReferenzTabellen(): array
    {
        return $this->repo->findReferenzTabellen();
    }

    public function getBenutzerListe(): array
    {
        return $this->benutzerRepo->findAll();
    }
}
