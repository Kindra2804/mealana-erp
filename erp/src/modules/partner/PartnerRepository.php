<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * PartnerRepository – CRUD für Partner-Stammdaten
 *
 * Partner sind externe Betriebe oder Personen, die mit MeaLana kooperieren.
 * Typen: "mietfach" (mieten Ausstellungsfach), "kommission" (verkaufen auf Provision),
 *        "spende" (spenden Waren), "beides" (Kommission + Spende).
 *
 * Die aktuelle Anzahl belegter Mietfächer wird per LEFT JOIN
 * aus mietfach_mietvertraege berechnet (aktuelle Verträge = mietende IS NULL
 * oder mietende >= heute).
 *
 * Löschen = kein echtes DELETE; stattdessen aktiv = 0 über setAktiv().
 */
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

    /**
     * Gibt alle Partner zurück mit aktueller Anzahl belegter Mietfächer.
     *
     * @param array $filter Optionale Filter: ['typ' => '...', 'aktiv' => 0|1]
     */
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
                   COUNT(v.id) AS aktuelle_faecher
            FROM   partner p
            LEFT JOIN mietfach_mietvertraege v
                   ON  v.partner_id  = p.id
                   AND (v.mietende IS NULL OR v.mietende >= CURDATE())
            $where
            GROUP BY p.id
            ORDER BY p.name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Gibt einen Partner anhand ID zurück, oder false wenn nicht gefunden. */
    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM partner WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Legt einen neuen Partner an und gibt die neue ID zurück.
     * Fehlende optionale Felder werden mit NULL / Standardwerten befüllt.
     */
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

        $erlaubteKeys = [
            'name', 'typ', 'email', 'telefon', 'iban',
            'uid_nummer', 'zvr_nummer', 'kleinunternehmer',
            'provisions_satz', 'abrechnungs_modus', 'abrechnungs_beleg_typ',
            'notiz', 'aktiv',
        ];
        $stmt->execute(array_intersect_key($data, array_flip($erlaubteKeys)));
        return (int) $this->db->lastInsertId();
    }

    /** Aktualisiert alle editierbaren Felder eines Partners. */
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

        $erlaubteKeys = [
            'id', 'name', 'typ', 'email', 'telefon', 'iban',
            'uid_nummer', 'zvr_nummer', 'kleinunternehmer',
            'provisions_satz', 'abrechnungs_modus', 'abrechnungs_beleg_typ',
            'notiz',
        ];
        $stmt->execute(array_intersect_key($data, array_flip($erlaubteKeys)));
        return $stmt->rowCount() > 0;
    }

    /** Setzt den Aktiv-Status eines Partners (1 = aktiv, 0 = inaktiv). */
    public function setAktiv(int $id, int $aktiv): bool
    {
        $stmt = $this->db->prepare('UPDATE partner SET aktiv = :aktiv WHERE id = :id');
        $stmt->execute(['aktiv' => $aktiv, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

}
