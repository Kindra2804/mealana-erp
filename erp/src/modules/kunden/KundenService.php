<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/KundenRepository.php';

/**
 * KundenService – Geschäftslogik für Kunden, Adressen und DSGVO-Consents
 *
 * Validiert Kundendaten und koordiniert Repository-Aufrufe.
 * Kundennummer wird beim Anlegen automatisch generiert (KD-00001, KD-00002, ...).
 *
 * Duplikat-Erkennung: E-Mail-Adressen werden über ihren HMAC-Hash verglichen,
 * ohne zu entschlüsseln. Beim Update wird die eigene ID ausgeschlossen.
 *
 * Adress-Anlegen beim Kunden-Anlegen: Wenn Straße + Ort vorhanden, wird
 * automatisch eine Hauptadresse als Standard angelegt.
 */
class KundenService
{
    private KundenRepository $repo;

    public function __construct()
    {
        $this->repo = new KundenRepository();
    }

    // -------------------------------------------------------------------------
    // Lesen
    // -------------------------------------------------------------------------

    /**
     * Gibt alle Kunden zurück (ohne Laufkunde), optional gefiltert.
     *
     * @param string $suche  Freitext-Suche (in PHP, da Felder verschlüsselt)
     * @param string $status Filtert auf "aktiv" | "gesperrt" | "geloescht"
     */
    public function getAll(string $suche = '', string $status = ''): array
    {
        return $this->repo->findAll($suche, $status);
    }

    /** Gibt einen vollständigen Kundendatensatz zurück (entschlüsselt). */
    public function getById(int $id): array|false
    {
        return $this->repo->findById($id);
    }

    // -------------------------------------------------------------------------
    // Anlegen
    // -------------------------------------------------------------------------

    /**
     * Legt einen neuen Kunden an.
     *
     * Ablauf:
     * 1. Formularvalidierung (Nachname oder Firma, E-Mail-Format, etc.)
     * 2. E-Mail-Duplikat-Check via Hash-Lookup
     * 3. Kundennummer generieren (KD-00001, ...)
     * 4. Kunden anlegen
     * 5. Optional: Adresse direkt mitanlegen wenn Straße + Ort vorhanden
     */
    public function anlegen(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        // Doppelte E-Mail prüfen (via Hash-Lookup ohne Entschlüsseln)
        if (!empty($data['email'])) {
            $existing = $this->repo->findByEmailHash($data['email']);
            if ($existing) {
                return ['erfolg' => false, 'fehler' => ['E-Mail ist bereits einem Kunden zugeordnet (KD: ' . $existing['kundennummer'] . ')']];
            }
        }

        $data['kundennummer'] = $this->repo->nextKundennummer();
        $id = $this->repo->insert($data);

        // Adresse direkt mitanlegen wenn Pflichtfelder vorhanden
        if (!empty($data['strasse']) && !empty($data['ort'])) {
            $this->repo->insertAdresse(array_merge($data, [
                'kunde_id'     => $id,
                'adresstyp'    => 'haupt',
                'ist_standard' => 1,
            ]));
        }

        Logger::log('kunden.anlegen', 'kunden', $id, [
            'kundennummer' => $data['kundennummer'],
            'name'         => trim(($data['vorname'] ?? '') . ' ' . ($data['nachname'] ?? $data['firmenname'] ?? '')),
        ]);

        return ['erfolg' => true, 'id' => $id, 'kundennummer' => $data['kundennummer']];
    }

    // -------------------------------------------------------------------------
    // Bearbeiten
    // -------------------------------------------------------------------------

    /**
     * Aktualisiert einen bestehenden Kunden.
     * Kundennummer ist unveränderlich — wird aus DB geholt und beibehalten.
     * E-Mail-Duplikat-Check schließt den eigenen Kunden aus.
     */
    public function aktualisieren(array $data): array
    {
        if (empty($data['id'])) {
            return ['erfolg' => false, 'fehler' => ['Kunden-ID fehlt']];
        }

        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        // E-Mail-Duplikat prüfen (andere Kunden ausschließen)
        if (!empty($data['email'])) {
            $existing = $this->repo->findByEmailHash($data['email']);
            if ($existing && (int)$existing['id'] !== (int)$data['id']) {
                return ['erfolg' => false, 'fehler' => ['E-Mail ist bereits einem anderen Kunden zugeordnet (KD: ' . $existing['kundennummer'] . ')']];
            }
        }

        // Kundennummer darf nicht vom Formular überschrieben werden
        $kunde = $this->repo->findById((int)$data['id']);
        if (!$kunde) {
            return ['erfolg' => false, 'fehler' => ['Kunde nicht gefunden']];
        }
        $data['kundennummer'] = $kunde['kundennummer'];

        $this->repo->update($data);

        Logger::log('kunden.bearbeiten', 'kunden', (int)$data['id'], [
            'kundennummer' => $kunde['kundennummer'],
        ]);

        return ['erfolg' => true];
    }

    /**
     * Setzt den Status eines Kunden.
     *
     * @param string $status "aktiv" | "gesperrt" | "geloescht"
     */
    public function statusSetzen(int $id, string $status): array
    {
        $erlaubt = ['aktiv', 'gesperrt', 'geloescht'];
        if (!in_array($status, $erlaubt)) {
            return ['erfolg' => false, 'fehler' => ['Ungültiger Status']];
        }
        $this->repo->updateStatus($id, $status);

        Logger::log('kunden.status', 'kunden', $id, ['status' => $status]);

        return ['erfolg' => true];
    }

    // -------------------------------------------------------------------------
    // Adressen
    // -------------------------------------------------------------------------

    /** Gibt alle Adressen eines Kunden zurück (entschlüsselt). */
    public function getAdressen(int $kundeId): array
    {
        return $this->repo->findAdressen($kundeId);
    }

    /** Legt eine neue Adresse an. Validiert Pflichtfelder (Straße, PLZ, Ort). */
    public function adresseAnlegen(array $data): array
    {
        $fehler = $this->validiereAdresse($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }
        $id = $this->repo->insertAdresse($data);

        Logger::log('kunden.adresse_anlegen', 'kunden_adressen', $id, [
            'kunde_id' => $data['kunde_id'],
            'typ'      => $data['adresstyp'] ?? '',
        ]);

        return ['erfolg' => true, 'id' => $id];
    }

    /** Aktualisiert eine Adresse. Validierung identisch zu adresseAnlegen(). */
    public function adresseAktualisieren(array $data): array
    {
        $fehler = $this->validiereAdresse($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }
        $this->repo->updateAdresse($data);

        Logger::log('kunden.adresse_bearbeiten', 'kunden_adressen', (int)($data['id'] ?? 0), [
            'kunde_id' => $data['kunde_id'],
        ]);

        return ['erfolg' => true];
    }

    /** Löscht eine Adresse dauerhaft. */
    public function adresseLoeschen(int $id): array
    {
        $this->repo->deleteAdresse($id);

        Logger::log('kunden.adresse_loeschen', 'kunden_adressen', $id);

        return ['erfolg' => true];
    }

    // -------------------------------------------------------------------------
    // DSGVO
    // -------------------------------------------------------------------------

    /** Gibt alle DSGVO-Consent-Einträge eines Kunden zurück. */
    public function getConsent(int $kundeId): array
    {
        return $this->repo->findConsent($kundeId);
    }

    /** Trägt einen neuen Consent-Eintrag ein (append-only, keine Updates). */
    public function consentEintragen(array $data): array
    {
        if (empty($data['kunde_id']) || empty($data['consent_typ'])) {
            return ['erfolg' => false, 'fehler' => ['Pflichtfelder fehlen']];
        }
        $id = $this->repo->insertConsent($data);

        Logger::log('kunden.consent', 'kunden_dsgvo_consent', $id, [
            'kunde_id'    => $data['kunde_id'],
            'consent_typ' => $data['consent_typ'],
        ]);

        return ['erfolg' => true, 'id' => $id];
    }

    // -------------------------------------------------------------------------
    // Validierung
    // -------------------------------------------------------------------------

    /**
     * Validiert Kundendaten.
     * Entweder Nachname oder Firmenname muss vorhanden sein.
     * E-Mail-Format, Kreditlimit-Zahl und Geburtsdatum-Format werden geprüft.
     */
    private function validiere(array $data): array
    {
        $fehler = [];

        if (empty($data['nachname']) && empty($data['firmenname'])) {
            $fehler[] = 'Nachname oder Firmenname ist Pflichtfeld';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $fehler[] = 'E-Mail-Adresse ist ungültig';
        }

        if (!empty($data['kreditlimit']) && !is_numeric($data['kreditlimit'])) {
            $fehler[] = 'Kreditlimit muss eine Zahl sein';
        }

        if (!empty($data['geburtsdatum'])) {
            $d = DateTime::createFromFormat('Y-m-d', $data['geburtsdatum']);
            if (!$d) $fehler[] = 'Geburtsdatum ungültig (Format: JJJJ-MM-TT)';
        }

        return $fehler;
    }

    /** Validiert Adress-Pflichtfelder: Kunden-ID, Straße, PLZ, Ort. */
    private function validiereAdresse(array $data): array
    {
        $fehler = [];
        if (empty($data['kunde_id']))  $fehler[] = 'Kunden-ID fehlt';
        if (empty($data['strasse']))   $fehler[] = 'Straße ist Pflichtfeld';
        if (empty($data['ort']))       $fehler[] = 'Ort ist Pflichtfeld';
        if (empty($data['plz']))       $fehler[] = 'PLZ ist Pflichtfeld';
        return $fehler;
    }
}
