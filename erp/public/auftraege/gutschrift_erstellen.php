<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';

$auftragId = (int)($_GET['auftrag_id'] ?? 0);
if (!$auftragId) {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$auftragService  = new AuftragService();
$dokumentService = new DokumentService();

$auftrag    = $auftragService->getById($auftragId);
$positionen = $auftragService->getPositionen($auftragId);
$rechnung   = $dokumentService->getRechnung($auftragId);

if (!$auftrag || !$rechnung) {
    $_SESSION['fehler'] = ['Kein gültiger Auftrag oder keine Rechnung gefunden.'];
    header('Location: /mealana/auftraege/detail.php?id=' . $auftragId);
    exit;
}

$fehler  = $_SESSION['fehler']  ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div style="max-width:800px; margin:0 auto;">

<h2 style="margin-bottom:4px;">Gutschrift erstellen</h2>
<p style="color:#666; margin-bottom:16px; font-size:0.9em;">
    Auftrag <strong><?= htmlspecialchars($auftrag['auftrag_nr']) ?></strong> &mdash;
    Rechnung <strong><?= htmlspecialchars($rechnung['rechnung_nr']) ?></strong>
    (<?= number_format($rechnung['bruttobetrag'], 2, ',', '.') ?> EUR, <?= date('d.m.Y', strtotime($rechnung['erstellt_am'])) ?>)
</p>

<?php if (!empty($fehler)): ?>
    <div class="alert alert-error" style="margin-bottom:16px;">
        <?= implode('<br>', array_map('htmlspecialchars', $fehler)) ?>
    </div>
<?php endif; ?>

<form method="post" action="/mealana/auftraege/gutschrift_speichern.php" id="gs-form">
    <input type="hidden" name="auftrag_id" value="<?= $auftragId ?>">
    <input type="hidden" name="rechnung_id" value="<?= $rechnung['id'] ?>">

    <!-- Art der Gutschrift -->
    <div class="erp-card" style="margin-bottom:16px; padding:16px;">
        <div style="font-weight:600; margin-bottom:10px;">Art der Gutschrift</div>
        <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer;">
            <input type="radio" name="gs_art" value="vollstorno"
                   <?= ($formdata['gs_art'] ?? '') === 'vollstorno' ? 'checked' : '' ?> id="gs_vollstorno">
            <span>Vollstornierung — komplette Rechnung
                (<?= number_format($rechnung['bruttobetrag'], 2, ',', '.') ?> EUR)</span>
        </label>
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
            <input type="radio" name="gs_art" value="teilgutschrift"
                   <?= ($formdata['gs_art'] ?? 'teilgutschrift') !== 'vollstorno' ? 'checked' : '' ?> id="gs_teil">
            <span>Teilgutschrift — Positionen auswählen</span>
        </label>
    </div>

    <!-- Positionstabelle (nur bei Teilgutschrift) -->
    <div id="gs-positionen" class="erp-card" style="margin-bottom:16px; padding:16px;">
        <div style="font-weight:600; margin-bottom:10px;">Positionen</div>
        <table class="erp-table">
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th>Bezeichnung</th>
                    <th style="width:60px; text-align:right;">Menge</th>
                    <th style="width:70px; text-align:right;">GS-Menge</th>
                    <th style="width:80px; text-align:right;">E-Preis</th>
                    <th style="width:90px; text-align:right;">GS-Betrag</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($positionen as $i => $pos):
                    $einzelBrutto = round($pos['einzelpreis_netto'] * (1 + $pos['steuer_prozent'] / 100), 2);
                    $gesamtBrutto = round($pos['gesamtpreis_netto'] * (1 + $pos['steuer_prozent'] / 100), 2);
                    $savedMenge   = $formdata['positionen'][$i]['menge'] ?? $pos['menge'];
                    $savedChecked = isset($formdata['positionen'][$i]) || empty($formdata);
                ?>
                <tr class="gs-pos-row">
                    <td>
                        <input type="checkbox" name="positionen[<?= $i ?>][aktiv]" value="1"
                               class="gs-checkbox" data-idx="<?= $i ?>"
                               <?= $savedChecked ? 'checked' : '' ?>>
                        <input type="hidden" name="positionen[<?= $i ?>][pos_id]"
                               value="<?= $pos['id'] ?>">
                        <input type="hidden" name="positionen[<?= $i ?>][steuer_prozent]"
                               value="<?= $pos['steuer_prozent'] ?>">
                        <input type="hidden" name="positionen[<?= $i ?>][einzelpreis_netto]"
                               value="<?= $pos['einzelpreis_netto'] ?>">
                        <input type="hidden" name="positionen[<?= $i ?>][artikel_id]"
                               value="<?= $pos['artikel_id'] ?>">
                    </td>
                    <td><?= htmlspecialchars($pos['bezeichnung']) ?>
                        <?php if ($pos['rabatt_prozent'] > 0): ?>
                            <br><small style="color:#888;">Rabatt: <?= $pos['rabatt_prozent'] ?> %</small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;"><?= $pos['menge'] ?></td>
                    <td style="text-align:right;">
                        <input type="number" name="positionen[<?= $i ?>][menge]"
                               class="erp-input gs-menge" data-idx="<?= $i ?>"
                               data-einzelbrutto="<?= $einzelBrutto ?>"
                               data-rabatt="<?= $pos['rabatt_prozent'] ?>"
                               min="1" max="<?= $pos['menge'] ?>"
                               value="<?= (int)$savedMenge ?>"
                               style="width:55px; text-align:right; padding:2px 4px;">
                    </td>
                    <td style="text-align:right;"><?= number_format($einzelBrutto, 2, ',', '.') ?></td>
                    <td style="text-align:right; font-weight:600;" id="gs-betrag-<?= $i ?>">
                        <?= number_format($gesamtBrutto, 2, ',', '.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="text-align:right; margin-top:12px; font-size:1.05em;">
            GS-Betrag gesamt:
            <strong id="gs-gesamt">0,00</strong> EUR
        </div>
    </div>

    <!-- Optionen -->
    <div class="erp-card" style="margin-bottom:16px; padding:16px;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <label class="form-label">Grund (intern)</label>
                <input type="text" name="grund" class="erp-input"
                       placeholder="Rückgabe / Reklamation / Kulanz …"
                       value="<?= htmlspecialchars($formdata['grund'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label" style="display:flex; align-items:center; gap:8px; padding-top:24px; cursor:pointer;">
                    <input type="checkbox" name="lager_rueckbuchen" value="1"
                           <?= !empty($formdata['lager_rueckbuchen']) ? 'checked' : '' ?>>
                    Lagerbestand zurückbuchen
                </label>
            </div>
        </div>
    </div>

    <div style="display:flex; gap:10px;">
        <button type="submit" class="erp-btn">Gutschrift erstellen</button>
        <a href="/mealana/auftraege/detail.php?id=<?= $auftragId ?>" class="erp-btn erp-btn-secondary">Abbrechen</a>
    </div>
</form>
</div>

<script src="/mealana/js/auftraege_gutschrift.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
