<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/AuftragRepository.php';

/**
 * AuftragService – Geschäftslogik für Verkaufsaufträge.
 *
 * Status-Flow Zahlungsstatus: ausstehend → bezahlt | erstattet | storniert
 * Status-Flow Lieferstatus:   neu → in_bearbeitung → versandbereit → versendet → abgeschlossen
 *                             → teilgeliefert → abgeschlossen (Fehlbestand-Auflösung)
 *                             → zurueckgestellt (Fehlbestand, Ware fehlt ganz)
 *
 * Nummernkreise laufen über dokument_nummern: auftrag=A-2026-00001, rechnung=R-2026-00001.
 * Snapshots (kunden_snapshot etc.) frieren Adressdaten zum Auftragszeitpunkt ein.
 */
class AuftragService
{
    private AuftragRepository $repo;

    public function __construct()
    {
        $this->repo = new AuftragRepository();
    }

    /** Gibt alle Aufträge zurück, optional gefiltert. */
    public function getAll(
        string $zahlungsstatus = '',
        string $lieferstatus = '',
        string $kanal = '',
        string $suche = ''
    ): array {
        return $this->repo->findAll($zahlungsstatus, $lieferstatus, $kanal, $suche);
    }

    /** Gibt einen Auftrag anhand ID zurück. */
    public function getById(int $id): array|false
    {
        return $this->repo->findById($id);
    }

    /** Gibt alle Positionen eines Auftrags zurück. */
    public function getPositionen(int $auftragId): array
    {
        return $this->repo->findPositionen($auftragId);
    }

    /** Gibt den Statuslog eines Auftrags zurück. */
    public function getStatuslog(int $auftragId): array
    {
        return $this->repo->findStatuslog($auftragId);
    }

    /** Gibt Artikel für den Positions-Typeahead zurück. */
    public function getArtikelFuerSuche(string $suche): array
    {
        return $this->repo->findArtikelFuerSuche($suche);
    }

    /** Gibt alle überfälligen Vorkasse-Aufträge für Mahnung/Stornierung zurück. */
    public function getVorkasseUeberfaellig(): array
    {
        return $this->repo->findVorkasseUeberfaellig();
    }

    /**
     * Legt einen neuen Auftrag mit Positionen an.
     *
     * Berechnet Netto, Steuer, Brutto aus den Positionen.
     * Friert Kunden-Adresse als JSON-Snapshot ein.
     * Mindestens eine Position ist Pflicht.
     */
    public function anlegen(array $data, array $positionen): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $berechnetePos = $this->berechnePositionen($positionen);
        if (empty($berechnetePos)) {
            return ['erfolg' => false, 'fehler' => ['Mindestens eine gültige Position ist erforderlich']];
        }

        $summen = $this->berechneSummen($berechnetePos);

        $kunden_snapshot = null;
        if (!empty($data['kunden_snapshot']) && is_array($data['kunden_snapshot'])) {
            $kunden_snapshot = json_encode($data['kunden_snapshot'], JSON_UNESCAPED_UNICODE);
        }

        $auftragData = [
            'kunden_id'                 => !empty($data['kunden_id'])   ? (int)$data['kunden_id']   : null,
            'kunden_snapshot'           => $kunden_snapshot,
            'lieferadresse_snapshot'    => !empty($data['lieferadresse_snapshot'])    ? json_encode($data['lieferadresse_snapshot'], JSON_UNESCAPED_UNICODE)    : null,
            'rechnungsadresse_snapshot' => !empty($data['rechnungsadresse_snapshot']) ? json_encode($data['rechnungsadresse_snapshot'], JSON_UNESCAPED_UNICODE) : null,
            'kanal'                     => $data['kanal'] ?? 'manuell',
            'kanal_auftrag_id'          => !empty($data['kanal_auftrag_id']) ? (int)$data['kanal_auftrag_id'] : null,
            'zahlungsstatus'            => 'ausstehend',
            'lieferstatus'              => 'neu',
            'zahlungsart'               => $data['zahlungsart'] ?? 'vorkasse',
            'lieferart'                 => $data['lieferart'] ?? 'versand',
            'versandklasse_id'          => !empty($data['versandklasse_id']) ? (int)$data['versandklasse_id'] : null,
            'zahlungsbedingung_id'      => !empty($data['zahlungsbedingung_id']) ? (int)$data['zahlungsbedingung_id'] : null,
            'gutschein_id'              => !empty($data['gutschein_id'])    ? (int)$data['gutschein_id']         : null,
            'gutschein_betrag'          => !empty($data['gutschein_betrag']) ? (float)$data['gutschein_betrag'] : 0.00,
            'versandkosten'             => !empty($data['versandkosten'])   ? (float)$data['versandkosten']     : 0.00,
            'rabatt_gesamt'             => !empty($data['rabatt_gesamt'])   ? (float)$data['rabatt_gesamt']     : 0.00,
            'nettobetrag'               => $summen['netto'],
            'steuerbetrag'              => $summen['steuer'],
            'bruttobetrag'              => $summen['brutto'],
            'notiz_intern'              => !empty($data['notiz_intern'])     ? $data['notiz_intern']     : null,
            'notiz_versand'             => !empty($data['notiz_versand'])   ? $data['notiz_versand']   : null,
            'kontakt_notiz'             => !empty($data['kontakt_notiz'])   ? $data['kontakt_notiz']   : null,
            'erstellt_von'              => $_SESSION['benutzer']['id'],
        ];

        $id = $this->repo->insert($auftragData);

        foreach ($berechnetePos as $i => $pos) {
            $this->repo->insertPosition(array_merge($pos, [
                'auftrag_id'      => $id,
                'sort_order'      => $i,
                'menge_geliefert' => 0,
            ]));
        }

        // Lagerreservierungen anlegen (für Bestand-Anzeige und Picklisten-Allocation)
        $this->repo->legeReservierungenAn($id, $berechnetePos, $auftragData['kanal'] ?? 'manuell');

        $this->repo->logStatus($id, ['lieferstatus' => [null, 'neu'], 'zahlungsstatus' => [null, 'ausstehend']], 'Auftrag angelegt', $_SESSION['benutzer']['id']);
        Logger::log('auftraege.anlegen', 'auftraege', $id, [
            'kanal'       => $auftragData['kanal'],
            'positionen'  => count($berechnetePos),
            'brutto'      => $summen['brutto'],
        ]);

        return ['erfolg' => true, 'id' => $id];
    }

    /**
     * Aktualisiert Zahlungs- und/oder Lieferstatus eines Auftrags.
     * Protokolliert jede Änderung im Statuslog.
     *
     * @param array $felder  Nur die zu ändernden Felder (z.B. ['zahlungsstatus' => 'bezahlt'])
     * @param string|null $notiz  Optionaler Kommentar für den Statuslog
     */
    public function statusAktualisieren(int $id, array $felder, ?string $notiz = null): array
    {
        $auftrag = $this->repo->findById($id);
        if (!$auftrag) {
            return ['erfolg' => false, 'fehler' => ['Auftrag nicht gefunden']];
        }

        $changes = [];
        $update  = [];

        $statusFelder = ['zahlungsstatus', 'lieferstatus', 'tracking_nr', 'versanddienstleister', 'notiz_intern', 'notiz_versand'];
        foreach ($statusFelder as $f) {
            if (array_key_exists($f, $felder) && $felder[$f] !== $auftrag[$f]) {
                $changes[$f] = [$auftrag[$f], $felder[$f]];
                $update[$f]  = $felder[$f];
            }
        }

        if (isset($felder['zahlungsstatus']) && $felder['zahlungsstatus'] === 'bezahlt' && empty($auftrag['bezahlt_am'])) {
            $update['bezahlt_am'] = date('Y-m-d H:i:s');
        }

        if (!empty($update)) {
            $this->repo->updateStatus($id, $update);
            $this->repo->logStatus($id, $changes, $notiz, $_SESSION['benutzer']['id']);
            Logger::log('auftraege.status', 'auftraege', $id, $changes);

            // Reservierungen schließen wenn Auftrag versendet oder abgeschlossen
            if (isset($changes['lieferstatus']) && in_array($changes['lieferstatus'][1], ['versendet', 'abgeschlossen'])) {
                $this->repo->schliesseReservierungen($id);
            }
        }

        return ['erfolg' => true];
    }

    /**
     * Storniert einen Auftrag.
     * Bereits versendete oder abgeschlossene Aufträge können nicht mehr storniert werden.
     */
    public function stornieren(int $id, ?string $notiz = null): array
    {
        $auftrag = $this->repo->findById($id);
        if (!$auftrag) {
            return ['erfolg' => false, 'fehler' => ['Auftrag nicht gefunden']];
        }
        if (in_array($auftrag['lieferstatus'], ['versendet', 'abgeschlossen'])) {
            return ['erfolg' => false, 'fehler' => ['Bereits versendete oder abgeschlossene Aufträge können nicht storniert werden']];
        }
        if ($auftrag['lieferstatus'] === 'storniert') {
            return ['erfolg' => false, 'fehler' => ['Auftrag ist bereits storniert']];
        }

        $this->repo->updateStatus($id, [
            'lieferstatus'    => 'storniert',
            'zahlungsstatus'  => 'storniert',
        ]);
        $this->repo->logStatus($id, [
            'lieferstatus'   => [$auftrag['lieferstatus'], 'storniert'],
            'zahlungsstatus' => [$auftrag['zahlungsstatus'], 'storniert'],
        ], $notiz ?? 'Auftrag storniert', $_SESSION['benutzer']['id']);

        $this->repo->schliesseReservierungen($id);

        Logger::log('auftraege.stornieren', 'auftraege', $id);
        return ['erfolg' => true];
    }

    /**
     * Berechnet Einzelpreis-Summen für eine Liste von Positions-Eingaben.
     * Überspringt Zeilen ohne artikel_id oder menge.
     */
    private function berechnePositionen(array $eingaben): array
    {
        $result = [];
        foreach ($eingaben as $pos) {
            if (empty($pos['artikel_id']) || empty($pos['menge'])) continue;
            $einzelNetto = round((float)($pos['einzelpreis_netto'] ?? 0), 4);
            $menge       = (int)$pos['menge'];
            $rabatt      = (float)($pos['rabatt_prozent'] ?? 0);
            $steuer      = (float)($pos['steuer_prozent'] ?? 20);

            $gesamtNetto = round($einzelNetto * $menge * (1 - $rabatt / 100), 2);

            $result[] = [
                'artikel_id'        => (int)$pos['artikel_id'],
                'charge'            => !empty($pos['charge'])      ? $pos['charge']      : null,
                'bezeichnung'       => $pos['bezeichnung']         ?? '',
                'ean'               => !empty($pos['ean'])         ? $pos['ean']         : null,
                'menge'             => $menge,
                'einzelpreis_netto' => $einzelNetto,
                'steuer_prozent'    => $steuer,
                'rabatt_prozent'    => $rabatt,
                'gesamtpreis_netto' => $gesamtNetto,
            ];
        }
        return $result;
    }

    /**
     * Addiert Netto, Steuer und Brutto aus berechneten Positionen.
     */
    private function berechneSummen(array $positionen): array
    {
        $netto  = 0.0;
        $steuer = 0.0;
        foreach ($positionen as $p) {
            $netto  += $p['gesamtpreis_netto'];
            $steuer += round($p['gesamtpreis_netto'] * $p['steuer_prozent'] / 100, 2);
        }
        return [
            'netto'  => round($netto, 2),
            'steuer' => round($steuer, 2),
            'brutto' => round($netto + $steuer, 2),
        ];
    }

    /** Validiert Pflichtfelder beim Anlegen. */
    private function validiere(array $data): array
    {
        $fehler = [];
        if (empty($data['zahlungsart'])) $fehler[] = 'Zahlungsart ist Pflichtfeld';
        return $fehler;
    }

    public function bearbeiten(int $id, array $data, array $positionen): array
    {
        // 1. Auftrag laden + prüfen (existiert? noch editierbar?)
        $auftragsdaten = $this->repo->findById($id);
        if (!$auftragsdaten) {
            return ['erfolg' => false, 'fehler' => ['Auftrag nicht gefunden']];
        }

        if (in_array($auftragsdaten['lieferstatus'], ['versendet', 'abgeschlossen', 'storniert'])) {
            return ['erfolg' => false, 'fehler' => ['Bereits versendete, abgeschlossene oder stornierte Aufträge können nicht bearbeitet werden']];
        }

        // 2. Positionen berechnen (berechnePositionen() — schon vorhanden!)
        $positionenBerechnet = $this->berechnePositionen($positionen);

        if (empty($positionenBerechnet)) {
            return ['erfolg' => false, 'fehler' => ['Mindestens eine gültige Position ist erforderlich']];
        }
        // 3. Summen berechnen (berechneSummen() — schon vorhanden!)
        $positionenSummen = $this->berechneSummen($positionenBerechnet);

        // 4. Header updaten (neues Repo-Method: updateHeader)
        $headerData = [
            'zahlungsart'      => $data['zahlungsart'] ?? 'vorkasse',
            'lieferart'        => $data['lieferart'] ?? 'versand',
            'versandklasse_id' => !empty($data['versandklasse_id']) ? (int)$data['versandklasse_id'] : null,
            'versandkosten'    => !empty($data['versandkosten'])    ? (float)$data['versandkosten']   : 0.00,
            'nettobetrag'      => $positionenSummen['netto'],
            'steuerbetrag'     => $positionenSummen['steuer'],
            'bruttobetrag'     => $positionenSummen['brutto'],
            'notiz_intern'     => !empty($data['notiz_intern'])  ? $data['notiz_intern']  : null,
            'notiz_versand'    => !empty($data['notiz_versand']) ? $data['notiz_versand'] : null,
        ];

        // Kunden-Wechsel (Laufkunde → Stammkunde oder Korrektur) — nur wenn kein Rechnungs-Lock
        if (array_key_exists('kunden_id', $data)) {
            $headerData['kunden_id']       = !empty($data['kunden_id']) ? (int)$data['kunden_id'] : null;
            $headerData['kunden_snapshot'] = !empty($data['kunden_snapshot']) ? json_encode($data['kunden_snapshot'], JSON_UNESCAPED_UNICODE) : null;
        }

        if (array_key_exists('lieferadresse_snapshot', $data)) {
            $headerData['lieferadresse_snapshot'] = !empty($data['lieferadresse_snapshot'])
                ? json_encode($data['lieferadresse_snapshot'], JSON_UNESCAPED_UNICODE) : null;
        }
        if (array_key_exists('rechnungsadresse_snapshot', $data)) {
            $headerData['rechnungsadresse_snapshot'] = !empty($data['rechnungsadresse_snapshot'])
                ? json_encode($data['rechnungsadresse_snapshot'], JSON_UNESCAPED_UNICODE) : null;
        }

        $this->repo->updateHeader($id, $headerData);

        // 5. Alte menge_geliefert-Werte merken (von Packplatz/Kasse gesetzt, sollen erhalten bleiben)
        $alteGeliefert = [];
        foreach ($this->repo->findPositionen($id) as $p) {
            if (!empty($p['artikel_id'])) {
                $alteGeliefert[(int)$p['artikel_id']] = (float)$p['menge_geliefert'];
            }
        }

        // 6. Alte Positionen löschen
        $this->repo->deletePositionen($id);

        // 7. Neue Positionen einfügen — menge_geliefert direkt beim INSERT wiederherstellen
        $alleNeuGeliefert    = true;
        $irgendetwasGelief   = false;
        foreach ($positionenBerechnet as $i => $pos) {
            $artId = (int)($pos['artikel_id'] ?? 0);
            $mg = ($artId && array_key_exists($artId, $alteGeliefert))
                ? min($alteGeliefert[$artId], (float)$pos['menge'])
                : 0.0;
            if ($mg < (float)$pos['menge']) $alleNeuGeliefert  = false;
            if ($mg > 0)                    $irgendetwasGelief = true;
            $this->repo->insertPosition(array_merge($pos, [
                'auftrag_id'      => $id,
                'sort_order'      => $i,
                'menge_geliefert' => $mg,
            ]));
        }

        // 8. Status neu berechnen wenn sich durch Menge-Korrektur Lieferung/Zahlung vervollständigt hat
        if ($irgendetwasGelief && in_array($auftragsdaten['lieferstatus'], ['teilgeliefert', 'abholbereit', 'kommissioniert'])) {
            $neuerLieferstatus = $alleNeuGeliefert ? 'abgeschlossen' : 'teilgeliefert';
            $statusUpdate = ['lieferstatus' => $neuerLieferstatus];

            if ($alleNeuGeliefert) {
                $zahlungen = $this->repo->findZahlungen($id);
                $bezahlt   = array_sum(array_column($zahlungen, 'betrag'));
                if ($bezahlt >= $positionenSummen['brutto'] - 0.01) {
                    $statusUpdate['zahlungsstatus'] = 'bezahlt';
                } elseif ($bezahlt > 0) {
                    $statusUpdate['zahlungsstatus'] = 'teilbezahlt';
                }
            }

            $this->repo->updateStatus($id, $statusUpdate);
        }

        // Reservierungen aktualisieren: alte schließen, neue anlegen
        $this->repo->schliesseReservierungen($id);
        $this->repo->legeReservierungenAn($id, $positionenBerechnet, $auftragsdaten['kanal'] ?? 'manuell');

        // 7. Statuslog schreiben (logStatus() — schon vorhanden!)
        $this->repo->logStatus($id, ['Gesamtbrutto' => [$auftragsdaten['bruttobetrag'], $positionenSummen['brutto']]], 'Auftrag bearbeitet', $_SESSION['benutzer']['id']);

        Logger::log('auftraege.bearbeiten', 'auftraege', $id, [
            'kanal'       => $auftragsdaten['kanal'],
            'positionen'  => count($positionenBerechnet),
            'brutto'      => $positionenSummen['brutto'],
        ]);

        return ['erfolg' => true, 'id' => $id];
    }

    public function getZahlungen(int $auftragId): array
    {
        return $this->repo->findZahlungen($auftragId);
    }

    public function bucheZahlung(int $auftragId, float $betrag, string $buchungsdatum, ?string $notiz): array
    {
        if ($betrag <= 0) {
            return ['erfolg' => false, 'fehler' => 'Betrag muss größer als 0 sein'];
        }

        $auftrag = $this->repo->findById($auftragId);
        if (!$auftrag) {
            return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden'];
        }
        if ($auftrag['zahlungsstatus'] === 'bezahlt') {
            return ['erfolg' => false, 'fehler' => 'Auftrag ist bereits vollständig bezahlt'];
        }

        $benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
        $this->repo->insertZahlung($auftragId, $betrag, $buchungsdatum, $notiz, $benutzerId);

        $summe   = $this->repo->getSummeZahlungen($auftragId);
        $gesamt  = (float)$auftrag['bruttobetrag'];

        if ($summe >= $gesamt) {
            $neuerStatus = 'bezahlt';
            $bezahltAm   = $buchungsdatum;
        } else {
            $neuerStatus = 'teilbezahlt';
            $bezahltAm   = null;
        }

        $felder = ['zahlungsstatus' => $neuerStatus];
        if ($bezahltAm) {
            $felder['bezahlt_am'] = $bezahltAm;
        }
        $this->repo->updateStatus($auftragId, $felder);
        $this->repo->logStatus($auftragId, ['zahlungsstatus' => [$auftrag['zahlungsstatus'], $neuerStatus]], 'Zahlung gebucht: ' . number_format($betrag, 2, ',', '.') . ' €', $benutzerId);

        Logger::log('auftraege.zahlung_buchen', 'auftraege', $auftragId, ['betrag' => $betrag, 'status' => $neuerStatus]);

        return ['erfolg' => true, 'neuer_status' => $neuerStatus, 'summe' => $summe, 'gesamt' => $gesamt];
    }
}
