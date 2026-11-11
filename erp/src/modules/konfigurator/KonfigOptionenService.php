<?php

require_once __DIR__ . '/../../core/database.php';

/**
 * Konfigurator-Optionen von einem Layout auf andere übertragen (Jacky 2026-11-11).
 *
 * Jedes Layout hat eigene Achsen-Zuordnungen (artikel_achsen) und Wert-Kopien (varianten_achse_werte).
 * uebertragen() gleicht die Ziel-Layouts an die Vorlage an:
 * - Achsen: Reihenfolge, Preis-Modus/-Wert, Bedingung (Wert-IDs auf das Ziel umgeschlüsselt).
 * - Werte: Abgleich je Achse über den Wert-Text. Gleichnamige werden aktualisiert (Zusatz, Aufpreis,
 *   Reihenfolge, Rohmaterial-Ausnahme) -- so bleiben Shop-Terme erhalten. Fehlende werden angelegt.
 * - Was die Vorlage nicht hat, wird entfernt -- außer Werte, die schon in Aufträgen/Bons stehen
 *   (position_konfiguration, FK RESTRICT): die bleiben und werden gemeldet.
 * Der Grundpreis der Layouts bleibt unangetastet. Ziel-Layouts werden zum Shop-Sync fällig.
 */
class KonfigOptionenService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Alle konfigurierbaren Artikel (Layouts) mit Anzahl Achsen/Werte. */
    public function layouts(): array
    {
        return $this->db->query("
            SELECT a.id, a.artikelnummer, a.name, a.aktiv,
                   (SELECT COUNT(*) FROM artikel_achsen aa WHERE aa.artikel_id = a.id) AS achsen,
                   (SELECT COUNT(*) FROM varianten_achse_werte w WHERE w.artikel_id = a.id) AS werte
            FROM artikel a WHERE a.ist_konfigurierbar = 1 ORDER BY a.artikelnummer")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Überträgt die Optionen von $quelleId auf $zielIds. $ausfuehren = false -> nur zählen (Probelauf,
     * alles in einer Transaktion, die zurückgerollt wird). Rückgabe je Ziel: Zähler + Hinweise.
     */
    public function uebertragen(int $quelleId, array $zielIds, bool $ausfuehren): array
    {
        $zielIds = array_values(array_diff(array_unique(array_map('intval', $zielIds)), [$quelleId]));
        if (!$zielIds) throw new RuntimeException('Bitte mindestens ein Ziel-Layout wählen.');
        $q = $this->db->prepare("SELECT id FROM artikel WHERE id = ? AND ist_konfigurierbar = 1");
        foreach (array_merge([$quelleId], $zielIds) as $id) {
            $q->execute([$id]);
            if (!$q->fetchColumn()) throw new RuntimeException("Artikel $id ist kein konfigurierbares Layout.");
        }

        $qAchsen = $this->achsen($quelleId);
        $qWerte  = $this->werte($quelleId);
        if (!$qAchsen) throw new RuntimeException('Die Vorlage hat keine Achsen.');
        $qWertById = [];
        foreach ($qWerte as $w) $qWertById[(int) $w['id']] = $w;

        $ergebnis = [];
        $this->db->beginTransaction();
        try {
            foreach ($zielIds as $zielId) $ergebnis[$zielId] = $this->uebertrageAufZiel($zielId, $qAchsen, $qWerte, $qWertById);
            $ausfuehren ? $this->db->commit() : $this->db->rollBack();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $ergebnis;
    }

    private function uebertrageAufZiel(int $zielId, array $qAchsen, array $qWerte, array $qWertById): array
    {
        $z = ['achsen_neu' => 0, 'achsen_weg' => 0, 'werte_neu' => 0, 'werte_geaendert' => 0, 'werte_weg' => 0, 'behalten' => []];

        // 1. Werte abgleichen (Pool je Achse+Text, damit auch doppelte Texte sauber zugeordnet werden)
        $pool = [];
        foreach ($this->werte($zielId) as $w) $pool[$w['achse_id'] . '|' . mb_strtolower($w['wert'])][] = $w;
        $map = [];      // Quell-Wert-ID -> Ziel-Wert-ID
        $behalteIds = [];
        $upd = $this->db->prepare("UPDATE varianten_achse_werte SET wert = ?, wert_zusatz = ?, aufpreis = ?, sort_order = ?, rohmaterial_artikel_id = ? WHERE id = ?");
        $ins = $this->db->prepare("INSERT INTO varianten_achse_werte (artikel_id, achse_id, wert, wert_zusatz, aufpreis, sort_order, rohmaterial_artikel_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($qWerte as $qw) {
            $key = $qw['achse_id'] . '|' . mb_strtolower($qw['wert']);
            $treffer = !empty($pool[$key]) ? array_shift($pool[$key]) : null;
            if ($treffer) {
                $geaendert = $treffer['wert'] !== $qw['wert'] || $treffer['wert_zusatz'] !== $qw['wert_zusatz']
                    || (float) $treffer['aufpreis'] !== (float) $qw['aufpreis'] || (int) $treffer['sort_order'] !== (int) $qw['sort_order']
                    || $treffer['rohmaterial_artikel_id'] !== $qw['rohmaterial_artikel_id'];
                if ($geaendert) {
                    $upd->execute([$qw['wert'], $qw['wert_zusatz'], $qw['aufpreis'], $qw['sort_order'], $qw['rohmaterial_artikel_id'], $treffer['id']]);
                    $z['werte_geaendert']++;
                }
                $map[(int) $qw['id']] = (int) $treffer['id'];
            } else {
                $ins->execute([$zielId, $qw['achse_id'], $qw['wert'], $qw['wert_zusatz'], $qw['aufpreis'], $qw['sort_order'], $qw['rohmaterial_artikel_id']]);
                $map[(int) $qw['id']] = (int) $this->db->lastInsertId();
                $z['werte_neu']++;
            }
            $behalteIds[$map[(int) $qw['id']]] = true;
        }

        // 2. Wert-Bedingungen umschlüsseln
        $bed = $this->db->prepare("UPDATE varianten_achse_werte SET bedingungs_wert_id = ? WHERE id = ?");
        foreach ($qWerte as $qw) {
            $neu = $qw['bedingungs_wert_id'] ? ($map[(int) $qw['bedingungs_wert_id']] ?? null) : null;
            $bed->execute([$neu, $map[(int) $qw['id']]]);
        }

        // 3. Achsen: anlegen/aktualisieren inkl. umgeschlüsselter Bedingung
        $zAchsen = [];
        foreach ($this->achsen($zielId) as $a) $zAchsen[(int) $a['achse_id']] = $a;
        $updA = $this->db->prepare("UPDATE artikel_achsen SET bedingungs_achse_id = ?, bedingungs_wert_id = ?, sort_order = ?, preis_modus = ?, preis_wert = ? WHERE id = ?");
        $insA = $this->db->prepare("INSERT INTO artikel_achsen (artikel_id, achse_id, bedingungs_achse_id, bedingungs_wert_id, sort_order, preis_modus, preis_wert) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $qAchsenIds = [];
        foreach ($qAchsen as $qa) {
            $aid = (int) $qa['achse_id'];
            $qAchsenIds[$aid] = true;
            $bedWert = $qa['bedingungs_wert_id'] ? ($map[(int) $qa['bedingungs_wert_id']] ?? null) : null;
            $bedAchse = $bedWert ? $qa['bedingungs_achse_id'] : null;
            if (isset($zAchsen[$aid])) {
                $updA->execute([$bedAchse, $bedWert, $qa['sort_order'], $qa['preis_modus'], $qa['preis_wert'], $zAchsen[$aid]['id']]);
            } else {
                $insA->execute([$zielId, $aid, $bedAchse, $bedWert, $qa['sort_order'], $qa['preis_modus'], $qa['preis_wert']]);
                $z['achsen_neu']++;
            }
        }

        // 4. Überzählige Werte entfernen (außer in Aufträgen/Bons verwendete)
        $benutzt = $this->db->prepare("SELECT COUNT(*) FROM position_konfiguration WHERE wert_id = ?");
        $loeschW = $this->db->prepare("DELETE FROM varianten_achse_werte WHERE id = ?");
        $behalteneAchsen = [];
        foreach ($this->werte($zielId) as $w) {
            if (isset($behalteIds[(int) $w['id']])) continue;
            $benutzt->execute([$w['id']]);
            if ($benutzt->fetchColumn()) {
                $z['behalten'][] = $w['achse_name'] . ': ' . $w['wert'];
                $behalteneAchsen[(int) $w['achse_id']] = true;
                continue;
            }
            // Bedingungen, die noch auf diesen Wert zeigen, lösen (sonst blockiert der Fremdschlüssel)
            $this->db->prepare("UPDATE artikel_achsen SET bedingungs_achse_id = NULL, bedingungs_wert_id = NULL WHERE bedingungs_wert_id = ?")->execute([$w['id']]);
            $loeschW->execute([$w['id']]);
            $z['werte_weg']++;
        }

        // 5. Achsen, die die Vorlage nicht hat, entfernen (verwendete Werte bleiben dann unsichtbar erhalten)
        foreach ($zAchsen as $aid => $za) {
            if (isset($qAchsenIds[$aid])) continue;
            $this->db->prepare("DELETE FROM artikel_achsen WHERE id = ?")->execute([$za['id']]);
            $z['achsen_weg']++;
        }

        $this->db->prepare("UPDATE artikel SET aktualisiert_am = NOW() WHERE id = ?")->execute([$zielId]);
        return $z;
    }

    private function achsen(int $artikelId): array
    {
        $st = $this->db->prepare("SELECT * FROM artikel_achsen WHERE artikel_id = ? ORDER BY sort_order, id");
        $st->execute([$artikelId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function werte(int $artikelId): array
    {
        $st = $this->db->prepare("
            SELECT w.*, ac.name AS achse_name FROM varianten_achse_werte w JOIN varianten_achsen ac ON ac.id = w.achse_id
            WHERE w.artikel_id = ? ORDER BY w.achse_id, w.sort_order, w.id");
        $st->execute([$artikelId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
