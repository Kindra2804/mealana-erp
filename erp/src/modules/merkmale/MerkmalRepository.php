<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * MerkmalRepository – Lesezugriff auf Merkmale und Merkmal-Gruppen
 *
 * Merkmale sind Produkteigenschaften die Artikeln zugewiesen werden
 * (z.B. "Material" = "Schurwolle", "Nadelstärke" = "3.5 mm").
 * Merkmale sind in Gruppen organisiert (z.B. Gruppe "Technisch").
 *
 * Datentypvarianten in artikel_merkmale:
 *   wert_text  → für Texte und Auswahlen
 *   wert_zahl  → für Zahlen mit Einheit (z.B. "3.5" mit Einheit "mm")
 *   wert_bool  → für Ja/Nein-Eigenschaften
 *
 * filterbar = 1 → erscheint im Shop als Filterkriterium.
 *
 * Hinweis: Dieser MerkmalRepository liest nur (kein Insert/Update/Delete).
 * Das vollständige Merkmale-CRUD für den Verwalten-Bereich nutzt direkte
 * DB-Aufrufe in den AJAX-Endpunkten (artikel/merkmale_*.php).
 */
class MerkmalRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Gibt alle Merkmale mit zugehörigem Gruppen-Namen zurück. */
    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                m.id,
                m.name,
                m.einheit,
                m.aktiv,
                m.datentyp,
                m.erstellt_am,
                m.filterbar,
                g.name AS Merkmalgruppenname
            FROM merkmale m
            INNER JOIN merkmal_gruppen g ON m.merkmal_gruppen_id = g.id
        ");

        return $stmt->fetchAll();
    }

    /**
     * Gibt alle aktiven Merkmale einer Gruppe zurück, alphabetisch sortiert.
     * Wird für Dropdown-Auswahl beim Merkmal-Zuweisung verwendet.
     *
     * @return array|false false wenn groupId ungültig ist (wird im Controller geprüft)
     */
    public function findMerkmaleByGroupId(int $groupId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT
                m.id,
                m.name,
                m.einheit,
                m.aktiv,
                m.datentyp,
                m.erstellt_am,
                m.filterbar,
                g.id AS merkmal_gruppen_id,
                g.name AS Merkmalgruppenname
            FROM merkmale m
            INNER JOIN merkmal_gruppen g ON m.merkmal_gruppen_id = g.id
            WHERE m.merkmal_gruppen_id = :group_id
            AND m.aktiv = 1
            ORDER BY m.name ASC
        ");

        $stmt->execute(['group_id' => $groupId]);
        return $stmt->fetchAll();
    }

    /**
     * Gibt alle einem Artikel zugewiesenen Merkmale mit eingetragenen Werten zurück.
     * Enthält wert_text, wert_zahl, wert_bool aus artikel_merkmale.
     * Nur aktive Merkmale werden berücksichtigt.
     */
    public function findMerkmaleByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            a.id AS ArtikelID,
            a.name AS Artikelname,
            m.einheit,
            m.aktiv,
            m.datentyp,
            m.erstellt_am,
            m.filterbar,
            g.id AS merkmal_gruppen_id,
            g.name AS Merkmalgruppenname,
            am.wert_text,
            am.wert_zahl,
            am.wert_bool
            FROM merkmale m
            INNER JOIN merkmal_gruppen g ON m.merkmal_gruppen_id = g.id
            INNER JOIN artikel_merkmale am ON am.merkmal_id = m.id
            INNER JOIN artikel a ON a.id = am.artikel_id
            WHERE a.id = :artikel_id
            AND m.aktiv = 1
            ORDER BY m.name ASC
    ");

        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    /**
     * Gibt nur filterbare Merkmale eines Artikels zurück.
     * Subset von findMerkmaleByArtikelId() — nur Merkmale mit filterbar = 1.
     * Wird für Shop-Facettensuche und Filteranzeige verwendet.
     */
    public function findFilterbareByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            m.name,
            m.einheit,
            m.datentyp,
            g.name AS gruppenname,
            am.wert_text,
            am.wert_zahl,
            am.wert_bool
        FROM artikel_merkmale am
        INNER JOIN merkmale m ON am.merkmal_id = m.id
        INNER JOIN merkmal_gruppen g ON m.merkmal_gruppen_id = g.id
        WHERE am.artikel_id = :artikel_id
        AND m.filterbar = 1
        ORDER BY m.name ASC
    ");

        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }
}
