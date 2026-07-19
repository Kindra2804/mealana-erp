<?php

require_once __DIR__ . '/../../core/database.php';

/**
 * BilderRepository – CRUD und Reihenfolge-Verwaltung für Artikel-Bilder
 *
 * Bilder sind in artikel_bilder gespeichert mit einer position-Spalte (0-basiert).
 * Position 0 ist immer das Hauptbild — es erscheint im Shop-Listing und beim Scan-Modus.
 *
 * Reihenfolge-Invariante:
 *   ↑/↓ (verschiebePosition) darf NICHT Position 0 überschreiben — nur ☆ (setzeHauptbild) darf das.
 *   Dies ist bewusst, damit das Hauptbild stabil bleibt wenn der Nutzer die Galeriereihenfolge sortiert.
 *
 * Dateien liegen in uploads/artikel/{artikel_id}/ und werden von bild_upload.php verwaltet.
 * Das Repository kennt nur den Dateinamen, nicht den vollständigen Pfad.
 */
class BilderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM artikel_bilder
            WHERE artikel_id = :artikel_id
            ORDER BY position ASC, id ASC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    /**
     * Hauptbild (Position 0) für mehrere Artikel auf einmal -- für Listen-Thumbnails,
     * eine Query statt einer pro Zeile.
     * @param int[] $artikelIds
     * @return array<int,array{dateiname:string,alt_text:string}> artikel_id => Bild
     */
    public function findHauptbilderByArtikelIds(array $artikelIds): array
    {
        $artikelIds = array_values(array_unique(array_filter($artikelIds)));
        if (empty($artikelIds)) return [];

        $platzhalter = implode(',', array_fill(0, count($artikelIds), '?'));
        $stmt = $this->db->prepare("
            SELECT artikel_id, dateiname, alt_text
            FROM artikel_bilder
            WHERE artikel_id IN ($platzhalter)
            ORDER BY position ASC, id ASC
        ");
        $stmt->execute($artikelIds);

        $ergebnis = [];
        foreach ($stmt->fetchAll() as $row) {
            // Erste Zeile pro artikel_id (kleinste position) gewinnt, weitere ignorieren
            if (!isset($ergebnis[$row['artikel_id']])) {
                $ergebnis[$row['artikel_id']] = $row;
            }
        }
        return $ergebnis;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM artikel_bilder WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Fügt ein neues Bild ein. Position = MAX(position)+1 → wird automatisch letztes Bild.
     * Hauptbild kann danach per setzeHauptbild() auf dieses Bild gesetzt werden.
     */
    public function insert(int $artikelId, string $dateiname, string $altText = ''): int
    {
        $naechstePosition = $this->naechstePosition($artikelId);

        $stmt = $this->db->prepare("
            INSERT INTO artikel_bilder (artikel_id, dateiname, alt_text, position)
            VALUES (:artikel_id, :dateiname, :alt_text, :position)
        ");
        $stmt->execute([
            'artikel_id' => $artikelId,
            'dateiname'  => $dateiname,
            'alt_text'   => $altText,
            'position'   => $naechstePosition,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateAltText(int $id, string $altText): void
    {
        $stmt = $this->db->prepare("
            UPDATE artikel_bilder SET alt_text = :alt_text WHERE id = :id
        ");
        $stmt->execute(['alt_text' => $altText, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM artikel_bilder WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /**
     * Setzt dieses Bild als Hauptbild (Position 0).
     * Das bisherige Hauptbild bekommt Position 1, alle anderen rücken nach.
     */
    public function setzeHauptbild(int $id, int $artikelId): void
    {
        // Alle Bilder des Artikels nach Position sortiert holen
        $bilder = $this->findByArtikelId($artikelId);

        // Neue Reihenfolge: gewähltes Bild zuerst, dann alle anderen in bisheriger Reihenfolge
        $neueReihenfolge = [$id];
        foreach ($bilder as $b) {
            if ((int)$b['id'] !== $id) {
                $neueReihenfolge[] = (int)$b['id'];
            }
        }

        $this->speichereReihenfolge($neueReihenfolge);
    }

    /**
     * Tauscht ein Bild mit seinem Nachbarn (richtung: 'hoch' oder 'runter').
     */
    public function verschiebePosition(int $id, int $artikelId, string $richtung): void
    {
        $bilder = $this->findByArtikelId($artikelId);
        $ids = array_column($bilder, 'id');
        $pos = array_search($id, array_map('intval', $ids));

        if ($pos === false) return;

        // pos > 1: ↑ darf nie Position 0 (Hauptbild) überschreiben — nur ☆ darf das
        if ($richtung === 'hoch' && $pos > 1) {
            [$ids[$pos - 1], $ids[$pos]] = [$ids[$pos], $ids[$pos - 1]];
        } elseif ($richtung === 'runter' && $pos > 0 && $pos < count($ids) - 1) {
            [$ids[$pos], $ids[$pos + 1]] = [$ids[$pos + 1], $ids[$pos]];
        }

        $this->speichereReihenfolge($ids);
    }

    private function speichereReihenfolge(array $ids): void
    {
        $stmt = $this->db->prepare("
            UPDATE artikel_bilder SET position = :position WHERE id = :id
        ");
        foreach ($ids as $position => $id) {
            $stmt->execute(['position' => $position, 'id' => $id]);
        }
    }

    private function naechstePosition(int $artikelId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(position) + 1, 0) AS naechste
            FROM artikel_bilder
            WHERE artikel_id = :artikel_id
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return (int)$stmt->fetchColumn();
    }
}
