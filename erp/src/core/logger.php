<?php
require_once __DIR__ . '/Database.php';

class Logger
{
    public static function log(
        string $aktion,
        ?string $referenzTabelle = null,
        ?int    $referenzId      = null,
        array $details = []
    ): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO aktivitaeten (benutzer_id, aktion, referenz_tabelle, referenz_id, details)
            VALUES (:benutzer_id, :aktion, :referenz_tabelle, :referenz_id, :details)
        ");
        $stmt->execute([
            'benutzer_id'      => $_SESSION['benutzer']['id'],
            'aktion'           => $aktion,
            'referenz_tabelle' => $referenzTabelle,
            'referenz_id'      => $referenzId,
            'details' => empty($details) ? null : json_encode($details)
        ]);
    }
}
