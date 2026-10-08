<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

header('Content-Type: application/json; charset=utf-8');

$aktion = $_POST['aktion'] ?? $_GET['aktion'] ?? '';
$svc    = new MesseSyncService();
$uid    = (int)($_SESSION['benutzer']['id'] ?? 0);

switch ($aktion) {

    // ── Umbuchung zur Messe ───────────────────────────────────────────────────
    // POST: aktion, kasse_id, von_lager_id, nach_lager_id, positionen (JSON)
    case 'umbuchung_zur_messe':
        $positionen  = json_decode($_POST['positionen'] ?? '[]', true);
        $vonLagerId  = (int)($_POST['von_lager_id']  ?? 0);
        $nachLagerId = (int)($_POST['nach_lager_id'] ?? 0);
        $kasseId     = (int)($_POST['kasse_id']      ?? 0) ?: null;
        $variante    = $_POST['variante'] ?? 'elektronisch';

        if (!$vonLagerId || !$nachLagerId || empty($positionen)) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter.']);
            exit;
        }
        echo json_encode($svc->umbuchungZurMesse($positionen, $vonLagerId, $nachLagerId, $kasseId, $uid, $variante));
        break;

    // ── Pre-Sync Export ───────────────────────────────────────────────────────
    // GET: aktion, sync_id
    case 'pre_sync_export':
        $syncId = (int)($_GET['sync_id'] ?? 0);

        if (!$syncId) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter.']);
            exit;
        }
        echo json_encode($svc->preSyncExportieren($syncId));
        break;

    // ── Post-Sync: Offline-Bons einlesen ────────────────────────────────────
    // POST: aktion, payload (JSON)
    case 'post_sync':
        $payload = json_decode($_POST['payload'] ?? '{}', true);
        if (empty($payload)) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Leerer Payload.']);
            exit;
        }
        echo json_encode($svc->postSyncVerarbeiten($payload, $uid));
        break;

    // ── Rückkehr: Restbestand zurückbuchen ───────────────────────────────────
    // POST: aktion, sync_id, von_lager_id, nach_lager_id, rueckgabe (JSON), schwund (JSON)
    case 'rueckkehr':
        $syncId      = (int)($_POST['sync_id']      ?? 0);
        $vonLagerId  = (int)($_POST['von_lager_id'] ?? 0);
        $nachLagerId = (int)($_POST['nach_lager_id']?? 0);
        $rueckgabe   = json_decode($_POST['rueckgabe'] ?? '[]', true);
        $schwund     = json_decode($_POST['schwund']   ?? '[]', true);
        // nur Papier-Messe: Stricherl (verkauft) + Freitext-Zeilen
        $strich      = json_decode($_POST['strich']    ?? '[]', true) ?: [];
        $freitext    = json_decode($_POST['freitext']  ?? '[]', true) ?: [];

        if (!$syncId || !$vonLagerId || !$nachLagerId) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter.']);
            exit;
        }
        echo json_encode($svc->rueckkehrVerarbeiten($syncId, $rueckgabe, $schwund, $vonLagerId, $nachLagerId, $uid, $strich, $freitext));
        break;

    // ── Papier-Messe: händischen Beleg nacherfassen ──────────────────────────
    // POST: aktion, sync_id, beleg_nr, beleg_datum (Y-m-d), zahlungsart, zeilen (JSON)
    // Kasse kommt aus dem Arbeitsplatz (inkl. Geräte-Sperre), nie vom Client.
    case 'beleg_nacherfassen':
        require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';
        require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
        $kasseId = (new ArbeitsplatzService())->aktuelleKasseId();
        if ($kasseId === null) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Kein Kassen-Arbeitsplatz — bitte über die Kassen-Startseite öffnen.']);
            exit;
        }
        $kasse = (new KassenService())->getKasse($kasseId);
        echo json_encode($svc->belegNacherfassen(
            (int)($_POST['sync_id'] ?? 0),
            $kasseId,
            (int)($kasse['lager_id'] ?? 1),
            (string)($_POST['beleg_nr'] ?? ''),
            (string)($_POST['beleg_datum'] ?? ''),
            (string)($_POST['zahlungsart'] ?? ''),
            json_decode($_POST['zeilen'] ?? '[]', true) ?: [],
            $uid
        ));
        break;

    // ── Offene Syncs für eine Kasse ───────────────────────────────────────────
    // GET: aktion, kasse_id
    case 'offene_syncs':
        $kasseId = (int)($_GET['kasse_id'] ?? 1);
        echo json_encode(['erfolg' => true, 'syncs' => $svc->getOffeneSyncs($kasseId)]);
        break;

    default:
        echo json_encode(['erfolg' => false, 'fehler' => 'Unbekannte Aktion: ' . htmlspecialchars($aktion)]);
}
