<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

// ID aus URL holen
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: liste.php');
    exit;
}

// Session Daten holen
$fehler   = $_SESSION['fehler']   ?? [];
$erfolg   = $_SESSION['erfolg']   ?? null;
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

$service = new ArtikelService();
$alleKategorien   = $service->getAlleKategorien();
$zugewieseneIds   = array_column($service->getKategorienFuerArtikel($id), 'id');
$artikelTypen     = $service->getAllArtikelTypen();
$alleEinheiten = $service->getAllEinheiten();


// Artikel aus DB laden – aber Session hat Vorrang bei Fehler!
if (empty($formdata)) {
    $artikel = $service->findById($id);

    if ($artikel === false) {
        header('Location: liste.php');
        exit;
    }
    $formdata = $artikel;

    $codes = $service->getCodesByArtikelId($id);
    $formdata['ean_gtin13'] = '';
    foreach ($codes as $c) {
        if ($c['typ'] === 'GTIN13') {
            $formdata['ean_gtin13'] = $c['code'];
            break;
        }
    }
}


// Hersteller und Steuerklassen für Dropdowns laden
$hersteller   = $service->getAllHersteller();
$steuerklassen = $service->getAllSteuerklassen();

// Hilfsfunktion: war dieser Wert im letzten Submit?
function old(string $field, array $formdata, string $default = ''): string
{
    $value = $formdata[$field] ?? $default;
    return htmlspecialchars((string)($value ?? $default));
}

// Hilfsfunktion: war diese Option selected?
function selected(string $field, string $value, array $formdata): string
{
    return ((string)($formdata[$field] ?? '')) === $value ? 'selected' : '';
}
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

        .fehler-box {
            background: #f8d7da;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .erfolg-box {
            background: #d4edda;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        /* Kategorie-Modal */
        #kat-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        #kat-modal {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            min-width: 320px;
            max-height: 80vh;
            overflow-y: auto;
        }

        #kat-checkboxen label {
            display: block;
            margin-bottom: 0.4rem;
        }

        #kat-aktionen {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .chip {
            display: inline-block;
            background: #e0e0e0;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            margin: 0.2rem;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Artikel bearbeiten</h1>

    <?php if ($erfolg): ?>
        <div class="erfolg-box"><?= htmlspecialchars($erfolg) ?></div>
    <?php endif; ?>

    <?php if (!empty($fehler)): ?>
        <div class="fehler-box">
            <strong>Bitte korrigiere folgende Fehler:</strong>
            <ul>
                <?php foreach ($fehler as $f): ?>
                    <li><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="aktualisieren.php" method="POST">

        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="gruppe">
            <h2>Stammdaten</h2>

            <label>Artikelnummer <span class="pflicht">*</span></label>
            <input type="text" name="artikelnummer"
                value="<?= old('artikelnummer', $formdata) ?>" required>

            <label>Artikeltyp <span class="pflicht">*</span></label>
            <select name="artikeltyp" id="artikeltyp">
                <option value="">– bitte wählen –</option>
                <?php foreach ($artikelTypen as $typ): ?>
                    <option value="<?= htmlspecialchars($typ['code']) ?>"
                        <?= selected('artikeltyp', $typ['code'], $formdata) ?>>
                        <?= htmlspecialchars($typ['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Name <span class="pflicht">*</span></label>
            <input type="text" name="name"
                value="<?= old('name', $formdata) ?>" required>

            <label>Hersteller</label>
            <select name="hersteller_id">
                <option value="">– kein Hersteller –</option>
                <?php foreach ($hersteller as $h): ?>
                    <option value="<?= $h['id'] ?>"
                        <?= selected('hersteller_id', (string)$h['id'], $formdata) ?>>
                        <?= htmlspecialchars($h['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Steuerklasse <span class="pflicht">*</span></label>
            <select name="steuerklasse_id">
                <?php foreach ($steuerklassen as $s): ?>
                    <option value="<?= $s['id'] ?>"
                        data-satz="<?= $s['satz'] ?>"
                        <?= selected('steuerklasse_id', (string)$s['id'], $formdata) ?>>
                        <?= htmlspecialchars($s['name']) ?> (<?= $s['satz'] ?>%)
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Gewicht Artikel (kg)</label>
            <input type="number" step="0.001" name="gewicht_artikel"
                value="<?= old('gewicht_artikel', $formdata) ?>">

            <label>Gewicht Versand (kg)</label>
            <input type="number" step="0.001" name="gewicht_versand"
                value="<?= old('gewicht_versand', $formdata) ?>">
        </div>

        <div class="gruppe">
            <h2>Preis</h2>

            <label>Brutto-VK <span class="pflicht">*</span></label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="number" step="0.01" name="brutto_vk" id="brutto_vk"
                    style="flex:1" value="<?= old('brutto_vk', $formdata) ?>">
                <button type="button" onclick="oeffnePreistabelle()"
                    style="width:auto; padding: 8px 12px;">+</button>
            </div>

            <label>Netto-VK (berechnet)</label>
            <input type="number" step="0.0001" name="netto_vk" id="netto_vk"
                readonly style="background:#f5f5f5"
                value="<?= old('netto_vk', $formdata) ?>">

            <div id="grundpreis_container" class="versteckt">
                <label>Grundpreis (berechnet)</label>
                <div id="grundpreis_anzeige"
                    style="font-size:18px; color:#4a7cb5; font-weight:bold; padding:8px;">
                    – wird berechnet –
                </div>
                <label id="bezugsmenge_label">Grundpreis Bezugsmenge (g)</label>
                <input type="number" name="grundpreis_bezugsmenge"
                    value="<?= old('grundpreis_bezugsmenge', $formdata, '100') ?>">
                <label>Grundpreis anzeigen im Shop</label>
                <select name="grundpreis_anzeigen">
                    <option value="1" <?= selected('grundpreis_anzeigen', '1', $formdata) ?>>Ja</option>
                    <option value="0" <?= selected('grundpreis_anzeigen', '0', $formdata) ?>>Nein</option>
                </select>
            </div>
        </div>

        <div class="gruppe">
            <h2>Barcodes / EAN</h2>
            <label>GTIN13</label>
            <input type="text" name="ean_gtin13"
                value="<?= old('ean_gtin13', $formdata) ?>" maxlength="13">
        </div>

        <div class="gruppe">
            <h2>Einheit</h2>
            <label>Einheit</label>
            <select name="einheit_id">
                <?php foreach ($alleEinheiten as $e): ?>
                    <option value="<?= $e['id'] ?>"
                        <?= selected('einheit_id', (string)$e['id'], $formdata) ?>>
                        <?= htmlspecialchars($e['name']) ?>
                        <?= $e['kuerzel'] ? ' (' . htmlspecialchars($e['kuerzel']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="gruppe versteckt" id="felder-physisch">
            <h2>Inhalt</h2>
            <label>Inhalt/Menge</label>
            <input type="number" step="0.001" name="inhalt_menge"
                value="<?= old('inhalt_menge', $formdata) ?>">

            <label>Inhalt-Einheit (g, m, ml...)</label>
            <input type="text" name="inhalt_einheit"
                value="<?= old('inhalt_einheit', $formdata) ?>">
        </div>

        <div class="gruppe">
            <h2>Beschreibung</h2>

            <label>Kurzbeschreibung</label>
            <input type="text" name="beschreibung_kurz"
                value="<?= old('beschreibung_kurz', $formdata) ?>">

            <label>Langbeschreibung</label>
            <textarea name="beschreibung_lang" rows="6"><?= old('beschreibung_lang', $formdata) ?></textarea>
        </div>

        <div class="gruppe">
            <h2>Sonstiges</h2>

            <label>Herkunftsland (2-stellig, z.B. AT)</label>
            <input type="text" name="herkunftsland" maxlength="2"
                value="<?= old('herkunftsland', $formdata) ?>">

            <label>TARIC-Code</label>
            <input type="text" name="taric_code"
                value="<?= old('taric_code', $formdata) ?>">

            <label>Varianten-Darstellung</label>
            <select name="varianten_darstellung">
                <option value="swatches" <?= selected('varianten_darstellung', 'swatches',  $formdata) ?>>Farb-Swatches</option>
                <option value="bilder" <?= selected('varianten_darstellung', 'bilder',    $formdata) ?>>Bilder</option>
                <option value="dropdown" <?= selected('varianten_darstellung', 'dropdown',  $formdata) ?>>Dropdown</option>
            </select>

            <label>Chargenartikel</label>
            <?php if (isset($formdata['charge_pflicht']) && $formdata['charge_pflicht'] == '1') {
                $chargePflichtChecked = 'checked';
            } else {
                $chargePflichtChecked = '';
            } ?>
            <input type="checkbox" name="charge_pflicht" value="1" <?= $chargePflichtChecked ?>>

            <label>Aktiv</label>
            <select name="aktiv">
                <option value="1" <?= selected('aktiv', '1', $formdata) ?>>Ja</option>
                <option value="0" <?= selected('aktiv', '0', $formdata) ?>>Nein</option>
            </select>
        </div>

        <div class="gruppe">
            <h2>Kategorien</h2>
            <div id="kat-chips">
                <?php foreach ($alleKategorien as $k): ?>
                    <?php if (in_array($k['id'], $zugewieseneIds)): ?>
                        <span class="chip"><?= htmlspecialchars($k['name']) ?></span>
                        <input type="hidden" name="kategorien[]" value="<?= $k['id'] ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <button type="button" onclick="katModalOeffnen()">Kategorien bearbeiten</button>
        </div>

        <button type="submit">Artikel updaten</button>

    </form>

    <div id="kat-backdrop" style="display:none" onclick="katModalSchliessen()">
        <div id="kat-modal" onclick="event.stopPropagation()">
            <h3>Kategorien zuweisen</h3>

            <div id="kat-checkboxen">
                <?php foreach ($alleKategorien as $k): ?>
                    <label>
                        <input type="checkbox"
                            value="<?= $k['id'] ?>"
                            data-name="<?= htmlspecialchars($k['name']) ?>">
                        <?= htmlspecialchars($k['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <hr>

            <div id="kat-neu">
                <input type="text" id="neue-kat-name" placeholder="Neue Kategorie...">
                <button type="button" onclick="katAnlegen()">Anlegen</button>
            </div>

            <div id="kat-aktionen">
                <button type="button" onclick="katUebernehmen()">Übernehmen</button>
                <button type="button" onclick="katModalSchliessen()">Abbrechen</button>
            </div>
        </div>
    </div>

    <script>
        // Beim Laden: gespeicherten Typ wiederherstellen
        const gespeicherterTyp = '<?= old('artikeltyp', $formdata) ?>';
        if (gespeicherterTyp) {
            zeigeFelder(gespeicherterTyp);
        }

        document.getElementById('artikeltyp').addEventListener('change', function() {
            zeigeFelder(this.value);
        });

        function zeigeFelder(typ) {
            const physisch = document.getElementById('felder-physisch');
            const grundpreis = document.getElementById('grundpreis_container');

            physisch.classList.add('versteckt');
            grundpreis.classList.add('versteckt');

            if (['GARN', 'NADEL', 'METERWARE'].includes(typ)) {
                physisch.classList.remove('versteckt');
            }
            if (typ === 'GARN' || typ === 'METERWARE') {
                grundpreis.classList.remove('versteckt');
            }

            // Bezugsmenge Label anpassen
            const label = document.getElementById('bezugsmenge_label');
            const bezugInput = document.querySelector('[name="grundpreis_bezugsmenge"]');
            if (typ === 'METERWARE') {
                label.textContent = 'Grundpreis Bezugsmenge (m)';
                if (!bezugInput.value) bezugInput.value = 1;
            } else if (typ === 'GARN') {
                label.textContent = 'Grundpreis Bezugsmenge (g)';
                if (!bezugInput.value) bezugInput.value = 100;
            }

            berechneGrundpreis();
        }

        document.getElementById('brutto_vk').addEventListener('input', function() {
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

        function berechneGrundpreis() {
            const brutto = parseFloat(document.getElementById('brutto_vk').value) || 0;
            let menge = parseFloat(document.querySelector('[name="inhalt_menge"]')?.value) || 0;
            const einheit = document.querySelector('[name="inhalt_einheit"]')?.value.toLowerCase().trim();
            const bezug = parseFloat(document.querySelector('[name="grundpreis_bezugsmenge"]')?.value) || 100;

            if (einheit === 'kg') menge = menge * 1000;
            if (einheit === 'l') menge = menge * 1000;
            if (einheit === 'm') menge = menge * 100;

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
            alert('Preistabelle kommt bald!');
        }

        // Berechnungen beim Laden anstoßen falls Werte vorhanden
        berechneNetto();
        berechneGrundpreis();

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
                span.className = 'chip';
                span.textContent = cb.dataset.name;
                chips.appendChild(span);
            });
            katModalSchliessen();
        }

        async function katAnlegen() {
            const katName = document.getElementById('neue-kat-name').value?.trim();
            if (!katName) {
                return;
            }

            const response = await fetch('kategorie_neu.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'name=' + encodeURIComponent(katName)
            });
            const data = await response.json();

            if (!data.erfolg) {
                alert(data.fehler);
                return;
            }

            const label = document.createElement('label');
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = data.id;
            cb.dataset.name = data.name;
            cb.checked = true;
            label.appendChild(cb);
            label.appendChild(document.createTextNode(' ' + data.name));
            document.getElementById('kat-checkboxen').appendChild(label);

            document.getElementById('neue-kat-name').value = '';
        }
    </script>

</body>

</html>