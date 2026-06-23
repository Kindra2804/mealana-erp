<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/wareneingang/WareneingangService.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

$service        = new WareneingangService();
$bestellService = new BestellungService();
$offene         = $service->getOffene();
$lieferanten    = $bestellService->getAlleLieferanten();
$lager          = $service->getAlleLager();
$durchlauf      = $_SESSION['we_durchlauf'] ?? [];

$fehlerWe = $_SESSION['fehler_we'] ?? '';
unset($_SESSION['fehler_we']);

$pageTitle        = 'Wareneingang';
$activeModule     = 'einkauf';
$actionBarContent = '<a href="/mealana/lager/wareneingang.php" class="btn btn-secondary btn-sm">Freier Wareneingang</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehlerWe): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;color:var(--color-danger)">
    <?= htmlspecialchars($fehlerWe) ?>
</div>
<?php endif; ?>

<?php if (!empty($durchlauf)): ?>
<div class="card" style="margin-bottom:16px;border-left:3px solid var(--color-nav)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <strong style="font-size:13px">Sammelliste (<?= count($durchlauf) ?> Artikel)</strong>
        <button type="button" class="btn btn-danger btn-sm" onclick="durchlaufLeeren()">Leeren</button>
    </div>
    <div style="margin-bottom:12px">
        <?php foreach ($durchlauf as $item): ?>
        <div style="font-size:12px;padding:4px 0;border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between">
            <span><?= htmlspecialchars($item['name']) ?></span>
            <span style="color:var(--color-text-muted)">× <?= (int)$item['menge'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <form method="post" action="/mealana/wareneingang/bestellung_aus_durchlauf.php"
          style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div>
            <label style="font-size:11px;color:var(--color-text-muted);display:block;margin-bottom:2px">Lieferant *</label>
            <select name="lieferant_id" class="erp-select" required>
                <option value="">– wählen –</option>
                <?php foreach ($lieferanten as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:11px;color:var(--color-text-muted);display:block;margin-bottom:2px">Lager</label>
            <select name="lager_id" class="erp-select">
                <?php foreach ($lager as $lg): ?>
                    <option value="<?= $lg['id'] ?>"><?= htmlspecialchars($lg['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Bestellung anlegen (als erledigt)</button>
    </form>
</div>
<?php endif; ?>

<!-- EAN-Scan Bereich -->
<div class="card" style="margin-bottom:16px">
    <div style="font-size:13px;font-weight:600;margin-bottom:10px">EAN scannen oder eingeben</div>
    <div style="display:flex;gap:8px;align-items:center">
        <input type="text" id="ean-input" class="erp-input" style="flex:1;font-size:16px;padding:10px"
            placeholder="EAN-Code scannen..." autofocus autocomplete="off">
        <button class="btn btn-primary" onclick="eanSuchen()" style="padding:10px 20px">Suchen</button>
        <a href="/mealana/lager/wareneingang.php" class="btn btn-secondary" style="padding:10px 16px">Freier Wareneingang →</a>
    </div>

    <!-- Scan-Ergebnis -->
    <div id="scan-ergebnis" style="display:none;margin-top:14px"></div>
</div>

<!-- Offene Bestellungen als Kacheln -->
<div id="kacheln-bereich">
    <?php if (empty($offene)): ?>
        <div class="card" style="color:var(--color-text-muted)">Keine offenen Bestellungen.</div>
    <?php else: ?>
        <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:8px">Offene Bestellungen (<?= count($offene) ?>)</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
            <?php foreach ($offene as $b):
                $istTeil = $b['status'] === 'teilgeliefert';
                $fortsch = $b['anzahl_positionen'] > 0 ? round((float)$b['positionen_erledigt'] / (float)$b['anzahl_positionen'] * 100) : 0;
            ?>
                <a href="/mealana/wareneingang/detail.php?bestellung_id=<?= $b['id'] ?>" style="text-decoration:none;color:inherit">
                    <div class="card" style="cursor:pointer;transition:box-shadow .15s;border:1px solid <?= $istTeil ? 'var(--color-warning)' : 'var(--color-border)' ?>" onmouseover="this.style.boxShadow='0 2px 12px rgba(0,0,0,.12)'" onmouseout="this.style.boxShadow=''">
                        <div style="font-weight:600;font-size:13px;margin-bottom:4px"><?= htmlspecialchars($b['lieferant_name']) ?></div>
                        <div style="font-size:12px;color:var(--color-text-muted)"><?= date('d.m.Y', strtotime($b['bestelldatum'])) ?></div>
                        <div style="font-size:12px;margin-top:4px"><?= (int)$b['anzahl_positionen'] ?> Positionen</div>
                        <?php if ($istTeil): ?>
                            <div style="margin-top:6px;background:#f0f0f0;border-radius:4px;height:6px;overflow:hidden">
                                <div style="width:<?= $fortsch ?>%;height:100%;background:var(--color-warning)"></div>
                            </div>
                            <div style="font-size:11px;color:var(--color-warning);margin-top:3px"><?= $fortsch ?>% eingegangen</div>
                        <?php else: ?>
                            <span class="chip chip-aktiv" style="margin-top:6px;font-size:11px">offen</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('ean-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') eanSuchen();
    });

    function eanSuchen() {
        var ean = document.getElementById('ean-input').value.trim();
        if (!ean) return;

        fetch('/mealana/wareneingang/artikel_suche.php?ean=' + encodeURIComponent(ean))
            .then(r => r.json())
            .then(data => {
                var box = document.getElementById('scan-ergebnis');

                if (!data.gefunden) {
                    box.innerHTML = '<div style="color:var(--color-danger);font-size:13px">EAN nicht gefunden: ' + escHtml(ean) + '</div>' +
                        '<a href="/mealana/wareneingang/artikel_vorbereiten.php?ean=' + encodeURIComponent(ean) + '" class="btn btn-secondary btn-sm" style="margin-top:8px">+ Neuen Artikel anlegen</a>';
                    box.style.display = 'block';
                    document.getElementById('kacheln-bereich').style.display = '';
                    return;
                }

                var a = data.artikel;
                var bs = data.bestellungen;

                var html = '<div style="display:flex;gap:14px;align-items:flex-start;padding:10px;background:#f8f9fa;border-radius:6px">';
                if (a.hauptbild) {
                    html += '<img src="/mealana/uploads/artikel/' + a.id + '/' + escHtml(a.hauptbild) + '" style="width:72px;height:72px;object-fit:cover;border-radius:4px;flex-shrink:0" onerror="this.style.display=\'none\'">';
                }
                html += '<div style="flex:1">';
                html += '<div style="font-weight:600;font-size:14px">' + escHtml(a.anzeige_name) + '</div>';
                html += '<div style="font-size:12px;color:var(--color-text-muted)">EAN: ' + escHtml(ean) + '</div>';

                if (!bs.length) {
                    html += '<div style="margin-top:8px;font-size:13px;color:var(--color-text-muted)">Keine offene Bestellung enthält diesen Artikel.</div>';
                    html += '<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">';
                    html += '<button type="button" class="btn btn-primary btn-sm" onclick="zurSammelliste(' + a.id + ', \'' + escHtml(a.anzeige_name).replace(/'/g, "\\'") + '\', \'' + encodeURIComponent(ean) + '\')">+ Zur Sammelliste</button>';
                    html += '<a href="/mealana/lager/wareneingang.php?ean=' + encodeURIComponent(ean) + '" class="btn btn-secondary btn-sm">Freier Wareneingang</a>';
                    html += '</div>';
                } else {
                    html += '<div style="margin-top:8px;font-size:13px;font-weight:500">In welcher Bestellung buchen?</div>';
                    bs.forEach(function(b) {
                        html += '<a href="/mealana/wareneingang/detail.php?bestellung_id=' + b.id + '&scan_artikel_id=' + a.id + '&scan_ean=' + encodeURIComponent(ean) + '" style="display:flex;justify-content:space-between;align-items:center;padding:7px 10px;margin-top:6px;background:#fff;border:1px solid var(--color-border);border-radius:4px;text-decoration:none;color:inherit">';
                        html += '<span style="font-size:13px">' + escHtml(b.lieferant_name) + ' &nbsp; ' + formatDatum(b.bestelldatum) + '</span>';
                        html += '<span style="font-size:12px;color:var(--color-text-muted)">offen: <strong>' + b.menge_offen + '</strong> Stk</span>';
                        html += '</a>';
                    });
                    html += '<a href="/mealana/lager/wareneingang.php?ean=' + encodeURIComponent(ean) + '" style="display:block;margin-top:8px;font-size:12px;color:var(--color-text-muted)">Freier Wareneingang ohne Bestellung</a>';
                }

                html += '</div></div>';
                box.innerHTML = html;
                box.style.display = 'block';
                document.getElementById('kacheln-bereich').style.display = 'none';
            });

        document.getElementById('ean-input').select();
    }

    function formatDatum(d) {
        var p = d.split('-');
        return p[2] + '.' + p[1] + '.' + p[0];
    }

    function zurSammelliste(artikelId, name, eanEncoded) {
        var ean = decodeURIComponent(eanEncoded);
        var fd = new FormData();
        fd.append('artikel_id', artikelId);
        fd.append('menge', 1);
        fd.append('name', name);
        fd.append('ean', ean);
        fetch('/mealana/wareneingang/durchlauf_add.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(function(d) {
                if (d.erfolg) {
                    window.location.reload();
                }
            });
    }

    function durchlaufLeeren() {
        fetch('/mealana/wareneingang/durchlauf_clear.php', { method: 'POST' })
            .then(r => r.json())
            .then(function() { window.location.reload(); });
    }

    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    addEventListener("DOMContentLoaded", (event) => {
        var ean = new URLSearchParams(window.location.search).get('ean');
        if (ean) {
            document.getElementById('ean-input').value = ean;
            eanSuchen();
        }
    })
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>