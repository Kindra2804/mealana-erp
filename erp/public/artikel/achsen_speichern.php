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
    $achseId = (int)$achseId;
    foreach ($reihen as $idx => $felder) {
        $text = trim($felder['wert'] ?? '');
        if ($text !== '') {
            $werte[] = [
                'achse_id'   => $achseId,
                'wert'       => $text,
                'sort_order' => (int)$idx,
            ];
        }
    }
}

$achsenIds = array_map('intval', $_POST['achsen'] ?? []);
$service   = new VariantenService();
$result    = $service->speichereAchsenUndWerte($artikelId, $achsenIds, $werte);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Achsen und Werte gespeichert';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: achsen_zuweisen.php?artikel_id=' . $artikelId);
exit;
