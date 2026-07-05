<?php

require_once __DIR__ . '/RollenRepository.php';

/**
 * RollenService – Rollen/Berechtigungen-Matrix
 *
 * Bearbeitungsregel: Ein Benutzer darf nur Berechtigungen von Rollen ändern,
 * deren Rang ECHT NIEDRIGER ist als der eigene Rang. Das ergibt automatisch:
 * - Niemand kann seine eigene Rolle bearbeiten (verhindert Selbst-Hochstufung)
 * - Ein "Assistent" (Rang 80) kann den Admin (Rang 90) nicht entmachten,
 *   aber Admin kann jederzeit dem Assistenten Rechte entziehen
 * - Superadmins Zeile ist für niemanden bearbeitbar (kein Rang ist > 100)
 *
 * "lizenz.verwalten" ist zusätzlich komplett gesperrt — unabhängig vom Rang
 * darf diese Berechtigung über die Matrix nie vergeben werden (nur Superadmin
 * hat sie, fix durch die Migration gesetzt; Lizenzverwaltung ist ohnehin ein
 * separates, nicht mitgeliefertes Tool, siehe project_rechte_rollen.md).
 */
class RollenService
{
    private const GESPERRTE_BERECHTIGUNG = 'lizenz.verwalten';

    private RollenRepository $repo;

    public function __construct()
    {
        $this->repo = new RollenRepository();
    }

    /**
     * Baut die komplette Matrix-Ansicht: Rollen (mit Info ob der aktuelle Benutzer
     * sie bearbeiten darf), Berechtigungen, und die Ist-Zuweisung als Lookup.
     */
    public function getMatrixAnsicht(int $eigenerRang): array
    {
        $rollen        = $this->repo->findAlleRollen();
        $berechtigungen = $this->repo->findAlleBerechtigungen();
        $zuweisungen    = $this->repo->findMatrix();

        $lookup = [];
        foreach ($zuweisungen as $z) {
            $lookup[$z['rolle_id']][$z['berechtigung_id']] = true;
        }

        foreach ($rollen as &$r) {
            $r['bearbeitbar'] = (int)$r['rang'] < $eigenerRang;
        }
        unset($r);

        return ['rollen' => $rollen, 'berechtigungen' => $berechtigungen, 'lookup' => $lookup];
    }

    /**
     * Setzt eine Berechtigung für eine Rolle, nach Prüfung der Rang-Regel + Lizenz-Sperre.
     */
    public function setzeBerechtigung(int $eigenerRang, int $rolleId, int $berechtigungId, bool $gewaehrt): array
    {
        $berechtigungName = $this->repo->findBerechtigungName($berechtigungId);
        if ($berechtigungName === self::GESPERRTE_BERECHTIGUNG) {
            return ['erfolg' => false, 'fehler' => ['Lizenzverwaltung kann über die Matrix nicht vergeben werden.']];
        }

        $zielRang = $this->repo->findRangById($rolleId);
        if ($zielRang === null) {
            return ['erfolg' => false, 'fehler' => ['Rolle nicht gefunden.']];
        }
        if ($zielRang >= $eigenerRang) {
            return ['erfolg' => false, 'fehler' => ['Du darfst nur Rollen mit niedrigerem Rang als deinem eigenen bearbeiten.']];
        }

        $this->repo->setzeBerechtigung($rolleId, $berechtigungId, $gewaehrt);
        return ['erfolg' => true];
    }
}
