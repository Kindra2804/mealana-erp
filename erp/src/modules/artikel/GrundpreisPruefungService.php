<?php
/**
 * Grundpreise prüfen und in Massen korrigieren (Artikel → Grundpreise).
 *
 * Anlass (2026-11-12, SOW-Umstieg): Der JTL-Import brachte falsche/fehlende Grundpreis-Angaben mit
 * (z. B. „Inhalt 100 kg“ statt 100 g) und vererbte sie nicht an die Kinder. Im Shop stand dann
 * „0,00 €/kg“ oder „enthält 100 kg“. Diese Seite zeigt Väter + Einzelartikel mit ihren Angaben,
 * erkennt Problemfälle und speichert mehrere Zeilen auf einmal.
 *
 * Beim Speichern: Felder am Vater/Einzelartikel setzen → propagiereZuKindern() (alle gemeinsamen
 * Vater-Felder, wie beim normalen Speichern eines Vaters) → für den Shop-Abgleich vormerken.
 *
 * Die Vorschau rechnet wie ShopSyncService::ermittleGrundpreisBasis(): Typ GARN + Einheit g → €/kg.
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/ArtikelRepository.php';
require_once __DIR__ . '/../shop/ShopSyncRepository.php';

class GrundpreisPruefungService
{
    /** Einheiten, die der Shop (Germanized) kennt und die das ERP hier anbietet */
    public const EINHEITEN = ['g', 'kg', 'ml', 'l', 'm', 'cm'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findTypen(): array
    {
        return $this->db->query("SELECT id, code, name FROM artikel_typen ORDER BY name")->fetchAll();
    }

    public function findShops(): array
    {
        return $this->db->query("SELECT id, name FROM shops WHERE ist_aktiv = 1 ORDER BY id")->fetchAll();
    }

    /**
     * Väter + Einzelartikel (aktiv) mit Grundpreis-Angaben, Kinder-Abweichungen und Preis für die Vorschau.
     * $filter: shop_id, typ_id, q, nur_probleme
     */
    public function finde(array $filter): array
    {
        $kg = (int)($this->db->query("SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1")->fetchColumn() ?: 1);
        $wo = ['a.vaterartikel_id IS NULL', 'a.aktiv = 1'];
        $p  = ['kg1' => $kg, 'kg2' => $kg];
        if (!empty($filter['shop_id'])) {
            $wo[] = 'EXISTS (SELECT 1 FROM artikel_shops ash WHERE ash.artikel_id = a.id AND ash.shop_id = :shop AND ash.aktiv = 1)';
            $p['shop'] = (int)$filter['shop_id'];
        }
        if (!empty($filter['typ_id'])) {
            $wo[] = 'a.artikeltyp_id = :typ';
            $p['typ'] = (int)$filter['typ_id'];
        }
        if (($filter['q'] ?? '') !== '') {
            $wo[] = '(a.artikelnummer LIKE :q1 OR a.name LIKE :q2)';
            $p['q1'] = $p['q2'] = '%' . $filter['q'] . '%';
        }

        $stmt = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name, a.artikeltyp_id, t.code AS typ_code,
                   a.inhalt_menge, a.inhalt_einheit, a.grundpreis_bezugsmenge, a.grundpreis_anzeigen,
                   h.name AS hersteller,
                   (SELECT COUNT(*) FROM artikel k WHERE k.vaterartikel_id = a.id) AS kinder,
                   (SELECT COUNT(*) FROM artikel k WHERE k.vaterartikel_id = a.id AND NOT (
                        k.inhalt_menge <=> a.inhalt_menge AND k.inhalt_einheit <=> a.inhalt_einheit
                        AND k.grundpreis_bezugsmenge <=> a.grundpreis_bezugsmenge
                        AND k.grundpreis_anzeigen <=> a.grundpreis_anzeigen)) AS kinder_abweichend,
                   COALESCE(
                       (SELECT MIN(pk.brutto_vk) FROM artikel k JOIN artikel_preise pk ON pk.artikel_id = k.id AND pk.kundengruppen_id = :kg1
                        WHERE k.vaterartikel_id = a.id AND k.aktiv = 1),
                       p.brutto_vk) AS preis
            FROM artikel a
            JOIN artikel_typen t ON t.id = a.artikeltyp_id
            LEFT JOIN hersteller h ON h.id = a.hersteller_id
            LEFT JOIN artikel_preise p ON p.artikel_id = a.id AND p.kundengruppen_id = :kg2
            WHERE " . implode(' AND ', $wo) . "
            ORDER BY a.name, a.artikelnummer
            LIMIT 2000
        ");
        $stmt->execute($p);

        $zeilen = [];
        foreach ($stmt->fetchAll() as $z) {
            $z['probleme'] = self::probleme($z);
            if (!empty($filter['nur_probleme']) && !$z['probleme']) continue;
            $zeilen[] = $z;
        }
        return $zeilen;
    }

    /** Problemfälle einer Zeile als kurze Texte (leer = alles in Ordnung). */
    public static function probleme(array $z): array
    {
        $f = [];
        $menge   = (float)$z['inhalt_menge'];
        $einheit = mb_strtolower(trim((string)$z['inhalt_einheit']));
        $an      = (int)$z['grundpreis_anzeigen'] === 1;
        $garn    = $z['typ_code'] === 'GARN';

        if ((int)$z['kinder_abweichend'] > 0) $f[] = "Kinder weichen ab ({$z['kinder_abweichend']})";
        if ($garn && !$an) $f[] = 'Garn ohne Grundpreis';
        if ($an && ($menge <= 0 || $einheit === '')) $f[] = 'Inhalt fehlt';
        if ($an && (float)$z['grundpreis_bezugsmenge'] <= 0 && !($garn && $einheit === 'g')) $f[] = 'Bezugsmenge fehlt';
        if ($einheit === 'kg' && $menge >= 10) $f[] = rtrim(rtrim(number_format($menge, 3, ',', ''), '0'), ',') . ' kg – wohl g?';
        if ($einheit === 'g' && $menge > 0 && ($menge < 1 || $menge > 5000)) $f[] = 'Inhalt unplausibel';
        if ($an && $einheit !== '' && !in_array($einheit, self::EINHEITEN, true)) $f[] = "Einheit „{$z['inhalt_einheit']}“ kennt der Shop nicht";
        if ($an && !$garn && in_array($einheit, ['g', 'kg'], true) && $z['typ_code'] === 'STANDARD') $f[] = 'Typ Standard – Wolle? (Garn rechnet je kg)';
        return $f;
    }

    /**
     * Speichert geänderte Zeilen. $zeilen: [{id, artikeltyp_id, inhalt_menge, inhalt_einheit,
     * grundpreis_bezugsmenge, grundpreis_anzeigen}]. Alle oder keine (Transaktion).
     */
    public function speichere(array $zeilen): array
    {
        $typen = array_column($this->findTypen(), 'code', 'id');
        $fehler = [];
        $saubere = [];
        foreach ($zeilen as $z) {
            $id = (int)($z['id'] ?? 0);
            $art = $this->db->prepare("SELECT id, artikelnummer FROM artikel WHERE id = ? AND vaterartikel_id IS NULL");
            $art->execute([$id]);
            $a = $art->fetch();
            if (!$a) { $fehler[] = "Artikel #$id nicht gefunden (oder ist ein Kind)."; continue; }
            $nr = $a['artikelnummer'];

            $typId   = (int)($z['artikeltyp_id'] ?? 0);
            $menge   = self::zahl($z['inhalt_menge'] ?? null);
            $einheit = mb_strtolower(trim((string)($z['inhalt_einheit'] ?? '')));
            $bezug   = self::zahl($z['grundpreis_bezugsmenge'] ?? null);
            $an      = !empty($z['grundpreis_anzeigen']) ? 1 : 0;

            if (!isset($typen[$typId])) { $fehler[] = "$nr: Artikeltyp fehlt."; continue; }
            if ($einheit !== '' && !in_array($einheit, self::EINHEITEN, true)) {
                // z. B. „Stk.“ aus JTL: ohne Grundpreis bedeutungslos → leeren; mit Grundpreis ein Fehler
                if (!$an) $einheit = '';
                else { $fehler[] = "{$nr}: Einheit „{$einheit}“ kennt der Shop nicht (g, kg, ml, l, m, cm)."; continue; }
            }
            if ($an) {
                if ($menge === null || $menge <= 0 || $einheit === '') { $fehler[] = "$nr: Für den Grundpreis braucht es Inhalt und Einheit."; continue; }
                if ($bezug === null || $bezug <= 0) $bezug = $typen[$typId] === 'GARN' ? 100 : null; // wie ArtikelService::wendeGrundpreisDefaultAn()
                if ($bezug === null) { $fehler[] = "$nr: Bezugsmenge fehlt."; continue; }
            }
            $saubere[] = ['id' => $id, 'artikeltyp_id' => $typId, 'inhalt_menge' => $menge, 'inhalt_einheit' => $einheit !== '' ? $einheit : null,
                          'grundpreis_bezugsmenge' => $bezug, 'grundpreis_anzeigen' => $an];
        }
        if ($fehler) return ['erfolg' => false, 'fehler' => $fehler];

        $repo = new ArtikelRepository();
        $sync = new ShopSyncRepository();
        $upd = $this->db->prepare("
            UPDATE artikel SET artikeltyp_id = :artikeltyp_id, inhalt_menge = :inhalt_menge, inhalt_einheit = :inhalt_einheit,
                   grundpreis_bezugsmenge = :grundpreis_bezugsmenge, grundpreis_anzeigen = :grundpreis_anzeigen
            WHERE id = :id AND vaterartikel_id IS NULL
        ");
        $this->db->beginTransaction();
        try {
            foreach ($saubere as $s) {
                $upd->execute($s);
                $this->vererbeUndVormerken($repo, $sync, $s['id']);
                Logger::log('artikel.grundpreis_massenpflege', 'artikel', $s['id'], $s);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => ['Speichern fehlgeschlagen: ' . $e->getMessage()]];
        }
        return ['erfolg' => true, 'gespeichert' => count($saubere)];
    }

    /** Nur Vererbung an die Kinder nachziehen (ohne Feldänderung), z. B. nach einem JTL-Import. */
    public function vererbe(array $ids): array
    {
        $repo = new ArtikelRepository();
        $sync = new ShopSyncRepository();
        $n = 0;
        $this->db->beginTransaction();
        try {
            foreach (array_unique(array_map('intval', $ids)) as $id) {
                $this->vererbeUndVormerken($repo, $sync, $id);
                Logger::log('artikel.grundpreis_vererbt', 'artikel', $id, []);
                $n++;
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => ['Vererben fehlgeschlagen: ' . $e->getMessage()]];
        }
        return ['erfolg' => true, 'gespeichert' => $n];
    }

    private function vererbeUndVormerken(ArtikelRepository $repo, ShopSyncRepository $sync, int $id): void
    {
        $repo->propagiereZuKindern($id);
        $kinder = $this->db->prepare("SELECT id FROM artikel WHERE vaterartikel_id = ?");
        $kinder->execute([$id]);
        $sync->markiereFuerErneutenSync(array_merge([$id], array_map('intval', $kinder->fetchAll(PDO::FETCH_COLUMN))));
    }

    private static function zahl($w): ?float
    {
        if ($w === null || $w === '') return null;
        $w = str_replace(',', '.', (string)$w);
        return is_numeric($w) ? (float)$w : null;
    }
}
