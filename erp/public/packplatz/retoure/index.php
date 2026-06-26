<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';

$db = Database::getInstance();

$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);

// Letzte abgeschlossene/versendete Aufträge
$auftraege = $db->query("
    SELECT a.id, a.auftrag_nr, a.erstellt_am, a.bruttobetrag, a.kunden_snapshot
    FROM auftraege a
    WHERE a.lieferstatus IN ('versendet','abgeschlossen','teilgeliefert')
      AND a.zahlungsstatus != 'storniert'
    ORDER BY a.erstellt_am DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Retoure';
$backUrl   = '/mealana/packplatz/index.php';
$headerSub = 'Retoure';
require_once __DIR__ . '/../shell_top.php';
?>

<?php if ($erfolg): ?>
<div style="background:#0d2d0d;border:1px solid #4caf50;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#4caf50">
    ✓ <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>
<?php if ($fehler): ?>
<div style="background:#2d0d0d;border:1px solid #e94560;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#ef5350">
    <?= htmlspecialchars($fehler) ?>
</div>
<?php endif; ?>

<div style="max-width:800px;margin:0 auto">

    <div style="background:#16213e;border:1px solid #0f3460;border-radius:10px;padding:20px;margin-bottom:20px">
        <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">📦 Auftrag suchen</div>
        <form method="get" action="detail.php" style="display:flex;gap:10px">
            <input type="text" name="auftrag_nr" class="pp-scan-input" style="flex:1;font-size:18px;padding:12px 16px"
                   placeholder="Auftragsnummer eingeben / scannen…" autofocus>
            <button type="submit" class="pp-btn pp-btn-primary" style="padding:12px 24px;font-size:16px">→</button>
        </form>
    </div>

    <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">Zuletzt versendete Aufträge</div>

    <?php if (empty($auftraege)): ?>
        <div style="color:#555;font-size:14px;text-align:center;padding:30px">Keine versendeten Aufträge</div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($auftraege as $a):
            $kd = json_decode($a['kunden_snapshot'] ?? '{}', true) ?: [];
            $kdName = trim(($kd['vorname'] ?? '') . ' ' . ($kd['nachname'] ?? ''));
            if (!empty($kd['firma'])) $kdName = $kd['firma'] . ($kdName ? ' / ' . $kdName : '');
        ?>
            <a href="detail.php?auftrag_id=<?= $a['id'] ?>"
               style="background:#16213e;border:2px solid #0f3460;border-radius:10px;padding:14px 18px;text-decoration:none;color:#eee;display:flex;justify-content:space-between;align-items:center;transition:border-color .15s"
               onmouseover="this.style.borderColor='#e94560'" onmouseout="this.style.borderColor='#0f3460'">
                <div>
                    <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($a['auftrag_nr']) ?></div>
                    <div style="font-size:13px;color:#aaa;margin-top:3px">
                        <?= htmlspecialchars($kdName ?: '—') ?> &nbsp;·&nbsp;
                        <?= date('d.m.Y', strtotime($a['erstellt_am'])) ?>
                    </div>
                </div>
                <div style="font-size:16px;font-weight:700;color:#aaa">
                    <?= number_format((float)$a['bruttobetrag'], 2, ',', '.') ?> €
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
