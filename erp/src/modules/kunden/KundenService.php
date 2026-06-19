<?php

require_once __DIR__ . '/KundenRepository.php';

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

    public function getAll(string $suche = '', string $status = ''): array
    {
        return $this->repo->findAll($suche, $status);
    }

    public function getById(int $id): array|false
    {
        return $this->repo->findById($id);
    }

    // -------------------------------------------------------------------------
    // Anlegen
    // -------------------------------------------------------------------------

    public function anlegen(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        // Doppelte E-Mail prüfen (via Hash-Lookup)
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

        return ['erfolg' => true, 'id' => $id, 'kundennummer' => $data['kundennummer']];
    }

    // -------------------------------------------------------------------------
    // Bearbeiten
    // -------------------------------------------------------------------------

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

        // kundennummer darf nicht geändert werden
        $kunde = $this->repo->findById((int)$data['id']);
        if (!$kunde) {
            return ['erfolg' => false, 'fehler' => ['Kunde nicht gefunden']];
        }
        $data['kundennummer'] = $kunde['kundennummer'];

        $this->repo->update($data);
        return ['erfolg' => true];
    }

    public function statusSetzen(int $id, string $status): array
    {
        $erlaubt = ['aktiv', 'gesperrt', 'geloescht'];
        if (!in_array($status, $erlaubt)) {
            return ['erfolg' => false, 'fehler' => ['Ungültiger Status']];
        }
        $this->repo->updateStatus($id, $status);
        return ['erfolg' => true];
    }

    // -------------------------------------------------------------------------
    // Adressen
    // -------------------------------------------------------------------------

    public function getAdressen(int $kundeId): array
    {
        return $this->repo->findAdressen($kundeId);
    }

    public function adresseAnlegen(array $data): array
    {
        $fehler = $this->validiereAdresse($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }
        $id = $this->repo->insertAdresse($data);
        return ['erfolg' => true, 'id' => $id];
    }

    public function adresseAktualisieren(array $data): array
    {
        $fehler = $this->validiereAdresse($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }
        $this->repo->updateAdresse($data);
        return ['erfolg' => true];
    }

    public function adresseLoeschen(int $id): array
    {
        $this->repo->deleteAdresse($id);
        return ['erfolg' => true];
    }

    // -------------------------------------------------------------------------
    // DSGVO
    // -------------------------------------------------------------------------

    public function getConsent(int $kundeId): array
    {
        return $this->repo->findConsent($kundeId);
    }

    public function consentEintragen(array $data): array
    {
        if (empty($data['kunde_id']) || empty($data['consent_typ'])) {
            return ['erfolg' => false, 'fehler' => ['Pflichtfelder fehlen']];
        }
        $id = $this->repo->insertConsent($data);
        return ['erfolg' => true, 'id' => $id];
    }

    // -------------------------------------------------------------------------
    // Validierung
    // -------------------------------------------------------------------------

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
