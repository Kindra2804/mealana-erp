<?php

require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/PartnerRepository.php';
require_once __DIR__ . '/PartnerLagerService.php';
require_once __DIR__ . '/../lager/LagerService.php';
require_once __DIR__ . '/../shop/ShopSyncRepository.php';

/**
 * Eigentümer von Artikeln wechseln (Migration 237, Jacky 2026-11-13: Indra-Design wird EU von Babsi).
 *
 * Ein Artikel gehört entweder der Hauptfirma (artikel.partner_id NULL, partner_modus 'eigen') oder einem
 * Partner (partner_id + partner_modus = Verkaufsart des Partners). Beim Wechsel:
 * - Kind-Artikel eines Vaters und die fertigen Schilder eines Konfigurator-Layouts wechseln mit.
 * - Der Bestand wird je Lager + Charge umgebucht: aus den eigenen Lagern ins Partner-Lager (wird bei
 *   Bedarf angelegt) bzw. zurück ins Hauptlager. Referenz "Eigentümerwechsel" im Bewegungslog.
 * - Shop-Zuordnungen bleiben; die Artikel werden für den nächsten Abgleich vorgemerkt.
 * Nur Partner mit "verkauft online" -- reine Laden-Partner pflegen ihre Ware über die Partnerseite (XP-Nummern).
 */
class ArtikelEigentumService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Partner, die Artikel besitzen dürfen (Auswahl in Massenaktion und Konfigurator). */
    public function moeglichePartner(): array
    {
        return $this->db->query("SELECT id, name, nr_kuerzel FROM partner WHERE aktiv = 1 AND online_verkauf = 1 ORDER BY name")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Hauptlager der Hauptfirma: erstes eigenes Lager, das kein Messelager ist. */
    private function hauptlagerId(): int
    {
        return (int) $this->db->query("SELECT id FROM lager WHERE lager_beziehung = 'eigen' AND typ <> 'messe' AND COALESCE(aktiv, 1) = 1 ORDER BY id LIMIT 1")->fetchColumn();
    }

    /** Ausgewählte Artikel + ihre Kinder + fertige Schilder ihrer Layouts (+ deren Kinder). */
    private function erweitere(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        for ($runde = 0; $runde < 3 && $ids; $runde++) {
            $in = implode(',', $ids);
            $neu = $this->db->query("
                SELECT id FROM artikel WHERE vaterartikel_id IN ($in)
                UNION SELECT artikel_id FROM konfig_fertige_artikel WHERE layout_artikel_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
            $vorher = count($ids);
            $ids = array_values(array_unique(array_merge($ids, array_map('intval', $neu))));
            if (count($ids) === $vorher) break;
        }
        return $ids;
    }

    /**
     * Setzt den Eigentümer. $partnerId null = Hauptfirma.
     * Rückgabe: ['erfolg', 'artikel' => Anzahl geändert, 'umgebucht' => Stück, 'hinweise' => [...]]
     */
    public function setze(array $artikelIds, ?int $partnerId, int $benutzerId): array
    {
        $partner = null;
        if ($partnerId) {
            $partner = (new PartnerRepository())->findById($partnerId);
            if (!$partner || !$partner['aktiv'] || !$partner['online_verkauf']) {
                return ['erfolg' => false, 'fehler' => ['Nur aktive Partner mit „verkauft online“ können Artikel besitzen.']];
            }
        }
        $ids = $this->erweitere($artikelIds);
        if (!$ids) return ['erfolg' => false, 'fehler' => ['Keine Artikel ausgewählt.']];

        $lagerSvc = new PartnerLagerService();
        $zielLager = null;
        if ($partner) {
            if (!$lagerSvc->getLager($partnerId)) $lagerSvc->lagerAnlegen($partnerId);
            $zielLager = (int) $lagerSvc->getLager($partnerId)['id'];
        } else {
            $zielLager = $this->hauptlagerId();
        }
        if (!$zielLager) return ['erfolg' => false, 'fehler' => ['Kein Ziellager gefunden.']];

        $modus = $partner ? $partner['typ'] : 'eigen';
        $in = implode(',', $ids);
        $artikel = $this->db->query("SELECT id, artikelnummer, partner_id FROM artikel WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);

        $lager = new LagerService();
        $geaendert = 0; $stueck = 0.0; $hinweise = [];
        $referenz = 'Eigentümerwechsel → ' . ($partner ? $partner['name'] : 'Hauptfirma');
        $upd = $this->db->prepare("UPDATE artikel SET partner_id = ?, partner_modus = ? WHERE id = ?");
        // Quelle: Hauptfirma -> alle eigenen Lager; Partner -> sein Partner-Lager
        $bestandEigen   = $this->db->prepare("SELECT lb.lager_id, lb.charge, lb.bestand FROM lagerbestand lb JOIN lager l ON l.id = lb.lager_id
                                              WHERE lb.artikel_id = ? AND lb.bestand > 0 AND l.lager_beziehung = 'eigen'");
        $bestandPartner = $this->db->prepare("SELECT lb.lager_id, lb.charge, lb.bestand FROM lagerbestand lb JOIN lager l ON l.id = lb.lager_id
                                              WHERE lb.artikel_id = ? AND lb.bestand > 0 AND l.lager_beziehung = 'partner_bestand' AND l.partner_id = ?");

        $eigeneTx = !$this->db->inTransaction();
        if ($eigeneTx) $this->db->beginTransaction();
        try {
            foreach ($artikel as $a) {
                $alterPartner = $a['partner_id'] ? (int) $a['partner_id'] : null;
                if ($alterPartner === $partnerId) continue;
                $upd->execute([$partnerId, $modus, (int) $a['id']]);
                $geaendert++;

                if ($alterPartner) $bestandPartner->execute([(int) $a['id'], $alterPartner]);
                $quelle = $alterPartner ? $bestandPartner : $bestandEigen;
                if (!$alterPartner) $bestandEigen->execute([(int) $a['id']]);
                foreach ($quelle->fetchAll(PDO::FETCH_ASSOC) as $b) {
                    if ((int) $b['lager_id'] === $zielLager) continue;
                    $r = $lager->umbucheZwischenLager((int) $a['id'], (int) $b['lager_id'], $zielLager, (float) $b['bestand'],
                                                      $benutzerId, $b['charge'], $referenz);
                    if (empty($r['erfolg'])) {
                        throw new RuntimeException("{$a['artikelnummer']}: " . ($r['fehler'] ?? 'Umbuchung fehlgeschlagen'));
                    }
                    $stueck += (float) $b['bestand'];
                }
            }
            if ($eigeneTx) $this->db->commit();
        } catch (Throwable $e) {
            if ($eigeneTx && $this->db->inTransaction()) $this->db->rollBack();
            if (!$eigeneTx) throw $e;
            return ['erfolg' => false, 'fehler' => ['Nichts geändert: ' . $e->getMessage()]];
        }

        if ($geaendert) {
            (new ShopSyncRepository())->markiereFuerErneutenSync($ids);
            Logger::log('artikel.eigentuemer', 'artikel', (int) $ids[0], [
                'partner_id' => $partnerId, 'artikel' => $geaendert, 'umgebucht' => $stueck, 'ids' => $ids,
            ], $benutzerId);
        }
        if (count($ids) > count(array_unique(array_map('intval', $artikelIds)))) {
            $hinweise[] = 'Kind-Artikel bzw. fertige Schilder wurden mit übernommen.';
        }
        return ['erfolg' => true, 'artikel' => $geaendert, 'umgebucht' => $stueck, 'hinweise' => $hinweise];
    }
}
