<?php
require_once __DIR__ . '/Database.php';

class Auth
{
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

            session_regenerate_id(true);
            unset($user['passwort']);
            $_SESSION['benutzer'] = $user;

            $id = $user['id'];

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
            $berechtigungen = array_column($result, 'berechtigung_name');

            $_SESSION['berechtigungen'] = $berechtigungen;
            return true;
        }



        return false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: /mealana/login.php');
        exit;
    }

    public static function check(): void
    {
        if (empty($_SESSION['benutzer']['id'])) {
            header('Location: /mealana/login.php');
            exit;
        }
    }

    public static function kann(string $berechtigung): bool
    {
        return in_array($berechtigung, $_SESSION['berechtigungen'] ?? []);
    }

    public static function benutzer(): array
    {
        return $_SESSION['benutzer'] ?? [];
    }
}
