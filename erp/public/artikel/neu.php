<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

// Session Daten holen
$fehler   = $_SESSION['fehler']   ?? [];
$erfolg   = $_SESSION['erfolg']   ?? null;
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

$service = new ArtikelService();

// Hersteller, Steuerklassen und Artikeltypen für Dropdowns laden
$hersteller    = $service->getAllHersteller();
$steuerklassen = $service->getAllSteuerklassen();
$artikelTypen  = $service->getAllArtikelTypen();
$alleEinheiten = $service->getAllEinheiten();

// Hilfsfunktion: war dieser Wert im letzten Submit?
function old(string $field, array $formdata, string $default = ''): string
{
    return htmlspecialchars($formdata[$field] ?? $default);
}

// Hilfsfunktion: war diese Option selected?
function selected(string $field, string $value, array $formdata): string
{
    return ($formdata[$field] ?? '') === $value ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Neuer Artikel – MeaLana ERP</title>
    <link rel="stylesheet" href="/mealana/css/app.css">
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Neuer Artikel</h1>

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

    <form action="speichern.php" method="POST">

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

        <button type="submit">Artikel speichern</button>

    </form>

    <script src="/mealana/js/artikel.js"></script>

    <script>
        // Beim Laden: gespeicherten Typ wiederherstellen
        const gespeicherterTyp = '<?= old('artikeltyp', $formdata) ?>';
        if (gespeicherterTyp) {
            zeigeFelder(gespeicherterTyp);
        }

        document.getElementById('artikeltyp').addEventListener('change', function() {
            zeigeFelder(this.value);
        });

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

        // Berechnungen beim Laden anstoßen falls Werte vorhanden
        berechneNetto();
        berechneGrundpreis();
    </script>

</body>

</html>