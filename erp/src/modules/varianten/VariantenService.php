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
        // Schutz: Werte-IDs die von Kind-Artikeln referenziert werden dürfen nicht gelöscht werden
        $inUseIds = $this->repo->findWertIdsInUse($artikelId);
        if (!empty($inUseIds)) {
            return [
                'erfolg' => false,
                'fehler' => ['Achsen und Werte können nicht geändert werden solange Varianten-Kombinationen (Kind-Artikel) vorhanden sind. Bitte zuerst alle Kind-Artikel löschen.']
            ];
        }

        $this->repo->deleteArtikelAchsenByArtikelId($artikelId);
        $this->repo->deleteWerteByArtikelId($artikelId);

        foreach ($achsenIds as $achseId) {
            $this->repo->insertArtikelAchse(['artikel_id' => $artikelId, 'achse_id' => $achseId]);
        }

        // Eltern-Achsen-Werte zuerst einfügen (werden als Unterachsen-Header referenziert)
        $elternAchseIds   = array_unique(array_filter(array_column($werte, 'ist_eltern_achse')));
        $nameToIdMap      = [];  // 'achse_id:wert_text' → neu-eingefügte DB-ID

        foreach ($werte as $wert) {
            if (!empty($wert['ist_eltern_achse'])) {
                $wert['artikel_id'] = $artikelId;
                $newId = $this->repo->insertWert($wert);
                $mapKey = $wert['achse_id'] . ':' . strtolower(trim($wert['wert']));
                $nameToIdMap[$mapKey] = $newId;
            }
        }

        // Kind-Achsen-Werte einfügen (mit aufgelöster bedingungs_wert_id falls nötig)
        foreach ($werte as $wert) {
            if (!empty($wert['ist_eltern_achse'])) continue;

            $wert['artikel_id'] = $artikelId;

            // Wenn bedingungs_wert_name gesetzt → ID aus nameToIdMap auflösen
            if (!empty($wert['bedingungs_wert_name']) && !empty($wert['bedingungs_achse_id'])) {
                $mapKey = $wert['bedingungs_achse_id'] . ':' . strtolower(trim($wert['bedingungs_wert_name']));
                $wert['bedingungs_wert_id'] = $nameToIdMap[$mapKey] ?? null;
            }

            $this->repo->insertWert($wert);
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
        foreach ($kombis as $kombi) {

            $kind = [
                'artikelnummer' => $kombi['artikelnummer'],
                'name' => $kombi['name'],
                'steuerklasse_id' => $vater['steuerklasse_id'],
                'artikeltyp_id' => $vater['artikeltyp_id'],
                'vaterartikel_id' => $vater['id'],
                'hat_eigenen_lagerstand' => (int) $hatEigenenLagerstand,
                'einheit_id' => $vater['einheit_id'],
                'charge_pflicht' => $vater['charge_pflicht']
            ];

            $kindId = $this->repo->insertKindArtikel($kind);

            $wertIds = explode(',', $kombi['key']);

            foreach ($wertIds as $w) {
                $wert = [
                    'kombination_id' => $kindId,
                    'wert_id' => (int) $w
                ];

                $this->repo->insertKombinationWert($wert);
            }
        }

        Logger::log('varkombi.erstellen', 'artikel', $vater['id'], ['varKombi_anzahl' => count($kombis)]);

        return ['erfolg' => true, 'anzahl' => count($kombis)];
    }
}
