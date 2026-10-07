<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/Positionsrechnung.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';

$auftragId = (int)($_GET['auftrag_id'] ?? 0);
if (!$auftragId) {
    header('Location: ' . BASE_PATH . '/auftraege/liste.php');
    exit;
}

$auftragService  = new AuftragService();
$dokumentService = new DokumentService();

$auftrag    = $auftragService->getById($auftragId);
$positionen = $auftragService->getPositionen($auftragId);
$rechnung   = $dokumentService->getRechnung($auftragId);

// Originalbeleg: Rechnung oder (an der Kasse bezahlt) Kassenbon
$bonBelege = $dokumentService->kassenbonBelege($auftragId);
if (!$auftrag || (!$rechnung && !$bonBelege)) {
    $_SESSION['fehler'] = ['Kein gültiger Auftrag oder kein Beleg (Rechnung/Kassenbon) gefunden.'];
    header('Location: ' . BASE_PATH . '/auftraege/detail.php?id=' . $auftragId);
    exit;
}

$fehler  = $_SESSION['fehler']  ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

// Stornorechnung-Betrag: was verrechnet und noch nicht korrigiert/erstattet ist (Kasse,
// Packplatz-Retoure, frühere Korrektur) -- plus Versandkosten, die noch nicht erstattet sind.
$versandErstattbar = $dokumentService->versandErstattbar($auftragId);
$schonKorrigiert   = array_sum(array_map(fn($p) => (int)($p['menge_gutgeschrieben'] ?? 0), $positionen)) > 0;
$alleRechnungen    = Database::getInstance()->prepare("SELECT rechnung_nr, bruttobetrag, erstellt_am FROM rechnungen WHERE auftrag_id = ? ORDER BY id");
$alleRechnungen->execute([$auftragId]);
$alleRechnungen    = $alleRechnungen->fetchAll(PDO::FETCH_ASSOC);
$vollstornoBetrag = 0.0;
foreach ($positionen as $pos) {
    $offen = min((int)$pos['menge'], (int)$pos['menge_verrechnet']) - (int)($pos['menge_gutgeschrieben'] ?? 0);
    if ($offen <= 0) continue;
    $vollstornoBetrag += Positionsrechnung::ausPosition($pos, $offen)['brutto'];
}

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div style="max-width:800px; margin:0 auto;">

<h2 style="margin-bottom:4px;">Rechnungskorrektur / Stornorechnung erstellen</h2>
<p style="color:#666; margin-bottom:16px; font-size:0.9em;">
    Auftrag <strong><?= htmlspecialchars($auftrag['auftrag_nr']) ?></strong> &mdash;
    <?php if ($bonBelege): ?>Kassenbon <?= implode(', ', array_map(fn($b) => '<strong>' . htmlspecialchars(substr($b['nr'], 10)) . '</strong> (' . $b['datum'] . ')', $bonBelege)) ?><?= $alleRechnungen ? ' · ' : '' ?><?php endif; ?>
    <?php if ($alleRechnungen): ?><?= count($alleRechnungen) > 1 ? 'Rechnungen' : 'Rechnung' ?>
    <?= implode(', ', array_map(fn($r) => '<strong>' . htmlspecialchars($r['rechnung_nr']) . '</strong> ('
        . number_format((float)$r['bruttobetrag'], 2, ',', '.') . ' EUR, ' . date('d.m.Y', strtotime($r['erstellt_am'])) . ')', $alleRechnungen)) ?>
    <?php endif; ?>
</p>

<?php if (!empty($fehler)): ?>
    <div class="alert alert-error" style="margin-bottom:16px;">
        <?= implode('<br>', array_map('htmlspecialchars', $fehler)) ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= BASE_PATH ?>/auftraege/gutschrift_speichern.php" id="gs-form">
    <input type="hidden" name="auftrag_id" value="<?= $auftragId ?>">
    <input type="hidden" name="rechnung_id" value="<?= (int)($rechnung['id'] ?? 0) ?>">

    <!-- Art der Korrektur -->
    <div class="erp-card" style="margin-bottom:16px; padding:16px;">
        <div style="font-weight:600; margin-bottom:10px;">Art der Korrektur</div>
        <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer;">
            <input type="radio" name="gs_art" value="vollstorno"
                   <?= ($formdata['gs_art'] ?? '') === 'vollstorno' ? 'checked' : '' ?> id="gs_vollstorno">
            <span>Stornorechnung — <?= $schonKorrigiert ? 'alles noch nicht Korrigierte' : 'alles' ?>
                (<span id="vs-betrag" data-ware="<?= round($vollstornoBetrag, 2) ?>"><?= number_format($vollstornoBetrag + $versandErstattbar, 2, ',', '.') ?></span> EUR<?= $versandErstattbar > 0 ? ' inkl. Versandkosten' : '' ?>)
                <?php if ($schonKorrigiert): ?>
                    <br><small style="color:#64748b">Ein Teil wurde bereits korrigiert bzw. erstattet (Kasse, Packplatz-Retoure oder frühere Rechnungskorrektur) und ist nicht mehr enthalten.</small>
                <?php endif; ?>
            </span>
        </label>
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
            <input type="radio" name="gs_art" value="teilgutschrift"
                   <?= ($formdata['gs_art'] ?? 'teilgutschrift') !== 'vollstorno' ? 'checked' : '' ?> id="gs_teil">
            <span>Rechnungskorrektur — Positionen auswählen</span>
        </label>
    </div>

    <!-- Positionstabelle (nur bei Rechnungskorrektur) -->
    <div id="gs-positionen" class="erp-card" style="margin-bottom:16px; padding:16px;">
        <div style="font-weight:600; margin-bottom:10px;">Positionen</div>
        <table class="erp-table">
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th>Bezeichnung</th>
                    <th style="width:60px; text-align:right;">Menge</th>
                    <th style="width:70px; text-align:right;">Korr.-Menge</th>
                    <th style="width:80px; text-align:right;">E-Preis</th>
                    <th style="width:90px; text-align:right;">Korr.-Betrag</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($positionen as $i => $pos):
                    $zeile        = Positionsrechnung::ausPosition($pos);
                    $einzelBrutto = $zeile['einzel_brutto'];
                    $gesamtBrutto = $zeile['brutto'];
                    // Bereits gutgeschriebene Menge (Kasse, Packplatz-Retoure, frühere
                    // Gutschrift) darf hier nicht nochmal gutgeschrieben werden.
                    $bereitsRetourniert = (int)($pos['menge_gutgeschrieben'] ?? 0);
                    $maxGutschrift      = max(0, min((int)$pos['menge'], (int)$pos['menge_verrechnet']) - $bereitsRetourniert); // nur Verrechnetes
                    $savedMenge   = $formdata['positionen'][$i]['menge'] ?? $maxGutschrift;
                    $savedChecked = (isset($formdata['positionen'][$i]) || empty($formdata)) && $maxGutschrift > 0;
                ?>
                <tr class="gs-pos-row">
                    <td>
                        <input type="checkbox" name="positionen[<?= $i ?>][aktiv]" value="1"
                               class="gs-checkbox" data-idx="<?= $i ?>"
                               <?= $savedChecked ? 'checked' : '' ?> <?= $maxGutschrift <= 0 ? 'disabled' : '' ?>>
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
                        <?php if ($bereitsRetourniert > 0): ?>
                            <br><small style="color:#dc2626;">bereits <?= $bereitsRetourniert ?>× korrigiert/erstattet<?= $maxGutschrift <= 0 ? ' — nichts mehr zu korrigieren' : '' ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;"><?= $pos['menge'] ?></td>
                    <td style="text-align:right;">
                        <input type="number" name="positionen[<?= $i ?>][menge]"
                               class="erp-input gs-menge" data-idx="<?= $i ?>"
                               data-einzelbrutto="<?= $einzelBrutto ?>"
                               data-rabatt="<?= $pos['rabatt_prozent'] ?>"
                               min="<?= $maxGutschrift > 0 ? 1 : 0 ?>" max="<?= $maxGutschrift ?>"
                               value="<?= (int)$savedMenge ?>" <?= $maxGutschrift <= 0 ? 'disabled' : '' ?>
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
            Korrekturbetrag gesamt:
            <strong id="gs-gesamt">0,00</strong> EUR
        </div>
    </div>

    <!-- Optionen -->
    <div class="erp-card" style="margin-bottom:16px; padding:16px;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <label class="form-label">Grund (steht auf dem Beleg)</label>
                <input type="text" name="grund" class="erp-input"
                       placeholder="Rückgabe / Reklamation / Kulanz …"
                       value="<?= htmlspecialchars($formdata['grund'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label" style="display:flex; align-items:center; gap:8px; padding-top:24px; cursor:pointer;">
                    <input type="checkbox" name="lager_rueckbuchen" value="1"
                           <?= !empty($formdata['lager_rueckbuchen']) ? 'checked' : '' ?>>
                    Ware zur Prüfung an den Packplatz (Rücklagerung)
                </label>
                <?php if ($versandErstattbar > 0): ?>
                <label class="form-label" style="display:flex; align-items:center; gap:8px; margin-top:8px; cursor:pointer;">
                    <input type="checkbox" name="versand_erstatten" value="1" id="versand-erstatten"
                           data-betrag="<?= $versandErstattbar ?>" <?= !empty($formdata['versand_erstatten']) ? 'checked' : '' ?>>
                    Versandkosten erstatten (<?= number_format($versandErstattbar, 2, ',', '.') ?> EUR)
                </label>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div style="display:flex; gap:10px;">
        <button type="submit" class="erp-btn">Beleg erstellen</button>
        <a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= $auftragId ?>" class="erp-btn erp-btn-secondary">Abbrechen</a>
    </div>
</form>
</div>

<script src="<?= BASE_PATH ?>/js/auftraege_gutschrift.js?v=<?= filemtime(__DIR__ . '/../js/auftraege_gutschrift.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
