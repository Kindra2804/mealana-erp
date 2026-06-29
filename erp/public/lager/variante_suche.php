<?php
require_once __DIR__ . '/../includes/auth_check.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../../src/core/Database.php';

$q     = trim($_GET['q'] ?? '');
$words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

if (count($words) === 0 || strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance();

// Einen LIKE-Ausdruck pro Wort — alle müssen irgendwo matchen
$kindConds      = [];
$standaloneConds = [];
$params         = [':exact' => $q];

foreach ($words as $i => $w) {
    $p = ':w' . $i;
    $params[$p] = '%' . $w . '%';
    $kindConds[]       = "(a.artikelnummer LIKE $p OR vater.name LIKE $p OR a.name LIKE $p OR ac.code LIKE $p)";
    $standaloneConds[] = "(a.artikelnummer LIKE $p OR a.name LIKE $p OR ac.code LIKE $p)";
}
$kindWhere       = implode(' AND ', $kindConds);
$standaloneWhere = implode(' AND ', $standaloneConds);

$sql = "
    -- Kind-Artikel (haben Vater)
    SELECT
        'kind'              AS typ,
        a.id,
        vater.artikelnummer AS artikelnummer,
        a.artikelnummer     AS varianten_artikelnummer,
        ac.code             AS gtin,
        a.aktiv,
        a.geaendert_am,
        a.name              AS kind_name,
        vater.name          AS artikel_name
    FROM artikel a
    INNER JOIN artikel vater ON vater.id = a.vaterartikel_id
    LEFT JOIN artikel_codes ac ON ac.artikel_id = a.id AND ac.typ = 'GTIN13'
    WHERE $kindWhere

    UNION ALL

    -- Standalone-Artikel (kein Vater, kein Kind)
    SELECT
        'artikel'           AS typ,
        a.id,
        a.artikelnummer     AS artikelnummer,
        NULL                AS varianten_artikelnummer,
        ac.code             AS gtin,
        a.aktiv,
        a.geaendert_am,
        NULL                AS kind_name,
        a.name              AS artikel_name
    FROM artikel a
    LEFT JOIN artikel_codes ac ON ac.artikel_id = a.id AND ac.typ = 'GTIN13'
    WHERE a.vaterartikel_id IS NULL AND a.ist_vater = 0
    AND ($standaloneWhere)

    ORDER BY artikel_name, typ, varianten_artikelnummer
    LIMIT 30
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll());
