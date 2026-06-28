<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

function setSetting(PDO $db, string $key, string $value): void
{
    $db->prepare("INSERT INTO system_einstellungen (schluessel, wert) VALUES (:k, :v)
                  ON DUPLICATE KEY UPDATE wert = :v2")
       ->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
}

$felder = [
    'plc_polling_ordner',
    'plc_item_at',
    'plc_item_at_express',
    'plc_item_eu',
    'plc_item_international',
];

foreach ($felder as $schluessel) {
    setSetting($db, $schluessel, trim($_POST[$schluessel] ?? ''));
}

Logger::log('einstellungen.versand_gespeichert');

$_SESSION['erfolg'] = 'Versand-Einstellungen gespeichert.';
header('Location: index.php');
exit;
