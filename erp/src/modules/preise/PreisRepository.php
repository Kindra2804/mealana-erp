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

    public function findAktionenFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                pa.id,
                pa.name,
                pa.typ,
                pa.gueltig_ab,
                pa.gueltig_bis,
                pa.aktiv,
                pap.brutto_vk,
                pap.netto_vk,
                pap.kundengruppen_id,
                k.name AS kg_name
            FROM preis_aktionen_positionen pap
            JOIN preis_aktionen pa ON pa.id = pap.aktion_id
            LEFT JOIN kundengruppen k ON k.id = pap.kundengruppen_id
            WHERE pap.artikel_id = :artikel_id
            ORDER BY pa.aktiv DESC, pa.gueltig_ab DESC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function deleteKundengruppenPreis(int $artikelId, int $kgId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM artikel_preise WHERE artikel_id = :artikel_id AND kundengruppen_id = :kundengruppen_id");
        return $stmt->execute(['artikel_id' => $artikelId, 'kundengruppen_id' => $kgId]);
    }

    public function findStaffelpreise(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                sp.id,
                sp.kundengruppen_id,
                k.name AS kundengruppen_name,
                sp.menge_ab,
                sp.brutto_vk,
                sp.netto_vk
            FROM artikel_staffelpreise sp
            JOIN kundengruppen k ON k.id = sp.kundengruppen_id
            WHERE sp.artikel_id = :artikel_id
            ORDER BY sp.kundengruppen_id, sp.menge_ab
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function insertStaffelpreis(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO artikel_staffelpreise (artikel_id, kundengruppen_id, menge_ab, brutto_vk, netto_vk)
            VALUES (:artikel_id, :kundengruppen_id, :menge_ab, :brutto_vk, :netto_vk)
        ");
        return $stmt->execute([
            'artikel_id'       => $data['artikel_id'],
            'kundengruppen_id' => $data['kundengruppen_id'],
            'menge_ab'         => $data['menge_ab'],
            'brutto_vk'        => $data['brutto_vk'],
            'netto_vk'         => $data['netto_vk'],
        ]);
    }

    public function updateStaffelpreis(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE artikel_staffelpreise SET
                kundengruppen_id = :kundengruppen_id,
                menge_ab         = :menge_ab,
                brutto_vk        = :brutto_vk,
                netto_vk         = :netto_vk
            WHERE id = :id AND artikel_id = :artikel_id
        ");
        return $stmt->execute([
            'kundengruppen_id' => $data['kundengruppen_id'],
            'menge_ab'         => $data['menge_ab'],
            'brutto_vk'        => $data['brutto_vk'],
            'netto_vk'         => $data['netto_vk'],
            'id'               => $data['id'],
            'artikel_id'       => $data['artikel_id'],
        ]);
    }

    public function deleteStaffelpreis(int $id, int $artikelId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM artikel_staffelpreise WHERE id = :id AND artikel_id = :artikel_id");
        return $stmt->execute(['id' => $id, 'artikel_id' => $artikelId]);
    }
}
