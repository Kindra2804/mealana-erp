<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/InventurRepository.php';
require_once __DIR__ . '/../lager/LagerRepository.php';

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
    // Abschluss (Slice 4) — echte Bestandskorrektur, siehe project_inventur_konzept
    // -------------------------------------------------------------------------

    /**
     * Berechnet die Abgleich-Gruppen (Artikel × Lager) für einen Lauf, ohne irgendetwas
     * zu buchen. Gemeinsame Grundlage für Vorschau UND tatsächlichen Abschluss —
     * beide müssen exakt dieselbe Aufteilung "vollständig"/"unvollständig" sehen.
     *
     * Vollständigkeitsregel (Jacky, 2026-07-18): eine Artikel/Lager-Gruppe wird nur dann
     * gebucht, wenn JEDE zum Abschlusszeitpunkt sichtbare Soll-Position entweder gezählt
     * oder auf der Check-Liste explizit auf 0 gesetzt wurde. Fehlt auch nur eine offene
     * Position, bleibt die GANZE Gruppe unangetastet (kein Teilbuchen).
     *
     * Rückgabe: ['gruppen' => [artikel_id, lager_id, artikel_name, vollstaendig,
     *            summe_vorher, summe_nachher, positionen[]], ...]
     */
    private function berechneAbgleich(array $lauf): array
    {
        $sollListe  = $this->getSollListe($lauf);
        $alle       = $this->repo->findPositionenFuerLauf((int)$lauf['id']);
        $gezaehlt   = array_filter($alle, fn($p) => $p['status'] === 'gezaehlt');

        // Vergleichsschlüssel bewusst OHNE lagerplatz_id: die Soll-Liste (aus lagerbestand)
        // kennt bei Scope=Lager/Kategorie/Artikel gar keinen Lagerplatz — der wird erst
        // beim Zählen als Zusatzinfo ("Ich zähle gerade an") angehängt. Würde man ihn hier
        // mitvergleichen, würde eine mit Lagerplatz-Tag gezählte Position nie zur
        // ursprünglichen (lagerplatzlosen) Soll-Zeile passen und die Gruppe fälschlich als
        // unvollständig gelten. Bei Scope=Lagerplatz ist der Lagerplatz ohnehin für alle
        // Zeilen gleich (der Scope selbst), Weglassen ändert dort nichts am Ergebnis.
        $gezaehltIndex = [];
        foreach ($gezaehlt as $p) {
            $key = $p['artikel_id'] . '|' . $p['lager_id'] . '|' . ($p['charge'] ?? '');
            $gezaehltIndex[$key] = true;
        }

        // Welche Artikel/Lager-Gruppen haben noch offene (nicht gezählte) Soll-Positionen?
        // WICHTIG: eine Gruppe mit GAR KEINER gezählten Position (Soll-Zeile nie angefasst)
        // muss genauso als unvollständig gelten wie eine mit nur teilweise gezählten Chargen —
        // beides ergibt sich hier automatisch, weil jede offene Soll-Zeile die Gruppe markiert.
        $unvollstaendigeGruppen = [];
        foreach ($sollListe as $s) {
            $key = $s['artikel_id'] . '|' . $s['lager_id'] . '|' . ($s['charge'] ?? '');
            if (!isset($gezaehltIndex[$key])) {
                $unvollstaendigeGruppen[$s['artikel_id'] . '|' . $s['lager_id']] = true;
            }
        }

        // Alle Artikel/Lager-Gruppen sammeln: aus der Soll-Liste (auch komplett unberührte
        // Zeilen) UND aus den gezählten Positionen (neue Funde ohne vorherige Soll-Zeile).
        $alleGruppenKeys = [];
        foreach ($sollListe as $s) {
            $alleGruppenKeys[$s['artikel_id'] . '|' . $s['lager_id']] = true;
        }
        $gezaehltNachGruppe = [];
        foreach ($gezaehlt as $p) {
            $gruppenKey = $p['artikel_id'] . '|' . $p['lager_id'];
            $alleGruppenKeys[$gruppenKey] = true;
            $gezaehltNachGruppe[$gruppenKey][] = $p;
        }

        $gruppen = [];
        foreach (array_keys($alleGruppenKeys) as $key) {
            [$artikelId, $lagerId] = array_map('intval', explode('|', $key));
            $positionen = $gezaehltNachGruppe[$key] ?? [];

            $beispiel = $positionen[0] ?? null;
            if (!$beispiel) {
                foreach ($sollListe as $s) {
                    if ((int)$s['artikel_id'] === $artikelId && (int)$s['lager_id'] === $lagerId) { $beispiel = $s; break; }
                }
            }

            // summeVorher kommt aus der Soll-Liste selbst (ein Wert je Charge, garantiert
            // dupliktatsfrei durch die UNIQUE-Regel auf lagerbestand) — NICHT aus den
            // gezählten Positionen, sonst würde eine über mehrere Lagerplätze aufgeteilte
            // Charge (mehrere Positionen, gleiche ursprüngliche Soll-Menge) doppelt gezählt.
            $summeVorher = 0.0;
            foreach ($sollListe as $s) {
                if ((int)$s['artikel_id'] === $artikelId && (int)$s['lager_id'] === $lagerId) {
                    $summeVorher += (float)($s['soll_menge'] ?? 0);
                }
            }
            $summeNachher = array_sum(array_map(fn($p) => (float)$p['ist_menge'], $positionen));

            $gruppen[] = [
                'artikel_id'      => $artikelId,
                'lager_id'        => $lagerId,
                'artikel_name'    => $beispiel['artikel_name'] ?? '',
                'artikelnummer'   => $beispiel['artikelnummer'] ?? '',
                'lager_name'      => $beispiel['lager_name'] ?? '',
                'vollstaendig'    => !isset($unvollstaendigeGruppen[$key]),
                'summe_vorher'    => $summeVorher,
                'summe_nachher'   => $summeNachher,
                'positionen'      => $positionen,
            ];
        }

        return $gruppen;
    }

    /**
     * Vorschau vor dem eigentlichen Abschluss: zeigt für jede vollständige Gruppe alle
     * Positionen wo Ist ≠ Soll (egal ob mehr oder weniger), plus die Liste unvollständiger
     * Gruppen, die beim Abschluss unangetastet bleiben würden. Bucht nichts.
     */
    public function vorschauAbschluss(int $laufId): array
    {
        $lauf = $this->getById($laufId);
        if (!$lauf) {
            return ['erfolg' => false, 'fehler' => ['Lauf nicht gefunden']];
        }

        $gruppen = $this->berechneAbgleich($lauf);

        $abweichungen    = [];
        $unvollstaendig  = [];
        foreach ($gruppen as $g) {
            if (!$g['vollstaendig']) {
                $unvollstaendig[] = $g;
                continue;
            }
            foreach ($g['positionen'] as $p) {
                $soll = (float)($p['soll_menge'] ?? 0);
                $ist  = (float)$p['ist_menge'];
                if (abs($ist - $soll) > 0.01) {
                    $abweichungen[] = [
                        'artikel_name'  => $g['artikel_name'],
                        'artikelnummer' => $g['artikelnummer'],
                        'lager_name'    => $g['lager_name'],
                        'lagerplatz_bezeichnung' => $p['lagerplatz_bezeichnung'] ?? null,
                        'charge'        => $p['charge'],
                        'soll'          => $soll,
                        'ist'           => $ist,
                        'notiz'         => $p['notiz'],
                    ];
                }
            }
        }

        return [
            'erfolg'         => true,
            'lauf'           => $lauf,
            'abweichungen'   => $abweichungen,
            'unvollstaendig' => $unvollstaendig,
        ];
    }

    /**
     * Führt den Abschluss tatsächlich durch: bucht jede vollständige Gruppe (Bestand neu
     * setzen, Differenz als lager_bewegungen Typ 'inventur'/'schwund', Lagerplatz-Reallokation),
     * lässt unvollständige Gruppen unangetastet. Schwund ohne Notiz wird komplett verweigert
     * (nicht nur die eine Gruppe übersprungen), damit man das vor dem Abschluss nachträgt.
     */
    public function abschliessen(int $laufId): array
    {
        $lauf = $this->getById($laufId);
        if (!$lauf || !in_array($lauf['status'], ['laufend', 'pausiert'], true)) {
            return ['erfolg' => false, 'fehler' => ['Inventur kann nicht abgeschlossen werden']];
        }

        $gruppen = $this->berechneAbgleich($lauf);
        $lagerRepo = new LagerRepository();

        // Erst validieren (Schwund braucht Notiz), bevor überhaupt etwas gebucht wird.
        foreach ($gruppen as $g) {
            if (!$g['vollstaendig'] || $g['summe_nachher'] >= $g['summe_vorher'] - 0.01) {
                continue;
            }
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

        $korrigiert = [];
        foreach ($gruppen as $g) {
            if (!$g['vollstaendig']) continue;

            foreach ($g['positionen'] as $p) {
                $vorherCharge  = (float)($p['soll_menge'] ?? 0);
                $nachherCharge = (float)$p['ist_menge'];
                $diff          = $nachherCharge - $vorherCharge;

                $lagerRepo->upsertBestand([
                    'artikel_id'     => $g['artikel_id'],
                    'lager_id'       => $g['lager_id'],
                    'charge'         => $p['charge'],
                    'charge_status'  => $p['charge'] !== null ? 'erfasst' : null,
                    'bestand'        => $nachherCharge,
                    'mindestbestand' => 0,
                ]);

                if (abs($diff) > 0.001) {
                    $lagerRepo->insertBewegung([
                        'artikel_id'      => $g['artikel_id'],
                        'lager_id'        => $g['lager_id'],
                        'lieferant_id'    => null,
                        'ek_preis'        => null,
                        'charge'          => $p['charge'],
                        'bewegungstyp'    => $diff > 0 ? 'inventur' : 'schwund',
                        'menge'           => abs($diff),
                        'bestand_vorher'  => $vorherCharge,
                        'bestand_nachher' => $nachherCharge,
                        'referenz'        => 'Inventur #' . $laufId,
                        'notiz'           => $p['notiz'],
                        'benutzer_id'     => $p['gezaehlt_von'],
                    ]);
                }

                if ($p['lagerplatz_id']) {
                    $lagerbestandId = $lagerRepo->findLagerbestandIdByKey($g['artikel_id'], $g['lager_id'], $p['charge']);
                    if ($lagerbestandId) {
                        $lagerRepo->upsertLagerbestandLagerplatz($lagerbestandId, (int)$p['lagerplatz_id'], $nachherCharge);
                    }
                }
            }

            $korrigiert[] = ['artikel_name' => $g['artikel_name'], 'vorher' => $g['summe_vorher'], 'nachher' => $g['summe_nachher']];
        }

        $this->repo->setStatus($laufId, 'abgeschlossen', true);
        Logger::log('inventur.abgeschlossen', 'inventur_laeufe', $laufId, [
            'korrigierte_artikel' => count($korrigiert),
        ]);

        return ['erfolg' => true, 'korrigiert' => $korrigiert];
    }
}
