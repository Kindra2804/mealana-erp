<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/mealana/css/variables.css">
    <link rel="stylesheet" href="/mealana/css/layout.css">
    <title><?= htmlspecialchars($pageTitle ?? 'MeaLana ERP') ?></title>
</head>

<body>
    <div class="erp-shell">
        <nav class="erp-topnav">
            <a href="#" class="erp-nav-logo">MeaLana ERP</a>
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
        <div class="erp-actionbar">Actionbar</div>
        <aside class="erp-sidebar">
            <nav class="erp-sidebar-nav">
                <a href="#" class="erp-sidebar-item <?= ($activeModule ?? '') === 'artikel' ? 'active' : '' ?>">📦 Artikel</a>
                <a href="#" class="erp-sidebar-item <?= ($activeModule ?? '') === 'lager' ? 'active' : '' ?>">🏭 Lager</a>
                <a href="#" class="erp-sidebar-item <?= ($activeModule ?? '') === 'kunden' ? 'active' : '' ?>">👥 Kunden</a>
                <a href="#" class="erp-sidebar-item <?= ($activeModule ?? '') === 'verkauf' ? 'active' : '' ?>">📋 Verkauf</a>
                <a href="#" class="erp-sidebar-item <?= ($activeModule ?? '') === 'versand' ? 'active' : '' ?>">📦 Versand</a>
                <a href="#" class="erp-sidebar-item <?= ($activeModule ?? '') === 'retouren' ? 'active' : '' ?>">🔄 Retouren</a>
                <a href="#" class="erp-sidebar-item <?= ($activeModule ?? '') === 'einkauf' ? 'active' : '' ?>">🛒 Einkauf</a>
                <a href="#" class="erp-sidebar-item <?= ($activeModule ?? '') === 'buchhaltung' ? 'active' : '' ?>">📊 Buchhaltung</a>
            </nav>
        </aside>
        <main class="erp-main">