<?php
/**
 * JTL-Lagerabgleich (Parallelbetrieb, Migration 231): Schritt 1 ERP -> JTL (Ameise-Datei),
 * Schritt 2 JTL -> ERP (eigener Export). Logik: src/modules/lager/JtlAbgleichService.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/JtlAbgleichService.php';

$service    = new JtlAbgleichService();
$benutzerId = (int) ($_SESSION['benutzer']['id'] ?? 0);

// --- Download der Ameise-Datei ---
if (isset($_GET['download'])) {
    $laufId = (int) $_GET['download'];
    $csv = $service->exportCsv($laufId);
    header('Content-Type: text/csv; charset=windows-1252');
    header('Content-Disposition: attachment; filename="ERP-nach-JTL_Abgleich-' . $laufId . '_' . date('Y-m-d_Hi') . '.csv"');
    echo $csv;
    exit;
}

// --- Aktionen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion = $_POST['aktion'] ?? '';
    try {
        switch ($aktion) {
            case 'export':
                $lauf = $service->erstelleExport($benutzerId);
                $_SESSION['erfolg'] = $lauf['status'] === 'export_offen'
                    ? "Export #{$lauf['id']} erstellt ({$lauf['export_zeilen']} Zeilen). Datei herunterladen und in JTL einspielen."
                    : "Keine Bestandsänderungen im ERP seit dem letzten Abgleich — nichts nach JTL zu übertragen. Weiter mit Schritt 2.";
                break;
            case 'bestaetigen':
                $service->bestaetigeExport((int) $_POST['lauf_id']);
                $_SESSION['erfolg'] = 'Export als „in JTL eingespielt“ bestätigt. Jetzt den JTL-Bestand exportieren und hier hochladen.';
                break;
            case 'verwerfen':
                $service->verwerfeExport((int) $_POST['lauf_id']);
                $_SESSION['erfolg'] = 'Export verworfen. Die Änderungen kommen mit dem nächsten Export wieder.';
                break;
            case 'vorschau':
                if (empty($_FILES['jtl_datei']['tmp_name']) || !is_uploaded_file($_FILES['jtl_datei']['tmp_name'])) {
                    throw new RuntimeException('Bitte die JTL-Datei auswählen.');
                }
                $_SESSION['jtl_abgleich_vorschau'] = $service->importVorschau($_FILES['jtl_datei']['tmp_name']);
                $_SESSION['jtl_abgleich_datei']    = $_FILES['jtl_datei']['name'];
                break;
            case 'buchen':
                $v = $_SESSION['jtl_abgleich_vorschau'] ?? null;
                if (!$v) throw new RuntimeException('Keine Vorschau vorhanden — Datei bitte neu hochladen.');
                $erg = $service->importBuchen($v, $_SESSION['jtl_abgleich_datei'] ?? '', $benutzerId, !empty($_POST['plausi_ok']));
                unset($_SESSION['jtl_abgleich_vorschau'], $_SESSION['jtl_abgleich_datei']);
                $_SESSION['erfolg'] = "Abgleich #{$erg['lauf_id']} gebucht: {$erg['buchungen']} Bestandskorrekturen (+"
                    . number_format($erg['zugang'], 0, ',', '.') . ' / −' . number_format($erg['abgang'], 0, ',', '.') . ' Stk.), '
                    . number_format($erg['artikel_umfang'], 0, ',', '.') . ' Artikel im Abgleich.';
                break;
            case 'vorschau_verwerfen':
                unset($_SESSION['jtl_abgleich_vorschau'], $_SESSION['jtl_abgleich_datei']);
                break;
        }
    } catch (Throwable $e) {
        $_SESSION['fehler'] = [$e->getMessage()];
    }
    header('Location: jtl_abgleich.php');
    exit;
}

$fehler = $_SESSION['fehler'] ?? [];
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$schritt  = $service->naechsterSchritt();
$vorschau = $_SESSION['jtl_abgleich_vorschau'] ?? null;
$verlauf  = $service->verlauf(15);
$umfang   = $service->umfangAnzahl();
$exportZeilen = ($schritt['schritt'] === 'bestaetigen') ? $service->exportVorschau((int) $schritt['lauf']['id'], 30) : [];

$fmt = fn($z) => rtrim(rtrim(number_format((float) $z, 3, ',', '.'), '0'), ',');
$datum = fn($d) => $d ? date('d.m.Y H:i', strtotime($d)) : '–';
$statusText = ['export_offen' => 'Export offen', 'export_bestaetigt' => 'bereit für JTL-Import', 'abgeschlossen' => 'abgeschlossen', 'verworfen' => 'verworfen'];

$pageTitle    = 'JTL-Abgleich';
$activeModule = 'lager';
$basePath     = BASE_PATH;
$actionBarContent = <<<HTML
    <a href="{$basePath}/lager/uebersicht.php" class="btn btn-secondary btn-sm">← Lagerübersicht</a>
HTML;
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
    <div class="card" style="border-left:4px solid var(--color-danger);margin-bottom:12px">
        <ul style="margin:0;padding-left:18px;color:var(--color-danger)">
            <?php foreach ((array) $fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($erfolg): ?>
    <div class="card" style="border-left:4px solid var(--color-success);margin-bottom:12px">
        <p style="margin:0;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></p>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom:12px">
    <p style="margin:0 0 6px"><strong>Parallelbetrieb mit JTL:</strong> MeaLana-Shop und Laden buchen in JTL, SOW im ERP. Mindestens einmal täglich abgleichen —
        <strong>immer zuerst Schritt 1, dann Schritt 2.</strong></p>
    <p style="margin:0;font-size:13px;color:var(--color-text-muted)">Im Abgleich: <?= number_format($umfang, 0, ',', '.') ?> Artikel (alle Artikelnummern der letzten JTL-Datei).
        Artikel, die es nur im ERP gibt, bleiben unberührt.</p>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">

    <!-- Schritt 1 -->
    <div class="card" style="<?= in_array($schritt['schritt'], ['export', 'bestaetigen'], true) ? 'border:2px solid var(--color-primary)' : 'opacity:.75' ?>">
        <h3 style="margin:0 0 8px">① ERP → JTL</h3>
        <?php if ($schritt['schritt'] === 'erstabgleich'): ?>
            <p style="font-size:13px">Noch kein Abgleich gemacht. Beim ersten Mal entfällt Schritt 1 — direkt mit Schritt 2 (Erstabgleich) beginnen.</p>
        <?php elseif ($schritt['schritt'] === 'export'): ?>
            <p style="font-size:13px"><strong><?= (int) $schritt['delta_anzahl'] ?></strong> Bestandsänderungen im ERP seit dem letzten Abgleich (SOW-Verkäufe, Retouren, Inventur …).</p>
            <form method="POST"><input type="hidden" name="aktion" value="export">
                <button class="btn btn-primary btn-sm">Export erstellen</button></form>
        <?php elseif ($schritt['schritt'] === 'bestaetigen'): $l = $schritt['lauf']; ?>
            <p style="font-size:13px">Export #<?= $l['id'] ?> vom <?= $datum($l['export_am']) ?> · <?= (int) $l['export_zeilen'] ?> Zeilen</p>
            <p><a class="btn btn-primary btn-sm" href="?download=<?= $l['id'] ?>">⬇ Ameise-Datei herunterladen</a></p>
            <ol style="font-size:13px;padding-left:18px;margin:8px 0">
                <li>JTL-Ameise → Artikel → <strong>Preise/Bestände (QuickSync)</strong></li>
                <li>Datei laden, Trennzeichen Semikolon, Kopfzeile, Encoding windows-1252</li>
                <li>Spalten: Artikelnummer, Lagername, Charge, Lagerbestand</li>
                <li>Importoption „Nur vorhandene Artikel aktualisieren“, <strong>„Eigenen Lagerbestand aktualisieren“ = „Neuen und alten Lagerbestand addieren“</strong></li>
                <li>Import starten, Protokoll prüfen</li>
            </ol>
            <?php if ($l['export_hinweise']): ?>
                <div style="font-size:12px;background:#fff7ed;border-radius:6px;padding:6px 10px;margin:8px 0"><?= nl2br(htmlspecialchars($l['export_hinweise'])) ?></div>
            <?php endif; ?>
            <div style="display:flex;gap:8px;margin-top:10px">
                <form method="POST"><input type="hidden" name="aktion" value="bestaetigen"><input type="hidden" name="lauf_id" value="<?= $l['id'] ?>">
                    <button class="btn btn-primary btn-sm">✓ In JTL eingespielt</button></form>
                <form method="POST" onsubmit="return confirm('Export verwerfen? Nur, wenn die Datei NICHT in JTL eingespielt wurde.')"><input type="hidden" name="aktion" value="verwerfen"><input type="hidden" name="lauf_id" value="<?= $l['id'] ?>">
                    <button class="btn btn-secondary btn-sm">Verwerfen (nicht eingespielt)</button></form>
            </div>
            <?php if ($exportZeilen): ?>
                <details style="margin-top:10px"><summary style="cursor:pointer;font-size:13px">Inhalt ansehen (erste <?= count($exportZeilen) ?>)</summary>
                    <table class="erp-table" style="font-size:12px;margin-top:6px"><thead><tr><th>Artikelnummer</th><th>Lager</th><th>Charge</th><th style="text-align:right">Menge</th></tr></thead><tbody>
                    <?php foreach ($exportZeilen as $z): ?><tr><td><?= htmlspecialchars($z[0]) ?></td><td><?= htmlspecialchars($z[1]) ?></td><td><?= htmlspecialchars($z[2]) ?></td><td style="text-align:right"><?= htmlspecialchars($z[3]) ?></td></tr><?php endforeach; ?>
                    </tbody></table></details>
            <?php endif; ?>
        <?php else: ?>
            <p style="font-size:13px;color:var(--color-success)">✓ Erledigt — keine offenen ERP-Änderungen für JTL.</p>
        <?php endif; ?>
    </div>

    <!-- Schritt 2 -->
    <?php $importMoeglich = in_array($schritt['schritt'], ['erstabgleich', 'import', 'import_ohne_aenderung'], true); ?>
    <div class="card" style="<?= $importMoeglich ? 'border:2px solid var(--color-primary)' : 'opacity:.75' ?>">
        <h3 style="margin:0 0 8px">② JTL → ERP<?= $schritt['schritt'] === 'erstabgleich' ? ' (Erstabgleich)' : '' ?></h3>
        <?php if (!$importMoeglich): ?>
            <p style="font-size:13px">🔒 Gesperrt, bis Schritt 1 erledigt und bestätigt ist.</p>
        <?php elseif (!$vorschau): ?>
            <p style="font-size:13px">In JTL den eigenen Export <strong>„Lagerabgleich_ERP“</strong> ausführen (alle Artikel, auch mit Bestand 0) und die Datei hier hochladen.
                Erst wird nur eine Vorschau gerechnet.</p>
            <?php if ($schritt['schritt'] === 'erstabgleich'): ?>
                <p style="font-size:12px;background:#eff6ff;border-radius:6px;padding:6px 10px">Erstabgleich: Der ERP-Bestand aller Artikel aus der Datei wird auf den JTL-Stand gesetzt (= Startbestand).</p>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data"><input type="hidden" name="aktion" value="vorschau">
                <input type="file" name="jtl_datei" accept=".csv,.txt" class="erp-select" style="width:100%;margin-bottom:8px">
                <button class="btn btn-primary btn-sm">Vorschau rechnen</button></form>
        <?php else: ?>
            <p style="font-size:13px;margin:0 0 6px">Datei: <strong><?= htmlspecialchars($_SESSION['jtl_abgleich_datei'] ?? '') ?></strong> · <?= number_format($vorschau['zeilen_datei'], 0, ',', '.') ?> Zeilen</p>
            <ul style="font-size:13px;margin:0 0 8px;padding-left:18px">
                <li><?= number_format($vorschau['artikel_umfang'], 0, ',', '.') ?> Artikel im Abgleich<?= $vorschau['neu_im_umfang'] ? ' (davon ' . number_format($vorschau['neu_im_umfang'], 0, ',', '.') . ' neu)' : '' ?></li>
                <li><strong><?= count($vorschau['buchungen']) ?> Bestandskorrekturen</strong>: +<?= $fmt($vorschau['zugang']) ?> / −<?= $fmt($vorschau['abgang']) ?> Stk.</li>
                <li><?= number_format($vorschau['nicht_im_erp'], 0, ',', '.') ?> Zeilen mit Artikelnummern, die es im ERP nicht gibt (übersprungen)</li>
                <?php if ($vorschau['ueberverkauf']): ?>
                    <li style="color:#c2410c"><?= count($vorschau['ueberverkauf']) ?> × wäre negativ (überverkauft) → auf 0 gesetzt: <?= htmlspecialchars(implode('; ', array_slice($vorschau['ueberverkauf'], 0, 5))) ?></li>
                <?php endif; ?>
            </ul>
            <?php if ($vorschau['braucht_bestaetigung']): ?>
                <div style="background:#fef2f2;border-radius:6px;padding:8px 10px;font-size:13px;color:#dc2626;margin-bottom:8px">
                    <strong>Achtung:</strong> <?= $vorschau['auf_null'] ?> von <?= $vorschau['mit_bestand'] ?> Artikeln mit Bestand würden auf 0 gesetzt.
                    Ist die JTL-Datei vollständig (alle Artikel, nicht gefiltert)?</div>
            <?php endif; ?>
            <?php if ($vorschau['beispiele']): ?>
                <details style="margin-bottom:8px"><summary style="cursor:pointer;font-size:13px">Größte Korrekturen ansehen</summary>
                    <table class="erp-table" style="font-size:12px;margin-top:6px"><thead><tr><th>Artikel · Lager · Charge</th><th style="text-align:right">ERP</th><th style="text-align:right">neu</th></tr></thead><tbody>
                    <?php foreach ($vorschau['beispiele'] as $b): ?><tr><td><?= htmlspecialchars($b['text']) ?></td><td style="text-align:right"><?= $fmt($b['vorher']) ?></td><td style="text-align:right"><?= $fmt($b['nachher']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></details>
            <?php endif; ?>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <form method="POST"><input type="hidden" name="aktion" value="buchen">
                    <?php if ($vorschau['braucht_bestaetigung']): ?>
                        <label style="font-size:13px;display:block;margin-bottom:6px"><input type="checkbox" name="plausi_ok" value="1" required> Ja, die Datei ist vollständig</label>
                    <?php endif; ?>
                    <button class="btn btn-primary btn-sm">Abgleich buchen</button></form>
                <form method="POST"><input type="hidden" name="aktion" value="vorschau_verwerfen"><button class="btn btn-secondary btn-sm">Andere Datei</button></form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3 style="margin:0 0 8px">Verlauf</h3>
    <?php if (!$verlauf): ?>
        <p style="font-size:13px;color:var(--color-text-muted)">Noch kein Abgleich.</p>
    <?php else: ?>
        <table class="erp-table" style="font-size:13px">
            <thead><tr><th>#</th><th>Status</th><th>Export</th><th style="text-align:right">Zeilen</th><th>Bestätigt</th><th>JTL-Import</th><th>Ergebnis</th><th>Wer</th></tr></thead>
            <tbody>
            <?php foreach ($verlauf as $l): $e = $l['import_ergebnis'] ? json_decode($l['import_ergebnis'], true) : null; ?>
                <tr>
                    <td><?= $l['id'] ?><?= $l['ist_erstabgleich'] ? ' (Erst)' : '' ?></td>
                    <td><?= $statusText[$l['status']] ?? $l['status'] ?></td>
                    <td><?= $datum($l['export_am']) ?></td>
                    <td style="text-align:right"><?= (int) $l['export_zeilen'] ?><?php if ($l['export_zeilen'] && $l['status'] !== 'verworfen'): ?> <a href="?download=<?= $l['id'] ?>" title="Datei erneut laden">⬇</a><?php endif; ?></td>
                    <td><?= $datum($l['export_bestaetigt_am']) ?></td>
                    <td><?= $datum($l['import_am']) ?></td>
                    <td><?= $e ? $e['buchungen'] . ' Korrekturen (+' . $fmt($e['zugang']) . ' / −' . $fmt($e['abgang']) . ')' : '' ?></td>
                    <td><?= htmlspecialchars($l['benutzername'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
