<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/ArtikelBenennung.php';
require_once __DIR__ . '/../hersteller/HerstellerRepository.php';

/**
 * JtlImportListe – Babsis Entscheidungen zur JTL-Übernahme (MeaLana), einmal geladen, für Vorschau UND Import.
 *
 *   import_ausschluss   (Migr. 254)  was gar nicht übernommen wird (Vater/Einzel samt Kindern, oder einzelne Kinder)
 *   import_geprueft     (Migr. 261)  welche Väter/Einzelartikel schon jemand angeschaut hat
 *   import_umbenennung  (Migr. 260)  korrigierte Ausgangsnummer (davor kommt noch das Kürzel)
 *   import_vorgabe      (Migr. 262)  fertige neue Nummer und/oder zugeordneter Hersteller (✎ in der Vorschau)
 *   hersteller.kuerzel / weitere_namen  Kürzel + JTL-Schreibweisen ("DROPS" -> "DROPS Design")
 *
 * benenne() liefert für einen Vater/Einzelartikel die neue Nummer, den Hersteller und die Kinder nach
 * ArtikelBenennung – die Vorschau "JTL: neue Nummern" und der JTL-Import rechnen damit identisch.
 * Alle Nummern hier sind JTL-Nummern OHNE Auslauf-"#".
 */
class JtlImportListe
{
    public const QUELLE = 'jtl';

    private array $aus;
    private array $geprueft;
    private array $umb;
    private array $vorgabe = [];
    private array $herstellerIndex;
    private array $hersteller = [];

    public function __construct()
    {
        $db = Database::getInstance();
        $q  = "'" . self::QUELLE . "'";
        $this->aus      = array_flip(array_map('strval', $db->query("SELECT nummer FROM import_ausschluss WHERE quelle = $q")->fetchAll(PDO::FETCH_COLUMN)));
        $this->geprueft = array_flip(array_map('strval', $db->query("SELECT nummer FROM import_geprueft WHERE quelle = $q")->fetchAll(PDO::FETCH_COLUMN)));
        $this->umb      = $db->query("SELECT nummer_alt, nummer_neu FROM import_umbenennung WHERE quelle = $q")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($db->query("SELECT nummer, nummer_neu, hersteller_id FROM import_vorgabe WHERE quelle = $q") as $v) {
            $this->vorgabe[(string) $v['nummer']] = $v;
        }
        $this->herstellerIndex = (new HerstellerRepository())->namensIndex(false);
        foreach ($db->query("SELECT id, name, kuerzel FROM hersteller") as $h) $this->hersteller[(int) $h['id']] = $h;
    }

    public static function ohneRaute(string $nr): string
    {
        return str_replace('#', '', trim($nr));
    }

    public function istAusgeschlossen(string $nr): bool
    {
        return isset($this->aus[self::ohneRaute($nr)]);
    }

    /** Ausgeschlossen zählt als entschieden (= geprüft). */
    public function istGeprueft(string $nr): bool
    {
        $nr = self::ohneRaute($nr);
        return isset($this->aus[$nr]) || isset($this->geprueft[$nr]);
    }

    /**
     * Neue Nummer + Namen für einen Vater/Einzelartikel samt (schon gefilterten) Kindern.
     *
     * @param string $jtlNr       JTL-Nummer des Vaters/Einzelartikels
     * @param string $name        Vatername (DROPS schon gekürzt)
     * @param string $herstellerJtl Herstellername aus JTL ('' = ohne)
     * @param array  $kinder      je ['jtl_nr', 'name', 'achsen'] (Achsen wie parseVariationskombinationen)
     * @return array ['nr', 'nr_fest', 'hersteller_id', 'hersteller_erp', 'kuerzel', 'hersteller_zugeordnet',
     *                'hinweise' => [...vorab + ArtikelBenennung], 'kinder' => [[jtl_nr, nr, name, art, typ, hinweise]]]
     */
    public function benenne(string $jtlNr, string $name, string $herstellerJtl, array $kinder): array
    {
        $jtlNr = self::ohneRaute($jtlNr);
        $vg    = $this->vorgabe[$jtlNr] ?? null;

        $hId = $herstellerJtl !== '' ? ($this->herstellerIndex[mb_strtolower(trim($herstellerJtl))] ?? null) : null;
        $zugeordnet = $vg && $vg['hersteller_id'] && isset($this->hersteller[(int) $vg['hersteller_id']]);
        if ($zugeordnet) $hId = (int) $vg['hersteller_id'];
        $h = $hId ? ($this->hersteller[$hId] ?? null) : null;

        $nrFest = $vg && $vg['nummer_neu'] !== null && $vg['nummer_neu'] !== '';
        $vorab  = [];
        if ($zugeordnet)              $vorab[] = 'Hersteller zugeordnet';
        elseif ($herstellerJtl === '') $vorab[] = 'ohne Hersteller';
        elseif (!$h)                  $vorab[] = 'Hersteller unbekannt';
        if ($nrFest)                  $vorab[] = 'Nummer vorgegeben';

        $kinderIn = [];
        foreach ($kinder as $k) {
            $kNr = self::ohneRaute((string) $k['jtl_nr']);
            $kinderIn[] = ['jtl_nr' => $kNr, 'nr' => $this->umb[$kNr] ?? $kNr, 'name' => $k['name'], 'achsen' => $k['achsen'] ?? []];
        }

        $r = ArtikelBenennung::rechne([
            'nr'      => $nrFest ? $vg['nummer_neu'] : ($this->umb[$jtlNr] ?? $jtlNr),
            'jtl_nr'  => $jtlNr,
            'name'    => $name,
            'kuerzel' => $h['kuerzel'] ?? null,
            'nr_fest' => $nrFest,
        ], $kinderIn);

        if (!$h || empty($h['kuerzel'])) {
            $r['hinweise'] = array_values(array_diff($r['hinweise'], ['Hersteller ohne Kürzel – Nummern bleiben']));
            if ($h) $vorab[] = 'Hersteller ohne Kürzel';
        }
        foreach ($r['kinder'] as $i => &$k) $k['jtl_nr'] = $kinderIn[$i]['jtl_nr'];
        unset($k);

        return [
            'nr' => $r['nr'], 'nr_fest' => $nrFest,
            'hersteller_id' => $h ? $hId : null, 'hersteller_erp' => $h['name'] ?? null, 'kuerzel' => $h['kuerzel'] ?? null,
            'hersteller_zugeordnet' => $zugeordnet,
            'vorgabe_nr' => $nrFest ? $vg['nummer_neu'] : null,
            'vorgabe_hersteller_id' => $zugeordnet ? (int) $vg['hersteller_id'] : null,
            'hinweise' => array_merge($vorab, $r['hinweise']),
            'kinder'   => $r['kinder'],
        ];
    }
}
