<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Zugriffsregeln.php';
require_once __DIR__ . '/Lizenz.php';
require_once __DIR__ . '/logger.php';

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
            $_SESSION['angemeldet_am'] = time();   // für benutzer.sitzung_ungueltig_ab (Superadmin-Übergabe)

            $id = $user['id'];

            // Eigene Rolle + Rang laden — bestimmt in der Rollen-Matrix-UI, wessen
            // Rechte dieser Benutzer bearbeiten darf (nur echt niedrigerer Rang).
            $rolleStmt = $db->prepare("
                SELECT r.id AS rolle_id, r.rang
                FROM benutzer_rollen br
                INNER JOIN rollen r ON r.id = br.rolle_id
                WHERE br.benutzer_id = :id
                ORDER BY r.rang DESC
                LIMIT 1
            ");
            $rolleStmt->execute(['id' => $id]);
            $rolle = $rolleStmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['benutzer']['rolle_id'] = $rolle['rolle_id'] ?? null;
            $_SESSION['benutzer']['rolle_rang'] = (int)($rolle['rang'] ?? 0);

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

            self::registriereSession($id);

            return true;
        }



        return false;
    }

    /**
     * Legt beim Login eine Zeile in `sessions` an bzw. aktualisiert sie, falls die
     * (durch session_regenerate_id() neu vergebene) Session-ID unerwartet schon
     * existiert. Grundlage für Arbeitsplatz-Bindung und künftige Session-Limits
     * (siehe project_kassen_verwaltung / project_rechte_rollen in den Notizen).
     */
    private static function registriereSession(int $benutzerId): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO sessions (id, benutzer_id, ip_adresse, user_agent, letzte_aktivitaet, erstellt_am)
            VALUES (:id, :benutzer_id, :ip, :ua, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                benutzer_id = VALUES(benutzer_id),
                ip_adresse = VALUES(ip_adresse),
                user_agent = VALUES(user_agent),
                letzte_aktivitaet = NOW()
        ");
        $stmt->execute([
            'id'          => session_id(),
            'benutzer_id' => $benutzerId,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'          => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }

    /**
     * Aktualisiert `letzte_aktivitaet` der aktuellen Session — der Heartbeat, an dem
     * spätere Kollisions-Checks (Arbeitsplatz bereits aktiv?) erkennen, ob eine
     * andere Session noch wirklich lebt oder nur nie sauber abgemeldet wurde.
     * Aufgerufen aus auth_check.php, direkt nach Auth::check().
     */
    public static function heartbeat(): void
    {
        if (empty($_SESSION['benutzer']['id'])) {
            return;
        }
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE sessions SET letzte_aktivitaet = NOW() WHERE id = :id");
        $stmt->execute(['id' => session_id()]);

        // Anmeldung beenden, wenn der Benutzer seither deaktiviert/umgestellt wurde
        // (z.B. abgelöster Superadmin, siehe SuperadminUebergabe)
        $stmt = $db->prepare("SELECT aktiv, UNIX_TIMESTAMP(sitzung_ungueltig_ab) FROM benutzer WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['benutzer']['id']]);
        [$aktiv, $ungueltigAb] = $stmt->fetch(PDO::FETCH_NUM) ?: [0, null];
        if (!$aktiv || ($ungueltigAb !== null && (int)$ungueltigAb >= (int)($_SESSION['angemeldet_am'] ?? 0))) {
            self::logout();
        }
    }

    /**
     * Beendet die Session und leitet auf die Login-Seite um.
     * session_destroy() löscht alle Session-Daten serverseitig.
     */
    public static function logout(): void
    {
        Database::getInstance()->prepare("DELETE FROM sessions WHERE id = :id")->execute(['id' => session_id()]);

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
     * Superadmin hat IMMER alles — unabhängig von rollen_berechtigungen. Das ist
     * bewusst ein Code-Invariant statt einer Daten-Abhängigkeit: sonst müsste bei
     * jeder neuen Berechtigung daran gedacht werden, sie auch Superadmin explizit
     * zuzuweisen (Ausnahme: lizenz.verwalten bleibt trotzdem exklusiv Superadmin,
     * das ändert diese Regel nicht — sie macht Superadmin nur zusätzlich robust
     * gegen vergessene Zuweisungen bei künftigen neuen Berechtigungen).
     *
     * @param string $berechtigung Format: "modul.aktion" (z.B. "artikel.bearbeiten")
     */
    public static function kann(string $berechtigung): bool
    {
        if (($_SESSION['benutzer']['rolle_rang'] ?? 0) >= 100) {
            return true;
        }
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

    /**
     * Seiten-Rechtecheck: schlägt in Zugriffsregeln nach, welche Berechtigung
     * das aufgerufene Skript braucht, und blockt bei Bedarf. Wird von
     * auth_check.php auf jeder Seite direkt nach check() aufgerufen.
     *
     * Kein Eintrag in Zugriffsregeln → keine Blockade (nur Login-Pflicht wie bisher).
     * Fehlende Berechtigung → JSON-Fehler (AJAX-Endpunkte) oder Redirect auf
     * zugriff_verweigert.php (normale Seiten).
     */
    public static function pruefeSeite(): void
    {
        $publicRoot = realpath(__DIR__ . '/../../public');
        $scriptDir  = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
        $datei      = basename($_SERVER['SCRIPT_FILENAME']);

        if ($publicRoot === false || $scriptDir === false || strpos($scriptDir, $publicRoot) !== 0) {
            return;
        }
        $verzeichnis = ltrim(str_replace('\\', '/', substr($scriptDir, strlen($publicRoot))), '/');

        // Update läuft (Wartungsmodus): nur der Superadmin arbeitet weiter, alle anderen sehen einen Hinweis
        if (is_file(__DIR__ . '/../../storage/wartung.json') && ($_SESSION['benutzer']['rolle_rang'] ?? 0) < 100) {
            http_response_code(503);
            if (Zugriffsregeln::istJsonEndpunkt($verzeichnis, $datei)) {
                header('Content-Type: application/json');
                echo json_encode(['erfolg' => false, 'fehler' => 'Update läuft — bitte in ein paar Minuten noch einmal versuchen.']);
            } else {
                echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta http-equiv="refresh" content="30"><title>Update läuft</title></head>'
                   . '<body style="font-family:sans-serif;background:#1e2a38;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0">'
                   . '<div style="background:#fff;border-radius:12px;padding:32px 36px;max-width:420px;text-align:center">'
                   . '<div style="font-size:32px">🔄</div><h2>Update läuft</h2><p style="color:#64748b">Das ERP wird gerade aktualisiert. '
                   . 'Die Seite lädt sich alle 30 Sekunden neu und geht weiter, sobald das Update fertig ist.</p></div></body></html>';
            }
            exit;
        }

        // Einrichtung: ohne die Pflicht-Schritte (Firma, Superadmin, Lizenz, Steuer, Lager) nur der Assistent
        require_once __DIR__ . '/../modules/einrichtung/OnboardingService.php';
        if (!OnboardingService::seiteFrei($verzeichnis, $datei) && !OnboardingService::pflichtErledigtGecacht()) {
            if (Zugriffsregeln::istJsonEndpunkt($verzeichnis, $datei)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['erfolg' => false, 'fehler' => 'Die Einrichtung ist noch nicht abgeschlossen.']);
                exit;
            }
            header('Location: ' . BASE_PATH . '/einrichtung/index.php');
            exit;
        }

        // Lizenz: Seite gehört zu einem nicht gekauften Modul (Lese-Seiten bleiben erreichbar)
        if (!Lizenz::seiteErlaubt($verzeichnis, $datei)) {
            $modul = Lizenz::modulFuerSeite($verzeichnis, $datei);
            if (Zugriffsregeln::istJsonEndpunkt($verzeichnis, $datei)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['erfolg' => false, 'fehler' => 'Modul „' . Lizenz::label($modul) . '“ ist nicht lizenziert.']);
                exit;
            }
            header('Location: ' . BASE_PATH . '/lizenz/nicht_lizenziert.php?modul=' . urlencode($modul));
            exit;
        }

        $benoetigt =Zugriffsregeln::benoetigteBerechtigung($verzeichnis, $datei);
        if ($benoetigt === null || self::kann($benoetigt)) {
            return;
        }

        Logger::log('system.zugriff_verweigert', null, null, [
            'seite'     => $verzeichnis . '/' . $datei,
            'benoetigt' => $benoetigt,
        ], null, 'warn');

        if (Zugriffsregeln::istJsonEndpunkt($verzeichnis, $datei)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['erfolg' => false, 'fehler' => 'Keine Berechtigung für diese Aktion.']);
            exit;
        }

        header('Location: ' . BASE_PATH . '/zugriff_verweigert.php');
        exit;
    }

    /**
     * Fallback-Ziel für Benutzer ohne dashboard.zugriff (z.B. Praktikant).
     * Reihenfolge = grobe Priorität der Module; benutzer/profil.php am Ende
     * ist immer erreichbar (kein Rechte-Eintrag), damit niemand ganz ohne
     * Ziel dasteht.
     *
     * @return string Absoluter Pfad ab BASE_PATH, z.B. "/artikel/liste.php"
     */
    public static function startseiteFuerBenutzer(): string
    {
        $kandidaten = [
            'artikel.anzeigen'     => '/artikel/liste.php',
            'lager.anzeigen'       => '/lager/uebersicht.php',
            'kunden.anzeigen'      => '/kunden/liste.php',
            'auftraege.anzeigen'   => '/auftraege/liste.php',
            'bestellwesen.anzeigen' => '/bestellungen/liste.php',
            'partner.anzeigen'     => '/partner/liste.php',
            'buchhaltung.anzeigen' => '/buchhaltung/artikel_gruppen.php',
            'einstellungen.anzeigen' => '/einstellungen/index.php',
            'benutzer.anzeigen'    => '/benutzer/liste.php',
        ];
        foreach ($kandidaten as $berechtigung => $ziel) {
            if (self::kann($berechtigung)) {
                return $ziel;
            }
        }
        return '/benutzer/profil.php';
    }

    /**
     * Manager-Override: prüft einen eingegebenen PIN gegen alle aktiven Benutzer
     * mit Rolle Manager+ (rang >= 70), die einen PIN gesetzt haben.
     *
     * Bewusst ohne Benutzername — an der Kasse/Packplatz soll der Manager nur
     * seinen PIN eintippen, nicht sich selbst erst auswählen müssen.
     *
     * @return array{id:int, formularname:string}|null Der Manager bei Erfolg, sonst null
     */
    public static function pruefeManagerPin(string $pin): ?array
    {
        $pin = trim($pin);
        if ($pin === '') {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->query("
            SELECT DISTINCT u.id, u.formularname, u.manager_pin_hash
            FROM benutzer u
            INNER JOIN benutzer_rollen br ON br.benutzer_id = u.id
            INNER JOIN rollen r ON r.id = br.rolle_id
            WHERE u.aktiv = 1 AND u.manager_pin_hash IS NOT NULL AND r.rang >= 70
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (password_verify($pin, $row['manager_pin_hash'])) {
                return ['id' => (int)$row['id'], 'formularname' => $row['formularname']];
            }
        }
        return null;
    }
}
