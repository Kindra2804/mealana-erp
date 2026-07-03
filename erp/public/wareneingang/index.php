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
$actionBarContent = '<a href="' . BASE_PATH . '/lager/wareneingang.php" class="btn btn-secondary btn-sm">Freier Wareneingang</a>';
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
    <form method="post" action="<?= BASE_PATH ?>/wareneingang/bestellung_aus_durchlauf.php"
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
        <a href="<?= BASE_PATH ?>/lager/wareneingang.php" class="btn btn-secondary" style="padding:10px 16px">Freier Wareneingang →</a>
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
                <a href="<?= BASE_PATH ?>/wareneingang/detail.php?bestellung_id=<?= $b['id'] ?>" style="text-decoration:none;color:inherit">
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

<script src="<?= BASE_PATH ?>/js/wareneingang_index.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
