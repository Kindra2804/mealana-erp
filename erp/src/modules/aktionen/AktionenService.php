<?php

require_once __DIR__ . '/AktionenRepository.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * AktionenService – Geschäftslogik für Aktionen und Aktionspreise
 *
 * Koordiniert AktionenRepository-Aufrufe, validiert Eingaben und loggt Aktionen.
 *
 * Starten einer Aktion ist nur möglich wenn mindestens eine Kategorie zugewiesen ist
 * (sonst würde eine "aktive" Aktion gar keine Artikel betreffen).
 *
 * savePreise() verarbeitet die Batch-Preiseingabe aus der Preismatrix:
 * - Leerer oder Null-Preis → deletePreis() (Aktionspreis entfernen)
 * - Preiseingabe → upsertPreis() mit Brutto+Netto (Netto aus Brutto berechnet wenn leer)
 *
 * getArtikelMitSubAchsenUndPreisen() baut die komplexe Datenstruktur für die
 * Preiseingabe-Maske: Artikel + Sub-Achsen + bestehende Preise + Normalpreise.
 */
class AktionenService
{
    private AktionenRepository $repo;

    public function __construct()
    {
        $this->repo = new AktionenRepository();
    }

    /**
     * Gibt das Repository-Objekt zurück.
     * Wird von AJAX-Endpunkten benötigt die direkt Repository-Methoden aufrufen
     * (z.B. getExistingPreiseFuerArtikel im Kategorie-Zuweisung-Modal).
     */
    public function getRepo(): AktionenRepository
    {
        return $this->repo;
    }

    /** Gibt alle Aktionen zurück (mit Status und Kategorien). */
    public function findAll(): array
    {
        return $this->repo->findAll();
    }

    /** Gibt eine Aktion anhand ID zurück. */
    public function findById(int $id): array|false
    {
        return $this->repo->findById($id);
    }

    /** Legt eine neue Aktion an. Name ist Pflichtfeld. */
    public function create(string $name, ?string $beschreibung): array
    {
        $name = trim($name);
        if (empty($name)) return ['erfolg' => false, 'fehler' => 'Name ist Pflichtfeld'];
        $id = $this->repo->insert($name, $beschreibung ?: null);
        Logger::log('aktion.anlegen', 'aktionen', $id, ['name' => $name]);
        return ['erfolg' => true, 'id' => $id];
    }

    /** Aktualisiert Name und Beschreibung einer Aktion. Name ist Pflichtfeld. */
    public function update(int $id, string $name, ?string $beschreibung): array
    {
        $name = trim($name);
        if (empty($name)) return ['erfolg' => false, 'fehler' => 'Name ist Pflichtfeld'];
        $this->repo->update($id, $name, $beschreibung ?: null);
        Logger::log('aktion.bearbeiten', 'aktionen', $id, ['name' => $name]);
        return ['erfolg' => true];
    }

    /**
     * Startet eine Aktion (setzt gestartet = 1).
     * Voraussetzung: mindestens eine Kategorie muss zugewiesen sein.
     * Ohne Kategorie hat die Aktion keine Auswirkung auf Preise.
     */
    public function starten(int $id): array
    {
        $aktion = $this->repo->findById($id);
        if (!$aktion) return ['erfolg' => false, 'fehler' => 'Aktion nicht gefunden'];
        if (empty($aktion['kategorien'])) return ['erfolg' => false, 'fehler' => 'Keine Kategorien zugewiesen'];
        $this->repo->setGestartet($id, true);
        Logger::log('aktion.starten', 'aktionen', $id);
        return ['erfolg' => true];
    }

    /** Stoppt eine laufende Aktion (setzt gestartet = 0 → Entwurf). */
    public function stoppen(int $id): array
    {
        $this->repo->setGestartet($id, false);
        Logger::log('aktion.stoppen', 'aktionen', $id);
        return ['erfolg' => true];
    }

    /** Löscht eine Aktion dauerhaft (inkl. Kategorien und Aktionspreise via Kaskade). */
    public function delete(int $id): array
    {
        $this->repo->delete($id);
        Logger::log('aktion.loeschen', 'aktionen', $id);
        return ['erfolg' => true];
    }

    /**
     * Weist eine Kategorie zu einer Aktion hinzu.
     * Validiert: Zeitraum Pflichtfelder, Von < Bis, keine zeitliche Überschneidung.
     * Eine Kategorie kann nicht in zwei Aktionen gleichzeitig aktiv sein.
     */
    public function addKategorie(int $aktionId, int $kategorieId, string $ab, string $bis): array
    {
        if (empty($ab) || empty($bis)) return ['erfolg' => false, 'fehler' => 'Zeitraum ist Pflichtfeld'];
        if ($ab > $bis)                return ['erfolg' => false, 'fehler' => 'Von-Datum muss vor Bis-Datum liegen'];

        if ($this->repo->hatZeitlicheUeberschneidung($aktionId, $kategorieId, $ab, $bis)) {
            return ['erfolg' => false, 'fehler' => 'Diese Kategorie ist im gewählten Zeitraum bereits in einer anderen Aktion'];
        }

        $akId = $this->repo->addKategorie($aktionId, $kategorieId, $ab, $bis);
        Logger::log('aktion.kategorie.hinzufuegen', 'aktionen', $aktionId, ['kategorie_id' => $kategorieId]);
        return ['erfolg' => true, 'ak_id' => $akId];
    }

    /**
     * Entfernt eine Kategorie-Zuweisung (inkl. Bereinigung der Aktionspreise).
     * Kein Logging hier weil removeKategorie() bereits intern bereinigt.
     */
    public function removeKategorie(int $akId): array
    {
        $this->repo->removeKategorie($akId);
        return ['erfolg' => true];
    }

    /** Gibt alle als Aktionskategorie markierten Kategorien zurück (für Dropdown). */
    public function getAktionsKategorienFuerAuswahl(): array
    {
        return $this->repo->getAktionsKategorienFuerAuswahl();
    }

    /** Gibt alle Kundengruppen zurück (für Preismatrix-Spalten). */
    public function getAlleKundengruppen(): array
    {
        return $this->repo->getAlleKundengruppen();
    }

    /**
     * Baut die Datenstruktur für die Aktionspreis-Eingabemaske.
     *
     * Schritt 1: Vater-Artikel der Kategorie laden
     * Schritt 2: Sub-Achsen für diese Artikel laden (für achsenabhängige Preise)
     * Schritt 3: Bestehende Aktionspreise laden (für Pre-Fill der Eingabefelder)
     * Schritt 4: Normale VK-Preise laden (als Referenz "Normalpreis: €X.XX")
     * Schritt 5: Alles zusammenführen pro Artikel
     *
     * Wenn ein Artikel Sub-Achsen hat: Preis pro Sub-Achse separat.
     * Wenn kein Sub-Achsen: ein globaler Preis (sub_achse_id = NULL).
     */
    public function getArtikelMitSubAchsenUndPreisen(int $aktionId, int $kategorieId, int $kgId): array
    {
        $artikel = $this->repo->getVaeterFuerKategorie($kategorieId);
        if (empty($artikel)) return [];

        $ids          = array_column($artikel, 'id');
        $subAchsen    = $this->repo->getSubAchsenFuerArtikel($ids);
        $preisIndex   = $this->repo->getExistingPreise($aktionId, $kgId);
        $normalPreise = $this->repo->getNormalePreise($ids, $kgId);

        // Sub-Achsen je Artikel gruppieren
        $achsenByArtikel = [];
        foreach ($subAchsen as $sa) {
            $achsenByArtikel[$sa['artikel_id']][] = $sa;
        }

        foreach ($artikel as &$a) {
            $a['sub_achsen'] = $achsenByArtikel[$a['id']] ?? [];
            $a['normal_vk']  = isset($normalPreise[$a['id']]) ? (float)$normalPreise[$a['id']] : null;
            // Bestehende Preise aus dem Index eintragen
            if (empty($a['sub_achsen'])) {
                $key         = $a['id'] . ':0';
                $a['preis']  = $preisIndex[$key] ?? null;
            } else {
                foreach ($a['sub_achsen'] as &$sa) {
                    $key        = $a['id'] . ':' . $sa['achse_id'];
                    $sa['preis'] = $preisIndex[$key] ?? null;
                }
                unset($sa);
            }
        }
        unset($a);

        return $artikel;
    }

    /**
     * Speichert Aktionspreise aus der Batch-Eingabemaske.
     * Preis = 0 oder leer → deletePreis() (Preis entfernen)
     * Netto wird aus Brutto berechnet wenn nicht angegeben (Brutto / (1 + MwSt/100)).
     * Kommazahl-Normalisierung: "9,99" → 9.99 (deutsches Zahlenformat akzeptieren).
     */
    public function savePreise(int $aktionId, int $kgId, array $preise): array
    {
        $gespeichert = 0;
        $geloescht   = 0;
        foreach ($preise as $p) {
            $artikelId  = (int)($p['artikel_id'] ?? 0);
            $subAchseId = isset($p['sub_achse_id']) && $p['sub_achse_id'] !== '' ? (int)$p['sub_achse_id'] : null;
            $bruttoVk   = str_replace(',', '.', (string)($p['brutto_vk'] ?? ''));

            if (!$artikelId) continue;

            if ($bruttoVk === '' || $bruttoVk === '0' || $bruttoVk === '0.00') {
                $this->repo->deletePreis($aktionId, $artikelId, $subAchseId, $kgId);
                $geloescht++;
                continue;
            }

            $bruttoVk = (float)$bruttoVk;
            $mwstSatz = (float)($p['mwst_satz'] ?? 20.0);
            $nettoRaw = str_replace(',', '.', (string)($p['netto_vk'] ?? ''));
            // Netto aus Brutto berechnen wenn kein Netto-Wert vorhanden
            $nettoVk  = ($nettoRaw !== '' && (float)$nettoRaw > 0)
                ? (float)$nettoRaw
                : $bruttoVk / (1 + $mwstSatz / 100);

            $this->repo->upsertPreis($aktionId, $artikelId, $subAchseId, $kgId, round($bruttoVk, 2), round($nettoVk, 4));
            $gespeichert++;
        }

        Logger::log('aktion.preise.speichern', 'aktionen', $aktionId, ['gespeichert' => $gespeichert, 'geloescht' => $geloescht, 'kg_id' => $kgId]);
        return ['erfolg' => true, 'gespeichert' => $gespeichert, 'geloescht' => $geloescht];
    }
}
