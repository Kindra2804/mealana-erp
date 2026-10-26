<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * PartnerRepository – CRUD für Partner-Stammdaten
 *
 * Partner sind externe Betriebe oder Personen, die mit MeaLana kooperieren.
 * Verkaufsart (Spalte typ): "fremdrechnung" (Verkauf im Namen + auf Rechnung des Partners),
 *   "kommission" (eigener Umsatz, Partner fakturiert uns), "spende" (Durchlauf).
 * Ein Mietfach ist unabhängig von der Verkaufsart möglich.
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
    /** Editierbare Stammdaten-Felder (insert + update) */
    private const FELDER = [
        'name', 'strasse', 'plz', 'ort', 'land', 'typ', 'email', 'telefon', 'iban',
        'uid_nummer', 'zvr_nummer', 'kleinunternehmer', 'ku_text',
        'kunde_id', 'lieferant_id', 'provisions_satz', 'notiz',
    ];

    public function insert(array $data): int
    {
        $werte = [];
        foreach (self::FELDER as $feld) {
            $werte[$feld] = $data[$feld] ?? null;
        }
        $werte['land']             = $werte['land'] ?: 'AT';
        $werte['kleinunternehmer'] = $werte['kleinunternehmer'] ?? 0;
        $werte['provisions_satz']  = $werte['provisions_satz'] ?? 0.00;
        $werte['aktiv']            = $data['aktiv'] ?? 1;

        $spalten = array_keys($werte);
        $stmt = $this->db->prepare(
            'INSERT INTO partner (' . implode(', ', $spalten) . ') VALUES (:' . implode(', :', $spalten) . ')'
        );
        $stmt->execute($werte);
        return (int) $this->db->lastInsertId();
    }

    /** Aktualisiert alle editierbaren Felder eines Partners. */
    public function update(array $data): bool
    {
        $werte = ['id' => (int)$data['id']];
        $set   = [];
        foreach (self::FELDER as $feld) {
            $werte[$feld] = $data[$feld] ?? null;
            $set[]        = "$feld = :$feld";
        }
        $werte['land'] = $werte['land'] ?: 'AT';

        $stmt = $this->db->prepare('UPDATE partner SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($werte);

        // Verkaufsart am Artikel mitziehen (Kasse/Bon/Export lesen artikel.partner_modus)
        $this->db->prepare('UPDATE artikel SET partner_modus = :typ WHERE partner_id = :id')
            ->execute(['typ' => $werte['typ'], 'id' => $werte['id']]);

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
