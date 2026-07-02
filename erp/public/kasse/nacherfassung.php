<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';

$service = new BfrService();

$offeneBelege     = $service->offeneBelege();
$offeneNullbelege = $service->offeneNullbelege();
$laeufe           = $service->nachsignierungsLaeufe();

$laufId      = isset($_GET['lauf_id']) ? (int)$_GET['lauf_id'] : 0;
$laufBelege  = $laufId ? $service->laufBelege($laufId) : [];

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);

$statusBadge = [
    'ausstehend' => '<span style="background:#92400e;color:#fbbf24;font-size:10px;padding:2px 6px;border-radius:3px">AUSSTEHEND</span>',
    'fehler'     => '<span style="background:#7f1d1d;color:#fca5a5;font-size:10px;padding:2px 6px;border-radius:3px">FEHLER</span>',
];

$pageTitle      = 'RKSV Nacherfassung';
$activeKasseNav = 'nacherfassung';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:1000px;margin:0 auto">

  <?php if ($erfolg): ?>
    <div class="ks-feedback ok"><?= htmlspecialchars($erfolg) ?></div>
  <?php endif; ?>
  <?php if ($fehler): ?>
    <div class="ks-feedback fehler"><?= htmlspecialchars($fehler) ?></div>
  <?php endif; ?>

  <div class="ks-card">
    <div class="ks-card-title">Offene / fehlgeschlagene Belege (<?= count($offeneBelege) ?>)</div>
    <?php if (empty($offeneBelege)): ?>
      <p style="color:#888;margin:0">Nichts offen — alle Belege sind signiert.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead>
          <tr>
            <th>Bon-Nr.</th><th>Kasse</th><th>Typ</th><th style="text-align:right">Betrag</th>
            <th>Erstellt</th><th>Status</th><th>Grund</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($offeneBelege as $b): ?>
          <tr>
            <td style="font-family:monospace"><?= htmlspecialchars($b['bon_nr']) ?></td>
            <td><?= htmlspecialchars($b['kasse_name']) ?></td>
            <td><?= htmlspecialchars($b['typ']) ?></td>
            <td style="text-align:right;color:<?= (float)$b['bruttobetrag'] < 0 ? '#ef5350' : 'inherit' ?>">
              € <?= number_format((float)$b['bruttobetrag'], 2, ',', '.') ?>
            </td>
            <td style="color:#888"><?= date('d.m.Y H:i', strtotime($b['erstellt_am'])) ?></td>
            <td><?= $statusBadge[$b['bfr_status']] ?? htmlspecialchars($b['bfr_status']) ?></td>
            <td style="color:#888;font-size:12px"><?= htmlspecialchars($b['bfr_fehlergrund'] ?? '') ?></td>
            <td>
              <?php if ($b['bfr_status'] === 'fehler'): ?>
                <form method="post" action="nacherfassung_retry.php" style="margin:0">
                  <input type="hidden" name="typ" value="bon">
                  <input type="hidden" name="id" value="<?= $b['id'] ?>">
                  <button type="submit" class="ks-btn ks-btn-secondary" style="padding:4px 10px;font-size:12px">Nochmal versuchen</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="ks-card">
    <div class="ks-card-title">Offene / fehlgeschlagene Nullbelege (<?= count($offeneNullbelege) ?>)</div>
    <?php if (empty($offeneNullbelege)): ?>
      <p style="color:#888;margin:0">Nichts offen.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead>
          <tr><th>Beleg-Nr.</th><th>Kasse</th><th>Monat</th><th>Ausgelöst durch</th><th>Status</th><th>Grund</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($offeneNullbelege as $n): ?>
          <tr>
            <td style="font-family:monospace"><?= htmlspecialchars($n['beleg_nr']) ?></td>
            <td><?= htmlspecialchars($n['kasse_name']) ?></td>
            <td><?= htmlspecialchars($n['monat']) ?></td>
            <td><?= htmlspecialchars($n['ausgeloest_durch']) ?></td>
            <td><?= $statusBadge[$n['bfr_status']] ?? htmlspecialchars($n['bfr_status']) ?></td>
            <td style="color:#888;font-size:12px"><?= htmlspecialchars($n['bfr_fehlergrund'] ?? '') ?></td>
            <td>
              <?php if ($n['bfr_status'] === 'fehler'): ?>
                <form method="post" action="nacherfassung_retry.php" style="margin:0">
                  <input type="hidden" name="typ" value="nullbeleg">
                  <input type="hidden" name="id" value="<?= $n['id'] ?>">
                  <button type="submit" class="ks-btn ks-btn-secondary" style="padding:4px 10px;font-size:12px">Nochmal versuchen</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="ks-card">
    <div class="ks-card-title">Nachsignierungsläufe (Sammelbelege)</div>
    <?php if (empty($laeufe)): ?>
      <p style="color:#888;margin:0">Bisher noch kein Nachsignierungslauf nötig gewesen.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead>
          <tr><th>Kasse</th><th>Ausgelöst durch</th><th>Gestartet</th><th>Beendet</th><th>Signiert</th><th>Fehlgeschlagen</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($laeufe as $l): ?>
          <tr style="<?= $laufId === (int)$l['id'] ? 'background:#1e293b' : '' ?>">
            <td><?= htmlspecialchars($l['kasse_name']) ?></td>
            <td><?= htmlspecialchars($l['ausgeloest_durch']) ?></td>
            <td style="color:#888"><?= date('d.m.Y H:i:s', strtotime($l['gestartet_am'])) ?></td>
            <td style="color:#888"><?= $l['beendet_am'] ? date('d.m.Y H:i:s', strtotime($l['beendet_am'])) : '—' ?></td>
            <td><?= (int)$l['anzahl_signiert'] ?></td>
            <td style="color:<?= $l['anzahl_fehlgeschlagen'] > 0 ? '#ef5350' : 'inherit' ?>"><?= (int)$l['anzahl_fehlgeschlagen'] ?></td>
            <td><a href="?lauf_id=<?= $l['id'] ?>" style="color:#60a5fa;font-size:12px">Belege ansehen</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($laufId): ?>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid #334155">
        <div style="font-weight:700;margin-bottom:8px">Belege in Lauf #<?= $laufId ?></div>
        <?php if (empty($laufBelege)): ?>
          <p style="color:#888;margin:0">Keine Belege gefunden.</p>
        <?php else: ?>
          <table class="ks-table">
            <thead><tr><th>Bon-Nr.</th><th>Typ</th><th style="text-align:right">Betrag</th><th>Status</th><th>Signiert am</th></tr></thead>
            <tbody>
            <?php foreach ($laufBelege as $lb): ?>
              <tr>
                <td style="font-family:monospace"><?= htmlspecialchars($lb['bon_nr']) ?></td>
                <td><?= htmlspecialchars($lb['typ']) ?></td>
                <td style="text-align:right">€ <?= number_format((float)$lb['bruttobetrag'], 2, ',', '.') ?></td>
                <td><?= $statusBadge[$lb['bfr_status']] ?? htmlspecialchars($lb['bfr_status']) ?></td>
                <td style="color:#888"><?= $lb['signiert_am'] ? date('d.m.Y H:i:s', strtotime($lb['signiert_am'])) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
