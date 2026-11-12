<?php
/**
 * Grundpreise prüfen: Väter + Einzelartikel mit Inhalt/Einheit/Bezugsmenge/Anzeigen,
 * Problemfälle, Vorschau wie im Shop, Massenbearbeitung. Logik: GrundpreisPruefungService,
 * Oberfläche: js/artikel_grundpreise.js, Speichern: grundpreise_speichern.php.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/GrundpreisPruefungService.php';

$service = new GrundpreisPruefungService();
$shops   = $service->findShops();
$typen   = $service->findTypen();

$filter = [
    'shop_id'      => (int)($_GET['shop'] ?? 0),
    'typ_id'       => (int)($_GET['typ'] ?? 0),
    'q'            => trim($_GET['q'] ?? ''),
    'nur_probleme' => !isset($_GET['gefiltert']) || !empty($_GET['probleme']),
];
$zeilen = $service->finde($filter);

$pageTitle    = 'Grundpreise prüfen';
$activeModule = 'artikel';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<style>
.gp-filter { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:12px }
.gp-filter label { display:flex; flex-direction:column; font-size:12px; color:var(--color-text-muted); gap:3px }
.gp-filter select, .gp-filter input[type=text] { padding:6px 8px; font-size:13px }
.gp-masse { display:flex; flex-wrap:wrap; gap:8px; align-items:center; background:var(--color-bg-subtle, #f5f7fa); border:1px solid var(--color-border, #e2e8f0); border-radius:8px; padding:8px 10px; margin-bottom:10px; font-size:13px }
.gp-masse input, .gp-masse select { padding:4px 6px; font-size:13px }
.gp-tab { width:100%; border-collapse:collapse; font-size:13px }
.gp-tab th { text-align:left; font-weight:600; font-size:12px; color:var(--color-text-muted); padding:6px; border-bottom:2px solid var(--color-border, #e2e8f0); position:sticky; top:0; background:var(--color-surface, #fff); z-index:1 }
.gp-tab td { padding:5px 6px; border-bottom:1px solid var(--color-border, #eef1f5); vertical-align:middle }
.gp-tab tr.ist-geaendert td { background:#fff8e6 }
.gp-tab input.gp-zahl { width:70px; padding:4px 6px; font-size:13px; text-align:right }
.gp-tab select { padding:4px 6px; font-size:13px }
.gp-name small { display:block; color:var(--color-text-muted); font-size:11px }
.gp-vorschau { white-space:nowrap; color:#334155 }
.gp-vorschau.ist-leer { color:var(--color-text-muted) }
.gp-chip { display:inline-block; background:#fde8e8; color:#9b1c1c; border-radius:10px; padding:1px 8px; font-size:11px; margin:1px 3px 1px 0; white-space:nowrap }
.gp-ok { color:#15803d; font-size:12px }
.gp-fuss { position:sticky; bottom:0; background:var(--color-surface, #fff); border-top:1px solid var(--color-border, #e2e8f0); padding:10px 0; display:flex; gap:10px; align-items:center; justify-content:flex-end }
</style>

<form class="gp-filter" method="get">
    <input type="hidden" name="gefiltert" value="1">
    <label>Kanal
        <select name="shop"><option value="0">alle</option>
            <?php foreach ($shops as $s): ?><option value="<?= $s['id'] ?>" <?= $filter['shop_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
        </select></label>
    <label>Artikeltyp
        <select name="typ"><option value="0">alle</option>
            <?php foreach ($typen as $t): ?><option value="<?= $t['id'] ?>" <?= $filter['typ_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
        </select></label>
    <label>Suche<input type="text" name="q" value="<?= htmlspecialchars($filter['q']) ?>" placeholder="Artikelnummer oder Name"></label>
    <label style="flex-direction:row;align-items:center;gap:6px;color:inherit;font-size:13px;padding-bottom:6px">
        <input type="checkbox" name="probleme" value="1" <?= $filter['nur_probleme'] ? 'checked' : '' ?>> nur Problemfälle</label>
    <button type="submit" class="btn btn-secondary btn-sm">Anzeigen</button>
    <span style="font-size:12px;color:var(--color-text-muted);padding-bottom:6px"><?= count($zeilen) ?> Artikel<?= count($zeilen) >= 2000 ? ' (max. 2000 – Filter eingrenzen)' : '' ?></span>
</form>

<p style="font-size:12px;color:var(--color-text-muted);margin:0 0 10px">
    Gezeigt werden Vaterartikel und Einzelartikel. Beim Speichern gehen die Angaben (wie beim normalen Speichern eines Vaters)
    an alle Kinder, und die Artikel werden für den Shop-Abgleich vorgemerkt. Garn mit Einheit g zeigt der Shop immer je 1 kg (Preisauszeichnungsgesetz).
</p>

<?php if (!$zeilen): ?>
    <div class="card"><p style="padding:var(--space-md)"><?= $filter['nur_probleme'] ? '✓ Keine Problemfälle für diesen Filter.' : 'Keine Artikel gefunden.' ?></p></div>
<?php else: ?>

<div class="gp-masse">
    <strong>Für markierte setzen:</strong>
    <select id="gp-m-typ"><option value="">Typ –</option><?php foreach ($typen as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?></select>
    <input id="gp-m-menge" type="text" placeholder="Inhalt" style="width:70px">
    <select id="gp-m-einheit"><option value="">Einheit –</option><?php foreach (GrundpreisPruefungService::EINHEITEN as $e): ?><option><?= $e ?></option><?php endforeach; ?></select>
    <input id="gp-m-bezug" type="text" placeholder="Bezug" style="width:60px">
    <select id="gp-m-an"><option value="">Grundpreis –</option><option value="1">anzeigen</option><option value="0">nicht anzeigen</option></select>
    <button type="button" class="btn btn-secondary btn-sm" onclick="gpMasse()">Übernehmen</button>
    <span style="color:var(--color-text-muted)">· Schnell:</span>
    <button type="button" class="btn btn-secondary btn-sm" onclick="gpSchnell(50)">Garn 50 g</button>
    <button type="button" class="btn btn-secondary btn-sm" onclick="gpSchnell(100)">Garn 100 g</button>
    <button type="button" class="btn btn-secondary btn-sm" onclick="gpSchnell(150)">Garn 150 g</button>
    <button type="button" class="btn btn-secondary btn-sm" onclick="gpKeiner()">kein Grundpreis</button>
</div>

<table class="gp-tab">
    <thead><tr>
        <th><input type="checkbox" onclick="gpAlle(this.checked)" title="alle markieren"></th>
        <th>Artikel</th><th>Kinder</th><th>Typ</th><th>Inhalt</th><th>Einheit</th><th>Bezug</th><th>Grundpreis</th><th>So zeigt es der Shop</th><th>Hinweise</th>
    </tr></thead>
    <tbody>
    <?php foreach ($zeilen as $z):
        $einheit = (string)$z['inhalt_einheit'];
        $fmt = fn($n) => $n === null ? '' : rtrim(rtrim(number_format((float)$n, 3, ',', ''), '0'), ',');
    ?>
        <tr data-id="<?= $z['id'] ?>" data-preis="<?= $z['preis'] !== null ? (float)$z['preis'] : '' ?>">
            <td><input type="checkbox" class="gp-wahl"></td>
            <td class="gp-name"><a href="<?= BASE_PATH ?>/artikel/detail.php?id=<?= $z['id'] ?>" target="_blank"><?= htmlspecialchars($z['artikelnummer']) ?></a>
                <?= htmlspecialchars($z['name']) ?><small><?= htmlspecialchars((string)$z['hersteller']) ?><?= $z['preis'] !== null ? ' · ab ' . number_format((float)$z['preis'], 2, ',', '.') . ' €' : '' ?></small></td>
            <td><?= (int)$z['kinder'] ?: '–' ?></td>
            <td><select class="gp-f" name="artikeltyp_id"><?php foreach ($typen as $t): ?><option value="<?= $t['id'] ?>" data-code="<?= $t['code'] ?>" <?= (int)$z['artikeltyp_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?></select></td>
            <td><input class="gp-f gp-zahl" name="inhalt_menge" value="<?= $fmt($z['inhalt_menge']) ?>"></td>
            <td><select class="gp-f" name="inhalt_einheit"><option value="">–</option>
                <?php if ($einheit !== '' && !in_array(mb_strtolower($einheit), GrundpreisPruefungService::EINHEITEN, true)): ?><option value="<?= htmlspecialchars($einheit) ?>" selected><?= htmlspecialchars($einheit) ?> (?)</option><?php endif; ?>
                <?php foreach (GrundpreisPruefungService::EINHEITEN as $e): ?><option <?= mb_strtolower($einheit) === $e ? 'selected' : '' ?>><?= $e ?></option><?php endforeach; ?></select></td>
            <td><input class="gp-f gp-zahl" name="grundpreis_bezugsmenge" value="<?= $fmt($z['grundpreis_bezugsmenge']) ?>" style="width:60px"></td>
            <td style="text-align:center"><input type="checkbox" class="gp-f" name="grundpreis_anzeigen" <?= (int)$z['grundpreis_anzeigen'] === 1 ? 'checked' : '' ?>></td>
            <td class="gp-vorschau"></td>
            <td><?php foreach ($z['probleme'] as $pr): ?><span class="gp-chip"><?= htmlspecialchars($pr) ?></span><?php endforeach; ?><?= !$z['probleme'] ? '<span class="gp-ok">✓</span>' : '' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="gp-fuss">
    <span id="gp-meldung" style="font-size:13px;margin-right:auto"></span>
    <button type="button" class="btn btn-secondary btn-sm" onclick="gpVererben()" title="Angaben der markierten Väter unverändert an ihre Kinder geben">Markierte an Kinder vererben</button>
    <button type="button" class="btn btn-primary btn-sm" id="gp-speichern" onclick="gpSpeichern()" disabled>Änderungen speichern</button>
</div>

<script>const GP = { basis: <?= json_encode(BASE_PATH) ?> };</script>
<script src="<?= BASE_PATH ?>/js/artikel_grundpreise.js"></script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
