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
        string $suche = '',
        bool   $mitAbgeschlossenen = false
    ): array {
        $where  = ['1=1'];
        $params = [];

        // Abgeschlossene (lieferung=abgeschlossen UND zahlung=bezahlt) standardmäßig ausblenden
        if (!$mitAbgeschlossenen && $lieferstatus === '' && $zahlungsstatus === '') {
            $where[] = "NOT (a.lieferstatus = 'abgeschlossen' AND a.zahlungsstatus = 'bezahlt')";
        }

        if ($zahlungsstatus === 'ueberbezahlt') {
            $where[] = "a.zahlungsstatus = 'bezahlt' AND (SELECT COALESCE(SUM(az.betrag),0) FROM auftrag_zahlungen az WHERE az.auftrag_id = a.id) > a.bruttobetrag";
        } elseif ($zahlungsstatus !== '') {
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
            $where[]        = '(a.auftrag_nr LIKE :suche OR a.kunden_snapshot LIKE :suche OR a.notiz_intern LIKE :suche)';
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
                a.kunden_snapshot,
                k.kundennummer,
                COUNT(p.id) AS positionen_anzahl,
                (SELECT COALESCE(SUM(az.betrag),0) FROM auftrag_zahlungen az WHERE az.auftrag_id = a.id) AS summe_zahlungen
            FROM auftraege a
            LEFT JOIN kunden k ON k.id = a.kunden_id
            LEFT JOIN auftrag_positionen p ON p.auftrag_id = a.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY a.id, a.auftrag_nr, a.kanal, a.zahlungsstatus, a.lieferstatus,
                     a.zahlungsart, a.bruttobetrag, a.versandkosten, a.tracking_nr,
                     a.mahnung_stufe, a.bezahlt_am, a.erstellt_am, a.kunden_id,
                     a.kunden_snapshot, k.kundennummer
            ORDER BY a.erstellt_am DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['kunden_name'] = $this->kundenNameAusSnapshot($row);
        }
        return $rows;
    }

    /**
     * Gibt einen Auftrag anhand ID zurück, inklusive Kundenname.
     */
    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                k.kundennummer
            FROM auftraege a
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE a.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $row['kunden_name']  = $this->kundenNameAusSnapshot($row);
        $row['kunden_email'] = $this->kundenEmailAusSnapshot($row);
        return $row;
    }

    /**
     * Liest den Anzeigenamen aus kunden_snapshot (JSON) oder fällt auf kundennummer zurück.
     */
    private function kundenNameAusSnapshot(array $row): string
    {
        if (!empty($row['kunden_snapshot'])) {
            $snap = json_decode($row['kunden_snapshot'], true);
            if (!empty($snap['name'])) return $snap['name'];
        }
        if (!empty($row['kundennummer'])) return 'Kd. ' . $row['kundennummer'];
        return 'Laufkunde';
    }

    private function kundenEmailAusSnapshot(array $row): string
    {
        if (!empty($row['kunden_snapshot'])) {
            $snap = json_decode($row['kunden_snapshot'], true);
            return $snap['email'] ?? '';
        }
        return '';
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
                b.dateiname                     AS bild_pfad
            FROM auftrag_positionen p
            JOIN artikel art ON art.id = p.artikel_id
            LEFT JOIN artikel vater ON vater.id = art.vaterartikel_id
            LEFT JOIN artikel_bilder b ON b.artikel_id = p.artikel_id AND b.position = 0
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
                b.formularname AS erstellt_von_name
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
        $words = preg_split('/\s+/', trim($suche), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($words)) return [];

        $whereParts = [];
        $params = [];
        foreach ($words as $i => $word) {
            $k = 'w' . $i;
            $whereParts[] = "(a.name LIKE :$k OR vater.name LIKE :$k OR a.artikelnummer LIKE :$k
                             OR vater.artikelnummer LIKE :$k
                             OR EXISTS (SELECT 1 FROM artikel_codes ac WHERE ac.artikel_id = a.id AND ac.code LIKE :$k))";
            $params[$k] = '%' . $word . '%';
        }
        $where = implode(' AND ', $whereParts);

        $stmt = $this->db->prepare("
            SELECT
                a.id,
                COALESCE(vater.name, a.name)                  AS name,
                CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.name END AS variante_name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS artikelnummer,
                (SELECT ap.brutto_vk FROM artikel_preise ap
                    JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                    WHERE ap.artikel_id = a.id
                    LIMIT 1)                                   AS vk_brutto,
                (SELECT sk.satz FROM steuerklassen sk WHERE sk.id = a.steuerklasse_id) AS steuer_prozent,
                (SELECT MIN(c.code) FROM artikel_codes c WHERE c.artikel_id = a.id AND c.typ = 'GTIN13') AS ean,
                (SELECT dateiname FROM artikel_bilder WHERE artikel_id = a.id AND position = 0 LIMIT 1) AS bild_pfad
            FROM artikel a
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE a.aktiv = 1
              AND (a.ist_vater = 0 OR a.ist_vater IS NULL)
              AND ($where)
            ORDER BY COALESCE(vater.name, a.name), a.name
            LIMIT 75
        ");
        $stmt->execute($params);
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
            INSERT IGNORE INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
            VALUES ('auftrag', 'A', :jahr, 0)
        ")->execute(['jahr' => $jahr]);

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
                zahlungsart, zahlungsbedingung_id, lieferart,
                gutschein_id, gutschein_betrag,
                versandkosten, rabatt_gesamt, versandklasse_id,
                nettobetrag, steuerbetrag, bruttobetrag,
                notiz_intern, notiz_versand, kontakt_notiz, erstellt_von
            ) VALUES (
                :auftrag_nr, :kunden_id, :kunden_snapshot,
                :lieferadresse_snapshot, :rechnungsadresse_snapshot,
                :kanal, :kanal_auftrag_id,
                :zahlungsstatus, :lieferstatus,
                :zahlungsart, :zahlungsbedingung_id, :lieferart,
                :gutschein_id, :gutschein_betrag,
                :versandkosten, :rabatt_gesamt, :versandklasse_id,
                :nettobetrag, :steuerbetrag, :bruttobetrag,
                :notiz_intern, :notiz_versand, :kontakt_notiz, :erstellt_von
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
                menge, menge_geliefert, einzelpreis_netto, steuer_prozent, rabatt_prozent,
                gesamtpreis_netto, sort_order
            ) VALUES (
                :auftrag_id, :artikel_id, :charge, :bezeichnung, :ean,
                :menge, :menge_geliefert, :einzelpreis_netto, :steuer_prozent, :rabatt_prozent,
                :gesamtpreis_netto, :sort_order
            )
        ");
        $stmt->execute($data);
    }

    /**
     * Legt Lagerreservierungen für alle Positionen eines neuen Auftrags an.
     */
    public function legeReservierungenAn(int $auftragId, array $positionen, string $kanal): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO reservierungen
                (artikel_id, lager_id, menge, kanal, referenz_tabelle, referenz_id, status)
            VALUES
                (:artikel_id, 1, :menge, :kanal, 'auftraege', :referenz_id, 'offen')
        ");
        foreach ($positionen as $pos) {
            if (empty($pos['artikel_id'])) continue;
            $stmt->execute([
                ':artikel_id'  => (int)$pos['artikel_id'],
                ':menge'       => (int)$pos['menge'],
                ':kanal'       => $kanal,
                ':referenz_id' => $auftragId,
            ]);
        }
    }

    /**
     * Schließt alle Reservierungen eines Auftrags (nach Versand oder Stornierung).
     */
    public function schliesseReservierungen(int $auftragId): void
    {
        $this->db->prepare("
            UPDATE reservierungen SET status = 'erledigt'
            WHERE referenz_tabelle = 'auftraege' AND referenz_id = :id AND status = 'offen'
        ")->execute([':id' => $auftragId]);
    }

    /**
     * Aktualisiert Zahlungs- und/oder Lieferstatus sowie optionale Felder.
     */
    public function updateStatus(int $id, array $felder): void
    {
        $sets   = [];
        $params = ['id' => $id];

        $erlaubt = [
            'zahlungsstatus',
            'lieferstatus',
            'bezahlt_am',
            'tracking_nr',
            'versanddienstleister',
            'mahnung_stufe',
            'mahnung_gesendet_am',
            'notiz_intern',
            'notiz_versand',
            'kontakt_notiz',
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
            INSERT IGNORE INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
            VALUES ('rechnung', 'R', :jahr, 0)
        ")->execute(['jahr' => $jahr]);

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

    public function updateHeader(int $id, array $felder): void
    {
        $sets   = [];
        $params = ['id' => $id];

        $erlaubt = [
            'kunden_id',
            'kunden_snapshot',
            'zahlungsart',
            'lieferart',
            'versandklasse_id',
            'versandkosten',
            'nettobetrag',
            'steuerbetrag',
            'bruttobetrag',
            'notiz_intern',
            'notiz_versand',
            'lieferadresse_snapshot',
            'rechnungsadresse_snapshot',
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

    public function deletePositionen($id): void
    {
        $stmt = $this->db->prepare("DELETE FROM auftrag_positionen WHERE auftrag_id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function setMengeGeliefert(int $positionId, float $menge): void
    {
        $this->db->prepare("UPDATE auftrag_positionen SET menge_geliefert = ? WHERE id = ?")
                 ->execute([$menge, $positionId]);
    }

    public function findZahlungen(int $auftragId): array
    {
        $stmt = $this->db->prepare("
            SELECT az.*, b.formularname AS erfasst_von_name
            FROM auftrag_zahlungen az
            LEFT JOIN benutzer b ON az.erfasst_von = b.id
            WHERE az.auftrag_id = :id
            ORDER BY az.buchungsdatum, az.erfasst_am
        ");
        $stmt->execute(['id' => $auftragId]);
        return $stmt->fetchAll();
    }

    public function insertZahlung(int $auftragId, float $betrag, string $buchungsdatum, ?string $notiz, int $benutzerId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO auftrag_zahlungen (auftrag_id, betrag, buchungsdatum, notiz, erfasst_von)
            VALUES (:auftrag_id, :betrag, :buchungsdatum, :notiz, :erfasst_von)
        ");
        $stmt->execute([
            'auftrag_id'    => $auftragId,
            'betrag'        => $betrag,
            'buchungsdatum' => $buchungsdatum,
            'notiz'         => $notiz,
            'erfasst_von'   => $benutzerId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getSummeZahlungen(int $auftragId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(betrag), 0) FROM auftrag_zahlungen WHERE auftrag_id = :id");
        $stmt->execute(['id' => $auftragId]);
        return (float)$stmt->fetchColumn();
    }
}
