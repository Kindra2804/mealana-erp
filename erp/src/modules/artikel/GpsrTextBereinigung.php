<?php
/**
 * GPSR-Blöcke aus Artikelbeschreibungen entfernen (Artikel → GPSR-Texte), 2026-11-12.
 *
 * Anlass: Der JTL-Import brachte bei ca. 2.700 Artikeln (KnitPro, TOPP, Addi, ProLana, Clover …)
 * die GPSR-Herstellerangaben als Text in der Beschreibung mit – als Block
 * „Kontaktinformation gem. Art. 19 EU GPSR … Verantwortliche Person gem. Art. 19 EU GPSR …“
 * bzw. als HTML des JTL-Plugins „xo-gpsr“. Im Shop stand das doppelt: einmal unschön in der
 * Beschreibung, einmal sauber im Reiter „Produktsicherheit“ (aus den Hersteller-Stammdaten).
 *
 * Regel: Ab dem Absatz mit der ersten GPSR-Überschrift wird alles abgeschnitten, leere Absätze
 * davor ebenso. Was übrig bleibt und nur aus Leerraum besteht, wird zu ''.
 * Vor dem Ändern werden alle alten Texte nach storage/gpsr_sicherung/ geschrieben.
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../shop/ShopSyncRepository.php';

class GpsrTextBereinigung
{
    /** Merkmale, an denen der Block beginnt */
    private const MARKEN = ['Kontaktinformation gem. Art. 19', 'Verantwortliche Person gem. Art. 19', 'xo-gpsr-'];
    private const FELDER = ['beschreibung', 'kurzbeschreibung'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Text ohne GPSR-Block, oder null, wenn kein Block drin ist. */
    public static function bereinige(?string $html): ?string
    {
        if ($html === null || $html === '') return null;
        $pos = [];
        foreach (self::MARKEN as $m) {
            $p = stripos($html, $m);
            if ($p !== false) $pos[] = $p;
        }
        if (!$pos) return null;
        $p = min($pos);

        // Beginn des Absatzes (bzw. Containers), in dem der Block startet
        $start = strripos(substr($html, 0, $p), '<p');
        if ($start === false) $start = strripos(substr($html, 0, $p), '<div');
        if ($start === false) $start = strripos(substr($html, 0, $p), '<span');
        if ($start === false) $start = $p;

        // leere Absätze / Zeilenumbrüche davor mitnehmen
        $rest = substr($html, 0, $start);
        while (preg_match('~(<p[^>]*>(\s|&nbsp;|<br\s*/?>)*</p>|<br\s*/?>)\s*$~i', $rest, $mm)) {
            $rest = substr($rest, 0, -strlen($mm[0]));
        }
        $rest = rtrim($rest);
        $nurText = trim(html_entity_decode(strip_tags($rest), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0");
        return $nurText === '' ? '' : $rest;
    }

    private function wo(): string
    {
        return "(a.beschreibung LIKE '%GPSR%' OR a.beschreibung LIKE '%xo-gpsr-%' OR a.kurzbeschreibung LIKE '%GPSR%' OR a.kurzbeschreibung LIKE '%xo-gpsr-%')";
    }

    /** Übersicht: Zahlen, Väter/Einzelartikel-Liste, Hersteller ohne Adresse. */
    public function pruefe(): array
    {
        $zeilen = $this->db->query("
            SELECT a.id, a.artikelnummer, a.name, a.vaterartikel_id, a.beschreibung, a.kurzbeschreibung,
                   h.name AS hersteller, h.id AS hersteller_id,
                   (IFNULL(h.strasse,'') <> '' AND IFNULL(h.ort,'') <> '') AS hersteller_adresse
            FROM artikel a LEFT JOIN hersteller h ON h.id = a.hersteller_id
            WHERE " . $this->wo() . "
            ORDER BY h.name, a.name, a.artikelnummer
        ")->fetchAll();

        $erg = ['gesamt' => 0, 'vaeter' => 0, 'kinder' => 0, 'leer_danach' => 0, 'felder' => ['beschreibung' => 0, 'kurzbeschreibung' => 0],
                'liste' => [], 'hersteller' => [], 'ohne_adresse' => []];
        foreach ($zeilen as $z) {
            $leer = true; $treffer = false;
            foreach (self::FELDER as $f) {
                $neu = self::bereinige($z[$f]);
                if ($neu === null) { if (trim(strip_tags((string)$z[$f])) !== '') $leer = false; continue; }
                $treffer = true; $erg['felder'][$f]++;
                if ($neu !== '') $leer = false;
            }
            if (!$treffer) continue;
            $erg['gesamt']++;
            $istVater = $z['vaterartikel_id'] === null;
            $erg[$istVater ? 'vaeter' : 'kinder']++;
            if ($leer) $erg['leer_danach']++;
            $hn = $z['hersteller'] ?? '– ohne Hersteller –';
            $erg['hersteller'][$hn] = ($erg['hersteller'][$hn] ?? 0) + ($istVater ? 1 : 0);
            if (!$z['hersteller_adresse']) $erg['ohne_adresse'][$hn] = ($erg['ohne_adresse'][$hn] ?? 0) + 1;
            if ($istVater) $erg['liste'][] = ['id' => (int)$z['id'], 'artikelnummer' => $z['artikelnummer'], 'name' => $z['name'], 'hersteller' => $hn, 'bleibt_text' => !$leer];
        }
        arsort($erg['hersteller']);
        return $erg;
    }

    /** Entfernt die Blöcke. Sichert vorher alle alten Texte. Alles oder nichts. */
    public function entferne(): array
    {
        $zeilen = $this->db->query("SELECT a.id, a.artikelnummer, a.beschreibung, a.kurzbeschreibung FROM artikel a WHERE " . $this->wo())->fetchAll();
        $aenderungen = [];
        foreach ($zeilen as $z) {
            $neu = [];
            foreach (self::FELDER as $f) {
                $n = self::bereinige($z[$f]);
                if ($n !== null) $neu[$f] = $n;
            }
            if ($neu) $aenderungen[] = ['id' => (int)$z['id'], 'artikelnummer' => $z['artikelnummer'], 'alt' => array_intersect_key($z, $neu), 'neu' => $neu];
        }
        if (!$aenderungen) return ['erfolg' => true, 'geaendert' => 0, 'sicherung' => null];

        // Sicherung
        $ordner = __DIR__ . '/../../../storage/gpsr_sicherung';
        if (!is_dir($ordner) && !mkdir($ordner, 0775, true) && !is_dir($ordner)) {
            return ['erfolg' => false, 'fehler' => 'Sicherungsordner storage/gpsr_sicherung konnte nicht angelegt werden.'];
        }
        $datei = $ordner . '/gpsr_' . date('Ymd_His') . '.json';
        $json = json_encode(array_map(fn($a) => ['id' => $a['id'], 'artikelnummer' => $a['artikelnummer'], 'alt' => $a['alt']], $aenderungen), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($datei, $json) === false) {
            return ['erfolg' => false, 'fehler' => 'Sicherung konnte nicht geschrieben werden – nichts geändert.'];
        }

        $sync = new ShopSyncRepository();
        $this->db->beginTransaction();
        try {
            foreach ($aenderungen as $a) {
                $set = implode(', ', array_map(fn($f) => "$f = :$f", array_keys($a['neu'])));
                $this->db->prepare("UPDATE artikel SET $set WHERE id = :id")->execute($a['neu'] + ['id' => $a['id']]);
            }
            $ids = array_column($aenderungen, 'id');
            foreach (array_chunk($ids, 500) as $teil) $sync->markiereFuerErneutenSync($teil);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => 'Entfernen fehlgeschlagen, nichts geändert: ' . $e->getMessage()];
        }
        Logger::log('artikel.gpsr_texte_entfernt', 'artikel', null, ['anzahl' => count($aenderungen), 'sicherung' => basename($datei)]);
        return ['erfolg' => true, 'geaendert' => count($aenderungen), 'sicherung' => 'storage/gpsr_sicherung/' . basename($datei)];
    }
}
