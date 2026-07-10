<?php
/**
 * Artikel-Typeahead für die Chargen-Nachverfolgung — Suche über Name, Artikelnummer
 * und alle hinterlegten Codes (EAN etc.), nur Nicht-Vater-Artikel (Vater ist keine
 * eigene Bestandseinheit). Eigener Endpunkt statt Wiederverwendung von z.B.
 * auftraege/artikel_ajax.php, damit die Seite rein unter der Lager-Berechtigung läuft.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$suche = trim($_GET['q'] ?? '');
if (strlen($suche) < 2) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance();

$words = preg_split('/\s+/', $suche, -1, PREG_SPLIT_NO_EMPTY);
$whereParts = [];
$params = [];
foreach ($words as $i => $word) {
    $k = 'w' . $i;
    $whereParts[] = "(a.name LIKE :$k OR vater.name LIKE :$k OR a.artikelnummer LIKE :$k
                     OR vater.artikelnummer LIKE :$k
                     OR EXISTS (SELECT 1 FROM artikel_codes ac WHERE ac.artikel_id = a.id AND ac.code LIKE :$k))";
    $params[$k] = '%' . $word . '%';
}
$where = implode(' AND ', $whereParts);

$stmt = $db->prepare("
    SELECT
        a.id,
        COALESCE(vater.name, a.name) AS name,
        CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.name END AS variante_name,
        COALESCE(vater.artikelnummer, a.artikelnummer) AS artikelnummer
    FROM artikel a
    LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
    WHERE (a.ist_vater = 0 OR a.ist_vater IS NULL)
      AND ($where)
    ORDER BY COALESCE(vater.name, a.name), a.name
    LIMIT 30
");
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
