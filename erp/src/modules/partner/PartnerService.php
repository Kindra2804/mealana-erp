<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/PartnerRepository.php';

class PartnerService
{
    private PartnerRepository $repo;

    public function __construct()
    {
        $this->repo = new PartnerRepository();
    }

    // -------------------------------------------------------------------------
    // Partner lesen
    // -------------------------------------------------------------------------

    public function getAll(array $filter = []): array
    {
        return $this->repo->findAll($filter);
    }

    public function getById(int $id): array|false
    {
        return $this->repo->findById($id);
    }

    public function getMietfaecher(int $partnerId): array
    {
        return $this->repo->findMietfaecherByPartner($partnerId);
    }

    // -------------------------------------------------------------------------
    // Partner speichern / aktualisieren
    // -------------------------------------------------------------------------

    public function save(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $data = $this->bereinige($data);
        $id   = $this->repo->insert($data);
        return ['erfolg' => true, 'id' => $id];
    }

    public function aktualisiere(array $data): array
    {
        if (empty($data['id'])) {
            return ['erfolg' => false, 'fehler' => ['ID fehlt.']];
        }

        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $data = $this->bereinige($data);
        $this->repo->update($data);
        return ['erfolg' => true];
    }

    public function setAktiv(int $id, int $aktiv): array
    {
        $this->repo->setAktiv($id, $aktiv);
        return ['erfolg' => true];
    }

    // -------------------------------------------------------------------------
    // Mietfächer speichern / aktualisieren
    // -------------------------------------------------------------------------

    public function saveMietfach(array $data): array
    {
        $fehler = $this->validieresMietfach($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $id = $this->repo->insertMietfach($data);
        return ['erfolg' => true, 'id' => $id];
    }

    public function aktualisiereMietfach(array $data): array
    {
        if (empty($data['id'])) {
            return ['erfolg' => false, 'fehler' => ['ID fehlt.']];
        }

        $fehler = $this->validieresMietfach($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $this->repo->updateMietfach($data);
        return ['erfolg' => true];
    }

    // -------------------------------------------------------------------------
    // Validierung
    // -------------------------------------------------------------------------

    private function validiere(array $data): array
    {
        $fehler = [];

        if (empty($data['name'])) {
            $fehler[] = 'Name ist Pflichtfeld.';
        }

        $gueltigeTypen = ['kommission', 'spende', 'beides'];
        if (empty($data['typ']) || !in_array($data['typ'], $gueltigeTypen, true)) {
            $fehler[] = 'Typ muss "kommission", "spende" oder "beides" sein.';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $fehler[] = 'E-Mail-Adresse ist ungültig.';
        }

        if (isset($data['provisions_satz']) && $data['provisions_satz'] !== '') {
            if (!is_numeric($data['provisions_satz']) || (float)$data['provisions_satz'] < 0) {
                $fehler[] = 'Provision muss eine Zahl größer oder gleich 0 sein.';
            }
        }

        return $fehler;
    }

    private function validieresMietfach(array $data): array
    {
        $fehler = [];

        if (empty($data['fach_bezeichnung'])) {
            $fehler[] = 'Fach-Bezeichnung ist Pflichtfeld.';
        }

        if (empty($data['mietbetrag_monatlich'])
            || !is_numeric($data['mietbetrag_monatlich'])
            || (float)$data['mietbetrag_monatlich'] <= 0) {
            $fehler[] = 'Mietbetrag muss eine Zahl größer als 0 sein.';
        }

        if (empty($data['mietbeginn'])) {
            $fehler[] = 'Mietbeginn ist Pflichtfeld.';
        }

        if (!empty($data['mietende']) && !empty($data['mietbeginn'])
            && $data['mietende'] <= $data['mietbeginn']) {
            $fehler[] = 'Mietende muss nach dem Mietbeginn liegen.';
        }

        return $fehler;
    }

    // -------------------------------------------------------------------------
    // Hilfsmethoden
    // -------------------------------------------------------------------------

    private function bereinige(array $data): array
    {
        foreach (['email', 'telefon', 'iban', 'uid_nummer', 'zvr_nummer', 'notiz'] as $feld) {
            if (array_key_exists($feld, $data) && $data[$feld] === '') {
                $data[$feld] = null;
            }
        }

        // Checkbox kommt aus Formular nur wenn angehakt → explizit 0 oder 1
        $data['kleinunternehmer'] = !empty($data['kleinunternehmer']) ? 1 : 0;

        return $data;
    }
}
