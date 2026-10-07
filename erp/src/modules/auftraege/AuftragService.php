<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/AuftragRepository.php';
require_once __DIR__ . '/AuftragAbschluss.php';
require_once __DIR__ . '/../konfigurator/KonfiguratorService.php';
require_once __DIR__ . '/Versandsteuer.php';

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
    private KonfiguratorService $konfiguratorService;

    public function __construct()
    {
        $this->repo = new AuftragRepository();
        $this->konfiguratorService = new KonfiguratorService();
    }

    /** Gibt alle Aufträge zurück, optional gefiltert. */
    public function getAll(
        string $zahlungsstatus = '',
        string $lieferstatus = '',
        string $kanal = '',
        string $suche = '',
        bool   $mitAbgeschlossenen = false,
        ?string $von = null,
        ?string $bis = null,
        string $belegFilter = ''
    ): array {
        return $this->repo->findAll($zahlungsstatus, $lieferstatus, $kanal, $suche, $mitAbgeschlossenen, $von, $bis, $belegFilter);
    }

    /** Belege (AB/LS/RG/GS/AZ/Bon) + "geliefert, nicht verrechnet" für die Auftragsliste. */
    public function getBelegeFuerAuftraege(array $ids): array
    {
        return $this->repo->findBelegeFuerAuftraege($ids);
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
     *
     * $erstelltVon überschreibt $_SESSION['benutzer']['id'] -- nötig für
     * Aufrufe ohne aktive Session (Cron/CLI, z.B. ShopBestellungSyncService).
     */
    public function anlegen(array $data, array $positionen, ?int $erstelltVon = null): array
    {
        $erstelltVon ??= $_SESSION['benutzer']['id'];
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $berechnetePos = $this->berechnePositionen($positionen);
        if (empty($berechnetePos)) {
            return ['erfolg' => false, 'fehler' => ['Mindestens eine gültige Position ist erforderlich']];
        }
        if ($partnerFehler = $this->pruefePartnerware($berechnetePos)) {
            return ['erfolg' => false, 'fehler' => $partnerFehler];
        }

        $summen = $this->berechneSummen($berechnetePos, (float)($data['versandkosten'] ?? 0));

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
            'shop_id'                   => !empty($data['shop_id']) ? (int)$data['shop_id'] : null,
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
            'erstellt_von'              => $erstelltVon,
        ];

        $id = $this->repo->insert($auftragData);

        foreach ($berechnetePos as $i => $pos) {
            // konfig_wert_ids muss VOR insertPosition() raus -- die Methode reicht $data 1:1 an
            // ein PDO execute() mit exakt benannten Platzhaltern durch, ein zusätzlicher Array-Key
            // würde dort "Invalid parameter number" werfen.
            $konfigWertIds = $pos['konfig_wert_ids'] ?? [];
            unset($pos['konfig_wert_ids']);

            $posId = $this->repo->insertPosition(array_merge($pos, [
                'auftrag_id'      => $id,
                'sort_order'      => $i,
                'menge_geliefert' => 0,
            ]));

            if (!empty($konfigWertIds)) {
                $this->konfiguratorService->speichereAuswahl('auftrag_positionen', $posId, $konfigWertIds);
            }
        }

        // Lagerreservierungen anlegen (für Bestand-Anzeige und Picklisten-Allocation)
        $this->repo->legeReservierungenAn($id, $berechnetePos, $auftragData['kanal'] ?? 'manuell');

        $this->repo->logStatus($id, ['lieferstatus' => [null, 'neu'], 'zahlungsstatus' => [null, 'ausstehend']], 'Auftrag angelegt', $erstelltVon);
        Logger::log('auftraege.anlegen', 'auftraege', $id, [
            'kanal'       => $auftragData['kanal'],
            'positionen'  => count($berechnetePos),
            'brutto'      => $summen['brutto'],
        ], $erstelltVon);

        return ['erfolg' => true, 'id' => $id];
    }

    /**
     * Aktualisiert Zahlungs- und/oder Lieferstatus eines Auftrags.
     * Protokolliert jede Änderung im Statuslog.
     *
     * @param array $felder  Nur die zu ändernden Felder (z.B. ['zahlungsstatus' => 'bezahlt'])
     * @param string|null $notiz  Optionaler Kommentar für den Statuslog
     * @param int|null $benutzerId  Überschreibt $_SESSION['benutzer']['id'] -- nötig für
     *        Aufrufe ohne aktive Session (Cron/CLI, z.B. ShopBestellungSyncService).
     */
    public function statusAktualisieren(int $id, array $felder, ?string $notiz = null, ?int $benutzerId = null): array
    {
        $benutzerId ??= $_SESSION['benutzer']['id'];
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
            $this->repo->logStatus($id, $changes, $notiz, $benutzerId);
            Logger::log('auftraege.status', 'auftraege', $id, $changes, $benutzerId);

            // Reservierungen schließen wenn Auftrag versendet oder abgeschlossen
            if (isset($changes['lieferstatus']) && in_array($changes['lieferstatus'][1], ['versendet', 'abgeschlossen', 'retoure_offen'])) {
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
        if (in_array($auftrag['lieferstatus'], ['versendet', 'abgeschlossen', 'retoure_offen'])) {
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
        $this->sendeStornoMail($auftrag, $notiz);
        return ['erfolg' => true];
    }

    /**
     * Informiert den Kunden per Mail über eine manuelle Stornierung.
     * Nutzt den beim Auftrag eingefrorenen kunden_snapshot (keine erneute
     * Entschlüsselung nötig). Scheitert der Mailversand, wird nur geloggt —
     * die Stornierung selbst ist zu diesem Zeitpunkt bereits abgeschlossen.
     */
    private function sendeStornoMail(array $auftrag, ?string $notiz): void
    {
        $kd    = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
        $email = $kd['email'] ?? '';
        if (!$email) {
            return;
        }

        $kdName = trim(($kd['vorname'] ?? '') . ' ' . ($kd['nachname'] ?? ''));
        if (!empty($kd['firma'])) $kdName = $kd['firma'];
        if (!$kdName) $kdName = 'Kunde';

        try {
            $db     = Database::getInstance();
            $konfig = $db->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel IN ('firma_email')")->fetchAll(PDO::FETCH_KEY_PAIR);

            $mailer = new Mailer();
            $mailer->sendeTemplate(
                $email,
                'Ihr Auftrag ' . $auftrag['auftrag_nr'] . ' wurde storniert',
                'mails/auftrag_storniert.html.twig',
                [
                    'kunde_name'     => $kdName,
                    'auftrag_nummer' => $auftrag['auftrag_nr'],
                    'auftrag_datum'  => date('d.m.Y', strtotime($auftrag['erstellt_am'])),
                    'betrag'         => number_format((float) $auftrag['bruttobetrag'], 2, ',', '.'),
                    'grund'          => $notiz,
                    'firma_email'    => $konfig['firma_email'] ?? '',
                ]
            );
        } catch (Throwable $e) {
            Logger::log('auftraege.storno_mail_fehler', 'auftraege', $auftrag['id'], ['fehler' => $e->getMessage()]);
        }
    }

    /**
     * Berechnet Einzelpreis-Summen für eine Liste von Positions-Eingaben.
     * Überspringt Zeilen ohne artikel_id oder menge.
     */
    /**
     * Partnerware (artikel.partner_id) ist nur Kassenverkauf (Jacky 2026-10-01): kein Auftrag,
     * kein Telefon-/Rechnungsverkauf -- Lager, Abrechnung und Bon laufen über die Kasse.
     *
     * @return string[] Fehlermeldungen (leer = ok)
     */
    private function pruefePartnerware(array $positionen): array
    {
        $ids = array_values(array_unique(array_filter(array_column($positionen, 'artikel_id'))));
        if (!$ids) return [];
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getInstance()->prepare("
            SELECT a.artikelnummer, a.name, p.name AS partner FROM artikel a
            JOIN partner p ON p.id = a.partner_id WHERE a.id IN ($ph)
        ");
        $stmt->execute($ids);
        return array_map(fn($a) => $a['artikelnummer'] . ' ' . $a['name'] . ' ist Partnerware (' . $a['partner']
            . ') und kann nur an der Kasse verkauft werden, nicht per Auftrag.', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function berechnePositionen(array $eingaben): array
    {
        $result = [];
        foreach ($eingaben as $pos) {
            if (empty($pos['artikel_id']) || empty($pos['menge'])) continue;
            $einzelNetto = round((float)($pos['einzelpreis_netto'] ?? 0), 4);
            $menge       = (int)$pos['menge'];
            $rabatt      = (float)($pos['rabatt_prozent'] ?? 0);
            $steuer      = (float)($pos['steuer_prozent'] ?? 20);

            $gesamtNetto = Positionsrechnung::zeile($einzelNetto, $menge, $rabatt, $steuer)['netto'];

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
                'konfig_wert_ids'   => !empty($pos['konfig_wert_ids']) ? array_map('intval', $pos['konfig_wert_ids']) : [],
                // Klartext-Fallback (z.B. aus dem Shop-Bestellungs-Sync) -- bleibt IMMER
                // erhalten, auch wenn die ID-basierte Konfigurator-Zuordnung oben (z.B. durch
                // eine seither veraltete Preis-Matrix) unvollständig ist. Siehe [[project_konfigurator_modul]].
                'konfig_freitext'   => !empty($pos['konfig_freitext']) ? $pos['konfig_freitext'] : null,
            ];
        }
        return $result;
    }

    /**
     * Addiert Netto, Steuer und Brutto aus berechneten Positionen.
     */
    /**
     * Summen des Auftrags INKL. Versandkosten (brutto, Steuersatz der überwiegenden
     * Leistung, siehe Versandsteuer). Bis 2026-09-30 fehlte der Versand hier komplett --
     * bruttobetrag (offener Betrag, Zahlung buchen, Mahnungen) war um die Versandkosten zu niedrig.
     */
    private function berechneSummen(array $positionen, float $versandBrutto = 0.0): array
    {
        $netto  = 0.0;
        $steuer = 0.0;
        foreach ($positionen as $p) {
            $z = Positionsrechnung::ausPosition($p);
            $netto  += $z['netto'];
            $steuer += $z['steuer'];
        }
        if ($versandBrutto > 0) {
            $v = Versandsteuer::aufteilen($versandBrutto, $positionen);
            $netto  += $v['netto'];
            $steuer += $v['steuer'];
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
        // Jeder bestellende Kunde braucht ein eigenes Kundenkonto (Debitor) — sonst landen
        // Rechnung und Zahlung in der Buchhaltung ohne Gegenkonto. Nur Kasse/Archiv dürfen ohne.
        if (empty($data['kunden_id']) && !in_array($data['kanal'] ?? 'manuell', ['kasse', 'jtl_archiv'], true)) {
            $fehler[] = 'Bitte einen Kunden wählen (oder neu anlegen) — jeder Auftrag braucht ein Kundenkonto für die Buchhaltung';
        }
        return $fehler;
    }

    public function bearbeiten(int $id, array $data, array $positionen): array
    {
        // 1. Auftrag laden + prüfen (existiert? noch editierbar?)
        $auftragsdaten = $this->repo->findById($id);
        if (!$auftragsdaten) {
            return ['erfolg' => false, 'fehler' => ['Auftrag nicht gefunden']];
        }

        if (in_array($auftragsdaten['lieferstatus'], ['versendet', 'abgeschlossen', 'storniert', 'retoure_offen'])) {
            return ['erfolg' => false, 'fehler' => ['Bereits versendete, abgeschlossene oder stornierte Aufträge können nicht bearbeitet werden']];
        }

        // 2. Positionen berechnen (berechnePositionen() — schon vorhanden!)
        $positionenBerechnet = $this->berechnePositionen($positionen);
        if ($partnerFehler = $this->pruefePartnerware($positionenBerechnet)) {
            return ['erfolg' => false, 'fehler' => $partnerFehler];
        }

        // Verrechnete Menge (Teilrechnung/Bon) darf nicht wegfallen -- Korrektur dann nur per Gutschrift
        $neueMengen = [];
        foreach ($positionenBerechnet as $pos) {
            $artId = (int)($pos['artikel_id'] ?? 0);
            $neueMengen[$artId] = ($neueMengen[$artId] ?? 0) + (int)$pos['menge'];
        }
        foreach ($this->repo->findPositionen($id) as $p) {
            if ((int)$p['menge_verrechnet'] > ($neueMengen[(int)$p['artikel_id']] ?? 0)) {
                return ['erfolg' => false, 'fehler' => [
                    $p['bezeichnung'] . ': ' . $p['menge_verrechnet'] . ' Stück stehen bereits auf einer Rechnung bzw. einem Bon — '
                    . 'weniger geht nur über eine Gutschrift.',
                ]];
            }
        }

        if (empty($positionenBerechnet)) {
            return ['erfolg' => false, 'fehler' => ['Mindestens eine gültige Position ist erforderlich']];
        }
        // 3. Summen berechnen (berechneSummen() — schon vorhanden!)
        $positionenSummen = $this->berechneSummen($positionenBerechnet, (float)($data['versandkosten'] ?? 0));

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
            if (empty($data['kunden_id']) && $auftragsdaten['kanal'] !== 'kasse') {
                return ['erfolg' => false, 'fehler' => ['Bitte einen Kunden wählen — jeder Auftrag braucht ein Kundenkonto für die Buchhaltung']];
            }
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
        // Belege-Zähler ebenfalls erhalten -- sonst würde bereits Verrechnetes (Teilrechnung,
        // Bon) nach dem Bearbeiten nochmal verrechnet (Belege-Umbau 2026-10-07)
        $alteZaehler = [];
        foreach ($this->repo->findPositionen($id) as $p) {
            if (!empty($p['artikel_id'])) {
                $alteGeliefert[(int)$p['artikel_id']] = (float)$p['menge_geliefert'];
                $alteZaehler[(int)$p['artikel_id']] = [
                    'menge_abgeholt'       => (int)$p['menge_abgeholt'],
                    'menge_retourniert'    => (int)$p['menge_retourniert'],
                    'menge_gutgeschrieben' => (int)$p['menge_gutgeschrieben'],
                    'menge_verrechnet'     => (int)$p['menge_verrechnet'],
                    'bezeichnung'          => $p['bezeichnung'],
                ];
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
            // konfig_wert_ids muss VOR insertPosition() raus (siehe anlegen()) -- beim Bearbeiten
            // eines Auftrags werden alle Positionen neu geschrieben, eine bestehende Konfigurator-
            // Auswahl geht dabei aktuell verloren (bewusst zurückgestellt, siehe Konfigurator-Memory:
            // die Klartext-bezeichnung bleibt als Fallback erhalten, nur die strukturierte
            // position_konfiguration-Kopplung nicht).
            unset($pos['konfig_wert_ids']);
            $neuePosId = $this->repo->insertPosition(array_merge($pos, [
                'auftrag_id'      => $id,
                'sort_order'      => $i,
                'menge_geliefert' => $mg,
            ]));
            if ($artId && isset($alteZaehler[$artId])) {
                $z = $alteZaehler[$artId];
                unset($alteZaehler[$artId]); // pro Artikel nur einmal übertragen
                Database::getInstance()->prepare("
                    UPDATE auftrag_positionen
                    SET menge_abgeholt = ?, menge_retourniert = ?, menge_gutgeschrieben = ?, menge_verrechnet = ?
                    WHERE id = ?
                ")->execute([
                    min($z['menge_abgeholt'], (int)$pos['menge']), min($z['menge_retourniert'], (int)$pos['menge']),
                    min($z['menge_gutgeschrieben'], (int)$pos['menge']), $z['menge_verrechnet'], $neuePosId,
                ]);
            }
        }

        // 8. Status neu berechnen wenn sich durch Menge-Korrektur Lieferung/Zahlung vervollständigt hat
        if ($irgendetwasGelief && in_array($auftragsdaten['lieferstatus'], ['teilgeliefert', 'abholbereit', 'kommissioniert'])) {
            // "abgeschlossen" entscheidet AuftragAbschluss (unten, nach dem Zahlungsstatus)
            $neuerLieferstatus = $alleNeuGeliefert ? 'versendet' : 'teilgeliefert';
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

    /**
     * Versandart umstellen (Babsi 2026-10-07): Online "Abholung" bestellt, will aber doch
     * Versand -- oder umgekehrt. Versandkosten anpassen, Beträge + Zahlungsstatus neu,
     * Positionen/Lager passend zur neuen Lieferart:
     *   Abholung → Versand: Ware, die gepackt im Abholfach liegt (menge_geliefert − menge_abgeholt),
     *     wird ins Lager zurückgebucht (Charge aus dem Warenausgang) -> geht normal über den
     *     Packplatz in den Versand. Status zurück auf "in Bearbeitung".
     *   Versand → Abholung: schon Verschicktes gilt als übergeben (menge_abgeholt = menge_geliefert).
     * Kostet es jetzt mehr und war schon bezahlt -> "teilbezahlt" + Mail mit Restbetrag
     * (Packplatz fragt vor dem Versand nach). Kostet es weniger -> Guthaben, das die Kasse
     * bei der Abholung bar oder als Gutschein auszahlt.
     */
    public function versandartAendern(int $id, string $lieferart, ?int $versandklasseId, float $versandkosten, int $benutzerId): array
    {
        $auftrag = $this->repo->findById($id);
        if (!$auftrag) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden'];
        if (!in_array($lieferart, ['versand', 'abholung'], true)) return ['erfolg' => false, 'fehler' => 'Ungültige Lieferart'];
        if (in_array($auftrag['kanal'], ['kasse', 'jtl_archiv', 'haendler'], true)) {
            return ['erfolg' => false, 'fehler' => 'Für diesen Auftragstyp nicht möglich.'];
        }
        if (in_array($auftrag['lieferstatus'], ['versendet', 'abgeschlossen', 'retoure_offen', 'storniert'], true)) {
            return ['erfolg' => false, 'fehler' => 'Der Auftrag ist schon ausgeliefert bzw. storniert — Versandart kann nicht mehr geändert werden.'];
        }
        $versandkosten = $lieferart === 'abholung' && $versandkosten < 0 ? 0.0 : round(max(0.0, $versandkosten), 2);
        $alteKosten    = round((float)$auftrag['versandkosten'], 2);
        if ($lieferart === $auftrag['lieferart'] && abs($versandkosten - $alteKosten) < 0.005
            && (int)$versandklasseId === (int)$auftrag['versandklasse_id']) {
            return ['erfolg' => false, 'fehler' => 'Keine Änderung.'];
        }

        $db = Database::getInstance();
        // Versandkosten schon auf einer Rechnung -> nur über Rechnungskorrektur
        if (abs($versandkosten - $alteKosten) >= 0.005) {
            $re = $db->prepare("SELECT rechnung_nr FROM rechnungen WHERE auftrag_id = ? AND storniert = 0 AND versandkosten_brutto > 0 LIMIT 1");
            $re->execute([$id]);
            if ($nr = $re->fetchColumn()) {
                return ['erfolg' => false, 'fehler' => "Die Versandkosten stehen schon auf Rechnung $nr — bitte über eine Rechnungskorrektur ändern."];
            }
        }

        $positionen = $this->repo->findPositionen($id);
        $eigeneTx = !$db->inTransaction();
        if ($eigeneTx) $db->beginTransaction();
        try {
            $rueckgebucht = [];
            if ($auftrag['lieferart'] === 'abholung' && $lieferart === 'versand') {
                require_once __DIR__ . '/../lager/LagerService.php';
                require_once __DIR__ . '/../packplatz/RetourService.php';
                $lager  = new LagerService();
                $retour = new RetourService();
                foreach ($positionen as $p) {
                    $imFach = (int)$p['menge_geliefert'] - (int)$p['menge_abgeholt'];
                    if ($imFach <= 0 || empty($p['artikel_id'])) continue;
                    foreach ($retour->verteileAufChargen($retour->verkaufteChargen($id, (int)$p['artikel_id']), $imFach) as $t) {
                        $lager->wareneingang([
                            'artikel_id'  => (int)$p['artikel_id'],
                            'lager_id'    => (int)($t['lager_id'] ?: 1),
                            'menge'       => $t['menge'],
                            'charge'      => $t['charge'],
                            'referenz'    => 'Umstellung auf Versand ' . $auftrag['auftrag_nr'],
                            'notiz'       => 'Aus dem Abholfach zurück ins Lager (Versandart geändert)',
                            'benutzer_id' => $benutzerId,
                        ]);
                    }
                    $db->prepare("UPDATE auftrag_positionen SET menge_geliefert = menge_abgeholt WHERE id = ?")->execute([$p['id']]);
                    $rueckgebucht[] = $imFach . '× ' . $p['bezeichnung'];
                }
            } elseif ($auftrag['lieferart'] === 'versand' && $lieferart === 'abholung') {
                // Schon Verschicktes gilt als übergeben -- liegt nicht im Abholfach
                $db->prepare("UPDATE auftrag_positionen SET menge_abgeholt = GREATEST(menge_abgeholt, menge_geliefert) WHERE auftrag_id = ?")
                   ->execute([$id]);
            }

            // Beträge neu (Positionen unverändert, nur Versand)
            $summen = $this->berechneSummen($positionen, $versandkosten);
            $felder = [
                'lieferart'        => $lieferart,
                'versandklasse_id' => $lieferart === 'versand' ? $versandklasseId : null,
                'versandkosten'    => $versandkosten,
                'nettobetrag'      => $summen['netto'],
                'steuerbetrag'     => $summen['steuer'],
                'bruttobetrag'     => $summen['brutto'],
            ];
            $neuerLieferstatus = $auftrag['lieferstatus'];
            if ($lieferart === 'versand' && in_array($auftrag['lieferstatus'], ['abholbereit', 'kommissioniert'], true)) {
                $neuerLieferstatus = 'in_bearbeitung';
            }

            // Zahlungsstatus aus Zahlungen + Gutschein gegen den neuen Betrag
            $bezahlt = $this->repo->getSummeZahlungen($id) + (float)$auftrag['gutschein_betrag'];
            $gesamt  = round($summen['brutto'] + $this->repo->getOffeneMahngebuehren($id), 2);
            $neuerZahlungsstatus = $auftrag['zahlungsstatus'];
            if (in_array($auftrag['zahlungsstatus'], ['ausstehend', 'teilbezahlt', 'bezahlt'], true)) {
                $neuerZahlungsstatus = $bezahlt >= $gesamt - 0.004 ? 'bezahlt' : ($bezahlt > 0.004 ? 'teilbezahlt' : 'ausstehend');
            }

            $db->prepare("
                UPDATE auftraege SET lieferart = ?, versandklasse_id = ?, versandkosten = ?, nettobetrag = ?, steuerbetrag = ?,
                                     bruttobetrag = ?, lieferstatus = ?, zahlungsstatus = ?, aktualisiert_am = NOW()
                WHERE id = ?
            ")->execute([$felder['lieferart'], $felder['versandklasse_id'], $versandkosten, $summen['netto'], $summen['steuer'],
                         $summen['brutto'], $neuerLieferstatus, $neuerZahlungsstatus, $id]);

            // Reservierungen für die (wieder) offene Ware neu anlegen
            $this->repo->schliesseReservierungen($id);
            $offenePos = [];
            foreach ($this->repo->findPositionen($id) as $p) {
                $rest = (int)$p['menge'] - (int)$p['menge_geliefert'];
                if ($rest > 0) $offenePos[] = ['artikel_id' => $p['artikel_id'], 'menge' => $rest];
            }
            if ($offenePos) $this->repo->legeReservierungenAn($id, $offenePos, $auftrag['kanal']);

            $arten = ['versand' => 'Versand', 'abholung' => 'Abholung'];
            $notiz = 'Versandart geändert: ' . $arten[$auftrag['lieferart']] . ' → ' . $arten[$lieferart]
                . ' · Versandkosten ' . number_format($alteKosten, 2, ',', '.') . ' → ' . number_format($versandkosten, 2, ',', '.') . ' €'
                . ($rueckgebucht ? ' · aus dem Abholfach zurück ins Lager: ' . implode(', ', $rueckgebucht) : '');
            $aenderungen = ['lieferart' => [$auftrag['lieferart'], $lieferart], 'versandkosten' => [$alteKosten, $versandkosten]];
            if ($neuerLieferstatus !== $auftrag['lieferstatus']) $aenderungen['lieferstatus'] = [$auftrag['lieferstatus'], $neuerLieferstatus];
            if ($neuerZahlungsstatus !== $auftrag['zahlungsstatus']) $aenderungen['zahlungsstatus'] = [$auftrag['zahlungsstatus'], $neuerZahlungsstatus];
            $this->repo->logStatus($id, $aenderungen, $notiz, $benutzerId);

            if ($eigeneTx) $db->commit();
        } catch (Throwable $e) {
            if ($eigeneTx && $db->inTransaction()) $db->rollBack();
            return ['erfolg' => false, 'fehler' => 'Fehler beim Umstellen: ' . $e->getMessage()];
        }

        Logger::log('auftraege.versandart_geaendert', 'auftraege', $id, ['von' => $auftrag['lieferart'], 'nach' => $lieferart,
            'versandkosten' => [$alteKosten, $versandkosten]], $benutzerId);

        $rest    = round($gesamt - $bezahlt, 2);
        $ergebnis = ['erfolg' => true, 'zahlungsstatus' => $neuerZahlungsstatus, 'rest' => max(0, $rest), 'guthaben' => max(0, -$rest),
                     'mail' => false];

        // War bezahlt, jetzt Restbetrag offen -> Kunde informieren (Bankdaten im Mail-Layout)
        if ($auftrag['zahlungsstatus'] === 'bezahlt' && $neuerZahlungsstatus === 'teilbezahlt') {
            $ergebnis['mail'] = $this->sendeRestbetragMail($id, $rest, $alteKosten, $versandkosten);
        }
        return $ergebnis;
    }

    /** Mail an den Kunden: Versandart geändert, Restbetrag bitte überweisen. */
    private function sendeRestbetragMail(int $id, float $rest, float $alteKosten, float $neueKosten): bool
    {
        try {
            $auftrag = $this->repo->findById($id);
            $kunde   = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
            $email   = trim($kunde['email'] ?? '');
            if (!$email) return false;
            $mailer = new Mailer();
            $mailer->sendeTemplate(
                empfaenger:   $email,
                betreff:      'Ihre Bestellung ' . $auftrag['auftrag_nr'] . ' — Versand statt Abholung',
                templatePfad: 'mails/versandart_restbetrag.html.twig',
                variablen: [
                    'logo_base64'    => $mailer->ladeShopLogo((int)($auftrag['shop_id'] ?? 1)),
                    'kunde_name'     => trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? '')) ?: ($kunde['firma'] ?? ''),
                    'auftrag_nummer' => $auftrag['auftrag_nr'],
                    'versandkosten'  => $neueKosten - $alteKosten,
                    'rest'           => $rest,
                    'firma_email'    => Database::getInstance()->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'mail_from_address'")->fetchColumn() ?: '',
                ],
            );
            return true;
        } catch (Throwable $e) {
            error_log('[VersandartMail] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Rückerstattung an den Kunden (z.B. Überweisung nach Stornorechnung/Rechnungskorrektur).
     * Höchstens das Guthaben aus den Belegen. Danach Zahlungsstatus aus dem Saldo und
     * Abschluss-Prüfung (Retoure offen -> abgeschlossen). Klicktest 2026-10-07.
     */
    public function bucheRueckerstattung(int $auftragId, float $betrag, string $buchungsdatum, ?string $notiz, ?string $zahlungsweg = null): array
    {
        $auftrag = $this->repo->findById($auftragId);
        if (!$auftrag) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden'];
        if (!AuftragAbschluss::saldoAussagekraeftig($auftragId)) {
            return ['erfolg' => false, 'fehler' => 'Es ist noch nicht alle ausgelieferte Ware verrechnet — bitte zuerst die Rechnung erstellen.'];
        }
        $guthaben = round(-AuftragAbschluss::saldo($auftragId), 2);
        if ($betrag <= 0 || $betrag > $guthaben + 0.005) {
            return ['erfolg' => false, 'fehler' => 'Rückerstattung höchstens € ' . number_format(max(0, $guthaben), 2, ',', '.') . ' (Guthaben des Kunden).'];
        }
        $benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
        $zahlungsweg ??= self::ZAHLUNGSWEG_AUS_ZAHLUNGSART[$auftrag['zahlungsart']] ?? null;
        $this->repo->insertZahlung($auftragId, -round($betrag, 2), $buchungsdatum, $notiz ?: 'Rückerstattung', $benutzerId, $zahlungsweg);
        $this->repo->logStatus($auftragId, [], 'Rückerstattung gebucht: ' . number_format($betrag, 2, ',', '.') . ' €', $benutzerId);
        AuftragAbschluss::zahlungsstatusAusBelegen($auftragId, $benutzerId);
        AuftragAbschluss::pruefe($auftragId, $benutzerId);
        Logger::log('auftraege.rueckerstattung', 'auftraege', $auftragId, ['betrag' => $betrag], $benutzerId);
        return ['erfolg' => true];
    }

    /** Zahlungsweg aus der Zahlungsart des Auftrags (Standard, wenn nichts gewählt wurde). */
    private const ZAHLUNGSWEG_AUS_ZAHLUNGSART = [
        'vorkasse' => 'ueberweisung', 'rechnung' => 'ueberweisung', 'paypal' => 'paypal',
        'bar' => 'bar', 'nachnahme' => 'nachnahme', 'gutschein' => 'gutschein',
    ];

    public function bucheZahlung(int $auftragId, float $betrag, string $buchungsdatum, ?string $notiz, ?string $zahlungsweg = null, ?int $kassenBonId = null): array
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
        $zahlungsweg ??= self::ZAHLUNGSWEG_AUS_ZAHLUNGSART[$auftrag['zahlungsart']] ?? null;
        $this->repo->insertZahlung($auftragId, $betrag, $buchungsdatum, $notiz, $benutzerId, $zahlungsweg, $kassenBonId);

        $stand = $this->setzeZahlungsstatus($auftrag, $buchungsdatum,
            'Zahlung gebucht: ' . number_format($betrag, 2, ',', '.') . ' €', $benutzerId);

        Logger::log('auftraege.zahlung_buchen', 'auftraege', $auftragId, ['betrag' => $betrag, 'status' => $stand['neuer_status']]);

        return ['erfolg' => true] + $stand;
    }

    /**
     * Zahlungsstatus nach erlassener Mahngebühr neu bewerten — war nur noch die Gebühr
     * offen, ist der Auftrag jetzt bezahlt (MahnwesenService::gebuehrErlassen).
     */
    public function zahlungsstatusNachGebuehrErlass(int $auftragId, int $benutzerId): void
    {
        $auftrag = $this->repo->findById($auftragId);
        if (!$auftrag || !in_array($auftrag['zahlungsstatus'], ['ausstehend', 'teilbezahlt'], true)) return;
        if ($this->repo->getSummeZahlungen($auftragId) <= 0) return; // nichts bezahlt → bleibt ausstehend
        $this->setzeZahlungsstatus($auftrag, date('Y-m-d'), 'Mahngebühr erlassen', $benutzerId);
    }

    /**
     * Setzt bezahlt/teilbezahlt anhand der Zahlungssumme. Zu zahlen ist der Auftragsbetrag
     * plus offene (versendete, nicht erlassene) Mahngebühren.
     */
    private function setzeZahlungsstatus(array $auftrag, string $datum, string $grund, int $benutzerId): array
    {
        $auftragId = (int)$auftrag['id'];
        $summe     = $this->repo->getSummeZahlungen($auftragId);
        $gesamt    = round((float)$auftrag['bruttobetrag'] + $this->repo->getOffeneMahngebuehren($auftragId), 2);

        $neuerStatus = $summe >= $gesamt - 0.004 ? 'bezahlt' : 'teilbezahlt';
        $felder = ['zahlungsstatus' => $neuerStatus];
        if ($neuerStatus === 'bezahlt') {
            $felder['bezahlt_am'] = $datum;
        }
        $this->repo->updateStatus($auftragId, $felder);
        $this->repo->logStatus($auftragId, ['zahlungsstatus' => [$auftrag['zahlungsstatus'], $neuerStatus]], $grund, $benutzerId);

        // "abgeschlossen" nur über die zentrale Prüfung (geliefert + verrechnet + bezahlt)
        AuftragAbschluss::pruefe($auftragId, $benutzerId);

        return ['neuer_status' => $neuerStatus, 'summe' => $summe, 'gesamt' => $gesamt];
    }
}
