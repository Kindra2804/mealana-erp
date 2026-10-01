<?php
/** Artikelsuche für die Lieferung an einen Händler: EAN/Nummer/Name, mit Bestand im gewählten Lager + Händlerpreis. */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/haendler/HaendlerService.php';

header('Content-Type: application/json');
$q       = trim($_GET['q'] ?? '');
$lagerId = (int)($_GET['lager_id'] ?? 1);
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

$db   = Database::getInstance();
$stmt = $db->prepare("
    SELECT a.id, a.artikelnummer, a.name,
           (SELECT COALESCE(SUM(lb.bestand), 0) FROM lagerbestand lb WHERE lb.artikel_id = a.id AND lb.lager_id = :lager) AS bestand,
           (SELECT code FROM artikel_codes WHERE artikel_id = a.id AND typ = 'GTIN13' LIMIT 1) AS ean
    FROM artikel a
    WHERE a.aktiv = 1 AND a.partner_id IS NULL
      AND NOT EXISTS (SELECT 1 FROM artikel k WHERE k.vaterartikel_id = a.id)
      AND (a.artikelnummer = :exakt
           OR EXISTS (SELECT 1 FROM artikel_codes c WHERE c.artikel_id = a.id AND c.code = :exakt2)
           OR a.name LIKE :like OR a.artikelnummer LIKE :like2)
    ORDER BY (a.artikelnummer = :exakt3) DESC, bestand DESC, a.artikelnummer
    LIMIT 15
");
$stmt->execute([':lager' => $lagerId, ':exakt' => $q, ':exakt2' => $q, ':exakt3' => $q, ':like' => "%$q%", ':like2' => "%$q%"]);
$svc = new HaendlerService();
$out = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $p = $svc->preis((int)$a['id']);
    $out[] = $a + ['preis_netto' => $p['preis_netto'], 'vk_brutto' => $p['vk_brutto'], 'preis_quelle' => $p['quelle']];
}
echo json_encode($out);
