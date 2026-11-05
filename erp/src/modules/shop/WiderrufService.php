<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/logger.php';
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

    // ── Ungeklärte Anträge (Gast-Widerruf ohne passende Bestellung) ─────────

    /**
     * Cron: Zahl der ungeklärten Anträge eines Shops merken (shops.widerrufe_ungeklaert).
     * Plugin zu alt / nicht erreichbar → Zahl bleibt, Rückgabe null.
     */
    public function zaehleUngeklaerte(array $shop): ?int
    {
        if (empty($shop['wc_url']) || empty($shop['wp_username']) || empty($shop['wp_app_password'])) {
            return null;
        }
        try {
            $daten = $this->client($shop)->connector('GET', 'widerrufe/offen');
        } catch (Throwable $e) {
            return null;
        }
        $anzahl = count(array_filter($daten['antraege'] ?? [], fn($a) => !empty($a['ungeklaert'])));
        $this->db->prepare("UPDATE shops SET widerrufe_ungeklaert = ? WHERE id = ?")->execute([$anzahl, (int)$shop['id']]);
        return $anzahl;
    }

    /**
     * Seite Verkauf → Widerrufe: ungeklärte Anträge aller Shops (live), je Antrag Vorschläge
     * passender Aufträge aus dem ERP (gleicher Shop, letzte 120 Tage, E-Mail/Name/Nummer).
     *
     * @return array{antraege: array, fehler: string[]}
     */
    public function ungeklaerteAntraege(): array
    {
        $shops = $this->db->query("
            SELECT id, name, wc_url, wc_key, wc_secret, wp_username, wp_app_password
            FROM shops WHERE ist_aktiv = 1 AND wc_url IS NOT NULL AND wp_username IS NOT NULL AND wp_app_password IS NOT NULL
        ")->fetchAll(PDO::FETCH_ASSOC);
        $antraege = []; $fehler = [];
        foreach ($shops as $shop) {
            try {
                $daten = $this->client($shop)->connector('GET', 'widerrufe/offen');
            } catch (Throwable $e) {
                $fehler[] = $shop['name'] . ': ' . $e->getMessage();
                continue;
            }
            $offen = array_values(array_filter($daten['antraege'] ?? [], fn($a) => !empty($a['ungeklaert'])));
            $this->db->prepare("UPDATE shops SET widerrufe_ungeklaert = ? WHERE id = ?")->execute([count($offen), (int)$shop['id']]);
            foreach ($offen as $a) {
                $a['shop_id']     = (int)$shop['id'];
                $a['shop_name']   = $shop['name'];
                $a['vorschlaege'] = $this->vorschlaege((int)$shop['id'], $a);
                $antraege[] = $a;
            }
        }
        return ['antraege' => $antraege, 'fehler' => $fehler];
    }

    /** Aufträge, die zum Antrag passen könnten (mit Treffer-Grund). */
    private function vorschlaege(int $shopId, array $antrag): array
    {
        $email = mb_strtolower(trim((string)($antrag['email'] ?? '')));
        $name  = trim((string)($antrag['name'] ?? ''));
        preg_match_all('/\d{3,}/', (string)($antrag['angaben'] ?? '') . ' ' . (string)($antrag['info'] ?? ''), $m);
        $nummern = $m[0];

        $stmt = $this->db->prepare("
            SELECT id, auftrag_nr, kanal_auftrag_id, erstellt_am, bruttobetrag, lieferstatus,
                   JSON_UNQUOTE(JSON_EXTRACT(kunden_snapshot, '$.name'))  AS kunde,
                   JSON_UNQUOTE(JSON_EXTRACT(kunden_snapshot, '$.email')) AS email
            FROM auftraege
            WHERE shop_id = ? AND kanal = 'woocommerce' AND kanal_auftrag_id IS NOT NULL
              AND lieferstatus <> 'storniert' AND widerruf_status IS NULL
              AND erstellt_am >= DATE_SUB(NOW(), INTERVAL 120 DAY)
            ORDER BY erstellt_am DESC
        ");
        $stmt->execute([$shopId]);
        $treffer = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $gruende = [];
            if ($email !== '' && mb_strtolower((string)$a['email']) === $email) $gruende[] = 'E-Mail';
            if ($name !== '' && $a['kunde'] && mb_stripos((string)$a['kunde'], $name) !== false) $gruende[] = 'Name';
            foreach ($nummern as $n) {
                if ((string)$a['kanal_auftrag_id'] === $n || str_contains((string)$a['auftrag_nr'], $n)) { $gruende[] = 'Nummer'; break; }
            }
            if ($gruende) {
                $a['gruende'] = $gruende;
                $treffer[] = $a;
            }
        }
        usort($treffer, fn($x, $y) => count($y['gruende']) <=> count($x['gruende']));
        return array_slice($treffer, 0, 5);
    }

    /** Antrag einem ERP-Auftrag zuordnen (Auftragsnummer oder ID); danach normaler Ablauf im Auftrag. */
    public function zuordnen(int $shopId, int $antragId, string $auftragEingabe, int $benutzerId): array
    {
        $eingabe = trim($auftragEingabe);
        $stmt = $this->db->prepare("
            SELECT id, auftrag_nr, shop_id, kanal_auftrag_id, widerruf_status FROM auftraege
            WHERE auftrag_nr = ? OR id = ? LIMIT 1
        ");
        $stmt->execute([$eingabe, ctype_digit($eingabe) ? (int)$eingabe : 0]);
        $auftrag = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$auftrag) {
            return ['erfolg' => false, 'fehler' => "Auftrag „{$eingabe}“ nicht gefunden."];
        }
        if ((int)$auftrag['shop_id'] !== $shopId || empty($auftrag['kanal_auftrag_id'])) {
            return ['erfolg' => false, 'fehler' => "Auftrag {$auftrag['auftrag_nr']} ist keine Bestellung aus diesem Shop — zuordnen geht nur dort. Sonst ablehnen."];
        }
        if (!empty($auftrag['widerruf_status'])) {
            return ['erfolg' => false, 'fehler' => "Zu Auftrag {$auftrag['auftrag_nr']} gibt es schon einen Widerruf."];
        }
        try {
            $this->client($this->shop($shopId))->connector('POST', 'widerrufe/' . $antragId . '/zuordnen', ['bestellung' => (int)$auftrag['kanal_auftrag_id']]);
        } catch (Throwable $e) {
            return ['erfolg' => false, 'fehler' => 'Zuordnen im Shop fehlgeschlagen: ' . $e->getMessage()];
        }
        // gleich markieren statt auf den nächsten Abgleich zu warten
        $this->db->prepare("UPDATE auftraege SET widerruf_am = COALESCE(widerruf_am, NOW()) WHERE id = ?")->execute([(int)$auftrag['id']]);
        $this->setze((int)$auftrag['id'], 'beantragt', 'Widerruf (Antrag ' . $antragId . ' ohne passende Bestellnummer) im ERP zugeordnet -- Auftrag gesperrt', $benutzerId);
        $this->db->prepare("UPDATE shops SET widerrufe_ungeklaert = GREATEST(widerrufe_ungeklaert - 1, 0) WHERE id = ?")->execute([$shopId]);
        return ['erfolg' => true, 'auftrag_id' => (int)$auftrag['id'],
                'text' => "Widerruf dem Auftrag {$auftrag['auftrag_nr']} zugeordnet — jetzt hier bestätigen oder ablehnen."];
    }

    /** Ungeklärten Antrag ablehnen (z. B. Laden-Kauf, Spam, nicht zuordenbar). */
    public function antragAblehnen(int $shopId, int $antragId, string $grund): array
    {
        $grund = trim($grund);
        if ($grund === '') {
            return ['erfolg' => false, 'fehler' => 'Bitte einen Grund angeben — er geht per Mail an den Kunden.'];
        }
        try {
            $this->client($this->shop($shopId))->connector('POST', 'widerrufe/' . $antragId . '/ablehnen', ['grund' => $grund]);
        } catch (Throwable $e) {
            return ['erfolg' => false, 'fehler' => 'Ablehnen im Shop fehlgeschlagen: ' . $e->getMessage()];
        }
        Logger::log('shop.widerruf_antrag_abgelehnt', 'shops', $shopId, ['antrag' => $antragId, 'grund' => $grund]);
        $this->db->prepare("UPDATE shops SET widerrufe_ungeklaert = GREATEST(widerrufe_ungeklaert - 1, 0) WHERE id = ?")->execute([$shopId]);
        return ['erfolg' => true, 'text' => 'Antrag abgelehnt, der Kunde bekommt die Begründung per Mail.'];
    }

    private function shop(int $shopId): array
    {
        $stmt = $this->db->prepare("SELECT id, name, wc_url, wc_key, wc_secret, wp_username, wp_app_password FROM shops WHERE id = ?");
        $stmt->execute([$shopId]);
        $shop = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$shop || !$shop['wc_url'] || !$shop['wp_username'] || !$shop['wp_app_password']) {
            throw new RuntimeException('Für diesen Shop fehlt die WordPress-Anmeldung (Einstellungen → Kanäle)');
        }
        return $shop;
    }

    private function client(array $shop): WooCommerceClient
    {
        return new WooCommerceClient($shop['wc_url'], (string)$shop['wc_key'], (string)$shop['wc_secret'], $shop['wp_username'], $shop['wp_app_password']);
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
