<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
$hatEigenenLagerstand  = (int)($_POST['hat_eigenen_lagerstand'] ?? 0);

if ($artikelId <= 0) {
    header('Location: liste.php');
    exit;
}

$kombis = array_filter($_POST['kombis'] ?? [], fn($k) => isset($k['selected']));

$artikelService = new ArtikelService();
$vater = $artikelService->findById($artikelId);

if ($vater && !empty($vater['ist_konfigurierbar'])) {
    header('Location: detail.php?id=' . $artikelId . '&tab=varianten&var_fehler=1');
    exit;
}

// Eigener Bestand des Artikels? Dann muss vorher feststehen, welche Variante ihn übernimmt —
// sonst bliebe er unsichtbar beim Vater liegen (Fund 2026-11-16, ID-U-A71).
$lagerService = new LagerService();
$bestandZiel  = trim((string)($_POST['bestand_ziel'] ?? ''));
$hatBestand   = !empty($lagerService->eigenerBestandZeilen($artikelId));
$zielNeuIndex = null;
$zielKindId   = null;
if ($hatBestand && !empty($kombis)) {
    if (preg_match('/^neu:(\d+)$/', $bestandZiel, $m) && isset($kombis[$m[1]])) {
        $zielNeuIndex = $m[1];
    } elseif (preg_match('/^kind:(\d+)$/', $bestandZiel, $m)) {
        $zielKindId = (int)$m[1];
    } else {
        $_SESSION['fehler'] = 'Der Artikel hat noch eigenen Lagerbestand — bitte wählen, welche Variante ihn übernimmt (die gewählte Variante muss auch angehakt sein).';
        header('Location: detail.php?id=' . $artikelId . '&tab=varianten');
        exit;
    }
}

$variantenService = new VariantenService();

$result = $variantenService->erstelleKombinationen($vater, $hatEigenenLagerstand, $kombis);

if ($result['erfolg']) {
    $artikelService->kopiereVaterRelationenZuKindern($artikelId, $result['ids'], $result['preisAnpassungen'] ?? []);
    foreach ($result['eanMap'] ?? [] as $kindId => $ean) {
        $artikelService->speichereCode((int)$kindId, 'GTIN13', $ean);
    }

    if ($zielNeuIndex !== null) {
        // ids kommen in derselben Reihenfolge wie $kombis
        $pos = array_search($zielNeuIndex, array_keys($kombis), false);
        $zielKindId = $pos !== false ? (int)($result['ids'][$pos] ?? 0) : null;
    }
    if ($zielKindId) {
        $ue = $lagerService->bestandAufVarianteUebertragen($artikelId, $zielKindId, $_SESSION['benutzer']['id'] ?? null);
        if ($ue['erfolg'] && $ue['menge'] != 0) {
            $_SESSION['erfolg'] = 'Varianten erstellt · Bestand (' . rtrim(rtrim(number_format($ue['menge'], 3, ',', ''), '0'), ',') . ' Stk.) an ' . $ue['kind_nr'] . ' übergeben';
        } elseif (!$ue['erfolg']) {
            $_SESSION['fehler'] = 'Varianten erstellt, aber Bestand nicht übergeben: ' . $ue['fehler'];
        }
    }
    header('Location: detail.php?id=' . $artikelId . '&tab=varianten');
    exit;
} else {
    header('Location: detail.php?id=' . $artikelId . '&tab=varianten&var_fehler=1');
    exit;
}
