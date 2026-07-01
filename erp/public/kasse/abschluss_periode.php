<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$service   = new KassenService();
$db        = Database::getInstance();

// Alle Kassen laden für Selektor
$kassen = $db->query("SELECT id, kasse_nr, name FROM kassen WHERE aktiv=1 ORDER BY kasse_nr")->fetchAll();

$konfig = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(\PDO::FETCH_KEY_PAIR);

// Parameter aus GET
$typ      = in_array($_GET['typ'] ?? '', ['monat', 'quartal']) ? $_GET['typ'] : 'monat';
$jahr     = (int)($_GET['jahr'] ?? date('Y'));
$monat    = max(1, min(12, (int)($_GET['monat'] ?? (int)date('m'))));
$quartal  = max(1, min(4,  (int)($_GET['quartal'] ?? (int)ceil(date('n') / 3))));
$kasseId  = (int)($_GET['kasse_id'] ?? ($kassen[0]['id'] ?? 1));

// Datumsgrenzen berechnen
if ($typ === 'monat') {
    $von = sprintf('%04d-%02d-01', $jahr, $monat);
    $bis = date('Y-m-t', strtotime($von));
    $periodLabel = date('F Y', strtotime($von));
} else {
    $qVon = ($quartal - 1) * 3 + 1;
    $qBis = $quartal * 3;
    $von  = sprintf('%04d-%02d-01', $jahr, $qVon);
    $bis  = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $jahr, $qBis)));
    $periodLabel = 'Q' . $quartal . ' ' . $jahr;
}

$anzeigen = isset($_GET['anzeigen']);
$daten = null;
if ($anzeigen) {
    $daten = $service->getPeriodeKennzahlen($kasseId, $von, $bis);
}

// Kasse-Info laden
$kasseInfo = null;
foreach ($kassen as $k) {
    if ((int)$k['id'] === $kasseId) { $kasseInfo = $k; break; }
}

function eur(float $v): string { return '€ ' . number_format($v, 2, ',', '.'); }
function esc_(string $v): string { return htmlspecialchars($v); }

$pageTitle      = 'Perioden-Abschluss';
$activeKasseNav = 'ks';
require_once __DIR__ . '/shell_top.php';
?>

<style>
  @media print {
    .periode-steuerung, .aktions-leiste { display: none !important; }
    .periode-bericht { max-width: 100% !important; padding: 0 !important; }
  }
  .periode-steuerung { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; }
  .ps-field { display: flex; flex-direction: column; gap: 4px; }
  .ps-field label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
  .periode-bericht { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px 28px; }
  .periode-kopf { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1e3a5f; padding-bottom: 14px; margin-bottom: 18px; }
  .pk-firma { font-size: 18px; font-weight: 800; color: #1e3a5f; }
  .pk-sub   { font-size: 12px; color: #64748b; margin-top: 2px; }
  .pk-typ   { font-size: 15px; font-weight: 800; color: #7c3aed; text-align: right; }
  .pk-info  { font-size: 12px; color: #64748b; text-align: right; margin-top: 3px; }
  .pk-periode { font-size: 13px; font-weight: 700; text-align: right; margin-top: 3px; color: #1e293b; }
  .kacheln4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
  .kachel { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
  .kachel-label { font-size: 11px; color: #64748b; margin-bottom: 6px; }
  .kachel-wert  { font-size: 18px; font-weight: 800; }
  .kachel-sub   { font-size: 11px; color: #94a3b8; margin-top: 3px; }
  .kachel.lila  .kachel-wert { color: #7c3aed; }
  .kachel.gruen .kachel-wert { color: #16a34a; }
  .kachel.amber .kachel-wert { color: #d97706; }
  .kachel.blau  .kachel-wert { color: #2563eb; }
  .sek-title { font-size: 11px; font-weight: 700; color: #1e3a5f; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin: 18px 0 10px; }
  .per-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  .per-table thead th { background: #1e3a5f; color: #fff; font-size: 11px; padding: 6px 10px; text-align: left; }
  .per-table thead th.r { text-align: right; }
  .per-table tbody td { padding: 6px 10px; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
  .per-table tbody td.r { text-align: right; white-space: nowrap; }
  .per-table tbody tr:nth-child(even) { background: #f8fafc; }
  .per-table tfoot td { padding: 7px 10px; font-weight: 700; font-size: 13px; border-top: 2px solid #1e3a5f; }
  .per-table tfoot td.r { text-align: right; }
  .zbons-table { width: 100%; border-collapse: collapse; }
  .zbons-table td, .zbons-table th { padding: 5px 10px; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
  .zbons-table th { background: #f8fafc; font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; }
  .zbons-table td.r { text-align: right; }
  .fusszeile { border-top: 1px solid #e2e8f0; margin-top: 20px; padding-top: 8px; font-size: 10px; color: #94a3b8; display: flex; justify-content: space-between; }
  .aktions-leiste { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
  .al-btn { padding: 10px 22px; border: none; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; text-decoration: none; }
  .al-prim  { background: #1e3a5f; color: #fff; }
  .al-gruen { background: #16a34a; color: #fff; }
  .al-sec   { background: #e2e8f0; color: #1e293b; }
  .leer-hinweis { text-align: center; color: #94a3b8; padding: 40px 20px; font-size: 15px; }
</style>

<div style="max-width:920px;margin:0 auto">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div style="font-size:16px;font-weight:700;color:#1e3a5f">Perioden-Abschluss</div>
    <a href="kassensturz.php" class="ks-btn ks-btn-secondary" style="font-size:13px">← Kassenstand</a>
  </div>

  <!-- STEUERUNG -->
  <form method="get" class="periode-steuerung">
    <input type="hidden" name="anzeigen" value="1">

    <div class="ps-field">
      <label>Typ</label>
      <select name="typ" class="ks-input" onchange="toggleFelder(this.value)">
        <option value="monat"   <?= $typ === 'monat'   ? 'selected' : '' ?>>Monatsabschluss</option>
        <option value="quartal" <?= $typ === 'quartal' ? 'selected' : '' ?>>Quartalsabschluss</option>
      </select>
    </div>

    <div class="ps-field">
      <label>Jahr</label>
      <select name="jahr" class="ks-input">
        <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?>
        <option value="<?= $y ?>" <?= $y === $jahr ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="ps-field" id="feld-monat" <?= $typ === 'quartal' ? 'style="display:none"' : '' ?>>
      <label>Monat</label>
      <select name="monat" class="ks-input">
        <?php
        $monate = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        foreach ($monate as $i => $mn):
          $m = $i + 1;
        ?>
        <option value="<?= $m ?>" <?= $m === $monat ? 'selected' : '' ?>><?= $mn ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="ps-field" id="feld-quartal" <?= $typ === 'monat' ? 'style="display:none"' : '' ?>>
      <label>Quartal</label>
      <select name="quartal" class="ks-input">
        <option value="1" <?= $quartal === 1 ? 'selected' : '' ?>>Q1 (Jan–Mär)</option>
        <option value="2" <?= $quartal === 2 ? 'selected' : '' ?>>Q2 (Apr–Jun)</option>
        <option value="3" <?= $quartal === 3 ? 'selected' : '' ?>>Q3 (Jul–Sep)</option>
        <option value="4" <?= $quartal === 4 ? 'selected' : '' ?>>Q4 (Okt–Dez)</option>
      </select>
    </div>

    <?php if (count($kassen) > 1): ?>
    <div class="ps-field">
      <label>Kasse</label>
      <select name="kasse_id" class="ks-input">
        <?php foreach ($kassen as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kasseId ? 'selected' : '' ?>>
          <?= esc_($k['kasse_nr']) ?> — <?= esc_($k['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php else: ?>
    <input type="hidden" name="kasse_id" value="<?= $kasseId ?>">
    <?php endif; ?>

    <div class="ps-field">
      <label>&nbsp;</label>
      <button type="submit" class="ks-btn ks-btn-primary">Auswerten</button>
    </div>
  </form>

  <!-- BERICHT -->
  <?php if ($anzeigen && $daten !== null): ?>

  <?php if ((int)$daten['anzahl_bons'] === 0): ?>
    <div class="periode-bericht">
      <div class="leer-hinweis">📭 Keine Verkaufsbons im Zeitraum <?= esc_($von) ?> – <?= esc_($bis) ?></div>
    </div>
  <?php else: ?>

  <div class="aktions-leiste">
    <button class="al-btn al-prim" onclick="window.print()">🖨 A4 drucken / PDF speichern</button>
    <button class="al-btn al-gruen" onclick="mailSenden()">📧 Per E-Mail senden</button>
    <a class="al-btn al-sec" href="abschluss_liste.php">📋 Tagesabschluss-Archiv</a>
  </div>

  <?php
    $kz         = $daten;
    $st         = $daten['steuer']           ?? [];
    $ag         = $daten['artikel_gruppen']  ?? [];
    $zBons      = $daten['z_bons']           ?? [];
    $umsatzBar  = (float)$kz['umsatz_bar']   + (float)$kz['umsatz_kombi_bar'];
    $umsatzKarte= (float)$kz['umsatz_karte'] + (float)$kz['umsatz_kombi_karte'];
    $umsatzGes  = (float)$kz['umsatz_gesamt'];
    $einlagen   = (float)$kz['einlagen'];
    $entnahmen  = abs((float)$kz['entnahmen']);
    $nettoBetrag= $steuerGes = 0;
    foreach ($st as $s) { $nettoBetrag += (float)$s['netto']; $steuerGes += (float)$s['steuer']; }
    $typLabel = ($typ === 'monat' ? 'MONATSABSCHLUSS' : 'QUARTALSABSCHLUSS') . ' — ' . strtoupper($periodLabel);
  ?>

  <div class="periode-bericht">

    <!-- KOPF -->
    <div class="periode-kopf">
      <div>
        <div class="pk-firma"><?= esc_($konfig['firmenname'] ?? 'MeaLana') ?></div>
        <?php $ort = trim(($konfig['firma_plz'] ?? '') . ' ' . ($konfig['firma_ort'] ?? '')); ?>
        <?php if ($ort): ?><div class="pk-sub"><?= esc_($ort) ?></div><?php endif; ?>
        <?php if (!empty($konfig['firma_uid'])): ?><div class="pk-sub">UID: <?= esc_($konfig['firma_uid']) ?></div><?php endif; ?>
      </div>
      <div>
        <div class="pk-typ"><?= $typLabel ?></div>
        <div class="pk-periode">Zeitraum: <?= date('d.m.Y', strtotime($von)) ?> – <?= date('d.m.Y', strtotime($bis)) ?></div>
        <div class="pk-info">Kasse: <?= esc_($kasseInfo['kasse_nr'] ?? '') ?> — <?= esc_($kasseInfo['name'] ?? '') ?></div>
        <div class="pk-info">Erstellt: <?= date('d.m.Y H:i') ?> Uhr</div>
        <?php if ($kz['bon_nr_von']): ?>
        <div class="pk-info">Bons: <?= esc_($kz['bon_nr_von']) ?> – <?= esc_($kz['bon_nr_bis']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- KACHELN -->
    <div class="kacheln4">
      <div class="kachel lila">
        <div class="kachel-label">Umsatz gesamt</div>
        <div class="kachel-wert"><?= eur($umsatzGes) ?></div>
        <div class="kachel-sub"><?= (int)$kz['anzahl_bons'] ?> Bons · <?= (int)$kz['anzahl_z_bons'] ?> Z-Bons</div>
      </div>
      <div class="kachel gruen">
        <div class="kachel-label">Bar (inkl. Kombi)</div>
        <div class="kachel-wert"><?= eur($umsatzBar) ?></div>
      </div>
      <div class="kachel amber">
        <div class="kachel-label">Karte (inkl. Kombi)</div>
        <div class="kachel-wert"><?= eur($umsatzKarte) ?></div>
      </div>
      <div class="kachel blau">
        <div class="kachel-label">Gutschein</div>
        <div class="kachel-wert"><?= eur((float)$kz['umsatz_gs']) ?></div>
        <?php if ((int)$kz['anzahl_stornos'] > 0): ?>
        <div class="kachel-sub" style="color:#dc2626"><?= (int)$kz['anzahl_stornos'] ?> Stornis</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ZAHLUNGSARTEN -->
    <div class="sek-title">Umsatz nach Zahlungsart</div>
    <table class="per-table">
      <thead><tr><th>Zahlungsart</th><th class="r">Betrag</th></tr></thead>
      <tbody>
        <tr><td>Bar</td><td class="r"><?= eur((float)$kz['umsatz_bar']) ?></td></tr>
        <tr><td>Karte (extern)</td><td class="r"><?= eur((float)$kz['umsatz_karte']) ?></td></tr>
        <tr><td>Gutschein</td><td class="r"><?= eur((float)$kz['umsatz_gs']) ?></td></tr>
        <tr><td>Kombizahlung — Bar-Anteil</td><td class="r"><?= eur((float)$kz['umsatz_kombi_bar']) ?></td></tr>
        <tr><td>Kombizahlung — Karte-Anteil</td><td class="r"><?= eur((float)$kz['umsatz_kombi_karte']) ?></td></tr>
        <?php if ((int)$kz['anzahl_stornos'] > 0): ?>
        <tr style="color:#dc2626"><td>davon storniert (<?= (int)$kz['anzahl_stornos'] ?> Bon/s)</td><td class="r">−<?= eur(abs((float)$kz['storniert_betrag'])) ?></td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot><tr><td>Gesamt</td><td class="r"><?= eur($umsatzGes) ?></td></tr></tfoot>
    </table>

    <!-- STEUERAUFSTELLUNG -->
    <?php if (!empty($st)): ?>
    <div class="sek-title">Steueraufstellung</div>
    <table class="per-table">
      <thead><tr><th>Steuersatz</th><th class="r">Netto</th><th class="r">USt</th><th class="r">Brutto</th></tr></thead>
      <tbody>
        <?php foreach ($st as $s): ?>
        <tr>
          <td><?= number_format((float)$s['satz'], 0) ?> %</td>
          <td class="r"><?= eur((float)$s['netto']) ?></td>
          <td class="r"><?= eur((float)$s['steuer']) ?></td>
          <td class="r"><?= eur((float)$s['brutto']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td>Summe</td>
          <td class="r"><?= eur($nettoBetrag) ?></td>
          <td class="r"><?= eur($steuerGes) ?></td>
          <td class="r"><?= eur($nettoBetrag + $steuerGes) ?></td>
        </tr>
      </tfoot>
    </table>
    <?php endif; ?>

    <!-- ARTIKELGRUPPEN-UMSÄTZE -->
    <?php if (!empty($ag)): ?>
    <div class="sek-title">Umsatz nach Artikelgruppe</div>
    <table class="per-table">
      <thead>
        <tr><th>Konto</th><th>Gruppe</th><th class="r">USt %</th><th class="r">Netto</th><th class="r">USt</th><th class="r">Brutto</th></tr>
      </thead>
      <tbody>
        <?php
        $agGesNetto = $agGesSteuer = $agGesBrutto = 0;
        $letzteGruppe = null;
        foreach ($ag as $g):
            $agGesNetto  += $g['netto'];
            $agGesSteuer += $g['steuer'];
            $agGesBrutto += $g['brutto'];
            $sep = $letzteGruppe !== null && $letzteGruppe !== $g['konto_nr'];
        ?>
        <tr <?= $sep ? 'style="border-top:1px solid #cbd5e1"' : '' ?>>
          <td><code style="font-size:9pt"><?= htmlspecialchars($g['konto_nr']) ?></code></td>
          <td><?= htmlspecialchars($g['gruppe_name']) ?></td>
          <td class="r"><?= number_format($g['satz'], 0) ?> %</td>
          <td class="r"><?= eur($g['netto']) ?></td>
          <td class="r"><?= eur($g['steuer']) ?></td>
          <td class="r"><?= eur($g['brutto']) ?></td>
        </tr>
        <?php $letzteGruppe = $g['konto_nr']; endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="3">Summe</td><td class="r"><?= eur($agGesNetto) ?></td><td class="r"><?= eur($agGesSteuer) ?></td><td class="r"><?= eur($agGesBrutto) ?></td></tr>
      </tfoot>
    </table>
    <?php endif; ?>

    <!-- KASSENBUCH-BEWEGUNGEN -->
    <?php if ($einlagen > 0 || $entnahmen > 0): ?>
    <div class="sek-title">Bargeld-Bewegungen im Zeitraum</div>
    <table class="per-table">
      <thead><tr><th>Typ</th><th class="r">Betrag</th></tr></thead>
      <tbody>
        <?php if ($einlagen > 0): ?><tr><td>Einlagen gesamt</td><td class="r" style="color:#16a34a"><?= eur($einlagen) ?></td></tr><?php endif; ?>
        <?php if ($entnahmen > 0): ?><tr><td>Entnahmen gesamt</td><td class="r" style="color:#dc2626">−<?= eur($entnahmen) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <!-- Z-BONS ÜBERSICHT -->
    <?php if (!empty($zBons)): ?>
    <div class="sek-title">Tagesabschlüsse (Z-Bons) im Zeitraum</div>
    <table class="zbons-table">
      <thead>
        <tr>
          <th>Bon-Nr.</th>
          <th>Datum</th>
          <th class="r">Kassenstand</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($zBons as $zb): ?>
        <tr>
          <td style="font-weight:700"><?= esc_($zb['bon_nr'] ?? '—') ?></td>
          <td><?= date('d.m.Y', strtotime($zb['datum'])) ?></td>
          <td class="r"><?= eur((float)($zb['kassenstand'] ?? 0)) ?></td>
          <td style="text-align:right">
            <a href="abschluss_druck.php?id=<?= (int)$zb['id'] ?>" target="_blank"
               style="font-size:11px;color:#1e3a5f;text-decoration:none">🔗 Detail</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <!-- FUSSZEILE -->
    <div class="fusszeile">
      <span><?= esc_($konfig['firmenname'] ?? '') ?><?= !empty($konfig['firma_uid']) ? ' · UID: ' . esc_($konfig['firma_uid']) : '' ?></span>
      <span><?= $typLabel ?> · Erstellt <?= date('d.m.Y H:i') ?></span>
    </div>

  </div><!-- /periode-bericht -->

  <?php endif; // anzahl_bons > 0 ?>
  <?php endif; // anzeigen ?>

</div>

<script>
function toggleFelder(typ) {
    document.getElementById('feld-monat').style.display   = (typ === 'monat')   ? '' : 'none';
    document.getElementById('feld-quartal').style.display = (typ === 'quartal') ? '' : 'none';
}

function mailSenden() {
    if (!confirm('Perioden-Auswertung als PDF per E-Mail senden?')) return;
    var btn = event.target;
    btn.disabled = true; btn.textContent = '⏳ Wird gesendet…';
    fetch('abschluss_periode_mail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            typ:      '<?= $typ ?>',
            jahr:     <?= $jahr ?>,
            monat:    <?= $monat ?>,
            quartal:  <?= $quartal ?>,
            kasse_id: <?= $kasseId ?>
        })
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false; btn.textContent = '📧 Per E-Mail senden';
        if (d.erfolg) alert('✓ Erfolgreich gesendet an: ' + (d.empfaenger || '—'));
        else alert('Fehler: ' + (d.fehler || 'Unbekannt'));
    })
    .catch(() => { btn.disabled = false; btn.textContent = '📧 Per E-Mail senden'; alert('Verbindungsfehler'); });
}
</script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
