<?php

require_once __DIR__ . '/MenueRepository.php';
require_once __DIR__ . '/SliderRepository.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../shop/WooCommerceClient.php';

/**
 * MenueService – Hauptmenü je Shop im ERP (Migration 226).
 *
 * Ein Menüpunkt zeigt auf eine Kategorie (optional mit allen Unterkategorien automatisch),
 * auf „alle Hersteller“ (im Shop dynamisch, in Spalten), auf einen Hersteller, einen Artikel
 * oder eine Adresse. Maximal 3 Ebenen (Jacky 2026-11-05).
 *
 * uebertrage() schickt eine flache Liste (schluessel/eltern) an das Plugin (POST nahtlos/v1/menue).
 * Das Plugin baut daraus das WP-Menü „NAHTLOS Hauptmenü“ und hängt es an die Theme-Orte.
 * Automatische Unterkategorien löst das ERP hier beim Übertragen auf.
 */
class MenueService
{
    public const MAX_EBENEN = 3;
    public const TYPEN = ['kategorie', 'hersteller_alle', 'hersteller', 'artikel', 'url'];

    private MenueRepository $repo;
    private SliderRepository $shopRepo;

    public function __construct()
    {
        $this->repo = new MenueRepository();
        $this->shopRepo = new SliderRepository();
    }

    public function getRepo(): MenueRepository { return $this->repo; }
    public function getShopRepo(): SliderRepository { return $this->shopRepo; }

    /** Menüpunkte als Baum (Kinder unter 'kinder'). */
    public function baum(int $shopId): array
    {
        $alle = $this->repo->findByShop($shopId);
        $nachEltern = [];
        foreach ($alle as $p) $nachEltern[(int)$p['parent_id']][] = $p;
        $bauen = function (int $eltern) use (&$bauen, $nachEltern) {
            return array_map(fn($p) => $p + ['kinder' => $bauen((int)$p['id'])], $nachEltern[$eltern] ?? []);
        };
        return $bauen(0);
    }

    public function speichere(array $d): array
    {
        $id  = (int)($d['id'] ?? 0);
        $alt = $id ? $this->repo->findById($id) : null;
        if ($id && !$alt) return ['erfolg' => false, 'fehler' => ['Menüpunkt nicht gefunden']];

        $d = array_intersect_key($d, array_flip(MenueRepository::FELDER));
        foreach ($d as $k => $v) if (is_string($v)) $d[$k] = trim($v) === '' ? null : trim($v);
        if ($alt) $d['shop_id'] = $alt['shop_id'];
        foreach (['unterkategorien_auto', 'nur_mit_artikeln', 'spalten', 'ist_aktiv'] as $k) $d[$k] = !empty($d[$k]) ? 1 : 0;
        foreach (['parent_id', 'ziel_id'] as $k) $d[$k] = !empty($d[$k]) ? (int)$d[$k] : null;

        $f = [];
        if (empty($d['shop_id']) || !$this->shopRepo->findShop((int)$d['shop_id'])) $f[] = 'Bitte einen Shop wählen.';
        if (!in_array($d['typ'] ?? '', self::TYPEN, true)) $f[] = 'Bitte wählen, worauf der Menüpunkt zeigt.';
        $typ = $d['typ'] ?? '';
        if (in_array($typ, ['kategorie', 'hersteller', 'artikel'], true) && !$d['ziel_id']) {
            $f[] = 'Bitte ' . ['kategorie' => 'die Kategorie', 'hersteller' => 'den Hersteller', 'artikel' => 'den Artikel'][$typ] . ' wählen.';
        }
        if ($typ === 'url' && !preg_match('#^(https?://|/)#', (string)$d['url'])) $f[] = 'Die Adresse muss mit https:// oder / beginnen.';
        if (in_array($typ, ['url', 'hersteller_alle'], true) && empty($d['bezeichnung'])) $f[] = 'Bitte eine Bezeichnung eingeben.';

        // Ebenen: Elternpunkt muss im selben Shop sein, nicht der Punkt selbst / ein eigener Unterpunkt, max. 3 Ebenen gesamt
        if ($d['parent_id']) {
            $eltern = $this->repo->findById($d['parent_id']);
            if (!$eltern || (int)$eltern['shop_id'] !== (int)$d['shop_id']) $f[] = 'Übergeordneter Menüpunkt nicht gefunden.';
            elseif ($eltern['typ'] === 'hersteller_alle') $f[] = 'Unter „Alle Hersteller“ können keine eigenen Punkte hängen.';
            elseif ($id && $this->istNachfahre((int)$d['parent_id'], $id)) $f[] = 'Ein Menüpunkt kann nicht unter sich selbst hängen.';
            else {
                $ebene = $this->repo->ebene($d['parent_id']) + 1;
                $tiefe = $id ? $this->repo->teilbaumTiefe($id) : 0;
                if ($ebene + $tiefe > self::MAX_EBENEN) $f[] = 'Das Menü hat höchstens ' . self::MAX_EBENEN . ' Ebenen.';
            }
        }
        if ($f) return ['erfolg' => false, 'fehler' => $f];

        // Felder leeren, die zum Typ nicht passen
        if ($typ !== 'url') $d['url'] = null;
        if (in_array($typ, ['url', 'hersteller_alle'], true)) $d['ziel_id'] = null;
        if ($typ !== 'kategorie') $d['unterkategorien_auto'] = 0;
        if (!in_array($typ, ['kategorie', 'hersteller'], true)) $d['nur_mit_artikeln'] = 0;
        if ($typ !== 'hersteller_alle') $d['spalten'] = 1;

        if ($alt) {
            // Wechsel des Elternpunkts: ans Ende der neuen Ebene
            $this->repo->update($id, $d);
            Logger::log('menue.bearbeiten', 'shop_menue_punkte', $id, ['typ' => $typ]);
        } else {
            $id = $this->repo->insert($d);
            Logger::log('menue.anlegen', 'shop_menue_punkte', $id, ['typ' => $typ, 'shop_id' => $d['shop_id']]);
        }
        $this->repo->markiereGeaendert((int)$d['shop_id']);
        return ['erfolg' => true, 'id' => $id];
    }

    private function istNachfahre(int $kandidat, int $von): bool
    {
        for ($i = 0, $p = $kandidat; $p && $i < 10; $i++) {
            if ($p === $von) return true;
            $punkt = $this->repo->findById($p);
            $p = $punkt ? (int)$punkt['parent_id'] : 0;
        }
        return false;
    }

    public function loesche(int $id): array
    {
        $p = $this->repo->findById($id);
        if (!$p) return ['erfolg' => false, 'fehler' => 'Menüpunkt nicht gefunden'];
        $this->repo->delete($id);
        $this->repo->markiereGeaendert((int)$p['shop_id']);
        Logger::log('menue.loeschen', 'shop_menue_punkte', $id, ['typ' => $p['typ']]);
        return ['erfolg' => true];
    }

    public function setAktiv(int $id, bool $aktiv): array
    {
        $p = $this->repo->findById($id);
        if (!$p) return ['erfolg' => false, 'fehler' => 'Menüpunkt nicht gefunden'];
        $this->repo->update($id, ['ist_aktiv' => $aktiv ? 1 : 0]);
        $this->repo->markiereGeaendert((int)$p['shop_id']);
        return ['erfolg' => true];
    }

    public function sortiere(int $shopId, array $ids): array
    {
        $this->repo->sortiere($ids);
        $this->repo->markiereGeaendert($shopId);
        return ['erfolg' => true];
    }

    /** Erster Entwurf: Hauptkategorien des Shops, je mit Unterkategorien automatisch. Nur bei leerem Menü. */
    public function vorschlag(int $shopId): array
    {
        if ($this->repo->anzahl($shopId) > 0) return ['erfolg' => false, 'fehler' => 'Das Menü ist nicht leer. Der Vorschlag geht nur bei einem leeren Menü.'];
        $haupt = $this->repo->findShopHauptkategorien($shopId);
        if (!$haupt) return ['erfolg' => false, 'fehler' => 'Im Shop gibt es noch keine Kategorien (erst den Kategorie-Abgleich laufen lassen).'];
        foreach ($haupt as $k) {
            $this->repo->insert(['shop_id' => $shopId, 'parent_id' => null, 'typ' => 'kategorie', 'ziel_id' => (int)$k['id'],
                'unterkategorien_auto' => 1, 'nur_mit_artikeln' => 0, 'spalten' => 1, 'ist_aktiv' => 1]);
        }
        $this->repo->markiereGeaendert($shopId);
        Logger::log('menue.vorschlag', 'shops', $shopId, ['punkte' => count($haupt)]);
        return ['erfolg' => true, 'punkte' => count($haupt)];
    }

    // ── Übertragen ─────────────────────────────────────────────────────────

    /** Baut die flache Liste für das Plugin. Hinweise für Ziele, die (noch) nicht im Shop sind. */
    public function bauePayload(int $shopId, array &$hinweise): array
    {
        $punkte = [];
        $add = function (array $p, int $ebene, ?string $eltern) use (&$add, &$punkte, &$hinweise, $shopId) {
            if (!$p['ist_aktiv']) return;
            $schluessel = 'm' . $p['id'];
            $titel = $p['bezeichnung'] ?: ($p['ziel_name'] ?? '');
            $ziel = null;
            $typ = $p['typ'] === 'artikel' ? 'produkt' : $p['typ'];
            if (in_array($p['typ'], ['kategorie', 'hersteller', 'artikel'], true)) {
                $ziel = $this->shopRepo->findExterneZielId($p['typ'], (int)$p['ziel_id'], $shopId);
                if ($ziel === null) { $hinweise[] = "„{$titel}“ ist (noch) nicht im Shop — weggelassen."; return; }
                $ziel = (int)$ziel;
            } elseif ($p['typ'] === 'url') {
                $ziel = $p['url'];
            }
            $punkte[] = ['schluessel' => $schluessel, 'eltern' => $eltern, 'titel' => $titel, 'typ' => $typ, 'ziel' => $ziel,
                         'nur_mit_artikeln' => (bool)$p['nur_mit_artikeln'], 'spalten' => (bool)$p['spalten']];
            foreach ($p['kinder'] as $k) if ($ebene < self::MAX_EBENEN) $add($k, $ebene + 1, $schluessel);
            if ($p['typ'] === 'kategorie' && $p['unterkategorien_auto']) $this->unterkategorien((int)$p['ziel_id'], $shopId, $ebene + 1, $schluessel, $punkte);
        };
        foreach ($this->baum($shopId) as $p) $add($p, 1, null);
        return $punkte;
    }

    /** Unterkategorien automatisch, rekursiv bis Ebene 3. */
    private function unterkategorien(int $kategorieId, int $shopId, int $ebene, string $eltern, array &$punkte): void
    {
        if ($ebene > self::MAX_EBENEN) return;
        foreach ($this->repo->findShopUnterkategorien($kategorieId, $shopId) as $k) {
            $schluessel = $eltern . '-k' . $k['id'];
            $punkte[] = ['schluessel' => $schluessel, 'eltern' => $eltern, 'titel' => $k['name'], 'typ' => 'kategorie',
                         'ziel' => (int)$k['externe_kategorie_id'], 'nur_mit_artikeln' => false, 'spalten' => false];
            $this->unterkategorien((int)$k['id'], $shopId, $ebene + 1, $schluessel, $punkte);
        }
    }

    public function uebertrage(int $shopId, ?int $benutzerId = null): array
    {
        $shop = $this->shopRepo->findShop($shopId);
        $stand = $this->repo->findShopStand($shopId);
        if (!$shop || !$stand) return ['erfolg' => false, 'fehler' => 'Shop nicht gefunden'];
        if (!$shop['wc_url'] || !$shop['wp_username'] || !$shop['wp_app_password']) {
            return ['erfolg' => false, 'fehler' => 'Für diesen Shop fehlt die WordPress-Anmeldung (Einstellungen → Kanäle).'];
        }
        $hinweise = [];
        try {
            $punkte = $this->bauePayload($shopId, $hinweise);
            $client = new WooCommerceClient($shop['wc_url'], (string)$shop['wc_key'], (string)$shop['wc_secret'], $shop['wp_username'], $shop['wp_app_password']);
            $antwort = $client->connector('POST', 'menue', [
                'version' => 1,
                'orte'    => array_values(array_filter(array_map('trim', explode(',', (string)$stand['menue_orte'])))),
                'punkte'  => $punkte,
            ]);
        } catch (Throwable $e) {
            $this->repo->markiereUebertragen($shopId, $e->getMessage());
            Logger::log('menue.uebertragen', 'shops', $shopId, ['fehler' => $e->getMessage()], $benutzerId, 'error');
            return ['erfolg' => false, 'fehler' => $e->getMessage(), 'hinweise' => $hinweise];
        }
        $this->repo->markiereUebertragen($shopId, null);
        Logger::log('menue.uebertragen', 'shops', $shopId, ['punkte' => count($punkte), 'hinweise' => $hinweise], $benutzerId);
        return ['erfolg' => true, 'uebertragen' => count($punkte), 'hinweise' => $hinweise, 'antwort' => $antwort];
    }

    /**
     * Cron: Menüs mit automatischen Unterkategorien neu übertragen, wenn sich Kategorien geändert haben
     * und im ERP keine unübertragenen Änderungen offen sind (die bleiben ein bewusster Klick).
     */
    public function cronUebertrage(int $systemBenutzerId, ?callable $log = null): void
    {
        foreach ($this->shopRepo->findShops() as $shop) {
            if ((int)$shop['sync_pausiert'] === 1) continue; // pausierter Shop: der Cron schreibt nichts hinein
            $stand = $this->repo->findShopStand((int)$shop['id']);
            if (!$stand || !$stand['menue_uebertragen_am'] || (int)$stand['menue_geaendert'] === 1) continue;
            $auto = array_filter($this->repo->findByShop((int)$shop['id']), fn($p) => $p['unterkategorien_auto'] && $p['ist_aktiv']);
            if (!$auto || !$this->repo->kategorienGeaendertSeit($stand['menue_uebertragen_am'], (int)$shop['id'])) continue;
            $r = $this->uebertrage((int)$shop['id'], $systemBenutzerId);
            if ($log) $log("Menü {$shop['name']}: " . ($r['erfolg'] ? "{$r['uebertragen']} Punkte übertragen" : 'FEHLER ' . $r['fehler']));
        }
    }
}
