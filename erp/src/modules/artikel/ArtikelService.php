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
        }
        if (empty($data['name'])) {
            $fehler[] = 'Name ist Pflichtfeld';
        }
        if (empty($data['artikeltyp'])) {
            $fehler[] = 'Artikeltyp ist Pflichtfeld';
        }

        return $fehler;
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO artikel (
            artikelnummer,
            hersteller_id,
            steuerklasse_id,
            artikeltyp,
            name,
            beschreibung_kurz,
            beschreibung_lang,
            einheit,
            inhalt_menge,
            inhalt_einheit,
            gewicht_artikel,
            gewicht_versand,
            herkunftsland,
            taric_code,
            varianten_darstellung,
            grundpreis_bezugsmenge,
            grundpreis_anzeigen,
            aktiv
        ) VALUES (
            :artikelnummer,
            :hersteller_id,
            :steuerklasse_id,
            :artikeltyp,
            :name,
            :beschreibung_kurz,
            :beschreibung_lang,
            :einheit,
            :inhalt_menge,
            :inhalt_einheit,
            :gewicht_artikel,
            :gewicht_versand,
            :herkunftsland,
            :taric_code,
            :varianten_darstellung,
            :grundpreis_bezugsmenge,
            :grundpreis_anzeigen,
            :aktiv
        )
    ");

        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }
}
