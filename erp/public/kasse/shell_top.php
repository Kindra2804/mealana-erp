<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Kasse') ?></title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: #1a1a2e;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color: #eee;
      min-height: 100vh;
    }
    .ks-header {
      background: #0d1b2a;
      border-bottom: 3px solid #e67e22;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 58px;
      position: sticky; top: 0; z-index: 50;
    }
    .ks-header-left  { display: flex; align-items: center; gap: 20px; }
    .ks-header-title { font-size: 22px; font-weight: 900; color: #e67e22; letter-spacing: 2px; }
    .ks-header-sub   { font-size: 13px; color: #aaa; }
    .ks-nav { display: flex; gap: 6px; align-items: center; }
    .ks-nav a {
      background: #1a3a5c;
      color: #ccc;
      border: none;
      border-radius: 6px;
      padding: 7px 14px;
      font-size: 13px;
      text-decoration: none;
      white-space: nowrap;
    }
    .ks-nav a:hover { background: #2a5a8c; color: #fff; }
    .ks-nav a.aktiv { background: #e67e22; color: #fff; }
    .ks-nav-sep { width: 1px; background: #2a2a4a; height: 28px; margin: 0 4px; }
    .ks-back {
      background: #333;
      color: #aaa;
      border-radius: 6px;
      padding: 7px 14px;
      font-size: 13px;
      text-decoration: none;
    }
    .ks-back:hover { background: #444; color: #fff; }
    .ks-user { font-size: 13px; color: #888; white-space: nowrap; }
    .ks-main { padding: 20px; max-width: 1600px; margin: 0 auto; }

    /* Kacheln */
    .ks-kacheln {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
    }
    .ks-kachel {
      background: #0d1b2a;
      border: 2px solid #1a3a5c;
      border-radius: 14px;
      padding: 32px 20px;
      text-align: center;
      cursor: pointer;
      text-decoration: none;
      color: #eee;
      transition: border-color .15s, transform .1s;
      display: block;
    }
    .ks-kachel:hover { border-color: #e67e22; transform: translateY(-3px); }
    .ks-kachel-icon  { font-size: 52px; display: block; margin-bottom: 14px; }
    .ks-kachel-label { font-size: 18px; font-weight: 700; }
    .ks-kachel-sub   { font-size: 12px; color: #888; margin-top: 5px; }
    .ks-kachel.disabled { opacity:.4; cursor:not-allowed; pointer-events:none; }

    /* Buttons */
    .ks-btn {
      border: none; border-radius: 8px; padding: 13px 24px;
      font-size: 15px; font-weight: 700; cursor: pointer;
      text-decoration: none; display: inline-block; text-align: center;
    }
    .ks-btn:disabled { opacity:.4; cursor:not-allowed; }
    .ks-btn-primary  { background: #e67e22; color: #fff; }
    .ks-btn-primary:hover  { background: #d35400; }
    .ks-btn-secondary { background: #1a3a5c; color: #eee; }
    .ks-btn-secondary:hover { background: #2a5a8c; }
    .ks-btn-success  { background: #27ae60; color: #fff; }
    .ks-btn-success:hover  { background: #1e8449; }
    .ks-btn-danger   { background: #c0392b; color: #fff; }
    .ks-btn-danger:hover   { background: #922b21; }
    .ks-btn-lg { font-size: 20px; padding: 18px 36px; }

    /* Cards */
    .ks-card {
      background: #0d1b2a;
      border: 1px solid #1a3a5c;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 16px;
    }
    .ks-card-title { font-size: 14px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px; }

    /* Tabelle */
    .ks-table { width: 100%; border-collapse: collapse; }
    .ks-table th { background: #0a1520; color: #888; font-size: 11px; text-align: left; padding: 8px 12px; text-transform: uppercase; letter-spacing: .5px; }
    .ks-table td { padding: 10px 12px; border-bottom: 1px solid #0a1520; font-size: 14px; vertical-align: middle; }
    .ks-table tr:hover td { background: #0f2035; }

    /* Inputs */
    .ks-input {
      background: #0a1520;
      border: 2px solid #1a3a5c;
      border-radius: 8px;
      color: #fff;
      font-size: 16px;
      padding: 10px 14px;
      outline: none;
      width: 100%;
    }
    .ks-input:focus { border-color: #e67e22; }
    .ks-select {
      background: #0a1520;
      border: 2px solid #1a3a5c;
      border-radius: 8px;
      color: #fff;
      font-size: 15px;
      padding: 9px 10px;
      outline: none;
      width: 100%;
    }
    .ks-select:focus { border-color: #e67e22; }

    /* Overlay */
    .ks-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.8);
      z-index: 200;
      align-items: center; justify-content: center;
    }
    .ks-overlay.aktiv { display: flex; }
    .ks-overlay-box {
      background: #0d1b2a;
      border: 2px solid #e67e22;
      border-radius: 16px;
      padding: 36px 40px;
      min-width: 380px;
      max-width: 550px;
      width: 90%;
    }
    .ks-overlay-titel { font-size: 20px; font-weight: 700; margin-bottom: 22px; color: #e67e22; }

    /* Feedback */
    .ks-feedback { padding: 10px 16px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; }
    .ks-feedback.ok    { background: #1a3a28; color: #4caf50; border: 1px solid #2e7d32; }
    .ks-feedback.fehler { background: #3a1a1a; color: #ef5350; border: 1px solid #7d2020; }
    .ks-feedback.info  { background: #1a2a3a; color: #64b5f6; border: 1px solid #1565c0; }
  </style>
</head>
<body>
<div class="ks-header">
  <div class="ks-header-left">
    <div class="ks-header-title">
      KASSE
      <?php if (!empty($kasseInfo)): ?>
        <span style="font-size:14px;color:#888;font-weight:400;margin-left:8px"><?= htmlspecialchars($kasseInfo['kasse_nr']) ?></span>
      <?php endif; ?>
    </div>
    <?php if (!empty($headerSub)): ?>
      <div class="ks-header-sub"><?= htmlspecialchars($headerSub) ?></div>
    <?php endif; ?>
  </div>
  <div class="ks-nav">
    <?php $cur = $activeKasseNav ?? ''; ?>
    <a href="/mealana/kasse/bon.php" class="<?= $cur === 'bon' ? 'aktiv' : '' ?>">🛒 Kasse</a>
    <a href="/mealana/kasse/offene_auswahl.php" class="<?= $cur === 'oa' ? 'aktiv' : '' ?>">↗ Mitgeben</a>
    <a href="/mealana/kasse/kassenbuch.php" class="<?= $cur === 'kb' ? 'aktiv' : '' ?>">💰 Kassenbuch</a>
    <a href="/mealana/kasse/kassensturz.php" class="<?= $cur === 'ks' ? 'aktiv' : '' ?>">📊 Kassenstand</a>
    <a href="/mealana/kasse/bon_journal.php" class="<?= $cur === 'journal' ? 'aktiv' : '' ?>">📋 Journal</a>
    <div class="ks-nav-sep"></div>
    <a href="/mealana/auftraege/liste.php" class="ks-back">→ ERP</a>
  </div>
  <div class="ks-user">👤 <?= htmlspecialchars($_SESSION['benutzer']['formularname'] ?? 'User') ?></div>
</div>
<div class="ks-main">
