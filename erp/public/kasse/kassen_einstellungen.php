<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$service = new KassenService();
$db      = Database::getInstance();

// POST: Einstellung speichern
$meldung = '';
$fehler  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kasse_id'])) {
    $id     = (int)$_POST['kasse_id'];
    $format = in_array($_POST['ausgabe_format'] ?? '', ['fragen','80mm','a4']) ? $_POST['ausgabe_format'] : 'fragen';
    $modus  = in_array($_POST['modus'] ?? '', ['online','offline']) ? $_POST['modus'] : 'online';
    $db->prepare("UPDATE kassen SET ausgabe_format = :f, modus = :m WHERE id = :id")
       ->execute([':f' => $format, ':m' => $modus, ':id' => $id]);
    $meldung = 'Einstellungen gespeichert.';
}

// Alle Kassen laden
$kassen = $db->query("SELECT * FROM kassen ORDER BY id")->fetchAll();

$pageTitle = 'Kassen-Einstellungen';
require_once __DIR__ . '/shell_top.php';
?>

<div style="max-width:700px;margin:30px auto;padding:0 16px">

<?php if ($meldung): ?>
  <div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:12px 16px;margin-bottom:20px;color:#166534;font-weight:600">
    ✓ <?= htmlspecialchars($meldung) ?>
  </div>
<?php endif; ?>

<?php foreach ($kassen as $k): ?>
<div class="card" style="margin-bottom:24px">
  <div style="font-size:16px;font-weight:700;color:#1e3a5f;margin-bottom:16px">
    ⚙ Kasse <?= (int)$k['id'] ?>: <?= htmlspecialchars($k['name'] ?? '') ?>
    <span style="font-size:11px;font-weight:400;color:#64748b;margin-left:8px">(<?= htmlspecialchars($k['kasse_nr'] ?? '') ?>)</span>
  </div>
  <form method="post">
    <input type="hidden" name="kasse_id" value="<?= (int)$k['id'] ?>">

    <div style="margin-bottom:16px">
      <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px">BON-AUSGABEFORMAT</label>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ([
            'fragen' => ['🖨 / 📄  Nach jeder Zahlung fragen', 'Auswahl-Dialog: 80mm Bon · A4 Rechnung · Ohne Druck'],
            '80mm'   => ['🖨  Immer 80mm Thermobondruck', 'Direkt drucken, kein Dialog — z.B. Messe-Kasse'],
            'a4'     => ['📄  Immer A4-Rechnung', 'Öffnet A4-PDF — z.B. reine B2B-Kasse'],
        ] as $val => [$label, $desc]): ?>
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 14px;border-radius:8px;border:1.5px solid <?= ($k['ausgabe_format'] ?? 'fragen') === $val ? '#2563eb' : '#e2e8f0' ?>;background:<?= ($k['ausgabe_format'] ?? 'fragen') === $val ? '#eff6ff' : '#fff' ?>">
          <input type="radio" name="ausgabe_format" value="<?= $val ?>" <?= ($k['ausgabe_format'] ?? 'fragen') === $val ? 'checked' : '' ?> style="margin-top:2px">
          <div>
            <div style="font-weight:600;font-size:13px"><?= $label ?></div>
            <div style="font-size:11px;color:#64748b;margin-top:2px"><?= $desc ?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="margin-bottom:16px">
      <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px">BETRIEBSMODUS</label>
      <div style="display:flex;gap:10px">
        <label style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:8px;border:1.5px solid <?= ($k['modus'] ?? 'online') === 'online' ? '#16a34a' : '#e2e8f0' ?>;background:<?= ($k['modus'] ?? 'online') === 'online' ? '#f0fdf4' : '#fff' ?>;cursor:pointer">
          <input type="radio" name="modus" value="online" <?= ($k['modus'] ?? 'online') === 'online' ? 'checked' : '' ?>>
          <div><div style="font-weight:600;font-size:13px">🟢 Online</div><div style="font-size:11px;color:#64748b">Normalbetrieb</div></div>
        </label>
        <label style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:8px;border:1.5px solid <?= ($k['modus'] ?? 'online') === 'offline' ? '#f59e0b' : '#e2e8f0' ?>;background:<?= ($k['modus'] ?? 'online') === 'offline' ? '#fffbeb' : '#fff' ?>;cursor:pointer">
          <input type="radio" name="modus" value="offline" <?= ($k['modus'] ?? 'online') === 'offline' ? 'checked' : '' ?>>
          <div><div style="font-weight:600;font-size:13px">🟡 Messebetrieb</div><div style="font-size:11px;color:#64748b">Offline-Modus (kein A4)</div></div>
        </label>
      </div>
    </div>

    <div style="text-align:right">
      <button type="submit" class="btn btn-primary">✓ Speichern</button>
    </div>
  </form>
</div>
<?php endforeach; ?>

<div style="margin-top:10px">
  <a href="/mealana/kasse/index.php" style="color:#64748b;font-size:13px;text-decoration:none">← Zurück zur Kasse</a>
</div>

</div>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
