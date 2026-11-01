<?php
// Lizenzdatei ausstellen: aus einem Auftrag (?auftrag_id=), als neue Ausgabe (?lizenz_kunde_id=) oder frei
require_once __DIR__ . '/../includes/auth_check.php';

if (!Lizenz::generatorAktiv()) {   // lädt LizenzGenerator, beim Kunden nicht vorhanden
    header('Location: index.php');
    exit;
}
$gen = new LizenzGenerator();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erg = $gen->ausstellen([
        'lizenz_kunde_id' => (int)($_POST['lizenz_kunde_id'] ?? 0),
        'kunde_id'        => (int)($_POST['kunde_id'] ?? 0) ?: null,
        'firma'           => $_POST['firma'] ?? '',
        'uid'             => $_POST['uid'] ?? '',
        'steuernummer'    => $_POST['steuernummer'] ?? '',
        'module'          => array_map('intval', $_POST['module'] ?? []),
        'wartung_bis'     => $_POST['wartung_bis'] ?? '',
        'hauslizenz'      => !empty($_POST['hauslizenz']),
        'auftrag_id'      => (int)($_POST['auftrag_id'] ?? 0) ?: null,
        'grund'           => $_POST['grund'] ?? '',
    ], (int)($_SESSION['benutzer']['id'] ?? 0));
    if ($erg['erfolg']) {
        $_SESSION['erfolg'] = 'Lizenz ' . $erg['lizenz_nr'] . ' (Ausgabe ' . $erg['ausgabe'] . ') ausgestellt.';
        header('Location: generator.php?neu=' . $erg['ausgabe_id']);
        exit;
    }
    $fehler = $erg['fehler'];
    $v = $_POST + ['module' => []];
    $v['hauslizenz'] = !empty($_POST['hauslizenz']);
    $v['hinweise'] = [];
} else {
    $fehler = null;
    $v = $gen->vorschlag((int)($_GET['auftrag_id'] ?? 0) ?: null, (int)($_GET['lizenz_kunde_id'] ?? 0) ?: null);
}

$lk = !empty($v['lizenz_kunde_id']) ? $gen->kunde((int)$v['lizenz_kunde_id']) : null;
$andereLizenzen = [];
if (!empty($v['kunde_id'])) {
    $stmt = Database::getInstance()->prepare("SELECT id, lizenz_nr, firma FROM lizenz_kunden WHERE kunde_id = ?");
    $stmt->execute([(int)$v['kunde_id']]);
    $andereLizenzen = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle        = 'Lizenz ausstellen';
$activeModule     = 'einstellungen';
$actionBarContent = '<a href="generator.php" class="btn btn-secondary btn-sm">← Lizenzkunden</a>'
    . (!empty($v['auftrag_id']) ? '<a href="' . BASE_PATH . '/auftraege/detail.php?id=' . (int)$v['auftrag_id'] . '" class="btn btn-secondary btn-sm">← Auftrag</a>' : '');
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;color:var(--color-danger)"><?= htmlspecialchars($fehler) ?></div>
<?php endif; ?>
<?php foreach ($v['hinweise'] as $h): ?>
    <div class="card" style="border-left:3px solid #2563eb;margin-bottom:12px"><?= htmlspecialchars($h) ?></div>
<?php endforeach; ?>

<form method="post" class="card" style="padding:16px 18px;display:grid;gap:14px;max-width:820px">
    <input type="hidden" name="kunde_id" value="<?= (int)($v['kunde_id'] ?? 0) ?>">
    <input type="hidden" name="auftrag_id" value="<?= (int)($v['auftrag_id'] ?? 0) ?>">

    <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Lizenz</label>
        <select name="lizenz_kunde_id" class="erp-select" onchange="if(this.value!=='<?= (int)($v['lizenz_kunde_id'] ?? 0) ?>'){location='?lizenz_kunde_id='+this.value+'<?= !empty($v['auftrag_id']) ? '&auftrag_id=' . (int)$v['auftrag_id'] : '' ?>'}">
            <option value="0">Neue Lizenz (neue Lizenznummer)</option>
            <?php foreach ($andereLizenzen as $al): ?>
                <option value="<?= (int)$al['id'] ?>" <?= (int)($v['lizenz_kunde_id'] ?? 0) === (int)$al['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($al['lizenz_nr'] . ' · ' . $al['firma']) ?> — neue Ausgabe
                </option>
            <?php endforeach; ?>
            <?php if ($lk && !in_array((int)$lk['id'], array_map('intval', array_column($andereLizenzen, 'id')), true)): ?>
                <option value="<?= (int)$lk['id'] ?>" selected><?= htmlspecialchars($lk['lizenz_nr'] . ' · ' . $lk['firma']) ?> — neue Ausgabe</option>
            <?php endif; ?>
        </select>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Firmenname (wie in den Einstellungen des Kunden)</label>
            <input type="text" name="firma" class="erp-input" style="width:100%" required value="<?= htmlspecialchars($v['firma'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">UID</label>
            <input type="text" name="uid" class="erp-input" style="width:100%" value="<?= htmlspecialchars($v['uid'] ?? '') ?>" placeholder="ATU…">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">… sonst Steuernummer</label>
            <input type="text" name="steuernummer" class="erp-input" style="width:100%" value="<?= htmlspecialchars($v['steuernummer'] ?? '') ?>">
        </div>
    </div>
    <div style="font-size:12px;color:var(--color-text-muted);margin-top:-8px">
        Die Lizenz gilt nur, wenn Firmenname und UID (bzw. Steuernummer) beim Kunden unter Einstellungen → Firma genauso eingetragen sind
        (Groß-/Kleinschreibung, Leerzeichen und Satzzeichen sind egal). RKSV mit BFR braucht eine UID.
    </div>

    <div>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;cursor:pointer">
            <input type="checkbox" name="hauslizenz" value="1" id="hauslizenz" <?= !empty($v['hauslizenz']) ? 'checked' : '' ?> onchange="hausToggle()">
            Hauslizenz (alle Module, Kassen/Shops unbegrenzt, Wartung unbegrenzt)
        </label>
    </div>

    <div id="modulbox">
        <table class="erp-table">
            <thead><tr><th>Modul</th><th style="width:140px">Lizenziert</th></tr></thead>
            <tbody>
            <?php foreach (Lizenz::MODULE as $code => $m): $anz = (int)($v['module'][$code] ?? 0); ?>
                <tr>
                    <td><?= htmlspecialchars($m['label']) ?></td>
                    <td>
                        <?php if ($code === 'grundmodul'): ?>
                            <input type="hidden" name="module[grundmodul]" value="1">✓ immer
                        <?php elseif ($m['menge']): ?>
                            <input type="number" name="module[<?= $code ?>]" min="0" max="99" class="erp-input" style="width:70px" value="<?= $anz ?>"> <?= htmlspecialchars($m['einheit']) ?>
                        <?php else: ?>
                            <input type="hidden" name="module[<?= $code ?>]" value="0">
                            <input type="checkbox" name="module[<?= $code ?>]" value="1" <?= $anz ? 'checked' : '' ?>>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px" id="wartungbox">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Wartung bis</label>
            <input type="date" name="wartung_bis" class="erp-input" value="<?= htmlspecialchars($v['wartung_bis'] ?? '') ?>">
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);align-self:end">
            Aus dem Auftrag vorgeschlagen: laufende Wartung + gekaufte Jahre; neue Lizenz: 1. Jahr inklusive.
        </div>
    </div>

    <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Grund / Notiz (optional)</label>
        <input type="text" name="grund" class="erp-input" style="width:100%" value="<?= htmlspecialchars($v['grund'] ?? (!empty($v['auftrag_id']) ? '' : '')) ?>"
               placeholder="z.B. Umfirmierung, zusätzliche Kasse, Wartungsverlängerung">
    </div>

    <div><button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Lizenzdatei jetzt signieren und ausstellen?')">🔑 Lizenzdatei ausstellen</button></div>
</form>

<script>
// Hauslizenz: Module und Wartung sind fix → ausblenden
function hausToggle() {
    var an = document.getElementById('hauslizenz').checked;
    document.getElementById('modulbox').style.display = an ? 'none' : '';
    document.getElementById('wartungbox').style.display = an ? 'none' : '';
}
hausToggle();
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
