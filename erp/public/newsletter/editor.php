<?php
// Newsletter-Editor: Bausteine links, Vorschau rechts (iframe), Testmail. JS: js/newsletter_editor.js
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterService.php';

$svc = new NewsletterService();
$n   = $svc->laden((int)($_GET['id'] ?? 0));
if (!$n) { $_SESSION['fehler'] = 'Newsletter nicht gefunden.'; header('Location: liste.php'); exit; }
$gesperrt = $n['status'] !== 'entwurf';
$meineMail = (string)(Database::getInstance()->query("SELECT email FROM benutzer WHERE id = " . (int)$_SESSION['benutzer']['id'])->fetchColumn() ?: '');

$pageTitle        = 'Newsletter: ' . $n['titel'];
$activeModule     = 'kunden';
$actionBarContent = '<a href="liste.php" class="btn btn-secondary btn-sm">← Newsletter</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<style>
  .nle-grid { display: grid; grid-template-columns: minmax(380px, 1fr) 640px; gap: 16px; align-items: start; }
  .nle-block { border: 1px solid var(--color-border); border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; background: #fff; }
  .nle-kopf { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-weight: 600; font-size: 13px; }
  .nle-kopf button { margin-left: 4px; }
  .nle-block label { display: block; font-size: 12px; color: var(--color-text-muted); margin-top: 6px; }
  .nle-block input[type=text], .nle-block textarea { width: 100%; box-sizing: border-box; }
  .nle-art { display: flex; justify-content: space-between; gap: 6px; font-size: 12px; padding: 3px 0; border-bottom: 1px solid #f1f5f9; }
  .nle-treffer { border: 1px solid var(--color-border); border-radius: 6px; max-height: 180px; overflow-y: auto; margin-top: 4px; display: none; background: #fff; }
  .nle-treffer div { padding: 5px 8px; cursor: pointer; font-size: 12px; } .nle-treffer div:hover { background: #eff6ff; }
  .nle-neu button { margin: 0 4px 6px 0; }
  @media (max-width: 1150px) { .nle-grid { grid-template-columns: 1fr; } }
</style>

<div class="nle-grid">
  <div>
    <div class="card" style="padding:12px 16px;margin-bottom:12px">
      <?php if ($gesperrt): ?><div style="color:var(--color-danger);font-size:13px;margin-bottom:8px">Dieser Newsletter ist verschickt und kann nicht mehr geändert werden — „Kopieren“ in der Liste.</div><?php endif; ?>
      <div style="font-size:13px;color:var(--color-text-muted);margin-bottom:6px">Shop: <strong><?= htmlspecialchars($n['shop_name']) ?></strong></div>
      <label class="form-label">Interner Name</label><input type="text" id="nle-titel" class="erp-input" value="<?= htmlspecialchars($n['titel']) ?>" style="width:100%">
      <label class="form-label" style="margin-top:8px">Betreff</label><input type="text" id="nle-betreff" class="erp-input" maxlength="200" value="<?= htmlspecialchars($n['betreff'] ?? '') ?>" style="width:100%" placeholder="z.B. Neue Wolle für kalte Tage">
      <label class="form-label" style="margin-top:8px">Vorschautext <span style="font-weight:400;color:var(--color-text-muted)">(steht im Postfach neben dem Betreff)</span></label>
      <input type="text" id="nle-vorschau" class="erp-input" maxlength="200" value="<?= htmlspecialchars($n['vorschautext'] ?? '') ?>" style="width:100%">
    </div>

    <div id="nle-bausteine"></div>

    <?php if (!$gesperrt): ?>
    <div class="card nle-neu" style="padding:10px 12px">
      <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:6px">Baustein hinzufügen:</div>
      <button class="btn btn-sm" onclick="nleNeu('kopfbild')">🖼 Kopfbild</button>
      <button class="btn btn-sm" onclick="nleNeu('text')">📝 Text</button>
      <button class="btn btn-sm" onclick="nleNeu('bild')">🏞 Bild</button>
      <button class="btn btn-sm" onclick="nleNeu('artikel')">🧶 Artikel</button>
      <button class="btn btn-sm" onclick="nleNeu('knopf')">🔘 Knopf</button>
      <button class="btn btn-sm" onclick="nleNeu('hinweis')">🎁 Aktion/Gutschein</button>
      <button class="btn btn-sm" onclick="nleNeu('trenner')">➖ Trenner</button>
      <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">Im Text: <code>{vorname}</code> wird zum Vornamen (fehlt er, fällt es weg), <code>**fett**</code>, Leerzeile = neuer Absatz.</div>
    </div>
    <?php endif; ?>
  </div>

  <div style="position:sticky;top:10px">
    <div class="card" style="padding:10px 12px;margin-bottom:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php if (!$gesperrt): ?><button class="btn btn-primary btn-sm" onclick="nleSpeichern()" id="nle-speichern-btn">💾 Speichern + Vorschau</button><?php endif; ?>
      <span id="nle-status" style="font-size:12px;color:var(--color-text-muted)"></span>
      <span style="flex:1"></span>
      <input type="email" id="nle-test-an" class="erp-input" value="<?= htmlspecialchars($meineMail) ?>" placeholder="Testmail an …" style="width:200px">
      <button class="btn btn-sm" onclick="nleTestmail()">✉ Testmail</button>
    </div>
    <iframe id="nle-vorschau-frame" src="vorschau.php?id=<?= (int)$n['id'] ?>" style="width:640px;max-width:100%;height:78vh;border:1px solid var(--color-border);border-radius:8px;background:#f3f4f6"></iframe>
  </div>
</div>

<script>
  window.NLE = { id: <?= (int)$n['id'] ?>, gesperrt: <?= $gesperrt ? 'true' : 'false' ?>, bausteine: <?= json_encode($n['bausteine'], JSON_UNESCAPED_UNICODE) ?> };
</script>
<script src="<?= BASE_PATH ?>/js/newsletter_editor.js?v=<?= filemtime(__DIR__ . '/../js/newsletter_editor.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
