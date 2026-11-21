<?php

require_once __DIR__ . '/DemoBasis.php';
require_once __DIR__ . '/DemoVerkauf.php';

/**
 * Stand der Demo-Daten (für Demo::frischHalten, ohne Cronjob):
 *   storage/demo_stand.json  {"tag": "Y-m-d", "bis": "Y-m-d H:i:s"} — erzeugt am Tag, Belege bis
 *   storage/demo_cache.json  gemerkte IDs des Generators (Artikel, Kunden, Lager …) für nachholen()
 * storage/ ist von außen gesperrt (erp/.htaccess).
 */
class DemoStand
{
    public static function pfad(string $datei): string
    {
        return __DIR__ . '/../../storage/' . $datei;
    }

    /** Am Ende des Generators: Stand = heute, Belege bis jetzt */
    public static function schreiben(DemoBasis $b): void
    {
        file_put_contents(self::pfad('demo_cache.json'), json_encode([
            'ids' => array_intersect_key($b->ids, array_flip(['verkauf', 'kunden', 'mitarbeiter', 'lager', 'kasse', 'shops'])),
            'jarvis' => $b->jarvisId, 'demo_benutzer' => $b->demoBenutzerId,
        ], JSON_UNESCAPED_UNICODE));
        self::stand(date('Y-m-d'), date('Y-m-d H:i:s'));
    }

    public static function laden(): ?array
    {
        $s = @file_get_contents(self::pfad('demo_stand.json'));
        return $s ? json_decode($s, true) : null;
    }

    private static function stand(string $tag, string $bis): void
    {
        file_put_contents(self::pfad('demo_stand.json'), json_encode(['tag' => $tag, 'bis' => $bis]));
    }

    /**
     * Belege seit dem letzten Stand bis jetzt nachholen. Gleichzeitige Aufrufe: nur einer arbeitet,
     * die anderen gehen ohne Warten weiter (flock nicht blockierend).
     */
    public static function nachholen(): void
    {
        $sperre = fopen(self::pfad('demo_live.lock'), 'c');
        if (!$sperre || !flock($sperre, LOCK_EX | LOCK_NB)) return;
        try {
            $stand = self::laden();
            $cache = json_decode((string)@file_get_contents(self::pfad('demo_cache.json')), true);
            if (!$stand || !$cache || $stand['tag'] !== date('Y-m-d')) return;
            $jetzt = date('Y-m-d H:i:s', time() - 30);
            if (strtotime($jetzt) - strtotime($stand['bis']) < 60) return;

            $b = new DemoBasis(random_int(1, PHP_INT_MAX));
            $b->ids = $cache['ids'];
            $b->jarvisId = (int)$cache['jarvis'];
            $b->demoBenutzerId = (int)$cache['demo_benutzer'];
            (new DemoVerkauf($b))->nachholen($stand['bis'], $jetzt);
            self::stand($stand['tag'], $jetzt);
        } finally {
            flock($sperre, LOCK_UN);
            fclose($sperre);
        }
    }
}
