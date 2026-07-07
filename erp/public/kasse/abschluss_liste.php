<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';

$service = new KassenService();
$aktuelleKasseId = (new ArbeitsplatzService())->aktuelleKasseId();
if ($aktuelleKasseId === null) {
    $_SESSION['fehler'] = 'Dieses Gerät ist keiner Kasse zugeordnet. Bitte zuerst einen Arbeitsplatz auswählen.';
    header('Location: ' . BASE_PATH . '/kasse/index.php');
    exit;
}
$kasseInfo  = $service->getKasse($aktuelleKasseId);
$kasseId    = (int)($kasseInfo['id'] ?? 1);
$liste      = $service->getAbschlussListe($kasseId, 180);

$pageTitle      = 'Abschluss-Archiv';
$activeKasseNav = 'ks';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:900px;margin:0 auto">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div style="font-size:16px;font-weight:700;color:#1e3a5f">Abschluss-Archiv (letzte 180 Einträge)</div>
    <a href="kassensturz.php" class="ks-btn ks-btn-secondary" style="font-size:13px">← Kassenstand</a>
  </div>

  <div class="ks-card">
    <table class="ks-table">
      <thead>
        <tr>
          <th>Typ</th>
          <th>Bon-Nr.</th>
          <th>Datum</th>
          <th>Kassierer</th>
          <th style="text-align:right">Umsatz</th>
          <th style="text-align:right">Kassenstand</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($liste)): ?>
        <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:24px">Noch keine Abschlüsse vorhanden.</td></tr>
        <?php endif; ?>
        <?php foreach ($liste as $row): ?>
        <tr>
          <td>
            <?php if ($row['typ'] === 'z'): ?>
              <span class="badge badge-rot">Z-Bon</span>
            <?php else: ?>
              <span class="badge badge-blau">X-Bon</span>
            <?php endif; ?>
          </td>
          <td style="font-weight:700"><?= htmlspecialchars($row['bon_nr'] ?? '') ?></td>
          <td><?= date('d.m.Y', strtotime($row['datum'])) ?></td>
          <td style="color:#64748b"><?= htmlspecialchars($row['kassierer_name'] ?? '—') ?></td>
          <td style="text-align:right;font-weight:700">€ <?= number_format((float)$row['umsatz'], 2, ',', '.') ?></td>
          <td style="text-align:right">€ <?= number_format((float)$row['kassenstand'], 2, ',', '.') ?></td>
          <td style="text-align:right">
            <a href="abschluss_druck.php?id=<?= (int)$row['id'] ?>"
               class="ks-btn ks-btn-secondary" style="font-size:12px;padding:4px 12px">
              🖨 Anzeigen
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
