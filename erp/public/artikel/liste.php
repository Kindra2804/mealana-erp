<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/core/Database.php';


$controller = new ArtikelController();
$service = new ArtikelService();

$db = Database::getInstance();
$alleHersteller   = $db->query("SELECT id, name FROM hersteller WHERE aktiv = 1 ORDER BY name")->fetchAll();
$alleArtikeltypen = $db->query("SELECT id, name FROM artikel_typen ORDER BY name")->fetchAll();

// === SPALTEN-KONFIGURATION ===
$alleSpaltenDef = [
    'status'          => ['label' => 'Status',          'default' => true,  'baubar' => true],
    'shops'           => ['label' => 'Shops',           'default' => true,  'baubar' => true],
    'bestand'         => ['label' => 'Bestand',         'default' => true,  'baubar' => true],
    'preis'           => ['label' => 'Preis',           'default' => true,  'baubar' => true],
    'hersteller'      => ['label' => 'Hersteller',      'default' => false, 'baubar' => true],
    'ean'             => ['label' => 'EAN',             'default' => false, 'baubar' => true],
    'einheit'         => ['label' => 'Einheit',         'default' => false, 'baubar' => true],
    'kategorie'       => ['label' => 'Kategorie',       'default' => false, 'baubar' => true],
    'geaendert_am'    => ['label' => 'Geändert am',     'default' => false, 'baubar' => true],
    'ek'              => ['label' => 'EK-Preis',        'default' => false, 'baubar' => true],
    'marge'           => ['label' => 'Marge %',         'default' => false, 'baubar' => true],
    'charge'          => ['label' => 'Charge-Pfl.',     'default' => false, 'baubar' => true],
    'merkmale'        => ['label' => 'Merkmale',        'default' => false, 'baubar' => false],
    'lagerplatz'      => ['label' => 'Lagerplatz',      'default' => false, 'baubar' => false],
    'letzte_inventur' => ['label' => 'Letzte Inventur', 'default' => false, 'baubar' => false],
];
$defaultSpalten = array_keys(array_filter($alleSpaltenDef, fn($s) => $s['default']));

$spStmt = $db->prepare("SELECT wert FROM benutzer_einstellungen WHERE benutzer_id = :uid AND schluessel = 'artikel_liste.spalten'");
$spStmt->execute(['uid' => $_SESSION['benutzer']['id']]);
$spRow = $spStmt->fetch();
$aktiveSpalten = $spRow ? json_decode($spRow['wert'], true) : $defaultSpalten;
$aktiveSpalten = array_values(array_filter($aktiveSpalten, fn($s) => isset($alleSpaltenDef[$s])));

function sp(string $key, array $aktiv): bool { return in_array($key, $aktiv); }

// Renderer für Spalten-Header (TH)
function spalteHeader(string $key, string $aktSort, string $aktDir, array $getParams): string {
    switch ($key) {
        case 'status':        return '<th style="width:130px">STATUS</th>';
        case 'shops':         return '<th style="width:100px">SHOPS</th>';
        case 'bestand':       return '<th style="width:60px;text-align:right" title="Physischer Gesamtbestand">' . sortKopf('bestand', 'BST.', $aktSort, $aktDir, $getParams) . '</th>';
        case 'preis':         return '<th style="width:90px;text-align:right">' . sortKopf('preis', 'PREIS', $aktSort, $aktDir, $getParams) . '</th>';
        case 'hersteller':    return '<th style="width:110px">' . sortKopf('hersteller', 'HERSTELLER', $aktSort, $aktDir, $getParams) . '</th>';
        case 'ean':           return '<th style="width:130px">EAN</th>';
        case 'einheit':       return '<th style="width:70px">EINHEIT</th>';
        case 'kategorie':     return '<th style="min-width:120px;max-width:200px">KATEGORIE</th>';
        case 'geaendert_am':  return '<th style="width:110px">' . sortKopf('geaendert_am', 'GEÄNDERT AM', $aktSort, $aktDir, $getParams) . '</th>';
        case 'ek':            return '<th style="width:80px;text-align:right">EK</th>';
        case 'marge':         return '<th style="width:70px;text-align:right">MARGE</th>';
        case 'charge':        return '<th style="width:60px;text-align:center">' . sortKopf('charge', 'CHARGE', $aktSort, $aktDir, $getParams) . '</th>';
        case 'merkmale':      return '<th style="width:100px">MERKMALE</th>';
        case 'lagerplatz':    return '<th style="width:100px">LAGERPLATZ</th>';
        case 'letzte_inventur': return '<th style="width:100px">INVENTUR</th>';
    }
    return '';
}

// Renderer für Vater-Zeilen-Zellen (TD)
function spalteVaterTd(string $key, array $a, string $bstKlasse, string $bstTitle, string $statusChips, bool $hatTeureresKind): string {
    switch ($key) {
        case 'status':
            return '<td class="status-cell">' . $statusChips . '</td>';
        case 'shops':
            return '<td class="kanal-cell">' . renderShopChips($a) . '</td>';
        case 'bestand':
            return '<td style="text-align:right" class="' . $bstKlasse . '" ' . $bstTitle . '>' . formatBestand($a['gesamtbestand']) . '</td>';
        case 'preis':
            if (!$a['brutto_vk']) return '<td style="text-align:right" class="preis-cell">–</td>';
            $ab = $hatTeureresKind ? '<span style="font-size:10px;color:var(--color-text-muted)">ab </span>' : '';
            return '<td style="text-align:right" class="preis-cell">' . $ab . number_format((float)$a['brutto_vk'], 2, ',', '.') . ' €</td>';
        case 'hersteller':
            return '<td>' . htmlspecialchars($a['hersteller'] ?? '–') . '</td>';
        case 'ean':
            return '<td style="font-size:12px;color:var(--color-text-muted)">' . htmlspecialchars($a['ean'] ?? '–') . '</td>';
        case 'einheit':
            return '<td>' . htmlspecialchars($a['einheit_kuerzel'] ?? '–') . '</td>';
        case 'kategorie':
            $kat = htmlspecialchars($a['kategorien'] ?? '');
            return '<td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' . $kat . '">' . ($kat ?: '–') . '</td>';
        case 'geaendert_am':
            return '<td style="font-size:12px;color:var(--color-text-muted)">' . ($a['geaendert_am'] ? date('d.m.Y', strtotime($a['geaendert_am'])) : '–') . '</td>';
        case 'ek':
            return '<td style="text-align:right;font-size:12px">' . ($a['standard_ek'] !== null ? number_format((float)$a['standard_ek'], 2, ',', '.') . ' €' : '–') . '</td>';
        case 'marge':
            $m = '–';
            if ($a['brutto_vk'] && $a['standard_ek'] !== null && $a['steuersatz']) {
                $nVk = (float)$a['brutto_vk'] / (1 + (float)$a['steuersatz'] / 100);
                if ($nVk > 0) $m = round((($nVk - (float)$a['standard_ek']) / $nVk) * 100, 1) . ' %';
            }
            return '<td style="text-align:right;font-size:12px">' . $m . '</td>';
        case 'charge':
            return '<td style="text-align:center">' . ($a['charge_pflicht'] ? '✓' : '') . '</td>';
        case 'merkmale':      return '<td style="font-size:12px;color:var(--color-text-muted)">–</td>';
        case 'lagerplatz':    return '<td style="font-size:12px;color:var(--color-text-muted)">–</td>';
        case 'letzte_inventur': return '<td style="font-size:12px;color:var(--color-text-muted)">–</td>';
    }
    return '<td></td>';
}

// Renderer für Kind-Zeilen (meistens leer außer bestand/preis/ean/status)
function spalteKindTd(string $key, array $k, string $kindBstKlasse, string $kindBstTitle, string $kindStatusChips): string {
    switch ($key) {
        case 'status':   return '<td class="status-cell">' . $kindStatusChips . '</td>';
        case 'bestand':  return '<td style="text-align:right;font-size:12px" class="' . $kindBstKlasse . '" ' . $kindBstTitle . '>' . formatBestand($k['gesamtbestand']) . '</td>';
        case 'preis':    return '<td style="text-align:right;font-size:12px" class="preis-cell">' . ($k['brutto_vk'] ? number_format((float)$k['brutto_vk'], 2, ',', '.') . ' €' : '–') . '</td>';
        case 'ean':      return '<td style="font-size:12px;color:var(--color-text-muted)">' . htmlspecialchars($k['ean'] ?? '–') . '</td>';
        case 'charge':   return '<td style="text-align:center">' . ($k['charge_pflicht'] ? '✓' : '') . '</td>';
    }
    return '<td></td>';
}

// Renderer für Zustandsartikel-Zeilen
function spalteZustandTd(string $key, array $za, string $zaBstKlasse, string $zaSuffix): string {
    switch ($key) {
        case 'status':
            $chips = '<span class="sc" style="background:#dbeafe;color:#1e40af;border:1px solid #93c5fd">' . $zaSuffix . '</span>';
            if (!$za['aktiv']) $chips .= '<span class="sc sc-deaktiviert">Deaktiviert</span>';
            return '<td class="status-cell">' . $chips . '</td>';
        case 'bestand': return '<td style="text-align:right;font-size:12px" class="' . $zaBstKlasse . '">' . formatBestand($za['gesamtbestand']) . '</td>';
        case 'preis':   return '<td style="text-align:right;font-size:12px">–</td>';
    }
    return '<td></td>';
}


$statusFilter     = $_GET['status_filter'] ?? '';
$aktivKategorieId = (int)($_GET['kategorie_id'] ?? 0) ?: null;

// Sortierung
$erlaubtSort = ['artikelnummer', 'name', 'bestand', 'preis', 'hersteller', 'geaendert_am', 'charge'];
$aktSort = in_array($_GET['sort'] ?? '', $erlaubtSort) ? $_GET['sort'] : 'artikelnummer';
$aktDir  = ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';
$sortMap = [
    'artikelnummer' => 'a.artikelnummer',
    'name'          => 'a.name',
    'bestand'       => 'gesamtbestand',
    'preis'         => 'COALESCE(ap.brutto_vk, 0)',
    'hersteller'    => 'h.name',
    'geaendert_am'  => 'a.geaendert_am',
    'charge'        => 'a.charge_pflicht',
];
$sortSpalteSQL = $sortMap[$aktSort];
$sortDirSQL    = strtoupper($aktDir);

// Kategorie-Filter auf alle Nachkommen ausweiten (damit "Wolle" auch Unterkategorien zeigt)
$alleKatIds = null;
if ($aktivKategorieId) {
    $alleKatIds = array_merge([$aktivKategorieId], $service->getAlleNachkommenIds($aktivKategorieId));
}

$filter = [
    'q'               => trim($_GET['q'] ?? ''),
    'hersteller_id'   => (int)($_GET['hersteller_id'] ?? 0) ?: null,
    'artikeltyp_id'   => (int)($_GET['artikeltyp_id'] ?? 0) ?: null,
    'nurMitBestand'   => isset($_GET['nurMitBestand']),
    'mitInaktiven'    => isset($_GET['inaktive']) || $statusFilter === 'inaktiv',
    'status_filter'   => $statusFilter,
    'kategorie_ids'   => $alleKatIds,
    'nurKategorielos' => $statusFilter === 'ohnekat',
    'sort'            => $aktSort,
    'dir'             => $aktDir,
];

$kategorienBaum = $service->getKategorienBaum();

$seite = (int)($_GET['seite'] ?? 1);
$proSeite = (int)($_GET['pro_seite'] ?? 12);

$offset = ($seite - 1) * $proSeite;

$artikel = $controller->index($filter, $proSeite, $offset);

$vaterIds = array_column($artikel, 'id');

$alleKinder = $service->getKinderFuerListe($vaterIds, $sortSpalteSQL, $sortDirSQL);
$kinderNachVater = [];
foreach ($alleKinder as $k) {
    $kinderNachVater[$k['vaterartikel_id']][] = $k;
}

$zustandsNachVater = $service->getZustandsArtikelFuerListe($vaterIds);

$gesamt = $controller->count($filter);
$seitenAnzahl = (int) ceil($gesamt / $proSeite);

// Bestand-Anzeige: ohne Nachkomma wenn ganzzahlig
function formatBestand(int|float|string $wert): string
{
    $f = (float)$wert;
    return ($f == floor($f)) ? number_format((int)$f, 0, ',', '.') : number_format($f, 3, ',', '.');
}

// Smart Pagination mit "…" bei vielen Seiten
function buildPaginierung(int $aktuelleSeite, int $gesamtSeiten, int $fenster = 2): array
{
    if ($gesamtSeiten <= 1) return [];
    $seiten = [1];
    $von = max(2, $aktuelleSeite - $fenster);
    $bis = min($gesamtSeiten - 1, $aktuelleSeite + $fenster);
    if ($von > 2) $seiten[] = '…';
    for ($i = $von; $i <= $bis; $i++) $seiten[] = $i;
    if ($bis < $gesamtSeiten - 1) $seiten[] = '…';
    $seiten[] = $gesamtSeiten;
    return $seiten;
}

// Hilfsfunktion: klickbarer Spaltenheader mit Sort-Indikator
function sortKopf(string $spalte, string $label, string $aktSort, string $aktDir, array $getParams): string
{
    $istAktiv = $aktSort === $spalte;
    $neueDir  = ($istAktiv && $aktDir === 'asc') ? 'desc' : 'asc';
    $p  = array_merge($getParams, ['sort' => $spalte, 'dir' => $neueDir, 'seite' => 1]);
    $qs = http_build_query($p);
    $pfeil = $istAktiv ? ($aktDir === 'asc' ? ' ▲' : ' ▼') : ' ↕';
    $farbe = $istAktiv ? 'color:var(--color-nav)' : 'color:inherit;opacity:.7';
    return '<a href="liste.php?' . $qs . '" style="' . $farbe . ';text-decoration:none;white-space:nowrap;cursor:pointer">'
        . htmlspecialchars($label) . $pfeil . '</a>';
}


function renderShopChips(array $artikel): string
{
    // K-Kanäle (Kassen) werden nicht mehr angezeigt — sie gelten immer für alle Artikel
    // Nur S-Kanäle (Shops) sobald artikel_shops existiert und GROUP_CONCAT-Feld "shop_kanaele" befüllt ist
    if (empty($artikel['shop_kanaele'])) return '–';
    $html = '';
    $shopCssMap = ['S1' => 'kc-s1', 'S2' => 'kc-s2', 'S3' => 'kc-s3'];
    foreach (explode(',', $artikel['shop_kanaele']) as $code) {
        $code = trim($code);
        $css  = $shopCssMap[$code] ?? 'kc-s1';
        $html .= '<span class="kc ' . $css . '">' . htmlspecialchars($code) . '</span>';
    }
    return $html;
}

function kindAbweichungen(array $kind, array $vater): array
{
    $abw = [];
    if (!$kind['aktiv'] && $vater['aktiv']) {
        $abw[] = 'Inaktiv';
    }
    if ((int)$kind['ist_auslaufartikel'] !== (int)$vater['ist_auslaufartikel']) {
        $abw[] = 'Auslauf';
    }
    $kp = $kind['brutto_vk'] !== null ? (float)$kind['brutto_vk'] : null;
    $vp = $vater['brutto_vk'] !== null ? (float)$vater['brutto_vk'] : null;
    if ($kp !== $vp) {
        $abw[] = 'Preis';
    }
    if ((int)$kind['ueberverkauf_erlaubt'] !== (int)$vater['ueberverkauf_erlaubt']) {
        $abw[] = 'Überverkauf';
    }
    return $abw;
}

$pageTitle    = "Artikelliste";
$activeModule = "artikel";

// Picker-Zeilen als HTML-String vorbereiten (kein PHP in heredoc möglich)
ob_start();
foreach ($alleSpaltenDef as $key => $def):
    $isPlaceholder = !$def['baubar'] ? ' placeholder' : '';
    $checked  = sp($key, $aktiveSpalten) ? ' checked' : '';
    $disabled = !$def['baubar'] ? ' disabled' : '';
    $label    = htmlspecialchars($def['label']) . (!$def['baubar'] ? ' ⏳' : '');
    $sortierbtn = $def['baubar']
        ? '<button class="spalten-sortierbtn" data-dir="up" title="Nach oben">↑</button>'
        . '<button class="spalten-sortierbtn" data-dir="down" title="Nach unten">↓</button>'
        : '';
    echo "<div class=\"spalten-panel-zeile{$isPlaceholder}\" data-key=\"{$key}\">"
       . "<input type=\"checkbox\" id=\"sp-{$key}\"{$checked}{$disabled}>"
       . "<label for=\"sp-{$key}\">{$label}</label>"
       . "{$sortierbtn}</div>\n";
endforeach;
$pickerZeilenHtml = ob_get_clean();

$actionBarContent = <<<HTML
<a href="neu.php" class="btn btn-primary btn-sm">+ Neu</a>
<button class="btn btn-secondary btn-sm">Kopieren</button>
<div class="actionbar-sep"></div>
<button class="btn btn-secondary btn-sm">⬇ Import</button>
<button class="btn btn-secondary btn-sm">⬆ Export</button>
<div class="actionbar-right">
    <span style="color:var(--color-text-muted);font-size:13px">Ausgewählt:</span>
    <span id="ausgewaehlt-count" style="color:var(--color-text-muted);font-size:13px">0</span>
    <select id="massen-aktion" class="btn btn-secondary btn-sm">
        <option value="">Aktion ▼</option>
        <option value="aktivieren">Aktivieren</option>
        <option value="deaktivieren">Deaktivieren</option>
        <option value="ist_auslaufartikel">ist Auslaufartikel</option>
        <option value="kein_auslaufartikel">kein Auslaufartikel</option>
    </select>
    <button id="massen-ausfuehren" class="btn btn-primary btn-sm">Ausführen</button>
    <div class="actionbar-sep"></div>
    <div class="spalten-picker-wrap">
        <button id="spalten-picker-btn" class="btn btn-secondary btn-sm" title="Spalten konfigurieren">⚙ Spalten</button>
        <div class="spalten-panel" id="spalten-panel">
            <div class="spalten-panel-titel">Sichtbare Spalten</div>
            $pickerZeilenHtml
            <div class="spalten-panel-footer">
                <button id="spalten-reset" class="btn btn-secondary btn-sm">Zurück zum Standard</button>
                <button id="spalten-anwenden" class="btn btn-primary btn-sm">Anwenden</button>
            </div>
        </div>
    </div>
</div>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

?>

<div class="card">
    <form method="GET" action="liste.php" class="filter-bar">
        <input type="text" name="q" class="erp-input"
            value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
            placeholder="🔍 Suche Artikel, EAN, Name…" style="min-width:240px">
        <select name="hersteller_id" class="erp-select" onchange="this.form.submit()">
            <option value="">– Hersteller –</option>
            <?php foreach ($alleHersteller as $h): ?>
                <option value="<?= $h['id'] ?>" <?= ($_GET['hersteller_id'] ?? '') == $h['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($h['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="artikeltyp_id" class="erp-select" onchange="this.form.submit()">
            <option value="">– Artikel-Typ –</option>
            <?php foreach ($alleArtikeltypen as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($_GET['artikeltyp_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status_filter" class="erp-select" onchange="this.form.submit()">
            <option value="">– Status –</option>
            <option value="auslauf" <?= ($_GET['status_filter'] ?? '') === 'auslauf'    ? 'selected' : '' ?>>Auslaufartikel</option>
            <option value="uv" <?= ($_GET['status_filter'] ?? '') === 'uv'         ? 'selected' : '' ?>>Überverkauf aktiv</option>
            <option value="fehlbest" <?= ($_GET['status_filter'] ?? '') === 'fehlbest'   ? 'selected' : '' ?>>Fehlbestand (Bst=0)</option>
            <option value="inaktiv" <?= ($_GET['status_filter'] ?? '') === 'inaktiv'    ? 'selected' : '' ?>>Inaktiv</option>
            <option value="ohnekat" <?= ($_GET['status_filter'] ?? '') === 'ohnekat'    ? 'selected' : '' ?>>Ohne Kategorie</option>
        </select>
        <select name="kanal_filter" class="erp-select" disabled title="Kanäle-Modul noch nicht aktiv">
            <option value="">– Kanal –</option>
            <option>K1 Kassa Boutique</option>
            <option>K2 Kassa Messe</option>
            <option>S1 Shop MeaLana</option>
            <option>S2 Sockenwolle</option>
            <option>S3 Bio-Wolle</option>
        </select>
        <label>
            <input onchange="this.form.submit()" type="checkbox" name="nurMitBestand" <?= isset($_GET['nurMitBestand']) ? 'checked' : '' ?>> Nur mit Bestand
        </label>
        <label><input onchange="this.form.submit()" type="checkbox" name="inaktive" <?= isset($_GET['inaktive']) ? 'checked' : '' ?>> Auch inaktive</label>
        <?php if ($aktivKategorieId): ?>
            <input type="hidden" name="kategorie_id" value="<?= $aktivKategorieId ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-secondary btn-sm">Suchen</button>
    </form>
</div>

<div class="card">
    <div style="overflow-x:auto">
    <table class="erp-table artikel-liste-table">
        <?php $getOhnePagSort = array_diff_key($_GET, array_flip(['seite', 'sort', 'dir'])); ?>
        <thead>
            <tr>
                <th class="cb-sticky" style="width:28px; text-align:center"><input type="checkbox" id="alle-auswaehlen" title="Alle auswählen"></th>
                <th style="width:42px"></th>
                <th style="width:100px"><?= sortKopf('artikelnummer', 'ART.-NR.', $aktSort, $aktDir, $getOhnePagSort) ?></th>
                <th><?= sortKopf('name', 'ARTIKELNAME', $aktSort, $aktDir, $getOhnePagSort) ?></th>
                <?php foreach ($aktiveSpalten as $sp_key): echo spalteHeader($sp_key, $aktSort, $aktDir, $getOhnePagSort); endforeach; ?>
                <th style="width:80px"><button type="button" id="alle-toggle-btn" onclick="alleToggle()">alle zuklappen</button></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($artikel as $a):
                $kinder = $kinderNachVater[$a['id']] ?? [];
                $hatKinder = count($kinder) > 0;
                // Status-Chips für Vater
                $statusChips = '';
                if (!$a['aktiv'])
                    $statusChips .= '<span class="sc sc-deaktiviert">Deaktiviert</span>';
                if ($a['aktiv'] && $a['ist_auslaufartikel'])
                    $statusChips .= '<span class="sc sc-auslauf">Auslauf</span>';
                if ($a['ueberverkauf_erlaubt'])
                    $statusChips .= '<span class="sc sc-uv" title="Überverkauf aktiviert">Üv</span>';
                // Fehlbest. bei normalen Artikeln: aktiv, kein Üv, kein Auslauf, Bestand = 0
                if ($a['aktiv'] && !$a['ueberverkauf_erlaubt'] && !$a['ist_auslaufartikel'] && (float)$a['gesamtbestand'] <= 0)
                    $statusChips .= '<span class="sc sc-fehlbest" title="Kein Bestand vorhanden">Fehlbest.</span>';
                // Fehlbest. zusätzlich zu Üv: wenn bereits auf Kundenauftrag/Reservierung
                if ($a['aktiv'] && $a['ueberverkauf_erlaubt'] && (float)$a['gesamtbestand'] <= 0 && (float)($a['reserviert'] ?? 0) > 0)
                    $statusChips .= '<span class="sc sc-fehlbest" title="Reserviert: ' . (int)$a['reserviert'] . ' Stk. auf offenen Aufträgen">Fehlbest.</span>';
                if ((int)($a['kat_anzahl'] ?? 1) === 0)
                    $statusChips .= '<span class="sc sc-ohnekat" title="Kein Kategorie-Eintrag – Artikel erscheint in keinem Shop">Kein Kat.</span>';
                // Sale-Chip: TODO — benötigt preis_aktionen-Tabelle (noch nicht gebaut)

                // ⚠ Vater-Badge
                $vaterAbwTypen = [];
                foreach ($kinder as $k) {
                    foreach (kindAbweichungen($k, $a) as $abw) {
                        $vaterAbwTypen[$abw] = true;
                    }
                }
                $vaterHatAbweichung = !empty($vaterAbwTypen);
                $hatZustandsArtikel = !empty($zustandsNachVater[$a['id']]);

                $bstKlasse = ((float)$a['gesamtbestand'] <= 0 && $a['aktiv']) ? 'bst-null' : '';
                $bstTitle  = '';
                if ((float)($a['reserviert'] ?? 0) > 0) {
                    $vk = (float)$a['gesamtbestand'] - (float)$a['reserviert'];
                    $bstTitle = 'title="' . formatBestand($a['gesamtbestand']) . ' physisch · '
                        . formatBestand($a['reserviert']) . ' reserviert · '
                        . formatBestand($vk) . ' verkaufbar"';
                }

                // "ab"-Preis: mind. ein Kind ist teurer als der Vater
                $hatTeureresKind = false;
                if ($a['brutto_vk'] !== null) {
                    foreach ($kinder as $k) {
                        if ($k['brutto_vk'] !== null && (float)$k['brutto_vk'] > (float)$a['brutto_vk']) {
                            $hatTeureresKind = true;
                            break;
                        }
                    }
                }
            ?>
                <tr class="artikel-zeile<?= !$a['aktiv'] ? ' row-inaktiv' : '' ?>">
                    <td class="cb-sticky" style="text-align:center; width:28px">
                        <input type="checkbox" class="zeile-cb" value="<?= $a['id'] ?>">
                        <?php if ($hatKinder || $hatZustandsArtikel): ?>
                            <br><span id="pfeil-<?= $a['id'] ?>" onclick="toggleKinder(<?= $a['id'] ?>)"
                                class="expand-arrow">▶</span>
                        <?php endif; ?>
                    </td>
                    <td class="thumb-cell">
                        <div class="artikel-thumb"></div>
                    </td>
                    <td class="artnr-cell">
                        <a href="detail.php?id=<?= $a['id'] ?>"><?= htmlspecialchars($a['artikelnummer']) ?></a>
                    </td>
                    <td>
                        <span class="artikel-name"><?= htmlspecialchars($a['name']) ?></span>
                        <?php if ($hatKinder): ?>
                            <span class="varianten-count"><?= count($kinder) ?> Var.</span>
                        <?php endif; ?>
                        <?php if ($vaterHatAbweichung): ?>
                            <span class="warn-badge" title="Kind-Abweichungen: <?= htmlspecialchars(implode(', ', array_keys($vaterAbwTypen))) ?>">!</span>
                        <?php endif; ?>
                        <?php if ($hatZustandsArtikel): ?>
                            <span class="warn-badge" style="background:#2563EB" title="B-Ware / Zustandsartikel vorhanden">!</span>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($aktiveSpalten as $sp_key): echo spalteVaterTd($sp_key, $a, $bstKlasse, $bstTitle, $statusChips, $hatTeureresKind); endforeach; ?>
                    <td class="aktion-cell">
                        <span class="row-aktionen">
                            <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-xs" title="Bearbeiten">✏️</a>
                            <a href="kopieren.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-xs" title="Kopieren">📋</a>
                            <a href="delete.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-xs" title="Deaktivieren"
                                style="color:var(--color-danger)"
                                onclick="return confirm('Artikel wirklich deaktivieren?')">🗑️</a>
                        </span>
                    </td>
                </tr>

                <?php foreach ($kinder as $k):
                    $kindAbw = kindAbweichungen($k, $a);
                    $kindStatusChips = '';
                    if (!$k['aktiv'])
                        $kindStatusChips .= '<span class="sc sc-deaktiviert">Deaktiviert</span>';
                    if ($k['aktiv'] && $k['ist_auslaufartikel'])
                        $kindStatusChips .= '<span class="sc sc-auslauf">Auslauf</span>';
                    if ($k['ueberverkauf_erlaubt'])
                        $kindStatusChips .= '<span class="sc sc-uv" title="Überverkauf aktiviert">Üv</span>';
                    if ($k['aktiv'] && !$k['ueberverkauf_erlaubt'] && !$k['ist_auslaufartikel'] && (float)$k['gesamtbestand'] <= 0)
                        $kindStatusChips .= '<span class="sc sc-fehlbest" title="Kein Bestand vorhanden">Fehlbest.</span>';
                    if ($k['aktiv'] && $k['ueberverkauf_erlaubt'] && (float)$k['gesamtbestand'] <= 0 && (float)($k['reserviert'] ?? 0) > 0)
                        $kindStatusChips .= '<span class="sc sc-fehlbest" title="Reserviert: ' . (int)$k['reserviert'] . ' Stk. auf offenen Aufträgen">Fehlbest.</span>';
                    $kindBstKlasse = ((float)$k['gesamtbestand'] <= 0 && $k['aktiv']) ? 'bst-null' : '';
                    $kindBstTitle  = '';
                    if ((float)($k['reserviert'] ?? 0) > 0) {
                        $kvk = (float)$k['gesamtbestand'] - (float)$k['reserviert'];
                        $kindBstTitle = 'title="' . formatBestand($k['gesamtbestand']) . ' physisch · '
                            . formatBestand($k['reserviert']) . ' reserviert · '
                            . formatBestand($kvk) . ' verkaufbar"';
                    }
                ?>
                    <tr class="kind-zeile-<?= $a['id'] ?> versteckt kind-zeile<?= !$k['aktiv'] ? ' row-inaktiv' : '' ?>">
                        <td class="cb-sticky" style="text-align:center"><input type="checkbox" class="zeile-cb" value="<?= $k['id'] ?>"></td>
                        <td class="thumb-cell">
                            <div class="artikel-thumb artikel-thumb-kind"></div>
                        </td>
                        <td class="artnr-cell" style="padding-left:20px; color:var(--color-text-muted); font-size:12px">
                            ↳ <a href="detail.php?id=<?= $k['id'] ?>"><?= htmlspecialchars($k['artikelnummer']) ?></a>
                        </td>
                        <td>
                            <span style="font-size:12px; color:var(--color-text-muted)"><?= htmlspecialchars($k['name']) ?></span>
                            <?php if (!empty($kindAbw)): ?>
                                <span class="warn-badge" title="Abweicht vom Vater: <?= htmlspecialchars(implode(', ', $kindAbw)) ?>">!</span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($aktiveSpalten as $sp_key): echo spalteKindTd($sp_key, $k, $kindBstKlasse, $kindBstTitle, $kindStatusChips); endforeach; ?>
                        <td class="aktion-cell">
                            <span class="row-aktionen">
                                <a href="detail.php?id=<?= $k['id'] ?>" class="btn btn-secondary btn-xs" title="Bearbeiten">✏️</a>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php
                $zustandsArtikel = $zustandsNachVater[$a['id']] ?? [];
                foreach ($zustandsArtikel as $za):
                    $zaBstKlasse = ((float)$za['gesamtbestand'] <= 0 && $za['aktiv']) ? 'bst-null' : '';
                    $zustandLabels = [
                        'gebraucht'          => 'GEB',
                        'generalueberholt'   => 'GUE',
                        'beschaedigt'        => 'BSC',
                        'retour'             => 'RET',
                        'demo'               => 'DMO',
                        'muster'             => 'MST',
                        'ausstellungsstueck' => 'AST',
                    ];
                    $zaSuffix = $zustandLabels[$za['zustand']] ?? strtoupper($za['zustand']);
                ?>
                    <tr class="kind-zeile-<?= $a['id'] ?> versteckt kind-zeile<?= !$za['aktiv'] ? ' row-inaktiv' : '' ?>">
                        <td class="cb-sticky" style="text-align:center"><input type="checkbox" class="zeile-cb" value="<?= $za['id'] ?>"></td>
                        <td class="thumb-cell">
                            <div class="artikel-thumb artikel-thumb-kind"></div>
                        </td>
                        <td class="artnr-cell" style="padding-left:20px; font-size:12px">
                            ↳ <a href="detail.php?id=<?= $za['id'] ?>"><?= htmlspecialchars($za['artikelnummer']) ?></a>
                        </td>
                        <td><span style="font-size:12px; color:var(--color-text-muted)"><?= htmlspecialchars($za['name']) ?></span></td>
                        <?php foreach ($aktiveSpalten as $sp_key): echo spalteZustandTd($sp_key, $za, $zaBstKlasse, $zaSuffix); endforeach; ?>
                        <td class="aktion-cell">
                            <span class="row-aktionen">
                                <a href="detail.php?id=<?= $za['id'] ?>" class="btn btn-secondary btn-xs" title="Bearbeiten">✏️</a>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<div class="card">
    <div class="pagination-bar">
        <div class="">
            Zeige <?= $offset + 1 ?>–<?= min($offset + $proSeite, $gesamt) ?> von <?= $gesamt ?> Hauptartikeln
        </div>
        <div class="pagination">
            <?php foreach (buildPaginierung($seite, $seitenAnzahl) as $eintrag):
                if ($eintrag === '…'): ?>
                    <span class="pagination-dots">…</span>
                <?php else:
                    $params = $_GET;
                    $params['seite'] = $eintrag;
                    $qs = http_build_query($params);
                    $aktiv = ($eintrag == $seite) ? 'active' : '';
                ?>
                    <a class="<?= $aktiv ?>" href="liste.php?<?= $qs ?>"><?= $eintrag ?></a>
            <?php endif;
            endforeach; ?>
        </div>
        <div class="">
            Hauptartikel/Seite:
            <select name="pro_seite" onchange="
            var p = new URLSearchParams(window.location.search);
            p.set('pro_seite', this.value);
            p.set('seite', 1);
            window.location.href = 'liste.php?' + p.toString();
        ">
                <option value="10" <?= $proSeite == 12 ? 'selected' : '' ?>>12</option>
                <option value="25" <?= $proSeite == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $proSeite == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $proSeite == 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>
    </div>


</div>

<!-- SHOP-LEGENDE -->
<div class="kanal-legende" id="kanal-legende-bar">
    <span class="kanal-legende-label">Shops:</span>
    <span id="kanal-legende-inhalt">
        <span class="kc kc-s1">S1</span> <span class="kanal-legende-text">Shop MeaLana</span>
        <span class="kanal-legende-sep">·</span>
        <span class="kc kc-s2">S2</span> <span class="kanal-legende-text">Sockenwolle</span>
        <span class="kanal-legende-sep">·</span>
        <span class="kc kc-s3">S3</span> <span class="kanal-legende-text">Bio-Wolle</span>
        <span class="kanal-legende-note">(K1/K2 Kassen = immer alle Artikel, kein eigenes Flag)</span>
    </span>
    <button onclick="legendeToggle()" id="legende-toggle-btn"
        style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:11px;color:var(--color-text-muted);padding:0 4px"
        title="Legende ein-/ausblenden">▲</button>
</div>
<script>
    (function() {
        var offen = localStorage.getItem('mealana_legende') !== 'zu';
        var inhalt = document.getElementById('kanal-legende-inhalt');
        var btn = document.getElementById('legende-toggle-btn');

        function anwenden() {
            inhalt.style.display = offen ? '' : 'none';
            btn.textContent = offen ? '▲' : '▼';
        }
        anwenden();
        window.legendeToggle = function() {
            offen = !offen;
            localStorage.setItem('mealana_legende', offen ? 'auf' : 'zu');
            anwenden();
        };
    })();
</script>


<script>
    const EXPANDED_KEY = 'mealana_expanded_artikel';

    function getExpanded() {
        try {
            return new Set(JSON.parse(localStorage.getItem(EXPANDED_KEY) || '[]').map(String));
        } catch (e) {
            return new Set();
        }
    }

    function saveExpanded(set) {
        localStorage.setItem(EXPANDED_KEY, JSON.stringify([...set]));
    }

    function toggleKinder(vaterId) {
        vaterId = String(vaterId);
        document.querySelectorAll('.kind-zeile-' + vaterId).forEach(r => r.classList.toggle('versteckt'));
        const p = document.getElementById('pfeil-' + vaterId);
        const istJetztOffen = p.textContent === '▶';
        p.textContent = istJetztOffen ? '▼' : '▶';

        const expanded = getExpanded();
        istJetztOffen ? expanded.add(vaterId) : expanded.delete(vaterId);
        saveExpanded(expanded);
    }

    function alleToggle() {
        const pfeile = document.querySelectorAll('[id^="pfeil-"]');
        const sindAlleZu = Array.from(pfeile).every(p => p.textContent === '▶');
        if (!sindAlleZu) {
            pfeile.forEach(p => {
                if (p.textContent === '▼') toggleKinder(p.id.replace('pfeil-', ''));
            });
        }
    }

    // Aufgeklappte Zeilen nach Seitenlade wiederherstellen
    (function() {
        const expanded = getExpanded();
        expanded.forEach(id => {
            const pfeil = document.getElementById('pfeil-' + id);
            if (pfeil && pfeil.textContent === '▶') toggleKinder(id);
        });
    })();



    function zaehlerAktualisieren() {
        var checkedSum = 0;
        document.querySelectorAll('.zeile-cb').forEach(cb => {
            if (cb.checked) checkedSum++;
        })
        document.getElementById('ausgewaehlt-count').innerText = checkedSum;
    }

    document.querySelectorAll('.zeile-cb').forEach(cb => {
        cb.addEventListener('change', function() {
            zaehlerAktualisieren();
        });
    });

    document.getElementById('alle-auswaehlen').addEventListener('change', function() {
        document.querySelectorAll('.zeile-cb').forEach(cb => cb.checked = this.checked);
        zaehlerAktualisieren();
    });

    document.getElementById('massen-ausfuehren').addEventListener('click', function() {
        const ids = [...document.querySelectorAll('.zeile-cb:checked')].map(cb => cb.value);
        const aktion = document.getElementById('massen-aktion').value;
        if (ids.length === 0) {
            alert('Bitte Artikel auswählen');
            return;
        }
        if (aktion === '') {
            alert('Bitte Aktion wählen');
            return;
        }

        fetch('massenupdate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ids: ids,
                    aktion: aktion
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.fehler) {
                    alert(data.fehler);
                    return;
                }
                location.reload();
            });

    });

    // === SPALTEN-PICKER ===
    (function() {
        const btn    = document.getElementById('spalten-picker-btn');
        const panel  = document.getElementById('spalten-panel');
        const defaultSpalten = <?= json_encode($defaultSpalten) ?>;
        const alleSpalten    = <?= json_encode(array_keys($alleSpaltenDef)) ?>;

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            panel.classList.toggle('offen');
        });

        document.addEventListener('click', function(e) {
            if (!panel.contains(e.target) && e.target !== btn) {
                panel.classList.remove('offen');
            }
        });

        function getSpalten() {
            return [...panel.querySelectorAll('.spalten-panel-zeile:not(.placeholder)')]
                .filter(z => z.querySelector('input[type=checkbox]').checked)
                .map(z => z.dataset.key);
        }

        function speichernUndLaden() {
            fetch('spalten_einstellung_speichern.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ spalten: getSpalten() })
            }).then(() => location.reload());
        }

        // Sortier-Buttons: nur DOM-Reihenfolge ändern, kein Reload
        panel.querySelectorAll('.spalten-sortierbtn').forEach(function(sortBtn) {
            sortBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const zeile = this.closest('.spalten-panel-zeile');
                if (this.dataset.dir === 'up') {
                    const prev = zeile.previousElementSibling;
                    if (prev && prev.classList.contains('spalten-panel-zeile')) {
                        panel.insertBefore(zeile, prev);
                    }
                } else {
                    const next = zeile.nextElementSibling;
                    if (next && next.classList.contains('spalten-panel-zeile')) {
                        panel.insertBefore(next, zeile);
                    }
                }
            });
        });

        // Zurück zum Standard: nur Checkboxen zurücksetzen, kein Reload
        document.getElementById('spalten-reset').addEventListener('click', function(e) {
            e.stopPropagation();
            alleSpalten.forEach(function(key) {
                const cb = document.getElementById('sp-' + key);
                if (cb && !cb.disabled) cb.checked = defaultSpalten.includes(key);
            });
        });

        // Anwenden: speichern + neu laden
        document.getElementById('spalten-anwenden').addEventListener('click', function(e) {
            e.stopPropagation();
            speichernUndLaden();
        });
    })();
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>