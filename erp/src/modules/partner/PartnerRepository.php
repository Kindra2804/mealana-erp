<?php

require_once __DIR__ . '/../../core/Database.php';

class PartnerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Partner
    // -------------------------------------------------------------------------

    public function findAll(array $filter = []): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if (!empty($filter['typ'])) {
            $conditions[] = 'p.typ = :typ';
            $params['typ'] = $filter['typ'];
        }

        if (isset($filter['aktiv'])) {
            $conditions[] = 'p.aktiv = :aktiv';
            $params['aktiv'] = $filter['aktiv'];
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->db->prepare("
            SELECT p.*,
                   COUNT(m.id)                              AS anzahl_faecher,
                   SUM(CASE WHEN m.aktiv = 1 THEN 1 END)   AS aktive_faecher
            FROM   partner p
            LEFT JOIN mietfaecher m ON m.partner_id = p.id
            $where
            GROUP BY p.id
            ORDER BY p.name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM partner WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function insert(array $data): int
    {
        $data['email']                = $data['email']                ?? null;
        $data['telefon']              = $data['telefon']              ?? null;
        $data['iban']                 = $data['iban']                 ?? null;
        $data['uid_nummer']           = $data['uid_nummer']           ?? null;
        $data['zvr_nummer']           = $data['zvr_nummer']           ?? null;
        $data['kleinunternehmer']     = $data['kleinunternehmer']     ?? 0;
        $data['provisions_satz']      = $data['provisions_satz']      ?? 0.00;
        $data['abrechnungs_modus']    = $data['abrechnungs_modus']    ?? 'getrennt';
        $data['abrechnungs_beleg_typ']= $data['abrechnungs_beleg_typ']?? 'gutschrift';
        $data['notiz']                = $data['notiz']                ?? null;
        $data['aktiv']                = $data['aktiv']                ?? 1;

        $stmt = $this->db->prepare('
            INSERT INTO partner (
                name, typ, email, telefon, iban,
                uid_nummer, zvr_nummer, kleinunternehmer,
                provisions_satz, abrechnungs_modus, abrechnungs_beleg_typ,
                notiz, aktiv
            ) VALUES (
                :name, :typ, :email, :telefon, :iban,
                :uid_nummer, :zvr_nummer, :kleinunternehmer,
                :provisions_satz, :abrechnungs_modus, :abrechnungs_beleg_typ,
                :notiz, :aktiv
            )
        ');

        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE partner SET
                name                  = :name,
                typ                   = :typ,
                email                 = :email,
                telefon               = :telefon,
                iban                  = :iban,
                uid_nummer            = :uid_nummer,
                zvr_nummer            = :zvr_nummer,
                kleinunternehmer      = :kleinunternehmer,
                provisions_satz       = :provisions_satz,
                abrechnungs_modus     = :abrechnungs_modus,
                abrechnungs_beleg_typ = :abrechnungs_beleg_typ,
                notiz                 = :notiz
            WHERE id = :id
        ');

        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    public function setAktiv(int $id, int $aktiv): bool
    {
        $stmt = $this->db->prepare('UPDATE partner SET aktiv = :aktiv WHERE id = :id');
        $stmt->execute(['aktiv' => $aktiv, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Mietfächer
    // -------------------------------------------------------------------------

    public function findMietfaecherByPartner(int $partnerId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM mietfaecher
            WHERE  partner_id = :partner_id
            ORDER BY fach_bezeichnung
        ');
        $stmt->execute(['partner_id' => $partnerId]);
        return $stmt->fetchAll();
    }

    public function insertMietfach(array $data): int
    {
        $data['mwst_satz'] = $data['mwst_satz'] ?? 20.00;
        $data['mietende']  = $data['mietende']  ?: null;
        $data['aktiv']     = $data['aktiv']      ?? 1;

        $stmt = $this->db->prepare('
            INSERT INTO mietfaecher (
                partner_id, fach_bezeichnung, mietbetrag_monatlich,
                mwst_satz, mietbeginn, mietende, aktiv
            ) VALUES (
                :partner_id, :fach_bezeichnung, :mietbetrag_monatlich,
                :mwst_satz, :mietbeginn, :mietende, :aktiv
            )
        ');

        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function updateMietfach(array $data): bool
    {
        $data['mietende'] = $data['mietende'] ?: null;

        $stmt = $this->db->prepare('
            UPDATE mietfaecher SET
                fach_bezeichnung      = :fach_bezeichnung,
                mietbetrag_monatlich  = :mietbetrag_monatlich,
                mwst_satz             = :mwst_satz,
                mietbeginn            = :mietbeginn,
                mietende              = :mietende,
                aktiv                 = :aktiv
            WHERE id = :id
        ');

        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }
}
