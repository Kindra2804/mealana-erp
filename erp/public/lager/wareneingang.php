<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$allelieferanten = (new LieferantenService())->findAll();

$fehler = $_SESSION['fehler'] ?? [];
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$db    = Database::getInstance();
$lager = $db->query("SELECT id, name FROM lager WHERE aktiv = 1")->fetchAll();

$pageTitle        = 'Wareneingang';
$activeModule     = 'lager';
$actionBarContent = <<<HTML
    <a href="/mealana/lager/uebersicht.php" class="btn btn-secondary btn-sm">← Lagerübersicht</a>
    <a href="/mealana/wareneingang/index.php" class="btn btn-secondary btn-sm">← Wareneingang</a>
HTML;


require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if (!empty($fehler)): ?>
    <div class="card" style="border-left:4px solid var(--color-danger);margin-bottom:12px">
        <ul style="margin:0;padding-left:18px;color:var(--color-danger)">
            <?php foreach ($fehler as $f): ?>
                <li><?= htmlspecialchars($f) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($erfolg): ?>
    <div class="card" style="border-left:4px solid var(--color-success);margin-bottom:12px">
        <p style="margin:0;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></p>
    </div>
<?php endif; ?>

<!-- Dialog: deaktivierter Artikel -->
<div id="deaktivierterArtikelDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
    <div class="card" style="width:400px;max-width:90vw">
        <h3 style="margin-top:0">Artikel deaktiviert</h3>
        <p>Der Artikel <strong id="artikelname"></strong> wurde am <strong id="aenderungsdatum"></strong> deaktiviert.</p>
        <p>Soll er beim Buchen reaktiviert werden?</p>
        <div style="display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-secondary btn-sm" onclick="abbruch()">Abbrechen</button>
            <button class="btn btn-primary btn-sm" onclick="reaktiviereUndBuche()">Ja, reaktivieren</button>
        </div>
    </div>
</div>

<div class="card" style="max-width:640px">
    <form action="wareneingang_speichern.php" method="POST">

        <h3 style="margin-top:0">Artikel / Variante</h3>
        <div style="display:flex;gap:8px;margin-bottom:8px">
            <input type="text" id="scan_suche" class="erp-input" style="flex:1"
                placeholder="EAN, Artikelnummer oder Name …">
            <button type="button" class="btn btn-secondary btn-sm" onclick="sucheVariante()">Suchen</button>
        </div>
        <div id="variante_ergebnis" style="margin-bottom:16px"></div>
        <input type="hidden" name="artikel_id" id="artikel_id">
        <input type="hidden" name="reaktivieren" id="reaktivieren" value="0">

        <hr style="border:none;border-top:1px solid var(--color-border);margin:16px 0">

        <h3>Lager &amp; Menge</h3>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 16px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Lager *</label>
                <select name="lager_id" class="erp-select" style="width:100%">
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($lager as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Menge *</label>
                <input type="number" step="0.001" name="menge" min="0.001" class="erp-input" style="width:100%">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Lieferant</label>
                <select name="lieferant_id" class="erp-select" style="width:100%">
                    <option value="">– kein Lieferant –</option>
                    <?php foreach ($allelieferanten as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">EK-Preis</label>
                <input type="number" step="0.0001" name="ek_preis" placeholder="0.0000" class="erp-input" style="width:100%">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Charge</label>
                <input type="text" name="charge" placeholder="Leer = unbekannt" class="erp-input" style="width:100%">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Bewegungstyp</label>
                <select name="bewegungstyp" class="erp-select" style="width:100%">
                    <option value="eingang">Wareneingang</option>
                    <option value="korrektur">Korrektur</option>
                    <option value="inventur">Inventur</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Referenz (z.B. Lieferschein-Nr.)</label>
                <input type="text" name="referenz" class="erp-input" style="width:100%">
            </div>
        </div>

        <div style="margin-top:12px">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Notiz</label>
            <textarea name="notiz" rows="3" class="erp-input" style="width:100%;resize:vertical"></textarea>
        </div>

        <div style="margin-top:20px">
            <button type="submit" class="btn btn-primary">Wareneingang buchen</button>
        </div>
    </form>
</div>

<script>
    let inaktiverArtikel = null;

    document.getElementById('scan_suche').addEventListener('keypress', function(e) {
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
                    div.innerHTML = '<p style="color:var(--color-danger);margin:0">Keine Variante gefunden.</p>';
                    return;
                }
                if (data.length === 1) {
                    if (data[0].aktiv == 0) {
                        zeigeInaktivDialog(data[0]);
                    } else {
                        waehleVariante(data[0]);
                    }
                    return;
                }
                div.innerHTML = data.map(v => `
                <div style="border:1px solid var(--color-border);padding:8px 12px;margin-bottom:4px;border-radius:4px;cursor:pointer"
                     onclick="waehleVariante(${JSON.stringify(v).replace(/"/g, '&quot;')})">
                    <strong>${v.artikelnummer}</strong> – ${v.artikel_name}
                </div>
            `).join('');
            });
    }

    function waehleVariante(v) {
        document.getElementById('artikel_id').value = v.id;
        document.getElementById('variante_ergebnis').innerHTML = `
        <div style="background:var(--color-success-light,#d4edda);padding:10px 12px;border-radius:4px;display:flex;justify-content:space-between;align-items:center">
            <span>✅ <strong>${v.artikelnummer}</strong> – ${v.artikel_name}</span>
            <button type="button" class="btn btn-secondary btn-sm" onclick="varianteZuruecksetzen()">✖</button>
        </div>`;
    }

    function varianteZuruecksetzen() {
        document.getElementById('artikel_id').value = '';
        document.getElementById('variante_ergebnis').innerHTML = '';
        document.getElementById('scan_suche').value = '';
        document.getElementById('scan_suche').focus();
        document.getElementById('reaktivieren').value = 0;
    }

    function zeigeInaktivDialog(v) {
        inaktiverArtikel = v;
        document.getElementById('artikelname').textContent = v.artikel_name;
        document.getElementById('aenderungsdatum').textContent = new Date(v.geaendert_am).toLocaleDateString('de-AT');
        document.getElementById('deaktivierterArtikelDialog').style.display = 'flex';
    }

    function reaktiviereUndBuche() {
        document.getElementById('reaktivieren').value = 1;
        waehleVariante(inaktiverArtikel);
        document.getElementById('deaktivierterArtikelDialog').style.display = 'none';
    }

    function abbruch() {
        document.getElementById('deaktivierterArtikelDialog').style.display = 'none';
        varianteZuruecksetzen();
    }
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>