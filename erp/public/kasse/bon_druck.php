<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/QrCode.php';

$bonId   = (int)($_GET['id'] ?? 0);
$service = new KassenService();
$bon     = $service->getBon($bonId);

if (!$bon) {
    die('<p style="font-family:sans-serif;padding:20px">Bon nicht gefunden.</p>');
}

$db = Database::getInstance();
$konfig = $db->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel IN ('firmenname','firma_email','firma_strasse','firma_ort','firma_uid')")->fetchAll(PDO::FETCH_KEY_PAIR);
$firmenname = $konfig['firmenname'] ?? 'MeaLana';
$firmaUid   = $konfig['firma_uid'] ?? '';

// Steuer-Totale berechnen (signed: Retour-Positionen reduzieren, Storno ignoriert Vorzeichen via $stSign)
$steuerTotale = [];
$nettoBetrag  = 0;
foreach ($bon['positionen'] as $p) {
    $rohmenge   = (float)$p['menge'];
    $menge      = ($p['block'] === 'retour') ? $rohmenge : abs($rohmenge); // Retour signed, sonst abs
    $brutto     = $menge * (float)$p['einzelpreis_brutto'] * (1 - (float)$p['rabatt_prozent'] / 100);
    $satz       = (float)$p['steuer_prozent'];
    $netto      = $satz > 0 ? $brutto / (1 + $satz / 100) : $brutto;
    $steuer     = $brutto - $netto;
    $key        = number_format($satz, 0);
    if (!isset($steuerTotale[$key])) $steuerTotale[$key] = ['satz' => $satz, 'netto' => 0, 'steuer' => 0, 'brutto' => 0];
    $steuerTotale[$key]['netto']  += $netto;
    $steuerTotale[$key]['steuer'] += $steuer;
    $steuerTotale[$key]['brutto'] += $brutto;
    $nettoBetrag += $netto;
}

$bonBrutto    = (float)$bon['bruttobetrag'];
$istRetour    = $bonBrutto < -0.005;
$bruttoBetrag = abs($bonBrutto);
$zahlungsartLabel = [
    'bar'          => 'Bar',
    'karte_extern' => 'Karte (extern)',
    'gutschein'    => 'Gutschein',
    'kombi'        => 'Bar + Karte',
][$bon['zahlungsart']] ?? $bon['zahlungsart'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Bon <?= htmlspecialchars($bon['bon_nr']) ?></title>
  <style>
    @page { margin: 3mm; size: 80mm auto; }
    * { box-sizing: border-box; }
    body {
      font-family: 'Courier New', monospace;
      font-size: 12px;
      width: 72mm;
      margin: 0 auto;
      padding: 2mm;
      color: #000;
    }
    .zentriert { text-align: center; }
    .rechts    { text-align: right; }
    .fett      { font-weight: bold; }
    .linie        { border-top: 1px dashed #000; margin: 3px 0; }
    .linie-doppelt{ border-top: 2px double #000; margin: 3px 0; }
    .pos-zeile { display: flex; justify-content: space-between; margin: 1px 0; }
    .pos-sub   { font-size: 10px; color: #444; padding-left: 4px; }
    .storno-kopf { border: 2px solid #000; padding: 3px; text-align: center; font-weight: bold; margin-bottom: 6px; }
    @media screen {
      body { background: #f5f5f5; border: 1px solid #ccc; padding: 8mm; margin: 20px auto; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
      .druck-btn {
        display: block; background: #333; color: #fff; border: none;
        padding: 10px 24px; cursor: pointer; font-size: 14px; border-radius: 6px;
        margin: 16px auto; text-align: center; width: fit-content;
      }
    }
    @media print { .druck-btn { display: none; } }
  </style>
</head>
<body>

<?php if ($bon['typ'] === 'storno'): ?>
<div class="storno-kopf">★ STORNO-BON ★</div>
<?php endif; ?>

<!-- Firmenkopf -->
<div class="zentriert fett" style="font-size:14px"><?= htmlspecialchars($firmenname) ?></div>
<?php if (!empty($konfig['firma_strasse'])): ?>
<div class="zentriert"><?= htmlspecialchars($konfig['firma_strasse']) ?></div>
<?php endif; ?>
<?php if (!empty($konfig['firma_ort'])): ?>
<div class="zentriert"><?= htmlspecialchars($konfig['firma_ort']) ?></div>
<?php endif; ?>
<?php if ($firmaUid): ?>
<div class="zentriert">UID: <?= htmlspecialchars($firmaUid) ?></div>
<?php endif; ?>

<div class="linie"></div>

<div class="pos-zeile">
  <span>Bon-Nr.:</span>
  <span class="fett"><?= htmlspecialchars($bon['bon_nr']) ?></span>
</div>
<div class="pos-zeile">
  <span>Datum:</span>
  <span><?= date('d.m.Y H:i', strtotime($bon['erstellt_am'])) ?></span>
</div>
<div class="pos-zeile">
  <span>Kasse:</span>
  <span><?= htmlspecialchars($bon['kasse_nr'] ?? 'K1') ?></span>
</div>
<?php if ($bon['kunden_id']): ?>
<div class="pos-zeile">
  <span>Kd.-ID:</span>
  <span><?= (int)$bon['kunden_id'] ?></span>
</div>
<?php endif; ?>

<div class="linie"></div>

<?php
// Positionen nach Block gruppieren
$posBlocks = [];
foreach ($bon['positionen'] as $pos) {
    $posBlocks[$pos['block'] ?? 'normal'][] = $pos;
}
$stSign    = $bon['typ'] === 'storno' ? '-' : '';
$hatAuftrag = isset($posBlocks['auftrag']);
$hatRest    = isset($posBlocks['normal']) || isset($posBlocks['addon']);
?>

<?php if ($hatAuftrag): ?>
<div class="linie-doppelt"></div>
<div class="fett" style="font-size:11px;margin:2px 0"><?= htmlspecialchars($bon['web_auftrag_nr'] ?? 'Auftrag') ?></div>
<?php foreach ($posBlocks['auftrag'] as $pos):
    $menge  = (float)$pos['menge'];
    $preis  = (float)$pos['einzelpreis_brutto'];
    $rabatt = (float)$pos['rabatt_prozent'];
    $gesamt = $menge * $preis * (1 - $rabatt / 100);
?>
<div class="pos-zeile">
  <span><?= htmlspecialchars(mb_substr($pos['bezeichnung'], 0, 28)) ?></span>
  <span class="fett"><?= $stSign ?>€ <?= number_format(abs($gesamt), 2, ',', '.') ?></span>
</div>
<?php if (abs($menge) != 1 || $rabatt > 0): ?>
<div class="pos-sub">
  <?= abs($menge) ?>× €<?= number_format(abs($preis), 2, ',', '.') ?>
  <?= $rabatt > 0 ? ' -' . number_format($rabatt, 0) . '%' : '' ?>
  · <?= number_format((float)$pos['steuer_prozent'], 0) ?>% MwSt
  <?= $pos['charge'] ? ' · Partie: ' . htmlspecialchars($pos['charge']) : '' ?>
</div>
<?php else: ?>
<div class="pos-sub"><?= number_format((float)$pos['steuer_prozent'], 0) ?>% MwSt<?= $pos['charge'] ? ' · Partie: ' . htmlspecialchars($pos['charge']) : '' ?></div>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

<?php
// Retour-Positionen (zurückgegebene Ware aus bereits bezahltem Auftrag)
$retourPositionen = $posBlocks['retour'] ?? [];
if (!empty($retourPositionen)):
?>
<div class="linie-doppelt"></div>
<div class="fett" style="font-size:11px;margin:2px 0">↩ RÜCKGABE</div>
<?php foreach ($retourPositionen as $pos):
    $menge  = abs((float)$pos['menge']);
    $preis  = (float)$pos['einzelpreis_brutto'];
    $rabatt = (float)$pos['rabatt_prozent'];
    $gesamt = $menge * $preis * (1 - $rabatt / 100);
?>
<div class="pos-zeile">
  <span><?= htmlspecialchars(mb_substr($pos['bezeichnung'], 0, 28)) ?></span>
  <span class="fett">-€ <?= number_format($gesamt, 2, ',', '.') ?></span>
</div>
<?php if ($menge != 1 || $rabatt > 0): ?>
<div class="pos-sub">
  <?= $menge ?>× <?= number_format($preis, 2, ',', '.') ?>€
  <?= $rabatt > 0 ? ' -' . number_format($rabatt, 0) . '%' : '' ?>
  · <?= number_format((float)$pos['steuer_prozent'], 0) ?>% MwSt
</div>
<?php else: ?>
<div class="pos-sub"><?= number_format((float)$pos['steuer_prozent'], 0) ?>% MwSt</div>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

<?php
// Restliche Positionen (normal + addon)
$restPositionen = array_merge($posBlocks['normal'] ?? [], $posBlocks['addon'] ?? []);
if (!empty($restPositionen)):
?>
<?php if ($hatAuftrag || !empty($retourPositionen)): ?>
<div class="linie-doppelt"></div>
<?php endif; ?>
<?php foreach ($restPositionen as $pos):
    $menge  = (float)$pos['menge'];
    $preis  = (float)$pos['einzelpreis_brutto'];
    $rabatt = (float)$pos['rabatt_prozent'];
    $gesamt = $menge * $preis * (1 - $rabatt / 100);
?>
<div class="pos-zeile">
  <span><?= htmlspecialchars(mb_substr($pos['bezeichnung'], 0, 28)) ?></span>
  <span class="fett"><?= $stSign ?>€ <?= number_format(abs($gesamt), 2, ',', '.') ?></span>
</div>
<?php if (abs($menge) != 1 || $rabatt > 0): ?>
<div class="pos-sub">
  <?= abs($menge) ?>× <?= number_format(abs($preis), 2, ',', '.') ?>€
  <?= $rabatt > 0 ? ' -' . number_format($rabatt, 0) . '%' : '' ?>
  · <?= number_format((float)$pos['steuer_prozent'], 0) ?>% MwSt
  <?= $pos['charge'] ? ' · Partie: ' . htmlspecialchars($pos['charge']) : '' ?>
</div>
<?php else: ?>
<div class="pos-sub"><?= number_format((float)$pos['steuer_prozent'], 0) ?>% MwSt<?= $pos['charge'] ? ' · Partie: ' . htmlspecialchars($pos['charge']) : '' ?></div>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

<div class="linie"></div>

<!-- Steuertabelle -->
<?php foreach ($steuerTotale as $k => $st): ?>
<div class="pos-zeile" style="font-size:10px">
  <span>Netto <?= $k ?>%</span>
  <span>€ <?= number_format($st['netto'], 2, ',', '.') ?></span>
</div>
<div class="pos-zeile" style="font-size:10px">
  <span>USt <?= $k ?>%</span>
  <span>€ <?= number_format($st['steuer'], 2, ',', '.') ?></span>
</div>
<?php endforeach; ?>

<div class="linie"></div>

<div class="pos-zeile fett" style="font-size:15px">
  <span><?= $istRetour ? 'RÜCKGABE' : 'GESAMT' ?></span>
  <span><?= $istRetour ? '-' : '' ?>€ <?= number_format($bruttoBetrag, 2, ',', '.') ?></span>
</div>

<div class="pos-zeile" style="margin-top:4px">
  <span>Zahlungsart:</span>
  <span><?= htmlspecialchars($zahlungsartLabel) ?></span>
</div>

<?php if ($bon['zahlungsart'] === 'bar'): ?>
  <?php if ($bon['gegeben'] !== null): ?>
  <div class="pos-zeile">
    <span>Gegeben:</span>
    <span>€ <?= number_format((float)$bon['gegeben'], 2, ',', '.') ?></span>
  </div>
  <div class="pos-zeile fett">
    <span>Rückgeld:</span>
    <span>€ <?= number_format((float)($bon['rueckgeld'] ?? 0), 2, ',', '.') ?></span>
  </div>
  <?php endif; ?>
<?php elseif ($bon['zahlungsart'] === 'kombi'): ?>
  <div class="pos-zeile">
    <span>Karte:</span>
    <span>€ <?= number_format((float)$bon['karten_betrag'], 2, ',', '.') ?></span>
  </div>
  <div class="pos-zeile">
    <span>Bar:</span>
    <span>€ <?= number_format((float)$bon['bar_betrag'], 2, ',', '.') ?></span>
  </div>
  <?php if ($bon['rueckgeld'] > 0): ?>
  <div class="pos-zeile fett">
    <span>Rückgeld:</span>
    <span>€ <?= number_format((float)$bon['rueckgeld'], 2, ',', '.') ?></span>
  </div>
  <?php endif; ?>
<?php elseif ($bon['zahlungsart'] === 'gutschein'): ?>
  <div class="pos-zeile">
    <span>Code:</span>
    <span><?= htmlspecialchars($bon['gutschein_code'] ?? '') ?></span>
  </div>
<?php endif; ?>

<?php if ($bon['bfr_status'] === 'signiert'): ?>
<div class="linie"></div>
<div class="zentriert" style="font-size:10px">RKSV</div>
<?php if ($bon['rksv_qr']): ?>
<div class="zentriert" style="margin:4px 0"><img src="<?= QrCode::dataUri($bon['rksv_qr']) ?>" style="width:120px;height:120px"></div>
<?php endif; ?>
<div style="font-size:9px;word-break:break-all"><?= htmlspecialchars($bon['rksv_signatur']) ?></div>
<?php else: ?>
<div class="linie"></div>
<div class="zentriert" style="font-size:9px;font-weight:bold">Sicherheitseinrichtung ausgefallen</div>
<?php endif; ?>

<div class="linie"></div>
<div class="zentriert" style="font-size:11px">Danke für Ihren Einkauf!</div>
<div class="zentriert" style="font-size:9px;color:#666;margin-top:4px"><?= htmlspecialchars($firmenname) ?></div>

<button class="druck-btn" onclick="window.print()">🖨 Drucken (80mm)</button>

<script>
// Auto-Druck beim Öffnen, kurze Verzögerung damit Styles laden
window.addEventListener('load', function() { setTimeout(window.print, 400); });
</script>
</body>
</html>
