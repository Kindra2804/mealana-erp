<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

$service   = new KassenService();
$kasseInfo = $service->getKasse(1);
$kasseId   = (int)($kasseInfo['id'] ?? 1);

$eintraege    = $service->getKassenbuchHeute($kasseId);
$kassenstand  = $service->getKassenstand($kasseId);

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);

$pageTitle    = 'Kassenbuch';
$activeKasseNav = 'kb';
$kasseInfo    = $kasseInfo;
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:700px;margin:0 auto">

  <?php if ($erfolg): ?>
    <div class="ks-feedback ok"><?= htmlspecialchars($erfolg) ?></div>
  <?php endif; ?>
  <?php if ($fehler): ?>
    <div class="ks-feedback fehler"><?= htmlspecialchars($fehler) ?></div>
  <?php endif; ?>

  <div class="ks-card" style="text-align:center">
    <div style="font-size:14px;color:#888;margin-bottom:4px">Aktueller Kassenstand</div>
    <div style="font-size:42px;font-weight:900;color:<?= $kassenstand >= 0 ? '#27ae60' : '#e74c3c' ?>">
      € <?= number_format(abs($kassenstand), 2, ',', '.') ?>
    </div>
  </div>

  <div class="ks-card">
    <div class="ks-card-title">Bargeldbewegung buchen</div>
    <form method="post" action="kassenbuch_speichern.php">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px">
        <div>
          <label style="font-size:13px;color:#888;display:block;margin-bottom:6px">Typ</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:2px solid #1a3a5c;border-radius:8px;cursor:pointer;color:#eee" id="lbl-einlage">
              <input type="radio" name="typ" value="einlage" checked onchange="typGewaehlt('einlage')" style="accent-color:#27ae60">
              <div><strong>Einlage</strong><div style="font-size:11px;color:#888">Geld in Kasse</div></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:2px solid #1a3a5c;border-radius:8px;cursor:pointer;color:#eee" id="lbl-entnahme">
              <input type="radio" name="typ" value="entnahme" onchange="typGewaehlt('entnahme')" style="accent-color:#e74c3c">
              <div><strong>Entnahme</strong><div style="font-size:11px;color:#888">Geld aus Kasse</div></div>
            </label>
          </div>
        </div>
        <div>
          <label style="font-size:13px;color:#888;display:block;margin-bottom:6px">Betrag (€)</label>
          <input type="number" name="betrag" step="0.01" min="0.01" class="ks-input" style="font-size:24px;font-weight:700" placeholder="0,00" required>
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label style="font-size:13px;color:#888;display:block;margin-bottom:6px">Notiz (optional)</label>
        <input type="text" name="notiz" class="ks-input" placeholder="z.B. Wechselgeld, Aufstockung…">
      </div>
      <input type="hidden" name="kasse_id" value="<?= $kasseId ?>">
      <button type="submit" class="ks-btn ks-btn-success" style="width:100%;font-size:18px;padding:16px" id="submit-btn">Buchen</button>
    </form>
  </div>

  <div class="ks-card">
    <div class="ks-card-title">Bewegungen heute</div>
    <?php if (empty($eintraege)): ?>
      <p style="color:#444;text-align:center;padding:20px 0">Noch keine Buchungen heute.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead>
          <tr><th>Zeit</th><th>Typ</th><th>Notiz</th><th style="text-align:right">Betrag</th><th>Benutzer</th></tr>
        </thead>
        <tbody>
          <?php foreach ($eintraege as $e): ?>
          <?php $pos = (float)$e['betrag'] >= 0; ?>
          <tr>
            <td style="color:#888"><?= date('H:i', strtotime($e['erstellt_am'])) ?></td>
            <td>
              <span style="background:<?= $pos ? '#1a3a28' : '#3a1a1a' ?>;color:<?= $pos ? '#4caf50' : '#ef5350' ?>;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:700">
                <?= $e['typ'] === 'einlage' ? 'Einlage' : ($e['typ'] === 'entnahme' ? 'Entnahme' : 'Anfangsbestand') ?>
              </span>
            </td>
            <td style="color:#aaa"><?= htmlspecialchars($e['notiz'] ?? '—') ?></td>
            <td style="text-align:right;font-weight:700;color:<?= $pos ? '#4caf50' : '#ef5350' ?>">
              <?= $pos ? '+' : '' ?>€ <?= number_format(abs((float)$e['betrag']), 2, ',', '.') ?>
            </td>
            <td style="color:#666;font-size:12px"><?= htmlspecialchars($e['benutzer_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<script>
function typGewaehlt(typ) {
    document.getElementById('lbl-einlage').style.borderColor  = typ === 'einlage'  ? '#27ae60' : '#1a3a5c';
    document.getElementById('lbl-entnahme').style.borderColor = typ === 'entnahme' ? '#e74c3c' : '#1a3a5c';
    document.getElementById('submit-btn').textContent = typ === 'einlage' ? '💚 Einlage buchen' : '🔴 Entnahme buchen';
}
typGewaehlt('einlage');
</script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
