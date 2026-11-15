<?php
// Newsletter auf der Belegseite (beleg.<domain>) — öffentlicher Teil für Anmeldung, Bestätigung (Double-Opt-in)
// und Abmeldung. Bewusst datensparsam: Die Empfängerliste bleibt im ERP. Hier liegen nur
//   nl_shops       — Name/Adresse/Farbe der Shops (für die Seiten, vom ERP gesetzt)
//   nl_tokens      — zufällige Link-Schlüssel + Status (ohne E-Mail), vom ERP gesetzt
//   nl_ereignisse  — Warteschlange (Anmeldung mit E-Mail, Bestätigung, Abmeldung); das ERP holt sie alle
//                    15 Minuten ab und lässt sie danach hier löschen (api.php nl_quittieren)
//   nl_links       — Zähl-Links der Newsletter (Schlüssel → Ziel-Adresse + Klick-Summe, ohne Empfänger)
//   Bilder der Newsletter liegen im Ordner <ordner>/nl_bilder (Name = SHA-1 des Inhalts), ausgeliefert über ?i=
require_once __DIR__ . '/lib.php';

function nl_tab(string $name): string
{
    $c = bp_config()['db'];
    return ($c['typ'] === 'mysql' ? preg_replace('/[^a-z0-9_]/i', '', $c['praefix'] ?? 'nahtlos_') : '') . $name;
}

function nl_db(): PDO
{
    static $fertig = false;
    $db = bp_db();
    if ($fertig) return $db;
    $mysql = bp_config()['db']['typ'] === 'mysql';
    $autoId = $mysql ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $db->exec("CREATE TABLE IF NOT EXISTS " . nl_tab('nl_shops') . " (
        slug VARCHAR(50) NOT NULL PRIMARY KEY, name VARCHAR(200) NOT NULL, url VARCHAR(255) NULL,
        farbe VARCHAR(20) NULL, aktualisiert VARCHAR(20) NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS " . nl_tab('nl_tokens') . " (
        token VARCHAR(64) NOT NULL PRIMARY KEY, shop VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL, aktualisiert VARCHAR(20) NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS " . nl_tab('nl_ereignisse') . " (
        id $autoId, typ VARCHAR(20) NOT NULL, shop VARCHAR(50) NULL, token VARCHAR(64) NULL,
        email VARCHAR(255) NULL, vorname VARCHAR(100) NULL, ip VARCHAR(45) NULL, zeit VARCHAR(20) NOT NULL,
        info TEXT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS " . nl_tab('nl_links') . " (
        schluessel VARCHAR(16) NOT NULL PRIMARY KEY, url VARCHAR(1000) NOT NULL,
        klicks INT NOT NULL DEFAULT 0, aktualisiert VARCHAR(20) NOT NULL)");
    $fertig = true;
    return $db;
}

function nl_shop(string $slug): ?array
{
    $st = nl_db()->prepare("SELECT * FROM " . nl_tab('nl_shops') . " WHERE slug = ?");
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function nl_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $st = nl_db()->prepare("SELECT * FROM " . nl_tab('nl_tokens') . " WHERE token = ?");
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function nl_ereignis(string $typ, ?string $shop, ?string $token, ?string $email = null, ?string $vorname = null, array $info = []): void
{
    nl_db()->prepare("INSERT INTO " . nl_tab('nl_ereignisse') . " (typ, shop, token, email, vorname, ip, zeit, info) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$typ, $shop, $token, $email, $vorname, nl_ip(), date('Y-m-d H:i:s'), $info ? json_encode($info, JSON_UNESCAPED_UNICODE) : null]);
}

function nl_token_status(string $token, string $status): void
{
    nl_db()->prepare("UPDATE " . nl_tab('nl_tokens') . " SET status = ?, aktualisiert = ? WHERE token = ?")
        ->execute([$status, date('Y-m-d H:i:s'), $token]);
}

function nl_bild_ordner(): string
{
    return rtrim(bp_config()['ordner'], '/\\') . '/nl_bilder';
}

/** Bildname = SHA-1 des Inhalts + Endung (nichts anderes wird ausgeliefert) */
function nl_bild_name_ok(string $name): bool
{
    return (bool)preg_match('/^[a-f0-9]{40}\.(jpg|png|gif)$/', $name);
}

function nl_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

/** Schutz gegen Massen-Anmeldungen: max. 10 Anmeldungen je IP und Stunde */
function nl_zu_viele_anmeldungen(): bool
{
    $st = nl_db()->prepare("SELECT COUNT(*) FROM " . nl_tab('nl_ereignisse') . " WHERE typ = 'anmeldung' AND ip = ? AND zeit > ?");
    $st->execute([nl_ip(), date('Y-m-d H:i:s', time() - 3600)]);
    return (int)$st->fetchColumn() >= 10;
}

/** Rücksprung nur auf die eigene Shop-Adresse (kein offener Redirect) */
function nl_ruecksprung_ok(?array $shop, string $ziel): bool
{
    if (!$shop || empty($shop['url']) || $ziel === '') return false;
    $basis = parse_url($shop['url'], PHP_URL_HOST);
    $host  = parse_url($ziel, PHP_URL_HOST);
    return $basis && $host && strcasecmp(preg_replace('/^www\./i', '', $basis), preg_replace('/^www\./i', '', $host)) === 0
        && in_array(parse_url($ziel, PHP_URL_SCHEME), ['http', 'https'], true);
}
