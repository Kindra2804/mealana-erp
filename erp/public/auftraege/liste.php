<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';

$service = new AuftragService();
$alleShops = Database::getInstance()->query("SELECT id, name FROM shops WHERE ist_aktiv = 1 ORDER BY id")->fetchAll();

$filterZahlung        = $_GET['zahlung']  ?? '';
$filterLieferung      = $_GET['lieferung'] ?? '';
$filterKanal          = $_GET['kanal']    ?? '';
$suche                = $_GET['suche']    ?? '';
$mitAbgeschlossenen   = isset($_GET['abgeschlossene']);
// Belege-Filter aus den Kacheln "Offene Werte" (Buchhaltung → Zahlungs-Kontrolle, Dashboard)
$belegFilterLabels = [
    'bestand'          => 'Auftragsbestand — bestellt, noch nicht geliefert',
    'nicht_verrechnet' => 'Geliefert, aber noch nicht verrechnet',
    'rechnung_offen'   => 'Offene Rechnungen — verrechnet, noch nicht bezahlt',
];
$belegFilter = isset($belegFilterLabels[$_GET['belege'] ?? '']) ? $_GET['belege'] : '';

// ── Zeitraum-Filter (Presets analog auftraege/statistik.php, plus Quartal/6-Monate/
//    Jahr-Monat-Auswahl -- wichtig geworden, seit der JTL-Archiv-Import Aufträge
//    bis 2013 zurück in die Liste bringt) ─────────────────────────────────────────
// Standard "Dieser Monat" -- "Alle Zeiträume" lädt mit dem JTL-Archiv ~39.000 Aufträge.
// Suche, Status-Filter und Kachel-Filter (belege=...) sollen aber auch Ältere finden -> dann alle.
$zeitraum = $_GET['zeitraum'] ?? (($suche !== '' || $belegFilter !== '' || $filterLieferung !== '' || $filterZahlung !== '') ? 'alle' : 'monat');
// Suche (auch Teilnummer wie "0051") immer über alle Zeiträume -- die Auswahl steht sonst
// auf "Dieser Monat" und würde mitgeschickt (Klicktest 2026-10-07)
if ($suche !== '') $zeitraum = 'alle';
$heute    = date('Y-m-d');
$von = null;
$bis = null;
$jmJahr  = (int)($_GET['jm_jahr']  ?? date('Y'));
$jmMonat = (int)($_GET['jm_monat'] ?? 0);

switch ($zeitraum) {
    case 'monat':
        $von = date('Y-m-01'); $bis = $heute; break;
    case 'quartal':
        $quartalStartMonat = (intdiv((int)date('n') - 1, 3) * 3) + 1;
        $von = date('Y-' . str_pad((string)$quartalStartMonat, 2, '0', STR_PAD_LEFT) . '-01');
        $bis = $heute;
        break;
    case '6monate':
        $von = date('Y-m-d', strtotime('-6 months')); $bis = $heute; break;
    case 'jahr':
        $von = date('Y-01-01'); $bis = $heute; break;
    case 'jahrmonat':
        if ($jmMonat >= 1 && $jmMonat <= 12) {
            $von = sprintf('%04d-%02d-01', $jmJahr, $jmMonat);
            $bis = date('Y-m-t', strtotime($von));
        } else {
            $von = sprintf('%04d-01-01', $jmJahr);
            $bis = sprintf('%04d-12-31', $jmJahr);
        }
        break;
    case 'frei':
        $von = $_GET['von'] ?? null;
        $bis = $_GET['bis'] ?? null;
        break;
    default: // 'alle'
        $zeitraum = 'alle';
        break;
}

$jahrVon = (int)date('Y', strtotime((Database::getInstance()->query("SELECT MIN(erstellt_am) FROM auftraege")->fetchColumn()) ?: $heute));
$jahrBis = (int)date('Y');

$auftraege = $service->getAll($filterZahlung, $filterLieferung, $filterKanal, $suche, $mitAbgeschlossenen, $von, $bis, $belegFilter);
// JTL-Archiv hat keine Belege im ERP -> nicht abfragen (bei "Alle Zeiträume" ~39.000 Aufträge)
$belege    = $service->getBelegeFuerAuftraege(array_column(array_filter($auftraege, fn($a) => $a['kanal'] !== 'jtl_archiv'), 'id'));

// Belege-Spalte: Kürzel, Bezeichnung, Farbe (Hintergrund/Rahmen/Text) -- siehe
// docs/design/belege_spalte_uebersicht_mockup.svg (von Barbara freigegeben 2026-10-07)
$belegTypen = [
    'ab'  => ['AB',  'Auftragsbestätigung', '#e0e7ff', '#6366f1', '#3730a3'],
    'ls'  => ['LS',  'Lieferschein',        '#fef3c7', '#d97706', '#92400e'],
    'rg'  => ['RG',  'Rechnung',            '#dcfce7', '#16a34a', '#166534'],
    'gs'  => ['RK',  'Rechnungskorrektur / Storno', '#fee2e2', '#dc2626', '#991b1b'],
    'az'  => ['AZ',  'Abholzettel',         '#f3e8ff', '#9333ea', '#6b21a8'],
    'bon' => ['Bon', 'Kassenbon (= Rechnung)', '#e0f2fe', '#0284c7', '#075985'],
];
// Welche Kürzel je Auftrag immer (auch grau) gezeigt werden
$belegeSpalte = function (array $a, array $b): array {
    if ($a['kanal'] === 'kasse') return ['bon'];
    if ($a['kanal'] === 'jtl_archiv') return [];
    $liste = ['ab', ($a['lieferart'] ?? '') === 'abholung' ? 'az' : 'ls', 'rg', 'gs'];
    if ($b['bon']) $liste[] = 'bon';
    if ($b['ls'] && !in_array('ls', $liste, true)) array_splice($liste, 2, 0, ['ls']);
    return $liste;
};

$zahlungsLabels = [
    'ausstehend'   => ['label' => 'Ausstehend',  'class' => 'chip-auslauf'],
    'bezahlt'      => ['label' => 'Bezahlt',     'class' => 'chip-aktiv'],
    'teilbezahlt'  => ['label' => 'Teilbezahlt', 'class' => 'chip-auslauf'],
    'ueberbezahlt' => ['label' => 'Überbezahlt', 'class' => 'chip-auslauf'],
    'erstattet'    => ['label' => 'Erstattet',   'class' => 'chip-inaktiv'],
    'gutschrift'   => ['label' => 'Guthaben',    'class' => 'chip-inaktiv'],
    'storniert'    => ['label' => 'Storniert',   'class' => 'chip-inaktiv'],
];
$zahlungsArtLabels = [
    'vorkasse'    => ['label' => 'Vorkasse',  'class' => 'chip-aktiv'],
    'paypal'      => ['label' => 'PayPal',      'class' => 'sc-aktion'],
    'rechnung'    => ['label' => 'Rechnung',  'class' => 'sc-fehlbest'],
    'bar'         => ['label' => 'Bar',    'class' => 'chip-aktiv'],
    'karte'       => ['label' => 'Karte',  'class' => 'chip-aktiv'],
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
    'retoure_offen'    => ['label' => 'Retoure offen',    'class' => 'chip-auslauf'],
    'storniert'        => ['label' => 'Storniert',        'class' => 'chip-inaktiv'],
    'kommissioniert'   => ['label' => 'Kommissioniert',   'class' => 'chip-auslauf'],
    'abholbereit'      => ['label' => 'Abholbereit',      'class' => 'chip-aktiv'],
];
$kanalLabels = [
    'woocommerce' => ['label' => 'WooCommerce', 'class' => 'chip-aktiv'],
    'manuell'     => ['label' => 'Manuell',     'class' => 'chip-auslauf'],
    'kasse'       => ['label' => 'Kasse',        'class' => 'chip-inaktiv'],
    'jtl_archiv'  => ['label' => 'Archiv',       'class' => 'chip-inaktiv'],
    'haendler'    => ['label' => 'Händler',      'class' => 'chip-auslauf'],
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
        <option value="retoure_offen" <?= $filterLieferung === 'retoure_offen'   ? 'selected' : '' ?>>Retoure offen</option>
        <option value="kommissioniert" <?= $filterLieferung === 'kommissioniert' ? 'selected' : '' ?>>Kommissioniert</option>
        <option value="abholbereit" <?= $filterLieferung === 'abholbereit'    ? 'selected' : '' ?>>Abholbereit</option>
    </select>
    <select class="erp-select" style="font-size:13px" id="filter-kanal">
        <option value="">Alle Kanäle</option>
        <option value="woocommerce" <?= $filterKanal === 'woocommerce' ? 'selected' : '' ?>>WooCommerce (alle Shops)</option>
        <?php foreach ($alleShops as $shop): ?>
            <option value="shop:<?= $shop['id'] ?>" <?= $filterKanal === 'shop:' . $shop['id'] ? 'selected' : '' ?>>
                &nbsp;&nbsp;– <?= htmlspecialchars($shop['name']) ?>
            </option>
        <?php endforeach; ?>
        <option value="manuell" <?= $filterKanal === 'manuell'     ? 'selected' : '' ?>>Manuell</option>
        <option value="kasse" <?= $filterKanal === 'kasse'       ? 'selected' : '' ?>>Kasse</option>
        <option value="jtl_archiv" <?= $filterKanal === 'jtl_archiv' ? 'selected' : '' ?>>Archiv</option>
    </select>
    <select class="erp-select" style="font-size:13px" id="filter-zeitraum">
        <option value="alle" <?= $zeitraum === 'alle' ? 'selected' : '' ?>>Alle Zeiträume</option>
        <option value="monat" <?= $zeitraum === 'monat' ? 'selected' : '' ?>>Dieser Monat</option>
        <option value="quartal" <?= $zeitraum === 'quartal' ? 'selected' : '' ?>>Dieses Quartal</option>
        <option value="6monate" <?= $zeitraum === '6monate' ? 'selected' : '' ?>>Letzte 6 Monate</option>
        <option value="jahr" <?= $zeitraum === 'jahr' ? 'selected' : '' ?>>Dieses Jahr</option>
        <option value="jahrmonat" <?= $zeitraum === 'jahrmonat' ? 'selected' : '' ?>>Jahr/Monat wählen…</option>
        <option value="frei" <?= $zeitraum === 'frei' ? 'selected' : '' ?>>Freier Zeitraum…</option>
    </select>
    <?php if ($zeitraum === 'jahrmonat'): ?>
        <select class="erp-select" style="font-size:13px" id="filter-jm-jahr">
            <?php for ($j = $jahrBis; $j >= $jahrVon; $j--): ?>
                <option value="<?= $j ?>" <?= $jmJahr === $j ? 'selected' : '' ?>><?= $j ?></option>
            <?php endfor; ?>
        </select>
        <select class="erp-select" style="font-size:13px" id="filter-jm-monat">
            <option value="0" <?= $jmMonat === 0 ? 'selected' : '' ?>>Alle Monate</option>
            <?php
            $monatsnamen = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
            foreach ($monatsnamen as $i => $mn): ?>
                <option value="<?= $i + 1 ?>" <?= $jmMonat === $i + 1 ? 'selected' : '' ?>><?= $mn ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($zeitraum === 'frei'): ?>
        <input type="date" class="erp-input" style="font-size:13px" id="filter-von" value="<?= htmlspecialchars($von ?? '') ?>">
        <span style="color:#94a3b8">bis</span>
        <input type="date" class="erp-input" style="font-size:13px" id="filter-bis" value="<?= htmlspecialchars($bis ?? '') ?>">
        <button type="button" class="btn btn-secondary btn-sm" onclick="applyFilter()">Anwenden</button>
    <?php endif; ?>
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:var(--color-text-muted)">
        <input type="checkbox" id="filter-abgeschlossene" onchange="applyFilter()" <?= $mitAbgeschlossenen ? 'checked' : '' ?>>
        Abgeschlossene anzeigen
    </label>
</div>

<script>
    function applyFilter() {
        const p = new URLSearchParams();
        const s  = document.getElementById('suche-input').value.trim();
        const z  = document.getElementById('filter-zahlung').value;
        const l  = document.getElementById('filter-lieferung').value;
        const k  = document.getElementById('filter-kanal').value;
        const a  = document.getElementById('filter-abgeschlossene').checked;
        const zr = document.getElementById('filter-zeitraum').value;
        if (s) p.set('suche', s);
        if (z) p.set('zahlung', z);
        if (l) p.set('lieferung', l);
        if (k) p.set('kanal', k);
        if (a) p.set('abgeschlossene', '1');
        if (zr) p.set('zeitraum', zr);

        const jmJahr  = document.getElementById('filter-jm-jahr');
        const jmMonat = document.getElementById('filter-jm-monat');
        if (jmJahr) p.set('jm_jahr', jmJahr.value);
        if (jmMonat) p.set('jm_monat', jmMonat.value);

        const von = document.getElementById('filter-von');
        const bis = document.getElementById('filter-bis');
        if (von && von.value) p.set('von', von.value);
        if (bis && bis.value) p.set('bis', bis.value);

        window.location = '?' + p.toString();
    }
    document.getElementById('filter-zahlung').addEventListener('change', applyFilter);
    document.getElementById('filter-lieferung').addEventListener('change', applyFilter);
    document.getElementById('filter-kanal').addEventListener('change', applyFilter);
    document.getElementById('filter-zeitraum').addEventListener('change', applyFilter);
    document.getElementById('filter-jm-jahr')?.addEventListener('change', applyFilter);
    document.getElementById('filter-jm-monat')?.addEventListener('change', applyFilter);
</script>

<?php if ($belegFilter): ?>
<div class="card" style="margin-bottom:10px;padding:8px 14px;background:#eff6ff;border-left:3px solid #2563eb">
    Gefiltert: <strong><?= htmlspecialchars($belegFilterLabels[$belegFilter]) ?></strong>
    · <a href="<?= BASE_PATH ?>/auftraege/liste.php">✕ Filter entfernen</a>
</div>
<?php endif; ?>

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
                    <th>Belege</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auftraege as $a):
                    $za = $zahlungsArtLabels[$a['zahlungsart']] ?? ['label' => $a['zahlungsart'], 'class' => ''];
                    $zStatus = $a['zahlungsstatus'];
                    if ((float)$a['bruttobetrag'] < 0) {
                        // Kassenbon mit Retoure/Gutschrift (Spiegel-Auftrag mit negativem Betrag)
                        $zStatus = 'gutschrift';
                    } elseif ($zStatus === 'bezahlt' && (float)($a['summe_zahlungen'] ?? 0) > (float)$a['bruttobetrag']) {
                        $zStatus = 'ueberbezahlt';
                    }
                    $zl = $zahlungsLabels[$zStatus] ?? ['label' => $zStatus, 'class' => ''];
                    $ll = $lieferLabels[$a['lieferstatus']]     ?? ['label' => $a['lieferstatus'],   'class' => ''];
                    $kl = $kanalLabels[$a['kanal']]             ?? ['label' => $a['kanal'],          'class' => ''];
                    // WooCommerce: echten Shop-Namen zeigen (Mealana/Bio-Wolle/Sockenwolle)
                    // statt des generischen "WooCommerce"-Labels -- wie in Einstellungen → Kanäle.
                    if ($a['kanal'] === 'woocommerce' && !empty($a['shop_name'])) {
                        $kl = ['label' => $a['shop_name'], 'class' => $kl['class']];
                    }
                    $istErledigt = $a['lieferstatus'] === 'abgeschlossen' && in_array($a['zahlungsstatus'], ['bezahlt', 'erstattet'], true);
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
                        <td>
                            <span class="chip <?= $ll['class'] ?>"><?= $ll['label'] ?></span>
                            <?php if (($a['lieferart'] ?? '') === 'abholung'): ?>
                                <span class="chip sc-aktion" title="Selbstabholung">🏬 Abholung</span>
                            <?php endif; ?>
                        </td>
                        <td class="belege-zelle" style="white-space:nowrap">
                            <?php $b = $belege[$a['id']] ?? ['ab' => [], 'ls' => [], 'rg' => [], 'gs' => [], 'az' => [], 'bon' => [], 'nicht_verrechnet' => false];
                            foreach ($belegeSpalte($a, $b) as $typ):
                                [$kurz, $name, $bg, $rand, $farbe] = $belegTypen[$typ];
                                $docs = array_map(fn($d) => [
                                    'nr' => $d['nr'], 'datum' => $d['datum'],
                                    'url' => isset($d['bon_id'])
                                        ? BASE_PATH . '/kasse/bon_a4.php?id=' . $d['bon_id']
                                        : BASE_PATH . '/auftraege/dokument_download.php?auftrag_id=' . $a['id'] . '&datei=' . rawurlencode($d['datei']),
                                ], $b[$typ]);
                                $anz = count($docs);
                            ?>
                                <span class="beleg-chip<?= $anz ? ' beleg-da' : '' ?>"
                                      style="<?= $anz ? "background:$bg;border-color:$rand;color:$farbe" : '' ?>"
                                      title="<?= $name . ($anz ? '' : ' — noch keine') ?>"
                                      <?= $anz ? 'data-titel="' . htmlspecialchars($name . ' zu ' . $a['auftrag_nr']) . '" data-docs="' . htmlspecialchars(json_encode($docs, JSON_UNESCAPED_UNICODE)) . '"' : '' ?>>
                                    <?= $kurz ?><?php if ($anz > 1): ?><span class="beleg-anz" style="background:<?= $rand ?>"><?= $anz ?></span><?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if ($b['nicht_verrechnet']): ?>
                                <span class="beleg-warn" title="Ausgeliefert, aber noch auf keinem Beleg — bitte Rechnung erstellen">!</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">Detail</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<style>
.beleg-chip { position:relative; display:inline-block; min-width:30px; padding:2px 6px; margin-right:4px; border:1px solid #cbd5e1;
              border-radius:5px; background:#f1f5f9; color:#94a3b8; font-size:11px; font-weight:700; text-align:center; cursor:default; }
.beleg-chip.beleg-da { cursor:pointer; }
.beleg-anz  { position:absolute; top:-7px; right:-7px; min-width:15px; height:15px; line-height:15px; border-radius:8px;
              color:#fff; font-size:9px; text-align:center; }
.beleg-warn { display:inline-block; width:20px; height:20px; line-height:20px; border-radius:50%; background:#2563eb;
              color:#fff; font-weight:700; text-align:center; vertical-align:middle; }
.beleg-popup { position:absolute; z-index:2000; background:#fff; border:1.5px solid #16a34a; border-radius:6px;
               box-shadow:0 4px 12px rgba(0,0,0,.15); padding:8px 12px; min-width:230px; font-size:12px; }
.beleg-popup .titel { font-weight:700; font-size:11px; margin-bottom:4px; }
.beleg-popup a { display:block; padding:3px 0; color:var(--color-text); text-decoration:none; }
.beleg-popup a:hover { text-decoration:underline; }
.beleg-popup .hint { color:var(--color-text-muted); font-size:10px; margin-top:2px; }
</style>
<script src="<?= BASE_PATH ?>/js/auftraege_liste_belege.js?v=<?= filemtime(__DIR__ . '/../js/auftraege_liste_belege.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>