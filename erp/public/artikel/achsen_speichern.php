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
                'id'         => (int)($felder['id'] ?? 0),
                'aufpreis'   => (float)($felder['aufpreis'] ?? 0),
            ];
        }
    }
}

$achsenIds  = array_map('intval', $_POST['achsen'] ?? []);
$preisModi  = $_POST['preis_modi']  ?? [];
$preisWerte = $_POST['preis_werte'] ?? [];

$service = new VariantenService();
$result  = $service->speichereAchsenUndWerte($artikelId, $achsenIds, $werte);

if ($result['erfolg']) {
    // Preis-Modus + Preis-Wert pro Achse speichern
    foreach ($achsenIds as $achseId) {
        $modus = in_array($preisModi[$achseId] ?? '', ['aufpreis','direktpreis'])
                 ? $preisModi[$achseId]
                 : 'direktpreis';
        $wert  = (float)($preisWerte[$achseId] ?? 0);
        $service->updateAchsePreis($artikelId, $achseId, $modus, $wert);
    }

    // Bedingte Anzeige pro Achse speichern (z.B. "Farbe 2" nur wenn "Farbschema" = "Zweifärbig")
    // wertIdsProAchse erst NACH dem Werte-Speichern laden, damit auch gerade neu angelegte Werte gültig sind
    $bedingungen      = $_POST['bedingung'] ?? [];
    $wertIdsProAchse  = [];
    foreach ($service->findWerteByArtikelId($artikelId) as $w) {
        $wertIdsProAchse[(int)$w['achse_id']][] = (int)$w['id'];
    }
    foreach ($achsenIds as $achseId) {
        $bedAchseId = (int)($bedingungen[$achseId]['achse'] ?? 0) ?: null;
        $bedWertId  = (int)($bedingungen[$achseId]['wert'] ?? 0) ?: null;
        $service->updateAchseBedingung($artikelId, $achseId, $achsenIds, $bedAchseId, $bedWertId, $wertIdsProAchse);
    }

    $_SESSION['erfolg'] = 'Achsen und Werte gespeichert';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: achsen_zuweisen.php?artikel_id=' . $artikelId);
exit;
