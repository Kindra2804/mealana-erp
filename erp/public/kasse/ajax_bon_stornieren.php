<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

header('Content-Type: application/json');

$bonId      = (int)($_POST['bon_id'] ?? 0);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

if (!$bonId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Anfrage.']);
    exit;
}

$service = new KassenService();
$result  = $service->storniereBon($bonId, $benutzerId);

// Gutscheine auf dem Bon (verkauft oder damit bezahlt) gegenbuchen -- der Storno-Bon
// ist bereits signiert, ein Fehler hier wird nur gemeldet, nicht zurückgerollt.
if ($result['erfolg']) {
    try {
        require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinService.php';
        $gs = (new GutscheinService())->bonStorniert($bonId, (int)$result['storno_bon_id'], (string)$result['bon_nr'], $benutzerId);
        $result['gutschein_warnungen'] = $gs['warnungen'];
        $result['gutschein_neue_codes'] = $gs['neue_codes'];
    } catch (Throwable $e) {
        Logger::log('gutschein.storno_fehler', 'kassen_bons', $bonId, ['fehler' => $e->getMessage()], $benutzerId, 'error');
        $result['gutschein_warnungen'] = ['Bon storniert, aber Gutschein-Gegenbuchung fehlgeschlagen — bitte in der Gutschein-Verwaltung prüfen.'];
    }
}

echo json_encode($result);
