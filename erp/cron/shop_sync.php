<?php
/**
 * Shop-Sync-Cronjob
 *
 * Läuft alle 15 Minuten (empfohlen).
 *
 * Windows Task Scheduler:
 *   Programm:  C:\xampp\php\php.exe
 *   Argumente: D:\ERP\mealana\erp\cron\shop_sync.php
 *
 * Linux crontab (crontab -e):
 *   (jede 15.) * * * php /var/www/mealana/erp/cron/shop_sync.php >> /var/log/mealana_cron.log 2>&1
 *
 * Zwei Richtungen pro aktivem Shop (Details siehe ShopSyncService/ShopBestellungSyncService):
 *   ERP -> Shop:  fällige Artikel/Kategorien/Achsen/Hersteller/Bilder/Bestand
 *   Shop -> ERP:  neue/geänderte Bestellungen (reines Polling, kein Webhook --
 *                 das ERP hat keinen öffentlichen Endpunkt)
 *
 * Ein Fehler bei einem Shop darf die anderen Shops nicht blockieren, darum
 * beide Richtungen pro Shop in ein eigenes try/catch gefasst (gleiches
 * Prinzip wie schon innerhalb von ShopSyncService pro Artikel).
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncRepository.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncService.php';
require_once __DIR__ . '/../src/modules/shop/ShopBestellungSyncService.php';
require_once __DIR__ . '/../src/modules/shop/WiderrufService.php';
require_once __DIR__ . '/../src/modules/gutscheine/GutscheinService.php';

$jarvisId = (int)Database::getInstance()
    ->query("SELECT id FROM benutzer WHERE username = 'system'")
    ->fetchColumn();

$repo             = new ShopSyncRepository();
$artikelSync      = new ShopSyncService();
$bestellungSync   = new ShopBestellungSyncService();
$widerrufService  = new WiderrufService();
$gutscheinService = new GutscheinService();

// Lizenz: ohne Modul Shop kein Abgleich, sonst nur so viele Shops wie lizenziert (älteste zuerst)
require_once __DIR__ . '/../src/core/Lizenz.php';
$lizenzShops = Lizenz::anzahl('shop');
if ($lizenzShops === 0) {
    echo "Shop-Abgleich übersprungen -- Modul Shop-Anbindung nicht lizenziert
";
    exit(0);
}
$shopNr = 0;
foreach ($repo->findAktiveShops() as $shop) {
    if (++$shopNr > $lizenzShops) {
        echo "[{$shop['slug']}] übersprungen -- Lizenz erlaubt {$lizenzShops} Shop(s)
";
        continue;
    }
    if ((int)$shop['sync_pausiert'] === 1) {
        // Bewusst vom Betreiber über die "Shop-Synchronisierung"-Seite pausiert
        // (Migration 157) -- anders als bulk_import_aktiv kein Selbst-Reset,
        // bleibt so bis er es über die UI wieder aufhebt.
        echo "[{$shop['slug']}] übersprungen -- Sync pausiert\n";
        continue;
    }

    // Sperre analog zum JTL-Komplettabgleich (siehe scripts/erstbefuellung_bilder.php) --
    // während ein manueller Bulk-Import/Komplettabgleich läuft, überspringt der Cron
    // NUR den Artikel-Teil (sonst Race Condition, doppelter Bild-Upload etc.).
    // Bestellungen + Gutscheine laufen weiter -- früher wurde der ganze Shop
    // übersprungen, bei einem stundenlangen Komplettabgleich kamen so lange gar
    // keine Bestellungen ins ERP (Fund 2026-09-30).
    $artikelGesperrt = (int)$shop['bulk_import_aktiv'] === 1;
    if ($artikelGesperrt) {
        echo "[{$shop['slug']}] Artikel übersprungen -- Bulk-Import/Komplettabgleich läuft gerade\n";
    }

    if (!$artikelGesperrt) try {
        $ergebnis = $artikelSync->syncShop($shop);
        echo "[{$shop['slug']}] Artikel: {$ergebnis['erfolg']} erfolgreich, {$ergebnis['fehler']} Fehler\n";
        // Nur bei tatsächlicher Aktivität loggen -- ein Leerlauf-Poll alle 15 Min
        // ohne Änderungen soll die Aktivitäten-Seite nicht zumüllen. Einzelne
        // Fehler sind schon als eigene 'error'-Einträge geloggt (siehe ShopSyncService),
        // das hier ist nur die Lauf-Zusammenfassung fürs schnelle Nachsehen.
        if ($ergebnis['erfolg'] > 0 || $ergebnis['fehler'] > 0) {
            Logger::log('shop.sync_lauf', 'shops', (int)$shop['id'], [
                'richtung' => 'artikel',
                'shop'     => $shop['slug'],
                'erfolg'   => $ergebnis['erfolg'],
                'fehler'   => $ergebnis['fehler'],
            ], $jarvisId, $ergebnis['fehler'] > 0 ? 'warn' : 'info');
        }
    } catch (Throwable $e) {
        Logger::log('shop.cron_fehler', 'shops', (int)$shop['id'], [
            'richtung' => 'artikel',
            'shop'     => $shop['slug'],
            'fehler'   => $e->getMessage(),
        ], $jarvisId, 'error');
        echo "[{$shop['slug']}] Artikel-Sync abgebrochen: {$e->getMessage()}\n";
    }

    try {
        $ergebnis = $bestellungSync->syncBestellungen($shop);
        echo "[{$shop['slug']}] Bestellungen: {$ergebnis['erfolg']} erfolgreich, {$ergebnis['fehler']} Fehler\n";
        if ($ergebnis['erfolg'] > 0 || $ergebnis['fehler'] > 0) {
            Logger::log('shop.sync_lauf', 'shops', (int)$shop['id'], [
                'richtung' => 'bestellungen',
                'shop'     => $shop['slug'],
                'erfolg'   => $ergebnis['erfolg'],
                'fehler'   => $ergebnis['fehler'],
            ], $jarvisId, $ergebnis['fehler'] > 0 ? 'warn' : 'info');
        }
    } catch (Throwable $e) {
        Logger::log('shop.cron_fehler', 'shops', (int)$shop['id'], [
            'richtung' => 'bestellungen',
            'shop'     => $shop['slug'],
            'fehler'   => $e->getMessage(),
        ], $jarvisId, 'error');
        echo "[{$shop['slug']}] Bestellungs-Sync abgebrochen: {$e->getMessage()}\n";
    }

    // Ungeklärte Widerrufe (Gast-Widerruf ohne passende Bestellung) zählen -- Plugin >= 1.6.0, sonst still übersprungen
    $ungeklaert = $widerrufService->zaehleUngeklaerte($shop);
    if ($ungeklaert) {
        echo "[{$shop['slug']}] Widerrufe ohne Zuordnung: {$ungeklaert}\n";
    }

    // ERP → Shop: Zahlungs-/Versandstand melden (z.B. am Packplatz versendet, an der
    // Kasse abgeholt) -- sonst blieben die Bestellungen im Shop offen.
    try {
        $st = $bestellungSync->meldeOffeneStatusAnShop($shop);
        echo "[{$shop['slug']}] Status an Shop gemeldet: {$st['gemeldet']}, Fehler: {$st['fehler']}\n";
    } catch (Throwable $e) {
        Logger::log('shop.cron_fehler', 'shops', (int)$shop['id'], [
            'richtung' => 'status', 'shop' => $shop['slug'], 'fehler' => $e->getMessage(),
        ], $jarvisId, 'error');
    }

    // Gutschein-Coupons NACH dem Bestellungs-Sync: so werden online eingelöste Codes
    // und dabei entstandene Restguthaben-Codes noch im selben Lauf abgeglichen.
    try {
        $ergebnis = $gutscheinService->syncShopCoupons($shop);
        $summe = $ergebnis['angelegt'] + $ergebnis['aktualisiert'] + $ergebnis['geloescht'];
        echo "[{$shop['slug']}] Gutscheine: {$ergebnis['angelegt']} angelegt, {$ergebnis['aktualisiert']} aktualisiert, {$ergebnis['geloescht']} gelöscht, {$ergebnis['fehler']} Fehler\n";
        if ($summe > 0 || $ergebnis['fehler'] > 0) {
            Logger::log('shop.sync_lauf', 'shops', (int)$shop['id'], [
                'richtung' => 'gutscheine', 'shop' => $shop['slug'],
            ] + $ergebnis, $jarvisId, $ergebnis['fehler'] > 0 ? 'warn' : 'info');
        }
    } catch (Throwable $e) {
        Logger::log('shop.cron_fehler', 'shops', (int)$shop['id'], [
            'richtung' => 'gutscheine', 'shop' => $shop['slug'], 'fehler' => $e->getMessage(),
        ], $jarvisId, 'error');
        echo "[{$shop['slug']}] Gutschein-Sync abgebrochen: {$e->getMessage()}\n";
    }
}

// Shop-Slider: Slides mit „solange Aktion läuft“ einmal täglich neu übertragen
// (der Aktionszeitraum kann sich geändert haben). Normale Änderungen überträgt nur der Klick im ERP.
try {
    require_once __DIR__ . '/../src/modules/slider/SliderService.php';
    (new SliderService())->cronUebertrage($jarvisId, function (string $zeile) { echo $zeile . "\n"; });
    // Shop-Menü: neu übertragen, wenn sich Kategorien geändert haben (Unterkategorien automatisch)
    require_once __DIR__ . '/../src/modules/slider/MenueService.php';
    (new MenueService())->cronUebertrage($jarvisId, function (string $zeile) { echo $zeile . "\n"; });
} catch (Throwable $e) {
    Logger::log('slider.cron_fehler', null, null, ['fehler' => $e->getMessage()], $jarvisId, 'error');
    echo "Slider-Übertragung abgebrochen: {$e->getMessage()}\n";
}
