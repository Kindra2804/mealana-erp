<?php

require_once __DIR__ . '/ArtikelRepository.php';

class ArtikelService
{
    private ArtikelRepository $repo;

    public function __construct()
    {
        $this->repo = new ArtikelRepository();
    }

    public function save(array $data): array
    {
        // 1. Validieren
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        // 2. Speichern
        $id = $this->repo->insert($data);

        return ['erfolg' => true, 'id' => $id];
    }

    private function validiere(array $data): array
    {
        $fehler = [];

        if (empty($data['artikelnummer'])) {
            $fehler[] = 'Artikelnummer ist Pflichtfeld';
        } else {
            // Prüfen ob Artikelnummer bereits existiert
            $vorhanden = $this->repo->findByArtikelnummer(
                $data['artikelnummer'],
                $data['id'] ?? null  // beim Update eigene ID ausschließen
            );
            if ($vorhanden !== false) {
                $fehler[] = 'Artikelnummer "' . $data['artikelnummer'] . '" existiert bereits!';
            }
        }
        if (empty($data['name'])) {
            $fehler[] = 'Name ist Pflichtfeld';
        }
        if (empty($data['artikeltyp'])) {
            $fehler[] = 'Artikeltyp ist Pflichtfeld';
        }

        return $fehler;
    }

    public function update(array $data): array
    {
        // 1. Validieren
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        // 2. Speichern
        $erfolg = $this->repo->update($data);

        return ['erfolg' => true];
    }
}
