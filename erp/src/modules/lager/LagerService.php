<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/LagerRepository.php';

class LagerService
{
    private LagerRepository $repo;

    public function __construct()
    {
        $this->repo = new LagerRepository();
    }

    public function wareneingang(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        // Aktuellen Bestand holen
        $bestandVorher = $this->repo->getBestand(
            $data['artikel_varianten_id'] ?? null,
            $data['artikel_id'] ?? null,
            (int) $data['lager_id'],
            $data['charge'] ?? null   // ← neu
        );


        $bestandNachher = $bestandVorher + (float) $data['menge'];

        // Bestand Upsert
        $chargePflicht = $this->repo->getChargePflicht(
            $data['artikel_varianten_id'] ?? null,
            $data['artikel_id'] ?? null
        );

        $this->repo->upsertBestand([
            'artikel_varianten_id' => $data['artikel_varianten_id'] ?? null,
            'artikel_id'           => $data['artikel_id'] ?? null,
            'lager_id'             => $data['lager_id'],
            'charge'               => $data['charge'] ?? null,
            'charge_status'        => !empty($data['charge'])
                ? 'erfasst'
                : ($chargePflicht ? 'nachzutragen' : null),
            'bestand'              => $bestandNachher,
            'mindestbestand'       => $data['mindestbestand'] ?? 0
        ]);

        // Bewegung protokollieren
        $bewegungId = $this->repo->insertBewegung([
            'artikel_varianten_id' => $data['artikel_varianten_id'] ?? null,
            'artikel_id'           => $data['artikel_id'] ?? null,
            'lager_id'             => $data['lager_id'],
            'lieferant_id'         => $data['lieferant_id'] ?? null,
            'ek_preis'             => $data['ek_preis'] ?? null,
            'charge'               => $chargePflicht ? $data['charge'] : null,
            'bewegungstyp'         => 'eingang',
            'menge'                => $data['menge'],
            'bestand_vorher'       => $bestandVorher,
            'bestand_nachher'      => $bestandNachher,
            'referenz'             => $data['referenz'] ?? null,
            'notiz'                => $data['notiz'] ?? null,
            'benutzer_id'          => $data['benutzer_id'] ?? null,
        ]);

        Logger::log('wareneingang.buchen', 'lagerbestand', $bewegungId, [
            'artikel_varianten_id' => $data['artikel_varianten_id'],
            'lager_id'             => $data['lager_id'],
            'menge'                => $data['menge'],
            'bestand_nachher'      => $bestandNachher,
        ]);
        return ['erfolg' => true];
    }

    private function validiere(array $data): array
    {
        $fehler = [];
        if (empty($data['artikel_varianten_id']) && empty($data['artikel_id'])) {
            $fehler[] = 'Artikel oder Variante müssen ausgefüllt sein';
        }
        if (empty($data['lager_id'])) {
            $fehler[] = 'Lager ist Pflichtfeld';
        }
        if (empty($data['menge']) || $data['menge'] <= 0) {
            $fehler[] = 'Menge muss größer als 0 sein';
        }
        return $fehler;
    }

    public function getUebersicht(): array
    {
        return $this->repo->findUebersicht();
    }

    public function chargeNachtragen(int $lagerbestand_id, string $charge, float $menge, ?int $benutzerId = null): array
    {
        if (empty($charge)) {
            return ['erfolg' => false, 'fehler' => 'Charge darf nicht leer sein'];
        }

        if (empty($menge)) {
            return ['erfolg' => false, 'fehler' => 'Charge darf nicht leer sein'];
        }

        if ($menge <= 0) {
            return ['erfolg' => false, 'fehler' => 'gültige Menge eingeben'];
        }

        $lb = $this->repo->findLagerbestandById($lagerbestand_id);
        if (!$lb) {
            return ['erfolg' => false, 'fehler' => 'Lagerbestands-ID nicht gefunden'];
        }
        if ($menge > $lb['bestand']) {
            return ['erfolg' => false, 'fehler' => 'Menge überschreitet Bestand des Artikels '];
        }

        $vorhanden = $this->repo->getBestand($lb['artikel_varianten_id'], $lb['artikel_id'], $lb['lager_id'], $charge);

        $this->repo->upsertBestand([
            'artikel_varianten_id' => $lb['artikel_varianten_id'],
            'artikel_id'           => $lb['artikel_id'],
            'lager_id'             => $lb['lager_id'],
            'charge'               => $charge,
            'charge_status'        => 'erfasst',
            'bestand'              => $vorhanden + $menge,
            'mindestbestand'       => 0
        ]);

        $neuerNullBestand = $lb['bestand'] - $menge;

        if ($neuerNullBestand <= 0) {
            $this->repo->deleteBestand($lb['id']);
        } else {
            $this->repo->updateBestandMenge($lb['id'], $neuerNullBestand);
        }

        $this->repo->insertBewegung([
            'artikel_varianten_id' => $lb['artikel_varianten_id'],
            'artikel_id'           => $lb['artikel_id'],
            'lager_id'             => $lb['lager_id'],
            'charge'               => $charge,
            'bewegungstyp'         => 'korrektur',
            'menge'                => $menge,
            'bestand_vorher'       => $lb['bestand'],
            'bestand_nachher'      => $lb['bestand'] - $menge,
            'referenz'             => null,
            'notiz'                => 'Charge nachgetragen',
            'benutzer_id'          => $benutzerId,
        ]);

        Logger::log('lager.charge_nachtragen', 'lagerbestand', $lagerbestand_id, [
            'charge' => $charge,
        ]);

        return ['erfolg' => true];
    }

    public function getNachzutragendeChargen(): array
    {
        return $this->repo->findNachzutragendeChargen();
    }
}
