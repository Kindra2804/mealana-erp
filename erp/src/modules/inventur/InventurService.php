<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/InventurRepository.php';
require_once __DIR__ . '/../lager/LagerRepository.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';

/**
 * InventurService – Lebenszyklus der Inventur-Läufe (Slice 1: nur Kopf + Scope)
 *
 * Status-Flow: laufend → pausiert → (fortgesetzt: neuer Lauf mit vorgaenger_lauf_id)
 *                       → abgebrochen
 * "abgeschlossen" kommt erst mit der Zählliste/Abschluss-Logik (spätere Slice) —
 * ohne echte Zählpositionen gibt es hier noch nichts sinnvoll abzuschließen.
 */
class InventurService
{
    private InventurRepository $repo;

    public function __construct()
    {
        $this->repo = new InventurRepository();
    }

    public function getAlle(): array
    {
        return $this->repo->findAlle();
    }

    public function getById(int $id): array|false
    {
        return $this->repo->findById($id);
    }

    /**
     * Startet einen neuen Inventur-Lauf.
     * Validiert: scope_tabelle muss bekannt sein, scope_id muss existieren.
     */
    public function starten(array $data): array
    {
        $scopeTabelle = $data['scope_tabelle'] ?? '';
        $scopeId      = (int)($data['scope_id'] ?? 0);

        if (!in_array($scopeTabelle, InventurRepository::gueltigeScopeTabellen(), true)) {
            return ['erfolg' => false, 'fehler' => ['Ungültiger Scope-Typ']];
        }
        if (!$scopeId) {
            return ['erfolg' => false, 'fehler' => ['Bitte ein Ziel für den Scope wählen']];
        }

        $bezeichnung = $this->repo->findScopeBezeichnung($scopeTabelle, $scopeId);
        if ($bezeichnung === null) {
            return ['erfolg' => false, 'fehler' => ['Gewähltes Scope-Ziel wurde nicht gefunden']];
        }

        $vorgaengerId = !empty($data['vorgaenger_lauf_id']) ? (int)$data['vorgaenger_lauf_id'] : null;

        $id = $this->repo->insert([
            'scope_tabelle'      => $scopeTabelle,
            'scope_id'           => $scopeId,
            'scope_bezeichnung'  => $bezeichnung,
            'blind_modus'        => !empty($data['blind_modus']) ? 1 : 0,
            'vorgaenger_lauf_id' => $vorgaengerId,
            'notiz'              => !empty($data['notiz']) ? trim($data['notiz']) : null,
            'benutzer_id'        => $_SESSION['benutzer']['id'],
        ]);

        Logger::log('inventur.gestartet', 'inventur_laeufe', $id, [
            'scope_tabelle' => $scopeTabelle,
            'scope'         => $bezeichnung,
        ]);

        return ['erfolg' => true, 'id' => $id];
    }

    /** Pausiert einen laufenden Inventur-Lauf — kann später fortgesetzt werden. */
    public function pausieren(int $id): array
    {
        $lauf = $this->repo->findById($id);
        if (!$lauf || $lauf['status'] !== 'laufend') {
            return ['erfolg' => false, 'fehler' => ['Nur laufende Inventuren können pausiert werden']];
        }
        $this->repo->setStatus($id, 'pausiert', false);
        Logger::log('inventur.pausiert', 'inventur_laeufe', $id);
        return ['erfolg' => true];
    }

    /** Bricht einen Inventur-Lauf endgültig ab (kein Fortsetzen mehr aus diesem Lauf selbst möglich). */
    public function abbrechen(int $id): array
    {
        $lauf = $this->repo->findById($id);
        if (!$lauf || !in_array($lauf['status'], ['laufend', 'pausiert'], true)) {
            return ['erfolg' => false, 'fehler' => ['Inventur kann nicht abgebrochen werden']];
        }
        $this->repo->setStatus($id, 'abgebrochen', true);
        Logger::log('inventur.abgebrochen', 'inventur_laeufe', $id);
        return ['erfolg' => true];
    }

    /**
     * Startet einen neuen Lauf als Fortsetzung eines pausierten/abgebrochenen Laufs —
     * gleicher Scope, Referenz auf den Vorgänger. Welche Positionen dabei als
     * "noch fehlend" vorbelegt werden, klärt sich mit der Zählliste (spätere Slice).
     */
    public function fortsetzen(int $vorgaengerId): array
    {
        $vorgaenger = $this->repo->findById($vorgaengerId);
        if (!$vorgaenger || !in_array($vorgaenger['status'], ['pausiert', 'abgebrochen'], true)) {
            return ['erfolg' => false, 'fehler' => ['Nur pausierte oder abgebrochene Inventuren können fortgesetzt werden']];
        }

        return $this->starten([
            'scope_tabelle'      => $vorgaenger['scope_tabelle'],
            'scope_id'           => $vorgaenger['scope_id'],
            'blind_modus'        => $vorgaenger['blind_modus'],
            'vorgaenger_lauf_id' => $vorgaengerId,
            'notiz'              => 'Fortsetzung von Lauf #' . $vorgaengerId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Auswahllisten für die Scope-Auswahl (neu.php)
    // -------------------------------------------------------------------------

    public function getAlleLagerFuerAuswahl(): array
    {
        return $this->repo->findAlleLagerFuerAuswahl();
    }

    public function getAlleLagerplaetzeFuerAuswahl(): array
    {
        return $this->repo->findAlleLagerplaetzeFuerAuswahl();
    }

    public function getAlleKategorienFuerAuswahl(): array
    {
        return $this->repo->findAlleKategorienFuerAuswahl();
    }

    public function getAlleMietfaecherFuerAuswahl(): array
    {
        return $this->repo->findAlleMietfaecherFuerAuswahl();
    }

    public function getArtikelFuerSuche(string $suche): array
    {
        return $this->repo->findArtikelFuerSuche($suche);
    }

    // -------------------------------------------------------------------------
    // Zählliste (Slice 2)
    // -------------------------------------------------------------------------

    /**
     * Gibt die Soll-Liste für einen Lauf zurück — Auflösung hängt vom Scope ab.
     * 'mietfaecher' hat noch keine Soll-Vergleichslogik (siehe project_inventur_konzept,
     * Semantik dafür ist noch offen) — leere Liste, reine Freitext-Erfassung.
     */
    public function getSollListe(array $lauf): array
    {
        return match ($lauf['scope_tabelle']) {
            'lager'        => $this->repo->findSollListeLager((int)$lauf['scope_id']),
            'lagerplaetze' => $this->repo->findSollListeLagerplatz((int)$lauf['scope_id']),
            'kategorien'   => $this->repo->findSollListeKategorie((int)$lauf['scope_id']),
            'artikel'      => $this->repo->findSollListeArtikel((int)$lauf['scope_id']),
            default        => [],
        };
    }

    public function getPositionenFuerLauf(int $laufId): array
    {
        return $this->repo->findPositionenFuerLauf($laufId);
    }

    /**
     * Fortschritt eines Laufs: wie viele Soll-Positionen sind schon gezählt.
     * Bei Scope "Lagerplatz" ist die Soll-Liste beim allerersten Zählgang bewusst
     * leer (siehe findSollListeLagerplatz) — dort gibt es keinen sinnvollen
     * Prozentwert, nur die Anzahl bereits frei erfasster Funde.
     */
    public function getFortschritt(array $lauf): array
    {
        $soll = $this->getSollListe($lauf);
        $positionen = $this->getPositionenFuerLauf((int)$lauf['id']);

        if (empty($soll)) {
            return ['gesamt' => 0, 'gezaehlt' => count($positionen), 'prozent' => null];
        }

        $mitLagerplatz = $lauf['scope_tabelle'] === 'lagerplaetze';
        $sollKeys = [];
        foreach ($soll as $s) {
            $sollKeys[$this->fortschrittSchluessel($s, $mitLagerplatz)] = true;
        }

        $treffer = 0;
        foreach ($positionen as $p) {
            $key = $this->fortschrittSchluessel($p, $mitLagerplatz);
            if (isset($sollKeys[$key])) {
                unset($sollKeys[$key]);
                $treffer++;
            }
        }

        $gesamt = count($soll);
        return ['gesamt' => $gesamt, 'gezaehlt' => $treffer, 'prozent' => (int)round($treffer / $gesamt * 100)];
    }

    /**
     * Identitäts-Schlüssel für den Soll/Ist-Abgleich beim Fortschritt. lagerplatz_id
     * fließt nur bei Scope "Lagerplatz" ein — bei Scope "Lager" trägt eine Position
     * zwar oft einen lagerplatz_id-Wert (informativer "aktueller Arbeitsbereich"),
     * das ist aber kein Teil der Soll-Identität (die Soll-Liste kennt dort gar
     * keinen Lagerplatz) und würde den Abgleich sonst fälschlich verfehlen lassen.
     */
    private function fortschrittSchluessel(array $row, bool $mitLagerplatz): string
    {
        $key = ($row['artikel_id'] ?? '') . '|' . ($row['lager_id'] ?? '') . '|' . ($row['charge'] ?? '');
        if ($mitLagerplatz) {
            $key .= '|' . ($row['lagerplatz_id'] ?? '');
        }
        return $key;
    }

    /**
     * Bucht eine Zählung: legt die Position an falls neu, sonst wird die bestehende
     * aktualisiert (z.B. wenn ein zweiter Zähler denselben Artikel nochmal erfasst).
     * lagerId wird bei Lagerplatz-Scope automatisch aus dem Lagerplatz aufgelöst,
     * falls nicht explizit übergeben.
     */
    public function bucheZaehlung(
        int $laufId,
        int $artikelId,
        ?int $lagerId,
        ?int $lagerplatzId,
        ?string $charge,
        float $istMenge,
        ?string $notiz,
        ?float $sollMenge = null
    ): array {
        if (!$lagerId && $lagerplatzId) {
            $lagerId = $this->repo->findLagerIdFuerLagerplatz($lagerplatzId);
        }
        if (!$lagerId) {
            return ['erfolg' => false, 'fehler' => ['Lager konnte nicht bestimmt werden']];
        }
        if ($istMenge < 0) {
            return ['erfolg' => false, 'fehler' => ['Menge darf nicht negativ sein']];
        }

        $benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
        $charge     = $charge !== null && $charge !== '' ? $charge : null;

        $bestehend = $this->repo->findPosition($laufId, $artikelId, $lagerId, $lagerplatzId, $charge);

        // Begründungspflicht bei Abweichung: unterhalb Manager-Rang (70, gleicher
        // Schwellwert wie beim Manager-Override-PIN) ist die Notiz Pflicht, ab
        // Manager optional (Jacky-Entscheidung 2026-07-18).
        $sollMengeEffektiv = $bestehend ? $bestehend['soll_menge'] : $sollMenge;
        $rang = (int)($_SESSION['benutzer']['rolle_rang'] ?? 0);
        if ($sollMengeEffektiv !== null && abs($istMenge - (float)$sollMengeEffektiv) > 0.01 && empty($notiz) && $rang < 70) {
            return ['erfolg' => false, 'fehler' => ['Bei Abweichung vom Soll-Bestand ist eine Notiz Pflicht']];
        }

        if ($bestehend) {
            $this->repo->updatePosition((int)$bestehend['id'], $istMenge, $notiz, $benutzerId);
            $id = (int)$bestehend['id'];
        } else {
            $id = $this->repo->insertPosition([
                'inventur_lauf_id' => $laufId,
                'artikel_id'       => $artikelId,
                'lager_id'         => $lagerId,
                'lagerplatz_id'    => $lagerplatzId,
                'charge'           => $charge,
                'soll_menge'       => $sollMenge,
                'ist_menge'        => $istMenge,
                'status'           => 'gezaehlt',
                'notiz'            => $notiz,
                'gezaehlt_von'     => $benutzerId,
            ]);
        }

        Logger::log('inventur.gezaehlt', 'inventur_positionen', $id, [
            'inventur_lauf_id' => $laufId,
            'artikel_id'       => $artikelId,
            'ist_menge'        => $istMenge,
        ]);

        return ['erfolg' => true, 'id' => $id];
    }

    // -------------------------------------------------------------------------
    // Live-Sperre (Slice 3)
    // -------------------------------------------------------------------------

    /**
     * Beansprucht einen Lagerplatz zum Zählen. Informativ, first-come — blockt nicht,
     * gibt aber eine Warnung zurück wenn kurz zuvor schon jemand anderer dort aktiv war.
     */
    public function lagerplatzWaehlen(int $laufId, int $lagerplatzId, int $benutzerId): array
    {
        $bestehend = $this->repo->findAktiveSperre($laufId, $lagerplatzId);
        $warnung   = null;

        if ($bestehend && (int)$bestehend['benutzer_id'] !== $benutzerId) {
            $warnung = 'Wird gerade gezählt von ' . $bestehend['benutzer_name'] . ' (seit ' . date('H:i', strtotime($bestehend['aktiv_seit'])) . ') — bitte kurz abstimmen.';
        }

        $this->repo->upsertSperre($laufId, $lagerplatzId, $benutzerId);

        return ['erfolg' => true, 'warnung' => $warnung];
    }

    // -------------------------------------------------------------------------
    // Buchungssperre (Slice 3): Voll-Lager-Inventur blockiert Kasse/Wareneingang
    // -------------------------------------------------------------------------

    /**
     * Prüft ob für ein Lager gerade eine laufende Voll-Scope-Inventur existiert.
     * Wird von KassenService::erstelleBon() und WareneingangService::bucheMenge()
     * als Gate genutzt — beide Buchungswege müssen während einer Voll-Lager-Zählung
     * pausiert sein, sonst verfälscht sich der Soll-Bestand während gezählt wird.
     */
    public function gibtEsLaufendeVollinventur(int $lagerId): bool
    {
        return $this->repo->findLaufendeVollinventur($lagerId) !== false;
    }

    // -------------------------------------------------------------------------
    // Abschluss (Slice 4, überarbeitet 2026-07-18) — echte Bestandskorrektur
    // -------------------------------------------------------------------------
    //
    // Modell nach Jackys Klarstellung (2026-07-18, entspricht dem realen JTL-Workflow):
    // Chargen und Lagerplätze spielen für die Differenzliste KEINE Rolle — nur der
    // Gesamtbestand alt vs. neu pro Artikel+Lager zählt (Inventur+ oder Schwund).
    // Ein früherer Ansatz (Vollständigkeits-Gate pro Charge-Zeichenkette) wurde wieder
    // verworfen: bei mehreren Zählern in unterschiedlichen Bereichen kann niemand wissen,
    // ob eine "fehlende" Charge nicht einfach woanders liegt — ein knallharter Chargen-
    // Abgleich hätte ständig fälschlich "unvollständig" gemeldet.
    //
    // Komplett ungezählte Artikel werden schlicht übersprungen (keine Fehlermeldung, kein
    // Blocker für andere Artikel — wie bei JTL). Nur Artikel mit mindestens einer gezählten
    // Position werden überhaupt betrachtet.

    /**
     * Berechnet die Abgleich-Gruppen (Artikel × Lager) für einen Lauf, ohne zu buchen.
     * Gemeinsame Grundlage für Vorschau UND tatsächlichen Abschluss.
     *
     * Scope-Sonderfall "Lagerplatz" (Jacky-Kontrollfrage 2026-07-18): hier darf "Vorher"
     * NICHT der Gesamtbestand des ganzen Lagers sein, sondern nur das, was bisher an
     * GENAU DIESEM Lagerplatz hinterlegt war — sonst würde ein Artikel, der an einem
     * anderen (in diesem Lauf gar nicht gezählten) Platz weiterhin unangetastet liegt,
     * fälschlich als riesiger Schwund erscheinen. Bei den anderen Scopes (ganzes Lager,
     * Kategorie, Artikel) bleibt der Gesamtbestand-Vergleich richtig, weil dort der
     * komplette Bestand des Artikels im Lager Gegenstand der Zählung ist.
     */
    private function berechneAbgleich(array $lauf): array
    {
        $alle     = $this->repo->findPositionenFuerLauf((int)$lauf['id']);
        $gezaehlt = array_filter($alle, fn($p) => $p['status'] === 'gezaehlt');

        $gezaehltNachGruppe = [];
        foreach ($gezaehlt as $p) {
            $gezaehltNachGruppe[$p['artikel_id'] . '|' . $p['lager_id']][] = $p;
        }

        $istLagerplatzScope = $lauf['scope_tabelle'] === 'lagerplaetze';
        $scopeLagerplatzId  = $istLagerplatzScope ? (int)$lauf['scope_id'] : null;

        $gruppen = [];
        foreach ($gezaehltNachGruppe as $key => $positionen) {
            [$artikelId, $lagerId] = array_map('intval', explode('|', $key));

            if ($istLagerplatzScope) {
                // "Vorher" = nur der bisher an DIESEM Platz hinterlegte Anteil.
                $alteAmPlatz = $this->repo->findChargenAmLagerplatz($artikelId, $lagerId, $scopeLagerplatzId);
                $alteVerteilung = [];
                foreach ($alteAmPlatz as $c) {
                    $alteVerteilung[$c['charge'] ?? ''] = (float)$c['menge'];
                }
            } else {
                // "Vorher" = Gesamtbestand jetzt (nicht der Soll-Snapshot vom Zählzeitpunkt) —
                // passt zur Buchungssperre bei Voll-Scope; bei Teil-Scope ein akzeptiertes
                // Restrisiko, falls zwischen Zählen und Abschluss noch etwas verkauft wurde.
                $alteChargen = $this->repo->findAktuelleChargen($artikelId, $lagerId);
                $alteVerteilung = [];
                foreach ($alteChargen as $c) {
                    $alteVerteilung[$c['charge'] ?? ''] = (float)$c['bestand'];
                }
            }

            $summeVorher  = array_sum($alteVerteilung);
            $summeNachher = array_sum(array_map(fn($p) => (float)$p['ist_menge'], $positionen));

            // Verteilung (Menge je Charge) alt vs. neu vergleichen — auch bei gleicher
            // Gesamtsumme kann sich die Aufteilung geändert haben (Chargen zusammengelegt).
            $neueVerteilung = [];
            foreach ($positionen as $p) {
                $ck = $p['charge'] ?? '';
                $neueVerteilung[$ck] = ($neueVerteilung[$ck] ?? 0) + (float)$p['ist_menge'];
            }
            $verteilungGeaendert = false;
            foreach (array_unique(array_merge(array_keys($alteVerteilung), array_keys($neueVerteilung))) as $ck) {
                if (abs(($alteVerteilung[$ck] ?? 0) - ($neueVerteilung[$ck] ?? 0)) > 0.01) {
                    $verteilungGeaendert = true;
                    break;
                }
            }

            $gruppen[] = [
                'artikel_id'           => $artikelId,
                'lager_id'             => $lagerId,
                'artikel_name'         => $positionen[0]['artikel_name'],
                'artikelnummer'        => $positionen[0]['artikelnummer'],
                'lager_name'           => $positionen[0]['lager_name'],
                'summe_vorher'         => $summeVorher,
                'summe_nachher'        => $summeNachher,
                'verteilung_geaendert' => $verteilungGeaendert,
                'positionen'           => $positionen,
                'ist_lagerplatz_scope' => $istLagerplatzScope,
                'scope_lagerplatz_id'  => $scopeLagerplatzId,
                'alte_verteilung'      => $alteVerteilung,
            ];
        }

        return $gruppen;
    }

    /**
     * Vorschau vor dem eigentlichen Abschluss: zeigt jede Artikel/Lager-Gruppe mit
     * Mengenabweichung ODER geänderter Chargen-/Lagerplatzverteilung. Bucht nichts.
     */
    public function vorschauAbschluss(int $laufId): array
    {
        $lauf = $this->getById($laufId);
        if (!$lauf) {
            return ['erfolg' => false, 'fehler' => ['Lauf nicht gefunden']];
        }

        $gruppen = $this->berechneAbgleich($lauf);

        $abweichungen = [];
        $unveraendertAnzahl = 0;
        foreach ($gruppen as $g) {
            $diff = $g['summe_nachher'] - $g['summe_vorher'];
            if (abs($diff) > 0.01 || $g['verteilung_geaendert']) {
                $abweichungen[] = [
                    'artikel_name'         => $g['artikel_name'],
                    'artikelnummer'        => $g['artikelnummer'],
                    'lager_name'           => $g['lager_name'],
                    'summe_vorher'         => $g['summe_vorher'],
                    'summe_nachher'        => $g['summe_nachher'],
                    'differenz'            => $diff,
                    'verteilung_geaendert' => $g['verteilung_geaendert'],
                    'positionen'           => $g['positionen'],
                ];
            } else {
                $unveraendertAnzahl++;
            }
        }

        return [
            'erfolg'              => true,
            'lauf'                => $lauf,
            'abweichungen'        => $abweichungen,
            'unveraendert_anzahl' => $unveraendertAnzahl,
        ];
    }

    /**
     * Führt den Abschluss tatsächlich durch:
     *  - Gruppe unverändert (Summe gleich + Verteilung gleich) → nur letzte_inventur_am setzen.
     *  - Verteilung geändert (auch bei gleicher Summe) → Chargen-Zeilen aktualisieren.
     *  - Echte Mengenabweichung → zusätzlich EINE lager_bewegungen-Zeile (Typ inventur/
     *    schwund) für die Netto-Differenz — pro Charge gebucht ergäbe bei freier
     *    Chargen-Umverteilung keine sinnvolle 1:1-Zuordnung mehr.
     * Fehlbestand ohne Notiz verweigert den GESAMTEN Abschluss (nicht nur die eine Gruppe).
     */
    public function abschliessen(int $laufId): array
    {
        $lauf = $this->getById($laufId);
        if (!$lauf || !in_array($lauf['status'], ['laufend', 'pausiert'], true)) {
            return ['erfolg' => false, 'fehler' => ['Inventur kann nicht abgeschlossen werden']];
        }

        $gruppen = $this->berechneAbgleich($lauf);

        foreach ($gruppen as $g) {
            if ($g['summe_nachher'] >= $g['summe_vorher'] - 0.01) continue;
            $hatNotiz = false;
            foreach ($g['positionen'] as $p) {
                if (!empty($p['notiz'])) { $hatNotiz = true; break; }
            }
            if (!$hatNotiz) {
                return ['erfolg' => false, 'fehler' => [
                    "Fehlbestand bei {$g['artikel_name']} ({$g['artikelnummer']}) — bitte auf der Zählliste eine Notiz nachtragen, bevor abgeschlossen werden kann."
                ]];
            }
        }

        $lagerRepo   = new LagerRepository();
        $artikelRepo = new ArtikelRepository();
        $heute       = date('Y-m-d');

        $korrigiert   = [];
        $unveraendert = [];

        foreach ($gruppen as $g) {
            $diffGesamt = $g['summe_nachher'] - $g['summe_vorher'];

            if (abs($diffGesamt) <= 0.01 && !$g['verteilung_geaendert']) {
                $artikelRepo->setLetzteInventur($g['artikel_id'], $heute);
                $unveraendert[] = ['artikel_name' => $g['artikel_name']];
                continue;
            }

            // Menge je Charge aggregieren (dieselbe Charge kann mehrfach gezählt worden
            // sein — dann fließen mehrere Positionen in eine Bestandszeile).
            $mengeJeCharge = [];
            foreach ($g['positionen'] as $p) {
                $ck = $p['charge'] ?? '';
                $mengeJeCharge[$ck] = ($mengeJeCharge[$ck] ?? 0) + (float)$p['ist_menge'];
            }

            if ($g['ist_lagerplatz_scope']) {
                // Lagerplatz-Scope: NIE den Gesamtbestand überschreiben — nur um die
                // lokale Differenz (an diesem Platz) anpassen, weil derselbe Artikel an
                // anderen Plätzen unangetastet weiterliegen kann. Chargen, die vorher an
                // diesem Platz lagen aber jetzt nicht mehr gezählt wurden, bleiben bewusst
                // unangetastet (nicht auf 0 gesetzt) — könnte schlicht übersehen worden sein.
                foreach ($mengeJeCharge as $ck => $mengeNeuLokal) {
                    $charge = $ck === '' ? null : $ck;
                    $mengeAltLokal = $g['alte_verteilung'][$ck] ?? 0.0;
                    $diffLokal = $mengeNeuLokal - $mengeAltLokal;

                    $aktuellerGesamt = $lagerRepo->getBestand($g['artikel_id'], $g['lager_id'], $charge);
                    $lagerRepo->upsertBestand([
                        'artikel_id'     => $g['artikel_id'],
                        'lager_id'       => $g['lager_id'],
                        'charge'         => $charge,
                        'charge_status'  => $charge !== null ? 'erfasst' : null,
                        'bestand'        => $aktuellerGesamt + $diffLokal,
                        'mindestbestand' => 0,
                    ]);

                    $lagerbestandId = $lagerRepo->findLagerbestandIdByKey($g['artikel_id'], $g['lager_id'], $charge);
                    if ($lagerbestandId) {
                        $lagerRepo->upsertLagerbestandLagerplatz($lagerbestandId, $g['scope_lagerplatz_id'], $mengeNeuLokal);
                    }
                }
            } else {
                foreach ($mengeJeCharge as $ck => $menge) {
                    $charge = $ck === '' ? null : $ck;
                    $lagerRepo->upsertBestand([
                        'artikel_id'     => $g['artikel_id'],
                        'lager_id'       => $g['lager_id'],
                        'charge'         => $charge,
                        'charge_status'  => $charge !== null ? 'erfasst' : null,
                        'bestand'        => $menge,
                        'mindestbestand' => 0,
                    ]);
                }
                // Alte Chargen, die jetzt in keiner gezählten Position mehr auftauchen
                // (zusammengelegt/umbenannt), auf 0 setzen statt als Karteileiche stehen
                // zu lassen — nur hier sinnvoll, weil bei diesen Scopes der KOMPLETTE
                // Bestand des Artikels im Lager Gegenstand der Zählung ist.
                foreach ($g['alte_verteilung'] as $ck => $menge) {
                    if (!isset($mengeJeCharge[$ck])) {
                        $lagerRepo->upsertBestand([
                            'artikel_id'     => $g['artikel_id'],
                            'lager_id'       => $g['lager_id'],
                            'charge'         => $ck === '' ? null : $ck,
                            'charge_status'  => $ck !== '' ? 'erfasst' : null,
                            'bestand'        => 0,
                            'mindestbestand' => 0,
                        ]);
                    }
                }
            }

            if (abs($diffGesamt) > 0.01) {
                $notiz = null;
                foreach ($g['positionen'] as $p) { if (!empty($p['notiz'])) { $notiz = $p['notiz']; break; } }

                // Für die Bewegungs-Historie den ECHTEN Gesamtbestand zeigen (nicht die
                // lokale Platz-Menge) — bei Lagerplatz-Scope wäre "vorher/nachher" sonst
                // irreführend, weil an anderen Plätzen unangetastet weiterhin Ware liegt.
                if ($g['ist_lagerplatz_scope']) {
                    $echtVorher  = array_sum(array_map(fn($c) => (float)$c['bestand'], $this->repo->findAktuelleChargen($g['artikel_id'], $g['lager_id']))) - $diffGesamt;
                    $echtNachher = $echtVorher + $diffGesamt;
                } else {
                    $echtVorher  = $g['summe_vorher'];
                    $echtNachher = $g['summe_nachher'];
                }

                $lagerRepo->insertBewegung([
                    'artikel_id'      => $g['artikel_id'],
                    'lager_id'        => $g['lager_id'],
                    'lieferant_id'    => null,
                    'ek_preis'        => null,
                    'charge'          => null,
                    'bewegungstyp'    => $diffGesamt > 0 ? 'inventur' : 'schwund',
                    'menge'           => abs($diffGesamt),
                    'bestand_vorher'  => $echtVorher,
                    'bestand_nachher' => $echtNachher,
                    'referenz'        => 'Inventur #' . $laufId,
                    'notiz'           => $notiz,
                    'benutzer_id'     => $g['positionen'][0]['gezaehlt_von'],
                ]);
            }

            // Lagerplatz-Reallokation für Nicht-Lagerplatz-Scopes (z.B. Scope=Lager mit
            // "Ich zähle gerade an"-Tag) — bei Lagerplatz-Scope bereits oben erledigt.
            if (!$g['ist_lagerplatz_scope']) {
                foreach ($g['positionen'] as $p) {
                    if ($p['lagerplatz_id']) {
                        $lagerbestandId = $lagerRepo->findLagerbestandIdByKey($g['artikel_id'], $g['lager_id'], $p['charge']);
                        if ($lagerbestandId) {
                            $lagerRepo->upsertLagerbestandLagerplatz($lagerbestandId, (int)$p['lagerplatz_id'], (float)$p['ist_menge']);
                        }
                    }
                }
            }

            $artikelRepo->setLetzteInventur($g['artikel_id'], $heute);
            $korrigiert[] = ['artikel_name' => $g['artikel_name'], 'vorher' => $g['summe_vorher'], 'nachher' => $g['summe_nachher']];
        }

        $this->repo->setStatus($laufId, 'abgeschlossen', true);
        Logger::log('inventur.abgeschlossen', 'inventur_laeufe', $laufId, [
            'korrigierte_artikel' => count($korrigiert),
            'unveraendert'        => count($unveraendert),
        ]);

        return ['erfolg' => true, 'korrigiert' => $korrigiert, 'unveraendert' => $unveraendert];
    }
}
