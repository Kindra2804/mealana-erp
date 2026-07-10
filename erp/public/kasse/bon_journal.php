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
              <button type="button" class="ks-btn ks-btn-danger" style="padding:5px 10px;font-size:12px"
                      onclick="stornoStarten(<?= $bon['id'] ?>)">Storno</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<!-- RKSV: BFR-Erreichbarkeits-Popup (2 Eskalationsstufen), gleiches Muster wie bon.php -->
<style>
.ov { display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:900; align-items:center; justify-content:center; }
.ov.offen { display:flex; }
.ov-box { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:32px 36px; min-width:360px; max-width:460px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.25); text-align:center; }
.ov-title { font-size:20px; font-weight:700; color:#1e3a5f; margin-bottom:20px; text-align:center; }
.ov-btn { border:none; border-radius:8px; padding:13px 20px; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit; display:block; width:100%; text-align:center; }
.ov-btn-prim { background:#2563eb; color:#fff; }
.ov-btn-prim:hover { background:#1d4ed8; }
</style>

<div class="ov" id="ov-bfr-ausfall">
  <div class="ov-box">
    <div style="font-size:32px;margin-bottom:6px">⚠</div>
    <div id="bfr-popup-stufe1">
      <div class="ov-title">Dienst nicht erreichbar!</div>
      <p style="color:#64748b;font-size:14px;margin-bottom:22px">
        Die technische Sicherheitseinrichtung (BFR) antwortet nicht.
      </p>
    </div>
    <div id="bfr-popup-stufe2" style="display:none">
      <div class="ov-title">Dienst immer noch nicht erreichbar</div>
      <p style="color:#64748b;font-size:14px;margin-bottom:14px">
        Der Storno bleibt gesperrt, bis der BFR-Dienst wieder antwortet.
        Bitte am Gerät prüfen:
      </p>
      <ul style="text-align:left;color:#64748b;font-size:13px;margin:0 0 22px 20px;padding:0">
        <li>Läuft "BFR" in der Taskleiste?</li>
        <li>Signaturkarte im Kartenleser gesteckt?</li>
        <li>Windows-Update / Firewall gerade aktiv?</li>
      </ul>
    </div>
    <div id="bfr-popup-kontext" style="font-size:12px;color:#94a3b8;margin-bottom:16px"></div>
    <button class="ov-btn ov-btn-prim" id="btn-bfr-retry" onclick="bfrErneutVersuchen()">Erneut versuchen</button>
  </div>
</div>

<script>
var _bfrFehlschlagAnzahl  = 0;
var _bfrPendingStornoId   = null;

function ov(id) { document.getElementById(id).classList.add('offen'); }
function ovSchliessen(id) { document.getElementById(id).classList.remove('offen'); }

function zeigeBfrPopup(kontextText) {
    _bfrFehlschlagAnzahl++;
    var stufe2 = _bfrFehlschlagAnzahl >= 2;
    document.getElementById('bfr-popup-stufe1').style.display = stufe2 ? 'none' : 'block';
    document.getElementById('bfr-popup-stufe2').style.display = stufe2 ? 'block' : 'none';
    document.getElementById('btn-bfr-retry').textContent = stufe2
        ? 'Überprüft — Dienst sollte wieder laufen'
        : 'Erneut versuchen';
    document.getElementById('bfr-popup-kontext').textContent = kontextText || '';
    ov('ov-bfr-ausfall');
}

function bfrErneutVersuchen() {
    ovSchliessen('ov-bfr-ausfall');
    if (_bfrPendingStornoId) {
        var bid = _bfrPendingStornoId;
        _bfrPendingStornoId = null;
        stornoAusfuehren(bid);
    }
}

function stornoStarten(bonId) {
    if (!confirm('Bon wirklich stornieren?')) return;
    stornoAusfuehren(bonId);
}

function stornoAusfuehren(bonId) {
    var body = new FormData();
    body.append('bon_id', bonId);
    fetch('<?= BASE_PATH ?>/kasse/ajax_bon_stornieren.php', { method: 'POST', body: body })
        .then(r => r.json())
        .then(function(d) {
            if (d.erfolg) {
                _bfrFehlschlagAnzahl = 0;
                location.reload();
            } else if (d.bfr_nicht_erreichbar) {
                _bfrPendingStornoId = bonId;
                zeigeBfrPopup('Storno wartet auf den Dienst.');
            } else {
                alert(d.fehler || 'Fehler beim Stornieren.');
            }
        })
        .catch(function() {
            alert('Netzwerkfehler — bitte erneut versuchen.');
        });
}
</script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
