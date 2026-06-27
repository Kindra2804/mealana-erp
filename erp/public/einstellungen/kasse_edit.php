<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db = Database::getInstance();

$istNeu = isset($_GET['neu']);
$id     = (int)($_GET['id'] ?? 0);

if (!$istNeu && $id < 1) {
    header('Location: index.php?tab=kassen');
    exit;
}

$kasse = null;
$schnellwahl = [];

if (!$istNeu) {
    $stmt = $db->prepare("SELECT * FROM kassen WHERE id = ?");
    $stmt->execute([$id]);
    $kasse = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$kasse) {
        header('Location: index.php?tab=kassen');
        exit;
    }
    $stmt = $db->prepare("
        SELECT ksw.slot, ksw.label, ksw.artikel_id, a.name AS artikel_name,
               (SELECT ac.code FROM artikel_codes ac WHERE ac.artikel_id = a.id AND ac.typ = 'GTIN13' LIMIT 1) AS ean,
               COALESCE(
                   (SELECT ap.brutto_vk FROM artikel_preise ap
                    INNER JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                    WHERE ap.artikel_id = a.id
                      AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= CURDATE())
                      AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= CURDATE())
                    ORDER BY ap.gueltig_ab DESC LIMIT 1
                   ), 0
               ) AS brutto_vk
        FROM kassen_schnellwahl ksw
        LEFT JOIN artikel a ON a.id = ksw.artikel_id
        WHERE ksw.kasse_id = ?
    ");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $schnellwahl[$r['slot']] = $r;
    }
}

$lager = $db->query("SELECT id, name FROM lager ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$e = $_SESSION['erfolg'] ?? null;
$f = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$pageTitle        = $istNeu ? 'Neue Kasse' : 'Kasse: ' . htmlspecialchars($kasse['name']);
$activeModule     = 'einstellungen';
$actionBarContent = '<a href="index.php?tab=kassen" class="btn btn-secondary btn-sm">&larr; Zurück</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($e): ?>
<div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)"><?= htmlspecialchars($e) ?></div>
<?php endif; ?>
<?php if ($f): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)"><?= htmlspecialchars($f) ?></div>
<?php endif; ?>

<form method="post" action="kasse_speichern.php" id="kasse-form">
    <input type="hidden" name="id" value="<?= $istNeu ? '' : $id ?>">

    <!-- ── Grunddaten ── -->
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Grunddaten</div>
        <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">

            <div class="form-group">
                <label class="form-label">Kassenname *</label>
                <input type="text" name="name" class="erp-input"
                    value="<?= htmlspecialchars($kasse['name'] ?? '') ?>" required
                    placeholder="z.B. Hauptkasse, Messe-Kasse">
            </div>

            <div class="form-group">
                <label class="form-label">Kassennummer (eindeutig) *</label>
                <input type="text" name="kasse_nr" class="erp-input"
                    value="<?= htmlspecialchars($kasse['kasse_nr'] ?? '') ?>" required
                    placeholder="K1, K2, ..."
                    <?= !$istNeu ? 'readonly style="background:var(--color-bg);color:var(--color-text-muted)"' : '' ?>>
                <?php if (!$istNeu): ?>
                    <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px">Kassennummer kann nach Erstellung nicht geändert werden.</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Zugeordnetes Lager *</label>
                <select name="lager_id" class="erp-select" required>
                    <?php foreach ($lager as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($kasse['lager_id'] ?? 0) == $l['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Betriebsmodus</label>
                <select name="modus" class="erp-select">
                    <option value="online"  <?= ($kasse['modus'] ?? 'online') === 'online'  ? 'selected' : '' ?>>Online (Echtzeit-DB)</option>
                    <option value="offline" <?= ($kasse['modus'] ?? 'online') === 'offline' ? 'selected' : '' ?>>Offline (Messe / SQLite)</option>
                </select>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px">Offline-Kassen benötigen Pre-Sync vor dem Einsatz.</div>
            </div>

            <div class="form-group">
                <label class="form-label">RKSV-Kassen-ID</label>
                <input type="text" name="rksv_kassen_id" class="erp-input"
                    value="<?= htmlspecialchars($kasse['rksv_kassen_id'] ?? '') ?>"
                    placeholder="z.B. RKSV-K1">
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px">Muss mit der BFR BONit Fiscal Recorder Konfiguration übereinstimmen.</div>
            </div>

            <div class="form-group" style="display:flex;flex-direction:column;justify-content:center;gap:10px;padding-top:18px">
                <label style="font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px">
                    <input type="checkbox" name="bon_logo" value="1" <?= ($kasse['bon_logo'] ?? 1) ? 'checked' : '' ?>>
                    <span>Firmenlogo auf Bon drucken</span>
                </label>
                <label style="font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px">
                    <input type="checkbox" name="aktiv" value="1" <?= ($kasse['aktiv'] ?? 1) ? 'checked' : '' ?>>
                    <span>Kasse aktiv</span>
                </label>
            </div>

        </div>
    </div>

    <!-- ── Schnellwahl ── -->
    <?php if (!$istNeu): ?>
    <div class="card" style="margin-bottom:16px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span>Schnellwahl-Tasten (9 Slots)</span>
            <span style="font-size:12px;color:var(--color-text-muted)">Slots 1–9 von links oben nach rechts unten</span>
        </div>
        <div style="padding:16px">

            <!-- Artikel-Suche (geteilt für alle Slots) -->
            <div id="sw-suche-panel" style="display:none;margin-bottom:16px;padding:12px;background:var(--color-bg);border:1px solid var(--color-border);border-radius:6px">
                <div style="font-size:13px;font-weight:600;margin-bottom:8px">Artikel suchen für Slot <span id="sw-slot-label">—</span></div>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="text" id="sw-suche-input" class="erp-input" placeholder="Name oder EAN eingeben..." style="flex:1">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="swSuchePanelHide()">Abbrechen</button>
                </div>
                <div id="sw-suche-ergebnisse" style="margin-top:8px;max-height:200px;overflow-y:auto"></div>
            </div>

            <!-- 3×3 Grid -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                <?php for ($slot = 1; $slot <= 9; $slot++):
                    $sw = $schnellwahl[$slot] ?? null;
                ?>
                <div class="sw-slot" data-slot="<?= $slot ?>" style="border:1px solid var(--color-border);border-radius:6px;padding:12px;min-height:100px;position:relative">
                    <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);margin-bottom:6px">Slot <?= $slot ?></div>

                    <input type="hidden" name="sw_artikel_id[<?= $slot ?>]" id="sw-artikel-id-<?= $slot ?>"
                           value="<?= $sw ? $sw['artikel_id'] : '' ?>">

                    <div id="sw-info-<?= $slot ?>" style="margin-bottom:8px">
                        <?php if ($sw && $sw['artikel_id']): ?>
                            <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($sw['artikel_name']) ?></div>
                            <div style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars($sw['ean'] ?? '') ?></div>
                            <div style="font-size:12px;color:var(--color-nav);font-weight:600;margin-top:2px">
                                € <?= number_format((float)$sw['brutto_vk'], 2, ',', '.') ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size:12px;color:var(--color-text-muted);font-style:italic">Kein Artikel</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom:8px">
                        <input type="text" name="sw_label[<?= $slot ?>]" class="erp-input"
                               value="<?= htmlspecialchars($sw['label'] ?? '') ?>"
                               placeholder="Beschriftung (optional)"
                               style="font-size:12px;padding:4px 8px">
                    </div>

                    <div style="display:flex;gap:6px">
                        <button type="button" class="btn btn-secondary btn-sm"
                                onclick="swSuchePanelShow(<?= $slot ?>)"
                                style="font-size:12px;padding:3px 10px">
                            Artikel wählen
                        </button>
                        <?php if ($sw && $sw['artikel_id']): ?>
                        <button type="button" class="btn btn-sm"
                                style="font-size:12px;padding:3px 10px;background:none;border:1px solid var(--color-border);color:var(--color-danger)"
                                onclick="swLeeren(<?= $slot ?>)">
                            Leeren
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card" style="margin-bottom:16px;padding:16px;color:var(--color-text-muted);font-size:13px">
        Schnellwahl-Tasten können nach dem Anlegen der Kasse konfiguriert werden.
    </div>
    <?php endif; ?>

    <div style="display:flex;justify-content:flex-end;gap:8px">
        <a href="index.php?tab=kassen" class="btn btn-secondary">Abbrechen</a>
        <button type="submit" class="btn btn-primary"><?= $istNeu ? 'Kasse anlegen' : 'Änderungen speichern' ?></button>
    </div>
</form>

<script>
let swAktiverSlot = null;
let swSucheTimer  = null;

function swSuchePanelShow(slot) {
    swAktiverSlot = slot;
    document.getElementById('sw-slot-label').textContent = slot;
    document.getElementById('sw-suche-input').value = '';
    document.getElementById('sw-suche-ergebnisse').innerHTML = '';
    document.getElementById('sw-suche-panel').style.display = 'block';
    document.getElementById('sw-suche-input').focus();
}

function swSuchePanelHide() {
    document.getElementById('sw-suche-panel').style.display = 'none';
    swAktiverSlot = null;
}

document.getElementById('sw-suche-input')?.addEventListener('input', function() {
    clearTimeout(swSucheTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('sw-suche-ergebnisse').innerHTML = ''; return; }
    swSucheTimer = setTimeout(() => swSuche(q), 250);
});

function swSuche(q) {
    const res = document.getElementById('sw-suche-ergebnisse');
    res.innerHTML = '<div style="font-size:12px;color:var(--color-text-muted);padding:4px">Suche...</div>';
    fetch('/mealana/kasse/ajax_artikel.php?suche=' + encodeURIComponent(q) + '&lager_id=<?= $kasse['lager_id'] ?? 1 ?>')
        .then(r => r.json())
        .then(d => {
            if (!d.erfolg || !d.ergebnisse?.length) {
                res.innerHTML = '<div style="font-size:12px;color:var(--color-text-muted);padding:4px">Keine Treffer.</div>';
                return;
            }
            res.innerHTML = d.ergebnisse.map(a => `
                <div onclick="swArtikelWaehlen(${a.artikel_id}, ${JSON.stringify(a.bezeichnung)}, ${JSON.stringify(a.ean ?? '')}, ${parseFloat(a.brutto_vk ?? 0)})"
                     style="padding:6px 8px;cursor:pointer;border-radius:4px;font-size:13px;display:flex;justify-content:space-between;align-items:center"
                     onmouseover="this.style.background='var(--color-bg)'" onmouseout="this.style.background=''">
                    <span>${escHtml(a.bezeichnung)}</span>
                    <span style="display:flex;gap:12px;align-items:center">
                        <span style="color:var(--color-text-muted);font-size:11px">${escHtml(a.ean ?? '')}</span>
                        <span style="color:var(--color-nav);font-weight:600;font-size:12px">€ ${parseFloat(a.brutto_vk ?? 0).toFixed(2).replace('.',',')}</span>
                    </span>
                </div>
            `).join('');
        });
}

function swArtikelWaehlen(artikelId, name, ean, brutto) {
    if (!swAktiverSlot) return;
    const slot = swAktiverSlot;
    const preisStr = '€ ' + brutto.toFixed(2).replace('.', ',');
    document.getElementById('sw-artikel-id-' + slot).value = artikelId;
    document.getElementById('sw-info-' + slot).innerHTML =
        `<div style="font-size:13px;font-weight:600">${escHtml(name)}</div>` +
        `<div style="font-size:11px;color:var(--color-text-muted)">${escHtml(ean)}</div>` +
        `<div style="font-size:12px;color:var(--color-nav);font-weight:600;margin-top:2px">${preisStr}</div>`;
    swSuchePanelHide();
}

function swLeeren(slot) {
    document.getElementById('sw-artikel-id-' + slot).value = '';
    document.getElementById('sw-info-' + slot).innerHTML =
        '<div style="font-size:12px;color:var(--color-text-muted);font-style:italic">Kein Artikel</div>';
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
