<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/WareneingangRepository.php';
require_once __DIR__ . '/../lager/LagerRepository.php';
require_once __DIR__ . '/../bestellungen/BestellungService.php';

class WareneingangService
{
    private WareneingangRepository $repo;
    private LagerRepository        $lagerRepo;

    public function __construct()
    {
        $this->repo      = new WareneingangRepository();
        $this->lagerRepo = new LagerRepository();
    }

    public function getOffene(): array
    {
        return $this->repo->findOffene();
    }

    public function getAlleLager(): array
    {
        return $this->repo->findAlleLager();
    }

    public function getChargenFuerArtikel(int $artikelId): array
    {
        return $this->repo->findChargenFuerArtikel($artikelId);
    }

    public function sucheNachEan(string $ean): array
    {
        $artikel = $this->repo->findArtikelByEan(trim($ean));
        if (!$artikel) {
            return ['gefunden' => false];
        }

        $bestellungen = $this->repo->findBestellungenFuerArtikel((int)$artikel['id']);

        return [
            'gefunden'    => true,
            'artikel'     => $artikel,
            'bestellungen' => $bestellungen,
        ];
    }

    public function getPositionenMitArtikel(int $bestellungId): array
    {
        return $this->repo->findPositionenMitArtikel($bestellungId);
    }

    public function bucheMenge(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $positionId   = (int)$data['position_id'];
        $artikelId    = (int)$data['artikel_id'];
        $lagerId      = (int)$data['lager_id'];
        $menge        = (float)$data['menge'];
        $charge       = !empty($data['charge']) ? trim($data['charge']) : null;
        $chargeStatus = ($charge === null) ? 'nachzutragen' : 'erfasst';
        $benutzerId   = $_SESSION['benutzer']['id'];

        $position = $this->repo->findPosition($positionId);
        if (!$position) {
            return ['erfolg' => false, 'fehler' => ['Position nicht gefunden']];
        }

        $bestellungId   = (int)$position['bestellung_id'];
        $lieferantId    = (int)$position['lieferant_id'];
        $bestellnummer  = BestellungService::bestellnummer($bestellungId, $position['bestelldatum']);

        // Lagerbestand aktualisieren
        $bestandVorher  = $this->lagerRepo->getBestand($artikelId, $lagerId, $charge);
        $bestandNachher = $bestandVorher + $menge;

        $this->lagerRepo->upsertBestand([
            'artikel_id'     => $artikelId,
            'lager_id'       => $lagerId,
            'charge'         => $charge,
            'charge_status'  => $chargeStatus,
            'bestand'        => $bestandNachher,
            'mindestbestand' => 0,
        ]);

        // Lager-Bewegung
        $bewegungId = $this->lagerRepo->insertBewegung([
            'artikel_id'      => $artikelId,
            'lager_id'        => $lagerId,
            'lieferant_id'    => $lieferantId,
            'ek_preis'        => $position['ek_preis'] ?? null,
            'charge'          => $charge,
            'bewegungstyp'    => 'eingang',
            'menge'           => $menge,
            'bestand_vorher'  => $bestandVorher,
            'bestand_nachher' => $bestandNachher,
            'referenz'        => $bestellnummer,
            'notiz'           => null,
            'benutzer_id'     => $benutzerId,
        ]);

        // Eingang-Record
        $this->repo->insertEingang([
            'position_id' => $positionId,
            'bewegung_id' => $bewegungId,
            'menge'       => $menge,
            'charge'      => $charge,
            'lager_id'    => $lagerId,
            'benutzer_id' => $benutzerId,
        ]);

        // Position aktualisieren
        $this->repo->updatePositionEingegangen($positionId, $menge);

        // Bestellungsstatus aktualisieren
        $komplett = $this->repo->pruefeBestellungKomplett($bestellungId);
        $this->repo->updateBestellungStatus($bestellungId, $komplett ? 'erledigt' : 'teilgeliefert');

        Logger::log('wareneingang.eingang', 'bestellungen', $bestellungId, [
            'artikel_id' => $artikelId,
            'menge'      => $menge,
            'charge'     => $charge,
            'lager_id'   => $lagerId,
        ]);

        return ['erfolg' => true, 'komplett' => $komplett];
    }

    public function abschliessenMitRest(int $bestellungId, string $aktion, ?string $gutschriftNotiz, ?float $gutschriftBetrag): array
    {
        if ($aktion === 'streichen') {
            $this->repo->streicheRestPositionen($bestellungId, $gutschriftNotiz, $gutschriftBetrag);
            $this->repo->updateBestellungStatus($bestellungId, 'erledigt');
            Logger::log('bestellungen.rest_gestrichen', 'bestellungen', $bestellungId, [
                'gutschrift_betrag' => $gutschriftBetrag,
                'notiz'             => $gutschriftNotiz,
            ]);
        }

        return ['erfolg' => true];
    }

    private function validiere(array $data): array
    {
        $fehler = [];
        if (empty($data['position_id'])) $fehler[] = 'Position fehlt';
        if (empty($data['artikel_id']))  $fehler[] = 'Artikel fehlt';
        if (empty($data['lager_id']))    $fehler[] = 'Lager fehlt';
        if (empty($data['menge']) || (float)$data['menge'] <= 0) $fehler[] = 'Menge muss größer 0 sein';
        return $fehler;
    }
}
