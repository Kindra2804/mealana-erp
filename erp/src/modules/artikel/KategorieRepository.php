<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * KategorieRepository – Datenzugriff für den Kategorie-Baum und Artikel-Zuweisungen
 *
 * Kategorien bilden einen beliebig tiefen Baum (parent_id selbst-referenziell).
 * Aktions-Kategorien (ist_aktions_kategorie = 1) steuern welche Artikel einer Aktion unterliegen.
 *
 * Wichtige Methoden:
 *   findAllMitEltern()                → Flat-Liste mit Aktions-Indikatoren (⏰-Symbol in UI)
 *   updateArtikelKategoriezuweisungen → Transaktionaler Replace + Aktionspreise-Bereinigung
 *   syncKategorienZuKindern()         → Synchronisiert Kategorien eines Vaters zu allen Kindern
 *   findAlleKinderIds()               → Rekursive CTE-Query für alle Nachkommen
 *   deleteKategorie()                 → Löscht Baum-Ast mit optionalem Artikel-Verschieben
 */
class KategorieRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                name
            FROM kategorien
            WHERE aktiv = 1
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Alle aktiven Kategorien als Flat-Liste für den Baum-Aufbau in ArtikelService::getKategorienBaum().
     * Berechnet pro Kategorie vier Werte über unabhängige korrelierte Subqueries (statt eines
     * gemeinsamen JOINs mit GROUP BY):
     *   artikel_anzahl → Anzahl zugewiesener, nicht-Vater-Artikel
     *   aktion_aktiv   → 1 wenn heute aktive Aktion (gestartet + Datum zwischen ab/bis)
     *   aktion_zukunft → 1 wenn zukünftige Aktion existiert
     *   aktion_info    → GROUP_CONCAT der Aktionsnamen+Zeiträume für Hover-Tooltip
     *
     * 🔴 Performance-Fix (2026-08-01, war 9,5s bei 2661 Artikeln): Die alte Version jointe
     * kategorien→artikel_kategorien→artikel(Vater)→artikel(Kind) in einem einzigen Statement.
     * Die JOIN-Bedingung auf die zweite artikel-Kopie enthielt ein OR über zwei verschiedene
     * Spalten ((a.id=vater.id AND ist_vater=0) OR (a.vaterartikel_id=vater.id)) — das verhindert
     * jede Index-Nutzung ("Range checked for each record" in EXPLAIN), MySQL scannte dadurch pro
     * Kategorie-Zuweisung (~224x) die komplette Artikeltabelle neu (~2327 Zeilen) = ~521.000 Zeilen
     * Prüfaufwand. Zusätzlich wurde durch den zweiten JOIN auf aktionen_kategorien ein Cross-Join-
     * artiger Fan-Out zwischen beiden unabhängigen Dimensionen erzeugt.
     * Fix: artikel_anzahl direkt über die eigene artikel_kategorien-Zeile des Artikels zählen
     * (a.ist_vater=0, kein Vater-Umweg nötig) — verifiziert dass JEDES Kind durch
     * syncKategorienZuKindern() ohnehin immer eine eigene Zeile bekommt, der Vater-JOIN war
     * für die Zählung redundant. Alle vier Werte + der Shop-Codes-Wert laufen jetzt als
     * unabhängige korrelierte Subqueries statt eines gemeinsamen JOINs — jede nutzt den
     * bestehenden Index auf artikel_kategorien.kategorie_id / aktionen_kategorien.kategorie_id.
     * Ergebnis byte-identisch zur alten Query verifiziert (Diff über alle 194 Kategorien),
     * Laufzeit 9,66s → 0,015s (~640x).
     */
    public function findAllMitEltern(): array
    {
        $stmt = $this->db->query("
            SELECT k.id, k.parent_id, k.name, k.beschreibung, k.bild_pfad, k.sortierung,
                k.ist_aktions_kategorie,
                (
                    SELECT COUNT(DISTINCT a.id)
                    FROM artikel_kategorien ak
                    JOIN artikel a ON a.id = ak.artikel_id AND a.aktiv = 1 AND a.ist_vater = 0
                    WHERE ak.kategorie_id = k.id
                ) AS artikel_anzahl,
                COALESCE((
                    SELECT MAX(CASE WHEN akt2.gestartet = 1 AND CURDATE() BETWEEN ak2.gueltig_ab AND ak2.gueltig_bis THEN 1 ELSE 0 END)
                    FROM aktionen_kategorien ak2
                    JOIN aktionen akt2 ON akt2.id = ak2.aktion_id
                    WHERE ak2.kategorie_id = k.id
                ), 0) AS aktion_aktiv,
                COALESCE((
                    SELECT MAX(CASE WHEN ak2.gueltig_ab > CURDATE() THEN 1 ELSE 0 END)
                    FROM aktionen_kategorien ak2
                    WHERE ak2.kategorie_id = k.id
                ), 0) AS aktion_zukunft,
                (
                    SELECT GROUP_CONCAT(
                        DISTINCT CONCAT(akt2.name, ': ', DATE_FORMAT(ak2.gueltig_ab, '%d.%m.%Y'), ' – ', DATE_FORMAT(ak2.gueltig_bis, '%d.%m.%Y'))
                        ORDER BY ak2.gueltig_ab ASC
                        SEPARATOR ' | '
                    )
                    FROM aktionen_kategorien ak2
                    JOIN aktionen akt2 ON akt2.id = ak2.aktion_id
                    WHERE ak2.kategorie_id = k.id
                ) AS aktion_info,
                (SELECT GROUP_CONCAT(DISTINCT CONCAT('S', s3.id) ORDER BY s3.id SEPARATOR ',')
                 FROM artikel_kategorien ak3
                 JOIN artikel a3 ON a3.id = ak3.artikel_id AND a3.aktiv = 1
                 JOIN artikel_shops ash3 ON ash3.artikel_id = a3.id AND ash3.aktiv = 1
                 JOIN shops s3 ON s3.id = ash3.shop_id
                 WHERE ak3.kategorie_id = k.id) AS eigene_shop_codes
            FROM kategorien k
            WHERE k.aktiv = 1
            ORDER BY k.sortierung ASC, k.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Alle explizit für einen Shop gesperrten Kategorien, als kategorie_id => ['S1','S2',...].
     * Für die Kanal-Chip-Berechnung (ArtikelService::berechneShopChips()) -- eine Sperre auf
     * einer Kategorie muss ihren Chip in JEDER Unterkategorie desselben Zweigs unterdrücken,
     * nicht nur an der gesperrten Kategorie selbst (Artikel hängen ja nur an Blatt-Kategorien).
     * Eine einzige Query statt einer Sub-Query pro Baumknoten (siehe Performance-Lehre bei
     * findAllMitEltern() oben -- 640x-Regression durch N+1 wurde hier bewusst vermieden).
     */
    public function findAusschluesseAlsShopCodes(): array
    {
        $stmt = $this->db->query("
            SELECT kategorie_id, CONCAT('S', shop_id) AS code
            FROM kategorie_shops
            WHERE ausgeschlossen = 1
        ");
        $ergebnis = [];
        foreach ($stmt->fetchAll() as $row) {
            $ergebnis[(int)$row['kategorie_id']][] = $row['code'];
        }
        return $ergebnis;
    }

    public function countByKategorie(int $kategorieId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT a.id)
            FROM artikel a
            INNER JOIN artikel_kategorien ak ON ak.artikel_id = a.id
            WHERE ak.kategorie_id = :kid
              AND a.aktiv = 1
              AND a.vaterartikel_id IS NULL
              AND a.zustand_vater_id IS NULL
        ");
        $stmt->execute(['kid' => $kategorieId]);
        return (int) $stmt->fetchColumn();
    }

    public function findByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                k.id, 
                k.name 
            FROM kategorien k
            INNER JOIN artikel_kategorien ak ON k.id = ak.kategorie_id
            WHERE ak.artikel_id = :artikel_id
            AND k.aktiv = 1
            ORDER BY k.name ASC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    /**
     * Ersetzt alle Kategorie-Zuweisungen eines Artikels in einer Transaktion.
     *
     * Ablauf:
     * 1. Alte Zuweisungen lesen → entfernte vs. neue Kategorien berechnen
     * 2. Alle alten löschen, alle neuen einfügen
     * 3. Aktionspreise für entfernte Kategorien bereinigen
     *    (Artikel nicht mehr in Kategorie → Aktionspreis obsolet)
     * 4. Aktionshinweise für neu zugewiesene Kategorien ermitteln
     *    (damit die View ein Modal für Aktionspreise anzeigen kann)
     *
     * @return array Aktionshinweise-Zeilen [aktion_id, aktion_name, ist_aktiv, naechster_start]
     */
    public function updateArtikelKategoriezuweisungen(int $artikelId, array $kategorieIds): array
    {
        try {
            $this->db->beginTransaction();

            // Bestehende Zuweisungen ermitteln um entfernte/neue Kategorien zu kennen
            $stmt = $this->db->prepare("SELECT kategorie_id FROM artikel_kategorien WHERE artikel_id = :artikel_id");
            $stmt->execute(['artikel_id' => $artikelId]);
            $alteKatIds      = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $entfernteKatIds = array_values(array_diff($alteKatIds, $kategorieIds));
            $neueKatIds      = array_values(array_diff($kategorieIds, $alteKatIds));

            $stmt = $this->db->prepare("DELETE FROM artikel_kategorien WHERE artikel_id = :artikel_id");
            $stmt->execute(['artikel_id' => $artikelId]);

            $stmt = $this->db->prepare("INSERT INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (:artikel_id, :kategorie_id)");
            foreach ($kategorieIds as $kategorieId) {
                $stmt->execute(['artikel_id' => $artikelId, 'kategorie_id' => $kategorieId]);
            }

            // Ohne diesen Bump bleibt eine neu zugewiesene Kategorie für den Shop-Sync
            // unsichtbar, wenn der Artikel selbst schon synct war (findFaelligeArtikel()
            // findet ihn dann nie wieder, findFaelligeKategorien() kennt nur bereits
            // angelegte Kategorien -- siehe project_shop_sync.md, Fund 2026-08-05).
            if (!empty($entfernteKatIds) || !empty($neueKatIds)) {
                $this->bumpAktualisiertAm([$artikelId]);
            }

            // Aktionspreise für entfernte Kategorien bereinigen
            if (!empty($entfernteKatIds)) {
                $pl       = implode(',', array_fill(0, count($entfernteKatIds), '?'));
                $aktStmt  = $this->db->prepare("SELECT DISTINCT aktion_id FROM aktionen_kategorien WHERE kategorie_id IN ($pl)");
                $aktStmt->execute($entfernteKatIds);
                $aktionIds = $aktStmt->fetchAll(\PDO::FETCH_COLUMN);

                if (!empty($aktionIds)) {
                    $pl2 = implode(',', array_fill(0, count($aktionIds), '?'));
                    $this->db->prepare("
                        DELETE FROM aktionen_artikel_preise
                        WHERE artikel_id = ? AND aktion_id IN ($pl2)
                    ")->execute(array_merge([$artikelId], $aktionIds));
                }
            }

            // Aktive + geplante (nicht abgelaufene) Aktionen für neu zugewiesene Kategorien
            $aktionsHinweise = [];
            if (!empty($neueKatIds)) {
                $pl = implode(',', array_fill(0, count($neueKatIds), '?'));
                $hinweisStmt = $this->db->prepare("
                    SELECT
                        a.id   AS aktion_id,
                        a.name AS aktion_name,
                        MAX(CASE WHEN CURDATE() BETWEEN ak.gueltig_ab AND ak.gueltig_bis THEN 1 ELSE 0 END) AS ist_aktiv,
                        MIN(ak.gueltig_ab) AS naechster_start
                    FROM aktionen a
                    JOIN aktionen_kategorien ak ON ak.aktion_id = a.id
                    WHERE ak.kategorie_id IN ($pl)
                      AND ak.gueltig_bis >= CURDATE()
                    GROUP BY a.id, a.name
                ");
                $hinweisStmt->execute($neueKatIds);
                $aktionsHinweise = $hinweisStmt->fetchAll();
            }

            $this->db->commit();
            return $aktionsHinweise;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Synchronisiert die Kategorien des Vaters zu allen seinen Kind-Artikeln.
     * Löscht alle Kind-Kategorien und setzt sie neu — Kinder haben immer dieselben Kategorien wie der Vater.
     * Wird nach saveKategorien() am Vater aufgerufen.
     */
    public function syncKategorienZuKindern(int $vaterId, array $kategorieIds): void
    {
        $stmt = $this->db->prepare("SELECT id FROM artikel WHERE vaterartikel_id = :vater_id");
        $stmt->execute(['vater_id' => $vaterId]);
        $kindIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($kindIds)) return;

        $pl = implode(',', array_fill(0, count($kindIds), '?'));
        $this->db->prepare("DELETE FROM artikel_kategorien WHERE artikel_id IN ($pl)")->execute($kindIds);

        // Siehe bumpAktualisiertAm() -- ohne Bump bleiben Kinder für den Shop-Sync
        // unsichtbar, wenn sie schon synct waren.
        $this->bumpAktualisiertAm($kindIds);

        if (empty($kategorieIds)) return;

        $stmt = $this->db->prepare("INSERT INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (?, ?)");
        foreach ($kindIds as $kindId) {
            foreach ($kategorieIds as $katId) {
                $stmt->execute([(int)$kindId, (int)$katId]);
            }
        }
    }

    public function bulkAddKategorie(array $artikelIds, int $kategorieId): void
    {
        $stmt = $this->db->prepare("INSERT IGNORE INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (?, ?)");
        foreach ($artikelIds as $aid) {
            $stmt->execute([(int)$aid, $kategorieId]);
        }
        // Kinder von Vater-Artikeln mitziehen
        $kindStmt = $this->db->prepare("SELECT id FROM artikel WHERE vaterartikel_id = ?");
        $insStmt  = $this->db->prepare("INSERT IGNORE INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (?, ?)");
        $betroffeneIds = $artikelIds;
        foreach ($artikelIds as $aid) {
            $kindStmt->execute([(int)$aid]);
            foreach ($kindStmt->fetchAll(\PDO::FETCH_COLUMN) as $kindId) {
                $insStmt->execute([(int)$kindId, $kategorieId]);
                $betroffeneIds[] = $kindId;
            }
        }
        $this->bumpAktualisiertAm($betroffeneIds);
    }

    public function bulkRemoveKategorie(array $artikelIds, int $kategorieId): void
    {
        $stmt = $this->db->prepare("DELETE FROM artikel_kategorien WHERE artikel_id = ? AND kategorie_id = ?");
        foreach ($artikelIds as $aid) {
            $stmt->execute([(int)$aid, $kategorieId]);
        }
        // Kinder von Vater-Artikeln mitziehen
        $kindStmt = $this->db->prepare("SELECT id FROM artikel WHERE vaterartikel_id = ?");
        $betroffeneIds = $artikelIds;
        foreach ($artikelIds as $aid) {
            $kindStmt->execute([(int)$aid]);
            foreach ($kindStmt->fetchAll(\PDO::FETCH_COLUMN) as $kindId) {
                $stmt->execute([(int)$kindId, $kategorieId]);
                $betroffeneIds[] = $kindId;
            }
        }
        $this->bumpAktualisiertAm($betroffeneIds);
    }

    /**
     * Markiert Artikel als fällig für den Shop-Sync -- ohne diesen Bump bleibt eine
     * Kategorie-Zuweisung an einen bereits synchten Artikel für findFaelligeArtikel()
     * unsichtbar (siehe project_shop_sync.md, Fund 2026-08-05).
     */
    private function bumpAktualisiertAm(array $artikelIds): void
    {
        if (empty($artikelIds)) return;
        $pl = implode(',', array_fill(0, count($artikelIds), '?'));
        $this->db->prepare("UPDATE artikel SET aktualisiert_am = NOW() WHERE id IN ($pl)")->execute($artikelIds);
    }

    public function insert(string $name, ?int $parentId = null, bool $istAktionsKategorie = false, ?string $beschreibung = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO kategorien (name, beschreibung, parent_id, ist_aktions_kategorie) VALUES (:name, :beschreibung, :parent_id, :iak)");
        $stmt->execute(['name' => $name, 'beschreibung' => $beschreibung, 'parent_id' => $parentId, 'iak' => $istAktionsKategorie ? 1 : 0]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT id, parent_id, name, beschreibung, bild_pfad, sortierung, ist_aktions_kategorie FROM kategorien WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findBildPfad(int $id): ?string
    {
        $stmt = $this->db->prepare("SELECT bild_pfad FROM kategorien WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $wert = $stmt->fetchColumn();
        return $wert !== false ? $wert : null;
    }

    public function updateBild(int $id, ?string $dateiname): void
    {
        $this->db->prepare("UPDATE kategorien SET bild_pfad = :bild WHERE id = :id")
            ->execute(['bild' => $dateiname, 'id' => $id]);
    }

    public function getSiblingsWithSort(?int $parentId): array
    {
        if ($parentId === null) {
            $stmt = $this->db->query("
                SELECT id, sortierung FROM kategorien
                WHERE parent_id IS NULL AND aktiv = 1
                ORDER BY COALESCE(sortierung, 0), name
            ");
        } else {
            $stmt = $this->db->prepare("
                SELECT id, sortierung FROM kategorien
                WHERE parent_id = :pid AND aktiv = 1
                ORDER BY COALESCE(sortierung, 0), name
            ");
            $stmt->execute(['pid' => $parentId]);
        }
        return $stmt->fetchAll();
    }

    public function updateSortierung(int $id, int $sort): void
    {
        $stmt = $this->db->prepare("UPDATE kategorien SET sortierung = :sort WHERE id = :id");
        $stmt->execute(['sort' => $sort, 'id' => $id]);
    }

    /**
     * Setzt eine Kategorie an eine bestimmte Stelle innerhalb ihrer Geschwister
     * (gleiche Oberkategorie), statt sie nur um eine Position zu verschieben.
     * $nachId = null → ganz an den Anfang. $nachId nicht gefunden (z.B. nach
     * einem Wechsel der Oberkategorie) → ebenfalls Anfang, als sicherer Fallback.
     * Nummeriert danach alle Geschwister neu durch (10, 20, 30, ...) — gleiches
     * Muster wie beim Verschieben per ▲▼-Pfeil in kategorie_sort_ajax.php.
     */
    public function positioniereNach(int $id, ?int $nachId, ?int $parentId): void
    {
        $geschwister = $this->getSiblingsWithSort($parentId);

        // Eigene Zeile zuerst raus (falls schon vorhanden, z.B. beim Bearbeiten)
        $geschwister = array_values(array_filter(
            $geschwister,
            fn(array $g): bool => (int)$g['id'] !== $id
        ));

        $index = null;
        if ($nachId !== null) {
            foreach ($geschwister as $i => $g) {
                if ((int)$g['id'] === $nachId) {
                    $index = $i + 1;
                    break;
                }
            }
        }
        $index ??= 0;

        array_splice($geschwister, $index, 0, [['id' => $id]]);

        foreach ($geschwister as $i => $g) {
            $this->updateSortierung((int)$g['id'], ($i + 1) * 10);
        }
    }

    public function update(int $id, string $name, ?int $parentId, bool $istAktionsKategorie = false, ?string $beschreibung = null): bool
    {
        $stmt = $this->db->prepare("UPDATE kategorien SET name = :name, beschreibung = :beschreibung, parent_id = :parent_id, ist_aktions_kategorie = :iak WHERE id = :id");
        return $stmt->execute(['name' => $name, 'beschreibung' => $beschreibung, 'parent_id' => $parentId, 'iak' => $istAktionsKategorie ? 1 : 0, 'id' => $id]);
    }

    /**
     * Gibt alle Nachkommen-IDs einer Kategorie rekursiv zurück (WITH RECURSIVE CTE).
     * Benötigt für: Lösch-Vorschau, Baum-Verschieben-Validierung (kein Zirkel), Massen-Löschen.
     */
    public function findAlleKinderIds(int $id): array
    {
        // Alle Nachkommen per rekursiver CTE (MariaDB 10.2+)
        $stmt = $this->db->prepare("
            WITH RECURSIVE nachkommen AS (
                SELECT id FROM kategorien WHERE parent_id = :id
                UNION ALL
                SELECT k.id FROM kategorien k
                INNER JOIN nachkommen n ON k.parent_id = n.id
            )
            SELECT id FROM nachkommen
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findArtikelNurInDiesenKategorien(array $kategorieIds): array
    {
        if (empty($kategorieIds)) return [];
        $pl = implode(',', array_fill(0, count($kategorieIds), '?'));
        $stmt = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name
            FROM artikel a
            WHERE a.vaterartikel_id IS NULL
              AND EXISTS (
                  SELECT 1 FROM artikel_kategorien ak WHERE ak.artikel_id = a.id AND ak.kategorie_id IN ($pl)
              )
              AND NOT EXISTS (
                  SELECT 1 FROM artikel_kategorien ak2 WHERE ak2.artikel_id = a.id AND ak2.kategorie_id NOT IN ($pl)
              )
            ORDER BY a.artikelnummer
        ");
        $stmt->execute(array_merge($kategorieIds, $kategorieIds));
        return $stmt->fetchAll();
    }

    /**
     * Löscht eine Kategorie mit allen Nachkommen in einer Transaktion.
     * Wenn verschiebeZuParentId gesetzt: Artikel der gelöschten Kategorien werden der
     * Eltern-Kategorie zugewiesen (INSERT IGNORE verhindert Duplikat-Fehler).
     */
    public function deleteKategorie(int $id, ?int $verschiebeZuParentId = null): void
    {
        $this->db->beginTransaction();
        try {
            $alleIds      = array_merge([$id], $this->findAlleKinderIds($id));
            $placeholders = implode(',', array_fill(0, count($alleIds), '?'));

            if ($verschiebeZuParentId !== null) {
                $this->db->prepare("
                    INSERT IGNORE INTO artikel_kategorien (artikel_id, kategorie_id)
                    SELECT artikel_id, ? FROM artikel_kategorien WHERE kategorie_id IN ($placeholders)
                ")->execute(array_merge([$verschiebeZuParentId], $alleIds));
            }

            $this->db->prepare("DELETE FROM artikel_kategorien WHERE kategorie_id IN ($placeholders)")
                ->execute($alleIds);

            $this->db->prepare("DELETE FROM kategorien WHERE id IN ($placeholders)")
                ->execute($alleIds);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
