<?php
require_once __DIR__ . '/../includes/auth_check.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../../src/core/Database.php';

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("

    -- Teil 1: Varianten
    SELECT 
        'variante'        AS typ,
        v.id,
        a.artikelnummer   AS artikelnummer,
        v.artikelnummer   AS varianten_artikelnummer,
        v.gtin,
        v.farbe_name,
        v.aktiv AS aktiv,
        v.geaendert_am AS geaendert_am,
        a.name            AS artikel_name
    FROM artikel_varianten v
    INNER JOIN artikel a ON v.artikel_id = a.id
    WHERE a.ist_vater = 1
    AND (
        v.artikelnummer LIKE :q
        OR v.gtin = :exact
        OR v.farbe_name LIKE :q
        OR a.name LIKE :q
    )

    UNION ALL

    -- Teil 2: Standalone-Artikel (ist_vater = 0)
    SELECT 
        'artikel'   AS typ,
        a.id        AS id,
        a.artikelnummer   AS artikelnummer,
        NULL        AS varianten_artikelnummer,
        ac.code     AS code,
        NULL        AS farbe_name,
        a.aktiv AS aktiv,
        a.geaendert_am AS geaendert_am,
        a.name      AS name
    FROM artikel a
    LEFT JOIN artikel_codes ac ON a.id = ac.artikel_id
    WHERE a.ist_vater = 0
    AND (
        a.artikelnummer LIKE :q
        OR ac.code = :exact
        OR a.name LIKE :q
    )

    ORDER BY artikel_name, typ, varianten_artikelnummer
    LIMIT 10


");

$stmt->execute([
    'q'     => '%' . $q . '%',
    'exact' => $q
]);

echo json_encode($stmt->fetchAll());
