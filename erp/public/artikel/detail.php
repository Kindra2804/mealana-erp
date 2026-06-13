<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$id = (int) ($_GET['id'] ?? 0);

$service = new ArtikelService();
$variantenService  = new VariantenService();
$lieferantenService = new LieferantenService();
$achsen            = $variantenService->findAchsenByArtikelId($id);
$werte             = $variantenService->findWerteByArtikelId($id);
$existingKombis    = $variantenService->findExistingKombinationen($id);
$zeigeInaktive = isset($_GET['inaktive']) && $_GET['inaktive'] == '1';
$kinder        = $service->getKinderFuerArtikel($id, true);
$artikel       = $service->getDetailArtikel($id);
$kategorien    = $service->getKategorienFuerArtikel($id);
$codes         = $service->getCodesByArtikelId($id);
$lieferanten   = $service->getLieferantenFuerArtikel($id);
$alleKategorien  = $service->getAlleKategorien();
$zugewieseneIds  = array_column($kategorien, 'id');
$artikelTypen    = $service->getAllArtikelTypen();
$alleEinheiten   = $service->getAllEinheiten();
$alleHersteller  = $service->getAllHersteller();
$steuerklassen   = $service->getAllSteuerklassen();
$variantenService  = new VariantenService();
$achsen            = $variantenService->findAchsenByArtikelId($id);
$werte             = $variantenService->findWerteByArtikelId($id);
$existingKombis    = $variantenService->findExistingKombinationen($id);
$alleLieferanten   = $lieferantenService->findAll();

$ean_gtin13 = '';
foreach ($codes as $c) {
    if ($c['typ'] === 'GTIN13') {
        $ean_gtin13 = $c['code'];
        break;
    }
}

if ($artikel === false) {
    echo 'Artikel nicht gefunden!';
    exit;
}

function kartesischesProdukt(array $arrays): array
{
    $result = [
        []
    ]; // startet mit einer leeren Kombination

    foreach ($arrays as $group) {
        $newResult = [];
        foreach ($result as $existing) {
            foreach ($group as $item) {
                $newResult[] = array_merge($existing, [$item]);
            }
        }
        $result = $newResult;
    }

    return $result;
}

// Werte nach achse_id gruppieren
$werteProAchse = [];
foreach ($werte as $w) {
    $werteProAchse[$w['achse_id']][] = $w;
}

// Kartesisches Produkt — nur wenn mind. eine Achse mit Werten da ist
$gruppen = array_values($werteProAchse);  // numerisch indiziert
$alleKombis = !empty($gruppen) ? kartesischesProdukt($gruppen) : [];

// var_dump($alleKombis);

$existing = $variantenService->findExistingKombinationen($id);

// Bestehende Wert-ID-Sets als Lookup bauen
$existingKeys = [];
foreach ($existing as $e) {
    $existingKeys[$e['wert_ids']] = $e;  // key: "1,3" → artikel-daten
}

// Kombis aufteilen
$neueKombis     = [];
$vorhandeneKombis = [];

foreach ($alleKombis as $kombi) {
    // Wert-IDs aus dieser Kombi extrahieren, sortieren, als String
    $ids = array_map(fn($w) => $w['id'], $kombi);
    sort($ids);
    $key = implode(',', $ids);

    if (isset($existingKeys[$key])) {
        $vorhandeneKombis[] = ['kombi' => $kombi, 'artikel' => $existingKeys[$key]];
    } else {
        $neueKombis[] = ['kombi' => $kombi, 'key' => $key];
    }
}

$pageTitle    = htmlspecialchars($artikel['name']);
$activeModule = 'artikel';

$actionBarContent = <<<HTML
<button form="stammdaten-form" type="submit" class="btn btn-primary btn-sm">💾 Speichern</button>
<a href="liste.php" class="btn btn-secondary btn-sm">Abbrechen</a>
<div class="actionbar-sep"></div>
<button class="btn btn-secondary btn-sm" style="color:var(--color-warning)">Deaktivieren</button>
<button class="btn btn-secondary btn-sm" style="color:#0a6ebd">Im Shop ▼</button>
<div class="actionbar-sep"></div>
<button class="btn btn-danger btn-sm">Löschen</button>
HTML;

$sidebarItems = [
    ['type' => 'back',    'label' => 'zur Liste', 'href' => '/mealana/artikel/liste.php'],
    ['type' => 'separator'],
    ['type' => 'context', 'artNr' => $artikel['artikelnummer'], 'name' => $artikel['name']],
    ['type' => 'separator'],
    ['type' => 'nav', 'icon' => '📋', 'label' => 'Stammdaten',  'href' => '#stammdaten',  'active' => true],
    ['type' => 'nav', 'icon' => '🎨', 'label' => 'Varianten',   'href' => '#varianten',   'badge' => count($kinder)],
    ['type' => 'nav', 'icon' => '💲', 'label' => 'Preise',      'href' => '#preise'],
    ['type' => 'nav', 'icon' => '📦', 'label' => 'Lager',       'href' => '#lager'],
    ['type' => 'nav', 'icon' => '🖼',  'label' => 'Bilder',      'href' => '#bilder'],
    ['type' => 'nav', 'icon' => '🏷',  'label' => 'Merkmale',    'href' => '#merkmale'],
    ['type' => 'nav', 'icon' => '🚚', 'label' => 'Lieferanten', 'href' => '#lieferanten'],
    ['type' => 'separator'],
    ['type' => 'grayed', 'icon' => '🌐', 'label' => 'SEO'],
    ['type' => 'grayed', 'icon' => '📊', 'label' => 'Statistik'],
];

require_once __DIR__ . '/../includes/shell_top.php';

?>

<div class="article-header">
    <div class="article-header-thumb" style="background: #e8eef7;"></div>
    <div>
        <div class="article-header-name"><?= htmlspecialchars($artikel['name']) ?></div>
        <div class="article-header-meta">
            <?= htmlspecialchars($artikel['artikelnummer']) ?>
            &nbsp;|&nbsp; <?= htmlspecialchars($artikel['artikeltyp']) ?>
            &nbsp;|&nbsp; <?= htmlspecialchars($artikel['hersteller'] ?? '–') ?>
        </div>
    </div>
    <div class="article-header-toggle">
        <span style="width:10px;height:10px;border-radius:50%;background:<?= $artikel['aktiv'] ? '#28a745' : '#dc3545' ?>;display:inline-block"></span>
        <?= $artikel['aktiv'] ? 'Aktiv' : 'Inaktiv' ?>
    </div>
</div>

<div class="tab-bar">
    <a class="tab active" href="#" onclick="zeigeTab('stammdaten',this);return false;">Stammdaten</a>
    <a class="tab" href="#" onclick="zeigeTab('varianten',this);return false;">
        Varianten <?php if (count($kinder) > 0): ?><span class="badge"><?= count($kinder) ?></span><?php endif; ?>
    </a>
    <a class="tab" href="#" onclick="zeigeTab('preise',this);return false;">Preise</a>
    <a class="tab" href="#" onclick="zeigeTab('lager',this);return false;">Lager</a>
    <a class="tab" href="#" onclick="zeigeTab('bilder',this);return false;">Bilder</a>
    <a class="tab" href="#" onclick="zeigeTab('merkmale',this);return false;">Merkmale</a>
    <a class="tab" href="#" onclick="zeigeTab('lieferanten',this);return false;">Lieferanten</a>
</div>

<div style="padding: var(--space-md)">

    <div id="tab-stammdaten">
        <form id="stammdaten-form" action="aktualisieren.php" method="POST">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="card">
                <div class="form-section">
                    <div class="form-section-header">Grunddaten</div>

                    <div class="form-row">
                        <label class="form-label">Bezeichnung *</label>
                        <input type="text" name="name" class="erp-input" style="width:100%"
                            value="<?= htmlspecialchars($artikel['name']) ?>" required>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Kurzbeschreibung</label>
                        <input type="text" name="kurzbeschreibung" class="erp-input" style="width:100%"
                            value="<?= htmlspecialchars($artikel['kurzbeschreibung'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Art.-Nr. *</label>
                        <input type="text" name="artikelnummer" class="erp-input"
                            value="<?= htmlspecialchars($artikel['artikelnummer']) ?>" required>
                    </div>
                    <div class="form-row">
                        <label class="form-label">EAN / GTIN</label>
                        <input type="text" name="ean_gtin13" class="erp-input" maxlength="13"
                            value="<?= htmlspecialchars($ean_gtin13) ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Artikeltyp *</label>
                        <select name="artikeltyp" class="erp-select">
                            <option value="">– bitte wählen –</option>
                            <?php foreach ($artikelTypen as $typ): ?>
                                <option value="<?= htmlspecialchars($typ['code']) ?>"
                                    <?= ($artikel['artikeltyp'] ?? '') === $typ['code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($typ['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Einheit</label>
                        <select name="einheit_id" class="erp-select">
                            <?php foreach ($alleEinheiten as $e): ?>
                                <option value="<?= $e['id'] ?>"
                                    <?= (string)($artikel['einheit_id'] ?? '') === (string)$e['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['name']) ?>
                                    <?= $e['kuerzel'] ? '(' . htmlspecialchars($e['kuerzel']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Kategorie</label>
                        <div style="display:flex; align-items:center; gap:var(--space-sm); flex-wrap:wrap">
                            <div id="kat-chips">
                                <?php foreach ($alleKategorien as $k): ?>
                                    <?php if (in_array($k['id'], $zugewieseneIds)): ?>
                                        <span class="chip chip-aktiv"><?= htmlspecialchars($k['name']) ?></span>
                                        <input type="hidden" name="kategorien[]" value="<?= $k['id'] ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="katModalOeffnen()">📁 Ändern</button>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Hersteller</label>
                        <select name="hersteller_id" class="erp-select">
                            <option value="">– kein Hersteller –</option>
                            <?php foreach ($alleHersteller as $h): ?>
                                <option value="<?= $h['id'] ?>"
                                    <?= (string)($artikel['hersteller_id'] ?? '') === (string)$h['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($h['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Steuerklasse *</label>
                        <select name="steuerklasse_id" class="erp-select">
                            <?php foreach ($steuerklassen as $s): ?>
                                <option value="<?= $s['id'] ?>"
                                    <?= (string)($artikel['steuerklasse_id'] ?? '') === (string)$s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?> (<?= $s['satz'] ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Zustand</label>
                        <select name="zustand" class="erp-select">
                            <option value="neu" <?= ($artikel['zustand'] ?? '') === 'neu' ? 'selected' : '' ?>>Neu</option>
                            <option value="neuwertig" <?= ($artikel['zustand'] ?? '') === 'neuwertig'  ? 'selected' : '' ?>>Neuwertig</option>
                            <option value="gebraucht" <?= ($artikel['zustand'] ?? '') === 'gebraucht'  ? 'selected' : '' ?>>Gebraucht</option>
                            <option value="bware" <?= ($artikel['zustand'] ?? '') === 'bware'  ? 'selected' : '' ?>>B-Ware</option>
                            <option value="ausstellungsstueck" <?= ($artikel['zustand'] ?? '') === 'ausstellungsstueck'  ? 'selected' : '' ?>>Ausstellungsstück</option>
                            <option value="generalueberholt" <?= ($artikel['zustand'] ?? '') === 'generalueberholt'  ? 'selected' : '' ?>>Generalüberholt</option>
                        </select>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-header">Beschreibung</div>

                    <div class="form-row">
                        <label class="form-label">Langbeschreibung</label>
                        <textarea name="beschreibung" class="erp-input" style="width:100%; height:120px"><?= htmlspecialchars($artikel['beschreibung'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Technische Details</label>
                        <textarea name="technische_details" class="erp-input" style="width:100%; height:80px"><?= htmlspecialchars($artikel['technische_details'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Interne Notiz</label>
                        <textarea name="beschreibung_intern" class="erp-input" style="width:100%; height:60px"><?= htmlspecialchars($artikel['beschreibung_intern'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-header">Physisch / Versand</div>

                    <div class="form-row">
                        <label class="form-label">Gewicht Artikel (kg)</label>
                        <input type="number" step="0.001" name="gewicht_artikel" class="erp-input"
                            value="<?= $artikel['gewicht_artikel'] ?? '' ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Gewicht Versand (kg)</label>
                        <input type="number" step="0.001" name="gewicht_versand" class="erp-input"
                            value="<?= $artikel['gewicht_versand'] ?? '' ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">L × B × H (mm)</label>
                        <div style="display:flex; gap:var(--space-sm)">
                            <input type="number" name="laenge" class="erp-input" style="width:80px"
                                placeholder="L" value="<?= $artikel['laenge'] ?? '' ?>">
                            <input type="number" name="breite" class="erp-input" style="width:80px"
                                placeholder="B" value="<?= $artikel['breite'] ?? '' ?>">
                            <input type="number" name="hoehe" class="erp-input" style="width:80px"
                                placeholder="H" value="<?= $artikel['hoehe'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Inhalt Menge</label>
                        <input type="number" step="0.001" name="inhalt_menge" class="erp-input"
                            value="<?= $artikel['inhalt_menge'] ?? '' ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Inhalt Einheit (g, ml…)</label>
                        <input type="text" name="inhalt_einheit" class="erp-input"
                            value="<?= htmlspecialchars($artikel['inhalt_einheit'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Herkunftsland</label>
                        <input type="text" name="herkunftsland" class="erp-input" maxlength="2"
                            value="<?= htmlspecialchars($artikel['herkunftsland'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">TARIC-Code</label>
                        <input type="text" name="taric_code" class="erp-input"
                            value="<?= htmlspecialchars($artikel['taric_code'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Grundpreis Bezugsmenge (g)</label>
                        <input type="number" name="grundpreis_bezugsmenge" class="erp-input"
                            value="<?= $artikel['grundpreis_bezugsmenge'] ?? '' ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Grundpreis anzeigen</label>
                        <select name="grundpreis_anzeigen" class="erp-select">
                            <option value="1" <?= ($artikel['grundpreis_anzeigen'] ?? 0) ? 'selected' : '' ?>>Ja</option>
                            <option value="0" <?= !($artikel['grundpreis_anzeigen'] ?? 0) ? 'selected' : '' ?>>Nein</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Überverkauf erlaubt</label>
                        <input type="checkbox" name="ueberverkauf_erlaubt" value="1"
                            <?= ($artikel['ueberverkauf_erlaubt'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Auslaufartikel</label>
                        <input type="checkbox" name="ist_auslaufartikel" value="1"
                            <?= ($artikel['ist_auslaufartikel'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Chargenpflicht</label>
                        <input type="checkbox" name="charge_pflicht" value="1"
                            <?= ($artikel['charge_pflicht'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Aktiv</label>
                        <select name="aktiv" class="erp-select">
                            <option value="1" <?= ($artikel['aktiv'] ?? 0) ? 'selected' : '' ?>>Ja</option>
                            <option value="0" <?= !($artikel['aktiv'] ?? 0) ? 'selected' : '' ?>>Nein</option>
                        </select>
                    </div>
                </div>

                <div style="padding-top: var(--space-sm)">
                    <button type="submit" class="btn btn-primary">💾 Speichern</button>
                    <a href="liste.php" class="btn btn-secondary">Abbrechen</a>
                </div>

            </div><!-- .card -->
        </form>
    </div>

    <div id="tab-varianten" class="versteckt">

        <!-- Panel Switcher -->
        <div style="display:flex; gap:var(--space-sm); margin-bottom:var(--space-md)">
            <button type="button" class="btn btn-primary btn-sm" id="var-btn-gen"
                onclick="varPanel('gen')">◀ Achsen &amp; Generator</button>
            <button type="button" class="btn btn-secondary btn-sm" id="var-btn-kinder"
                onclick="varPanel('kinder')">Kinder-Liste ▶</button>
            <?php if (!empty($neueKombis)): ?>
                <span style="margin-left:var(--space-sm); font-size:12px; color:var(--color-text-muted); align-self:center">
                    💡 <?= count($neueKombis) ?> neue Kombinationen möglich
                </span>
            <?php endif; ?>
        </div>

        <!-- Panel: Achsen & Generator -->
        <div id="var-panel-gen">
            <!-- Achsen Card -->
            <div class="card" style="margin-bottom:var(--space-md)">
                <div class="form-section-header" style="display:flex; justify-content:space-between; align-items:center">
                    <span>Achsen</span>
                    <a href="achsen_zuweisen.php?artikel_id=<?= $id ?>" class="btn btn-secondary btn-sm">✏️ Achsen bearbeiten</a>
                </div>

                <?php if (empty($achsen)): ?>
                    <p style="color:var(--color-text-muted); font-size:13px">Keine Achsen zugewiesen — <a href="achsen_zuweisen.php?artikel_id=<?= $id ?>">jetzt zuweisen</a></p>
                <?php else: ?>
                    <?php foreach ($achsen as $a): ?>
                        <div style="display:flex; align-items:center; gap:var(--space-md); margin-bottom:var(--space-sm)">
                            <span class="chip chip-aktiv" style="min-width:120px; text-align:center">
                                <?= htmlspecialchars($a['name']) ?>
                            </span>
                            <span style="font-size:11px; color:var(--color-text-muted)"><?= htmlspecialchars($a['darstellungsform']) ?></span>
                            <div style="display:flex; gap:var(--space-xs); flex-wrap:wrap">
                                <?php foreach ($werteProAchse[$a['id']] ?? [] as $w): ?>
                                    <span class="chip" style="background:#EDF2F7; color:#4A5568; border:1px solid #CBD5E0">
                                        <?= htmlspecialchars($w['wert']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Generator Card -->
            <div class="card">
                <div class="form-section-header">VarKombi-Generator</div>

                <?php if (empty($achsen)): ?>
                    <p style="color:var(--color-text-muted); font-size:13px">Erst Achsen zuweisen um Kombinationen generieren zu können.</p>
                <?php elseif (empty($neueKombis) && empty($vorhandeneKombis)): ?>
                    <p style="color:var(--color-text-muted); font-size:13px">Keine Kombinationen berechenbar.</p>
                <?php else: ?>

                    <!-- Info Strip -->
                    <div style="display:flex; gap:var(--space-lg); padding:var(--space-sm) 0; margin-bottom:var(--space-md); border-bottom:1px solid var(--color-border); font-size:12px">
                        <span><span style="color:#27AE60">●</span> <?= count($vorhandeneKombis) ?> existieren bereits</span>
                        <span><span style="color:#3182CE">●</span> <?= count($neueKombis) ?> werden neu erstellt</span>
                    </div>

                    <form action="varkombi_erstellen.php" method="POST" id="generator-form">
                        <input type="hidden" name="artikel_id" value="<?= $id ?>">
                        <label style="font-size:13px; display:flex; align-items:center; gap:var(--space-xs); margin-bottom:var(--space-md)">
                            <input type="checkbox" name="hat_eigenen_lagerstand" value="1" checked>
                            Eigener Lagerstand pro Variante
                        </label>

                        <table class="erp-table">
                            <thead>
                                <tr>
                                    <th style="width:32px"></th>
                                    <th>Artikelnummer</th>
                                    <th>Bezeichnung</th>
                                    <th>Aufpreis</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Bestehende (read-only) -->
                                <?php foreach ($vorhandeneKombis as $v): ?>
                                    <tr>
                                        <td><input type="checkbox" disabled checked></td>
                                        <td><a href="detail.php?id=<?= $v['artikel']['id'] ?>"><?= htmlspecialchars($v['artikel']['artikelnummer']) ?></a></td>
                                        <td><?= htmlspecialchars($v['artikel']['name']) ?></td>
                                        <td>–</td>
                                        <td><span class="chip <?= $v['artikel']['aktiv'] === 1 ? 'chip-aktiv' : 'chip-inaktiv' ?>"><?= $v['artikel']['aktiv'] === 1 ? '✓ existiert' : '✗ inaktiv' ?></span></td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Neue (editierbar) -->
                                <?php foreach ($neueKombis as $n => $k): ?>
                                    <?php
                                    $wertNamen     = array_map(fn($w) => $w['wert'], $k['kombi']);
                                    $vorschlagNr   = $artikel['artikelnummer'] . '-' . implode('-', $wertNamen);
                                    $vorschlagName = $artikel['name'] . ' ' . implode(' ', $wertNamen);
                                    $aufpreis      = array_sum(array_column($k['kombi'], 'aufpreis'));
                                    ?>
                                    <tr style="background:#EBF8FF">
                                        <td>
                                            <input type="hidden" name="kombis[<?= $n ?>][key]" value="<?= htmlspecialchars($k['key']) ?>">
                                            <input type="checkbox" name="kombis[<?= $n ?>][selected]" value="1" checked>
                                        </td>
                                        <td><input type="text" name="kombis[<?= $n ?>][artikelnummer]" class="erp-input" style="width:160px" value="<?= htmlspecialchars($vorschlagNr) ?>"></td>
                                        <td><input type="text" name="kombis[<?= $n ?>][name]" class="erp-input" style="width:100%" value="<?= htmlspecialchars($vorschlagName) ?>"></td>
                                        <td><input type="number" name="kombis[<?= $n ?>][aufpreis]" class="erp-input" style="width:80px" value="<?= number_format($aufpreis, 2, '.', '') ?>" step="0.01"></td>
                                        <td><span class="chip" style="background:#BEE3F8; color:#2B6CB0">● neu</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if (!empty($neueKombis)): ?>
                            <div style="display:flex; justify-content:flex-end; margin-top:var(--space-md)">
                                <button type="submit" class="btn btn-primary">▶ Ausgewählte generieren (<?= count($neueKombis) ?>)</button>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--color-text-muted); font-size:13px; margin-top:var(--space-md)">✓ Alle möglichen Kombinationen sind bereits angelegt.</p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>

        </div>

        <!-- Panel: Kinder-Liste -->
        <div id="var-panel-kinder" class="versteckt">
            <!-- Kinder-Tabelle -->
            <?php if (empty($kinder)): ?>
                <p style="color:var(--color-text-muted); font-size:13px">Noch keine Varianten vorhanden.</p>
            <?php else: ?>
                <div class="card">
                    <table class="erp-table">
                        <thead>
                            <tr>
                                <th>Artikelnummer</th>
                                <th>Bezeichnung</th>
                                <th>EAN</th>
                                <th>Preis</th>
                                <th>Bestand</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kinder as $k): ?>
                                <tr <?= !$k['aktiv'] ? 'class="row-inaktiv"' : '' ?>>
                                    <td><a href="detail.php?id=<?= $k['id'] ?>"><?= htmlspecialchars($k['artikelnummer']) ?></a></td>
                                    <td><?= htmlspecialchars($k['name']) ?></td>
                                    <td><?= htmlspecialchars($k['gtin'] ?? '–') ?></td>
                                    <td><?= $k['brutto_vk'] ? number_format($k['brutto_vk'], 2, ',', '.') . ' €' : '–' ?></td>
                                    <td><?= $k['gesamtbestand'] ?></td>
                                    <td><?= $k['aktiv'] ? '<span class="chip chip-aktiv">Aktiv</span>' : '<span class="chip chip-inaktiv">Inaktiv</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <div id="tab-preise" class="versteckt">
        <div class="card">Preise Platzhalter</div>
    </div>
    <div id="tab-lager" class="versteckt">
        <div class="card">Lager Platzhalter</div>
    </div>
    <div id="tab-bilder" class="versteckt">
        <div class="card">Bilder Platzhalter</div>
    </div>
    <div id="tab-merkmale" class="versteckt">
        <div class="card">Merkmale Platzhalter</div>
    </div>
    <div id="tab-lieferanten" class="versteckt">
        <div class="card">
            <div class="pagination-bar">
                <div>Lieferanten</div>
                <button class="btn btn-secondary btn-sm" onclick="liefModalOeffnen()">+ Lieferant hinzufügen</button>
            </div>
        </div>
        <?php if (empty($lieferanten)): ?>
            <p style="color:var(--color-text-muted); font-size:13px">Noch keine Lieferanten vorhanden.</p>
        <?php else: ?>
            <div class="card">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th>Lieferant</th>
                            <th>Lief.-Art.-Nr.</th>
                            <th>EK (netto)</th>
                            <th>Whg</th>
                            <th>VPE</th>
                            <th>LZ</th>
                            <th>MindBestMg.</th>
                            <th>Std.</th>
                            <th>AKTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lieferanten as $l): ?>
                            <tr data-al-id="<?= $l['id'] ?>"
                                data-lieferant-id="<?= $l['lieferant_id'] ?>"
                                data-artnr="<?= htmlspecialchars($l['artikelnummer_lieferant'] ?? '') ?>"
                                data-ek="<?= $l['netto_ek'] ?? '' ?>"
                                data-waehrung="<?= htmlspecialchars($l['waehrung']) ?>"
                                data-vpe="<?= $l['vpe_menge'] ?? '' ?>"
                                data-lz="<?= $l['lieferzeit_tage'] ?? '' ?>"
                                data-mba="<?= $l['mindestabnahme'] ?? '' ?>"
                                data-standard="<?= $l['standard_lieferant'] ? '1' : '' ?>"
                                <?= !$l['aktiv'] ? 'class="row-inaktiv"' : '' ?>>
                                <td><?= htmlspecialchars($l['lieferant_name']) ?></td>
                                <td><?= htmlspecialchars($l['artikelnummer_lieferant'] ?? '–') ?></td>
                                <td><?= $l['netto_ek'] ? number_format($l['netto_ek'], 2, ',', '.') . ' €' : '–' ?></td>
                                <td><?= htmlspecialchars($l['waehrung']) ?></td>
                                <td><?= $l['vpe_menge'] ?? '-' ?></td>
                                <td><?= $l['lieferzeit_tage'] ?? '-' ?></td>
                                <td><?= $l['mindestabnahme'] ?? '-' ?></td>
                                <td><?= $l['standard_lieferant'] ? '⭐' : '' ?></td>
                                <td class="aktionen">
                                    <button class="btn btn-secondary btn-sm" onclick="liefModalOeffnen(<?= $l['id'] ?>)">✏️</button>
                                    <a href="delete.php?id=<?= $l['id'] ?>"
                                        onclick="return confirm('Lieferant wirklich deaktivieren?')">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="kat-backdrop" class="modal-backdrop" onclick="katModalSchliessen()">
    <div id="kat-modal" class="modal" onclick="event.stopPropagation()">
        <h3>Kategorien zuweisen</h3>

        <div id="kat-checkboxen">
            <?php foreach ($alleKategorien as $k): ?>
                <label>
                    <input type="checkbox"
                        value="<?= $k['id'] ?>"
                        data-name="<?= htmlspecialchars($k['name']) ?>">
                    <?= htmlspecialchars($k['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <hr>

        <div id="kat-neu">
            <input type="text" id="neue-kat-name" placeholder="Neue Kategorie...">
            <button type="button" onclick="katAnlegen()">Anlegen</button>
        </div>

        <div id="kat-aktionen">
            <button type="button" onclick="katUebernehmen()">Übernehmen</button>
            <button type="button" onclick="katModalSchliessen()">Abbrechen</button>
        </div>
    </div>
</div>

<div id="lief-backdrop" class="modal-backdrop" onclick="liefModalSchliessen()">
    <div id="lief-modal" class="modal" onclick="event.stopPropagation()">
        <div id="lief-titel" style="font-size:15px; font-weight:600; padding-bottom:var(--space-sm); border-bottom:1px solid var(--color-border); margin-bottom:var(--space-xs)">
            Lieferant bearbeiten:
        </div>
        <form style="display:flex; flex-direction:column; gap:var(--space-sm)" action="">
            <input type="hidden" name="artikel_id" value="<?= $id ?>">
            <input type="hidden" name="al_id" id="lief-al-id" value="">
            <div class="form-row">
                <label class="form-label">Lieferant</label>
                <select class="erp-select" style="width:100%" name="lieferant_id" id="lief-lieferant-id">
                    <option value="">– Lieferant auswählen –</option>
                    <?php foreach ($alleLieferanten as $l): ?>
                        <option value="<?= $l['id'] ?>">
                            <?= htmlspecialchars($l['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label class="form-label">Artikelnummer beim Lieferant</label>
                <input class="erp-input" style="width:100%" type="text" name="artikelnummer_lieferant" id="lief-artnr"
                    value="">
            </div>
            <div class="form-row">
                <label class="form-label">Netto-EK</label>
                <input class="erp-input" style="width:100%" type="number" step="0.0001" name="netto_ek" id="lief-ek" value="">
            </div>
            <div class="form-row">
                <label class="form-label">Währung</label>
                <input class="erp-input" style="width:100%" type="text" name="waehrung" id="lief-waehrung"
                    value="">
            </div>
            <div class="form-row">
                <label class="form-label">VPE</label>
                <input class="erp-input" style="width:100%" type="number" step="1" min="1" name="vpe_menge" id="lief-vpe"
                    value="">
            </div>
            <div class="form-row">
                <label class="form-label">Lieferzeit</label>
                <input class="erp-input" style="width:100%" type="number" step="1" min="1" name="lieferzeit_tage" id="lief-lz"
                    value="">
            </div>
            <div class="form-row">
                <label class="form-label">Mindestbestellmenge</label>
                <input class="erp-input" style="width:100%" type="number" step="0.1" name="mindestabnahme" id="lief-mba"
                    value="">
            </div>
            <div class="form-row">
                <label class="form-label">Ist Standardlieferant</label>
                <input type="checkbox" name="standard_lieferant" id="lief-standard" value="1">
            </div>
            <div style="display:flex; gap:var(--space-sm); justify-content:flex-end; margin-top:var(--space-sm)">
                <button type="button" class="btn btn-primary btn-sm" onclick="liefSpeichern()">Übernehmen</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="liefModalSchliessen()">Abbrechen</button>
            </div>
        </form>
    </div>
</div>

<style>
    .aktionen {
        visibility: hidden;
    }

    /* Standard: versteckt */
    .erp-table tr:hover .aktionen {
        visibility: visible;
    }

    /* Hover: sichtbar */
</style>
<script>
    function zeigeTab(name, el) {
        document.querySelectorAll('[id^="tab-"]').forEach(d => d.classList.add('versteckt'));
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + name).classList.remove('versteckt');
        el.classList.add('active');
    }

    function katModalSchliessen() {
        document.getElementById('kat-backdrop').style.display = 'none';
    }


    function katModalOeffnen() {
        const kategorienArray = [...document.querySelectorAll('input[name="kategorien[]"]')].map(input => input.value);

        document.querySelectorAll('#kat-checkboxen input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = kategorienArray.includes(checkbox.value);
        });

        document.getElementById('kat-backdrop').style.display = 'flex';
    }

    function katUebernehmen() {
        const angehakt = [...document.querySelectorAll('#kat-checkboxen input[type="checkbox"]:checked')];
        document.querySelectorAll('input[name="kategorien[]"]').forEach(el => el.remove());
        const chips = document.getElementById('kat-chips');

        chips.innerHTML = '';
        angehakt.forEach(cb => {
            // 1. Hidden Input
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'kategorien[]';
            input.value = cb.value;
            chips.appendChild(input);

            // 2. Chip-Anzeige
            const span = document.createElement('span');
            span.className = 'chip';
            span.textContent = cb.dataset.name;
            chips.appendChild(span);
        });
        katModalSchliessen();
    }

    async function katAnlegen() {
        const katName = document.getElementById('neue-kat-name').value?.trim();
        if (!katName) {
            return;
        }

        const response = await fetch('kategorie_neu.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'name=' + encodeURIComponent(katName)
        });
        const data = await response.json();

        if (!data.erfolg) {
            alert(data.fehler);
            return;
        }

        const label = document.createElement('label');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.value = data.id;
        cb.dataset.name = data.name;
        cb.checked = true;
        label.appendChild(cb);
        label.appendChild(document.createTextNode(' ' + data.name));
        document.getElementById('kat-checkboxen').appendChild(label);

        document.getElementById('neue-kat-name').value = '';
    }

    function liefModalOeffnen(alId = null) {
        document.querySelector('#lief-modal div').textContent =
            alId ? 'Lieferant bearbeiten:' : 'Lieferant hinzufügen:';

        // Hidden field setzen
        document.getElementById('lief-al-id').value = alId ?? '';

        if (alId) {
            // Bearbeiten: Zeile finden, Daten lesen
            const tr = document.querySelector(`tr[data-al-id="${alId}"]`);
            document.getElementById('lief-lieferant-id').value = tr.dataset.lieferantId;
            document.getElementById('lief-artnr').value = tr.dataset.artnr;
            document.getElementById('lief-ek').value = tr.dataset.ek;
            document.getElementById('lief-waehrung').value = tr.dataset.waehrung;
            document.getElementById('lief-vpe').value = tr.dataset.vpe;
            document.getElementById('lief-lz').value = tr.dataset.lz;
            document.getElementById('lief-mba').value = tr.dataset.mba;
            document.getElementById('lief-standard').checked = tr.dataset.standard;
            // ... usw für alle Felder

        } else {
            // Neu: alle Felder leeren
            document.getElementById('lief-lieferant-id').value = '';
            document.getElementById('lief-artnr').value = '';
            document.getElementById('lief-ek').value = '';
            document.getElementById('lief-waehrung').value = '';
            document.getElementById('lief-vpe').value = '';
            document.getElementById('lief-lz').value = '';
            document.getElementById('lief-mba').value = '';
            document.getElementById('lief-standard').checked = '';
            // ... usw
        }

        document.getElementById('lief-backdrop').style.display = 'flex';
    }

    function liefModalSchliessen() {
        document.getElementById('lief-backdrop').style.display = 'none';
    }

    async function liefSpeichern() {
        const form = document.querySelector('#lief-modal form');
        const body = new URLSearchParams(new FormData(form)).toString();

        const response = await fetch('artikel_lieferant_speichern.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body
        });
        const data = await response.json();

        if (!data.erfolg) {
            alert(data.fehler);
            return;
        }

        liefModalSchliessen();
        location.reload();
    }

    function varPanel(name) {
        document.getElementById('var-panel-gen').classList.toggle('versteckt', name !== 'gen');
        document.getElementById('var-panel-kinder').classList.toggle('versteckt', name !== 'kinder');
        document.getElementById('var-btn-gen').className = 'btn btn-sm ' + (name === 'gen' ? 'btn-primary' : 'btn-secondary');
        document.getElementById('var-btn-kinder').className = 'btn btn-sm ' + (name === 'kinder' ? 'btn-primary' : 'btn-secondary');
    }
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>