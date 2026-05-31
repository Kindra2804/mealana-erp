<?php
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/core/Database.php';

// Hersteller und Steuerklassen für Dropdowns laden
$db = Database::getInstance();
$hersteller = $db->query("SELECT id, name FROM hersteller WHERE 1 ORDER BY name")->fetchAll();
$steuerklassen = $db->query("SELECT id, name, satz FROM steuerklassen WHERE aktiv = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Neuer Artikel – MeaLana ERP</title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .gruppe {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
        }

        label {
            display: block;
            margin-bottom: 4px;
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 12px;
            box-sizing: border-box;
        }

        .pflicht {
            color: red;
        }

        .versteckt {
            display: none;
        }

        h2 {
            color: #333;
        }

        button {
            background: #4a7cb5;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <h1>Neuer Artikel</h1>

    <form action="speichern.php" method="POST">

        <div class="gruppe">
            <h2>Stammdaten</h2>

            <label>Artikelnummer <span class="pflicht">*</span></label>
            <input type="text" name="artikelnummer" required>

            <label>Artikeltyp <span class="pflicht">*</span></label>
            <select name="artikeltyp" id="artikeltyp">
                <option value="">– bitte wählen –</option>
                <option value="GARN">Garn</option>
                <option value="NADEL">Nadel</option>
                <option value="METERWARE">Meterware</option>
                <option value="DOWNLOAD">Download</option>
                <option value="SET">Set</option>
                <option value="STANDARD">Standard</option>
            </select>

            <label>Name <span class="pflicht">*</span></label>
            <input type="text" name="name" required>

            <label>Hersteller</label>
            <select name="hersteller_id">
                <option value="">– kein Hersteller –</option>
                <?php foreach ($hersteller as $h): ?>
                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Steuerklasse <span class="pflicht">*</span></label>
            <select name="steuerklasse_id">
                <?php foreach ($steuerklassen as $s): ?>
                    <option value="<?= $s['id'] ?>" data-satz="<?= $s['satz'] ?>">
                        <?= htmlspecialchars($s['name']) ?> (<?= $s['satz'] ?>%)
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Gewicht Artikel (kg)</label>
            <input type="number" step="0.001" name="gewicht_artikel">

            <label>Gewicht Versand (kg)</label>
            <input type="number" step="0.001" name="gewicht_versand">
        </div>

        <div class="gruppe">
            <h2>Preis</h2>

            <label>Brutto-VK <span class="pflicht">*</span></label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="number" step="0.01" name="brutto_vk"
                    id="brutto_vk" style="flex:1">
                <button type="button" onclick="oeffnePreistabelle()"
                    style="width:auto; padding: 8px 12px;">+</button>
            </div>

            <label>Netto-VK (berechnet)</label>
            <input type="number" step="0.0001" name="netto_vk"
                id="netto_vk" readonly style="background:#f5f5f5">

            <!-- Grundpreis Anzeige (nur GARN) -->
            <div id="grundpreis_container" class="versteckt">
                <label>Grundpreis (berechnet)</label>
                <div id="grundpreis_anzeige"
                    style="font-size:18px; color:#4a7cb5; font-weight:bold; padding:8px;">
                    – wird berechnet –
                </div>
                <label>Grundpreis Bezugsmenge (g)</label>
                <input type="number" name="grundpreis_bezugsmenge" value="100">
                <label>Grundpreis anzeigen im Shop</label>
                <select name="grundpreis_anzeigen">
                    <option value="1">Ja</option>
                    <option value="0">Nein</option>
                </select>
            </div>
        </div>

        <!-- Nur für GARN, NADEL, METERWARE -->
        <div class="gruppe versteckt" id="felder-physisch">
            <h2>Maße & Gewicht</h2>

            <label>Einheit</label>
            <select name="einheit">
                <option value="Knäuel">Knäuel</option>
                <option value="Meter">Meter</option>
                <option value="Gramm">Gramm</option>
                <option value="Stk">Stück</option>
            </select>

            <label>Inhalt/Menge</label>
            <input type="number" step="0.001" name="inhalt_menge">

            <label>Inhalt-Einheit (g, m, ml...)</label>
            <input type="text" name="inhalt_einheit">

        </div>

        <div class="gruppe">
            <h2>Beschreibung</h2>

            <label>Kurzbeschreibung</label>
            <input type="text" name="beschreibung_kurz">

            <label>Langbeschreibung</label>
            <textarea name="beschreibung_lang" rows="6"></textarea>
        </div>

        <div class="gruppe">
            <h2>Sonstiges</h2>

            <label>Herkunftsland (2-stellig, z.B. AT)</label>
            <input type="text" name="herkunftsland" maxlength="2">

            <label>TARIC-Code</label>
            <input type="text" name="taric_code">

            <label>Varianten-Darstellung</label>
            <select name="varianten_darstellung">
                <option value="swatches">Farb-Swatches</option>
                <option value="bilder">Bilder</option>
                <option value="dropdown">Dropdown</option>
            </select>

            <label>Aktiv</label>
            <select name="aktiv">
                <option value="1">Ja</option>
                <option value="0">Nein</option>
            </select>
        </div>

        <button type="submit">Artikel speichern</button>

    </form>

    <script>
        document.getElementById('artikeltyp').addEventListener('change', function() {
            const typ = this.value;

            const physisch = document.getElementById('felder-physisch');

            // Alle verstecken
            physisch.classList.add('versteckt');

            // Je nach Typ zeigen
            if (['GARN', 'NADEL', 'METERWARE'].includes(typ)) {
                physisch.classList.remove('versteckt');
            }
            if (typ === 'GARN' || typ === 'METERWARE') {
                document.getElementById('grundpreis_container')
                    .classList.remove('versteckt');
            } else {
                document.getElementById('grundpreis_container')
                    .classList.add('versteckt');
            }
        });

        // Netto berechnen
        document.getElementById('brutto_vk')
            .addEventListener('input', function() {
                berechneNetto();
                berechneGrundpreis();
            });

        document.querySelector('[name="steuerklasse_id"]')
            .addEventListener('change', berechneNetto);

        document.querySelector('[name="grundpreis_bezugsmenge"]')
            ?.addEventListener('input', berechneGrundpreis);

        document.querySelector('[name="inhalt_menge"]')
            ?.addEventListener('input', berechneGrundpreis);

        document.querySelector('[name="inhalt_einheit"]')
            ?.addEventListener('input', berechneGrundpreis);

        if (typ === 'METERWARE') {
            document.getElementById('bezugsmenge_label').textContent =
                'Grundpreis Bezugsmenge (m)';
            document.querySelector('[name="grundpreis_bezugsmenge"]').value = 1;
        } else if (typ === 'GARN') {
            document.getElementById('bezugsmenge_label').textContent =
                'Grundpreis Bezugsmenge (g)';
            document.querySelector('[name="grundpreis_bezugsmenge"]').value = 100;
        }

        function berechneNetto() {
            const brutto = parseFloat(document.getElementById('brutto_vk').value) || 0;
            const steuerSelect = document.querySelector('[name="steuerklasse_id"]');
            const satz = parseFloat(
                steuerSelect.options[steuerSelect.selectedIndex].dataset.satz
            ) || 20;

            if (brutto > 0) {
                document.getElementById('netto_vk').value =
                    (brutto / (1 + satz / 100)).toFixed(4);
            }
        }

        //Grundpreis berechnen
        function berechneGrundpreis() {
            const brutto = parseFloat(document.getElementById('brutto_vk').value) || 0;
            let menge = parseFloat(document.querySelector('[name="inhalt_menge"]')?.value) || 0;
            const einheit = document.querySelector('[name="inhalt_einheit"]')?.value.toLowerCase().trim();
            const bezug = parseFloat(document.querySelector('[name="grundpreis_bezugsmenge"]')?.value) || 100;

            // Umrechnung auf Basiseinheit
            if (einheit === 'kg') menge = menge * 1000; // kg → g
            if (einheit === 'l') menge = menge * 1000; // l  → ml
            if (einheit === 'm') menge = menge * 100; // m  → cm

            if (brutto > 0 && menge > 0) {
                const grundpreis = (brutto / menge) * bezug;
                const einheitLabel = ['m', 'cm'].includes(einheit) ? 'm' : 'g';
                document.getElementById('grundpreis_anzeige').textContent =
                    grundpreis.toFixed(2) + '€ / ' + bezug + einheitLabel;
            } else {
                document.getElementById('grundpreis_anzeige').textContent =
                    '– wird berechnet –';
            }
        }



        function oeffnePreistabelle() {
            // kommt später – Modal mit allen Kundengruppen
            alert('Preistabelle kommt bald!');
        }
    </script>

</body>


</html>