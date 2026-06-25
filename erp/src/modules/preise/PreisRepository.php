<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * PreisRepository – Datenzugriff für alle Preisebenen eines Artikels
 *
 * Es gibt vier Preisquellen, die in PreisService::getEffektiverPreis() Priorität haben:
 *
 * 1. SALE-Override (preis_aktionen_positionen)
 *    → Artikel-spezifische Sonderpreise mit eigenem Zeitraum (z.B. "Weihnachtsangebot")
 *    → Höchste Priorität, überschreibt alles andere
 *
 * 2. Kategorie-Aktion (aktionen_artikel_preise)
 *    → Preise aus dem Aktionsmodul (Aktion muss gestartet + im Zeitraum sein)
 *
 * 3. KG-Festpreis (artikel_preise für bestimmte KG)
 *    → Händlerpreis, Vertriebspartnerpreis, etc. mit optionalem Zeitraum
 *
 * 4. Standard-KG-Preis (artikel_preise der Standard-Kundengruppe)
 *    → Fallback: normale Endkunden-VK
 *
 * Staffelpreise (artikel_staffelpreise) sind separat und werden für Mengenrabatte
 * verwendet — nicht Teil des Effektivpreis-Flows, sondern als eigene Preisstaffel.
 */
class PreisRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Speichert oder aktualisiert einen Kundengruppen-Preis (check-then-update/insert).
     * Kein ON DUPLICATE KEY UPDATE weil das Schema keinen eindeutigen Composite-Index hat.
     * gueltig_ab/gueltig_bis = null bedeutet: immer gültig.
     */
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

    /**
     * Gibt alle Kundengruppen mit zugehörigen Preisen für einen Artikel zurück.
     * LEFT JOIN: Kundengruppen ohne Preis erscheinen auch (mit NULL-Preis → "nicht gesetzt").
     * Für die Preis-Verwaltungstabelle in der Artikel-Detailansicht.
     */
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

    /**
     * Gibt alle Aktionen zurück die einen Aktionspreis für diesen Artikel enthalten.
     * Für die Anzeige im Preis-Tab der Artikel-Detailansicht ("Aktive Aktionen").
     * Enthält: Aktionsname, Zeitraum, Kundengruppe, Achsenname (bei Sub-Achsen-Preisen).
     */
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

    /** Löscht den Preis einer bestimmten Kundengruppe für einen Artikel. */
    public function deleteKundengruppenPreis(int $artikelId, int $kgId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM artikel_preise WHERE artikel_id = :artikel_id AND kundengruppen_id = :kundengruppen_id");
        return $stmt->execute(['artikel_id' => $artikelId, 'kundengruppen_id' => $kgId]);
    }

    /**
     * Gibt alle Staffelpreise eines Artikels zurück (gruppiert nach KG und Menge).
     * Sortiert: KG-ID aufsteigend, dann Menge aufsteigend (Lesbarkeit der Tabelle).
     */
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

    /** Legt einen neuen Staffelpreis an. */
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

    /** Aktualisiert einen Staffelpreis. artikel_id in WHERE um unautorisierte Änderungen zu verhindern. */
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

    /** Löscht einen Staffelpreis. artikel_id in WHERE als Sicherheitscheck. */
    public function deleteStaffelpreis(int $id, int $artikelId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM artikel_staffelpreise WHERE id = :id AND artikel_id = :artikel_id");
        return $stmt->execute(['id' => $id, 'artikel_id' => $artikelId]);
    }

    // ── Effektivpreis-Lookup (Prioritätsstufen 1-4) ───────────────────

    /**
     * Priorität 1: SALE-Override (preis_aktionen_positionen).
     * Zeitraum-Check: gueltig_ab/gueltig_bis NULL bedeutet "immer gültig".
     * Gibt false zurück wenn kein aktiver Sale vorhanden.
     */
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

    /**
     * Priorität 2: Aktionspreis aus Aktionsmodul.
     * Bedingungen: a.gestartet = 1 UND CURDATE() im Aktionskategorie-Zeitraum.
     * Gibt false zurück wenn keine aktive Aktion für diesen Artikel/KG-Kombination.
     */
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

    /**
     * Priorität 3: Kundengruppen-Festpreis (artikel_preise für diese spezifische KG).
     * Zeitraum-Check: NULL bedeutet "immer gültig".
     * Gibt false zurück wenn kein KG-spezifischer Preis vorhanden.
     */
    public function findKundengruppenPreisFuerKg(int $artikelId, int $kgId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT ap.brutto_vk, ap.netto_vk, k.name
            FROM artikel_preise ap
            JOIN kundengruppen k ON ap.kundengruppen_id = k.id
            WHERE ap.artikel_id = :artikel_id
            AND ap.kundengruppen_id = :kundengruppen_id
            AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= NOW())
            AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= NOW())
            LIMIT 1
        ");

        $stmt->execute([
            'artikel_id'       => $artikelId,
            'kundengruppen_id' => $kgId,
        ]);

        return $stmt->fetch();
    }

    /**
     * Priorität 4: Standard-KG-Preis (Fallback).
     * Nimmt den Preis der Kundengruppe mit ist_standard = 1.
     * Wenn auch dieser fehlt → Artikel hat gar keinen Preis (Rückgabe false).
     */
    public function findStandardPreis(int $artikelId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT ap.brutto_vk, ap.netto_vk, k.name
            FROM artikel_preise ap
            JOIN kundengruppen k ON ap.kundengruppen_id = k.id
            WHERE ap.artikel_id = :artikel_id
            AND k.ist_standard = 1
            AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= NOW())
            AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= NOW())
            LIMIT 1
        ");

        $stmt->execute([
            'artikel_id'       => $artikelId,
        ]);

        return $stmt->fetch();
    }

    // ── SALE-Overrides CRUD ───────────────────────────────────────────

    /**
     * Gibt alle SALE-Overrides eines Artikels zurück.
     * ist_aktiv wird als berechnetes SQL-Feld zurückgegeben (1 wenn Zeitraum aktiv).
     * bis_lagerstand_null: Override endet automatisch wenn Bestand auf 0 fällt.
     */
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

    /**
     * Speichert oder aktualisiert einen SALE-Override.
     * Wenn $data['id'] gesetzt: UPDATE, sonst INSERT.
     * artikel_id in WHERE schützt vor Cross-Artikel-Manipulation.
     * Gibt die ID des gespeicherten Records zurück.
     */
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

    /** Löscht einen SALE-Override. artikel_id als Sicherheitscheck. */
    public function deleteSaleOverride(int $id, int $artikelId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM preis_aktionen_positionen WHERE id = :id AND artikel_id = :artikel_id");
        return $stmt->execute(['id' => $id, 'artikel_id' => $artikelId]);
    }
}
