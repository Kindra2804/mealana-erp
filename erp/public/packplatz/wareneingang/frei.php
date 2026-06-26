<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../../src/modules/lieferanten/LieferantenService.php';

$lagerService    = new LagerService();
$liefService     = new LieferantenService();
$alleLager       = $lagerService->getAlleLager();
$alleLieferanten = $liefService->findAll();

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);

$pageTitle = 'Freier Wareneingang';
$backUrl   = '/mealana/packplatz/wareneingang/index.php';
$headerSub = 'Freier WE';
require_once __DIR__ . '/../shell_top.php';
?>

<style>
.fwe-card { background:#16213e; border:1px solid #0f3460; border-radius:10px; padding:20px; margin-bottom:16px; }
.fwe-input { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:20px; padding:10px 14px; outline:none; width:100%; box-sizing:border-box; }
.fwe-input:focus { border-color:#e94560; }
.fwe-select { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:16px; padding:10px 12px; outline:none; width:100%; }
.fwe-label { font-size:12px; color:#aaa; display:block; margin-bottom:6px; }
</style>

<?php if ($erfolg): ?>
<div style="background:#0d2d1a;border:1px solid #4caf50;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#4caf50">
    ✓ <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>
<?php if ($fehler): ?>
<div style="background:#2d0d0d;border:1px solid #e94560;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#ef5350">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>

<div style="max-width:720px;margin:0 auto">

    <!-- Scan-Bereich -->
    <div class="fwe-card">
        <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">🔍 Artikel suchen</div>
        <div style="display:flex;gap:8px">
            <input type="text" id="scan-input" class="fwe-input" placeholder="EAN scannen oder Artikelnummer…" autofocus autocomplete="off">
            <button class="pp-btn pp-btn-secondary" style="padding:10px 20px;font-size:18px;flex-shrink:0" onclick="artikelSuchen()">→</button>
        </div>
        <div id="suche-fehler" style="color:#ef5350;font-size:13px;margin-top:8px;display:none"></div>
    </div>

    <!-- Buchungs-Form (nach Scan) -->
    <div id="buchungs-bereich" style="display:none">
        <div class="fwe-card">
            <div style="display:flex;gap:14px;align-items:center;margin-bottom:16px">
                <div id="artikel-bild" style="width:72px;height:72px;background:#0a0a1a;border:1px solid #0f3460;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0">📦</div>
                <div>
                    <div id="artikel-name" style="font-size:18px;font-weight:700"></div>
                    <div id="artikel-nr" style="font-size:13px;color:#aaa;margin-top:3px"></div>
                    <div id="artikel-lager" style="font-size:12px;color:#6c8ebf;margin-top:2px"></div>
                </div>
            </div>

            <form method="post" action="/mealana/packplatz/wareneingang/frei_speichern.php">
                <input type="hidden" id="f-artikel-id" name="artikel_id">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                    <div>
                        <label class="fwe-label">Menge *</label>
                        <input type="number" name="menge" id="f-menge" class="fwe-input" min="1" step="1" required placeholder="0" style="font-size:28px;font-weight:700;text-align:center">
                    </div>
                    <div>
                        <label class="fwe-label">Lager *</label>
                        <select name="lager_id" class="fwe-select">
                            <?php foreach ($alleLager as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                    <div>
                        <label class="fwe-label">Charge (optional)</label>
                        <input type="text" name="charge" class="fwe-input" style="font-size:16px" placeholder="z.B. LOT-2026-01">
                    </div>
                    <div>
                        <label class="fwe-label">EK-Preis netto (optional)</label>
                        <input type="number" name="ek_preis" class="fwe-input" step="0.0001" style="font-size:16px" placeholder="0.00">
                    </div>
                </div>

                <div style="margin-bottom:16px">
                    <label class="fwe-label">Lieferant (optional)</label>
                    <select name="lieferant_id" class="fwe-select">
                        <option value="">– kein Lieferant –</option>
                        <?php foreach ($alleLieferanten as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="pp-btn pp-btn-success" style="width:100%;font-size:22px;padding:18px">
                    ✓ Einbuchen
                </button>
            </form>
        </div>
    </div>

</div>

<script>
document.getElementById('scan-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); artikelSuchen(); }
});

async function artikelSuchen() {
    var q = document.getElementById('scan-input').value.trim();
    var fehlerEl = document.getElementById('suche-fehler');
    fehlerEl.style.display = 'none';
    if (!q) return;

    var r    = await fetch('/mealana/packplatz/intern/artikel_ajax.php?q=' + encodeURIComponent(q));
    var data = await r.json();

    if (!data.gefunden) {
        fehlerEl.textContent = 'Artikel nicht gefunden: ' + q;
        fehlerEl.style.display = 'block';
        document.getElementById('buchungs-bereich').style.display = 'none';
        return;
    }

    var a = data.artikel;
    document.getElementById('f-artikel-id').value = a.id;
    document.getElementById('artikel-name').textContent = a.name;
    document.getElementById('artikel-nr').textContent = a.artikelnummer;

    // Lagerstand
    var lagerText = (data.bestand || []).map(function(lb) {
        return lb.lager_name + ': ' + parseFloat(lb.bestand);
    }).join(' · ');
    document.getElementById('artikel-lager').textContent = lagerText || 'Kein Lagerbestand';

    // Bild
    if (a.hauptbild) {
        document.getElementById('artikel-bild').innerHTML =
            '<img src="/mealana/uploads/artikel/' + a.id + '/' + a.hauptbild.replace(/"/g,'') +
            '" style="width:72px;height:72px;object-fit:contain;border-radius:6px" onerror="this.parentElement.innerHTML=\'📦\'">';
    } else {
        document.getElementById('artikel-bild').textContent = '📦';
    }

    document.getElementById('buchungs-bereich').style.display = 'block';
    document.getElementById('f-menge').focus();
}
</script>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
