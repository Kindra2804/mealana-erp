<?php

require_once __DIR__ . '/Database.php';

/**
 * Firmenlogo — eine Stelle für alle Belege, Mails und Anzeigen.
 *
 * Hauptlogo = Einstellungen → Firma → „Hauptlogo“ (system_einstellungen.logo_pfad), braucht keinen Kanal.
 * Laden-Belege (Kasse, Kundenanzeige, Paperless, Partner, Händler, Lieferanten-Bestellung) nehmen immer das Hauptlogo,
 * Shop-Belege (Auftrag, Rechnung, Mails eines Kanals) das Kanal-Logo und ohne eigenes Logo ebenfalls das Hauptlogo.
 * Pfade sind relativ zu public/ (z.B. "img/logos/logo_firma.png").
 */
class Firmenlogo
{
    public static function haupt(): ?string
    {
        $db = Database::getInstance();
        $pfad = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'logo_pfad'")->fetchColumn();
        if ($pfad && self::datei($pfad)) return $pfad;
        // Altbestand: früher hing das Hauptlogo am ersten Kanal
        $pfad = $db->query("SELECT logo_pfad FROM shops WHERE logo_pfad IS NOT NULL AND logo_pfad <> '' ORDER BY id LIMIT 1")->fetchColumn();
        return ($pfad && self::datei($pfad)) ? $pfad : null;
    }

    /** Kanal-Logo, sonst Hauptlogo. $shopId 0/null = kein Kanal → Hauptlogo */
    public static function fuerShop(?int $shopId): ?string
    {
        if ($shopId) {
            $stmt = Database::getInstance()->prepare("SELECT logo_pfad FROM shops WHERE id = ?");
            $stmt->execute([$shopId]);
            $pfad = $stmt->fetchColumn();
            if ($pfad && self::datei($pfad)) return $pfad;
        }
        return self::haupt();
    }

    /** Absoluter Dateipfad oder null, wenn die Datei fehlt */
    public static function datei(?string $pfad): ?string
    {
        if (!$pfad) return null;
        $datei = __DIR__ . '/../../public/' . ltrim($pfad, '/');
        return is_file($datei) ? $datei : null;
    }

    public static function base64(?string $pfad): string
    {
        $datei = self::datei($pfad);
        return $datei ? base64_encode(file_get_contents($datei)) : '';
    }

    /** data:-URI mit richtigem Bildtyp (für Seiten außerhalb des ERP, z.B. Belegseite) */
    public static function dataUri(?string $pfad): string
    {
        $datei = self::datei($pfad);
        if (!$datei) return '';
        $mime = mime_content_type($datei) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($datei));
    }
}
