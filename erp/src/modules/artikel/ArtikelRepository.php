<?php

require_once __DIR__ . '/../../core/Database.php';

class ArtikelRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function getStandardKgId(): int
    {
        static $id = null;
        if ($id === null) {
            $id = (int) $this->db->query("SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1")->fetchColumn();
        }
        return $id ?: 1;
    }

    public function findAll(array $filter, int $limit = 25, int $offset = 0): array
    {
        $conditions = ['a.vaterartikel_id IS NULL', 'a.zustand_vater_id IS NULL'];
        $having = '';

        $sortMap = [
            'artikelnummer' => 'a.artikelnummer',
            'name'          => 'a.name',
            'bestand'       => 'gesamtbestand',
            'preis'         => 'brutto_vk',
        ];
        $sortSpalte = $sortMap[$filter['sort'] ?? ''] ?? 'a.artikelnummer';
        $sortDir    = ($filter['dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';

        $params = [];

        // $params['limit'] = $limit;
        // $params['offset'] = $offset;

        if (!empty($filter['hersteller_id'])) {
            $conditions[] = "a.hersteller_id = :hersteller_id";
            $params['hersteller_id'] = $filter['hersteller_id'];
        }

        if (!empty($filter['artikeltyp_id'])) {
            $conditions[] = "a.artikeltyp_id = :artikeltyp_id";
            $params['artikeltyp_id'] = $filter['artikeltyp_id'];
        }

        if (!empty($filter['nurMitBestand'])) {
            $having = 'HAVING gesamtbestand > 0';
        }

        if (empty($filter['mitInaktiven'])) {
            $conditions[] = "a.aktiv = 1";
        }

        $sf = $filter['status_filter'] ?? '';
        if ($sf === 'auslauf') {
            $conditions[] = "a.ist_auslaufartikel = 1";
        } elseif ($sf === 'uv') {
            $conditions[] = "a.ueberverkauf_erlaubt = 1";
        } elseif ($sf === 'fehlbest') {
            $having = 'HAVING gesamtbestand <= 0';
        } elseif ($sf === 'inaktiv') {
            $conditions[] = "a.aktiv = 0";
        }

        if (!empty($filter['q'])) {
            $conditions[] = "(a.name LIKE :q OR a.artikelnummer LIKE :q)";
            $params['q'] = '%' . $filter['q'] . '%';
        }

        if (!empty($filter['kategorie_ids'])) {
            $katIds = array_map('intval', $filter['kategorie_ids']);
            $katPl  = implode(',', $katIds);
            $conditions[] = "a.id IN (SELECT artikel_id FROM artikel_kategorien WHERE kategorie_id IN ($katPl))";
        }

        if (!empty($filter['nurKategorielos'])) {
            $conditions[] = "NOT EXISTS (SELECT 1 FROM artikel_kategorien ak_kat WHERE ak_kat.artikel_id = a.id)";
        }

        $qf = $filter['qualitaet'] ?? '';
        if ($qf === 'keine_ean') {
            // Kein EAN-Code weder beim Artikel selbst noch bei einem seiner Kinder
            $conditions[] = "NOT EXISTS (
                SELECT 1 FROM artikel_codes ac_q
                WHERE ac_q.typ = 'ean'
                  AND (ac_q.artikel_id = a.id
                       OR ac_q.artikel_id IN (SELECT id FROM artikel WHERE vaterartikel_id = a.id))
            )";
        } elseif ($qf === 'doppelte_ean') {
            // Mindestens ein EAN-Code (Artikel oder Kind) ist in der DB mehrfach vorhanden
            $conditions[] = "EXISTS (
                SELECT 1 FROM artikel_codes ac_q
                WHERE ac_q.typ = 'ean'
                  AND (ac_q.artikel_id = a.id
                       OR ac_q.artikel_id IN (SELECT id FROM artikel WHERE vaterartikel_id = a.id))
                  AND (SELECT COUNT(*) FROM artikel_codes ac_dup WHERE ac_dup.code = ac_q.code AND ac_dup.typ = 'ean') > 1
            )";
        } elseif ($qf === 'keine_bilder') {
            $conditions[] = "NOT EXISTS (SELECT 1 FROM artikel_bilder ab_q WHERE ab_q.artikel_id = a.id)";
        }

        $where = "WHERE " . implode(" AND ", $conditions);
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.artikelnummer,
                a.name,
                at.code AS artikeltyp,
                at.name AS artikeltyp_name,
                a.aktiv,
                a.charge_pflicht,
                a.ist_auslaufartikel,
                a.ueberverkauf_erlaubt,
                a.geaendert_am,
                h.name AS hersteller,
                s.satz AS steuersatz,
                e.kuerzel AS einheit_kuerzel,
                ap.brutto_vk,
                al_std.netto_ek AS standard_ek,
                COALESCE(SUM(lb.bestand), 0) AS gesamtbestand,
                (SELECT COUNT(*) FROM artikel k WHERE k.vaterartikel_id = a.id) AS kind_anzahl,
                (SELECT COALESCE(SUM(r.menge), 0) FROM reservierungen r WHERE r.artikel_id = a.id AND r.status = 'offen') AS reserviert,
                (SELECT COUNT(*) FROM artikel_kategorien WHERE artikel_id = a.id) AS kat_anzahl,
                (SELECT code FROM artikel_codes WHERE artikel_id = a.id AND typ = 'ean' LIMIT 1) AS ean,
                (SELECT GROUP_CONCAT(k2.name ORDER BY k2.name SEPARATOR ', ')
                 FROM artikel_kategorien ak2
                 JOIN kategorien k2 ON k2.id = ak2.kategorie_id
                 WHERE ak2.artikel_id = a.id) AS kategorien
            FROM artikel a
            JOIN artikel_typen at ON a.artikeltyp_id = at.id
            LEFT JOIN hersteller h ON a.hersteller_id = h.id
            LEFT JOIN steuerklassen s ON a.steuerklasse_id = s.id
            LEFT JOIN einheiten e ON a.einheit_id = e.id
            LEFT JOIN artikel_preise ap ON a.id = ap.artikel_id AND ap.kundengruppen_id = (SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1)
            LEFT JOIN artikel_lieferanten al_std ON al_std.artikel_id = a.id AND al_std.standard_lieferant = 1
            LEFT JOIN artikel kind ON kind.vaterartikel_id = a.id
            LEFT JOIN lagerbestand lb ON lb.artikel_id = IFNULL(kind.id, a.id)
            $where
            GROUP BY a.id
            $having
            ORDER BY $sortSpalte $sortDir
            LIMIT $limit OFFSET $offset;
        ");

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countAll(array $filter): int
    {
        $conditions = ['a.vaterartikel_id IS NULL', 'a.zustand_vater_id IS NULL'];
        $having = '';

        $params = [];

        if (!empty($filter['hersteller_id'])) {
            $conditions[] = "a.hersteller_id = :hersteller_id";
            $params['hersteller_id'] = $filter['hersteller_id'];
        }

        if (!empty($filter['artikeltyp_id'])) {
            $conditions[] = "a.artikeltyp_id = :artikeltyp_id";
            $params['artikeltyp_id'] = $filter['artikeltyp_id'];
        }

        if (!empty($filter['nurMitBestand'])) {
            $having = 'HAVING gesamtbestand > 0';
        }

        if (empty($filter['mitInaktiven'])) {
            $conditions[] = "a.aktiv = 1";
        }

        $sf = $filter['status_filter'] ?? '';
        if ($sf === 'auslauf') {
            $conditions[] = "a.ist_auslaufartikel = 1";
        } elseif ($sf === 'uv') {
            $conditions[] = "a.ueberverkauf_erlaubt = 1";
        } elseif ($sf === 'fehlbest') {
            $having = 'HAVING gesamtbestand <= 0';
        } elseif ($sf === 'inaktiv') {
            $conditions[] = "a.aktiv = 0";
        }

        if (!empty($filter['q'])) {
            $conditions[] = "(a.name LIKE :q OR a.artikelnummer LIKE :q)";
            $params['q'] = '%' . $filter['q'] . '%';
        }

        if (!empty($filter['kategorie_ids'])) {
            $katIds = array_map('intval', $filter['kategorie_ids']);
            $katPl  = implode(',', $katIds);
            $conditions[] = "a.id IN (SELECT artikel_id FROM artikel_kategorien WHERE kategorie_id IN ($katPl))";
        }

        if (!empty($filter['nurKategorielos'])) {
            $conditions[] = "NOT EXISTS (SELECT 1 FROM artikel_kategorien ak_kat WHERE ak_kat.artikel_id = a.id)";
        }

        $qf = $filter['qualitaet'] ?? '';
        if ($qf === 'keine_ean') {
            $conditions[] = "NOT EXISTS (
                SELECT 1 FROM artikel_codes ac_q
                WHERE ac_q.typ = 'ean'
                  AND (ac_q.artikel_id = a.id
                       OR ac_q.artikel_id IN (SELECT id FROM artikel WHERE vaterartikel_id = a.id))
            )";
        } elseif ($qf === 'doppelte_ean') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM artikel_codes ac_q
                WHERE ac_q.typ = 'ean'
                  AND (ac_q.artikel_id = a.id
                       OR ac_q.artikel_id IN (SELECT id FROM artikel WHERE vaterartikel_id = a.id))
                  AND (SELECT COUNT(*) FROM artikel_codes ac_dup WHERE ac_dup.code = ac_q.code AND ac_dup.typ = 'ean') > 1
            )";
        } elseif ($qf === 'keine_bilder') {
            $conditions[] = "NOT EXISTS (SELECT 1 FROM artikel_bilder ab_q WHERE ab_q.artikel_id = a.id)";
        }

        $where = "WHERE " . implode(" AND ", $conditions);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM (
                SELECT
                a.id,
                COALESCE(SUM(lb.bestand), 0) AS gesamtbestand
                FROM artikel a
                JOIN artikel_typen at ON a.artikeltyp_id = at.id
                LEFT JOIN hersteller h ON a.hersteller_id = h.id
                LEFT JOIN steuerklassen s ON a.steuerklasse_id = s.id
                LEFT JOIN artikel kind ON kind.vaterartikel_id = a.id
                LEFT JOIN lagerbestand lb ON lb.artikel_id = IFNULL(kind.id, a.id)
                $where
                GROUP BY a.id
                $having
            ) AS sub
        ");

        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.artikelnummer,
                a.name,
                a.vaterartikel_id,
                a.hat_eigenen_lagerstand,
                a.hersteller_id,
                a.steuerklasse_id,
                a.artikeltyp_id,
                a.grundpreis_bezugsmenge,
                a.grundpreis_anzeigen,
                a.gewicht_versand,
                a.gewicht_artikel,
                a.laenge,
                a.breite,
                a.hoehe,
                a.herkunftsland,
                a.taric_code,
                a.kurzbeschreibung,
                a.beschreibung,
                a.technische_details,
                a.beschreibung_intern,
                a.meta_titel,
                a.meta_description,
                a.url_slug,
                a.einheit_id,
                a.inhalt_einheit,
                a.inhalt_menge,
                a.charge_pflicht,
                a.ist_auslaufartikel,
                a.ueberverkauf_erlaubt,
                a.aktiv,
                a.zustand,
                a.uvp,
                a.preise_vererben,
                at.code AS artikeltyp,
                at.name AS artikeltyp_name,
                h.name AS hersteller,
                s.satz AS steuersatz,
                e.name AS einheit_name,
                at.teilbar AS artikeltyp_teilbar,
                ap.brutto_vk,
                ap.netto_vk
            FROM artikel a
            JOIN artikel_typen at ON a.artikeltyp_id = at.id
            LEFT JOIN hersteller h ON a.hersteller_id = h.id
            LEFT JOIN steuerklassen s ON a.steuerklasse_id = s.id
            LEFT JOIN einheiten e ON a.einheit_id = e.id
            LEFT JOIN artikel_preise ap ON a.id = ap.artikel_id AND ap.kundengruppen_id = (SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1)
            WHERE a.id = :id
        ");

        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findKinderByArtikelId(int $artikelId, bool $mitInaktiven = false): array
    {
        $where = $mitInaktiven
            ? "WHERE a.vaterartikel_id = :vaterartikel_id"
            : "WHERE a.vaterartikel_id = :vaterartikel_id AND a.aktiv = 1";

        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.artikelnummer,
                a.name,
                a.aktiv,
                a.ist_auslaufartikel,
                a.ueberverkauf_erlaubt,
                ap.brutto_vk,
                ac.code AS gtin,
                COALESCE(SUM(lb.bestand), 0) AS gesamtbestand
            FROM artikel a
            LEFT JOIN artikel_preise ap ON a.id = ap.artikel_id AND ap.kundengruppen_id = (SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1)
            LEFT JOIN artikel_codes ac ON a.id = ac.artikel_id AND ac.typ = 'GTIN13'
            LEFT JOIN lagerbestand lb ON lb.artikel_id = a.id
            $where
            GROUP BY a.id
            ORDER BY a.name ASC
        ");

        $stmt->execute(['vaterartikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function findByIdMitKindern(int $id): array|false
    {
        $artikel = $this->findById($id);
        if ($artikel === false) return false;
        $artikel['kinder'] = $this->findKinderByArtikelId($id);
        return $artikel;
    }

    public function findKinderFuerListe(array $vaterIds, string $sortSpalte = 'a.artikelnummer', string $sortDir = 'ASC'): array
    {
        if (empty($vaterIds)) return [];
        $placeholders = implode(',', array_fill(0, count($vaterIds), '?'));
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.vaterartikel_id,
                a.artikelnummer,
                a.name,
                a.aktiv,
                a.ist_auslaufartikel,
                a.ueberverkauf_erlaubt,
                a.charge_pflicht,
                a.geaendert_am,
                ap.brutto_vk,
                h.name AS hersteller,
                COALESCE(SUM(lb.bestand), 0) AS gesamtbestand,
                (SELECT COALESCE(SUM(r.menge), 0) FROM reservierungen r WHERE r.artikel_id = a.id AND r.status = 'offen') AS reserviert
            FROM artikel a
            LEFT JOIN artikel_preise ap ON a.id = ap.artikel_id AND ap.kundengruppen_id = (SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1)
            LEFT JOIN lagerbestand lb ON lb.artikel_id = a.id
            LEFT JOIN hersteller h ON a.hersteller_id = h.id
            LEFT JOIN artikel_typen at ON a.artikeltyp_id = at.id
            WHERE a.vaterartikel_id IN ($placeholders)
            GROUP BY a.id
            ORDER BY $sortSpalte $sortDir
        ");
        $stmt->execute($vaterIds);
        return $stmt->fetchAll();
    }

    public function findByIdMitPreisen(int $id): array|false
    {
        $artikel = $this->findByIdMitKindern($id);
        if ($artikel === false) return false;

        $stmt = $this->db->prepare("
            SELECT
                k.name AS kundengruppe,
                k.ist_standard,
                p.brutto_vk,
                p.netto_vk,
                p.gueltig_ab,
                p.gueltig_bis
            FROM artikel_preise p
            LEFT JOIN kundengruppen k ON p.kundengruppen_id = k.id
            WHERE p.artikel_id = :artikel_id
            ORDER BY k.name ASC
        ");
        $stmt->execute(['artikel_id' => $id]);
        $artikel['preise'] = $stmt->fetchAll();

        return $artikel;
    }

    public function findByArtikelnummer(string $artikelnummer, ?int $excludeId = null): array|false
    {
        $sql = "SELECT id FROM artikel WHERE artikelnummer = :artikelnummer";
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->db->prepare($sql);
        $params = ['artikelnummer' => $artikelnummer];
        if ($excludeId) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function insert(array $data): int
    {
        if (!isset($data['artikeltyp_id'])) {
            $data['artikeltyp_id'] = $this->resolveArtikeltypId($data['artikeltyp']);
        }

        unset($data['artikeltyp']);

        $stmt = $this->db->prepare("
            INSERT INTO artikel (
                vaterartikel_id,
                hat_eigenen_lagerstand,
                artikelnummer,
                hersteller_id,
                steuerklasse_id,
                artikeltyp_id,
                name,
                kurzbeschreibung,
                beschreibung,
                technische_details,
                beschreibung_intern,
                meta_titel,
                meta_description,
                url_slug,
                einheit_id,
                inhalt_menge,
                inhalt_einheit,
                gewicht_artikel,
                gewicht_versand,
                laenge,
                breite,
                hoehe,
                herkunftsland,
                taric_code,
                grundpreis_bezugsmenge,
                grundpreis_anzeigen,
                charge_pflicht,
                ist_auslaufartikel,
                ueberverkauf_erlaubt,
                aktiv,
                zustand,
                zustand_vater_id
            ) VALUES (
                :vaterartikel_id,
                :hat_eigenen_lagerstand,
                :artikelnummer,
                :hersteller_id,
                :steuerklasse_id,
                :artikeltyp_id,
                :name,
                :kurzbeschreibung,
                :beschreibung,
                :technische_details,
                :beschreibung_intern,
                :meta_titel,
                :meta_description,
                :url_slug,
                :einheit_id,
                :inhalt_menge,
                :inhalt_einheit,
                :gewicht_artikel,
                :gewicht_versand,
                :laenge,
                :breite,
                :hoehe,
                :herkunftsland,
                :taric_code,
                :grundpreis_bezugsmenge,
                :grundpreis_anzeigen,
                :charge_pflicht,
                :ist_auslaufartikel,
                :ueberverkauf_erlaubt,
                :aktiv,
                :zustand,
                :zustand_vater_id
            )
        ");

        $data['vaterartikel_id']       = $data['vaterartikel_id'] ?? null;
        $data['hat_eigenen_lagerstand'] = $data['hat_eigenen_lagerstand'] ?? 1;
        $data['ist_auslaufartikel']     = $data['ist_auslaufartikel'] ?? 0;
        $data['laenge']                = $data['laenge']  ?? null;
        $data['breite']                = $data['breite']  ?? null;
        $data['hoehe']                 = $data['hoehe']   ?? null;
        $data['zustand']               = $data['zustand'] ?? 'neu';
        $data['zustand_vater_id']      = $data['zustand_vater_id'] ?? null;

        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): bool
    {
        $data['artikeltyp_id'] = $this->resolveArtikeltypId($data['artikeltyp']);
        unset($data['artikeltyp']);

        $stmt = $this->db->prepare("
            UPDATE artikel SET
                artikelnummer       = :artikelnummer,
                hersteller_id       = :hersteller_id,
                steuerklasse_id     = :steuerklasse_id,
                artikeltyp_id       = :artikeltyp_id,
                name                = :name,
                kurzbeschreibung    = :kurzbeschreibung,
                beschreibung        = :beschreibung,
                technische_details  = :technische_details,
                beschreibung_intern = :beschreibung_intern,
                meta_titel          = :meta_titel,
                meta_description    = :meta_description,
                url_slug            = :url_slug,
                einheit_id          = :einheit_id,
                inhalt_menge        = :inhalt_menge,
                inhalt_einheit      = :inhalt_einheit,
                gewicht_artikel     = :gewicht_artikel,
                gewicht_versand     = :gewicht_versand,
                laenge              = :laenge,
                breite              = :breite,
                hoehe               = :hoehe,
                herkunftsland       = :herkunftsland,
                taric_code          = :taric_code,
                grundpreis_bezugsmenge = :grundpreis_bezugsmenge,
                grundpreis_anzeigen    = :grundpreis_anzeigen,
                charge_pflicht         = :charge_pflicht,
                ist_auslaufartikel     = :ist_auslaufartikel,
                ueberverkauf_erlaubt    = :ueberverkauf_erlaubt,
                aktiv                  = :aktiv,
                zustand                = :zustand,
                zustand_vater_id       = :zustand_vater_id
            WHERE id = :id
        ");

        $data['laenge']           = $data['laenge']           ?? null;
        $data['breite']           = $data['breite']           ?? null;
        $data['hoehe']            = $data['hoehe']            ?? null;
        $data['zustand']          = $data['zustand']          ?? 'neu';
        $data['zustand_vater_id'] = $data['zustand_vater_id'] ?? null;

        $stmt->execute($data);
        return true;
    }

    public function updateKind(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE artikel SET
                artikelnummer      = :artikelnummer,
                aktiv              = :aktiv,
                ueberverkauf_erlaubt    = :ueberverkauf_erlaubt,
                ist_auslaufartikel = :ist_auslaufartikel
            WHERE id = :id
        ");
        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE artikel SET aktiv = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deactivateKinder(int $vaterId): void
    {
        $stmt = $this->db->prepare("
            UPDATE artikel SET aktiv = 0, deaktiviert_mit_vater = 1
            WHERE vaterartikel_id = :vater AND aktiv = 1
        ");
        $stmt->execute(['vater' => $vaterId]);
    }

    public function activate(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE artikel SET aktiv = 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function reactivateKinder(int $vaterId): void
    {
        $stmt = $this->db->prepare("
            UPDATE artikel SET aktiv = 1, deaktiviert_mit_vater = 0
            WHERE vaterartikel_id = :vater AND deaktiviert_mit_vater = 1
        ");
        $stmt->execute(['vater' => $vaterId]);
    }

    public function setAuslauf(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE artikel SET ist_auslaufartikel = 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function removeAuslauf(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE artikel SET ist_auslaufartikel = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function setAuslaufKinder(int $vaterId): void
    {
        $stmt = $this->db->prepare("
            UPDATE artikel SET ist_auslaufartikel = 1, auslauf_mit_vater = 1
            WHERE vaterartikel_id = :vater AND ist_auslaufartikel = 0
        ");
        $stmt->execute(['vater' => $vaterId]);
    }

    public function removeAuslaufKinder(int $vaterId): void
    {
        $stmt = $this->db->prepare("
            UPDATE artikel SET ist_auslaufartikel = 0, auslauf_mit_vater = 0
            WHERE vaterartikel_id = :vater AND auslauf_mit_vater = 1
        ");
        $stmt->execute(['vater' => $vaterId]);
    }

    public function insertPreis(int $artikelId, float $bruttoVk, float $nettoVk, int $kundengruppenId = 0): bool
    {
        if ($kundengruppenId === 0) $kundengruppenId = $this->getStandardKgId();
        $stmt = $this->db->prepare("
            INSERT INTO artikel_preise (artikel_id, kundengruppen_id, brutto_vk, netto_vk)
            VALUES (:artikel_id, :kundengruppen_id, :brutto_vk, :netto_vk)
        ");
        return $stmt->execute([
            'artikel_id'       => $artikelId,
            'kundengruppen_id' => $kundengruppenId,
            'brutto_vk'        => $bruttoVk,
            'netto_vk'         => $nettoVk
        ]);
    }

    public function insertCode(int $artikelId, string $typ, string $code): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO artikel_codes (artikel_id, typ, code)
            VALUES (:artikel_id, :typ, :code)
        ");
        $stmt->execute(['artikel_id' => $artikelId, 'typ' => $typ, 'code' => $code]);
    }

    public function updatePreis(int $artikelId, float $bruttoVk, float $nettoVk, int $kundengruppenId = 0): bool
    {
        if ($kundengruppenId === 0) $kundengruppenId = $this->getStandardKgId();
        $stmt = $this->db->prepare("
            SELECT id FROM artikel_preise
            WHERE artikel_id = :artikel_id AND kundengruppen_id = :kundengruppen_id
        ");
        $stmt->execute(['artikel_id' => $artikelId, 'kundengruppen_id' => $kundengruppenId]);

        if ($stmt->fetch()) {
            $stmt = $this->db->prepare("
                UPDATE artikel_preise SET brutto_vk = :brutto_vk, netto_vk = :netto_vk
                WHERE artikel_id = :artikel_id AND kundengruppen_id = :kundengruppen_id
            ");
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO artikel_preise (artikel_id, kundengruppen_id, brutto_vk, netto_vk)
                VALUES (:artikel_id, :kundengruppen_id, :brutto_vk, :netto_vk)
            ");
        }

        return $stmt->execute([
            'artikel_id'       => $artikelId,
            'kundengruppen_id' => $kundengruppenId,
            'brutto_vk'        => $bruttoVk,
            'netto_vk'         => $nettoVk
        ]);
    }

    public function findAllArtikelTypen(): array
    {
        $stmt = $this->db->query("
            SELECT id, code, name FROM artikel_typen
            WHERE aktiv = 1 ORDER BY sortierung ASC
        ");
        return $stmt->fetchAll();
    }

    private function resolveArtikeltypId(string $code): int
    {
        $stmt = $this->db->prepare("SELECT id FROM artikel_typen WHERE code = :code");
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new InvalidArgumentException("Unbekannter Artikeltyp-Code: '$code'");
        }
        return (int) $row['id'];
    }

    public function search(string $q): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id, a.artikelnummer, a.name,
                at.code AS artikeltyp,
                a.ist_auslaufartikel,
                a.aktiv,
                h.name AS hersteller
            FROM artikel a
            JOIN artikel_typen at ON a.artikeltyp_id = at.id
            LEFT JOIN hersteller h ON a.hersteller_id = h.id
            WHERE a.aktiv = 1
            AND a.vaterartikel_id IS NULL
            AND (
                a.artikelnummer LIKE :q
                OR a.name LIKE :q
                OR h.name LIKE :q
            )
            ORDER BY a.artikelnummer ASC
            LIMIT 50
        ");
        $stmt->execute(['q' => '%' . $q . '%']);
        return $stmt->fetchAll();
    }

    public function findCodesByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM artikel_codes WHERE artikel_id = :artikel_id");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function deleteCodesByArtikelIdAndType(int $artikelId, string $typ): void
    {
        $stmt = $this->db->prepare("DELETE FROM artikel_codes WHERE artikel_id = :artikel_id AND typ = :typ");
        $stmt->execute(['artikel_id' => $artikelId, 'typ' => $typ]);
    }

    public function setArtikelAktiv(int $id, int $aktiv): void
    {
        $stmt = $this->db->prepare("UPDATE artikel SET aktiv = :aktiv WHERE id = :id");
        $stmt->execute(['id' => $id, 'aktiv' => $aktiv]);
    }

    public function setAuslaufartikelAktiv(int $id, int $ist_auslaufartikel): void
    {
        $stmt = $this->db->prepare("UPDATE artikel SET ist_auslaufartikel = :ist_auslaufartikel WHERE id = :id");
        $stmt->execute(['id' => $id, 'ist_auslaufartikel' => $ist_auslaufartikel]);
    }

    public function propagateAuslaufZuKindern(int $vaterId, int $istAuslauf): void
    {
        $stmt = $this->db->prepare("
            UPDATE artikel SET ist_auslaufartikel = :ist_auslaufartikel
            WHERE vaterartikel_id = :vaterartikel_id
        ");
        $stmt->execute(['vaterartikel_id' => $vaterId, 'ist_auslaufartikel' => $istAuslauf]);
    }

    public function countAktiveKinder(int $vaterId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM artikel WHERE vaterartikel_id = :id AND aktiv = 1
        ");
        $stmt->execute(['id' => $vaterId]);
        return (int) $stmt->fetchColumn();
    }

    public function getLieferantenFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            al.id,
            al.lieferant_id,
            al.artikelnummer_lieferant,
            al.netto_ek,
            al.waehrung,
            al.vpe_menge,
            al.vpe_ean,
            al.lieferzeit_tage,
            al.mindestabnahme,
            al.standard_lieferant,
            al.aktiv,
            l.name AS lieferant_name
        FROM artikel_lieferanten al
        JOIN lieferanten l ON l.id = al.lieferant_id
        WHERE al.artikel_id = :artikel_id
        ORDER BY al.standard_lieferant DESC, l.name
        ");

        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    // Externe Klassen die derzeit keine eigenen Repos haben
    public function findAllHersteller(): array
    {
        return $this->db->query("SELECT id, name FROM hersteller ORDER BY name")->fetchAll();
    }

    public function findAllSteuerklassen(): array
    {
        return $this->db->query("SELECT id, name, satz FROM steuerklassen WHERE aktiv = 1")->fetchAll();
    }

    public function copyPreise(int $quellId, int $neueId)
    {
        $stmt = $this->db->prepare("
            INSERT INTO artikel_preise (
                artikel_id,
                kundengruppen_id,
                brutto_vk,
                netto_vk,
                gueltig_ab,
                gueltig_bis
                )
            SELECT 
                :neue_id,
                kundengruppen_id,
                brutto_vk,
                netto_vk,
                gueltig_ab,
                gueltig_bis
            FROM artikel_preise
            WHERE artikel_id = :quell_id
        ");

        $stmt->execute([
            'neue_id' => $neueId,
            'quell_id' => $quellId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function copyKategorien(int $quellId, int $neueId)
    {
        $stmt = $this->db->prepare("
            INSERT INTO artikel_kategorien (
                artikel_id,
                kategorie_id
                )
            SELECT 
                :neue_id,
                kategorie_id
            FROM artikel_kategorien
            WHERE artikel_id = :quell_id
        ");

        $stmt->execute([
            'neue_id' => $neueId,
            'quell_id' => $quellId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function copyMerkmale(int $quellId, int $neueId)
    {
        $stmt = $this->db->prepare("
            INSERT INTO artikel_merkmale (
                artikel_id,
                merkmal_id,
                wert_text,
                wert_zahl,
                wert_bool
                )
            SELECT 
                :neue_id,
                merkmal_id,
                wert_text,
                wert_zahl,
                wert_bool
            FROM artikel_merkmale
            WHERE artikel_id = :quell_id
        ");

        $stmt->execute([
            'neue_id' => $neueId,
            'quell_id' => $quellId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function copyLieferanten(int $quellId, int $neueId)
    {
        $stmt = $this->db->prepare("
            INSERT INTO artikel_lieferanten (
                artikel_id,
                lieferant_id,
                artikelnummer_lieferant,
                netto_ek,
                waehrung,
                vpe_menge,
                vpe_ean,
                lieferzeit_tage,
                mindestabnahme,
                standard_lieferant,
                aktiv
                )
            SELECT 
                :neue_id,
                lieferant_id,
                NULL,
                NULL,
                waehrung,
                NULL,
                NULL,
                NULL,
                NULL,
                0,
                aktiv
            FROM artikel_lieferanten
            WHERE artikel_id = :quell_id
        ");

        $stmt->execute([
            'neue_id' => $neueId,
            'quell_id' => $quellId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function propagiereZuKindern(int $vaterId): void
    {
        $vater = $this->findById($vaterId);
        if (!$vater) return;

        $this->db->prepare("
            UPDATE artikel SET
                hersteller_id          = :hersteller_id,
                steuerklasse_id        = :steuerklasse_id,
                artikeltyp_id          = :artikeltyp_id,
                kurzbeschreibung       = :kurzbeschreibung,
                beschreibung           = :beschreibung,
                technische_details     = :technische_details,
                beschreibung_intern    = :beschreibung_intern,
                meta_titel             = :meta_titel,
                meta_description       = :meta_description,
                einheit_id             = :einheit_id,
                inhalt_menge           = :inhalt_menge,
                inhalt_einheit         = :inhalt_einheit,
                gewicht_artikel        = :gewicht_artikel,
                gewicht_versand        = :gewicht_versand,
                laenge                 = :laenge,
                breite                 = :breite,
                hoehe                  = :hoehe,
                herkunftsland          = :herkunftsland,
                taric_code             = :taric_code,
                grundpreis_bezugsmenge = :grundpreis_bezugsmenge,
                grundpreis_anzeigen    = :grundpreis_anzeigen,
                charge_pflicht         = :charge_pflicht,
                ueberverkauf_erlaubt   = :ueberverkauf_erlaubt
            WHERE vaterartikel_id = :vater_id
        ")->execute([
            'vater_id'               => $vaterId,
            'hersteller_id'          => $vater['hersteller_id'],
            'steuerklasse_id'        => $vater['steuerklasse_id'],
            'artikeltyp_id'          => $vater['artikeltyp_id'],
            'kurzbeschreibung'       => $vater['kurzbeschreibung'],
            'beschreibung'           => $vater['beschreibung'],
            'technische_details'     => $vater['technische_details'],
            'beschreibung_intern'    => $vater['beschreibung_intern'],
            'meta_titel'             => $vater['meta_titel'],
            'meta_description'       => $vater['meta_description'],
            'einheit_id'             => $vater['einheit_id'],
            'inhalt_menge'           => $vater['inhalt_menge'],
            'inhalt_einheit'         => $vater['inhalt_einheit'],
            'gewicht_artikel'        => $vater['gewicht_artikel'],
            'gewicht_versand'        => $vater['gewicht_versand'],
            'laenge'                 => $vater['laenge'],
            'breite'                 => $vater['breite'],
            'hoehe'                  => $vater['hoehe'],
            'herkunftsland'          => $vater['herkunftsland'],
            'taric_code'             => $vater['taric_code'],
            'grundpreis_bezugsmenge' => $vater['grundpreis_bezugsmenge'],
            'grundpreis_anzeigen'    => $vater['grundpreis_anzeigen'],
            'charge_pflicht'         => $vater['charge_pflicht'],
            'ueberverkauf_erlaubt'   => $vater['ueberverkauf_erlaubt'],
        ]);
    }

    public function findZustandsArtikelByVaterId(int $vaterId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id, a.artikelnummer, a.name, a.zustand, a.aktiv,
                COALESCE(SUM(lb.bestand), 0) AS gesamtbestand
            FROM artikel a
            LEFT JOIN lagerbestand lb ON lb.artikel_id = a.id
            WHERE a.zustand_vater_id = :vater_id
            GROUP BY a.id
            ORDER BY a.zustand ASC
        ");
        $stmt->execute(['vater_id' => $vaterId]);
        return $stmt->fetchAll();
    }

    public function findZustandsArtikelFuerListe(array $vaterIds): array
    {
        if (empty($vaterIds)) return [];
        $placeholders = implode(',', array_fill(0, count($vaterIds), '?'));
        $stmt = $this->db->prepare("
            SELECT
                a.id, a.artikelnummer, a.name, a.zustand, a.aktiv, a.zustand_vater_id,
                COALESCE(SUM(lb.bestand), 0) AS gesamtbestand
            FROM artikel a
            LEFT JOIN lagerbestand lb ON lb.artikel_id = a.id
            WHERE a.zustand_vater_id IN ($placeholders)
            GROUP BY a.id
            ORDER BY a.zustand_vater_id, a.zustand
        ");
        $stmt->execute($vaterIds);
        return $stmt->fetchAll();
    }

    public function searchVaterArtikel(string $q): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name
            FROM artikel a
            WHERE a.aktiv = 1
              AND a.vaterartikel_id IS NULL
              AND a.zustand_vater_id IS NULL
              AND a.zustand = 'neu'
              AND (a.artikelnummer LIKE :q OR a.name LIKE :q)
            ORDER BY a.artikelnummer ASC
            LIMIT 20
        ");
        $stmt->execute(['q' => '%' . $q . '%']);
        return $stmt->fetchAll();
    }

    public function findByIdSimple(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT id, artikelnummer, name FROM artikel WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getPreisStatusBatch(array $artikelIds, int $standardKgId): array
    {
        if (empty($artikelIds)) return [];
        $pl = implode(',', array_fill(0, count($artikelIds), '?'));

        $saleStmt = $this->db->prepare("
            SELECT DISTINCT artikel_id FROM preis_aktionen_positionen
            WHERE artikel_id IN ($pl)
            AND kundengruppen_id = ?
            AND (gueltig_ab IS NULL OR gueltig_ab <= NOW())
            AND (gueltig_bis IS NULL OR gueltig_bis >= NOW())
        ");
        $saleStmt->execute(array_merge($artikelIds, [$standardKgId]));
        $saleIds = array_flip($saleStmt->fetchAll(PDO::FETCH_COLUMN));

        $aktionStmt = $this->db->prepare("
            SELECT DISTINCT aap.artikel_id FROM aktionen_artikel_preise aap
            JOIN aktionen a ON a.id = aap.aktion_id
            JOIN aktionen_kategorien ak ON ak.aktion_id = aap.aktion_id
            WHERE aap.artikel_id IN ($pl)
            AND aap.kundengruppen_id = ?
            AND a.gestartet = 1
            AND ak.gueltig_ab <= CURDATE()
            AND ak.gueltig_bis >= CURDATE()
        ");
        $aktionStmt->execute(array_merge($artikelIds, [$standardKgId]));
        $aktionIds = array_flip($aktionStmt->fetchAll(PDO::FETCH_COLUMN));

        $result = [];
        foreach ($artikelIds as $id) {
            $hatSale   = isset($saleIds[$id]);
            $hatAktion = isset($aktionIds[$id]);
            if ($hatSale || $hatAktion) {
                $result[$id] = ['hat_sale' => $hatSale, 'hat_aktion' => $hatAktion];
            }
        }
        return $result;
    }
}
