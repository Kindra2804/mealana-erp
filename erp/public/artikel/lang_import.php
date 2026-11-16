<?php
/**
 * Lang-Yarns-Import: Artikelstamm-CSV aus dem Lang-Händlerportal.
 *   1. Datei hochladen  -> storage/lang_import/lang_<token>.csv
 *   2. Probelauf        -> ?datei=<token> (ändert nichts)
 *   3. Import           -> AJAX {aktion:'ausfuehren', datei, lieferant_id, von} je 20 Väter (js/lang_import.js)
 * Logik: src/modules/import/LangImportService.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/import/LangImportService.php';

$ordner = __DIR__ . '/../../storage/lang_import';
$pfadZu = function (string $token) use ($ordner): ?string {
    if (!preg_match('/^[a-f0-9]{16}$/', $token)) return null;
    $p = "$ordner/lang_$token.csv";
    return is_file($p) ? $p : null;
};

// ── AJAX: einen Abschnitt importieren ───────────────────────────────
if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    header('Content-Type: application/json');
    try {
        $in   = json_decode(file_get_contents('php://input'), true) ?? [];
        $pfad = $pfadZu((string) ($in['datei'] ?? ''));
        if (!$pfad) throw new RuntimeException('Die hochgeladene Datei ist nicht mehr da – bitte neu hochladen.');
        if (($in['aktion'] ?? '') !== 'ausfuehren') throw new RuntimeException('Unbekannte Aktion');
        @set_time_limit(300);
        echo json_encode((new LangImportService())->ausfuehren(
            $pfad, (int) ($in['lieferant_id'] ?? 0), max(0, (int) ($in['von'] ?? 0)), 20, $_SESSION['benutzer']['id'] ?? null
        ));
    } catch (Throwable $e) {
        echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()]);
    }
    exit;
}

// ── Hochladen ───────────────────────────────────────────────────────
$fehler = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['datei'])) {
    $f = $_FILES['datei'];
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $fehler = 'Hochladen fehlgeschlagen (Datei zu groß? upload_max_filesize prüfen).';
    } else {
        if (!is_dir($ordner)) mkdir($ordner, 0775, true);
        foreach (glob("$ordner/lang_*.csv") ?: [] as $alt) {          // alte Uploads (älter als 2 Tage) wegräumen
            if (filemtime($alt) < time() - 2 * 86400) @unlink($alt);
        }
        $token = bin2hex(random_bytes(8));
        move_uploaded_file($f['tmp_name'], "$ordner/lang_$token.csv");
        header('Location: lang_import.php?datei=' . $token . '&lieferant=' . (int) ($_POST['lieferant_id'] ?? 0));
        exit;
    }
}

$svc         = new LangImportService();
$repo        = new LangImportRepository();
$lieferanten = $repo->findLieferanten();
$lieferantId = (int) ($_GET['lieferant'] ?? 0);
if (!$lieferantId) {
    foreach ($lieferanten as $l) if (stripos($l['name'], 'Lang Garn') === 0) { $lieferantId = (int) $l['id']; break; }
}

// ── Probelauf ───────────────────────────────────────────────────────
$token = (string) ($_GET['datei'] ?? '');
$plan  = null;
if ($token !== '') {
    $pfad = $pfadZu($token);
    if (!$pfad) {
        $fehler = 'Die hochgeladene Datei ist nicht mehr da – bitte neu hochladen.';
    } else {
        try {
            $plan = $svc->plan($pfad);
        } catch (Throwable $e) {
            $fehler = $e->getMessage();
        }
    }
}

// CSV-Download „nicht mehr in der Datei“
if ($plan && isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="lang_nicht_in_datei.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Artikelnummer', 'Name', 'Bestand', 'Aktiv', 'Auslauf'], ';');
    foreach ($plan['nicht_in_datei'] as $r) fputcsv($out, [$r[0], $r[1], $r[2], $r[3] ? 'ja' : 'nein', $r[4] ? 'ja' : 'nein'], ';');
    fclose($out);
    exit;
}

$h  = fn($s) => htmlspecialchars((string) $s);
$eu = fn($z) => number_format((float) $z, 2, ',', '.') . ' €';

/** Aufklappbare Liste */
$liste = function (string $titel, array $kopf, array $zeilen, ?string $hinweis = null) use ($h) {
    if (!$zeilen) return;
    echo '<details class="card" style="padding:var(--space-md);margin-top:var(--space-sm)"><summary style="cursor:pointer;font-weight:600">'
       . $h($titel) . ' (' . count($zeilen) . ')</summary>';
    if ($hinweis) echo '<div style="font-size:13px;color:var(--color-text-muted);margin:6px 0">' . $hinweis . '</div>';
    echo '<div style="max-height:420px;overflow:auto;margin-top:6px"><table class="erp-table"><thead><tr>';
    foreach ($kopf as $k) echo '<th>' . $h($k) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($zeilen as $z) { echo '<tr>'; foreach ($z as $w) echo '<td style="font-size:12px">' . $h($w) . '</td>'; echo '</tr>'; }
    echo '</tbody></table></div></details>';
};

$pageTitle    = 'Lang-Yarns-Import';
$activeModule = 'artikel';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="padding:var(--space-md);max-width:1100px">

<h2 style="margin:0 0 4px">Lang-Yarns-Import</h2>
<div style="font-size:13px;color:var(--color-text-muted);margin-bottom:var(--space-md)">
    Artikelstamm-CSV aus dem Lang-Händlerportal. Übernommen werden Garne (Hauptgruppe 10) und Bücher/Anleitungen (30, nur deutsche Fassungen).
    Nummern: <code>749.0003</code> → <code>LY-749-0003</code>, die Originalnummer wird Lief-Art.-Nr.
    Status 35/40/55/61 wird nicht angelegt, vorhandene Artikel werden Auslaufartikel.
    EK, UVP, EAN und Zolltarifnummer kommen aus der Datei, Verkaufspreise nur bei neuen Artikeln.
    Neue Artikel sind aktiv, aber in keinem Shop. Es wird nichts gelöscht.
</div>

<?php if ($fehler): ?><div class="error-banner" style="margin-bottom:var(--space-md)">✗ <?= $h($fehler) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card" style="padding:var(--space-md);display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <label style="font-size:12px;flex:1;min-width:260px">Datei (LangYarns_Artikelstamm.csv)
        <input type="file" name="datei" accept=".csv,text/csv" class="erp-input" style="width:100%" required>
    </label>
    <label style="font-size:12px;min-width:220px">Lieferant
        <select name="lieferant_id" class="erp-select" style="width:100%">
            <?php foreach ($lieferanten as $l): ?>
                <option value="<?= (int) $l['id'] ?>" <?= (int) $l['id'] === $lieferantId ? 'selected' : '' ?>><?= $h($l['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="btn btn-primary">Hochladen + Probelauf</button>
</form>

<?php if ($plan): $s = $plan['summe']; ?>
<div class="card" style="padding:var(--space-md);margin-top:var(--space-md)">
    <div style="font-weight:600;margin-bottom:8px">Probelauf – noch nichts geändert</div>
    <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:10px">
        <?= number_format($plan['zeilen'], 0, ',', '.') ?> Artikelzeilen gelesen<?= $plan['rest'] ? ', ' . $plan['rest'] . ' Zeilen am Dateiende ignoriert (Webseiten-Code)' : '' ?>,
        <?= $plan['vaeter'] ?> Väter mit Kindern in Garne/Bücher.
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
        <?php foreach ([
            ['Neue Väter', $s['vaeter_neu'], '#2B6CB0'],
            ['Neue Farben', $s['kinder_neu'], '#2B6CB0'],
            ['Neue Bücher', $s['einzel_neu'], '#2B6CB0'],
            ['Aktualisieren', $s['aktualisieren'], '#2F855A'],
            ['→ Auslauf', $s['auslauf'], '#C05621'],
            ['Übersprungen', $s['uebersprungen'], '#9B2C2C'],
        ] as [$t, $z, $c]): ?>
            <div style="border:1px solid var(--color-border);border-radius:6px;padding:8px 10px">
                <div style="font-size:12px;color:var(--color-text-muted)"><?= $t ?></div>
                <div style="font-size:22px;font-weight:600;color:<?= $c ?>"><?= number_format($z, 0, ',', '.') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div style="font-size:12px;color:var(--color-text-muted);margin-top:8px">
        <?php if ($s['schon_auslauf']): ?><?= $s['schon_auslauf'] ?> sind schon Auslaufartikel. <?php endif; ?>
        <?php if ($s['nummer_bereinigt']): ?><?= $s['nummer_bereinigt'] ?> Artikelnummern werden bereinigt (Leerzeichen, Kleinschreibung). <?php endif; ?>
        <?php if ($s['ohne_preis']): ?><span style="color:#C05621"><?= $s['ohne_preis'] ?> neue Artikel ohne Preis in der Datei (VP = 0) – Preis danach von Hand.</span><?php endif; ?>
    </div>
</div>

<?php
$liste('Neue Artikel', ['Artikelnummer', 'Name', 'Verkaufspreis'],
    array_map(fn($r) => [$r[0], $r[1], $r[2] > 0 ? $eu($r[2]) : '—'], $plan['listen']['neu']),
    count($plan['listen']['neu']) >= 400 ? 'Die ersten 400.' : null);
$liste('Werden Auslaufartikel', ['Artikelnummer', 'Name', 'Lang-Status', 'Bestand'], $plan['listen']['auslauf'],
    'Ohne Bestand werden sie gleich inaktiv, mit Bestand erst, wenn er abverkauft ist.');
$liste('Über die EAN gefunden (andere Artikelnummer im ERP)', ['Lang-Nummer', 'Nummer im ERP', 'Name'], $plan['listen']['ean_treffer'],
    'Werden aktualisiert, die Artikelnummer bleibt. Bitte prüfen – vermutlich falsch angelegt.');
$liste('Übersprungen', ['Artikelnummer', 'Name', 'Grund'], $plan['listen']['uebersprungen']);
$liste('Im ERP, aber nicht mehr in der Datei', ['Artikelnummer', 'Name', 'Bestand', 'Aktiv', 'Auslauf'],
    array_map(fn($r) => [$r[0], $r[1], rtrim(rtrim(number_format($r[2], 2, ',', ''), '0'), ','), $r[3] ? 'ja' : 'nein', $r[4] ? 'ja' : 'nein'], $plan['nicht_in_datei']),
    'Werden nicht verändert – zum Prüfen (falsche Nummer? ausgelaufen?). Mit Bestand stehen oben. '
    . '<a href="lang_import.php?datei=' . $h($token) . '&csv=1">⬇ als CSV</a>');
?>

<div id="li-aktionen" class="card" style="padding:var(--space-md);margin-top:var(--space-md);display:flex;gap:12px;align-items:center;justify-content:flex-end;flex-wrap:wrap">
    <div id="li-fortschritt" style="flex:1;min-width:240px;display:none">
        <div style="height:10px;background:#EDF2F7;border-radius:5px;overflow:hidden"><div id="li-balken" style="height:100%;width:0;background:#3182CE"></div></div>
        <div id="li-text" style="font-size:12px;margin-top:4px;color:var(--color-text-muted)"></div>
    </div>
    <button type="button" id="li-start" class="btn btn-primary">Import starten</button>
</div>
<div id="li-ergebnis"></div>

<script>
window.LI_DATEI     = <?= json_encode($token) ?>;
window.LI_LIEFERANT = <?= (int) $lieferantId ?>;
</script>
<script src="<?= BASE_PATH ?>/js/lang_import.js"></script>
<?php endif; ?>

</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
