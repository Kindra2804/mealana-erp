<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/PflegeSymbole.php';

/**
 * AnhaengerFreitextService – Anhänger für Stücke, die nicht im ERP sind (Babsis Strickstücke), Migration 235.
 * Gedruckt wird über PreisschildService (Vorlage "anhaenger", Option frei_ids).
 */
class AnhaengerFreitextService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function liste(bool $auchAusgeblendete = false): array
    {
        $sql = "SELECT * FROM anhaenger_freitext" . ($auchAusgeblendete ? '' : " WHERE aktiv = 1") . " ORDER BY aktiv DESC, erstellt_am DESC, id DESC";
        $zeilen = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($zeilen as &$z) $z['pflege'] = json_decode((string) $z['pflege'], true) ?: [];
        return $zeilen;
    }

    /** Legt an (ohne id) oder ändert. Liefert die id. */
    public function speichern(array $d): int
    {
        $titel = trim((string) ($d['titel'] ?? ''));
        if ($titel === '') throw new InvalidArgumentException('Bitte einen Namen eingeben.');
        $pflege = array_values(array_filter((array) ($d['pflege'] ?? []), fn($p) => PflegeSymbole::svg((string) $p) !== null));
        $preis = trim(str_replace(',', '.', (string) ($d['preis'] ?? '')));
        $werte = [
            'kopfzeile' => mb_substr(trim((string) ($d['kopfzeile'] ?? '')), 0, 60),
            'titel'     => mb_substr($titel, 0, 80),
            'material'  => mb_substr(trim((string) ($d['material'] ?? '')), 0, 120),
            'groesse'   => mb_substr(trim((string) ($d['groesse'] ?? '')), 0, 80),
            'zusatz'    => mb_substr(trim((string) ($d['zusatz'] ?? '')), 0, 160),
            'pflege'    => json_encode($pflege),
            'preis'     => $preis === '' ? null : round((float) $preis, 2),
            'badge'     => mb_substr(trim((string) ($d['badge'] ?? '')), 0, 20),
        ];
        if (!empty($d['id'])) {
            $werte['id'] = (int) $d['id'];
            $this->db->prepare("UPDATE anhaenger_freitext SET kopfzeile = :kopfzeile, titel = :titel, material = :material, groesse = :groesse,
                                zusatz = :zusatz, pflege = :pflege, preis = :preis, badge = :badge WHERE id = :id")->execute($werte);
            return $werte['id'];
        }
        $this->db->prepare("INSERT INTO anhaenger_freitext (kopfzeile, titel, material, groesse, zusatz, pflege, preis, badge)
                            VALUES (:kopfzeile, :titel, :material, :groesse, :zusatz, :pflege, :preis, :badge)")->execute($werte);
        return (int) $this->db->lastInsertId();
    }

    /** Ausblenden (verkauft/weggelegt) bzw. wieder einblenden */
    public function setzeAktiv(int $id, bool $aktiv): void
    {
        $this->db->prepare("UPDATE anhaenger_freitext SET aktiv = ? WHERE id = ?")->execute([(int) $aktiv, $id]);
    }

    /** Pflegesymbole zur Auswahl: symbol => Text (aus dem Merkmal "Pflege", Migration 233) */
    public function pflegeAuswahl(): array
    {
        return $this->db->query("
            SELECT w.symbol, w.wert FROM merkmal_werte w JOIN merkmale m ON m.id = w.merkmal_id AND m.slug = 'pflege'
            WHERE w.symbol IS NOT NULL ORDER BY w.sort_order")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
