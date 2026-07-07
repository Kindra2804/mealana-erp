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
$kasseInfo = $service->getKasse($aktuelleKasseId);
$kasseId   = (int)($kasseInfo['id'] ?? 1);

$datum = $_GET['datum'] ?? date('Y-m-d');
$bons  = $service->getBonListe($kasseId, $datum);

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);

$pageTitle    = 'Bon-Journal';
$activeKasseNav = 'journal';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:900px;margin:0 auto">

  <?php if ($erfolg): ?>
    <div class="ks-feedback ok"><?= htmlspecialchars($erfolg) ?></div>
  <?php endif; ?>
  <?php if ($fehler): ?>
    <div class="ks-feedback fehler"><?= htmlspecialchars($fehler) ?></div>
  <?php endif; ?>

  <div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
    <form method="get" style="display:flex;gap:8px;align-items:center">
      <label style="font-size:13px;color:#888">Datum:</label>
      <input type="date" name="datum" value="<?= htmlspecialchars($datum) ?>"
             class="ks-input" style="width:160px;font-size:14px">
      <button type="submit" class="ks-btn ks-btn-secondary" style="padding:9px 16px">Filter</button>
    </form>
    <div style="margin-left:auto;font-size:14px;color:#888">
      <?= count($bons) ?> Bon(s) am <?= date('d.m.Y', strtotime($datum)) ?>
    </div>
  </div>

  <?php if (empty($bons)): ?>
    <div class="ks-card" style="text-align:center;color:#444;padding:40px">Keine Bons für diesen Tag.</div>
  <?php else: ?>
  <div class="ks-card" style="padding:0">
    <table class="ks-table">
      <thead>
        <tr>
          <th>Bon-Nr.</th>
          <th>Zeit</th>
          <th>Zahlungsart</th>
          <th style="text-align:right">Betrag</th>
          <th>Status</th>
          <th>Benutzer</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bons as $bon):
          $typBadge = [
            'verkauf' => '',
            'storno'  => '<span style="background:#c0392b;color:#fff;font-size:10px;padding:2px 6px;border-radius:3px">STORNO</span>',
            'x_bon'   => '<span style="background:#2980b9;color:#fff;font-size:10px;padding:2px 6px;border-radius:3px">X-BON</span>',
            'z_bon'   => '<span style="background:#8e44ad;color:#fff;font-size:10px;padding:2px 6px;border-radius:3px">Z-BON</span>',
          ][$bon['typ']] ?? '';
          $zahlLabel = [
            'bar' => '💶 Bar',
            'karte_extern' => '💳 Karte',
            'gutschein' => '🎁 GS',
            'kombi' => '💱 Kombi',
          ][$bon['zahlungsart']] ?? $bon['zahlungsart'];
        ?>
        <tr style="<?= $bon['storniert'] ? 'opacity:.5' : '' ?>">
          <td class="fett" style="font-family:monospace"><?= htmlspecialchars($bon['bon_nr']) ?></td>
          <td style="color:#888"><?= date('H:i:s', strtotime($bon['erstellt_am'])) ?></td>
          <td><?= $bon['typ'] === 'verkauf' ? $zahlLabel : '' ?></td>
          <td style="text-align:right;font-weight:700;color:<?= (float)$bon['bruttobetrag'] < 0 ? '#ef5350' : '#eee' ?>">
            € <?= number_format((float)$bon['bruttobetrag'], 2, ',', '.') ?>
          </td>
          <td>
            <?= $typBadge ?>
            <?= $bon['storniert'] ? '<span style="background:#555;color:#ccc;font-size:10px;padding:2px 6px;border-radius:3px">STORNIERT</span>' : '' ?>
          </td>
          <td style="color:#666;font-size:12px"><?= htmlspecialchars($bon['benutzer_name'] ?? '—') ?></td>
          <td style="white-space:nowrap">
            <a href="bon_druck.php?id=<?= $bon['id'] ?>" target="_blank"
               class="ks-btn ks-btn-secondary" style="padding:5px 10px;font-size:12px" title="80mm-Bon">🖨</a>
            <?php if (in_array($bon['typ'], ['verkauf', 'storno'], true)): ?>
              <a href="bon_a4.php?id=<?= $bon['id'] ?>" target="_blank"
                 class="ks-btn ks-btn-secondary" style="padding:5px 10px;font-size:12px" title="Als A4-Rechnung anzeigen/drucken">A4</a>
            <?php endif; ?>
            <?php if ($bon['typ'] === 'verkauf' && !$bon['storniert']): ?>
              <form method="post" action="bon_stornieren.php" style="display:inline" onsubmit="return confirm('Bon wirklich stornieren?')">
                <input type="hidden" name="bon_id" value="<?= $bon['id'] ?>">
                <button type="submit" class="ks-btn ks-btn-danger" style="padding:5px 10px;font-size:12px">Storno</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
