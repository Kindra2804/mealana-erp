<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

$service     = new KassenService();
$kasseInfo   = $service->getKasse(1);
$kasseId     = (int)($kasseInfo['id'] ?? 1);
$kennzahlen  = $service->getTagesKennzahlen($kasseId);
$kassenstand = $service->getKassenstand($kasseId);

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
$letzterXBon = $_SESSION['letzter_x_bon'] ?? null; unset($_SESSION['letzter_x_bon']);
$letzterZBon = $_SESSION['letzter_z_bon'] ?? null; unset($_SESSION['letzter_z_bon']);

$pageTitle    = 'Kassenstand / Kassensturz';
$activeKasseNav = 'ks';
require_once __DIR__ . '/shell_top.php';

function eur(float $v): string { return '€ ' . number_format($v, 2, ',', '.'); }
?>

<div style="max-width:750px;margin:0 auto">

  <?php if ($fehler): ?>
    <div class="ks-feedback fehler"><?= htmlspecialchars($fehler) ?></div>
  <?php endif; ?>

  <?php if ($letzterXBon): ?>
  <div class="ks-feedback ok">✓ X-Bon erstellt: <?= htmlspecialchars($letzterXBon['bon_nr']) ?></div>
  <?php endif; ?>
  <?php if ($letzterZBon): ?>
  <div class="ks-feedback ok" style="font-size:16px;font-weight:700">✓ Tagesabschluss: <?= htmlspecialchars($letzterZBon['bon_nr']) ?></div>
  <?php endif; ?>

  <!-- Kassenstand-Kacheln -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px">
    <div class="ks-card" style="text-align:center">
      <div style="font-size:12px;color:#888;margin-bottom:4px">Kassenstand (Bar)</div>
      <div style="font-size:28px;font-weight:900;color:<?= $kassenstand >= 0 ? '#27ae60' : '#e74c3c' ?>">
        <?= eur($kassenstand) ?>
      </div>
    </div>
    <div class="ks-card" style="text-align:center">
      <div style="font-size:12px;color:#888;margin-bottom:4px">Umsatz heute</div>
      <div style="font-size:28px;font-weight:900;color:#e67e22">
        <?= eur((float)$kennzahlen['umsatz_gesamt']) ?>
      </div>
      <div style="font-size:12px;color:#666"><?= (int)$kennzahlen['anzahl_bons'] ?> Bons</div>
    </div>
    <div class="ks-card" style="text-align:center">
      <div style="font-size:12px;color:#888;margin-bottom:4px">Stornos</div>
      <div style="font-size:28px;font-weight:900;color:#e74c3c">
        <?= eur(abs((float)$kennzahlen['storniert_betrag'])) ?>
      </div>
      <div style="font-size:12px;color:#666"><?= (int)$kennzahlen['anzahl_stornos'] ?> Bon(s)</div>
    </div>
  </div>

  <!-- Aufschlüsselung nach Zahlungsart -->
  <div class="ks-card">
    <div class="ks-card-title">Umsatz nach Zahlungsart — <?= htmlspecialchars($kennzahlen['datum']) ?></div>
    <table class="ks-table">
      <thead><tr><th>Zahlungsart</th><th style="text-align:right">Betrag</th></tr></thead>
      <tbody>
        <tr><td>Bar</td><td style="text-align:right;font-weight:700"><?= eur((float)$kennzahlen['umsatz_bar'] + (float)$kennzahlen['umsatz_kombi_bar']) ?></td></tr>
        <tr><td>Karte (extern)</td><td style="text-align:right;font-weight:700"><?= eur((float)$kennzahlen['umsatz_karte'] + (float)$kennzahlen['umsatz_kombi_karte']) ?></td></tr>
        <tr><td>Gutschein</td><td style="text-align:right;font-weight:700"><?= eur((float)$kennzahlen['umsatz_gs']) ?></td></tr>
        <tr style="border-top:2px solid #1a3a5c">
          <td><strong>Gesamt (netto Stornos)</strong></td>
          <td style="text-align:right;font-weight:900;font-size:16px;color:#e67e22">
            <?= eur((float)$kennzahlen['umsatz_gesamt']) ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Kassenbuch heute -->
  <?php if ((float)$kennzahlen['einlagen'] != 0 || (float)$kennzahlen['entnahmen'] != 0): ?>
  <div class="ks-card">
    <div class="ks-card-title">Bargeld-Bewegungen</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div>
        <div style="font-size:12px;color:#888">Einlagen</div>
        <div style="font-size:20px;font-weight:700;color:#4caf50"><?= eur((float)$kennzahlen['einlagen']) ?></div>
      </div>
      <div>
        <div style="font-size:12px;color:#888">Entnahmen</div>
        <div style="font-size:20px;font-weight:700;color:#ef5350"><?= eur(abs((float)$kennzahlen['entnahmen'])) ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Zählhilfe -->
  <div class="ks-card">
    <div class="ks-card-title">Zählhilfe (Kassenbestand manuell zählen)</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px" id="zaehl-grid">
      <?php
      $scheine  = [200, 100, 50, 20, 10, 5];
      $muenzen  = [2, 1, 0.5, 0.2, 0.1, 0.05, 0.02, 0.01];
      foreach (array_merge($scheine, $muenzen) as $wert):
        $id = 'z_' . str_replace('.', '_', $wert);
      ?>
      <div style="text-align:center">
        <div style="font-size:13px;color:#888;margin-bottom:4px">
          <?= $wert >= 1 ? '€ ' . number_format($wert, 0) : number_format($wert * 100, 0) . ' ct' ?>
        </div>
        <input type="number" id="<?= $id ?>" min="0" value="0" step="1"
               class="ks-input" style="text-align:center;font-size:18px;font-weight:700;padding:8px"
               oninput="zaehlen()" data-wert="<?= $wert ?>">
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:14px;display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:14px;color:#888">Gezählter Kassenstand:</div>
      <div style="font-size:26px;font-weight:900;color:#fff" id="zaehl-summe">€ 0,00</div>
    </div>
    <div id="zaehl-diff" style="text-align:right;font-size:13px;color:#888;margin-top:4px"></div>
  </div>

  <!-- X-Bon / Z-Bon -->
  <div class="ks-card">
    <div class="ks-card-title">Abschlüsse</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div>
        <div style="font-size:14px;color:#aaa;margin-bottom:8px"><strong>X-Bon</strong> — Zwischenabschluss</div>
        <div style="font-size:12px;color:#666;margin-bottom:12px">Erstellt Bon-Protokoll, setzt Kasse nicht zurück.</div>
        <form method="post" action="kassensturz_speichern.php">
          <input type="hidden" name="aktion" value="x_bon">
          <input type="hidden" name="kasse_id" value="<?= $kasseId ?>">
          <button type="submit" class="ks-btn ks-btn-secondary" style="width:100%">📊 X-Bon drucken</button>
        </form>
      </div>
      <div>
        <div style="font-size:14px;color:#e67e22;margin-bottom:8px"><strong>Z-Bon</strong> — Tagesabschluss</div>
        <div style="font-size:12px;color:#666;margin-bottom:12px">Erstellt Tagesabschluss-Bon und schließt den Tag ab.</div>
        <form method="post" action="kassensturz_speichern.php" onsubmit="return confirm('Tagesabschluss wirklich durchführen?')">
          <input type="hidden" name="aktion" value="z_bon">
          <input type="hidden" name="kasse_id" value="<?= $kasseId ?>">
          <button type="submit" class="ks-btn ks-btn-primary" style="width:100%">🔒 Z-Bon / Tagesabschluss</button>
        </form>
      </div>
    </div>
  </div>

</div>

<script>
function zaehlen() {
    var summe = 0;
    document.querySelectorAll('#zaehl-grid input').forEach(function(el) {
        summe += (parseInt(el.value) || 0) * parseFloat(el.dataset.wert);
    });
    var summeGer = Math.round(summe * 100) / 100;
    document.getElementById('zaehl-summe').textContent = '€ ' + summeGer.toFixed(2).replace('.', ',');
    var soll = <?= round($kassenstand, 2) ?>;
    var diff = summeGer - soll;
    var diffEl = document.getElementById('zaehl-diff');
    if (Math.abs(diff) < 0.005) {
        diffEl.textContent = '✓ Stimmt überein';
        diffEl.style.color = '#27ae60';
    } else {
        diffEl.textContent = (diff > 0 ? 'Überbestand: +' : 'Fehlbetrag: ') + '€ ' + Math.abs(diff).toFixed(2).replace('.', ',');
        diffEl.style.color = diff > 0 ? '#f39c12' : '#e74c3c';
    }
}
</script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
