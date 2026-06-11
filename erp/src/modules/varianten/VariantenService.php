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

        // 1: repo->deleteArtikelAchsenByArtikelId($artikelId) — alle alten Achsen-Zuweisungen löschen
        $this->repo->deleteArtikelAchsenByArtikelId($artikelId);

        // 2: repo->deleteWerteByArtikelId($artikelId) — alle alten Werte löschen
        $this->repo->deleteWerteByArtikelId($artikelId);

        // 3: Für jede $achseId in $achsenIds → repo->insertArtikelAchse([...])
        foreach ($achsenIds as $achseId) {
            $this->repo->insertArtikelAchse([
                'artikel_id' => $artikelId,
                'achse_id'   => $achseId
            ]);
        }

        // 4: Für jeden Wert in $werte → repo->insertWert([...])
        foreach ($werte as $wert) {
            $wert['artikel_id'] = $artikelId;
            $this->repo->insertWert($wert);
        }

        Logger::log('achsenUndWerte.speichern', 'artikel_achsen', $artikelId, ['achsen_anzahl' => count($achsenIds), 'werte_anzahl' => count($werte)]);

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
}
