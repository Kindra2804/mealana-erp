<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/LagerRepository.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';

class LagerService
{
    private LagerRepository $repo;
    private ArtikelRepository $artikelRepo;

    public function __construct()
    {
        $this->repo = new LagerRepository();
        $this->artikelRepo = new ArtikelRepository();
    }

    private function getJarvisId(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM benutzer WHERE username = 'system'");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function pruefAuslaufartikelStatus(int $artikelId, float $neuerBestand): void
    {
        $artikel = $this->artikelRepo->findById($artikelId);
        if (!$artikel || !$artikel['ist_auslaufartikel']) return;

        $sollAktiv = $neuerBestand > 0 ? 1 : 0;
        if ($artikel['aktiv'] == $sollAktiv) return;

        $jarvisId = $this->getJarvisId();

        $this->artikelRepo->setArtikelAktiv($artikelId, $sollAktiv);
        Logger::log('artikel_aktiv.geaendert', 'artikel', $artikelId, [
            'aktiv'        => $sollAktiv,
            'artikelnummer' => $artikel['artikelnummer'],
        ], $jarvisId);

        // Bei Kind-Artikel: Vater-Status prüfen
        if ($artikel['vaterartikel_id']) {
            $nochKinderAktiv = $this->artikelRepo->countAktiveKinder($artikel['vaterartikel_id']);
            $vater = $this->artikelRepo->findById($artikel['vaterartikel_id']);
            $vaterSollAktiv = $nochKinderAktiv > 0 ? 1 : 0;

            if ($vater && $vater['aktiv'] != $vaterSollAktiv) {
                $this->artikelRepo->setArtikelAktiv($artikel['vaterartikel_id'], $vaterSollAktiv);
                Logger::log('artikel_aktiv.geaendert', 'artikel', $artikel['vaterartikel_id'], [
                    'aktiv'        => $vaterSollAktiv,
                    'artikelnummer' => $vater['artikelnummer'],
                ], $jarvisId);
            }
        }
    }

    public function wareneingang(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $artikelId = (int) $data['artikel_id'];

        if (!empty($data['reaktivieren']) && $data['reaktivieren'] == '1') {
            $this->artikelRepo->setAuslaufartikelAktiv($artikelId, 1);
            $this->artikelRepo->setArtikelAktiv($artikelId, 1);
            Logger::log('artikel.reaktiviert', 'artikel', $artikelId, [
                'lager_id' => $data['lager_id'],
                'menge'    => $data['menge'],
            ], $data['benutzer_id'] ?? null);
        }

        $bestandVorher = $this->repo->getBestand(
            $artikelId,
            (int) $data['lager_id'],
            $data['charge'] ?? null
        );

        $bestandNachher = $bestandVorher + (float) $data['menge'];

        $chargePflicht = $this->repo->getChargePflicht($artikelId);

        $this->repo->upsertBestand([
            'artikel_id'    => $artikelId,
            'lager_id'      => $data['lager_id'],
            'charge'        => $data['charge'] ?? null,
            'charge_status' => !empty($data['charge'])
                ? 'erfasst'
                : ($chargePflicht ? 'nachzutragen' : null),
            'bestand'       => $bestandNachher,
            'mindestbestand' => $data['mindestbestand'] ?? 0,
        ]);

        $this->pruefAuslaufartikelStatus($artikelId, $bestandNachher);

        $bewegungId = $this->repo->insertBewegung([
            'artikel_id'    => $artikelId,
            'lager_id'      => $data['lager_id'],
            'lieferant_id'  => $data['lieferant_id'] ?? null,
            'ek_preis'      => $data['ek_preis'] ?? null,
            'charge'        => $chargePflicht ? ($data['charge'] ?? null) : null,
            'bewegungstyp'  => 'eingang',
            'menge'         => $data['menge'],
            'bestand_vorher'  => $bestandVorher,
            'bestand_nachher' => $bestandNachher,
            'referenz'      => $data['referenz'] ?? null,
            'notiz'         => $data['notiz'] ?? null,
            'benutzer_id'   => $data['benutzer_id'] ?? null,
        ]);

        Logger::log('wareneingang.buchen', 'lagerbestand', $bewegungId, [
            'artikel_id'     => $artikelId,
            'lager_id'       => $data['lager_id'],
            'menge'          => $data['menge'],
            'bestand_nachher' => $bestandNachher,
        ]);

        return ['erfolg' => true];
    }

    private function validiere(array $data): array
    {
        $fehler = [];
        if (empty($data['artikel_id'])) {
            $fehler[] = 'Artikel muss ausgewählt sein';
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
        if ($menge <= 0) {
            return ['erfolg' => false, 'fehler' => 'Gültige Menge eingeben'];
        }

        $lb = $this->repo->findLagerbestandById($lagerbestand_id);
        if (!$lb) {
            return ['erfolg' => false, 'fehler' => 'Lagerbestands-ID nicht gefunden'];
        }
        if ($menge > $lb['bestand']) {
            return ['erfolg' => false, 'fehler' => 'Menge überschreitet Bestand des Artikels'];
        }

        $artikelId = (int) $lb['artikel_id'];
        $vorhanden = $this->repo->getBestand($artikelId, $lb['lager_id'], $charge);

        $this->repo->upsertBestand([
            'artikel_id'    => $artikelId,
            'lager_id'      => $lb['lager_id'],
            'charge'        => $charge,
            'charge_status' => 'erfasst',
            'bestand'       => $vorhanden + $menge,
            'mindestbestand' => 0,
        ]);

        $neuerNullBestand = $lb['bestand'] - $menge;
        if ($neuerNullBestand <= 0) {
            $this->repo->deleteBestand($lb['id']);
        } else {
            $this->repo->updateBestandMenge($lb['id'], $neuerNullBestand);
        }

        $this->repo->insertBewegung([
            'artikel_id'      => $artikelId,
            'lager_id'        => $lb['lager_id'],
            'lieferant_id'    => null,
            'ek_preis'        => null,
            'charge'          => $charge,
            'bewegungstyp'    => 'korrektur',
            'menge'           => $menge,
            'bestand_vorher'  => $lb['bestand'],
            'bestand_nachher' => $lb['bestand'] - $menge,
            'referenz'        => null,
            'notiz'           => 'Charge nachgetragen',
            'benutzer_id'     => $benutzerId,
        ]);

        Logger::log('lager.charge_nachtragen', 'lagerbestand', $lagerbestand_id, ['charge' => $charge]);

        return ['erfolg' => true];
    }

    public function getNachzutragendeChargen(): array
    {
        return $this->repo->findNachzutragendeChargen();
    }
}
