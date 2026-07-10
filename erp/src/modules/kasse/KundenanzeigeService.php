<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * KundenanzeigeService – Live-Status für den Zweitbildschirm neben der Kasse.
 *
 * Die Kasse (bon.php) schreibt bei jeder Warenkorb-Änderung ihren aktuellen Stand
 * über schreibeStatus(), das Kundenanzeige-Tablet liest ihn per Polling über
 * leseStatus() — eine Zeile pro Kasse, kein Verlauf. Bewusst DB-Polling statt
 * WebSocket: kein zusätzlicher Serverprozess nötig, passt zum bestehenden Muster
 * (Arbeitsplatz-Heartbeat, kassen_geparkte_bons).
 */
class KundenanzeigeService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Schreibt den aktuellen Anzeige-Zustand einer Kasse (Upsert). */
    public function schreibeStatus(int $kasseId, string $zustand, array $payload): void
    {
        if (!in_array($zustand, ['idle', 'warenkorb', 'abrechnen'], true)) return;

        $this->db->prepare("
            INSERT INTO kassen_live_state (kasse_id, zustand, payload, aktualisiert_am)
            VALUES (:kid, :zustand, :payload, NOW())
            ON DUPLICATE KEY UPDATE zustand = :zustand2, payload = :payload2, aktualisiert_am = NOW()
        ")->execute([
            ':kid'      => $kasseId,
            ':zustand'  => $zustand,
            ':payload'  => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':zustand2' => $zustand,
            ':payload2' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Liest den aktuellen Anzeige-Zustand einer Kasse für das Kundenanzeige-Tablet.
     * Reichert den Warenkorb-Zustand um das Hauptbild des zuletzt gescannten Artikels an
     * (die Kasse selbst kennt keine Bildpfade, das bleibt bewusst Server-Logik).
     */
    public function leseStatus(int $kasseId): array
    {
        $stmt = $this->db->prepare("SELECT zustand, payload, aktualisiert_am FROM kassen_live_state WHERE kasse_id = :kid");
        $stmt->execute([':kid' => $kasseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['zustand' => 'idle', 'payload' => new stdClass()];
        }

        $payload = json_decode($row['payload'] ?? '{}', true) ?: [];

        if ($row['zustand'] === 'warenkorb' && !empty($payload['artikel_id'])) {
            $bStmt = $this->db->prepare("
                SELECT dateiname FROM artikel_bilder
                WHERE artikel_id = :aid AND position = 0
                LIMIT 1
            ");
            $bStmt->execute([':aid' => $payload['artikel_id']]);
            $dateiname = $bStmt->fetchColumn();
            $payload['artikel_bild'] = $dateiname
                ? BASE_PATH . '/uploads/artikel/' . $payload['artikel_id'] . '/' . $dateiname
                : null;
        }

        return [
            'zustand'         => $row['zustand'],
            'payload'         => $payload,
            'aktualisiert_am' => $row['aktualisiert_am'],
        ];
    }
}
