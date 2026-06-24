<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/VariantenRepository.php';

/**
 * VariantenService – Business-Logik für Achsen-Zuweisungen und Kombinations-Generator
 *
 * speichereAchsenUndWerte() ist die komplexeste Methode:
 *   Schritt 1: In-use Werte ermitteln (dürfen nicht gelöscht werden)
 *   Schritt 2: Nicht-in-use Werte löschen (aus Submission werden sie neu eingefügt)
 *   Schritt 3: Achsen mit in-use Werten sind "geschützt" und können nicht entfernt werden
 *   Schritt 4: Nicht-geschützte, nicht-gewünschte Achsen entfernen
 *   Schritt 5: Fehlende Achsen einfügen (idempotent — existierende überspringen)
 *   Schritt 6: Neue Werte einfügen (Duplikat-Check via achse_id|wert-Lookup)
 *
 * erstelleKombinationen() erstellt Kind-Artikel für den VarKombi-Generator:
 *   Jedes Kind erbt ~25 Felder vom Vater-Artikel (vollständige Kopie der Stammdaten).
 *   Gibt IDs der neu erstellten Kinder zurück damit ArtikelService Relationen kopieren kann.
 */
class VariantenService
{
    private VariantenRepository $repo;

    public function __construct()
    {
        $this->repo = new VariantenRepository();
    }

    /**
     * Granulares Speichern von Achsen und Werten — schützt Werte die in Kombinationen verwendet werden.
     *
     * Problem: Wenn Kombinationen (Kind-Artikel) existieren, dürfen deren Werte nicht gelöscht werden
     * da sonst die varianten_kombination_werte FK-Referenzen auf nicht-existente Werte zeigen.
     * Lösung: In-use Werte bleiben erhalten, freie Werte werden durch die neuen Submission-Werte ersetzt.
     * Duplikat-Schutz: Wenn ein in-use Wert denselben (achse_id|wert) hat wie ein neuer Wert,
     * wird der neue Wert übersprungen (würde sonst einen Duplicate-Key-Error erzeugen).
     */
    public function speichereAchsenUndWerte(int $artikelId, array $achsenIds, array $werte): array
    {
        $inUseIds = array_map('intval', $this->repo->findWertIdsInUse($artikelId));

        // In-use Werte aus DB holen – Lookup (achse_id|wert) → id für Duplikat-Check
        $currentWerte  = $this->repo->findWerteByArtikelId($artikelId);
        $inUseWerte    = array_filter($currentWerte, fn($w) => in_array((int)$w['id'], $inUseIds));
        $inUseLookup   = [];
        foreach ($inUseWerte as $w) {
            $inUseLookup[(int)$w['achse_id'] . '|' . $w['wert']] = (int)$w['id'];
        }

        // Achse-IDs mit in-use Werten (können nicht entfernt werden)
        $protectedAchseIds = array_unique(array_map(fn($w) => (int)$w['achse_id'], $inUseWerte));

        // Nicht-in-use Werte löschen (werden aus Submission neu eingefügt)
        $this->repo->deleteWerteExcluding($artikelId, $inUseIds ?: [0]);

        // Achsen: nur entfernen wenn nicht geschützt UND nicht in $achsenIds
        $currentAchsen   = $this->repo->findAchsenByArtikelId($artikelId);
        $currentAchseIds = array_map(fn($a) => (int)$a['achse_id'], $currentAchsen);
        foreach ($currentAchseIds as $cId) {
            if (!in_array($cId, $achsenIds) && !in_array($cId, $protectedAchseIds)) {
                $this->repo->deleteArtikelAchse($artikelId, $cId);
            }
        }

        // Neue Achsen einfügen (nur fehlende)
        $currentAchsen   = $this->repo->findAchsenByArtikelId($artikelId);
        $existingAchseSet = array_flip(array_map(fn($a) => (int)$a['achse_id'], $currentAchsen));
        foreach ($achsenIds as $achseId) {
            if (!isset($existingAchseSet[$achseId])) {
                $this->repo->insertArtikelAchse(['artikel_id' => $artikelId, 'achse_id' => $achseId]);
            }
        }

        // Werte einfügen: nur wenn nicht bereits als in-use vorhanden (Duplikat vermeiden)
        foreach ($werte as $wert) {
            $key = (int)$wert['achse_id'] . '|' . $wert['wert'];
            if (!isset($inUseLookup[$key])) {
                $wert['artikel_id'] = $artikelId;
                $this->repo->insertWert($wert);
            }
        }

        Logger::log('achsenUndWerte.speichern', 'artikel_achsen', $artikelId, [
            'achsen_anzahl' => count($achsenIds),
            'werte_anzahl'  => count($werte),
        ]);

        return ['erfolg' => true];
    }

    public function findAchsenByArtikelId(int $artikelId): array
    {
        if ($artikelId > 0) {
            return $this->repo->findAchsenByArtikelId($artikelId);
        }

        return ['erfolg' => false, 'fehler' => ['ArtikelId kann nicht 0 sein']];
    }

    public function findWerteByArtikelId(int $artikelId): array
    {
        if ($artikelId > 0) {
            return $this->repo->findWerteByArtikelId($artikelId);
        }

        return ['erfolg' => false, 'fehler' => ['ArtikelId kann nicht 0 sein']];
    }

    public function findExistingKombinationen(int $vaterId): array
    {
        return $this->repo->findExistingKombinationen($vaterId);
    }

    public function findWertIdsInUse(int $artikelId): array
    {
        return $this->repo->findWertIdsInUse($artikelId);
    }

    /**
     * Erstellt Kind-Artikel für eine Liste von Wert-Kombinationen.
     * Jeder Kind-Artikel erbt ~25 Felder vom Vater (Stammdaten, Texte, Maße, Flags).
     * Gibt die IDs aller neu erstellten Kinder zurück damit ArtikelService::kopiereVaterRelationenZuKindern()
     * Kategorien, Merkmale, Lieferanten und Preise darauf kopieren kann.
     * Das 'key' in $kombi ist ein Komma-separierter String von Wert-IDs (z.B. "3,7,12").
     */
    public function erstelleKombinationen(array $vater, bool $hatEigenenLagerstand, array $kombis): array
    {
        $neuErstellteIds = [];

        foreach ($kombis as $kombi) {

            $kind = [
                'artikelnummer'          => $kombi['artikelnummer'],
                'name'                   => $kombi['name'],
                'vaterartikel_id'        => $vater['id'],
                'hat_eigenen_lagerstand' => (int) $hatEigenenLagerstand,
                'hersteller_id'          => $vater['hersteller_id'],
                'steuerklasse_id'        => $vater['steuerklasse_id'],
                'artikeltyp_id'          => $vater['artikeltyp_id'],
                'einheit_id'             => $vater['einheit_id'],
                'kurzbeschreibung'       => $vater['kurzbeschreibung'],
                'beschreibung'           => $vater['beschreibung'],
                'technische_details'     => $vater['technische_details'],
                'beschreibung_intern'    => $vater['beschreibung_intern'],
                'meta_titel'             => $vater['meta_titel'],
                'meta_description'       => $vater['meta_description'],
                'url_slug'               => null,
                'inhalt_menge'           => $vater['inhalt_menge'],
                'inhalt_einheit'         => $vater['inhalt_einheit'],
                'gewicht_artikel'        => $vater['gewicht_artikel'],
                'gewicht_versand'        => $vater['gewicht_versand'],
                'laenge'                 => $vater['laenge'],
                'breite'                 => $vater['breite'],
                'hoehe'                  => $vater['hoehe'],
                'herkunftsland'          => $vater['herkunftsland'],
                'taric_code'             => $vater['taric_code'],
                'grundpreis_bezugsmenge' => $vater['grundpreis_bezugsmenge'],
                'grundpreis_anzeigen'    => $vater['grundpreis_anzeigen'],
                'charge_pflicht'         => $vater['charge_pflicht'],
                'ist_auslaufartikel'     => $vater['ist_auslaufartikel'],
                'ueberverkauf_erlaubt'   => $vater['ueberverkauf_erlaubt'],
                'aktiv'                  => 1,
                'zustand'                => 'neu',
                'zustand_vater_id'       => null,
            ];

            $kindId = $this->repo->insertKindArtikel($kind);
            $neuErstellteIds[] = $kindId;

            foreach (explode(',', $kombi['key']) as $w) {
                $this->repo->insertKombinationWert([
                    'kombination_id' => $kindId,
                    'wert_id'        => (int) $w,
                ]);
            }
        }

        Logger::log('varkombi.erstellen', 'artikel', $vater['id'], ['varKombi_anzahl' => count($kombis)]);

        return ['erfolg' => true, 'anzahl' => count($kombis), 'ids' => $neuErstellteIds];
    }
}
