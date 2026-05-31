<?php
session_start();
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$fehler  = $_SESSION['fehler']  ?? [];
$erfolg  = $_SESSION['erfolg']  ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$db = Database::getInstance();
$lager = $db->query("SELECT id, name FROM lager WHERE aktiv = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Wareneingang – MeaLana ERP</title>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>

    <h1>📦 Wareneingang</h1>

    <?php if ($erfolg): ?>
        <div class="erfolg-box"><?= htmlspecialchars($erfolg) ?></div>
    <?php endif; ?>

    <?php if (!empty($fehler)): ?>
        <div class="fehler-box">
            <ul><?php foreach ($fehler as $f): ?>
                    <li><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="wareneingang_speichern.php" method="POST">

        <div class="gruppe">
            <h2>Variante</h2>

            <label>EAN scannen oder suchen</label>
            <div style="display:flex; gap:10px;">
                <input type="text" id="scan_suche" placeholder="EAN, Artikelnummer oder Name...">
                <button type="button" onclick="sucheVariante()">🔍</button>
            </div>

            <div id="variante_ergebnis" style="margin-top:10px;"></div>

            <!-- Wird per JS gefüllt wenn Variante gefunden -->
            <input type="hidden" name="artikel_varianten_id" id="artikel_varianten_id">
        </div>

        <div class="gruppe">
            <h2>Lager & Menge</h2>

            <label>Lager *</label>
            <select name="lager_id">
                <option value="">– bitte wählen –</option>
                <?php foreach ($lager as $l): ?>
                    <option value="<?= $l['id'] ?>">
                        <?= htmlspecialchars($l['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Menge *</label>
            <input type="number" step="0.001" name="menge" min="0.001">

            <label>Charge</label>
            <input type="text" name="charge" placeholder="Leer = unbekannt">

            <label>Bewegungstyp</label>
            <select name="bewegungstyp">
                <option value="eingang">Wareneingang</option>
                <option value="korrektur">Korrektur</option>
                <option value="inventur">Inventur</option>
            </select>

            <label>Referenz (z.B. Lieferschein-Nr.)</label>
            <input type="text" name="referenz">

            <label>Notiz</label>
            <textarea name="notiz" rows="3"></textarea>
        </div>

        <button type="submit">💾 Wareneingang buchen</button>

    </form>

    <script>
        document.getElementById('scan_suche')
            .addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sucheVariante();
                }
            });

        function sucheVariante() {
            const q = document.getElementById('scan_suche').value.trim();
            if (q.length < 2) return;

            fetch('variante_suche.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    const div = document.getElementById('variante_ergebnis');

                    if (data.length === 0) {
                        div.innerHTML = '<p style="color:red">Keine Variante gefunden!</p>';
                        return;
                    }

                    if (data.length === 1) {
                        // Direkt auswählen
                        waehleVariante(data[0]);
                        return;
                    }

                    // Mehrere Treffer – Auswahl anzeigen
                    div.innerHTML = data.map(v => `
                <div style="border:1px solid #ddd; padding:8px; margin:4px; cursor:pointer;"
                     onclick="waehleVariante(${JSON.stringify(v).replace(/"/g, '&quot;')})">
                    <strong>${v.artikelnummer}</strong> – 
                    ${v.farbe_name} – ${v.artikel_name}
                </div>
            `).join('');
                });
        }

        function waehleVariante(v) {
            document.getElementById('artikel_varianten_id').value = v.id;
            document.getElementById('variante_ergebnis').innerHTML = `
        <div style="background:#d4edda; padding:10px; border-radius:4px;">
            ✅ <strong>${v.artikelnummer}</strong> – 
            ${v.farbe_name} – ${v.artikel_name}
            <button type="button" onclick="varianteZuruecksetzen()" 
                    style="float:right">✖</button>
        </div>
    `;
        }

        function varianteZuruecksetzen() {
            document.getElementById('artikel_varianten_id').value = '';
            document.getElementById('variante_ergebnis').innerHTML = '';
            document.getElementById('scan_suche').value = '';
            document.getElementById('scan_suche').focus();
        }
    </script>

</body>

</html>