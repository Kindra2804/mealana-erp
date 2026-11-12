<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * MerkmalService – Merkmale eines Artikels speichern, an die Kinder vererben, Werte zusammenführen
 * (Migration 233, Jacky 2026-11-12).
 *
 * Regeln:
 * - Ein Vater gibt seine Merkmale beim Speichern an alle Kinder weiter (wie Kategorien).
 *   Kinder dürfen abweichen, wenn man sie einzeln speichert -- der nächste Vater-Speichervorgang
 *   überschreibt das aber wieder.
 * - "Lauflänge je 100 g" und "Maschenprobe/10 cm" (Filter) werden nicht von Hand gepflegt, sondern aus
 *   "Gewicht/Länge" bzw. "Maschenprobe" abgeleitet (siehe bereicheAbleiten()).
 * - Werte zusammenführen: alle Artikel des Quellwerts bekommen den Zielwert, der Quellwert
 *   wird gelöscht (für Schreibvarianten wie "Acryl" / "Polyacryl").
 *
 * Merkmale werden im Code immer über ihren slug angesprochen, nie über die ID.
 */
class MerkmalService
{
    /** m je 100 g -> Schlüssel des Bereichs-Werts (merkmal_werte.symbol) */
    private const LAUFLAENGE_BEREICHE = [150 => 'lb_150', 250 => 'lb_250', 350 => 'lb_350', 450 => 'lb_450'];
    /** Maschen je 10 cm -> Schlüssel (klassische Abstufung, unabhängig von der Drops-Garngruppe) */
    private const MASCHEN_BEREICHE = [10 => 'mp_10', 16 => 'mp_16', 20 => 'mp_20', 25 => 'mp_25', 30 => 'mp_30'];

    private PDO $db;
    private array $idCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** merkmale.id zu einem slug (null, wenn es das Merkmal nicht gibt) */
    public function merkmalId(string $slug): ?int
    {
        if (!array_key_exists($slug, $this->idCache)) {
            $st = $this->db->prepare("SELECT id FROM merkmale WHERE slug = ? AND aktiv = 1 LIMIT 1");
            $st->execute([$slug]);
            $this->idCache[$slug] = ($id = $st->fetchColumn()) ? (int) $id : null;
        }
        return $this->idCache[$slug];
    }

    /** Wert-ID für einen Text; legt ihn an, wenn es ihn noch nicht gibt (Vergleich ohne Groß/klein). */
    public function wertIdFindenOderAnlegen(int $merkmalId, string $wert): int
    {
        $wert = trim($wert);
        $st = $this->db->prepare("SELECT id FROM merkmal_werte WHERE merkmal_id = ? AND wert = ? LIMIT 1");
        $st->execute([$merkmalId, $wert]);
        if ($id = $st->fetchColumn()) return (int) $id;

        $st = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM merkmal_werte WHERE merkmal_id = ?");
        $st->execute([$merkmalId]);
        $this->db->prepare("INSERT INTO merkmal_werte (merkmal_id, wert, sort_order) VALUES (?, ?, ?)")
                 ->execute([$merkmalId, $wert, (int) $st->fetchColumn()]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Alle Merkmale eines Artikels ersetzen (Artikel-Detail, Tab Merkmale).
     * @param array $merkmale merkmal_id => [wert_id, ...]
     * @return int Anzahl Kinder, an die vererbt wurde
     */
    public function speichern(int $artikelId, array $merkmale): int
    {
        $eigeneTx = !$this->db->inTransaction();
        if ($eigeneTx) $this->db->beginTransaction();
        try {
            $this->ersetzen($artikelId, $merkmale);
            $this->bereicheAbleiten($artikelId);
            $kinder = $this->anKinderVererben($artikelId);
            if ($eigeneTx) $this->db->commit();
            return $kinder;
        } catch (Throwable $e) {
            if ($eigeneTx) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Setzt die Werte EINES Merkmals (slug) bei einem Artikel -- andere Merkmale bleiben unberührt.
     * Für den Import. Leere Liste = Merkmal beim Artikel entfernen.
     * @param string[] $werte Texte
     */
    public function setzeMerkmal(int $artikelId, string $slug, array $werte): void
    {
        $mid = $this->merkmalId($slug);
        if (!$mid) throw new RuntimeException("Merkmal '$slug' fehlt -- Migration 233 eingespielt?");
        $this->db->prepare("DELETE FROM artikel_merkmale WHERE artikel_id = ? AND merkmal_id = ?")->execute([$artikelId, $mid]);
        $ins = $this->db->prepare("INSERT IGNORE INTO artikel_merkmale (artikel_id, merkmal_id, merkmal_wert_id) VALUES (?, ?, ?)");
        foreach (array_unique($werte) as $w) {
            if (trim($w) === '') continue;
            $ins->execute([$artikelId, $mid, $this->wertIdFindenOderAnlegen($mid, $w)]);
        }
    }

    /** Fügt einen Wert hinzu, ohne die übrigen Werte des Merkmals zu entfernen (z.B. Auszeichnung BIO). */
    public function wertHinzufuegen(int $artikelId, string $slug, string $wert): void
    {
        $mid = $this->merkmalId($slug);
        if (!$mid) return;
        $this->db->prepare("INSERT IGNORE INTO artikel_merkmale (artikel_id, merkmal_id, merkmal_wert_id) VALUES (?, ?, ?)")
                 ->execute([$artikelId, $mid, $this->wertIdFindenOderAnlegen($mid, $wert)]);
    }

    /**
     * Setzt die Filter-Bereiche, die sich aus genauen Werten ergeben:
     *   "Lauflänge je 100 g" aus "Gewicht/Länge"   ("50 g = 125 m" -> 250 m -> "151–250 m")
     *   "Maschenprobe/10 cm" aus "Maschenprobe"     ("10 x 10 cm = 21 M x 28 R" -> 21 M -> "21–25 M")
     * Nur wenn der genaue Wert lesbar ist -- sonst bleibt ein von Hand gesetzter Bereich stehen.
     */
    public function bereicheAbleiten(int $artikelId): void
    {
        $this->bereichSetzen($artikelId, 'gewicht_laenge', 'lauflaenge_bereich',
            self::meterJe100g(...), self::LAUFLAENGE_BEREICHE, 'lb_mehr');
        $this->bereichSetzen($artikelId, 'maschenprobe', 'maschenprobe_10cm',
            self::maschenJe10cm(...), self::MASCHEN_BEREICHE, 'mp_mehr');
    }

    private function bereichSetzen(int $artikelId, string $quellSlug, string $zielSlug, callable $zahl, array $bereiche, string $sonst): void
    {
        $zielId  = $this->merkmalId($zielSlug);
        $quellId = $this->merkmalId($quellSlug);
        if (!$zielId || !$quellId) return;

        $st = $this->db->prepare("SELECT w.wert FROM artikel_merkmale am JOIN merkmal_werte w ON w.id = am.merkmal_wert_id
                                  WHERE am.artikel_id = ? AND am.merkmal_id = ? LIMIT 1");
        $st->execute([$artikelId, $quellId]);
        $wert = $zahl((string) $st->fetchColumn());
        if ($wert === null) return;

        $symbol = $sonst;
        foreach ($bereiche as $bis => $s) {
            if ($wert <= $bis) { $symbol = $s; break; }
        }
        $st = $this->db->prepare("SELECT id FROM merkmal_werte WHERE merkmal_id = ? AND symbol = ? LIMIT 1");
        $st->execute([$zielId, $symbol]);
        $wid = $st->fetchColumn();
        if (!$wid) return;
        $this->db->prepare("DELETE FROM artikel_merkmale WHERE artikel_id = ? AND merkmal_id = ?")->execute([$artikelId, $zielId]);
        $this->db->prepare("INSERT IGNORE INTO artikel_merkmale (artikel_id, merkmal_id, merkmal_wert_id) VALUES (?, ?, ?)")
                 ->execute([$artikelId, $zielId, $wid]);
    }

    /** "10 x 10 cm = 21 M x 28 R" -> 21 (Maschen je 10 cm), null wenn nicht lesbar */
    public static function maschenJe10cm(string $text): ?float
    {
        return preg_match('/=\s*([\d.,]+)\s*M\b/u', $text, $t) ? (float) str_replace(',', '.', $t[1]) : null;
    }

    /** "50 g = 125 m" -> 250.0 (Meter je 100 g), null wenn nicht lesbar */
    public static function meterJe100g(string $text): ?float
    {
        if (!preg_match('/([\d.,]+)\s*g\s*=\s*([\d.,]+)\s*m/iu', $text, $t)) return null;
        $g = (float) str_replace(',', '.', $t[1]);
        $m = (float) str_replace(',', '.', $t[2]);
        return $g > 0 ? round($m * 100 / $g, 1) : null;
    }

    /** Kopiert alle Merkmale eines Vaters 1:1 auf seine Kinder. Liefert die Anzahl Kinder. */
    public function anKinderVererben(int $vaterId): int
    {
        $st = $this->db->prepare("SELECT id FROM artikel WHERE vaterartikel_id = ?");
        $st->execute([$vaterId]);
        $kinder = $st->fetchAll(PDO::FETCH_COLUMN);
        if (!$kinder) return 0;

        $in = implode(',', array_map('intval', $kinder));
        $this->db->exec("DELETE FROM artikel_merkmale WHERE artikel_id IN ($in)");
        $this->db->prepare("
            INSERT INTO artikel_merkmale (artikel_id, merkmal_id, merkmal_wert_id)
            SELECT k.id, am.merkmal_id, am.merkmal_wert_id
            FROM artikel_merkmale am JOIN artikel k ON k.vaterartikel_id = am.artikel_id
            WHERE am.artikel_id = ?")->execute([$vaterId]);
        return count($kinder);
    }

    /**
     * Quellwert in Zielwert aufgehen lassen (gleiches Merkmal). Liefert die Anzahl umgestellter Artikel.
     */
    public function zusammenfuehren(int $quellWertId, int $zielWertId): int
    {
        if ($quellWertId === $zielWertId) throw new InvalidArgumentException('Quelle und Ziel sind derselbe Wert.');
        $st = $this->db->prepare("SELECT id, merkmal_id FROM merkmal_werte WHERE id IN (?, ?)");
        $st->execute([$quellWertId, $zielWertId]);
        $werte = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($werte) !== 2) throw new InvalidArgumentException('Wert nicht gefunden.');
        if ($werte[$quellWertId] !== $werte[$zielWertId]) throw new InvalidArgumentException('Nur Werte desselben Merkmals lassen sich zusammenführen.');

        $eigeneTx = !$this->db->inTransaction();
        if ($eigeneTx) $this->db->beginTransaction();
        try {
            // Artikel, die schon beide Werte haben, behalten nur den Zielwert (UNIQUE artikel_id+wert)
            $n = $this->db->prepare("UPDATE IGNORE artikel_merkmale SET merkmal_wert_id = ? WHERE merkmal_wert_id = ?");
            $n->execute([$zielWertId, $quellWertId]);
            $anzahl = $n->rowCount();
            $this->db->prepare("DELETE FROM artikel_merkmale WHERE merkmal_wert_id = ?")->execute([$quellWertId]);
            $this->db->prepare("DELETE FROM merkmal_werte WHERE id = ?")->execute([$quellWertId]);
            if ($eigeneTx) $this->db->commit();
            return $anzahl;
        } catch (Throwable $e) {
            if ($eigeneTx) $this->db->rollBack();
            throw $e;
        }
    }

    /** Wie viele Artikel nutzen die Werte eines Merkmals? wert_id => Anzahl */
    public function nutzung(): array
    {
        return $this->db->query("SELECT merkmal_wert_id, COUNT(*) FROM artikel_merkmale GROUP BY merkmal_wert_id")
                        ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function ersetzen(int $artikelId, array $merkmale): void
    {
        $this->db->prepare("DELETE FROM artikel_merkmale WHERE artikel_id = ?")->execute([$artikelId]);
        $ins = $this->db->prepare("INSERT IGNORE INTO artikel_merkmale (artikel_id, merkmal_id, merkmal_wert_id) VALUES (?, ?, ?)");
        foreach ($merkmale as $merkmalId => $wertIds) {
            foreach ((array) $wertIds as $wertId) {
                $ins->execute([$artikelId, (int) $merkmalId, (int) $wertId]);
            }
        }
    }
}
