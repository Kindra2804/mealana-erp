<?php
require_once __DIR__ . '/../../core/Firmenlogo.php';

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Lizenz.php';
require_once __DIR__ . '/BonA4Renderer.php';

/**
 * Paperless-Rechnung: A4-Bon als PDF auf die Belegseite laden (beleg-portal/, z.B. beleg.mealana.at),
 * Abruf-Link für den QR-Code zurückgeben und später nachsehen, ob der Kunde ihn geöffnet hat.
 * Verbindung über den API-Schlüssel (Header X-Nahtlos-Schluessel), gleicher Wert wie in config.php der Belegseite.
 */
class PaperlessService
{
    private const TIMEOUT = 15;

    /** Lizenz da + Adresse und Schlüssel eingetragen → Button „Beleg per QR“ an der Kasse */
    public static function verfuegbar(): bool
    {
        if (!Lizenz::hat('paperless')) return false;
        $k = self::konfig();
        return $k['url'] !== '' && $k['schluessel'] !== '';
    }

    /** @return array{url: string, schluessel: string} */
    public static function konfig(): array
    {
        $rows = Database::getInstance()
            ->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel IN ('paperless_url','paperless_schluessel')")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['url'        => rtrim(trim($rows['paperless_url'] ?? ''), '/'),
                'schluessel' => trim($rows['paperless_schluessel'] ?? '')];
    }

    /** Verbindung testen — Antwort der Belegseite (datenbank, ordner_beschreibbar, anzahl) oder Fehler */
    public static function test(?string $url = null, ?string $schluessel = null): array
    {
        $k = self::konfig();
        return self::anfrage('test', [], null, $url ?? $k['url'], $schluessel ?? $k['schluessel']);
    }

    /**
     * Bon hochladen (oder vorhandenen Link zurückgeben, falls schon hochgeladen).
     * @return array{erfolg: bool, url?: string, fehler?: string}
     */
    public static function hochladen(int $bonId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT kb.id, kb.bon_nr, kb.bruttobetrag, kb.erstellt_am, kb.paperless_url, k.bon_logo
                                FROM kassen_bons kb JOIN kassen k ON k.id = kb.kasse_id WHERE kb.id = ?");
        $stmt->execute([$bonId]);
        $bon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$bon) return ['erfolg' => false, 'fehler' => 'Bon nicht gefunden.'];
        if ($bon['paperless_url']) return ['erfolg' => true, 'url' => $bon['paperless_url']];
        if (!self::verfuegbar()) return ['erfolg' => false, 'fehler' => 'Paperless ist nicht eingerichtet.'];

        try {
            $pdf = BonA4Renderer::pdfSpeichern($bonId);
        } catch (Throwable $e) {
            error_log('[Paperless] PDF ' . $e->getMessage());
            $pdf = null;
        }
        if (!$pdf) return ['erfolg' => false, 'fehler' => 'PDF konnte nicht erzeugt werden.'];

        $firma = (string)$db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'firmenname'")->fetchColumn();
        $felder = [
            'pdf'    => new CURLFile($pdf, 'application/pdf', 'beleg.pdf'),
            'bon_nr' => $bon['bon_nr'],
            'betrag' => number_format((float)$bon['bruttobetrag'], 2, ',', '.'),
            'datum'  => date('d.m.Y H:i', strtotime($bon['erstellt_am'])),
            'firma'  => $firma,
            'logo'   => $bon['bon_logo'] ? self::logoDataUri() : '',
        ];
        $k   = self::konfig();
        $erg = self::anfrage('upload', [], $felder, $k['url'], $k['schluessel']);
        if (empty($erg['erfolg']) || empty($erg['token']) || empty($erg['url'])) {
            return ['erfolg' => false, 'fehler' => $erg['fehler'] ?? 'Upload fehlgeschlagen.'];
        }
        $db->prepare("UPDATE kassen_bons SET paperless_token = ?, paperless_url = ?, paperless_hochgeladen_am = NOW() WHERE id = ?")
           ->execute([$erg['token'], $erg['url'], $bonId]);
        return ['erfolg' => true, 'url' => $erg['url']];
    }

    /** Hat der Kunde den Beleg schon geöffnet? Fragt die Belegseite, solange noch nicht abgerufen. */
    public static function abgerufen(int $bonId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT paperless_token, paperless_abgerufen_am FROM kassen_bons WHERE id = ?");
        $stmt->execute([$bonId]);
        $bon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$bon || !$bon['paperless_token']) return false;
        if ($bon['paperless_abgerufen_am']) return true;

        $k   = self::konfig();
        $erg = self::anfrage('status', ['t' => $bon['paperless_token']], null, $k['url'], $k['schluessel']);
        if (empty($erg['abgerufen'])) return false;
        $db->prepare("UPDATE kassen_bons SET paperless_abgerufen_am = NOW() WHERE id = ?")->execute([$bonId]);
        return true;
    }

    /**
     * Nicht gescannt (60 s vorbei, nächster Kunde, doch gedruckt) → auf der Belegseite sofort löschen,
     * Link am Bon entfernen (ein späterer QR lädt neu hoch). Liefert true, falls er doch noch abgerufen wurde.
     */
    public static function verwerfen(int $bonId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT paperless_token, paperless_abgerufen_am FROM kassen_bons WHERE id = ?");
        $stmt->execute([$bonId]);
        $bon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$bon || !$bon['paperless_token']) return false;
        if ($bon['paperless_abgerufen_am']) return true;

        $k   = self::konfig();
        $erg = self::anfrage('verwerfen', ['t' => $bon['paperless_token']], null, $k['url'], $k['schluessel']);
        if (!empty($erg['abgerufen'])) {
            $db->prepare("UPDATE kassen_bons SET paperless_abgerufen_am = NOW() WHERE id = ?")->execute([$bonId]);
            return true;
        }
        // Belegseite nicht erreichbar → dort räumt die 24-h-Regel auf; hier trotzdem lösen
        $db->prepare("UPDATE kassen_bons SET paperless_token = NULL, paperless_url = NULL, paperless_hochgeladen_am = NULL WHERE id = ?")
           ->execute([$bonId]);
        return false;
    }

    /** Logo wie am Bon (Hauptlogo, siehe Firmenlogo) als data-URI, max. ~290 KB */
    private static function logoDataUri(): string
    {
        $datei = Firmenlogo::datei(Firmenlogo::haupt());
        if (!$datei || !is_file($datei) || filesize($datei) > 290000) return '';
        $mime = mime_content_type($datei) ?: '';
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) return '';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($datei));
    }

    private static function anfrage(string $aktion, array $query, ?array $post, string $url, string $schluessel): array
    {
        if ($url === '' || $schluessel === '') return ['erfolg' => false, 'fehler' => 'Adresse oder API-Schlüssel fehlt.'];
        $ch = curl_init($url . '/api.php?' . http_build_query(['aktion' => $aktion] + $query));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['X-Nahtlos-Schluessel: ' . $schluessel, 'Accept: application/json'],
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        $antwort = curl_exec($ch);
        $code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $fehler  = curl_error($ch);
        curl_close($ch);

        if ($antwort === false) return ['erfolg' => false, 'fehler' => 'Belegseite nicht erreichbar: ' . $fehler];
        $daten = json_decode($antwort, true);
        if (!is_array($daten)) {
            return ['erfolg' => false, 'fehler' => 'Unerwartete Antwort (HTTP ' . $code . ') — stimmt die Adresse?'];
        }
        return $daten;
    }
}
