<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';

$db   = Database::getInstance();
$modus = $_GET['modus'] ?? 'auftrag';

// ─── Auftrag/Aufträge laden ───────────────────────────────────────────────────

$auftraege = [];

if ($modus === 'pickliste_nr') {
    $plNr = trim($_GET['pickliste_nr'] ?? '');
    $pl   = $db->prepare("SELECT * FROM picklisten WHERE nummer = ?")->execute([$plNr])
        ? $db->prepare("SELECT * FROM picklisten WHERE nummer = ?") : null;
    $stmt = $db->prepare("SELECT * FROM picklisten WHERE nummer = ?");
    $stmt->execute([$plNr]);
    $pickliste = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pickliste) {
        $_SESSION['fehler'] = "Pickliste '{$plNr}' nicht gefunden.";
        header('Location: index.php');
        exit;
    }
    $modus = 'pickliste';
    $_GET['pickliste_id'] = $pickliste['id'];
}

if ($modus === 'pickliste') {
    $picklisteId = (int)($_GET['pickliste_id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM picklisten WHERE id = ?");
    $stmt->execute([$picklisteId]);
    $pickliste = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pickliste) {
        $_SESSION['fehler'] = 'Pickliste nicht gefunden.';
        header('Location: index.php');
        exit;
    }
    $ids = $db->prepare("SELECT auftrag_id FROM pickliste_auftraege WHERE pickliste_id = ?");
    $ids->execute([$picklisteId]);
    $auftragIds = $ids->fetchAll(PDO::FETCH_COLUMN);
    if (empty($auftragIds)) {
        $_SESSION['fehler'] = 'Pickliste hat keine Aufträge.';
        header('Location: index.php');
        exit;
    }
    $in = implode(',', array_map('intval', $auftragIds));
    $auftraege = $db->query("SELECT * FROM auftraege WHERE id IN ({$in}) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Einzelner Auftrag
    if (!empty($_GET['auftrag_id'])) {
        $aId = (int)$_GET['auftrag_id'];
    } else {
        $nr   = trim($_GET['auftrag_nr'] ?? '');
        $stmt = $db->prepare("SELECT id FROM auftraege WHERE auftragsnummer = ?");
        $stmt->execute([$nr]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['fehler'] = "Auftrag '{$nr}' nicht gefunden.";
            header('Location: index.php');
            exit;
        }
        $aId = (int)$row['id'];
    }
    $stmt = $db->prepare("SELECT * FROM auftraege WHERE id = ?");
    $stmt->execute([$aId]);
    $auftrag = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$auftrag) {
        $_SESSION['fehler'] = 'Auftrag nicht gefunden.';
        header('Location: index.php');
        exit;
    }
    $auftraege = [$auftrag];
    $pickliste = null;
}

// Nur den ersten Auftrag aktiv anzeigen (bei Pickliste: einer nach dem anderen)
$aktuellerAuftrag = $auftraege[0];
$auftragId        = (int)$aktuellerAuftrag['id'];

// Positionen laden
$positionen = $db->prepare("
    SELECT ap.*, a.artikelnummer, a.name, a.ean_gtin13,
           a.gewicht_versand,
           (SELECT bi.pfad FROM artikel_bilder bi
            WHERE bi.artikel_id = ap.artikel_id AND bi.ist_hauptbild = 1
            LIMIT 1) AS bild_pfad
    FROM auftrag_positionen ap
    LEFT JOIN artikel a ON a.id = ap.artikel_id
    WHERE ap.auftrag_id = ?
    ORDER BY ap.sort_order, ap.id
");
$positionen->execute([$auftragId]);
$positionen = $positionen->fetchAll(PDO::FETCH_ASSOC);

// Lieferadresse dekodieren
$lieferAdr = !empty($aktuellerAuftrag['lieferadresse_snapshot'])
    ? json_decode($aktuellerAuftrag['lieferadresse_snapshot'], true)
    : json_decode($aktuellerAuftrag['kunden_snapshot'] ?? '{}', true);

// Berechnetes Gesamtgewicht
$gewichtBerechnet = 0;
foreach ($positionen as $pos) {
    $gewichtBerechnet += (float)($pos['gewicht_versand'] ?? 0) * (float)$pos['menge'];
}
$gewichtBerechnet = round($gewichtBerechnet, 3);

// Picklisten-Kontext für Navigations-Button
$picklisteParam = $pickliste ? '?pickliste_id=' . $pickliste['id'] : '';

$pageTitle = 'Verpacken: ' . $aktuellerAuftrag['auftragsnummer'];
$backUrl   = '/mealana/packplatz/warenausgang/index.php';
$headerSub = 'Warenausgang › ' . $aktuellerAuftrag['auftragsnummer'];
require_once __DIR__ . '/../shell_top.php';
?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start">

    <!-- LINKE SEITE: Scan + Tabelle -->
    <div>

        <!-- Scan-Bar -->
        <div class="pp-scan-bar">
            <div>
                <div class="pp-scan-label">Vorwahl</div>
                <input type="number" id="vorwahl" class="pp-vorwahl" value="1" min="1" max="99">
            </div>
            <div style="flex:1">
                <div class="pp-scan-label">EAN / Artikelnummer scannen</div>
                <input type="text" id="scan-field" class="pp-scan-input" style="width:100%"
                    placeholder="Barcode scannen ..."
                    autocomplete="off" autofocus>
            </div>
            <div id="scan-bild-box">
                <div class="pp-scan-bild-placeholder">📷</div>
            </div>
        </div>

        <!-- Positions-Tabelle -->
        <div style="background:#16213e;border-radius:10px;overflow:hidden">
            <table class="pp-table" id="pos-tabelle">
                <thead>
                    <tr>
                        <th style="width:100px">Art.-Nr.</th>
                        <th>Bezeichnung</th>
                        <th style="width:140px">EAN</th>
                        <th style="width:120px;text-align:center">Gescannt / Ges.</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($positionen as $i => $pos): ?>
                    <tr id="pos-row-<?= $i ?>" data-idx="<?= $i ?>"
                        data-ean="<?= htmlspecialchars($pos['ean_gtin13'] ?? '') ?>"
                        data-artnr="<?= htmlspecialchars($pos['artikelnummer'] ?? '') ?>"
                        data-gesamt="<?= (int)$pos['menge'] ?>"
                        data-gescannt="0"
                        data-bild="<?= htmlspecialchars($pos['bild_pfad'] ?? '') ?>"
                        data-name="<?= htmlspecialchars($pos['name'] ?? $pos['bezeichnung_snapshot'] ?? '') ?>">
                        <td style="font-size:13px;font-family:monospace"><?= htmlspecialchars($pos['artikelnummer'] ?? '—') ?></td>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($pos['name'] ?? $pos['bezeichnung_snapshot'] ?? '—') ?></div>
                            <?php if (empty($pos['ean_gtin13'])): ?>
                                <div style="font-size:11px;color:#e94560;margin-top:2px">⚠ Kein EAN</div>
                            <?php endif; ?>
                        </td>
                        <td style="font-family:monospace;font-size:13px;color:#aaa">
                            <?= htmlspecialchars($pos['ean_gtin13'] ?? '—') ?>
                        </td>
                        <td style="text-align:center">
                            <span class="pp-menge-cell">
                                <span id="gescannt-<?= $i ?>">0</span> / <?= (int)$pos['menge'] ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="pp-btn pp-btn-secondary"
                                style="padding:5px 10px;font-size:14px"
                                onclick="manuellesPlus(<?= $i ?>)" title="Manuell +1">+</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Bottom-Bar -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding:16px;background:#16213e;border-radius:10px">
            <button type="button" class="pp-btn pp-btn-warning" onclick="teillieferung()" id="btn-teillieferung">
                Teillieferung
            </button>
            <button type="button" class="pp-btn pp-btn-success" id="btn-verpacken" disabled onclick="verpackenStarten()">
                ✓ Verpacken
            </button>
        </div>

    </div>

    <!-- RECHTE SEITE: Auftrags-Info -->
    <div>
        <div class="pp-auftrag-info">
            <div style="font-size:15px;font-weight:700;margin-bottom:10px;color:#e94560">
                <?= htmlspecialchars($aktuellerAuftrag['auftragsnummer']) ?>
            </div>
            <div>📅 <?= date('d.m.Y', strtotime($aktuellerAuftrag['erstellt_am'])) ?></div>
            <div>💳 <?= htmlspecialchars($aktuellerAuftrag['zahlungsart']) ?>
                — <?= htmlspecialchars($aktuellerAuftrag['zahlungsstatus']) ?>
            </div>
            <div>🚚 <?= htmlspecialchars($aktuellerAuftrag['lieferart']) ?></div>
            <?php if ($lieferAdr): ?>
                <div style="margin-top:10px;border-top:1px solid #0f3460;padding-top:10px">
                    <strong><?= htmlspecialchars(trim(($lieferAdr['vorname'] ?? '') . ' ' . ($lieferAdr['nachname'] ?? ''))) ?></strong><br>
                    <?php if (!empty($lieferAdr['firma'])): ?><?= htmlspecialchars($lieferAdr['firma']) ?><br><?php endif; ?>
                    <?= htmlspecialchars(($lieferAdr['strasse'] ?? '') . ' ' . ($lieferAdr['hausnummer'] ?? '')) ?><br>
                    <?= htmlspecialchars(($lieferAdr['plz'] ?? '') . ' ' . ($lieferAdr['ort'] ?? '')) ?><br>
                    <?= htmlspecialchars($lieferAdr['land'] ?? '') ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($aktuellerAuftrag['notiz_versand'])): ?>
                <div style="margin-top:10px;border-top:1px solid #0f3460;padding-top:10px;color:#f39c12">
                    ⚠ <?= htmlspecialchars($aktuellerAuftrag['notiz_versand']) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($aktuellerAuftrag['notiz_intern'])): ?>
                <div style="margin-top:6px;font-size:12px;color:#888">
                    Intern: <?= htmlspecialchars($aktuellerAuftrag['notiz_intern']) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($pickliste && count($auftraege) > 1): ?>
            <div style="margin-top:16px;padding:12px 16px;background:#16213e;border-radius:8px">
                <div style="font-size:12px;color:#aaa;margin-bottom:8px">Pickliste <?= htmlspecialchars($pickliste['nummer']) ?></div>
                <?php foreach ($auftraege as $i => $a): ?>
                    <div style="font-size:13px;padding:4px 0;color:<?= $a['id'] === $auftragId ? '#e94560' : '#aaa' ?>;font-weight:<?= $a['id'] === $auftragId ? '700' : '400' ?>">
                        <?= $i === 0 ? '▶ ' : '' ?><?= htmlspecialchars($a['auftragsnummer']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- OVERLAY: Verpacken (Gewicht + Tracking) -->
<div class="pp-overlay" id="overlay-verpacken">
    <div class="pp-overlay-box" style="min-width:480px">
        <div class="pp-overlay-titel">📦 Paket fertig</div>

        <div style="margin-bottom:20px">
            <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px">Gewicht (kg) — bitte Paket wiegen:</label>
            <input type="number" id="overlay-gewicht" class="pp-overlay-input"
                value="<?= $gewichtBerechnet ?>"
                step="0.001" min="0.001" style="margin-bottom:0">
            <div style="font-size:11px;color:#555;margin-top:4px">
                Berechnet aus Artikelgewichten: <?= $gewichtBerechnet ?> kg
            </div>
        </div>

        <div>
            <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px">Trackingnummer scannen:</label>
            <input type="text" id="overlay-tracking" class="pp-overlay-input"
                placeholder="Barcode vom Label scannen ..."
                autocomplete="off">
        </div>

        <div style="display:flex;gap:12px;justify-content:center;margin-top:24px">
            <button class="pp-btn pp-btn-secondary" onclick="overlaySchliessen()">Abbrechen</button>
            <button class="pp-btn pp-btn-success" id="btn-tracking-ok" onclick="verpackenAbschliessen(false)" disabled>
                ✓ Abschließen
            </button>
        </div>
    </div>
</div>

<!-- OVERLAY: Teillieferung -->
<div class="pp-overlay" id="overlay-teillieferung">
    <div class="pp-overlay-box" style="min-width:480px">
        <div class="pp-overlay-titel">📦 Teillieferung</div>
        <div style="color:#aaa;font-size:14px;margin-bottom:20px">
            Nur die gescannten Positionen werden jetzt versendet.<br>
            Fehlende Artikel bleiben im Auftrag und kommen erneut auf die Pickliste.
        </div>
        <div>
            <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px">Gewicht (kg):</label>
            <input type="number" id="overlay-tl-gewicht" class="pp-overlay-input"
                value="<?= $gewichtBerechnet ?>" step="0.001" min="0.001" style="margin-bottom:16px">
            <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px">Trackingnummer scannen:</label>
            <input type="text" id="overlay-tl-tracking" class="pp-overlay-input"
                placeholder="Barcode vom Label scannen ..." autocomplete="off">
        </div>
        <div style="display:flex;gap:12px;justify-content:center;margin-top:24px">
            <button class="pp-btn pp-btn-secondary" onclick="document.getElementById('overlay-teillieferung').classList.remove('aktiv')">Abbrechen</button>
            <button class="pp-btn pp-btn-warning" id="btn-tl-ok" onclick="verpackenAbschliessen(true)" disabled>
                Teillieferung abschließen
            </button>
        </div>
    </div>
</div>

<script>
const POSITIONEN = <?= json_encode(array_map(fn($p, $i) => [
    'idx'     => $i,
    'ean'     => $p['ean_gtin13'] ?? '',
    'artnr'   => $p['artikelnummer'] ?? '',
    'gesamt'  => (int)$p['menge'],
    'name'    => $p['name'] ?? $p['bezeichnung_snapshot'] ?? '',
    'bild'    => $p['bild_pfad'] ?? '',
], $positionen, array_keys($positionen))) ?>;
const AUFTRAG_ID    = <?= $auftragId ?>;
const PICKLISTE_ID  = <?= $pickliste ? $pickliste['id'] : 'null' ?>;
const IS_VERSAND    = <?= json_encode($aktuellerAuftrag['lieferart'] === 'versand') ?>;
</script>
<script src="/mealana/js/packplatz_scan.js"></script>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
