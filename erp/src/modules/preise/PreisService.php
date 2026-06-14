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
