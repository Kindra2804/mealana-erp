<?php
require_once __DIR__ . '/../../core/Database.php';

class PreisRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function upsertKundengruppenPreis(array $data): bool
    {
        $check = $this->db->prepare("
            SELECT id FROM artikel_preise
            WHERE artikel_id = :artikel_id AND kundengruppen_id = :kundengruppen_id
        ");
        $check->execute(['artikel_id' => $data['artikel_id'], 'kundengruppen_id' => $data['kundengruppen_id']]);
        $existing = $check->fetch();

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE artikel_preise SET
                    brutto_vk  = :brutto_vk,
                    netto_vk   = :netto_vk,
                    gueltig_ab = :gueltig_ab,
                    gueltig_bis = :gueltig_bis
                WHERE id = :id
            ");
            $stmt->execute([
                'brutto_vk'   => $data['brutto_vk'],
                'netto_vk'    => $data['netto_vk'],
                'gueltig_ab'  => $data['gueltig_ab'] ?: null,
                'gueltig_bis' => $data['gueltig_bis'] ?: null,
                'id'          => $existing['id'],
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO artikel_preise (artikel_id, kundengruppen_id, brutto_vk, netto_vk, gueltig_ab, gueltig_bis)
                VALUES (:artikel_id, :kundengruppen_id, :brutto_vk, :netto_vk, :gueltig_ab, :gueltig_bis)
            ");
            $stmt->execute([
                'artikel_id'       => $data['artikel_id'],
                'kundengruppen_id' => $data['kundengruppen_id'],
                'brutto_vk'        => $data['brutto_vk'],
                'netto_vk'         => $data['netto_vk'],
                'gueltig_ab'       => $data['gueltig_ab'] ?: null,
                'gueltig_bis'      => $data['gueltig_bis'] ?: null,
            ]);
        }
        return true;
    }

    public function findKundengruppenPreise(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                k.id,
                k.name,
                ap.brutto_vk,
                ap.netto_vk,
                ap.gueltig_ab,
                ap.gueltig_bis
            FROM kundengruppen k
            LEFT JOIN artikel_preise ap ON ap.kundengruppen_id = k.id AND ap.artikel_id = :artikel_id
            WHERE k.aktiv=1
            ORDER BY k.id
        ");

        $stmt->execute(['artikel_id' => $artikelId]);

        return $stmt->fetchAll();
    }
}
