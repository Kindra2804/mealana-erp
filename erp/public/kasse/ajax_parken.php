<?php
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db      = Database::getInstance();
$aktion  = $_GET['aktion'] ?? $_POST['aktion'] ?? '';
// "speichern" kommt als JSON-Body (bon.php::bonParken) — kasse_id steht dann dort, nicht in GET/POST
$inp     = json_decode(file_get_contents('php://input') ?: 'null', true);
$kasseId = (int)($_GET['kasse_id'] ?? $_POST['kasse_id'] ?? (is_array($inp) ? ($inp['kasse_id'] ?? 0) : 0));
$userId  = (int)($_SESSION['benutzer']['id'] ?? 0);

if (!$kasseId) { echo json_encode(['erfolg' => false, 'fehler' => 'Keine Kasse-ID']); exit; }

switch ($aktion) {

    case 'speichern':
        if (!$inp) { echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Daten']); exit; }

        // Pro Kasse nur EIN geparkter Bon (Jacky 2026-10-02)
        $schon = $db->prepare("SELECT COUNT(*) FROM kassen_geparkte_bons WHERE kasse_id = ?");
        $schon->execute([$kasseId]);
        if ((int)$schon->fetchColumn() > 0) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Es ist schon ein Bon geparkt — bitte zuerst den geparkten Bon holen und abschließen.']); exit;
        }

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
        // Summe in PHP statt per JSON_TABLE/->> (gibt es in MariaDB nicht — die Liste brach ab).
        // Gleiche Rechnung wie bon.php::getGesamt (Zeilen- oder Bon-Rabatt, der höhere zählt).
        $stmt = $db->prepare("
            SELECT id, kunden_name, global_rabatt, auftrag_id, notiz, erstellt_am, warenkorb
            FROM kassen_geparkte_bons
            WHERE kasse_id = :kasse_id
            ORDER BY erstellt_am DESC
        ");
        $stmt->execute(['kasse_id' => $kasseId]);
        $liste = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $zeilen = json_decode($row['warenkorb'] ?? '[]', true) ?: [];
            $total  = 0.0;
            foreach ($zeilen as $z) {
                $rab    = ($z['block'] ?? null) === 'gutschein_kauf' ? 0 : max((float)($z['rabatt_prozent'] ?? 0), (float)$row['global_rabatt']);
                $total += (float)($z['menge'] ?? 0) * (float)($z['einzelpreis_brutto'] ?? 0) * (1 - $rab / 100);
            }
            unset($row['warenkorb']);
            $liste[] = $row + ['positionen_anz' => count($zeilen), 'total' => round($total, 2)];
        }
        echo json_encode(['erfolg' => true, 'liste' => $liste]);
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
