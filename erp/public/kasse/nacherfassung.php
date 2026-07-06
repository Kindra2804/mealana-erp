<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';

$service = new BfrService();

$offen      = $service->offeneEpisodenMitWarnung();
$historie   = $service->ausfallHistorie();

$ausfallId = isset($_GET['ausfall_id']) ? (int)$_GET['ausfall_id'] : 0;
$ereignisse = $ausfallId ? $service->episodeEreignisse($ausfallId) : [];

$typBadge = [
    'dienst_nicht_erreichbar'          => '<span style="background:#7f1d1d;color:#fca5a5;font-size:10px;padding:2px 6px;border-radius:3px">DIENST WEG</span>',
    'sicherheitseinrichtung_ausgefallen' => '<span style="background:#92400e;color:#fbbf24;font-size:10px;padding:2px 6px;border-radius:3px">AUSGEFALLEN</span>',
];

$pageTitle      = 'RKSV Ausfall-Historie';
$activeKasseNav = 'nacherfassung';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:1000px;margin:0 auto">

  <div class="ks-card">
    <div class="ks-card-title">Offene Störungen (<?= count($offen) ?>)</div>
    <?php if (empty($offen)): ?>
      <p style="color:#888;margin:0">Keine offene Störung — alle Kassen signieren normal.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead>
          <tr><th>Kasse</th><th>Seit</th><th>Dauer</th><th>Ereignisse</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($offen as $a): ?>
          <tr style="<?= $a['warnung_24h'] ? 'background:#450a0a' : '' ?>">
            <td><?= htmlspecialchars($a['kasse_name']) ?></td>
            <td style="color:#888"><?= date('d.m.Y H:i', strtotime($a['erste_erkennung_am'])) ?></td>
            <td>
              <?= (int)$a['dauer_stunden'] ?> h
              <?php if ($a['warnung_24h']): ?>
                <span style="color:#f87171;font-weight:700;margin-left:6px">⚠ über 24h — FON-Meldepflicht (48h) im Blick behalten</span>
              <?php endif; ?>
            </td>
            <td><?= (int)$a['anzahl_ereignisse'] ?></td>
            <td><a href="?ausfall_id=<?= $a['id'] ?>" style="color:#60a5fa;font-size:12px">Ereignisse ansehen</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="ks-card">
    <div class="ks-card-title">Historie (offen + gelöst)</div>
    <?php if (empty($historie)): ?>
      <p style="color:#888;margin:0">Bisher keine Störung aufgetreten.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead>
          <tr><th>Kasse</th><th>Von</th><th>Bis</th><th>Dauer</th><th>Ereignisse</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($historie as $h): ?>
          <tr style="<?= $ausfallId === (int)$h['id'] ? 'background:#1e293b' : '' ?>">
            <td><?= htmlspecialchars($h['kasse_name']) ?></td>
            <td style="color:#888"><?= date('d.m.Y H:i:s', strtotime($h['erste_erkennung_am'])) ?></td>
            <td style="color:#888"><?= $h['geloest_am'] ? date('d.m.Y H:i:s', strtotime($h['geloest_am'])) : '<span style="color:#f87171">läuft noch</span>' ?></td>
            <td><?= $h['geloest_am'] ? round((strtotime($h['geloest_am']) - strtotime($h['erste_erkennung_am'])) / 60) . ' min' : '—' ?></td>
            <td><?= (int)$h['anzahl_ereignisse'] ?></td>
            <td><a href="?ausfall_id=<?= $h['id'] ?>" style="color:#60a5fa;font-size:12px">Ereignisse ansehen</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($ausfallId): ?>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid #334155">
        <div style="font-weight:700;margin-bottom:8px">Ereignisse zu Störung #<?= $ausfallId ?></div>
        <?php if (empty($ereignisse)): ?>
          <p style="color:#888;margin:0">Keine Ereignisse gefunden.</p>
        <?php else: ?>
          <table class="ks-table">
            <thead><tr><th>Zeitpunkt</th><th>Typ</th><th>Bon-Nr.</th><th>Versuche</th></tr></thead>
            <tbody>
            <?php foreach ($ereignisse as $e): ?>
              <tr>
                <td style="color:#888"><?= date('d.m.Y H:i:s', strtotime($e['aufgetreten_am'])) ?></td>
                <td><?= $typBadge[$e['typ']] ?? htmlspecialchars($e['typ']) ?></td>
                <td style="font-family:monospace"><?= htmlspecialchars($e['bon_nr'] ?? '—') ?></td>
                <td><?= (int)$e['anzahl_versuche'] ?></td>
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
