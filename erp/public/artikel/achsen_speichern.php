<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
if ($artikelId <= 0) {
    header('Location: liste.php');
    exit;
}

// Welche Achsen-IDs sind "Eltern" (andere Achsen hängen von ihnen ab)
$achsService         = new AchsenService();
$alleGlobaleAchsen   = $achsService->findAll();
$elternAchsenIds     = [];
foreach ($alleGlobaleAchsen as $a) {
    if ($a['abhaengig_von_achse_id']) {
        $elternAchsenIds[(int)$a['abhaengig_von_achse_id']] = true;
    }
}

$werte = [];
foreach ($_POST['werte'] ?? [] as $achseId => $reihen) {
    $achseId = (int)$achseId;
    foreach ($reihen as $index => $felder) {
        if (!empty(trim($felder['wert'] ?? ''))) {
            $werte[] = [
                'achse_id'             => $achseId,
                'wert'                 => trim($felder['wert']),
                'bedingungs_wert_name' => trim($felder['bedingungs_wert_name'] ?? '') ?: null,
                'bedingungs_achse_id'  => (int)($felder['bedingungs_achse_id'] ?? 0) ?: null,
                'ist_eltern_achse'     => isset($elternAchsenIds[$achseId]),
                'sort_order'           => (int)$index,
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
