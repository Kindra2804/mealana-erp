<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';

$service = new BfrService();

$offen      = $service->offeneEpisodenMitWarnung();
$historie   = $service->ausfallHistorie();

$ausfallId = isset($_GET['ausfall_id']) ? (int)$_GET['ausfall_id'] : 0;
$ereignisse = $ausfallId ? $service->episodeEreignisse($ausfallId) : [];

$typBadge = [
    'dienst_nicht_erreichbar'          => '<span style="background:#7f1d1d;color:#fca5a5;font-size:10px;padding:2px 6px;border-radius:3px">DIENST WEG</span>',
    'sicherheitseinrichtung_ausgefallen' => '<span style="background:#92400e;color:#fbbf24;font-size:10px;padding:2px 6px;border-radius:3px">AUSGEFALLEN</span>',
];

// Kassen mit aktiver RKSV-Registrierung (für den DEP-Export)
$rksvKassen = Database::getInstance()->query("
    SELECT id, name, kasse_nr, rksv_kassen_id, bfr_url, bfr_aktiv_seit
    FROM kassen
    WHERE aktiv = 1 AND bfr_url IS NOT NULL AND bfr_url <> '' AND bfr_aktiv_seit IS NOT NULL
    ORDER BY kasse_nr
")->fetchAll(PDO::FETCH_ASSOC);
$darfExport  = Auth::kann('kasse.stoppen');
$alleKassen  = Database::getInstance()->query("SELECT id, name, kasse_nr, aktiv FROM kassen ORDER BY kasse_nr")->fetchAll(PDO::FETCH_ASSOC);
$fehler      = $_SESSION['fehler'] ?? null;        unset($_SESSION['fehler']);
$depLokalUrl = $_SESSION['dep_lokal_url'] ?? null; unset($_SESSION['dep_lokal_url']);

$pageTitle      = 'RKSV Ausfall-Historie';
$activeKasseNav = 'nacherfassung';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:1000px;margin:0 auto">

  <div style="text-align:right;margin-bottom:10px">
    <a href="<?= BASE_PATH ?>/kasse/bfr_log.php" style="font-size:12px;color:#60a5fa">🔧 Rohdaten-Protokoll ansehen (für Hersteller-Fehlermeldung) →</a>
  </div>

  <?php if ($fehler): ?>
  <div class="ks-card" style="border-left:3px solid #f87171">
    <div style="color:#fca5a5"><?= htmlspecialchars($fehler) ?></div>
    <?php if ($depLokalUrl): ?>
      <div style="color:#94a3b8;font-size:12px;margin-top:6px">
        Am PC, auf dem der BFR läuft, geht es direkt im Browser:
        <a href="<?= htmlspecialchars($depLokalUrl) ?>" style="color:#60a5fa"><?= htmlspecialchars($depLokalUrl) ?></a>
        (speichert die Datei DEP.json).
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="ks-card">
    <div class="ks-card-title">DEP-Export für die Finanzprüfung (RKSV § 7)</div>
    <p style="color:#94a3b8;font-size:12px;margin:0 0 10px">
      Holt das komplette Datenerfassungsprotokoll direkt vom BFR (seit der Inbetriebnahme, als JSON).
      Ein kürzerer Zeitraum geht nur im BFR-Programm selbst. Kann bei vielen Belegen etwas dauern.
    </p>
    <?php if (empty($rksvKassen)): ?>
      <p style="color:#888;margin:0">Keine Kasse mit aktiver RKSV-Registrierung.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead><tr><th>Kasse</th><th>Kassen-ID</th><th>BFR</th><th>Aktiv seit</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rksvKassen as $k): ?>
          <tr>
            <td><?= htmlspecialchars($k['name']) ?> (<?= htmlspecialchars($k['kasse_nr']) ?>)</td>
            <td><?= htmlspecialchars($k['rksv_kassen_id']) ?></td>
            <td style="color:#888"><?= htmlspecialchars($k['bfr_url']) ?></td>
            <td style="color:#888"><?= date('d.m.Y', strtotime($k['bfr_aktiv_seit'])) ?></td>
            <td style="text-align:right">
              <?php if ($darfExport): ?>
                <a href="<?= BASE_PATH ?>/kasse/dep_export.php?kasse_id=<?= (int)$k['id'] ?>" data-no-loader
                   style="color:#60a5fa;font-size:12px" onclick="this.textContent='⏳ wird geholt …'">⬇ DEP herunterladen</a>
              <?php else: ?>
                <span style="color:#666;font-size:12px" title="Recht kasse.stoppen nötig">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php if ($darfExport): ?>
  <div class="ks-card">
    <div class="ks-card-title">Datenexport für die Betriebsprüfung (§ 131 BAO)</div>
    <p style="color:#94a3b8;font-size:12px;margin:0 0 10px">
      ZIP mit allen Kassendaten im Zeitraum, eine Datei je Tabelle (CSV, TAB-getrennt, öffnet in Excel):
      Belege, Positionen, Kassenbuch, Tagesabschlüsse, Nullbelege, Ausfälle, Messen + kleine Stammdaten.
      Ohne Artikelstammdaten. LIESMICH.txt beschreibt Inhalt und Format.
    </p>
    <form method="post" action="<?= BASE_PATH ?>/kasse/pruef_export.php" data-no-loader
          style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap"
          onsubmit="var b=this.querySelector('button');b.textContent='⏳ wird erstellt …';setTimeout(function(){b.textContent='⬇ ZIP erstellen';},8000)">
      <label style="font-size:12px;color:#94a3b8">Von<br>
        <input type="date" name="von" required value="<?= date('Y') ?>-01-01" style="padding:6px;border-radius:6px;border:1px solid #475569"></label>
      <label style="font-size:12px;color:#94a3b8">Bis<br>
        <input type="date" name="bis" required value="<?= date('Y-m-d') ?>" style="padding:6px;border-radius:6px;border:1px solid #475569"></label>
      <label style="font-size:12px;color:#94a3b8">Kasse<br>
        <select name="kasse_id" style="padding:6px;border-radius:6px;border:1px solid #475569">
          <option value="">Alle Kassen</option>
          <?php foreach ($alleKassen as $k): ?>
            <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['kasse_nr'] . ' · ' . $k['name']) ?><?= $k['aktiv'] ? '' : ' (inaktiv)' ?></option>
          <?php endforeach; ?>
        </select></label>
      <label style="font-size:12px;color:#94a3b8;padding-bottom:7px">
        <input type="checkbox" name="mit_dep" value="1"<?= $rksvKassen ? ' checked' : ' disabled' ?>> DEP vom BFR dazulegen</label>
      <button type="submit" style="padding:7px 14px;border-radius:6px;border:0;background:#2563eb;color:#fff;cursor:pointer">⬇ ZIP erstellen</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="ks-card">
    <div class="ks-card-title">Offene Störungen (<?= count($offen) ?>)</div>
    <?php if (empty($offen)): ?>
      <p style="color:#888;margin:0">Keine offene Störung — alle Kassen signieren normal.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead>
          <tr><th>Kasse</th><th>Seit</th><th>Dauer</th><th>Ereignisse</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($offen as $a): ?>
          <tr style="<?= $a['warnung_24h'] ? 'background:#450a0a' : '' ?>">
            <td><?= htmlspecialchars($a['kasse_name']) ?></td>
            <td style="color:#888"><?= date('d.m.Y H:i', strtotime($a['erste_erkennung_am'])) ?></td>
            <td>
              <?= (int)$a['dauer_stunden'] ?> h
              <?php if ($a['warnung_24h']): ?>
                <span style="color:#f87171;font-weight:700;margin-left:6px">⚠ über 24h — FON-Meldepflicht (48h) im Blick behalten</span>
              <?php endif; ?>
            </td>
            <td><?= (int)$a['anzahl_ereignisse'] ?></td>
            <td><a href="?ausfall_id=<?= $a['id'] ?>" style="color:#60a5fa;font-size:12px">Ereignisse ansehen</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="ks-card">
    <div class="ks-card-title">Historie (offen + gelöst)</div>
    <?php if (empty($historie)): ?>
      <p style="color:#888;margin:0">Bisher keine Störung aufgetreten.</p>
    <?php else: ?>
      <table class="ks-table">
        <thead>
          <tr><th>Kasse</th><th>Von</th><th>Bis</th><th>Dauer</th><th>Ereignisse</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($historie as $h): ?>
          <tr style="<?= $ausfallId === (int)$h['id'] ? 'background:#1e293b' : '' ?>">
            <td><?= htmlspecialchars($h['kasse_name']) ?></td>
            <td style="color:#888"><?= date('d.m.Y H:i:s', strtotime($h['erste_erkennung_am'])) ?></td>
            <td style="color:#888"><?= $h['geloest_am'] ? date('d.m.Y H:i:s', strtotime($h['geloest_am'])) : '<span style="color:#f87171">läuft noch</span>' ?></td>
            <td><?= $h['geloest_am'] ? round((strtotime($h['geloest_am']) - strtotime($h['erste_erkennung_am'])) / 60) . ' min' : '—' ?></td>
            <td><?= (int)$h['anzahl_ereignisse'] ?></td>
            <td><a href="?ausfall_id=<?= $h['id'] ?>" style="color:#60a5fa;font-size:12px">Ereignisse ansehen</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($ausfallId): ?>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid #334155">
        <div style="font-weight:700;margin-bottom:8px">Ereignisse zu Störung #<?= $ausfallId ?></div>
        <?php if (empty($ereignisse)): ?>
          <p style="color:#888;margin:0">Keine Ereignisse gefunden.</p>
        <?php else: ?>
          <table class="ks-table">
            <thead><tr><th>Zeitpunkt</th><th>Typ</th><th>Bon-Nr.</th><th>Versuche</th></tr></thead>
            <tbody>
            <?php foreach ($ereignisse as $e): ?>
              <tr>
                <td style="color:#888"><?= date('d.m.Y H:i:s', strtotime($e['aufgetreten_am'])) ?></td>
                <td><?= $typBadge[$e['typ']] ?? htmlspecialchars($e['typ']) ?></td>
                <td style="font-family:monospace"><?= htmlspecialchars($e['bon_nr'] ?? '—') ?></td>
                <td><?= (int)$e['anzahl_versuche'] ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
