<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$service   = new KassenService();
$lagerSvc  = new LagerService();
$kasseInfo = $service->getKasse(1);
$lagerId   = (int)($kasseInfo['lager_id'] ?? 1);
$kasseId   = (int)($kasseInfo['id'] ?? 1);
$alleLager = $lagerSvc->getAlleLager();

$offene    = $service->getOffeneAuswahl('offen');
$erledigte = $service->getOffeneAuswahl('zurueck');

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);

$pageTitle    = 'Offene Auswahl — Mitgeben';
$activeKasseNav = 'oa';
require_once __DIR__ . '/shell_top.php';
?>

<style>
.oa-karte {
  background: #0d1b2a;
  border: 1px solid #1a3a5c;
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 10px;
}
.oa-karte.ueberfaellig { border-color: #c0392b; }
.oa-pos-chip {
  display: inline-block;
  background: #071018;
  border: 1px solid #1a3a5c;
  border-radius: 5px;
  padding: 3px 10px;
  font-size: 12px;
  color: #aaa;
  margin: 2px;
}
</style>

<?php if ($erfolg): ?>
  <div class="ks-feedback ok" style="max-width:900px;margin:0 auto 12px"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>
<?php if ($fehler): ?>
  <div class="ks-feedback fehler" style="max-width:900px;margin:0 auto 12px"><?= htmlspecialchars($fehler) ?></div>
<?php endif; ?>

<div style="max-width:900px;margin:0 auto">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 style="margin:0;font-size:18px;color:#e67e22">↗ Offene Auswahl</h2>
    <button class="ks-btn ks-btn-primary" onclick="neuDialog()">+ Neu mitgeben</button>
  </div>

  <!-- Offene Auswahl -->
  <?php if (empty($offene)): ?>
    <div class="ks-card" style="text-align:center;color:#444;padding:40px">Keine offenen Mitgaben.</div>
  <?php else: ?>
    <?php foreach ($offene as $oa):
      $ueberfaellig = $oa['rueckgabe_bis'] && $oa['rueckgabe_bis'] < date('Y-m-d');
    ?>
    <div class="oa-karte <?= $ueberfaellig ? 'ueberfaellig' : '' ?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-size:16px;font-weight:700;color:#eee">
            <?= htmlspecialchars($oa['kunden_name'] ?: 'Laufkunde') ?>
            <?php if ($ueberfaellig): ?>
              <span style="background:#c0392b;color:#fff;font-size:11px;padding:2px 8px;border-radius:4px;margin-left:6px">ÜBERFÄLLIG</span>
            <?php endif; ?>
          </div>
          <div style="font-size:12px;color:#888;margin-top:3px">
            Ausgegeben: <?= date('d.m.Y H:i', strtotime($oa['ausgegeben_am'])) ?>
            <?= $oa['rueckgabe_bis'] ? ' · Zurück bis: <strong style="color:' . ($ueberfaellig ? '#e74c3c' : '#f39c12') . '">' . date('d.m.Y', strtotime($oa['rueckgabe_bis'])) . '</strong>' : '' ?>
          </div>
          <div style="margin-top:6px">
            <?php foreach ($oa['positionen'] as $p): ?>
              <span class="oa-pos-chip"><?= htmlspecialchars($p['bezeichnung']) ?> × <?= (int)$p['menge'] ?></span>
            <?php endforeach; ?>
          </div>
          <?php if ($oa['notiz']): ?>
            <div style="font-size:12px;color:#666;margin-top:4px">Notiz: <?= htmlspecialchars($oa['notiz']) ?></div>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0">
          <form method="post" action="offene_auswahl_verarbeiten.php" onsubmit="return confirm('Artikel als ZURÜCKGEGEBEN buchen?')">
            <input type="hidden" name="oa_id" value="<?= $oa['id'] ?>">
            <input type="hidden" name="aktion" value="zurueck">
            <button type="submit" class="ks-btn ks-btn-secondary" style="font-size:13px;padding:8px 14px">↩ Zurückgegeben</button>
          </form>
          <a href="/mealana/kasse/bon.php" class="ks-btn ks-btn-success" style="font-size:13px;padding:8px 14px">
            🛒 Kassieren
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Erledigte (letzte 10) -->
  <?php if (!empty($erledigte)): ?>
  <div class="ks-card" style="margin-top:24px">
    <div class="ks-card-title">Zuletzt erledigt</div>
    <?php foreach (array_slice($erledigte, 0, 10) as $oa): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #071018;font-size:13px">
      <div>
        <span style="color:#888"><?= date('d.m.Y', strtotime($oa['ausgegeben_am'])) ?></span>
        <span style="color:#aaa;margin-left:10px"><?= htmlspecialchars($oa['kunden_name'] ?: 'Laufkunde') ?></span>
      </div>
      <span style="background:#1a3a28;color:#4caf50;padding:2px 8px;border-radius:4px;font-size:11px">Zurück</span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<!-- Overlay: Neu mitgeben -->
<div class="ks-overlay" id="overlay-neu">
  <div class="ks-overlay-box" style="max-width:580px">
    <div class="ks-overlay-titel">↗ Artikel mitgeben</div>
    <p style="color:#888;font-size:13px;margin-top:-10px">Artikel werden sofort aus dem Lager ausgebucht.</p>

    <div style="margin-bottom:14px">
      <label style="font-size:13px;color:#888;display:block;margin-bottom:6px">Kundenname (optional)</label>
      <input type="text" id="neu-name" class="ks-input" placeholder="Name oder leer für Laufkunde">
    </div>
    <div style="margin-bottom:14px">
      <label style="font-size:13px;color:#888;display:block;margin-bottom:6px">Rückgabe bis (optional)</label>
      <input type="date" id="neu-datum" class="ks-input">
    </div>
    <div style="margin-bottom:14px">
      <label style="font-size:13px;color:#888;display:block;margin-bottom:6px">Lager</label>
      <select id="neu-lager" class="ks-select">
        <?php foreach ($alleLager as $l): ?>
          <option value="<?= $l['id'] ?>" <?= $l['id'] == $lagerId ? 'selected' : '' ?>>
            <?= htmlspecialchars($l['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="margin-bottom:8px">
      <label style="font-size:13px;color:#888;display:block;margin-bottom:6px">Artikel hinzufügen</label>
      <div style="display:flex;gap:8px">
        <input type="text" id="neu-scan" class="ks-input" placeholder="EAN oder Artikelnr. scannen…" style="flex:1">
        <button class="ks-btn ks-btn-secondary" onclick="neuArtikelSuchen()">OK</button>
      </div>
    </div>
    <div id="neu-positionen-liste" style="min-height:60px;margin-bottom:14px"></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <button class="ks-btn ks-btn-success" onclick="neuSpeichern()">↗ Mitgeben</button>
      <button class="ks-btn ks-btn-secondary" onclick="document.getElementById('overlay-neu').classList.remove('aktiv')">Abbrechen</button>
    </div>
  </div>
</div>

<script>
var neuPositionen = [];
var neuLagerId = <?= $lagerId ?>;

function neuDialog() {
    neuPositionen = [];
    document.getElementById('neu-name').value  = '';
    document.getElementById('neu-datum').value = '';
    document.getElementById('neu-scan').value  = '';
    aktualisiereNeuListe();
    document.getElementById('overlay-neu').classList.add('aktiv');
    setTimeout(() => document.getElementById('neu-scan').focus(), 100);
}

document.getElementById('neu-scan').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') neuArtikelSuchen();
});
document.getElementById('neu-lager').addEventListener('change', function() {
    neuLagerId = parseInt(this.value);
});

function neuArtikelSuchen() {
    var code = document.getElementById('neu-scan').value.trim();
    if (!code) return;
    fetch('/mealana/kasse/ajax_artikel.php?code=' + encodeURIComponent(code) + '&lager_id=' + neuLagerId)
        .then(r => r.json())
        .then(function(d) {
            if (!d.erfolg || d.typ === 'vater') {
                alert(d.fehler || 'Bitte Variante scannen.');
                return;
            }
            neuPositionen.push({
                artikel_id: d.id,
                bezeichnung: d.bezeichnung,
                ean: d.ean || null,
                menge: 1,
                einzelpreis_brutto: parseFloat(d.brutto_vk) || 0,
                steuer_prozent: parseFloat(d.steuer_prozent) || 20,
                charge: d.fifo_charge || null
            });
            aktualisiereNeuListe();
            document.getElementById('neu-scan').value = '';
            document.getElementById('neu-scan').focus();
        });
}

function aktualisiereNeuListe() {
    var el = document.getElementById('neu-positionen-liste');
    if (!neuPositionen.length) {
        el.innerHTML = '<div style="color:#444;font-size:13px;text-align:center;padding:10px">Noch keine Artikel</div>';
        return;
    }
    var html = '<table style="width:100%;border-collapse:collapse">';
    neuPositionen.forEach(function(p, i) {
        html += '<tr><td style="padding:6px 0;color:#eee">' + p.bezeichnung + '</td>'
            + '<td style="padding:6px 0;color:#888;text-align:center">×' + p.menge + '</td>'
            + '<td style="padding:6px 0;text-align:right"><button onclick="neuPosEntfernen(' + i + ')" style="background:none;border:none;color:#c0392b;cursor:pointer;font-size:16px">✕</button></td></tr>';
    });
    html += '</table>';
    el.innerHTML = html;
}

function neuPosEntfernen(i) {
    neuPositionen.splice(i, 1);
    aktualisiereNeuListe();
}

function neuSpeichern() {
    if (!neuPositionen.length) { alert('Bitte mindestens einen Artikel hinzufügen.'); return; }
    fetch('/mealana/kasse/offene_auswahl_speichern.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            kunden_name: document.getElementById('neu-name').value.trim() || null,
            rueckgabe_bis: document.getElementById('neu-datum').value || null,
            lager_id: neuLagerId,
            positionen: neuPositionen
        })
    }).then(r => r.json()).then(function(d) {
        if (d.erfolg) {
            window.location.reload();
        } else {
            alert('Fehler: ' + (d.fehler || 'Unbekannt'));
        }
    });
}
</script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
