<?php
$moduleLabel = match ($activeModule ?? '') {
    'artikel'     => 'Artikel',
    'lager'       => 'Lager',
    'kunden'      => 'Kunden',
    'verkauf'     => 'Verkauf',
    'versand'     => 'Versand',
    'retouren'    => 'Retouren',
    'einkauf'     => 'Einkauf',
    'buchhaltung'   => 'Buchhaltung',
    'lieferanten' => 'Lieferanten',
    'hersteller'  => 'Hersteller',
    'partner'      => 'Partner',
    'benutzer'     => 'Benutzer',
    'rollen'       => 'Rollen & Rechte',
    'einstellungen' => 'Einstellungen',
    default       => '',
};

$sidebarItems = match ($activeModule ?? '') {
    'artikel' => [
        ['icon' => '📋', 'label' => 'Liste',            'href' => BASE_PATH . '/artikel/liste.php'],
        ['icon' => '➕', 'label' => 'Neu erstellen',     'href' => BASE_PATH . '/artikel/neu.php'],
        ['icon' => '🗂', 'label' => 'Kategorien',       'href' => BASE_PATH . '/artikel/kategorien_verwalten.php'],
        ['icon' => '🏷', 'label' => 'Merkmale',         'href' => BASE_PATH . '/artikel/merkmale_verwalten.php'],
        ['icon' => '💲', 'label' => 'Preise / Aktionen', 'href' => BASE_PATH . '/aktionen/liste.php'],
        ['icon' => '🏭', 'label' => 'Hersteller',       'href' => BASE_PATH . '/hersteller/liste.php'],
    ],
    'lager' => [
        ['icon' => '📦', 'label' => 'Übersicht',          'href' => BASE_PATH . '/lager/uebersicht.php'],
        ['icon' => '📥', 'label' => 'Wareneingang',       'href' => BASE_PATH . '/lager/wareneingang.php'],
        ['icon' => '🔖', 'label' => 'Chargen-Nachtrag',  'href' => BASE_PATH . '/lager/nachtrag_liste.php'],
        ['icon' => '🔍', 'label' => 'Chargen-Suche',      'href' => BASE_PATH . '/lager/chargen_nachverfolgung.php'],
        ['icon' => '📋', 'label' => 'Picklisten',         'href' => BASE_PATH . '/lager/picklisten.php'],
        ['icon' => '🗄️', 'label' => 'Lagerverwaltung',    'href' => BASE_PATH . '/lager/verwaltung.php'],
        ['icon' => '📍', 'label' => 'Lagerplätze',         'href' => BASE_PATH . '/lager/lagerplaetze.php'],
        ['icon' => '🔢', 'label' => 'Inventur',            'href' => BASE_PATH . '/inventur/liste.php'],
    ],
    'verkauf' => [
        ['icon' => '📋', 'label' => 'Aufträge',           'href' => BASE_PATH . '/auftraege/liste.php'],
        ['icon' => '➕', 'label' => 'Neuer Auftrag',      'href' => BASE_PATH . '/auftraege/neu.php'],
        ['icon' => '📁', 'label' => 'Dokumentenarchiv',   'href' => BASE_PATH . '/dokumente/index.php'],
    ],
    'einkauf' => [
        ['icon' => '📋', 'label' => 'Bestellungen',    'href' => BASE_PATH . '/bestellungen/liste.php'],
        ['icon' => '➕', 'label' => 'Neue Bestellung', 'href' => BASE_PATH . '/bestellungen/neu.php'],
        ['icon' => '📥', 'label' => 'Wareneingang',    'href' => BASE_PATH . '/wareneingang/index.php'],
        ['icon' => '🏢', 'label' => 'Lieferanten',     'href' => BASE_PATH . '/lieferanten/liste.php'],
    ],
    'lieferanten' => [
        ['icon' => '📋', 'label' => 'Liste',        'href' => BASE_PATH . '/lieferanten/liste.php'],
        ['icon' => '➕', 'label' => 'Neu',          'href' => BASE_PATH . '/lieferanten/neu.php'],
        ['icon' => '◀',  'label' => 'Zurück: Einkauf', 'href' => BASE_PATH . '/bestellungen/liste.php', 'type' => 'back'],
    ],
    'kunden' => [
        ['icon' => '📋', 'label' => 'Liste',        'href' => BASE_PATH . '/kunden/liste.php'],
        ['icon' => '➕', 'label' => 'Neuer Kunde',  'href' => BASE_PATH . '/kunden/neu.php'],
    ],
    'hersteller' => [
        ['icon' => '📋', 'label' => 'Liste',            'href' => BASE_PATH . '/hersteller/liste.php'],
        ['icon' => '◀',  'label' => 'Zurück: Artikel',  'href' => BASE_PATH . '/artikel/liste.php', 'type' => 'back'],
    ],
    'versand' => [
        ['icon' => '⚙️', 'label' => 'PLC-Einstellungen', 'href' => BASE_PATH . '/versand/index.php'],
        ['icon' => '📦', 'label' => 'Packplatz',          'href' => BASE_PATH . '/packplatz/warenausgang/index.php'],
        ['icon' => '↩️', 'label' => 'Retouren',           'href' => BASE_PATH . '/packplatz/retoure/index.php'],
    ],
    'partner' => [
        ['icon' => '👥', 'label' => 'Partner',           'href' => BASE_PATH . '/partner/liste.php'],
        ['icon' => '🗄',  'label' => 'Mietfächer',       'href' => BASE_PATH . '/partner/mietfaecher.php'],
    ],
    'buchhaltung' => [
        ['icon' => '🏷', 'label' => 'Artikelgruppen',   'href' => BASE_PATH . '/buchhaltung/artikel_gruppen.php'],
        ['icon' => '📇', 'label' => 'Kreditoren',       'href' => BASE_PATH . '/buchhaltung/kreditoren.php'],
        ['icon' => '🧮', 'label' => 'Lieferantenrechnungen', 'href' => BASE_PATH . '/buchhaltung/lieferantenrechnungen.php'],
        ['icon' => '📒', 'label' => 'Kontenplan',       'href' => BASE_PATH . '/buchhaltung/kontenplan.php'],
        ['icon' => '💳', 'label' => 'Zahlungsart-Konten', 'href' => BASE_PATH . '/buchhaltung/zahlungsart_konten.php'],
        ['icon' => '🧾', 'label' => 'Steuer-Konten',    'href' => BASE_PATH . '/buchhaltung/steuerklassen_konten.php'],
        ['icon' => '📤', 'label' => 'DATEV/CSV-Export', 'href' => BASE_PATH . '/buchhaltung/export.php'],
    ],
    'einstellungen' => [
        ['icon' => '🏢', 'label' => 'Firma',       'href' => BASE_PATH . '/einstellungen/index.php?tab=firma'],
        ['icon' => '🛍', 'label' => 'Kanäle',      'href' => BASE_PATH . '/einstellungen/index.php?tab=kanaele'],
        ['icon' => '✉️', 'label' => 'Mail / SMTP', 'href' => BASE_PATH . '/einstellungen/index.php?tab=mail'],
        ['icon' => '⚙️', 'label' => 'System',      'href' => BASE_PATH . '/einstellungen/index.php?tab=system'],
        ['icon' => '🖨️', 'label' => 'Kassen',      'href' => BASE_PATH . '/einstellungen/index.php?tab=kassen'],
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
    <script>window.BASE_PATH = <?= json_encode(BASE_PATH) ?>;</script>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/variables.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/layout.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/components.css">
    <title><?= htmlspecialchars($pageTitle ?? 'MeaLana ERP') ?></title>
</head>

<body>
    <div class="erp-shell<?= (empty($sidebarItems) && empty($kategorienBaum)) ? ' no-sidebar' : '' ?>">
        <nav class="erp-topnav">
            <a href="#" class="erp-nav-logo">
                <img src="<?= BASE_PATH ?>/img/nahtlos_icon.png" alt="NahtlOS">
            </a>
            <div class="erp-nav-links">
                <a href="<?= BASE_PATH ?>/dashboard.php" class="erp-nav-link <?= ($activeModule ?? '') === 'dashboard' ? 'active' : '' ?>">🏠</a>
                <div class="erp-nav-divider"></div>
                <a href="<?= BASE_PATH ?>/artikel/liste.php" class="erp-nav-link <?= in_array($activeModule ?? '', ['artikel', 'hersteller']) ? 'active' : '' ?>">Artikel</a>
                <a href="<?= BASE_PATH ?>/kunden/liste.php" class="erp-nav-link <?= ($activeModule ?? '') === 'kunden'      ? 'active' : '' ?>">Kunden</a>
                <a href="<?= BASE_PATH ?>/auftraege/liste.php" class="erp-nav-link <?= ($activeModule ?? '') === 'verkauf'     ? 'active' : '' ?>">Verkauf</a>
                <a href="<?= BASE_PATH ?>/lager/picklisten.php" class="erp-nav-link <?= ($activeModule ?? '') === 'lager'       ? 'active' : '' ?>">Lager</a>
                <a href="<?= BASE_PATH ?>/versand/index.php" class="erp-nav-link <?= ($activeModule ?? '') === 'versand'     ? 'active' : '' ?>">Versand</a>
                <a href="<?= BASE_PATH ?>/packplatz/retoure/index.php" class="erp-nav-link <?= ($activeModule ?? '') === 'retouren'    ? 'active' : '' ?>">Retouren</a>
                <div class="erp-nav-divider"></div>
                <a href="<?= BASE_PATH ?>/bestellungen/liste.php" class="erp-nav-link <?= in_array($activeModule ?? '', ['einkauf', 'lieferanten']) ? 'active' : '' ?>">Einkauf</a>
                <a href="<?= BASE_PATH ?>/partner/liste.php" class="erp-nav-link <?= ($activeModule ?? '') === 'partner'     ? 'active' : '' ?>">Partner</a>
                <a href="<?= BASE_PATH ?>/buchhaltung/artikel_gruppen.php" class="erp-nav-link <?= ($activeModule ?? '') === 'buchhaltung' ? 'active' : '' ?>">Buchhaltung</a>
            </div>
            <div class="erp-nav-icons">
                <a href="<?= BASE_PATH ?>/bedienungsanleitung.php" title="Bedienungsanleitung" class="erp-nav-icon">📖</a>
                <a href="<?= BASE_PATH ?>/einstellungen/index.php" title="Einstellungen" class="erp-nav-icon <?= ($activeModule ?? '') === 'einstellungen' ? 'active' : '' ?>">⚙️</a>
                <div class="erp-nav-more" id="erp-nav-more-wrap">
                    <button class="erp-nav-icon erp-nav-more-btn" onclick="erpNavMoreToggle()" title="Weitere Bereiche">···</button>
                    <div class="erp-nav-more-menu" id="erp-nav-more-menu">
                        <a href="<?= BASE_PATH ?>/kasse/bon.php" class="erp-nav-more-item">🛒 Kasse</a>
                        <a href="<?= BASE_PATH ?>/packplatz/index.php" class="erp-nav-more-item">📦 Packplatz</a>
                        <div class="erp-nav-more-sep"></div>
                        <a href="<?= BASE_PATH ?>/benutzer/liste.php" class="erp-nav-more-item">👤 Benutzerverwaltung</a>
                        <a href="<?= BASE_PATH ?>/rollen/matrix.php" class="erp-nav-more-item">🔐 Rollen & Rechte</a>
                        <?php if (Auth::kann('system.log')): ?>
                            <a href="<?= BASE_PATH ?>/admin/aktivitaeten.php" class="erp-nav-more-item">📜 Aktivitäten-Log</a>
                        <?php endif; ?>
                        <?php if (Auth::kann('api.zugriff')): ?>
                            <a href="#" class="erp-nav-more-item erp-nav-more-item-disabled" title="Kommt bald">🔑 Lizenzverwaltung</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="erp-nav-user">
                <a href="<?= BASE_PATH ?>/benutzer/profil.php" style="color:white;text-decoration:none">👤 <?= htmlspecialchars(Auth::benutzer()['formularname']) ?></a>
                <a class="btn" href="<?= BASE_PATH ?>/logout.php" style="color:white;font-size:11px;margin-left:6px">Abmelden</a>
            </div>
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
                        <a href="<?= BASE_PATH ?>/artikel/kategorien_verwalten.php"
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
                <script>
                    window.MEALANA_AKTIV_KAT = <?= (int)($aktivKatId ?? 0) ?>;
                </script>
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
        <script src="<?= BASE_PATH ?>/js/shell.js"></script>
        <main class="erp-main">