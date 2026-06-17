<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/MerkmaleRepository.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_POST['action'] ?? '');
$repo   = new MerkmaleRepository();

try {
    switch ($action) {

        case 'merkmal_neu':
            $name    = trim($input['name'] ?? '');
            $slug    = trim($input['slug'] ?? '');
            $mehrfach = !empty($input['mehrfach_auswahl']);
            $filterbar = !empty($input['filterbar']);
            if (!$name) throw new Exception('Name darf nicht leer sein');
            if (!$slug) $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
            $id = $repo->insertMerkmal($name, $slug, $mehrfach, $filterbar);
            $repo->setArtikeltypen($id, $input['artikeltyp_ids'] ?? []);
            echo json_encode(['erfolg' => true, 'id' => $id]);
            break;

        case 'merkmal_bearbeiten':
            $id      = (int)($input['id'] ?? 0);
            $name    = trim($input['name'] ?? '');
            $slug    = trim($input['slug'] ?? '');
            $mehrfach = !empty($input['mehrfach_auswahl']);
            $filterbar = !empty($input['filterbar']);
            if (!$id || !$name) throw new Exception('Ungültige Daten');
            $repo->updateMerkmal($id, $name, $slug, $mehrfach, $filterbar);
            $repo->setArtikeltypen($id, $input['artikeltyp_ids'] ?? []);
            echo json_encode(['erfolg' => true]);
            break;

        case 'merkmal_loeschen':
            $id = (int)($input['id'] ?? 0);
            if (!$id) throw new Exception('Ungültige ID');
            $repo->deleteMerkmal($id);
            echo json_encode(['erfolg' => true]);
            break;

        case 'merkmal_sort':
            $id  = (int)($input['id'] ?? 0);
            $dir = $input['richtung'] ?? '';
            if (!$id || !in_array($dir, ['hoch', 'runter'])) throw new Exception('Ungültige Daten');
            $repo->sortMerkmal($id, $dir);
            echo json_encode(['erfolg' => true]);
            break;

        case 'wert_neu':
            $merkmalId = (int)($input['merkmal_id'] ?? 0);
            $wert      = trim($input['wert'] ?? '');
            if (!$merkmalId || !$wert) throw new Exception('Ungültige Daten');
            $id = $repo->insertWert($merkmalId, $wert);
            echo json_encode(['erfolg' => true, 'id' => $id, 'wert' => $wert]);
            break;

        case 'wert_bearbeiten':
            $id   = (int)($input['id'] ?? 0);
            $wert = trim($input['wert'] ?? '');
            if (!$id || !$wert) throw new Exception('Ungültige Daten');
            $repo->updateWert($id, $wert);
            echo json_encode(['erfolg' => true]);
            break;

        case 'wert_loeschen':
            $id = (int)($input['id'] ?? 0);
            if (!$id) throw new Exception('Ungültige ID');
            $repo->deleteWert($id);
            echo json_encode(['erfolg' => true]);
            break;

        case 'wert_sort':
            $id  = (int)($input['id'] ?? 0);
            $dir = $input['richtung'] ?? '';
            if (!$id || !in_array($dir, ['hoch', 'runter'])) throw new Exception('Ungültige Daten');
            $repo->sortWert($id, $dir);
            echo json_encode(['erfolg' => true]);
            break;

        default:
            echo json_encode(['fehler' => 'Unbekannte Aktion']);
    }
} catch (Exception $e) {
    echo json_encode(['fehler' => $e->getMessage()]);
}
