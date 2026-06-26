<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';

$db           = Database::getInstance();
$lagerService = new LagerService();

// Auftrag per Nr oder ID laden
$auftragId = (int)($_GET['auftrag_id'] ?? 0);
$auftragNr = trim($_GET['auftrag_nr'] ?? '');

if (!$auftragId && $auftragNr) {
    $stmt = $db->prepare("SELECT id FROM auftraege WHERE auftrag_nr = :nr LIMIT 1");
    $stmt->execute([':nr' => $auftragNr]);
    $auftragId = (int)$stmt->fetchColumn();
}

if (!$auftragId) {
    $_SESSION['fehler'] = 'Auftrag nicht gefunden.';
    header('Location: index.php'); exit;
}

$stmt = $db->prepare("SELECT * FROM auftraege WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $auftragId]);
$auftrag = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$auftrag) {
    $_SESSION['fehler'] = 'Auftrag nicht gefunden.';
    header('Location: index.php'); exit;
}

$positionen = $db->prepare("
    SELECT ap.*, a.zustand_vater_id,
           (SELECT code FROM artikel_codes WHERE artikel_id = ap.artikel_id AND typ = 'GTIN13' LIMIT 1) AS ean
    FROM auftrag_positionen ap
    LEFT JOIN artikel a ON a.id = ap.artikel_id
    WHERE ap.auftrag_id = :id
    ORDER BY ap.sort_order, ap.id
");
$positionen->execute([':id' => $auftragId]);
$positionen = $positionen->fetchAll(PDO::FETCH_ASSOC);

// Rechnung vorhanden?
$rechnung = $db->prepare("SELECT id, rechnung_nr FROM rechnungen WHERE auftrag_id = :id AND storniert = 0 ORDER BY id DESC LIMIT 1");
$rechnung->execute([':id' => $auftragId]);
$rechnung = $rechnung->fetch(PDO::FETCH_ASSOC);

$alleLager = $lagerService->getAlleLager();

$kd = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
$kdName = trim(($kd['vorname'] ?? '') . ' ' . ($kd['nachname'] ?? ''));
if (!empty($kd['firma'])) $kdName = $kd['firma'] . ($kdName ? ' / ' . $kdName : '');
$kdEmail = $kd['email'] ?? '';

$pageTitle = 'Retoure — ' . htmlspecialchars($auftrag['auftrag_nr']);
$backUrl   = '/mealana/packplatz/retoure/index.php';
$headerSub = 'Retoure — ' . $auftrag['auftrag_nr'];
require_once __DIR__ . '/../shell_top.php';
?>

<style>
.ret-card { background:#16213e; border:1px solid #0f3460; border-radius:10px; padding:20px; margin-bottom:16px; }
.ret-input { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:16px; padding:8px 12px; outline:none; }
.ret-input:focus { border-color:#e94560; }
.ret-select { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:15px; padding:8px 10px; outline:none; }
.ret-table { width:100%; border-collapse:collapse; }
.ret-table th { background:#0f3460; color:#aaa; font-size:12px; text-align:left; padding:8px 12px; text-transform:uppercase; letter-spacing:.5px; }
.ret-table td { padding:10px 12px; border-bottom:1px solid #1a1a3e; font-size:14px; vertical-align:middle; }
</style>

<form method="post" action="speichern.php">
<input type="hidden" name="auftrag_id" value="<?= $auftragId ?>">
<?php if ($rechnung): ?>
    <input type="hidden" name="rechnung_id" value="<?= $rechnung['id'] ?>">
<?php endif; ?>

<div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start">

    <!-- Linke Spalte -->
    <div>
        <div class="ret-card">
            <div style="font-size:16px;font-weight:700;margin-bottom:12px;color:#e94560">
                Auftrag <?= htmlspecialchars($auftrag['auftrag_nr']) ?>
            </div>
            <div style="font-size:13px;color:#aaa;line-height:1.8">
                <div>Kunde: <span style="color:#eee"><?= htmlspecialchars($kdName ?: '—') ?></span></div>
                <div>Datum: <?= date('d.m.Y', strtotime($auftrag['erstellt_am'])) ?></div>
                <div>Betrag: <strong style="color:#eee"><?= number_format((float)$auftrag['bruttobetrag'], 2, ',', '.') ?> €</strong></div>
                <?php if ($rechnung): ?>
                    <div>Rechnung: <span style="color:#4caf50"><?= htmlspecialchars($rechnung['rechnung_nr']) ?></span></div>
                <?php else: ?>
                    <div style="color:#ff9800">⚠ Keine Rechnung — GS nicht möglich</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ret-card">
            <div style="font-size:14px;font-weight:700;margin-bottom:12px;color:#aaa">Positionen auswählen</div>
            <table class="ret-table">
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th>Artikel</th>
                        <th>Orig.</th>
                        <th style="width:80px">Menge</th>
                        <th style="width:120px">Zustand</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($positionen as $i => $p): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="positionen[<?= $i ?>][checked]" value="1"
                                   id="chk<?= $i ?>" style="width:18px;height:18px;accent-color:#e94560">
                        </td>
                        <td>
                            <label for="chk<?= $i ?>" style="cursor:pointer">
                                <div style="font-weight:600"><?= htmlspecialchars($p['bezeichnung']) ?></div>
                                <?php if ($p['ean']): ?>
                                    <div style="font-size:11px;color:#6c8ebf"><?= htmlspecialchars($p['ean']) ?></div>
                                <?php endif; ?>
                            </label>
                            <input type="hidden" name="positionen[<?= $i ?>][pos_id]" value="<?= $p['id'] ?>">
                            <input type="hidden" name="positionen[<?= $i ?>][artikel_id]" value="<?= $p['artikel_id'] ?>">
                            <input type="hidden" name="positionen[<?= $i ?>][bezeichnung]" value="<?= htmlspecialchars($p['bezeichnung']) ?>">
                            <input type="hidden" name="positionen[<?= $i ?>][einzelpreis_netto]" value="<?= $p['einzelpreis_netto'] ?>">
                            <input type="hidden" name="positionen[<?= $i ?>][steuer_prozent]" value="<?= $p['steuer_prozent'] ?>">
                        </td>
                        <td style="color:#aaa"><?= (int)$p['menge'] ?></td>
                        <td>
                            <input type="number" name="positionen[<?= $i ?>][menge]" min="1"
                                   max="<?= (int)$p['menge'] ?>" value="1"
                                   class="ret-input" style="width:70px;text-align:center;font-size:18px">
                        </td>
                        <td>
                            <select name="positionen[<?= $i ?>][zustand]" class="ret-select">
                                <option value="neu">Neu</option>
                                <option value="gebraucht">Gebraucht</option>
                                <option value="beschaedigt">Beschädigt</option>
                                <option value="retour">Retour</option>
                                <option value="defekt">Defekt</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Rechte Spalte: Aktion -->
    <div>
        <div class="ret-card">
            <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">Einbuchen in Lager</div>
            <select name="lager_id" class="ret-select" style="width:100%">
                <?php foreach ($alleLager as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="ret-card">
            <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">Ergebnis</div>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px">
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #0f3460;border-radius:8px;cursor:pointer;color:#eee" id="lbl-gs">
                    <input type="radio" name="ergebnis" value="gutschrift" <?= $rechnung ? '' : 'disabled' ?> onchange="ergebnisGewaehlt('gutschrift')"
                           style="width:18px;height:18px;accent-color:#e94560" <?= !$rechnung ? '' : '' ?>>
                    <div>
                        <div style="font-weight:600">Gutschrift erstellen</div>
                        <div style="font-size:11px;color:#aaa"><?= $rechnung ? 'Rg. ' . htmlspecialchars($rechnung['rechnung_nr']) : 'Keine Rechnung vorhanden'?></div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #0f3460;border-radius:8px;cursor:pointer;color:#eee">
                    <input type="radio" name="ergebnis" value="ersatz" onchange="ergebnisGewaehlt('ersatz')"
                           style="width:18px;height:18px;accent-color:#e94560">
                    <div>
                        <div style="font-weight:600">Ersatzlieferung</div>
                        <div style="font-size:11px;color:#aaa">Kein Dokument — nur Einbuchen</div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #0f3460;border-radius:8px;cursor:pointer;color:#eee">
                    <input type="radio" name="ergebnis" value="nur_einbuchen" checked onchange="ergebnisGewaehlt('nur_einbuchen')"
                           style="width:18px;height:18px;accent-color:#e94560">
                    <div>
                        <div style="font-weight:600">Nur einbuchen</div>
                        <div style="font-size:11px;color:#aaa">Kein Dokument, kein Mail</div>
                    </div>
                </label>
            </div>

            <div id="gs-bereich" style="display:none;border-top:1px solid #0f3460;padding-top:12px;margin-top:4px">
                <label style="font-size:12px;color:#aaa;display:block;margin-bottom:4px">Grund (Gutschrift)</label>
                <input type="text" name="gs_grund" class="ret-input" style="width:100%" placeholder="z.B. Reklamation, falsche Ware…">
            </div>
        </div>

        <?php if ($kdEmail): ?>
        <div class="ret-card">
            <div style="font-size:14px;font-weight:700;margin-bottom:10px;color:#aaa">E-Mail an Kunden</div>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;color:#eee;font-size:14px">
                <input type="checkbox" name="mail_senden" value="1" style="width:18px;height:18px;accent-color:#e94560">
                Benachrichtigung senden
            </label>
            <div style="font-size:12px;color:#555;margin-top:6px"><?= htmlspecialchars($kdEmail) ?></div>
            <div style="margin-top:10px">
                <label style="font-size:12px;color:#aaa;display:block;margin-bottom:4px">Notiz (optional)</label>
                <input type="text" name="mail_notiz" class="ret-input" style="width:100%;font-size:14px" placeholder="Interne Anmerkung für den Kunden…">
            </div>
        </div>
        <?php else: ?>
        <div class="ret-card">
            <div style="font-size:13px;color:#555">⚠ Keine E-Mail-Adresse beim Kunden hinterlegt</div>
        </div>
        <?php endif; ?>

        <button type="submit" class="pp-btn pp-btn-success" style="width:100%;font-size:20px;padding:18px">
            ✓ Retoure verarbeiten
        </button>
    </div>

</div>
</form>

<script>
function ergebnisGewaehlt(val) {
    document.getElementById('gs-bereich').style.display = val === 'gutschrift' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
