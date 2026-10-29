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
$kasseInfo   = $service->getKasse($aktuelleKasseId);
$kasseId     = (int)($kasseInfo['id'] ?? 1);
// Heute ab dem letzten Z-Bon (nach einem Z-Bon nur, was der nächste Z-Bon enthalten wird)
[$offenVon, $offenBis] = $service->zBereich($kasseId, date('Y-m-d'));
$kennzahlen  = $service->getTagesKennzahlen($kasseId, date('Y-m-d'), $offenVon, $offenBis);
$partnerUmsaetze = $service->getPartnerUmsaetze($kasseId, $kennzahlen['datum'], $offenVon, $offenBis);
$nachZBon    = substr($offenVon, 11) !== '00:00:00';
$kassenstand = $service->getKassenstand($kasseId);
// Z-Bons laufen in Tagesreihenfolge — offene frühere Tage zuerst abschließen
$offeneZTage = $service->offeneZTage($kasseId, date('Y-m-d'));
$letzterZTag = $service->letzterZTag($kasseId);

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
      <div style="font-size:12px;color:#888;margin-bottom:4px"><?= $nachZBon ? 'Umsatz seit Z-Bon' : 'Umsatz heute' ?></div>
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
    <div class="ks-card-title">Umsatz nach Zahlungsart — <?= date('d.m.Y', strtotime($kennzahlen['datum'])) ?><?= $nachZBon ? ' · seit Z-Bon um ' . date('H:i', strtotime($offenVon)) . ' Uhr' : '' ?></div>
    <table class="ks-table">
      <thead><tr><th>Zahlungsart</th><th style="text-align:right">Betrag</th></tr></thead>
      <tbody>
        <tr><td>Bar</td><td style="text-align:right;font-weight:700"><?= eur((float)$kennzahlen['umsatz_bar'] + (float)$kennzahlen['umsatz_kombi_bar']) ?></td></tr>
        <tr><td>Karte (extern)</td><td style="text-align:right;font-weight:700"><?= eur((float)$kennzahlen['umsatz_karte'] + (float)$kennzahlen['umsatz_kombi_karte']) ?></td></tr>
        <tr><td>Gutschein</td><td style="text-align:right;font-weight:700"><?= eur((float)$kennzahlen['umsatz_gs']) ?></td></tr>
        <?php $partnerGes = (float)$kennzahlen['partner_fremd'] + (float)$kennzahlen['partner_spende']; ?>
        <?php if ($partnerUmsaetze): ?>
        <tr style="border-top:2px solid #1a3a5c">
          <td><strong>Kassiert gesamt (inkl. Partnerware)</strong></td>
          <td style="text-align:right;font-weight:700"><?= eur((float)$kennzahlen['umsatz_gesamt'] + $partnerGes) ?></td>
        </tr>
        <?php foreach ($partnerUmsaetze as $pu): ?>
        <tr style="font-size:13px;color:#888">
          <td>− <?= $pu['modus'] === 'spende' ? 'Spende' : 'Fremdrechnung' ?>: <?= htmlspecialchars($pu['partner_name']) ?> (<?= $pu['anzahl_bons'] ?> Bon<?= $pu['anzahl_bons'] === 1 ? '' : 's' ?>)</td>
          <td style="text-align:right"><?= eur(-$pu['brutto']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        <tr style="border-top:2px solid #1a3a5c">
          <td><strong><?= $partnerUmsaetze ? 'Eigener Umsatz' : 'Gesamt' ?></strong></td>
          <td style="text-align:right;font-weight:900;font-size:16px;color:#e67e22">
            <?= eur((float)$kennzahlen['umsatz_gesamt']) ?>
          </td>
        </tr>
        <?php if ((int)$kennzahlen['anzahl_stornos'] > 0): ?>
        <tr style="font-size:13px;color:#e74c3c">
          <td>Stornierte Bons (<?= (int)$kennzahlen['anzahl_stornos'] ?>) — oben nicht enthalten</td>
          <td style="text-align:right"><?= eur(abs((float)$kennzahlen['storniert_betrag'])) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ((float)$kennzahlen['partner_spende_bar'] > 0.004): ?>
        <tr style="font-size:12px;color:#888">
          <td colspan="2">Spende bar <?= eur((float)$kennzahlen['partner_spende_bar']) ?> liegt in der Spendenlade, nicht im Kassenstand.</td>
        </tr>
        <?php endif; ?>
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
          <?php if ($offeneZTage): ?>
          <div style="font-size:12px;color:#e67e22;margin-bottom:8px">
            <strong>!</strong> Noch ohne Z-Bon: <?= implode(', ', array_map(fn($d) => date('d.m.Y', strtotime($d)), $offeneZTage)) ?>
            — bitte der Reihe nach abschließen (sonst passiert das automatisch um Mitternacht).
          </div>
          <?php endif; ?>
          <label style="font-size:11px;color:#888;display:block;margin-bottom:3px">Für Tag (leer = heute — z.B. nachträglich nach Messe-Upload)</label>
          <input type="date" name="datum" class="ks-input" style="margin-bottom:8px" max="<?= date('Y-m-d') ?>"
                 <?= $letzterZTag ? 'min="' . date('Y-m-d', strtotime($letzterZTag . ' +1 day')) . '"' : '' ?>
                 value="<?= $offeneZTage[0] ?? '' ?>">
          <button type="submit" class="ks-btn ks-btn-primary" style="width:100%">🔒 Z-Bon / Tagesabschluss</button>
        </form>
      </div>
    </div>
    <div style="border-top:1px solid #2a4a6c;margin-top:14px;padding-top:14px;display:flex;gap:10px">
      <a href="abschluss_periode.php" class="ks-btn ks-btn-secondary" style="flex:1;text-align:center">📅 Monats-/Quartalsabschluss</a>
      <a href="abschluss_liste.php"   class="ks-btn ks-btn-secondary" style="flex:1;text-align:center">📋 Abschluss-Archiv</a>
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
