<?php
require_once __DIR__ . '/../../src/core/Auth.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');

$aktion = $_POST['aktion'] ?? $_GET['aktion'] ?? '';
$svc    = new MesseSyncService();
$uid    = (int)Auth::getUserId();

switch ($aktion) {

    // ── Umbuchung zur Messe ───────────────────────────────────────────────────
    // POST: aktion, kasse_id, von_lager_id, nach_lager_id, positionen (JSON)
    case 'umbuchung_zur_messe':
        $positionen  = json_decode($_POST['positionen'] ?? '[]', true);
        $vonLagerId  = (int)($_POST['von_lager_id']  ?? 0);
        $nachLagerId = (int)($_POST['nach_lager_id'] ?? 0);
        $kasseId     = (int)($_POST['kasse_id']      ?? 1);

        if (!$vonLagerId || !$nachLagerId || empty($positionen)) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter.']);
            exit;
        }
        echo json_encode($svc->umbuchungZurMesse($positionen, $vonLagerId, $nachLagerId, $kasseId, $uid));
        break;

    // ── Pre-Sync Export ───────────────────────────────────────────────────────
    // GET: aktion, sync_id, lager_id
    case 'pre_sync_export':
        $syncId  = (int)($_GET['sync_id']  ?? 0);
        $lagerId = (int)($_GET['lager_id'] ?? 0);

        if (!$syncId || !$lagerId) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter.']);
            exit;
        }
        echo json_encode($svc->preSyncExportieren($syncId, $lagerId));
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

        if (!$syncId || !$vonLagerId || !$nachLagerId) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter.']);
            exit;
        }
        echo json_encode($svc->rueckkehrVerarbeiten($syncId, $rueckgabe, $schwund, $vonLagerId, $nachLagerId, $uid));
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
