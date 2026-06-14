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

$zustandSuffixMap = [
    'neu'               => '',
    'gebraucht'         => 'GEB',
    'generalueberholt'  => 'GUE',
    'beschaedigt'       => 'BSC',
    'retour'            => 'RET',
    'demo'              => 'DMO',
    'muster'            => 'MST',
    'ausstellungsstueck'=> 'AST',
];

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
            <input type="text" name="artikelnummer" id="artikelnummer"
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
            <h2>Zustand</h2>

            <label>Zustand <span class="pflicht">*</span></label>
            <select name="zustand" id="zustand_select" onchange="zustandGeaendert(this.value)">
                <?php foreach ($zustandSuffixMap as $wert => $suffix): ?>
                    <option value="<?= $wert ?>"
                        <?= selected('zustand', $wert, $formdata) ?>>
                        <?= $wert === 'neu' ? 'Neu (Standard)' : htmlspecialchars(ucfirst(str_replace('_', ' ', $wert))) . ' (' . $suffix . ')' ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div id="vater_suche_bereich" style="display:none; margin-top:0.75rem; position:relative">
                <label>Vater-Artikel <span class="pflicht">*</span></label>
                <input type="text" id="vater_suche_input" class="erp-input"
                    placeholder="Mind. 2 Zeichen – Artikelnummer oder Name…"
                    autocomplete="off" style="width:100%"
                    oninput="vaterSuchen(this.value)">
                <div id="vater_suche_ergebnis"
                    style="border:1px solid #ddd;border-radius:4px;background:#fff;display:none;max-height:200px;overflow-y:auto;position:absolute;z-index:100;width:100%;box-shadow:0 4px 12px rgba(0,0,0,0.15)"></div>
                <input type="hidden" name="zustand_vater_id" id="zustand_vater_id"
                    value="<?= (int)($formdata['zustand_vater_id'] ?? 0) ?: '' ?>">
                <div id="vater_info" style="font-size:13px;color:var(--color-text-muted);margin-top:4px">
                    <?php if (!empty($formdata['zustand_vater_id'])): ?>
                        <?php
                            $vaterService = new ArtikelService();
                            $vaterInfo = $vaterService->findByIdSimple((int)$formdata['zustand_vater_id']);
                        ?>
                        <?php if ($vaterInfo): ?>
                            Vater: <strong><?= htmlspecialchars($vaterInfo['artikelnummer']) ?></strong>
                            – <?= htmlspecialchars($vaterInfo['name']) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
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
            <input type="text" name="kurzbeschreibung"
                value="<?= old('kurzbeschreibung', $formdata) ?>">

            <label>Langbeschreibung</label>
            <textarea name="beschreibung" rows="6"><?= old('beschreibung', $formdata) ?></textarea>

            <label>Technische Details</label>
            <textarea name="technische_details" rows="4"><?= old('technische_details', $formdata) ?></textarea>

            <label>Interne Notiz (nie öffentlich)</label>
            <textarea name="beschreibung_intern" rows="3"><?= old('beschreibung_intern', $formdata) ?></textarea>

        </div>

        <div class="gruppe">
            <h2>SEO</h2>

            <label>URL-Slug</label>
            <input type="text" name="url_slug"
                value="<?= old('url_slug', $formdata) ?>">

            <label>Meta-Titel (max. 70 Zeichen)</label>
            <input type="text" name="meta_titel" maxlength="70"
                value="<?= old('meta_titel', $formdata) ?>">

            <label>Meta-Beschreibung (max. 160 Zeichen)</label>
            <textarea name="meta_description" rows="3" maxlength="160"><?= old('meta_description', $formdata) ?></textarea>
        </div>


        <div class="gruppe">
            <h2>Sonstiges</h2>

            <label>Herkunftsland (2-stellig, z.B. AT)</label>
            <input type="text" name="herkunftsland" maxlength="2"
                value="<?= old('herkunftsland', $formdata) ?>">

            <label>TARIC-Code</label>
            <input type="text" name="taric_code"
                value="<?= old('taric_code', $formdata) ?>">

            <label>Chargenartikel</label>
            <?php if (isset($formdata['charge_pflicht']) && $formdata['charge_pflicht'] == '1') {
                $chargePflichtChecked = 'checked';
            } else {
                $chargePflichtChecked = '';
            } ?>
            <input type="checkbox" name="charge_pflicht" value="1" <?= $chargePflichtChecked ?>>

            <label>Überverkauf erlaubt</label>
            <?php if (isset($formdata['ueberverkauf_erlaubt']) && $formdata['ueberverkauf_erlaubt'] == '1') {
                $ueberverkaufChecked = 'checked';
            } else {
                $ueberverkaufChecked = '';
            } ?>
            <input type="checkbox" name="ueberverkauf_erlaubt" value="1" <?= $ueberverkaufChecked ?>>

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
        const zustandSuffixMap = <?= json_encode(array_filter($zustandSuffixMap)) ?>;
        let vaterArtikelNummer = '';

        function zustandGeaendert(wert) {
            const bereich = document.getElementById('vater_suche_bereich');
            const artnrInput = document.getElementById('artikelnummer');
            if (wert === 'neu') {
                bereich.style.display = 'none';
                artnrInput.readOnly = false;
                artnrInput.style.background = '';
                artnrInput.value = '';
                vaterArtikelNummer = '';
            } else {
                bereich.style.display = 'block';
                artnrInput.readOnly = true;
                artnrInput.style.background = '#f5f5f5';
                aktualisiereArtnr(wert);
            }
        }

        function aktualisiereArtnr(zustand) {
            const suffix = zustandSuffixMap[zustand] || '';
            const artnrInput = document.getElementById('artikelnummer');
            if (vaterArtikelNummer && suffix) {
                artnrInput.value = vaterArtikelNummer + '-' + suffix;
            } else {
                artnrInput.value = '';
            }
        }

        let vaterSuchTimer = null;
        function vaterSuchen(q) {
            clearTimeout(vaterSuchTimer);
            const ergebnisDiv = document.getElementById('vater_suche_ergebnis');
            if (q.length < 2) { ergebnisDiv.style.display = 'none'; return; }
            vaterSuchTimer = setTimeout(() => {
                fetch('artikel_vater_suche.php?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        if (!data.length) { ergebnisDiv.style.display = 'none'; return; }
                        ergebnisDiv.innerHTML = data.map(a =>
                            `<div style="padding:6px 10px;cursor:pointer;border-bottom:1px solid #eee;font-size:13px"
                                onmousedown="vaterAuswaehlen(${a.id}, '${a.artikelnummer.replace(/'/g,"\\'")}', '${a.name.replace(/'/g,"\\'")}')">
                                <strong>${a.artikelnummer}</strong> – ${a.name}
                            </div>`
                        ).join('');
                        ergebnisDiv.style.display = 'block';
                    });
            }, 250);
        }

        function vaterAuswaehlen(id, artnr, name) {
            document.getElementById('zustand_vater_id').value = id;
            document.getElementById('vater_suche_input').value = artnr + ' – ' + name;
            document.getElementById('vater_info').textContent = 'Vater: ' + artnr + ' – ' + name;
            document.getElementById('vater_suche_ergebnis').style.display = 'none';
            vaterArtikelNummer = artnr;
            aktualisiereArtnr(document.getElementById('zustand_select').value);
        }

        // Init: Falls Session-Daten vorhanden (nach Fehler)
        const initZustand = '<?= old('zustand', $formdata, 'neu') ?>';
        if (initZustand !== 'neu') {
            zustandGeaendert(initZustand);
        }

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