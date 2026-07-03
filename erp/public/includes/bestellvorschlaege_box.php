<?php
/**
 * Bestellvorschläge-Box: Artikel unter Meldebestand oder mit Unterdeckung (verfügbar < 0).
 *
 * Einbinden mit: $mode = 'neu' | 'liste'; require ...
 *   'neu'   → "Zur Bestellung"-Button (via JS per Lieferant-Auswahl aktiviert/deaktiviert)
 *   'liste' → "Bestellen"-Link der neu.php mit vorgewähltem Lieferant öffnet
 */

if (!isset($bestellService)) {
    require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';
    $bestellService = new BestellungService();
}

$vorschlaege = $bestellService->getBestellvorschlaege();
if (empty($vorschlaege)) return;

$mode = $mode ?? 'liste';
?>

<details id="bestellvorschlaege-box" style="margin-bottom:12px" open>
    <summary style="cursor:pointer;padding:10px 14px;background:#fff8e1;border:1px solid #f59e0b;border-radius:4px;font-weight:600;font-size:13px;list-style:none;display:flex;align-items:center;gap:8px;user-select:none">
        <span>⚠ Bestellvorschläge</span>
        <span style="background:#f59e0b;color:#fff;border-radius:10px;padding:1px 8px;font-size:12px;font-weight:600"><?= count($vorschlaege) ?></span>
        <span style="margin-left:auto;font-size:11px;font-weight:400;color:var(--color-text-muted)">unter Meldebestand / Unterdeckung</span>
    </summary>
    <div style="border:1px solid #f59e0b;border-top:none;border-radius:0 0 4px 4px;background:#fff;overflow:hidden">
        <table class="erp-table" style="margin:0">
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th style="text-align:right">Bestand</th>
                    <th style="text-align:right;white-space:nowrap">Melde-Bst.</th>
                    <th>Std.-Lieferant</th>
                    <th style="text-align:right">VPE</th>
                    <th style="text-align:right">Vorschlag</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vorschlaege as $v):
                $ist   = (float)$v['gesamtbestand'];
                $res   = (float)$v['reserviert'];
                $verf  = $ist - $res;
                $melde = $v['meldebestand'] !== null ? (int)$v['meldebestand'] : null;

                $bedarf = 0;
                if ($verf < 0) $bedarf = (int)ceil(abs($verf));
                if ($melde !== null && $verf < $melde) $bedarf = max($bedarf, (int)ceil($melde - $verf));

                $vpe      = max(1, (int)($v['vpe_menge'] ?? 1));
                $anzVpe   = (int)ceil($bedarf / $vpe);
                $vorschlagMenge = $anzVpe * $vpe;

                $stdLiefId   = (int)($v['std_lieferant_id'] ?? 0);
                $stdLiefName = $v['std_lieferant_name'] ?? '–';
                $netto_ek    = (float)($v['netto_ek'] ?? 0);

                $verfColor = $verf < 0 ? '#dc2626' : ($verf <= 0 ? '#d97706' : '#059669');
                $bestandHtml  = number_format($ist, 0, ',', '.');
                $bestandHtml .= '<br><span style="font-size:10px;color:' . ($res > 0 ? '#d97706' : 'var(--color-text-muted)') . '">' . number_format($res, 0, ',', '.') . ' res.</span>';
                $bestandHtml .= '<br><span style="font-size:10px;color:' . $verfColor . ';font-weight:600">' . number_format($verf, 0, ',', '.') . ' verf.</span>';
            ?>
                <tr data-std-lieferant="<?= $stdLiefId ?>">
                    <td>
                        <div style="font-size:13px;font-weight:500"><?= htmlspecialchars($v['artikel_name']) ?></div>
                        <div style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars($v['artikelnummer']) ?></div>
                    </td>
                    <td style="text-align:right;line-height:1.7;font-size:12px"><?= $bestandHtml ?></td>
                    <td style="text-align:right;color:var(--color-text-muted);font-size:12px">
                        <?= $melde !== null ? $melde : '–' ?>
                    </td>
                    <td style="font-size:12px"><?= htmlspecialchars($stdLiefName) ?></td>
                    <td style="text-align:right;font-size:12px;color:var(--color-text-muted)"><?= $vpe > 1 ? $vpe . ' Stk' : '–' ?></td>
                    <td style="text-align:right;font-size:12px;font-weight:600;color:#c0820a">
                        <?php if ($vpe > 1): ?>
                            <?= $anzVpe ?> VPE<br><span style="font-weight:400;font-size:11px">(= <?= $vorschlagMenge ?> Stk)</span>
                        <?php else: ?>
                            <?= $vorschlagMenge ?> Stk
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <?php if ($mode === 'neu'): ?>
                            <?php if ($stdLiefId): ?>
                                <button type="button"
                                    class="btn btn-secondary btn-sm vorschlag-btn"
                                    style="opacity:0.4;pointer-events:none"
                                    data-artikel-id="<?= (int)$v['id'] ?>"
                                    data-artikel-name="<?= htmlspecialchars($v['artikel_name'], ENT_QUOTES) ?>"
                                    data-menge="<?= $vorschlagMenge ?>"
                                    data-ek="<?= $netto_ek ?>"
                                    data-lieferant-id="<?= $stdLiefId ?>"
                                    onclick="vorschlagUebernehmen(this)">
                                    + Zur Bestellung
                                </button>
                            <?php else: ?>
                                <span style="font-size:11px;color:var(--color-text-muted)">kein Std.-Lieferant</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($stdLiefId): ?>
                                <a href="<?= BASE_PATH ?>/bestellungen/neu.php?lieferant_id=<?= $stdLiefId ?>" class="btn btn-secondary btn-sm" style="font-size:12px">Bestellen</a>
                            <?php else: ?>
                                <span style="font-size:11px;color:var(--color-text-muted)">kein Std.-Lieferant</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
