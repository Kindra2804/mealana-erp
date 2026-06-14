<?php
require_once __DIR__ . '/PreisRepository.php';

class PreisService
{
    private PreisRepository $repo;

    public function __construct()
    {
        $this->repo = new PreisRepository();
    }

    public function getKundengruppenPreise(int $artikelId): array
    {
        return $this->repo->findKundengruppenPreise($artikelId);
    }

    public function getAktionenFuerArtikel(int $artikelId): array
    {
        return $this->repo->findAktionenFuerArtikel($artikelId);
    }

    public function loescheKundengruppenPreis(int $artikelId, int $kgId): array
    {
        $this->repo->deleteKundengruppenPreis($artikelId, $kgId);
        return ['erfolg' => true];
    }

    public function getStaffelpreise(int $artikelId): array
    {
        return $this->repo->findStaffelpreise($artikelId);
    }

    public function speichereStaffelpreis(array $data): array
    {
        $fehler = [];
        if (empty($data['artikel_id']))       $fehler[] = 'Artikel fehlt';
        if (empty($data['kundengruppen_id'])) $fehler[] = 'Kundengruppe fehlt';
        if (!isset($data['menge_ab']) || $data['menge_ab'] === '') $fehler[] = 'Menge fehlt';
        if (!isset($data['brutto_vk']) || $data['brutto_vk'] === '') $fehler[] = 'Brutto VK fehlt';
        if (!isset($data['netto_vk'])  || $data['netto_vk']  === '') $fehler[] = 'Netto VK fehlt';

        if (!empty($fehler)) return ['erfolg' => false, 'fehler' => implode(', ', $fehler)];

        if (!empty($data['id'])) {
            $this->repo->updateStaffelpreis($data);
        } else {
            $this->repo->insertStaffelpreis($data);
        }
        return ['erfolg' => true];
    }

    public function loescheStaffelpreis(int $id, int $artikelId): array
    {
        $this->repo->deleteStaffelpreis($id, $artikelId);
        return ['erfolg' => true];
    }

    public function speichereKundengruppenPreis(array $data): array
    {
        $fehler = [];
        if (empty($data['artikel_id']))       $fehler[] = 'Artikel fehlt';
        if (empty($data['kundengruppen_id'])) $fehler[] = 'Kundengruppe fehlt';
        if (!isset($data['brutto_vk']) || $data['brutto_vk'] === '') $fehler[] = 'Brutto VK fehlt';
        if (!isset($data['netto_vk'])  || $data['netto_vk']  === '') $fehler[] = 'Netto VK fehlt';

        if (!empty($fehler)) return ['erfolg' => false, 'fehler' => implode(', ', $fehler)];

        $this->repo->upsertKundengruppenPreis($data);
        return ['erfolg' => true];
    }
}
