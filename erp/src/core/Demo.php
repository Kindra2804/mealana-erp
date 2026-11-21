<?php

/**
 * Demo-Modus (2026-11-21): eine eigene Installation mit erfundenen Daten für Präsentationen.
 * Eingeschaltet, wenn erp/config/demo.php existiert und 'aktiv' => true liefert (Datei liegt
 * nur in der Demo-Installation, nie im Git und nie in einem Kunden-Paket).
 *
 * Im Demo-Modus geht nichts nach außen (Mail, Shops, BFR, Belegseite, Newsletter-Portal) und
 * Bereiche, die die Demo kaputt machen könnten (Benutzer, Updates, Lizenz, Importe …), sind
 * gesperrt. Die Daten erzeugt scripts/demo/demo_erzeugen.php, jede Nacht neu.
 */
class Demo
{
    private static ?array $konfig = null;

    /** Ganze Verzeichnisse ('*') oder einzelne Dateien, die in der Demo nie aufgerufen werden dürfen */
    private const GESPERRT = [
        'update'        => '*',
        'einrichtung'   => '*',
        'lizenz'        => ['hochladen.php', 'generator.php', 'ausstellen.php', 'datei.php'],
        'benutzer'      => ['speichern.php', 'aktualisieren.php', 'status_setzen.php', 'link_erneut_senden.php'],
        'rollen'        => ['berechtigung_setzen.php'],
        'einstellungen' => ['speichern.php', 'kasse_speichern.php', 'kasse_registrierung_speichern.php', 'kasse_zertifikat_speichern.php',
                            'nummernkreis_aktualisieren.php', 'abholort_speichern.php', 'test_mail.php', 'paperless_test.php',
                            'shop_sync_start.php', 'shop_sync_bilder_ftp_start.php', 'shop_sync_bilder_export_start.php',
                            'shop_sync_reconcile_start.php'],
        'artikel'       => ['import.php', 'jtl_import.php', 'jtl_import_commit.php', 'jtl_bilder_import.php',
                            'jtl_bilder_import_commit.php', 'lang_import.php'],
        'kunden'        => ['jtl_import.php'],
        'newsletter'    => ['import.php'],
    ];

    /** Nur Absenden (POST) gesperrt, Anschauen erlaubt — z.B. das eigene Profil (Passwort ändern) */
    private const NUR_POST_GESPERRT = [
        'benutzer' => ['profil.php'],
    ];

    public static function aktiv(): bool
    {
        return (bool)(self::konfig()['aktiv'] ?? false);
    }

    /** Werte aus config/demo.php, z.B. 'login' => 'demo', 'passwort' => 'demo' */
    public static function konfig(): array
    {
        if (self::$konfig === null) {
            $datei = __DIR__ . '/../../config/demo.php';
            self::$konfig = is_file($datei) ? (array)require $datei : [];
        }
        return self::$konfig;
    }

    /** Darf diese Seite in der Demo aufgerufen werden? (Verzeichnis ab public/, Dateiname) */
    public static function seiteGesperrt(string $verzeichnis, string $datei): bool
    {
        if (!self::aktiv()) return false;
        $regel = self::GESPERRT[$verzeichnis] ?? null;
        if ($regel === '*' || (is_array($regel) && in_array($datei, $regel, true))) return true;
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            && in_array($datei, self::NUR_POST_GESPERRT[$verzeichnis] ?? [], true);
    }

    /** Für Netzwerk-Aufrufe (Shop, BFR, Belegseite …): in der Demo eine klare Ausnahme statt Verbindung */
    public static function keineVerbindung(string $wohin): void
    {
        if (self::aktiv()) {
            throw new RuntimeException("Demo-Version: Verbindung zu $wohin ist abgeschaltet.");
        }
    }

    public const HINWEIS = 'Demo-Version – alle Daten sind erfunden und werden jeden Tag neu erzeugt. Mails, Shops und Kasse sind nicht verbunden.';

    // ── Frisch halten ohne Cronjob (2026-11-21) ───────────────────────────
    // Der erste Aufruf eines neuen Tages startet den Neuaufbau im Hintergrund (Warteseite),
    // spätere Aufrufe holen höchstens alle 10 Minuten die Belege seit dem letzten Stand nach.
    // Niemand schaut zu → keine Last.

    private const NACHHOLEN_ALLE_SEKUNDEN = 600;
    private const SPERRE_SEKUNDEN = 900;

    /** Aus config/bootstrap.php bei jedem Seitenaufruf der Demo (nicht auf der Kommandozeile) */
    public static function frischHalten(): void
    {
        if (!self::aktiv() || PHP_SAPI === 'cli' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'demo_cron.php') return;
        $storage = __DIR__ . '/../../storage';
        $laeuft = $storage . '/demo_erzeugen.lock';
        if (is_file($laeuft) && time() - filemtime($laeuft) < self::SPERRE_SEKUNDEN) self::warteseite();

        $stand = json_decode((string)@file_get_contents($storage . '/demo_stand.json'), true);
        if (($stand['tag'] ?? '') !== date('Y-m-d')) {
            // Neuer Tag (oder noch nie erzeugt): neu aufbauen. Scheitert das, frühestens nach
            // 15 Minuten wieder versuchen und solange die alten Daten zeigen.
            if (self::neuaufbauStarten()) self::warteseite();
            return;
        }
        if (time() - strtotime($stand['bis'] ?? 'now') >= self::NACHHOLEN_ALLE_SEKUNDEN) {
            try {
                require_once __DIR__ . '/../../scripts/demo/DemoStand.php';
                DemoStand::nachholen();
            } catch (Throwable $e) {
                error_log('[Demo] Nachholen fehlgeschlagen: ' . $e->getMessage());   // Seite trotzdem zeigen
            }
        }
    }

    /**
     * Generator im Hintergrund starten (PHP der Kommandozeile aus 'php_cli' in config/demo.php).
     * false, wenn der letzte Start keine 15 Minuten her ist.
     */
    public static function neuaufbauStarten(): bool
    {
        $storage = __DIR__ . '/../../storage';
        $start = $storage . '/demo_erzeugen.start';
        if (is_file($start) && time() - filemtime($start) < self::SPERRE_SEKUNDEN) return false;
        if (!function_exists('exec')) return false;
        touch($start);
        touch($storage . '/demo_erzeugen.lock');

        $php    = (string)(self::konfig()['php_cli'] ?? 'php');
        $skript = realpath(__DIR__ . '/../../scripts/demo/demo_erzeugen.php');
        $log    = $storage . '/demo_erzeugen.log';
        $lock   = $storage . '/demo_erzeugen.lock';
        if (PHP_OS_FAMILY === 'Windows') {   // lokale Demo unter XAMPP
            pclose(popen('start "" /B cmd /C ""' . $php . '" "' . $skript . '" > "' . $log . '" 2>&1 & del "' . $lock . '""', 'r'));
        } else {
            exec(sprintf('nohup sh -c %s > /dev/null 2>&1 &', escapeshellarg(sprintf('%s %s > %s 2>&1; rm -f %s',
                escapeshellarg($php), escapeshellarg($skript), escapeshellarg($log), escapeshellarg($lock)))));
        }
        return true;
    }

    /** „Demo wird vorbereitet“ — lädt sich alle 8 Sekunden neu, bis der Neuaufbau fertig ist */
    private static function warteseite(): never
    {
        http_response_code(503);
        header('Retry-After: 30');
        $json = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['erfolg' => false, 'fehler' => 'Die Demo wird gerade für heute neu vorbereitet – bitte in einer Minute noch einmal.']);
            exit;
        }
        $basis = defined('BASE_PATH') ? BASE_PATH : '';
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<meta http-equiv="refresh" content="8"><title>Demo wird vorbereitet</title>'
           . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#1e2a38;font-family:"Segoe UI",Arial,sans-serif}'
           . '.k{background:#fff;border-radius:14px;padding:32px 36px;max-width:420px;margin:16px;text-align:center}img{width:170px}p{color:#64748b;line-height:1.5}'
           . '.r{width:34px;height:34px;margin:14px auto 0;border:4px solid #dbe4f0;border-top-color:#2563eb;border-radius:50%;animation:d 1s linear infinite}'
           . '@keyframes d{to{transform:rotate(360deg)}}</style></head><body><div class="k">'
           . '<img src="' . htmlspecialchars($basis) . '/img/nahtlos.png" alt="NAHTLOS"><h2 style="margin:16px 0 6px;color:#1f2d3d">Demo wird vorbereitet …</h2>'
           . '<p>Die Demo-Daten werden für heute frisch erzeugt.<br>Das dauert etwa eine Minute – die Seite lädt sich von selbst neu.</p>'
           . '<div class="r"></div></div></body></html>';
        exit;
    }
}
