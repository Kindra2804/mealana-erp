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
            (int) $data['artikel_varianten_id'],
            (int) $data['lager_id']
        );

        $bestandNachher = $bestandVorher + (float) $data['menge'];

        // Bestand Upsert
        $this->repo->upsertBestand([
            'artikel_varianten_id' => $data['artikel_varianten_id'],
            'lager_id'             => $data['lager_id'],
            'charge'               => $data['charge'] ?? null,
            'charge_status'        => empty($data['charge']) ? 'unbekannt' : 'erfasst',
            'bestand'              => $bestandNachher,
            'mindestbestand'       => $data['mindestbestand'] ?? 0
        ]);

        // Bewegung protokollieren
        $bewegungId = $this->repo->insertBewegung([
            'artikel_varianten_id' => $data['artikel_varianten_id'],
            'lager_id'             => $data['lager_id'],
            'charge'               => $data['charge'] ?? null,
            'bewegungstyp'         => 'eingang',
            'menge'                => $data['menge'],
            'bestand_vorher'       => $bestandVorher,
            'bestand_nachher'      => $bestandNachher,
            'referenz'             => $data['referenz'] ?? null,
            'notiz'                => $data['notiz'] ?? null
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
        if (empty($data['artikel_varianten_id'])) {
            $fehler[] = 'Variante ist Pflichtfeld';
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
}
