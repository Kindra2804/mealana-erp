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

$offenePapier = $syncSvc->getOffenePapierMessen();

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

  <div style="text-align:right;margin-bottom:10px">
    <a href="messe_rueckkehr.php" class="ks-btn ks-btn-secondary" style="padding:6px 14px;font-size:13px">↩ Von Messe zurück / Belege nacherfassen</a>
  </div>

  <?php if (empty($messeLager)): ?>
    <div class="ks-card">
      <div class="ks-card-title">Kein Messe-Lager gefunden</div>
      <p style="font-size:14px;color:#475569">Es existiert kein Lager mit Typ „messe“. Bitte zuerst unter Lager-Verwaltung anlegen.</p>
    </div>
  <?php else: ?>

  <div class="ks-card">
    <div class="ks-card-title">1 — Ziel wählen</div>

    <!-- Zwei Messe-Varianten, je nach Art/Größe der Messe -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
      <label class="msv-variante">
        <input type="radio" name="msv-variante" value="papier" checked onchange="msvVarianteGeaendert()">
        <span><strong>📝 Papier-Messe</strong><br>
          <small>Strichliste + händische Belege — kein Gerät, kein Strom/Internet nötig.
          Belege werden danach einzeln nacherfasst.</small></span>
      </label>
      <label class="msv-variante" <?= empty($kassen) ? 'style="opacity:.5"' : '' ?>>
        <input type="radio" name="msv-variante" value="elektronisch" <?= empty($kassen) ? 'disabled' : '' ?> onchange="msvVarianteGeaendert()">
        <span><strong>💻 Elektronische Messe-Kasse</strong><br>
          <small><?= empty($kassen)
              ? 'Keine Offline-Kasse eingerichtet (Einstellungen → Kassen, Modus „offline“).'
              : 'Offline-Kasse mit Signatur am Laptop, Bons werden danach synchronisiert.' ?></small></span>
      </label>
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:4px">
      <div style="flex:1;min-width:200px;display:none" id="msv-kasse-feld">
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

  <?php if (!empty($offenePapier)): ?>
  <div class="ks-card">
    <div class="ks-card-title">📝 Offene Papier-Messen</div>
    <table class="ks-table">
      <thead>
        <tr><th>Messe-Lager</th><th style="text-align:right">Artikel</th><th>Vorbereitet</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($offenePapier as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['lager_name'] ?? '') ?></td>
          <td style="text-align:right"><?= (int)$s['artikel_count'] ?></td>
          <td style="color:#888"><?= date('d.m.Y H:i', strtotime($s['erstellt_am'])) ?></td>
          <td style="text-align:right;white-space:nowrap">
            <a href="messe_strichliste.php?sync_id=<?= (int)$s['id'] ?>" target="_blank" class="ks-btn ks-btn-secondary" style="padding:5px 12px;font-size:12px">
              🖨 Strichliste
            </a>
            <a href="messe_rueckkehr.php?sync_id=<?= (int)$s['id'] ?>" class="ks-btn ks-btn-secondary" style="padding:5px 12px;font-size:12px">
              Rückkehr →
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:12px;color:#64748b;margin:10px 0 0">
      Weitere Artikel können jederzeit nachgebucht werden — sie landen in derselben Papier-Messe, die Strichliste einfach neu drucken.
    </p>
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

<style>
.msv-variante {
  flex: 1 1 260px; display: flex; gap: 10px; align-items: flex-start; cursor: pointer;
  border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; background: #f8fafc;
}
.msv-variante:has(input:checked) { border-color: #2563eb; background: #eff6ff; }
.msv-variante small { color: #64748b; }
</style>

<script src="<?= BASE_PATH ?>/js/kasse_messe_vorbereiten.js"></script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
