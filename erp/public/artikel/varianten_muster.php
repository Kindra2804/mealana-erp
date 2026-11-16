<?php
/**
 * Massenaktion "Varianten-Muster übernehmen" (Artikelliste → Aktion, öffnet im neuen Tab).
 * Aufruf:  POST ids[]  gewählte Artikel (Ziele; ein mitgewählter Master wird vorausgewählt)
 * AJAX:    POST JSON {aktion: 'vorschau', master_id, ids}
 *          POST JSON {aktion: 'uebernehmen', master_id, ziel_id, bestand}   (ein Ziel je Aufruf)
 * Logik: src/modules/varianten/VariantenMusterService.php, Oberfläche: js/varianten_muster.js
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenMusterService.php';

$svc = new VariantenMusterService();

// ── AJAX ─────────────────────────────────────────────────────────────
if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    header('Content-Type: application/json');
    try {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $masterId = (int)($in['master_id'] ?? 0);
        if (($in['aktion'] ?? '') === 'vorschau') {
            echo json_encode(['erfolg' => true] + $svc->vorschau($masterId, (array)($in['ids'] ?? [])));
        } elseif (($in['aktion'] ?? '') === 'uebernehmen') {
            $bestand = trim((string)($in['bestand'] ?? '')) ?: null;
            echo json_encode($svc->uebernehmen($masterId, (int)($in['ziel_id'] ?? 0), $bestand, $_SESSION['benutzer']['id'] ?? null));
        } else {
            throw new RuntimeException('Unbekannte Aktion');
        }
    } catch (Throwable $e) {
        echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()]);
    }
    exit;
}

// ── Seite ────────────────────────────────────────────────────────────
$roh = $_POST['ids'] ?? ($_GET['ids'] ?? []);
if (is_string($roh)) $roh = explode(',', $roh);
$ids = array_values(array_unique(array_filter(array_map('intval', (array)$roh))));

// Vorschlag Master: der mitgewählte Artikel mit den meisten Varianten
$masterVorschlag = null;
if ($ids) {
    $db = Database::getInstance();
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT a.id, a.artikelnummer, a.name, (SELECT COUNT(*) FROM artikel k WHERE k.vaterartikel_id = a.id) AS kinder
                        FROM artikel a WHERE a.id IN ($ph) AND a.vaterartikel_id IS NULL ORDER BY kinder DESC, a.artikelnummer LIMIT 1");
    $st->execute($ids);
    $r = $st->fetch();
    if ($r && (int)$r['kinder'] > 0) $masterVorschlag = $r;
}

$pageTitle    = 'Varianten-Muster übernehmen';
$activeModule = 'artikel';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="padding:var(--space-md);max-width:1100px">

<h2 style="margin:0 0 4px">Varianten-Muster übernehmen</h2>
<div style="font-size:13px;color:var(--color-text-muted);margin-bottom:var(--space-md)">
    Ein Master-Artikel legt fest, in welchen Varianten ein Artikel verfügbar ist. Die <?= count($ids) ?> gewählten Artikel
    bekommen fehlende Varianten dazu — vorhandene bleiben unverändert (auch ihr Bestand), es wird nie etwas gelöscht.
    Nummer und Name: eigene Nummer/Name + das Kürzel der Master-Variante (z.&nbsp;B. <code>-KLS</code>).
    Preis = eigener Preis + Aufschlag der Master-Variante. Zuerst kommt eine Vorschau.
</div>

<?php if (!$ids): ?>
    <div class="card" style="padding:var(--space-md)">Keine Artikel gewählt. Bitte in der Artikelliste Artikel anhaken und die Aktion „Varianten-Muster übernehmen“ wählen.</div>
<?php else: ?>

<div class="card" style="padding:var(--space-md);display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <label style="font-size:12px;flex:1;min-width:280px;position:relative">Master-Artikel (hat alle Varianten, die es geben soll)
        <input type="text" id="vm-master-suche" class="erp-input" style="width:100%" autocomplete="off"
               placeholder="Artikelnummer oder Name …"
               value="<?= $masterVorschlag ? htmlspecialchars($masterVorschlag['artikelnummer'] . ' – ' . $masterVorschlag['name']) : '' ?>">
        <div id="vm-master-treffer" style="display:none;position:absolute;z-index:20;left:0;right:0;background:#fff;border:1px solid var(--color-border);max-height:260px;overflow:auto"></div>
    </label>
    <button type="button" id="vm-vorschau" class="btn btn-primary">Vorschau</button>
</div>

<div id="vm-ergebnis" style="margin-top:var(--space-md)"></div>

<div id="vm-aktionen" style="display:none;margin-top:var(--space-md);justify-content:flex-end;gap:12px;align-items:center">
    <span id="vm-zusammenfassung" style="font-size:13px;color:var(--color-text-muted)"></span>
    <button type="button" id="vm-uebernehmen" class="btn btn-primary">Übernehmen</button>
</div>

<?php endif; ?>
</div>

<script>
window.VM_IDS       = <?= json_encode($ids) ?>;
window.VM_MASTER_ID = <?= $masterVorschlag ? (int)$masterVorschlag['id'] : 'null' ?>;
</script>
<script src="<?= BASE_PATH ?>/js/varianten_muster.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
