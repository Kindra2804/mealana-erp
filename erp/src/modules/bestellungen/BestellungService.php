<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/BestellungRepository.php';

/**
 * BestellungService – Geschäftslogik für Einkaufsbestellungen
 *
 * Verwaltet den Lebenszyklus einer Bestellung:
 * Anlegen → Positionen hinzufügen → Aktualisieren → Rechnung speichern → Stornieren
 *
 * Status-Flow: offen → (teilgeliefert via Wareneingang) → erledigt | storniert
 *
 * Bestellnummer: "BE-2026-0001" — statische Methode bestellnummer() wird auch
 * von WareneingangService als Referenz auf lager_bewegungen.referenz verwendet.
 */
class BestellungService
{
    private BestellungRepository $repo;

    public function __construct()
    {
        $this->repo = new BestellungRepository();
    }

    /**
     * Gibt alle Bestellungen zurück, optional gefiltert.
     *
     * @param string $status     "" für alle Status
     * @param int    $lieferantId 0 für alle Lieferanten
     */
    public function getAll(string $status = '', int $lieferantId = 0): array
    {
        return $this->repo->findAll($status, $lieferantId);
    }

    /** Gibt eine Bestellung anhand ID zurück. */
    public function getById(int $id): array|false
    {
        return $this->repo->findById($id);
    }

    /** Gibt alle Positionen einer Bestellung zurück (mit Artikel-Details, Hauptbild). */
    public function getPositionen(int $bestellungId): array
    {
        return $this->repo->findPositionen($bestellungId);
    }

    /** Gibt alle aktiven Lieferanten für das Dropdown zurück. */
    public function getAlleLieferanten(): array
    {
        return $this->repo->findAlleLieferanten();
    }

    /**
     * Gibt Artikel für den Positionstypeahead zurück (nur Lieferanten-zugeordnete).
     * Für "alle Artikel" → findAlleArtikelFuerSuche().
     */
    public function getArtikelFuerLieferant(int $lieferantId, string $suche = ''): array
    {
        return $this->repo->findArtikelFuerLieferant($lieferantId, $suche);
    }

    /** Gibt alle aktiven Artikel für den erweiterten Typeahead (unabhängig vom Lieferanten). */
    public function getAlleArtikelFuerSuche(string $suche): array
    {
        return $this->repo->findAlleArtikelFuerSuche($suche);
    }

    /**
     * Fügt eine einzelne Position zu einer bestehenden Bestellung hinzu.
     * Ohne artikel_id oder menge_bestellt wird die Position übersprungen.
     */
    public function positionHinzufuegen(int $bestellungId, array $pos): void
    {
        if (empty($pos['artikel_id']) || empty($pos['menge_bestellt'])) return;
        $this->repo->insertPosition([
            'bestellung_id'   => $bestellungId,
            'artikel_id'      => (int)$pos['artikel_id'],
            'menge_bestellt'  => (float)$pos['menge_bestellt'],
            'ek_preis'        => !empty($pos['ek_preis'])       ? (float)$pos['ek_preis']       : null,
            'lieferzeit_text' => !empty($pos['lieferzeit_text']) ? $pos['lieferzeit_text']       : null,
        ]);
    }

    /**
     * Gibt die Rückstandsliste für einen Lieferanten zurück:
     * Artikel mit offenen Reservierungen die der aktuelle Bestand nicht deckt.
     */
    public function getReserviertNichtLagernd(int $lieferantId): array
    {
        return $this->repo->findReserviertNichtLagerndFuerLieferant($lieferantId);
    }

    /** Bestellvorschläge: unter Meldebestand oder Unterdeckung, mit Std.-Lieferant-Infos. */
    public function getBestellvorschlaege(): array
    {
        return $this->repo->findBestellvorschlaege();
    }

    /**
     * Legt eine neue Bestellung mit Positionen an.
     *
     * Validierung: Lieferant und Bestelldatum sind Pflichtfelder, min. 1 Position.
     * Status startet immer als "offen".
     * Positionen ohne artikel_id oder menge werden übersprungen.
     * Bestellnummer wird im Logger-Eintrag für bessere Nachvollziehbarkeit gespeichert.
     */
    public function anlegen(array $data, array $positionen): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }
        if (empty($positionen)) {
            return ['erfolg' => false, 'fehler' => ['Mindestens eine Position ist erforderlich']];
        }

        $bestellungData = [
            'lieferant_id'   => (int)$data['lieferant_id'],
            'status'         => 'offen',
            'bestelldatum'   => $data['bestelldatum'],
            'erwartet_am'    => !empty($data['erwartet_am'])    ? $data['erwartet_am']    : null,
            'lieferzeit_text' => !empty($data['lieferzeit_text']) ? $data['lieferzeit_text'] : null,
            'zahlungsart'    => !empty($data['zahlungsart'])    ? $data['zahlungsart']    : null,
            'ab_nummer'      => !empty($data['ab_nummer'])      ? $data['ab_nummer']      : null,
            'notiz'          => !empty($data['notiz'])          ? $data['notiz']          : null,
            'benutzer_id'    => $_SESSION['benutzer']['id'],
        ];

        $id = $this->repo->insert($bestellungData);

        foreach ($positionen as $pos) {
            if (empty($pos['artikel_id']) || empty($pos['menge_bestellt'])) continue;
            $this->repo->insertPosition([
                'bestellung_id'   => $id,
                'artikel_id'      => (int)$pos['artikel_id'],
                'menge_bestellt'  => (float)$pos['menge_bestellt'],
                'ek_preis'        => !empty($pos['ek_preis'])       ? (float)$pos['ek_preis']       : null,
                'lieferzeit_text' => !empty($pos['lieferzeit_text']) ? $pos['lieferzeit_text']       : null,
            ]);
        }

        Logger::log('bestellungen.anlegen', 'bestellungen', $id, [
            'bestellnummer' => $this->bestellnummer($id, $bestellungData['bestelldatum']),
            'lieferant_id'  => $bestellungData['lieferant_id'],
            'positionen'    => count($positionen),
        ]);

        return ['erfolg' => true, 'id' => $id];
    }

    /** Aktualisiert die Kopfdaten einer Bestellung (kein Status-Update). */
    public function aktualisieren(array $data): array
    {
        if (empty($data['id'])) {
            return ['erfolg' => false, 'fehler' => ['Bestellungs-ID fehlt']];
        }
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $update = [
            'id'             => (int)$data['id'],
            'lieferant_id'   => (int)$data['lieferant_id'],
            'bestelldatum'   => $data['bestelldatum'],
            'erwartet_am'    => !empty($data['erwartet_am'])     ? $data['erwartet_am']     : null,
            'lieferzeit_text' => !empty($data['lieferzeit_text']) ? $data['lieferzeit_text'] : null,
            'zahlungsart'    => !empty($data['zahlungsart'])     ? $data['zahlungsart']     : null,
            'ab_nummer'      => !empty($data['ab_nummer'])       ? $data['ab_nummer']       : null,
            'notiz'          => !empty($data['notiz'])           ? $data['notiz']           : null,
        ];

        $this->repo->update($update);
        Logger::log('bestellungen.bearbeiten', 'bestellungen', (int)$data['id']);

        return ['erfolg' => true];
    }

    /**
     * Speichert Rechnungs- und Lieferscheindaten zu einer Bestellung.
     * Wird aufgerufen wenn die physische Rechnung beim Wareneingang gescannt wird.
     */
    public function rechnungSpeichern(array $data): array
    {
        if (empty($data['id'])) {
            return ['erfolg' => false, 'fehler' => ['ID fehlt']];
        }

        $update = [
            'id'              => (int)$data['id'],
            'ls_nummer'       => !empty($data['ls_nummer'])       ? $data['ls_nummer']       : null,
            'rechnung_nummer' => !empty($data['rechnung_nummer']) ? $data['rechnung_nummer'] : null,
            'rechnung_betrag' => !empty($data['rechnung_betrag']) ? (float)$data['rechnung_betrag'] : null,
            'rechnung_datum'  => !empty($data['rechnung_datum'])  ? $data['rechnung_datum']  : null,
        ];

        $this->repo->updateRechnung($update);
        Logger::log('bestellungen.rechnung', 'bestellungen', (int)$data['id'], [
            'rechnung_nummer' => $update['rechnung_nummer'],
            'betrag'          => $update['rechnung_betrag'],
        ]);

        return ['erfolg' => true];
    }

    /**
     * Storniert eine Bestellung.
     * Erledigte Bestellungen können nicht storniert werden — die Ware ist bereits im Lager.
     */
    public function stornieren(int $id): array
    {
        $bestellung = $this->repo->findById($id);
        if (!$bestellung) {
            return ['erfolg' => false, 'fehler' => ['Bestellung nicht gefunden']];
        }
        if ($bestellung['status'] === 'erledigt') {
            return ['erfolg' => false, 'fehler' => ['Erledigte Bestellungen können nicht storniert werden']];
        }

        $this->repo->updateStatus($id, 'storniert');
        Logger::log('bestellungen.stornieren', 'bestellungen', $id);

        return ['erfolg' => true];
    }

    /**
     * Generiert die Bestellnummer im Format "BE-2026-0001".
     * Statische Methode weil sie auch von WareneingangService verwendet wird
     * um die Bestellnummer als Referenz in lager_bewegungen.referenz zu speichern.
     * Format: BE-{Jahr 4-stellig}-{ID 4-stellig mit Nullen aufgefüllt}
     */
    public static function bestellnummer(int $id, string $datum): string
    {
        return 'BE-' . date('Y', strtotime($datum)) . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    }

    /** Validiert Pflichtfelder: Lieferant und Bestelldatum. */
    private function validiere(array $data): array
    {
        $fehler = [];
        if (empty($data['lieferant_id'])) $fehler[] = 'Lieferant ist Pflichtfeld';
        if (empty($data['bestelldatum'])) $fehler[] = 'Bestelldatum ist Pflichtfeld';
        return $fehler;
    }
}
