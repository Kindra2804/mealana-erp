<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/HerstellerRepository.php';

class HerstellerService
{
    private HerstellerRepository $repo;

    private const EU_LAENDER = [
        'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI',
        'FR','GR','HR','HU','IE','IT','LT','LU','LV','MT',
        'NL','PL','PT','RO','SE','SI','SK'
    ];

    public function __construct()
    {
        $this->repo = new HerstellerRepository();
    }

    public function findAll(bool $mitInaktiven = false): array
    {
        return $this->repo->findAll($mitInaktiven);
    }

    public function findById(int $id): array|false
    {
        if ($id <= 0) return false;
        return $this->repo->findById($id);
    }

    public function istEuLand(string $iso): bool
    {
        return in_array(strtoupper($iso), self::EU_LAENDER);
    }

    public function getGpsrStatus(array $hersteller): string
    {
        $land = strtoupper($hersteller['land'] ?? '');
        if (!$land) return 'unbekannt';
        if ($this->istEuLand($land)) return 'eu';
        return empty($hersteller['reo_name']) ? 'fehlt' : 'reo_ok';
    }

    public function getEuLaenderJson(): string
    {
        return json_encode(self::EU_LAENDER);
    }

    public function save(array $data, ?array $datei = null): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) return ['erfolg' => false, 'fehler' => $fehler];

        $data = $this->bereinige($data);
        $data['logo_pfad'] = null;

        $id = $this->repo->insert($data);

        if ($datei && ($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $logo = $this->speichereLogo($datei, $id);
            if ($logo) $this->repo->updateLogo($id, $logo);
        }

        Logger::log('hersteller.anlegen', 'hersteller', $id, ['name' => $data['name']]);
        return ['erfolg' => true, 'id' => $id];
    }

    public function update(array $data, ?array $datei = null): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) return ['erfolg' => false, 'fehler' => $fehler];

        $data = $this->bereinige($data);

        $aktuell = $this->repo->findById((int)$data['id']);
        $data['logo_pfad'] = $aktuell['logo_pfad'] ?? null;

        if ($datei && ($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $logo = $this->speichereLogo($datei, (int)$data['id']);
            if ($logo) $data['logo_pfad'] = $logo;
        }

        $this->repo->update($data);
        Logger::log('hersteller.bearbeiten', 'hersteller', $data['id'], ['name' => $data['name']]);
        return ['erfolg' => true];
    }

    public function delete(int $id): array
    {
        if ($this->repo->findById($id) === false) {
            return ['erfolg' => false, 'fehler' => ['Hersteller nicht gefunden']];
        }
        $this->repo->deactivate($id);
        Logger::log('hersteller.loeschen', 'hersteller', $id);
        return ['erfolg' => true];
    }

    private function validiere(array $data): array
    {
        $fehler = [];
        if (empty($data['name'])) {
            $fehler[] = 'Name ist Pflichtfeld';
        } elseif ($this->repo->findByName(trim($data['name']), isset($data['id']) ? (int)$data['id'] : null) !== false) {
            $fehler[] = 'Hersteller mit diesem Namen existiert bereits';
        }
        return $fehler;
    }

    private function bereinige(array $data): array
    {
        $textFelder = [
            'name','handelsname','webseite','land','email',
            'strasse','plz','ort',
            'reo_name','reo_strasse','reo_plz','reo_ort','reo_land','reo_email',
            'notizen'
        ];
        foreach ($textFelder as $f) {
            $data[$f] = isset($data[$f]) && $data[$f] !== '' ? trim($data[$f]) : null;
        }
        if ($data['land']) $data['land'] = strtoupper($data['land']);
        if ($data['reo_land']) $data['reo_land'] = strtoupper($data['reo_land']);
        $data['aktiv'] = isset($data['aktiv']) && $data['aktiv'] ? 1 : 0;
        return $data;
    }

    private function speichereLogo(array $datei, int $id): ?string
    {
        $erlaubte = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($datei['type'], $erlaubte)) return null;

        $zielOrdner = __DIR__ . '/../../../public/img/hersteller/';
        if (!is_dir($zielOrdner)) mkdir($zielOrdner, 0755, true);

        [$breite, $hoehe, $typ] = getimagesize($datei['tmp_name']);
        $src = match($typ) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($datei['tmp_name']),
            IMAGETYPE_PNG  => imagecreatefrompng($datei['tmp_name']),
            IMAGETYPE_GIF  => imagecreatefromgif($datei['tmp_name']),
            IMAGETYPE_WEBP => imagecreatefromwebp($datei['tmp_name']),
            default        => null,
        };
        if (!$src) return null;

        $max   = 200;
        $scale = min($max / $breite, $max / $hoehe, 1.0);
        $nB    = max(1, (int)($breite * $scale));
        $nH    = max(1, (int)($hoehe  * $scale));

        $dest = imagecreatetruecolor($nB, $nH);
        imagecopyresampled($dest, $src, 0, 0, 0, 0, $nB, $nH, $breite, $hoehe);

        $dateiname = $id . '.jpg';
        imagejpeg($dest, $zielOrdner . $dateiname, 90);
        imagedestroy($src);
        imagedestroy($dest);

        return $dateiname;
    }
}
