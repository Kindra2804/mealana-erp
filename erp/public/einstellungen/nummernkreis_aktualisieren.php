<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/Logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=nummernkreise');
    exit;
}

$id      = (int) ($_POST['id'] ?? 0);
$praefix = trim($_POST['praefix'] ?? '');
$letztNr = (int) ($_POST['letzt_nr'] ?? -1);

if (!$id || $praefix === '' || $letztNr < 0) {
    $_SESSION['fehler'] = 'Ungültige Eingabe.';
    header('Location: index.php?tab=nummernkreise');
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("UPDATE dokument_nummern SET praefix = :praefix, letzt_nr = :letzt_nr WHERE id = :id");
$stmt->execute(['praefix' => $praefix, 'letzt_nr' => $letztNr, 'id' => $id]);

Logger::log('einstellungen.nummernkreis_geaendert', 'dokument_nummern', $id, [
    'praefix'  => $praefix,
    'letzt_nr' => $letztNr,
]);

$_SESSION['erfolg'] = 'Nummernkreis aktualisiert.';
header('Location: index.php?tab=nummernkreise');
exit;
