<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../preise/PreisService.php';

/**
 * Newsletter-Inhalte (Schritt 3, Migration 251): anlegen, Bausteine speichern, kopieren, Bilder, Artikelsuche.
 * Gerendert wird im NewsletterRenderer, verschickt (Schritt 4) im NewsletterVersandService.
 */
class NewsletterService
{
    private PDO $db;

    /** erlaubte Bausteine und ihre Felder (alles andere wird beim Speichern verworfen) */
    public const BAUSTEINE = [
        'kopfbild' => ['bild', 'link', 'alt'],
        'text'     => ['ueberschrift', 'text'],
        'bild'     => ['bild', 'link', 'alt', 'text'],
        'knopf'    => ['text', 'link'],
        'artikel'  => ['ueberschrift', 'artikel_ids', 'spalten'],
        'hinweis'  => ['ueberschrift', 'text', 'code'],
        'trenner'  => [],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function liste(?int $shopId = null): array
    {
        $sql = "SELECT n.*, s.name AS shop_name, b.formularname AS ersteller FROM newsletter n
                JOIN shops s ON s.id = n.shop_id LEFT JOIN benutzer b ON b.id = n.erstellt_von";
        $p = [];
        if ($shopId) { $sql .= " WHERE n.shop_id = ?"; $p[] = $shopId; }
        $st = $this->db->prepare($sql . " ORDER BY n.id DESC");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function laden(int $id): ?array
    {
        $st = $this->db->prepare("SELECT n.*, s.name AS shop_name, s.slug AS shop_slug, s.wc_url, s.website_url, s.design_farbe
                                  FROM newsletter n JOIN shops s ON s.id = n.shop_id WHERE n.id = ?");
        $st->execute([$id]);
        $n = $st->fetch(PDO::FETCH_ASSOC);
        if (!$n) return null;
        $n['bausteine'] = json_decode((string)$n['bausteine'], true) ?: [];
        $n['segment']   = json_decode((string)($n['segment'] ?? ''), true) ?: null;
        return $n;
    }

    public function anlegen(int $shopId, string $titel, int $benutzerId): int
    {
        $start = [
            ['typ' => 'text', 'ueberschrift' => 'Hallo {vorname}!', 'text' => "Schön, dass du dabei bist. Hier sind unsere Neuheiten …"],
            ['typ' => 'artikel', 'ueberschrift' => 'Neu im Shop', 'artikel_ids' => [], 'spalten' => 2],
            ['typ' => 'knopf', 'text' => 'Zum Shop', 'link' => ''],
        ];
        $this->db->prepare("INSERT INTO newsletter (shop_id, titel, betreff, bausteine, erstellt_von) VALUES (?, ?, ?, ?, ?)")
            ->execute([$shopId, mb_substr(trim($titel) ?: 'Neuer Newsletter', 0, 200), null, json_encode($start, JSON_UNESCAPED_UNICODE), $benutzerId]);
        $id = (int)$this->db->lastInsertId();
        Logger::log('newsletter.angelegt', 'newsletter', $id, ['titel' => $titel], $benutzerId);
        return $id;
    }

    /** Speichern aus dem Editor (nur im Entwurf). Bausteine werden auf erlaubte Typen/Felder gefiltert. */
    public function speichern(int $id, array $daten): array
    {
        $n = $this->laden($id);
        if (!$n) return ['erfolg' => false, 'fehler' => 'Newsletter nicht gefunden.'];
        if ($n['status'] !== 'entwurf') return ['erfolg' => false, 'fehler' => 'Ein verschickter Newsletter kann nicht mehr geändert werden — bitte kopieren.'];
        $bausteine = [];
        foreach ((array)($daten['bausteine'] ?? []) as $b) {
            $typ = (string)($b['typ'] ?? '');
            if (!isset(self::BAUSTEINE[$typ])) continue;
            $sauber = ['typ' => $typ];
            foreach (self::BAUSTEINE[$typ] as $feld) {
                $v = $b[$feld] ?? null;
                if ($feld === 'artikel_ids') {
                    $v = array_values(array_unique(array_filter(array_map('intval', (array)$v))));
                } elseif ($feld === 'spalten') {
                    $v = in_array((int)$v, [1, 2, 3], true) ? (int)$v : 2;
                } elseif ($feld === 'link') {
                    $v = trim((string)$v);
                    if ($v !== '' && !preg_match('#^https?://#i', $v)) $v = 'https://' . ltrim($v, '/');
                } elseif ($feld === 'bild') {
                    $v = preg_match('#^uploads/newsletter/\d+/[A-Za-z0-9._-]+$#', (string)$v) ? (string)$v : '';
                } else {
                    $v = mb_substr(trim((string)$v), 0, $feld === 'text' ? 5000 : 300);
                }
                $sauber[$feld] = $v;
            }
            $bausteine[] = $sauber;
        }
        // Segment nur, wenn Segmente freigeschaltet sind (sonst bleibt das gespeicherte unverändert)
        require_once __DIR__ . '/NewsletterSegmentService.php';
        if (NewsletterSegmentService::aktiv() && array_key_exists('segment', $daten)) {
            $seg = (new NewsletterSegmentService())->normalisieren($daten['segment']);
            $this->db->prepare("UPDATE newsletter SET segment = ? WHERE id = ?")->execute([$seg ? json_encode($seg, JSON_UNESCAPED_UNICODE) : null, $id]);
        }
        $this->db->prepare("UPDATE newsletter SET titel = ?, betreff = ?, vorschautext = ?, bausteine = ? WHERE id = ?")
            ->execute([mb_substr(trim((string)($daten['titel'] ?? $n['titel'])) ?: $n['titel'], 0, 200),
                       mb_substr(trim((string)($daten['betreff'] ?? '')), 0, 200) ?: null,
                       mb_substr(trim((string)($daten['vorschautext'] ?? '')), 0, 200) ?: null,
                       json_encode($bausteine, JSON_UNESCAPED_UNICODE), $id]);
        return ['erfolg' => true];
    }

    public function kopieren(int $id, int $benutzerId): ?int
    {
        $n = $this->laden($id);
        if (!$n) return null;
        $this->db->prepare("INSERT INTO newsletter (shop_id, titel, betreff, vorschautext, bausteine, segment, erstellt_von) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$n['shop_id'], mb_substr('Kopie von ' . $n['titel'], 0, 200), $n['betreff'], $n['vorschautext'],
                       json_encode($n['bausteine'], JSON_UNESCAPED_UNICODE), $n['segment'] ? json_encode($n['segment'], JSON_UNESCAPED_UNICODE) : null, $benutzerId]);
        $neu = (int)$this->db->lastInsertId();
        // Bilder mitkopieren (eigener Ordner je Newsletter)
        $alt = __DIR__ . '/../../../public/uploads/newsletter/' . $id;
        if (is_dir($alt)) {
            @mkdir(__DIR__ . '/../../../public/uploads/newsletter/' . $neu, 0775, true);
            foreach (glob($alt . '/*') as $f) copy($f, __DIR__ . '/../../../public/uploads/newsletter/' . $neu . '/' . basename($f));
            $json = str_replace('uploads\/newsletter\/' . $id . '\/', 'uploads\/newsletter\/' . $neu . '\/', json_encode($n['bausteine'], JSON_UNESCAPED_UNICODE));
            $this->db->prepare("UPDATE newsletter SET bausteine = ? WHERE id = ?")->execute([$json, $neu]);
        }
        return $neu;
    }

    public function loeschen(int $id, int $benutzerId): bool
    {
        $st = $this->db->prepare("DELETE FROM newsletter WHERE id = ? AND status = 'entwurf'");
        $st->execute([$id]);
        if (!$st->rowCount()) return false;
        foreach (glob(__DIR__ . '/../../../public/uploads/newsletter/' . $id . '/*') ?: [] as $f) @unlink($f);
        @rmdir(__DIR__ . '/../../../public/uploads/newsletter/' . $id);
        Logger::log('newsletter.geloescht', 'newsletter', $id, [], $benutzerId);
        return true;
    }

    /** Bild für einen Baustein: max. 1200 px breit, JPEG (PNG bleibt PNG wegen Transparenz). Rückgabe: relativer Pfad. */
    public function bildHochladen(int $id, array $datei): array
    {
        if (($datei['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($datei['tmp_name'])) return ['erfolg' => false, 'fehler' => 'Upload fehlgeschlagen.'];
        $info = @getimagesize($datei['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return ['erfolg' => false, 'fehler' => 'Bitte ein Bild (JPG, PNG, GIF, WebP).'];
        }
        $quelle = @imagecreatefromstring((string)file_get_contents($datei['tmp_name']));
        if (!$quelle) return ['erfolg' => false, 'fehler' => 'Bild konnte nicht gelesen werden.'];
        $w = imagesx($quelle); $h = imagesy($quelle);
        $f = min(1, 1200 / $w);
        $ziel = imagecreatetruecolor(max(1, (int)round($w * $f)), max(1, (int)round($h * $f)));
        $png = $info[2] === IMAGETYPE_PNG;
        if ($png) { imagealphablending($ziel, false); imagesavealpha($ziel, true); }
        else imagefill($ziel, 0, 0, imagecolorallocate($ziel, 255, 255, 255));
        imagecopyresampled($ziel, $quelle, 0, 0, 0, 0, imagesx($ziel), imagesy($ziel), $w, $h);
        $ordner = __DIR__ . '/../../../public/uploads/newsletter/' . $id;
        if (!is_dir($ordner)) mkdir($ordner, 0775, true);
        $name = bin2hex(random_bytes(6)) . ($png ? '.png' : '.jpg');
        $png ? imagepng($ziel, $ordner . '/' . $name, 7) : imagejpeg($ziel, $ordner . '/' . $name, 85);
        imagedestroy($quelle); imagedestroy($ziel);
        return ['erfolg' => true, 'pfad' => 'uploads/newsletter/' . $id . '/' . $name];
    }

    /** Artikelsuche für den Artikel-Baustein (Väter + Einzelartikel, aktiv), mit Effektivpreis. */
    public function artikelSuche(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $st = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name
            FROM artikel a
            WHERE a.aktiv = 1 AND a.vaterartikel_id IS NULL AND (a.name LIKE :q OR a.artikelnummer LIKE :q2)
            ORDER BY a.name LIMIT " . max(1, $limit));
        $st->execute(['q' => '%' . $q . '%', 'q2' => $q . '%']);
        $erg = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($erg as &$a) $a += $this->preis((int)$a['id']);
        return $erg;
    }

    /** Effektivpreis (Standard-Kundengruppe): ['preis' => brutto, 'preis_vorher' => brutto|null] */
    public function preis(int $artikelId): array
    {
        static $kg = null, $svc = null;
        $kg ??= (int)$this->db->query("SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1")->fetchColumn();
        $svc ??= new PreisService();
        $e = $svc->getEffektiverPreis($artikelId, $kg);
        $vorher = null;
        if (in_array($e['quelle'] ?? '', ['sale', 'aktion'], true)) {
            $st = $this->db->prepare("SELECT ap.brutto_vk FROM artikel_preise ap WHERE ap.artikel_id = ? AND ap.kundengruppen_id = ? ORDER BY ap.gueltig_ab IS NULL DESC, ap.gueltig_ab DESC LIMIT 1");
            $st->execute([$artikelId, $kg]);
            $v = $st->fetchColumn();
            if ($v !== false && (float)$v > (float)$e['brutto_vk'] + 0.004) $vorher = (float)$v;
        }
        return ['preis' => $e['brutto_vk'] !== null ? (float)$e['brutto_vk'] : null, 'preis_vorher' => $vorher];
    }
}
