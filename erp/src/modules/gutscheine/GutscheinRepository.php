<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * GutscheinRepository – CRUD für Gutscheine, Vorlagen und Transaktionen.
 *
 * ERP ist die einzige Quelle der Wahrheit für Restguthaben/Status -- WooCommerce
 * bekommt nur einen gespiegelten fixed_cart-Coupon (siehe GutscheinService),
 * niemals direkt gegen diese Tabellen geprüft. Siehe project_gutscheine.md.
 */
class GutscheinRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(string $status = '', string $suche = ''): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($status !== '') {
            $where[]           = 'g.status = :status';
            $params['status']  = $status;
        }
        if ($suche !== '') {
            $where[]          = '(g.code LIKE :suche OR g.empfaenger_name LIKE :suche)';
            $params['suche']  = '%' . $suche . '%';
        }

        $stmt = $this->db->prepare("
            SELECT g.*, k.kundennummer,
                   v.name AS vorlage_name
            FROM gutscheine g
            LEFT JOIN kunden k ON k.id = g.kunden_id
            LEFT JOIN gutschein_vorlagen v ON v.id = g.vorlage_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY g.erstellt_am DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT g.*, k.kundennummer,
                   v.name AS vorlage_name, v.hintergrundbild_pfad
            FROM gutscheine g
            LEFT JOIN kunden k ON k.id = g.kunden_id
            LEFT JOIN gutschein_vorlagen v ON v.id = g.vorlage_id
            WHERE g.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    public function findByCode(string $code): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM gutscheine WHERE code = :code");
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    /**
     * Vollständige Kette bei Teileinlösung: läuft von einem beliebigen Punkt in
     * der Kette rückwärts bis zum Ursprung UND vorwärts bis zum aktuell
     * gültigen Code. Für den Support-Fall "Code funktioniert nicht" (weil
     * längst durch einen neuen ersetzt) -- Jacky-Anfrage 2026-08-29.
     *
     * @return array Kette in chronologischer Reihenfolge (ältester zuerst).
     */
    public function findKette(int $gutscheinId): array
    {
        // Rückwärts zum Ursprung
        $kette = [];
        $aktuelleId = $gutscheinId;
        $schutzZaehler = 0;
        while ($aktuelleId !== null && $schutzZaehler++ < 50) {
            $g = $this->findById($aktuelleId);
            if (!$g) break;
            array_unshift($kette, $g);
            $aktuelleId = $g['vorgaenger_gutschein_id'] !== null ? (int)$g['vorgaenger_gutschein_id'] : null;
        }

        // Vorwärts zu allen Nachfolgern (normalerweise höchstens einer pro Schritt,
        // eine Teileinlösung erzeugt immer nur EINEN neuen Code für den Rest)
        $letzteId = (int)end($kette)['id'];
        $schutzZaehler = 0;
        while ($schutzZaehler++ < 50) {
            $stmt = $this->db->prepare("SELECT id FROM gutscheine WHERE vorgaenger_gutschein_id = :id LIMIT 1");
            $stmt->execute(['id' => $letzteId]);
            $naechsteId = $stmt->fetchColumn();
            if (!$naechsteId) break;
            $g = $this->findById((int)$naechsteId);
            if (!$g) break;
            $kette[] = $g;
            $letzteId = (int)$naechsteId;
        }

        return $kette;
    }

    public function codeExistiert(string $code): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM gutscheine WHERE code = :code");
        $stmt->execute(['code' => $code]);
        return (bool)$stmt->fetchColumn();
    }

    public function insert(array $daten): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO gutscheine (
                code, vorlage_id, betrag, restguthaben, gueltig_bis, status,
                kunden_id, empfaenger_name, empfaenger_email, zustellung_am,
                versandart, grusstext, shop_id, kanal_erstellt,
                auftrag_id_ursprung, vorgaenger_gutschein_id, ausgestellt_von
            ) VALUES (
                :code, :vorlage_id, :betrag, :restguthaben, :gueltig_bis, :status,
                :kunden_id, :empfaenger_name, :empfaenger_email, :zustellung_am,
                :versandart, :grusstext, :shop_id, :kanal_erstellt,
                :auftrag_id_ursprung, :vorgaenger_gutschein_id, :ausgestellt_von
            )
        ");
        $stmt->execute($daten);
        return (int)$this->db->lastInsertId();
    }

    public function updateRestguthabenUndStatus(int $id, float $restguthaben, string $status): void
    {
        $stmt = $this->db->prepare("
            UPDATE gutscheine SET restguthaben = :restguthaben, status = :status WHERE id = :id
        ");
        $stmt->execute(['restguthaben' => $restguthaben, 'status' => $status, 'id' => $id]);
    }

    public function updateWooCouponId(int $id, ?int $wooCouponId): void
    {
        $stmt = $this->db->prepare("UPDATE gutscheine SET woo_coupon_id = :wcid WHERE id = :id");
        $stmt->execute(['wcid' => $wooCouponId, 'id' => $id]);
    }

    public function markiereVersendet(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE gutscheine SET versendet_am = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function storniere(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE gutscheine SET status = 'storniert' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /** Gutscheine mit geplanter Zustellung heute, die noch nicht verschickt wurden (für den Cronjob). */
    public function findFaelligeZustellungen(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM gutscheine
            WHERE zustellung_am IS NOT NULL AND zustellung_am <= CURDATE()
              AND versendet_am IS NULL AND status != 'storniert'
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertTransaktion(array $daten): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO gutschein_transaktionen (
                gutschein_id, auftrag_id, kassen_bon_id, betrag, kanal, notiz, benutzer_id
            ) VALUES (
                :gutschein_id, :auftrag_id, :kassen_bon_id, :betrag, :kanal, :notiz, :benutzer_id
            )
        ");
        $stmt->execute($daten);
        return (int)$this->db->lastInsertId();
    }

    public function findTransaktionenFuerGutschein(int $gutscheinId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, a.auftrag_nr
            FROM gutschein_transaktionen t
            LEFT JOIN auftraege a ON a.id = t.auftrag_id
            WHERE t.gutschein_id = :id
            ORDER BY t.erstellt_am ASC
        ");
        $stmt->execute(['id' => $gutscheinId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Vorlagen ─────────────────────────────────────────────────────────

    public function findAlleVorlagen(bool $nurAktive = false): array
    {
        $sql = "SELECT * FROM gutschein_vorlagen";
        if ($nurAktive) {
            $sql .= " WHERE aktiv = 1";
        }
        $sql .= " ORDER BY sort_order, name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findVorlageById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM gutschein_vorlagen WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    public function insertVorlage(array $daten): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO gutschein_vorlagen (name, hintergrundbild_pfad, aktiv, sort_order)
            VALUES (:name, :hintergrundbild_pfad, :aktiv, :sort_order)
        ");
        $stmt->execute($daten);
        return (int)$this->db->lastInsertId();
    }

    public function updateVorlage(int $id, array $daten): void
    {
        $daten['id'] = $id;
        $stmt = $this->db->prepare("
            UPDATE gutschein_vorlagen
            SET name = :name, hintergrundbild_pfad = :hintergrundbild_pfad,
                aktiv = :aktiv, sort_order = :sort_order
            WHERE id = :id
        ");
        $stmt->execute($daten);
    }

    // ── Shop-Gutschein-Artikel-Erkennung (für ShopBestellungSyncService) ────

    public function findeGutscheinArtikelIds(): array
    {
        $stmt = $this->db->query("SELECT id FROM artikel WHERE ist_gutschein = 1");
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Idempotenz-Check für den Shop-Gutschein-Artikel-Kauf: wurde für dieses
     * WC-Line-Item (innerhalb dieser Bestellung) bereits mindestens ein
     * Gutschein erzeugt? Verhindert Doppel-Erzeugung bei einem erneuten Poll
     * derselben Bestellung (z.B. wegen eines späteren Statuswechsels).
     */
    public function existiertBereitsFuerLineItem(int $auftragId, int $lineItemId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM gutscheine
            WHERE auftrag_id_ursprung = :auftrag_id AND kanal_line_item_id = :line_item_id
            LIMIT 1
        ");
        $stmt->execute(['auftrag_id' => $auftragId, 'line_item_id' => (string)$lineItemId]);
        return (bool)$stmt->fetchColumn();
    }

    public function verknuepfeMitLineItem(int $gutscheinId, int $auftragId, int $lineItemId): void
    {
        $stmt = $this->db->prepare("
            UPDATE gutscheine SET auftrag_id_ursprung = :auftrag_id, kanal_line_item_id = :line_item_id
            WHERE id = :id
        ");
        $stmt->execute(['auftrag_id' => $auftragId, 'line_item_id' => (string)$lineItemId, 'id' => $gutscheinId]);
    }
}
