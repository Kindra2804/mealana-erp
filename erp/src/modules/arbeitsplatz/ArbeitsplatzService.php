<?php
require_once __DIR__ . '/ArbeitsplatzRepository.php';
require_once __DIR__ . '/../kasse/KassenService.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * ArbeitsplatzService – Geräte-/Arbeitsplatz-Erkennung fürs Kasse-Modul.
 *
 * Ein Arbeitsplatz wird über einen UUID-Token identifiziert, den der Browser in
 * localStorage hält (siehe js/kasse_arbeitsplatz.js). Für Kassen mit aktiver
 * BFR-Registrierung (kassen.bfr_aktiv_seit gesetzt) gibt es KEINE freie Auswahl
 * mehr — die Bindung entsteht automatisch beim Abschluss der Registrierung
 * (siehe bindeAnKasseBeiBfrAbschluss()), weil RKSV die Kassen-ID fix an die
 * Signaturkarte/Hardware bindet.
 */
class ArbeitsplatzService
{
    /** Ab wann eine andere Session am selben Arbeitsplatz als "nicht mehr aktiv" gilt. */
    private const KOLLISION_TIMEOUT_MINUTEN = 10;

    private ArbeitsplatzRepository $repo;
    private KassenService $kassenService;

    public function __construct()
    {
        $this->repo = new ArbeitsplatzRepository();
        $this->kassenService = new KassenService();
    }

    /**
     * Zustand für den aktuellen Browser beim Öffnen von kasse/index.php.
     *
     * @return array{status:string, ...}
     *   status='unbekannt'  → kein/unbekannter Token, Auswahl-Screen zeigen (Feld 'kassen')
     *   status='kollision'  → Arbeitsplatz erkannt, aber woanders noch aktiv (Feld 'andere_session')
     *   status='gebunden'   → alles ok, Session ist an den Arbeitsplatz gebunden
     *   status='gesperrt'   → Geräte-Sperre greift (Felder 'grund', 'meldung', 'bfr_kasse'),
     *                         siehe geraetePruefung() — VOR Auswahl/Bindung geprüft
     */
    public function pruefeZustand(?string $token, string $sessionId): array
    {
        $token        = $token !== null ? trim($token) : '';
        $arbeitsplatz = $token !== '' ? $this->repo->findByToken($token) : null;

        $sperre = $this->geraetePruefung(
            $arbeitsplatz && $arbeitsplatz['kasse_id'] !== null ? (int)$arbeitsplatz['kasse_id'] : null
        );
        if ($sperre) {
            return ['status' => 'gesperrt'] + $sperre;
        }

        if (!$arbeitsplatz) {
            // Token verweist auf nichts (mehr) — z.B. Arbeitsplatz wurde deaktiviert
            return $this->auswahlZustand();
        }

        $this->repo->bindeSession($sessionId, (int)$arbeitsplatz['id'], $token);

        $kollision = $this->repo->findAndereAktiveSession((int)$arbeitsplatz['id'], $sessionId, self::KOLLISION_TIMEOUT_MINUTEN);
        if ($kollision) {
            return [
                'status'         => 'kollision',
                'arbeitsplatz'   => $arbeitsplatz,
                'andere_session' => $kollision,
            ];
        }

        return ['status' => 'gebunden', 'arbeitsplatz' => $arbeitsplatz];
    }

    private function auswahlZustand(): array
    {
        return [
            'status' => 'unbekannt',
            'kassen' => $this->repo->findAuswaehlbareKassen(),
        ];
    }

    /**
     * Bestätigte Auswahl aus dem "Welcher Arbeitsplatz bist du?"-Screen.
     * $modus='kasse' → $daten['kasse_id'], $modus='sonstiges' → $daten['typ']+$daten['name'].
     */
    public function waehle(string $modus, array $daten, string $sessionId): array
    {
        if ($modus === 'kasse') {
            $kasseId = (int)($daten['kasse_id'] ?? 0);
            $kasse   = $kasseId ? $this->kassenService->getKasse($kasseId) : null;

            if (!$kasse || $kasse['bfr_aktiv_seit'] !== null) {
                return ['erfolg' => false, 'fehler' => 'Diese Kasse ist nicht (mehr) frei wählbar — evtl. inzwischen RKSV-registriert.'];
            }
            if ($this->repo->findByKasseId($kasseId)) {
                return ['erfolg' => false, 'fehler' => 'Diese Kasse ist bereits einem anderen Gerät zugeordnet.'];
            }
            $sperre = $this->geraetePruefung($kasseId);
            if ($sperre) {
                return ['erfolg' => false, 'fehler' => $sperre['meldung']];
            }

            $token = self::generiereToken();
            $id    = $this->repo->insert([
                'name'     => $kasse['name'],
                'typ'      => 'kasse',
                'kasse_id' => $kasseId,
                'geraete_token' => $token,
            ]);
        } else {
            $typ  = $daten['typ'] ?? '';
            $name = trim($daten['name'] ?? '');
            if (!in_array($typ, ['lager', 'buero', 'mobil'], true) || $name === '') {
                return ['erfolg' => false, 'fehler' => 'Bitte Typ und Name angeben.'];
            }

            $token = self::generiereToken();
            $id    = $this->repo->insert([
                'name'     => $name,
                'typ'      => $typ,
                'kasse_id' => null,
                'geraete_token' => $token,
            ]);
        }

        $this->repo->bindeSession($sessionId, $id, $token);
        return ['erfolg' => true, 'geraete_token' => $token];
    }

    /**
     * Kollision übernehmen: Manager-PIN prüfen, alte Session beenden, eigene binden.
     */
    public function uebernehmeKollision(int $arbeitsplatzId, string $pin, string $sessionId, ?string $token): array
    {
        $manager = Auth::pruefeManagerPin($pin);
        if (!$manager) {
            return ['erfolg' => false, 'fehler' => 'PIN ungültig.'];
        }

        $andere = $this->repo->findAndereAktiveSession($arbeitsplatzId, $sessionId, self::KOLLISION_TIMEOUT_MINUTEN);
        if ($andere) {
            $this->repo->loescheSession($andere['id']);
        }

        if ($token) {
            $this->repo->bindeSession($sessionId, $arbeitsplatzId, $token);
        }

        Logger::log('manager_override', 'arbeitsplaetze', $arbeitsplatzId, [
            'freigegeben_von' => $manager['id'],
            'kontext'         => 'arbeitsplatz_uebernahme',
        ]);

        return ['erfolg' => true];
    }

    /**
     * Automatische Bindung beim Abschluss der BFR-Registrierung (kein Dropdown!) —
     * der Browser, der die Registrierung abschließt, IST das physische Kassen-Gerät
     * (bfr_url zeigt immer auf 127.0.0.1, siehe project_kassen_verwaltung Notizen).
     *
     * @return string Der jetzt gültige Token (kann vom übergebenen abweichen, falls
     *                 durch einen parallelen Vorgang schon eine Bindung existierte).
     */
    public function bindeAnKasseBeiBfrAbschluss(int $kasseId, string $token, string $sessionId): string
    {
        $bestehender = $this->repo->findByKasseId($kasseId);
        if ($bestehender) {
            $this->repo->bindeSession($sessionId, (int)$bestehender['id'], $bestehender['geraete_token']);
            return $bestehender['geraete_token'];
        }

        // Der Token ist ans physische Gerät gebunden, nicht an eine Kasse. Würde man
        // versehentlich auf demselben Gerät eine ANDERE Kasse registrieren, würde das
        // spätere INSERT sonst roh am UNIQUE-Constraint auf geraete_token scheitern
        // (ungefangene PDOException statt Fehlermeldung) — hier vorab freundlich abfangen.
        $tokenBelegt = $this->repo->findByToken($token);
        if ($tokenBelegt && (int)$tokenBelegt['kasse_id'] !== $kasseId) {
            throw new RuntimeException(
                'Dieses Gerät ist bereits an die Kasse "' . ($tokenBelegt['name'] ?? ('#' . $tokenBelegt['kasse_id'])) . '" gebunden. ' .
                'Ein physisches Gerät kann nur einer RKSV-Kasse zugeordnet sein — bitte zuerst dort "Neue Kassen-ID anfordern" ausführen, um die alte Bindung zu lösen.'
            );
        }

        $kasse = $this->kassenService->getKasse($kasseId);
        $id    = $this->repo->insert([
            'name'     => $kasse['name'] ?? ('Kasse ' . $kasseId),
            'typ'      => 'kasse',
            'kasse_id' => $kasseId,
            'geraete_token' => $token,
        ]);
        $this->repo->bindeSession($sessionId, $id, $token);
        return $token;
    }

    /** Für die Kassen-Verwaltung: aktuell gebundener Arbeitsplatz (falls vorhanden). */
    public function findBindungFuerKasse(int $kasseId): ?array
    {
        return $this->repo->findByKasseId($kasseId);
    }

    /** Warnung für die Kassen-Verwaltung: sitzt dort gerade jemand aktiv? */
    public function istAktivInVerwendung(int $arbeitsplatzId): bool
    {
        return $this->repo->zaehleAktiveSessions($arbeitsplatzId, self::KOLLISION_TIMEOUT_MINUTEN) > 0;
    }

    /**
     * Hardware-Wechsel (Aktion "Neue Kassen-ID anfordern"): löst die alte Bindung,
     * damit das neue Gerät sich beim Abschluss der neuen Registrierung frisch binden kann.
     */
    public function loeseBindungFuerKasse(int $kasseId): void
    {
        $this->repo->deaktiviereFuerKasse($kasseId);
    }

    /**
     * kasse_id für die aktuelle PHP-Session — NULL, solange die Session an keinen
     * Kassen-Arbeitsplatz gebunden ist. Der Aufrufer MUSS das behandeln (auf
     * kasse/index.php umleiten, dort stellt kasse_arbeitsplatz.js die Bindung her).
     *
     * Bis 2026-10-08 gab es hier einen stillen Fallback auf Kasse 1 (solange K1 kein
     * BFR hatte). Nach jedem Login ist die Session aber kurz ungebunden (neue
     * Session-ID) — wer in diesem Moment in bon.php landete, kassierte auf dem
     * Signatur-Laptop (K3) unbemerkt als K1 → unsignierte Belege. Daher: kein
     * Fallback mehr, jede Kasse wird ausdrücklich über den Arbeitsplatz bestimmt.
     */
    public function aktuelleKasseId(): ?int
    {
        $kasseId = $this->repo->findKasseIdFuerSession(session_id());
        // Geräte-Sperre (siehe geraetePruefung): falsches Gerät = keine Kasse →
        // Aufrufer leitet auf kasse/index.php um, dort wird der Grund angezeigt.
        if ($kasseId !== null && $this->geraetePruefung($kasseId) !== null) {
            return null;
        }
        return $kasseId;
    }

    // ── Geräte-Sperre für Signatur-Kassen (RKSV) ─────────────────────────────
    //
    // Der Server ruft den BFR einer Signatur-Kasse unter kassen.bfr_url auf (z.B.
    // http://10.0.0.40:8787). Der Host darin IST also das physische Kassen-Gerät.
    // Daraus folgen zwei Regeln, geprüft anhand der IP, von der die Anfrage kommt:
    //   1. Eine Signatur-Kasse darf nur von ihrem BFR-Gerät aus kassieren.
    //   2. Ein BFR-Gerät darf nur als SEINE Signatur-Kasse kassieren (nie als K1).
    // Ändert sich die IP (Modemtausch, neue Range), reicht es, unter Einstellungen →
    // Kassen → RKSV-Registrierung die BFR-URL anzupassen (Recht kasse.verwaltung).
    // Browser-seitig (localStorage, Hardware-ID, C:\BFR) ist das nicht lösbar: der
    // Browser darf weder den BFR direkt fragen (CORS) noch Geräte-Infos lesen.

    /**
     * NULL = alles ok. Sonst ['grund' => 'falsches_geraet'|'signatur_geraet',
     * 'meldung' => Text, 'bfr_kasse' => die Signatur-Kasse dieses Geräts oder NULL].
     */
    public function geraetePruefung(?int $kasseId): ?array
    {
        $bfrKassen = $this->repo->findBfrKassen();
        $ip        = self::clientIp();

        // Regel 2: steht hier ein BFR-Gerät, muss es als genau diese Kasse laufen
        $hier = $this->bfrKasseFuerIp($bfrKassen, $ip);
        if ($hier && (int)$hier['id'] !== $kasseId) {
            return [
                'grund'     => 'signatur_geraet',
                'meldung'   => 'Dieses Gerät ist die Signatur-Kasse „' . $hier['name'] . '“ (' . $hier['kasse_nr'] . ') — '
                             . 'ein Start als andere Kasse ist nicht möglich.',
                'bfr_kasse' => $hier,
            ];
        }

        // Regel 1: eine Signatur-Kasse nur von ihrem BFR-Gerät aus
        foreach ($bfrKassen as $k) {
            if ((int)$k['id'] === $kasseId && !self::ipPasstZuBfrUrl($ip, $k['bfr_url'])) {
                return [
                    'grund'     => 'falsches_geraet',
                    'meldung'   => 'Die Signatur-Kasse „' . $k['name'] . '“ kann nur an ihrem eigenen Gerät betrieben werden ('
                                 . (parse_url($k['bfr_url'], PHP_URL_HOST) ?: $k['bfr_url']) . ', dieses Gerät: ' . $ip . '). '
                                 . 'Hat sich die IP geändert (z.B. Modemtausch), bitte unter Einstellungen → Kassen → RKSV-Registrierung die BFR-URL anpassen.',
                    'bfr_kasse' => null,
                ];
            }
        }
        return null;
    }

    private function bfrKasseFuerIp(array $bfrKassen, string $ip): ?array
    {
        foreach ($bfrKassen as $k) {
            if (self::ipPasstZuBfrUrl($ip, $k['bfr_url'])) {
                return $k;
            }
        }
        return null;
    }

    /** IP der aktuellen Anfrage, IPv6-Schreibweisen von localhost/IPv4 vereinheitlicht. */
    private static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '::1') {
            return '127.0.0.1';
        }
        if (str_starts_with($ip, '::ffff:')) {
            return substr($ip, 7);
        }
        return $ip;
    }

    private static function ipPasstZuBfrUrl(string $ip, string $bfrUrl): bool
    {
        $host = parse_url($bfrUrl, PHP_URL_HOST);
        if (!$host || $ip === '') {
            return false;
        }
        $host = trim($host, '[]');
        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            // BFR läuft am Server-PC selbst → nur lokale Anfragen sind "dieses Gerät"
            return $ip === '127.0.0.1';
        }
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $host = gethostbyname($host);   // Rechnername statt IP in bfr_url
        }
        return $host === $ip;
    }

    /**
     * Signatur-Gerät hat seine Bindung verloren (Browser-Speicher geleert, anderer
     * Browser, oder versehentlich als andere Kasse gewählt): mit Manager-PIN wieder
     * an SEINE Signatur-Kasse binden. Welche Kasse das ist, bestimmt allein die IP —
     * nie ein Parameter vom Client.
     */
    public function bindeSignaturGeraet(string $pin, string $sessionId): array
    {
        $manager = Auth::pruefeManagerPin($pin);
        if (!$manager) {
            return ['erfolg' => false, 'fehler' => 'PIN ungültig.'];
        }

        $hier = $this->bfrKasseFuerIp($this->repo->findBfrKassen(), self::clientIp());
        if (!$hier) {
            return ['erfolg' => false, 'fehler' => 'Dieses Gerät ist keiner Signatur-Kasse zugeordnet.'];
        }

        $token = $this->bindeAnKasseBeiBfrAbschluss((int)$hier['id'], self::generiereToken(), $sessionId);

        Logger::log('manager_override', 'kassen', (int)$hier['id'], [
            'freigegeben_von' => $manager['id'],
            'kontext'         => 'signatur_geraet_neu_binden',
            'ip'              => self::clientIp(),
        ]);

        return ['erfolg' => true, 'geraete_token' => $token];
    }

    /** UUID v4, exakt CHAR(36)-kompatibel. */
    public static function generiereToken(): string
    {
        $daten = random_bytes(16);
        $daten[6] = chr(ord($daten[6]) & 0x0f | 0x40);
        $daten[8] = chr(ord($daten[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($daten), 4));
    }
}
