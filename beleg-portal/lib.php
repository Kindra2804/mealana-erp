<?php
// Belegseite (Paperless) — gemeinsame Funktionen. Eigenständig, ohne WordPress/WooCommerce.
// Gespeichert wird nur der Beleg selbst (PDF + Bon-Nr./Betrag/Datum/Firma/Logo), keine Kundendaten.

const BP_VERSION = '1.0';

function bp_config(): array
{
    static $c = null;
    if ($c === null) {
        $pfad = __DIR__ . '/config.php';
        if (!is_file($pfad)) {
            http_response_code(500);
            exit('Belegseite noch nicht eingerichtet (config.php fehlt).');
        }
        $c = require $pfad;
    }
    return $c;
}

function bp_db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = bp_config()['db'];
    if ($c['typ'] === 'sqlite') {
        if (!is_dir(dirname($c['datei']))) mkdir(dirname($c['datei']), 0750, true);
        $pdo = new PDO('sqlite:' . $c['datei']);
    } else {
        $pdo = new PDO('mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4', $c['benutzer'], $c['passwort']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Tabelle bei Bedarf anlegen — Spaltentypen laufen unter SQLite und MySQL gleich
    $pdo->exec("CREATE TABLE IF NOT EXISTS " . bp_tabelle() . " (
        token        VARCHAR(64)  NOT NULL PRIMARY KEY,
        bon_nr       VARCHAR(40)  NOT NULL,
        betrag       VARCHAR(20)  NOT NULL,
        beleg_datum  VARCHAR(20)  NOT NULL,
        firma        VARCHAR(200) NOT NULL,
        logo         MEDIUMTEXT   NULL,
        erstellt_am  VARCHAR(20)  NOT NULL,
        abgerufen_am VARCHAR(20)  NULL
    )");
    return $pdo;
}

function bp_tabelle(): string
{
    $c = bp_config()['db'];
    return ($c['typ'] === 'mysql' ? preg_replace('/[^a-z0-9_]/i', '', $c['praefix'] ?? 'nahtlos_') : '') . 'belege';
}

function bp_pdf_pfad(string $token): string
{
    return rtrim(bp_config()['ordner'], '/\\') . '/' . $token . '.pdf';
}

function bp_token_gueltig(string $t): bool
{
    return (bool)preg_match('/^[a-f0-9]{48}$/', $t);
}

/** Abgelaufene Belege löschen (läuft bei jedem Aufruf mit — kein Cron am Webspace nötig) */
function bp_aufraeumen(): void
{
    $c = bp_config();
    $grenzeOffen = date('Y-m-d H:i:s', time() - (int)$c['unabgerufen_stunden'] * 3600);
    $grenzeAbger = date('Y-m-d H:i:s', time() - (int)$c['abgerufen_tage'] * 86400);
    $db = bp_db();
    $stmt = $db->prepare("SELECT token FROM " . bp_tabelle() . " WHERE (abgerufen_am IS NULL AND erstellt_am < ?) OR (abgerufen_am IS NOT NULL AND abgerufen_am < ?)");
    $stmt->execute([$grenzeOffen, $grenzeAbger]);
    $del = $db->prepare("DELETE FROM " . bp_tabelle() . " WHERE token = ?");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $token) {
        @unlink(bp_pdf_pfad($token));
        $del->execute([$token]);
    }
}

function bp_beleg(string $token): ?array
{
    if (!bp_token_gueltig($token)) return null;
    $stmt = bp_db()->prepare("SELECT * FROM " . bp_tabelle() . " WHERE token = ?");
    $stmt->execute([$token]);
    $b = $stmt->fetch();
    return ($b && is_file(bp_pdf_pfad($token))) ? $b : null;
}

/** Erster Abruf durch den Kunden (Seite geöffnet oder PDF geladen) */
function bp_als_abgerufen_markieren(string $token): void
{
    bp_db()->prepare("UPDATE " . bp_tabelle() . " SET abgerufen_am = ? WHERE token = ? AND abgerufen_am IS NULL")
           ->execute([date('Y-m-d H:i:s'), $token]);
}

function bp_json(array $daten, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
}

function bp_api_pruefen(): void
{
    $schluessel = $_SERVER['HTTP_X_NAHTLOS_SCHLUESSEL'] ?? '';
    $soll = (string)bp_config()['api_schluessel'];
    if ($soll === '' || str_starts_with($soll, 'HIER-') || !hash_equals($soll, $schluessel)) {
        bp_json(['erfolg' => false, 'fehler' => 'API-Schlüssel ungültig.'], 403);
    }
}
