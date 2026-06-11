<!-- erp/public/includes/nav.php -->
<nav style="background:#4a7cb5; padding:10px 20px; margin-bottom:20px;display:flex; justify-content:space-between;">
    <div>
        <a href="/mealana/" style="color:white; font-weight:bold; font-size:18px; margin-right:30px; text-decoration:none;">
            🧶 MeaLana ERP
        </a>
        <a href="/mealana/artikel/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            📦 Artikel
        </a>
        <a href="/mealana/lager/uebersicht.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🏪 Lager
        </a>
        <a href="/mealana/lager/wareneingang.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            📥 Wareneingang
        </a>
        <a href="/mealana/lager/nachtrag_liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🏷️ Chargen-Nachtrag
        </a>
        <a href="/mealana/lieferanten/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🚚 Lieferanten
        </a>
        <a href="/mealana/hersteller/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🏭 Hersteller
        </a>
        <a href="/mealana/lagerbewegungen/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            📊 Lagerbewegungen
        </a>
        <a href="/mealana/merkmale/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🏷️ Merkmale
        </a>
        <a href="/mealana/varianten/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            🎨 Varianten
        </a>
        <a href="/mealana/achsen/liste.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            📐 Achsen
        </a>
    </div>
    <div>
        <span style="color:white; margin-right:15px;">
            👤 <?= htmlspecialchars($_SESSION['benutzer']['formularname']) ?>
        </span>
        <a href="/mealana/logout.php"
            style="color:white; margin-right:20px; text-decoration:none;">
            Logout
        </a>
    </div>
</nav>