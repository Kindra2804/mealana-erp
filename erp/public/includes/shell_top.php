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
    default       => '',
};

$sidebarItems = match ($activeModule ?? '') {
    'artikel' => [
        ['icon' => '📋', 'label' => 'Liste',        'href' => '/mealana/artikel/liste.php'],
        ['icon' => '➕', 'label' => 'Neu erstellen', 'href' => '/mealana/artikel/neu.php'],
        ['icon' => '🖼', 'label' => 'Bilder',       'href' => '#'],
        ['icon' => '🏷', 'label' => 'Merkmale',     'href' => '#'],
        ['icon' => '💲', 'label' => 'Preise',       'href' => '#'],
        ['icon' => '🌐', 'label' => 'SEO',          'href' => '#'],
        ['icon' => '📊', 'label' => 'Statistik',    'href' => '#'],
    ],
    'lager' => [
        ['icon' => '📦', 'label' => 'Übersicht',    'href' => '/mealana/lager/uebersicht.php'],
        ['icon' => '📥', 'label' => 'Wareneingang', 'href' => '/mealana/lager/wareneingang.php'],
    ],
    'lieferanten' => [
        ['icon' => '📋', 'label' => 'Liste',        'href' => '/mealana/lieferanten/liste.php'],
        ['icon' => '➕', 'label' => 'Neu',          'href' => '/mealana/lieferanten/neu.php'],
    ],
    default => [],
};

$currentPath = $_SERVER['PHP_SELF'] ?? '';
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
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'artikel' ? 'active' : '' ?>">Artikel</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'lager' ? 'active' : '' ?>">Lager</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'kunden' ? 'active' : '' ?>">Kunden</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'verkauf' ? 'active' : '' ?>">Verkauf</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'versand' ? 'active' : '' ?>">Versand</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'retouren' ? 'active' : '' ?>">Retouren</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'einkauf' ? 'active' : '' ?>">Einkauf</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'buchhaltung' ? 'active' : '' ?>">Buchhaltung</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'zahnrad' ? 'active' : '' ?>">Zahnrad</a>
                <a href="#" class="erp-nav-link <?= ($activeModule ?? '') === 'dreiPunkte' ? 'active' : '' ?>">DreiPunkte</a>
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
                    <?php $isActive = ($item['href'] !== '#' && str_ends_with($currentPath, basename($item['href']))) ? 'active' : ''; ?>
                    <a href="<?= $item['href'] ?>" class="erp-sidebar-item <?= $isActive ?>">
                        <?= $item['icon'] ?> <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="erp-main">