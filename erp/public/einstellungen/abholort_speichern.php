<?php
/**
 * Einstellungen → Abholorte: anlegen/ändern bzw. löschen (siehe AbholortService).
 * POST: aktion (speichern|loeschen), id, name, strasse, plz, ort, telefon, oeffnungszeiten,
 *       hinweis, ist_standard, lager_ids[]
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/AbholortService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=abholorte');
    exit;
}

$service  = new AbholortService();
$ergebnis = ($_POST['aktion'] ?? '') === 'loeschen'
    ? $service->loeschen((int)($_POST['id'] ?? 0))
    : $service->speichern($_POST);

if ($ergebnis['erfolg']) {
    $_SESSION['erfolg'] = ($_POST['aktion'] ?? '') === 'loeschen' ? 'Abholort gelöscht.' : 'Abholort gespeichert.';
} else {
    $_SESSION['fehler'] = $ergebnis['fehler'];
}
header('Location: index.php?tab=abholorte');
exit;
