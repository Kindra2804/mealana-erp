<?php

require_once __DIR__ . '/../../core/database.php';

/**
 * KonfiguratorRepository – Datenzugriff für position_konfiguration
 *
 * position_konfiguration speichert die zur Bestellzeit gewählte Achse/Wert-Auswahl eines
 * konfigurierbaren Artikels, polymorph an eine Position gehängt (referenz_tabelle +
 * referenz_id — gleiches Muster wie bei reservierungen). Mögliche referenz_tabelle-Werte:
 * 'kassen_bon_positionen', 'auftrag_positionen'.
 *
 * wert_text ist ein Klartext-Snapshot des Wert-Namens zum Bestellzeitpunkt (Migration 171) —
 * der FK auf varianten_achse_werte verhindert nur Löschen, nicht Umbenennen. Ohne Snapshot
 * würde eine spätere Tippfehler-Korrektur am Wert rückwirkend die Bedeutung alter Bestellungen
 * verändern.
 */
class KonfiguratorRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function insertAuswahl(string $referenzTabelle, int $referenzId, int $achseId, int $wertId, ?string $wertText = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO position_konfiguration (referenz_tabelle, referenz_id, achse_id, wert_id, wert_text)
            VALUES (:referenz_tabelle, :referenz_id, :achse_id, :wert_id, :wert_text)
        ");
        $stmt->execute([
            'referenz_tabelle' => $referenzTabelle,
            'referenz_id'      => $referenzId,
            'achse_id'         => $achseId,
            'wert_id'          => $wertId,
            'wert_text'        => $wertText,
        ]);
    }

    /** Auswahl einer einzelnen Position, mit Achse/Wert-Klartext (wert_text-Snapshot bevorzugt). */
    public function findAuswahl(string $referenzTabelle, int $referenzId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                pk.id, pk.achse_id, va.name AS achse_name, pk.wert_id,
                COALESCE(pk.wert_text, vaw.wert) AS wert, vaw.wert_zusatz
            FROM position_konfiguration pk
            JOIN varianten_achsen va ON va.id = pk.achse_id
            LEFT JOIN varianten_achse_werte vaw ON vaw.id = pk.wert_id
            WHERE pk.referenz_tabelle = :referenz_tabelle AND pk.referenz_id = :referenz_id
            ORDER BY va.sort_order, va.name
        ");
        $stmt->execute(['referenz_tabelle' => $referenzTabelle, 'referenz_id' => $referenzId]);
        return $stmt->fetchAll();
    }

    /** Batch-Variante für Listen-Views (z.B. Bon-Journal) — vermeidet N+1-Queries. Gruppiert nach referenz_id. */
    public function findAuswahlFuerReferenzIds(string $referenzTabelle, array $referenzIds): array
    {
        if (empty($referenzIds)) return [];
        $placeholders = implode(',', array_fill(0, count($referenzIds), '?'));
        $stmt = $this->db->prepare("
            SELECT
                pk.referenz_id, pk.achse_id, va.name AS achse_name, pk.wert_id,
                COALESCE(pk.wert_text, vaw.wert) AS wert, vaw.wert_zusatz
            FROM position_konfiguration pk
            JOIN varianten_achsen va ON va.id = pk.achse_id
            LEFT JOIN varianten_achse_werte vaw ON vaw.id = pk.wert_id
            WHERE pk.referenz_tabelle = ? AND pk.referenz_id IN ($placeholders)
            ORDER BY pk.referenz_id, va.sort_order, va.name
        ");
        $stmt->execute(array_merge([$referenzTabelle], $referenzIds));

        $gruppiert = [];
        foreach ($stmt->fetchAll() as $row) {
            $gruppiert[(int)$row['referenz_id']][] = $row;
        }
        return $gruppiert;
    }

    public function deleteAuswahl(string $referenzTabelle, int $referenzId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM position_konfiguration WHERE referenz_tabelle = :referenz_tabelle AND referenz_id = :referenz_id
        ");
        $stmt->execute(['referenz_tabelle' => $referenzTabelle, 'referenz_id' => $referenzId]);
    }

    public function istKonfigurierbar(int $artikelId): bool
    {
        $stmt = $this->db->prepare("SELECT ist_konfigurierbar FROM artikel WHERE id = :id");
        $stmt->execute(['id' => $artikelId]);
        return (bool)$stmt->fetchColumn();
    }

    /** Basisdaten für die Preisberechnung: Name, Artikelnummer, Konfigurator-/Vater-Flags, Steuersatz. */
    public function findArtikelBasisdaten(int $artikelId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.name, a.artikelnummer, a.ist_konfigurierbar, a.ist_vater,
                   sk.satz AS steuer_prozent
            FROM artikel a
            JOIN steuerklassen sk ON sk.id = a.steuerklasse_id
            WHERE a.id = :id
        ");
        $stmt->execute(['id' => $artikelId]);
        return $stmt->fetch();
    }
}
