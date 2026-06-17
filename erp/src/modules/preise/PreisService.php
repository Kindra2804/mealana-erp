<?php
require_once __DIR__ . '/PreisRepository.php';
require_once __DIR__ . '/../../core/Logger.php';

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
        Logger::log('preis.kundengruppe.loeschen', 'artikel_preise', $artikelId, ['kg_id' => $kgId]);
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
            Logger::log('preis.staffel.bearbeiten', 'artikel_staffelpreise', $data['artikel_id'], ['kg_id' => $data['kundengruppen_id'], 'menge_ab' => $data['menge_ab']]);
        } else {
            $this->repo->insertStaffelpreis($data);
            Logger::log('preis.staffel.anlegen', 'artikel_staffelpreise', $data['artikel_id'], ['kg_id' => $data['kundengruppen_id'], 'menge_ab' => $data['menge_ab']]);
        }
        return ['erfolg' => true];
    }

    public function loescheStaffelpreis(int $id, int $artikelId): array
    {
        $this->repo->deleteStaffelpreis($id, $artikelId);
        Logger::log('preis.staffel.loeschen', 'artikel_staffelpreise', $artikelId, ['staffelpreis_id' => $id]);
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
        Logger::log('preis.kundengruppe.speichern', 'artikel_preise', $data['artikel_id'], ['kg_id' => $data['kundengruppen_id']]);
        return ['erfolg' => true];
    }
}
