<?php
/**
 * Mobil-Messe (Sunmi): die vom Gerät hochgeladenen Belege an DIESER Kasse nacherfassen.
 *
 * Das Gerät erteilt nur Belege (Belegerteilungspflicht, ohne Signatur). Nach der Rückkehr wird jeder Beleg
 * ein eigener, signierter Bon mit den echten Artikeln/Chargen, Lagerabgang aus dem Messe-Lager, Vermerk
 * „Nacherfassung Messe-Beleg Nr. … vom …“. Logik: MesseSyncService::mobilBelegNacherfassen(),
 * JS: js/kasse_messe_mobil_belege.js (nacheinander, Stopp beim ersten Fehler).
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
$kasseInfo = (new KassenService())->getKasse($aktuelleKasseId);
$messeSvc  = new MesseSyncService();
$syncId    = (int)($_GET['sync_id'] ?? 0);
$sync      = $messeSvc->getSyncById($syncId);
if (!$sync || $sync['variante'] !== 'mobil') {
    $_SESSION['fehler'] = 'Mobil-Messe nicht gefunden.';
    header('Location: ' . BASE_PATH . '/kasse/messe_vorbereiten.php');
    exit;
}
$belege = $messeSvc->getMobilBelege($syncId);
$offen  = array_values(array_filter($belege, fn($b) => $b['bon_id'] === null));
$summe  = ['bar' => 0.0, 'karte_extern' => 0.0];
foreach ($belege as $b) {
    if (!$b['storniert']) $summe[$b['zahlungsart']] += (float)$b['bruttobetrag'];
}
$signiert = !empty($kasseInfo['bfr_aktiv_seit']);
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');

$pageTitle      = 'Mobil-Belege nacherfassen';
$activeKasseNav = 'messe';
require_once __DIR__ . '/shell_top.php';
?>
<div style="max-width:1000px;margin:0 auto">

  <div class="ks-card">
    <div class="ks-card-title">📱 Mobil-Messe Nr. <?= $syncId ?> — Belege nacherfassen</div>
    <p style="font-size:13px;color:#475569;margin:0 0 10px">
      Jeder Beleg vom Gerät wird hier ein eigener <strong>signierter Bon</strong> an der Kasse
      <strong><?= htmlspecialchars(($kasseInfo['name'] ?? '') . ' (' . ($kasseInfo['kasse_nr'] ?? '') . ')') ?></strong>,
      mit Vermerk „Nacherfassung Messe-Beleg Nr. … vom …“. Partnerware (Fremdrechnung/Spende) wird wie an der Laden-Kasse nicht signiert.
      Das Lager bucht „↩ Ware zurückbuchen“.
      Fehlen Belege, am Gerät „⤴ Belege hochladen“ drücken (doppelt hochladen schadet nicht).
    </p>
    <?php if (!$signiert): ?>
      <div class="ks-feedback info" style="margin-bottom:10px">Diese Kasse signiert nicht (keine RKSV-Registrierung) — die Bons werden ohne Signatur angelegt.
        Richtig nur, wenn der Betrieb keine Registrierkassenpflicht hat; sonst an der Signatur-Kasse nacherfassen.</div>
    <?php endif; ?>
    <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:14px;margin-bottom:12px">
      <div>Belege: <strong><?= count($belege) ?></strong> · offen: <strong id="mb-offen"><?= count($offen) ?></strong></div>
      <div>Bar: <strong>€ <?= $fmt($summe['bar']) ?></strong></div>
      <div>Karte: <strong>€ <?= $fmt($summe['karte_extern']) ?></strong></div>
      <div>Gesamt: <strong>€ <?= $fmt($summe['bar'] + $summe['karte_extern']) ?></strong></div>
    </div>
    <?php if ($offen): ?>
      <button class="ks-btn ks-btn-primary" id="mb-alle" onclick="mbAlleNacherfassen()">
        ✓ Alle <?= count($offen) ?> offenen Belege nacherfassen
      </button>
      <div id="mb-status" style="font-size:13px;margin-top:8px"></div>
    <?php endif; ?>
    <?php if ($sync['rueckkehr_am'] === null): ?>
      <div style="margin-top:12px;font-size:13px;color:#475569">
        Danach: <a href="messe_rueckkehr.php?sync_id=<?= $syncId ?>">↩ Ware zurückbuchen</a> (verkauft kommt aus diesen Belegen).
      </div>
    <?php else: ?>
      <div style="margin-top:12px;font-size:13px;color:#16a34a">Ware zurückgebucht am <?= date('d.m.Y H:i', strtotime($sync['rueckkehr_am'])) ?>.</div>
    <?php endif; ?>
  </div>

  <div class="ks-card">
    <table class="ks-table">
      <thead><tr><th>Beleg</th><th>Zeit (Gerät)</th><th>Positionen</th><th>Zahlart</th><th style="text-align:right">Betrag</th><th>Bon</th></tr></thead>
      <tbody>
      <?php if (!$belege): ?>
        <tr><td colspan="6" style="color:#888">Noch keine Belege hochgeladen.</td></tr>
      <?php endif; ?>
      <?php foreach ($belege as $b):
          $pos = json_decode((string)$b['daten'], true)['positionen'] ?? []; ?>
        <tr data-beleg-id="<?= (int)$b['id'] ?>" data-offen="<?= $b['bon_id'] === null ? 1 : 0 ?>">
          <td><strong><?= htmlspecialchars($b['beleg_nr']) ?></strong></td>
          <td style="color:#888;white-space:nowrap"><?= date('d.m.Y H:i', strtotime($b['beleg_zeit'])) ?></td>
          <td style="font-size:12px"><?= htmlspecialchars(implode(', ', array_map(fn($p) => rtrim(rtrim((string)$p['menge'], '0'), '.') . '× ' . $p['bezeichnung'], $pos))) ?></td>
          <td><?= $b['zahlungsart'] === 'bar' ? 'Bar' : 'Karte' ?></td>
          <td style="text-align:right;white-space:nowrap">€ <?= $fmt($b['bruttobetrag']) ?></td>
          <td class="mb-bon" style="white-space:nowrap">
            <?php if ($b['bon_id']): ?>
              <span style="color:#16a34a">✓ <?= htmlspecialchars($b['bon_nr']) ?></span><?= $b['storniert'] ? ' <span style="color:#dc2626">(storniert)</span>' : '' ?>
            <?php else: ?>
              <span style="color:#d97706">offen</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="<?= BASE_PATH ?>/js/kasse_messe_mobil_belege.js"></script>
<?php require_once __DIR__ . '/shell_bottom.php'; ?>
