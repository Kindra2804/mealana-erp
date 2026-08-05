<?php
/**
 * Komplettabgleich -- holt einen großen Sync-Rückstau zügig auf.
 *
 * Der normale Cron (cron/shop_sync.php) synct bewusst nur 20 Artikel alle 15
 * Minuten (gedacht für den laufenden Normalbetrieb, kein Massen-Ersteinstieg).
 * Bei einem großen Rückstau (z.B. Erstbefüllung mit tausenden Artikeln) würde
 * das Tage bis Wochen dauern. Dieses Skript ruft ShopSyncService::syncShop()
 * stattdessen wiederholt mit einem viel größeren Limit auf, bis nichts mehr
 * fällig ist -- und pausiert bei einer erkannten Rate-Limit-Sperre genau so
 * lange, wie der Server selbst verlangt (Retry-After-Header), statt auf den
 * nächsten 15-Minuten-Cron-Tick zu warten.
 *
 * Aufruf:
 *   php scripts/komplettabgleich.php <shop-slug> [batch-groesse] [ohne-bilder]
 *
 * Beispiel:
 *   php scripts/komplettabgleich.php mealana 200
 *   php scripts/komplettabgleich.php mealana 200 ohne-bilder
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncRepository.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncService.php';

// Ohne das würde PHP die Ausgabe intern puffern -- bei einem Batch, der wegen
// vieler Bilder/Achsen mehrere Minuten dauert, käme sonst alles auf einmal am
// Ende statt live während des Laufs.
ob_implicit_flush(true);

// Falls der Server beim 429 keinen Retry-After-Header mitschickt: gleiche
// Basis-Sperrzeit wie beim dokumentierten Fund (2026-07-30), plus Puffer.
const FALLBACK_WARTEZEIT_SEKUNDEN = 90;

// Alle X verarbeiteten Artikel eine Fortschrittszeile -- sonst bei einem
// großen Batch minutenlang keine Rückmeldung (jedes Bild braucht seine eigene
// Upload-Zeit + 0,4s Pause, siehe WooCommerceClient::MEDIEN_UPLOAD_PAUSE_SEKUNDEN).
const FORTSCHRITT_SCHRITT = 5;

$shopSlug   = $argv[1] ?? null;
$limit      = isset($argv[2]) ? (int)$argv[2] : 200;
$mitBildern = ($argv[3] ?? '') !== 'ohne-bilder';

if (!$shopSlug) {
    fwrite(STDERR, "Aufruf: php komplettabgleich.php <shop-slug> [batch-groesse]\n");
    exit(1);
}

$repo = new ShopSyncRepository();
$shop = null;
foreach ($repo->findAktiveShops() as $s) {
    if ($s['slug'] === $shopSlug) {
        $shop = $s;
        break;
    }
}
if (!$shop) {
    fwrite(STDERR, "Shop '$shopSlug' nicht gefunden oder nicht aktiv.\n");
    exit(1);
}

// Gleiche Sperre wie beim Bilder-Ersteinstieg (erstbefuellung_bilder.php) --
// der normale 15-Minuten-Cron überspringt den Shop, solange dieses Skript
// läuft, sonst Race Condition (Cron greift mit veraltetem Stand rein).
$repo->setBulkImportAktiv((int)$shop['id'], true);

echo "Komplettabgleich für '{$shop['slug']}' gestartet (Batch-Größe: $limit"
    . ($mitBildern ? '' : ', ohne Bilder') . ")\n";

try {
    $service = new ShopSyncService();

    $gesamtErfolg = 0;
    $gesamtFehler = 0;
    $durchlauf    = 0;

    $fortschritt = function (int $erledigt, int $gesamtImBatch) {
        if ($erledigt % FORTSCHRITT_SCHRITT === 0 || $erledigt === $gesamtImBatch) {
            echo "  ... $erledigt/$gesamtImBatch\n";
        }
    };

    // Zählt aufeinanderfolgende Batches OHNE jeden Fortschritt (weder erfolg
    // noch fehler). Ein einzelner solcher Batch ist normal -- z.B. wenn ein
    // Batch nur aus Kind-Artikeln besteht, deren Vater im SELBEN Batch gerade
    // erst seine external_id bekommen hat: die SQL-Momentaufnahme, mit der der
    // Batch gestartet wurde, kennt diese neue external_id noch nicht, die
    // Kinder bleiben also 'pending' und werden erst im NÄCHSTEN Batch wieder
    // aufgegriffen. Erst ZWEI Leer-Durchläufe IN FOLGE bedeuten "wirklich
    // nichts mehr zu tun" (bug 2026-08-04: vorher brach das Skript nach einem
    // einzigen Leer-Batch ab und meldete "fertig", obwohl noch tausende
    // Artikel offen waren -- betroffen war eine Gruppe Väter, die dem
    // Shop-Kanal gar nicht zugewiesen waren, ihre Kinder aber schon).
    $leerDurchlaeufeInFolge = 0;

    while (true) {
        $durchlauf++;
        $ergebnis = $service->syncShop($shop, $limit, $fortschritt, $mitBildern);
        $gesamtErfolg += $ergebnis['erfolg'];
        $gesamtFehler += $ergebnis['fehler'];

        echo "[Durchlauf $durchlauf] {$ergebnis['erfolg']} erfolgreich, {$ergebnis['fehler']} Fehler"
            . ($ergebnis['rate_limitiert'] ? " -- Rate-Limit erkannt\n" : "\n");

        if ($ergebnis['rate_limitiert']) {
            $wartezeit = $ergebnis['retry_after'] ?? FALLBACK_WARTEZEIT_SEKUNDEN;
            echo "  Pausiere {$wartezeit}s, danach automatisch weiter...\n";
            sleep($wartezeit);
            continue; // weiter, unabhängig davon ob dieser Durchlauf noch was geschafft hat
        }

        if ($ergebnis['erfolg'] > 0) {
            $leerDurchlaeufeInFolge = 0;
            continue;
        }

        if ($ergebnis['fehler'] > 0) {
            // Kein einziger Erfolg, aber Fehler -- vermutlich nur noch
            // dauerhaft fehlerhafte Artikel übrig (würden bei jedem weiteren
            // Durchlauf denselben Fehler produzieren, ohne Fortschritt).
            break;
        }

        $leerDurchlaeufeInFolge++;
        if ($leerDurchlaeufeInFolge >= 2) {
            break;
        }
        echo "  Batch komplett ohne Fortschritt (0 erfolgreich, 0 Fehler) -- versuche nochmal, bevor ich abbreche...\n";
    }

    echo "\nFertig nach $durchlauf Durchläufen: $gesamtErfolg erfolgreich, $gesamtFehler Fehler insgesamt.\n";
    if ($gesamtFehler > 0) {
        echo "Hinweis: $gesamtFehler Fehler stehen noch offen -- Details im Aktivitäten-Log ('shop.sync_fehler' u.ä.).\n";
    }
} finally {
    $repo->setBulkImportAktiv((int)$shop['id'], false);
}
