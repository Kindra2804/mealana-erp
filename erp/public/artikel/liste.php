<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/core/Database.php';


$controller = new ArtikelController();
$service = new ArtikelService();

$db = Database::getInstance();
$alleHersteller  = $db->query("SELECT id, name FROM hersteller WHERE aktiv = 1 ORDER BY name")->fetchAll();
$alleArtikeltypen = $db->query("SELECT id, name FROM artikel_typen ORDER BY name")->fetchAll();


$filter = [
    'q'             => trim($_GET['q'] ?? ''),
    'hersteller_id' => (int)($_GET['hersteller_id'] ?? 0) ?: null,
    'artikeltyp_id' => (int)($_GET['artikeltyp_id'] ?? 0) ?: null,
    'nurMitBestand' => isset($_GET['nurMitBestand']),
    'mitInaktiven'  => isset($_GET['inaktive']),
];

$seite = (int)($_GET['seite'] ?? 1);
$proSeite = (int)($_GET['pro_seite'] ?? 15);

$offset = ($seite - 1) * $proSeite;

$artikel = $controller->index($filter, $proSeite, $offset);

$vaterIds = array_column($artikel, 'id');

$alleKinder = $service->getKinderFuerListe($vaterIds);
$kinderNachVater = [];
foreach ($alleKinder as $k) {
    $kinderNachVater[$k['vaterartikel_id']][] = $k;
}

$gesamt = $controller->count($filter);
$seitenAnzahl = (int) ceil($gesamt / $proSeite);

$pageTitle    = "Artikelliste";
$activeModule = "artikel";

$actionBarContent = <<<HTML
<a href="neu.php" class="btn btn-primary btn-sm">+ Neu</a>
<button class="btn btn-secondary btn-sm">Kopieren</button>
<div class="actionbar-sep"></div>
<button class="btn btn-secondary btn-sm">⬇ Import</button>
<button class="btn btn-secondary btn-sm">⬆ Export</button>
<div class="actionbar-right">
    <span style="color:var(--color-text-muted);font-size:13px">Ausgewählt: 0</span>
    <button class="btn btn-secondary btn-sm">Aktion ▼</button>
</div>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

?>

<div class="card">
    <form method="GET" action="liste.php" class="filter-bar">
        <input type="text" name="q" class="erp-input"
            value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
            placeholder="Artikel suchen...">
        <select name="hersteller_id" class="erp-select">
            <option value="">– Hersteller –</option>
            <?php foreach ($alleHersteller as $h): ?>
                <option value="<?= $h['id'] ?>" <?= ($_GET['hersteller_id'] ?? '') == $h['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($h['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="artikeltyp_id" class="erp-select">
            <option value="">– Artikel-Typ –</option>
            <?php foreach ($alleArtikeltypen as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($_GET['artikeltyp_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label><input type="checkbox" name="nurMitBestand" <?= isset($_GET['nurMitBestand']) ? 'checked' : '' ?>> Nur mit Bestand</label>
        <label><input type="checkbox" name="inaktive" <?= isset($_GET['inaktive']) ? 'checked' : '' ?>> Auch inaktive</label>
        <button type="submit" class="btn btn-secondary">🔍</button>
    </form>
</div>

<div class="card">
    <table class="erp-table">
        <tr>
            <th style="width:28px"></th>
            <th>Artikelnummer</th>
            <th>Name</th>
            <th>Typ</th>
            <th>Hersteller</th>
            <th>Bestand</th>
            <th>Status</th>
            <th>Aktion</th>
        </tr>

        <?php foreach ($artikel as $a):
            $kinder = $kinderNachVater[$a['id']] ?? [];
            $hatKinder = count($kinder) > 0;
            if (!$a['aktiv']) $zeilenstil = 'class="row-inaktiv"';
            elseif ($a['ist_auslaufartikel']) $zeilenstil = 'class="row-auslauf"';
            else $zeilenstil = '';
        ?>
            <!-- Vater-Zeile -->
            <tr <?= $zeilenstil ?>>
                <td style="text-align:center">
                    <?php if ($hatKinder): ?>
                        <span id="pfeil-<?= $a['id'] ?>" onclick="toggleKinder(<?= $a['id'] ?>)"
                            style="cursor:pointer; color:var(--color-nav); font-size:11px; user-select:none">▶</span>
                    <?php endif; ?>
                </td>
                <td><a href="detail.php?id=<?= $a['id'] ?>"><?= htmlspecialchars($a['artikelnummer']) ?></a></td>
                <td>
                    <?= htmlspecialchars($a['name']) ?>
                    <?php if ($hatKinder): ?>
                        <span style="color:var(--color-text-muted); font-size:11px; margin-left:var(--space-xs)"><?= count($kinder) ?> Varianten</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($a['artikeltyp']) ?></td>
                <td><?= htmlspecialchars($a['hersteller']) ?></td>
                <td><?= $a['gesamtbestand'] ?></td>
                <td><?= $a['aktiv'] ? '<span class="chip chip-aktiv">Aktiv</span>' : '<span class="chip chip-inaktiv">Deaktiviert</span>' ?></td>
                <td>
                    <a href="detail.php?id=<?= $a['id'] ?>">✏️</a>
                    <a href="delete.php?id=<?= $a['id'] ?>" onclick="return confirm('Artikel wirklich deaktivieren?')">🗑️</a>
                </td>
            </tr>

            <!-- Kind-Zeilen -->
            <?php foreach ($kinder as $k): ?>
                <tr class="kind-zeile-<?= $a['id'] ?> versteckt" style="background:#FAFCFF">
                    <td></td>
                    <td style="padding-left:var(--space-lg); color:var(--color-text-muted); font-size:12px">
                        ↳ <a href="detail.php?id=<?= $k['id'] ?>"><?= htmlspecialchars($k['artikelnummer']) ?></a>
                    </td>
                    <td style="font-size:12px; color:var(--color-text-muted)"><?= htmlspecialchars($k['name']) ?></td>
                    <td colspan="2"></td>
                    <td style="font-size:12px"><?= $k['gesamtbestand'] ?></td>
                    <td><?= $k['aktiv'] ? '<span class="chip chip-aktiv">Aktiv</span>' : '<span class="chip chip-inaktiv">Inaktiv</span>' ?></td>
                    <td><a href="detail.php?id=<?= $k['id'] ?>">✏️</a></td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>

    </table>
</div>
<div class="card">
    <div class="pagination-bar">
        <div class="">
            Zeige <?= $offset + 1 ?>–<?= min($offset + $proSeite, $gesamt) ?> von <?= $gesamt ?> Artikeln
        </div>
        <div class="pagination">
            <?php for ($i = 1; $i <= $seitenAnzahl; $i++) {
                $params = $_GET;           // alle aktuellen GET-Parameter (Filter, pro_seite...)
                $params['seite'] = $i;     // seite überschreiben/setzen
                $qs = http_build_query($params);
                $aktiv = ($i == $seite) ? 'active' : '';
            ?>
                <a class="<?= $aktiv ?>" href="liste.php?<?= $qs ?>">[<?= $i ?>]</a>
            <?php } ?>
        </div>
        <div class="">
            Zeilen/Seite:
            <select name="pro_seite" onchange="
            var p = new URLSearchParams(window.location.search);
            p.set('pro_seite', this.value);
            p.set('seite', 1);
            window.location.href = 'liste.php?' + p.toString();
        ">
                <option value="10" <?= $proSeite == 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $proSeite == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $proSeite == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $proSeite == 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>
    </div>


</div>

<script>
    function toggleKinder(vaterId) {
        document.querySelectorAll('.kind-zeile-' + vaterId).forEach(r => r.classList.toggle('versteckt'));
        const p = document.getElementById('pfeil-' + vaterId);
        p.textContent = p.textContent === '▶' ? '▼' : '▶';
    }
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>