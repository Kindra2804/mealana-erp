<?php
// Dropshipping-Karte in der Auftrags-Detailseite (eingebunden aus detail.php, braucht $auftrag).
// Zeigt nur etwas, wenn der Auftrag per Strecke gehen kann oder schon per Strecke bestellt ist.
if (!isset($auftrag)) exit;
if (!Lizenz::hat('dropshipping')) return;   // Modul nicht lizenziert → keine Karte
require_once __DIR__ . '/../../src/modules/dropshipping/DropshippingService.php';

$dsService    = new DropshippingService();
$dsPruefung   = $dsService->pruefen((int)$auftrag['id']);
$dsBestellung = $dsPruefung['bestellung'];
if (!$dsPruefung['moeglich'] && !$dsBestellung) return;

$dsKopierFeld = function (string $label, ?string $wert): string {
    if ($wert === null || trim($wert) === '') return '';
    $w = htmlspecialchars($wert);
    return '<div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">'
         . ($label !== '' ? '<span style="width:70px;font-size:12px;color:var(--color-text-muted)">' . htmlspecialchars($label) . '</span>' : '')
         . '<span style="flex:1">' . $w . '</span>'
         . '<button type="button" class="btn btn-secondary btn-sm ds-kopier-btn" style="padding:1px 7px" data-wert="' . $w . '" title="Kopieren">⧉</button></div>';
};
?>
<div class="card" style="margin-bottom:12px;border-left:3px solid #2563eb">
    <div class="card-header" style="display:flex;align-items:center;gap:8px">
        Dropshipping
        <?php if (!$dsBestellung): ?>
            <span class="chip chip-aktiv">Strecke möglich</span>
        <?php elseif ($dsBestellung['status'] === 'offen'): ?>
            <span class="chip chip-auslauf">bei <?= htmlspecialchars($dsBestellung['lieferant_name']) ?> bestellt</span>
        <?php else: ?>
            <span class="chip chip-inaktiv">per Strecke versendet</span>
        <?php endif; ?>
    </div>
    <div style="padding:12px 16px">

    <?php if (!$dsBestellung): ?>
        <?php $dsLief = $dsPruefung['lieferant']; ?>
        <p style="margin:0 0 10px">
            Alle Positionen sind bei <strong><?= htmlspecialchars($dsLief['name']) ?></strong> per Dropshipping lieferbar.
            Der Lieferant schickt direkt an den Kunden, es gibt keine Lagerbuchung und keinen Packplatz.
        </p>
        <?php if (!$dsPruefung['bereit']): ?>
            <p style="margin:0 0 10px;color:#2563eb"><strong style="display:inline-block;width:18px;height:18px;border-radius:50%;background:#2563eb;color:#fff;text-align:center;line-height:18px;font-size:12px">!</strong>
                Noch nicht bezahlt — Strecke normalerweise erst nach Zahlungseingang bestellen.</p>
        <?php endif; ?>
        <form method="post" action="dropshipping_aktion.php" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"
              <?= !$dsPruefung['bereit'] ? 'onsubmit="return confirm(\'Der Auftrag ist noch nicht bezahlt. Trotzdem per Dropshipping bestellen?\')"' : '' ?>>
            <input type="hidden" name="aktion" value="bestellen">
            <input type="hidden" name="auftrag_id" value="<?= (int)$auftrag['id'] ?>">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Pauschale (€ netto)</label>
                <input type="text" name="pauschale" class="erp-input" style="width:110px" inputmode="decimal"
                       value="<?= $dsLief['dropship_pauschale'] !== null ? number_format((float)$dsLief['dropship_pauschale'], 2, ',', '') : '' ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Per Dropshipping bestellen</button>
        </form>

    <?php else: ?>
        <?php
        $dsNr   = DropshippingService::bestellnummer((int)$dsBestellung['id'], $dsBestellung['bestelldatum']);
        $dsAdr  = json_decode($dsBestellung['lieferadresse_snapshot'] ?? '{}', true) ?: [];
        $dsPos  = $dsService->bestellPositionen((int)$dsBestellung['id']);
        $dsEk   = 0.0;
        foreach ($dsPos as $p) $dsEk += (float)$p['ek_preis'] * (float)$p['menge_bestellt'];
        ?>
        <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:10px">
            <a href="<?= BASE_PATH ?>/bestellungen/detail.php?id=<?= (int)$dsBestellung['id'] ?>"><?= $dsNr ?></a>
            vom <?= date('d.m.Y', strtotime($dsBestellung['bestelldatum'])) ?> ·
            EK € <?= number_format($dsEk, 2, ',', '.') ?>
            <?php if ($dsBestellung['dropship_pauschale'] !== null): ?>
                + Pauschale € <?= number_format((float)$dsBestellung['dropship_pauschale'], 2, ',', '.') ?>
            <?php endif; ?>
            = € <?= number_format($dsEk + (float)$dsBestellung['dropship_pauschale'], 2, ',', '.') ?> netto erwartete Lieferanten-Rechnung
        </div>

        <?php if ($dsBestellung['status'] === 'offen'): ?>
        <div style="display:grid;grid-template-columns:minmax(260px,1fr) 2fr;gap:20px">
            <div>
                <div style="font-weight:600;margin-bottom:6px">Lieferadresse fürs Portal</div>
                <?= $dsKopierFeld('Firma', ($dsAdr['firma'] ?? '') ?: ($dsAdr['firmenname'] ?? null)) ?>
                <?php if (!empty($dsAdr['vorname']) || !empty($dsAdr['nachname'])): ?>
                    <?= $dsKopierFeld('Vorname', $dsAdr['vorname'] ?? null) ?>
                    <?= $dsKopierFeld('Nachname', $dsAdr['nachname'] ?? null) ?>
                <?php else: ?>
                    <?= $dsKopierFeld('Name', $dsAdr['name'] ?? null) ?>
                <?php endif; ?>
                <?= $dsKopierFeld('Straße', trim(($dsAdr['strasse'] ?? '') . ' ' . ($dsAdr['hausnummer'] ?? ''))) ?>
                <?= $dsKopierFeld('Zusatz', $dsAdr['zusatz'] ?? null) ?>
                <?= $dsKopierFeld('PLZ', $dsAdr['plz'] ?? null) ?>
                <?= $dsKopierFeld('Ort', $dsAdr['ort'] ?? null) ?>
                <?= $dsKopierFeld('Land', $dsAdr['land'] ?? null) ?>
                <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
                    Als E-Mail im Portal <strong>unsere</strong> Adresse angeben — dann kommt die Versandmail mit dem Tracking zu uns.
                </div>
            </div>
            <div>
                <div style="font-weight:600;margin-bottom:6px">Artikel</div>
                <table class="erp-table">
                    <thead><tr><th>Lief.-Art.-Nr.</th><th>Artikel</th><th>EAN</th><th style="text-align:right">Menge</th></tr></thead>
                    <tbody>
                    <?php foreach ($dsPos as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['artikelnummer_lieferant'] !== null && $p['artikelnummer_lieferant'] !== '' && $p['artikelnummer_lieferant'] !== '0'): ?>
                                    <?= $dsKopierFeld('', $p['artikelnummer_lieferant']) ?>
                                <?php else: ?>
                                    <span style="color:var(--color-text-muted)">– (unsere: <?= htmlspecialchars($p['artikelnummer']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['artikel_name']) ?></td>
                            <td><?= $p['ean'] ? $dsKopierFeld('', $p['ean']) : '–' ?></td>
                            <td style="text-align:right;font-weight:600"><?= rtrim(rtrim(number_format((float)$p['menge_bestellt'], 3, ',', ''), '0'), ',') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:14px;padding-top:12px;border-top:1px solid var(--color-border)">
            <form method="post" action="dropshipping_aktion.php" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
                <input type="hidden" name="aktion" value="versendet">
                <input type="hidden" name="auftrag_id" value="<?= (int)$auftrag['id'] ?>">
                <input type="hidden" name="bestellung_id" value="<?= (int)$dsBestellung['id'] ?>">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Trackingnummer (aus der Versandmail)</label>
                    <input type="text" name="tracking" class="erp-input" style="width:240px" required>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Versanddienstleister</label>
                    <select name="versanddienstleister" class="erp-select">
                        <?php foreach (VersandMail::DIENSTLEISTER as $k => $label): ?>
                            <option value="<?= $k ?>" <?= $k === 'dhl' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"
                        onclick="return confirm('Als versendet buchen? Der Kunde bekommt die Versandmail und die Rechnung.')">Versendet + Tracking</button>
            </form>
            <form method="post" action="dropshipping_aktion.php" style="margin-left:auto"
                  onsubmit="return confirm('Dropship-Bestellung stornieren? Der Auftrag geht dann wieder normal über den Packplatz.')">
                <input type="hidden" name="aktion" value="stornieren">
                <input type="hidden" name="auftrag_id" value="<?= (int)$auftrag['id'] ?>">
                <input type="hidden" name="bestellung_id" value="<?= (int)$dsBestellung['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Dropship-Bestellung stornieren</button>
            </form>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>
<script src="<?= BASE_PATH ?>/js/dropshipping.js"></script>
