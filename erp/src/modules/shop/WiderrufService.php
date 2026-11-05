<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/WooCommerceClient.php';
require_once __DIR__ . '/../auftraege/AuftragRepository.php';
require_once __DIR__ . '/../auftraege/AuftragService.php';

/**
 * WiderrufService – Widerrufe aus dem Shop im ERP entscheiden (Jacky 2026-11-05)
 *
 * Der Kunde widerruft über den Germanized-Widerrufsbutton, der Shop-Abgleich markiert den
 * Auftrag (auftraege.widerruf_status = 'beantragt', Migration 227). Entschieden wird HIER,
 * nicht im Shop (single source): das ERP ruft das WP-Plugin „NAHTLOS Connector“ (ab 1.5.0)
 * auf, das genau die Germanized-Funktionen der Backend-Buttons nutzt. Germanized setzt den
 * Bestellstatus, bucht im Shop zurück und schickt dem Kunden „Widerruf bestätigt“ bzw.
 * „Dein Widerruf wurde abgelehnt“ -- das ERP schickt deshalb KEINE eigene Storno-Mail.
 *
 * Bestätigen:
 *   - vollständig, nicht versendet → Auftrag im ERP storniert (Reservierungen frei)
 *   - vollständig, schon versendet → 'widerrufen', Retoure wie gewohnt am Packplatz
 *   - Teilwiderruf → Markierung weg; die betroffenen Positionen nimmt man von Hand aus dem
 *     Auftrag (Hinweis im Auftrag nennt sie VOR dem Bestätigen)
 * Ablehnen: Markierung weg, Grund geht an den Kunden.
 */
class WiderrufService
{
    private PDO $db;
    private AuftragRepository $repo;

    public function __construct()
    {
        $this->db   = Database::getInstance();
        $this->repo = new AuftragRepository();
    }

    /**
     * Offener Antrag zum Auftrag aus dem Shop (für die Anzeige im Auftrag).
     *
     * @return array{antrag: ?array, fehler: ?string}
     */
    public function offenerAntrag(int $auftragId): array
    {
        try {
            [$auftrag, $client] = $this->auftragUndClient($auftragId);
            $daten = $client->connector('GET', 'widerruf/' . (int)$auftrag['kanal_auftrag_id']);
            foreach ($daten['antraege'] ?? [] as $antrag) {
                if (($antrag['status'] ?? '') === 'requested') {
                    return ['antrag' => $antrag, 'fehler' => null];
                }
            }
            return ['antrag' => null, 'fehler' => null];
        } catch (Throwable $e) {
            return ['antrag' => null, 'fehler' => $e->getMessage()];
        }
    }

    /** @return array{erfolg: bool, fehler?: string, text?: string} */
    public function bestaetigen(int $auftragId, int $benutzerId): array
    {
        try {
            [$auftrag, $client] = $this->auftragUndClient($auftragId, true);
            $antwort = $client->connector('POST', 'widerruf/' . (int)$auftrag['kanal_auftrag_id'] . '/bestaetigen');
        } catch (Throwable $e) {
            return ['erfolg' => false, 'fehler' => 'Bestätigen im Shop fehlgeschlagen: ' . $e->getMessage()];
        }

        $antrag    = $antwort['antrag'] ?? [];
        $teilweise = !empty($antrag['teilweise']);
        $artikel   = implode(', ', array_map(fn($p) => ($p['menge'] + 0) . '× ' . $p['name'], $antrag['positionen'] ?? []));

        if ($teilweise) {
            $this->setze($auftragId, null, 'Teilwiderruf bestätigt (' . $artikel . ') -- Sperre aufgehoben', $benutzerId);
            return ['erfolg' => true, 'text' => 'Teilwiderruf bestätigt, der Kunde bekommt die Bestätigung per Mail. '
                . 'Bitte die widerrufenen Positionen aus dem Auftrag nehmen (' . $artikel . ').'];
        }

        $this->setze($auftragId, 'widerrufen', 'Widerruf bestätigt (' . $artikel . ')', $benutzerId);

        if (in_array($auftrag['lieferstatus'], ['versendet', 'abgeschlossen', 'retoure_offen', 'storniert'], true)) {
            return ['erfolg' => true, 'text' => 'Widerruf bestätigt, der Kunde bekommt die Bestätigung per Mail. '
                . 'Die Ware ist schon unterwegs: Retoure abwarten und am Packplatz einlagern.'];
        }
        $storno = (new AuftragService())->stornieren($auftragId, 'Widerruf bestätigt', false);
        if (!$storno['erfolg']) {
            return ['erfolg' => true, 'text' => 'Widerruf bestätigt, aber der Auftrag konnte nicht storniert werden: '
                . implode(', ', (array)$storno['fehler']) . ' — bitte von Hand prüfen.'];
        }
        return ['erfolg' => true, 'text' => 'Widerruf bestätigt und Auftrag storniert. Der Kunde bekommt die Bestätigung per Mail.'];
    }

    /** @return array{erfolg: bool, fehler?: string, text?: string} */
    public function ablehnen(int $auftragId, string $grund, int $benutzerId): array
    {
        $grund = trim($grund);
        if ($grund === '') {
            return ['erfolg' => false, 'fehler' => 'Bitte einen Grund angeben — er geht per Mail an den Kunden.'];
        }
        try {
            [$auftrag, $client] = $this->auftragUndClient($auftragId, true);
            $client->connector('POST', 'widerruf/' . (int)$auftrag['kanal_auftrag_id'] . '/ablehnen', ['grund' => $grund]);
        } catch (Throwable $e) {
            return ['erfolg' => false, 'fehler' => 'Ablehnen im Shop fehlgeschlagen: ' . $e->getMessage()];
        }
        $this->setze($auftragId, null, 'Widerruf abgelehnt: ' . $grund, $benutzerId);
        return ['erfolg' => true, 'text' => 'Widerruf abgelehnt, der Kunde bekommt die Begründung per Mail. Der Auftrag ist wieder frei.'];
    }

    /** @return array{0: array, 1: WooCommerceClient} */
    private function auftragUndClient(int $auftragId, bool $mussBeantragtSein = false): array
    {
        $auftrag = $this->repo->findById($auftragId);
        if (!$auftrag || empty($auftrag['shop_id']) || empty($auftrag['kanal_auftrag_id'])) {
            throw new RuntimeException('Kein Shop-Auftrag');
        }
        if ($mussBeantragtSein && ($auftrag['widerruf_status'] ?? null) !== 'beantragt') {
            throw new RuntimeException('Zu diesem Auftrag ist kein offener Widerruf vermerkt');
        }
        $stmt = $this->db->prepare("SELECT wc_url, wc_key, wc_secret, wp_username, wp_app_password FROM shops WHERE id = ?");
        $stmt->execute([(int)$auftrag['shop_id']]);
        $shop = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$shop || !$shop['wc_url'] || !$shop['wp_username'] || !$shop['wp_app_password']) {
            throw new RuntimeException('Für diesen Shop fehlt die WordPress-Anmeldung (Einstellungen → Kanäle)');
        }
        return [$auftrag, new WooCommerceClient($shop['wc_url'], (string)$shop['wc_key'], (string)$shop['wc_secret'], $shop['wp_username'], $shop['wp_app_password'])];
    }

    private function setze(int $auftragId, ?string $neu, string $notiz, int $benutzerId): void
    {
        $stmt = $this->db->prepare("SELECT widerruf_status FROM auftraege WHERE id = ?");
        $stmt->execute([$auftragId]);
        $alt = $stmt->fetchColumn() ?: null;
        $this->db->prepare("UPDATE auftraege SET widerruf_status = ?, widerruf_am = IF(? IS NULL, NULL, widerruf_am) WHERE id = ?")->execute([$neu, $neu, $auftragId]);
        $this->repo->logStatus($auftragId, ['widerruf_status' => [$alt, $neu]], $notiz, $benutzerId);
        Logger::log('auftraege.widerruf', 'auftraege', $auftragId, ['von' => $alt, 'nach' => $neu, 'notiz' => $notiz]);
    }
}
