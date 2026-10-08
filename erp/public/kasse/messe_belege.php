<?php
/**
 * Papier-Messe: händische Belege einzeln nacherfassen (Einzelaufzeichnungspflicht).
 *
 * Jeder Beleg wird ein eigener, signierter Bon an DIESER Kasse (Arbeitsplatz),
 * Zeilen = Artikelgruppe + Betrag (+ Steuer aus der Gruppe, änderbar).
 * Logik: MesseSyncService::belegNacherfassen(), JS: js/kasse_messe_belege.js.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';
require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';

$aktuelleKasseId = (new ArbeitsplatzService())->aktuelleKasseId();
if ($aktuelleKasseId === null) {
    $_SESSION['fehler'] = 'Dieses Gerät ist keiner Kasse zugeordnet. Bitte zuerst einen Arbeitsplatz auswählen.';
    header('Location: ' . BASE_PATH . '/kasse/index.php');
    exit;
}

$kassenSvc = new KassenService();
$messeSvc  = new MesseSyncService();
$kasseInfo = $kassenSvc->getKasse($aktuelleKasseId);

$syncId = (int)($_GET['sync_id'] ?? 0);
$sync   = $messeSvc->getSyncById($syncId);
if (!$sync || $sync['variante'] !== 'papier') {
    $_SESSION['fehler'] = 'Papier-Messe nicht gefunden.';
    header('Location: ' . BASE_PATH . '/kasse/messe_rueckkehr.php');
    exit;
}

$belege  = $messeSvc->getNacherfassteBelege($syncId);
$gruppen = $kassenSvc->getKassenGruppen();
$wert    = $sync['rueckkehr_am'] !== null ? $messeSvc->getStrichlistenWert($syncId) : null;

$summeBar = $summeKarte = 0.0;
$letzterBeleg = null;
foreach ($belege as $b) {
    if ($b['storniert']) continue;
    if ($b['zahlungsart'] === 'bar') $summeBar += (float)$b['bruttobetrag'];
    else                             $summeKarte += (float)$b['bruttobetrag'];
    $letzterBeleg = $b;
}
$summeGesamt = round($summeBar + $summeKarte, 2);

// Vorschläge für den nächsten Beleg: Nr. +1 (wenn numerisch), Datum + Zahlart vom letzten
$naechsteNr = ($letzterBeleg && ctype_digit($letzterBeleg['handbeleg_nr'])) ? (string)((int)$letzterBeleg['handbeleg_nr'] + 1) : '';
$vorDatum   = $letzterBeleg['handbeleg_datum'] ?? date('Y-m-d', strtotime($sync['erstellt_am']));
$vorDatum   = min($vorDatum, date('Y-m-d'));
$vorZahlart = $letzterBeleg['zahlungsart'] ?? 'bar';

$signiert = !empty($kasseInfo['bfr_aktiv_seit']);
$fmt = fn($b) => number_format((float)$b, 2, ',', '.');

$pageTitle      = 'Messe-Belege nacherfassen';
$activeKasseNav = 'messe';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:1000px;margin:0 auto">

  <div class="ks-card">
    <div class="ks-card-title">📝 Messe-Nr. <?= $syncId ?> — Belege nacherfassen</div>
    <p style="font-size:13px;color:#475569;margin:0">
      Kasse: <strong><?= htmlspecialchars($kasseInfo['name'] ?? '') ?></strong>
      <?= $signiert
          ? '<span style="color:#16a34a">· signiert (RKSV)</span>'
          : '<span style="color:#d97706">· ⚠ diese Kasse signiert nicht — für die RKSV an der Signatur-Kasse nacherfassen</span>' ?>
      <br>Jeder händische Beleg wird ein eigener Bon mit Vermerk „Nacherfassung Messe-Beleg Nr. … vom …“.
      <strong>Bar-Belege erhöhen den Kassenstand dieser Kasse</strong> — Messe-Bargeld einlegen oder danach als Entnahme buchen.
    </p>
  </div>

  <div class="ks-card">
    <div class="ks-card-title">Beleg erfassen</div>
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px">
      <div>
        <label class="mb-label">Beleg-Nr.</label>
        <input type="text" id="mb-nr" class="ks-input" maxlength="30" style="width:110px" value="<?= htmlspecialchars($naechsteNr) ?>">
      </div>
      <div>
        <label class="mb-label">Belegdatum</label>
        <input type="date" id="mb-datum" class="ks-input" value="<?= htmlspecialchars($vorDatum) ?>" max="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label class="mb-label">Zahlart</label>
        <div class="mb-zahlart">
          <button type="button" data-za="bar" onclick="mbZahlart('bar')">💶 Bar</button>
          <button type="button" data-za="karte_extern" onclick="mbZahlart('karte_extern')">💳 Bankomat</button>
        </div>
      </div>
    </div>

    <table class="ks-table">
      <thead>
        <tr><th>Artikelgruppe</th><th style="width:140px;text-align:right">Betrag €</th><th style="width:110px">Steuer</th><th style="width:40px"></th></tr>
      </thead>
      <tbody id="mb-zeilen"></tbody>
    </table>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;gap:10px;flex-wrap:wrap">
      <button type="button" class="ks-btn ks-btn-secondary" onclick="mbZeile()">+ Zeile</button>
      <div style="font-size:18px">Summe: <strong id="mb-summe">0,00</strong> €</div>
      <button type="button" class="ks-btn ks-btn-primary ks-btn-lg" id="mb-btn" onclick="mbErfassen()">✓ Nacherfassen<?= $signiert ? ' + signieren' : '' ?></button>
    </div>
    <div id="mb-feedback" style="margin-top:10px"></div>
  </div>

  <div class="ks-card">
    <div class="ks-card-title" style="display:flex;justify-content:space-between;align-items:center">
      <span>Erfasste Belege (<?= count(array_filter($belege, fn($b) => !$b['storniert'])) ?>)</span>
      <a href="messe_abschluss.php?sync_id=<?= $syncId ?>" target="_blank" class="ks-btn ks-btn-secondary" style="padding:5px 12px;font-size:12px">🖨 Messe-Abschluss</a>
    </div>
    <?php if (!$belege): ?>
      <p style="color:#94a3b8;margin:0">Noch keine Belege erfasst.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead><tr><th>Beleg-Nr.</th><th>Datum</th><th>Zahlart</th><th style="text-align:right">Betrag</th><th>Bon</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($belege as $b): ?>
          <tr style="<?= $b['storniert'] ? 'opacity:.45;text-decoration:line-through' : '' ?>">
            <td><strong><?= htmlspecialchars($b['handbeleg_nr']) ?></strong></td>
            <td><?= date('d.m.Y', strtotime($b['handbeleg_datum'])) ?></td>
            <td><?= $b['zahlungsart'] === 'bar' ? 'Bar' : 'Bankomat' ?></td>
            <td style="text-align:right">€ <?= $fmt($b['bruttobetrag']) ?></td>
            <td style="font-size:12px"><?= htmlspecialchars($b['bon_nr']) ?> <?= $b['signiert'] ? '<span style="color:#16a34a">✓</span>' : '' ?></td>
            <td><a href="bon_druck.php?id=<?= (int)$b['id'] ?>" target="_blank" style="font-size:12px">Bon</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div class="mb-summen">
      <div>Bar <strong>€ <?= $fmt($summeBar) ?></strong></div>
      <div>Bankomat <strong>€ <?= $fmt($summeKarte) ?></strong></div>
      <div>Belege gesamt <strong>€ <?= $fmt($summeGesamt) ?></strong></div>
      <?php if ($wert): ?>
        <div>Strichliste <strong>€ <?= $fmt($wert['gesamt']) ?></strong></div>
        <?php $diff = round($summeGesamt - $wert['gesamt'], 2); ?>
        <div style="color:<?= abs($diff) < 0.005 ? '#16a34a' : '#d97706' ?>">Differenz <strong>€ <?= $fmt($diff) ?></strong></div>
      <?php else: ?>
        <div style="color:#94a3b8">Abgleich mit der Strichliste nach der Lager-Rückbuchung</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<style>
.mb-label { font-size: 12px; color: #64748b; display: block; margin-bottom: 4px; }
.mb-zahlart { display: flex; gap: 6px; }
.mb-zahlart button {
  padding: 8px 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; background: #f8fafc;
  font-size: 14px; font-weight: 600; cursor: pointer; font-family: inherit;
}
.mb-zahlart button.aktiv { background: #1e3a5f; border-color: #1e3a5f; color: #fff; }
.mb-summen { display: flex; gap: 22px; flex-wrap: wrap; margin-top: 12px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 14px; }
</style>

<script>
var MB_SYNC_ID = <?= $syncId ?>;
var MB_GRUPPEN = <?= json_encode($gruppen) ?>;
var MB_ZAHLART = <?= json_encode($vorZahlart) ?>;
</script>
<script src="<?= BASE_PATH ?>/js/kasse_messe_belege.js"></script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
