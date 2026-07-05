<?php

require_once __DIR__ . '/BenutzerRepository.php';
require_once __DIR__ . '/../../core/Mailer.php';

/**
 * PasswortResetService – Passwort-Setzen-Link (gemeinsamer Mechanismus für
 * "Admin legt Benutzer an" UND "Passwort vergessen" auf der Login-Seite)
 *
 * Sicherheitsprinzipien:
 * - Token wird nie im Klartext gespeichert, nur als SHA-256-Hash (wie ein
 *   Passwort-Hash) — bei DB-Leak kein direkter Konto-Übernahme-Weg.
 * - angefordertFuerEmail() verrät nie, ob eine E-Mail-Adresse existiert
 *   (verhindert User-Enumeration über die öffentliche "Passwort vergessen"-Seite).
 * - Rate-Limiting: pro Benutzer maximal 1 neuer Token alle 5 Minuten.
 */
class PasswortResetService
{
    private const GUELTIGKEIT_STUNDEN = 24;
    private const RATE_LIMIT_MINUTEN  = 5;

    private BenutzerRepository $repo;

    public function __construct()
    {
        $this->repo = new BenutzerRepository();
    }

    /**
     * Öffentlicher Einstiegspunkt für "Passwort vergessen". Sendet einen Link,
     * falls die E-Mail zu einem aktiven Benutzer gehört — verrät aber nach außen
     * nie, ob das der Fall war (Aufrufer zeigt immer dieselbe generische Meldung).
     */
    public function angefordertFuerEmail(string $email): void
    {
        $benutzer = $this->repo->findByEmail(trim($email));
        if ($benutzer) {
            $this->sendeSetzenLink((int)$benutzer['id'], $benutzer['email']);
        }
    }

    /**
     * Erzeugt einen neuen Token und verschickt den Passwort-Setzen-Link.
     * Rate-Limiting: überspringt stillschweigend, wenn erst vor Kurzem einer
     * ausgestellt wurde (verhindert Mail-Spam bei mehrfachem Klick/Missbrauch).
     */
    public function sendeSetzenLink(int $benutzerId, string $email): void
    {
        $letzter = $this->repo->findLetztenTokenZeitpunkt($benutzerId);
        if ($letzter !== null) {
            $sekundenSeitLetztem = time() - strtotime($letzter);
            if ($sekundenSeitLetztem < self::RATE_LIMIT_MINUTEN * 60) {
                return;
            }
        }

        $tokenKlartext = bin2hex(random_bytes(32));
        $tokenHash     = hash('sha256', $tokenKlartext);
        $laeuftAbAm    = date('Y-m-d H:i:s', strtotime('+' . self::GUELTIGKEIT_STUNDEN . ' hours'));

        $this->repo->insertToken($benutzerId, $tokenHash, $laeuftAbAm);

        (new Mailer())->sendeTemplate(
            $email,
            'Passwort setzen',
            'mails/passwort_setzen.html.twig',
            [
                'link'               => $this->baueLink($tokenKlartext),
                'gueltigkeit_stunden'=> self::GUELTIGKEIT_STUNDEN,
            ]
        );
    }

    /**
     * Prüft einen Token (Klartext aus der URL). Gibt den Token-Datensatz zurück
     * (inkl. benutzer_id/username) wenn gültig, sonst false.
     */
    public function validiereToken(string $tokenKlartext): array|false
    {
        $hash = hash('sha256', $tokenKlartext);
        return $this->repo->findGueltigenTokenByHash($hash);
    }

    /** Setzt ein neues Passwort anhand eines gültigen Tokens und markiert ihn als verwendet. */
    public function setzeNeuesPasswort(string $tokenKlartext, string $neuesPasswort, string $wiederholung): array
    {
        $token = $this->validiereToken($tokenKlartext);
        if (!$token) {
            return ['erfolg' => false, 'fehler' => ['Der Link ist ungültig oder abgelaufen.']];
        }
        if (!$token['benutzer_aktiv']) {
            return ['erfolg' => false, 'fehler' => ['Dieser Benutzer ist deaktiviert.']];
        }
        if (strlen($neuesPasswort) < 8) {
            return ['erfolg' => false, 'fehler' => ['Passwort muss mindestens 8 Zeichen haben.']];
        }
        if ($neuesPasswort !== $wiederholung) {
            return ['erfolg' => false, 'fehler' => ['Passwort und Bestätigung stimmen nicht überein.']];
        }

        $this->repo->setPasswort((int)$token['benutzer_id'], password_hash($neuesPasswort, PASSWORD_BCRYPT));
        $this->repo->markiereTokenVerwendet((int)$token['id']);

        return ['erfolg' => true];
    }

    /** Baut den absoluten Link für die Mail — BASE_PATH ist relativ, Mails brauchen die volle URL. */
    private function baueLink(string $tokenKlartext): string
    {
        $schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$schema}://{$host}" . BASE_PATH . '/passwort_setzen.php?token=' . $tokenKlartext;
    }
}
