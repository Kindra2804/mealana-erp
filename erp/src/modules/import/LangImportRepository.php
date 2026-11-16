<?php
/**
 * Datenbank-Zugriffe für den Lang-Yarns-Import (LangImportService).
 * Nur SQL, keine Regeln -- die stehen im Service.
 */
require_once __DIR__ . '/../../core/Database.php';

class LangImportRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Alle Artikel mit Präfix "LY", Schlüssel = Nummer ohne Leerzeichen (z.B. "LY- 1084" -> "LY-1084").
     * Mit Bestand, damit die Liste „nicht mehr in der Datei“ ihn gleich zeigen kann.
     */
    public function ladeLyArtikel(): array
    {
        $st = $this->db->query("
            SELECT a.id, a.artikelnummer, a.name, a.vaterartikel_id, a.ist_vater, a.aktiv,
                   a.ist_auslaufartikel, a.taric_code, a.uvp,
                   (SELECT COALESCE(SUM(l.bestand), 0) FROM lagerbestand l WHERE l.artikel_id = a.id) AS bestand
            FROM artikel a
            WHERE a.artikelnummer LIKE 'LY%'
        ");
        $map = [];
        foreach ($st as $r) {
            $map[strtoupper(preg_replace('/\s+/', '', $r['artikelnummer']))] = $r;
        }
        return $map;
    }

    /** EAN (GTIN13) => artikel_id, für den Abgleich „falsche Artikelnummer, aber gleiche EAN“. */
    public function ladeEanMap(): array
    {
        $map = [];
        foreach ($this->db->query("SELECT code, artikel_id FROM artikel_codes WHERE typ = 'GTIN13' AND artikel_id IS NOT NULL") as $r) {
            $map[$r['code']] = (int) $r['artikel_id'];
        }
        return $map;
    }

    public function findArtikelKurz(int $id): ?array
    {
        $st = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name, a.vaterartikel_id, a.ist_vater, a.aktiv,
                   a.ist_auslaufartikel, a.taric_code, a.uvp,
                   (SELECT COALESCE(SUM(l.bestand), 0) FROM lagerbestand l WHERE l.artikel_id = a.id) AS bestand
            FROM artikel a WHERE a.id = ?
        ");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function findHerstellerIdByName(string $name): ?int
    {
        $st = $this->db->prepare("SELECT id FROM hersteller WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $st->execute([trim($name)]);
        $id = $st->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function findLieferanten(): array
    {
        return $this->db->query("SELECT id, name FROM lieferanten ORDER BY name")->fetchAll();
    }

    /** Lieferanten-Zeile anlegen oder aktualisieren (Lief-Art.-Nr., EK, VPE-EAN). */
    public function setzeLieferantZeile(int $artikelId, int $lieferantId, string $nr, ?float $ek, ?string $vpeEan): void
    {
        $st = $this->db->prepare("SELECT id FROM artikel_lieferanten WHERE artikel_id = ? AND lieferant_id = ? ORDER BY id LIMIT 1");
        $st->execute([$artikelId, $lieferantId]);
        $id = $st->fetchColumn();
        if ($id) {
            $this->db->prepare("
                UPDATE artikel_lieferanten
                SET artikelnummer_lieferant = :nr,
                    netto_ek = COALESCE(:ek, netto_ek),
                    vpe_ean  = COALESCE(:vpe, vpe_ean),
                    aktiv = 1
                WHERE id = :id
            ")->execute(['nr' => $nr, 'ek' => $ek, 'vpe' => $vpeEan, 'id' => $id]);
            return;
        }
        // Erster Lieferant des Artikels wird Standard-Lieferant
        $st = $this->db->prepare("SELECT COUNT(*) FROM artikel_lieferanten WHERE artikel_id = ? AND standard_lieferant = 1");
        $st->execute([$artikelId]);
        $standard = (int) $st->fetchColumn() === 0 ? 1 : 0;
        $this->db->prepare("
            INSERT INTO artikel_lieferanten (artikel_id, lieferant_id, artikelnummer_lieferant, netto_ek, waehrung, vpe_ean, standard_lieferant, aktiv)
            VALUES (?, ?, ?, ?, 'EUR', ?, ?, 1)
        ")->execute([$artikelId, $lieferantId, $nr, $ek, $vpeEan, $standard]);
    }

    public function hatGtin(int $artikelId): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM artikel_codes WHERE artikel_id = ? AND typ = 'GTIN13' LIMIT 1");
        $st->execute([$artikelId]);
        return (bool) $st->fetchColumn();
    }

    public function eanVergeben(string $ean): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM artikel_codes WHERE code = ? LIMIT 1");
        $st->execute([$ean]);
        return (bool) $st->fetchColumn();
    }

    public function fuegeGtinHinzu(int $artikelId, string $ean): void
    {
        $this->db->prepare("INSERT INTO artikel_codes (artikel_id, typ, code) VALUES (?, 'GTIN13', ?)")->execute([$artikelId, $ean]);
    }

    /** UVP, Zolltarifnummer (nur wenn leer) und ggf. bereinigte Artikelnummer setzen. Gibt true zurück, wenn sich etwas geändert hat. */
    public function aktualisiereStamm(int $artikelId, ?float $uvp, ?string $taric, ?string $neueNummer): bool
    {
        $st = $this->db->prepare("
            UPDATE artikel
            SET uvp = COALESCE(:uvp, uvp),
                taric_code = CASE WHEN (taric_code IS NULL OR taric_code = '') THEN :taric ELSE taric_code END,
                artikelnummer = COALESCE(:nr, artikelnummer)
            WHERE id = :id
              AND (NOT (uvp <=> COALESCE(:uvp2, uvp))
                   OR ((taric_code IS NULL OR taric_code = '') AND :taric2 IS NOT NULL)
                   OR (:nr2 IS NOT NULL AND artikelnummer <> :nr3))
        ");
        $st->execute([
            'uvp' => $uvp, 'uvp2' => $uvp, 'taric' => $taric, 'taric2' => $taric,
            'nr' => $neueNummer, 'nr2' => $neueNummer, 'nr3' => $neueNummer, 'id' => $artikelId,
        ]);
        return $st->rowCount() > 0;
    }

    /** Nummer schon bei einem ANDEREN Artikel? (Vergleich ohne Groß/klein, wie die DB) */
    public function nummerVergeben(string $nr, int $ausserId = 0): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM artikel WHERE artikelnummer = ? AND id <> ? LIMIT 1");
        $st->execute([$nr, $ausserId]);
        return (bool) $st->fetchColumn();
    }

    /** Neue EAN = Artikel muss neu in den Shop (GTIN wird übertragen). */
    public function fuerShopMarkieren(int $artikelId): void
    {
        $this->db->prepare("UPDATE artikel SET aktualisiert_am = NOW() WHERE id = ?")->execute([$artikelId]);
    }

    public function setzeAuslauf(int $artikelId): void
    {
        $this->db->prepare("UPDATE artikel SET ist_auslaufartikel = 1 WHERE id = ?")->execute([$artikelId]);
    }

    /** Hat der Vater noch Kinder, die kein Auslaufartikel sind? */
    public function zaehleKinderOhneAuslauf(int $vaterId): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM artikel WHERE vaterartikel_id = ? AND ist_auslaufartikel = 0");
        $st->execute([$vaterId]);
        return (int) $st->fetchColumn();
    }

    public function zaehleKinder(int $vaterId): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM artikel WHERE vaterartikel_id = ?");
        $st->execute([$vaterId]);
        return (int) $st->fetchColumn();
    }

    /** Achsen eines Artikels (achse_id-Liste) */
    public function findAchsenIds(int $artikelId): array
    {
        $st = $this->db->prepare("SELECT achse_id FROM artikel_achsen WHERE artikel_id = ?");
        $st->execute([$artikelId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findAchseIdByName(string $name): ?int
    {
        $st = $this->db->prepare("SELECT id FROM varianten_achsen WHERE name = ? LIMIT 1");
        $st->execute([$name]);
        $id = $st->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** Werte eines Artikels auf einer Achse: normalisierter Text => wert_id */
    public function findWerte(int $artikelId, int $achseId): array
    {
        $st = $this->db->prepare("SELECT id, wert FROM varianten_achse_werte WHERE artikel_id = ? AND achse_id = ?");
        $st->execute([$artikelId, $achseId]);
        $map = [];
        foreach ($st as $r) $map[mb_strtolower(trim($r['wert']))] = (int) $r['id'];
        return $map;
    }

    public function maxWertSort(int $artikelId, int $achseId): int
    {
        $st = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM varianten_achse_werte WHERE artikel_id = ? AND achse_id = ?");
        $st->execute([$artikelId, $achseId]);
        return (int) $st->fetchColumn();
    }

    public function standardKundengruppeId(): int
    {
        $id = $this->db->query("SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1")->fetchColumn();
        return $id ? (int) $id : 1;
    }

    /** Basispreis (ohne Zeitraum) der Standard-Kundengruppe setzen -- per SELECT, nicht rowCount (MariaDB). */
    public function setzeBasisPreis(int $artikelId, float $brutto, float $netto): void
    {
        $kg = $this->standardKundengruppeId();
        $st = $this->db->prepare("SELECT id FROM artikel_preise WHERE artikel_id = ? AND kundengruppen_id = ? AND gueltig_ab IS NULL AND gueltig_bis IS NULL LIMIT 1");
        $st->execute([$artikelId, $kg]);
        if ($id = $st->fetchColumn()) {
            $this->db->prepare("UPDATE artikel_preise SET brutto_vk = ?, netto_vk = ? WHERE id = ?")->execute([$brutto, $netto, $id]);
        } else {
            $this->db->prepare("INSERT INTO artikel_preise (artikel_id, kundengruppen_id, brutto_vk, netto_vk) VALUES (?, ?, ?, ?)")
                     ->execute([$artikelId, $kg, $brutto, $netto]);
        }
    }

    // ── Abgewählte Väter (Migration 254) ────────────────────────────

    /** nummer => true */
    public function ladeAusschluss(string $quelle): array
    {
        $st = $this->db->prepare("SELECT nummer FROM import_ausschluss WHERE quelle = ?");
        $st->execute([$quelle]);
        return array_fill_keys($st->fetchAll(PDO::FETCH_COLUMN), true);
    }

    public function setzeAusschluss(string $quelle, string $nummer, string $name, ?int $benutzerId, bool $ausschliessen): void
    {
        if ($ausschliessen) {
            $this->db->prepare("INSERT IGNORE INTO import_ausschluss (quelle, nummer, name, benutzer_id) VALUES (?, ?, ?, ?)")
                     ->execute([$quelle, $nummer, $name, $benutzerId]);
        } else {
            $this->db->prepare("DELETE FROM import_ausschluss WHERE quelle = ? AND nummer = ?")->execute([$quelle, $nummer]);
        }
    }

    // ── Preisvergleich ───────────────────────────────────────────────

    /** artikel_id => bisheriger EK beim Lieferanten */
    public function ladeEkMap(int $lieferantId): array
    {
        $st = $this->db->prepare("SELECT artikel_id, MAX(netto_ek) ek FROM artikel_lieferanten WHERE lieferant_id = ? GROUP BY artikel_id");
        $st->execute([$lieferantId]);
        return array_map('floatval', array_column($st->fetchAll(), 'ek', 'artikel_id'));
    }

    /** artikel_id => Verkaufspreis brutto (Standard-Kundengruppe, ohne Zeitraum) */
    public function ladeVkMap(): array
    {
        $st = $this->db->prepare("SELECT artikel_id, MAX(brutto_vk) vk FROM artikel_preise
                                  WHERE kundengruppen_id = ? AND gueltig_ab IS NULL AND gueltig_bis IS NULL GROUP BY artikel_id");
        $st->execute([$this->standardKundengruppeId()]);
        return array_map('floatval', array_column($st->fetchAll(), 'vk', 'artikel_id'));
    }

    /** Strecke (Dropshipping) je Artikel: 1 = möglich, 0 = ausgenommen. Nur vorhandene Lieferanten-Zeile. */
    public function setzeDropshipping(int $artikelId, int $lieferantId, int $wert): void
    {
        $this->db->prepare("UPDATE artikel_lieferanten SET dropshipping = ? WHERE artikel_id = ? AND lieferant_id = ?")
                 ->execute([$wert, $artikelId, $lieferantId]);
    }

    /** Gilt beim Lieferanten „Strecke für alle Artikel“? */
    public function lieferantStreckeAllgemein(int $lieferantId): bool
    {
        $st = $this->db->prepare("SELECT dropshipping_aktiv FROM lieferanten WHERE id = ?");
        $st->execute([$lieferantId]);
        return (int) $st->fetchColumn() === 1;
    }

    /** Lieferant: Strecke nur noch für einzeln markierte Artikel (Jacky 2026-11-16). Pauschale bleibt. */
    public function lieferantStreckeNurMarkierte(int $lieferantId): void
    {
        $this->db->prepare("UPDATE lieferanten SET dropshipping_aktiv = 0 WHERE id = ?")->execute([$lieferantId]);
    }

    /** Von den angegebenen Artikeln die, die beim Lieferanten ausdrücklich „Strecke ja“ haben */
    public function mitStreckeJa(int $lieferantId, array $artikelIds): array
    {
        if (!$artikelIds) return [];
        $ph = implode(',', array_fill(0, count($artikelIds), '?'));
        $st = $this->db->prepare("SELECT DISTINCT artikel_id FROM artikel_lieferanten WHERE lieferant_id = ? AND dropshipping = 1 AND artikel_id IN ($ph)");
        $st->execute(array_merge([$lieferantId], array_values($artikelIds)));
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** „Strecke ja“ zurück auf „wie Lieferant“ (= aus, wenn der Lieferant nur markierte Artikel streckt) */
    public function streckeZuruecksetzen(int $lieferantId, array $artikelIds): void
    {
        if (!$artikelIds) return;
        $ph = implode(',', array_fill(0, count($artikelIds), '?'));
        $this->db->prepare("UPDATE artikel_lieferanten SET dropshipping = NULL WHERE lieferant_id = ? AND dropshipping = 1 AND artikel_id IN ($ph)")
                 ->execute(array_merge([$lieferantId], array_values($artikelIds)));
    }

    public function findLagerbestandZeilenUngleichNull(int $artikelId): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM lagerbestand WHERE artikel_id = ? AND bestand <> 0");
        $st->execute([$artikelId]);
        return (int) $st->fetchColumn();
    }
}
