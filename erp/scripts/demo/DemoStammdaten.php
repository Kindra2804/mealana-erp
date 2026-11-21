<?php

/**
 * Demo-Teil 1: Firma „Garnstube Muster GmbH“ (erfunden), Demo-Benutzer, Lizenz, Verkaufskanäle,
 * Lager, Abholort, Kasse. Danach ist der Einrichtungs-Assistent erledigt.
 */
class DemoStammdaten
{
    public const FIRMA = 'Garnstube Muster GmbH';

    public function __construct(private DemoBasis $b) {}

    public function erzeugen(): void
    {
        $this->firma();
        $this->benutzer();
        $this->lizenz();
        $this->kanaele();
        $this->lagerUndKasse();
        $this->b->log('Stammdaten: Firma, Benutzer, Lizenz, 3 Kanäle, 3 Lager, Kasse');
    }

    private function firma(): void
    {
        $werte = [
            'firmenname'   => self::FIRMA,
            'strasse'      => 'Musterplatz 1',
            'plz'          => '4020',
            'ort'          => 'Linz',
            'land'         => 'Österreich',
            'uid_nummer'   => 'ATU12345678',
            'steuernummer' => '46/123/4567',
            'telefon'      => '+43 732 000000',
            'email'        => 'office@garnstube.example',
            'website'      => 'https://garnstube.example',
            'firma_web'    => 'https://garnstube.example',
            'bank_name'    => 'Musterbank Linz',
            'iban'         => 'AT001234500000000000',
            'bic'          => 'MUSTATXXXXX',
            'besteuerungsart' => 'normal',
            'kleinunternehmer' => '0',
            'mail_aktiv'   => '0',
            'mail_from_name'    => self::FIRMA,
            'mail_from_address' => 'office@garnstube.example',
            'social_instagram'  => 'https://instagram.com/',
            'social_facebook'   => 'https://facebook.com/',
            'beleg_bilder_rechnung' => '1', 'beleg_bilder_lieferschein' => '1', 'beleg_bilder_pickliste' => '1',
            'beleg_bilder_kassenbon_a4' => '1', 'beleg_bilder_mail_auftragsbestaetigung' => '1',
            'preisanzeige_auftrag' => 'beides',
            // Einrichtungs-Assistent als erledigt markieren
            OnboardingService::FLAG['superadmin'] => '1',
            OnboardingService::FLAG['steuer']     => '1',
        ];
        foreach ($werte as $k => $v) $this->b->einstellung($k, $v);
    }

    private function benutzer(): void
    {
        $konf = Demo::konfig();
        $superadmin = (int)$this->b->db->query("SELECT id FROM rollen WHERE name = 'superadmin'")->fetchColumn();
        $this->b->demoBenutzerId = $this->b->ins('benutzer', [
            'username'     => $konf['login'] ?? 'demo',
            'passwort'     => password_hash($konf['passwort'] ?? 'demo', PASSWORD_DEFAULT),
            'vorname'      => 'Demo',
            'nachname'     => 'Benutzer',
            'formularname' => 'Demo Benutzer',
            'email'        => 'demo@garnstube.example',
            'aktiv'        => 1,
        ]);
        $this->b->ins('benutzer_rollen', ['benutzer_id' => $this->b->demoBenutzerId, 'rolle_id' => $superadmin]);

        // Mitarbeiter fürs Zeigen von Benutzerliste/Logbuch — können sich nie anmelden ('!')
        foreach ([['Anna', 'Berger', 'mitarbeiter'], ['Markus', 'Huber', 'kassier'], ['Sabine', 'Leitner', 'lager']] as [$vn, $nn, $rolle]) {
            $rid = (int)$this->b->db->query("SELECT id FROM rollen WHERE name = " . $this->b->db->quote($rolle))->fetchColumn();
            $id = $this->b->ins('benutzer', [
                'username' => strtolower($vn), 'passwort' => '!', 'vorname' => $vn, 'nachname' => $nn,
                'formularname' => "$vn $nn", 'email' => strtolower($vn) . '@garnstube.example', 'aktiv' => 1,
            ]);
            if ($rid) $this->b->ins('benutzer_rollen', ['benutzer_id' => $id, 'rolle_id' => $rid]);
            $this->b->ids['mitarbeiter'][] = $id;
        }
    }

    private function lizenz(): void
    {
        $inhalt = trim(file_get_contents(__DIR__ . '/demo.lic'));
        $erg = Lizenz::einspielen($inhalt, $this->b->demoBenutzerId);
        if (!$erg['erfolg']) throw new RuntimeException('Demo-Lizenz: ' . $erg['fehler']);
    }

    private function kanaele(): void
    {
        // Kanal 1 kommt aus der Grunddatenbank („Hauptkanal“) → Hauptshop ohne Nummern-Kürzel
        $this->b->db->prepare("UPDATE shops SET slug = 'garnstube', name = 'Garnstube Online', nr_kuerzel = '',
                wc_url = 'https://shop.garnstube.example', website_url = 'https://shop.garnstube.example',
                design_farbe = '#3F7CC0', ist_aktiv = 1, mail_from_name = 'Garnstube Online' WHERE id = 1")->execute();
        $this->b->ids['shops'] = [1];
        foreach ([['stoffzimmer', 'Stoffzimmer Online', 'STZ', '#3B6B52'], ['sockenliebe', 'Sockenliebe', 'SOL', '#C8574A']] as [$slug, $name, $kz, $farbe]) {
            $this->b->ids['shops'][] = $this->b->ins('shops', [
                'slug' => $slug, 'name' => $name, 'nr_kuerzel' => $kz, 'sub_marke' => 1,
                'wc_url' => "https://$slug.example", 'website_url' => "https://$slug.example",
                'design_farbe' => $farbe, 'ist_aktiv' => 1, 'mail_from_name' => $name, 'erstellt_am' => $this->b->vorTagen(400),
            ]);
        }
    }

    private function lagerUndKasse(): void
    {
        $abholort = $this->b->ins('abholorte', [
            'name' => 'Garnstube Linz', 'strasse' => 'Musterplatz 1', 'plz' => '4020', 'ort' => 'Linz',
            'telefon' => '+43 732 000000', 'oeffnungszeiten' => "Mo–Fr 9–18 Uhr\nSa 9–13 Uhr", 'ist_standard' => 1,
        ]);
        $this->b->ids['lager']['laden'] = $this->b->ins('lager', [
            'name' => 'Ladengeschäft', 'typ' => 'ladengeschaeft', 'aktiv' => 1, 'lager_beziehung' => 'eigen', 'abholort_id' => $abholort,
        ]);
        $this->b->ids['lager']['zentral'] = $this->b->ins('lager', [
            'name' => 'Zentrallager', 'typ' => 'lager', 'aktiv' => 1, 'lager_beziehung' => 'eigen', 'abholort_id' => $abholort,
        ]);
        $this->b->ids['lager']['messe'] = $this->b->ins('lager', [
            'name' => 'Messestand', 'typ' => 'messe', 'aktiv' => 1, 'lager_beziehung' => 'eigen', 'fuer_offline_kasse_waehlbar' => 1,
        ]);
        $this->b->ids['abholort'] = $abholort;
        $this->b->ids['kasse'] = $this->b->ins('kassen', [
            'name' => 'Ladenkasse', 'kasse_nr' => 'K1', 'lager_id' => $this->b->ids['lager']['laden'],
            'modus' => 'online', 'ausgabe_format' => 'fragen', 'aktiv' => 1, 'bon_logo' => 1, 'erstellt_am' => $this->b->vorTagen(400),
        ]);
    }
}
