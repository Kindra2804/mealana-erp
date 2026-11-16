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
        if (($in['aktion'] ?? '') === 'abwahl') {     // Vater ab-/anwählen, bleibt gespeichert
            (new LangImportService())->setzeAbwahl((string) ($in['vnr'] ?? ''), (string) ($in['name'] ?? ''),
                !empty($in['abwaehlen']), $_SESSION['benutzer']['id'] ?? null);
            echo json_encode(['erfolg' => true]);
            exit;
        }
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
            $plan = $svc->plan($pfad, $lieferantId);
        } catch (Throwable $e) {
            $fehler = $e->getMessage();
        }
    }
}

// CSV-Downloads zum Durcharbeiten (nicht in Datei / über EAN gefunden / Preise)
if ($plan && isset($_GET['csv'])) {
    $art = (string) $_GET['csv'];
    [$datei, $kopf, $zeilen] = match ($art) {
        'ean'    => ['lang_ueber_ean_gefunden.csv', ['Lang-Nummer', 'Nummer im ERP', 'Name'], $plan['listen']['ean_treffer']],
        'preise' => ['lang_preisaenderungen.csv', ['Artikel', 'Name', 'Farben', 'EK alt', 'EK neu', 'EK %', 'unser VK', 'Lang-VP'],
                     array_map(fn($p) => [$p['nr'], $p['name'], $p['anzahl'], $p['ek_alt'], $p['ek_neu'], $p['ek_prozent'], $p['vk'], $p['vp']], $plan['preise'])],
        default  => ['lang_nicht_in_datei.csv', ['Artikelnummer', 'Name', 'Bestand', 'Aktiv', 'Auslauf'],
                     array_map(fn($r) => [$r[0], $r[1], $r[2], $r[3] ? 'ja' : 'nein', $r[4] ? 'ja' : 'nein'], $plan['nicht_in_datei'])],
    };
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $datei . '"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, $kopf, ';');
    foreach ($zeilen as $z) fputcsv($out, array_map(fn($w) => is_float($w) ? str_replace('.', ',', (string) $w) : $w, $z), ';');
    fclose($out);
    exit;
}

$h   = fn($s) => htmlspecialchars((string) $s);
$eu  = fn($z) => $z === null ? '' : number_format((float) $z, 2, ',', '.') . ' €';
$csv = fn($art) => '<a href="lang_import.php?datei=' . $h($token) . '&lieferant=' . $lieferantId . '&csv=' . $art . '">⬇ als CSV</a>';

/** Aufklappbare Liste; $markiert = orange Rahmen + offen (muss geprüft werden) */
$liste = function (string $titel, array $kopf, array $zeilen, ?string $hinweis = null, bool $markiert = false) use ($h) {
    if (!$zeilen) return;
    $rahmen = $markiert ? 'border-left:4px solid #DD6B20;' : '';
    echo '<details class="card" style="padding:var(--space-md);margin-top:var(--space-sm);' . $rahmen . '"' . ($markiert ? ' open' : '') . '>'
       . '<summary style="cursor:pointer;font-weight:600">' . ($markiert ? '<span style="color:#C05621">⚑</span> ' : '')
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
    Status 35/40/55/61 wird nicht angelegt; schon vorhandene Artikel werden Auslaufartikel (ohne Strecke).
    EK, EAN und Zolltarifnummer kommen aus der Datei; Verkaufspreis = Lang-VP nur bei neuen Artikeln.
    Garne werden „Strecke möglich“. Neue Artikel sind aktiv, aber in keinem Shop. Es wird nichts gelöscht.
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
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
        <?php foreach ([
            ['li-k-vaeter', 'Neue Garne (Väter)', $s['vaeter_neu'], '#2B6CB0'],
            ['li-k-farben', 'Neue Farben', $s['kinder_neu'], '#2B6CB0'],
            ['li-k-buecher', 'Neue Bücher', $s['einzel_neu'], '#2B6CB0'],
            ['', 'Aktualisieren', $s['aktualisieren'], '#2F855A'],
            ['', 'EK gestiegen', $s['ek_hoeher'], '#C53030'],
            ['', '→ Auslauf', $s['auslauf'], '#C05621'],
        ] as [$id, $t, $z, $c]): ?>
            <div style="border:1px solid var(--color-border);border-radius:6px;padding:8px 10px">
                <div style="font-size:12px;color:var(--color-text-muted)"><?= $t ?></div>
                <div <?= $id ? 'id="' . $id . '"' : '' ?> style="font-size:22px;font-weight:600;color:<?= $c ?>"><?= number_format($z, 0, ',', '.') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div style="font-size:12px;color:var(--color-text-muted);margin-top:8px">
        <?php if ($s['ek_niedriger']): ?><?= $s['ek_niedriger'] ?> Farben mit niedrigerem EK. <?php endif; ?>
        <?php if ($s['vk_abweichend']): ?><?= $s['vk_abweichend'] ?> Farben, bei denen unser VK vom Lang-VP abweicht. <?php endif; ?>
        <?php if ($s['schon_auslauf']): ?><?= $s['schon_auslauf'] ?> sind schon Auslaufartikel. <?php endif; ?>
        <?php if ($s['nummer_bereinigt']): ?><?= $s['nummer_bereinigt'] ?> Artikelnummern werden bereinigt (Leerzeichen, Kleinschreibung). <?php endif; ?>
        <?php if ($s['uebersprungen']): ?><span style="color:#9B2C2C"><?= $s['uebersprungen'] ?> übersprungen (Liste unten).</span> <?php endif; ?>
        <?php if ($s['ohne_preis']): ?><span style="color:#C05621"><?= $s['ohne_preis'] ?> neue Artikel ohne Preis in der Datei (VP = 0) – Preis danach von Hand.</span><?php endif; ?>
    </div>
    <?php $st = $plan['strecke']; if ($st['allgemein'] || $st['zuruecksetzen']): ?>
    <div style="font-size:13px;padding:8px;margin-top:10px;border-left:4px solid #3182CE;background:#EBF8FF">
        <strong style="color:#2B6CB0">!</strong> <strong>Strecke:</strong> nur noch Garne aus dieser Liste.
        <?php if ($st['allgemein']): ?>Beim Lieferanten wird „Strecke für alle Artikel“ ausgeschaltet – andere Lang-Artikel gibt es dann nur aus unserem Lager.<?php endif; ?>
        <?php if ($st['zuruecksetzen']): ?><?= count($st['zuruecksetzen']) ?> Lang-Artikel, die nicht mehr in der Liste stehen, verlieren ihr „Strecke ja“.<?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($plan['auswahl']): ?>
<details class="card" style="padding:var(--space-md);margin-top:var(--space-sm)" open>
    <summary style="cursor:pointer;font-weight:600">Neue Garne und Bücher – auswählen (<?= count($plan['auswahl']) ?>)</summary>
    <div style="font-size:13px;color:var(--color-text-muted);margin:6px 0">
        Häkchen weg = wird <strong>nicht importiert</strong>. Das bleibt gespeichert, auch für den nächsten Lang-Import.
        Betrifft nur neue Artikel – Vorhandenes wird trotzdem aktualisiert.
        <span id="li-abgewaehlt-info"><?= $s['abgewaehlt'] ? $s['abgewaehlt'] . ' Artikel abgewählt.' : '' ?></span>
    </div>
    <div style="max-height:520px;overflow:auto"><table class="erp-table"><thead><tr>
        <th style="width:30px"></th><th>Nummer</th><th>Name</th><th>Neu</th><th>Zusammensetzung</th><th>Lauflänge</th><th>Nadel</th><th>VK</th>
    </tr></thead><tbody>
    <?php foreach ($plan['auswahl'] as $a): ?>
        <tr class="li-wahl" data-vnr="<?= $h($a['vnr']) ?>" data-name="<?= $h($a['name']) ?>" data-anzahl="<?= (int) $a['neu'] ?>"
            data-typ="<?= $h($a['typ']) ?>" data-vater-neu="<?= $a['typ'] === 'garn' && !$a['vorhanden'] ? 1 : 0 ?>"
            style="<?= $a['abgewaehlt'] ? 'opacity:.45' : '' ?>">
            <td><input type="checkbox" class="li-wahl-cb" <?= $a['abgewaehlt'] ? '' : 'checked' ?>></td>
            <td style="font-size:12px;white-space:nowrap"><?= $h($a['nr']) ?></td>
            <td style="font-size:12px"><strong><?= $h($a['name']) ?></strong>
                <?php if ($a['vorhanden']): ?><span class="chip" style="font-size:10px">vorhanden</span><?php endif; ?></td>
            <td style="font-size:12px;white-space:nowrap"><?= (int) $a['neu'] ?> <?= $a['typ'] === 'buch' ? 'Buch' : ($a['neu'] === 1 ? 'Farbe' : 'Farben') ?></td>
            <td style="font-size:12px"><?= $h($a['zusammensetzung']) ?></td>
            <td style="font-size:12px;white-space:nowrap"><?= $h($a['lauflaenge']) ?></td>
            <td style="font-size:12px;white-space:nowrap"><?= $h($a['nadel']) ?></td>
            <td style="font-size:12px;white-space:nowrap"><?= $a['vk'] > 0 ? $eu($a['vk']) : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</details>
<?php endif; ?>

<?php if ($plan['preise']): ?>
<details class="card" style="padding:var(--space-md);margin-top:var(--space-sm);border-left:4px solid #C53030" open>
    <summary style="cursor:pointer;font-weight:600"><span style="color:#C53030">⚑</span> Preisänderungen (<?= count($plan['preise']) ?> Garne/Artikel)</summary>
    <div style="font-size:13px;color:var(--color-text-muted);margin:6px 0">
        <strong>EK</strong> wird übernommen; <strong>unser Verkaufspreis bleibt</strong> – bei Bedarf danach per Massenaktion „💶 Preise ändern“ anpassen.
        „Lang-VP“ = Lang-Verkaufspreis, nur angezeigt, wenn er von unserem VK abweicht. <?= $csv('preise') ?>
    </div>
    <div style="max-height:420px;overflow:auto"><table class="erp-table"><thead><tr>
        <th>Artikel</th><th>Name</th><th>Farben</th><th>EK alt</th><th>EK neu</th><th>EK %</th><th>unser VK</th><th>Lang-VP</th>
    </tr></thead><tbody>
    <?php foreach ($plan['preise'] as $p):
        $farbe = $p['ek_prozent'] === null ? '' : ($p['ek_prozent'] > 0 ? '#C53030' : '#2F855A'); ?>
        <tr>
            <td style="font-size:12px;white-space:nowrap"><?= $h($p['nr']) ?></td>
            <td style="font-size:12px"><?= $h($p['name']) ?></td>
            <td style="font-size:12px"><?= (int) $p['anzahl'] ?></td>
            <td style="font-size:12px;white-space:nowrap"><?= $eu($p['ek_alt']) ?></td>
            <td style="font-size:12px;white-space:nowrap;color:<?= $farbe ?>"><?= $eu($p['ek_neu']) ?></td>
            <td style="font-size:12px;white-space:nowrap;color:<?= $farbe ?>;font-weight:600">
                <?= $p['ek_prozent'] === null ? '' : ($p['ek_prozent'] > 0 ? '▲ +' : '▼ ') . number_format($p['ek_prozent'], 1, ',', '') . ' %' ?></td>
            <td style="font-size:12px;white-space:nowrap"><?= $eu($p['vk']) ?></td>
            <td style="font-size:12px;white-space:nowrap;<?= $p['vp'] !== null ? 'background:#FEFCBF;font-weight:600' : '' ?>"><?= $eu($p['vp']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</details>
<?php endif; ?>

<?php
$liste('Über die EAN gefunden – Artikelnummer im ERP prüfen/ausbessern', ['Lang-Nummer', 'Nummer im ERP', 'Name'], $plan['listen']['ean_treffer'],
    'Diese Lang-Artikel stehen im ERP unter einer anderen Nummer. Sie werden aktualisiert, die Nummer bleibt – bitte auf die Lang-Nummer ausbessern, '
    . 'damit alle Garne gleich aussehen. ' . $csv('ean'), true);
$liste('Werden Auslaufartikel', ['Artikelnummer', 'Name', 'Lang-Status', 'Bestand'], $plan['listen']['auslauf'],
    'Ohne Bestand werden sie gleich inaktiv, mit Bestand erst, wenn er abverkauft ist. Strecke ist für sie aus.');
$liste('Übersprungen', ['Artikelnummer', 'Name', 'Grund'], $plan['listen']['uebersprungen'], null, true);
$liste('Im ERP, aber nicht mehr in der Datei', ['Artikelnummer', 'Name', 'Bestand', 'Aktiv', 'Auslauf'],
    array_map(fn($r) => [$r[0], $r[1], rtrim(rtrim(number_format($r[2], 2, ',', ''), '0'), ','), $r[3] ? 'ja' : 'nein', $r[4] ? 'ja' : 'nein'], $plan['nicht_in_datei']),
    'Werden nicht verändert – zum Prüfen (falsche Nummer? ausgelaufen?). Mit Bestand stehen oben. ' . $csv('nicht'));
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
