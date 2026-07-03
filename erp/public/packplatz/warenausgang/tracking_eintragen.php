<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';

$db        = Database::getInstance();
$auftragId = (int)($_GET['auftrag_id'] ?? 0);

if (!$auftragId) {
    $_SESSION['fehler'] = 'Kein Auftrag angegeben.';
    header('Location: index.php'); exit;
}

$stmt = $db->prepare("SELECT * FROM auftraege WHERE id = ?");
$stmt->execute([$auftragId]);
$auftrag = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$auftrag || $auftrag['lieferstatus'] !== 'kommissioniert') {
    $_SESSION['fehler'] = 'Auftrag nicht gefunden oder nicht im Status Kommissioniert.';
    header('Location: index.php'); exit;
}

$positionen = $db->prepare("
    SELECT id, bezeichnung, menge, menge_geliefert
    FROM auftrag_positionen
    WHERE auftrag_id = ? AND artikel_id IS NOT NULL
    ORDER BY sort_order, id
");
$positionen->execute([$auftragId]);
$positionen = $positionen->fetchAll(PDO::FETCH_ASSOC);

// Pickliste zu diesem Auftrag ermitteln (damit abschliessen.php die Pickliste schließt)
$plStmt = $db->prepare("SELECT pickliste_id FROM pickliste_auftraege WHERE auftrag_id = ? LIMIT 1");
$plStmt->execute([$auftragId]);
$picklisteId = (int)($plStmt->fetchColumn() ?: 0);

// Offene Menge je Position (für positionen_json)
$posJson = [];
foreach ($positionen as $i => $p) {
    $offen = max(0, (float)$p['menge'] - (float)$p['menge_geliefert']);
    $posJson[] = ['idx' => $i, 'gesamt' => $offen, 'gescannt' => $offen];
}

$pageTitle = 'Tracking eintragen';
$backUrl   = BASE_PATH . '/packplatz/warenausgang/index.php';
$headerSub = 'Warenausgang';
require_once __DIR__ . '/../shell_top.php';
?>

<div style="max-width:680px;margin:0 auto">

    <!-- Auftragsinfo -->
    <div style="background:#16213e;border:1px solid #0f3460;border-radius:10px;padding:20px 24px;margin-bottom:24px">
        <div style="font-size:13px;color:#94a3b8;margin-bottom:4px">Auftrag</div>
        <div style="font-size:22px;font-weight:700;color:#e2e8f0"><?= htmlspecialchars($auftrag['auftrag_nr']) ?></div>
        <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap">
            <span style="background:#1e3a2e;color:#4ade80;padding:3px 10px;border-radius:4px;font-size:12px">✓ Kommissioniert</span>
            <span style="color:#94a3b8;font-size:13px"><?= count($positionen) ?> Position(en)</span>
            <span style="color:#94a3b8;font-size:13px">€ <?= number_format((float)$auftrag['bruttobetrag'], 2, ',', '.') ?></span>
        </div>
    </div>

    <!-- Tracking-Formular -->
    <form method="POST" action="abschliessen.php">
        <input type="hidden" name="auftrag_id"      value="<?= $auftragId ?>">
        <input type="hidden" name="pickliste_id"    value="<?= $picklisteId ?>">
        <input type="hidden" name="teillieferung"   value="0">
        <input type="hidden" name="positionen_json" value="<?= htmlspecialchars(json_encode($posJson)) ?>">

        <div style="background:#16213e;border:1px solid #0f3460;border-radius:10px;padding:24px;display:flex;flex-direction:column;gap:18px">

            <div>
                <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Versanddienstleister</label>
                <select name="versanddienstleister" id="sel-carrier" onchange="onCarrierChange(this.value)"
                        style="width:100%;background:#0d1b2a;border:1px solid #334155;color:#e2e8f0;padding:10px 14px;border-radius:6px;font-size:14px">
                    <option value="post_at">🇦🇹 Österreichische Post (PLC)</option>
                    <option value="dhl">DHL</option>
                    <option value="dpd">DPD</option>
                    <option value="gls">GLS</option>
                    <option value="ups">UPS</option>
                    <option value="manuell">✋ Manuell (kein PLC)</option>
                </select>
            </div>

            <div>
                <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Trackingnummer</label>
                <input type="text" name="tracking" id="inp-tracking" autofocus
                       placeholder="Barcode scannen oder eingeben"
                       style="width:100%;background:#0d1b2a;border:1px solid #334155;color:#e2e8f0;padding:10px 14px;border-radius:6px;font-size:15px;box-sizing:border-box"
                       oninput="document.getElementById('btn-abschliessen').disabled = this.value.trim().length < 3">
            </div>

            <div>
                <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Gewicht (g) <span style="color:#475569;font-weight:400">optional</span></label>
                <input type="number" name="gewicht" value="0" min="0" step="0.001"
                       style="width:100%;background:#0d1b2a;border:1px solid #334155;color:#e2e8f0;padding:10px 14px;border-radius:6px;font-size:14px;box-sizing:border-box">
            </div>

            <div id="plc-hinweis" style="background:#1a2a1a;border:1px solid #2a4a2a;border-radius:6px;padding:10px 14px;font-size:13px;color:#86efac">
                📄 PLC-Datei wird beim Abschließen automatisch erzeugt
            </div>

        </div>

        <div style="display:flex;gap:12px;margin-top:20px">
            <a href="index.php" class="pp-btn pp-btn-secondary" style="flex:1;text-align:center;text-decoration:none;padding:12px">Abbrechen</a>
            <button type="submit" id="btn-abschliessen" disabled
                    class="pp-btn pp-btn-success" style="flex:2;padding:12px;font-size:15px;font-weight:700">
                ✓ Abschließen &amp; versenden
            </button>
        </div>
    </form>

</div>

<script>
function onCarrierChange(v) {
    const hint = document.getElementById('plc-hinweis');
    if (v === 'post_at') {
        hint.style.display = '';
    } else {
        hint.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
