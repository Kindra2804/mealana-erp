<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * PreisMassenService – Massenaktion "Preise ändern" (Artikelliste → artikel/preise_massen.php)
 *
 * Ändert einen Preis (Ziel) vieler Artikel auf einmal, gerechnet von einer Basis:
 *   Ziel/Basis: 'kg:<id>' = Kundengruppen-Preis (artikel_preise), 'uvp' = artikel.uvp
 *   Art:        'fest' (neuer Preis = Wert), 'euro' (Basis + Wert), 'prozent' (Basis + Wert %)
 *   Rundung:    siehe rundePreis()
 *
 * Gewählte Väter nehmen ihre Kinder mit. Ein Kind, dessen Ziel-Preis vom Vater abweicht
 * (z.B. größere Größe teurer), ist "abweichend": in der Vorschau mit "!" markiert und
 * standardmäßig NICHT angehakt. Ein direkt gewähltes Kind wird immer normal behandelt.
 *
 * Es gibt kein echtes Preis-Vererben: jedes Kind hat seine eigene Zeile in artikel_preise.
 * Deshalb schreibt uebernehmen() jede Zeile einzeln.
 */
class PreisMassenService
{
    public const RUNDUNGEN = [
        'keine' => 'nicht runden (auf Cent)',
        '0.10'  => 'auf 10 Cent',
        '0.50'  => 'auf ,00 oder ,50',
        '0.90'  => 'auf ,90',
        '0.95'  => 'auf ,95',
        '0.99'  => 'auf ,99',
        '1.00'  => 'auf volle Euro',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Auswahl für Ziel und Basis: alle Kundengruppen (Standard zuerst) + UVP */
    public function preisArten(): array
    {
        $arten = [];
        foreach ($this->db->query("SELECT id, name, ist_standard FROM kundengruppen ORDER BY ist_standard DESC, id") as $kg) {
            $arten['kg:' . $kg['id']] = $kg['ist_standard'] ? 'Standardpreis (' . $kg['name'] . ')' : 'Kundengruppe ' . $kg['name'];
        }
        $arten['uvp'] = 'UVP';
        return $arten;
    }

    /**
     * Rundet einen errechneten Preis nach der gewählten Regel (siehe RUNDUNGEN), immer AUFwärts,
     * damit eine Preiserhöhung nicht durch die Rundung zur Senkung wird (8,95 + 5 % = 9,40 → 9,90, nicht 8,90).
     * Ein Preis, der schon passt, bleibt (9,90 bei ,90). Gerechnet wird in ganzen Cent (keine Kommazahl-Ungenauigkeit).
     * Geschrieben von Jacky.
     */
    public static function rundePreis(float $preis, string $rundung): float
    {
        $cent = (int) round($preis * 100);
        $endung = (int) round((float) $rundung * 100);
        $ergebnisInCent = match ($rundung) {
            'keine'                => $cent,
            '0.10'                 => (int) ceil($cent / 10) * 10,
            '0.50'                 => (int) ceil($cent / 50) * 50,
            '1.00'                 => (int) ceil($cent / 100) * 100,
            '0.90', '0.95', '0.99' => (int) (ceil(($cent - $endung) / 100) * 100 + $endung),
            default                => $cent,
        };

        $ergebnisInEuro = ($ergebnisInCent / 100);
        return round($ergebnisInEuro, 2);
    }

    /** Rechnet den neuen Preis aus der Basis. null = nicht berechenbar (Basis fehlt). */
    public static function rechne(?float $basis, string $art, float $wert, string $rundung): ?float
    {
        if ($art === 'fest') {
            $neu = $wert;
        } else {
            if ($basis === null || $basis <= 0) return null;   // 0,00 € ist kein brauchbarer Ausgangspreis
            $neu = $art === 'prozent' ? $basis * (1 + $wert / 100) : $basis + $wert;
        }
        $neu = self::rundePreis($neu, $rundung);
        return $neu > 0 ? $neu : null;
    }

    /**
     * Vorschau: je Artikel alt → neu. Nichts wird gespeichert.
     * @return array ['zeilen' => [...], 'ziel_name', 'basis_name']
     */
    public function vorschau(array $ids, string $ziel, string $basis, string $art, float $wert, string $rundung): array
    {
        $arten = $this->preisArten();
        if (!isset($arten[$ziel]))  throw new RuntimeException('Unbekannter Ziel-Preis');
        if (!isset($arten[$basis])) throw new RuntimeException('Unbekannte Basis');
        if (!in_array($art, ['fest', 'euro', 'prozent'], true)) throw new RuntimeException('Unbekannte Änderungsart');
        if (!isset(self::RUNDUNGEN[$rundung])) $rundung = 'keine';

        $artikel = $this->ladeMitKindern($ids);
        if (!$artikel) return ['zeilen' => [], 'ziel_name' => $arten[$ziel], 'basis_name' => $arten[$basis]];

        $alle   = array_keys($artikel);
        $zielP  = $this->preise($alle, $ziel);
        $basisP = $basis === $ziel ? $zielP : $this->preise($alle, $basis);

        $zeilen = [];
        foreach ($artikel as $id => $a) {
            $alt = $zielP[$id] ?? null;
            $bas = $basisP[$id] ?? null;
            $neu = self::rechne($bas, $art, $wert, $rundung);

            // Kind, das über seinen Vater mitkommt, mit anderem Preis als der Vater
            $abweichend = false;
            if ($a['ueber_vater']) {
                $vAlt = $zielP[$a['vaterartikel_id']] ?? null;
                $abweichend = $alt !== null && $vAlt !== null && abs($alt - $vAlt) > 0.004;
            }
            $hinweis = '';
            if ($neu === null) $hinweis = ($bas === null || $bas <= 0) && $art !== 'fest' ? 'keine Basis (' . $arten[$basis] . ' fehlt oder 0,00)' : 'Ergebnis ≤ 0';
            elseif ($abweichend) $hinweis = 'eigener Preis, weicht vom Vater ab';

            $zeilen[] = [
                'id'            => $id,
                'artikelnummer' => $a['artikelnummer'],
                'name'          => $a['name'],
                'vater_id'      => $a['ueber_vater'] ? (int) $a['vaterartikel_id'] : null,
                'ist_vater'     => $a['ist_vater'],
                'aktiv'         => (bool) $a['aktiv'],
                'alt'           => $alt,
                'basis'         => $bas,
                'neu'           => $neu,
                'abweichend'    => $abweichend,
                'anhaken'       => $neu !== null && !$abweichend && ($alt === null || abs($neu - $alt) > 0.004),
                'hinweis'       => $hinweis,
            ];
        }
        return ['zeilen' => $zeilen, 'ziel_name' => $arten[$ziel], 'basis_name' => $arten[$basis]];
    }

    /**
     * Speichert die angehakten Zeilen der Vorschau (Preis je Zeile evtl. von Hand überschrieben).
     * @param array $positionen [['id' => int, 'neu' => float], ...]
     * @param array $einstellung Ziel/Basis/Art/Wert/Rundung – nur fürs Protokoll
     */
    public function uebernehmen(string $ziel, array $positionen, array $einstellung): array
    {
        $arten = $this->preisArten();
        if (!isset($arten[$ziel])) throw new RuntimeException('Unbekannter Ziel-Preis');

        $neuJeId = [];
        foreach ($positionen as $p) {
            $id = (int) ($p['id'] ?? 0);
            $neu = round((float) ($p['neu'] ?? 0), 2);
            if ($id > 0 && $neu > 0) $neuJeId[$id] = $neu;
        }
        if (!$neuJeId) throw new RuntimeException('Keine Artikel zum Ändern angehakt');

        $ids = array_keys($neuJeId);
        $in  = implode(',', $ids);
        $info = $this->db->query("
            SELECT a.id, a.vaterartikel_id, s.satz
            FROM artikel a JOIN steuerklassen s ON s.id = a.steuerklasse_id
            WHERE a.id IN ($in)")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        $alt = $this->preise($ids, $ziel);

        $aenderungen = [];
        $this->db->beginTransaction();
        try {
            if ($ziel === 'uvp') {
                $st = $this->db->prepare("UPDATE artikel SET uvp = ? WHERE id = ?");
            } else {
                $kgId = (int) substr($ziel, 3);
                $upd = $this->db->prepare("UPDATE artikel_preise SET brutto_vk = ?, netto_vk = ? WHERE artikel_id = ? AND kundengruppen_id = ?");
                $ins = $this->db->prepare("INSERT INTO artikel_preise (artikel_id, kundengruppen_id, brutto_vk, netto_vk) VALUES (?, ?, ?, ?)");
            }
            foreach ($neuJeId as $id => $neu) {
                if (!isset($info[$id])) continue;
                if ($ziel === 'uvp') {
                    $st->execute([$neu, $id]);
                } else {
                    $netto = round($neu / (1 + (float) $info[$id]['satz'] / 100), 2);
                    if (array_key_exists($id, $alt)) $upd->execute([$neu, $netto, $id, $kgId]);
                    else                             $ins->execute([$id, $kgId, $neu, $netto]);
                }
                $aenderungen[] = ['id' => $id, 'alt' => $alt[$id] ?? null, 'neu' => $neu];
            }

            // Shop-Sync erkennt Änderungen nur über aktualisiert_am – Artikel und ihre Väter markieren
            $vaeter = array_filter(array_map(fn($r) => (int) $r['vaterartikel_id'], $info));
            $markieren = implode(',', array_unique(array_merge($ids, $vaeter)));
            $this->db->exec("UPDATE artikel SET aktualisiert_am = NOW() WHERE id IN ($markieren)");

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        unset($_SESSION['schilder_veraltet']);   // Dashboard-Zähler "Schilder veraltet" neu rechnen
        Logger::log('preis.massen', $ziel === 'uvp' ? 'artikel' : 'artikel_preise', null, [
            'ziel'        => $arten[$ziel],
            'basis'       => $arten[$einstellung['basis'] ?? ''] ?? null,
            'art'         => $einstellung['art'] ?? null,
            'wert'        => $einstellung['wert'] ?? null,
            'rundung'     => $einstellung['rundung'] ?? null,
            'anzahl'      => count($aenderungen),
            'aenderungen' => $aenderungen,
        ]);
        return ['anzahl' => count($aenderungen)];
    }

    /** Liste der letzten Massen-Preisänderungen (Protokoll unten auf der Seite) */
    public function protokoll(int $limit = 10): array
    {
        $st = $this->db->prepare("
            SELECT a.erstellt_am, a.details, TRIM(CONCAT(COALESCE(b.vorname,''),' ',COALESCE(b.nachname,''))) AS benutzer
            FROM aktivitaeten a LEFT JOIN benutzer b ON b.id = a.benutzer_id
            WHERE a.aktion = 'preis.massen' ORDER BY a.id DESC LIMIT ?");
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return array_map(function ($r) {
            $d = json_decode((string) $r['details'], true) ?: [];
            unset($d['aenderungen']);
            return ['wann' => $r['erstellt_am'], 'wer' => $r['benutzer']] + $d;
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    // ── intern ───────────────────────────────────────────────────────

    /**
     * Gewählte Artikel + Kinder gewählter Väter, sortiert Vater → seine Kinder.
     * @return array id => [artikelnummer, name, aktiv, vaterartikel_id, ist_vater, ueber_vater]
     */
    private function ladeMitKindern(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', $ids);
        $rows = $this->db->query("
            SELECT a.id, a.artikelnummer, a.name, a.aktiv, a.vaterartikel_id,
                   (SELECT COUNT(*) FROM artikel k WHERE k.vaterartikel_id = a.id) AS kinder,
                   (a.id IN ($in)) AS gewaehlt
            FROM artikel a
            WHERE a.id IN ($in) OR a.vaterartikel_id IN ($in)
            ORDER BY COALESCE(a.vaterartikel_id, a.id), a.vaterartikel_id IS NOT NULL, a.artikelnummer")->fetchAll(PDO::FETCH_ASSOC);

        $erg = [];
        foreach ($rows as $r) {
            $erg[(int) $r['id']] = [
                'artikelnummer'   => $r['artikelnummer'],
                'name'            => $r['name'],
                'aktiv'           => $r['aktiv'],
                'vaterartikel_id' => $r['vaterartikel_id'],
                'ist_vater'       => (int) $r['kinder'] > 0,
                'ueber_vater'     => !$r['gewaehlt'],   // kam nur über den gewählten Vater dazu
            ];
        }
        return $erg;
    }

    /** id => Brutto-Preis für eine Preisart ('kg:<id>' oder 'uvp'); fehlende Preise fehlen im Ergebnis */
    private function preise(array $ids, string $art): array
    {
        if (!$ids) return [];
        $in = implode(',', array_map('intval', $ids));
        if ($art === 'uvp') {
            $sql = "SELECT id, uvp FROM artikel WHERE id IN ($in) AND uvp IS NOT NULL";
        } else {
            $kgId = (int) substr($art, 3);
            $sql = "SELECT artikel_id, brutto_vk FROM artikel_preise WHERE kundengruppen_id = $kgId AND artikel_id IN ($in)";
        }
        return array_map('floatval', $this->db->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR));
    }
}
