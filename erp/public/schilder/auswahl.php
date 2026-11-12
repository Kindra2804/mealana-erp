<?php
/**
 * Preisschilder drucken – Auswahl (PreisschildService).
 * Aufruf:  ?sync_id=X   alle Garnqualitäten einer Messe-Umbuchung (Knopf in "Messe vorbereiten")
 *          POST ids[]   aus der Artikelliste (Mehrfachauswahl)
 *          vorlage=messe|laden (Umschalten oben, die Auswahl bleibt erhalten)
 * Farben werden zu ihrem Vater zusammengefasst; alle Zeilen sind vorab angehakt.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/schilder/PreisschildService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$svc     = new PreisschildService();
$vorlage = (string) ($_POST['vorlage'] ?? ($_GET['vorlage'] ?? 'messe'));
if (!isset(PreisschildService::VORLAGEN[$vorlage])) $vorlage = 'messe';
$titel   = '';
$syncId  = (int) ($_GET['sync_id'] ?? 0);

if ($syncId) {
    $ids = $svc->artikelAusMesse($syncId);
    $st = Database::getInstance()->prepare("SELECT l.name, s.erstellt_am FROM kassen_messe_sync s LEFT JOIN lager l ON l.id = s.lager_id WHERE s.id = ?");
    $st->execute([$syncId]);
    if ($m = $st->fetch(PDO::FETCH_ASSOC)) $titel = 'Messe: ' . ($m['name'] ?? '') . ' · vorbereitet ' . date('d.m.Y', strtotime($m['erstellt_am']));
} else {
    $roh = $_POST['ids'] ?? ($_GET['ids'] ?? []);
    if (is_string($roh)) $roh = explode(',', $roh);
    $ids = $svc->schildArtikel((array) $roh);
    if ($ids) $titel = 'Auswahl aus der Artikelliste';
}

$zeilen  = $ids ? $svc->uebersicht($ids, $vorlage) : [];
$shops   = $svc->shops();
$jeSeite = PreisschildService::VORLAGEN[$vorlage]['je_seite'];
$istLaden = $vorlage === 'laden';

$pageTitle    = 'Preisschilder drucken';
$activeModule = 'artikel';
$kategorienBaum = (new ArtikelService())->getKategorienBaum();
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="padding:var(--space-md);max-width:1100px">

<?php if (!$zeilen): ?>
    <div class="card" style="font-size:13px;line-height:1.6">
        <strong>Noch keine Artikel gewählt.</strong><br>
        So kommen Artikel hierher:<br>
        • <strong>Messe:</strong> Kasse → Messe vorbereiten → bei der Messe auf „🏷 Preisschilder“<br>
        • <strong>Beliebige Artikel:</strong> Artikelliste → Häkchen setzen → Aktion „🏷 Preisschilder drucken“ → Ausführen
    </div>
<?php else: ?>

<!-- Vorlage umschalten: lädt die Seite mit derselben Auswahl neu -->
<form method="post" action="auswahl.php<?= $syncId ? '?sync_id=' . $syncId : '' ?>" id="ps-umschalten">
    <input type="hidden" name="vorlage" id="ps-umschalten-vorlage">
    <?php if (!$syncId) foreach ($zeilen as $z): ?><input type="hidden" name="ids[]" value="<?= (int)$z['id'] ?>"><?php endforeach; ?>
</form>
<div style="display:flex;gap:6px;margin-bottom:var(--space-sm)">
    <?php foreach (PreisschildService::VORLAGEN as $key => $v): ?>
        <button type="button" class="btn btn-sm <?= $key === $vorlage ? 'btn-primary' : 'btn-secondary' ?>" onclick="psVorlage('<?= $key ?>')">
            <?= $key === 'messe' ? '🎪' : '🏪' ?> <?= htmlspecialchars($v['name']) ?>
        </button>
    <?php endforeach; ?>
</div>

<form method="post" action="drucken.php" target="_blank" id="ps-form">
    <input type="hidden" name="vorlage" value="<?= $vorlage ?>">

    <div class="card" style="margin-bottom:var(--space-sm)">
        <div style="font-weight:700;color:var(--color-nav);margin-bottom:4px">🏷 <?= htmlspecialchars(PreisschildService::VORLAGEN[$vorlage]['name']) ?> · <?= $jeSeite ?> pro A4</div>
        <?php if ($titel): ?><div style="font-size:12px;color:var(--color-text-muted);margin-bottom:10px"><?= htmlspecialchars($titel) ?></div><?php endif; ?>
        <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-end;font-size:13px">
            <label>Start bei Feld<br>
                <select name="start" class="erp-select" style="width:auto">
                    <?php for ($i = 1; $i <= $jeSeite; $i++): ?><option value="<?= $i ?>"><?= $i ?><?= $i === 1 ? ' (neues Blatt)' : '' ?></option><?php endfor; ?>
                </select>
            </label>
            <?php if ($istLaden): ?>
                <label>Hausfarbe<br>
                    <select name="farbe" class="erp-select" style="width:auto">
                        <?php foreach (PreisschildService::HAUSFARBEN as $key => [$name, $hex]): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="mit_bild" value="1"> mit Bild</label>
            <?php else: ?>
                <label>Messepreis<br>
                    <input type="number" name="messe_prozent" min="0" max="90" step="1" class="erp-input" style="width:70px" placeholder="0"> % billiger
                </label>
                <?php if ($shops): ?>
                <label>QR „Farbe nicht da? Online bestellen“<br>
                    <select name="qr_shop_id" class="erp-select" style="width:auto">
                        <option value="">kein QR</option>
                        <?php foreach ($shops as $s): ?><option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
            <?php endif; ?>
            <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="schnitt" value="1" checked> Schnittlinien</label>
        </div>
        <div style="font-size:11px;color:var(--color-text-muted);margin-top:8px">
            Felder: 1 2 / 3 4 / 5 6 / 7 8 (von oben links).
            <?php if ($istLaden): ?>
                Badges kommen automatisch: Aktion, Auslauf / Letzte Chance (Auslauf und höchstens 3 Stück da), Neu (jünger als 60 Tage); BIO, GOTS und Handgefärbt aus dem Merkmal „Auszeichnung“, Pflegesymbole aus dem Merkmal „Pflege“.
            <?php else: ?>
                Messepreis gilt auf den Normalpreis; läuft eine günstigere Aktion, bleibt die. QR nur bei Artikeln, die im gewählten Shop online sind.
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="psAlle(true)">Alle</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="psAlle(false)">Keine</button>
            <span id="ps-zaehler" style="font-size:13px;color:var(--color-text-muted)"></span>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto" id="ps-drucken">🖨 Drucken</button>
        </div>
        <table class="erp-table">
            <thead><tr><th style="width:28px"></th><th>Artikelnr.</th><th>Name</th><th>Hersteller</th><th style="text-align:right">Preis</th>
                <?php if ($istLaden): ?><th>Badges</th><?php endif; ?><th>Zuletzt gedruckt</th><th>Hinweis</th></tr></thead>
            <tbody>
            <?php foreach ($zeilen as $z):
                $l = $z['letzter_druck'];
                $hinweis = [];
                if (!$z['zusammensetzung'] && !$z['lauflaenge']) $hinweis[] = 'keine Merkmale';
                if ($istLaden && empty($z['pflege'])) $hinweis[] = 'keine Pflege';
                if ($z['preis'] === null) $hinweis[] = 'kein Preis';
                if ($l && $z['preis'] !== null && abs((float)$l['preis'] - $z['preis']) > 0.004) $hinweis[] = 'Preis geändert';
            ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= (int)$z['id'] ?>" class="ps-cb" checked></td>
                    <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($z['artikelnummer']) ?></td>
                    <td><?= htmlspecialchars($z['titel']) ?></td>
                    <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($z['hersteller']) ?></td>
                    <td style="text-align:right;white-space:nowrap">
                        <?php if ($z['preis'] !== null): ?><?= $z['ab'] ? 'ab ' : '' ?><?= number_format($z['preis'], 2, ',', '.') ?> €<?php else: ?>–<?php endif; ?>
                    </td>
                    <?php if ($istLaden): ?>
                        <td style="white-space:nowrap">
                            <?php foreach ($z['badges'] ?? [] as $b): ?>
                                <span style="display:inline-block;font-size:10px;font-weight:700;padding:1px 6px;border-radius:8px;<?= $b['rahmen'] ? 'border:1px solid ' . $b['farbe'] . ';color:' . $b['farbe'] : 'background:' . $b['farbe'] . ';color:#fff' ?>"><?= htmlspecialchars($b['text']) ?></span>
                            <?php endforeach; ?>
                        </td>
                    <?php endif; ?>
                    <td style="font-size:12px;color:var(--color-text-muted)"><?= $l ? date('d.m.Y', strtotime($l['gedruckt_am'])) . ' · ' . number_format((float)$l['preis'], 2, ',', '.') . ' €' : 'noch nie' ?></td>
                    <td style="font-size:12px;color:#d97706"><?= htmlspecialchars(implode(', ', $hinweis)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>
<?php endif; ?>
</div>

<script>
function psZaehlen() {
    var n = document.querySelectorAll('.ps-cb:checked').length;
    var start = parseInt((document.querySelector('[name="start"]') || {}).value || '1', 10);
    var blaetter = Math.ceil((n + start - 1) / <?= $jeSeite ?>);
    var z = document.getElementById('ps-zaehler');
    if (z) z.textContent = n + (n === 1 ? ' Schild' : ' Schilder') + ' · ' + blaetter + (blaetter === 1 ? ' Blatt' : ' Blätter');
    var b = document.getElementById('ps-drucken');
    if (b) b.disabled = n === 0;
}
function psAlle(an) { document.querySelectorAll('.ps-cb').forEach(function (c) { c.checked = an; }); psZaehlen(); }
function psVorlage(v) {
    document.getElementById('ps-umschalten-vorlage').value = v;
    document.getElementById('ps-umschalten').submit();
}
document.addEventListener('change', function (e) { if (e.target.matches('.ps-cb, [name="start"]')) psZaehlen(); });
psZaehlen();
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
