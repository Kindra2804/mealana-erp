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

    public const HINWEIS = 'Demo-Version – alle Daten sind erfunden und werden jede Nacht um 0:00 Uhr zurückgesetzt. Mails, Shops und Kasse sind nicht verbunden.';
}
