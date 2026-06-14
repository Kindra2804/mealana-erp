<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json');

$pdo = Database::getInstance();

$artikel_id  = (int) ($_POST['artikel_id'] ?? 0);
$al_id       = (int) ($_POST['al_id'] ?? 0);
$lieferant_id = (int) ($_POST['lieferant_id'] ?? 0);
$artikelnummer_lieferant = (string) ($_POST['artikelnummer_lieferant'] ?? 0);
$netto_ek = (float) ($_POST['netto_ek'] ?? 0);
$waehrung = (string) ($_POST['waehrung'] ?? 0);
$vpe_menge = (int) ($_POST['vpe_menge'] ?? 0);
$vpe_ean = preg_replace('/\D/', '', (string) ($_POST['vpe_ean'] ?? ''));
$vpe_ean = strlen($vpe_ean) > 0 ? $vpe_ean : null;
$lieferzeit_tage = (int) ($_POST['lieferzeit_tage'] ?? 0);
$mindestabnahme = (float) ($_POST['mindestabnahme'] ?? 0);
$standard_lieferant = (int) ($_POST['standard_lieferant'] ?? 0);
// ... weitere Felder einlesen

if (!$artikel_id || !$lieferant_id) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Pflichtfelder fehlen']);
    exit;
}

if ($al_id) {
    // UPDATE — bestehende Zeile
    $stmt = $pdo->prepare("
        UPDATE artikel_lieferanten SET
            artikel_id = :artikel_id,
            lieferant_id = :lieferant_id,
            artikelnummer_lieferant = :artikelnummer_lieferant,
            netto_ek = :netto_ek,
            waehrung = :waehrung,
            vpe_menge = :vpe_menge,
            vpe_ean = :vpe_ean,
            lieferzeit_tage = :lieferzeit_tage,
            mindestabnahme = :mindestabnahme,
            standard_lieferant = :standard_lieferant
        WHERE id = :al_id
    ");

    $stmt->execute([
        'artikel_id' => $artikel_id,
        'lieferant_id' => $lieferant_id,
        'artikelnummer_lieferant' => $artikelnummer_lieferant,
        'netto_ek' => $netto_ek,
        'waehrung' => $waehrung,
        'vpe_menge' => $vpe_menge,
        'vpe_ean' => $vpe_ean,
        'lieferzeit_tage' => $lieferzeit_tage,
        'mindestabnahme' => $mindestabnahme,
        'standard_lieferant' => $standard_lieferant,
        'al_id' => $al_id
    ]);
} else {
    // INSERT — neue Zeile
    $stmt = $pdo->prepare("
        INSERT INTO artikel_lieferanten (
        artikel_id,
        lieferant_id,
        artikelnummer_lieferant,
        netto_ek,
        waehrung,
        vpe_menge,
        vpe_ean,
        lieferzeit_tage,
        mindestabnahme,
        standard_lieferant
        ) VALUES (
        :artikel_id,
        :lieferant_id,
        :artikelnummer_lieferant,
        :netto_ek,
        :waehrung,
        :vpe_menge,
        :vpe_ean,
        :lieferzeit_tage,
        :mindestabnahme,
        :standard_lieferant)
    ");

    $stmt->execute([
        'artikel_id' => $artikel_id,
        'lieferant_id' => $lieferant_id,
        'artikelnummer_lieferant' => $artikelnummer_lieferant,
        'netto_ek' => $netto_ek,
        'waehrung' => $waehrung,
        'vpe_menge' => $vpe_menge,
        'vpe_ean' => $vpe_ean,
        'lieferzeit_tage' => $lieferzeit_tage,
        'mindestabnahme' => $mindestabnahme,
        'standard_lieferant' => $standard_lieferant,
    ]);
}

echo json_encode(['erfolg' => true]);
