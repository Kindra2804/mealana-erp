<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/PartnerRepository.php';

/**
 * PartnerService – Geschäftslogik für Partner
 *
 * Validiert und speichert Partner-Stammdaten.
 * Mietfach-spezifische Logik (Fächer, Verträge) liegt in MietfachService.
 *
 * Verkaufsart (Spalte typ, Klärung Steuerberatung 2026-10-26):
 *   "fremdrechnung" → Verkauf im Namen + auf Rechnung des Partners: nie unser Umsatz,
 *                     keine RKSV, keine Provision, keine Rechnung von uns (nur Aufstellung/Aviso)
 *   "kommission"    → eigener Umsatz; Partner stellt uns Rechnung (EK = netto − Provision)
 *   "spende"        → Durchlauf (z.B. Yarnpride), eigene Kassenlade
 * Ein Mietfach ist bei jeder Verkaufsart zusätzlich möglich (eigene Mietrechnung, nie gegenverrechnet).
 */
class PartnerService
{
    public const VERKAUFSARTEN = [
        'fremdrechnung' => 'Fremdrechnung',
        'kommission'    => 'Kommission',
        'spende'        => 'Spende',
    ];

    /** Steht am Bon im Partner-Abschnitt, wenn der Partner keinen eigenen Text hinterlegt hat */
    public const KU_STANDARDTEXT = 'Umsatzsteuerbefreit – Kleinunternehmer gem. § 6 Abs. 1 Z 27 UStG';

    private PartnerRepository $repo;

    public function __construct()
    {
        $this->repo = new PartnerRepository();
    }

    // -------------------------------------------------------------------------
    // Partner lesen
    // -------------------------------------------------------------------------

    /**
     * Gibt alle Partner zurück, optional gefiltert nach Typ und/oder Aktiv-Status.
     *
     * @param array $filter z.B. ['typ' => 'fremdrechnung', 'aktiv' => 1]
     */
    public function getAll(array $filter = []): array
    {
        return $this->repo->findAll($filter);
    }

    /** Gibt einen Partner anhand ID zurück, oder false wenn nicht gefunden. */
    public function getById(int $id): array|false
    {
        return $this->repo->findById($id);
    }

    // -------------------------------------------------------------------------
    // Partner speichern / aktualisieren
    // -------------------------------------------------------------------------

    /**
     * Legt einen neuen Partner an.
     * Validiert: Name Pflichtfeld, Typ muss gültig sein, E-Mail-Format.
     */
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

    /**
     * Aktualisiert einen bestehenden Partner.
     * Validierung identisch zu save().
     */
    public function aktualisiere(array $data): array
    {
        if (empty($data['id'])) {
            return ['erfolg' => false, 'fehler' => ['ID fehlt.']];
        }

        $alt = $this->repo->findById((int)$data['id']);
        if (!$alt) {
            return ['erfolg' => false, 'fehler' => ['Partner nicht gefunden.']];
        }
        // Felder, die das Formular nicht schickt (z.B. kunde_id aus dem Mietlauf), bleiben erhalten
        $data = array_merge($alt, $data);

        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $data = $this->bereinige($data);
        $this->repo->update($data);
        return ['erfolg' => true];
    }

    /** Setzt den Aktiv-Status eines Partners. */
    public function setAktiv(int $id, int $aktiv): array
    {
        $this->repo->setAktiv($id, $aktiv);
        return ['erfolg' => true];
    }

    // -------------------------------------------------------------------------
    // Validierung
    // -------------------------------------------------------------------------

    /** Validiert Name, Typ und E-Mail-Format. */
    private function validiere(array $data): array
    {
        $fehler = [];

        if (empty($data['name'])) {
            $fehler[] = 'Name ist Pflichtfeld.';
        }

        if (empty($data['typ']) || !array_key_exists($data['typ'], self::VERKAUFSARTEN)) {
            $fehler[] = 'Verkaufsart muss Fremdrechnung, Kommission oder Spende sein.';
        }

        if (!empty($data['land']) && !preg_match('/^[A-Z]{2}$/', $data['land'])) {
            $fehler[] = 'Land ist ungültig.';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $fehler[] = 'E-Mail-Adresse ist ungültig.';
        }

        if (isset($data['provisions_satz']) && $data['provisions_satz'] !== '') {
            $prov = str_replace(',', '.', (string)$data['provisions_satz']);
            if (!is_numeric($prov) || (float)$prov < 0 || (float)$prov > 100) {
                $fehler[] = 'Provision muss zwischen 0 und 100 % liegen.';
            }
        }

        return $fehler;
    }

    // -------------------------------------------------------------------------
    // Hilfsmethoden
    // -------------------------------------------------------------------------

    /**
     * Normalisiert Formular-Daten: leere Strings → null, Checkbox → 0/1.
     * Wichtig damit die DB keine leeren Strings statt NULL enthält.
     */
    private function bereinige(array $data): array
    {
        foreach (['strasse', 'plz', 'ort', 'email', 'telefon', 'iban', 'uid_nummer', 'zvr_nummer', 'ku_text', 'notiz'] as $feld) {
            if (array_key_exists($feld, $data)) {
                $data[$feld] = trim((string)$data[$feld]) === '' ? null : trim((string)$data[$feld]);
            }
        }

        // Checkbox kommt aus Formular nur wenn angehakt → explizit 0 oder 1
        $data['kleinunternehmer'] = !empty($data['kleinunternehmer']) ? 1 : 0;
        if (!$data['kleinunternehmer']) {
            $data['ku_text'] = null;
        }

        // Provision + Kreditor gibt es nur bei Kommission (Fremdrechnung: keine Provision, nur Fachmiete)
        if (($data['typ'] ?? '') !== 'kommission') {
            $data['provisions_satz'] = 0;
            $data['lieferant_id']    = null;
        } else {
            $data['provisions_satz'] = (float)str_replace(',', '.', (string)($data['provisions_satz'] ?? 0));
            $data['lieferant_id']    = !empty($data['lieferant_id']) ? (int)$data['lieferant_id'] : null;
        }
        $data['kunde_id'] = !empty($data['kunde_id']) ? (int)$data['kunde_id'] : null;

        return $data;
    }
}
