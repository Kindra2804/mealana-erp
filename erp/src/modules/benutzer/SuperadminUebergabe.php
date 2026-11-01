<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Lizenz.php';
require_once __DIR__ . '/../../core/logger.php';

/**
 * Superadmin-Übergabe per Schlüssel von NAHTLOS (Lizenz-Bau Schritt 3, Konzept Jacky 2026-10-30)
 *
 * Schlüssel = signierte Datei (gleiche Signatur wie die Lizenz) mit typ=superadmin_uebergabe,
 * gebunden an Firmenname + UID/Steuernummer, einmalig (nonce), gültig bis gueltig_bis.
 * Ablauf: Schlüssel prüfen → neuen Superadmin wählen oder anlegen → bisherige Superadmins
 * herabstufen (Rolle wählbar) oder deaktivieren (nie löschen) → Log.
 */
class SuperadminUebergabe
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Rückgabe: ['ok' => bool, 'daten' => array|null, 'fehler' => ?string] */
    public function pruefeSchluessel(string $inhalt): array
    {
        $sig = Lizenz::pruefeSignatur(trim($inhalt));
        if (!$sig['daten']) return ['ok' => false, 'daten' => null, 'fehler' => $sig['fehler']];
        $d = $sig['daten'];

        if (($d['typ'] ?? '') !== 'superadmin_uebergabe' || empty($d['nonce'])) {
            return ['ok' => false, 'daten' => null, 'fehler' => 'Das ist kein Superadmin-Übergabe-Schlüssel.'];
        }
        if (!Lizenz::firmaPasst($d)) {
            return ['ok' => false, 'daten' => null, 'fehler' => 'Der Schlüssel gilt für „' . ($d['firma'] ?? '?') . '“ — nicht für diese Installation.'];
        }
        if (($d['gueltig_bis'] ?? '') < date('Y-m-d')) {
            return ['ok' => false, 'daten' => null, 'fehler' => 'Der Schlüssel ist abgelaufen (gültig bis ' . date('d.m.Y', strtotime($d['gueltig_bis'])) . ').'];
        }
        $stmt = $this->db->prepare("SELECT verwendet_am FROM superadmin_uebergaben WHERE nonce = ?");
        $stmt->execute([$d['nonce']]);
        if ($verwendet = $stmt->fetchColumn()) {
            return ['ok' => false, 'daten' => null, 'fehler' => 'Der Schlüssel wurde bereits am ' . date('d.m.Y H:i', strtotime($verwendet)) . ' verwendet.'];
        }
        return ['ok' => true, 'daten' => $d, 'fehler' => null];
    }

    /** Aktive Benutzer mit ihrer höchsten Rolle */
    public function benutzer(): array
    {
        return $this->db->query("
            SELECT b.id, b.username, b.formularname, b.email,
                   (SELECT r.name FROM benutzer_rollen br JOIN rollen r ON r.id = br.rolle_id
                    WHERE br.benutzer_id = b.id ORDER BY r.rang DESC LIMIT 1) AS rolle,
                   (SELECT MAX(r.rang) FROM benutzer_rollen br JOIN rollen r ON r.id = br.rolle_id
                    WHERE br.benutzer_id = b.id) AS rang
            FROM benutzer b
            WHERE b.aktiv = 1 AND b.username <> 'system'
            ORDER BY rang DESC, b.formularname
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Rollen unterhalb Superadmin (für das Herabstufen) */
    public function rollenUnterSuperadmin(): array
    {
        return $this->db->query("SELECT id, name, rang FROM rollen WHERE rang < 100 ORDER BY rang DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * $ziel:  ['benutzer_id' => int] ODER ['neu' => [username, formularname, email, passwort]]
     * $alte:  [benutzer_id => 'rolle:<id>' | 'deaktivieren'] für alle bisherigen Superadmins außer dem Ziel
     */
    public function durchfuehren(string $schluessel, array $ziel, array $alte, ?string $ip = null): array
    {
        $pruef = $this->pruefeSchluessel($schluessel);
        if (!$pruef['ok']) return ['erfolg' => false, 'fehler' => $pruef['fehler']];
        $d = $pruef['daten'];

        $superId = (int)$this->db->query("SELECT id FROM rollen WHERE name = 'superadmin'")->fetchColumn();
        $erlaubteRollen = array_column($this->rollenUnterSuperadmin(), 'id');

        $this->db->beginTransaction();
        try {
            // Ziel: bestehender Benutzer oder neu anlegen
            if (!empty($ziel['neu'])) {
                $n = $ziel['neu'];
                $username = trim($n['username'] ?? '');
                $formular = trim($n['formularname'] ?? '');
                if ($username === '' || $formular === '') throw new InvalidArgumentException('Benutzername und Anzeigename sind Pflicht.');
                if (strlen($n['passwort'] ?? '') < 8) throw new InvalidArgumentException('Passwort muss mindestens 8 Zeichen haben.');
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM benutzer WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn()) throw new InvalidArgumentException("Benutzername „{$username}“ ist schon vergeben.");
                $this->db->prepare("INSERT INTO benutzer (username, passwort, formularname, email, aktiv) VALUES (?, ?, ?, ?, 1)")
                         ->execute([$username, password_hash($n['passwort'], PASSWORD_DEFAULT), $formular, trim($n['email'] ?? '') ?: null]);
                $zielId = (int)$this->db->lastInsertId();
            } else {
                $zielId = (int)($ziel['benutzer_id'] ?? 0);
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM benutzer WHERE id = ? AND aktiv = 1 AND username <> 'system'");
                $stmt->execute([$zielId]);
                if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Bitte einen aktiven Benutzer wählen oder einen neuen anlegen.');
            }

            // Bisherige Superadmins (außer Ziel): jeder braucht eine Entscheidung
            $bisher = $this->db->prepare("
                SELECT DISTINCT br.benutzer_id FROM benutzer_rollen br
                JOIN benutzer b ON b.id = br.benutzer_id
                WHERE br.rolle_id = ? AND b.aktiv = 1 AND br.benutzer_id <> ?
            ");
            $bisher->execute([$superId, $zielId]);
            $protokoll = [];
            foreach ($bisher->fetchAll(PDO::FETCH_COLUMN) as $altId) {
                $aktion = $alte[$altId] ?? '';
                if ($aktion === 'deaktivieren') {
                    $this->db->prepare("UPDATE benutzer SET aktiv = 0, sitzung_ungueltig_ab = NOW() WHERE id = ?")->execute([$altId]);
                    $this->db->prepare("DELETE FROM benutzer_rollen WHERE benutzer_id = ? AND rolle_id = ?")->execute([$altId, $superId]);
                } elseif (preg_match('/^rolle:(\d+)$/', $aktion, $m) && in_array((int)$m[1], array_map('intval', $erlaubteRollen), true)) {
                    $this->db->prepare("DELETE FROM benutzer_rollen WHERE benutzer_id = ?")->execute([$altId]);
                    $this->db->prepare("INSERT INTO benutzer_rollen (benutzer_id, rolle_id) VALUES (?, ?)")->execute([$altId, (int)$m[1]]);
                    $this->db->prepare("UPDATE benutzer SET sitzung_ungueltig_ab = NOW() WHERE id = ?")->execute([$altId]);
                } else {
                    throw new InvalidArgumentException('Für jeden bisherigen Superadmin bitte „herabstufen“ (mit Rolle) oder „deaktivieren“ wählen.');
                }
                $protokoll[] = ['benutzer_id' => (int)$altId, 'aktion' => $aktion];
            }

            // Ziel wird (einziger) Superadmin; bestehende Anmeldung neu laden lassen
            $this->db->prepare("DELETE FROM benutzer_rollen WHERE benutzer_id = ?")->execute([$zielId]);
            $this->db->prepare("INSERT INTO benutzer_rollen (benutzer_id, rolle_id) VALUES (?, ?)")->execute([$zielId, $superId]);
            $this->db->prepare("UPDATE benutzer SET aktiv = 1, sitzung_ungueltig_ab = NOW() WHERE id = ?")->execute([$zielId]);

            $this->db->prepare("
                INSERT INTO superadmin_uebergaben (nonce, schluessel_nr, neuer_benutzer_id, alte_superadmins, ip_adresse)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$d['nonce'], $d['schluessel_nr'] ?? '?', $zielId, json_encode($protokoll), $ip]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Fehler: ' . $e->getMessage()];
        }

        Logger::log('benutzer.superadmin_uebergabe', 'benutzer', $zielId, [
            'schluessel_nr' => $d['schluessel_nr'] ?? '?', 'alte' => $protokoll, 'ip' => $ip,
        ], $zielId, 'warn');
        return ['erfolg' => true, 'benutzer_id' => $zielId];
    }
}
