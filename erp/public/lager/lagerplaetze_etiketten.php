<?php
/**
 * Etiketten für Lagerplätze (A4, 3 × 8 Etiketten à 70 × 37 mm) mit QR-Code.
 * Der QR-Code enthält die Adresse von inventur/fach.php?lp=<id>: mit dem Handy
 * gescannt öffnet er direkt die Zählung dieses Fachs, im Scanfeld der Inventur-
 * Zählseite springt er zum Fach. Die ID bleibt gleich, auch wenn das Kürzel später
 * umbenannt wird -- dann reicht ein neues Etikett, alte QR-Codes funktionieren weiter.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/QrCode.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$ids = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))));
if (!$ids) { http_response_code(400); exit('Keine Lagerplätze gewählt.'); }

$ph   = implode(',', array_fill(0, count($ids), '?'));
$stmt = Database::getInstance()->prepare("
    SELECT lp.id, lp.bezeichnung, l.name AS lager_name
    FROM lagerplaetze lp JOIN lager l ON l.id = lp.lager_id
    WHERE lp.id IN ($ph)
    ORDER BY l.name, lp.sortierung, lp.bezeichnung
");
$stmt->execute($ids);
$plaetze = $stmt->fetchAll(PDO::FETCH_ASSOC);

$basisUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH . '/inventur/fach.php?lp=';

// Leere Etiketten am Bogenanfang überspringen (angebrochener Etikettenbogen)
$start = max(0, min(23, (int)($_GET['start'] ?? 0)));

$etiketten = array_merge(array_fill(0, $start, null), $plaetze);
$seiten    = array_chunk($etiketten, 24);

ob_start();
?>
<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
  @page { margin: 0; }
  body { margin: 0; font-family: 'DejaVu Sans', sans-serif; }
  .seite { position: relative; width: 210mm; height: 296mm; page-break-after: always; }
  .seite:last-child { page-break-after: auto; }
  .etikett { position: absolute; width: 70mm; height: 37mm; overflow: hidden; }
  .qr { position: absolute; left: 3mm; top: 3.5mm; width: 30mm; height: 30mm; }
  .kuerzel { position: absolute; left: 35mm; top: 9mm; width: 34mm; font-weight: bold; color: #111; white-space: nowrap; }
  .lager { position: absolute; left: 35mm; top: 22mm; width: 33mm; font-size: 7.5pt; color: #555; }
</style></head><body>
<?php foreach ($seiten as $seite): ?>
<div class="seite">
  <?php foreach ($seite as $i => $lp): if (!$lp) continue;
      $spalte = $i % 3; $zeile = intdiv($i, 3); ?>
  <div class="etikett" style="left:<?= $spalte * 70 ?>mm;top:<?= 0.5 + $zeile * 37 ?>mm">
    <img class="qr" src="<?= QrCode::dataUri($basisUrl . $lp['id'], 240) ?>">
    <?php $len = mb_strlen($lp['bezeichnung']); // Schrift so groß wie möglich, ohne Umbruch
          $pt  = $len <= 6 ? 22 : ($len <= 8 ? 17 : ($len <= 10 ? 14 : ($len <= 13 ? 11 : 9))); ?>
    <div class="kuerzel" style="font-size:<?= $pt ?>pt"><?= htmlspecialchars($lp['bezeichnung']) ?></div>
    <div class="lager"><?= htmlspecialchars($lp['lager_name']) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
</body></html>
<?php
$html = ob_get_clean();

$opt = new \Dompdf\Options();
$opt->set('defaultFont', 'DejaVu Sans');
$opt->set('isRemoteEnabled', false);
$dom = new \Dompdf\Dompdf($opt);
$dom->loadHtml($html, 'UTF-8');
$dom->setPaper('A4', 'portrait');
$dom->render();

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Lagerplatz-Etiketten.pdf"');
echo $dom->output();
