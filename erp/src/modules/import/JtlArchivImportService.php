<?php

require_once __DIR__ . '/JtlCsvReader.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../kunden/KundenRepository.php';
require_once __DIR__ . '/../kunden/KundenService.php';
require_once __DIR__ . '/../auftraege/AuftragRepository.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';

/**
 * JtlArchivImportService – Kunden+Aufträge aus JTL-Wawi-Exporten als Archiv ins ERP.
 *
 * Zwei getrennte Importe (Kunden zuerst, Aufträge lösen den Kunden über
 * kunden.jtl_kundennummer auf). Beide sind wiederholbar/resume-fähig: ein
 * zweiter Lauf mit demselben oder einem größeren Export überspringt bereits
 * importierte Datensätze (Dedup über jtl_kundennummer bzw. auftrag_nr) statt
 * zu duplizieren — siehe project_jtl_kunden_auftraege_import.md.
 *
 * Fehlende Artikelnummern in Bestellpositionen bekommen einen eigenen,
 * inaktiven Platzhalter-Artikel (artikelnummer = "JTL-<Original>"), granularer
 * als das geteilte 99er-Diverses-Muster der Kasse — AuftragRepository::findPositionen()
 * nutzt einen INNER JOIN auf artikel, eine Position ohne echten Artikel-Datensatz
 * würde in der Detailansicht kommentarlos verschwinden.
 *
 * Zahlung (Jacky 2026-11-19): unbezahlt laut JTL und älter als OFFEN_FIX_TAGE -> gilt fix als bezahlt
 * (JTL/LS-POS-Lücke: an der Kasse bezahlt, im JTL-Auftrag nie nachgetragen). Jüngere unbezahlte
 * kommen als 'ausstehend' und in die Prüfliste (jtl_import_offen, Kunden → JTL-Import).
 *
 * Preise/Steuersätze werden 1:1 aus der CSV eingefroren, nicht aus artikel_preise
 * neu berechnet — analog ShopBestellungSyncService::verarbeiteBestellung().
 * Aus demselben Grund läuft der Auftrags-Insert über AuftragRepository::insertArchiv()
 * statt AuftragService::anlegen() — letzteres setzt zahlungsstatus/lieferstatus/
 * erstellt_am immer hart auf 'ausstehend'/'neu'/jetzt, hier sollen aber die
 * echten historischen JTL-Werte direkt beim Insert stehen.
 */
class JtlArchivImportService
{
    private const PLATZHALTER_ARTIKELTYP_ID   = 6; // artikel_typen: STANDARD
    private const PLATZHALTER_EINHEIT_ID       = 4; // einheiten: Stück
    private const PLATZHALTER_STEUERKLASSE_ID  = 1; // steuerklassen: Normaler Steuersatz 20%
    /** Unbezahlte Archiv-Aufträge älter als so viele Tage gelten beim Import als bezahlt */
    public const OFFEN_FIX_TAGE = 365;

    /**
     * JTL-Klartext-Zahlungsart (kleingeschrieben) -> eigenes zahlungsart-ENUM.
     * Erweitert 2026-08-11 anhand des großen Voll-Exports seit 2013 (deutlich
     * mehr Varianten als im 18-Monats-Export) -- nicht erfasste Werte fallen
     * auf 'rechnung' zurück und werden geloggt, kein Blocker fürs Anlegen.
     */
    private const ZAHLUNGSART_MAP = [
        'paypal checkout'            => 'paypal',
        'paypal basic'               => 'paypal',
        'paypal'                     => 'paypal',
        'rücküberweisung paypal'     => 'paypal',
        'barzahlung'                 => 'bar',
        'bar'                        => 'bar',
        'ec-karte'                    => 'bar',
        'visa'                       => 'bar',
        'eurocard / mastercard'      => 'bar',
        'kreditkarte'                => 'bar',
        'vorkasse-überweisung'       => 'vorkasse',
        'vorkasse überweisung'       => 'vorkasse',
        'überweisung'                => 'vorkasse',
        'sofortüberweisung'          => 'vorkasse',
        'sofortüberweisung.at'       => 'vorkasse',
        'sofortueberweisung'         => 'vorkasse',
        'sofortueberweisung.at'      => 'vorkasse',
        'sofort banking'             => 'vorkasse',
        'sofort überweisung'         => 'vorkasse',
        'rechnung'                   => 'rechnung',
        'nachnahme'                  => 'nachnahme',
        'nachnahme at'               => 'nachnahme',
        'css_gutschein'              => 'gutschein',
        'gutschein'                  => 'gutschein',
        'gutschrift'                 => 'gutschein',
        'gutschrift auf kundenkonto' => 'gutschein',
        'buchung als gutschein'      => 'gutschein',
        'guthaben'                   => 'gutschein',
        'umbuchung'                  => 'gemischt',
        'rücküberweisung kto-kunde'  => 'gemischt',
        'rückbuchung kundenkonto'    => 'gemischt',
    ];

    /** Länderbezeichnungen aus der JTL-CSV, die nicht 1:1 mit laender.name_de matchen. */
    private const LAND_ALIAS = [
        'großbritannien' => 'GB',
        'uk'             => 'GB',
        'england'        => 'GB',
    ];

    private PDO $db;
    private KundenRepository $kundenRepo;
    private KundenService $kundenService;
    private AuftragRepository $auftragRepo;
    private ArtikelRepository $artikelRepo;
    private int $jarvisId;
    private array $laenderCache = [];
    private array $artikelPlatzhalterCache = [];

    public function __construct()
    {
        $this->db            = Database::getInstance();
        $this->kundenRepo    = new KundenRepository();
        $this->kundenService = new KundenService();
        $this->auftragRepo   = new AuftragRepository();
        $this->artikelRepo   = new ArtikelRepository();
        $this->jarvisId      = (int)$this->db
            ->query("SELECT id FROM benutzer WHERE username = 'system'")
            ->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Kunden
    // -------------------------------------------------------------------------

    /**
     * Importiert Kunden aus einem JTL-Kundendaten-Export.
     * Dedup: jtl_kundennummer zuerst, dann E-Mail-Hash (nur nachverknüpft, keine Dublette).
     *
     * @return array{neu:int,uebersprungen:int,verknuepft:int,fehler:string[]}
     */
    public function importiereKunden(string $csvPfad, bool $dryRun): array
    {
        return $this->kundenZeilen(JtlCsvReader::lese($csvPfad), $dryRun);
    }

    /**
     * Verarbeitet eingelesene Zeilen eines Kundendaten-Exports (auch abschnittsweise, JtlArchivLaufService).
     * @return array{neu:int,uebersprungen:int,verknuepft:int,fehler:string[]}
     */
    public function kundenZeilen(array $zeilen, bool $dryRun): array
    {
        $neu = 0;
        $uebersprungen = 0;
        $verknuepft = 0;
        $fehler = [];

        foreach ($zeilen as $row) {
            $jtlNr = trim($row['Kundennummer'] ?? '');
            if ($jtlNr === '') {
                continue;
            }

            if ($this->kundenRepo->findByJtlKundennummer($jtlNr)) {
                $uebersprungen++;
                continue;
            }

            // JTL-Datenqualität: vereinzelt Platzhalter ("-") oder Tippfehler (Leerzeichen/Komma/
            // Semikolon statt @/.) -- eine ungültige E-Mail soll den Kunden-Import nicht blockieren,
            // der Kunde wird dann einfach ohne E-Mail angelegt (Dedup läuft ohnehin über jtl_kundennummer).
            $email = trim($row['E-Mail-Adresse'] ?? '');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Logger::log('jtl_import.email_ungueltig', null, null, [
                    'jtl_kundennummer' => $jtlNr,
                    'email_roh'        => $email,
                ], $this->jarvisId, 'warn');
                $email = '';
            }
            if ($email !== '') {
                $bestehender = $this->kundenRepo->findByEmailHash($email);
                if ($bestehender) {
                    if (!$dryRun) {
                        $this->kundenRepo->addJtlReferenz((int)$bestehender['id'], $jtlNr);
                    }
                    $verknuepft++;
                    continue;
                }
            }

            if ($dryRun) {
                $neu++;
                continue;
            }

            $firmenname = trim($row['Firma'] ?? '');
            $data = [
                'vorname'              => trim($row['Vorname'] ?? ''),
                'nachname'             => trim($row['Nachname'] ?? ''),
                'firmenname'           => $firmenname,
                'ist_firma'            => $firmenname !== '' ? 1 : 0,
                'email'                => $email !== '' ? $email : null,
                'telefon'              => trim($row['Telefon'] ?? ''),
                'mobil'                => trim($row['Mobil'] ?? ''),
                'kundenherkunft'       => 'jtl_archiv',
                'strasse'              => trim($row['Straße und Nr.'] ?? ''),
                'plz'                  => trim($row['PLZ'] ?? ''),
                'ort'                  => trim($row['Ort'] ?? ''),
                'land'                 => $this->laenderCodeAus($row['Land / ISO-Code (2-stellig)'] ?? ''),
                'kundengruppe_id'      => null,
                'zahlungsbedingung_id' => null,
                'standardzahlungsart'  => null,
                'kreditlimit'          => null,
            ];

            $ergebnis = $this->kundenService->anlegen($data, $this->jarvisId);
            if (!$ergebnis['erfolg']) {
                $fehler[] = "$jtlNr: " . implode(', ', $ergebnis['fehler']);
                continue;
            }

            $this->kundenRepo->addJtlReferenz((int)$ergebnis['id'], $jtlNr);
            Logger::log('jtl_import.kunde_anlegen', 'kunden', $ergebnis['id'], [
                'jtl_kundennummer' => $jtlNr,
            ], $this->jarvisId);
            $neu++;
        }

        return ['neu' => $neu, 'uebersprungen' => $uebersprungen, 'verknuepft' => $verknuepft, 'fehler' => $fehler];
    }

    private function laenderCodeAus(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'AT';
        }

        $key = mb_strtolower($name);
        if (isset(self::LAND_ALIAS[$key])) {
            return self::LAND_ALIAS[$key];
        }

        if (empty($this->laenderCache)) {
            $rows = $this->db->query("SELECT iso_code, name_de FROM laender")->fetchAll();
            foreach ($rows as $r) {
                $this->laenderCache[mb_strtolower($r['name_de'])] = $r['iso_code'];
            }
        }
        if (isset($this->laenderCache[$key])) {
            return $this->laenderCache[$key];
        }

        Logger::log('jtl_import.land_unbekannt', null, null, ['name' => $name], $this->jarvisId, 'warn');
        return 'AT';
    }

    // -------------------------------------------------------------------------
    // Aufträge
    // -------------------------------------------------------------------------

    /**
     * Importiert Aufträge aus einem JTL-Auftrags-Export (Positionszeilen, nach
     * Auftragsnummer gruppiert). Kunden müssen bereits per importiereKunden()
     * importiert sein. Dedup: auftrag_nr = die JTL-Auftragsnummer 1:1.
     *
     * @return array{neu:int,uebersprungen:int,fehler:string[]}
     */
    public function importiereAuftraege(string $csvPfad, bool $dryRun): array
    {
        $gruppen = [];
        foreach (JtlCsvReader::lese($csvPfad) as $row) {
            $nr = trim($row['Auftragsnummer'] ?? '');
            if ($nr === '') {
                continue;
            }
            $gruppen[$nr][] = $row;
        }
        return $this->auftragGruppen($gruppen, $dryRun);
    }

    /**
     * Verarbeitet Aufträge (Auftragsnummer => Positionszeilen), auch abschnittsweise.
     * @return array{neu:int,uebersprungen:int,angebote:int,alt_bezahlt:int,offen:int,offen_ids:int[],fehler:string[]}
     */
    public function auftragGruppen(array $gruppen, bool $dryRun): array
    {
        $neu = 0;
        $uebersprungen = 0;
        $angebote = 0;
        $altBezahlt = 0;
        $offenIds = [];
        $offen = 0;
        $fehler = [];
        $grenze = date('Y-m-d H:i:s', strtotime('-' . self::OFFEN_FIX_TAGE . ' days'));

        foreach ($gruppen as $auftragNr => $positionsZeilen) {
            $kopf = $positionsZeilen[0];

            if (($kopf['Ist Angebot'] ?? '') === 'Ja') {
                $angebote++;
                continue; // reines Angebot, keine echte Bestellung
            }

            $vorhanden = $this->db->prepare("SELECT id FROM auftraege WHERE auftrag_nr = :nr");
            $vorhanden->execute(['nr' => $auftragNr]);
            if ($vorhanden->fetchColumn()) {
                $uebersprungen++;
                continue;
            }

            $jtlKundennummer = trim($kopf['Kundennummer'] ?? '');
            $kunde = $jtlKundennummer !== '' ? $this->kundenRepo->findByJtlKundennummer($jtlKundennummer) : false;
            if (!$kunde) {
                $fehler[] = "$auftragNr: Kunde '$jtlKundennummer' nicht gefunden (Kunden-Import zuerst laufen lassen?)";
                continue;
            }

            $positionen = [];
            foreach ($positionsZeilen as $row) {
                $menge = (int)round((float)str_replace(',', '.', $row['Menge'] ?? '0'));
                if ($menge === 0) {
                    continue;
                }

                $artikelId = $this->ermittleOderErstelleArtikelId($row, $dryRun);
                if ($artikelId === null) {
                    continue; // nur im Dry-Run moeglich (kein echter Platzhalter-Insert)
                }

                $positionen[] = [
                    'artikel_id'        => $artikelId,
                    'bezeichnung'       => mb_substr(trim($row['Bezeichnung (Position)'] ?? ''), 0, 255),
                    'menge'             => $menge,
                    'einzelpreis_netto' => (float)str_replace(',', '.', $row['Netto-VK'] ?? '0'),
                    'steuer_prozent'    => (float)str_replace(',', '.', $row['USt. in %'] ?? '20'),
                    'rabatt_prozent'    => (float)str_replace(',', '.', $row['Rabatt (%)'] ?? '0'),
                    'gesamtpreis_netto' => (float)str_replace(',', '.', $row['Netto-VK (gesamt)'] ?? '0'),
                ];
            }

            if (empty($positionen)) {
                $fehler[] = "$auftragNr: keine gültigen Positionen";
                continue;
            }

            $netto = 0.0;
            $steuer = 0.0;
            foreach ($positionen as $p) {
                $netto  += $p['gesamtpreis_netto'];
                $steuer += round($p['gesamtpreis_netto'] * $p['steuer_prozent'] / 100, 2);
            }
            $netto  = round($netto, 2);
            $steuer = round($steuer, 2);
            $brutto = round($netto + $steuer, 2);

            $bezahltAm  = $this->parseDatum($kopf['Datum Zahlungseingang'] ?? '');
            $erstelltAm = $this->parseDatum($kopf['Auftragsdatum'] ?? '') ?? date('Y-m-d H:i:s');

            // Download-Aufträge (0,00/0,01 €) hat JTL bei der Zahlung oft nicht nachgepflegt --
            // Jackys Regel (2026-08-11, nach Sichtprüfung der offenen Archiv-Aufträge): bei
            // Kleinstbeträgen gilt der Auftrag als erledigt, auch ohne Datum Zahlungseingang.
            $istKleinstbetrag = $brutto >= 0 && $brutto <= 0.01;
            if ($istKleinstbetrag && $bezahltAm === null) {
                $bezahltAm = $erstelltAm;
            }
            // Unbezahlt und älter als ein Jahr: gilt als bezahlt (JTL/LS-POS-Lücke), jünger -> Prüfliste
            $notiz = 'JTL-Archiv-Import (ehem. JTL-Auftragsnr. ' . $auftragNr . ')';
            $istOffen = false;
            if ($bezahltAm === null) {
                if ($erstelltAm < $grenze) {
                    $bezahltAm = $erstelltAm;
                    $notiz .= ' · in JTL ohne Zahlungseingang, älter als ' . self::OFFEN_FIX_TAGE . ' Tage → als bezahlt übernommen';
                    $altBezahlt++;
                } else {
                    $istOffen = true;
                    $offen++;
                }
            }

            if ($dryRun) {
                $neu++;
                continue;
            }

            $auftragData = [
                'auftrag_nr'                => $auftragNr,
                'kunden_id'                 => (int)$kunde['id'],
                'kunden_snapshot'           => json_encode($this->baueSnapshot($kopf, 'K_'), JSON_UNESCAPED_UNICODE),
                'lieferadresse_snapshot'    => json_encode($this->baueSnapshot($kopf, 'L_'), JSON_UNESCAPED_UNICODE),
                'rechnungsadresse_snapshot' => json_encode($this->baueSnapshot($kopf, 'K_'), JSON_UNESCAPED_UNICODE),
                'kanal'                     => 'jtl_archiv',
                'zahlungsstatus'            => ($bezahltAm || $istKleinstbetrag) ? 'bezahlt' : 'ausstehend',
                'lieferstatus'              => 'abgeschlossen',
                'zahlungsart'               => $this->mappeZahlungsart((string)($kopf['Zahlungsart Auftrag'] ?? '')),
                'nettobetrag'               => $netto,
                'steuerbetrag'              => $steuer,
                'bruttobetrag'              => $brutto,
                'bezahlt_am'                => $bezahltAm,
                'notiz_intern'              => $notiz,
                'erstellt_am'               => $erstelltAm,
                'erstellt_von'              => $this->jarvisId,
            ];

            $auftragId = $this->auftragRepo->insertArchiv($auftragData);
            foreach ($positionen as $i => $pos) {
                $this->auftragRepo->insertPosition(array_merge($pos, [
                    'auftrag_id'      => $auftragId,
                    'charge'          => null,
                    'ean'             => null,
                    'menge_geliefert' => $pos['menge'], // Archiv = historisch abgeschlossen
                    'konfig_freitext' => null,          // seit dem Konfigurator Pflicht-Platzhalter in insertPosition()
                    'sort_order'      => $i,
                ]));
            }

            if ($istOffen) $offenIds[] = (int) $auftragId;
            Logger::log('jtl_import.auftrag_anlegen', 'auftraege', $auftragId, [
                'auftrag_nr' => $auftragNr,
                'positionen' => count($positionen),
                'brutto'     => $brutto,
            ], $this->jarvisId);
            $neu++;
        }

        return ['neu' => $neu, 'uebersprungen' => $uebersprungen, 'angebote' => $angebote, 'alt_bezahlt' => $altBezahlt,
                'offen' => $offen, 'offen_ids' => $offenIds, 'fehler' => $fehler];
    }

    /**
     * Löst die Artikel-ID für eine Bestellposition auf. Existiert die
     * Artikelnummer im ERP, wird der echte Artikel verwendet. Sonst wird ein
     * eigener, inaktiver Platzhalter-Artikel "JTL-<Original>" gefunden oder
     * neu angelegt (pro distinct Original-Nummer nur einmal). Positionen ohne
     * eigene Artikelnummer (Versandposition, Kupon, ...) nutzen stattdessen
     * die Positionsart als Schlüssel.
     */
    private function ermittleOderErstelleArtikelId(array $row, bool $dryRun): ?int
    {
        $original = trim($row['Artikelnummer'] ?? '');
        if ($original === '') {
            $original = mb_strtoupper(trim($row['Positionsart'] ?? 'POSITION'));
        }

        $treffer = $this->artikelRepo->findByArtikelnummer($original);
        if ($treffer) {
            return (int)$treffer['id'];
        }

        $platzhalterNr = mb_substr('JTL-' . $original, 0, 30);

        if (isset($this->artikelPlatzhalterCache[$platzhalterNr])) {
            return $this->artikelPlatzhalterCache[$platzhalterNr];
        }

        $treffer = $this->artikelRepo->findByArtikelnummer($platzhalterNr);
        if ($treffer) {
            $this->artikelPlatzhalterCache[$platzhalterNr] = (int)$treffer['id'];
            return (int)$treffer['id'];
        }

        if ($dryRun) {
            // Zaehlung soll trotzdem stimmen (kein echter Insert, aber die Position
            // waere im echten Lauf gueltig) -- Fake-ID, nie fuer echte Inserts genutzt.
            $this->artikelPlatzhalterCache[$platzhalterNr] = -1;
            return -1;
        }

        $name = trim($row['Bezeichnung (Position)'] ?? '') ?: $platzhalterNr;
        $id = $this->artikelRepo->insert([
            'hat_eigenen_lagerstand' => 0,
            'artikelnummer'          => $platzhalterNr,
            'hersteller_id'          => null,
            'steuerklasse_id'        => self::PLATZHALTER_STEUERKLASSE_ID,
            'artikeltyp_id'          => self::PLATZHALTER_ARTIKELTYP_ID,
            'name'                   => mb_substr($name, 0, 255),
            'kurzbeschreibung'       => null,
            'beschreibung'           => null,
            'einheit_id'             => self::PLATZHALTER_EINHEIT_ID,
            'inhalt_menge'           => null,
            'inhalt_einheit'         => null,
            'gewicht_artikel'        => null,
            'herkunftsland'          => null,
            'grundpreis_bezugsmenge' => null,
            'grundpreis_anzeigen'    => 0,
            'charge_pflicht'         => 0,
            'aktiv'                  => 0,
        ]);
        $this->artikelPlatzhalterCache[$platzhalterNr] = $id;

        Logger::log('jtl_import.platzhalter_artikel_anlegen', 'artikel', $id, [
            'artikelnummer' => $platzhalterNr,
        ], $this->jarvisId);

        return $id;
    }

    private function baueSnapshot(array $row, string $prefix): array
    {
        return [
            'name'    => trim(($row[$prefix . 'Vorname'] ?? '') . ' ' . ($row[$prefix . 'Nachname'] ?? '')),
            'firma'   => $row[$prefix . 'Firma'] ?? '',
            'strasse' => trim($row[$prefix . 'Straße und Nr.'] ?? ''),
            'plz'     => $row[$prefix . 'PLZ'] ?? '',
            'ort'     => $row[$prefix . 'Ort'] ?? '',
            'land'    => $this->laenderCodeAus($row[$prefix . 'Land'] ?? ''),
            'email'   => $row[$prefix . 'E-Mail-Adresse'] ?? '',
            'telefon' => $row[$prefix . 'Telefon'] ?? '',
        ];
    }

    private function mappeZahlungsart(string $wert): string
    {
        $key = mb_strtolower(trim($wert));
        if (isset(self::ZAHLUNGSART_MAP[$key])) {
            return self::ZAHLUNGSART_MAP[$key];
        }
        if ($wert !== '') {
            Logger::log('jtl_import.zahlungsart_unbekannt', null, null, ['wert' => $wert], $this->jarvisId, 'warn');
        }
        return 'rechnung';
    }

    private function parseDatum(string $wert): ?string
    {
        $wert = trim($wert);
        if ($wert === '') {
            return null;
        }
        $d = DateTime::createFromFormat('d.m.Y', $wert);
        return $d ? $d->format('Y-m-d 00:00:00') : null;
    }
}
