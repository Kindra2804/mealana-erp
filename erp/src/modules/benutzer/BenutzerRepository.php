<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * BenutzerRepository – CRUD für Benutzer-Stammdaten + Rollen-Zuweisung
 *
 * Ein Benutzer hat genau eine Rolle (benutzer_rollen ist zwar n:m angelegt,
 * wird hier aber bewusst 1:1 gehandhabt — bei jedem Speichern wird die alte
 * Zuweisung gelöscht und die neue eingefügt).
 *
 * Der Systembenutzer "system" (Jarvis, siehe Migration 105) wird in findAll()
 * bewusst ausgeblendet — kein echter Login-Kandidat, nur für automatische
 * Log-Einträge gedacht.
 *
 * Löschen = kein echtes DELETE; stattdessen aktiv = 0 über setAktiv().
 */
class BenutzerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Gibt alle Benutzer zurück (ohne den Systembenutzer "system"), mit Rollenname. */
    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT b.*, r.id AS rolle_id, r.name AS rolle_name
            FROM benutzer b
            LEFT JOIN benutzer_rollen br ON br.benutzer_id = b.id
            LEFT JOIN rollen r ON r.id = br.rolle_id
            WHERE b.username != 'system'
            ORDER BY b.formularname
        ");
        return $stmt->fetchAll();
    }

    /** Gibt einen Benutzer anhand ID zurück (mit Rolle), oder false wenn nicht gefunden. */
    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT b.*, r.id AS rolle_id, r.name AS rolle_name
            FROM benutzer b
            LEFT JOIN benutzer_rollen br ON br.benutzer_id = b.id
            LEFT JOIN rollen r ON r.id = br.rolle_id
            WHERE b.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /** Prüft ob ein Benutzername bereits vergeben ist (optional: außer bei dieser ID, für Bearbeiten). */
    public function usernameExistiert(string $username, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM benutzer WHERE username = :username';
        $params = ['username' => $username];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Gibt alle Rollen zurück (für Dropdown). */
    public function findAlleRollen(): array
    {
        $stmt = $this->db->query("SELECT id, name FROM rollen WHERE aktiv = 1 ORDER BY id");
        return $stmt->fetchAll();
    }

    /**
     * Legt einen neuen Benutzer an. $data['passwort_hash'] muss bereits gehasht sein
     * (Platzhalter-Hash bei Link-Versand, echter Hash bei Direkt-Setzen).
     */
    public function insert(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO benutzer (username, passwort, vorname, nachname, formularname, email, aktiv)
            VALUES (:username, :passwort, :vorname, :nachname, :formularname, :email, :aktiv)
        ');
        $stmt->execute([
            'username'     => $data['username'],
            'passwort'     => $data['passwort_hash'],
            'vorname'      => $data['vorname'],
            'nachname'     => $data['nachname'],
            'formularname' => $data['formularname'],
            'email'        => $data['email'],
            'aktiv'        => $data['aktiv'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Aktualisiert Stammdaten eines Benutzers (kein Passwort — das läuft über den Reset-Flow bzw. profil.php). */
    public function update(array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE benutzer SET
                vorname      = :vorname,
                nachname     = :nachname,
                formularname = :formularname,
                email        = :email,
                aktiv        = :aktiv
            WHERE id = :id
        ');
        $stmt->execute([
            'vorname'      => $data['vorname'],
            'nachname'     => $data['nachname'],
            'formularname' => $data['formularname'],
            'email'        => $data['email'],
            'aktiv'        => $data['aktiv'],
            'id'           => $data['id'],
        ]);
        return $stmt->rowCount() > 0;
    }

    /** Setzt die Rolle eines Benutzers (löscht alte Zuweisung, fügt neue ein — bewusst 1:1). */
    public function setRolle(int $benutzerId, int $rolleId): void
    {
        $this->db->prepare('DELETE FROM benutzer_rollen WHERE benutzer_id = :id')->execute(['id' => $benutzerId]);
        $this->db->prepare('INSERT INTO benutzer_rollen (benutzer_id, rolle_id) VALUES (:bid, :rid)')
                  ->execute(['bid' => $benutzerId, 'rid' => $rolleId]);
    }

    /** Setzt den Aktiv-Status eines Benutzers (1 = aktiv, 0 = inaktiv). */
    public function setAktiv(int $id, int $aktiv): bool
    {
        $stmt = $this->db->prepare('UPDATE benutzer SET aktiv = :aktiv WHERE id = :id');
        $stmt->execute(['aktiv' => $aktiv, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Setzt das Passwort eines Benutzers (bereits gehasht). */
    public function setPasswort(int $id, string $hash): void
    {
        $this->db->prepare('UPDATE benutzer SET passwort = :pw WHERE id = :id')
                  ->execute(['pw' => $hash, 'id' => $id]);
    }

    /** Gibt einen aktiven Benutzer anhand E-Mail zurück, oder false wenn nicht gefunden. */
    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT id, email, aktiv FROM benutzer WHERE email = :email AND aktiv = 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    // -------------------------------------------------------------------------
    // Passwort-Tokens
    // -------------------------------------------------------------------------

    /** Legt einen neuen Passwort-Token an. */
    public function insertToken(int $benutzerId, string $tokenHash, string $laeuftAbAm): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO benutzer_passwort_tokens (benutzer_id, token_hash, laeuft_ab_am)
            VALUES (:bid, :hash, :ablauf)
        ');
        $stmt->execute(['bid' => $benutzerId, 'hash' => $tokenHash, 'ablauf' => $laeuftAbAm]);
    }

    /** Gibt den Zeitpunkt des zuletzt ausgestellten Tokens für einen Benutzer zurück (für Rate-Limiting), oder null. */
    public function findLetztenTokenZeitpunkt(int $benutzerId): ?string
    {
        $stmt = $this->db->prepare('
            SELECT ausgestellt_am FROM benutzer_passwort_tokens
            WHERE benutzer_id = :bid ORDER BY ausgestellt_am DESC LIMIT 1
        ');
        $stmt->execute(['bid' => $benutzerId]);
        $wert = $stmt->fetchColumn();
        return $wert === false ? null : $wert;
    }

    /** Gibt einen gültigen (nicht abgelaufenen, nicht verwendeten) Token anhand Hash zurück, oder false. */
    public function findGueltigenTokenByHash(string $tokenHash): array|false
    {
        $stmt = $this->db->prepare('
            SELECT t.*, b.username, b.aktiv AS benutzer_aktiv
            FROM benutzer_passwort_tokens t
            INNER JOIN benutzer b ON b.id = t.benutzer_id
            WHERE t.token_hash = :hash
              AND t.verwendet_am IS NULL
              AND t.laeuft_ab_am >= NOW()
        ');
        $stmt->execute(['hash' => $tokenHash]);
        return $stmt->fetch();
    }

    /** Markiert einen Token als verwendet. */
    public function markiereTokenVerwendet(int $tokenId): void
    {
        $this->db->prepare('UPDATE benutzer_passwort_tokens SET verwendet_am = NOW() WHERE id = :id')
                  ->execute(['id' => $tokenId]);
    }
}
