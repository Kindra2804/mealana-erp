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
                k.ist_standard,
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
                a.id AS aktion_id,
                aap.kundengruppen_id,
                aap.brutto_vk,
                aap.netto_vk,
                a.name AS aktion_name,
                a.beschreibung,
                a.gestartet,
                ak.gueltig_ab,
                ak.gueltig_bis,
                k.name AS kundengruppen_name,
                k.typ,
                va.name AS achsen_name,
                kat.name AS kategorie_name
            FROM aktionen_artikel_preise aap
            JOIN aktionen a ON a.id = aap.aktion_id
            JOIN aktionen_kategorien ak ON ak.aktion_id = aap.aktion_id
            JOIN kundengruppen k ON k.id = aap.kundengruppen_id
            LEFT JOIN varianten_achsen va ON va.id = aap.sub_achse_id
            LEFT JOIN kategorien kat ON kat.id = ak.kategorie_id
            WHERE aap.artikel_id = :artikel_id
            ORDER BY a.gestartet DESC, ak.gueltig_ab DESC
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

    public function findSaleOverride(int $artikelId, int $kgId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT brutto_vk, netto_vk, gueltig_bis
            FROM preis_aktionen_positionen
            WHERE artikel_id = :artikel_id
            AND kundengruppen_id = :kundengruppen_id
            AND (gueltig_ab IS NULL OR gueltig_ab <= NOW())
            AND (gueltig_bis IS NULL OR gueltig_bis >= NOW())
            LIMIT 1
        ");

        $stmt->execute([
            'artikel_id'       => $artikelId,
            'kundengruppen_id' => $kgId,
        ]);

        return $stmt->fetch();
    }


    public function findAktionsPreis(int $artikelId, int $kgId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT aap.brutto_vk, aap.netto_vk, a.name AS aktion_name, ak.gueltig_bis
            FROM aktionen_artikel_preise aap
            JOIN aktionen a ON a.id = aap.aktion_id
            JOIN aktionen_kategorien ak ON ak.aktion_id = aap.aktion_id
            WHERE aap.artikel_id = :artikel_id
            AND aap.kundengruppen_id = :kundengruppen_id
            AND a.gestartet = 1
            AND (ak.gueltig_ab <= CURDATE())
            AND (ak.gueltig_bis >= CURDATE())
            LIMIT 1
        ");

        $stmt->execute([
            'artikel_id'       => $artikelId,
            'kundengruppen_id' => $kgId,
        ]);

        return $stmt->fetch();
    }

    public function findKundengruppenPreisFuerKg(int $artikelId, int $kgId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT ap.brutto_vk, ap.netto_vk, k.name
            FROM artikel_preise ap
            JOIN kundengruppen k ON ap.kundengruppen_id = k.id
            WHERE ap.artikel_id = :artikel_id
            AND ap.kundengruppen_id = :kundengruppen_id
            AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= CURDATE())
            AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= NOW())
            LIMIT 1
        ");

        $stmt->execute([
            'artikel_id'       => $artikelId,
            'kundengruppen_id' => $kgId,
        ]);

        return $stmt->fetch();
    }

    public function findStandardPreis(int $artikelId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT ap.brutto_vk, ap.netto_vk, k.name
            FROM artikel_preise ap
            JOIN kundengruppen k ON ap.kundengruppen_id = k.id
            WHERE ap.artikel_id = :artikel_id
            AND k.ist_standard = 1
            LIMIT 1
        ");

        $stmt->execute([
            'artikel_id'       => $artikelId,
        ]);

        return $stmt->fetch();
    }

    // ── SALE-Overrides ────────────────────────────────────────────────

    public function findSaleOverridesFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                pap.id,
                pap.kundengruppen_id,
                k.name AS kg_name,
                pap.brutto_vk,
                pap.netto_vk,
                pap.preis_vorher_brutto,
                pap.gueltig_ab,
                pap.gueltig_bis,
                pap.bis_lagerstand_null,
                (
                    (pap.gueltig_ab IS NULL OR pap.gueltig_ab <= NOW())
                    AND (pap.gueltig_bis IS NULL OR pap.gueltig_bis >= NOW())
                ) AS ist_aktiv
            FROM preis_aktionen_positionen pap
            LEFT JOIN kundengruppen k ON k.id = pap.kundengruppen_id
            WHERE pap.artikel_id = :artikel_id
            ORDER BY pap.gueltig_ab DESC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function upsertSaleOverride(array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare("
                UPDATE preis_aktionen_positionen SET
                    kundengruppen_id    = :kg_id,
                    brutto_vk           = :brutto_vk,
                    netto_vk            = :netto_vk,
                    preis_vorher_brutto = :preis_vorher_brutto,
                    gueltig_ab          = :gueltig_ab,
                    gueltig_bis         = :gueltig_bis,
                    bis_lagerstand_null = :bis_lagerstand_null
                WHERE id = :id AND artikel_id = :artikel_id
            ");
            $stmt->execute([
                'kg_id'               => $data['kundengruppen_id'] ?: null,
                'brutto_vk'           => $data['brutto_vk'],
                'netto_vk'            => $data['netto_vk'],
                'preis_vorher_brutto' => $data['preis_vorher_brutto'] ?: null,
                'gueltig_ab'          => $data['gueltig_ab'] ?: null,
                'gueltig_bis'         => $data['gueltig_bis'] ?: null,
                'bis_lagerstand_null' => $data['bis_lagerstand_null'] ? 1 : 0,
                'id'                  => $data['id'],
                'artikel_id'          => $data['artikel_id'],
            ]);
            return (int)$data['id'];
        }
        $stmt = $this->db->prepare("
            INSERT INTO preis_aktionen_positionen
                (artikel_id, kundengruppen_id, brutto_vk, netto_vk, preis_vorher_brutto, gueltig_ab, gueltig_bis, bis_lagerstand_null)
            VALUES (:artikel_id, :kg_id, :brutto_vk, :netto_vk, :preis_vorher_brutto, :gueltig_ab, :gueltig_bis, :bis_lagerstand_null)
        ");
        $stmt->execute([
            'artikel_id'          => $data['artikel_id'],
            'kg_id'               => $data['kundengruppen_id'] ?: null,
            'brutto_vk'           => $data['brutto_vk'],
            'netto_vk'            => $data['netto_vk'],
            'preis_vorher_brutto' => $data['preis_vorher_brutto'] ?: null,
            'gueltig_ab'          => $data['gueltig_ab'] ?: null,
            'gueltig_bis'         => $data['gueltig_bis'] ?: null,
            'bis_lagerstand_null' => $data['bis_lagerstand_null'] ? 1 : 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function deleteSaleOverride(int $id, int $artikelId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM preis_aktionen_positionen WHERE id = :id AND artikel_id = :artikel_id");
        return $stmt->execute(['id' => $id, 'artikel_id' => $artikelId]);
    }
}
