<?php
require_once __DIR__ . '/../../core/Logger.php';
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

        // Preise vor insert() rausziehen
        $bruttoVk = $data['brutto_vk'] ?? null;
        $nettoVk  = $data['netto_vk']  ?? null;
        unset($data['brutto_vk'], $data['netto_vk']);

        $id = $this->repo->insert($data);

        // Preis speichern
        if ($bruttoVk && $nettoVk) {
            $this->repo->insertPreis($id, (float)$bruttoVk, (float)$nettoVk);
        }

        Logger::log('artikel.anlegen', 'artikel', $id, ['name' => $data['name']]);
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
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        // Preise rausziehen
        $bruttoVk = $data['brutto_vk'] ?? null;
        $nettoVk  = $data['netto_vk']  ?? null;
        unset($data['brutto_vk'], $data['netto_vk']);

        $erfolg = $this->repo->update($data);

        // Preis aktualisieren
        if ($bruttoVk && $nettoVk) {
            $this->repo->updatePreis(
                (int) $data['id'],
                (float) $bruttoVk,
                (float) $nettoVk
            );
        }

        Logger::log('artikel.bearbeiten', 'artikel', $data['id'], ['name' => $data['name']]);
        return ['erfolg' => true];
    }

    public function saveVariante(array $data): array
    {
        $fehler = $this->validiereVariante($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $id = $this->repo->insertVariante($data);

        Logger::log('artikel.variante_anlegen', 'artikel_varianten', $id, ['farbe' => $data['farbe_name']]);
        return ['erfolg' => true, 'id' => $id];
    }

    private function validiereVariante(array $data): array
    {
        $fehler = [];

        if (empty($data['artikelnummer'])) {
            $fehler[] = 'Artikelnummer ist Pflichtfeld';
        }
        if (empty($data['farbe_name'])) {
            $fehler[] = 'Farbname ist Pflichtfeld';
        }
        if (empty($data['artikel_id'])) {
            $fehler[] = 'Artikel-Zuordnung fehlt';
        }

        return $fehler;
    }

    public function varianteUpdate(array $data): array
    {
        $fehler = $this->validiereVariante($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $erfolg = $this->repo->updateVariante($data);

        Logger::log('artikel.variante_bearbeiten', 'artikel_varianten', $data['id'], ['farbe' => $data['farbe_name']]);
        return ['erfolg' => true];
    }

    public function delete(int $id): array
    {
        if ($this->repo->findById($id) === false) {
            return ['erfolg' => false, 'fehler' => 'Artikel nicht gefunden'];
        }
        $this->repo->deactivate($id);
        Logger::log('artikel.loeschen', 'artikel', $id);
        return ['erfolg' => true];
    }
}
