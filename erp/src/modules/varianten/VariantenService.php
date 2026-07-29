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
     * Text-Korrektur bei in-use Werten: jeder Wert bringt seine wert_id mit (0 = neu angelegt).
     * Gehört die id zu einem geschützten Wert und hat sich der Text geändert (z.B. Tippfehler-Fix),
     * wird der bestehende Datensatz per UPDATE korrigiert statt einen zweiten, unbenutzten Wert anzulegen.
     */
    public function speichereAchsenUndWerte(int $artikelId, array $achsenIds, array $werte): array
    {
        $inUseIds   = array_map('intval', $this->repo->findWertIdsInUse($artikelId));
        $inUseIdSet = array_flip($inUseIds);

        // In-use Werte aus DB holen – Lookup (achse_id|wert) → id für Duplikat-Check, id → wert für Text-Update
        $currentWerte  = $this->repo->findWerteByArtikelId($artikelId);
        $inUseWerte    = array_filter($currentWerte, fn($w) => in_array((int)$w['id'], $inUseIds));
        $inUseLookup   = [];
        $inUseTextById = [];
        foreach ($inUseWerte as $w) {
            $inUseLookup[(int)$w['achse_id'] . '|' . $w['wert']] = (int)$w['id'];
            $inUseTextById[(int)$w['id']] = $w['wert'];
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

        // Neue Achsen einfügen (nur fehlende) + sort_order für alle setzen
        $currentAchsen    = $this->repo->findAchsenByArtikelId($artikelId);
        $existingAchseSet = array_flip(array_map(fn($a) => (int)$a['achse_id'], $currentAchsen));
        foreach ($achsenIds as $sortOrder => $achseId) {
            if (!isset($existingAchseSet[$achseId])) {
                $this->repo->insertArtikelAchse(['artikel_id' => $artikelId, 'achse_id' => $achseId, 'sort_order' => $sortOrder]);
            } else {
                $this->repo->updateAchseSortOrder($artikelId, $achseId, $sortOrder);
            }
        }

        // Werte einfügen: nur wenn nicht bereits als in-use vorhanden (Duplikat vermeiden)
        // Ausnahme: bringt der Wert die id eines geschützten Werts mit, wird stattdessen dessen Text korrigiert
        foreach ($werte as $wert) {
            $wertId = (int)($wert['id'] ?? 0);
            if ($wertId > 0 && isset($inUseIdSet[$wertId])) {
                if (($inUseTextById[$wertId] ?? null) !== $wert['wert']) {
                    $this->repo->updateWertText($wertId, $wert['wert']);
                }
                continue;
            }

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

    public function updateAchsePreis(int $artikelId, int $achseId, string $modus, float $wert): void
    {
        $this->repo->updateAchsePreis($artikelId, $achseId, $modus, $wert);
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
        $neuErstellteIds  = [];
        $preisAnpassungen = [];
        $eanMap           = [];   // [kindId => ean]

        // Preis-Lookup für diesen Vater
        $achsenPreisMap = $this->repo->findAchsenPreisMap($vater['id']);
        $wertAchseMap   = !empty($achsenPreisMap) ? $this->repo->findWertAchseMap($vater['id']) : [];

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

            // Existiert die Artikelnummer bereits? (z.B. Abbruch eines früheren Durchlaufs)
            $existingId = $this->repo->findIdByArtikelnummer($kind['artikelnummer']);
            $kindId     = $existingId ?? $this->repo->insertKindArtikel($kind);
            $neuErstellteIds[] = $kindId;

            $ean = trim($kombi['ean'] ?? '');
            if ($ean !== '') {
                $eanMap[$kindId] = $ean;
            }

            // Preisanpassung für dieses Kind berechnen
            if (!empty($achsenPreisMap)) {
                $direktpreis   = null;
                $aufpreisSumme = 0.0;
                foreach (array_map('intval', explode(',', $kombi['key'])) as $wid) {
                    $achseId  = $wertAchseMap[$wid] ?? null;
                    $preisInf = $achseId ? ($achsenPreisMap[$achseId] ?? null) : null;
                    if (!$preisInf) continue;
                    if ($preisInf['modus'] === 'direktpreis') {
                        $direktpreis = $preisInf['preis_wert'];
                    } else {
                        $aufpreisSumme += $preisInf['preis_wert'];
                    }
                }
                if ($direktpreis !== null) {
                    $preisAnpassungen[$kindId] = ['modus' => 'direktpreis', 'preis_wert' => $direktpreis + $aufpreisSumme];
                } elseif ($aufpreisSumme > 0) {
                    $preisAnpassungen[$kindId] = ['modus' => 'aufpreis', 'preis_wert' => $aufpreisSumme];
                }
            }

            foreach (explode(',', $kombi['key']) as $w) {
                $this->repo->insertKombinationWert([
                    'kombination_id' => $kindId,
                    'wert_id'        => (int) $w,
                ]);
            }
        }

        Logger::log('varkombi.erstellen', 'artikel', $vater['id'], ['varKombi_anzahl' => count($kombis)]);

        return ['erfolg' => true, 'anzahl' => count($kombis), 'ids' => $neuErstellteIds, 'preisAnpassungen' => $preisAnpassungen, 'eanMap' => $eanMap];
    }

    /**
     * Baut aus einer flachen Achsen+Werte-Zuweisung eines Artikels die
     * "Dimensionen" für Variantenkombinationen: Sub-Achsen, deren Parent NICHT
     * direkt zugewiesen ist, werden mit allen Geschwistern zu EINER Dimension
     * unioniert statt als eigene Dimension behandelt zu werden -- Sub-Achsen
     * (z.B. "Mix"/"Uni" unter Gruppenachse "Farbe") sind fachliche
     * Unterkategorien der Parent-Achse, keine eigenständige Produkt-Eigenschaft.
     *
     * Gemeinsame Logik für den VarKombi-Generator (artikel/detail.php) UND den
     * WooCommerce-Shop-Sync (ShopSyncService) -- beide MÜSSEN dieselbe Regel
     * verwenden, sonst entstehen im Shop Kombinationen, die es nie geben kann
     * (z.B. gleichzeitig "Mix Rot" + "Uni Blau" wählbar). Genau das war ein
     * echter Bug (2026-07-29, siehe project_shop_sync.md): der Shop-Sync hatte
     * diese Union-Regel nicht und behandelte jede Sub-Achse als eigenes
     * WooCommerce-Attribut.
     *
     * @param array $achsen  wie findAchsenByArtikelId() liefert (braucht mind.
     *                       achse_id, name, abhaengig_von_achse_id)
     * @param array $werte   wie findWerteByArtikelId() liefert (braucht mind. achse_id)
     * @param array<int,string> $achseNamenById Name-Lookup, MUSS auch nicht
     *                       zugewiesene Eltern-Achsen enthalten (z.B. AchsenRepository::findAll())
     * @return array<int, array{name:string, achse_id:int, werte:array}>
     */
    public function baueAchsenDimensionen(array $achsen, array $werte, array $achseNamenById): array
    {
        $werteProAchse = [];
        foreach ($werte as $w) {
            $werteProAchse[$w['achse_id']][] = $w;
        }

        $assignedAchseIdSet = array_flip(array_map('intval', array_column($achsen, 'achse_id')));

        $subAchsenByParent = [];
        foreach ($achsen as $a) {
            $pid = (int)($a['abhaengig_von_achse_id'] ?? 0);
            if ($pid > 0) {
                $subAchsenByParent[$pid][] = (int)$a['achse_id'];
            }
        }

        $dimensionen = [];
        $verarbeitet = [];

        foreach ($achsen as $a) {
            $aId = (int)$a['achse_id'];
            if (isset($verarbeitet[$aId])) continue;

            $pid = (int)($a['abhaengig_von_achse_id'] ?? 0);

            if ($pid > 0 && isset($assignedAchseIdSet[$pid])) {
                // Sub-Achse, Parent zugewiesen -> wird beim Parent-Durchlauf eingebaut
                $verarbeitet[$aId] = true;
                continue;
            }

            if ($pid > 0 && !isset($assignedAchseIdSet[$pid])) {
                // Sub-Achse, Parent NICHT zugewiesen -> UNION aller Geschwister = eine Dimension
                $gruppe = [];
                foreach ($subAchsenByParent[$pid] ?? [] as $sibId) {
                    if (isset($verarbeitet[$sibId])) continue;
                    $sibSuffix = $achseNamenById[$sibId] ?? '';
                    foreach ($werteProAchse[$sibId] ?? [] as $w) {
                        $w['achse_suffix'] = $sibSuffix;
                        $gruppe[] = $w;
                    }
                    $verarbeitet[$sibId] = true;
                }
                if (!empty($gruppe)) {
                    $dimensionen[] = ['name' => $achseNamenById[$pid] ?? '?', 'achse_id' => $pid, 'werte' => $gruppe];
                }
                continue;
            }

            // Root-Achse (pid=0): eigene Werte + ALLE Sub-Achsen-Werte (UNION, mit Suffix)
            $gruppe = [];
            $verarbeitet[$aId] = true;

            foreach ($werteProAchse[$aId] ?? [] as $w) {
                $gruppe[] = $w;
            }

            foreach ($subAchsenByParent[$aId] ?? [] as $subId) {
                if (isset($verarbeitet[$subId])) continue;
                $subSuffix = $achseNamenById[$subId] ?? '';
                foreach ($werteProAchse[$subId] ?? [] as $w) {
                    $w['achse_suffix'] = $subSuffix;
                    $gruppe[] = $w;
                }
                $verarbeitet[$subId] = true;
            }

            if (!empty($gruppe)) {
                $dimensionen[] = ['name' => $a['name'], 'achse_id' => $aId, 'werte' => $gruppe];
            }
        }

        return $dimensionen;
    }
}
