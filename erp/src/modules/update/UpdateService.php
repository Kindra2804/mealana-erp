<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Lizenz.php';
require_once __DIR__ . '/../../core/Migrator.php';

/**
 * Updates einspielen (Paket aus tools/paket/bauen.php, signiert von NAHTLOS).
 *
 * Ablauf: pruefen() → einspielen(): Verlauf-Zeile, Datenbank-Sicherung (Pflicht, sonst Abbruch),
 * Sicherung der bisherigen Dateien, Wartungsmodus, Dateien kopieren, Migrationen, Wartungsmodus aus.
 * Bei einem Fehler: zurueckspielen() stellt Dateien und Datenbank aus der Sicherung wieder her
 * (MySQL rollt halb angewendete Migrationen nicht selbst zurück).
 *
 * Nie überschrieben wird, was der Installation gehört: Konfiguration, Belege/Storage, Uploads, Logos.
 */
class UpdateService
{
    public const GESCHUETZT = [
        'erp/config/database.php', 'erp/config/encryption.php',
        'erp/storage/', 'erp/public/uploads/', 'erp/public/img/logos/', 'erp/public/img/hersteller/',
        'beleg-portal/config.php', 'beleg-portal/daten/',
    ];
    /** Schutz-/Platzhalterdateien in geschützten Ordnern dürfen mit (z.B. daten/.htaccess sperrt die Belege) */
    private const ORDNER_DATEIEN = ['.htaccess', '.gitkeep'];
    private const MANIFEST = 'nahtlos-manifest.json';

    /** Installationsordner = der Ordner, in dem erp/ liegt */
    public static function wurzel(): string
    {
        return str_replace('\\', '/', dirname(realpath(__DIR__ . '/../../..')));
    }

    public static function ordner(): string
    {
        $o = self::wurzel() . '/erp/storage/updates';
        if (!is_dir($o)) mkdir($o, 0755, true);
        return $o;
    }

    /** „0.4.0(beta)“ → „0.4.0“ */
    public static function installierteVersion(): string
    {
        $v = trim((string)@file_get_contents(self::wurzel() . '/erp/VERSION'));
        return preg_match('/^\d+(\.\d+)*/', $v, $m) ? $m[0] : '0';
    }

    // ── Prüfen ────────────────────────────────────────────────────────────────

    /**
     * @return array{ok: bool, fehler: string[], hinweise: string[], manifest: ?array}
     */
    public static function pruefen(string $zip): array
    {
        $fehler = []; $hinweise = [];
        try {
            new PharData($zip);
        } catch (Throwable $e) {
            return ['ok' => false, 'fehler' => ['Keine gültige Paket-Datei (ZIP).'], 'hinweise' => [], 'manifest' => null];
        }
        $roh = @file_get_contents('phar://' . $zip . '/' . self::MANIFEST);
        if ($roh === false) {
            return ['ok' => false, 'fehler' => ['Kein NAHTLOS-Update-Paket (Manifest fehlt).'], 'hinweise' => [], 'manifest' => null];
        }
        $sig = Lizenz::pruefeSignatur($roh);
        $m   = $sig['daten'];
        if ($sig['fehler'] || ($m['typ'] ?? '') !== 'update_paket') {
            return ['ok' => false, 'fehler' => [$sig['fehler'] ?? 'Das ist kein Update-Paket.'], 'hinweise' => [], 'manifest' => null];
        }

        $ist = self::installierteVersion();
        if (version_compare($m['version'], $ist, '<=')) {
            $fehler[] = "Paket-Version {$m['version']} ist nicht neuer als die installierte Version $ist.";
        }

        // Lizenz: ohne Lizenz (Testphase) keine Updates; sonst nur Pakete, die innerhalb der Wartung erschienen sind
        $lizenz = Lizenz::status();
        if ($lizenz['status'] !== 'aktiv') {
            $fehler[] = 'Updates gibt es nur mit einer gültigen Lizenz (Testversion: keine Updates).';
        } else {
            $bis = Lizenz::wartungBis();
            if ($bis !== null && $m['datum'] > $bis) {
                $fehler[] = 'Das Paket ist vom ' . date('d.m.Y', strtotime($m['datum'])) . ', die Wartung lief bis '
                          . date('d.m.Y', strtotime($bis)) . '. Bitte die Wartung verlängern.';
            }
        }

        // Inhalt: Pfade sauber, nichts Geschütztes, Prüfsummen stimmen
        $kaputt = 0;
        foreach ($m['dateien'] as $rel => $hash) {
            if (str_contains($rel, '..') || str_starts_with($rel, '/') || self::geschuetzt($rel)) {
                $fehler[] = "Unzulässiger Pfad im Paket: $rel";
                continue;
            }
            $inhalt = @file_get_contents('phar://' . $zip . '/' . $rel);
            if ($inhalt === false || !hash_equals($hash, hash('sha256', $inhalt))) $kaputt++;
        }
        if ($kaputt) $fehler[] = "$kaputt Datei(en) im Paket sind beschädigt oder verändert.";

        // Technik: Sicherung muss möglich sein, Ordner beschreibbar
        if (!self::werkzeug('mysqldump')) $fehler[] = 'mysqldump wurde nicht gefunden — ohne Datenbank-Sicherung kein Update.';
        foreach (['erp', 'erp/public', 'erp/src', 'erp/database/migrations'] as $d) {
            if (!is_writable(self::wurzel() . '/' . $d)) $fehler[] = "Ordner $d ist nicht beschreibbar.";
        }
        if (self::wartungAktiv()) $hinweise[] = 'Der Wartungsmodus ist noch aktiv (vorheriges Update nicht abgeschlossen?).';

        return ['ok' => !$fehler, 'fehler' => $fehler, 'hinweise' => $hinweise, 'manifest' => $m];
    }

    public static function geschuetzt(string $rel): bool
    {
        if (in_array(basename($rel), self::ORDNER_DATEIEN, true)) return false;
        foreach (self::GESCHUETZT as $g) if (str_starts_with($rel, $g)) return true;
        return false;
    }

    // ── Einspielen ────────────────────────────────────────────────────────────

    /**
     * @return array{erfolg: bool, verlauf_id: int, meldung: string, migrationen: string[]}
     */
    public static function einspielen(string $zip, int $benutzerId): array
    {
        @set_time_limit(0);
        ignore_user_abort(true);
        $p = self::pruefen($zip);
        if (!$p['ok']) return ['erfolg' => false, 'verlauf_id' => 0, 'meldung' => implode(' ', $p['fehler']), 'migrationen' => []];
        $m   = $p['manifest'];
        $von = self::installierteVersion();
        $db  = Database::getInstance();
        self::verlaufTabelle();

        $ordner = self::ordner() . '/' . date('Ymd_His') . "_{$von}_auf_{$m['version']}";
        mkdir($ordner, 0755, true);
        copy($zip, "$ordner/paket.zip");
        $db->prepare("INSERT INTO update_verlauf (version_von, version_nach, status, sicherung_ordner, benutzer_id, gestartet_am)
                      VALUES (?, ?, 'laeuft', ?, ?, NOW())")->execute([$von, $m['version'], basename($ordner), $benutzerId]);
        $id = (int)$db->lastInsertId();
        $ende = function (string $status, string $meldung) use ($db, $id) {
            $db->prepare("UPDATE update_verlauf SET status = ?, meldung = ?, beendet_am = NOW() WHERE id = ?")->execute([$status, $meldung, $id]);
        };

        // 1. Datenbank-Sicherung — Pflicht
        $dump = self::datenbankSichern("$ordner/datenbank.sql");
        if ($dump !== null) {
            $ende('fehlgeschlagen', 'Datenbank-Sicherung fehlgeschlagen: ' . $dump);
            return ['erfolg' => false, 'verlauf_id' => $id, 'meldung' => 'Datenbank-Sicherung fehlgeschlagen — Update abgebrochen, nichts verändert. ' . $dump, 'migrationen' => []];
        }

        // 2. Bisherige Dateien sichern (nur die, die das Paket überschreibt) + Liste der neuen Dateien
        $wurzel = self::wurzel();
        $neu = [];
        try {
            // Als Ordner, nicht als ZIP: Kopieren dauert Sekunden, PharData-Packen über eine Minute
            foreach (array_keys($m['dateien']) as $rel) {
                if (!is_file("$wurzel/$rel")) { $neu[] = $rel; continue; }
                $ziel = "$ordner/dateien/$rel";
                if (!is_dir(dirname($ziel))) mkdir(dirname($ziel), 0755, true);
                if (!copy("$wurzel/$rel", $ziel)) throw new RuntimeException("Konnte $rel nicht sichern.");
            }
            file_put_contents("$ordner/neue_dateien.json", json_encode($neu, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } catch (Throwable $e) {
            $ende('fehlgeschlagen', 'Datei-Sicherung fehlgeschlagen: ' . $e->getMessage());
            return ['erfolg' => false, 'verlauf_id' => $id, 'meldung' => 'Datei-Sicherung fehlgeschlagen — Update abgebrochen, nichts verändert.', 'migrationen' => []];
        }

        // 3. Wartungsmodus, Dateien kopieren, Migrationen
        self::wartung(true, $m['version']);
        try {
            foreach (array_keys($m['dateien']) as $rel) {
                $ziel = "$wurzel/$rel";
                if (!is_dir(dirname($ziel))) mkdir(dirname($ziel), 0755, true);
                if (file_put_contents($ziel, file_get_contents('phar://' . $zip . '/' . $rel), LOCK_EX) === false) {
                    throw new RuntimeException("Datei konnte nicht geschrieben werden: $rel");
                }
            }
            if (function_exists('opcache_reset')) opcache_reset();
            $mig = (new Migrator($wurzel . '/erp/database/migrations'))->ausfuehren();
            if (!$mig['erfolg']) {
                throw new RuntimeException("Migration {$mig['fehler_datei']} fehlgeschlagen: {$mig['fehler']}");
            }
        } catch (Throwable $e) {
            $ende('fehlgeschlagen', $e->getMessage());
            // Wartungsmodus bleibt an: erst zurückspielen (oder bewusst aufheben)
            return ['erfolg' => false, 'verlauf_id' => $id, 'meldung' => $e->getMessage() . ' — bitte „Sicherung zurückspielen“.', 'migrationen' => $mig['angewendet'] ?? []];
        }
        self::wartung(false);
        $ende('erfolgreich', count($mig['angewendet']) . ' Migration(en)');
        return ['erfolg' => true, 'verlauf_id' => $id, 'meldung' => "Version {$m['version']} eingespielt.", 'migrationen' => $mig['angewendet']];
    }

    // ── Zurückspielen ─────────────────────────────────────────────────────────

    public static function zurueckspielen(int $verlaufId): array
    {
        @set_time_limit(0);
        ignore_user_abort(true);
        $db = Database::getInstance();
        $st = $db->prepare("SELECT * FROM update_verlauf WHERE id = ?");
        $st->execute([$verlaufId]);
        $v = $st->fetch(PDO::FETCH_ASSOC);
        $ordner = $v ? self::ordner() . '/' . basename($v['sicherung_ordner']) : null;
        if (!$v || !is_file("$ordner/datenbank.sql")) return ['erfolg' => false, 'meldung' => 'Sicherung nicht gefunden.'];
        $wurzel = self::wurzel();

        self::wartung(true, $v['version_von']);
        try {
            // WICHTIG: Zurückgespielt wird mit dem Code der NEUEN Version (das Update hat ihn schon kopiert) —
            // das Sicherungsformat (Ordner dateien/, neue_dateien.json, datenbank.sql) darf sich deshalb nie ändern.
            if (!is_dir("$ordner/dateien") && is_file("$ordner/neue_dateien.json")
                && count(json_decode((string)file_get_contents("$ordner/neue_dateien.json"), true) ?: []) === 0) {
                throw new RuntimeException('Datei-Sicherung fehlt (Ordner dateien/).');
            }
            if (is_dir("$ordner/dateien")) {
                $basis = strlen("$ordner/dateien") + 1;
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$ordner/dateien", FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    $rel = strtr(substr($f->getPathname(), $basis), '\\', '/');
                    if (!is_dir(dirname("$wurzel/$rel"))) mkdir(dirname("$wurzel/$rel"), 0755, true);
                    if (!copy($f->getPathname(), "$wurzel/$rel")) throw new RuntimeException("Konnte $rel nicht zurückkopieren.");
                }
            }
            foreach (json_decode((string)@file_get_contents("$ordner/neue_dateien.json"), true) ?: [] as $rel) {
                if (!self::geschuetzt($rel) && !str_contains($rel, '..')) @unlink("$wurzel/$rel");
            }
            if (function_exists('opcache_reset')) opcache_reset();
            $fehler = self::datenbankZurueck("$ordner/datenbank.sql");
            if ($fehler !== null) throw new RuntimeException('Datenbank konnte nicht zurückgespielt werden: ' . $fehler);
        } catch (Throwable $e) {
            return ['erfolg' => false, 'meldung' => $e->getMessage()];
        }
        self::wartung(false);
        // Die Datenbank ist jetzt auf dem Stand VOR dem Update — die Verlauf-Zeile (Status „läuft“) nachtragen
        self::verlaufTabelle();
        $db = Database::getInstance();
        $db->prepare("UPDATE update_verlauf SET status = 'zurueckgespielt', beendet_am = NOW(),
                      meldung = CONCAT(COALESCE(meldung, ''), ' — zurückgespielt am ', DATE_FORMAT(NOW(), '%d.%m.%Y %H:%i')) WHERE id = ?")
           ->execute([$verlaufId]);
        return ['erfolg' => true, 'meldung' => 'Sicherung zurückgespielt — Version ' . $v['version_von'] . ' ist wieder aktiv.'];
    }

    // ── Wartungsmodus ─────────────────────────────────────────────────────────

    public static function wartungAktiv(): bool
    {
        return is_file(self::wurzel() . '/erp/storage/wartung.json');
    }

    public static function wartung(bool $an, string $version = ''): void
    {
        $datei = self::wurzel() . '/erp/storage/wartung.json';
        if ($an) {
            file_put_contents($datei, json_encode(['seit' => date('Y-m-d H:i:s'), 'version' => $version]));
        } elseif (is_file($datei)) {
            unlink($datei);
        }
    }

    // ── Verlauf ───────────────────────────────────────────────────────────────

    /** Die Tabelle kommt mit Migration 222 — das erste Update auf diese Version braucht sie aber schon vorher */
    public static function verlaufTabelle(): void
    {
        Database::getInstance()->exec("
            CREATE TABLE IF NOT EXISTS update_verlauf (
                id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
                version_von      VARCHAR(20)  NOT NULL,
                version_nach     VARCHAR(20)  NOT NULL,
                status           ENUM('laeuft','erfolgreich','fehlgeschlagen','zurueckgespielt') NOT NULL,
                sicherung_ordner VARCHAR(100) NOT NULL,
                meldung          TEXT         NULL,
                benutzer_id      INT UNSIGNED NULL,
                gestartet_am     DATETIME     NOT NULL,
                beendet_am       DATETIME     NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public static function verlauf(int $anzahl = 20): array
    {
        self::verlaufTabelle();
        return Database::getInstance()->query("
            SELECT u.*, b.formularname FROM update_verlauf u LEFT JOIN benutzer b ON b.id = u.benutzer_id
            ORDER BY u.id DESC LIMIT " . (int)$anzahl)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Datenbank-Werkzeuge (mysqldump / mysql aus dem MariaDB-Ordner) ──────────

    public static function werkzeug(string $name): ?string
    {
        $basis = (string)Database::getInstance()->query("SELECT @@basedir")->fetchColumn();
        foreach ([rtrim($basis, '/\\') . "/bin/$name.exe", rtrim($basis, '/\\') . "/bin/$name", "C:/xampp/mysql/bin/$name.exe"] as $p) {
            if ($p && is_file($p)) return $p;
        }
        return null;
    }

    /** @return string|null Fehlermeldung oder null bei Erfolg */
    private static function datenbankSichern(string $datei): ?string
    {
        $exe = self::werkzeug('mysqldump');
        if (!$exe) return 'mysqldump nicht gefunden.';
        $c = require self::wurzel() . '/erp/config/database.php';
        $cmd = [$exe, '--host=' . $c['host'], '--user=' . $c['username'], '--single-transaction', '--routines', '--triggers',
                '--default-character-set=utf8mb4', '--result-file=' . $datei, $c['dbname']];
        if (($c['password'] ?? '') !== '') array_splice($cmd, 3, 0, ['--password=' . $c['password']]);
        $erg = self::ausfuehren($cmd);
        if ($erg['code'] !== 0) return trim($erg['fehler']) ?: 'Exit-Code ' . $erg['code'];
        // Vollständig? mysqldump schreibt am Ende „-- Dump completed“
        $fh = fopen($datei, 'r');
        fseek($fh, -200, SEEK_END);
        $schluss = fread($fh, 200);
        fclose($fh);
        return str_contains($schluss, 'Dump completed') ? null : 'Sicherung unvollständig.';
    }

    private static function datenbankZurueck(string $datei): ?string
    {
        $exe = self::werkzeug('mysql');
        if (!$exe) return 'mysql nicht gefunden.';
        $c = require self::wurzel() . '/erp/config/database.php';
        // Vorher alles leeren: Tabellen, die erst eine neue Migration angelegt hat, stehen nicht im Dump
        $pdo = new PDO("mysql:host={$c['host']};dbname={$c['dbname']};charset=utf8mb4", $c['username'], $c['password'] ?? '',
                       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_NUM) as [$t, $typ]) {
            $pdo->exec(($typ === 'VIEW' ? 'DROP VIEW IF EXISTS `' : 'DROP TABLE IF EXISTS `') . str_replace('`', '', $t) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $pdo = null;
        $cmd = [$exe, '--host=' . $c['host'], '--user=' . $c['username'], '--default-character-set=utf8mb4', $c['dbname']];
        if (($c['password'] ?? '') !== '') array_splice($cmd, 3, 0, ['--password=' . $c['password']]);
        $erg = self::ausfuehren($cmd, $datei);
        return $erg['code'] === 0 ? null : (trim($erg['fehler']) ?: 'Exit-Code ' . $erg['code']);
    }

    private static function ausfuehren(array $cmd, ?string $eingabeDatei = null): array
    {
        $desc = [0 => $eingabeDatei ? ['file', $eingabeDatei, 'r'] : ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $desc, $pipes);
        if (!is_resource($proc)) return ['code' => -1, 'fehler' => 'Prozess konnte nicht gestartet werden.'];
        if (!$eingabeDatei) fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $fehler = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return ['code' => proc_close($proc), 'fehler' => $fehler];
    }
}
