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
 *   php scripts/komplettabgleich.php <shop-slug> [faelligkeits-limit] [ohne-bilder] [wc-batch-groesse]
 *
 * `faelligkeits-limit` ist NICHT dasselbe wie `wc-batch-groesse`: Ersteres
 * steuert, wie viele fällige Artikel EIN syncShop()-Aufruf aus der DB holt
 * (SQL LIMIT), Letzteres steuert, wie viele Artikel EIN einzelner
 * WooCommerce-Batch-Request (/products/batch) enthält -- bei einem
 * faelligkeits-limit von z.B. 200 und wc-batch-groesse 50 macht ein einziger
 * syncShop()-Aufruf vier Batch-Requests hintereinander.
 *
 * Beispiel:
 *   php scripts/komplettabgleich.php mealana 200
 *   php scripts/komplettabgleich.php mealana 200 ohne-bilder
 *   php scripts/komplettabgleich.php mealana 200 ohne-bilder 50
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncRepository.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncService.php';
require_once __DIR__ . '/../src/modules/shop/ShopBestellungSyncService.php';
require_once __DIR__ . '/../src/modules/gutscheine/GutscheinService.php';

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

// Server überlastet (502/503/504/508/Zeitüberschreitung, siehe ServerUeberlastetException):
// warten (60 s, dann doppelt so lang, max. 10 Min.), WooCommerce-Batch halbieren (min. 5) und
// weitermachen. Nach so vielen Überlastungen IN FOLGE ohne einen einzigen Erfolg dazwischen
// aufgeben -- dann ist der Server ernsthaft am Limit, der 15-Minuten-Cron macht später weiter.
const UEBERLASTUNG_ERSTE_PAUSE_SEKUNDEN = 60;
const UEBERLASTUNG_MAX_PAUSE_SEKUNDEN   = 600;
const UEBERLASTUNG_MIN_BATCH            = 5;
const UEBERLASTUNG_MAX_IN_FOLGE         = 6;

$shopSlug      = $argv[1] ?? null;
$limit         = isset($argv[2]) ? (int)$argv[2] : 200;
$mitBildern    = ($argv[3] ?? '') !== 'ohne-bilder';
$batchGroesse  = isset($argv[4]) ? (int)$argv[4] : null;

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

function druckeFaelligkeit(ShopSyncRepository $repo, int $shopId, bool $mitBildern = true): void
{
    $z = $repo->zaehleFaellige($shopId, $mitBildern);
    echo "  Fällig: {$z['vaeter']} Väter/Standalone, {$z['kinder']} Kinder, {$z['bilder']} Bilder"
        . ($z['abzumelden'] > 0 ? ", {$z['abzumelden']} zum Abmelden (Entwurf)" : "")
        . ($z['blockierte_kinder'] > 0 ? " -- {$z['blockierte_kinder']} Kinder warten auf ihren Vater (Kanal dort nicht aktiv)" : "")
        . "\n";
}

druckeFaelligkeit($repo, (int)$shop['id'], $mitBildern);

try {
    $service = new ShopSyncService();
    // Ohne Angabe: WooCommerce-Batch nie größer als das Fälligkeits-Limit (UI "Batch 10" → wirklich 10er-Pakete)
    $service->setBatchGroesse($batchGroesse ?? min($service->getBatchGroesse(), max(1, $limit)));
    echo "WC-Batch-Größe: {$service->getBatchGroesse()}\n";

    // „Automatischen Sync pausieren“ während des Laufs = Komplettabgleich nach dem aktuellen Durchlauf beenden.
    // Nur ein WECHSEL auf pausiert zählt (wer schon pausiert startet, will den Abgleich trotzdem).
    $pausiertBeimStart = (int)$shop['sync_pausiert'] === 1;
    $pausiertJetzt = function () use ($shop): bool {
        $st = Database::getInstance()->prepare('SELECT sync_pausiert FROM shops WHERE id = ?');
        $st->execute([(int)$shop['id']]);
        return (int)$st->fetchColumn() === 1;
    };
    $ueberlastungenInFolge = 0;

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
        druckeFaelligkeit($repo, (int)$shop['id'], $mitBildern);

        if (!$pausiertBeimStart && $pausiertJetzt()) {
            echo "  Automatischer Sync wurde pausiert -- Komplettabgleich wird beendet.\n";
            break;
        }

        if (!empty($ergebnis['ueberlastet'])) {
            // Ein Durchlauf mit Erfolgen vor der Überlastung zählt als neue Serie
            $ueberlastungenInFolge = $ergebnis['erfolg'] > 0 ? 1 : $ueberlastungenInFolge + 1;
            if ($ueberlastungenInFolge > UEBERLASTUNG_MAX_IN_FOLGE) {
                echo "  Server " . UEBERLASTUNG_MAX_IN_FOLGE . "x in Folge überlastet -- Abbruch. Der automatische Sync macht später weiter.\n";
                break;
            }
            $alt = $service->getBatchGroesse();
            $service->setBatchGroesse(max(UEBERLASTUNG_MIN_BATCH, intdiv($alt, 2)));
            $wartezeit = $ergebnis['retry_after']
                ?? min(UEBERLASTUNG_MAX_PAUSE_SEKUNDEN, UEBERLASTUNG_ERSTE_PAUSE_SEKUNDEN * 2 ** ($ueberlastungenInFolge - 1));
            echo "  Server überlastet ({$ueberlastungenInFolge}. Mal in Folge) -- Pause {$wartezeit}s, WC-Batch $alt → {$service->getBatchGroesse()}."
                . " Unklare neue Artikel werden vor dem Neuanlegen per Artikelnummer gesucht.\n";
            sleep($wartezeit);
            continue;
        }
        if ($ergebnis['erfolg'] > 0) {
            $ueberlastungenInFolge = 0;
        }

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

    echo "\nFertig nach $durchlauf Durchläufen: $gesamtErfolg erfolgreich, $gesamtFehler Fehlversuche unterwegs.\n";
    // Fehlversuche (z.B. nach einem 502) werden meist im nächsten Durchlauf aufgelöst -- entscheidend
    // ist, was JETZT noch auf Fehler steht.
    $offenStmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM artikel_shops WHERE shop_id = ? AND aktiv = 1 AND sync_status = 'error'");
    $offenStmt->execute([(int)$shop['id']]);
    $nochOffen = (int)$offenStmt->fetchColumn();
    echo $nochOffen > 0
        ? "Hinweis: $nochOffen Artikel stehen noch auf Fehler -- Details im Aktivitäten-Log ('shop.sync_fehler' u.ä.), ggf. „Fehler abgleichen“.\n"
        : "Keine Artikel mit Fehler offen.\n";

    // Wie der 15-Minuten-Cron: nach den Artikeln auch Bestellungen abholen und
    // Gutschein-Coupons abgleichen -- sonst wirkt "Komplettabgleich starten" für
    // Bestellungen wie tot (Jacky 2026-09-30), obwohl der Name alles verspricht.
    try {
        $bestSync = new ShopBestellungSyncService();
        $b = $bestSync->syncBestellungen($shop);
        echo "Bestellungen: {$b['erfolg']} verarbeitet, {$b['fehler']} Fehler\n";
        $st = $bestSync->meldeOffeneStatusAnShop($shop);
        echo "Status an Shop gemeldet (bezahlt/versendet): {$st['gemeldet']}, Fehler: {$st['fehler']}\n";
    } catch (Throwable $e) {
        echo "Bestellungs-Abgleich abgebrochen: {$e->getMessage()}\n";
    }
    try {
        $g = (new GutscheinService())->syncShopCoupons($shop);
        echo "Gutscheine: {$g['angelegt']} angelegt, {$g['aktualisiert']} aktualisiert, {$g['geloescht']} gelöscht, {$g['fehler']} Fehler\n";
    } catch (Throwable $e) {
        echo "Gutschein-Abgleich abgebrochen: {$e->getMessage()}\n";
    }
} finally {
    $repo->setBulkImportAktiv((int)$shop['id'], false);
    $repo->setAktuellerSyncLog((int)$shop['id'], null);
}
