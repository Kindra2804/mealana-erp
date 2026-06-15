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

$vaterArtikel      = null;
$istZustandsArtikel = !empty($formdata['zustand_vater_id']);
if ($istZustandsArtikel) {
    $vaterArtikel = $service->findByIdSimple((int)$formdata['zustand_vater_id']);
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

$pageTitle      = 'Artikel bearbeiten';
$activeModule   = 'artikel';
$actionBarContent = <<<HTML
<button form="artikel-bearbeiten-form" type="submit" class="btn btn-primary btn-sm">💾 Speichern</button>
<a href="detail.php?id={$id}" class="btn btn-secondary btn-sm">Abbrechen</a>
HTML;
require_once __DIR__ . '/../includes/shell_top.php';
?>


<p style="margin-bottom:1rem">
    <a href="achsen_zuweisen.php?artikel_id=<?= $id ?>">⚙️ Achsen &amp; Werte konfigurieren</a>
</p>

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

    <form id="artikel-bearbeiten-form" action="aktualisieren.php" method="POST">

        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="gruppe">
            <h2>Stammdaten</h2>

            <label>Artikelnummer <span class="pflicht">*</span></label>
            <input type="text" name="artikelnummer" id="artikelnummer"
                value="<?= old('artikelnummer', $formdata) ?>"
                <?= $istZustandsArtikel ? 'readonly style="background:#f5f5f5"' : '' ?>
                required>

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

            <?php if ($istZustandsArtikel && $vaterArtikel): ?>
                <div style="font-size:13px;color:var(--color-text-muted);margin-bottom:0.75rem;padding:8px;background:#f8fafc;border-radius:4px;border:1px solid var(--color-border)">
                    Zustandsartikel von:
                    <a href="detail.php?id=<?= $vaterArtikel['id'] ?>">
                        <strong><?= htmlspecialchars($vaterArtikel['artikelnummer']) ?></strong>
                    </a>
                    – <?= htmlspecialchars($vaterArtikel['name']) ?>
                    <input type="hidden" name="zustand_vater_id" value="<?= (int)$formdata['zustand_vater_id'] ?>">
                </div>
            <?php else: ?>
                <input type="hidden" name="zustand_vater_id" value="">
            <?php endif; ?>

            <label>Zustand</label>
            <?php if ($istZustandsArtikel): ?>
                <select name="zustand" id="zustand_select" onchange="zustandBearbeitenGeaendert(this.value)">
                    <?php foreach ($zustandSuffixMap as $wert => $suffix): ?>
                        <?php if ($wert === 'neu') continue; ?>
                        <option value="<?= $wert ?>"
                            <?= selected('zustand', $wert, $formdata) ?>>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $wert))) ?>
                            (<?= $suffix ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="font-size:12px;color:var(--color-text-muted);margin-top:4px">
                    Änderung passt die Artikelnummer automatisch an. Lagerbewegung bitte manuell über den Lager-Tab durchführen.
                </p>
            <?php else: ?>
                <input type="text" value="Neu (Standard)" readonly style="background:#f5f5f5">
                <input type="hidden" name="zustand" value="neu">
            <?php endif; ?>
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

            <label>Auslaufartikel</label>
            <?php if (isset($formdata['ist_auslaufartikel']) && $formdata['ist_auslaufartikel'] == '1') {
                $auslaufartikelChecked = 'checked';
            } else {
                $auslaufartikelChecked = '';
            } ?>
            <input type="checkbox" name="ist_auslaufartikel" value="1" <?= $auslaufartikelChecked ?>>

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

    <div id="kat-backdrop" class="modal-backdrop" onclick="katModalSchliessen()">
        <div class="modal" onclick="event.stopPropagation()">
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
        /* Das Modal nutzt jetzt .modal-backdrop / .modal aus components.css */

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

        <?php if ($istZustandsArtikel && $vaterArtikel): ?>
        const zustandSuffixMap = <?= json_encode(array_filter($zustandSuffixMap)) ?>;
        const vaterArtikelNummer = '<?= htmlspecialchars($vaterArtikel['artikelnummer']) ?>';

        function zustandBearbeitenGeaendert(wert) {
            const suffix = zustandSuffixMap[wert] || '';
            const artnrInput = document.getElementById('artikelnummer');
            if (suffix) {
                artnrInput.value = vaterArtikelNummer + '-' + suffix;
            }
        }
        <?php endif; ?>
    </script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
