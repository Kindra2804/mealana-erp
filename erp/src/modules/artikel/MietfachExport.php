<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Lizenz.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * Artikel für ein Mietfach exportieren (Jacky 2026-11-25).
 *
 * Wer selbst Mietfach-Inhaber bei einem anderen NAHTLOS-Betrieb ist, packt hier seine Artikel in eine
 * Datei. Der Mietfach-Betreiber liest sie beim Partner ein (Partner → Artikel → „Aus NAHTLOS-Datei
 * übernehmen“, partner/MietfachImport.php) – kein Abtippen, keine Bildersuche.
 *
 * - Kann jede Installation (Grundmodul). Bestand und Einkaufspreise gehen NICHT mit.
 * - Varianten werden aufgelöst: jeder aktive Kind-Artikel ist eine eigene Zeile (Kind-Namen sind
 *   vollständig, z.B. „Seife Lavendel“). Preis/Steuer/Bild/Text fehlen beim Kind → vom Vater.
 * - Ausgelassen (mit Hinweis): inaktive Artikel, fremde Partnerware, Gutscheine, Konfigurator-,
 *   Download- und Lizenz-Artikel.
 * - Paket = .tar mit daten.json (format „nahtlos-mietfach“, version 1) + bilder/<n>.jpg (max. 800 px).
 */
class MietfachExport
{
    public const FORMAT  = 'nahtlos-mietfach';
    public const VERSION = 1;
    private const BILD_MAX = 800;

    private PDO $db;
    private string $public;

    public function __construct(?PDO $db = null, ?string $publicPfad = null)
    {
        $this->db     = $db ?? Database::getInstance();
        $this->public = rtrim($publicPfad ?? __DIR__ . '/../../../public', '/\\');
    }

    /**
     * Gewählte Artikel → verkaufbare Zeilen (Väter werden zu ihren Kindern).
     * @return array{positionen: array, hinweise: string[]}
     */
    public function sammle(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return ['positionen' => [], 'hinweise' => []];
        $ph = implode(',', array_fill(0, count($ids), '?'));

        // Gewählte Väter → ihre Kinder; gewählte Kinder/Einzelartikel direkt
        $stmt = $this->db->prepare("
            SELECT id FROM artikel WHERE id IN ($ph) AND ist_vater = 0
            UNION
            SELECT k.id FROM artikel k WHERE k.vaterartikel_id IN ($ph)
        ");
        $stmt->execute(array_merge($ids, $ids));
        $alle = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$alle) return ['positionen' => [], 'hinweise' => ['Keine verkaufbaren Artikel in der Auswahl.']];

        $ph2  = implode(',', array_fill(0, count($alle), '?'));
        $stmt = $this->db->prepare("
            SELECT a.*, v.artikelnummer AS vater_nummer, v.name AS vater_name,
                   COALESCE(a.steuerklasse_id, v.steuerklasse_id) AS stk_id,
                   COALESCE(NULLIF(a.kurzbeschreibung, ''), v.kurzbeschreibung) AS kurztext,
                   COALESCE(a.inhalt_menge, v.inhalt_menge) AS inhalt,
                   COALESCE(NULLIF(a.inhalt_einheit, ''), v.inhalt_einheit) AS inhalt_eh
            FROM artikel a
            LEFT JOIN artikel v ON v.id = a.vaterartikel_id
            WHERE a.id IN ($ph2)
            ORDER BY COALESCE(v.artikelnummer, a.artikelnummer), a.artikelnummer
        ");
        $stmt->execute($alle);

        $positionen = [];
        $ausgelassen = ['inaktiv' => 0, 'partnerware' => 0, 'sonderartikel' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            if (!$a['aktiv'])               { $ausgelassen['inaktiv']++; continue; }
            if ($a['partner_id'] !== null)  { $ausgelassen['partnerware']++; continue; }
            if ($a['ist_gutschein'] || $a['ist_konfigurierbar'] || $a['download_dateiname'] || $a['lizenz_modul']) {
                $ausgelassen['sonderartikel']++; continue;
            }
            $positionen[] = [
                'id'           => (int)$a['id'],
                'nummer'       => $a['artikelnummer'],
                'name'         => $a['name'],
                'vater_nummer' => $a['vater_nummer'],
                'vater_name'   => $a['vater_name'],
                'ean'          => $this->ean((int)$a['id']),
                'brutto_vk'    => $this->preis((int)$a['id'], $a['vaterartikel_id'] ? (int)$a['vaterartikel_id'] : null),
                'steuersatz'   => $this->steuersatz($a['stk_id'] ? (int)$a['stk_id'] : null),
                'kurztext'     => trim(html_entity_decode(strip_tags((string)$a['kurztext']), ENT_QUOTES, 'UTF-8')),
                'inhalt_menge' => $a['inhalt'] !== null ? (float)$a['inhalt'] : null,
                'inhalt_einheit' => $a['inhalt_eh'] ?: null,
                'bild_datei'   => $this->bild((int)$a['id'], $a['vaterartikel_id'] ? (int)$a['vaterartikel_id'] : null),
            ];
        }

        $hinweise = [];
        if ($ausgelassen['inaktiv'])       $hinweise[] = $ausgelassen['inaktiv'] . ' inaktive Artikel ausgelassen.';
        if ($ausgelassen['partnerware'])   $hinweise[] = $ausgelassen['partnerware'] . ' Artikel fremder Partner ausgelassen.';
        if ($ausgelassen['sonderartikel']) $hinweise[] = $ausgelassen['sonderartikel'] . ' Gutschein-, Konfigurator-, Download- oder Lizenz-Artikel ausgelassen.';
        $ohnePreis = count(array_filter($positionen, fn($p) => !$p['brutto_vk']));
        if ($ohnePreis) $hinweise[] = $ohnePreis . ' Artikel ohne Verkaufspreis – der Mietfach-Betreiber muss den Preis selbst eintragen.';
        $ohneEan = count(array_filter($positionen, fn($p) => !$p['ean']));
        if ($ohneEan) $hinweise[] = $ohneEan . ' Artikel ohne EAN – an der Kasse dann nur über Nummer/Name zu finden.';
        return ['positionen' => $positionen, 'hinweise' => $hinweise];
    }

    /**
     * Baut das Paket und gibt den Pfad der .tar-Datei zurück (temporär, Aufrufer löscht sie).
     * @return array{datei: string, dateiname: string, anzahl: int, bilder: int}
     */
    public function paket(array $ids): array
    {
        $erg = $this->sammle($ids);
        if (!$erg['positionen']) throw new RuntimeException('Keine Artikel zum Exportieren.');

        $arbeit = sys_get_temp_dir() . '/nahtlos_mietfach_' . getmypid() . '_' . bin2hex(random_bytes(4));
        mkdir("$arbeit/bilder", 0777, true);

        $firma   = Lizenz::firmendaten();
        $artikel = [];
        $bilder  = 0;
        foreach ($erg['positionen'] as $n => $p) {
            $bild = null;
            if ($p['bild_datei'] && $this->bildVerkleinern($p['bild_datei'], "$arbeit/bilder/" . ($n + 1) . '.jpg')) {
                $bild = 'bilder/' . ($n + 1) . '.jpg';
                $bilder++;
            }
            unset($p['id'], $p['bild_datei']);
            $artikel[] = $p + ['bild' => $bild];
        }
        $daten = [
            'format'      => self::FORMAT,
            'version'     => self::VERSION,
            'erstellt_am' => date('c'),
            'absender'    => [
                'firma'        => $firma['firmenname'],
                'uid'          => $firma['uid_nummer'],
                'steuernummer' => $firma['steuernummer'],
            ],
            'artikel'     => $artikel,
        ];
        file_put_contents("$arbeit/daten.json", json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));

        $kurz      = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $firma['firmenname'] ?: 'nahtlos') ?: 'nahtlos')), '_');
        $dateiname = 'mietfach_' . ($kurz ?: 'nahtlos') . '_' . date('Y-m-d') . '.tar';
        $datei     = sys_get_temp_dir() . '/' . bin2hex(random_bytes(6)) . '_' . $dateiname;
        (new PharData($datei))->buildFromDirectory($arbeit);
        $this->entferne($arbeit);

        Logger::log('artikel.mietfach_export', 'artikel', null, [
            'anzahl' => count($artikel), 'bilder' => $bilder, 'datei' => $dateiname,
            'nummern' => array_slice(array_column($artikel, 'nummer'), 0, 50),
        ]);
        return ['datei' => $datei, 'dateiname' => $dateiname, 'anzahl' => count($artikel), 'bilder' => $bilder];
    }

    // ── Helfer ────────────────────────────────────────────────────────────────

    private function ean(int $artikelId): ?string
    {
        $stmt = $this->db->prepare("SELECT code FROM artikel_codes WHERE artikel_id = ? AND typ = 'GTIN13' LIMIT 1");
        $stmt->execute([$artikelId]);
        return $stmt->fetchColumn() ?: null;
    }

    /** Standard-Verkaufspreis brutto (Kind, sonst Vater) */
    private function preis(int $artikelId, ?int $vaterId): ?float
    {
        $stmt = $this->db->prepare("
            SELECT ap.brutto_vk FROM artikel_preise ap
            JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
            WHERE ap.artikel_id = ? LIMIT 1
        ");
        foreach (array_filter([$artikelId, $vaterId]) as $id) {
            $stmt->execute([$id]);
            $p = $stmt->fetchColumn();
            if ($p !== false && (float)$p > 0) return round((float)$p, 2);
        }
        return null;
    }

    private function steuersatz(?int $stkId): float
    {
        if (!$stkId) return 20.0;
        $stmt = $this->db->prepare("SELECT satz FROM steuerklassen WHERE id = ?");
        $stmt->execute([$stkId]);
        $s = $stmt->fetchColumn();
        return $s === false ? 20.0 : (float)$s;
    }

    /** Erstes Bild des Artikels, sonst des Vaters – absoluter Dateipfad oder null */
    private function bild(int $artikelId, ?int $vaterId): ?string
    {
        $stmt = $this->db->prepare("SELECT dateiname FROM artikel_bilder WHERE artikel_id = ? ORDER BY position, id LIMIT 1");
        foreach (array_filter([$artikelId, $vaterId]) as $id) {
            $stmt->execute([$id]);
            $d = $stmt->fetchColumn();
            if ($d) {
                $pfad = $this->public . "/uploads/artikel/$id/" . basename($d);
                if (is_file($pfad)) return $pfad;
            }
        }
        return null;
    }

    /** Auf max. 800 px verkleinern, JPEG (Transparenz → weiß). */
    private function bildVerkleinern(string $quelle, string $ziel): bool
    {
        if (!extension_loaded('gd')) return false;
        $roh = @file_get_contents($quelle);
        $img = $roh !== false ? @imagecreatefromstring($roh) : false;
        if (!$img) return false;
        $b = imagesx($img); $h = imagesy($img);
        $f = min(1, self::BILD_MAX / max($b, $h));
        $nb = max(1, (int)round($b * $f)); $nh = max(1, (int)round($h * $f));
        $neu = imagecreatetruecolor($nb, $nh);
        imagefill($neu, 0, 0, imagecolorallocate($neu, 255, 255, 255));
        imagecopyresampled($neu, $img, 0, 0, 0, 0, $nb, $nh, $b, $h);
        $ok = imagejpeg($neu, $ziel, 82);
        imagedestroy($img); imagedestroy($neu);
        return $ok;
    }

    private function entferne(string $pfad): void
    {
        if (is_dir($pfad)) {
            foreach (array_diff(scandir($pfad), ['.', '..']) as $e) $this->entferne("$pfad/$e");
            rmdir($pfad);
        } elseif (is_file($pfad)) {
            unlink($pfad);
        }
    }
}
