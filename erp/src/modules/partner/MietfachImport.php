<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../artikel/MietfachExport.php';
require_once __DIR__ . '/../artikel/BilderRepository.php';
require_once __DIR__ . '/../artikel/BildVerarbeitung.php';
require_once __DIR__ . '/PartnerLagerService.php';
require_once __DIR__ . '/PartnerRepository.php';

/**
 * Artikel aus dem NAHTLOS des Mietfach-Inhabers übernehmen (Jacky 2026-11-25).
 * Gegenstück zu artikel/MietfachExport.php. Ablauf: Datei hochladen → vorschau() → uebernehmen().
 *
 * - Ergebnis sind ganz normale, flache Partnerartikel über PartnerLagerService::artikelSpeichern
 *   (XP-Nummer, Partner, Gruppe PARTNER, Fach) – dieselben Prüfungen und Log-Einträge wie von Hand.
 * - Wiedererkennung: partner_artikel_quelle (Partner + Artikelnummer des Partners). Beim ersten Import
 *   werden schon von Hand angelegte Partnerartikel über die EAN gefunden und verknüpft.
 * - Preis: der des Partners gilt. Hat der Betreiber einen eigenen Fachpreis gesetzt (eigener_preis = 1),
 *   bleibt der, außer „Partnerpreis übernehmen“ ist angehakt.
 * - Fach, Aktiv-Status und EK bleiben bei vorhandenen Artikeln unberührt; Bild nur, wenn noch keines da ist.
 * - Bestand: nie – Ware kommt weiter über „Ware übernehmen“.
 */
class MietfachImport
{
    private PDO $db;
    private string $public;

    public function __construct(?PDO $db = null, ?string $publicPfad = null)
    {
        $this->db     = $db ?? Database::getInstance();
        $this->public = rtrim($publicPfad ?? __DIR__ . '/../../../public', '/\\');
    }

    /** Ablage hochgeladener Dateien bis zum Übernehmen */
    public static function ablage(): string
    {
        $d = __DIR__ . '/../../../storage/mietfach_import';
        if (!is_dir($d)) mkdir($d, 0775, true);
        return realpath($d);
    }

    /** Liest und prüft ein Paket. Wirft RuntimeException mit verständlicher Meldung. */
    public function lesen(string $tar): array
    {
        try {
            $phar = new PharData($tar);
            $d = json_decode($phar['daten.json']->getContent(), true);
        } catch (Throwable $e) {
            throw new RuntimeException('Die Datei ist kein NAHTLOS-Mietfach-Export (.tar aus „Für Mietfach exportieren“).');
        }
        if (!is_array($d) || ($d['format'] ?? '') !== MietfachExport::FORMAT) {
            throw new RuntimeException('Die Datei ist kein NAHTLOS-Mietfach-Export.');
        }
        if ((int)($d['version'] ?? 0) > MietfachExport::VERSION) {
            throw new RuntimeException('Die Datei stammt aus einer neueren NAHTLOS-Version – bitte zuerst hier aktualisieren.');
        }
        $artikel = [];
        foreach ((array)($d['artikel'] ?? []) as $a) {
            $nr = trim((string)($a['nummer'] ?? ''));
            if ($nr === '' || trim((string)($a['name'] ?? '')) === '') continue;
            $artikel[$nr] = $a; // doppelte Nummern: letzte gewinnt
        }
        $d['artikel'] = array_values($artikel);
        return $d;
    }

    /**
     * Vergleicht das Paket mit der Partnerware.
     * Jede Zeile: quell_nummer, name, ean, preis, steuersatz, bild, status
     *   (neu|geaendert|gleich|ohne_preis), artikel_id?, artikelnummer?, aenderungen[], eigener_preis, unser_preis, hinweise[]
     */
    public function vorschau(int $partnerId, array $daten): array
    {
        $zeilen = [];
        foreach ($daten['artikel'] as $a) {
            $quell = trim((string)$a['nummer']);
            $ean   = $this->eanSauber($a['ean'] ?? null);
            $preis = isset($a['brutto_vk']) && (float)$a['brutto_vk'] > 0 ? round((float)$a['brutto_vk'], 2) : null;
            $z = [
                'quell_nummer'  => $quell,
                'name'          => mb_substr(trim((string)$a['name']), 0, 255),
                'ean'           => $ean,
                'preis'         => $preis,
                'steuersatz'    => (float)($a['steuersatz'] ?? 20),
                'kurztext'      => trim((string)($a['kurztext'] ?? '')),
                'bild'          => $a['bild'] ?? null,
                'status'        => 'neu',
                'artikel_id'    => null,
                'artikelnummer' => null,
                'aenderungen'   => [],
                'eigener_preis' => false,
                'unser_preis'   => null,
                'hinweise'      => [],
            ];

            $vorh = $this->vorhanden($partnerId, $quell, $ean);
            if ($vorh) {
                $z['artikel_id']    = (int)$vorh['id'];
                $z['artikelnummer'] = $vorh['artikelnummer'];
                $z['eigener_preis'] = (bool)$vorh['eigener_preis'];
                $z['unser_preis']   = $vorh['brutto_vk'] !== null ? round((float)$vorh['brutto_vk'], 2) : null;
                if (!$vorh['verknuepft']) $z['hinweise'][] = 'über die EAN mit vorhandenem Artikel verknüpft';
                if ($vorh['name'] !== $z['name']) $z['aenderungen'][] = 'Name';
                if ($ean && $vorh['ean'] !== $ean) $z['aenderungen'][] = 'EAN';
                if (abs((float)$vorh['steuersatz'] - $z['steuersatz']) > 0.001) $z['aenderungen'][] = 'MwSt';
                if ($preis !== null && $z['unser_preis'] !== $preis) {
                    if ($z['eigener_preis']) {
                        $z['hinweise'][] = 'eigener Fachpreis € ' . number_format($z['unser_preis'], 2, ',', '.') . ' bleibt';
                    } else {
                        $z['aenderungen'][] = 'Preis';
                    }
                }
                if ($vorh['partner_preis'] !== null && $preis !== null && round((float)$vorh['partner_preis'], 2) !== $preis && $z['eigener_preis']) {
                    $z['aenderungen'][] = 'Partnerpreis';
                }
            } elseif ($preis === null) {
                $z['status'] = 'ohne_preis'; // nicht übernehmbar: Partner soll den Preis in seinem ERP eintragen
                $z['hinweise'][] = 'ohne Verkaufspreis – kann nicht übernommen werden (Preis im ERP des Partners eintragen)';
            }

            if ($ean && ($fremd = $this->eanBelegt($ean, $z['artikel_id']))) {
                $z['hinweise'][] = 'EAN ist schon bei ' . $fremd . ' vergeben – wird ohne EAN übernommen';
                $z['ean'] = null;
                $z['aenderungen'] = array_values(array_diff($z['aenderungen'], ['EAN']));
            }
            if ($vorh) $z['status'] = ($z['aenderungen'] || !$vorh['verknuepft']) ? 'geaendert' : 'gleich';
            if (!$this->steuerklasse($z['steuersatz'])) {
                $z['hinweise'][] = 'Steuersatz ' . $z['steuersatz'] . ' % gibt es hier nicht – 20 % verwendet';
            }
            $zeilen[] = $z;
        }
        return $zeilen;
    }

    /**
     * Übernimmt die gewählten Zeilen.
     * $auswahl: Quell-Nummern, die übernommen werden; $preisErzwingen: Quell-Nummern mit eigenem
     * Fachpreis, bei denen trotzdem der Partnerpreis gilt; $fachId: Fach für neue Artikel.
     */
    public function uebernehmen(int $partnerId, string $tar, array $auswahl, array $preisErzwingen, ?int $fachId, string $dateiname): array
    {
        $partner = (new PartnerRepository())->findById($partnerId);
        if (!$partner) return ['erfolg' => false, 'fehler' => ['Partner nicht gefunden.']];
        $daten  = $this->lesen($tar);
        $zeilen = $this->vorschau($partnerId, $daten);
        $auswahl        = array_flip(array_map('strval', $auswahl));
        $preisErzwingen = array_flip(array_map('strval', $preisErzwingen));
        $svc    = new PartnerLagerService();
        $phar   = new PharData($tar);
        $zahl   = ['neu' => 0, 'geaendert' => 0, 'bilder' => 0];
        $fehler = [];
        $nummern = [];

        foreach ($zeilen as $z) {
            if (!in_array($z['status'], ['neu', 'geaendert'], true) || !isset($auswahl[$z['quell_nummer']])) continue;
            $stk = $this->steuerklasse($z['steuersatz']) ?: $this->steuerklasse(20.0);

            $this->db->beginTransaction();
            try {
                if ($z['artikel_id']) {
                    $alt = $this->artikelZeile($z['artikel_id']);
                    $mitPartnerPreis = !$z['eigener_preis'] || isset($preisErzwingen[$z['quell_nummer']]);
                    $preis = ($mitPartnerPreis && $z['preis'] !== null) ? $z['preis'] : (float)$alt['brutto_vk'];
                    $erg = $svc->artikelSpeichern($partnerId, [
                        'id'            => $z['artikel_id'],
                        'nummer_suffix' => substr($alt['artikelnummer'], strlen(PartnerLagerService::nummernPraefix($partnerId))),
                        'name'          => $z['name'],
                        'brutto_vk'     => $preis,
                        'steuerklasse_id' => $stk,
                        'ean'           => $z['ean'] ?? $alt['ean'],
                        'stammplatz_id' => $alt['stammplatz_id'],
                        'aktiv'         => $alt['aktiv'],
                    ]);
                    $eigener = $mitPartnerPreis ? 0 : 1;
                } else {
                    $erg = $svc->artikelSpeichern($partnerId, [
                        'nummer_suffix' => $this->freierSuffix($partnerId, $z['quell_nummer']),
                        'name'          => $z['name'],
                        'brutto_vk'     => $z['preis'],
                        'steuerklasse_id' => $stk,
                        'ean'           => $z['ean'],
                        'stammplatz_id' => $fachId,
                        'aktiv'         => 1,
                    ]);
                    $eigener = 0;
                }
                if (empty($erg['erfolg'])) throw new RuntimeException(implode(' ', (array)($erg['fehler'] ?? ['unbekannter Fehler'])));
                $artikelId = (int)$erg['id'];

                if ($z['kurztext'] !== '') {
                    $this->db->prepare("UPDATE artikel SET kurzbeschreibung = ? WHERE id = ?")->execute([$z['kurztext'], $artikelId]);
                }
                $this->db->prepare("
                    INSERT INTO partner_artikel_quelle (partner_id, quell_nummer, artikel_id, partner_preis, eigener_preis, zuletzt_importiert)
                    VALUES (?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE artikel_id = VALUES(artikel_id), partner_preis = VALUES(partner_preis),
                                            eigener_preis = VALUES(eigener_preis), zuletzt_importiert = NOW()
                ")->execute([$partnerId, $z['quell_nummer'], $artikelId, $z['preis'], $eigener]);
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                $fehler[] = $z['quell_nummer'] . ' (' . $z['name'] . '): ' . $e->getMessage();
                continue;
            }

            if ($z['bild'] && $this->bildUebernehmen($phar, (string)$z['bild'], $artikelId, $z['name'])) $zahl['bilder']++;
            $zahl[$z['artikel_id'] ? 'geaendert' : 'neu']++;
            $nummern[] = $erg['artikelnummer'];
        }

        Logger::log('partner.mietfach_import', 'partner', $partnerId, [
            'datei' => $dateiname, 'absender' => $daten['absender'] ?? null, 'erstellt_am' => $daten['erstellt_am'] ?? null,
            'neu' => $zahl['neu'], 'geaendert' => $zahl['geaendert'], 'bilder' => $zahl['bilder'],
            'fehler' => count($fehler), 'nummern' => array_slice($nummern, 0, 100),
        ], null, $fehler ? 'warn' : 'info');
        return ['erfolg' => true] + $zahl + ['fehler' => $fehler];
    }

    /**
     * Nach einer Preisänderung von Hand auf der Partnerseite: eigener Fachpreis ja/nein merken.
     * (Aufruf aus PartnerLagerService::artikelSpeichern, nur für verknüpfte Artikel.)
     */
    public static function preisVonHand(PDO $db, int $artikelId, float $brutto): void
    {
        $db->prepare("
            UPDATE partner_artikel_quelle
            SET eigener_preis = (partner_preis IS NULL OR ROUND(partner_preis, 2) <> ROUND(?, 2))
            WHERE artikel_id = ?
        ")->execute([$brutto, $artikelId]);
    }

    // ── Helfer ────────────────────────────────────────────────────────────────

    /** Vorhandener Partnerartikel: erst über die Verknüpfung, sonst über die EAN */
    private function vorhanden(int $partnerId, string $quell, ?string $ean): array|false
    {
        $sel = "
            SELECT a.id, a.artikelnummer, a.name, s.satz AS steuersatz,
                   (SELECT code FROM artikel_codes WHERE artikel_id = a.id AND typ = 'GTIN13' LIMIT 1) AS ean,
                   (SELECT ap.brutto_vk FROM artikel_preise ap JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                    WHERE ap.artikel_id = a.id LIMIT 1) AS brutto_vk,
                   q.partner_preis, COALESCE(q.eigener_preis, 0) AS eigener_preis, (q.id IS NOT NULL) AS verknuepft
            FROM artikel a
            LEFT JOIN steuerklassen s ON s.id = a.steuerklasse_id
            LEFT JOIN partner_artikel_quelle q ON q.artikel_id = a.id
        ";
        $stmt = $this->db->prepare("$sel WHERE q.partner_id = ? AND q.quell_nummer = ?");
        $stmt->execute([$partnerId, $quell]);
        if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) return $r;
        if (!$ean) return false;
        $stmt = $this->db->prepare("$sel JOIN artikel_codes c ON c.artikel_id = a.id AND c.typ = 'GTIN13' AND c.code = ?
                                     WHERE a.partner_id = ? AND q.id IS NULL LIMIT 1");
        $stmt->execute([$ean, $partnerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function artikelZeile(int $id): array
    {
        $stmt = $this->db->prepare("
            SELECT a.artikelnummer, a.stammplatz_id, a.aktiv,
                   (SELECT code FROM artikel_codes WHERE artikel_id = a.id AND typ = 'GTIN13' LIMIT 1) AS ean,
                   (SELECT ap.brutto_vk FROM artikel_preise ap JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                    WHERE ap.artikel_id = a.id LIMIT 1) AS brutto_vk
            FROM artikel a WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Artikelnummer bei einem anderen Artikel? → dessen Nummer, sonst null */
    private function eanBelegt(string $ean, ?int $ausser): ?string
    {
        $stmt = $this->db->prepare("
            SELECT a.artikelnummer FROM artikel_codes c JOIN artikel a ON a.id = c.artikel_id
            WHERE c.typ = 'GTIN13' AND c.code = ? AND a.id <> ? LIMIT 1
        ");
        $stmt->execute([$ean, (int)$ausser]);
        return $stmt->fetchColumn() ?: null;
    }

    private function eanSauber($ean): ?string
    {
        $ean = preg_replace('/\D/', '', (string)$ean);
        return strlen($ean) >= 8 && strlen($ean) <= 14 ? $ean : null;
    }

    private function steuerklasse(float $satz): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM steuerklassen WHERE ABS(satz - ?) < 0.001 ORDER BY aktiv DESC, id LIMIT 1");
        $stmt->execute([$satz]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    /** Nummer des Partners als XP-Endung, wenn gültig und frei (max. 30 Zeichen gesamt); sonst leer = laufende Nummer */
    private function freierSuffix(int $partnerId, string $quell): string
    {
        $praefix = PartnerLagerService::nummernPraefix($partnerId);
        $s = strtoupper(preg_replace('/[^A-Za-z0-9\-_.]+/', '-', $quell));
        $s = trim($s, '-');
        if ($s === '' || !preg_match('/^[A-Z0-9]/', $s) || strlen($praefix . $s) > 30) return '';
        $stmt = $this->db->prepare("SELECT 1 FROM artikel WHERE artikelnummer = ?");
        $stmt->execute([$praefix . $s]);
        return $stmt->fetchColumn() ? '' : $s;
    }

    /** Bild aus dem Paket, nur wenn der Artikel noch keines hat */
    private function bildUebernehmen(PharData $phar, string $pfad, int $artikelId, string $alt): bool
    {
        if (!preg_match('#^bilder/\d+\.jpg$#', $pfad) || !isset($phar[$pfad])) return false;
        $hat = $this->db->prepare("SELECT 1 FROM artikel_bilder WHERE artikel_id = ? LIMIT 1");
        $hat->execute([$artikelId]);
        if ($hat->fetchColumn()) return false;

        $tmp = tempnam(sys_get_temp_dir(), 'mfb');
        file_put_contents($tmp, $phar[$pfad]->getContent());
        $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : 'image/jpeg';
        if (!in_array($mime, BildVerarbeitung::ERLAUBTE_MIMES, true)) { @unlink($tmp); return false; }
        $ordner = $this->public . '/uploads/artikel/' . $artikelId;
        if (!is_dir($ordner)) mkdir($ordner, 0755, true);
        $name = 'bild_' . time() . '_' . random_int(1000, 9999) . '.jpg';
        $erg  = BildVerarbeitung::verkleinereUndSpeichere($tmp, $mime, "$ordner/$name");
        @unlink($tmp);
        if ($erg === false) return false;
        (new BilderRepository())->insert($artikelId, $erg, mb_substr($alt, 0, 255));
        return true;
    }
}
