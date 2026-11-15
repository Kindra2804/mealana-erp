<?php
// Newsletter-Editor: Bausteine links, Vorschau rechts (iframe), Testmail. JS: js/newsletter_editor.js
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterService.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterVersandService.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterSegmentService.php';

$svc = new NewsletterService();
$n   = $svc->laden((int)($_GET['id'] ?? 0));
if (!$n) { $_SESSION['fehler'] = 'Newsletter nicht gefunden.'; header('Location: liste.php'); exit; }
$gesperrt = $n['status'] !== 'entwurf';
$vsvc = new NewsletterVersandService();
$fort = $gesperrt ? $vsvc->fortschritt((int)$n['id']) : null;
$links = $gesperrt ? $vsvc->links((int)$n['id']) : [];
$segAn  = NewsletterSegmentService::aktiv() || $n['segment'];   // gespeichertes Segment immer zeigen
$segSvc = new NewsletterSegmentService();
$segAusw = $segAn ? $segSvc->auswahl() : null;
$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
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

<?php if ($erfolg): ?><div class="card" style="border-left:3px solid var(--color-success);padding:10px 16px;margin-bottom:12px;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></div><?php endif; ?>
<?php if ($fehler): ?><div class="card" style="border-left:3px solid var(--color-danger);padding:10px 16px;margin-bottom:12px;color:var(--color-danger)"><?= htmlspecialchars($fehler) ?></div><?php endif; ?>

<?php if ($gesperrt): $p = $fort; $proz = $p['gesamt'] ? round(($p['gesendet'] + $p['fehler'] + $p['uebersprungen']) / $p['gesamt'] * 100) : 100; ?>
<div class="card" style="padding:12px 16px;margin-bottom:12px">
  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <strong style="font-size:15px"><?= $n['status'] === 'versendet' ? '✅ Verschickt' : ($n['versand_pausiert'] ? '⏸ Angehalten' : '📤 Wird verschickt') ?></strong>
    <span><?= $p['gesendet'] ?> von <?= $p['gesamt'] ?> verschickt</span>
    <?php if ($p['offen']): ?><span style="color:var(--color-text-muted)"><?= $p['offen'] ?> offen · ca. <?= max(1, (int)ceil($p['offen'] / NewsletterVersandService::maxProStunde())) ?> Std.</span><?php endif; ?>
    <?php if ($p['fehler']): ?><span style="color:var(--color-danger)"><?= $p['fehler'] ?> Fehler</span><?php endif; ?>
    <?php if ($p['uebersprungen']): ?><span style="color:var(--color-text-muted)"><?= $p['uebersprungen'] ?> übersprungen</span><?php endif; ?>
    <span>🖱 <?= (int)$n['klicks'] ?> Klicks<?= $n['klicks_am'] ? ' <span style="color:var(--color-text-muted);font-size:12px">(Stand ' . date('d.m. H:i', strtotime($n['klicks_am'])) . ')</span>' : '' ?></span>
    <span style="flex:1"></span>
    <?php if ($n['status'] === 'versand_laeuft'): ?>
      <form method="post" action="editor_aktion.php" style="display:inline"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="aktion" value="<?= $n['versand_pausiert'] ? 'fortsetzen' : 'pausieren' ?>"><button class="btn btn-sm"><?= $n['versand_pausiert'] ? '▶ Fortsetzen' : '⏸ Anhalten' ?></button></form>
      <form method="post" action="editor_aktion.php" style="display:inline" onsubmit="return confirm('Versand beenden? Wer den Newsletter noch nicht bekommen hat, bekommt ihn nicht mehr.')"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="aktion" value="abbrechen"><button class="btn btn-sm">Versand beenden</button></form>
    <?php endif; ?>
  </div>
  <div style="height:8px;background:#e5e7eb;border-radius:4px;margin-top:10px;overflow:hidden"><div style="height:100%;width:<?= $proz ?>%;background:var(--color-success)"></div></div>
  <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
    Gestartet <?= date('d.m.Y H:i', strtotime($n['versand_start_am'])) ?><?= $n['versand_ende_am'] ? ' · fertig ' . date('d.m.Y H:i', strtotime($n['versand_ende_am'])) : '' ?>
    · höchstens <?= NewsletterVersandService::maxProStunde() ?> Mails pro Stunde je Postfach (Cron alle 5 Minuten)
  </div>
  <?php if ($links): ?>
  <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13px">Klicks je Link</summary>
    <table class="erp-table" style="width:100%;margin-top:6px;font-size:12px">
      <?php foreach ($links as $l): ?><tr><td style="word-break:break-all"><?= htmlspecialchars($l['url']) ?></td><td style="text-align:right;width:70px"><?= (int)$l['klicks'] ?></td></tr><?php endforeach; ?>
    </table>
    <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">Gezählt wird nur die Summe je Link — nicht, wer geklickt hat. Manche Mailprogramme prüfen Links automatisch, das kann ein paar Klicks dazuzählen.</div>
  </details>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="nle-grid">
  <div>
    <div class="card" style="padding:12px 16px;margin-bottom:12px">
      <?php if ($gesperrt): ?><div style="color:var(--color-text-muted);font-size:13px;margin-bottom:8px">Ein gestarteter Newsletter kann nicht mehr geändert werden — für einen neuen „Kopieren“ in der Liste.</div><?php endif; ?>
      <div style="font-size:13px;color:var(--color-text-muted);margin-bottom:6px">Shop: <strong><?= htmlspecialchars($n['shop_name']) ?></strong></div>
      <label class="form-label">Interner Name</label><input type="text" id="nle-titel" class="erp-input" value="<?= htmlspecialchars($n['titel']) ?>" style="width:100%">
      <label class="form-label" style="margin-top:8px">Betreff</label><input type="text" id="nle-betreff" class="erp-input" maxlength="200" value="<?= htmlspecialchars($n['betreff'] ?? '') ?>" style="width:100%" placeholder="z.B. Neue Wolle für kalte Tage">
      <label class="form-label" style="margin-top:8px">Vorschautext <span style="font-weight:400;color:var(--color-text-muted)">(steht im Postfach neben dem Betreff)</span></label>
      <input type="text" id="nle-vorschau" class="erp-input" maxlength="200" value="<?= htmlspecialchars($n['vorschautext'] ?? '') ?>" style="width:100%">
    </div>

    <?php if ($segAn): $sg = $n['segment'] ?? []; $dis = ($gesperrt || !NewsletterSegmentService::aktiv()) ? 'disabled' : ''; ?>
    <details class="card" style="padding:10px 16px;margin-bottom:12px" <?= $n['segment'] ? 'open' : '' ?> id="nle-segment">
      <summary style="cursor:pointer;font-weight:600;font-size:13px">🎯 Empfänger: <span id="nle-seg-text"><?= htmlspecialchars($segSvc->beschreibung($n['segment'])) ?></span></summary>
      <div style="font-size:12px;color:var(--color-text-muted);margin:6px 0">Alle gesetzten Bedingungen müssen zutreffen. Leer = egal. Mehrfachauswahl mit Strg-Taste.</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 14px;font-size:12px">
        <label>Kundenkonto<br><select class="erp-select" data-seg="kunde" <?= $dis ?>>
          <option value="">egal</option><option value="kunden" <?= ($sg['kunde'] ?? '') === 'kunden' ? 'selected' : '' ?>>nur Kunden (haben schon bestellt/Konto)</option>
          <option value="nicht_kunden" <?= ($sg['kunde'] ?? '') === 'nicht_kunden' ? 'selected' : '' ?>>nur Nicht-Kunden</option></select></label>
        <label>Land (Kürzel, mit Komma)<br><input type="text" class="erp-input" data-seg="laender" placeholder="z.B. AT, DE" value="<?= htmlspecialchars(implode(', ', $sg['laender'] ?? [])) ?>" <?= $dis ?>></label>
        <label>Kundengruppe<br><select class="erp-select" data-seg="kundengruppen" multiple size="3" style="width:100%" <?= $dis ?>>
          <?php foreach ($segAusw['kundengruppen'] as $k): ?><option value="<?= (int)$k['id'] ?>" <?= in_array((int)$k['id'], $sg['kundengruppen'] ?? [], true) ? 'selected' : '' ?>><?= htmlspecialchars($k['name']) ?></option><?php endforeach; ?></select></label>
        <label>Angemeldet seit<br><input type="date" class="erp-input" data-seg="angemeldet_seit" value="<?= htmlspecialchars($sg['angemeldet_seit'] ?? '') ?>" <?= $dis ?>></label>
        <label>Gekauft aus Kategorie (inkl. Unterkategorien)<br><select class="erp-select" data-seg="kategorien" multiple size="6" style="width:100%" <?= $dis ?>>
          <?php foreach ($segAusw['kategorien'] as $k): ?><option value="<?= (int)$k['id'] ?>" <?= in_array((int)$k['id'], $sg['kategorien'] ?? [], true) ? 'selected' : '' ?>><?= htmlspecialchars($k['name']) ?></option><?php endforeach; ?></select></label>
        <label>Gekauft von Hersteller<br><select class="erp-select" data-seg="hersteller" multiple size="6" style="width:100%" <?= $dis ?>>
          <?php foreach ($segAusw['hersteller'] as $k): ?><option value="<?= (int)$k['id'] ?>" <?= in_array((int)$k['id'], $sg['hersteller'] ?? [], true) ? 'selected' : '' ?>><?= htmlspecialchars($k['name']) ?></option><?php endforeach; ?></select></label>
        <label>… in den letzten Monaten<br><input type="number" min="1" max="240" class="erp-input" data-seg="kauf_monate" value="<?= (int)($sg['kauf_monate'] ?? 24) ?>" style="width:90px" <?= $dis ?>></label>
        <label>Letzter Kauf<br><select class="erp-select" data-seg="letzter_kauf" <?= $dis ?>>
          <option value="">egal</option><option value="innerhalb" <?= ($sg['letzter_kauf'] ?? '') === 'innerhalb' ? 'selected' : '' ?>>innerhalb der letzten</option>
          <option value="nicht_innerhalb" <?= ($sg['letzter_kauf'] ?? '') === 'nicht_innerhalb' ? 'selected' : '' ?>>länger her als (Schläfer)</option></select>
          <input type="number" min="1" max="240" class="erp-input" data-seg="letzter_monate" value="<?= (int)($sg['letzter_monate'] ?? 12) ?>" style="width:70px" <?= $dis ?>> Monate</label>
      </div>
      <?php if (!$dis): ?><div style="margin-top:8px"><button class="btn btn-sm" type="button" onclick="nleSegZaehlen()">Empfänger zählen</button> <span id="nle-seg-zahl" style="font-size:13px"></span></div><?php endif; ?>
    </details>
    <?php endif; ?>

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
      <?php if (!$gesperrt): ?><button class="btn btn-primary btn-sm" onclick="nleVersand()" title="Erst nach einer Testmail möglich">🚀 Versand starten</button><?php endif; ?>
    </div>
    <iframe id="nle-vorschau-frame" src="vorschau.php?id=<?= (int)$n['id'] ?>" style="width:640px;max-width:100%;height:78vh;border:1px solid var(--color-border);border-radius:8px;background:#f3f4f6"></iframe>
  </div>
</div>

<script>
  window.NLE = { id: <?= (int)$n['id'] ?>, shopId: <?= (int)$n['shop_id'] ?>, segmente: <?= $segAn && NewsletterSegmentService::aktiv() && !$gesperrt ? 'true' : 'false' ?>, gesperrt: <?= $gesperrt ? 'true' : 'false' ?>, bausteine: <?= json_encode($n['bausteine'], JSON_UNESCAPED_UNICODE) ?> };
</script>
<script src="<?= BASE_PATH ?>/js/newsletter_editor.js?v=<?= filemtime(__DIR__ . '/../js/newsletter_editor.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
