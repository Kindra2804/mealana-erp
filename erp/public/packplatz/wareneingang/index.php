<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/modules/wareneingang/WareneingangService.php';
require_once __DIR__ . '/../../../src/modules/bestellungen/BestellungService.php';

$service        = new WareneingangService();
$bestellService = new BestellungService();
$offene         = $service->getOffene();
$lieferanten    = $bestellService->getAlleLieferanten();

$fehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['fehler']);

$pageTitle = 'Wareneingang';
$backUrl   = '/mealana/packplatz/index.php';
$headerSub = 'Wareneingang';
require_once __DIR__ . '/../shell_top.php';
?>

<?php if ($fehler): ?>
<div style="background:#2d0d0d;border:1px solid #e94560;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#ef5350">
    <?= htmlspecialchars($fehler) ?>
</div>
<?php endif; ?>

<div style="max-width:900px;margin:0 auto">

    <div style="font-size:18px;font-weight:700;margin-bottom:20px;color:#e94560">📥 Offene Bestellungen</div>

    <?php if (empty($offene)): ?>
        <div style="background:#16213e;border:1px solid #0f3460;border-radius:10px;padding:40px;text-align:center;color:#666;font-size:16px">
            Keine offenen Lieferungen vorhanden
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach ($offene as $b):
                $nr = BestellungService::bestellnummer($b['id'], $b['bestelldatum']);
                $farbe = $b['status'] === 'teilgeliefert' ? '#e65100' : '#0f3460';
                $label = match($b['status']) {
                    'teilgeliefert' => 'Teilgeliefert',
                    default         => 'Offen',
                };
            ?>
                <a href="detail.php?bestellung_id=<?= $b['id'] ?>"
                   style="background:#16213e;border:2px solid <?= $farbe ?>;border-radius:10px;padding:16px 20px;text-decoration:none;color:#eee;display:flex;justify-content:space-between;align-items:center;transition:border-color .15s"
                   onmouseover="this.style.borderColor='#e94560'" onmouseout="this.style.borderColor='<?= $farbe ?>'">
                    <div>
                        <div style="font-size:20px;font-weight:700"><?= htmlspecialchars($nr) ?></div>
                        <div style="font-size:13px;color:#aaa;margin-top:4px">
                            <?= htmlspecialchars($b['lieferant_name'] ?? '—') ?>
                            &nbsp;·&nbsp;
                            <?= date('d.m.Y', strtotime($b['bestelldatum'])) ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:12px;align-items:center">
                        <div style="font-size:12px;padding:5px 12px;border-radius:20px;background:<?= $b['status']==='teilgeliefert' ? '#3a1a00' : '#0f1a2e' ?>;color:<?= $b['status']==='teilgeliefert' ? '#ff9800' : '#6c8ebf' ?>">
                            <?= $label ?>
                        </div>
                        <div style="font-size:24px;color:#e94560">›</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Freier Wareneingang: Artikel ohne Bestellung einscannen -->
    <div style="margin-top:30px;background:#16213e;border:1px solid #0f3460;border-radius:10px;padding:16px 20px">
        <div style="font-size:14px;font-weight:600;color:#aaa;margin-bottom:12px">Freier Wareneingang</div>
        <form method="post" action="/mealana/lager/wareneingang_speichern.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
            <div>
                <label style="font-size:12px;color:#666;display:block;margin-bottom:4px">Lieferant</label>
                <select name="lieferant_id" style="background:#0a0a1a;border:2px solid #0f3460;border-radius:8px;color:#eee;font-size:16px;padding:10px 14px;outline:none">
                    <option value="">– optional –</option>
                    <?php foreach ($lieferanten as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="/mealana/packplatz/wareneingang/frei.php"
               style="background:#0f3460;color:#eee;border:none;border-radius:8px;padding:12px 24px;font-size:16px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center">
                → Freier WE öffnen
            </a>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
