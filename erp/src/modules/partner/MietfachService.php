<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/MietfachRepository.php';

class MietfachService
{
    private MietfachRepository $repo;

    public function __construct()
    {
        $this->repo = new MietfachRepository();
    }

    // -------------------------------------------------------------------------
    // Fächer lesen
    // -------------------------------------------------------------------------

    public function getAllMitStatus(): array
    {
        return $this->repo->findAllMitStatus();
    }

    public function getFreie(): array
    {
        return $this->repo->findFreie();
    }

    public function getFaecherByPartner(int $partnerId): array
    {
        return $this->repo->findFaecherByPartner($partnerId);
    }

    public function getVertraege(int $fachId): array
    {
        return $this->repo->findVertraege($fachId);
    }

    // -------------------------------------------------------------------------
    // Fach anlegen / bearbeiten
    // -------------------------------------------------------------------------

    public function saveFach(array $data): array
    {
        $fehler = $this->validieresFach($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $data = $this->bereinigeFach($data);
        $id   = $this->repo->insert($data);
        return ['erfolg' => true, 'id' => $id];
    }

    public function aktualisiereFach(array $data): array
    {
        if (empty($data['id'])) {
            return ['erfolg' => false, 'fehler' => ['ID fehlt.']];
        }

        $fehler = $this->validieresFach($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $data = $this->bereinigeFach($data);
        $this->repo->update($data);
        return ['erfolg' => true];
    }

    // -------------------------------------------------------------------------
    // Mietvertrag starten / beenden
    // -------------------------------------------------------------------------

    public function vertragStarten(array $data): array
    {
        $fehler = [];

        $fachId    = (int)($data['mietfach_id'] ?? 0);
        $partnerId = (int)($data['partner_id']  ?? 0);

        if (!$fachId)    $fehler[] = 'Fach fehlt.';
        if (!$partnerId) $fehler[] = 'Partner fehlt.';

        if (empty($data['mietbetrag_monatlich']) || (float)$data['mietbetrag_monatlich'] <= 0) {
            $fehler[] = 'Mietbetrag muss größer als 0 sein.';
        }

        if (empty($data['mietbeginn'])) {
            $fehler[] = 'Mietbeginn ist Pflichtfeld.';
        }

        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        if ($this->repo->isFachBelegt($fachId)) {
            return ['erfolg' => false, 'fehler' => ['Fach ist bereits belegt.']];
        }

        $id = $this->repo->insertVertrag([
            'mietfach_id'          => $fachId,
            'partner_id'           => $partnerId,
            'mietbetrag_monatlich' => (float)$data['mietbetrag_monatlich'],
            'mwst_satz'            => (float)($data['mwst_satz'] ?? 20.00),
            'mietbeginn'           => $data['mietbeginn'],
            'mietende'             => !empty($data['mietende']) ? $data['mietende'] : null,
            'notiz'                => $data['notiz'] ?: null,
        ]);

        return ['erfolg' => true, 'id' => $id];
    }

    public function vertragBeenden(array $data): array
    {
        $vertragId = (int)($data['vertrag_id'] ?? 0);
        $mietende  = $data['mietende'] ?? date('Y-m-d');

        if (!$vertragId) {
            return ['erfolg' => false, 'fehler' => ['Vertrags-ID fehlt.']];
        }

        $this->repo->vertragBeenden($vertragId, $mietende);
        return ['erfolg' => true];
    }

    // -------------------------------------------------------------------------
    // Validierung / Bereinigung
    // -------------------------------------------------------------------------

    private function validieresFach(array $data): array
    {
        $fehler = [];

        if (empty($data['fach_bezeichnung'])) {
            $fehler[] = 'Bezeichnung ist Pflichtfeld.';
        }

        foreach (['laenge_cm', 'breite_cm', 'hoehe_cm'] as $feld) {
            if (!empty($data[$feld]) && (!is_numeric($data[$feld]) || (float)$data[$feld] < 0)) {
                $fehler[] = ucfirst(str_replace('_cm', ' cm', $feld)) . ' muss eine positive Zahl sein.';
            }
        }

        if (!empty($data['standard_preis']) && (!is_numeric($data['standard_preis']) || (float)$data['standard_preis'] < 0)) {
            $fehler[] = 'Standardpreis muss eine positive Zahl sein.';
        }

        return $fehler;
    }

    private function bereinigeFach(array $data): array
    {
        foreach (['ort_beschreibung', 'notiz'] as $feld) {
            if (array_key_exists($feld, $data) && $data[$feld] === '') {
                $data[$feld] = null;
            }
        }

        foreach (['laenge_cm', 'breite_cm', 'hoehe_cm', 'standard_preis'] as $feld) {
            if (array_key_exists($feld, $data) && $data[$feld] === '') {
                $data[$feld] = null;
            }
        }

        $data['aktiv'] = !empty($data['aktiv']) ? 1 : 0;

        return $data;
    }
}
