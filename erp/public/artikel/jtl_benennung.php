<?php
/**
 * JTL: neue Nummern – Vorschau des neuen Nummern-/Namensschemas für die MeaLana-Übernahme (ändert nichts).
 *   Nummer  Kürzel-Vater-Kind (LN-113-0001), Name "Vater Farbe [Art] (0001)", Nadeln: Nummer bleibt.
 *   Quelle  zwei unveränderte JTL-Exporte (Artikelstammdaten + Variationskombinationen) in storage/jtl_benennung/
 *   Dazu    Ausschluss + Umbenennungen (Babsis Auswahl, quelle 'jtl') und Herstellerkürzel aus dem ERP
 * Logik: src/modules/import/JtlBenennungVorschau.php + ArtikelBenennung.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/import/JtlBenennungVorschau.php';

$h  = fn($s) => htmlspecialchars((string) $s);
$fehler = null;

// ── Hochladen (eine oder beide Dateien ersetzen) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $fehler = 'Datei zu groß für die Server-Einstellung (post_max_size).';
    } else {
        if (!is_dir(JtlBenennungVorschau::ordner())) mkdir(JtlBenennungVorschau::ordner(), 0775, true);
        $neu = 0;
        foreach (['stammdaten', 'variationen'] as $art) {
            $f = $_FILES[$art] ?? null;
            if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) continue;
            if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                $fehler = 'Hochladen fehlgeschlagen (' . $art . ').';
                continue;
            }
            move_uploaded_file($f['tmp_name'], JtlBenennungVorschau::pfad($art));
            $neu++;
        }
        if ($neu && !$fehler) { header('Location: jtl_benennung.php'); exit; }
    }
}

// ── Ergebnis + Filter ───────────────────────────────────────────────
$erg = null;
try {
    $erg = (new JtlBenennungVorschau())->ergebnis();
} catch (Throwable $e) {
    $fehler = $fehler ?? $e->getMessage();
}

// Nur zur Info (kein Fehler): grau statt orange, zählt nicht bei "nur mit Hinweis"
$nurInfo = ['Farbnummer aus Kindnummer'];

$f = [
    'q'          => trim((string) ($_GET['q'] ?? '')),
    'hersteller' => (string) ($_GET['hersteller'] ?? ''),
    'typ'        => (string) ($_GET['typ'] ?? ''),
    'hinweis'    => (string) ($_GET['hinweis'] ?? ''),
    'nur'        => !empty($_GET['nur']),
];
$treffer = [];
$herstellerListe = [];
if ($erg) {
    $q = mb_strtolower($f['q']);
    foreach ($erg['gruppen'] as $g) {
        $herstellerListe[$g['hersteller']] = true;
        if ($f['hersteller'] !== '' && $g['hersteller'] !== ($f['hersteller'] === '-' ? '' : $f['hersteller'])) continue;
        if ($f['typ'] !== '' && $g['typ'] !== $f['typ']) continue;
        $kinder = $g['kinder'];
        if ($f['hinweis'] !== '' || $f['nur']) {
            // "nur mit Hinweis": reine Info-Hinweise (grau) zählen nicht
            $passt  = fn($hw) => $f['hinweis'] !== '' ? in_array($f['hinweis'], $hw, true) : (bool) array_diff($hw, $nurInfo);
            $vaterPasst = $passt($g['hinweise']);
            $kinder = array_values(array_filter($kinder, fn($k) => $vaterPasst || $passt($k['hinweise'])));
            if (!$vaterPasst && !$kinder) continue;
        }
        if ($q !== '') {
            $text = fn($x) => mb_strtolower($x['jtl_nr'] . ' ' . $x['nr'] . ' ' . $x['name']);
            if (!str_contains($text($g), $q)) {
                $kinder = array_values(array_filter($kinder, fn($k) => str_contains($text($k), $q)));
                if (!$kinder) continue;
            }
        }
        $g['kinder_gezeigt'] = $kinder;
        $treffer[] = $g;
    }
    ksort($herstellerListe, SORT_NATURAL | SORT_FLAG_CASE);
}

// ── CSV der gefilterten Liste (zum Durchsehen in Excel – enthält keine GTINs) ──
if ($erg && isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="jtl_neue_nummern.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Art', 'JTL-Nummer', 'Neue Nummer', 'Neuer Name', 'Hersteller', 'Kürzel', 'Hinweis'], ';');
    foreach ($treffer as $g) {
        fputcsv($out, [$g['typ'] === 'einzel' ? 'Einzel' : 'Vater', $g['jtl_nr'], $g['nr'], $g['name'], $g['hersteller'], $g['kuerzel'], implode(' · ', $g['hinweise'])], ';');
        foreach ($g['kinder_gezeigt'] as $k) {
            fputcsv($out, ['Kind', $k['jtl_nr'], $k['nr'], $k['name'], $g['hersteller'], $g['kuerzel'], implode(' · ', $k['hinweise'])], ';');
        }
    }
    fclose($out);
    exit;
}

$proSeite = 40;
$seiten   = max(1, (int) ceil(count($treffer) / $proSeite));
$seite    = min($seiten, max(1, (int) ($_GET['seite'] ?? 1)));
$link     = fn(array $mehr) => 'jtl_benennung.php?' . http_build_query(array_filter(array_merge($f, ['nur' => $f['nur'] ? 1 : null], $mehr), fn($v) => $v !== '' && $v !== null && $v !== false));
$datum    = fn(?array $i) => $i ? date('d.m.Y H:i', $i['zeit']) . ' · ' . number_format($i['groesse'] / 1048576, 1, ',', '.') . ' MB' : 'fehlt';
$chip     = fn(string $t, string $farbe = '#C05621') => '<span style="display:inline-block;font-size:11px;padding:1px 6px;margin:1px 2px 1px 0;border-radius:3px;border:1px solid ' . $farbe . ';color:' . $farbe . '">' . htmlspecialchars($t) . '</span>';
$hinweisHtml = fn(array $hw) => implode('', array_map(fn($t) => in_array($t, $nurInfo, true) ? $chip($t, '#718096') : $chip($t), $hw));

$pageTitle    = 'JTL: neue Nummern';
$activeModule = 'artikel';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="padding:var(--space-md);max-width:1200px">

<h2 style="margin:0 0 4px">JTL: neue Nummern (Vorschau)</h2>
<div style="font-size:13px;color:var(--color-text-muted);margin-bottom:var(--space-md)">
    So heißen die MeaLana-Artikel nach der Übernahme aus JTL. <strong>Hier wird nichts geändert.</strong>
    Nummer: Kürzel-Vater-Kind, z.B. <code>LN-113-0001</code> (Kürzel beim <a href="../hersteller/liste.php">Hersteller</a>).
    Name: <code>Karisma natur [Uni] (0001)</code> – [Uni]/[Mix] nur, wenn der Garn diese Unterteilung hat.
    Nadeln und Größen behalten ihre Nummer, Name „Vater Länge Stärke“.
    In JTL bleibt alles, wie es ist. Ausgeschlossene Artikel (Babsis Auswahl) werden nicht gezeigt.
</div>

<?php if ($fehler): ?><div class="error-banner" style="margin-bottom:var(--space-md)">✗ <?= $h($fehler) ?></div><?php endif; ?>

<details class="card" style="padding:var(--space-md)" <?= $erg ? '' : 'open' ?>>
    <summary style="cursor:pointer;font-weight:600">JTL-Dateien
        <span style="font-weight:400;font-size:12px;color:var(--color-text-muted)">
            – Stammdaten: <?= $h($datum(JtlBenennungVorschau::dateiInfo('stammdaten'))) ?>
            · Variationskombinationen: <?= $h($datum(JtlBenennungVorschau::dateiInfo('variationen'))) ?>
        </span>
    </summary>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-top:10px">
        <label style="font-size:12px;flex:1;min-width:240px">Artikelstammdaten (JTL-Export, vollständig)
            <input type="file" name="stammdaten" accept=".csv,text/csv" class="erp-input" style="width:100%">
        </label>
        <label style="font-size:12px;flex:1;min-width:240px">Variationskombinationen (JTL-Export, vollständig)
            <input type="file" name="variationen" accept=".csv,text/csv" class="erp-input" style="width:100%">
        </label>
        <button type="submit" class="btn btn-primary">Hochladen</button>
    </form>
    <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px">Eine Datei reicht, wenn sich nur die geändert hat. Beide müssen alle Artikel enthalten (nicht nur aktive).</div>
</details>

<?php if ($erg): $s = $erg['summe']; ?>
<div class="card" style="padding:var(--space-md);margin-top:var(--space-md)">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
        <?php foreach ([['Väter', $s['vaeter'], '#2B6CB0'], ['Kinder', $s['kinder'], '#2B6CB0'], ['Einzelartikel', $s['einzel'], '#2B6CB0'],
                        ['ausgeschlossen', $s['ausgeschlossen'], '#718096']] as [$t, $z, $c]): ?>
            <div style="border:1px solid var(--color-border);border-radius:6px;padding:8px 10px">
                <div style="font-size:12px;color:var(--color-text-muted)"><?= $t ?></div>
                <div style="font-size:22px;font-weight:600;color:<?= $c ?>"><?= number_format($z, 0, ',', '.') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($s['hinweise']): ?>
    <div style="margin-top:10px;font-size:12px">
        <span style="color:var(--color-text-muted)">Hinweise (anklicken = nur diese zeigen):</span><br>
        <?php foreach ($s['hinweise'] as $t => $n): ?>
            <a href="<?= $h($link(['hinweis' => $t, 'seite' => null])) ?>" style="text-decoration:none"><?= $chip($t . ' (' . number_format($n, 0, ',', '.') . ')', in_array($t, $nurInfo, true) ? '#718096' : '#C05621') ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<form method="get" class="card" style="padding:var(--space-md);margin-top:var(--space-sm);display:flex;gap:10px;align-items:end;flex-wrap:wrap">
    <label style="font-size:12px;flex:1;min-width:200px">Suche (Nummer oder Name)
        <input type="text" name="q" value="<?= $h($f['q']) ?>" class="erp-input" style="width:100%" placeholder="z.B. Karisma oder LN-113">
    </label>
    <label style="font-size:12px;min-width:180px">Hersteller
        <select name="hersteller" class="erp-select" style="width:100%">
            <option value="">alle</option>
            <?php foreach (array_keys($herstellerListe) as $hn): ?>
                <?php $wert = $hn === '' ? '-' : $hn; ?>
                <option value="<?= $h($wert) ?>" <?= $wert === $f['hersteller'] ? 'selected' : '' ?>><?= $h($hn === '' ? '(ohne Hersteller)' : $hn) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label style="font-size:12px;min-width:130px">Art
        <select name="typ" class="erp-select" style="width:100%">
            <option value="">alle</option>
            <option value="vater" <?= $f['typ'] === 'vater' ? 'selected' : '' ?>>Väter</option>
            <option value="einzel" <?= $f['typ'] === 'einzel' ? 'selected' : '' ?>>Einzelartikel</option>
        </select>
    </label>
    <label style="font-size:12px;min-width:200px">Hinweis
        <select name="hinweis" class="erp-select" style="width:100%">
            <option value="">alle</option>
            <?php foreach (array_keys($s['hinweise']) as $t): ?>
                <option value="<?= $h($t) ?>" <?= $t === $f['hinweis'] ? 'selected' : '' ?>><?= $h($t) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label style="font-size:12px;display:flex;gap:4px;align-items:center;padding-bottom:6px">
        <input type="checkbox" name="nur" value="1" <?= $f['nur'] ? 'checked' : '' ?>> nur mit Hinweis
    </label>
    <button type="submit" class="btn btn-primary btn-sm">Filtern</button>
    <a href="jtl_benennung.php" class="btn btn-secondary btn-sm">Zurücksetzen</a>
    <a href="<?= $h($link(['csv' => 1, 'seite' => null])) ?>" class="btn btn-secondary btn-sm" title="Gefilterte Liste für Excel (ohne GTINs)">⬇ CSV</a>
</form>

<div style="font-size:12px;color:var(--color-text-muted);margin:10px 0 4px">
    <?= number_format(count($treffer), 0, ',', '.') ?> Väter/Einzelartikel gefunden · Seite <?= $seite ?> von <?= $seiten ?>
</div>

<table class="erp-table">
    <thead><tr><th style="width:190px">JTL-Nummer</th><th style="width:190px">Neue Nummer</th><th>Neuer Name</th><th style="width:260px">Hinweis</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($treffer, ($seite - 1) * $proSeite, $proSeite) as $g): ?>
        <tr style="background:var(--color-bg-subtle,#f7f7f7)">
            <td style="font-family:monospace;font-size:12px"><?= $h($g['jtl_nr']) ?></td>
            <td style="font-family:monospace;font-size:12px;font-weight:600"><?= $h($g['nr']) ?></td>
            <td>
                <strong><?= $h($g['name']) ?></strong>
                <span style="font-size:11px;color:var(--color-text-muted)">
                    · <?= $g['typ'] === 'einzel' ? 'Einzelartikel' : count($g['kinder']) . ' Kinder' ?>
                    · <?= $h($g['hersteller_erp'] ?? ($g['hersteller'] ?: 'ohne Hersteller')) ?><?= $g['kuerzel'] ? ' (' . $h($g['kuerzel']) . ')' : '' ?>
                </span>
            </td>
            <td><?= $hinweisHtml($g['hinweise']) ?></td>
        </tr>
        <?php foreach ($g['kinder_gezeigt'] as $k): ?>
        <tr>
            <td style="font-family:monospace;font-size:12px;padding-left:18px;color:var(--color-text-muted)"><?= $h($k['jtl_nr']) ?></td>
            <td style="font-family:monospace;font-size:12px<?= $k['nr'] === $k['jtl_nr'] ? ';color:var(--color-text-muted)' : '' ?>"><?= $h($k['nr']) ?></td>
            <td style="font-size:13px"><?= $h($k['name']) ?></td>
            <td><?= $hinweisHtml($k['hinweise']) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($seiten > 1): ?>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin:12px 0">
    <?php for ($i = 1; $i <= $seiten; $i++): ?>
        <?php if ($i === 1 || $i === $seiten || abs($i - $seite) <= 3): ?>
            <a href="<?= $h($link(['seite' => $i])) ?>" class="btn btn-sm <?= $i === $seite ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
        <?php elseif (abs($i - $seite) === 4): ?>
            <span style="padding:4px">…</span>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
