<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../src/modules/preise/PreisService.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';
require_once __DIR__ . '/../../src/modules/artikel/MerkmaleRepository.php';

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
$kategorienBaum  = $service->getKategorienBaum();
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

$achsService        = new AchsenService();
$alleGlobalenAchsen = $achsService->findAll();

$lagerService  = new LagerService();
$lagerGruppen  = $lagerService->getLagerBestandChargen($id);
$bewegungslog  = $lagerService->getBewegungslog($id);
$alleLager     = $lagerService->getAlleLager();

$preisService        = new PreisService();
$kundengruppenPreise = $preisService->getKundengruppenPreise($id);
$staffelpreise       = $preisService->getStaffelpreise($id);
$preisAktionen       = $preisService->getAktionenFuerArtikel($id);
$saleOverrides       = $preisService->getSaleOverridesFuerArtikel($id);

$zustandsArtikelListe = ($artikel && empty($artikel['zustand_vater_id']))
    ? $service->getZustandsArtikelFuerDetail($id)
    : [];

$istKind = !empty($artikel['vaterartikel_id']);

$merkmaleRepo        = new MerkmaleRepository();
$artikeltypId        = $artikel ? (int)$artikel['artikeltyp_id'] : null;
$merkmaleFuerTyp     = $merkmaleRepo->findFuerArtikeltyp($artikeltypId);
$artikelMerkmale     = $merkmaleRepo->findByArtikelId($id);
$gesetzteWertIds     = array_column($artikelMerkmale, 'merkmal_wert_id');

// Standard-Lieferant für Marge
$stdLieferant = null;
foreach ($lieferanten as $l) {
    if ($l['standard_lieferant']) {
        $stdLieferant = $l;
        break;
    }
}

// Standard-KG-Preis für Marge + Grundpreis
$kgEndkunde   = null;
$standardKgId = null;
foreach ($kundengruppenPreise as $kp) {
    if ($kp['ist_standard']) {
        $standardKgId = (int)$kp['id'];
        if ($kp['netto_vk'] !== null) $kgEndkunde = $kp;
        break;
    }
}
$effektiverPreis = $standardKgId ? $preisService->getEffektiverPreis($id, $standardKgId) : null;

// Marge berechnen
$margeInfo = null;
if ($stdLieferant && (float)$stdLieferant['netto_ek'] > 0 && $kgEndkunde && (float)$kgEndkunde['netto_vk'] > 0) {
    $nEk = (float)$stdLieferant['netto_ek'];
    $nVk = (float)$kgEndkunde['netto_vk'];
    $margeInfo = ['prozent' => ($nVk - $nEk) / $nVk * 100, 'absolut' => $nVk - $nEk];
}

// Grundpreis berechnen
$grundpreisAnzeige = null;
if (
    $artikel['grundpreis_anzeigen'] && (float)($artikel['inhalt_menge'] ?? 0) > 0
    && (float)($artikel['grundpreis_bezugsmenge'] ?? 0) > 0 && (float)($artikel['brutto_vk'] ?? 0) > 0
) {
    $gpWert = (float)$artikel['brutto_vk'] / (float)$artikel['inhalt_menge'] * (float)$artikel['grundpreis_bezugsmenge'];
    $gpBez  = rtrim(rtrim(number_format((float)$artikel['grundpreis_bezugsmenge'], 3, ',', '.'), '0'), ',');
    $grundpreisAnzeige = number_format($gpWert, 2, ',', '.') . ' € / ' . $gpBez . ' ' . ($artikel['inhalt_einheit'] ?? '');
}

$lagerGesamtBestand = array_sum(array_column($lagerGruppen, 'gesamt'));
$lagerAnzahlLager   = count($lagerGruppen);
$lagerAnzahlChargen = array_sum(array_map(fn($lg) => count($lg['chargen']), $lagerGruppen));

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

$flashErfolg = $_SESSION['erfolg'] ?? null;
$flashFehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);


if (!function_exists('formatBestand')) {
    function formatBestand(int|float|string $wert): string
    {
        $v = (float) $wert;
        return $v == (int) $v
            ? number_format($v, 0, ',', '.')
            : number_format($v, 3, ',', '.');
    }
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

$zugewieseneAchsenIds = array_column($achsen, 'achse_id');
$wertIdsInUse         = $variantenService->findWertIdsInUse($id);
$wertIdsInUseSet      = array_flip($wertIdsInUse);

// Achsenhierarchie für Generator aufbauen
$achseInfoMap = array_column($alleGlobalenAchsen, null, 'id');

// Lookup: welche achse_ids sind diesem Artikel zugewiesen?
$assignedAchseIdSet = array_flip(array_map('intval', array_column($achsen, 'achse_id')));

// Sub-Achsen nach Parent-Id gruppieren (nur die dem Artikel zugewiesenen)
$subAchsenByParent = [];
foreach ($achsen as $a) {
    $aId = (int)$a['achse_id'];
    $pid = (int)($a['abhaengig_von_achse_id'] ?? 0);
    if ($pid > 0) {
        $subAchsenByParent[$pid][] = $aId;
    }
}

// Dimensionen aufbauen:
// Sub-Achsen-Werte kommen IMMER in die Parent-Dimension (UNION), nie als eigene Dimension.
// Grund: Sub-Achsen sind Unterkategorien der Parent-Achse, nicht separate Produkt-Dimensionen.
// Suffix = Sub-Achsen-Name (z.B. "gelb MIX" statt nur "gelb")
$dimensionen = [];
$verarbeitet = [];

foreach ($achsen as $a) {
    $aId = (int)$a['achse_id'];
    if (isset($verarbeitet[$aId])) continue;

    $pid = (int)($a['abhaengig_von_achse_id'] ?? 0);

    if ($pid > 0 && isset($assignedAchseIdSet[$pid])) {
        // Sub-Achse, Parent zugewiesen → wird beim Parent-Durchlauf eingebaut
        $verarbeitet[$aId] = true;
        continue;
    }

    if ($pid > 0 && !isset($assignedAchseIdSet[$pid])) {
        // Sub-Achse, Parent NICHT zugewiesen → UNION aller Geschwister = eine Dimension
        $gruppe = [];
        foreach ($subAchsenByParent[$pid] ?? [] as $sibId) {
            if (isset($verarbeitet[$sibId])) continue;
            $sibSuffix = $achseInfoMap[$sibId]['name'] ?? '';
            foreach ($werteProAchse[$sibId] ?? [] as $w) {
                $w['achse_suffix'] = $sibSuffix;
                $gruppe[] = $w;
            }
            $verarbeitet[$sibId] = true;
        }
        if (!empty($gruppe)) {
            $dimensionen[] = $gruppe;
        }
        continue;
    }

    // Root-Achse (pid=0): eigene Werte + ALLE Sub-Achsen-Werte (UNION, mit Suffix)
    $gruppe = [];
    $verarbeitet[$aId] = true;

    foreach ($werteProAchse[$aId] ?? [] as $w) {
        $gruppe[] = $w;
    }

    foreach ($subAchsenByParent[$aId] ?? [] as $subId) {
        if (isset($verarbeitet[$subId])) continue;
        $subSuffix = $achseInfoMap[$subId]['name'] ?? '';
        foreach ($werteProAchse[$subId] ?? [] as $w) {
            $w['achse_suffix'] = $subSuffix;
            $gruppe[] = $w;
        }
        $verarbeitet[$subId] = true;
    }

    if (!empty($gruppe)) {
        $dimensionen[] = $gruppe;
    }
}
$alleKombis = !empty($dimensionen) ? kartesischesProdukt($dimensionen) : [];

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
<div class="actionbar-left">
    <button form="stammdaten-form" type="submit" class="btn btn-primary btn-sm">💾 Speichern</button>
    <a href="liste.php" class="btn btn-secondary btn-sm">Abbrechen</a>
    <div class="actionbar-sep"></div>
    <button class="btn btn-secondary btn-sm" style="color:var(--color-warning)">Deaktivieren</button>
    <button class="btn btn-secondary btn-sm" style="color:#0a6ebd">Im Shop ▼</button>
</div>
<span id="unsaved-banner" class="unsaved-indicator">ungespeicherte Änderungen</span>
<div class="actionbar-right">
    <button class="btn btn-danger btn-sm">Löschen</button>
</div>
HTML;

$sidebarItems = [
    ['type' => 'back',    'label' => 'zur Liste', 'href' => '/mealana/artikel/liste.php'],
    ['type' => 'separator'],
    ['type' => 'context', 'artNr' => $artikel['artikelnummer'], 'name' => $artikel['name']],
    ['type' => 'separator'],
    ['type' => 'nav', 'icon' => '📋', 'label' => 'Stammdaten',  'href' => '#stammdaten',  'active' => true],
    ['type' => 'nav', 'icon' => '💲', 'label' => 'Preise',      'href' => '#preise'],
    ['type' => 'nav', 'icon' => '📦', 'label' => 'Lager',       'href' => '#lager'],
    ['type' => 'nav', 'icon' => '🖼',  'label' => 'Bilder',      'href' => '#bilder'],
    ['type' => 'nav', 'icon' => '🏷',  'label' => 'Merkmale',    'href' => '#merkmale'],
    ['type' => 'nav', 'icon' => '🚚', 'label' => 'Lieferanten', 'href' => '#lieferanten'],
    ['type' => 'separator'],
    ['type' => 'nav', 'icon' => '🌐', 'label' => 'SEO', 'href' => '#seo'],
    ['type' => 'grayed', 'icon' => '📊', 'label' => 'Statistik'],
];
if (!$istKind) {
    array_splice($sidebarItems, 5, 0, [['type' => 'nav', 'icon' => '🎨', 'label' => 'Varianten', 'href' => '#varianten', 'badge' => count($kinder)]]);
}

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
        <div style="display:flex;gap:var(--space-lg);margin-top:4px;font-size:12px;align-items:center;flex-wrap:wrap">
            <?php $hatAktivAktion = $effektiverPreis && in_array($effektiverPreis['quelle'], ['sale', 'aktion']); ?>
            <span>
                VK: <strong<?= $hatAktivAktion ? ' style="text-decoration:line-through;color:var(--color-text-muted);font-weight:normal"' : '' ?>><?= $artikel['brutto_vk'] ? number_format((float)$artikel['brutto_vk'], 2, ',', '.') . ' €' : '–' ?></strong>
            </span>
            <?php if ($hatAktivAktion): ?>
                <span style="display:inline-flex;align-items:center;gap:5px;background:#FFF8E1;border:1px solid #FFB300;border-radius:6px;padding:2px 10px;color:#E65100">
                    🔥 <strong>Aktion aktiv</strong>
                    <?php if ($effektiverPreis['info']): ?>
                        <span style="color:var(--color-text-muted)">·</span> <?= htmlspecialchars($effektiverPreis['info']) ?>
                    <?php endif; ?>
                    <?php if ($effektiverPreis['bis']): ?>
                        <span style="color:var(--color-text-muted)">·</span> bis <?= date('d.m.Y', strtotime($effektiverPreis['bis'])) ?>
                    <?php endif; ?>
                    <span style="color:var(--color-text-muted)">·</span> <strong><?= number_format((float)$effektiverPreis['brutto_vk'], 2, ',', '.') ?> €</strong>
                </span>
            <?php endif; ?>
            <span>
                Bestand: <strong style="<?= $lagerGesamtBestand <= 0 ? 'color:var(--color-danger)' : '' ?>"><?= formatBestand($lagerGesamtBestand) ?></strong>
            </span>
        </div>
    </div>
    <div class="article-header-toggle">
        <span style="width:10px;height:10px;border-radius:50%;background:<?= $artikel['aktiv'] ? '#28a745' : '#dc3545' ?>;display:inline-block"></span>
        <?= $artikel['aktiv'] ? 'Aktiv' : 'Inaktiv' ?>
    </div>
</div>
<?php if ($flashErfolg): ?>
    <div class="success-banner" id="flash-php">✓ <?= htmlspecialchars($flashErfolg) ?></div>
<?php endif; ?>
<?php if ($flashFehler): ?>
    <div class="error-banner" id="flash-php-err">✗ <?= htmlspecialchars($flashFehler) ?></div>
<?php endif; ?>
<div id="ajax-flash" style="display:none;margin:var(--space-sm) var(--space-md) 0"></div>
<div class="tab-bar">
    <a class="tab active" href="#" onclick="zeigeTab('stammdaten',this);return false;">Stammdaten</a>
    <?php if (!$istKind): ?>
        <a class="tab" href="#" onclick="zeigeTab('varianten',this);return false;">
            Varianten <?php if (count($kinder) > 0): ?><span class="badge"><?= count($kinder) ?></span><?php endif; ?>
        </a>
    <?php endif; ?>
    <a class="tab" href="#" onclick="zeigeTab('preise',this);return false;">Preise</a>
    <a class="tab" href="#" onclick="zeigeTab('lager',this);return false;">Lager</a>
    <a class="tab" href="#" onclick="zeigeTab('bilder',this);return false;">Bilder</a>
    <a class="tab" href="#" onclick="zeigeTab('merkmale',this);return false;">Merkmale</a>
    <a class="tab" href="#" onclick="zeigeTab('lieferanten',this);return false;">Lieferanten</a>
    <a class="tab" href="#" onclick="zeigeTab('seo',this);return false;">SEO</a>
</div>

<div style="padding: var(--space-md)">

    <div id="tab-stammdaten">
        <form id="stammdaten-form" action="aktualisieren.php" method="POST">
            <input type="hidden" name="id" value="<?= $id ?>">
            <?php /* SEO-Felder: nicht im Wireframe, aber Daten-Erhalt bis SEO-Tab gebaut wird 
            <input type="hidden" name="meta_titel" value="<?= htmlspecialchars($artikel['meta_titel']       ?? '') ?>">
            <input type="hidden" name="meta_description" value="<?= htmlspecialchars($artikel['meta_description'] ?? '') ?>">
            <input type="hidden" name="url_slug" value="<?= htmlspecialchars($artikel['url_slug']         ?? '') ?>">
            */ ?>
            <input type="hidden" name="zustand_vater_id" value="<?= (int)($artikel['zustand_vater_id'] ?? 0) ?: '' ?>">

            <!-- Kern-Daten + Einstellungen -->
            <div style="display:grid;grid-template-columns:1fr 280px;gap:var(--space-md);align-items:start;min-width:0">

                <div class="card" style="min-width:0">
                    <div class="form-section">
                        <div class="form-section-header">Kern-Daten</div>

                        <div class="form-group">
                            <label class="form-label">Bezeichnung *</label>
                            <input type="text" name="name" class="erp-input" style="width:100%"
                                value="<?= htmlspecialchars($artikel['name']) ?>" required>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
                            <div class="form-group">
                                <label class="form-label">Art.-Nr. *</label>
                                <input type="text" name="artikelnummer" class="erp-input" style="width:100%"
                                    value="<?= htmlspecialchars($artikel['artikelnummer']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">EAN / GTIN</label>
                                <input type="text" name="ean_gtin13" class="erp-input" style="width:100%"
                                    maxlength="13" value="<?= htmlspecialchars($ean_gtin13) ?>">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
                            <div class="form-group">
                                <label class="form-label">Hersteller</label>
                                <select name="hersteller_id" class="erp-select" style="width:100%">
                                    <option value="">– kein Hersteller –</option>
                                    <?php foreach ($alleHersteller as $h): ?>
                                        <option value="<?= $h['id'] ?>"
                                            <?= (string)($artikel['hersteller_id'] ?? '') === (string)$h['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($h['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Steuerklasse *</label>
                                <select name="steuerklasse_id" class="erp-select" style="width:100%">
                                    <?php foreach ($steuerklassen as $s): ?>
                                        <option value="<?= $s['id'] ?>"
                                            <?= (string)($artikel['steuerklasse_id'] ?? '') === (string)$s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name']) ?> (<?= $s['satz'] ?>%)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
                            <div class="form-group">
                                <label class="form-label">Artikeltyp *</label>
                                <select name="artikeltyp" class="erp-select" style="width:100%">
                                    <option value="">– bitte wählen –</option>
                                    <?php foreach ($artikelTypen as $typ): ?>
                                        <option value="<?= htmlspecialchars($typ['code']) ?>"
                                            <?= ($artikel['artikeltyp'] ?? '') === $typ['code'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($typ['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Einheit</label>
                                <select name="einheit_id" class="erp-select" style="width:100%">
                                    <?php foreach ($alleEinheiten as $e): ?>
                                        <option value="<?= $e['id'] ?>"
                                            <?= (string)($artikel['einheit_id'] ?? '') === (string)$e['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e['name']) ?>
                                            <?= $e['kuerzel'] ? '(' . htmlspecialchars($e['kuerzel']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kategorie</label>
                            <div style="display:flex;align-items:center;gap:var(--space-sm);flex-wrap:wrap">
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
                    </div>
                </div>

                <!-- Einstellungen + Zustand (eine Card mit Divider, wie im Wireframe) -->
                <div class="card">
                    <div class="form-section">
                        <div class="form-section-header">Einstellungen</div>
                        <label class="form-check">
                            <input type="checkbox" name="aktiv" value="1"
                                <?= ($artikel['aktiv'] ?? 0) ? 'checked' : '' ?>>
                            Aktiv
                        </label>
                        <label class="form-check">
                            <input type="checkbox" name="ueberverkauf_erlaubt" value="1"
                                <?= ($artikel['ueberverkauf_erlaubt'] ?? 0) ? 'checked' : '' ?>>
                            Überverkauf erlaubt
                        </label>
                        <label class="form-check">
                            <input type="checkbox" name="ist_auslaufartikel" value="1"
                                <?= ($artikel['ist_auslaufartikel'] ?? 0) ? 'checked' : '' ?>>
                            Auslaufartikel
                        </label>
                        <label class="form-check">
                            <input type="checkbox" name="charge_pflicht" value="1"
                                <?= ($artikel['charge_pflicht'] ?? 0) ? 'checked' : '' ?>>
                            Chargenpflicht
                        </label>

                        <hr style="border:none;border-top:1px solid var(--color-border);margin:var(--space-sm) 0">

                        <div class="form-section-header" style="margin-top:var(--space-sm)">Zustand</div>
                        <select name="zustand" class="erp-select" style="width:100%">
                            <option value="neu" <?= ($artikel['zustand'] ?? '') === 'neu' ? 'selected' : '' ?>>Neu (Standard)</option>
                            <option value="gebraucht" <?= ($artikel['zustand'] ?? '') === 'gebraucht' ? 'selected' : '' ?>>Gebraucht (GEB)</option>
                            <option value="generalueberholt" <?= ($artikel['zustand'] ?? '') === 'generalueberholt' ? 'selected' : '' ?>>Generalüberholt (GUE)</option>
                            <option value="beschaedigt" <?= ($artikel['zustand'] ?? '') === 'beschaedigt' ? 'selected' : '' ?>>Beschädigt (BSC)</option>
                            <option value="retour" <?= ($artikel['zustand'] ?? '') === 'retour' ? 'selected' : '' ?>>Retour (RET)</option>
                            <option value="demo" <?= ($artikel['zustand'] ?? '') === 'demo' ? 'selected' : '' ?>>Demo (DMO)</option>
                            <option value="muster" <?= ($artikel['zustand'] ?? '') === 'muster' ? 'selected' : '' ?>>Muster (MST)</option>
                            <option value="ausstellungsstueck" <?= ($artikel['zustand'] ?? '') === 'ausstellungsstueck' ? 'selected' : '' ?>>Ausstellungsstück (AST)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Beschreibung -->
            <div class="card" style="margin-top:var(--space-md)">
                <div class="form-section">
                    <div class="form-section-header">Beschreibung</div>

                    <div class="form-group">
                        <label class="form-label">Kurzbeschreibung</label>
                        <input type="text" name="kurzbeschreibung" class="erp-input" style="width:100%"
                            value="<?= htmlspecialchars($artikel['kurzbeschreibung'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Langbeschreibung</label>
                        <textarea name="beschreibung" class="erp-input" style="width:100%;height:120px"><?= htmlspecialchars($artikel['beschreibung'] ?? '') ?></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
                        <div class="form-group">
                            <label class="form-label">Technische Details</label>
                            <textarea name="technische_details" class="erp-input" style="width:100%;height:80px"><?= htmlspecialchars($artikel['technische_details'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Interne Notiz (nie öffentlich)</label>
                            <textarea name="beschreibung_intern" class="erp-input" style="width:100%;height:80px"><?= htmlspecialchars($artikel['beschreibung_intern'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Physisch + Logistik -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md);margin-top:var(--space-md)">

                <div class="card">
                    <div class="form-section">
                        <div class="form-section-header">Physisch</div>

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
                            <div style="display:flex;gap:var(--space-sm)">
                                <input type="number" name="laenge" class="erp-input" style="width:72px"
                                    placeholder="L" value="<?= $artikel['laenge'] ?? '' ?>">
                                <input type="number" name="breite" class="erp-input" style="width:72px"
                                    placeholder="B" value="<?= $artikel['breite'] ?? '' ?>">
                                <input type="number" name="hoehe" class="erp-input" style="width:72px"
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
                    </div>
                </div>

                <div class="card">
                    <div class="form-section">
                        <div class="form-section-header">Logistik / Grundpreis</div>

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
                            <label class="form-label">Grundpreis Bezugsmenge</label>
                            <div style="display:flex;align-items:center;gap:var(--space-xs)">
                                <input type="number" name="grundpreis_bezugsmenge" class="erp-input"
                                    value="<?= $artikel['grundpreis_bezugsmenge'] ?? 100 ?>">
                                <span style="font-size:13px;color:var(--color-text-muted)">g</span>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Grundpreis im Shop</label>
                            <label class="form-check" style="margin:0">
                                <input type="checkbox" name="grundpreis_anzeigen" value="1"
                                    <?= ($artikel['grundpreis_anzeigen'] ?? 0) ? 'checked' : '' ?>>
                                anzeigen
                            </label>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <?php if (!$istKind): ?>
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
                        <?php
                        // Baum aufbauen: Root-Achsen + ihre Sub-Achsen gruppiert
                        // $a['abhaengig_von_achse_id'] kommt direkt aus findAchsenByArtikelId SQL
                        $dispRoots = [];
                        $dispSubs  = []; // parent achse_id → [achsen]
                        foreach ($achsen as $a) {
                            $aId = (int)$a['achse_id'];
                            $pid = (int)($a['abhaengig_von_achse_id'] ?? 0);
                            if ($pid > 0 && isset($assignedAchseIdSet[$pid])) {
                                $dispSubs[$pid][] = $a;
                            } else {
                                $dispRoots[] = $a;
                            }
                        }
                        ?>
                        <?php foreach ($dispRoots as $a):
                            $aId = (int)$a['achse_id'];
                        ?>
                            <div style="margin-bottom:var(--space-xs)">
                                <!-- Root-Achse -->
                                <div style="display:flex; align-items:center; gap:var(--space-md); margin-bottom:2px">
                                    <span class="chip chip-aktiv" style="min-width:120px; text-align:center">
                                        <?= htmlspecialchars($a['name']) ?>
                                    </span>
                                    <span style="font-size:11px; color:var(--color-text-muted)"><?= htmlspecialchars($a['darstellungsform']) ?></span>
                                    <div style="display:flex; gap:var(--space-xs); flex-wrap:wrap">
                                        <?php foreach ($werteProAchse[$aId] ?? [] as $w): ?>
                                            <span class="chip" style="background:#EDF2F7; color:#4A5568; border:1px solid #CBD5E0">
                                                <?= htmlspecialchars($w['wert']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- Sub-Achsen direkt darunter eingerückt -->
                                <?php foreach ($dispSubs[$aId] ?? [] as $sub):
                                    $subId = (int)$sub['achse_id'];
                                ?>
                                    <div style="display:flex; align-items:center; gap:var(--space-md); margin-bottom:2px;
                                                margin-left:20px; padding-left:12px; border-left:3px solid #C7D2FE">
                                        <span class="chip" style="min-width:120px; text-align:center;
                                                                   background:#EDE9FE; color:#5B21B6; border:1px solid #C4B5FD">
                                            ↳ <?= htmlspecialchars($sub['name']) ?>
                                        </span>
                                        <span style="font-size:11px; color:var(--color-text-muted)"><?= htmlspecialchars($sub['darstellungsform']) ?></span>
                                        <div style="display:flex; gap:var(--space-xs); flex-wrap:wrap">
                                            <?php foreach ($werteProAchse[$subId] ?? [] as $w): ?>
                                                <span class="chip" style="background:#F5F3FF; color:#5B21B6; border:1px solid #DDD6FE">
                                                    <?= htmlspecialchars($w['wert']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
                                        $wertNamen     = array_map(fn($w) => trim($w['wert'] . (!empty($w['achse_suffix']) ? ' ' . $w['achse_suffix'] : '')), $k['kombi']);
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
                                    <button type="submit" id="gen-submit-btn" class="btn btn-primary">▶ Ausgewählte generieren (<?= count($neueKombis) ?>)</button>
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
    <?php endif; ?>

    <div id="tab-preise" class="versteckt">

        <?php $hatKgPreise = !empty(array_filter($kundengruppenPreise, fn($kp) => $kp['brutto_vk'] !== null)); ?>

        <!-- Preisinfo: UVP · Grundpreis · Marge -->
        <div class="card" style="margin-bottom:var(--space-lg)">
            <div style="display:flex;justify-content:space-evenly;align-items:flex-start;padding:var(--space-xs) 0">

                <!-- UVP -->
                <div style="flex:1;padding:0 var(--space-lg);border-right:1px solid var(--color-border);text-align:center">
                    <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--color-text-muted);margin-bottom:4px">UVP / Streichpreis</div>
                    <div style="display:flex;align-items:center;justify-content:center;gap:var(--space-sm)">
                        <span id="uvp-anzeige" style="font-size:18px;font-weight:600">
                            <?= $artikel['uvp'] ? number_format((float)$artikel['uvp'], 2, ',', '.') . ' €' : '–' ?>
                        </span>
                        <button class="btn btn-sm" onclick="uvpBearbeiten()" title="UVP bearbeiten">✏️</button>
                    </div>
                    <div id="uvp-edit" style="display:none;margin-top:var(--space-xs)">
                        <input class="erp-input" type="number" step="0.01" min="0" id="uvp-input"
                            value="<?= htmlspecialchars($artikel['uvp'] ?? '') ?>" style="width:120px">
                        <button class="btn btn-primary btn-sm" onclick="uvpSpeichern()" style="margin-left:4px">✓</button>
                        <button class="btn btn-sm" onclick="uvpAbbrechen()" style="margin-left:2px">✕</button>
                    </div>
                </div>

                <!-- Grundpreis -->
                <div style="flex:1;padding:0 var(--space-lg);border-right:1px solid var(--color-border);text-align:center">
                    <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--color-text-muted);margin-bottom:4px">Grundpreis (Endkunde)</div>
                    <?php if ($grundpreisAnzeige): ?>
                        <div style="font-size:18px;font-weight:600"><?= htmlspecialchars($grundpreisAnzeige) ?></div>
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px">
                            aus <?= number_format((float)$artikel['inhalt_menge'], 0, ',', '.') ?> <?= htmlspecialchars($artikel['inhalt_einheit'] ?? '') ?> Inhalt
                        </div>
                    <?php elseif (!$artikel['grundpreis_anzeigen']): ?>
                        <div style="color:var(--color-text-muted);font-size:13px">Deaktiviert (Stammdaten)</div>
                    <?php else: ?>
                        <div style="color:var(--color-text-muted);font-size:13px">– (Endkunden-Preis fehlt)</div>
                    <?php endif; ?>
                </div>

                <!-- Marge -->
                <div style="flex:1;padding:0 var(--space-lg);text-align:center">
                    <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--color-text-muted);margin-bottom:4px">Marge (Endkunde / Std.-Lief.)</div>
                    <?php if ($margeInfo): ?>
                        <div style="font-size:18px;font-weight:600;color:<?= $margeInfo['prozent'] < 20 ? 'var(--color-danger)' : ($margeInfo['prozent'] < 35 ? 'var(--color-warning)' : 'var(--color-success)') ?>">
                            <?= number_format($margeInfo['prozent'], 1, ',', '.') ?> %
                        </div>
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px">
                            <?= number_format($margeInfo['absolut'], 4, ',', '.') ?> € netto pro Stk.
                        </div>
                    <?php elseif (!$stdLieferant): ?>
                        <div style="display:flex;align-items:center;justify-content:center;gap:6px;font-size:13px;color:var(--color-text-muted)">
                            <span style="display:inline-flex;align-items:center;justify-content:center;background:#2563EB;color:#fff;border-radius:50%;width:16px;height:16px;font-size:10px;font-weight:700;flex-shrink:0" title="Kein Standard-Lieferant gesetzt">!</span>
                            Kein Standard-Lieferant
                        </div>
                    <?php else: ?>
                        <div style="color:var(--color-text-muted);font-size:13px">– (Preise fehlen)</div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- SALE-Override -->
        <?php
        $hatSaleOverrides       = !empty($saleOverrides);
        $hatAktiveSaleOverrides = !empty(array_filter($saleOverrides, fn($s) => $s['ist_aktiv']));
        ?>
        <div class="card" style="margin-bottom:var(--space-lg)">
            <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer"
                onclick="togglePreisSektion('sale-body', this)">
                <div style="display:flex;align-items:center;gap:var(--space-sm)">
                    <h3 style="margin:0">SALE-Override</h3>
                    <?php if ($hatAktiveSaleOverrides): ?>
                        <span style="background:#dc2626;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px">
                            <?= count(array_filter($saleOverrides, fn($s) => $s['ist_aktiv'])) ?> aktiv
                        </span>
                    <?php endif; ?>
                </div>
                <span id="sale-toggle"><?= $hatSaleOverrides ? '▲' : '▼' ?></span>
            </div>
            <div id="sale-body" style="margin-top:var(--space-md);<?= $hatSaleOverrides ? '' : 'display:none' ?>">
                <?php if ($hatSaleOverrides): ?>
                    <table class="erp-table" style="margin-bottom:var(--space-sm)">
                        <thead>
                            <tr>
                                <th>Kundengruppe</th>
                                <th style="text-align:right">Sale-Preis</th>
                                <th style="text-align:right">Vorher</th>
                                <th style="white-space:nowrap">Gültig ab</th>
                                <th style="white-space:nowrap">Gültig bis</th>
                                <th>Bis Lagerstand 0</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saleOverrides as $s): ?>
                                <tr>
                                    <td><?= $s['kg_name'] ? htmlspecialchars($s['kg_name']) : '<span style="color:var(--color-text-muted)">Alle</span>' ?></td>
                                    <td style="text-align:right;font-weight:600"><?= number_format((float)$s['brutto_vk'], 2, ',', '.') ?> €</td>
                                    <td style="text-align:right;color:var(--color-text-muted)">
                                        <?= $s['preis_vorher_brutto'] ? number_format((float)$s['preis_vorher_brutto'], 2, ',', '.') . ' €' : '–' ?>
                                    </td>
                                    <td style="white-space:nowrap"><?= $s['gueltig_ab'] ? date('d.m.Y H:i', strtotime($s['gueltig_ab'])) : '–' ?></td>
                                    <td style="white-space:nowrap"><?= $s['gueltig_bis'] ? date('d.m.Y H:i', strtotime($s['gueltig_bis'])) : '–' ?></td>
                                    <td style="text-align:center"><?= $s['bis_lagerstand_null'] ? '✓' : '–' ?></td>
                                    <td>
                                        <?php if ($s['ist_aktiv']): ?>
                                            <span style="background:#dcfce7;color:#166534;font-size:12px;padding:2px 8px;border-radius:10px;font-weight:600">aktiv</span>
                                        <?php else: ?>
                                            <span style="background:#f1f5f9;color:#64748b;font-size:12px;padding:2px 8px;border-radius:10px">inaktiv</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap">
                                        <button class="btn btn-sm" onclick="saleModalOeffnen(<?= htmlspecialchars(json_encode($s)) ?>)">✏️</button>
                                        <button class="btn btn-sm btn-danger" onclick="saleLoeschen(<?= $s['id'] ?>)">🗑</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <button class="btn btn-secondary btn-sm" onclick="saleModalOeffnen(null)">+ SALE-Override anlegen</button>
            </div>
        </div>

        <!-- Preis-Aktionen (Kategorie-Aktionen) -->
        <?php
        $heute             = date('Y-m-d');
        $hatAktionen       = !empty($preisAktionen);
        $hatAktiveAktionen = !empty(array_filter($preisAktionen, fn($a) =>
            $a['gestartet'] && $a['gueltig_ab'] <= $heute && $a['gueltig_bis'] >= $heute
        ));
        ?>
        <div class="card" style="margin-bottom:var(--space-lg)">
            <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer"
                onclick="togglePreisSektion('aktionen-body', this)">
                <div style="display:flex;align-items:center;gap:var(--space-sm)">
                    <h3 style="margin:0">Preis-Aktionen</h3>
                    <?php if ($hatAktiveAktionen): ?>
                        <span style="background:#16a34a;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px">
                            aktiv
                        </span>
                    <?php endif; ?>
                </div>
                <span id="aktionen-toggle"><?= $hatAktionen ? '▲' : '▼' ?></span>
            </div>
            <div id="aktionen-body" style="margin-top:var(--space-md);<?= $hatAktionen ? '' : 'display:none' ?>">
                <?php if ($hatAktionen): ?>
                    <table class="erp-table">
                        <thead>
                            <tr>
                                <th>Aktion</th>
                                <th>Kategorie</th>
                                <th>Kundengruppe</th>
                                <th style="text-align:right">Aktionspreis</th>
                                <th style="white-space:nowrap">Gültig ab</th>
                                <th style="white-space:nowrap">Gültig bis</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preisAktionen as $a):
                                $istAktiv = $a['gestartet'] && $a['gueltig_ab'] <= $heute && $a['gueltig_bis'] >= $heute;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['aktion_name']) ?></td>
                                    <td><?= $a['kategorie_name'] ? htmlspecialchars($a['kategorie_name']) : '–' ?></td>
                                    <td><?= $a['kundengruppen_name'] ? htmlspecialchars($a['kundengruppen_name']) : '<span style="color:var(--color-text-muted)">Alle</span>' ?></td>
                                    <td style="text-align:right"><?= number_format((float)$a['brutto_vk'], 2, ',', '.') ?> €</td>
                                    <td style="white-space:nowrap"><?= date('d.m.Y', strtotime($a['gueltig_ab'])) ?></td>
                                    <td style="white-space:nowrap"><?= date('d.m.Y', strtotime($a['gueltig_bis'])) ?></td>
                                    <td>
                                        <?php if ($istAktiv): ?>
                                            <span style="background:#dcfce7;color:#166534;font-size:12px;padding:2px 8px;border-radius:10px;font-weight:600">aktiv</span>
                                        <?php elseif (!$a['gestartet']): ?>
                                            <span style="background:#f1f5f9;color:#64748b;font-size:12px;padding:2px 8px;border-radius:10px">Entwurf</span>
                                        <?php else: ?>
                                            <span style="background:#fef9c3;color:#854d0e;font-size:12px;padding:2px 8px;border-radius:10px">
                                                <?= $a['gueltig_ab'] > $heute ? 'geplant' : 'abgelaufen' ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:var(--color-text-muted);font-size:13px">Keine Aktionen für diesen Artikel.</p>
                <?php endif; ?>
                <div style="margin-top:var(--space-sm)">
                    <a href="../aktionen/" class="btn btn-secondary btn-sm">Aktionen verwalten →</a>
                </div>
            </div>
        </div>

        <!-- Kundengruppen-Preise -->
        <div class="card" style="margin-bottom:var(--space-lg)">
            <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer"
                onclick="togglePreisSektion('kg-preise-body', this)">
                <h3 style="margin:0">Kundengruppen-Preise</h3>
                <span id="kg-preise-toggle"><?= $hatKgPreise ? '▲' : '▼' ?></span>
            </div>
            <div id="kg-preise-body" style="margin-top:var(--space-md);<?= $hatKgPreise ? '' : 'display:none' ?>">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th>Kundengruppe</th>
                            <th style="text-align:right">Brutto VK</th>
                            <th style="text-align:right">Netto VK</th>
                            <th style="white-space:nowrap;min-width:90px">Gültig ab</th>
                            <th style="white-space:nowrap;min-width:90px">Gültig bis</th>
                            <th style="width:80px;white-space:nowrap"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kundengruppenPreise as $kp): ?>
                            <tr class="artikel-zeile" data-kg-id="<?= $kp['id'] ?>"
                                data-brutto="<?= htmlspecialchars($kp['brutto_vk'] ?? '') ?>"
                                data-netto="<?= htmlspecialchars($kp['netto_vk'] ?? '') ?>"
                                data-ab="<?= htmlspecialchars($kp['gueltig_ab'] ?? '') ?>"
                                data-bis="<?= htmlspecialchars($kp['gueltig_bis'] ?? '') ?>">
                                <td>
                                    <?php if ($kp['ist_standard']): ?><span style="color:#FFB300" title="Standard-Kundengruppe">★</span> <?php endif; ?>
                                    <?= htmlspecialchars($kp['name']) ?>
                                </td>
                                <td style="text-align:right">
                                    <?= $kp['brutto_vk'] !== null
                                        ? number_format((float)$kp['brutto_vk'], 2, ',', '.') . ' €'
                                        : '<span style="color:var(--color-text-muted)">–</span>' ?>
                                </td>
                                <td style="text-align:right">
                                    <?= $kp['netto_vk'] !== null
                                        ? number_format((float)$kp['netto_vk'], 2, ',', '.') . ' €'
                                        : '<span style="color:var(--color-text-muted)">–</span>' ?>
                                </td>
                                <td style="white-space:nowrap"><?= $kp['gueltig_ab'] ? date('d.m.Y', strtotime($kp['gueltig_ab'])) : '–' ?></td>
                                <td style="white-space:nowrap"><?= $kp['gueltig_bis'] ? date('d.m.Y', strtotime($kp['gueltig_bis'])) : '–' ?></td>
                                <td class="row-aktionen" style="white-space:nowrap">
                                    <?php if ($kp['brutto_vk'] !== null): ?>
                                        <button class="btn btn-sm" onclick="preisModalOeffnen(<?= $kp['id'] ?>);event.stopPropagation()" title="Bearbeiten">✏️</button>
                                        <?php if ($kp['id'] != 1): ?>
                                            <button class="btn btn-sm" style="color:var(--color-danger)" onclick="preisLoeschen(<?= $kp['id'] ?>);event.stopPropagation()" title="Löschen">🗑️</button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-primary" onclick="preisModalOeffnen(<?= $kp['id'] ?>);event.stopPropagation()" title="Preis anlegen">+</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Staffelpreise -->
        <?php $hatStaffelpreise = !empty($staffelpreise); ?>
        <div class="card" style="margin-bottom:var(--space-lg)">
            <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer"
                onclick="togglePreisSektion('staffel-body', this)">
                <h3 style="margin:0">Staffelpreise</h3>
                <span id="staffel-toggle"><?= $hatStaffelpreise ? '▲' : '▼' ?></span>
            </div>
            <div id="staffel-body" style="margin-top:var(--space-md);<?= $hatStaffelpreise ? '' : 'display:none' ?>">
                <?php if ($hatStaffelpreise): ?>
                    <table class="erp-table">
                        <thead>
                            <tr>
                                <th>Kundengruppe</th>
                                <th style="text-align:right">Menge ab</th>
                                <th style="text-align:right">Brutto VK</th>
                                <th style="text-align:right">Netto VK</th>
                                <th style="width:80px;white-space:nowrap"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffelpreise as $sp): ?>
                                <tr class="artikel-zeile" data-sp-id="<?= $sp['id'] ?>"
                                    data-kg-id="<?= $sp['kundengruppen_id'] ?>"
                                    data-menge="<?= htmlspecialchars($sp['menge_ab']) ?>"
                                    data-brutto="<?= htmlspecialchars($sp['brutto_vk']) ?>"
                                    data-netto="<?= htmlspecialchars($sp['netto_vk']) ?>">
                                    <td><?= htmlspecialchars($sp['kundengruppen_name']) ?></td>
                                    <td style="text-align:right"><?= formatBestand($sp['menge_ab']) ?></td>
                                    <td style="text-align:right"><?= number_format((float)$sp['brutto_vk'], 2, ',', '.') ?> €</td>
                                    <td style="text-align:right"><?= number_format((float)$sp['netto_vk'], 4, ',', '.') ?> €</td>
                                    <td class="row-aktionen" style="white-space:nowrap">
                                        <button class="btn btn-sm" onclick="staffelModalOeffnen(<?= $sp['id'] ?>);event.stopPropagation()" title="Bearbeiten">✏️</button>
                                        <button class="btn btn-sm" style="color:var(--color-danger)" onclick="staffelLoeschen(<?= $sp['id'] ?>);event.stopPropagation()" title="Löschen">🗑️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:var(--color-text-muted);font-size:13px">Noch keine Staffelpreise vorhanden.</p>
                <?php endif; ?>
                <div style="margin-top:var(--space-sm)">
                    <button class="btn btn-secondary btn-sm" onclick="staffelModalOeffnen()">+ Staffelpreis hinzufügen</button>
                </div>
            </div>
        </div>

    </div>
    <div id="tab-lager" class="versteckt">

        <?php if (!empty($_GET['we_fehler'])): ?>
            <div style="background:#fee2e2;color:#991b1b;padding:var(--space-sm) var(--space-md);border-radius:6px;margin-bottom:var(--space-md);font-size:13px">
                ⚠ <?= htmlspecialchars($_GET['we_fehler']) ?>
            </div>
        <?php endif; ?>

        <!-- Bestandsübersicht -->
        <div class="card">
            <div class="pagination-bar">
                <div style="font-weight:600">Lagerbestand</div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-secondary btn-sm" disabled title="In Entwicklung">Umlagerung →</button>
                    <button class="btn btn-secondary btn-sm" onclick="weModalOeffnen()">+ Wareneingang buchen</button>
                </div>
            </div>

            <?php if (!empty($lagerGruppen)): ?>
                <div style="font-size:13px;color:var(--color-text-muted);padding:var(--space-xs) 0 var(--space-sm)">
                    Gesamtbestand: <strong style="color:var(--color-text)"><?= formatBestand($lagerGesamtBestand) ?></strong>
                    in <strong style="color:var(--color-text)"><?= $lagerAnzahlLager ?></strong> <?= $lagerAnzahlLager === 1 ? 'Lager' : 'Lagern' ?>
                    <?php if ($lagerAnzahlChargen > 0): ?>
                        &nbsp;·&nbsp; <strong style="color:var(--color-text)"><?= $lagerAnzahlChargen ?></strong> verschiedene <?= $lagerAnzahlChargen === 1 ? 'Charge' : 'Chargen' ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($lagerGruppen)): ?>
                <p style="color:var(--color-text-muted);font-size:13px;padding:var(--space-sm) 0">Kein Lagerbestand vorhanden.</p>
            <?php else: ?>
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th>Lager</th>
                            <th style="text-align:right">Gesamt</th>
                            <th style="text-align:right">Mindestbestand</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lagerGruppen as $lid => $lg): ?>
                            <?php $hatChargen = !empty($lg['chargen']); ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($lg['name']) ?></strong></td>
                                <td style="text-align:right;<?= (float)$lg['gesamt'] <= 0 ? 'color:var(--color-danger)' : ((float)$lg['gesamt'] <= (float)$lg['mindestbestand'] ? 'color:var(--color-warning)' : '') ?>">
                                    <?= formatBestand($lg['gesamt']) ?>
                                </td>
                                <td style="text-align:right"><?= formatBestand($lg['mindestbestand']) ?></td>
                                <td>
                                    <?php if ($hatChargen): ?>
                                        <button class="btn btn-secondary btn-sm" onclick="toggleChargen(this, <?= $lid ?>)" data-count="<?= count($lg['chargen']) ?>">
                                            ▲ Chargen (<?= count($lg['chargen']) ?>)
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($hatChargen): ?>
                                <tr id="chargen-<?= $lid ?>">
                                    <td colspan="4" style="padding:0">
                                        <table class="erp-table" style="margin:0;background:#f8fafc">
                                            <thead>
                                                <tr>
                                                    <th style="padding-left:32px">Charge</th>
                                                    <th>Status</th>
                                                    <th style="text-align:right">Menge</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($lg['chargen'] as $ch): ?>
                                                    <tr>
                                                        <td style="padding-left:32px"><?= htmlspecialchars($ch['charge']) ?></td>
                                                        <td><?= htmlspecialchars($ch['charge_status'] ?? '–') ?></td>
                                                        <td style="text-align:right"><?= formatBestand($ch['bestand']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($zustandsArtikelListe)): ?>
            <!-- Zustandsartikel -->
            <div class="card" style="margin-top:var(--space-md)">
                <div class="pagination-bar">
                    <div style="font-weight:600">
                        B-Ware / Zustandsartikel
                        <span style="margin-left:8px;font-size:12px;font-weight:400;color:var(--color-text-muted)"><?= count($zustandsArtikelListe) ?> Artikel</span>
                    </div>
                    <a href="neu.php" class="btn btn-secondary btn-sm">+ Zustandsartikel anlegen</a>
                </div>
                <table class="erp-table" style="margin-top:var(--space-sm)">
                    <thead>
                        <tr>
                            <th>Artikelnummer</th>
                            <th>Zustand</th>
                            <th style="text-align:right">Bestand</th>
                            <th>Status</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $zustandLabels = [
                            'gebraucht'          => ['GEB', '#dbeafe', '#1e40af'],
                            'generalueberholt'   => ['GUE', '#d1fae5', '#065f46'],
                            'beschaedigt'        => ['BSC', '#fee2e2', '#991b1b'],
                            'retour'             => ['RET', '#fff7ed', '#9a3412'],
                            'demo'               => ['DMO', '#f5f3ff', '#5b21b6'],
                            'muster'             => ['MST', '#fef9c3', '#713f12'],
                            'ausstellungsstueck' => ['AST', '#f0fdf4', '#14532d'],
                        ];
                        foreach ($zustandsArtikelListe as $za):
                            [$zl, $zbg, $zfg] = $zustandLabels[$za['zustand']] ?? [strtoupper($za['zustand']), '#f3f4f6', '#374151'];
                        ?>
                            <tr>
                                <td>
                                    <a href="detail.php?id=<?= $za['id'] ?>"><?= htmlspecialchars($za['artikelnummer']) ?></a>
                                </td>
                                <td>
                                    <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;background:<?= $zbg ?>;color:<?= $zfg ?>">
                                        <?= $zl ?>
                                    </span>
                                </td>
                                <td style="text-align:right;<?= (float)$za['gesamtbestand'] <= 0 ? 'color:var(--color-danger)' : '' ?>">
                                    <?= formatBestand($za['gesamtbestand']) ?>
                                </td>
                                <td>
                                    <?php if (!$za['aktiv']): ?>
                                        <span class="sc sc-deaktiviert">Inaktiv</span>
                                    <?php else: ?>
                                        <span style="font-size:12px;color:var(--color-success)">Aktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="detail.php?id=<?= $za['id'] ?>" class="btn btn-secondary btn-xs">✏️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Bewegungslog -->
        <div class="card" style="margin-top:var(--space-md)">
            <div style="font-weight:600;padding-bottom:var(--space-xs);margin-bottom:var(--space-sm);border-bottom:1px solid var(--color-border)">
                Letzte Lagerbewegungen
            </div>
            <?php if (empty($bewegungslog)): ?>
                <p style="color:var(--color-text-muted);font-size:13px">Noch keine Lagerbewegungen vorhanden.</p>
            <?php else: ?>
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Typ</th>
                            <th style="text-align:right">Menge</th>
                            <th>Vorher → Nachher</th>
                            <th>Charge</th>
                            <th>Lager</th>
                            <th>Referenz / Notiz</th>
                            <th>Benutzer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $typFarben = [
                            'eingang'   => ['#dcfce7', '#166534'],
                            'ausgang'   => ['#fee2e2', '#991b1b'],
                            'korrektur' => ['#fff7ed', '#9a3412'],
                            'inventur'  => ['#eff6ff', '#1e40af'],
                        ];
                        ?>
                        <?php foreach ($bewegungslog as $b): ?>
                            <?php [$bg, $fg] = $typFarben[$b['bewegungstyp']] ?? ['#f1f5f9', '#334155']; ?>
                            <tr>
                                <td style="white-space:nowrap"><?= date('d.m.Y H:i', strtotime($b['erstellt_am'])) ?></td>
                                <td>
                                    <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600">
                                        <?= htmlspecialchars(ucfirst($b['bewegungstyp'])) ?>
                                    </span>
                                </td>
                                <td style="text-align:right"><?= formatBestand($b['menge']) ?></td>
                                <td style="white-space:nowrap"><?= formatBestand($b['bestand_vorher']) ?> → <?= formatBestand($b['bestand_nachher']) ?></td>
                                <td><?= htmlspecialchars($b['charge'] ?? '–') ?></td>
                                <td><?= htmlspecialchars($b['lager_name']) ?></td>
                                <td>
                                    <?php if (!empty($b['referenz'])): ?>
                                        <span style="font-weight:600"><?= htmlspecialchars($b['referenz']) ?></span><?= !empty($b['notiz']) ? ' · ' : '' ?>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($b['notiz'] ?? (!empty($b['referenz']) ? '' : '–')) ?>
                                </td>
                                <td><?= htmlspecialchars($b['formularname'] ?? '–') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
    <div id="tab-bilder" class="versteckt">
        <div class="card">Bilder Platzhalter</div>
    </div>
    <div id="tab-merkmale" class="versteckt">
        <?php if (empty($merkmaleFuerTyp)): ?>
            <div class="card" style="color:var(--color-text-muted);font-size:13px">
                Für diesen Artikeltyp sind keine Merkmale konfiguriert.
                <a href="/mealana/artikel/merkmale_verwalten.php">Merkmale verwalten →</a>
            </div>
        <?php else: ?>
        <div class="card">
            <div class="form-section-header" style="margin-bottom:var(--space-sm)">Merkmale</div>

            <?php foreach ($merkmaleFuerTyp as $m): ?>
            <?php
                $gesetzteWerte = array_filter($artikelMerkmale, fn($am) => $am['merkmal_id'] == $m['id']);
                $gesetzteWerteIds = array_column(array_values($gesetzteWerte), 'merkmal_wert_id');
            ?>
            <div class="form-row" style="align-items:flex-start;margin-bottom:var(--space-sm)">
                <label class="form-label" style="min-width:160px;padding-top:4px">
                    <?= htmlspecialchars($m['name']) ?>
                    <?php if ($m['mehrfach_auswahl']): ?>
                        <span style="font-size:10px;color:var(--color-text-muted)">(mehrere)</span>
                    <?php endif; ?>
                </label>
                <div style="flex:1">
                    <!-- Chip-Anzeige der gewählten Werte -->
                    <div id="merk-chips-<?= $m['id'] ?>" style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:4px">
                        <?php foreach ($gesetzteWerteIds as $wid): ?>
                            <?php $w = array_filter($m['werte'], fn($v) => $v['id'] == $wid); $w = reset($w); ?>
                            <?php if ($w): ?>
                                <span class="chip chip-aktiv" style="font-size:12px"><?= htmlspecialchars($w['wert']) ?></span>
                                <input type="hidden" name="merk[<?= $m['id'] ?>][]" value="<?= $wid ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($gesetzteWerteIds)): ?>
                            <span style="font-size:12px;color:var(--color-text-muted)">–</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($m['werte'])): ?>
                    <button type="button" class="btn btn-secondary btn-sm"
                            onclick="merkmalWaehlen(<?= $m['id'] ?>, <?= (int)$m['mehrfach_auswahl'] ?>, <?= htmlspecialchars(json_encode($m['werte'])) ?>, <?= htmlspecialchars(json_encode($gesetzteWerteIds)) ?>)">
                        Wählen
                    </button>
                    <?php else: ?>
                    <a href="/mealana/artikel/merkmale_verwalten.php" target="_blank" class="btn btn-secondary btn-sm" style="font-size:11px">Werte konfigurieren →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:var(--space-md)">
                <button type="button" class="btn btn-primary btn-sm" onclick="merkmaleSpeichern(<?= $id ?>)">💾 Merkmale speichern</button>
            </div>
        </div>
        <?php endif; ?>
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
                            <th>EK (brutto)</th>
                            <th>Whg</th>
                            <th>VPE</th>
                            <th>VPE-EAN</th>
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
                                data-brutto-ek="<?= $l['brutto_ek'] ?? '' ?>"
                                data-waehrung="<?= htmlspecialchars($l['waehrung']) ?>"
                                data-vpe="<?= $l['vpe_menge'] ?? '' ?>"
                                data-vpe-ean="<?= htmlspecialchars($l['vpe_ean'] ?? '') ?>"
                                data-lz="<?= $l['lieferzeit_tage'] ?? '' ?>"
                                data-mba="<?= $l['mindestabnahme'] ?? '' ?>"
                                data-standard="<?= $l['standard_lieferant'] ? '1' : '' ?>"
                                <?= !$l['aktiv'] ? 'class="row-inaktiv"' : '' ?>>
                                <td><?= htmlspecialchars($l['lieferant_name']) ?></td>
                                <td><?= htmlspecialchars($l['artikelnummer_lieferant'] ?? '–') ?></td>
                                <td><?= $l['netto_ek'] ? number_format($l['netto_ek'], 2, ',', '.') . ' €' : '–' ?></td>
                                <td><?= ($l['brutto_ek'] ?? null) ? number_format($l['brutto_ek'], 2, ',', '.') . ' €' : '–' ?></td>
                                <td><?= htmlspecialchars($l['waehrung']) ?></td>
                                <td><?= $l['vpe_menge'] ?? '-' ?></td>
                                <td><?= htmlspecialchars($l['vpe_ean'] ?? '-') ?></td>
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
    <div id="tab-seo" class="versteckt">
        <div class="card">
            <div class="form-section-header">SEO & URLs</div>
            <div>
                <form id="seo-form" action="seo_speichern.php" method="POST">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-group">
                        <label class="form-label" for="url_slug">URL Slug</label>
                        <input class="erp-input" style="width:100%" type="text" name="url_slug" placeholder="z.B. rote-wolle-100g" value="<?= htmlspecialchars($artikel['url_slug'] ?? '') ?>">
                        <span style="font-size:12px;color:var(--color-text-muted)">Leer lassen → wird beim Shop-Export automatisch aus dem Artikelname generiert</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="meta_titel">Meta-Titel</label>
                        <input class="erp-input" style="width:100%" type="text" name="meta_titel" maxlength="60" placeholder="max. 60 Zeichen" value="<?= htmlspecialchars($artikel['meta_titel']       ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="meta_description">Meta-Beschreibung</label>
                        <textarea class="erp-textarea" rows="4" cols="50" name="meta_description" maxlength="160" placeholder="max. 160 Zeichen"><?= htmlspecialchars($artikel['meta_description'] ?? '') ?></textarea>
                    </div>

                    <button class="btn btn-secondary btn-sm" type="submit">Speichern</button>
                </form>
            </div>
        </div>
    </div><!-- /tab-seo -->

    <?php
    function renderKatBaumModal(array $nodes, int $tiefe = 0): string
        {
            $html = '';
            $last = count($nodes) - 1;
            foreach ($nodes as $idx => $node) {
                $isLast   = ($idx === $last);
                $pl       = $tiefe * 20;
                $linie    = $tiefe > 0
                    ? '<span class="kat-linie">' . ($isLast ? '└─' : '├─') . '</span>'
                    : '';
                $labelCls = $tiefe === 0 ? 'kat-label kat-wurzel' : 'kat-label';
                $count    = $node['artikel_anzahl'] > 0
                    ? ' <span class="kat-count">' . (int)$node['artikel_anzahl'] . '</span>'
                    : '';
                $html .= '<label class="kat-zeile" data-tiefe="' . $tiefe . '" style="padding-left:' . $pl . 'px">'
                    . $linie
                    . '<input type="checkbox" value="' . (int)$node['id'] . '"'
                    . ' data-name="' . htmlspecialchars($node['name']) . '"'
                    . ' data-parent-id="' . (int)($node['parent_id'] ?? 0) . '">'
                    . '<span class="' . $labelCls . '">' . htmlspecialchars($node['name']) . '</span>'
                    . $count
                    . '</label>';
                if (!empty($node['kinder'])) {
                    $html .= renderKatBaumModal($node['kinder'], $tiefe + 1);
                }
            }
            return $html;
        }
        ?>
        <div id="kat-backdrop" class="modal-backdrop" onclick="katModalSchliessen()">
            <div id="kat-modal" class="modal" onclick="event.stopPropagation()">
                <div class="modal-header">Kategorien zuweisen</div>

                <div id="kat-checkboxen">
                    <?= renderKatBaumModal($kategorienBaum) ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:var(--space-sm) 0">

                <div id="kat-neu">
                    <div style="font-size:12px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Neue Kategorie anlegen</div>
                    <div style="display:flex;gap:var(--space-sm);align-items:center">
                        <select id="neue-kat-parent" class="erp-select" style="width:160px">
                            <option value="">– Obergruppe (Root) –</option>
                            <?php foreach ($alleKategorien as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="neue-kat-name" class="erp-input" placeholder="Name..." style="flex:1">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="katAnlegen()">Anlegen</button>
                    </div>
                </div>

                <div id="kat-aktionen" style="margin-top:var(--space-sm);display:flex;gap:var(--space-sm);justify-content:flex-end">
                    <button type="button" class="btn btn-secondary" onclick="katModalSchliessen()">Abbrechen</button>
                    <button type="button" class="btn btn-primary" onclick="katUebernehmen()">Übernehmen</button>
                </div>
            </div>
        </div>

        <div id="we-backdrop" class="modal-backdrop" onclick="weModalSchliessen()">
            <div id="we-modal" class="modal" onclick="event.stopPropagation()">
                <div style="font-size:15px;font-weight:600;padding-bottom:var(--space-sm);border-bottom:1px solid var(--color-border);margin-bottom:var(--space-xs)">
                    Wareneingang buchen
                </div>
                <form method="POST" action="lager_schnell_we.php" style="display:flex;flex-direction:column;gap:var(--space-sm)">
                    <input type="hidden" name="artikel_id" value="<?= $id ?>">
                    <div class="form-row">
                        <label class="form-label">Lager *</label>
                        <select name="lager_id" class="erp-select" style="width:100%" required>
                            <option value="">– Lager auswählen –</option>
                            <?php foreach ($alleLager as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Menge *</label>
                        <input type="number" name="menge" class="erp-input" style="width:100%"
                            step="<?= $artikel['artikeltyp_teilbar'] ? '0.001' : '1' ?>"
                            min="<?= $artikel['artikeltyp_teilbar'] ? '0.001' : '1' ?>" required>
                    </div>
                    <?php if ($artikel['charge_pflicht']): ?>
                        <div class="form-row">
                            <label class="form-label">Charge *</label>
                            <input type="text" name="charge" class="erp-input" style="width:100%" required>
                        </div>
                    <?php else: ?>
                        <div class="form-row">
                            <label class="form-label">Charge</label>
                            <input type="text" name="charge" class="erp-input" style="width:100%" placeholder="optional">
                        </div>
                    <?php endif; ?>
                    <div class="form-row">
                        <label class="form-label">Notiz</label>
                        <input type="text" name="notiz" class="erp-input" style="width:100%" placeholder="optional">
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:var(--space-sm);padding-top:var(--space-sm)">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="weModalSchliessen()">Abbrechen</button>
                        <button type="submit" class="btn btn-primary btn-sm">Buchen</button>
                    </div>
                </form>
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
                        <input class="erp-input" style="width:100%" type="number" step="0.0001" name="netto_ek" id="lief-ek" value=""
                               oninput="liefCalcBrutto()">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Brutto-EK</label>
                        <input class="erp-input" style="width:100%" type="number" step="0.0001" name="brutto_ek" id="lief-brutto-ek" value=""
                               oninput="liefCalcNetto()">
                        <span style="font-size:11px;color:var(--color-text-muted);margin-top:2px">bei <?= (float)($artikel['steuersatz'] ?? 20) ?>% MwSt.</span>
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
                        <label class="form-label">VPE-EAN</label>
                        <input class="erp-input" style="width:100%" type="text" name="vpe_ean" id="lief-vpe-ean"
                            maxlength="13" placeholder="z.B. 7071723011379" value="">
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

        <div id="staffel-backdrop" class="modal-backdrop" onclick="staffelModalSchliessen()">
            <div id="staffel-modal" class="modal" onclick="event.stopPropagation()">
                <div style="font-size:15px;font-weight:600;padding-bottom:var(--space-sm);border-bottom:1px solid var(--color-border);margin-bottom:var(--space-md)">
                    <span id="staffel-titel">Staffelpreis hinzufügen</span>
                </div>
                <form id="staffel-form" style="display:flex;flex-direction:column;gap:var(--space-sm)">
                    <input type="hidden" name="artikel_id" value="<?= $id ?>">
                    <input type="hidden" name="id" id="staffel-id" value="">
                    <div class="form-row">
                        <label class="form-label">Kundengruppe</label>
                        <select class="erp-select" style="width:100%" name="kundengruppen_id" id="staffel-kg">
                            <option value="">– bitte wählen –</option>
                            <?php foreach ($kundengruppenPreise as $kp): ?>
                                <option value="<?= $kp['id'] ?>"><?= htmlspecialchars($kp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Menge ab</label>
                        <input class="erp-input" style="width:100%" type="number" step="1" min="1"
                            name="menge_ab" id="staffel-menge" placeholder="z.B. 10">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Brutto VK (€)</label>
                        <input class="erp-input" style="width:100%" type="number" step="0.01" min="0"
                            name="brutto_vk" id="staffel-brutto" placeholder="0,00" oninput="staffelNettoBerechnen()">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Netto VK (€) <span style="font-size:11px;color:var(--color-text-muted)">(auto)</span></label>
                        <input class="erp-input" style="width:100%" type="number" step="0.0001" min="0"
                            name="netto_vk" id="staffel-netto" placeholder="0,0000">
                    </div>
                    <div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-sm)">
                        <button type="button" class="btn btn-primary btn-sm" onclick="staffelSpeichern()">Speichern</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="staffelModalSchliessen()">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="preis-backdrop" class="modal-backdrop" onclick="preisModalSchliessen()">
            <div id="preis-modal" class="modal" onclick="event.stopPropagation()">
                <div style="font-size:15px;font-weight:600;padding-bottom:var(--space-sm);border-bottom:1px solid var(--color-border);margin-bottom:var(--space-md)">
                    Preis — <span id="preis-kg-name"></span>
                </div>
                <form id="preis-form" style="display:flex;flex-direction:column;gap:var(--space-sm)">
                    <input type="hidden" name="artikel_id" value="<?= $id ?>">
                    <input type="hidden" name="kundengruppen_id" id="preis-kg-id" value="">
                    <div class="form-row">
                        <label class="form-label">Brutto VK (€)</label>
                        <input class="erp-input" style="width:100%" type="number" step="0.01" min="0"
                            name="brutto_vk" id="preis-brutto" placeholder="0,00" oninput="preisNettoBerechnen()">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Netto VK (€) <span style="font-size:11px;color:var(--color-text-muted)">(auto)</span></label>
                        <input class="erp-input" style="width:100%" type="number" step="0.0001" min="0"
                            name="netto_vk" id="preis-netto" placeholder="0,0000">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Gültig ab</label>
                        <input class="erp-input" style="width:100%" type="date" name="gueltig_ab" id="preis-ab">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Gültig bis</label>
                        <input class="erp-input" style="width:100%" type="date" name="gueltig_bis" id="preis-bis">
                    </div>
                    <div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-sm)">
                        <button type="button" class="btn btn-primary btn-sm" onclick="preisSpeichern()">Speichern</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="preisModalSchliessen()">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SALE-Override-Modal -->
        <div id="sale-backdrop" class="modal-backdrop" style="display:none" onclick="saleModalSchliessen()">
            <div id="sale-modal" class="modal" onclick="event.stopPropagation()">
                <div style="font-size:15px;font-weight:600;padding-bottom:var(--space-sm);border-bottom:1px solid var(--color-border);margin-bottom:var(--space-md)">
                    SALE-Override
                </div>
                <form id="sale-form" style="display:flex;flex-direction:column;gap:var(--space-sm)">
                    <input type="hidden" name="id" id="sale-id" value="">
                    <input type="hidden" name="artikel_id" value="<?= $id ?>">
                    <div class="form-row">
                        <label class="form-label">Kundengruppe <span style="font-size:11px;color:var(--color-text-muted)">(leer = alle)</span></label>
                        <select class="erp-select" style="width:100%" name="kundengruppen_id" id="sale-kg">
                            <option value="">– Alle Kundengruppen –</option>
                            <?php foreach ($kundengruppenPreise as $kp): ?>
                                <option value="<?= $kp['id'] ?>"><?= htmlspecialchars($kp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Sale-Preis Brutto (€)</label>
                        <input class="erp-input" style="width:100%" type="number" step="0.01" min="0"
                            name="brutto_vk" id="sale-brutto" placeholder="0,00" oninput="saleNettoBerechnen()">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Sale-Preis Netto (€) <span style="font-size:11px;color:var(--color-text-muted)">(auto)</span></label>
                        <input class="erp-input" style="width:100%" type="number" step="0.0001" min="0"
                            name="netto_vk" id="sale-netto" placeholder="0,0000">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Preis vorher Brutto (€) <span style="font-size:11px;color:var(--color-text-muted)">(optional, für Streichpreis)</span></label>
                        <input class="erp-input" style="width:100%" type="number" step="0.01" min="0"
                            name="preis_vorher_brutto" id="sale-vorher" placeholder="0,00">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Gültig ab</label>
                        <input class="erp-input" style="width:100%" type="datetime-local" name="gueltig_ab" id="sale-ab">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Gültig bis</label>
                        <input class="erp-input" style="width:100%" type="datetime-local" name="gueltig_bis" id="sale-bis">
                    </div>
                    <div style="display:flex;align-items:center;gap:var(--space-sm)">
                        <input type="checkbox" name="bis_lagerstand_null" id="sale-lagerstand" value="1">
                        <label for="sale-lagerstand" style="cursor:pointer">Endet wenn Lagerstand = 0</label>
                    </div>
                    <div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-sm)">
                        <button type="button" class="btn btn-primary btn-sm" onclick="saleSpeichern()">Speichern</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="saleModalSchliessen()">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Merkmal-Auswahl-Modal -->
        <div id="merk-backdrop" class="modal-backdrop" style="display:none" onclick="merkmalModalSchliessen()">
            <div class="modal" style="max-width:420px;max-height:70vh;display:flex;flex-direction:column" onclick="event.stopPropagation()">
                <div class="modal-header" id="merk-modal-titel">Merkmal wählen</div>
                <div id="merk-modal-liste" style="overflow-y:auto;flex:1;padding:var(--space-sm)"></div>
                <div style="display:flex;gap:var(--space-sm);justify-content:flex-end;padding:var(--space-sm);border-top:1px solid var(--color-border)">
                    <button class="btn btn-secondary" onclick="merkmalModalSchliessen()">Abbrechen</button>
                    <button class="btn btn-primary" onclick="merkmalUebernehmen()">Übernehmen</button>
                </div>
            </div>
        </div>

        <script>
            function showFlash(text, typ) {
                const el = document.getElementById('ajax-flash');
                el.className = typ === 'fehler' ? 'error-banner' : 'success-banner';
                el.textContent = (typ === 'fehler' ? '✗ ' : '✓ ') + text;
                el.style.display = 'block';
                clearTimeout(el._t);
                el._t = setTimeout(function() { el.style.display = 'none'; }, 4000);
            }

            const TAB_KEY = 'artikel_tab_<?= $id ?>';

            function zeigeTab(name, el) {
                document.querySelectorAll('[id^="tab-"]').forEach(d => d.classList.add('versteckt'));
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.getElementById('tab-' + name).classList.remove('versteckt');
                el.classList.add('active');
                localStorage.setItem(TAB_KEY, name);
            }

            // Tab wiederherstellen: URL-Parameter hat Vorrang, dann localStorage
            (function() {
                const urlParams = new URLSearchParams(location.search);
                const urlTab    = urlParams.get('tab');
                const saved     = urlTab || localStorage.getItem(TAB_KEY);
                if (saved) {
                    const el = document.querySelector(`.tab[onclick*="'${saved}'"]`);
                    if (el) zeigeTab(saved, el);
                }
                // URL-Parameter entfernen damit er nicht im Browser kleben bleibt
                if (urlTab) {
                    urlParams.delete('tab');
                    const clean = location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', clean);
                }
            })();

            function weModalOeffnen() {
                document.getElementById('we-backdrop').style.display = 'flex';
            }

            function weModalSchliessen() {
                document.getElementById('we-backdrop').style.display = 'none';
            }

            function uvpBearbeiten() {
                document.getElementById('uvp-anzeige').style.display = 'none';
                document.querySelector('[onclick="uvpBearbeiten()"]').style.display = 'none';
                document.getElementById('uvp-edit').style.display = 'flex';
                document.getElementById('uvp-input').focus();
            }

            function uvpAbbrechen() {
                document.getElementById('uvp-edit').style.display = 'none';
                document.getElementById('uvp-anzeige').style.display = '';
                document.querySelector('[onclick="uvpBearbeiten()"]').style.display = '';
            }

            function uvpSpeichern() {
                const wert = document.getElementById('uvp-input').value;
                const data = new FormData();
                data.append('artikel_id', <?= $id ?>);
                data.append('uvp', wert);
                fetch('uvp_speichern.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (json.erfolg) {
                            location.href = 'detail.php?id=<?= $id ?>&tab=preise';
                        } else {
                            showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
                        }
                    });
            }

            function togglePreisSektion(bodyId, header) {
                const body = document.getElementById(bodyId);
                const toggle = header.querySelector('span');
                const offen = body.style.display !== 'none';
                body.style.display = offen ? 'none' : '';
                toggle.textContent = offen ? '▼' : '▲';
            }

            const MWST_SATZ = <?= (float)($artikel['steuersatz'] ?? 20) ?>;

            function preisModalOeffnen(kgId) {
                const row = document.querySelector(`tr[data-kg-id="${kgId}"]`);
                document.getElementById('preis-kg-id').value = kgId;
                document.getElementById('preis-kg-name').textContent = row.querySelector('td').textContent.trim();
                document.getElementById('preis-brutto').value = row.dataset.brutto || '';
                document.getElementById('preis-netto').value = row.dataset.netto || '';
                document.getElementById('preis-ab').value = row.dataset.ab ? row.dataset.ab.substring(0, 10) : '';
                document.getElementById('preis-bis').value = row.dataset.bis ? row.dataset.bis.substring(0, 10) : '';
                document.getElementById('preis-backdrop').style.display = 'flex';
            }

            function preisModalSchliessen() {
                document.getElementById('preis-backdrop').style.display = 'none';
            }

            function preisNettoBerechnen() {
                const brutto = parseFloat(document.getElementById('preis-brutto').value);
                if (!isNaN(brutto) && brutto > 0) {
                    const netto = brutto / (1 + MWST_SATZ / 100);
                    document.getElementById('preis-netto').value = netto.toFixed(4);
                }
            }

            function preisLoeschen(kgId) {
                if (!confirm('Preis für diese Kundengruppe wirklich löschen?')) return;
                const data = new FormData();
                data.append('artikel_id', <?= $id ?>);
                data.append('kundengruppen_id', kgId);
                fetch('preis_loeschen.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (json.erfolg) {
                            location.href = 'detail.php?id=<?= $id ?>&tab=preise';
                        } else {
                            showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
                        }
                    });
            }

            function preisSpeichern() {
                const form = document.getElementById('preis-form');
                const data = new FormData(form);
                fetch('preis_speichern.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (json.erfolg) {
                            preisModalSchliessen();
                            location.href = 'detail.php?id=<?= $id ?>&tab=preise';
                        } else {
                            showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
                        }
                    });
            }

            function toggleChargen(btn, lagerId) {
                const row = document.getElementById('chargen-' + lagerId);
                const offen = btn.textContent.includes('▲');
                row.style.display = offen ? 'none' : '';
                btn.textContent = (offen ? '▼' : '▲') + ' Chargen (' + btn.dataset.count + ')';
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
                    span.className = 'chip chip-aktiv';
                    span.textContent = cb.dataset.name;
                    chips.appendChild(span);
                });
                katModalSchliessen();
            }

            async function katAnlegen() {
                const katName = document.getElementById('neue-kat-name').value?.trim();
                const parentId = document.getElementById('neue-kat-parent').value || '';
                if (!katName) return;

                const body = 'name=' + encodeURIComponent(katName) +
                    (parentId ? '&parent_id=' + encodeURIComponent(parentId) : '');

                const response = await fetch('kategorie_neu.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body
                });
                const data = await response.json();
                if (!data.erfolg) {
                    showFlash(data.fehler || 'Fehler', 'fehler');
                    return;
                }

                // Neuen Eintrag im Baum hinzufügen
                const tiefe = parentId ? 1 : 0;
                const pl = tiefe * 20;
                const linie = tiefe > 0 ? '<span class="kat-linie">└─</span>' : '';
                const label = document.createElement('label');
                label.className = 'kat-zeile';
                label.dataset.tiefe = tiefe;
                label.style.paddingLeft = pl + 'px';
                label.innerHTML = linie +
                    '<input type="checkbox" value="' + data.id + '"' +
                    ' data-name="' + data.name.replace(/"/g, '&quot;') + '"' +
                    ' data-parent-id="' + (parentId || 0) + '" checked>' +
                    '<span class="kat-label' + (tiefe === 0 ? ' kat-wurzel' : '') + '">' + data.name + '</span>';
                document.getElementById('kat-checkboxen').appendChild(label);

                // Parent-Dropdown ergänzen
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name;
                document.getElementById('neue-kat-parent').appendChild(opt);

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
                    document.getElementById('lief-brutto-ek').value = tr.dataset.bruttoEk;
                    document.getElementById('lief-waehrung').value = tr.dataset.waehrung;
                    document.getElementById('lief-vpe').value = tr.dataset.vpe;
                    document.getElementById('lief-vpe-ean').value = tr.dataset.vpeEan;
                    document.getElementById('lief-lz').value = tr.dataset.lz;
                    document.getElementById('lief-mba').value = tr.dataset.mba;
                    document.getElementById('lief-standard').checked = tr.dataset.standard;
                    // ... usw für alle Felder

                } else {
                    // Neu: alle Felder leeren
                    document.getElementById('lief-lieferant-id').value = '';
                    document.getElementById('lief-artnr').value = '';
                    document.getElementById('lief-ek').value = '';
                    document.getElementById('lief-brutto-ek').value = '';
                    document.getElementById('lief-waehrung').value = '';
                    document.getElementById('lief-vpe').value = '';
                    document.getElementById('lief-vpe-ean').value = '';
                    document.getElementById('lief-lz').value = '';
                    document.getElementById('lief-mba').value = '';
                    document.getElementById('lief-standard').checked = '';
                    // ... usw
                }

                document.getElementById('lief-backdrop').style.display = 'flex';
            }

            var LIEF_MWST = <?= (float)($artikel['steuersatz'] ?? 20) ?>;

            function liefCalcBrutto() {
                var netto = parseFloat(document.getElementById('lief-ek').value);
                if (!isNaN(netto) && netto > 0) {
                    document.getElementById('lief-brutto-ek').value = (netto * (1 + LIEF_MWST / 100)).toFixed(4);
                } else {
                    document.getElementById('lief-brutto-ek').value = '';
                }
            }

            function liefCalcNetto() {
                var brutto = parseFloat(document.getElementById('lief-brutto-ek').value);
                if (!isNaN(brutto) && brutto > 0) {
                    document.getElementById('lief-ek').value = (brutto / (1 + LIEF_MWST / 100)).toFixed(4);
                } else {
                    document.getElementById('lief-ek').value = '';
                }
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
                    showFlash(data.fehler || 'Fehler', 'fehler');
                    return;
                }

                liefModalSchliessen();
                location.reload();
            }

            // Generator: Button-Count live aktualisieren
            document.querySelectorAll('#generator-form input[type=checkbox][name*="selected"]').forEach(cb => {
                cb.addEventListener('change', () => {
                    const checked = document.querySelectorAll('#generator-form input[type=checkbox][name*="selected"]:checked').length;
                    const btn = document.getElementById('gen-submit-btn');
                    if (btn) btn.textContent = '▶ Ausgewählte generieren (' + checked + ')';
                });
            });

            function staffelModalOeffnen(spId = null) {
                document.getElementById('staffel-id').value = spId ?? '';
                if (spId) {
                    const row = document.querySelector(`tr[data-sp-id="${spId}"]`);
                    document.getElementById('staffel-titel').textContent = 'Staffelpreis bearbeiten';
                    document.getElementById('staffel-kg').value = row.dataset.kgId;
                    document.getElementById('staffel-menge').value = row.dataset.menge;
                    document.getElementById('staffel-brutto').value = row.dataset.brutto;
                    document.getElementById('staffel-netto').value = row.dataset.netto;
                } else {
                    document.getElementById('staffel-titel').textContent = 'Staffelpreis hinzufügen';
                    document.getElementById('staffel-kg').value = '';
                    document.getElementById('staffel-menge').value = '';
                    document.getElementById('staffel-brutto').value = '';
                    document.getElementById('staffel-netto').value = '';
                }
                document.getElementById('staffel-backdrop').style.display = 'flex';
            }

            function staffelModalSchliessen() {
                document.getElementById('staffel-backdrop').style.display = 'none';
            }

            function staffelNettoBerechnen() {
                const brutto = parseFloat(document.getElementById('staffel-brutto').value);
                if (!isNaN(brutto) && brutto > 0) {
                    document.getElementById('staffel-netto').value = (brutto / (1 + MWST_SATZ / 100)).toFixed(4);
                }
            }

            function staffelSpeichern() {
                const data = new FormData(document.getElementById('staffel-form'));
                fetch('staffelpreis_speichern.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (json.erfolg) {
                            staffelModalSchliessen();
                            location.href = 'detail.php?id=<?= $id ?>&tab=preise';
                        } else {
                            showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
                        }
                    });
            }

            function staffelLoeschen(spId) {
                if (!confirm('Staffelpreis wirklich löschen?')) return;
                const data = new FormData();
                data.append('id', spId);
                data.append('artikel_id', <?= $id ?>);
                fetch('staffelpreis_loeschen.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (json.erfolg) {
                            location.href = 'detail.php?id=<?= $id ?>&tab=preise';
                        } else {
                            showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
                        }
                    });
            }

            function saleModalOeffnen(sale) {
                document.getElementById('sale-id').value         = sale ? sale.id : '';
                document.getElementById('sale-kg').value         = sale ? (sale.kundengruppen_id ?? '') : '';
                document.getElementById('sale-brutto').value     = sale ? sale.brutto_vk : '';
                document.getElementById('sale-netto').value      = sale ? sale.netto_vk : '';
                document.getElementById('sale-vorher').value     = sale ? (sale.preis_vorher_brutto ?? '') : '';
                document.getElementById('sale-ab').value         = sale && sale.gueltig_ab
                    ? sale.gueltig_ab.substring(0, 16) : '';
                document.getElementById('sale-bis').value        = sale && sale.gueltig_bis
                    ? sale.gueltig_bis.substring(0, 16) : '';
                document.getElementById('sale-lagerstand').checked = sale ? !!parseInt(sale.bis_lagerstand_null) : false;
                document.getElementById('sale-backdrop').style.display = 'flex';
            }

            function saleModalSchliessen() {
                document.getElementById('sale-backdrop').style.display = 'none';
            }

            function saleNettoBerechnen() {
                const brutto = parseFloat(document.getElementById('sale-brutto').value);
                if (!isNaN(brutto) && brutto > 0) {
                    document.getElementById('sale-netto').value = (brutto / (1 + MWST_SATZ / 100)).toFixed(4);
                }
            }

            function saleSpeichern() {
                const data = new FormData(document.getElementById('sale-form'));
                if (!document.getElementById('sale-lagerstand').checked) {
                    data.delete('bis_lagerstand_null');
                }
                fetch('sale_override_speichern.php', { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(json => {
                        if (json.erfolg) {
                            saleModalSchliessen();
                            location.href = 'detail.php?id=<?= $id ?>&tab=preise';
                        } else {
                            showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
                        }
                    });
            }

            function saleLoeschen(saleId) {
                if (!confirm('SALE-Override wirklich löschen?')) return;
                const data = new FormData();
                data.append('id', saleId);
                data.append('artikel_id', <?= $id ?>);
                fetch('sale_override_loeschen.php', { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(json => {
                        if (json.erfolg) {
                            location.href = 'detail.php?id=<?= $id ?>&tab=preise';
                        } else {
                            showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
                        }
                    });
            }

            function varPanel(name) {
                document.getElementById('var-panel-gen').classList.toggle('versteckt', name !== 'gen');
                document.getElementById('var-panel-kinder').classList.toggle('versteckt', name !== 'kinder');
                document.getElementById('var-btn-gen').className = 'btn btn-sm ' + (name === 'gen' ? 'btn-primary' : 'btn-secondary');
                document.getElementById('var-btn-kinder').className = 'btn btn-sm ' + (name === 'kinder' ? 'btn-primary' : 'btn-secondary');
            }
        </script>

        <?php if (!$istKind): ?>
            <!-- ── Achsen-Modal ────────────────────────────────────────────────── -->
            <div id="achsen-backdrop" class="modal-backdrop" onclick="achsenModalSchliessen()">
                <div class="modal" style="max-width:520px;max-height:80vh;display:flex;flex-direction:column" onclick="event.stopPropagation()">
                    <div class="modal-header" style="flex-shrink:0">
                        Achsen &amp; Variantenwerte
                        <button onclick="achsenModalSchliessen()" class="modal-close">✕</button>
                    </div>
                    <div style="overflow-y:auto;flex:1;padding:var(--space-md)">
                        <?php if (empty($alleGlobalenAchsen)): ?>
                            <p style="color:var(--color-text-muted);font-size:13px">
                                Keine Achsen im System — erst
                                <a href="/mealana/achsen/liste.php" target="_blank">Achsen anlegen ↗</a>
                            </p>
                        <?php else: ?>
                            <?php foreach ($alleGlobalenAchsen as $ga):
                                $istChecked      = in_array($ga['id'], $zugewieseneAchsenIds);
                                $werteVorhanden  = $werteProAchse[$ga['id']] ?? [];
                                $achseGesperrt   = !empty(array_filter(
                                    $werteVorhanden,
                                    fn($w) => isset($wertIdsInUseSet[$w['id']])
                                ));
                            ?>
                                <div style="margin-bottom:var(--space-md);padding-bottom:var(--space-sm);border-bottom:1px solid var(--color-border)">
                                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;font-size:13px">
                                        <input type="checkbox" class="achse-checkbox"
                                            data-achse-id="<?= $ga['id'] ?>"
                                            <?= $istChecked ? 'checked' : '' ?>
                                            <?= $achseGesperrt ? 'data-has-locked="1"' : '' ?>
                                            onchange="achseToggle(this)">
                                        <?= htmlspecialchars($ga['name']) ?>
                                        <span style="font-size:11px;color:var(--color-text-muted);font-weight:400"><?= htmlspecialchars($ga['darstellungsform']) ?></span>
                                        <?php if ($achseGesperrt): ?>
                                            <span style="font-size:11px;color:var(--color-text-muted)">— hat Kind-Artikel</span>
                                        <?php endif; ?>
                                    </label>
                                    <div id="achse-werte-<?= $ga['id'] ?>" style="margin-top:8px;margin-left:22px;<?= !$istChecked ? 'display:none' : '' ?>">
                                        <div id="achse-chips-<?= $ga['id'] ?>" style="margin-bottom:6px">
                                            <?php foreach ($werteVorhanden as $w):
                                                $wertGesperrt = isset($wertIdsInUseSet[$w['id']]);
                                            ?>
                                                <div class="achse-wert-zeile" style="display:flex;align-items:center;justify-content:space-between;padding:4px 6px;background:#F7FAFC;border:1px solid #E2E8F0;border-radius:4px;margin-bottom:3px">
                                                    <span class="achse-chip-text" style="font-size:13px"><?= htmlspecialchars($w['wert']) ?></span>
                                                    <div style="display:flex;gap:2px;flex-shrink:0;align-items:center">
                                                        <button type="button" onclick="achseWertHoch(this)" class="btn btn-secondary btn-xs" title="Nach oben">▲</button>
                                                        <button type="button" onclick="achseWertRunter(this)" class="btn btn-secondary btn-xs" title="Nach unten">▼</button>
                                                        <?php if ($wertGesperrt): ?>
                                                            <span title="Wird von einem Kind-Artikel verwendet — kann nicht entfernt werden"
                                                                style="font-size:12px;color:var(--color-text-muted);padding:0 4px;cursor:default">🔒</span>
                                                        <?php else: ?>
                                                            <button type="button" onclick="achseZeileEntfernen(this)" class="btn btn-danger btn-xs" title="Entfernen">✕</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div style="display:flex;gap:4px">
                                            <input type="text" class="erp-input achse-wert-input"
                                                data-achse-id="<?= $ga['id'] ?>"
                                                placeholder="Wert eingeben + Enter"
                                                style="font-size:12px;padding:4px 8px;flex:1"
                                                onkeydown="if(event.key==='Enter'||event.key==='Tab'){event.preventDefault();achseWertHinzufuegen(<?= $ga['id'] ?>)}">
                                            <button type="button" class="btn btn-secondary btn-xs"
                                                onclick="achseWertHinzufuegen(<?= $ga['id'] ?>)">+</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div style="flex-shrink:0;padding:var(--space-sm) var(--space-md);border-top:1px solid var(--color-border);display:flex;gap:var(--space-sm);justify-content:flex-end">
                        <button onclick="achsenModalSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
                        <button id="achsen-speichern-btn" onclick="achsenSpeichern()" class="btn btn-primary btn-sm">Speichern</button>
                    </div>
                </div>

            </div>

            <script>
                function achsenModalOeffnen() {
                    document.getElementById('achsen-backdrop').style.display = 'flex';
                }

                function achsenModalSchliessen() {
                    document.getElementById('achsen-backdrop').style.display = 'none';
                }

                function achseToggle(cb) {
                    if (!cb.checked && cb.dataset.hasLocked) {
                        cb.checked = true;
                        showFlash('Diese Achse hat Kind-Artikel — sie kann nicht entfernt werden solange Kind-Artikel existieren.', 'fehler');
                        return;
                    }
                    var id = cb.dataset.achseId;
                    document.getElementById('achse-werte-' + id).style.display = cb.checked ? '' : 'none';
                }

                function achseZeileEntfernen(btn) {
                    btn.closest('.achse-wert-zeile').remove();
                }

                function achseWertHoch(btn) {
                    var zeile = btn.closest('.achse-wert-zeile');
                    var prev = zeile.previousElementSibling;
                    if (prev && prev.classList.contains('achse-wert-zeile')) {
                        zeile.parentNode.insertBefore(zeile, prev);
                    }
                }

                function achseWertRunter(btn) {
                    var zeile = btn.closest('.achse-wert-zeile');
                    var next = zeile.nextElementSibling;
                    if (next && next.classList.contains('achse-wert-zeile')) {
                        zeile.parentNode.insertBefore(next, zeile);
                    }
                }

                function achseWertHinzufuegen(achseId) {
                    var input = document.querySelector('.achse-wert-input[data-achse-id="' + achseId + '"]');
                    var wert = input.value.trim();
                    if (!wert) return;
                    var container = document.getElementById('achse-chips-' + achseId);
                    var zeile = document.createElement('div');
                    zeile.className = 'achse-wert-zeile';
                    zeile.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:4px 6px;background:#F7FAFC;border:1px solid #E2E8F0;border-radius:4px;margin-bottom:3px';
                    zeile.innerHTML = '<span class="achse-chip-text" style="font-size:13px">' + wert.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>' +
                        '<div style="display:flex;gap:2px;flex-shrink:0">' +
                        '<button type="button" onclick="achseWertHoch(this)" class="btn btn-secondary btn-xs" title="Nach oben">▲</button>' +
                        '<button type="button" onclick="achseWertRunter(this)" class="btn btn-secondary btn-xs" title="Nach unten">▼</button>' +
                        '<button type="button" onclick="achseZeileEntfernen(this)" class="btn btn-danger btn-xs" title="Entfernen">✕</button>' +
                        '</div>';
                    container.appendChild(zeile);
                    input.value = '';
                    input.focus();
                }

                function achsenSpeichern() {
                    var btn = document.getElementById('achsen-speichern-btn');
                    btn.disabled = true;
                    var achsenDaten = [];
                    document.querySelectorAll('.achse-checkbox').forEach(function(cb) {
                        if (!cb.checked) return;
                        var achseId = parseInt(cb.dataset.achseId);
                        var werte = [];
                        document.querySelectorAll('#achse-chips-' + achseId + ' .achse-chip-text').forEach(function(t) {
                            var txt = t.textContent.trim();
                            if (txt) werte.push(txt);
                        });
                        achsenDaten.push({
                            id: achseId,
                            werte: werte
                        });
                    });
                    fetch('/mealana/artikel/achsen_zuweisen_ajax.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                artikel_id: <?= $id ?>,
                                achsen: achsenDaten
                            })
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(d) {
                            if (d.erfolg) {
                                window.location.reload();
                            } else {
                                showFlash(d.fehler || 'Fehler beim Speichern', 'fehler');
                                btn.disabled = false;
                            }
                        })
                        .catch(function() {
                            showFlash('Verbindungsfehler', 'fehler');
                            btn.disabled = false;
                        });
                }

                document.getElementById('stammdaten-form').addEventListener('change', function() {
                    document.getElementById('unsaved-banner').style.display = 'inline-flex';
                });

                var bannerSuccess = document.querySelector('.success-banner');
                if (bannerSuccess) {
                    setTimeout(function() {
                        bannerSuccess.style.display = 'none'
                    }, 3000);
                }

            // ── Merkmale ──────────────────────────────────────────────────
            var _merkmalAktuell = null;

            function merkmalWaehlen(merkmalId, mehrfach, werte, gesetzteIds) {
                _merkmalAktuell = {merkmalId, mehrfach, werte};
                document.getElementById('merk-modal-titel').textContent = 'Merkmal wählen';
                const liste = document.getElementById('merk-modal-liste');
                liste.innerHTML = '';
                werte.forEach(function(w) {
                    const label = document.createElement('label');
                    label.style.cssText = 'display:flex;align-items:center;gap:8px;padding:6px 4px;cursor:pointer;font-size:13px';
                    const input = document.createElement('input');
                    input.type = mehrfach ? 'checkbox' : 'radio';
                    input.name = 'merk-auswahl';
                    input.value = w.id;
                    input.checked = gesetzteIds.includes(w.id);
                    label.appendChild(input);
                    label.appendChild(document.createTextNode(w.wert));
                    liste.appendChild(label);
                });
                document.getElementById('merk-backdrop').style.display = 'flex';
            }

            function merkmalModalSchliessen() {
                document.getElementById('merk-backdrop').style.display = 'none';
            }

            function merkmalUebernehmen() {
                if (!_merkmalAktuell) return;
                const mid = _merkmalAktuell.merkmalId;
                const gewaehlte = [...document.querySelectorAll('input[name="merk-auswahl"]:checked')].map(i => parseInt(i.value));
                const wertMap = {};
                _merkmalAktuell.werte.forEach(function(w) { wertMap[w.id] = w.wert; });

                // Hidden inputs + Chips neu aufbauen
                const chipsDiv = document.getElementById('merk-chips-' + mid);
                chipsDiv.innerHTML = '';
                gewaehlte.forEach(function(wid) {
                    const span = document.createElement('span');
                    span.className = 'chip chip-aktiv';
                    span.style.fontSize = '12px';
                    span.textContent = wertMap[wid] || wid;
                    chipsDiv.appendChild(span);
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'merk[' + mid + '][]';
                    inp.value = wid;
                    chipsDiv.appendChild(inp);
                });
                if (!gewaehlte.length) {
                    chipsDiv.innerHTML = '<span style="font-size:12px;color:var(--color-text-muted)">–</span>';
                }
                merkmalModalSchliessen();
            }

            function merkmaleSpeichern(artikelId) {
                const daten = {};
                document.querySelectorAll('[name^="merk["]').forEach(function(inp) {
                    const m = inp.name.match(/merk\[(\d+)\]/);
                    if (!m) return;
                    const mid = m[1];
                    if (!daten[mid]) daten[mid] = [];
                    daten[mid].push(parseInt(inp.value));
                });
                fetch('merkmale_speichern.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({artikel_id: artikelId, merkmale: daten})
                }).then(r => r.json()).then(function(d) {
                    if (d.erfolg) {
                        showFlash('Merkmale gespeichert', 'erfolg');
                    } else {
                        showFlash(d.fehler || 'Fehler beim Speichern', 'fehler');
                    }
                });
            }
            </script>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>