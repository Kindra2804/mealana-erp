<?php

require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/InventurRepository.php';

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
}
