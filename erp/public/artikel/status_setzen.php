<?php
/**
 * Knöpfe in der Artikel-Detailseite: Deaktivieren / Aktivieren / Löschen.
 * Löschen = endgültig, aber nur wenn mit dem Artikel noch nie etwas passiert ist
 * (siehe ArtikelService::loescheEndgueltig) -- sonst bleibt nur Deaktivieren.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$aktion = $_POST['aktion'] ?? '';
$service = new ArtikelService();
$artikel = $service->findById($id);

if ($artikel === false) {
    $_SESSION['fehler'] = 'Artikel nicht gefunden.';
    header('Location: liste.php');
    exit;
}

$detailUrl = 'detail.php?id=' . $id;
$vaterId   = $artikel['vaterartikel_id'] ? (int) $artikel['vaterartikel_id'] : null;

switch ($aktion) {
    case 'deaktivieren':
        $service->delete($id);
        $_SESSION['erfolg'] = $vaterId
            ? 'Variante wurde deaktiviert.'
            : 'Artikel wurde deaktiviert (inkl. aktiver Varianten).';
        header('Location: ' . $detailUrl);
        exit;

    case 'aktivieren':
        $service->aktivieren($id);
        $_SESSION['erfolg'] = 'Artikel wurde aktiviert.';
        header('Location: ' . $detailUrl);
        exit;

    case 'loeschen':
        if (!Auth::kann('artikel.loeschen')) {
            $_SESSION['fehler'] = 'Dir fehlt das Recht „Artikel löschen“.';
            header('Location: ' . $detailUrl);
            exit;
        }
        $result = $service->loescheEndgueltig($id);
        if (!$result['erfolg']) {
            $_SESSION['fehler'] = $result['fehler'];
            header('Location: ' . $detailUrl);
            exit;
        }
        $_SESSION['erfolg'] = 'Artikel ' . $artikel['artikelnummer'] . ' wurde endgültig gelöscht.';
        header('Location: ' . ($vaterId ? "detail.php?id={$vaterId}&tab=varianten" : 'liste.php'));
        exit;
}

$_SESSION['fehler'] = 'Unbekannte Aktion.';
header('Location: ' . $detailUrl);
exit;
