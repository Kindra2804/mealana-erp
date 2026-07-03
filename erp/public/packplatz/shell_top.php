<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/css/variables.css">
  <title><?= htmlspecialchars($pageTitle ?? 'Packplatz') ?></title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: #1a1a2e;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color: #eee;
      min-height: 100vh;
    }
    .pp-header {
      background: #16213e;
      border-bottom: 2px solid #0f3460;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 56px;
    }
    .pp-header-title {
      font-size: 18px;
      font-weight: 700;
      color: #e94560;
      letter-spacing: 1px;
    }
    .pp-header-sub {
      font-size: 13px;
      color: #aaa;
    }
    .pp-header-nav {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    .pp-back {
      background: #0f3460;
      color: #eee;
      border: none;
      border-radius: 6px;
      padding: 7px 16px;
      font-size: 14px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }
    .pp-back:hover { background: #1a4a8a; }
    .pp-user {
      font-size: 13px;
      color: #aaa;
    }
    .pp-main {
      padding: 20px;
      max-width: 1400px;
      margin: 0 auto;
    }
    /* Kacheln für Hauptmenü */
    .pp-kacheln {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-top: 30px;
    }
    .pp-kachel {
      background: #16213e;
      border: 2px solid #0f3460;
      border-radius: 12px;
      padding: 30px 20px;
      text-align: center;
      cursor: pointer;
      text-decoration: none;
      color: #eee;
      transition: border-color .15s, transform .1s;
      display: block;
    }
    .pp-kachel:hover { border-color: #e94560; transform: translateY(-2px); }
    .pp-kachel-icon { font-size: 48px; display: block; margin-bottom: 12px; }
    .pp-kachel-label { font-size: 17px; font-weight: 700; }
    .pp-kachel-sub { font-size: 12px; color: #aaa; margin-top: 4px; }
    .pp-kachel.disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }
    /* Scan-Bereich */
    .pp-scan-bar {
      background: #16213e;
      border: 2px solid #0f3460;
      border-radius: 10px;
      padding: 14px 18px;
      display: flex;
      gap: 14px;
      align-items: center;
      margin-bottom: 16px;
    }
    .pp-scan-input {
      background: #0a0a1a;
      border: 2px solid #0f3460;
      border-radius: 8px;
      color: #fff;
      font-size: 20px;
      padding: 10px 14px;
      width: 280px;
      outline: none;
    }
    .pp-scan-input:focus { border-color: #e94560; }
    .pp-vorwahl {
      background: #0a0a1a;
      border: 2px solid #0f3460;
      border-radius: 8px;
      color: #fff;
      font-size: 20px;
      padding: 10px 10px;
      width: 80px;
      text-align: center;
      outline: none;
    }
    .pp-vorwahl:focus { border-color: #e94560; }
    .pp-scan-label { font-size: 12px; color: #aaa; }
    /* Artikelbild beim Scan */
    .pp-scan-bild {
      width: 90px; height: 90px;
      object-fit: contain;
      border-radius: 8px;
      background: #0a0a1a;
      border: 1px solid #0f3460;
    }
    .pp-scan-bild-placeholder {
      width: 90px; height: 90px;
      border-radius: 8px;
      background: #0a0a1a;
      border: 1px dashed #444;
      display: flex; align-items: center; justify-content: center;
      font-size: 28px; color: #444;
    }
    /* Positions-Tabelle */
    .pp-table { width: 100%; border-collapse: collapse; }
    .pp-table th {
      background: #0f3460;
      color: #aaa;
      font-size: 12px;
      text-align: left;
      padding: 8px 12px;
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .pp-table td {
      padding: 10px 12px;
      border-bottom: 1px solid #1a1a3e;
      font-size: 15px;
      vertical-align: middle;
    }
    .pp-table tr.pp-ok   td { background: #0d2d1a; color: #4caf50; }
    .pp-table tr.pp-zuviel td { background: #2d0d0d; color: #ef5350; }
    .pp-table tr.pp-aktiv td { background: #1a1a3e; }
    .pp-table tr td:first-child { border-radius: 6px 0 0 6px; }
    .pp-table tr td:last-child  { border-radius: 0 6px 6px 0; }
    .pp-menge-cell { font-size: 18px; font-weight: 700; }
    .pp-menge-ok   { color: #4caf50; }
    .pp-menge-zuviel { color: #ef5350; }
    /* Buttons */
    .pp-btn {
      border: none; border-radius: 8px; padding: 14px 28px;
      font-size: 16px; font-weight: 700; cursor: pointer;
      text-decoration: none; display: inline-block;
    }
    .pp-btn-primary { background: #e94560; color: #fff; }
    .pp-btn-primary:hover { background: #c73450; }
    .pp-btn-secondary { background: #0f3460; color: #eee; }
    .pp-btn-secondary:hover { background: #1a4a8a; }
    .pp-btn-success { background: #2e7d32; color: #fff; font-size: 20px; padding: 18px 40px; }
    .pp-btn-success:hover { background: #388e3c; }
    .pp-btn-warning { background: #e65100; color: #fff; }
    .pp-btn-warning:hover { background: #bf360c; }
    .pp-btn:disabled { opacity: .4; cursor: not-allowed; }
    /* Auftrags-Info Box */
    .pp-auftrag-info {
      background: #16213e;
      border: 1px solid #0f3460;
      border-radius: 8px;
      padding: 12px 16px;
      font-size: 13px;
      line-height: 1.8;
    }
    .pp-auftrag-info strong { color: #e94560; }
    /* Tracking-Overlay */
    .pp-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.85);
      z-index: 100;
      align-items: center; justify-content: center;
    }
    .pp-overlay.aktiv { display: flex; }
    .pp-overlay-box {
      background: #16213e;
      border: 2px solid #e94560;
      border-radius: 16px;
      padding: 40px;
      text-align: center;
      min-width: 400px;
    }
    .pp-overlay-titel { font-size: 22px; font-weight: 700; margin-bottom: 20px; }
    .pp-overlay-input {
      background: #0a0a1a;
      border: 2px solid #0f3460;
      border-radius: 8px;
      color: #fff;
      font-size: 24px;
      padding: 14px 18px;
      width: 100%;
      text-align: center;
      outline: none;
      margin-bottom: 20px;
    }
    .pp-overlay-input:focus { border-color: #e94560; }
  </style>
</head>
<body>
<div class="pp-header">
  <div>
    <div class="pp-header-title">PACKPLATZ</div>
    <?php if (!empty($headerSub)): ?>
      <div class="pp-header-sub"><?= htmlspecialchars($headerSub) ?></div>
    <?php endif; ?>
  </div>
  <div class="pp-header-nav">
    <?php if (!empty($backUrl)): ?>
      <a href="<?= htmlspecialchars($backUrl) ?>" class="pp-back">← Zurück</a>
    <?php endif; ?>
    <a href="<?= BASE_PATH ?>/start.php" class="pp-back">→ Start</a>
    <div class="pp-user">👤 <?= htmlspecialchars($_SESSION['benutzer']['formularname'] ?? 'User') ?></div>
  </div>
</div>
<div class="pp-main">
