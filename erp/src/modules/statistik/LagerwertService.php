<?php

require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/logger.php';

/**
 * LagerwertService – bewerteter Lagerbestand (live) + festgehaltene Schnappschüsse.
 *
 * Bewertung je Artikel (netto, wie Marge/Statistik), erste vorhandene Quelle gewinnt:
 *   1. wareneingang       – EK der letzten Lagerbewegung 'eingang' mit EK (echter EK aus der Bestellung)
 *   2. standardlieferant  – artikel_lieferanten.netto_ek des Standardlieferanten
 *   3. lieferant          – günstigster aktiver Lieferanten-EK
 *   4. vater              – EK des Vater- bzw. Originalartikels (Kinder bekommen beim Anlegen
 *                           keinen EK mitkopiert, Zustandsartikel -RET/-GEB/-BSC auch nicht)
 *   5. keiner             – 0 € → erscheint in der Warnliste "ohne EK"
 *
 * Gezählt: eigene Lager + Händler-Außenlager (Kommissionsware bleibt bis zur Verkaufsmeldung
 * unser Eigentum). Nie: Partner-Lager, Partnerware, Artikel ohne Lagerbestandsführung.
 */
class LagerwertService
{
    public const ANLAESSE = [
        'monatsende'         => 'Monatsende',
        'inventur_start'     => 'Inventur-Start',
        'inventur_abschluss' => 'Inventur-Abschluss',
    ];

    public const EK_QUELLEN = [
        'wareneingang'      => 'letzter Wareneingang',
        'standardlieferant' => 'Standardlieferant',
        'lieferant'         => 'günstigster Lieferant',
        'vater'             => 'Vater-/Originalartikel',
        'keiner'            => 'kein EK',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Bewertete Bestandsliste je Artikel und Lager (Chargen zusammengefasst), nur Mengen > 0.
     * @return list<array{artikel_id:int, artikelnummer:string, artikel_name:string, lager_id:int,
     *                    lager_name:string, lager_beziehung:string, menge:float, ek_netto:float,
     *                    ek_quelle:string, wert:float}>
     */
    public function berechne(): array
    {
        $stmt = $this->db->query("
            SELECT
                a.id            AS artikel_id,
                a.artikelnummer,
                a.name          AS artikel_name,
                COALESCE(a.zustand_vater_id, a.vaterartikel_id) AS eltern_id,
                l.id            AS lager_id,
                l.name          AS lager_name,
                l.lager_beziehung,
                SUM(lb.bestand) AS menge
            FROM lagerbestand lb
            JOIN artikel a ON a.id = lb.artikel_id
            JOIN lager   l ON l.id = lb.lager_id
            WHERE l.lager_beziehung IN ('eigen', 'haendler_aussenlager')
              AND a.partner_id IS NULL
              AND COALESCE(a.keine_lagerbestandsfuehrung, 0) = 0
            GROUP BY a.id, l.id
            HAVING SUM(lb.bestand) > 0
            ORDER BY l.id, a.artikelnummer
        ");
        $zeilen = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$zeilen) return [];

        $ek = $this->ladeEkQuellen();

        $ergebnis = [];
        foreach ($zeilen as $z) {
            $id       = (int)$z['artikel_id'];
            $elternId = $z['eltern_id'] !== null ? (int)$z['eltern_id'] : null;

            if (isset($ek['wareneingang'][$id])) {
                [$preis, $quelle] = [$ek['wareneingang'][$id], 'wareneingang'];
            } elseif (isset($ek['standard'][$id])) {
                [$preis, $quelle] = [$ek['standard'][$id], 'standardlieferant'];
            } elseif (isset($ek['min'][$id])) {
                [$preis, $quelle] = [$ek['min'][$id], 'lieferant'];
            } elseif ($elternId && ($ek['standard'][$elternId] ?? $ek['min'][$elternId] ?? null) !== null) {
                [$preis, $quelle] = [$ek['standard'][$elternId] ?? $ek['min'][$elternId], 'vater'];
            } else {
                [$preis, $quelle] = [0.0, 'keiner'];
            }

            $menge = (float)$z['menge'];
            $ergebnis[] = [
                'artikel_id'      => $id,
                'artikelnummer'   => $z['artikelnummer'],
                'artikel_name'    => $z['artikel_name'],
                'eltern_id'       => $elternId,
                'lager_id'        => (int)$z['lager_id'],
                'lager_name'      => $z['lager_name'],
                'lager_beziehung' => $z['lager_beziehung'],
                'menge'           => $menge,
                'ek_netto'        => $preis,
                'ek_quelle'       => $quelle,
                'wert'            => round($menge * $preis, 2),
            ];
        }
        return $ergebnis;
    }

    /** EK-Quellen je Artikel-ID: ['wareneingang' => [id => ek], 'standard' => [...], 'min' => [...]] */
    private function ladeEkQuellen(): array
    {
        $we = $this->db->query("
            SELECT b.artikel_id, b.ek_preis
            FROM lager_bewegungen b
            JOIN (SELECT artikel_id, MAX(id) AS max_id
                  FROM lager_bewegungen
                  WHERE bewegungstyp = 'eingang' AND ek_preis > 0
                  GROUP BY artikel_id) x ON x.max_id = b.id
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $standard = $this->db->query("
            SELECT artikel_id, MAX(netto_ek)
            FROM artikel_lieferanten
            WHERE standard_lieferant = 1 AND aktiv = 1 AND netto_ek > 0
            GROUP BY artikel_id
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $min = $this->db->query("
            SELECT artikel_id, MIN(netto_ek)
            FROM artikel_lieferanten
            WHERE aktiv = 1 AND netto_ek > 0
            GROUP BY artikel_id
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $float = fn(array $a) => array_map('floatval', $a);
        return ['wareneingang' => $float($we), 'standard' => $float($standard), 'min' => $float($min)];
    }

    /**
     * Summen aus einer bewerteten Bestandsliste: gesamt, eigen/Händler getrennt und je Lager.
     */
    public function zusammenfassen(array $positionen): array
    {
        $lager = [];
        $artikel = [];
        $ohneEk = [];
        $sum = ['wert_eigen' => 0.0, 'wert_haendler' => 0.0, 'menge_gesamt' => 0.0];

        foreach ($positionen as $p) {
            $lid = $p['lager_id'];
            $lager[$lid] ??= [
                'lager_id' => $lid, 'lager_name' => $p['lager_name'], 'lager_beziehung' => $p['lager_beziehung'],
                'wert' => 0.0, 'menge' => 0.0, 'artikel' => [], 'ohne_ek' => [],
            ];
            $lager[$lid]['wert']  += $p['wert'];
            $lager[$lid]['menge'] += $p['menge'];
            $lager[$lid]['artikel'][$p['artikel_id']] = true;
            if ($p['ek_quelle'] === 'keiner') {
                $lager[$lid]['ohne_ek'][$p['artikel_id']] = true;
                $ohneEk[$p['artikel_id']] = true;
            }
            $artikel[$p['artikel_id']] = true;
            $sum[$p['lager_beziehung'] === 'haendler_aussenlager' ? 'wert_haendler' : 'wert_eigen'] += $p['wert'];
            $sum['menge_gesamt'] += $p['menge'];
        }

        foreach ($lager as &$l) {
            $l['wert']            = round($l['wert'], 2);
            $l['artikel_anzahl']  = count($l['artikel']);
            $l['artikel_ohne_ek'] = count($l['ohne_ek']);
            unset($l['artikel'], $l['ohne_ek']);
        }
        unset($l);

        return [
            'wert_eigen'      => round($sum['wert_eigen'], 2),
            'wert_haendler'   => round($sum['wert_haendler'], 2),
            'wert_gesamt'     => round($sum['wert_eigen'] + $sum['wert_haendler'], 2),
            'menge_gesamt'    => $sum['menge_gesamt'],
            'artikel_anzahl'  => count($artikel),
            'artikel_ohne_ek' => count($ohneEk),
            'lager'           => array_values($lager),
        ];
    }

    /**
     * Hält den aktuellen Lagerwert als Schnappschuss fest. Läuft in einer offenen Transaktion
     * mit (z.B. Inventur-Abschluss), sonst in einer eigenen.
     */
    public function festhalten(string $anlass, ?int $inventurLaufId = null, ?int $benutzerId = null): int
    {
        if (!isset(self::ANLAESSE[$anlass])) {
            throw new InvalidArgumentException("Unbekannter Lagerwert-Anlass: $anlass");
        }
        $benutzerId ??= $_SESSION['benutzer']['id'] ?? null;

        $positionen = $this->berechne();
        $summe      = $this->zusammenfassen($positionen);

        $eigeneTransaktion = !$this->db->inTransaction();
        if ($eigeneTransaktion) $this->db->beginTransaction();
        try {
            $this->db->prepare("
                INSERT INTO lagerwert_snapshots
                    (stichtag, anlass, inventur_lauf_id, wert_eigen, wert_haendler, menge_gesamt,
                     artikel_anzahl, artikel_ohne_ek, benutzer_id)
                VALUES (NOW(), :anlass, :lauf, :eigen, :haendler, :menge, :anzahl, :ohne_ek, :benutzer)
            ")->execute([
                'anlass'   => $anlass,
                'lauf'     => $inventurLaufId,
                'eigen'    => $summe['wert_eigen'],
                'haendler' => $summe['wert_haendler'],
                'menge'    => $summe['menge_gesamt'],
                'anzahl'   => $summe['artikel_anzahl'],
                'ohne_ek'  => $summe['artikel_ohne_ek'],
                'benutzer' => $benutzerId,
            ]);
            $id = (int)$this->db->lastInsertId();

            $insLager = $this->db->prepare("
                INSERT INTO lagerwert_snapshot_lager
                    (snapshot_id, lager_id, lager_name, lager_beziehung, wert, menge, artikel_anzahl, artikel_ohne_ek)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($summe['lager'] as $l) {
                $insLager->execute([$id, $l['lager_id'], $l['lager_name'], $l['lager_beziehung'],
                                    $l['wert'], $l['menge'], $l['artikel_anzahl'], $l['artikel_ohne_ek']]);
            }

            // Mehrzeilige INSERTs in Blöcken — bei ~3.500 Positionen sonst 3.500 Roundtrips
            foreach (array_chunk($positionen, 500) as $block) {
                $platzhalter = implode(',', array_fill(0, count($block), '(?,?,?,?,?,?,?,?,?)'));
                $werte = [];
                foreach ($block as $p) {
                    array_push($werte, $id, $p['artikel_id'], $p['artikelnummer'], $p['artikel_name'],
                               $p['lager_id'], $p['menge'], $p['ek_netto'], $p['ek_quelle'], $p['wert']);
                }
                $this->db->prepare("
                    INSERT INTO lagerwert_snapshot_positionen
                        (snapshot_id, artikel_id, artikelnummer, artikel_name, lager_id, menge, ek_netto, ek_quelle, wert)
                    VALUES $platzhalter
                ")->execute($werte);
            }

            if ($eigeneTransaktion) $this->db->commit();
        } catch (Throwable $e) {
            if ($eigeneTransaktion && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        Logger::log('lagerwert.festgehalten', 'lagerwert_snapshots', $id, [
            'anlass' => $anlass,
            'wert'   => $summe['wert_gesamt'],
            'inventur_lauf_id' => $inventurLaufId,
        ], $benutzerId);

        return $id;
    }

    /**
     * Wie festhalten(), aber ein Fehler darf den auslösenden Vorgang (Inventur) nie
     * blockieren — er wird nur geloggt.
     */
    public function festhaltenOhneAbbruch(string $anlass, ?int $inventurLaufId = null, ?int $benutzerId = null): ?int
    {
        try {
            return $this->festhalten($anlass, $inventurLaufId, $benutzerId);
        } catch (Throwable $e) {
            Logger::log('lagerwert.fehler', 'inventur_laeufe', $inventurLaufId, [
                'anlass' => $anlass,
                'fehler' => $e->getMessage(),
            ], $benutzerId ?? ($_SESSION['benutzer']['id'] ?? null), 'error');
            return null;
        }
    }

    public function monatsendeVorhanden(string $jahrMonat): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM lagerwert_snapshots
            WHERE anlass = 'monatsende' AND DATE_FORMAT(stichtag, '%Y-%m') = ?
        ");
        $stmt->execute([$jahrMonat]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function findSnapshots(int $limit = 60): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, (s.wert_eigen + s.wert_haendler) AS wert_gesamt,
                   il.scope_bezeichnung AS inventur_bezeichnung
            FROM lagerwert_snapshots s
            LEFT JOIN inventur_laeufe il ON il.id = s.inventur_lauf_id
            ORDER BY s.stichtag DESC, s.id DESC
            LIMIT " . max(1, $limit)
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSnapshot(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT *, (wert_eigen + wert_haendler) AS wert_gesamt FROM lagerwert_snapshots WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findSnapshotLager(int $snapshotId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM lagerwert_snapshot_lager WHERE snapshot_id = ? ORDER BY lager_beziehung, lager_name");
        $stmt->execute([$snapshotId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSnapshotPositionen(int $snapshotId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, COALESCE(sl.lager_name, '') AS lager_name
            FROM lagerwert_snapshot_positionen p
            LEFT JOIN lagerwert_snapshot_lager sl ON sl.snapshot_id = p.snapshot_id AND sl.lager_id = p.lager_id
            WHERE p.snapshot_id = ?
            ORDER BY sl.lager_name, p.artikelnummer
        ");
        $stmt->execute([$snapshotId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * IDs der in der Artikelliste sichtbaren Zeilen (Väter/Einzelartikel), unter denen ein
     * bewerteter Artikel mit Bestand ohne EK hängt — für den Qualitätsfilter "Kein EK".
     * Kinder → Vater, Zustandsartikel → Original (→ dessen Vater).
     */
    public function listenIdsOhneEk(): array
    {
        $ids = [];
        foreach ($this->berechne() as $p) {
            if ($p['ek_quelle'] === 'keiner') $ids[$p['artikel_id']] = true;
        }
        if (!$ids) return [];

        $eltern = $this->db->query("SELECT id, COALESCE(zustand_vater_id, vaterartikel_id) FROM artikel
                                    WHERE vaterartikel_id IS NOT NULL OR zustand_vater_id IS NOT NULL")
                           ->fetchAll(PDO::FETCH_KEY_PAIR);
        $ergebnis = [];
        foreach (array_keys($ids) as $id) {
            for ($i = 0; $i < 3 && isset($eltern[$id]); $i++) $id = (int)$eltern[$id];
            $ergebnis[$id] = true;
        }
        return array_keys($ergebnis);
    }
}
