<!-- erp/public/includes/nav.php -->
<nav style="background:#4a7cb5; padding:10px 20px; margin-bottom:20px;display:flex; justify-content:space-between;">
    <div>
        <a href="<?= BASE_PATH ?>/" style="color:white; font-weight:bold; font-size:18px; margin-right:30px; text-decoration:none;">
            🧶 MeaLana ERP
        </a>
        <a href="<?= BASE_PATH ?>/artikel/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            📦 Artikel
        </a>
        <a href="<?= BASE_PATH ?>/lager/uebersicht.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🏪 Lager
        </a>
        <a href="<?= BASE_PATH ?>/lager/wareneingang.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            📥 Wareneingang
        </a>
        <a href="<?= BASE_PATH ?>/lager/nachtrag_liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🏷️ Chargen-Nachtrag
        </a>
        <a href="<?= BASE_PATH ?>/lieferanten/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🚚 Lieferanten
        </a>
        <a href="<?= BASE_PATH ?>/hersteller/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🏭 Hersteller
        </a>
        <a href="<?= BASE_PATH ?>/lagerbewegungen/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            📊 Lagerbewegungen
        </a>
        <a href="<?= BASE_PATH ?>/merkmale/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🏷️ Merkmale
        </a>
        <a href="<?= BASE_PATH ?>/varianten/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🎨 Varianten
        </a>
        <a href="<?= BASE_PATH ?>/achsen/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            📐 Achsen
        </a>
    </div>
    <div>
        <span style="color:white; margin-right:15px;">
            👤 <?= htmlspecialchars($_SESSION['benutzer']['formularname']) ?>
        </span>
        <a href="<?= BASE_PATH ?>/logout.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            Logout
        </a>
    </div>
</nav>