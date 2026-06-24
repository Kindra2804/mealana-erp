<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/LagerRepository.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';

/**
 * LagerService – Geschäftslogik für Wareneingänge, Chargen und Lagerbestand
 *
 * Koordiniert LagerRepository und ArtikelRepository.
 * Kernmethode: wareneingang() bucht Menge in Lager und protokolliert die Bewegung.
 *
 * Auslaufartikel-Automatik (pruefAuslaufartikelStatus):
 *   ist_auslaufartikel = 1 → bei Bestand 0: Artikel automatisch deaktivieren (aktiv = 0)
 *                         → bei Bestand > 0 nach Eingang: automatisch reaktivieren
 *   Bei Kind-Artikeln: Vater-Status folgt automatisch (aktiv wenn min. 1 Kind aktiv).
 *   Alle Statusänderungen werden als Jarvis-Log-Einträge protokolliert.
 *
 * Charge-Nachtrag (chargeNachtragen):
 *   Wenn beim Wareneingang keine Charge erfasst wurde (charge_status = 'nachzutragen'),
 *   kann die Charge nachträglich eingetragen werden. Die Menge wird von der Null-Charge-Zeile
 *   auf eine neue Charge-Zeile umgebucht. Wenn die Null-Charge-Zeile leer wird, wird sie gelöscht.
 *
 * Jarvis = System-User (username='system') für automatische Log-Einträge ohne menschliche Session.
 */
class LagerService
{
    private LagerRepository   $repo;
    private ArtikelRepository $artikelRepo;

    public function __construct()
    {
        $this->repo        = new LagerRepository();
        $this->artikelRepo = new ArtikelRepository();
    }

    /**
     * Gibt die ID des System-Users "Jarvis" zurück.
     * Jarvis führt automatische Aktionen durch (Auslaufartikel-Status etc.)
     * und wird für Logger::log() als $benutzerId übergeben.
     */
    private function getJarvisId(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM benutzer WHERE username = 'system'");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Prüft ob der Status eines Auslaufartikels angepasst werden muss.
     * Wird nach jedem Wareneingang aufgerufen (Reaktivierung möglich).
     *
     * Logik:
     *   ist_auslaufartikel = 0 → nichts tun
     *   Neuer Bestand > 0 + Artikel gerade inaktiv → reaktivieren
     *   Neuer Bestand = 0 + Artikel gerade aktiv → deaktivieren
     *   Bei Kind-Artikel: Vater-Aktivstatus abhängig von Anzahl aktiver Kinder
     */
    private function pruefAuslaufartikelStatus(int $artikelId, float $neuerBestand): void
    {
        $artikel = $this->artikelRepo->findById($artikelId);
        if (!$artikel || !$artikel['ist_auslaufartikel']) return;

        $sollAktiv = $neuerBestand > 0 ? 1 : 0;
        if ($artikel['aktiv'] == $sollAktiv) return; // Keine Änderung nötig

        $jarvisId = $this->getJarvisId();

        $this->artikelRepo->setArtikelAktiv($artikelId, $sollAktiv);
        Logger::log('artikel_aktiv.geaendert', 'artikel', $artikelId, [
            'aktiv'        => $sollAktiv,
            'artikelnummer' => $artikel['artikelnummer'],
        ], $jarvisId);

        // Bei Kind-Artikel: Vater-Status prüfen ob er noch aktive Kinder hat
        if ($artikel['vaterartikel_id']) {
            $nochKinderAktiv = $this->artikelRepo->countAktiveKinder($artikel['vaterartikel_id']);
            $vater = $this->artikelRepo->findById($artikel['vaterartikel_id']);
            $vaterSollAktiv = $nochKinderAktiv > 0 ? 1 : 0;

            if ($vater && $vater['aktiv'] != $vaterSollAktiv) {
                $this->artikelRepo->setArtikelAktiv($artikel['vaterartikel_id'], $vaterSollAktiv);
                Logger::log('artikel_aktiv.geaendert', 'artikel', $artikel['vaterartikel_id'], [
                    'aktiv'        => $vaterSollAktiv,
                    'artikelnummer' => $vater['artikelnummer'],
                ], $jarvisId);
            }
        }
    }

    /**
     * Bucht einen Wareneingang in den Lagerbestand.
     *
     * Pflichtfelder: artikel_id, lager_id, menge > 0.
     * Optionale Felder: charge, lieferant_id, ek_preis, notiz, referenz, mindestbestand.
     *
     * Ablauf:
     * 1. Validierung
     * 2. Optional: Auslaufartikel reaktivieren wenn reaktivieren = 1 gesetzt
     * 3. Aktuellen Bestand lesen
     * 4. Neuen Bestand berechnen
     * 5. Charge-Status setzen (erfasst/nachzutragen/null)
     * 6. Lagerbestand upserten
     * 7. Auslaufartikel-Status prüfen
     * 8. Lagerbewegung protokollieren
     */
    public function wareneingang(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $artikelId = (int) $data['artikel_id'];

        // Optionale manuelle Reaktivierung eines Auslaufartikels
        if (!empty($data['reaktivieren']) && $data['reaktivieren'] == '1') {
            $this->artikelRepo->setAuslaufartikelAktiv($artikelId, 1);
            $this->artikelRepo->setArtikelAktiv($artikelId, 1);
            // Bei Vater-Artikel: alle Kinder ebenfalls reaktivieren
            $this->artikelRepo->propagateAuslaufZuKindern($artikelId, 1);
            Logger::log('artikel.reaktiviert', 'artikel', $artikelId, [
                'lager_id' => $data['lager_id'],
                'menge'    => $data['menge'],
            ], $data['benutzer_id'] ?? null);
        }

        $bestandVorher = $this->repo->getBestand(
            $artikelId,
            (int) $data['lager_id'],
            $data['charge'] ?? null
        );

        $bestandNachher = $bestandVorher + (float) $data['menge'];

        $chargePflicht = $this->repo->getChargePflicht($artikelId);

        $this->repo->upsertBestand([
            'artikel_id'    => $artikelId,
            'lager_id'      => $data['lager_id'],
            'charge'        => $data['charge'] ?? null,
            'charge_status' => !empty($data['charge'])
                ? 'erfasst'
                : ($chargePflicht ? 'nachzutragen' : null),
            'bestand'       => $bestandNachher,
            'mindestbestand' => $data['mindestbestand'] ?? 0,
        ]);

        // Nach dem Buchen: Auslaufartikel-Automatik prüfen (Reaktivierung bei Bestand > 0)
        $this->pruefAuslaufartikelStatus($artikelId, $bestandNachher);

        $bewegungId = $this->repo->insertBewegung([
            'artikel_id'    => $artikelId,
            'lager_id'      => $data['lager_id'],
            'lieferant_id'  => $data['lieferant_id'] ?? null,
            'ek_preis'      => $data['ek_preis'] ?? null,
            'charge'        => $data['charge'] ?? null,
            'bewegungstyp'  => 'eingang',
            'menge'         => $data['menge'],
            'bestand_vorher'  => $bestandVorher,
            'bestand_nachher' => $bestandNachher,
            'referenz'      => $data['referenz'] ?? null,
            'notiz'         => $data['notiz'] ?? null,
            'benutzer_id'   => $data['benutzer_id'] ?? null,
        ]);

        Logger::log('wareneingang.buchen', 'lagerbestand', $bewegungId, [
            'artikel_id'     => $artikelId,
            'lager_id'       => $data['lager_id'],
            'menge'          => $data['menge'],
            'bestand_nachher' => $bestandNachher,
        ]);

        return ['erfolg' => true];
    }

    /** Validiert Pflichtfelder des Wareneingangs: artikel_id, lager_id, menge > 0. */
    private function validiere(array $data): array
    {
        $fehler = [];
        if (empty($data['artikel_id'])) {
            $fehler[] = 'Artikel muss ausgewählt sein';
        }
        if (empty($data['lager_id'])) {
            $fehler[] = 'Lager ist Pflichtfeld';
        }
        if (empty($data['menge']) || $data['menge'] <= 0) {
            $fehler[] = 'Menge muss größer als 0 sein';
        }
        return $fehler;
    }

    /** Gibt die UNION-ALL Lager-Übersicht zurück (alle Artikel mit Bestand > 0). */
    public function getUebersicht(): array
    {
        return $this->repo->findUebersicht();
    }

    /**
     * Trägt eine Charge für einen bestehenden Lagerbestand-Eintrag (charge_status = 'nachzutragen') nach.
     *
     * Ablauf:
     * 1. Lagerbestand-ID prüfen (existiert, Menge nicht überschritten)
     * 2. Existierende Charge-Zeile (oder neue) mit Menge erhöhen
     * 3. Null-Charge-Zeile um die umgebuchte Menge verringern (oder löschen)
     * 4. Lagerbewegung vom Typ 'korrektur' protokollieren
     */
    public function chargeNachtragen(int $lagerbestand_id, string $charge, float $menge, ?int $benutzerId = null): array
    {
        if (empty($charge)) {
            return ['erfolg' => false, 'fehler' => 'Charge darf nicht leer sein'];
        }
        if ($menge <= 0) {
            return ['erfolg' => false, 'fehler' => 'Gültige Menge eingeben'];
        }

        $lb = $this->repo->findLagerbestandById($lagerbestand_id);
        if (!$lb) {
            return ['erfolg' => false, 'fehler' => 'Lagerbestands-ID nicht gefunden'];
        }
        if ($menge > $lb['bestand']) {
            return ['erfolg' => false, 'fehler' => 'Menge überschreitet Bestand des Artikels'];
        }

        $artikelId = (int) $lb['artikel_id'];
        $vorhanden = $this->repo->getBestand($artikelId, $lb['lager_id'], $charge);

        // Neue Charge-Zeile anlegen oder bestehende erhöhen
        $this->repo->upsertBestand([
            'artikel_id'    => $artikelId,
            'lager_id'      => $lb['lager_id'],
            'charge'        => $charge,
            'charge_status' => 'erfasst',
            'bestand'       => $vorhanden + $menge,
            'mindestbestand' => 0,
        ]);

        // Null-Charge-Zeile verringern oder löschen
        $neuerNullBestand = $lb['bestand'] - $menge;
        if ($neuerNullBestand <= 0) {
            $this->repo->deleteBestand($lb['id']);
        } else {
            $this->repo->updateBestandMenge($lb['id'], $neuerNullBestand);
        }

        $this->repo->insertBewegung([
            'artikel_id'      => $artikelId,
            'lager_id'        => $lb['lager_id'],
            'lieferant_id'    => null,
            'ek_preis'        => null,
            'charge'          => $charge,
            'bewegungstyp'    => 'korrektur',
            'menge'           => $menge,
            'bestand_vorher'  => $lb['bestand'],
            'bestand_nachher' => $lb['bestand'] - $menge,
            'referenz'        => null,
            'notiz'           => 'Charge nachgetragen',
            'benutzer_id'     => $benutzerId,
        ]);

        Logger::log('lager.charge_nachtragen', 'lagerbestand', $lagerbestand_id, ['charge' => $charge]);

        return ['erfolg' => true];
    }

    /** Gibt alle Lagerbestand-Einträge zurück bei denen die Charge noch nachzutragen ist. */
    public function getNachzutragendeChargen(): array
    {
        return $this->repo->findNachzutragendeChargen();
    }

    /** Gibt alle aktiven Lager zurück (für Dropdown). */
    public function getAlleLager(): array
    {
        return $this->repo->findAlleLager();
    }

    /**
     * Gibt den Bestand pro Lager + Charge für einen Artikel zurück.
     * Gruppiert nach Lager-ID mit Summe (gesamt) + Chargen-Liste.
     */
    public function getLagerBestandChargen(int $artikelId): array
    {
        return $this->repo->findBestandChargeProLager($artikelId);
    }

    /** Gibt die letzten 10 Lagerbewegungen für einen Artikel zurück. */
    public function getBewegungslog(int $artikelId): array
    {
        return $this->repo->findBewegungslogFuerArtikel($artikelId);
    }
}
