<?php
require_once __DIR__ . '/../../core/Database.php';

/**
 * ArbeitsplatzRepository – reiner DB-Zugriff auf `arbeitsplaetze` + die dafür
 * relevanten Spalten auf `sessions`. Siehe ArbeitsplatzService für die eigentliche
 * Logik (Auswahl, Kollisions-Check, BFR-Sonderregel).
 */
class ArbeitsplatzRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM arbeitsplaetze WHERE geraete_token = :t AND aktiv = 1");
        $stmt->execute(['t' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByKasseId(int $kasseId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM arbeitsplaetze WHERE kasse_id = :k AND aktiv = 1");
        $stmt->execute(['k' => $kasseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Kassen, die noch nicht RKSV-aktiv sind — nur diese dürfen frei ausgewählt
     * werden. Sobald bfr_aktiv_seit gesetzt ist, bindet sich die Kasse nur noch
     * automatisch bei Abschluss der BFR-Registrierung (siehe ArbeitsplatzService).
     */
    public function findAuswaehlbareKassen(): array
    {
        return $this->db->query("
            SELECT k.id, k.name, k.kasse_nr
            FROM kassen k
            WHERE k.aktiv = 1 AND k.bfr_aktiv_seit IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM arbeitsplaetze a WHERE a.kasse_id = k.id AND a.aktiv = 1
              )
            ORDER BY k.name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $daten): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO arbeitsplaetze (name, geraete_token, typ, kasse_id, aktiv)
            VALUES (:name, :token, :typ, :kasse_id, 1)
        ");
        $stmt->execute([
            'name'     => $daten['name'],
            'token'    => $daten['geraete_token'],
            'typ'      => $daten['typ'],
            'kasse_id' => $daten['kasse_id'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function bindeSession(string $sessionId, int $arbeitsplatzId, string $token): void
    {
        $stmt = $this->db->prepare("
            UPDATE sessions SET arbeitsplatz_id = :a, geraete_token = :t WHERE id = :id
        ");
        $stmt->execute(['a' => $arbeitsplatzId, 't' => $token, 'id' => $sessionId]);
    }

    /**
     * Andere Session, die denselben Arbeitsplatz belegt und noch als "lebendig"
     * gilt (letzte_aktivitaet jünger als $timeoutMinuten). $timeoutMinuten kommt
     * nur aus dem Service (fester Konstanten-Wert), daher unbedenklich direkt
     * interpoliert statt gebunden — PDO erlaubt keinen Parameter in INTERVAL.
     */
    public function findAndereAktiveSession(int $arbeitsplatzId, string $eigeneSessionId, int $timeoutMinuten): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.id, s.letzte_aktivitaet, b.formularname
            FROM sessions s
            INNER JOIN benutzer b ON b.id = s.benutzer_id
            WHERE s.arbeitsplatz_id = :a
              AND s.id != :eigene
              AND s.letzte_aktivitaet > (NOW() - INTERVAL {$timeoutMinuten} MINUTE)
            LIMIT 1
        ");
        $stmt->execute(['a' => $arbeitsplatzId, 'eigene' => $eigeneSessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * kasse_id des Arbeitsplatzes, an den die aktuelle Session gebunden ist —
     * NULL wenn ungebunden oder der Arbeitsplatz kein typ='kasse' ist.
     */
    public function findKasseIdFuerSession(string $sessionId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT a.kasse_id
            FROM sessions s
            INNER JOIN arbeitsplaetze a ON a.id = s.arbeitsplatz_id
            WHERE s.id = :sid AND a.aktiv = 1 AND a.kasse_id IS NOT NULL
        ");
        $stmt->execute(['sid' => $sessionId]);
        $wert = $stmt->fetchColumn();
        return $wert !== false ? (int)$wert : null;
    }

    /** Anzahl noch "lebendiger" Sessions an diesem Arbeitsplatz (für Warnung vor Bindung-lösen). */
    public function zaehleAktiveSessions(int $arbeitsplatzId, int $timeoutMinuten): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM sessions
            WHERE arbeitsplatz_id = :a AND letzte_aktivitaet > (NOW() - INTERVAL {$timeoutMinuten} MINUTE)
        ");
        $stmt->execute(['a' => $arbeitsplatzId]);
        return (int)$stmt->fetchColumn();
    }

    public function loescheSession(string $sessionId): void
    {
        $this->db->prepare("DELETE FROM sessions WHERE id = :id")->execute(['id' => $sessionId]);
    }

    /**
     * Hardware-Wechsel: löst die Bindung an eine Kasse, statt die Zeile zu löschen
     * (sessions.arbeitsplatz_id hat eine FK-Sperre gegen harte Löschung, und ein
     * deaktivierter Datensatz bleibt für die Aktivitäten-Historie nachvollziehbar).
     */
    public function deaktiviereFuerKasse(int $kasseId): void
    {
        $this->db->prepare("UPDATE arbeitsplaetze SET kasse_id = NULL, aktiv = 0 WHERE kasse_id = :k")
            ->execute(['k' => $kasseId]);
    }
}
