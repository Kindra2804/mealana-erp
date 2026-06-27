<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Encryption.php';

/**
 * LieferantenRepository – CRUD für Lieferanten, Vertreter und Zugänge
 *
 * Lieferanten sind Großhändler/Marken von denen MeaLana einkauft.
 * Jeder Lieferant kann mehrere Vertreter und Zugänge (Händlerportale) haben.
 * Passwörter in lieferanten_zugaenge werden AES-256-GCM verschlüsselt gespeichert.
 * Löschen = Soft-Delete (aktiv = 0).
 */
class LieferantenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(bool $mitInaktiven = false): array
    {
        $where = $mitInaktiven ? '' : 'WHERE l.aktiv = 1';
        $stmt  = $this->db->query("
            SELECT l.id, l.name, l.land, l.website, l.email, l.telefon,
                   l.waehrung, l.zahlungsziel_tage, l.lieferzeit_tage,
                   l.aktiv, l.erstellt_am
            FROM lieferanten l
            $where
            ORDER BY l.name ASC
        ");
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT id, name, land, strasse, plz, ort, kundennummer, waehrung,
                   website, email, telefon,
                   zahlungsziel_tage, skonto_prozent, skonto_tage,
                   mindestbestellwert, lieferzeit_tage, lieferbedingung,
                   interne_notizen, aktiv, erstellt_am, geaendert_am
            FROM lieferanten
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Prüft ob ein Lieferant mit diesem Namen bereits existiert.
     * excludeId wird beim Update übergeben damit der Lieferant sich selbst nicht sperrt.
     */
    public function findByName(string $name, ?int $excludeId = null): array|false
    {
        $sql = "SELECT id FROM lieferanten WHERE name = :name";
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->db->prepare($sql);
        $params = ['name' => $name];
        if ($excludeId) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function findByIdMitVertretern(int $id): array|false
    {
        $lieferant = $this->findById($id);
        if ($lieferant === false) {
            return false;
        }
        $lieferant['vertreter'] = $this->findVertreterByLieferantId($id);
        return $lieferant;
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO lieferanten
                (name, land, strasse, plz, ort, kundennummer, waehrung,
                 website, email, telefon,
                 zahlungsziel_tage, skonto_prozent, skonto_tage,
                 mindestbestellwert, lieferzeit_tage, lieferbedingung,
                 interne_notizen, aktiv)
            VALUES
                (:name, :land, :strasse, :plz, :ort, :kundennummer, :waehrung,
                 :website, :email, :telefon,
                 :zahlungsziel_tage, :skonto_prozent, :skonto_tage,
                 :mindestbestellwert, :lieferzeit_tage, :lieferbedingung,
                 :interne_notizen, :aktiv)
        ");
        $stmt->execute($this->buildParams($data));
        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lieferanten SET
                name               = :name,
                land               = :land,
                strasse            = :strasse,
                plz                = :plz,
                ort                = :ort,
                kundennummer       = :kundennummer,
                waehrung           = :waehrung,
                website            = :website,
                email              = :email,
                telefon            = :telefon,
                zahlungsziel_tage  = :zahlungsziel_tage,
                skonto_prozent     = :skonto_prozent,
                skonto_tage        = :skonto_tage,
                mindestbestellwert = :mindestbestellwert,
                lieferzeit_tage    = :lieferzeit_tage,
                lieferbedingung    = :lieferbedingung,
                interne_notizen    = :interne_notizen,
                aktiv              = :aktiv,
                geaendert_am       = NOW()
            WHERE id = :id
        ");
        $params       = $this->buildParams($data);
        $params['id'] = $data['id'];
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE lieferanten SET aktiv = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function search(string $q): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, land, website, email, telefon, aktiv, erstellt_am
            FROM lieferanten
            WHERE (name LIKE :q OR land LIKE :q)
        ");
        $stmt->execute(['q' => '%' . $q . '%']);
        return $stmt->fetchAll();
    }

    private function buildParams(array $data): array
    {
        $n = fn($k) => (isset($data[$k]) && $data[$k] !== '') ? $data[$k] : null;
        return [
            'name'               => $data['name'],
            'land'               => $n('land'),
            'strasse'            => $n('strasse'),
            'plz'                => $n('plz'),
            'ort'                => $n('ort'),
            'kundennummer'       => $n('kundennummer'),
            'waehrung'           => $data['waehrung'] ?? 'EUR',
            'website'            => $n('website'),
            'email'              => $n('email'),
            'telefon'            => $n('telefon'),
            'zahlungsziel_tage'  => $n('zahlungsziel_tage'),
            'skonto_prozent'     => $n('skonto_prozent'),
            'skonto_tage'        => $n('skonto_tage'),
            'mindestbestellwert' => $n('mindestbestellwert'),
            'lieferzeit_tage'    => $n('lieferzeit_tage'),
            'lieferbedingung'    => $n('lieferbedingung'),
            'interne_notizen'    => $n('interne_notizen'),
            'aktiv'              => isset($data['aktiv']) ? (int) $data['aktiv'] : 1,
        ];
    }

    // -------------------------------------------------------------------------
    // Vertreter
    // -------------------------------------------------------------------------

    public function findVertreterByLieferantId(int $lieferantId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, vorname, nachname, telefon, email, mobil, notizen,
                   erstellt_am, geaendert_am
            FROM lieferanten_vertreter
            WHERE lieferant_id = :lieferant_id AND aktiv = 1
            ORDER BY nachname ASC
        ");
        $stmt->execute(['lieferant_id' => $lieferantId]);
        return $stmt->fetchAll();
    }

    public function findVertreterById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT id, lieferant_id, vorname, nachname, telefon, email, mobil, notizen, aktiv
            FROM lieferanten_vertreter
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function insertVertreter(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO lieferanten_vertreter
                (lieferant_id, vorname, nachname, telefon, email, mobil, notizen, aktiv)
            VALUES
                (:lieferant_id, :vorname, :nachname, :telefon, :email, :mobil, :notizen, :aktiv)
        ");
        $stmt->execute([
            'lieferant_id' => $data['lieferant_id'],
            'vorname'      => $data['vorname']      ?? null,
            'nachname'     => $data['nachname'],
            'telefon'      => $data['telefon']      ?? null,
            'email'        => $data['email']        ?? null,
            'mobil'        => $data['mobil']        ?? null,
            'notizen'      => $data['notizen']      ?? null,
            'aktiv'        => isset($data['aktiv']) ? (int) $data['aktiv'] : 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateVertreter(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lieferanten_vertreter SET
                vorname      = :vorname,
                nachname     = :nachname,
                telefon      = :telefon,
                email        = :email,
                mobil        = :mobil,
                notizen      = :notizen,
                aktiv        = :aktiv,
                geaendert_am = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id'       => $data['id'],
            'vorname'  => $data['vorname']  ?? null,
            'nachname' => $data['nachname'] ?? null,
            'telefon'  => $data['telefon']  ?? null,
            'email'    => $data['email']    ?? null,
            'mobil'    => $data['mobil']    ?? null,
            'notizen'  => $data['notizen']  ?? null,
            'aktiv'    => isset($data['aktiv']) ? (int) $data['aktiv'] : 1,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deactivateVertreter(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE lieferanten_vertreter SET aktiv = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Zugänge (Händlerportale) – AES-256-GCM Passwort-Verschlüsselung
    // -------------------------------------------------------------------------

    public function findZugaengeByLieferantId(int $lieferantId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, bezeichnung, url, benutzername, passwort_enc, notizen, aktiv
            FROM lieferanten_zugaenge
            WHERE lieferant_id = :lid AND aktiv = 1
            ORDER BY bezeichnung ASC
        ");
        $stmt->execute(['lid' => $lieferantId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['passwort'] = Encryption::decrypt($row['passwort_enc'] ?? null);
            unset($row['passwort_enc']);
        }
        return $rows;
    }

    public function findZugangById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT id, lieferant_id, bezeichnung, url, benutzername, passwort_enc, notizen, aktiv
            FROM lieferanten_zugaenge
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) return false;
        $row['passwort'] = Encryption::decrypt($row['passwort_enc'] ?? null);
        unset($row['passwort_enc']);
        return $row;
    }

    public function insertZugang(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO lieferanten_zugaenge
                (lieferant_id, bezeichnung, url, benutzername, passwort_enc, notizen, aktiv)
            VALUES
                (:lieferant_id, :bezeichnung, :url, :benutzername, :passwort_enc, :notizen, 1)
        ");
        $stmt->execute([
            'lieferant_id' => $data['lieferant_id'],
            'bezeichnung'  => $data['bezeichnung'],
            'url'          => $data['url']          ?? null,
            'benutzername' => $data['benutzername'] ?? null,
            'passwort_enc' => (isset($data['passwort']) && $data['passwort'] !== '')
                                ? Encryption::encrypt($data['passwort']) : null,
            'notizen'      => $data['notizen']      ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateZugang(array $data): bool
    {
        $existing = $this->findZugangById((int) $data['id']);
        if ($existing === false) return false;

        // Wenn kein neues Passwort → bestehendes re-verschlüsseln (aus decrypt)
        $neuesPw = (isset($data['passwort']) && $data['passwort'] !== '')
            ? Encryption::encrypt($data['passwort'])
            : ($existing['passwort'] !== null ? Encryption::encrypt($existing['passwort']) : null);

        $stmt = $this->db->prepare("
            UPDATE lieferanten_zugaenge SET
                bezeichnung  = :bezeichnung,
                url          = :url,
                benutzername = :benutzername,
                passwort_enc = :passwort_enc,
                notizen      = :notizen,
                geaendert_am = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id'           => $data['id'],
            'bezeichnung'  => $data['bezeichnung'],
            'url'          => $data['url']          ?? null,
            'benutzername' => $data['benutzername'] ?? null,
            'passwort_enc' => $neuesPw,
            'notizen'      => $data['notizen']      ?? null,
        ]);
        return $stmt->rowCount() >= 0;
    }

    public function deleteZugang(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM lieferanten_zugaenge WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Artikel dieses Lieferanten (aus artikel_lieferanten)
    // -------------------------------------------------------------------------

    public function findArtikelByLieferantId(int $lieferantId): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name,
                   al.artikelnummer_lieferant, al.netto_ek, al.vpe_menge, al.lieferzeit_tage,
                   al.standard_lieferant
            FROM artikel_lieferanten al
            JOIN artikel a ON a.id = al.artikel_id
            WHERE al.lieferant_id = :lid
            ORDER BY a.name ASC
        ");
        $stmt->execute(['lid' => $lieferantId]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Bestellungen bei diesem Lieferanten
    // -------------------------------------------------------------------------

    public function findBestellungenByLieferantId(int $lieferantId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, status, bestelldatum, erwartet_am, ab_nummer,
                   rechnung_betrag, rechnung_datum, notiz
            FROM bestellungen
            WHERE lieferant_id = :lid
            ORDER BY bestelldatum DESC
        ");
        $stmt->execute(['lid' => $lieferantId]);
        return $stmt->fetchAll();
    }
}
