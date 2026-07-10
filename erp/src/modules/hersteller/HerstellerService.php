<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/HerstellerRepository.php';

/**
 * HerstellerService – Geschäftslogik für Hersteller
 *
 * Verwaltet GPSR-Status-Berechnung (EU-Produktsicherheitsverordnung 2023/988),
 * Logo-Upload mit GD-Resize auf max. 200×200px, und Validierung.
 *
 * GPSR-Logik:
 *   EU-Land  → Status "eu" (Name + Adresse + E-Mail reicht)
 *   Nicht-EU → Status "reo_ok" wenn REO-Daten vorhanden, sonst "fehlt"
 *   Beispiele: DROPS (NO), Lang Yarns (CH) → nicht EU → REO erforderlich!
 *
 * EU_LAENDER-Konstante: 27 EU-Mitgliedsstaaten als ISO-2-Codes.
 */
class HerstellerService
{
    private HerstellerRepository $repo;

    /** Alle 27 EU-Mitgliedsstaaten als ISO-3166-1-Alpha-2-Codes. */
    private const EU_LAENDER = [
        'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI',
        'FR','GR','HR','HU','IE','IT','LT','LU','LV','MT',
        'NL','PL','PT','RO','SE','SI','SK'
    ];

    public function __construct()
    {
        $this->repo = new HerstellerRepository();
    }

    /** Gibt alle (aktiven) Hersteller zurück. */
    public function findAll(bool $mitInaktiven = false): array
    {
        return $this->repo->findAll($mitInaktiven);
    }

    /** Gibt einen Hersteller anhand ID zurück. Gibt false zurück bei ID <= 0. */
    public function findById(int $id): array|false
    {
        if ($id <= 0) return false;
        return $this->repo->findById($id);
    }

    /** Prüft ob ein ISO-Ländercode zu einem EU-Mitgliedsstaat gehört. */
    public function istEuLand(string $iso): bool
    {
        return in_array(strtoupper($iso), self::EU_LAENDER);
    }

    /**
     * Berechnet den GPSR-Status eines Herstellers.
     *
     * @return string "eu" | "reo_ok" | "fehlt" | "unbekannt"
     */
    public function getGpsrStatus(array $hersteller): string
    {
        $land = strtoupper($hersteller['land'] ?? '');
        if (!$land) return 'unbekannt';
        if ($this->istEuLand($land)) return 'eu';
        // Nicht-EU: REO-Angaben nötig (Responsible Economic Operator = EU-Vertreter)
        return empty($hersteller['reo_name']) ? 'fehlt' : 'reo_ok';
    }

    /** Gibt die EU-Länderliste als JSON-String für das Frontend (JS-Validierung). */
    public function getEuLaenderJson(): string
    {
        return json_encode(self::EU_LAENDER);
    }

    /**
     * Legt einen neuen Hersteller an.
     * Wenn eine Logo-Datei mitgeschickt wurde, wird sie nach dem Insert verarbeitet.
     *
     * @param array      $data  Formular-Daten
     * @param array|null $datei $_FILES['logo'] oder null
     */
    public function save(array $data, ?array $datei = null): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) return ['erfolg' => false, 'fehler' => $fehler];

        $data = $this->bereinige($data);
        $data['logo_pfad'] = null;  // Erst nach dem Insert setzen (ID wird als Dateiname verwendet)
        unset($data['id']);        // Das Modal-Formular schickt immer ein (bei Neuanlage leeres) id-Feld mit —
                                    // insert() hat aber keinen :id-Platzhalter, das würde PDO durcheinanderbringen

        $id = $this->repo->insert($data);

        $uploadCode = $datei ? ($datei['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($uploadCode !== UPLOAD_ERR_NO_FILE) {
            try {
                if ($uploadCode !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(self::uploadFehlerText($uploadCode));
                }
                $this->repo->updateLogo($id, $this->speichereLogo($datei, $id));
            } catch (RuntimeException $e) {
                Logger::log('hersteller.anlegen', 'hersteller', $id, ['name' => $data['name']]);
                return ['erfolg' => true, 'id' => $id, 'fehler' => ['Hersteller gespeichert, aber Logo: ' . $e->getMessage()]];
            }
        }

        Logger::log('hersteller.anlegen', 'hersteller', $id, ['name' => $data['name']]);
        return ['erfolg' => true, 'id' => $id];
    }

    /**
     * Aktualisiert einen Hersteller.
     * Bestehendes Logo bleibt erhalten wenn kein neues hochgeladen wurde.
     */
    public function update(array $data, ?array $datei = null): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) return ['erfolg' => false, 'fehler' => $fehler];

        $data = $this->bereinige($data);

        // Vorhandenen Logo-Pfad aus DB holen und beibehalten
        $aktuell = $this->repo->findById((int)$data['id']);
        $data['logo_pfad'] = $aktuell['logo_pfad'] ?? null;

        $logoFehler = null;
        $uploadCode = $datei ? ($datei['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($uploadCode !== UPLOAD_ERR_NO_FILE) {
            try {
                if ($uploadCode !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(self::uploadFehlerText($uploadCode));
                }
                $data['logo_pfad'] = $this->speichereLogo($datei, (int)$data['id']);
            } catch (RuntimeException $e) {
                $logoFehler = 'Logo nicht übernommen: ' . $e->getMessage() . ' (bisheriges Logo bleibt erhalten)';
            }
        }

        $this->repo->update($data);
        Logger::log('hersteller.bearbeiten', 'hersteller', $data['id'], ['name' => $data['name']]);
        return $logoFehler ? ['erfolg' => true, 'fehler' => [$logoFehler]] : ['erfolg' => true];
    }

    /**
     * Deaktiviert einen Hersteller (Soft-Delete).
     * Gibt Fehler zurück wenn Hersteller nicht gefunden.
     */
    public function delete(int $id): array
    {
        if ($this->repo->findById($id) === false) {
            return ['erfolg' => false, 'fehler' => ['Hersteller nicht gefunden']];
        }
        $this->repo->deactivate($id);
        Logger::log('hersteller.loeschen', 'hersteller', $id);
        return ['erfolg' => true];
    }

    /** Validiert Pflichtfeld (Name) und Eindeutigkeit. */
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

    /** Normalisiert Textfelder (trim, leere Strings → null, Land → uppercase). */
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
        // ISO-Codes immer uppercase speichern (AT, DE, NO, ...)
        if ($data['land']) $data['land'] = strtoupper($data['land']);
        if ($data['reo_land']) $data['reo_land'] = strtoupper($data['reo_land']);
        $data['aktiv'] = isset($data['aktiv']) && $data['aktiv'] ? 1 : 0;
        return $data;
    }

    /** Übersetzt einen PHP-Upload-Fehlercode in eine verständliche Meldung. */
    private static function uploadFehlerText(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei zu groß.',
            UPLOAD_ERR_PARTIAL      => 'Datei wurde nur teilweise hochgeladen (Verbindungsabbruch?).',
            UPLOAD_ERR_NO_TMP_DIR   => 'Server-Konfigurationsfehler: kein temporäres Upload-Verzeichnis (upload_tmp_dir in php.ini prüfen).',
            UPLOAD_ERR_CANT_WRITE   => 'Server konnte die Datei nicht auf die Festplatte schreiben (Schreibrechte prüfen).',
            UPLOAD_ERR_EXTENSION    => 'Upload wurde durch eine PHP-Erweiterung blockiert.',
            default                 => 'Unbekannter Upload-Fehler (Code ' . $code . ').',
        };
    }

    /**
     * Verarbeitet ein Logo-Upload mit PHP-GD: skaliert auf max. 200×200px,
     * speichert als JPEG (90% Qualität) unter public/img/hersteller/{id}.jpg.
     *
     * Unterstützte Formate: JPEG, PNG, GIF, WebP.
     * Wirft RuntimeException mit einer für den Anwender verständlichen Meldung statt
     * eines PHP-Fatal-Errors (fehlende GD-Erweiterung, kaputtes Bild, Schreibrechte) —
     * ein unbehandelter Fatal Error hier bricht die JSON-Antwort komplett ab und zeigt
     * dem Anwender nur "Netzwerkfehler" statt der eigentlichen Ursache.
     */
    private function speichereLogo(array $datei, int $id): string
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('PHP-GD-Erweiterung ist auf diesem Server nicht aktiviert.');
        }

        $erlaubte = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($datei['type'], $erlaubte)) {
            throw new RuntimeException('Nicht unterstütztes Bildformat (' . $datei['type'] . ').');
        }

        $zielOrdner = __DIR__ . '/../../../public/img/hersteller/';
        if (!is_dir($zielOrdner) && !mkdir($zielOrdner, 0755, true) && !is_dir($zielOrdner)) {
            throw new RuntimeException('Zielordner für Logos konnte nicht angelegt werden (Schreibrechte prüfen).');
        }
        if (!is_writable($zielOrdner)) {
            throw new RuntimeException('Zielordner für Logos ist nicht beschreibbar (Schreibrechte prüfen).');
        }

        $bildinfo = @getimagesize($datei['tmp_name']);
        if ($bildinfo === false) {
            throw new RuntimeException('Bilddatei konnte nicht gelesen werden (beschädigt oder kein gültiges Bild).');
        }
        [$breite, $hoehe, $typ] = $bildinfo;

        $src = match($typ) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($datei['tmp_name']),
            IMAGETYPE_PNG  => imagecreatefrompng($datei['tmp_name']),
            IMAGETYPE_GIF  => imagecreatefromgif($datei['tmp_name']),
            IMAGETYPE_WEBP => imagecreatefromwebp($datei['tmp_name']),
            default        => null,
        };
        if (!$src) {
            throw new RuntimeException('Bildformat konnte nicht verarbeitet werden.');
        }

        // Skalierung: max. 200×200, niemals vergrößern (scale <= 1.0)
        $max   = 200;
        $scale = min($max / $breite, $max / $hoehe, 1.0);
        $nB    = max(1, (int)($breite * $scale));
        $nH    = max(1, (int)($hoehe  * $scale));

        $dest = imagecreatetruecolor($nB, $nH);
        imagecopyresampled($dest, $src, 0, 0, 0, 0, $nB, $nH, $breite, $hoehe);

        // Dateiname = Hersteller-ID + .jpg (Überschreibt vorheriges Logo automatisch)
        $dateiname = $id . '.jpg';
        if (!imagejpeg($dest, $zielOrdner . $dateiname, 90)) {
            imagedestroy($src);
            imagedestroy($dest);
            throw new RuntimeException('Logo konnte nicht gespeichert werden (Schreibrechte prüfen).');
        }
        imagedestroy($src);
        imagedestroy($dest);

        return $dateiname;
    }
}
