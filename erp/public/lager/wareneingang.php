<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';
$allelieferanten = (new LieferantenService())->findAll();

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

    <div id="deaktivierterArtikelDialog" style="display:none">
        <p>ACHTUNG !</p>
        <p> der Artikel <span id="artikelname"></span> wurde am <span id="aenderungsdatum"> </span> deaktiviert !</p>
        <p>Soll dieser Artikel wieder reaktiviert werden ?</p>
        <div>
            <button id="ja" onclick="reaktiviereUndBuche()">JA</button> <button id="Abbruch" onclick="abbruch()">Abbruch</button>
        </div>
    </div>


    <form action="wareneingang_speichern.php" method="POST">

        <div class="gruppe">
            <h2>Artikel / Variante</h2>

            <label>EAN scannen oder suchen</label>
            <div style="display:flex; gap:10px;">
                <input type="text" id="scan_suche" placeholder="EAN, Artikelnummer oder Name...">
                <button type="button" onclick="sucheVariante()">🔍</button>
            </div>

            <div id="variante_ergebnis" style="margin-top:10px;"></div>

            <!-- Wird per JS gefüllt wenn Artikel gefunden -->
            <input type="hidden" name="artikel_id" id="artikel_id">
            <input type="hidden" name="reaktivieren" id="reaktivieren" value="0">
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

            <label>Lieferant (optional)</label>
            <select name="lieferant_id">
                <option value="">– kein Lieferant –</option>
                <?php foreach ($allelieferanten as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>EK-Preis (optional)</label>
            <input type="number" step="0.0001" name="ek_preis" placeholder="0.0000">


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
        let inaktiverArtikel = null;

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
                        if (data[0].aktiv == 0) {
                            zeigeInaktivDialog(data[0]);
                        } else {
                            waehleVariante(data[0]);
                        };
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
            document.getElementById('artikel_id').value = v.id;

            document.getElementById('variante_ergebnis').innerHTML = `
        <div style="background:#d4edda; padding:10px; border-radius:4px;">
            ✅ <strong>${v.artikelnummer}</strong> – ${v.artikel_name}
            ${v.farbe_name ? '<br><small>' + v.farbe_name + '</small>' : ''}


            <button type="button" onclick="varianteZuruecksetzen()" 
                    style="float:right">✖</button>
        </div>
    `;
        }

        function varianteZuruecksetzen() {
            document.getElementById('artikel_id').value = '';
            document.getElementById('variante_ergebnis').innerHTML = '';
            document.getElementById('scan_suche').value = '';
            document.getElementById('scan_suche').focus();
            document.getElementById("reaktivieren").value = 0;
        }

        function zeigeInaktivDialog(v) {
            inaktiverArtikel = v;

            document.getElementById("deaktivierterArtikelDialog").style.display = "block";
            document.getElementById('artikelname').innerHTML = v.artikel_name;
            document.getElementById('aenderungsdatum').innerHTML =
                new Date(v.geaendert_am).toLocaleDateString('de-AT');

        }

        function reaktiviereUndBuche() {
            document.getElementById("reaktivieren").value = 1;
            waehleVariante(inaktiverArtikel);
            document.getElementById("deaktivierterArtikelDialog").style.display = "none";
        }

        function abbruch() {
            document.getElementById("deaktivierterArtikelDialog").style.display = "none";
            varianteZuruecksetzen();
        }
    </script>

</body>

</html>