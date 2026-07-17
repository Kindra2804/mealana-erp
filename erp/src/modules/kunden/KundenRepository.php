<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Encryption.php';

/**
 * KundenRepository – CRUD für Kunden mit AES-256-GCM Verschlüsselung
 *
 * Alle persönlichen Daten (Name, E-Mail, Telefon, Adresse, etc.) werden
 * verschlüsselt in der DB gespeichert (DSGVO-Pflicht). Lesbare Klartexte
 * stehen nur nach dem Entschlüsseln zur Verfügung.
 *
 * Spaltenkonvention:
 *   vorname_enc  → BLOB, verschlüsselt   | vorname  → Klartext (nach entschluesseln())
 *   email_hash   → HMAC für exakte Suche | email    → Klartext
 *
 * Suche nach Name/E-Mail erfolgt in PHP (nicht SQL-LIKE), weil verschlüsselte
 * Felder keinen Index-Scan erlauben. Nur Duplikat-Check per email_hash ist O(1).
 *
 * Laufkunde (id=1) hat ist_laufkunde=1 und erscheint nicht in der Kundenliste.
 * Löschen = Status "geloescht" (Soft-Delete).
 */
class KundenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Liste + Suche
    // -------------------------------------------------------------------------

    /**
     * Gibt alle Kunden zurück (ohne Laufkunde), nach Suche und Status gefiltert.
     *
     * Suche und Statusfilter erfolgen in PHP weil verschlüsselte Felder
     * kein SQL-LIKE erlauben. Bei großen Kundenstämmen → Performance-Thema.
     *
     * @param string $suche  Freitextsuche in Vor-/Nachname, Firma, E-Mail, Kundennummer
     * @param string $status Filterung auf "aktiv" | "gesperrt" | "geloescht"
     */
    public function findAll(string $suche = '', string $status = ''): array
    {
        $stmt = $this->db->query("
            SELECT
                k.id, k.kundennummer, k.status, k.ist_firma, k.ist_laufkunde,
                k.kundenherkunft, k.kreditlimit, k.sprache, k.erstellt_am,
                k.vorname_enc, k.nachname_enc, k.firmenname_enc,
                k.email_enc, k.email_hash,
                kg.name AS kundengruppe
            FROM kunden k
            LEFT JOIN kundengruppen kg ON k.kundengruppe_id = kg.id
            WHERE k.ist_laufkunde = 0
            ORDER BY k.id DESC
        ");

        $rows = $stmt->fetchAll();
        // Alle Zeilen entschlüsseln bevor gefiltert wird
        $rows = array_map([$this, 'entschluesseln'], $rows);

        // Suche in PHP (verschlüsselte Felder lassen kein SQL-LIKE zu)
        if ($suche !== '') {
            $s = strtolower($suche);
            $rows = array_filter($rows, function ($r) use ($s) {
                return str_contains(strtolower($r['vorname'] ?? ''), $s)
                    || str_contains(strtolower($r['nachname'] ?? ''), $s)
                    || str_contains(strtolower($r['firmenname'] ?? ''), $s)
                    || str_contains(strtolower($r['email'] ?? ''), $s)
                    || str_contains(strtolower($r['kundennummer']), $s);
            });
        }

        if ($status !== '') {
            $rows = array_filter($rows, fn($r) => $r['status'] === $status);
        }

        return array_values($rows);
    }

    /**
     * Gibt einen vollständigen Kundendatensatz zurück (entschlüsselt).
     * Enthält zusätzlich: Zahlungsbedingung, Telefon, Mobil, Notiz, etc.
     */
    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT
                k.id, k.kundennummer, k.debitorennummer, k.status, k.ist_firma, k.ist_laufkunde,
                k.kundenherkunft, k.kreditlimit, k.sprache,
                k.standardzahlungsart, k.kundengruppe_id, k.zahlungsbedingung_id,
                k.erstellt_am, k.aktualisiert_am,
                k.vorname_enc, k.nachname_enc, k.firmenname_enc,
                k.email_enc, k.email_hash,
                k.telefon_enc, k.mobil_enc, k.geburtsdatum_enc,
                k.uid_nummer_enc, k.notiz_enc,
                kg.name AS kundengruppe,
                zb.name AS zahlungsbedingung_name
            FROM kunden k
            LEFT JOIN kundengruppen kg ON k.kundengruppe_id = kg.id
            LEFT JOIN zahlungsbedingungen zb ON k.zahlungsbedingung_id = zb.id
            WHERE k.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->entschluesseln($row) : false;
    }

    /**
     * Sucht einen Kunden über den HMAC-Hash seiner E-Mail-Adresse.
     * O(1)-Lookup via Index auf email_hash — wird für Duplikat-Erkennung verwendet.
     * Normalisierung (lowercase + trim) passiert in Encryption::hash().
     */
    /** Setzt nur die Debitorennummer (Einzelfeld-Update, für die Klick-zum-Bearbeiten-Aktion auf detail.php). */
    public function updateDebitorennummer(int $id, string $debitorennummer): void
    {
        $this->db->prepare("UPDATE kunden SET debitorennummer = ? WHERE id = ?")
            ->execute([$debitorennummer, $id]);
    }

    /** Prüft ob eine Debitorennummer schon einem ANDEREN Kunden gehört ($ausserId beim Bearbeiten die eigene ID). */
    public function debitorennummerVergeben(string $debitorennummer, ?int $ausserId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM kunden WHERE debitorennummer = :nr";
        $params = ['nr' => $debitorennummer];
        if ($ausserId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $ausserId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function findByEmailHash(string $email): array|false
    {
        $hash = Encryption::hash($email);
        $stmt = $this->db->prepare("
            SELECT id, kundennummer, status, vorname_enc, nachname_enc, email_enc
            FROM kunden WHERE email_hash = :hash AND ist_laufkunde = 0
        ");
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();
        return $row ? $this->entschluesseln($row) : false;
    }

    // -------------------------------------------------------------------------
    // Kundennummer
    // -------------------------------------------------------------------------

    /**
     * Generiert die nächste Kundennummer im Format "KD-00001".
     * Findet die höchste bestehende KD-Nummer via Regex + CAST und zählt um 1 hoch.
     * Wenn keine Kunden existieren, startet bei KD-00001.
     */
    public function nextKundennummer(): string
    {
        $stmt = $this->db->query("
            SELECT kundennummer FROM kunden
            WHERE kundennummer REGEXP '^KD-[0-9]+$'
            ORDER BY CAST(SUBSTRING(kundennummer, 4) AS UNSIGNED) DESC
            LIMIT 1
        ");
        $last = $stmt->fetchColumn();
        $next = $last ? ((int) substr($last, 3)) + 1 : 1;
        return 'KD-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // Schreiben
    // -------------------------------------------------------------------------

    /**
     * Legt einen neuen Kunden an. Verschlüsselt alle personenbezogenen Felder
     * über verschluesseln() bevor der INSERT ausgeführt wird.
     */
    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO kunden (
                kundennummer, debitorennummer, status, ist_firma, kundengruppe_id,
                zahlungsbedingung_id, standardzahlungsart, kreditlimit,
                sprache, kundenherkunft,
                vorname_enc, nachname_enc, firmenname_enc,
                email_enc, email_hash,
                telefon_enc, mobil_enc, geburtsdatum_enc,
                uid_nummer_enc, notiz_enc
            ) VALUES (
                :kundennummer, :debitorennummer, :status, :ist_firma, :kundengruppe_id,
                :zahlungsbedingung_id, :standardzahlungsart, :kreditlimit,
                :sprache, :kundenherkunft,
                :vorname_enc, :nachname_enc, :firmenname_enc,
                :email_enc, :email_hash,
                :telefon_enc, :mobil_enc, :geburtsdatum_enc,
                :uid_nummer_enc, :notiz_enc
            )
        ");

        $stmt->execute($this->verschluesseln($data));
        return (int) $this->db->lastInsertId();
    }

    /** Aktualisiert alle Felder eines Kunden. Verschlüsselt personenbezogene Daten. */
    public function update(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE kunden SET
                debitorennummer      = :debitorennummer,
                status               = :status,
                ist_firma            = :ist_firma,
                kundengruppe_id      = :kundengruppe_id,
                zahlungsbedingung_id = :zahlungsbedingung_id,
                standardzahlungsart  = :standardzahlungsart,
                kreditlimit          = :kreditlimit,
                sprache              = :sprache,
                kundenherkunft       = :kundenherkunft,
                vorname_enc          = :vorname_enc,
                nachname_enc         = :nachname_enc,
                firmenname_enc       = :firmenname_enc,
                email_enc            = :email_enc,
                email_hash           = :email_hash,
                telefon_enc          = :telefon_enc,
                mobil_enc            = :mobil_enc,
                geburtsdatum_enc     = :geburtsdatum_enc,
                uid_nummer_enc       = :uid_nummer_enc,
                notiz_enc            = :notiz_enc
            WHERE id = :id
        ");

        $params = $this->verschluesseln($data);
        unset($params['kundennummer']); // unveraenderlich, kein Platzhalter im UPDATE (siehe bug_hersteller_modal_insert Musterfix)
        $params['id'] = $data['id'];
        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    /** Aktualisiert nur den Status eines Kunden (aktiv/gesperrt/geloescht). */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE kunden SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Adressen
    // -------------------------------------------------------------------------

    /**
     * Gibt alle Adressen eines Kunden zurück (entschlüsselt).
     * Sortiert: Standard-Adresse zuerst, dann alphabetisch nach Adresstyp.
     */
    public function findAdressen(int $kundeId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, kunde_id, adresstyp, ist_standard, land,
                   firma_enc, vorname_enc, nachname_enc,
                   strasse_enc, hausnummer_enc, plz_enc, ort_enc, zusatz_enc
            FROM kunden_adressen
            WHERE kunde_id = :kunde_id
            ORDER BY ist_standard DESC, adresstyp ASC
        ");
        $stmt->execute(['kunde_id' => $kundeId]);
        return array_map([$this, 'entschluesselnAdresse'], $stmt->fetchAll());
    }

    /**
     * Legt eine neue Adresse an.
     * Wenn ist_standard = 1: alle anderen Adressen desselben Typs werden auf 0 gesetzt.
     */
    public function insertAdresse(array $data): int
    {
        if (!empty($data['ist_standard'])) {
            // Vor dem Insert: alle anderen Standards für diesen Typ zurücksetzen
            $this->db->prepare("UPDATE kunden_adressen SET ist_standard = 0 WHERE kunde_id = :kid AND adresstyp = :typ")
                ->execute(['kid' => $data['kunde_id'], 'typ' => $data['adresstyp']]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO kunden_adressen (
                kunde_id, adresstyp, ist_standard, land,
                firma_enc, vorname_enc, nachname_enc,
                strasse_enc, hausnummer_enc, plz_enc, ort_enc, zusatz_enc
            ) VALUES (
                :kunde_id, :adresstyp, :ist_standard, :land,
                :firma_enc, :vorname_enc, :nachname_enc,
                :strasse_enc, :hausnummer_enc, :plz_enc, :ort_enc, :zusatz_enc
            )
        ");
        $stmt->execute($this->verschluesselnAdresse($data));
        return (int) $this->db->lastInsertId();
    }

    /**
     * Aktualisiert eine Adresse.
     * Wenn ist_standard = 1: alle anderen Adressen desselben Typs (außer dieser) auf 0 setzen.
     */
    public function updateAdresse(array $data): bool
    {
        if (!empty($data['ist_standard'])) {
            $this->db->prepare("UPDATE kunden_adressen SET ist_standard = 0 WHERE kunde_id = :kid AND adresstyp = :typ AND id != :id")
                ->execute(['kid' => $data['kunde_id'], 'typ' => $data['adresstyp'], 'id' => $data['id']]);
        }

        $stmt = $this->db->prepare("
            UPDATE kunden_adressen SET
                adresstyp    = :adresstyp,
                ist_standard = :ist_standard,
                land         = :land,
                firma_enc    = :firma_enc,
                vorname_enc  = :vorname_enc,
                nachname_enc = :nachname_enc,
                strasse_enc  = :strasse_enc,
                hausnummer_enc = :hausnummer_enc,
                plz_enc      = :plz_enc,
                ort_enc      = :ort_enc,
                zusatz_enc   = :zusatz_enc
            WHERE id = :id
        ");
        $params = $this->verschluesselnAdresse($data);
        $params['id'] = $data['id'];
        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    /** Löscht eine Adresse dauerhaft (keine Soft-Delete bei Adressen). */
    public function deleteAdresse(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM kunden_adressen WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Statistik
    // -------------------------------------------------------------------------

    /**
     * Kennzahlen für die Kundenübersicht: Bestellanzahl, letzte Bestellung,
     * durchschnittlicher Warenkorb, Umsatz gesamt, Anzahl Retouren.
     * Stornierte Aufträge zählen nicht in Bestellanzahl/Umsatz/Warenkorb.
     */
    public function findStatistik(int $kundeId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)          AS anzahl_bestellungen,
                MAX(erstellt_am)  AS letzte_bestellung,
                AVG(bruttobetrag) AS warenkorb_schnitt,
                SUM(bruttobetrag) AS umsatz_gesamt
            FROM auftraege
            WHERE kunden_id = :kid AND lieferstatus != 'storniert'
        ");
        $stmt->execute(['kid' => $kundeId]);
        $stats = $stmt->fetch();

        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT ap.auftrag_id) AS anzahl_retouren
            FROM auftrag_positionen ap
            JOIN auftraege a ON a.id = ap.auftrag_id
            WHERE a.kunden_id = :kid AND ap.menge_retourniert > 0
        ");
        $stmt->execute(['kid' => $kundeId]);
        $stats['anzahl_retouren'] = (int) $stmt->fetchColumn();

        return $stats;
    }

    // -------------------------------------------------------------------------
    // DSGVO Consent
    // -------------------------------------------------------------------------

    /**
     * Gibt alle DSGVO-Consent-Einträge eines Kunden zurück (neueste zuerst).
     * Consent-Typen: "newsletter", "marketing", "datenweitergabe", etc.
     */
    public function findConsent(int $kundeId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, consent_typ, eingewilligt, eingewilligt_am,
                   quelle, widerrufen_am, kommentar
            FROM kunden_dsgvo_consent
            WHERE kunde_id = :kid
            ORDER BY eingewilligt_am DESC
        ");
        $stmt->execute(['kid' => $kundeId]);
        return $stmt->fetchAll();
    }

    /** Legt einen neuen Consent-Eintrag an (append-only, keine Updates). */
    public function insertConsent(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO kunden_dsgvo_consent
                (kunde_id, consent_typ, eingewilligt, quelle, ip_adresse, kommentar)
            VALUES
                (:kunde_id, :consent_typ, :eingewilligt, :quelle, :ip_adresse, :kommentar)
        ");
        $stmt->execute([
            'kunde_id'     => $data['kunde_id'],
            'consent_typ'  => $data['consent_typ'],
            'eingewilligt' => (int) $data['eingewilligt'],
            'quelle'       => $data['quelle'],
            'ip_adresse'   => $data['ip_adresse'] ?? null,
            'kommentar'    => $data['kommentar'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Hilfsfunktionen: Verschlüsseln / Entschlüsseln
    // -------------------------------------------------------------------------

    /**
     * Baut das DB-Insert/Update-Array für Kundendaten.
     * Verschlüsselt alle personenbezogenen Felder via Encryption::encrypt().
     * Erstellt email_hash via Encryption::hash() für die Duplikat-Suche.
     */
    private function verschluesseln(array $data): array
    {
        return [
            'kundennummer'        => $data['kundennummer'],
            'debitorennummer'     => $data['debitorennummer'] ?? null,
            'status'              => $data['status'] ?? 'aktiv',
            'ist_firma'           => (int) ($data['ist_firma'] ?? 0),
            'kundengruppe_id'     => $data['kundengruppe_id'] ?: null,
            'zahlungsbedingung_id'=> $data['zahlungsbedingung_id'] ?: null,
            'standardzahlungsart' => $data['standardzahlungsart'] ?: null,
            'kreditlimit'         => $data['kreditlimit'] ?: null,
            'sprache'             => $data['sprache'] ?? 'de',
            'kundenherkunft'      => $data['kundenherkunft'] ?? 'erp',
            'vorname_enc'         => Encryption::encrypt($data['vorname'] ?? null),
            'nachname_enc'        => Encryption::encrypt($data['nachname'] ?? null),
            'firmenname_enc'      => Encryption::encrypt($data['firmenname'] ?? null),
            'email_enc'           => Encryption::encrypt($data['email'] ?? null),
            'email_hash'          => Encryption::hash($data['email'] ?? null),
            'telefon_enc'         => Encryption::encrypt($data['telefon'] ?? null),
            'mobil_enc'           => Encryption::encrypt($data['mobil'] ?? null),
            'geburtsdatum_enc'    => Encryption::encrypt($data['geburtsdatum'] ?? null),
            'uid_nummer_enc'      => Encryption::encrypt($data['uid_nummer'] ?? null),
            'notiz_enc'           => Encryption::encrypt($data['notiz'] ?? null),
        ];
    }

    /**
     * Entschlüsselt alle _enc-Felder einer Kundenzeile und entfernt sie danach.
     * Views sehen nur Klartexte (vorname, nachname, etc.) — nie die Rohdaten.
     * email_hash wird ebenfalls entfernt (nur für interne Suche gedacht).
     */
    private function entschluesseln(array $row): array
    {
        $row['vorname']    = Encryption::decrypt($row['vorname_enc'] ?? null);
        $row['nachname']   = Encryption::decrypt($row['nachname_enc'] ?? null);
        $row['firmenname'] = Encryption::decrypt($row['firmenname_enc'] ?? null);
        $row['email']      = Encryption::decrypt($row['email_enc'] ?? null);
        $row['telefon']    = Encryption::decrypt($row['telefon_enc'] ?? null);
        $row['mobil']      = Encryption::decrypt($row['mobil_enc'] ?? null);
        $row['geburtsdatum'] = Encryption::decrypt($row['geburtsdatum_enc'] ?? null);
        $row['uid_nummer'] = Encryption::decrypt($row['uid_nummer_enc'] ?? null);
        $row['notiz']      = Encryption::decrypt($row['notiz_enc'] ?? null);

        // _enc-Rohdaten aus dem Array entfernen — Views sehen nur Klartext
        foreach (array_keys($row) as $key) {
            if (str_ends_with($key, '_enc')) unset($row[$key]);
        }
        unset($row['email_hash']);

        return $row;
    }

    /**
     * Baut das DB-Insert/Update-Array für Adressdaten.
     * Verschlüsselt alle Adressfelder.
     */
    private function verschluesselnAdresse(array $data): array
    {
        return [
            'kunde_id'       => $data['kunde_id'],
            'adresstyp'      => $data['adresstyp'] ?? 'haupt',
            'ist_standard'   => (int) ($data['ist_standard'] ?? 0),
            'land'           => $data['land'] ?? 'AT',
            'firma_enc'      => Encryption::encrypt($data['firma'] ?? null),
            'vorname_enc'    => Encryption::encrypt($data['vorname'] ?? null),
            'nachname_enc'   => Encryption::encrypt($data['nachname'] ?? null),
            'strasse_enc'    => Encryption::encrypt($data['strasse'] ?? null),
            'hausnummer_enc' => Encryption::encrypt($data['hausnummer'] ?? null),
            'plz_enc'        => Encryption::encrypt($data['plz'] ?? null),
            'ort_enc'        => Encryption::encrypt($data['ort'] ?? null),
            'zusatz_enc'     => Encryption::encrypt($data['zusatz'] ?? null),
        ];
    }

    /**
     * Entschlüsselt alle _enc-Felder einer Adresszeile und entfernt sie danach.
     */
    private function entschluesselnAdresse(array $row): array
    {
        $row['firma']      = Encryption::decrypt($row['firma_enc'] ?? null);
        $row['vorname']    = Encryption::decrypt($row['vorname_enc'] ?? null);
        $row['nachname']   = Encryption::decrypt($row['nachname_enc'] ?? null);
        $row['strasse']    = Encryption::decrypt($row['strasse_enc'] ?? null);
        $row['hausnummer'] = Encryption::decrypt($row['hausnummer_enc'] ?? null);
        $row['plz']        = Encryption::decrypt($row['plz_enc'] ?? null);
        $row['ort']        = Encryption::decrypt($row['ort_enc'] ?? null);
        $row['zusatz']     = Encryption::decrypt($row['zusatz_enc'] ?? null);

        foreach (array_keys($row) as $key) {
            if (str_ends_with($key, '_enc')) unset($row[$key]);
        }
        return $row;
    }
}
