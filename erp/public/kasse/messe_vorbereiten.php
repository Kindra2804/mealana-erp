<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';

$db = Database::getInstance();

$kassen = $db->query("
    SELECT id, name FROM kassen WHERE modus = 'offline' AND aktiv = 1 ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$messeLager = $db->query("
    SELECT id, name FROM lager WHERE typ = 'messe' ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$quellLager = $db->query("
    SELECT id, name FROM lager WHERE typ != 'messe' AND aktiv = 1 ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$syncSvc = new MesseSyncService();
$offeneSyncs = [];
foreach ($kassen as $k) {
    foreach ($syncSvc->getOffeneSyncs((int)$k['id']) as $s) {
        $s['kasse_name'] = $k['name'];
        $offeneSyncs[] = $s;
    }
}

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);

$pageTitle      = 'Messe vorbereiten';
$activeKasseNav = 'messe';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:900px;margin:0 auto">

  <?php if ($erfolg): ?>
    <div class="ks-feedback ok"><?= htmlspecialchars($erfolg) ?></div>
  <?php endif; ?>
  <?php if ($fehler): ?>
    <div class="ks-feedback fehler"><?= htmlspecialchars($fehler) ?></div>
  <?php endif; ?>

  <?php if (empty($kassen)): ?>
    <div class="ks-card">
      <div class="ks-card-title">Keine Offline-Kasse eingerichtet</div>
      <p style="font-size:14px;color:#475569;margin-bottom:14px">
        Bevor Ware ins Messe-Lager umgebucht werden kann, muss unter
        <strong>Einstellungen → Kassen</strong> eine Kasse mit Modus <strong>„offline“</strong>
        angelegt werden (z.B. „Messe-Laptop“).
      </p>
      <a href="<?= BASE_PATH ?>/einstellungen/index.php?tab=kassen" class="ks-btn ks-btn-primary">Zu den Kassen-Einstellungen</a>
    </div>
  <?php elseif (empty($messeLager)): ?>
    <div class="ks-card">
      <div class="ks-card-title">Kein Messe-Lager gefunden</div>
      <p style="font-size:14px;color:#475569">Es existiert kein Lager mit Typ „messe“. Bitte zuerst unter Lager-Verwaltung anlegen.</p>
    </div>
  <?php else: ?>

  <div class="ks-card">
    <div class="ks-card-title">1 — Ziel wählen</div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:4px">
      <div style="flex:1;min-width:200px">
        <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px">Offline-Kasse</label>
        <select id="msv-kasse" class="ks-select">
          <?php foreach ($kassen as $k): ?>
            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1;min-width:200px">
        <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px">Messe-Lager</label>
        <select id="msv-lager" class="ks-select">
          <?php foreach ($messeLager as $l): ?>
            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1;min-width:200px">
        <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px">Von Lager (Quelle)</label>
        <select id="msv-von-lager" class="ks-select">
          <?php foreach ($quellLager as $l): ?>
            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="ks-card">
    <div class="ks-card-title">2 — Artikel scannen</div>
    <div style="display:flex;gap:10px">
      <input type="text" id="msv-scan" class="ks-input" placeholder="EAN / Artikelnummer scannen oder eingeben" autofocus>
    </div>
    <div id="msv-feedback" style="font-size:13px;margin-top:8px;min-height:18px"></div>

    <table class="ks-table" style="margin-top:14px">
      <thead>
        <tr>
          <th>Artikel</th>
          <th style="width:90px;text-align:right">Bestand</th>
          <th style="width:110px;text-align:right">Menge</th>
          <th style="width:50px"></th>
        </tr>
      </thead>
      <tbody id="msv-liste">
        <tr id="msv-leer"><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px">Noch keine Artikel gescannt.</td></tr>
      </tbody>
    </table>

    <div style="margin-top:16px">
      <button type="button" id="msv-submit" class="ks-btn ks-btn-primary ks-btn-lg" disabled>Umbuchung durchführen</button>
    </div>
  </div>

  <?php endif; ?>

  <?php if (!empty($offeneSyncs)): ?>
  <div class="ks-card">
    <div class="ks-card-title">Bereits vorbereitete Sync-Pakete</div>
    <table class="ks-table">
      <thead>
        <tr><th>Kasse</th><th>Lager</th><th style="text-align:right">Artikel</th><th>Erstellt</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($offeneSyncs as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['kasse_name']) ?></td>
          <td><?= htmlspecialchars($s['lager_name'] ?? '') ?></td>
          <td style="text-align:right"><?= (int)$s['artikel_count'] ?></td>
          <td style="color:#888"><?= date('d.m.Y H:i', strtotime($s['erstellt_am'])) ?></td>
          <td>
            <a href="bon_offline.php?sync_id=<?= (int)$s['id'] ?>" class="ks-btn ks-btn-secondary" style="padding:5px 12px;font-size:12px">
              Offline-Kasse laden →
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<!-- Chargen-Auswahl (nur bei charge_pflicht-Artikeln) -->
<div class="ov" id="ov-msv-charge" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:10px;padding:22px;width:380px;max-width:92vw">
    <div style="font-weight:700;font-size:15px;margin-bottom:4px" id="msv-charge-titel">Charge wählen</div>
    <div style="font-size:12px;color:#64748b;margin-bottom:12px">Dieser Artikel ist chargenpflichtig — es kann nur aus den tatsächlich vorhandenen Chargen umgebucht werden.</div>
    <div id="msv-charge-liste" style="display:flex;flex-direction:column;gap:8px;max-height:280px;overflow-y:auto"></div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
      <button type="button" class="ks-btn ks-btn-primary" onclick="msvChargeAbbrechen()">Fertig ✓</button>
    </div>
  </div>
</div>

<script src="<?= BASE_PATH ?>/js/kasse_messe_vorbereiten.js"></script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
