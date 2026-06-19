<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/VariantenRepository.php';

class VariantenService
{
    private VariantenRepository $repo;

    public function __construct()
    {
        $this->repo = new VariantenRepository();
    }

    public function speichereAchsenUndWerte(int $artikelId, array $achsenIds, array $werte): array
    {
        $inUseIds = array_map('intval', $this->repo->findWertIdsInUse($artikelId));

        // In-use Werte aus DB holen – Lookup (achse_id|wert) → id für Duplikat-Check
        $currentWerte  = $this->repo->findWerteByArtikelId($artikelId);
        $inUseWerte    = array_filter($currentWerte, fn($w) => in_array((int)$w['id'], $inUseIds));
        $inUseLookup   = [];
        foreach ($inUseWerte as $w) {
            $inUseLookup[(int)$w['achse_id'] . '|' . $w['wert']] = (int)$w['id'];
        }

        // Achse-IDs mit in-use Werten (können nicht entfernt werden)
        $protectedAchseIds = array_unique(array_map(fn($w) => (int)$w['achse_id'], $inUseWerte));

        // Nicht-in-use Werte löschen (werden aus Submission neu eingefügt)
        $this->repo->deleteWerteExcluding($artikelId, $inUseIds ?: [0]);

        // Achsen: nur entfernen wenn nicht geschützt UND nicht in $achsenIds
        $currentAchsen   = $this->repo->findAchsenByArtikelId($artikelId);
        $currentAchseIds = array_map(fn($a) => (int)$a['achse_id'], $currentAchsen);
        foreach ($currentAchseIds as $cId) {
            if (!in_array($cId, $achsenIds) && !in_array($cId, $protectedAchseIds)) {
                $this->repo->deleteArtikelAchse($artikelId, $cId);
            }
        }

        // Neue Achsen einfügen (nur fehlende)
        $currentAchsen   = $this->repo->findAchsenByArtikelId($artikelId);
        $existingAchseSet = array_flip(array_map(fn($a) => (int)$a['achse_id'], $currentAchsen));
        foreach ($achsenIds as $achseId) {
            if (!isset($existingAchseSet[$achseId])) {
                $this->repo->insertArtikelAchse(['artikel_id' => $artikelId, 'achse_id' => $achseId]);
            }
        }

        // Werte einfügen: nur wenn nicht bereits als in-use vorhanden (Duplikat vermeiden)
        foreach ($werte as $wert) {
            $key = (int)$wert['achse_id'] . '|' . $wert['wert'];
            if (!isset($inUseLookup[$key])) {
                $wert['artikel_id'] = $artikelId;
                $this->repo->insertWert($wert);
            }
        }

        Logger::log('achsenUndWerte.speichern', 'artikel_achsen', $artikelId, [
            'achsen_anzahl' => count($achsenIds),
            'werte_anzahl'  => count($werte),
        ]);

        return ['erfolg' => true];
    }

    public function findAchsenByArtikelId(int $artikelId): array
    {
        if ($artikelId > 0) {
            return $this->repo->findAchsenByArtikelId($artikelId);
        }

        return ['erfolg' => false, 'fehler' => ['ArtikelId kann nicht 0 sein']];
    }

    public function findWerteByArtikelId(int $artikelId): array
    {
        if ($artikelId > 0) {
            return $this->repo->findWerteByArtikelId($artikelId);
        }

        return ['erfolg' => false, 'fehler' => ['ArtikelId kann nicht 0 sein']];
    }

    public function findExistingKombinationen(int $vaterId): array
    {
        return $this->repo->findExistingKombinationen($vaterId);
    }

    public function findWertIdsInUse(int $artikelId): array
    {
        return $this->repo->findWertIdsInUse($artikelId);
    }

    public function erstelleKombinationen(array $vater, bool $hatEigenenLagerstand, array $kombis): array
    {
        $neuErstellteIds = [];

        foreach ($kombis as $kombi) {

            $kind = [
                'artikelnummer'          => $kombi['artikelnummer'],
                'name'                   => $kombi['name'],
                'vaterartikel_id'        => $vater['id'],
                'hat_eigenen_lagerstand' => (int) $hatEigenenLagerstand,
                'hersteller_id'          => $vater['hersteller_id'],
                'steuerklasse_id'        => $vater['steuerklasse_id'],
                'artikeltyp_id'          => $vater['artikeltyp_id'],
                'einheit_id'             => $vater['einheit_id'],
                'kurzbeschreibung'       => $vater['kurzbeschreibung'],
                'beschreibung'           => $vater['beschreibung'],
                'technische_details'     => $vater['technische_details'],
                'beschreibung_intern'    => $vater['beschreibung_intern'],
                'meta_titel'             => $vater['meta_titel'],
                'meta_description'       => $vater['meta_description'],
                'url_slug'               => null,
                'inhalt_menge'           => $vater['inhalt_menge'],
                'inhalt_einheit'         => $vater['inhalt_einheit'],
                'gewicht_artikel'        => $vater['gewicht_artikel'],
                'gewicht_versand'        => $vater['gewicht_versand'],
                'laenge'                 => $vater['laenge'],
                'breite'                 => $vater['breite'],
                'hoehe'                  => $vater['hoehe'],
                'herkunftsland'          => $vater['herkunftsland'],
                'taric_code'             => $vater['taric_code'],
                'grundpreis_bezugsmenge' => $vater['grundpreis_bezugsmenge'],
                'grundpreis_anzeigen'    => $vater['grundpreis_anzeigen'],
                'charge_pflicht'         => $vater['charge_pflicht'],
                'ist_auslaufartikel'     => $vater['ist_auslaufartikel'],
                'ueberverkauf_erlaubt'   => $vater['ueberverkauf_erlaubt'],
                'aktiv'                  => 1,
                'zustand'                => 'neu',
                'zustand_vater_id'       => null,
            ];

            $kindId = $this->repo->insertKindArtikel($kind);
            $neuErstellteIds[] = $kindId;

            foreach (explode(',', $kombi['key']) as $w) {
                $this->repo->insertKombinationWert([
                    'kombination_id' => $kindId,
                    'wert_id'        => (int) $w,
                ]);
            }
        }

        Logger::log('varkombi.erstellen', 'artikel', $vater['id'], ['varKombi_anzahl' => count($kombis)]);

        return ['erfolg' => true, 'anzahl' => count($kombis), 'ids' => $neuErstellteIds];
    }
}
