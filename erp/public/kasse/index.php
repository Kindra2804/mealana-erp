<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';

// Anders als die übrigen Kasse-Seiten: KEIN Redirect bei aktuelleKasseId()===null,
// sonst Redirect-Schleife auf sich selbst. Diese Seite trägt ja gerade den JS-Flow
// (Overlay), der die fehlende Bindung erst herstellt — bis dahin läuft die Seite
// einfach mit "keine Kasse gewählt"-Platzhaltern weiter.
$service   = new KassenService();
$aktuelleKasseId = (new ArbeitsplatzService())->aktuelleKasseId();
$kasseInfo = $aktuelleKasseId !== null ? $service->getKasse($aktuelleKasseId) : null;
$pageTitle = 'Kasse — Startportal';
require_once __DIR__ . '/shell_top.php';

$istMesse = ($kasseInfo['modus'] ?? 'online') === 'offline';
?>

<div style="text-align:center;margin:24px 0 28px">
  <div style="display:inline-block;background:#fff;border-radius:12px;padding:10px 18px 6px;margin-bottom:10px;box-shadow:0 4px 20px rgba(0,0,0,.08)">
    <img src="<?= BASE_PATH ?>/img/nahtlos.png" alt="NahtlOS" style="width:110px;height:auto;display:block">
  </div>
  <div style="font-size:16px;color:#888;margin-top:6px;display:flex;align-items:center;justify-content:center;gap:12px">
    <span><?= htmlspecialchars($kasseInfo['name'] ?? 'Keine Kasse zugeordnet') ?> · <?= date('d.m.Y') ?></span>
    <?php if ($istMesse): ?>
      <span style="font-size:12px;font-weight:700;padding:3px 12px;border-radius:12px;background:#fff3e0;color:#e67e22;letter-spacing:.5px">MESSEBETRIEB</span>
    <?php else: ?>
      <span style="font-size:12px;font-weight:700;padding:3px 12px;border-radius:12px;background:#e8f5e9;color:#2e7d32;letter-spacing:.5px">ONLINE</span>
    <?php endif; ?>
  </div>
</div>

<?php if ($aktuelleKasseId === null): ?>
<!-- Noch keine Kasse gebunden: KEINE Kacheln zeigen, sonst könnte man vor Abschluss
     der Arbeitsplatz-Erkennung (kasse_arbeitsplatz.js) schon in bon.php landen.
     Die JS-Erkennung lädt die Seite neu, sobald die Bindung steht. -->
<div id="ap-warte" style="max-width:520px;margin:0 auto;text-align:center;padding:24px;background:#fff;border-radius:12px;color:#64748b;font-size:15px">
  Arbeitsplatz wird erkannt …
</div>
<?php else: ?>
<div class="ks-kacheln" style="max-width:900px;margin:0 auto">

  <a href="<?= BASE_PATH ?>/kasse/bon.php" class="ks-kachel">
    <span class="ks-kachel-icon">🛒</span>
    <div class="ks-kachel-label">Kassieren</div>
    <div class="ks-kachel-sub">Artikel scannen · Bar · Karte · Gutschein</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/offene_auswahl.php" class="ks-kachel">
    <span class="ks-kachel-icon">↗️</span>
    <div class="ks-kachel-label">Mitgeben</div>
    <div class="ks-kachel-sub">Artikel zur Ansicht · Farbvergleich</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/kassenbuch.php" class="ks-kachel">
    <span class="ks-kachel-icon">💰</span>
    <div class="ks-kachel-label">Kassenbuch</div>
    <div class="ks-kachel-sub">Einlage · Entnahme</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/kassensturz.php" class="ks-kachel">
    <span class="ks-kachel-icon">📊</span>
    <div class="ks-kachel-label">Kassenstand</div>
    <div class="ks-kachel-sub">X-Bon · Z-Bon · Zählhilfe</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/bon_journal.php" class="ks-kachel">
    <span class="ks-kachel-icon">📋</span>
    <div class="ks-kachel-label">Bon-Journal</div>
    <div class="ks-kachel-sub">Alle Bons · Suche · Storno</div>
  </a>

  <a href="<?= BASE_PATH ?>/kasse/kassen_einstellungen.php" class="ks-kachel">
    <span class="ks-kachel-icon">⚙</span>
    <div class="ks-kachel-label">Einstellungen</div>
    <div class="ks-kachel-sub">Ausgabeformat · Modus</div>
  </a>

</div>
<?php endif; ?>

<div style="text-align:center;margin-top:28px">
  <a href="<?= BASE_PATH ?>/start.php" style="color:#444;font-size:13px;text-decoration:none">→ Startseite</a>
  &nbsp;&nbsp;·&nbsp;&nbsp;
  <a href="<?= BASE_PATH ?>/logout.php" style="color:#444;font-size:13px;text-decoration:none">Abmelden</a>
  &nbsp;&nbsp;·&nbsp;&nbsp;
  <span style="color:#bbb;font-size:12px">v<?= htmlspecialchars(APP_VERSION) ?></span>
</div>

<!-- Arbeitsplatz-Auswahl: "Welcher Arbeitsplatz bist du?" (kein Token / unbekannter Token) -->
<div id="ap-ov-auswahl" class="ks-overlay">
  <div class="ks-overlay-box">
    <div class="ks-overlay-titel">Welcher Arbeitsplatz bist du?</div>
    <div id="ap-kassen-liste" style="display:flex;flex-direction:column;gap:10px;margin-bottom:6px"></div>
    <div id="ap-sonstiges-felder" style="display:none;margin:6px 0 14px 24px;display:flex;flex-direction:column;gap:8px">
      <select id="ap-typ" class="ks-select">
        <option value="lager">Lager</option>
        <option value="buero">Büro</option>
        <option value="mobil">Mobil</option>
      </select>
      <input type="text" id="ap-name" class="ks-input" placeholder="Bezeichnung, z.B. Lager-Scanner">
    </div>
    <div id="ap-fehler" class="ks-feedback fehler" style="display:none"></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px">
      <a href="<?= BASE_PATH ?>/kasse/kassen_einstellungen.php" style="font-size:12px;color:#64748b;text-decoration:none">⚙ Zu den Kassen-Einstellungen</a>
      <button type="button" class="ks-btn ks-btn-primary" onclick="apAuswahlBestaetigen()">Übernehmen</button>
    </div>
  </div>
</div>

<!-- Kollision: Arbeitsplatz woanders noch aktiv -->
<div id="ap-ov-kollision" class="ks-overlay">
  <div class="ks-overlay-box">
    <div class="ks-overlay-titel">⚠ Arbeitsplatz bereits in Verwendung</div>
    <p id="ap-kollision-text" class="text-muted" style="margin:0 0 16px"></p>
    <p style="font-size:13px;margin:0 0 8px">Bitte dort zuerst abmelden — oder mit Manager-PIN übernehmen:</p>
    <input type="password" id="ap-kollision-pin" inputmode="numeric" pattern="\d{4,6}" maxlength="6"
           placeholder="PIN" autocomplete="off" class="ks-input" style="max-width:140px;text-align:center;letter-spacing:6px">
    <div id="ap-kollision-fehler" class="ks-feedback fehler" style="display:none;margin-top:8px"></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px">
      <a href="<?= BASE_PATH ?>/kasse/kassen_einstellungen.php" style="font-size:12px;color:#64748b;text-decoration:none">⚙ Zu den Kassen-Einstellungen</a>
      <button type="button" class="ks-btn ks-btn-primary" onclick="apKollisionUebernehmen()">Übernehmen</button>
    </div>
  </div>
</div>

<!-- Geräte-Sperre: Signatur-Kasse am falschen Gerät / Signatur-Gerät als falsche Kasse -->
<div id="ap-ov-gesperrt" class="ks-overlay">
  <div class="ks-overlay-box">
    <div class="ks-overlay-titel">🔒 Kasse an diesem Gerät gesperrt</div>
    <p id="ap-gesperrt-text" style="margin:0 0 16px;font-size:14px"></p>
    <div id="ap-gesperrt-binden" style="display:none">
      <p style="font-size:13px;margin:0 0 8px">Dieses Gerät mit Manager-PIN wieder als <strong id="ap-gesperrt-kasse"></strong> verbinden:</p>
      <input type="password" id="ap-gesperrt-pin" inputmode="numeric" pattern="\d{4,6}" maxlength="6"
             placeholder="PIN" autocomplete="off" class="ks-input" style="max-width:140px;text-align:center;letter-spacing:6px">
      <div id="ap-gesperrt-fehler" class="ks-feedback fehler" style="display:none;margin-top:8px"></div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px">
      <a href="<?= BASE_PATH ?>/start.php" style="font-size:12px;color:#64748b;text-decoration:none">→ Zur ERP-Startseite</a>
      <button type="button" id="ap-gesperrt-btn" class="ks-btn ks-btn-primary" style="display:none" onclick="apSignaturGeraetBinden()">Verbinden</button>
    </div>
  </div>
</div>

<script>window.AP_KASSE_GEBUNDEN = <?= $aktuelleKasseId !== null ? 'true' : 'false' ?>;</script>
<script src="<?= BASE_PATH ?>/js/kasse_arbeitsplatz.js"></script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
