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
     */
    public function pruefeZustand(?string $token, string $sessionId): array
    {
        $token = $token !== null ? trim($token) : '';
        if ($token === '') {
            return $this->auswahlZustand();
        }

        $arbeitsplatz = $this->repo->findByToken($token);
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
     * kasse_id für die aktuelle PHP-Session — Ersatz für die bisher hart codierte
     * `getKasse(1)`. Ohne gebundenen Arbeitsplatz gibt's einen Fallback auf Kasse 1
     * NUR wenn die (noch) kein aktives BFR hat — sonst wäre ein völlig unbekanntes
     * Gerät in der Lage, unter der Identität/Signaturkarte einer fremden,
     * RKSV-registrierten Kasse Belege zu erzeugen. Gibt's dafür keinen sicheren
     * Fallback, liefert die Methode NULL — der Aufrufer MUSS das behandeln
     * (auf kasse/index.php umleiten), nicht einfach mit Kasse 1 weitermachen.
     */
    public function aktuelleKasseId(): ?int
    {
        $kasseId = $this->repo->findKasseIdFuerSession(session_id());
        if ($kasseId !== null) {
            return $kasseId;
        }

        $hauptkasse = $this->kassenService->getKasse(1);
        return ($hauptkasse && $hauptkasse['bfr_aktiv_seit'] === null) ? 1 : null;
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
