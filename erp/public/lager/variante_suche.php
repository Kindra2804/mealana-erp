<?php
require_once __DIR__ . '/../includes/auth_check.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../../src/core/Database.php';

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$db   = Database::getInstance();
$stmt = $db->prepare("
    -- Kind-Artikel (haben Vater)
    SELECT
        'kind'              AS typ,
        a.id,
        vater.artikelnummer AS artikelnummer,
        a.artikelnummer     AS varianten_artikelnummer,
        ac.code             AS gtin,
        a.farbe_name,
        a.aktiv,
        a.geaendert_am,
        vater.name          AS artikel_name
    FROM artikel a
    INNER JOIN artikel vater ON vater.id = a.vaterartikel_id
    LEFT JOIN artikel_codes ac ON ac.artikel_id = a.id AND ac.typ = 'GTIN13'
    WHERE (
        a.artikelnummer LIKE :q
        OR ac.code = :exact
        OR a.farbe_name LIKE :q
        OR vater.name LIKE :q
    )

    UNION ALL

    -- Standalone-Artikel (kein Vater, kein Kind)
    SELECT
        'artikel'           AS typ,
        a.id,
        a.artikelnummer     AS artikelnummer,
        NULL                AS varianten_artikelnummer,
        ac.code             AS gtin,
        NULL                AS farbe_name,
        a.aktiv,
        a.geaendert_am,
        a.name              AS artikel_name
    FROM artikel a
    LEFT JOIN artikel_codes ac ON ac.artikel_id = a.id AND ac.typ = 'GTIN13'
    WHERE a.vaterartikel_id IS NULL AND a.ist_vater = 0
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
    'exact' => $q,
]);

echo json_encode($stmt->fetchAll());
