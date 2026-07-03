<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';

$service = new AuftragService();

$filterZahlung        = $_GET['zahlung']  ?? '';
$filterLieferung      = $_GET['lieferung'] ?? '';
$filterKanal          = $_GET['kanal']    ?? '';
$suche                = $_GET['suche']    ?? '';
$mitAbgeschlossenen   = isset($_GET['abgeschlossene']);

$auftraege = $service->getAll($filterZahlung, $filterLieferung, $filterKanal, $suche, $mitAbgeschlossenen);

$zahlungsLabels = [
    'ausstehend'   => ['label' => 'Ausstehend',  'class' => 'chip-auslauf'],
    'bezahlt'      => ['label' => 'Bezahlt',     'class' => 'chip-aktiv'],
    'teilbezahlt'  => ['label' => 'Teilbezahlt', 'class' => 'chip-auslauf'],
    'ueberbezahlt' => ['label' => 'Überbezahlt', 'class' => 'chip-auslauf'],
    'erstattet'    => ['label' => 'Erstattet',   'class' => 'chip-inaktiv'],
    'storniert'    => ['label' => 'Storniert',   'class' => 'chip-inaktiv'],
];
$zahlungsArtLabels = [
    'vorkasse'    => ['label' => 'Vorkasse',  'class' => 'chip-aktiv'],
    'paypal'      => ['label' => 'PayPal',      'class' => 'sc-aktion'],
    'rechnung'    => ['label' => 'Rechnung',  'class' => 'sc-fehlbest'],
    'bar'         => ['label' => 'Bar',    'class' => 'chip-aktiv'],
    'gutschein'   => ['label' => 'Gutschein',    'class' => 'sc-ohnekat'],
    'gemischt'    => ['label' => 'Gemischt',    'class' => 'sc-ohnekat'],
];
$lieferLabels = [
    'neu'              => ['label' => 'Neu',              'class' => 'chip-aktiv'],
    'in_bearbeitung'   => ['label' => 'In Bearbeitung',   'class' => 'chip-auslauf'],
    'versandbereit'    => ['label' => 'Versandbereit',    'class' => 'chip-auslauf'],
    'teilgeliefert'    => ['label' => 'Teilgeliefert',    'class' => 'chip-auslauf'],
    'zurueckgestellt'  => ['label' => 'Zurückgestellt',   'class' => 'chip-inaktiv'],
    'versendet'        => ['label' => 'Versendet',        'class' => 'chip-aktiv'],
    'abgeschlossen'    => ['label' => 'Abgeschlossen',    'class' => 'chip-inaktiv'],
    'storniert'        => ['label' => 'Storniert',        'class' => 'chip-inaktiv'],
    'kommissioniert'   => ['label' => 'Kommissioniert',   'class' => 'chip-auslauf'],
    'abholbereit'      => ['label' => 'Abholbereit',      'class' => 'chip-aktiv'],
];
$kanalLabels = [
    'woocommerce' => ['label' => 'WooCommerce', 'class' => 'chip-aktiv'],
    'manuell'     => ['label' => 'Manuell',     'class' => 'chip-auslauf'],
    'kasse'       => ['label' => 'Kasse',        'class' => 'chip-inaktiv'],
];

$pageTitle        = 'Aufträge';
$activeModule     = 'verkauf';
$actionBarContent = '<a href="' . BASE_PATH . '/auftraege/neu.php" class="btn btn-primary btn-sm">+ Neuer Auftrag</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="filter-bar" style="margin-bottom:12px">
    <input type="text" class="erp-input" placeholder="Suche…" style="width:160px;font-size:13px"
        value="<?= htmlspecialchars($suche) ?>" id="suche-input"
        onkeydown="if(event.key==='Enter') applyFilter()">
    <select class="erp-select" style="font-size:13px" id="filter-zahlung">
        <option value="">Alle Zahlung</option>
        <option value="ausstehend"   <?= $filterZahlung === 'ausstehend'   ? 'selected' : '' ?>>Ausstehend</option>
        <option value="teilbezahlt"  <?= $filterZahlung === 'teilbezahlt'  ? 'selected' : '' ?>>Teilbezahlt</option>
        <option value="bezahlt"      <?= $filterZahlung === 'bezahlt'      ? 'selected' : '' ?>>Bezahlt</option>
        <option value="ueberbezahlt" <?= $filterZahlung === 'ueberbezahlt' ? 'selected' : '' ?>>Überbezahlt</option>
        <option value="storniert"    <?= $filterZahlung === 'storniert'    ? 'selected' : '' ?>>Storniert</option>
    </select>
    <select class="erp-select" style="font-size:13px" id="filter-lieferung">
        <option value="">Alle Lieferung</option>
        <option value="neu" <?= $filterLieferung === 'neu'             ? 'selected' : '' ?>>Neu</option>
        <option value="in_bearbeitung" <?= $filterLieferung === 'in_bearbeitung'  ? 'selected' : '' ?>>In Bearbeitung</option>
        <option value="versandbereit" <?= $filterLieferung === 'versandbereit'   ? 'selected' : '' ?>>Versandbereit</option>
        <option value="teilgeliefert" <?= $filterLieferung === 'teilgeliefert'   ? 'selected' : '' ?>>Teilgeliefert</option>
        <option value="versendet" <?= $filterLieferung === 'versendet'       ? 'selected' : '' ?>>Versendet</option>
        <option value="zurueckgestellt" <?= $filterLieferung === 'zurueckgestellt' ? 'selected' : '' ?>>Zurückgestellt</option>
        <option value="abgeschlossen" <?= $filterLieferung === 'abgeschlossen'   ? 'selected' : '' ?>>Abgeschlossen</option>
        <option value="kommissioniert" <?= $filterLieferung === 'kommissioniert' ? 'selected' : '' ?>>Kommissioniert</option>
        <option value="abholbereit" <?= $filterLieferung === 'abholbereit'    ? 'selected' : '' ?>>Abholbereit</option>
    </select>
    <select class="erp-select" style="font-size:13px" id="filter-kanal">
        <option value="">Alle Kanäle</option>
        <option value="woocommerce" <?= $filterKanal === 'woocommerce' ? 'selected' : '' ?>>WooCommerce</option>
        <option value="manuell" <?= $filterKanal === 'manuell'     ? 'selected' : '' ?>>Manuell</option>
        <option value="kasse" <?= $filterKanal === 'kasse'       ? 'selected' : '' ?>>Kasse</option>
    </select>
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:var(--color-text-muted)">
        <input type="checkbox" id="filter-abgeschlossene" onchange="applyFilter()" <?= $mitAbgeschlossenen ? 'checked' : '' ?>>
        Abgeschlossene anzeigen
    </label>
</div>

<script>
    function applyFilter() {
        const p = new URLSearchParams();
        const s = document.getElementById('suche-input').value.trim();
        const z = document.getElementById('filter-zahlung').value;
        const l = document.getElementById('filter-lieferung').value;
        const k = document.getElementById('filter-kanal').value;
        const a = document.getElementById('filter-abgeschlossene').checked;
        if (s) p.set('suche', s);
        if (z) p.set('zahlung', z);
        if (l) p.set('lieferung', l);
        if (k) p.set('kanal', k);
        if (a) p.set('abgeschlossene', '1');
        window.location = '?' + p.toString();
    }
    document.getElementById('filter-zahlung').addEventListener('change', applyFilter);
    document.getElementById('filter-lieferung').addEventListener('change', applyFilter);
    document.getElementById('filter-kanal').addEventListener('change', applyFilter);
</script>

<div class="card">
    <?php if (empty($auftraege)): ?>
        <p style="color:var(--color-text-muted);padding:16px">Keine Aufträge gefunden.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Auftrag</th>
                    <th>Kanal</th>
                    <th>Kunde</th>
                    <th>Datum</th>
                    <th>Pos.</th>
                    <th>Brutto</th>
                    <th>Zahlungsart</th>
                    <th>Zahlung</th>
                    <th>Lieferung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auftraege as $a):
                    $za = $zahlungsArtLabels[$a['zahlungsart']] ?? ['label' => $a['zahlungsart'], 'class' => ''];
                    $zStatus = $a['zahlungsstatus'];
                    if ($zStatus === 'bezahlt' && (float)($a['summe_zahlungen'] ?? 0) > (float)$a['bruttobetrag']) {
                        $zStatus = 'ueberbezahlt';
                    }
                    $zl = $zahlungsLabels[$zStatus] ?? ['label' => $zStatus, 'class' => ''];
                    $ll = $lieferLabels[$a['lieferstatus']]     ?? ['label' => $a['lieferstatus'],   'class' => ''];
                    $kl = $kanalLabels[$a['kanal']]             ?? ['label' => $a['kanal'],          'class' => ''];
                    $istErledigt = $a['lieferstatus'] === 'abgeschlossen' && $a['zahlungsstatus'] === 'bezahlt';
                ?>
                    <tr<?= $istErledigt ? ' style="opacity:0.55;background:var(--color-bg-secondary)"' : '' ?>>
                        <td>
                            <a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= $a['id'] ?>" style="font-weight:600">
                                <?= htmlspecialchars($a['auftrag_nr']) ?>
                            </a>
                            <?php if ($a['mahnung_stufe'] > 0): ?>
                                <span style="color:var(--color-warning);margin-left:4px;font-size:12px" title="Mahnstufe <?= $a['mahnung_stufe'] ?>">&#9888;</span>
                            <?php endif ?>
                        </td>
                        <td><span class="chip <?= $kl['class'] ?>"><?= $kl['label'] ?></span></td>
                        <td><?= htmlspecialchars($a['kunden_name']) ?></td>
                        <td style="white-space:nowrap"><?= date('d.m.Y', strtotime($a['erstellt_am'])) ?></td>
                        <td style="text-align:center"><?= (int)$a['positionen_anzahl'] ?></td>
                        <td style="text-align:right;font-weight:600"><?= number_format((float)$a['bruttobetrag'], 2, ',', '.') ?> €</td>
                        <td><span class="chip <?= $za['class'] ?>"><?= $za['label'] ?></span></td>
                        <td><span class="chip <?= $zl['class'] ?>"><?= $zl['label'] ?></span></td>
                        <td><span class="chip <?= $ll['class'] ?>"><?= $ll['label'] ?></span></td>
                        <td>
                            <a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">Detail</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>