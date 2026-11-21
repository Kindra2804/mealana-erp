<?php
require_once __DIR__ . '/Database.php';



/**
 * Logger – Aktivitätsprotokoll für alle ERP-Schreiboperationen
 *
 * Schreibt jeden relevanten Vorgang in die `aktivitaeten`-Tabelle.
 * Wird in allen Service-Klassen am Ende jeder Schreib-Operation aufgerufen.
 *
 * Format von $aktion: "modul.aktion" (z.B. "artikel.bearbeiten",
 * "wareneingang.buchen", "aktion.kategorie.hinzufuegen").
 *
 * Die Admin-Aktivitätsseite (public/admin/aktivitaeten.php) zeigt
 * alle Einträge mit Filter und Suche.
 */
class Logger
{
    /**
     * Schreibt einen Aktivitätseintrag in die aktivitaeten-Tabelle.
     *
     * @param string   $aktion            Format "modul.aktion" (z.B. "artikel.bearbeiten")
     * @param string|null $referenzTabelle  Betroffene DB-Tabelle (z.B. "artikel")
     * @param int|null    $referenzId       ID des betroffenen Datensatzes
     * @param array       $details          Optionale Zusatzinfos (werden als JSON gespeichert)
     * @param int|null    $benutzerId       Überschreibt $_SESSION wenn angegeben
     *                                      (z.B. für Jarvis/System-Aktionen ohne echte Session)
     * @param string      $stufe            'info' (Standard) | 'warn' | 'error' -- für die
     *                                      Logger-UI (Shell-Zeile + Admin-Aktivitätenseite).
     *                                      Nur an Fehlerpfaden bewusst 'warn'/'error' setzen.
     */
    public static function log(
        string $aktion,
        ?string $referenzTabelle = null,
        ?int    $referenzId      = null,
        array $details = [],
        ?int $benutzerId = null,
        string $stufe = 'info'
    ): void {

        // Jarvis (system-Benutzer) hat keine Session — benutzerId wird direkt übergeben
        if ($benutzerId === null) {
            $benutzerId = $_SESSION['benutzer']['id'];
        };

        if (!in_array($stufe, ['info', 'warn', 'error'], true)) {
            $stufe = 'info';
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO aktivitaeten (benutzer_id, aktion, referenz_tabelle, referenz_id, details, stufe)
            VALUES (:benutzer_id, :aktion, :referenz_tabelle, :referenz_id, :details, :stufe)
        ");
        $stmt->execute([
            'benutzer_id'      => $benutzerId,
            'aktion'           => $aktion,
            'referenz_tabelle' => $referenzTabelle,
            'referenz_id'      => $referenzId,
            // details als JSON; JSON_INVALID_UTF8_SUBSTITUTE verhindert false-Rückgabe bei
            // kaputten UTF-8-Sequenzen (z.B. Windows-1252-Umlaute aus Excel-CSV-Uploads)
            'details' => empty($details) ? null : (json_encode($details, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: null),
            'stufe' => $stufe,
        ]);
    }
}
