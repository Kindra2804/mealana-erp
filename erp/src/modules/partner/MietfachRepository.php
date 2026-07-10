<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * MietfachRepository – CRUD für physische Mietfächer und ihre Mietverträge
 *
 * Mietfächer sind physische Ausstellungsflächen im Laden (z.B. Regal, Vitrine).
 * Jedes Fach hat Stammdaten (Bezeichnung, Maße, Standardpreis) und eine
 * History von Mietverträgen (mietfach_mietvertraege).
 *
 * Ein Fach ist "belegt" wenn ein Vertrag existiert mit:
 *   mietende IS NULL (unbefristeter Vertrag) ODER mietende >= CURDATE()
 *
 * findAllMitStatus() verwendet eine Subquery für den aktuellen Vertrag
 * statt einem einfachen LEFT JOIN — damit es bei mehreren überlappenden
 * Verträgen (Fehlerfall) keine duplizierten Zeilen gibt.
 */
class MietfachRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Physische Fächer
    // -------------------------------------------------------------------------

    public function findAll(): array
    {
        // Gibt alle Fächer zurück, jeweils mit aktivem Mietvertrag (falls vorhanden)
        $stmt = $this->db->query("
            SELECT f.*,
                   v.id              AS vertrag_id,
                   v.partner_id      AS mieter_id,
                   v.mietbetrag_monatlich,
                   v.mwst_satz,
                   v.mietbeginn,
                   v.mietende,
                   p.name            AS mieter_name
            FROM   mietfaecher f
            LEFT JOIN mietfach_mietvertraege v
                   ON  v.mietfach_id = f.id
                   AND v.mietende   IS NULL   -- aktiver Vertrag (unbefristet)
                   OR  (v.mietfach_id = f.id AND v.mietende >= CURDATE())
            LEFT JOIN partner p ON p.id = v.partner_id
            GROUP BY f.id
            ORDER BY f.fach_bezeichnung
        ");
        return $stmt->fetchAll();
    }

    /**
     * Sauberere Variante: verwendet Subquery für aktuellen Vertrag
     * um doppelte Zeilen bei Datenfehlern zu vermeiden.
     * Diese Methode sollte in der UI gegenüber findAll() bevorzugt werden.
     */
    public function findAllMitStatus(): array
    {
        $stmt = $this->db->query("
            SELECT f.*,
                   v.id                   AS vertrag_id,
                   v.partner_id           AS mieter_id,
                   v.mietbetrag_monatlich AS vertrag_preis,
                   v.mwst_satz            AS vertrag_mwst,
                   v.mietbeginn,
                   v.mietende,
                   p.name                 AS mieter_name
            FROM   mietfaecher f
            LEFT JOIN mietfach_mietvertraege v ON v.id = (
                SELECT id FROM mietfach_mietvertraege
                WHERE  mietfach_id = f.id
                  AND  (mietende IS NULL OR mietende >= CURDATE())
                ORDER BY mietbeginn DESC
                LIMIT  1
            )
            LEFT JOIN partner p ON p.id = v.partner_id
            ORDER BY f.fach_bezeichnung
        ");
        return $stmt->fetchAll();
    }

    /** Gibt ein Fach anhand ID zurück, oder false wenn nicht gefunden. */
    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM mietfaecher WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Gibt alle aktiven und aktuell unbelegten Fächer zurück.
     * Wird für die Dropdown-Auswahl beim Vertrag starten verwendet.
     */
    public function findFreie(): array
    {
        $stmt = $this->db->query("
            SELECT f.*
            FROM   mietfaecher f
            WHERE  f.aktiv = 1
              AND  NOT EXISTS (
                  SELECT 1 FROM mietfach_mietvertraege v
                  WHERE  v.mietfach_id = f.id
                    AND  (v.mietende IS NULL OR v.mietende >= CURDATE())
              )
            ORDER BY f.fach_bezeichnung
        ");
        return $stmt->fetchAll();
    }

    /**
     * Legt ein neues Mietfach an und gibt die neue ID zurück.
     * array_intersect_key() filtert überzählige Keys raus (z.B. falls Neu- und
     * Bearbeiten-Formular jemals zu einem gemeinsamen Modal zusammengelegt werden
     * und ein verstecktes id-Feld mitschicken) — sonst wirft PDO bei einem extra
     * Key ohne passenden Platzhalter SQLSTATE[HY093], siehe [[bug_hersteller_modal_insert]].
     */
    public function insert(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO mietfaecher (
                fach_bezeichnung, ort_beschreibung,
                laenge_cm, breite_cm, hoehe_cm,
                standard_preis, notiz, aktiv
            ) VALUES (
                :fach_bezeichnung, :ort_beschreibung,
                :laenge_cm, :breite_cm, :hoehe_cm,
                :standard_preis, :notiz, :aktiv
            )
        ');
        $erlaubteKeys = [
            'fach_bezeichnung', 'ort_beschreibung',
            'laenge_cm', 'breite_cm', 'hoehe_cm',
            'standard_preis', 'notiz', 'aktiv',
        ];
        $stmt->execute(array_intersect_key($data, array_flip($erlaubteKeys)));
        return (int) $this->db->lastInsertId();
    }

    /** Aktualisiert alle Felder eines Mietfachs. */
    public function update(array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE mietfaecher SET
                fach_bezeichnung  = :fach_bezeichnung,
                ort_beschreibung  = :ort_beschreibung,
                laenge_cm         = :laenge_cm,
                breite_cm         = :breite_cm,
                hoehe_cm          = :hoehe_cm,
                standard_preis    = :standard_preis,
                notiz             = :notiz,
                aktiv             = :aktiv
            WHERE id = :id
        ');
        $erlaubteKeys = [
            'id', 'fach_bezeichnung', 'ort_beschreibung',
            'laenge_cm', 'breite_cm', 'hoehe_cm',
            'standard_preis', 'notiz', 'aktiv',
        ];
        $stmt->execute(array_intersect_key($data, array_flip($erlaubteKeys)));
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Mietverträge
    // -------------------------------------------------------------------------

    /**
     * Gibt den aktuell aktiven Mietvertrag für ein Fach zurück.
     * Ein Vertrag gilt als aktiv wenn mietende IS NULL oder mietende >= heute.
     * Gibt false zurück wenn das Fach aktuell unbelegt ist.
     */
    public function findAktuellerVertrag(int $fachId): array|false
    {
        $stmt = $this->db->prepare('
            SELECT v.*, p.name AS partner_name
            FROM   mietfach_mietvertraege v
            JOIN   partner p ON p.id = v.partner_id
            WHERE  v.mietfach_id = :fach_id
              AND  (v.mietende IS NULL OR v.mietende >= CURDATE())
            ORDER BY v.mietbeginn DESC
            LIMIT  1
        ');
        $stmt->execute(['fach_id' => $fachId]);
        return $stmt->fetch();
    }

    /**
     * Gibt die vollständige Vertragshistory eines Fachs zurück (neueste zuerst).
     * Enthält auch abgelaufene Verträge für die Historieanzeige.
     */
    public function findVertraege(int $fachId): array
    {
        $stmt = $this->db->prepare('
            SELECT v.*, p.name AS partner_name
            FROM   mietfach_mietvertraege v
            JOIN   partner p ON p.id = v.partner_id
            WHERE  v.mietfach_id = :fach_id
            ORDER BY v.mietbeginn DESC
        ');
        $stmt->execute(['fach_id' => $fachId]);
        return $stmt->fetchAll();
    }

    /**
     * Gibt alle aktuell belegten Fächer eines Partners zurück.
     * Wird auf der Partner-Detailseite als Übersicht angezeigt.
     */
    public function findFaecherByPartner(int $partnerId): array
    {
        $stmt = $this->db->prepare('
            SELECT f.fach_bezeichnung, f.ort_beschreibung,
                   v.mietbetrag_monatlich, v.mietbeginn, v.mietende
            FROM   mietfach_mietvertraege v
            JOIN   mietfaecher f ON f.id = v.mietfach_id
            WHERE  v.partner_id = :partner_id
              AND  (v.mietende IS NULL OR v.mietende >= CURDATE())
            ORDER BY f.fach_bezeichnung
        ');
        $stmt->execute(['partner_id' => $partnerId]);
        return $stmt->fetchAll();
    }

    /** Legt einen neuen Mietvertrag an und gibt die neue ID zurück. */
    public function insertVertrag(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO mietfach_mietvertraege (
                mietfach_id, partner_id, mietbetrag_monatlich,
                mwst_satz, mietbeginn, mietende, notiz
            ) VALUES (
                :mietfach_id, :partner_id, :mietbetrag_monatlich,
                :mwst_satz, :mietbeginn, :mietende, :notiz
            )
        ');
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Beendet einen Mietvertrag durch Setzen des mietende-Datums.
     * Gibt true zurück wenn der Datensatz aktualisiert wurde.
     */
    public function vertragBeenden(int $vertragId, string $mietende): bool
    {
        $stmt = $this->db->prepare('
            UPDATE mietfach_mietvertraege SET mietende = :mietende WHERE id = :id
        ');
        $stmt->execute(['mietende' => $mietende, 'id' => $vertragId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Prüft ob ein Fach aktuell belegt ist (aktiver Vertrag vorhanden).
     * Verhindert, dass für dasselbe Fach zwei gleichzeitige Verträge angelegt werden.
     */
    public function isFachBelegt(int $fachId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM mietfach_mietvertraege
            WHERE  mietfach_id = :fach_id
              AND  (mietende IS NULL OR mietende >= CURDATE())
        ');
        $stmt->execute(['fach_id' => $fachId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
