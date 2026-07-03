<?php
require_once __DIR__ . '/Database.php';

/**
 * Auth – Session-basierte Authentifizierung und Berechtigungsprüfung
 *
 * Verwaltet Login/Logout und lädt beim Login alle Berechtigungen des
 * Benutzers in die Session. Berechtigungen haben das Format
 * "modul.aktion" (z.B. "artikel.bearbeiten", "lager.eingang").
 *
 * Verwendung in Views:
 *   Auth::check()       → void, leitet auf Login um wenn nicht eingeloggt
 *   Auth::kann('...')   → bool, ob aktuelle Berechtigung vorhanden
 *   Auth::benutzer()    → Array mit id, username, formularname
 *
 * Auth-Guard auf jeder geschützten Seite:
 *   require_once __DIR__ . '/includes/auth_check.php';
 */
class Auth
{
    /**
     * Versucht den Login mit Benutzername und Passwort.
     *
     * Ablauf:
     * 1. Benutzer per Username aus DB laden (nur aktive)
     * 2. Passwort mit bcrypt-Hash vergleichen (password_verify)
     * 3. Session regenerieren (verhindert Session-Fixation-Angriffe)
     * 4. Benutzerdaten + alle Berechtigungen in $_SESSION laden
     *
     * @return bool true bei Erfolg, false bei falschem Passwort/User
     */
    public static function login(string $username, string $passwort): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT
                u.id,
                u.username,
                u.passwort,
                u.formularname
            FROM benutzer u
            WHERE u.aktiv = 1 AND u.username = :username
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($passwort, $user['passwort'])) {

            // Neue Session-ID generieren — schützt vor Session-Fixation
            session_regenerate_id(true);

            // Passwort-Hash niemals in die Session schreiben
            unset($user['passwort']);
            $_SESSION['benutzer'] = $user;

            $id = $user['id'];

            // Alle Berechtigungen über Rollen-Join laden
            $stmt = $db->prepare("
            SELECT
                u.vorname,
                u.nachname,
                u.formularname,
                u.email,
                b.name AS berechtigung_name,
                b.beschreibung AS berechtigung_beschreibung
            FROM benutzer u
            INNER JOIN benutzer_rollen br ON br.benutzer_id = u.id
            INNER JOIN rollen r ON r.id = br.rolle_id
            INNER JOIN rollen_berechtigungen rb ON rb.rolle_id = r.id
            INNER JOIN berechtigungen b ON b.id = rb.berechtigung_id
            WHERE u.id = :id AND
            b.aktiv = 1
            ");
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Nur die Berechtigungs-Strings in die Session — nicht die ganzen Zeilen
            $berechtigungen = array_column($result, 'berechtigung_name');

            $_SESSION['berechtigungen'] = $berechtigungen;
            return true;
        }



        return false;
    }

    /**
     * Beendet die Session und leitet auf die Login-Seite um.
     * session_destroy() löscht alle Session-Daten serverseitig.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        // Session-Cookie im Browser aktiv löschen (nicht nur serverseitig)
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }

    /**
     * Auth-Guard: Prüft ob ein Benutzer eingeloggt ist.
     * Leitet bei fehlender Session sofort auf Login um (exit danach).
     * Verwendung: Auth::check() ganz oben in jeder geschützten Seite.
     */
    public static function check(): void
    {
        if (empty($_SESSION['benutzer']['id'])) {
            header('Location: ' . BASE_PATH . '/login.php');
            exit;
        }
    }

    /**
     * Prüft ob der eingeloggte Benutzer eine bestimmte Berechtigung hat.
     *
     * @param string $berechtigung Format: "modul.aktion" (z.B. "artikel.bearbeiten")
     */
    public static function kann(string $berechtigung): bool
    {
        return in_array($berechtigung, $_SESSION['berechtigungen'] ?? []);
    }

    /**
     * Gibt die Session-Daten des eingeloggten Benutzers zurück.
     * Enthält: id, username, formularname.
     */
    public static function benutzer(): array
    {
        return $_SESSION['benutzer'] ?? [];
    }
}
