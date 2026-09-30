<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/logger.php';
require_once __DIR__ . '/../lager/LagerService.php';

/**
 * RetourService – gemeinsame Logik für alles, was als Retoure zurück ins Lager kommt
 * (Packplatz-Retoure, Packplatz-Rücklagerung aus Kasse/ERP-Gutschrift, interne
 * Zustands-Umbuchung). Vorher hatte jeder Weg seine eigene Buchung, und der am
 * Packplatz gewählte Zustand war nur eine Notiz -- auch "gebraucht" landete als
 * verkaufbarer Neu-Bestand im Originalartikel.
 *
 * Zustandsregel (Jacky 2026-09-30):
 *   neu              -> Originalartikel
 *   defekt           -> nicht in den Bestand: Retoure-Eingang + sofortige Schwund-Ausbuchung (Lagerverfolgung)
 *   alles andere     -> Zustandsartikel (Artikelnummer + Anhang, z.B. D-101071-RET),
 *                       wird bei Bedarf automatisch angelegt; zählt nie zum Shop-Bestand
 */
class RetourService
{
    public const ZUSTAND_SUFFIXE = [
        'gebraucht'          => 'GEB',
        'generalueberholt'   => 'GEN',
        'beschaedigt'        => 'BSC',
        'retour'             => 'RET',
        'demo'               => 'DEMO',
        'muster'             => 'MST',
        'ausstellungsstueck' => 'AUS',
    ];
    public const ZUSTAND_LABELS = [
        'neu'                => 'Neu',
        'gebraucht'          => 'Gebraucht',
        'generalueberholt'   => 'Generalüberholt',
        'beschaedigt'        => 'Beschädigt',
        'retour'             => 'Retour',
        'demo'               => 'Demo',
        'muster'             => 'Muster',
        'ausstellungsstueck' => 'Ausstellungsstück',
        'defekt'             => 'Defekt',
    ];

    private PDO $db;
    private LagerService $lager;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->lager = new LagerService();
    }

    /**
     * Liefert den Zustandsartikel zu einem Original (legt ihn an, falls nötig).
     * Neu angelegt erbt er die Stammdaten inkl. Artikelgruppe und aktuellem
     * Standardpreis (als Startwert -- B-Ware-Preis danach am Artikel anpassen),
     * aber KEINE Shop-Zuordnung.
     *
     * @return array{id:int, artikelnummer:string, name:string, neu_angelegt:bool}
     */
    public function findeOderLegeZustandsartikelAn(int $originalId, string $zustand, int $benutzerId): array
    {
        if (!isset(self::ZUSTAND_SUFFIXE[$zustand])) {
            throw new InvalidArgumentException("Für Zustand '$zustand' gibt es keinen Zustandsartikel.");
        }

        $stmt = $this->db->prepare("SELECT * FROM artikel WHERE id = :id");
        $stmt->execute([':id' => $originalId]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orig) {
            throw new RuntimeException('Artikel nicht gefunden.');
        }
        // Retoure eines Zustandsartikels selbst: in dessen Original-Familie bleiben
        if (!empty($orig['zustand_vater_id']) && $orig['zustand'] !== 'neu') {
            if ($orig['zustand'] === $zustand) {
                return ['id' => (int)$orig['id'], 'artikelnummer' => $orig['artikelnummer'], 'name' => $orig['name'], 'neu_angelegt' => false];
            }
            return $this->findeOderLegeZustandsartikelAn((int)$orig['zustand_vater_id'], $zustand, $benutzerId);
        }

        $zsStmt = $this->db->prepare("SELECT id, artikelnummer, name FROM artikel WHERE zustand_vater_id = :vid AND zustand = :z LIMIT 1");
        $zsStmt->execute([':vid' => $originalId, ':z' => $zustand]);
        if ($zs = $zsStmt->fetch(PDO::FETCH_ASSOC)) {
            return ['id' => (int)$zs['id'], 'artikelnummer' => $zs['artikelnummer'], 'name' => $zs['name'], 'neu_angelegt' => false];
        }

        $neueNr = $orig['artikelnummer'] . '-' . self::ZUSTAND_SUFFIXE[$zustand];
        $check = $this->db->prepare("SELECT id FROM artikel WHERE artikelnummer = :nr");
        $check->execute([':nr' => $neueNr]);
        if ($check->fetchColumn()) {
            $neueNr .= '-' . $originalId; // Kollision mit fremdem Artikel vermeiden
        }
        $neuName = $orig['name'] . ' (' . self::ZUSTAND_LABELS[$zustand] . ')';

        $this->db->prepare("
            INSERT INTO artikel (
                artikelnummer, name, zustand, zustand_vater_id,
                steuerklasse_id, artikeltyp_id, artikel_gruppe_id, hersteller_id, einheit_id,
                hat_eigenen_lagerstand, aktiv, ist_vater,
                inhalt_menge, inhalt_einheit, gewicht_artikel, gewicht_versand, charge_pflicht
            ) VALUES (
                :nr, :name, :zustand, :zvid,
                :sklid, :atid, :agid, :hid, :eid,
                1, 1, 0,
                :imenge, :ieinheit, :gewart, :gewvers, :cpflicht
            )
        ")->execute([
            ':nr' => $neueNr, ':name' => $neuName, ':zustand' => $zustand, ':zvid' => $originalId,
            ':sklid' => $orig['steuerklasse_id'], ':atid' => $orig['artikeltyp_id'],
            ':agid' => $orig['artikel_gruppe_id'], ':hid' => $orig['hersteller_id'], ':eid' => $orig['einheit_id'],
            ':imenge' => $orig['inhalt_menge'], ':ieinheit' => $orig['inhalt_einheit'],
            ':gewart' => $orig['gewicht_artikel'], ':gewvers' => $orig['gewicht_versand'],
            ':cpflicht' => $orig['charge_pflicht'] ?? 0,
        ]);
        $neuId = (int)$this->db->lastInsertId();

        // Aktuell gültige Preise als Startwert übernehmen
        $this->db->prepare("
            INSERT INTO artikel_preise (artikel_id, kundengruppen_id, brutto_vk, netto_vk, gueltig_ab, gueltig_bis)
            SELECT :neu, kundengruppen_id, brutto_vk, netto_vk, gueltig_ab, gueltig_bis
            FROM artikel_preise
            WHERE artikel_id = :orig
              AND (gueltig_bis IS NULL OR gueltig_bis >= CURDATE())
        ")->execute([':neu' => $neuId, ':orig' => $originalId]);

        Logger::log('artikel.zustandsartikel_angelegt', 'artikel', $neuId, [
            'vater_id' => $originalId, 'zustand' => $zustand, 'artikelnr' => $neueNr,
        ], $benutzerId);

        return ['id' => $neuId, 'artikelnummer' => $neueNr, 'name' => $neuName, 'neu_angelegt' => true];
    }

    /**
     * Bucht zurückgekommene Ware nach der Zustandsregel ein (siehe Klassenkommentar).
     * $d: artikel_id, lager_id, menge, zustand, charge?, referenz, notiz?, benutzer_id
     *
     * @return array{erfolg:bool, artikel_id:?int, artikelnummer:?string, text:string, fehler?:string}
     */
    public function einbuchen(array $d): array
    {
        $zustand = $d['zustand'] ?? 'neu';
        $benutzerId = (int)($d['benutzer_id'] ?? 0);

        if ($zustand === 'defekt') {
            // Jacky 2026-09-30: defekt nicht in den Bestand, aber in der Lagerverfolgung
            // dokumentieren -> Retoure-Eingang + sofortige Schwund-Ausbuchung (netto 0,
            // beide Bewegungen inkl. Charge im Bewegungslog des Artikels sichtbar).
            $basis = [
                'artikel_id'  => (int)$d['artikel_id'],
                'lager_id'    => (int)$d['lager_id'],
                'menge'       => (float)$d['menge'],
                'charge'      => $d['charge'] ?? null,
                'referenz'    => $d['referenz'],
                'benutzer_id' => $benutzerId,
            ];
            $ein = $this->lager->wareneingang($basis + ['notiz' => trim(($d['notiz'] ?? '') . ' — Zustand: Defekt', ' —')]);
            if (!($ein['erfolg'] ?? false)) {
                return ['erfolg' => false, 'artikel_id' => null, 'artikelnummer' => null, 'text' => '',
                        'fehler' => $ein['fehler'] ?? 'Einbuchen fehlgeschlagen'];
            }
            $this->lager->warenSchwund($basis + ['notiz' => 'Defekte Retoure ausgebucht']);
            return ['erfolg' => true, 'artikel_id' => null, 'artikelnummer' => null,
                    'text' => $d['menge'] . '× defekt — als Schwund ausgebucht'];
        }

        $zielId = (int)$d['artikel_id'];
        $zielNr = null;
        if ($zustand !== 'neu') {
            $zs = $this->findeOderLegeZustandsartikelAn($zielId, $zustand, $benutzerId);
            $zielId = $zs['id'];
            $zielNr = $zs['artikelnummer'];
        }

        $r = $this->lager->wareneingang([
            'artikel_id'  => $zielId,
            'lager_id'    => (int)$d['lager_id'],
            'menge'       => (float)$d['menge'],
            'charge'      => $d['charge'] ?? null,
            'referenz'    => $d['referenz'],
            'notiz'       => trim(($d['notiz'] ?? '') . ' — Zustand: ' . (self::ZUSTAND_LABELS[$zustand] ?? $zustand), ' —'),
            'benutzer_id' => $benutzerId,
        ]);
        if (!($r['erfolg'] ?? false)) {
            return ['erfolg' => false, 'artikel_id' => null, 'artikelnummer' => null, 'text' => '',
                    'fehler' => $r['fehler'] ?? 'Einbuchen fehlgeschlagen'];
        }
        if ($zielNr === null) {
            $s = $this->db->prepare("SELECT artikelnummer FROM artikel WHERE id = ?");
            $s->execute([$zielId]);
            $zielNr = (string)$s->fetchColumn();
        }
        return ['erfolg' => true, 'artikel_id' => $zielId, 'artikelnummer' => $zielNr,
                'text' => $d['menge'] . '× → ' . $zielNr];
    }

    /**
     * Welche Chargen wurden mit diesem Auftrag für diesen Artikel verkauft, aus welchem
     * Lager? Quelle sind die Lagerbewegungen (Warenausgang) -- die einzige Stelle, die
     * Versand, Abholung und Teillieferung mit exakter Menge pro Charge abdeckt
     * (auftrag_positionen.charge ist nur eine Textliste ohne Mengen).
     *
     * @return array<int, array{charge:?string, lager_id:int, menge:float}>
     */
    public function verkaufteChargen(int $auftragId, int $artikelId): array
    {
        $a = $this->db->prepare("SELECT auftrag_nr FROM auftraege WHERE id = ?");
        $a->execute([$auftragId]);
        $referenzen = [(string)$a->fetchColumn()];

        $b = $this->db->prepare("SELECT bon_nr FROM kassen_bons WHERE (auftrag_id = :id OR web_auftrag_id = :id2) AND typ = 'verkauf' AND storniert = 0");
        $b->execute([':id' => $auftragId, ':id2' => $auftragId]);
        foreach ($b->fetchAll(PDO::FETCH_COLUMN) as $bonNr) {
            $referenzen[] = 'Kassenbon ' . $bonNr;
        }
        $referenzen = array_values(array_filter($referenzen));
        if (!$referenzen) return [];

        $ph = implode(',', array_fill(0, count($referenzen), '?'));
        $stmt = $this->db->prepare("
            SELECT charge, lager_id, SUM(menge) AS menge
            FROM lager_bewegungen
            WHERE artikel_id = ? AND bewegungstyp = 'ausgang' AND referenz IN ($ph)
            GROUP BY charge, lager_id
            -- bekannte Chargen zuerst (Vorschlag soll nie 'ohne Charge' nehmen, solange
            -- benannte verkauft wurden), innerhalb davon in Verkaufsreihenfolge
            ORDER BY charge IS NULL, MIN(erstellt_am)
        ");
        $stmt->execute(array_merge([$artikelId], $referenzen));
        return array_map(fn($r) => [
            'charge' => $r['charge'], 'lager_id' => (int)$r['lager_id'], 'menge' => (float)$r['menge'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Teilt eine Rückgabemenge auf die verkauften Chargen auf (in Verkaufsreihenfolge,
     * jede Charge höchstens bis zu ihrer verkauften Menge). Rest ohne bekannte Charge
     * kommt als Eintrag mit charge=null.
     *
     * @return array<int, array{charge:?string, lager_id:?int, menge:float}>
     */
    public function verteileAufChargen(array $verkauft, float $menge): array
    {
        $teile = [];
        foreach ($verkauft as $v) {
            if ($menge <= 0) break;
            $m = min($menge, $v['menge']);
            if ($m <= 0) continue;
            $teile[] = ['charge' => $v['charge'], 'lager_id' => $v['lager_id'], 'menge' => $m];
            $menge -= $m;
        }
        if ($menge > 0) {
            $teile[] = ['charge' => null, 'lager_id' => $verkauft[0]['lager_id'] ?? null, 'menge' => $menge];
        }
        return $teile;
    }
}
