<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';

$db      = Database::getInstance();
$syncSvc = new MesseSyncService();

$syncId = (int)($_GET['sync_id'] ?? 0);
$syncs  = $syncSvc->getSyncsFuerRueckkehr();

$aktiverSync = null;
$umbuchungen = [];
if ($syncId) {
    $aktiverSync = $syncSvc->getSyncById($syncId);
    if ($aktiverSync) {
        $umbuchungen = $syncSvc->getUmbuchungenBySyncId($syncId);
    }
}

// Hauptlager als Standard-Rückbuchungsziel
$hauptlager = $db->query("SELECT id, name FROM lager WHERE typ = 'ladengeschaeft' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC)
    ?: $db->query("SELECT id, name FROM lager ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);

$pageTitle      = 'Von Messe zurück';
$activeKasseNav = 'messe';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:900px;margin:0 auto">

  <?php if ($erfolg): ?><div class="ks-feedback ok"><?= htmlspecialchars($erfolg) ?></div><?php endif; ?>
  <?php if ($fehler): ?><div class="ks-feedback fehler"><?= htmlspecialchars($fehler) ?></div><?php endif; ?>

  <?php if (!$aktiverSync): ?>

    <?php if (empty($syncs)): ?>
      <div class="ks-card" style="text-align:center;color:#666;padding:40px">
        Keine Messe-Sync-Pakete zur Rückkehr bereit.<br>
        <span style="font-size:12px;color:#94a3b8">Erst wenn die Bons vom Offline-Client hochgeladen wurden, erscheint ein Paket hier.</span>
      </div>
    <?php else: ?>
      <div class="ks-card">
        <div class="ks-card-title">Zurückgekehrte Sync-Pakete wählen</div>
        <table class="ks-table">
          <thead><tr><th>Kasse</th><th>Lager</th><th style="text-align:right">Bons</th><th style="text-align:right">Umsatz</th><th>Hochgeladen</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($syncs as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['kasse_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($s['lager_name'] ?? '') ?></td>
              <td style="text-align:right"><?= (int)$s['bon_count'] ?></td>
              <td style="text-align:right">€ <?= number_format((float)$s['umsatz'], 2, ',', '.') ?></td>
              <td style="color:#888"><?= $s['abgeschlossen_am'] ? date('d.m.Y H:i', strtotime($s['abgeschlossen_am'])) : '–' ?></td>
              <td><a href="?sync_id=<?= (int)$s['id'] ?>" class="ks-btn ks-btn-primary" style="padding:5px 12px;font-size:12px">Bearbeiten →</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  <?php else: ?>

    <div class="ks-card">
      <div class="ks-card-title">Restbestand erfassen — <?= htmlspecialchars($aktiverSync['sync_token']) ?></div>
      <p style="font-size:13px;color:#64748b;margin-bottom:14px">
        Pro Artikel: wie viel kommt unverkauft zurück, wie viel ist Schwund (Verlust/Beschädigung)?
        Der Rest gilt automatisch als verkauft.
      </p>

      <table class="ks-table">
        <thead>
          <tr>
            <th>Artikel</th>
            <th style="width:120px">Charge</th>
            <th style="text-align:right;width:80px">Mitgenommen</th>
            <th style="text-align:right;width:100px">Zurück</th>
            <th style="text-align:right;width:100px">Schwund</th>
            <th style="text-align:right;width:80px">Verkauft</th>
          </tr>
        </thead>
        <tbody id="mr-tabelle">
          <?php foreach ($umbuchungen as $i => $u): ?>
          <tr data-artikel-id="<?= (int)$u['artikel_id'] ?>" data-charge="<?= htmlspecialchars($u['charge'] ?? '') ?>" data-menge-raus="<?= (float)$u['menge_raus'] ?>">
            <td><?= htmlspecialchars($u['bezeichnung']) ?><?= $u['ean'] ? '<div style="font-size:11px;color:#94a3b8">' . htmlspecialchars($u['ean']) . '</div>' : '' ?></td>
            <td><?= $u['charge'] ? '<span style="color:#2563eb;font-weight:600">' . htmlspecialchars($u['charge']) . '</span>' : '<span style="color:#94a3b8">—</span>' ?></td>
            <td style="text-align:right"><?= (float)$u['menge_raus'] ?></td>
            <td style="text-align:right">
              <input type="number" min="0" step="1" value="<?= (float)($u['menge_rueck'] ?? 0) ?>" class="ks-input mr-rueck" style="width:80px;text-align:right;padding:4px 6px" onchange="mrNeuBerechnen(this)">
            </td>
            <td style="text-align:right">
              <input type="number" min="0" step="1" value="<?= (float)($u['menge_schwund'] ?? 0) ?>" class="ks-input mr-schwund" style="width:80px;text-align:right;padding:4px 6px" onchange="mrNeuBerechnen(this)">
            </td>
            <td style="text-align:right" class="mr-verkauft-zelle"><?= (float)$u['menge_raus'] - (float)($u['menge_rueck'] ?? 0) - (float)($u['menge_schwund'] ?? 0) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top:18px;display:flex;gap:10px;align-items:center">
        <button type="button" class="ks-btn ks-btn-primary ks-btn-lg" onclick="mrAbschliessen(<?= (int)$aktiverSync['id'] ?>, <?= (int)$aktiverSync['lager_id'] ?>, <?= (int)($hauptlager['id'] ?? 1) ?>)">
          ✓ Rückkehr abschließen
        </button>
        <span style="font-size:13px;color:#64748b">
          Zurück ins Lager: <strong><?= htmlspecialchars($hauptlager['name'] ?? 'Hauptlager') ?></strong>
        </span>
      </div>
      <div id="mr-feedback" style="margin-top:10px"></div>
    </div>

  <?php endif; ?>

</div>

<script src="<?= BASE_PATH ?>/js/kasse_messe_rueckkehr.js"></script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
