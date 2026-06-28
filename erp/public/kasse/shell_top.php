<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Kasse') ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: #f1f5f9;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      color: #1e293b;
      min-height: 100vh;
    }

    /* ── Header ───────────────────────────────────────────────────────────── */
    .ks-header {
      background: #1e3a5f;
      padding: 0 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 54px;
      position: sticky; top: 0; z-index: 50;
    }
    .ks-header-left  { display: flex; align-items: center; gap: 18px; }
    .ks-header-title { font-size: 19px; font-weight: 800; color: #fff; letter-spacing: 1px; }
    .ks-header-sub   { font-size: 13px; color: #93c5fd; }
    .ks-nav { display: flex; gap: 5px; align-items: center; }
    .ks-nav a {
      background: #334155;
      color: #e2e8f0;
      border: none;
      border-radius: 6px;
      padding: 6px 13px;
      font-size: 13px;
      text-decoration: none;
      white-space: nowrap;
    }
    .ks-nav a:hover { background: #475569; color: #fff; }
    .ks-nav a.aktiv { background: #2563eb; color: #fff; }
    .ks-nav-sep { width: 1px; background: #2d4a6a; height: 26px; margin: 0 4px; }
    .ks-back {
      background: #2d4a6a;
      color: #93c5fd;
      border-radius: 6px;
      padding: 6px 13px;
      font-size: 13px;
      text-decoration: none;
    }
    .ks-back:hover { background: #334155; color: #fff; }
    .ks-user { font-size: 13px; color: #93c5fd; white-space: nowrap; }
    .ks-main { padding: 24px; max-width: 1600px; margin: 0 auto; }

    /* ── Kacheln (index.php) ─────────────────────────────────────────────── */
    .ks-kacheln {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 18px;
    }
    .ks-kachel {
      background: #fff;
      border: 2px solid #e2e8f0;
      border-radius: 14px;
      padding: 32px 20px;
      text-align: center;
      cursor: pointer;
      text-decoration: none;
      color: #1e293b;
      transition: border-color .15s, transform .1s, box-shadow .15s;
      display: block;
    }
    .ks-kachel:hover { border-color: #2563eb; transform: translateY(-3px); box-shadow: 0 6px 20px rgba(37,99,235,.12); }
    .ks-kachel-icon  { font-size: 48px; display: block; margin-bottom: 14px; }
    .ks-kachel-label { font-size: 17px; font-weight: 700; color: #1e3a5f; }
    .ks-kachel-sub   { font-size: 12px; color: #64748b; margin-top: 5px; }
    .ks-kachel.disabled { opacity:.4; cursor:not-allowed; pointer-events:none; }

    /* ── Buttons ─────────────────────────────────────────────────────────── */
    .ks-btn {
      border: none; border-radius: 8px; padding: 10px 22px;
      font-size: 14px; font-weight: 700; cursor: pointer;
      text-decoration: none; display: inline-block; text-align: center;
      font-family: inherit;
    }
    .ks-btn:disabled { opacity:.4; cursor:not-allowed; }
    .ks-btn-primary   { background: #2563eb; color: #fff; }
    .ks-btn-primary:hover   { background: #1d4ed8; }
    .ks-btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .ks-btn-secondary:hover { background: #e2e8f0; }
    .ks-btn-success   { background: #16a34a; color: #fff; }
    .ks-btn-success:hover   { background: #15803d; }
    .ks-btn-danger    { background: #dc2626; color: #fff; }
    .ks-btn-danger:hover    { background: #b91c1c; }
    .ks-btn-lg { font-size: 18px; padding: 15px 32px; }

    /* ── Cards ───────────────────────────────────────────────────────────── */
    .ks-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 16px;
    }
    .ks-card-title { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .7px; margin-bottom: 12px; }

    /* ── Tabelle ─────────────────────────────────────────────────────────── */
    .ks-table { width: 100%; border-collapse: collapse; }
    .ks-table th {
      background: #f8fafc; color: #64748b;
      font-size: 11px; text-align: left;
      padding: 9px 12px; text-transform: uppercase; letter-spacing: .5px;
      border-bottom: 2px solid #e2e8f0;
    }
    .ks-table td { padding: 11px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
    .ks-table tr:hover td { background: #f8fafc; }
    .ks-table tr:last-child td { border-bottom: none; }

    /* ── Inputs ──────────────────────────────────────────────────────────── */
    .ks-input {
      background: #f8fafc;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      color: #1e293b;
      font-size: 15px;
      padding: 9px 13px;
      outline: none;
      width: 100%;
      font-family: inherit;
    }
    .ks-input:focus { border-color: #2563eb; background: #fff; }
    .ks-select {
      background: #f8fafc;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      color: #1e293b;
      font-size: 14px;
      padding: 8px 10px;
      outline: none;
      width: 100%;
      font-family: inherit;
    }
    .ks-select:focus { border-color: #2563eb; }

    /* ── Overlay ─────────────────────────────────────────────────────────── */
    .ks-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 200;
      align-items: center; justify-content: center;
    }
    .ks-overlay.aktiv { display: flex; }
    .ks-overlay-box {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 34px 38px;
      min-width: 360px;
      max-width: 540px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0,0,0,.2);
    }
    .ks-overlay-titel { font-size: 19px; font-weight: 700; margin-bottom: 20px; color: #1e3a5f; }

    /* ── Feedback ────────────────────────────────────────────────────────── */
    .ks-feedback { padding: 10px 16px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; font-weight: 500; }
    .ks-feedback.ok     { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
    .ks-feedback.fehler { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
    .ks-feedback.info   { background: #eff6ff; color: #1d4ed8; border: 1px solid #93c5fd; }

    /* ── Hilfeklassen ────────────────────────────────────────────────────── */
    .text-muted { color: #64748b; }
    .text-small { font-size: 12px; }
    .badge {
      display: inline-block; padding: 2px 8px; border-radius: 10px;
      font-size: 11px; font-weight: 700;
    }
    .badge-gruen  { background: #f0fdf4; color: #166534; }
    .badge-rot    { background: #fef2f2; color: #991b1b; }
    .badge-blau   { background: #eff6ff; color: #1d4ed8; }
    .badge-grau   { background: #f8fafc; color: #475569; }
    .badge-amber  { background: #fffbeb; color: #92400e; }
  </style>
</head>
<body>
<div class="ks-header">
  <div class="ks-header-left">
    <div class="ks-header-title">
      KASSE
      <?php if (!empty($kasseInfo)): ?>
        <span style="font-size:13px;color:#93c5fd;font-weight:400;margin-left:6px"><?= htmlspecialchars($kasseInfo['kasse_nr'] ?? '') ?></span>
      <?php endif; ?>
    </div>
    <?php if (!empty($headerSub)): ?>
      <div class="ks-header-sub"><?= htmlspecialchars($headerSub) ?></div>
    <?php endif; ?>
  </div>
  <div class="ks-nav">
    <?php $cur = $activeKasseNav ?? ''; ?>
    <a href="/mealana/kasse/bon.php"          class="<?= $cur === 'bon'     ? 'aktiv' : '' ?>">🛒 Kassieren</a>
    <a href="/mealana/kasse/offene_auswahl.php" class="<?= $cur === 'oa'   ? 'aktiv' : '' ?>">↗ Mitgeben</a>
    <a href="/mealana/kasse/kassenbuch.php"   class="<?= $cur === 'kb'     ? 'aktiv' : '' ?>">💰 Kassenbuch</a>
    <a href="/mealana/kasse/kassensturz.php"  class="<?= $cur === 'ks'     ? 'aktiv' : '' ?>">📊 Kassenstand</a>
    <a href="/mealana/kasse/bon_journal.php"  class="<?= $cur === 'journal' ? 'aktiv' : '' ?>">📋 Journal</a>
    <div class="ks-nav-sep"></div>
    <a href="/mealana/start.php" class="ks-back">→ Start</a>
  </div>
  <div class="ks-user">👤 <?= htmlspecialchars($_SESSION['benutzer']['formularname'] ?? '') ?></div>
</div>
<div class="ks-main">
