<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);

if ($artikelId <= 0) {
    header('Location: liste.php');
    exit;
}


$werte = [];
foreach ($_POST['werte'] ?? [] as $achseId => $reihen) {
    foreach ($reihen as $index => $felder) {
        if (!empty(trim($felder['wert'] ?? ''))) {
            $werte[] = [
                'achse_id'  => (int)$achseId,
                'wert'      => trim($felder['wert']),
                'sort_order' => $index
            ];
        }
    }
}

$achsenIds = array_map('intval', $_POST['achsen'] ?? []);

$service = new VariantenService();

$result = $service->speichereAchsenUndWerte($artikelId, $achsenIds, $werte);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Achsen und Werte eingefügt';
    header('Location: bearbeiten.php?id=' . $artikelId);
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    header('Location: achsen_zuweisen.php?artikel_id=' . $artikelId);
    exit;
}
