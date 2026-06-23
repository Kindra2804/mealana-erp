<?php
$moduleLabel = match ($activeModule ?? '') {
    'artikel'     => 'Artikel',
    'lager'       => 'Lager',
    'kunden'      => 'Kunden',
    'verkauf'     => 'Verkauf',
    'versand'     => 'Versand',
    'retouren'    => 'Retouren',
    'einkauf'     => 'Einkauf',
    'buchhaltung' => 'Buchhaltung',
    'lieferanten' => 'Lieferanten',
    'hersteller'  => 'Hersteller',
    'partner'     => 'Partner',
    default       => '',
};

$sidebarItems = match ($activeModule ?? '') {
    'artikel' => [
        ['icon' => '📋', 'label' => 'Liste',            'href' => '/mealana/artikel/liste.php'],
        ['icon' => '➕', 'label' => 'Neu erstellen',     'href' => '/mealana/artikel/neu.php'],
        ['icon' => '🗂', 'label' => 'Kategorien',       'href' => '/mealana/artikel/kategorien_verwalten.php'],
        ['icon' => '🏷', 'label' => 'Merkmale',         'href' => '/mealana/artikel/merkmale_verwalten.php'],
        ['icon' => '💲', 'label' => 'Preise / Aktionen', 'href' => '/mealana/aktionen/liste.php'],
        ['icon' => '🏭', 'label' => 'Hersteller',       'href' => '/mealana/hersteller/liste.php'],
    ],
    'lager' => [
        ['icon' => '📦', 'label' => 'Übersicht',        'href' => '/mealana/lager/uebersicht.php'],
        ['icon' => '📥', 'label' => 'Wareneingang',     'href' => '/mealana/lager/wareneingang.php'],
        ['icon' => '🔖', 'label' => 'Chargen-Nachtrag', 'href' => '/mealana/lager/nachtrag_liste.php'],
    ],
    'einkauf' => [
        ['icon' => '📋', 'label' => 'Bestellungen',    'href' => '/mealana/bestellungen/liste.php'],
        ['icon' => '➕', 'label' => 'Neue Bestellung', 'href' => '/mealana/bestellungen/neu.php'],
        ['icon' => '📥', 'label' => 'Wareneingang',    'href' => '/mealana/wareneingang/index.php'],
        ['icon' => '🏢', 'label' => 'Lieferanten',     'href' => '/mealana/lieferanten/liste.php'],
    ],
    'lieferanten' => [
        ['icon' => '📋', 'label' => 'Liste',        'href' => '/mealana/lieferanten/liste.php'],
        ['icon' => '➕', 'label' => 'Neu',          'href' => '/mealana/lieferanten/neu.php'],
        ['icon' => '◀',  'label' => 'Zurück: Einkauf', 'href' => '/mealana/bestellungen/liste.php', 'type' => 'back'],
    ],
    'kunden' => [
        ['icon' => '📋', 'label' => 'Liste',        'href' => '/mealana/kunden/liste.php'],
        ['icon' => '➕', 'label' => 'Neuer Kunde',  'href' => '/mealana/kunden/neu.php'],
    ],
    'hersteller' => [
        ['icon' => '📋', 'label' => 'Liste',            'href' => '/mealana/hersteller/liste.php'],
        ['icon' => '◀',  'label' => 'Zurück: Artikel',  'href' => '/mealana/artikel/liste.php', 'type' => 'back'],
    ],
    'partner' => [
        ['icon' => '👥', 'label' => 'Partner',           'href' => '/mealana/partner/liste.php'],
        ['icon' => '🗄',  'label' => 'Mietfächer',       'href' => '/mealana/partner/mietfaecher.php'],
    ],
    default => [],
};

// $currentPath = $_SERVER['PHP_SELF'] ?? '';
$currentPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

?>


<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/mealana/css/variables.css">
    <link rel="stylesheet" href="/mealana/css/layout.css">
    <link rel="stylesheet" href="/mealana/css/components.css">
    <title><?= htmlspecialchars($pageTitle ?? 'MeaLana ERP') ?></title>
</head>

<body>
    <div class="erp-shell">
        <nav class="erp-topnav">
            <a href="#" class="erp-nav-logo">
                <img src="/mealana/img/logo.png" alt="MeaLana ERP">
            </a>
            <div class="erp-nav-links">
                <a href="/mealana/artikel/liste.php"      class="erp-nav-link <?= in_array($activeModule ?? '', ['artikel', 'hersteller']) ? 'active' : '' ?>">Artikel</a>
                <a href="/mealana/lager/wareneingang.php" class="erp-nav-link <?= ($activeModule ?? '') === 'lager'       ? 'active' : '' ?>">Lager</a>
                <a href="/mealana/kunden/liste.php"       class="erp-nav-link <?= ($activeModule ?? '') === 'kunden'      ? 'active' : '' ?>">Kunden</a>
                <a href="/mealana/bestellungen/liste.php" class="erp-nav-link <?= in_array($activeModule ?? '', ['einkauf','lieferanten']) ? 'active' : '' ?>">Einkauf</a>
                <a href="/mealana/partner/liste.php"      class="erp-nav-link <?= ($activeModule ?? '') === 'partner'     ? 'active' : '' ?>">Partner</a>
                <a href="#" title="Verkauf — kommt bald"      class="erp-nav-link erp-nav-link-disabled <?= ($activeModule ?? '') === 'verkauf'     ? 'active' : '' ?>">Verkauf</a>
                <a href="#" title="Versand — kommt bald"      class="erp-nav-link erp-nav-link-disabled <?= ($activeModule ?? '') === 'versand'     ? 'active' : '' ?>">Versand</a>
                <a href="#" title="Retouren — kommt bald"     class="erp-nav-link erp-nav-link-disabled <?= ($activeModule ?? '') === 'retouren'    ? 'active' : '' ?>">Retouren</a>
                <a href="#" title="Buchhaltung — kommt bald"  class="erp-nav-link erp-nav-link-disabled <?= ($activeModule ?? '') === 'buchhaltung' ? 'active' : '' ?>">Buchhaltung</a>
            </div>
            <div class="erp-nav-icons">
                <a href="/mealana/bedienungsanleitung.php" title="Bedienungsanleitung" class="erp-nav-icon">📖</a>
                <a href="#" title="Einstellungen — kommt bald" class="erp-nav-icon erp-nav-link-disabled">⚙️</a>
                <a href="#" title="Super-Admin — kommt bald"   class="erp-nav-icon erp-nav-link-disabled">···</a>
            </div>
            <div class="erp-nav-user">👤 Karl</div>
        </nav>
        <div class="erp-actionbar"><?= $actionBarContent ?? '' ?></div>
        <aside class="erp-sidebar">
            <?php if ($moduleLabel): ?>
                <div class="sidebar-module-header"><?= htmlspecialchars($moduleLabel) ?></div>
            <?php endif; ?>
            <nav class="erp-sidebar-nav">
                <?php foreach ($sidebarItems as $item): ?>
                    <?php $typ = $item['type'] ?? 'nav'; ?>
                    <?php if ($typ === 'separator'): ?>
                        <div class="sidebar-sep"></div>
                    <?php elseif ($typ === 'back'): ?>
                        <a href="<?= $item['href'] ?>" class="erp-sidebar-item" style="font-size:13px; color:var(--color-text-muted)">
                            ← <?= htmlspecialchars($item['label']) ?>
                        </a>
                    <?php elseif ($typ === 'context'): ?>
                        <div class="sidebar-context-box">
                            <div class="artnr"><?= htmlspecialchars($item['artNr']) ?></div>
                            <div class="name"><?= htmlspecialchars($item['name']) ?></div>
                        </div>
                    <?php elseif ($typ === 'grayed'): ?>
                        <a href="#" class="erp-sidebar-item sidebar-grayed">
                            <?= $item['icon'] ?> <?= htmlspecialchars($item['label']) ?>
                        </a>
                    <?php else: ?>
                        <?php
                        $isActive = isset($item['active']) && $item['active']
                            ? 'active'
                            : (($item['href'] !== '#' && $currentPath === $item['href']) ? 'active' : '');
                        ?>
                        <a href="<?= $item['href'] ?>" class="erp-sidebar-item <?= $isActive ?>">
                            <?= $item['icon'] ?> <?= htmlspecialchars($item['label']) ?>
                            <?php if (!empty($item['badge'])): ?>
                                <span class="badge" style="margin-left:auto"><?= (int)$item['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

            </nav>

            <?php if (!empty($kategorienBaum)): ?>
                <div class="sidebar-kat-wrapper">
                    <div class="sidebar-kat-header">
                        <span>Kategorien</span>
                        <a href="/mealana/artikel/kategorien_verwalten.php"
                            style="background:none;border:none;cursor:pointer;font-size:14px;color:var(--color-nav);padding:0 2px;line-height:1"
                            title="Kategorieverwaltung">🗂</a>
                    </div>
                    <nav class="sidebar-kat-tree" id="kat-tree">
                        <?php
                        $aktivKatId = $aktivKategorieId ?? null;

                        function renderKatKnoten(array $knoten, int $tiefe, ?int $aktivKatId): void
                        {
                            $hatKinder    = !empty($knoten['kinder']);
                            $istAktiv     = ($aktivKatId !== null && $knoten['id'] === $aktivKatId);
                            $einzug       = 12 + $tiefe * 14;
                            $nodeId       = 'kat-' . $knoten['id'];
                            $toggleId     = 'kattog-' . $knoten['id'];
                            $anzahl       = (int)($knoten['artikel_anzahl'] ?? 0);
                            $istAktionKat  = (int)($knoten['ist_aktions_kategorie'] ?? 0);
                            $aktionAktiv   = (int)($knoten['aktion_aktiv']   ?? 0);
                            $aktionZukunft = (int)($knoten['aktion_zukunft'] ?? 0);
                            $aktionInfo    = $knoten['aktion_info'] ?? '';
                            $aktionSymbol  = '';
                            $katOpacity    = '';
                            if ($istAktionKat) {
                                if ($aktionAktiv) {
                                    $titel = 'Aktions-Kategorie (aktiv)' . ($aktionInfo ? "\n" . $aktionInfo : '');
                                    $aktionSymbol = '<span title="' . htmlspecialchars($titel) . '" style="color:#e67e22;margin-right:2px;font-size:11px">⏰</span>';
                                } elseif ($aktionZukunft) {
                                    $titel = 'Aktions-Kategorie (geplant)' . ($aktionInfo ? "\n" . $aktionInfo : '');
                                    $aktionSymbol = '<span title="' . htmlspecialchars($titel) . '" style="color:#e67e22;margin-right:2px;font-size:11px">⏰</span>';
                                    $katOpacity   = 'opacity:.45;';
                                } else {
                                    $titel = 'Aktions-Kategorie (abgelaufen)' . ($aktionInfo ? "\n" . $aktionInfo : '');
                                    $aktionSymbol = '<span title="' . htmlspecialchars($titel) . '" style="filter:grayscale(1);opacity:.5;margin-right:2px;font-size:11px">⏰</span>';
                                    $katOpacity   = 'opacity:.45;';
                                }
                            }
                        ?>
                            <div class="kat-knoten">
                                <a href="liste.php?kategorie_id=<?= $knoten['id'] ?>"
                                    class="kat-zeile <?= $istAktiv ? 'aktiv' : '' ?>"
                                    style="padding-left:<?= $einzug ?>px;<?= $katOpacity ?>">
                                    <?php if ($hatKinder): ?>
                                        <span class="kat-toggle" id="<?= $toggleId ?>"
                                            onclick="event.preventDefault();katToggle('<?= $nodeId ?>','<?= $toggleId ?>')">▶</span>
                                    <?php else: ?>
                                        <span class="kat-toggle-leer"></span>
                                    <?php endif; ?>
                                    <span class="kat-zeile-name"><?= $aktionSymbol ?><?= htmlspecialchars($knoten['name']) ?></span>
                                    <?php if ($anzahl > 0): ?>
                                        <span class="kat-anzahl"><?= $anzahl ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php if ($hatKinder): ?>
                                    <div id="<?= $nodeId ?>" class="kat-kinder versteckt">
                                        <?php foreach ($knoten['kinder'] as $kind): ?>
                                            <?php renderKatKnoten($kind, $tiefe + 1, $aktivKatId); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php
                        }

                        foreach ($kategorienBaum as $wurzel) {
                            renderKatKnoten($wurzel, 0, $aktivKatId);
                        }
                        ?>
                        <?php if ($aktivKatId): ?>
                            <a href="liste.php" class="kat-filter-aufheben">✕ Filter aufheben</a>
                        <?php endif; ?>
                    </nav>
                </div>
                <script>window.MEALANA_AKTIV_KAT = <?= (int)($aktivKatId ?? 0) ?>;</script>
                <script src="/mealana/js/shell.js"></script>

                <!-- Neue Kategorie Modal -->
                <div id="kat-neu-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
                    <div style="background:#fff;border-radius:8px;padding:20px;width:320px;box-shadow:0 4px 24px rgba(0,0,0,.2)">
                        <div style="font-weight:700;font-size:14px;margin-bottom:12px;color:var(--color-nav)">Neue Kategorie</div>
                        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Name *</label>
                        <input id="kat-neu-name" type="text" class="erp-input" style="width:100%;margin-bottom:10px"
                            placeholder="z.B. Merinowolle"
                            onkeydown="if(event.key==='Enter')katNeuSpeichern();if(event.key==='Escape')katNeuSchliessen()">
                        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Oberkategorie (optional)</label>
                        <select id="kat-neu-parent" class="erp-select" style="width:100%;margin-bottom:12px">
                            <option value="">– Keine (Hauptkategorie) –</option>
                            <?php
                            function renderKatOption(array $knoten, int $tiefe): void
                            {
                                $einzug = str_repeat('  ', $tiefe);
                                echo '<option value="' . $knoten['id'] . '">'
                                    . htmlspecialchars($einzug . ($tiefe > 0 ? '↳ ' : '') . $knoten['name'])
                                    . '</option>';
                                foreach ($knoten['kinder'] as $kind) {
                                    renderKatOption($kind, $tiefe + 1);
                                }
                            }
                            foreach ($kategorienBaum as $w) {
                                renderKatOption($w, 0);
                            }
                            ?>
                        </select>
                        <div id="kat-neu-fehler" style="font-size:12px;color:var(--color-danger);min-height:16px;margin-bottom:8px"></div>
                        <div style="display:flex;gap:8px;justify-content:flex-end">
                            <button onclick="katNeuSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
                            <button onclick="katNeuSpeichern()" class="btn btn-primary btn-sm">Speichern</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
        <main class="erp-main">