<?php
/**
 * Artikel aus dem NAHTLOS des Partners übernehmen (Partner → Artikel → „Aus NAHTLOS-Datei übernehmen“).
 *   GET  ?id=<partner>                        Datei wählen
 *   POST id + datei (Upload)                  Vorschau neu / geändert / unverändert
 *   POST id + token + aktion=uebernehmen      gewählte Zeilen übernehmen
 * Logik: src/modules/partner/MietfachImport.php · Gegenstück: artikel/mietfach_export.php
 * Modul: Partner / Mietfächer (Lizenz::SEITEN 'partner' => '*').
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerService.php';
require_once __DIR__ . '/../../src/modules/partner/MietfachImport.php';

$id      = (int)($_REQUEST['id'] ?? 0);
$partner = (new PartnerService())->getById($id);
if (!$partner) { header('Location: ' . BASE_PATH . '/partner/liste.php'); exit; }

$lagerSvc = new PartnerLagerService();
$lager    = $lagerSvc->getLager($id);
$plaetze  = $lager ? $lagerSvc->getPlaetze($id) : [];
$imp      = new MietfachImport();
$fehler   = null;
$zeilen   = null;
$daten    = null;
$ergebnis = null;
$token    = '';

/** Pfad zur hochgeladenen Datei aus dem Token (nur Hex, nur eigene Sitzung) */
function mietfachDatei(string $token, int $partnerId): ?array
{
    $info = $_SESSION['mietfach_import'][$token] ?? null;
    if (!preg_match('/^[a-f0-9]{32}$/', $token) || !$info || (int)$info['partner_id'] !== $partnerId) return null;
    $pfad = MietfachImport::ablage() . '/' . $token . '.tar';
    return is_file($pfad) ? ['pfad' => $pfad, 'name' => $info['name']] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lager) {
    try {
        if (($_POST['aktion'] ?? '') === 'uebernehmen') {
            $token = (string)($_POST['token'] ?? '');
            $d = mietfachDatei($token, $id);
            if (!$d) throw new RuntimeException('Die hochgeladene Datei ist nicht mehr da – bitte noch einmal hochladen.');
            $fach = (int)($_POST['fach_id'] ?? 0);
            if ($fach && !in_array($fach, array_map('intval', array_column($plaetze, 'id')), true)) $fach = 0;
            $ergebnis = $imp->uebernehmen($id, $d['pfad'], (array)($_POST['auswahl'] ?? []), (array)($_POST['preis'] ?? []), $fach ?: null, $d['name']);
            @unlink($d['pfad']);
            unset($_SESSION['mietfach_import'][$token]);
        } else {
            $f = $_FILES['datei'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || (empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)
                    ? 'Die Datei ist zu groß für die Server-Einstellung (upload_max_filesize / post_max_size).'
                    : 'Bitte eine Datei wählen.');
            }
            $token = bin2hex(random_bytes(16));
            $pfad  = MietfachImport::ablage() . '/' . $token . '.tar';
            move_uploaded_file($f['tmp_name'], $pfad);
            try {
                $daten = $imp->lesen($pfad);
            } catch (Throwable $e) {
                @unlink($pfad);
                throw $e;
            }
            $_SESSION['mietfach_import'][$token] = ['partner_id' => $id, 'name' => basename($f['name'])];
            $zeilen = $imp->vorschau($id, $daten);
        }
    } catch (Throwable $e) {
        $fehler = $e->getMessage();
    }
}

$pageTitle        = $partner['name'] . ' – Artikel übernehmen';
$activeModule     = 'partner';
$actionBarContent = '<a href="' . BASE_PATH . '/partner/detail.php?id=' . $id . '&tab=artikel" class="btn btn-secondary btn-sm">← ' . htmlspecialchars($partner['name']) . '</a>';
require_once __DIR__ . '/../includes/shell_top.php';

$statusText = ['neu' => 'neu', 'geaendert' => 'geändert', 'gleich' => 'unverändert', 'ohne_preis' => 'ohne Preis'];
$statusFarbe = ['neu' => '#16a34a', 'geaendert' => '#2563eb', 'gleich' => 'var(--color-text-muted)', 'ohne_preis' => '#b45309'];
$euro = fn($v) => $v === null ? '–' : '€ ' . number_format((float)$v, 2, ',', '.');
?>
<div style="padding:var(--space-md);max-width:1200px">

<h2 style="margin:0 0 4px">Artikel aus NAHTLOS-Datei übernehmen</h2>
<div style="font-size:13px;color:var(--color-text-muted);margin-bottom:var(--space-md)">
    Für <strong><?= htmlspecialchars($partner['name']) ?></strong>. Der Partner erstellt die Datei in seinem NAHTLOS:
    <em>Artikelliste → Artikel anhaken → Aktion „📦 Für Mietfach exportieren“</em>.
    Es werden normale Partnerartikel (<code><?= PartnerLagerService::nummernPraefix($id) ?>…</code>) angelegt oder aktualisiert –
    <strong>ohne Bestand</strong>: die Ware kommt wie gewohnt über „Ware übernehmen“.
</div>

<?php if (!$lager): ?>
    <div class="card" style="padding:var(--space-md)">Dieser Partner hat noch kein eigenes Lager –
        bitte zuerst auf der <a href="<?= BASE_PATH ?>/partner/detail.php?id=<?= $id ?>">Partnerseite</a> „Partner-Lager anlegen“.</div>

<?php elseif ($ergebnis): ?>
    <div class="card" style="padding:var(--space-md);border-left:4px solid #16a34a">
        <strong>Übernommen:</strong> <?= $ergebnis['neu'] ?> neu angelegt · <?= $ergebnis['geaendert'] ?> aktualisiert · <?= $ergebnis['bilder'] ?> Bilder.
        <?php if ($ergebnis['fehler']): ?>
            <div style="margin-top:8px;color:#b91c1c"><?= count($ergebnis['fehler']) ?> nicht übernommen:</div>
            <ul style="margin:4px 0 0;font-size:13px"><?php foreach ($ergebnis['fehler'] as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <div style="margin-top:12px">
            <a class="btn btn-primary btn-sm" href="<?= BASE_PATH ?>/partner/detail.php?id=<?= $id ?>&tab=artikel">Zu den Artikeln</a>
            <a class="btn btn-secondary btn-sm" href="<?= BASE_PATH ?>/partner/beleg_neu.php?id=<?= $id ?>&typ=uebernahme">📥 Ware übernehmen</a>
        </div>
    </div>

<?php elseif ($zeilen === null): ?>
    <?php if ($fehler): ?><div class="card" style="padding:var(--space-md);border-left:4px solid #dc2626;margin-bottom:12px"><?= htmlspecialchars($fehler) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="card" style="padding:var(--space-md);display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="file" name="datei" accept=".tar" required>
        <button class="btn btn-primary">Vorschau anzeigen</button>
        <span style="font-size:12px;color:var(--color-text-muted)">Noch wird nichts geändert – erst nach der Vorschau mit „Übernehmen“.</span>
    </form>

<?php else:
    $zaehl = array_count_values(array_column($zeilen, 'status'));
    $abs   = $daten['absender'] ?? [];
?>
    <div class="card" style="padding:var(--space-md);margin-bottom:12px;font-size:13px">
        Datei von <strong><?= htmlspecialchars($abs['firma'] ?? '?') ?></strong>
        <?= !empty($abs['uid']) ? '· UID ' . htmlspecialchars($abs['uid']) : (!empty($abs['steuernummer']) ? '· StNr ' . htmlspecialchars($abs['steuernummer']) : '') ?>
        · erstellt <?= htmlspecialchars(date('d.m.Y H:i', strtotime($daten['erstellt_am'] ?? 'now'))) ?>
        · <?= count($zeilen) ?> Artikel:
        <?php foreach ($statusText as $k => $t): if (!empty($zaehl[$k])): ?>
            <span style="color:<?= $statusFarbe[$k] ?>;font-weight:600;margin-left:6px"><?= $zaehl[$k] ?> <?= $t ?></span>
        <?php endif; endforeach; ?>
        <?php if (mb_strtolower(trim($abs['firma'] ?? '')) !== mb_strtolower(trim($partner['name']))): ?>
            <div style="display:flex;gap:8px;align-items:center;margin-top:8px"><span class="mf-ausruf">!</span>
                <span>Der Absender heißt anders als dieser Partner („<?= htmlspecialchars($partner['name']) ?>“) – bitte prüfen, ob die Datei zum richtigen Partner gehört.</span></div>
        <?php endif; ?>
    </div>

    <form method="post">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="aktion" value="uebernehmen">
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px;flex-wrap:wrap">
            <label style="font-size:13px">Fach für neue Artikel
                <select name="fach_id" class="erp-select" style="min-width:160px">
                    <option value="">– keines –</option>
                    <?php foreach ($plaetze as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['bezeichnung']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" <?= empty($zaehl['neu']) && empty($zaehl['geaendert']) ? 'disabled' : '' ?>>Übernehmen</button>
            <?php if (empty($zaehl['neu']) && empty($zaehl['geaendert'])): ?><span style="font-size:13px">Alles schon auf dem neuesten Stand.</span><?php endif; ?>
        </div>

        <table class="erp-table">
            <thead><tr><th style="width:30px"><input type="checkbox" id="mf-alle" checked title="alle"></th><th style="width:100px">STATUS</th>
                <th style="width:140px">NR. PARTNER</th><th style="width:140px">BEI UNS</th><th>BEZEICHNUNG</th><th style="width:120px">EAN</th>
                <th style="width:100px;text-align:right">PREIS</th><th style="width:55px">MWST</th></tr></thead>
            <tbody>
            <?php foreach ($zeilen as $z): $waehlbar = in_array($z['status'], ['neu', 'geaendert'], true); ?>
                <tr <?= $waehlbar ? '' : 'style="opacity:.6"' ?>>
                    <td><?php if ($waehlbar): ?><input type="checkbox" class="mf-wahl" name="auswahl[]" value="<?= htmlspecialchars($z['quell_nummer']) ?>" checked><?php endif; ?></td>
                    <td style="color:<?= $statusFarbe[$z['status']] ?>;font-weight:600"><?= $statusText[$z['status']] ?>
                        <?php if ($z['aenderungen']): ?><div style="font-size:11px;font-weight:400"><?= htmlspecialchars(implode(', ', $z['aenderungen'])) ?></div><?php endif; ?></td>
                    <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($z['quell_nummer']) ?></td>
                    <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($z['artikelnummer'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($z['name']) ?>
                        <?php foreach ($z['hinweise'] as $h): ?><div style="font-size:11px;color:var(--color-text-muted)"><span class="mf-ausruf">!</span> <?= htmlspecialchars($h) ?></div><?php endforeach; ?></td>
                    <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($z['ean'] ?? '') ?></td>
                    <td style="text-align:right"><?= $euro($z['preis']) ?>
                        <?php if ($z['eigener_preis'] && $z['preis'] !== null && $z['unser_preis'] !== $z['preis']): ?>
                            <label style="display:block;font-size:11px;white-space:nowrap"><input type="checkbox" name="preis[]" value="<?= htmlspecialchars($z['quell_nummer']) ?>"> statt <?= $euro($z['unser_preis']) ?></label>
                        <?php elseif ($z['unser_preis'] !== null && $z['unser_preis'] !== $z['preis']): ?>
                            <div style="font-size:11px;color:var(--color-text-muted)">bisher <?= $euro($z['unser_preis']) ?></div>
                        <?php endif; ?></td>
                    <td><?= rtrim(rtrim(number_format($z['steuersatz'], 1, ',', ''), '0'), ',') ?> %</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form>
    <script>
    document.getElementById('mf-alle').addEventListener('change', function () {
        document.querySelectorAll('.mf-wahl').forEach(c => { c.checked = this.checked; });
    });
    </script>
<?php endif; ?>

</div>
<style>
    .mf-ausruf { display:inline-block;width:16px;height:16px;line-height:16px;border-radius:50%;background:#2563eb;color:#fff;font-weight:700;font-size:11px;text-align:center;font-style:normal }
</style>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
