<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';

header('Content-Type: application/json');

$q  = trim($_GET['q'] ?? '');
if (!$q) { echo json_encode(['gefunden' => false]); exit; }

$db = Database::getInstance();

// Suche per EAN oder Artikelnummer
$stmt = $db->prepare("
    SELECT a.id, a.name, a.artikelnummer, a.zustand, a.zustand_vater_id,
           COALESCE(a.charge_pflicht, 0) AS charge_pflicht,
           (SELECT code FROM artikel_codes WHERE artikel_id = a.id AND typ = 'GTIN13' LIMIT 1) AS ean,
           (SELECT dateiname FROM artikel_bilder WHERE artikel_id = COALESCE(a.vaterartikel_id, a.id) AND position = 0 LIMIT 1) AS hauptbild
    FROM artikel a
    LEFT JOIN artikel_codes ac ON ac.artikel_id = a.id AND ac.typ = 'GTIN13'
    WHERE ac.code = :ean OR a.artikelnummer = :nr
    LIMIT 1
");
$stmt->execute([':ean' => $q, ':nr' => $q]);
$artikel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artikel) { echo json_encode(['gefunden' => false]); exit; }

$artikelId = (int)$artikel['id'];

// Lagerstand pro Lager
$bestand = $db->prepare("
    SELECT lb.lager_id, l.name AS lager_name, COALESCE(SUM(lb.bestand), 0) AS bestand
    FROM lagerbestand lb
    JOIN lager l ON l.id = lb.lager_id
    WHERE lb.artikel_id = :id
    GROUP BY lb.lager_id, l.name
    ORDER BY l.name
");
$bestand->execute([':id' => $artikelId]);
$bestandListe = $bestand->fetchAll(PDO::FETCH_ASSOC);

// Zustandsartikel (nur wenn Artikel selbst 'neu' und kein Zustandskind)
$zustandsartikel = [];
if (empty($artikel['zustand_vater_id'])) {
    $zStmt = $db->prepare("
        SELECT a.id, a.name, a.zustand
        FROM artikel a
        WHERE a.zustand_vater_id = :vid AND a.aktiv = 1
        ORDER BY a.zustand
    ");
    $zStmt->execute([':vid' => $artikelId]);
    $zustandLabels = [
        'gebraucht'         => 'Gebraucht',
        'generalueberholt'  => 'Generalüberholt',
        'beschaedigt'       => 'Beschädigt',
        'retour'            => 'Retour',
        'demo'              => 'Demo',
        'muster'            => 'Muster',
        'ausstellungsstueck'=> 'Ausstellungsstück',
    ];
    foreach ($zStmt->fetchAll(PDO::FETCH_ASSOC) as $z) {
        $z['zustand_label'] = $zustandLabels[$z['zustand']] ?? ucfirst($z['zustand']);
        $zustandsartikel[]  = $z;
    }
}

// Hat der Artikel Chargen im Lager?
$hatChargen = $db->prepare("
    SELECT EXISTS(
        SELECT 1 FROM lagerbestand
        WHERE artikel_id = :id AND charge IS NOT NULL AND bestand > 0
    ) AS hat
");
$hatChargen->execute([':id' => $artikelId]);
$artikel['hat_chargen'] = (bool)$hatChargen->fetchColumn();

echo json_encode([
    'gefunden'       => true,
    'artikel'        => $artikel,
    'bestand'        => $bestandListe,
    'zustandsartikel'=> $zustandsartikel,
]);
