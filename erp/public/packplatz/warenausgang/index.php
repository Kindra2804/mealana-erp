<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';

$db = Database::getInstance();

// Offene Picklisten laden
$picklisten = $db->query("
    SELECT pl.*, COUNT(pa.auftrag_id) AS anzahl_auftraege
    FROM picklisten pl
    LEFT JOIN pickliste_auftraege pa ON pa.pickliste_id = pl.id
    WHERE pl.status IN ('offen','gedruckt')
    GROUP BY pl.id
    ORDER BY pl.erstellt_am DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Kommissionierte Aufträge (warten auf Tracking)
$kommissioniert = $db->query("
    SELECT id, auftrag_nr, erstellt_am, bruttobetrag, aktualisiert_am
    FROM auftraege
    WHERE lieferstatus = 'kommissioniert'
      AND zahlungsstatus NOT IN ('storniert')
    ORDER BY aktualisiert_am DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$fehler   = $_SESSION['fehler'] ?? null;
unset($_SESSION['fehler']);

$pageTitle = 'Warenausgang';
$backUrl   = '/mealana/packplatz/index.php';
$headerSub = 'Warenausgang';
require_once __DIR__ . '/../shell_top.php';
?>

<?php if ($fehler): ?>
<div style="background:#2d0d0d;border:1px solid #e94560;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#ef5350">
    <?= htmlspecialchars($fehler) ?>
</div>
<?php endif; ?>

<?php if (!empty($kommissioniert)): ?>
<div style="background:#1a2a1a;border:1px solid #2d4a2d;border-radius:10px;padding:18px 20px;margin-bottom:24px;max-width:1100px;margin-left:auto;margin-right:auto">
    <div style="font-size:15px;font-weight:700;color:#4ade80;margin-bottom:12px">
        📦 Kommissioniert — Tracking ausstehend (<?= count($kommissioniert) ?>)
    </div>
    <div style="display:flex;flex-direction:column;gap:6px">
        <?php foreach ($kommissioniert as $k): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;background:#16213e;border:1px solid #0f3460;border-radius:6px;padding:10px 14px">
            <div>
                <span style="font-weight:700;color:#e2e8f0"><?= htmlspecialchars($k['auftrag_nr']) ?></span>
                <span style="color:#64748b;font-size:12px;margin-left:10px">
                    kommissioniert <?= date('d.m. H:i', strtotime($k['aktualisiert_am'])) ?>
                </span>
            </div>
            <a href="tracking_eintragen.php?auftrag_id=<?= $k['id'] ?>"
               style="background:#0f3460;border:1px solid #1e4a8a;color:#60a5fa;padding:6px 14px;border-radius:5px;text-decoration:none;font-size:13px;font-weight:600"
               onmouseover="this.style.background='#1e4a8a'" onmouseout="this.style.background='#0f3460'">
                ⏩ Tracking eintragen
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:1100px;margin:0 auto">

    <!-- PICKLISTE -->
    <div>
        <div style="font-size:18px;font-weight:700;margin-bottom:16px;color:#e94560">📋 Pickliste öffnen</div>

        <?php if (empty($picklisten)): ?>
            <div style="color:#666;font-size:14px;padding:20px;background:#16213e;border-radius:8px;text-align:center">
                Keine offenen Picklisten vorhanden
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:8px">
                <?php foreach ($picklisten as $pl): ?>
                    <a href="scan.php?modus=pickliste&pickliste_id=<?= $pl['id'] ?>"
                       style="background:#16213e;border:2px solid #0f3460;border-radius:8px;padding:14px 18px;text-decoration:none;color:#eee;display:flex;justify-content:space-between;align-items:center;transition:border-color .15s"
                       onmouseover="this.style.borderColor='#e94560'" onmouseout="this.style.borderColor='#0f3460'">
                        <div>
                            <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($pl['nummer']) ?></div>
                            <div style="font-size:12px;color:#aaa;margin-top:3px">
                                <?= (int)$pl['anzahl_auftraege'] ?> Auftrag/Aufträge ·
                                <?= date('d.m.Y H:i', strtotime($pl['erstellt_am'])) ?>
                            </div>
                        </div>
                        <div style="font-size:12px;padding:4px 10px;border-radius:4px;background:<?= $pl['status']==='gedruckt' ? '#1a3a1a' : '#1a2a3a' ?>;color:<?= $pl['status']==='gedruckt' ? '#4caf50' : '#aaa' ?>">
                            <?= $pl['status'] === 'gedruckt' ? 'Gedruckt' : 'Offen' ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Picklisten-Nr. manuell eingeben / scannen -->
        <div style="margin-top:16px">
            <form method="get" action="scan.php" style="display:flex;gap:8px;align-items:center">
                <input type="hidden" name="modus" value="pickliste_nr">
                <input type="text" name="pickliste_nr" class="pp-scan-input"
                    placeholder="PL-Nr. scannen / eingeben"
                    autofocus style="flex:1;font-size:16px;padding:10px 14px">
                <button type="submit" class="pp-btn pp-btn-primary" style="padding:10px 18px">→</button>
            </form>
        </div>
    </div>

    <!-- AUFTRAG DIREKT -->
    <div>
        <div style="font-size:18px;font-weight:700;margin-bottom:16px;color:#e94560">📦 Auftrag direkt verpacken</div>
        <div style="color:#aaa;font-size:13px;margin-bottom:14px">
            Für Artikel die nicht auf Picklisten kommen —<br>Auftragsnummer eingeben oder scannen:
        </div>
        <form method="get" action="scan.php" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="modus" value="auftrag">
            <input type="text" name="auftrag_nr" class="pp-scan-input"
                placeholder="A-2026-XXXXX scannen"
                style="flex:1;font-size:16px;padding:10px 14px">
            <button type="submit" class="pp-btn pp-btn-primary" style="padding:10px 18px">→</button>
        </form>

        <div style="margin-top:30px">
            <div style="font-size:14px;font-weight:600;color:#aaa;margin-bottom:10px">Oder aus offenen Aufträgen wählen:</div>
            <?php
            $offeneAuftraege = $db->query("
                SELECT id, auftrag_nr, erstellt_am, bruttobetrag
                FROM auftraege
                WHERE lieferstatus IN ('neu','in_bearbeitung','versandbereit','teilgeliefert')
                  AND zahlungsstatus NOT IN ('storniert')
                ORDER BY erstellt_am ASC
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (empty($offeneAuftraege)): ?>
                <div style="color:#666;font-size:13px">Keine offenen Aufträge</div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:6px">
                    <?php foreach ($offeneAuftraege as $a): ?>
                        <a href="scan.php?modus=auftrag&auftrag_id=<?= $a['id'] ?>"
                           style="background:#16213e;border:1px solid #0f3460;border-radius:6px;padding:10px 14px;text-decoration:none;color:#eee;display:flex;justify-content:space-between;font-size:13px"
                           onmouseover="this.style.borderColor='#e94560'" onmouseout="this.style.borderColor='#0f3460'">
                            <span style="font-weight:600"><?= htmlspecialchars($a['auftrag_nr']) ?></span>
                            <span style="color:#aaa"><?= date('d.m.', strtotime($a['erstellt_am'])) ?> · <?= number_format((float)$a['bruttobetrag'], 2, ',', '.') ?> €</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
