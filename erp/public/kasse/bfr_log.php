<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

$service = new BfrService();

$kasseId = isset($_GET['kasse_id']) && $_GET['kasse_id'] !== '' ? (int)$_GET['kasse_id'] : null;
$limit   = max(10, min(500, (int)($_GET['limit'] ?? 200)));

$log       = $service->kommunikationsLog($kasseId, $limit);
$alleKassen = (new KassenService())->getAlleKassen();

$pageTitle      = 'BFR-Rohdaten-Protokoll';
$activeKasseNav = 'nacherfassung';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:1200px;margin:0 auto">

  <div class="ks-card">
    <div style="font-size:13px;color:#93c5fd;margin-bottom:14px;line-height:1.6">
      Exaktes Request-XML und exakte Antwort jedes einzelnen BFR-Aufrufs (<code>/state</code> + <code>/register</code>) —
      für eine präzise Fehlermeldung an den BFR-Hersteller. Läuft seit 2026-07-09, ältere Aufrufe sind hier nicht enthalten.
    </div>
    <form method="get" style="display:flex;gap:10px;align-items:center;margin-bottom:4px">
      <select name="kasse_id" class="ks-select" style="width:220px" onchange="this.form.submit()">
        <option value="">Alle Kassen</option>
        <?php foreach ($alleKassen as $k): ?>
          <option value="<?= $k['id'] ?>" <?= $kasseId === (int)$k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="limit" class="ks-select" style="width:140px" onchange="this.form.submit()">
        <?php foreach ([50, 100, 200, 500] as $l): ?>
          <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>>Letzte <?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (empty($log)): ?>
    <div class="ks-card"><p style="color:#888;margin:0">Noch keine Aufrufe protokolliert.</p></div>
  <?php else: ?>
    <?php foreach ($log as $e): ?>
    <div class="ks-card" style="<?= $e['curl_fehler'] || $e['response_body'] === null ? 'border-left:3px solid #dc2626' : '' ?>">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px">
        <div style="font-size:13px">
          <strong style="color:#93c5fd"><?= htmlspecialchars($e['kasse_name'] ?? ('Kasse #' . $e['kasse_id'])) ?></strong>
          &nbsp;·&nbsp; <span style="font-family:monospace">/<?= htmlspecialchars($e['endpunkt']) ?></span>
          &nbsp;·&nbsp; <?= date('d.m.Y H:i:s', strtotime($e['erstellt_am'])) ?>
          &nbsp;·&nbsp; <?= (int)$e['dauer_ms'] ?> ms
        </div>
        <?php if ($e['curl_fehler']): ?>
          <span style="background:#7f1d1d;color:#fca5a5;font-size:11px;padding:2px 8px;border-radius:4px">CURL-FEHLER: <?= htmlspecialchars($e['curl_fehler']) ?></span>
        <?php elseif ($e['response_body'] === null): ?>
          <span style="background:#7f1d1d;color:#fca5a5;font-size:11px;padding:2px 8px;border-radius:4px">KEINE ANTWORT</span>
        <?php else: ?>
          <span style="background:#052e16;color:#4ade80;font-size:11px;padding:2px 8px;border-radius:4px">ANTWORT ERHALTEN</span>
        <?php endif; ?>
      </div>

      <?php if ($e['request_body']): ?>
        <div style="font-size:11px;color:#64748b;margin-bottom:4px">Request (gesendet):</div>
        <pre style="background:#0f172a;color:#e2e8f0;padding:10px 12px;border-radius:6px;overflow-x:auto;font-size:12px;margin:0 0 10px;white-space:pre-wrap;word-break:break-all"><?= htmlspecialchars($e['request_body']) ?></pre>
      <?php endif; ?>

      <div style="font-size:11px;color:#64748b;margin-bottom:4px">Antwort (empfangen):</div>
      <pre style="background:#0f172a;color:#e2e8f0;padding:10px 12px;border-radius:6px;overflow-x:auto;font-size:12px;margin:0;white-space:pre-wrap;word-break:break-all"><?= $e['response_body'] !== null ? htmlspecialchars($e['response_body']) : '(keine — Verbindung fehlgeschlagen)' ?></pre>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
