<?php

require_once __DIR__ . '/../../src/modules/kunden/KundenRepository.php';

/**
 * Demo-Teil 2b: erfundene Kunden (verschlüsselt wie im echten Betrieb, über KundenRepository).
 * E-Mails immer @example.com (reservierte Domain, gehört niemandem). Dazu ein paar Händler.
 * Für Teil 3 gemerkt in $b->ids['kunden'] = [id, land, haendler, herkunft].
 */
class DemoKunden
{
    private const VORNAMEN = ['Anna', 'Maria', 'Elisabeth', 'Sabine', 'Petra', 'Monika', 'Claudia', 'Andrea', 'Julia', 'Katharina', 'Lisa',
        'Sarah', 'Lena', 'Hannah', 'Sophie', 'Laura', 'Theresa', 'Barbara', 'Christine', 'Eva', 'Ingrid', 'Helga', 'Renate', 'Ursula',
        'Gabriele', 'Martina', 'Nicole', 'Verena', 'Johanna', 'Magdalena', 'Valentina', 'Emma', 'Marie', 'Klara', 'Rosa', 'Michaela',
        'Thomas', 'Michael', 'Andreas', 'Stefan', 'Markus', 'Peter', 'Martin', 'Josef', 'Florian', 'Lukas'];
    private const NACHNAMEN = ['Gruber', 'Huber', 'Bauer', 'Wagner', 'Müller', 'Pichler', 'Steiner', 'Moser', 'Mayer', 'Hofer', 'Leitner',
        'Berger', 'Fuchs', 'Eder', 'Fischer', 'Schmid', 'Winkler', 'Weber', 'Schwarz', 'Maier', 'Schneider', 'Reiter', 'Mayr', 'Schmidt',
        'Wimmer', 'Egger', 'Brunner', 'Lang', 'Baumgartner', 'Auer', 'Binder', 'Lechner', 'Wolf', 'Wallner', 'Aigner', 'Ebner', 'Koller',
        'Lehner', 'Haas', 'Schuster', 'Holzer', 'Lindner', 'Hoffmann', 'Krenn', 'Kofler'];
    private const STRASSEN = ['Hauptstraße', 'Bahnhofstraße', 'Kirchengasse', 'Lindenweg', 'Gartenstraße', 'Schulgasse', 'Mühlweg', 'Feldgasse',
        'Bergstraße', 'Wiesenweg', 'Rosengasse', 'Am Anger', 'Dorfplatz', 'Sonnenweg', 'Waldstraße', 'Birkenallee'];
    private const ORTE_AT = [['4020', 'Linz'], ['1070', 'Wien'], ['1180', 'Wien'], ['5020', 'Salzburg'], ['8010', 'Graz'], ['6020', 'Innsbruck'],
        ['4600', 'Wels'], ['4400', 'Steyr'], ['3100', 'St. Pölten'], ['9020', 'Klagenfurt'], ['6900', 'Bregenz'], ['2700', 'Wiener Neustadt'],
        ['4810', 'Gmunden'], ['4910', 'Ried im Innkreis'], ['3500', 'Krems'], ['8700', 'Leoben'], ['4040', 'Linz'], ['4060', 'Leonding']];
    private const ORTE_DE = [['80331', 'München'], ['94032', 'Passau'], ['83022', 'Rosenheim'], ['93047', 'Regensburg'], ['86150', 'Augsburg'], ['90402', 'Nürnberg']];

    public function __construct(private DemoBasis $b) {}

    public function erzeugen(int $anzahl = 650): void
    {
        $repo = new KundenRepository();
        for ($i = 0; $i < $anzahl; $i++) {
            $vn = $this->b->eins(self::VORNAMEN);
            $nn = $this->b->eins(self::NACHNAMEN);
            $land = $this->b->chance(12) ? 'DE' : 'AT';
            $herkunft = $this->b->gewichtet(['shop' => 62, 'kasse' => 25, 'messe' => 8, 'empfehlung' => 5]);
            $this->kunde($repo, [
                'vorname' => $vn, 'nachname' => $nn, 'land' => $land, 'herkunft' => $herkunft,
                'email' => $this->mail($vn, $nn, $i), 'telefon' => $this->b->chance(55) ? '+43 6' . mt_rand(50, 99) . ' ' . mt_rand(1000000, 9999999) : null,
            ], false);
        }
        foreach ([['Wollstüberl Muster', 'Ried im Innkreis', '4910'], ['Handarbeitsladen Beispiel KG', 'Graz', '8010'], ['Masche & Faden Muster OG', 'Salzburg', '5020']] as [$firma, $ort, $plz]) {
            $this->kunde($repo, ['firma' => $firma, 'vorname' => $this->b->eins(self::VORNAMEN), 'nachname' => $this->b->eins(self::NACHNAMEN),
                'land' => 'AT', 'ort' => $ort, 'plz' => $plz, 'herkunft' => 'empfehlung',
                'email' => 'einkauf@' . strtolower(preg_replace('/[^a-z]/i', '', $firma)) . '.example.com', 'telefon' => '+43 7' . mt_rand(100, 999) . ' ' . mt_rand(10000, 99999)], true);
        }
        $this->b->log('Kunden: ' . count($this->b->ids['kunden']) . ' (davon 3 Händler)');
    }

    private function kunde(KundenRepository $repo, array $d, bool $haendler): void
    {
        $nr = $repo->nextKundennummer();
        $id = $repo->insert([
            'kundennummer' => $nr, 'debitorennummer' => '2' . str_pad((string)(int)substr($nr, 3), 5, '0', STR_PAD_LEFT),
            'status' => 'aktiv', 'ist_firma' => $haendler ? 1 : 0, 'kundengruppe_id' => $haendler ? 2 : 1,
            'zahlungsbedingung_id' => $haendler ? 3 : null, 'standardzahlungsart' => $haendler ? 'rechnung' : null, 'kreditlimit' => $haendler ? 2000 : null,
            'sprache' => 'de', 'kundenherkunft' => $d['herkunft'],
            'vorname' => $d['vorname'], 'nachname' => $d['nachname'], 'firmenname' => $d['firma'] ?? null,
            'email' => $d['email'], 'telefon' => $d['telefon'] ?? null,
            'uid_nummer' => $haendler ? 'ATU' . mt_rand(10000000, 99999999) : null,
        ]);
        [$plz, $ort] = isset($d['plz']) ? [$d['plz'], $d['ort']] : $this->b->eins($d['land'] === 'DE' ? self::ORTE_DE : self::ORTE_AT);
        $strasse = $this->b->eins(self::STRASSEN);
        $hausnr  = (string)mt_rand(1, 120);
        $repo->insertAdresse([
            'kunde_id' => $id, 'adresstyp' => 'haupt', 'ist_standard' => 1, 'land' => $d['land'],
            'firma' => $d['firma'] ?? null, 'vorname' => $d['vorname'], 'nachname' => $d['nachname'],
            'strasse' => $strasse, 'hausnummer' => $hausnr, 'plz' => $plz, 'ort' => $ort,
        ]);
        $this->b->db->prepare("UPDATE kunden SET erstellt_am = ? WHERE id = ?")->execute([$this->b->vorTagen(mt_rand(30, 900)), $id]);
        $this->b->ids['kunden'][] = ['id' => $id, 'land' => $d['land'], 'haendler' => $haendler, 'herkunft' => $d['herkunft'],
            'name' => $d['firma'] ?? ($d['vorname'] . ' ' . $d['nachname']), 'vorname' => $d['vorname'], 'nachname' => $d['nachname'],
            'firma' => $d['firma'] ?? null, 'email' => $d['email'], 'plz' => $plz, 'ort' => $ort, 'strasse' => "$strasse $hausnr",
            'kundennummer' => $nr];
    }

    private function mail(string $vn, string $nn, int $i): string
    {
        $a = strtolower(strtr($vn, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']));
        $n = strtolower(strtr($nn, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']));
        return $this->b->eins(["$a.$n", "$a$n", substr($a, 0, 1) . ".$n", "$n.$a"]) . ($i > 40 ? $i : '') . '@example.com';
    }
}
