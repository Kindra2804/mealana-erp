<?php
/**
 * Kassen-Liste / Preisliste (KassenlisteService) -- jede Variante einzeln, mit Barcode für die Kasse.
 * Aufruf:  Seitenleiste Artikel → Preisschilder → 🧾 Kassen-Liste (Filter Kategorie/Hersteller/Partner)
 *          POST ids[] aus der Artikelliste (Massenaktion; Väter bringen ihre Varianten mit)
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/schilder/KassenlisteService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$svc = new KassenlisteService();
$r   = $_POST + $_GET;
$ids = $r['ids'] ?? [];
if (is_string($ids)) $ids = explode(',', $ids);
$ids = array_values(array_filter(array_map('intval', (array) $ids)));
$filter = [
    'kategorie_id'  => (int) ($r['kategorie_id'] ?? 0),
    'hersteller_id' => (int) ($r['hersteller_id'] ?? 0),
    'partner_id'    => (int) ($r['partner_id'] ?? 0),
    // Standard (Seite frisch geöffnet): nur lagernde
    'nur_lagernd'   => isset($r['gesendet']) ? !empty($r['nur_lagernd']) : true,
    'ids'           => $ids,
];
$zeilen = $svc->artikel($filter);
$titel  = $svc->filterText($filter) ?: ($ids ? 'Auswahl aus der Artikelliste' : '');

$pageTitle    = 'Kassen-Liste / Preisliste';
$activeModule = 'artikel';
$kategorienBaum = (new ArtikelService())->getKategorienBaum();
require_once __DIR__ . '/../includes/shell_top.php';

$opt = function (array $liste, int $wert) {
    $h = '<option value="0">alle</option>';
    foreach ($liste as $id => $name) $h .= '<option value="' . (int) $id . '"' . ($id === $wert ? ' selected' : '') . '>' . htmlspecialchars($name) . '</option>';
    return $h;
};
?>
<div style="padding:var(--space-md);max-width:1300px">

<form method="post" class="card" style="margin-bottom:var(--space-sm)">
    <input type="hidden" name="gesendet" value="1">
    <?php if ($ids): ?><input type="hidden" name="ids" value="<?= htmlspecialchars(implode(',', $ids)) ?>"><?php endif; ?>
    <div style="font-weight:700;color:var(--color-nav);margin-bottom:8px">🧾 Welche Artikel?</div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;font-size:13px">
        <label>Partner (Fachmieter)<br><select name="partner_id" class="erp-select" style="width:auto;max-width:260px"><?= $opt($svc->partner(), $filter['partner_id']) ?></select></label>
        <label>Hersteller<br><select name="hersteller_id" class="erp-select" style="width:auto;max-width:220px"><?= $opt($svc->hersteller(), $filter['hersteller_id']) ?></select></label>
        <label>Kategorie (mit Unterkategorien)<br><select name="kategorie_id" class="erp-select" style="width:auto;max-width:280px"><?= $opt($svc->kategorien(), $filter['kategorie_id']) ?></select></label>
        <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="nur_lagernd" value="1" <?= $filter['nur_lagernd'] ? 'checked' : '' ?>> nur lagernde</label>
        <button type="submit" class="btn btn-secondary btn-sm">Anzeigen</button>
    </div>
    <?php if ($ids): ?>
        <div style="font-size:12px;color:var(--color-text-muted);margin-top:8px">Auswahl aus der Artikelliste (<?= count($ids) ?> markiert, Väter mit allen Varianten) – die Filter grenzen zusätzlich ein. <a href="kassenliste.php">Auswahl verwerfen</a></div>
    <?php endif; ?>
</form>

<?php if (!$zeilen): ?>
    <div class="card" style="font-size:13px;line-height:1.6">
        <?php if ($filter['partner_id'] || $filter['hersteller_id'] || $filter['kategorie_id'] || $ids): ?>
            <strong>Keine passenden Artikel.</strong><?= $filter['nur_lagernd'] ? ' Vielleicht ohne „nur lagernde“ versuchen?' : '' ?>
        <?php else: ?>
            <strong>Bitte oben Partner, Hersteller oder Kategorie wählen</strong> – oder in der Artikelliste Artikel anhaken und „🧾 Kassen-Liste drucken“ wählen.<br>
            Jede Variante steht einzeln drin, mit Standardpreis und Barcode (EAN, sonst die Artikelnummer). Läuft eine Aktion, nimmt die Kasse beim Scannen ohnehin den Aktionspreis.
        <?php endif; ?>
    </div>
<?php else: ?>
<form method="post" action="kassenliste_drucken.php" target="_blank" id="kl-form">
    <input type="hidden" name="titel" value="<?= htmlspecialchars($titel) ?>">
    <input type="hidden" name="untertitel" value="<?= $filter['nur_lagernd'] ? 'nur lagernde Artikel' : 'alle aktiven Artikel' ?>">
    <div class="card" style="margin-bottom:var(--space-sm)">
        <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;font-size:13px">
            <?php foreach (KassenlisteService::VORLAGEN as $key => $v): ?>
                <label style="display:flex;gap:6px;align-items:center"><input type="radio" name="vorlage" value="<?= $key ?>" <?= $key === 'kassenliste' ? 'checked' : '' ?>> <?= $v['symbol'] ?> <strong><?= htmlspecialchars($v['name']) ?></strong></label>
            <?php endforeach; ?>
            <span style="border-left:1px solid var(--color-border,#ddd);height:20px"></span>
            <span class="kl-nur-liste" style="display:flex;gap:24px">
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="mit_bild" value="1" checked> Bild</label>
                <label style="display:flex;gap:6px;align-items:center" id="kl-barcode"><input type="checkbox" name="mit_barcode" value="1" checked> Barcode</label>
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="gruppieren" value="1" checked> nach Kategorie gruppieren</label>
            </span>
            <span class="kl-nur-mini" style="display:none;gap:24px;align-items:center">
                <label>Start bei Feld
                    <select name="start" class="erp-select" style="width:auto">
                        <?php for ($i = 1; $i <= KassenlisteService::MINI_JE_SEITE; $i++): ?><option value="<?= $i ?>"><?= $i ?><?= $i === 1 ? ' (neues Blatt)' : '' ?></option><?php endfor; ?>
                    </select>
                </label>
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="schnitt" value="1" checked> Schnittlinien</label>
            </span>
        </div>
        <div style="font-size:11px;color:var(--color-text-muted);margin-top:8px">
            <span class="kl-nur-liste">Kassen-Liste: ca. 12 Zeilen je A4 mit Barcode zum Scannen neben der Kasse. Preisliste: kompakt ohne Barcode, ca. 25 Zeilen (ohne Bild ca. 40).</span>
            <span class="kl-nur-mini" style="display:none">Mini-Anhänger: 27 je A4 (3 × 9), Feld 1 oben links. Kurzname unten in der Liste vergeben – er wird je Artikel gemerkt. Leer = automatisch (Text in „…“ + Zusatz). Anzahl ist mit dem Bestand vorbelegt, 0 = keiner.</span>
        </div>
    </div>

    <div class="card">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="klAlle(true)">Alle</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="klAlle(false)">Keine</button>
            <span id="kl-zaehler" style="font-size:13px;color:var(--color-text-muted)"></span>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto" id="kl-drucken">🖨 PDF erzeugen</button>
        </div>
        <table class="erp-table">
            <thead><tr><th style="width:28px"></th><th>Artikelnr.</th><th>Name</th><th style="text-align:right">Preis</th><th>Barcode</th><th style="text-align:right">Bestand</th>
                <th class="kl-nur-mini" style="display:none">Kurzname (max. <?= KassenlisteService::KURZ_MAX ?>)</th><th class="kl-nur-mini" style="display:none">Anzahl</th></tr></thead>
            <tbody>
            <?php $gruppe = null; $gNr = 0; foreach ($zeilen as $z): ?>
                <?php if ($z['gruppe'] !== $gruppe): $gruppe = $z['gruppe']; $gNr++; ?>
                    <tr><td style="background:#e8eef6"><input type="checkbox" class="kl-gruppe" data-g="<?= $gNr ?>" checked title="Alle Artikel dieser Kategorie wählen/abwählen"></td>
                        <td colspan="7" style="background:#e8eef6;color:var(--color-nav);font-weight:700;font-size:12px"><label style="cursor:pointer" onclick="this.closest('tr').querySelector('.kl-gruppe').click()">▸ <?= htmlspecialchars($gruppe) ?></label> <span class="kl-gruppe-zahl" data-g="<?= $gNr ?>" style="font-weight:400;color:var(--color-text-muted)"></span></td></tr>
                <?php endif; ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= $z['id'] ?>" class="kl-cb" data-g="<?= $gNr ?>" checked></td>
                    <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($z['artikelnummer']) ?></td>
                    <td><?= htmlspecialchars($z['name']) ?></td>
                    <td style="text-align:right;white-space:nowrap"><?= $z['preis'] !== null ? number_format($z['preis'], 2, ',', '.') . ' €' : '<span style="color:#d97706">kein Preis</span>' ?></td>
                    <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($z['code']) ?><?= $z['code'] === $z['artikelnummer'] ? ' <span style="color:var(--color-text-muted);font-family:inherit;font-size:11px">(Art.-Nr.)</span>' : '' ?></td>
                    <td style="text-align:right"><?= $z['bestand'] === null ? '–' : rtrim(rtrim(number_format($z['bestand'], 2, ',', ''), '0'), ',') ?></td>
                    <td class="kl-nur-mini" style="display:none"><input type="text" name="kurz[<?= $z['id'] ?>]" value="<?= htmlspecialchars($z['kurzname']) ?>" placeholder="<?= htmlspecialchars($z['kurz_auto']) ?>" maxlength="<?= KassenlisteService::KURZ_MAX ?>" class="erp-input" style="width:240px;padding:3px 6px;font-size:12px"></td>
                    <td class="kl-nur-mini" style="display:none"><input type="number" name="anzahl[<?= $z['id'] ?>]" value="<?= $z['bestand'] !== null && $z['bestand'] >= 1 ? (int) $z['bestand'] : 1 ?>" min="0" max="200" class="erp-input" style="width:60px;padding:3px 6px;font-size:12px"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>
<?php endif; ?>
</div>

<script src="<?= BASE_PATH ?>/js/kassenliste.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
