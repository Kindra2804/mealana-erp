<?php
/**
 * Übergibt liegengebliebenen Vater-Bestand an eine Variante (Hinweis-Kasten im Tab Varianten).
 * POST: artikel_id (Vater), kind_id
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$vaterId = (int)($_POST['artikel_id'] ?? 0);
$kindId  = (int)($_POST['kind_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $vaterId <= 0) {
    header('Location: liste.php');
    exit;
}

if ($kindId <= 0) {
    $_SESSION['fehler'] = 'Bitte eine Variante wählen';
} else {
    $ue = (new LagerService())->bestandAufVarianteUebertragen($vaterId, $kindId, $_SESSION['benutzer']['id'] ?? null);
    if ($ue['erfolg']) {
        $_SESSION['erfolg'] = 'Bestand (' . rtrim(rtrim(number_format($ue['menge'], 3, ',', ''), '0'), ',') . ' Stk.) an ' . ($ue['kind_nr'] ?? 'Variante') . ' übergeben';
    } else {
        $_SESSION['fehler'] = $ue['fehler'];
    }
}
header('Location: detail.php?id=' . $vaterId . '&tab=varianten');
exit;
