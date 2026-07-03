<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db      = Database::getInstance();
$aktion  = $_GET['aktion'] ?? $_POST['aktion'] ?? '';
$kasseId = (int)($_GET['kasse_id'] ?? $_POST['kasse_id'] ?? 0);
$userId  = (int)($_SESSION['benutzer']['id'] ?? 0);

if (!$kasseId) { echo json_encode(['erfolg' => false, 'fehler' => 'Keine Kasse-ID']); exit; }

switch ($aktion) {

    case 'speichern':
        $raw = file_get_contents('php://input');
        $inp = json_decode($raw, true);
        if (!$inp) { echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Daten']); exit; }

        $warenkorb   = json_encode($inp['warenkorb']   ?? []);
        $globalRab   = (float)($inp['global_rabatt']   ?? 0);
        $kundenId    = $inp['kunden_id']   ? (int)$inp['kunden_id']   : null;
        $kundenName  = $inp['kunden_name'] ? trim($inp['kunden_name']) : null;
        $auftragId   = $inp['auftrag_id']  ? (int)$inp['auftrag_id']  : null;
        $notiz       = isset($inp['notiz']) ? trim($inp['notiz']) : null;
        $kontext     = isset($inp['kontext']) ? json_encode($inp['kontext']) : null;

        $stmt = $db->prepare("
            INSERT INTO kassen_geparkte_bons
                (kasse_id, kassierer_id, kunden_id, kunden_name, warenkorb, global_rabatt, auftrag_id, notiz, kontext)
            VALUES
                (:kasse_id, :kassierer_id, :kunden_id, :kunden_name, :warenkorb, :global_rabatt, :auftrag_id, :notiz, :kontext)
        ");
        $stmt->execute([
            'kasse_id'     => $kasseId,
            'kassierer_id' => $userId ?: null,
            'kunden_id'    => $kundenId,
            'kunden_name'  => $kundenName,
            'warenkorb'    => $warenkorb,
            'global_rabatt'=> $globalRab,
            'auftrag_id'   => $auftragId,
            'notiz'        => $notiz,
            'kontext'      => $kontext,
        ]);
        echo json_encode(['erfolg' => true, 'id' => (int)$db->lastInsertId()]);
        break;

    case 'liste':
        $stmt = $db->prepare("
            SELECT id, kunden_name, global_rabatt, auftrag_id, notiz, erstellt_am,
                   JSON_LENGTH(warenkorb) AS positionen_anz,
                   (SELECT SUM(CAST(p.value->>'$.summe' AS DECIMAL(10,2)))
                    FROM JSON_TABLE(warenkorb, '$[*]' COLUMNS (value JSON PATH '$')) j
                   ) AS total
            FROM kassen_geparkte_bons
            WHERE kasse_id = :kasse_id
            ORDER BY erstellt_am DESC
        ");
        $stmt->execute(['kasse_id' => $kasseId]);
        echo json_encode(['erfolg' => true, 'liste' => $stmt->fetchAll()]);
        break;

    case 'laden':
        $id   = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM kassen_geparkte_bons WHERE id = :id AND kasse_id = :kasse_id");
        $stmt->execute(['id' => $id, 'kasse_id' => $kasseId]);
        $row = $stmt->fetch();
        if (!$row) { echo json_encode(['erfolg' => false, 'fehler' => 'Bon nicht gefunden']); exit; }
        $row['warenkorb'] = json_decode($row['warenkorb'], true);
        echo json_encode(['erfolg' => true, 'bon' => $row]);
        break;

    case 'loeschen':
        $id   = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM kassen_geparkte_bons WHERE id = :id AND kasse_id = :kasse_id");
        $stmt->execute(['id' => $id, 'kasse_id' => $kasseId]);
        echo json_encode(['erfolg' => true]);
        break;

    default:
        echo json_encode(['erfolg' => false, 'fehler' => 'Unbekannte Aktion']);
}
