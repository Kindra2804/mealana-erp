<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$id      = (int)($_GET['id'] ?? 0);
$service = new KassenService();
$a       = $service->getAbschluss($id);

if (!$a) die('<p style="font-family:sans-serif;padding:20px">Abschluss nicht gefunden.</p>');

$db     = Database::getInstance();
$keys   = ['firmenname','firma_strasse','firma_plz','firma_ort','firma_uid','firma_email','firma_iban','firma_bic','firma_bank'];
$konfig = $db->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel IN ('" . implode("','", $keys) . "')")->fetchAll(\PDO::FETCH_KEY_PAIR);

$daten = $a['daten'];
$kz    = $daten['kennzahlen'];
$st    = $daten['steuer']           ?? [];
$ag    = $daten['artikel_gruppen']  ?? [];

$istZ       = $a['typ'] === 'z';
$typLabel   = $istZ ? 'Z-BON — TAGESABSCHLUSS' : 'X-BON — ZWISCHENABSCHLUSS';
$datum      = date('d.m.Y', strtotime($a['datum']));
$erstelltAm = date('d.m.Y H:i', strtotime($a['erstellt_am']));

$umsatzBar    = (float)$kz['umsatz_bar']         + (float)$kz['umsatz_kombi_bar'];
$umsatzKarte  = (float)$kz['umsatz_karte']        + (float)$kz['umsatz_kombi_karte'];
$umsatzGs     = (float)$kz['umsatz_gs'];
$umsatzGes    = (float)$kz['umsatz_gesamt'];
$einlagen     = (float)$kz['einlagen'];
$entnahmen    = abs((float)$kz['entnahmen']);

$nettoBetrag  = 0;
$steuerGes    = 0;
foreach ($st as $s) { $nettoBetrag += $s['netto']; $steuerGes += $s['steuer']; }

function eur(float $v): string { return '€ ' . number_format($v, 2, ',', '.'); }
function esc(string $v): string { return htmlspecialchars($v); }
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= $typLabel ?> — <?= $datum ?></title>
  <style>
    @page { size: A4 portrait; margin: 14mm 18mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; color: #1e293b; background: #fff; }

    @media screen {
      body { max-width: 210mm; margin: 20px auto; padding: 18mm; background: #fff; box-shadow: 0 2px 16px rgba(0,0,0,.12); min-height: 297mm; }
      .aktions-leiste { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
      .al-btn { padding: 10px 22px; border: none; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; text-decoration: none; }
      .al-prim  { background: #1e3a5f; color: #fff; }
      .al-gruen { background: #16a34a; color: #fff; }
      .al-sec   { background: #e2e8f0; color: #1e293b; }
    }
    @media print { .aktions-leiste { display: none !important; } }

    .kopf { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10mm; border-bottom: 3px solid #1e3a5f; padding-bottom: 6mm; }
    .firma-name { font-size: 17pt; font-weight: 800; color: #1e3a5f; }
    .firma-zeile { font-size: 9pt; color: #64748b; margin-top: 2px; }
    .dok-typ  { font-size: 14pt; font-weight: 800; color: <?= $istZ ? '#dc2626' : '#1e3a5f' ?>; letter-spacing: .3px; text-align: right; }
    .dok-bon  { font-size: 11pt; font-weight: 700; text-align: right; margin-top: 3px; }
    .dok-info { font-size: 9pt; color: #64748b; text-align: right; margin-top: 2px; }

    .kacheln { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 8mm; }
    .kachel { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; text-align: center; }
    .kachel-label { font-size: 8pt; color: #64748b; margin-bottom: 4px; }
    .kachel-wert  { font-size: 14pt; font-weight: 800; }
    .kachel-sub   { font-size: 8pt; color: #94a3b8; margin-top: 2px; }
    .kachel.gruen .kachel-wert { color: #16a34a; }
    .kachel.blau  .kachel-wert { color: #2563eb; }
    .kachel.amber .kachel-wert { color: #d97706; }
    .kachel.rot   .kachel-wert { color: #dc2626; }

    h2 { font-size: 10pt; font-weight: 700; color: #1e3a5f; letter-spacing: .5px; margin: 6mm 0 3mm; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
    thead th { background: #1e3a5f; color: #fff; font-size: 9pt; font-weight: 700; padding: 5px 8px; text-align: left; }
    thead th.r { text-align: right; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td { padding: 5px 8px; font-size: 9.5pt; border-bottom: 1px solid #f1f5f9; }
    tbody td.r { text-align: right; white-space: nowrap; }
    tfoot td { padding: 6px 8px; font-weight: 700; font-size: 10pt; border-top: 2px solid #1e3a5f; }
    tfoot td.r { text-align: right; }

    .kassenstand-box {
      display: flex; justify-content: space-between; align-items: center;
      background: <?= $istZ ? '#fef2f2' : '#eff6ff' ?>; border: 2px solid <?= $istZ ? '#fca5a5' : '#93c5fd' ?>;
      border-radius: 8px; padding: 10px 16px; margin-bottom: 8mm;
    }
    .ks-label { font-size: 10pt; font-weight: 700; color: #1e293b; }
    .ks-wert  { font-size: 18pt; font-weight: 900; color: <?= $istZ ? '#dc2626' : '#1e3a5f' ?>; }

    .fusszeile { border-top: 1px solid #e2e8f0; margin-top: 8mm; padding-top: 5px; font-size: 7.5pt; color: #94a3b8; display: flex; justify-content: space-between; }

    <?php if ($istZ): ?>
    .z-banner { background: #fef2f2; border: 2px solid #fca5a5; border-radius: 8px; padding: 8px 14px; text-align: center; font-weight: 800; font-size: 12pt; color: #dc2626; letter-spacing: 1px; margin-bottom: 6mm; }
    <?php endif; ?>
  </style>
</head>
<body>

<div class="aktions-leiste">
  <button class="al-btn al-prim" onclick="window.print()">🖨 A4 drucken / PDF speichern</button>
  <button class="al-btn al-gruen" onclick="mailSenden()">📧 Per E-Mail senden</button>
  <a class="al-btn al-sec" href="kassensturz.php">← Kassenstand</a>
  <a class="al-btn al-sec" href="abschluss_liste.php">📋 Abschluss-Archiv</a>
</div>

<?php if ($istZ): ?>
<div class="z-banner">🔒 TAGESABSCHLUSS — TAG ABGESCHLOSSEN</div>
<?php endif; ?>

<!-- KOPF -->
<div class="kopf">
  <div>
    <div class="firma-name"><?= esc($konfig['firmenname'] ?? 'MeaLana') ?></div>
    <?php foreach (['firma_strasse', 'plz_ort', 'firma_uid'] as $k):
        if ($k === 'plz_ort') {
            $z = trim(($konfig['firma_plz'] ?? '') . ' ' . ($konfig['firma_ort'] ?? ''));
        } elseif (isset($konfig[$k]) && $konfig[$k] !== '') {
            $z = ($k === 'firma_uid' ? 'UID: ' : '') . $konfig[$k];
        } else { continue; }
        if (!$z) continue;
    ?>
    <div class="firma-zeile"><?= esc($z) ?></div>
    <?php endforeach; ?>
  </div>
  <div>
    <div class="dok-typ"><?= $typLabel ?></div>
    <div class="dok-bon"><?= esc($a['bon_nr']) ?></div>
    <div class="dok-info">Kasse: <?= esc($a['kasse_nr'] ?? '') ?> — <?= esc($a['kasse_name'] ?? '') ?></div>
    <div class="dok-info">Datum: <?= $datum ?></div>
    <div class="dok-info">Erstellt: <?= $erstelltAm ?> Uhr</div>
    <div class="dok-info">Kassierer: <?= esc($a['kassierer_name'] ?? '—') ?></div>
    <?php if ($daten['bon_nr_von'] ?? null): ?>
    <div class="dok-info">Bons: <?= esc($daten['bon_nr_von']) ?> – <?= esc($daten['bon_nr_bis']) ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- KACHELN -->
<div class="kacheln">
  <div class="kachel blau">
    <div class="kachel-label">Umsatz gesamt</div>
    <div class="kachel-wert"><?= eur($umsatzGes) ?></div>
    <div class="kachel-sub"><?= (int)$kz['anzahl_bons'] ?> Bons</div>
  </div>
  <div class="kachel gruen">
    <div class="kachel-label">Bar (inkl. Kombi)</div>
    <div class="kachel-wert"><?= eur($umsatzBar) ?></div>
  </div>
  <div class="kachel amber">
    <div class="kachel-label">Karte (inkl. Kombi)</div>
    <div class="kachel-wert"><?= eur($umsatzKarte) ?></div>
  </div>
</div>

<!-- KASSENSTAND -->
<div class="kassenstand-box">
  <div class="ks-label"><?= $istZ ? '🔒 Kassenstand bei Tagesabschluss' : 'Kassenstand (Zwischenstand)' ?></div>
  <div class="ks-wert"><?= eur((float)$a['kassenstand']) ?></div>
</div>

<!-- ZAHLUNGSARTEN -->
<h2>Umsatz nach Zahlungsart</h2>
<table>
  <thead><tr><th>Zahlungsart</th><th class="r">Betrag</th></tr></thead>
  <tbody>
    <tr><td>Bar</td><td class="r"><?= eur((float)$kz['umsatz_bar']) ?></td></tr>
    <tr><td>Karte (extern)</td><td class="r"><?= eur((float)$kz['umsatz_karte']) ?></td></tr>
    <tr><td>Gutschein</td><td class="r"><?= eur($umsatzGs) ?></td></tr>
    <tr><td>Kombizahlung — Bar-Anteil</td><td class="r"><?= eur((float)$kz['umsatz_kombi_bar']) ?></td></tr>
    <tr><td>Kombizahlung — Karte-Anteil</td><td class="r"><?= eur((float)$kz['umsatz_kombi_karte']) ?></td></tr>
  </tbody>
  <tfoot>
    <tr>
      <td>Gesamt (netto Stornos)</td>
      <td class="r"><?= eur($umsatzGes) ?></td>
    </tr>
    <?php if ((int)$kz['anzahl_stornos'] > 0): ?>
    <tr style="font-size:9pt;color:#dc2626">
      <td>davon storniert (<?= (int)$kz['anzahl_stornos'] ?> Bon/s)</td>
      <td class="r"><?= eur(abs((float)$kz['storniert_betrag'])) ?></td>
    </tr>
    <?php endif; ?>
  </tfoot>
</table>

<!-- STEUERAUFSTELLUNG -->
<?php if (!empty($st)): ?>
<h2>Steueraufstellung</h2>
<table>
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
<h2>Umsatz nach Artikelgruppe</h2>
<table>
  <thead>
    <tr>
      <th>Konto</th>
      <th>Gruppe</th>
      <th class="r">USt %</th>
      <th class="r">Netto</th>
      <th class="r">USt</th>
      <th class="r">Brutto</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $agGesNetto  = 0;
    $agGesSteuer = 0;
    $agGesBrutto = 0;
    $letzteGruppe = null;
    foreach ($ag as $g):
        $agGesNetto  += $g['netto'];
        $agGesSteuer += $g['steuer'];
        $agGesBrutto += $g['brutto'];
        $gruppeWechsel = $letzteGruppe !== null && $letzteGruppe !== $g['konto_nr'];
    ?>
    <tr <?= $gruppeWechsel ? 'style="border-top:1px solid #cbd5e1"' : '' ?>>
      <td><code style="font-size:9pt"><?= esc($g['konto_nr']) ?></code></td>
      <td><?= esc($g['gruppe_name']) ?></td>
      <td class="r"><?= number_format($g['satz'], 0) ?> %</td>
      <td class="r"><?= eur($g['netto']) ?></td>
      <td class="r"><?= eur($g['steuer']) ?></td>
      <td class="r"><?= eur($g['brutto']) ?></td>
    </tr>
    <?php $letzteGruppe = $g['konto_nr']; endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3">Summe</td>
      <td class="r"><?= eur($agGesNetto) ?></td>
      <td class="r"><?= eur($agGesSteuer) ?></td>
      <td class="r"><?= eur($agGesBrutto) ?></td>
    </tr>
  </tfoot>
</table>
<?php endif; ?>

<!-- KASSENBUCH-BEWEGUNGEN -->
<?php if ($einlagen > 0 || $entnahmen > 0): ?>
<h2>Bargeld-Bewegungen</h2>
<table>
  <thead><tr><th>Typ</th><th class="r">Betrag</th></tr></thead>
  <tbody>
    <?php if ($einlagen > 0): ?><tr><td>Einlagen</td><td class="r" style="color:#16a34a"><?= eur($einlagen) ?></td></tr><?php endif; ?>
    <?php if ($entnahmen > 0): ?><tr><td>Entnahmen</td><td class="r" style="color:#dc2626">−<?= eur($entnahmen) ?></td></tr><?php endif; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- FUSSZEILE -->
<div class="fusszeile">
  <span><?= esc($konfig['firmenname'] ?? '') ?><?= !empty($konfig['firma_uid']) ? ' · UID: ' . esc($konfig['firma_uid']) : '' ?></span>
  <span><?= $typLabel ?> · <?= $datum ?> · <?= esc($a['bon_nr']) ?></span>
</div>

<script>
var ABSCHLUSS_ID = <?= $id ?>;
function mailSenden() {
    if (!confirm('Abschluss-PDF per E-Mail senden?')) return;
    var btn = event.target;
    btn.disabled = true; btn.textContent = '⏳ Wird gesendet…';
    fetch('/mealana/kasse/abschluss_mail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: ABSCHLUSS_ID })
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
</body>
</html>
