<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/WareneingangRepository.php';
require_once __DIR__ . '/../lager/LagerRepository.php';
require_once __DIR__ . '/../bestellungen/BestellungService.php';
require_once __DIR__ . '/../lager/LagerService.php';
require_once __DIR__ . '/../lieferanten/LieferantenGuthabenRepository.php';

/**
 * WareneingangService – Geschäftslogik für den Wareneingangs-Workflow
 *
 * Koordiniert drei Repositories: WareneingangRepository (Eingang-Records),
 * LagerRepository (Bestandsführung) und BestellungService (Bestellnummer).
 *
 * Buchungsablauf bei bucheMenge():
 * 1. Validierung (position_id, artikel_id, lager_id, menge > 0)
 * 2. Aktuellen Bestand lesen (LagerRepository::getBestand)
 * 3. Lagerbestand erhöhen (LagerRepository::upsertBestand)
 * 4. Lagerbewegung anlegen (LagerRepository::insertBewegung, Typ "eingang")
 * 5. Eingang-Record anlegen (verbindet Bewegung mit Bestellposition)
 * 6. menge_eingegangen der Position erhöhen
 * 7. Bestellungsstatus prüfen und ggf. auf "teilgeliefert" oder "erledigt" setzen
 *
 * EAN-Scan-Workflow: sucheNachEan() findet Artikel und gibt
 * gleichzeitig alle offenen Bestellpositionen zurück.
 *
 * chargeStatus:
 *   Wenn keine Charge angegeben: "nachzutragen" (chargenpflichtige Artikel sichtbar)
 *   Wenn Charge angegeben: "erfasst"
 */
class WareneingangService
{
    private WareneingangRepository $repo;
    private LagerRepository        $lagerRepo;
    private LagerService           $lagerService;

    public function __construct()
    {
        $this->repo      = new WareneingangRepository();
        $this->lagerRepo = new LagerRepository();
        $this->lagerService = new LagerService();
    }

    /** Gibt alle offenen und teilgelieferten Bestellungen zurück. */
    public function getOffene(): array
    {
        return $this->repo->findOffene();
    }

    /** Gibt alle aktiven Lager zurück (für Dropdown beim Einbuchen). */
    public function getAlleLager(): array
    {
        return $this->repo->findAlleLager();
    }

    /** Gibt bestehende Chargen eines Artikels zurück (für Charge-Autocomplete). */
    public function getChargenFuerArtikel(int $artikelId): array
    {
        return $this->repo->findChargenFuerArtikel($artikelId);
    }

    /**
     * Sucht einen Artikel per EAN-Code und gibt seine offenen Bestellpositionen zurück.
     *
     * @return array ['gefunden' => bool, 'artikel' => [...], 'bestellungen' => [...]]
     *               Wenn nicht gefunden: ['gefunden' => false]
     */
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

    /** Gibt alle Positionen einer Bestellung mit Artikel-Details zurück. */
    public function getPositionenMitArtikel(int $bestellungId): array
    {
        return $this->repo->findPositionenMitArtikel($bestellungId);
    }

    /**
     * Bucht eine Menge aus dem Wareneingang in den Lagerbestand.
     *
     * Validiert: position_id, artikel_id, lager_id, menge > 0.
     * Charge ist optional — bei chargenpflichtigen Artikeln ohne Charge
     * wird charge_status = 'nachzutragen' gesetzt (für die Nachtragsliste).
     *
     * Die Bestellnummer (BE-2026-NNNN) wird als referenz in lager_bewegungen gespeichert
     * damit die Bewegung später der Bestellung zugeordnet werden kann.
     *
     * @return array ['erfolg' => true, 'komplett' => bool]
     *               komplett = true wenn die Bestellung nach dieser Buchung vollständig ist.
     */
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
        // charge_status: "nachzutragen" wenn keine Charge — erscheint in der Nachtragsliste
        $chargeStatus = ($charge === null) ? 'nachzutragen' : 'erfasst';
        $benutzerId   = $_SESSION['benutzer']['id'];

        $position = $this->repo->findPosition($positionId);
        if (!$position) {
            return ['erfolg' => false, 'fehler' => ['Position nicht gefunden']];
        }

        $bestellungId   = (int)$position['bestellung_id'];
        $lieferantId    = (int)$position['lieferant_id'];
        // Bestellnummer für lager_bewegungen.referenz generieren
        $bestellnummer  = BestellungService::bestellnummer($bestellungId, $position['bestelldatum']);

        // Bestand vor der Buchung lesen (für Bewegungslog)
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

        $this->lagerService->pruefAuslaufartikelStatus($artikelId, $bestandNachher);

        // Lager-Bewegung mit Bestellnummer als Referenz
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

        // Eingang-Record: verknüpft Lagerbewegung mit Bestellposition
        $this->repo->insertEingang([
            'position_id' => $positionId,
            'bewegung_id' => $bewegungId,
            'menge'       => $menge,
            'charge'      => $charge,
            'lager_id'    => $lagerId,
            'benutzer_id' => $benutzerId,
        ]);

        // Position und Bestellungsstatus aktualisieren
        $this->repo->updatePositionEingegangen($positionId, $menge);

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

    /**
     * Schließt eine Bestellung mit Restmengen ab.
     * Aktion "streichen": alle offenen Positionen werden als gestrichen markiert.
     * Ein optionaler Gutschriftbetrag landet nicht mehr als Freitext-Notiz auf der
     * Bestellung, sondern als echte Zugangsbuchung im Lieferanten-Guthaben-Konto
     * (DROPS-Modell: Vorkasse, Teillieferung — der Rest bleibt als Gutschrift beim
     * Lieferanten stehen und kann bei der nächsten Bestellung verrechnet werden,
     * siehe BestellungService::bucheZahlung()).
     * (Weitere Aktionen wie "warten" werden im Frontend abgefangen — hier nur "streichen".)
     */
    public function abschliessenMitRest(int $bestellungId, string $aktion, ?string $gutschriftNotiz, ?float $gutschriftBetrag): array
    {
        if ($aktion === 'streichen') {
            $this->repo->streicheRestPositionen($bestellungId);
            $this->repo->updateBestellungStatus($bestellungId, 'erledigt');

            if ($gutschriftBetrag) {
                $bestellung = (new BestellungService())->getById($bestellungId);
                $guthabenRepo = new LieferantenGuthabenRepository();
                $guthabenRepo->insertBewegung(
                    (int)$bestellung['lieferant_id'],
                    $gutschriftBetrag,
                    'gutschrift_erhalten',
                    $bestellungId,
                    $gutschriftNotiz,
                    date('Y-m-d'),
                    (int)($_SESSION['benutzer']['id'] ?? 0)
                );
            }

            Logger::log('bestellungen.rest_gestrichen', 'bestellungen', $bestellungId, [
                'gutschrift_betrag' => $gutschriftBetrag,
                'notiz'             => $gutschriftNotiz,
            ]);
        }

        return ['erfolg' => true];
    }

    /** Validiert Pflichtfelder: position_id, artikel_id, lager_id, menge > 0. */
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
