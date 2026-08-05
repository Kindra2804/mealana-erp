<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$service        = new ArtikelService();
$kategorienBaum = $service->getKategorienBaum();

// Shop-Sichtbarkeit-Bereich im Bearbeiten-Modal: Liste aktiver Shops +
// bereits gesetzte Ausschlüsse (kategorie_shops.ausgeschlossen), als JS-Map
// mitgeliefert statt für jede Kategorie einzeln nachzuladen.
$db             = Database::getInstance();
$alleShops      = $db->query("SELECT id, name FROM shops WHERE ist_aktiv = 1 ORDER BY id")->fetchAll();
$ausschluesse   = $db->query("SELECT kategorie_id, shop_id, ausgeschlossen FROM kategorie_shops WHERE ausgeschlossen = 1")->fetchAll();

$pageTitle    = 'Kategorien verwalten';
$activeModule = 'artikel';

$actionBarContent = <<<HTML
<button onclick="katNeuOeffnen(null, null)" class="btn btn-primary btn-sm">+ Neue Hauptkategorie</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

function renderVerwaltungsKnoten(array $knoten, int $tiefe, int $pos, int $total): void
{
    $einzug  = $tiefe * 24;
    $istBlatt = empty($knoten['kinder']);
    $anzahl  = (int)($knoten['artikel_anzahl'] ?? 0);
    ?>
    <?php
        $istAktionKat   = (int)($knoten['ist_aktions_kategorie'] ?? 0);
        $aktionAktiv    = (int)($knoten['aktion_aktiv']   ?? 0);
        $aktionZukunft  = (int)($knoten['aktion_zukunft'] ?? 0);
        $aktionInfo     = $knoten['aktion_info'] ?? '';
        $uhrsymbol      = '';
        $zeileDimmed    = false;
        if ($istAktionKat) {
            if ($aktionAktiv) {
                $titel      = 'Aktions-Kategorie (aktiv)' . ($aktionInfo ? "\n" . $aktionInfo : '');
                $uhrsymbol  = ' <span title="' . htmlspecialchars($titel) . '" style="color:#e67e22">⏰</span>';
            } elseif ($aktionZukunft) {
                $titel      = 'Aktions-Kategorie (geplant)' . ($aktionInfo ? "\n" . $aktionInfo : '');
                $uhrsymbol  = ' <span title="' . htmlspecialchars($titel) . '" style="color:#e67e22">⏰</span>';
                $zeileDimmed = true;
            } else {
                $titel      = 'Aktions-Kategorie (abgelaufen)' . ($aktionInfo ? "\n" . $aktionInfo : '');
                $uhrsymbol  = ' <span title="' . htmlspecialchars($titel) . '" style="filter:grayscale(1);opacity:.5">⏰</span>';
                $zeileDimmed = true;
            }
        }
    ?>
    <div class="katv-zeile" data-id="<?= $knoten['id'] ?>"
         data-name="<?= htmlspecialchars($knoten['name']) ?>"
         data-beschreibung="<?= htmlspecialchars($knoten['beschreibung'] ?? '') ?>"
         data-bild="<?= htmlspecialchars($knoten['bild_pfad'] ?? '') ?>"
         data-parent="<?= $knoten['parent_id'] ?? '' ?>"
         data-iak="<?= $istAktionKat ?>"
         style="padding-left:<?= 16 + $einzug ?>px<?= $zeileDimmed ? ';opacity:.45' : '' ?>">

        <span class="katv-toggle <?= $istBlatt ? 'katv-leer' : '' ?>"
              onclick="this.closest('.katv-gruppe').querySelector('.katv-kinder')?.classList.toggle('versteckt');this.textContent=this.textContent==='▶'?'▼':'▶'">
            <?= $istBlatt ? '' : '▼' ?>
        </span>

        <span class="katv-name"><?= htmlspecialchars($knoten['name']) ?><?= $uhrsymbol ?></span>

        <?php if ($anzahl > 0): ?>
            <span class="katv-anzahl"><?= $anzahl ?> Artikel</span>
        <?php endif; ?>

        <div class="katv-aktionen">
            <?php if ($pos > 0): ?>
                <button class="btn btn-secondary btn-xs"
                        onclick="katSortieren(<?= $knoten['id'] ?>, 'hoch')"
                        title="Nach oben">▲</button>
            <?php endif; ?>
            <?php if ($pos < $total - 1): ?>
                <button class="btn btn-secondary btn-xs"
                        onclick="katSortieren(<?= $knoten['id'] ?>, 'runter')"
                        title="Nach unten">▼</button>
            <?php endif; ?>
            <button class="btn btn-secondary btn-xs"
                    onclick="katNeuOeffnen(<?= $knoten['id'] ?>, <?= htmlspecialchars(json_encode($knoten['name'])) ?>)"
                    title="Neue Unterkategorie anlegen">+ Unter-Kat.</button>
            <button class="btn btn-secondary btn-xs"
                    onclick="katBearbeiten(<?= $knoten['id'] ?>, <?= htmlspecialchars(json_encode($knoten['name'])) ?>, <?= $knoten['parent_id'] ?? 'null' ?>)"
                    title="Name oder Oberkategorie ändern">Bearb.</button>
            <button class="btn btn-danger btn-xs"
                    onclick="katLoeschenVorschau(<?= $knoten['id'] ?>, <?= htmlspecialchars(json_encode($knoten['name'])) ?>)"
                    title="Kategorie löschen">Löschen</button>
        </div>
    </div>

    <?php if (!$istBlatt): ?>
        <div class="katv-kinder">
            <?php
            $kinderAnzahl = count($knoten['kinder']);
            foreach ($knoten['kinder'] as $ki => $kind): ?>
                <div class="katv-gruppe">
                    <?php renderVerwaltungsKnoten($kind, $tiefe + 1, $ki, $kinderAnzahl); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
}

// Flache Liste für Dropdowns (Bearbeiten / Neu)
function flattenBaum(array $baum, int $tiefe = 0): array
{
    $result = [];
    foreach ($baum as $k) {
        $result[] = ['id' => $k['id'], 'name' => $k['name'], 'tiefe' => $tiefe, 'parent_id' => $k['parent_id']];
        if (!empty($k['kinder'])) {
            $result = array_merge($result, flattenBaum($k['kinder'], $tiefe + 1));
        }
    }
    return $result;
}
$flacheListe = flattenBaum($kategorienBaum);
?>
<script>
// Für die "Einordnen nach"-Dropdown im Modal: pro Oberkategorie die Geschwister
// in der aktuellen Sortierreihenfolge (kategorien_verwalten.js baut daraus die Optionen).
window.KATEGORIEN_FLACH = <?= json_encode(array_map(
    fn($k) => ['id' => (int)$k['id'], 'parent_id' => $k['parent_id'] !== null ? (int)$k['parent_id'] : null, 'name' => $k['name']],
    $flacheListe
)) ?>;

// Shop-Sichtbarkeit im Bearbeiten-Modal: Liste aktiver Shops + bereits gesetzte
// Ausschlüsse (fehlender Eintrag = nicht ausgeschlossen, das ist der Normalfall).
window.SHOPS = <?= json_encode(array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name']], $alleShops)) ?>;
window.KATEGORIE_AUSSCHLUESSE = {};
<?php foreach ($ausschluesse as $a): ?>
if (!window.KATEGORIE_AUSSCHLUESSE[<?= (int)$a['kategorie_id'] ?>]) window.KATEGORIE_AUSSCHLUESSE[<?= (int)$a['kategorie_id'] ?>] = {};
window.KATEGORIE_AUSSCHLUESSE[<?= (int)$a['kategorie_id'] ?>][<?= (int)$a['shop_id'] ?>] = true;
<?php endforeach; ?>
</script>

<div class="card">
    <?php if (empty($kategorienBaum)): ?>
        <p style="color:var(--color-text-muted);padding:var(--space-md)">
            Noch keine Kategorien angelegt.
            <button onclick="katNeuOeffnen(null)" class="btn btn-primary btn-sm" style="margin-left:8px">Erste Kategorie anlegen</button>
        </p>
    <?php else: ?>
        <div class="katv-baum">
            <?php
            $wurzelAnzahl = count($kategorienBaum);
            foreach ($kategorienBaum as $wi => $wurzel): ?>
                <div class="katv-gruppe">
                    <?php renderVerwaltungsKnoten($wurzel, 0, $wi, $wurzelAnzahl); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ── Neu/Bearbeiten Modal ───────────────────────────────────────── -->
<div id="katv-modal" class="modal-backdrop">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span id="katv-modal-titel">Neue Kategorie</span>
            <button onclick="katvModalSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="katv-edit-id" value="">
            <div class="form-row">
                <label class="form-label">Name *</label>
                <input id="katv-name" type="text" class="erp-input" style="width:100%"
                       onkeydown="if(event.key==='Enter')katvSpeichern()">
            </div>
            <div class="form-row">
                <label class="form-label">Oberkategorie</label>
                <select id="katv-parent" class="erp-select" style="width:100%" onchange="katvAktualisierePositionsDropdown()">
                    <option value="">– Keine (Hauptkategorie) –</option>
                    <?php foreach ($flacheListe as $f): ?>
                        <option value="<?= $f['id'] ?>">
                            <?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $f['tiefe']) . ($f['tiefe'] > 0 ? '↳ ' : '') . htmlspecialchars($f['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label class="form-label">Einordnen</label>
                <select id="katv-nach" class="erp-select" style="width:100%"></select>
            </div>
            <div class="form-row">
                <label class="form-label">Beschreibung</label>
                <textarea id="katv-beschreibung" class="erp-input" style="width:100%;min-height:70px;resize:vertical"></textarea>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">
                    Wird nur im Online-Shop auf der Kategorieseite angezeigt, im ERP selbst nirgends
                </div>
            </div>
            <div class="form-row" id="katv-bild-bereich" style="display:none">
                <label class="form-label">Kategoriebild</label>
                <div style="display:flex;align-items:center;gap:10px">
                    <img id="katv-bild-preview" src="" alt=""
                         style="display:none;width:64px;height:64px;object-fit:cover;border-radius:4px;border:1px solid var(--color-border)">
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <input type="file" id="katv-bild-datei" accept="image/jpeg,image/png,image/webp" style="font-size:12px" onchange="katvBildHochladen(this)">
                        <button type="button" id="katv-bild-entfernen-btn" onclick="katvBildEntfernen()"
                                class="btn btn-secondary btn-xs" style="display:none;width:fit-content">Bild entfernen</button>
                    </div>
                </div>
                <div id="katv-bild-fehler" style="color:var(--color-danger);font-size:12px;margin-top:4px"></div>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">
                    Wird im Online-Shop angezeigt (z.B. Mega-Menü/Kategorieseite). Erst nach dem ersten Speichern verfügbar.
                </div>
            </div>
            <div class="form-row" style="margin-top:4px">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                    <input type="checkbox" id="katv-ist-aktions-kat" style="width:15px;height:15px">
                    <span>⏰ Ist Aktions-Kategorie</span>
                </label>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px;margin-left:23px">
                    Zeitlich begrenzte Sonderpreise können für diese Kategorie geplant werden
                </div>
            </div>
            <div class="form-row" id="katv-shop-bereich" style="display:none">
                <label class="form-label">Shop-Sichtbarkeit</label>
                <div id="katv-shop-checkboxen" style="display:flex;flex-direction:column;gap:6px"></div>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">
                    Abgewählte Shops bekommen diese Kategorie NIE zugewiesen, auch wenn ein Artikel
                    hier UND in einer anderen (für diesen Shop erlaubten) Kategorie steht. Erst nach
                    dem ersten Speichern verfügbar. Änderung wirkt beim nächsten Sync-Lauf.
                </div>
            </div>
            <div id="katv-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px"></div>
        </div>
        <div class="modal-footer">
            <button onclick="katvModalSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button onclick="katvSpeichern()" class="btn btn-primary btn-sm">Speichern</button>
        </div>
    </div>
</div>

<!-- ── Löschen-Bestätigungs Modal ───────────────────────────────── -->
<div id="katv-del-modal" class="modal-backdrop">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <span>Kategorie löschen</span>
            <button onclick="katvDelSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <p id="katv-del-info" style="margin-bottom:var(--space-sm)"></p>
            <div id="katv-del-warnungen"></div>
            <div id="katv-del-optionen" style="display:none;margin-top:12px;padding:10px;background:#f8f9fa;border-radius:4px;border:1px solid var(--color-border)">
                <div style="font-size:12.5px;font-weight:600;margin-bottom:8px">Was soll mit den Artikeln passieren?</div>
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:12.5px;margin-bottom:8px;cursor:pointer">
                    <input type="radio" name="katv-del-modus" value="verschieben" checked style="margin-top:2px">
                    <span>Artikel in Oberkategorie <strong id="katv-del-parent-name"></strong> verschieben</span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:12.5px;cursor:pointer">
                    <input type="radio" name="katv-del-modus" value="entfernen" style="margin-top:2px">
                    <span>Zuweisung nur entfernen <span style="color:var(--color-text-muted)">(Artikel können kategorielos werden)</span></span>
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="katvDelSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button id="katv-del-btn" onclick="katvLoeschenBestaetigt()" class="btn btn-danger btn-sm">Löschen</button>
        </div>
    </div>
</div>


<script src="<?= BASE_PATH ?>/js/kategorien_verwalten.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>

