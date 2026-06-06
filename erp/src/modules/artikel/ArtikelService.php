<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/ArtikelRepository.php';
require_once __DIR__ . '/KategorieRepository.php';

class ArtikelService
{
    private ArtikelRepository $repo;
    private KategorieRepository $kategorieRepo;


    public function __construct()
    {
        $this->repo = new ArtikelRepository();
        $this->kategorieRepo = new KategorieRepository();
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

        $eanGtin13 = $data['ean_gtin13'] ?? null;
        unset($data['ean_gtin13']);

        $id = $this->repo->insert($data);

        // Preis speichern
        if ($bruttoVk && $nettoVk) {
            $this->repo->insertPreis($id, (float)$bruttoVk, (float)$nettoVk);
        }

        if ($eanGtin13) {
            $this->repo->insertCode($id, 'GTIN13', $eanGtin13);
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

        $eanGtin13 = $data['ean_gtin13'] ?? null;
        unset($data['ean_gtin13']);

        $erfolg = $this->repo->update($data);
        if (!$erfolg) {
            return ['erfolg' => false, 'fehler' => ['Datenbankfehler beim Speichern']];
        }

        $this->repo->deleteCodesByArtikelIdAndType($data['id'], 'GTIN13');
        if ($eanGtin13) {
            $this->repo->insertCode((int)$data['id'], 'GTIN13', $eanGtin13);
        }

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

    public function findById(int $id): array|false
    {
        return $this->repo->findById($id);
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

    public function getAlleKategorien(): array
    {
        return $this->kategorieRepo->findAll();
    }

    public function saveKategorien(int $artikelId, array $kategorieIds): void
    {
        $this->kategorieRepo->updateArtikelKategoriezuweisungen($artikelId, $kategorieIds);
        Logger::log('artikel.kategorien_aktualisieren', 'artikel', $artikelId, ['kategorie_ids' => $kategorieIds]);
    }

    public function getKategorienFuerArtikel(int $artikelId): array
    {
        return $this->kategorieRepo->findByArtikelId($artikelId);
    }

    public function getAllArtikelTypen(): array
    {
        return $this->repo->findAllArtikelTypen();
    }

    public function getCodesByArtikelId(int $artikelId): array
    {
        return $this->repo->findCodesByArtikelId($artikelId);
    }

    // Hilfsmethoden für Dropdown-Daten — bis eigene Services existieren
    public function getAllHersteller(): array
    {
        return $this->repo->findAllHersteller();
    }

    public function getAllSteuerklassen(): array
    {
        return $this->repo->findAllSteuerklassen();
    }
}
