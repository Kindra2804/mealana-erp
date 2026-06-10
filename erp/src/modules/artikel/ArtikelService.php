<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/ArtikelRepository.php';
require_once __DIR__ . '/KategorieRepository.php';
require_once __DIR__ . '/EinheitenRepository.php';

class ArtikelService
{
    private ArtikelRepository $repo;
    private KategorieRepository $kategorieRepo;
    private EinheitenRepository $einheitenRepo;

    public function __construct()
    {
        $this->repo = new ArtikelRepository();
        $this->kategorieRepo = new KategorieRepository();
        $this->einheitenRepo = new EinheitenRepository();
    }

    public function getAllEinheiten(): array
    {
        return $this->einheitenRepo->findAll();
    }

    public function save(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $bruttoVk  = $data['brutto_vk']  ?? null;
        $nettoVk   = $data['netto_vk']   ?? null;
        $eanGtin13 = $data['ean_gtin13'] ?? null;
        unset($data['brutto_vk'], $data['netto_vk'], $data['ean_gtin13']);

        $id = $this->repo->insert($data);

        if ($bruttoVk && $nettoVk) {
            $this->repo->insertPreis($id, (float) $bruttoVk, (float) $nettoVk);
        }
        if ($eanGtin13) {
            $this->repo->insertCode($id, 'GTIN13', $eanGtin13);
        }

        Logger::log('artikel.anlegen', 'artikel', $id, ['name' => $data['name']]);
        return ['erfolg' => true, 'id' => $id];
    }

    public function update(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $bruttoVk  = $data['brutto_vk']  ?? null;
        $nettoVk   = $data['netto_vk']   ?? null;
        $eanGtin13 = $data['ean_gtin13'] ?? null;
        unset($data['brutto_vk'], $data['netto_vk'], $data['ean_gtin13']);

        $this->repo->update($data);

        $this->repo->deleteCodesByArtikelIdAndType($data['id'], 'GTIN13');
        if ($eanGtin13) {
            $this->repo->insertCode((int) $data['id'], 'GTIN13', $eanGtin13);
        }
        if ($bruttoVk && $nettoVk) {
            $this->repo->updatePreis((int) $data['id'], (float) $bruttoVk, (float) $nettoVk);
        }

        Logger::log('artikel.bearbeiten', 'artikel', $data['id'], ['name' => $data['name']]);
        return ['erfolg' => true];
    }

    // Speichert ein neues Kind (Variante) eines Vater-Artikels
    public function saveKind(array $data): array
    {
        $fehler = $this->validiereKind($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        // Vater laden – Kind erbt Felder
        $vater = $this->repo->findById((int) $data['vaterartikel_id']);
        if (!$vater) {
            return ['erfolg' => false, 'fehler' => ['Vater-Artikel nicht gefunden']];
        }

        $bruttoVk = $data['brutto_vk'] ?? null;
        $gtin     = $data['gtin']      ?? null;
        unset($data['brutto_vk'], $data['gtin']);

        $kindData = [
            'vaterartikel_id'      => (int) $data['vaterartikel_id'],
            'hat_eigenen_lagerstand' => 1,
            'artikelnummer'        => $data['artikelnummer'],
            'artikeltyp'           => $vater['artikeltyp'],
            'hersteller_id'        => $vater['hersteller_id'],
            'steuerklasse_id'      => $vater['steuerklasse_id'],
            'einheit_id'           => $vater['einheit_id'],
            'name'                 => $vater['name'] . ' – ' . ($data['farbe_name'] ?? ''),
            'farbe_name'           => $data['farbe_name'] ?? null,
            'farbe_hex'            => $data['farbe_hex'] ?? null,
            'inhalt_menge'         => $vater['inhalt_menge'],
            'inhalt_einheit'       => $vater['inhalt_einheit'],
            'gewicht_artikel'      => $vater['gewicht_artikel'],
            'gewicht_versand'      => $vater['gewicht_versand'],
            'herkunftsland'        => $vater['herkunftsland'],
            'taric_code'           => $vater['taric_code'],
            'varianten_darstellung'  => $vater['varianten_darstellung'],
            'grundpreis_bezugsmenge' => $vater['grundpreis_bezugsmenge'],
            'grundpreis_anzeigen'    => $vater['grundpreis_anzeigen'],
            'charge_pflicht'       => $vater['charge_pflicht'],
            'ist_auslaufartikel'   => $data['ist_auslaufartikel'] ?? 0,
            'aktiv'                => $data['aktiv'] ?? 1,
            'kurzbeschreibung'     => null,
            'beschreibung'         => null,
            'technische_details'   => null,
            'beschreibung_intern'  => null,
            'meta_titel'           => null,
            'meta_description'     => null,
            'url_slug'             => null,
        ];

        $id = $this->repo->insert($kindData);

        if ($bruttoVk) {
            $nettoVk = round((float) $bruttoVk / (1 + $vater['steuersatz'] / 100), 4);
            $this->repo->insertPreis($id, (float) $bruttoVk, $nettoVk);
        }
        if ($gtin) {
            $this->repo->insertCode($id, 'GTIN13', $gtin);
        }

        Logger::log('artikel.kind_anlegen', 'artikel', $id, ['farbe' => $data['farbe_name'] ?? '']);
        return ['erfolg' => true, 'id' => $id];
    }

    // Aktualisiert ein Kind (Variante)
    public function kindUpdate(array $data): array
    {
        $fehler = $this->validiereKind($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $bruttoVk = $data['brutto_vk'] ?? null;
        $gtin     = $data['gtin']      ?? null;

        $this->repo->updateKind([
            'id'               => (int) $data['id'],
            'artikelnummer'    => $data['artikelnummer'],
            'farbe_name'       => $data['farbe_name'] ?? null,
            'farbe_hex'        => $data['farbe_hex'] ?? null,
            'aktiv'            => $data['aktiv'] ?? 1,
            'ist_auslaufartikel' => $data['ist_auslaufartikel'] ?? 0,
        ]);

        $this->repo->deleteCodesByArtikelIdAndType((int) $data['id'], 'GTIN13');
        if ($gtin) {
            $this->repo->insertCode((int) $data['id'], 'GTIN13', $gtin);
        }

        if ($bruttoVk) {
            $kind = $this->repo->findById((int) $data['id']);
            $vater = $this->repo->findById((int) $data['vaterartikel_id']);
            if ($kind && $vater) {
                $nettoVk = round((float) $bruttoVk / (1 + $vater['steuersatz'] / 100), 4);
                $this->repo->updatePreis((int) $data['id'], (float) $bruttoVk, $nettoVk);
            }
        }

        Logger::log('artikel.kind_bearbeiten', 'artikel', $data['id'], ['farbe' => $data['farbe_name'] ?? '']);
        return ['erfolg' => true];
    }

    public function findById(int $id): array|false
    {
        return $this->repo->findById($id);
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

    public function getKinderFuerArtikel(int $id, bool $mitInaktiven = false): array
    {
        return $this->repo->findKinderByArtikelId($id, $mitInaktiven);
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

    public function createKategorie(string $name): array
    {
        $trimmed = trim($name);
        if (empty($trimmed)) {
            return ['erfolg' => false, 'fehler' => 'Name darf nicht leer sein'];
        }
        $id = $this->kategorieRepo->insert($trimmed);
        return $id
            ? ['erfolg' => true, 'id' => $id, 'name' => $trimmed]
            : ['erfolg' => false, 'fehler' => 'Fehler beim Speichern'];
    }

    public function getAllHersteller(): array
    {
        return $this->repo->findAllHersteller();
    }

    public function getAllSteuerklassen(): array
    {
        return $this->repo->findAllSteuerklassen();
    }

    public function getDetailArtikel(int $id): array|false
    {
        return $this->repo->findByIdMitPreisen($id);
    }

    public function getLieferantenFuerArtikel(int $artikelId): array
    {
        return $this->repo->getLieferantenFuerArtikel($artikelId);
    }

    private function validiere(array $data): array
    {
        $fehler = [];

        if (empty($data['artikelnummer'])) {
            $fehler[] = 'Artikelnummer ist Pflichtfeld';
        } else {
            $vorhanden = $this->repo->findByArtikelnummer($data['artikelnummer'], $data['id'] ?? null);
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

    private function validiereKind(array $data): array
    {
        $fehler = [];

        if (empty($data['artikelnummer'])) {
            $fehler[] = 'Artikelnummer ist Pflichtfeld';
        } else {
            $vorhanden = $this->repo->findByArtikelnummer($data['artikelnummer'], $data['id'] ?? null);
            if ($vorhanden !== false) {
                $fehler[] = 'Artikelnummer "' . $data['artikelnummer'] . '" existiert bereits!';
            }
        }
        if (empty($data['farbe_name'])) {
            $fehler[] = 'Name/Farbname ist Pflichtfeld';
        }
        if (empty($data['vaterartikel_id'])) {
            $fehler[] = 'Vater-Artikel-Zuordnung fehlt';
        }

        return $fehler;
    }

    public function kopiere(int $quell_id, array $kopierData): array
    {
        $quellArtikel = $this->repo->findById($quell_id);

        if (empty($quellArtikel)) {
            return ['erfolg' => false, 'fehler' => ['Artikel nicht gefunden']];
        }

        $vorhanden = $this->repo->findByArtikelnummer($kopierData['artikelnummer']);
        if ($vorhanden !== false) {
            return ['erfolg' => false, 'fehler' => ['Artikelnummer ' . $kopierData['artikelnummer'] . ' existiert bereits!']];
        }

        $neuerArtikel = $quellArtikel;  // alle Felder vom Original
        $neuerArtikel['artikelnummer'] = $kopierData['artikelnummer'];  // neue Nummer
        $neuerArtikel['name'] = $kopierData['name'];  // neuer Name
        $neuerArtikel['aktiv']         = 0;
        $neuerArtikel['url_slug']      = null;
        unset($neuerArtikel['id']);

        if ($kopierData['ueberverkauf'] === '0') {
            $neuerArtikel['ueberverkauf_erlaubt'] = 0;
        }

        $neuerArtikelGefiltert = array_intersect_key($neuerArtikel, array_flip([
            'vaterartikel_id',
            'hat_eigenen_lagerstand',
            'artikelnummer',
            'hersteller_id',
            'steuerklasse_id',
            'artikeltyp_id',
            'name',
            'farbe_name',
            'farbe_hex',
            'kurzbeschreibung',
            'beschreibung',
            'technische_details',
            'beschreibung_intern',
            'meta_titel',
            'meta_description',
            'url_slug',
            'einheit_id',
            'inhalt_menge',
            'inhalt_einheit',
            'gewicht_artikel',
            'gewicht_versand',
            'herkunftsland',
            'taric_code',
            'varianten_darstellung',
            'grundpreis_bezugsmenge',
            'grundpreis_anzeigen',
            'charge_pflicht',
            'ist_auslaufartikel',
            'ueberverkauf_erlaubt',
            'aktiv'
        ]));

        $neueId = $this->repo->insert($neuerArtikelGefiltert);

        if ($kopierData['preise']) {
            $this->repo->copyPreise($quell_id, $neueId);
        }

        if ($kopierData['kategorien']) {
            $this->repo->copyKategorien($quell_id, $neueId);
        }

        if ($kopierData['merkmale']) {
            $this->repo->copyMerkmale($quell_id, $neueId);
        }

        if ($kopierData['lieferanten']) {
            $this->repo->copyLieferanten($quell_id, $neueId);
        }


        if (!$neueId) {
            return ['erfolg' => false, 'fehler' => ['Fehler beim speichern ']];
        }

        return ['erfolg' => true, 'id' => $neueId];
    }
}
