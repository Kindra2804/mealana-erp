<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * AuftragRepository – CRUD für Verkaufsaufträge, Positionen, Statuslog und Rechnungen.
 *
 * Auftragsnummer: "A-2026-00001" via dokument_nummern (typ='auftrag') — transaktionssicher.
 * Rechnungsnummer: "R-2026-00001" via dokument_nummern (typ='rechnung').
 *
 * Zahlungsstatus und Lieferstatus sind unabhängige Felder (JTL-Ansatz).
 * Alle Statusänderungen werden in auftrag_statuslog protokolliert.
 */
class AuftragRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gibt alle Aufträge zurück, optional gefiltert.
     * Enthält: Kundenname (oder "Laufkunde"), Bruttobetrag, Positionsanzahl.
     *
     * @param string $zahlungsstatus "" für alle
     * @param string $lieferstatus   "" für alle
     * @param string $kanal          "" für alle
     * @param string $suche          Suche in auftrag_nr, Kundenname
     */
    public function findAll(
        string $zahlungsstatus = '',
        string $lieferstatus = '',
        string $kanal = '',
        string $suche = ''
    ): array {
        $where  = ['1=1'];
        $params = [];

        if ($zahlungsstatus !== '') {
            $where[]                = 'a.zahlungsstatus = :zahlungsstatus';
            $params['zahlungsstatus'] = $zahlungsstatus;
        }
        if ($lieferstatus !== '') {
            $where[]               = 'a.lieferstatus = :lieferstatus';
            $params['lieferstatus'] = $lieferstatus;
        }
        if ($kanal !== '') {
            $where[]        = 'a.kanal = :kanal';
            $params['kanal'] = $kanal;
        }
        if ($suche !== '') {
            $where[]        = '(a.auftrag_nr LIKE :suche OR k.name LIKE :suche OR a.notiz_intern LIKE :suche)';
            $params['suche'] = '%' . $suche . '%';
        }

        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.auftrag_nr,
                a.kanal,
                a.zahlungsstatus,
                a.lieferstatus,
                a.zahlungsart,
                a.bruttobetrag,
                a.versandkosten,
                a.tracking_nr,
                a.mahnung_stufe,
                a.bezahlt_am,
                a.erstellt_am,
                a.kunden_id,
                COALESCE(k.name, 'Laufkunde') AS kunden_name,
                COUNT(p.id) AS positionen_anzahl
            FROM auftraege a
            LEFT JOIN kunden k ON k.id = a.kunden_id
            LEFT JOIN auftrag_positionen p ON p.auftrag_id = a.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY a.id, a.auftrag_nr, a.kanal, a.zahlungsstatus, a.lieferstatus,
                     a.zahlungsart, a.bruttobetrag, a.versandkosten, a.tracking_nr,
                     a.mahnung_stufe, a.bezahlt_am, a.erstellt_am, a.kunden_id, k.name
            ORDER BY a.erstellt_am DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gibt einen Auftrag anhand ID zurück, inklusive Kundenname.
     */
    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                COALESCE(k.name, 'Laufkunde') AS kunden_name,
                k.email                         AS kunden_email
            FROM auftraege a
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE a.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Gibt alle Positionen eines Auftrags zurück, mit Artikelname und Hauptbild.
     */
    public function findPositionen(int $auftragId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                COALESCE(vater.name, art.name) AS artikel_name_aktuell,
                b.pfad                          AS bild_pfad
            FROM auftrag_positionen p
            JOIN artikel art ON art.id = p.artikel_id
            LEFT JOIN artikel vater ON vater.id = art.vaterartikel_id
            LEFT JOIN artikel_bilder b ON b.artikel_id = p.artikel_id AND b.ist_hauptbild = 1
            WHERE p.auftrag_id = :auftrag_id
            ORDER BY p.sort_order, p.id
        ");
        $stmt->execute(['auftrag_id' => $auftragId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gibt alle Einträge des Statuslogs für einen Auftrag zurück.
     */
    public function findStatuslog(int $auftragId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                l.*,
                b.benutzername AS erstellt_von_name
            FROM auftrag_statuslog l
            JOIN benutzer b ON b.id = l.erstellt_von
            WHERE l.auftrag_id = :auftrag_id
            ORDER BY l.erstellt_am DESC
        ");
        $stmt->execute(['auftrag_id' => $auftragId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sucht aktive Artikel für den Positions-Typeahead (alle Artikel, keine Vater-Filter).
     */
    public function findArtikelFuerSuche(string $suche): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                COALESCE(vater.name, a.name)                  AS name,
                CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.name END AS variante_name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS artikelnummer,
                a.vk_brutto,
                a.steuerklasse_id,
                (SELECT MIN(c.code) FROM artikel_codes c WHERE c.artikel_id = a.id AND c.typ = 'GTIN13')
                                                              AS ean,
                (SELECT pfad FROM artikel_bilder WHERE artikel_id = a.id AND ist_hauptbild = 1 LIMIT 1)
                                                              AS bild_pfad
            FROM artikel a
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE a.aktiv = 1
              AND (a.ist_vater = 0 OR a.ist_vater IS NULL)
              AND (
                  a.name LIKE :suche
                  OR vater.name LIKE :suche
                  OR a.artikelnummer LIKE :suche
                  OR vater.artikelnummer LIKE :suche
              )
            ORDER BY COALESCE(vater.name, a.name), a.name
            LIMIT 20
        ");
        $stmt->execute(['suche' => '%' . $suche . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Legt einen neuen Auftrag an und gibt die neue ID zurück.
     * Auftragsnummer wird transaktionssicher via dokument_nummern generiert.
     */
    public function insert(array $data): int
    {
        $this->db->beginTransaction();

        $jahr = date('Y');
        $this->db->prepare("
            UPDATE dokument_nummern SET letzt_nr = letzt_nr + 1
            WHERE typ = 'auftrag' AND jahr = :jahr
        ")->execute(['jahr' => $jahr]);

        $nr = $this->db->prepare("
            SELECT letzt_nr FROM dokument_nummern WHERE typ = 'auftrag' AND jahr = :jahr
        ");
        $nr->execute(['jahr' => $jahr]);
        $laufNr     = (int)$nr->fetchColumn();
        $auftragNr  = 'A-' . $jahr . '-' . str_pad($laufNr, 5, '0', STR_PAD_LEFT);

        $stmt = $this->db->prepare("
            INSERT INTO auftraege (
                auftrag_nr, kunden_id, kunden_snapshot,
                lieferadresse_snapshot, rechnungsadresse_snapshot,
                kanal, kanal_auftrag_id,
                zahlungsstatus, lieferstatus,
                zahlungsart, zahlungsbedingung_id,
                gutschein_id, gutschein_betrag,
                versandkosten, rabatt_gesamt,
                nettobetrag, steuerbetrag, bruttobetrag,
                notiz_intern, notiz_versand, erstellt_von
            ) VALUES (
                :auftrag_nr, :kunden_id, :kunden_snapshot,
                :lieferadresse_snapshot, :rechnungsadresse_snapshot,
                :kanal, :kanal_auftrag_id,
                :zahlungsstatus, :lieferstatus,
                :zahlungsart, :zahlungsbedingung_id,
                :gutschein_id, :gutschein_betrag,
                :versandkosten, :rabatt_gesamt,
                :nettobetrag, :steuerbetrag, :bruttobetrag,
                :notiz_intern, :notiz_versand, :erstellt_von
            )
        ");
        $stmt->execute(array_merge($data, ['auftrag_nr' => $auftragNr]));
        $id = (int)$this->db->lastInsertId();

        $this->db->commit();
        return $id;
    }

    /**
     * Fügt eine Position zu einem Auftrag hinzu.
     */
    public function insertPosition(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO auftrag_positionen (
                auftrag_id, artikel_id, charge, bezeichnung, ean,
                menge, einzelpreis_netto, steuer_prozent, rabatt_prozent,
                gesamtpreis_netto, sort_order
            ) VALUES (
                :auftrag_id, :artikel_id, :charge, :bezeichnung, :ean,
                :menge, :einzelpreis_netto, :steuer_prozent, :rabatt_prozent,
                :gesamtpreis_netto, :sort_order
            )
        ");
        $stmt->execute($data);
    }

    /**
     * Aktualisiert Zahlungs- und/oder Lieferstatus sowie optionale Felder.
     */
    public function updateStatus(int $id, array $felder): void
    {
        $sets   = [];
        $params = ['id' => $id];

        $erlaubt = [
            'zahlungsstatus', 'lieferstatus', 'bezahlt_am',
            'tracking_nr', 'versanddienstleister',
            'mahnung_stufe', 'mahnung_gesendet_am',
            'notiz_intern', 'notiz_versand',
        ];
        foreach ($erlaubt as $f) {
            if (array_key_exists($f, $felder)) {
                $sets[]    = "$f = :$f";
                $params[$f] = $felder[$f];
            }
        }
        if (empty($sets)) return;

        $this->db->prepare("UPDATE auftraege SET " . implode(', ', $sets) . " WHERE id = :id")
                 ->execute($params);
    }

    /**
     * Schreibt einen Eintrag ins Statuslog.
     */
    public function logStatus(int $auftragId, array $changes, ?string $notiz, int $benutzerId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO auftrag_statuslog (auftrag_id, felder_geaendert, notiz, erstellt_von)
            VALUES (:auftrag_id, :felder_geaendert, :notiz, :erstellt_von)
        ");
        $stmt->execute([
            'auftrag_id'       => $auftragId,
            'felder_geaendert' => json_encode($changes, JSON_UNESCAPED_UNICODE),
            'notiz'            => $notiz,
            'erstellt_von'     => $benutzerId,
        ]);
    }

    /**
     * Gibt alle Aufträge zurück die für Mahnung/Stornierung fällig sind (Vorkasse).
     * 14+ Tage ohne Zahlung → mahnung_stufe=0 → Erinnerung fällig.
     * 30+ Tage → stornierungsliste.
     */
    public function findVorkasseUeberfaellig(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                COALESCE(k.name, 'Laufkunde') AS kunden_name,
                k.email AS kunden_email,
                DATEDIFF(NOW(), a.erstellt_am) AS tage_offen
            FROM auftraege a
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE a.zahlungsart = 'vorkasse'
              AND a.zahlungsstatus = 'ausstehend'
              AND a.lieferstatus NOT IN ('storniert','abgeschlossen')
              AND DATEDIFF(NOW(), a.erstellt_am) >= 14
            ORDER BY tage_offen DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Erzeugt eine neue Rechnungsnummer (transaktionssicher) und legt den Rechnungsdatensatz an.
     */
    public function insertRechnung(array $data): int
    {
        $this->db->beginTransaction();

        $jahr = date('Y');
        $this->db->prepare("
            UPDATE dokument_nummern SET letzt_nr = letzt_nr + 1
            WHERE typ = 'rechnung' AND jahr = :jahr
        ")->execute(['jahr' => $jahr]);

        $nr = $this->db->prepare("
            SELECT letzt_nr FROM dokument_nummern WHERE typ = 'rechnung' AND jahr = :jahr
        ");
        $nr->execute(['jahr' => $jahr]);
        $laufNr      = (int)$nr->fetchColumn();
        $rechnungNr  = 'R-' . $jahr . '-' . str_pad($laufNr, 5, '0', STR_PAD_LEFT);

        $stmt = $this->db->prepare("
            INSERT INTO rechnungen (rechnung_nr, auftrag_id, nettobetrag, steuerbetrag, bruttobetrag, faellig_am, erstellt_von)
            VALUES (:rechnung_nr, :auftrag_id, :nettobetrag, :steuerbetrag, :bruttobetrag, :faellig_am, :erstellt_von)
        ");
        $stmt->execute(array_merge($data, ['rechnung_nr' => $rechnungNr]));
        $id = (int)$this->db->lastInsertId();

        $this->db->commit();
        return $id;
    }
}
