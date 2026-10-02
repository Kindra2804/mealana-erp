<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/MahnwesenService.php';

$service     = new MahnwesenService();
$e           = $service->einstellungen();
$vorschlaege = $service->findVorschlaege();
$gebuehren   = $service->findOffeneGebuehren();
$klaeren     = $service->findManuellKlaeren();
$verlauf     = $service->findVerlauf(30);

$typLabel = ['erinnerung' => 'Zahlungserinnerung', 'mahnung1' => '1. Mahnung', 'mahnung2' => '2. Mahnung'];

function eur(float $betrag): string {
    return '€ ' . number_format($betrag, 2, ',', '.');
}
function datum(?string $d): string {
    return $d ? date('d.m.Y', strtotime($d)) : '–';
}
function tageSeit(?string $d): string {
    return $d ? (string)max(0, (int)floor((time() - strtotime($d)) / 86400)) : '–';
}

$pageTitle    = 'Mahnwesen';
$activeModule = 'verkauf';
$basePath     = BASE_PATH;
$actionBarContent = $vorschlaege
    ? '<button type="button" class="btn btn-primary btn-sm mw-aktion" data-aktion="alle_freigeben">✉ Alle ' . count($vorschlaege) . ' freigeben</button>'
    : '';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<style>
.db-card { background:white; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:16px; }
.db-card-title { font-weight:700; font-size:13px; color:#1e3a5f; margin-bottom:4px; }
.mw-sub { font-size:11px; color:#64748b; margin-bottom:12px; }
.db-table { width:100%; border-collapse:collapse; font-size:12px; }
.db-table th { background:#f8fafc; color:#64748b; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; padding:6px 8px; text-align:left; border-bottom:1px solid #e2e8f0; }
.db-table td { padding:7px 8px; border-bottom:1px solid #f1f5f9; color:#1e3a5f; vertical-align:middle; }
.db-table tr:last-child td { border-bottom:none; }
.db-table .r { text-align:right; white-space:nowrap; }
.mw-leer { text-align:center; color:#94a3b8; padding:16px; }
.mw-chip { display:inline-block; font-size:10px; padding:1px 7px; border-radius:10px; background:#f1f5f9; color:#475569; white-space:nowrap; }
.mw-chip.mahnung1 { background:#fff7ed; color:#c2410c; }
.mw-chip.mahnung2 { background:#fef2f2; color:#b91c1c; }
.mw-chip.verworfen { background:#f1f5f9; color:#94a3b8; text-decoration:line-through; }
.mw-btns { display:flex; gap:6px; justify-content:flex-end; }
</style>

<div class="db-card">
    <div class="db-card-title">Zur Freigabe (<?= count($vorschlaege) ?>)</div>
    <div class="mw-sub">
        Rechnungskunden: Erinnerung geht <?= $e['erinnerung_tage'] ?> Tage nach Fälligkeit automatisch raus, die 1. Mahnung wird
        <?= $e['stufe1_tage'] ?> Tage danach vorgeschlagen, die 2. Mahnung <?= $e['stufe2_tage'] ?> Tage nach der 1.
        Mahngebühr: <?= eur($e['gebuehr1']) ?> / <?= eur($e['gebuehr2']) ?> (Einstellungen → System → Mahnwesen).
        Freigeben erzeugt das PDF und schickt es per Mail.
    </div>
    <table class="db-table">
        <thead><tr><th>Kunde</th><th>Auftrag / Rechnung</th><th>Stufe</th><th class="r">fällig seit</th><th class="r">offen</th><th class="r">Gebühr</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($vorschlaege as $v): ?>
            <tr>
                <td><?= htmlspecialchars($v['kunde_name']) ?><?= $v['mail_an'] ? '' : ' <span class="mw-chip" title="Keine E-Mail-Adresse — PDF bitte per Post schicken">✉ fehlt</span>' ?></td>
                <td>
                    <a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= (int)$v['auftrag_id'] ?>" style="color:#2563eb"><?= htmlspecialchars($v['auftrag_nr']) ?></a>
                    <div style="font-size:10px;color:#94a3b8"><?= htmlspecialchars($v['rechnung_nr'] ?? '') ?></div>
                </td>
                <td><span class="mw-chip <?= $v['typ'] ?>"><?= $typLabel[$v['typ']] ?? $v['typ'] ?></span></td>
                <td class="r"><?= tageSeit($v['faellig_am']) ?> Tage</td>
                <td class="r" style="font-weight:600"><?= eur($v['offen']) ?></td>
                <td class="r"><?= eur($v['typ'] === 'mahnung2' ? $e['gebuehr2'] : $e['gebuehr1']) ?></td>
                <td>
                    <div class="mw-btns">
                        <button type="button" class="btn btn-primary btn-sm mw-aktion" data-aktion="freigeben" data-id="<?= (int)$v['id'] ?>">Freigeben</button>
                        <button type="button" class="btn btn-secondary btn-sm mw-aktion" data-aktion="verwerfen" data-id="<?= (int)$v['id'] ?>">Verwerfen</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$vorschlaege): ?><tr><td colspan="7" class="mw-leer">Nichts zur Freigabe.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="db-card">
    <div class="db-card-title">Manuell klären (<?= count($klaeren) ?>)</div>
    <div class="mw-sub">2. Mahnung verschickt, Frist abgelaufen, noch offen — anrufen, Inkasso oder Anwalt.</div>
    <table class="db-table">
        <thead><tr><th>Kunde</th><th>Auftrag / Rechnung</th><th class="r">Frist war</th><th class="r">offen inkl. Gebühren</th></tr></thead>
        <tbody>
        <?php foreach ($klaeren as $k): ?>
            <tr>
                <td><?= htmlspecialchars($k['kunde_name']) ?></td>
                <td><a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= (int)$k['auftrag_id'] ?>" style="color:#2563eb"><?= htmlspecialchars($k['auftrag_nr']) ?></a>
                    <div style="font-size:10px;color:#94a3b8"><?= htmlspecialchars($k['rechnung_nr'] ?? '') ?></div></td>
                <td class="r"><?= datum($k['neue_frist']) ?></td>
                <td class="r" style="font-weight:600"><?= eur($k['offen'] + $service->rechnungsStand((int)$k['auftrag_id'])['gebuehren_offen']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$klaeren): ?><tr><td colspan="4" class="mw-leer">Keine.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="db-card">
    <div class="db-card-title">Offene Mahngebühren (<?= count($gebuehren) ?>)</div>
    <div class="mw-sub">Der Auftrag gilt erst als bezahlt, wenn auch die Gebühr bezahlt oder erlassen ist.</div>
    <table class="db-table">
        <thead><tr><th>Kunde</th><th>Auftrag</th><th>Mahnung</th><th class="r">versendet</th><th class="r">Gebühr</th><th class="r">Rechnung offen</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($gebuehren as $g): ?>
            <tr>
                <td><?= htmlspecialchars($g['kunde_name']) ?></td>
                <td><a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= (int)$g['auftrag_id'] ?>" style="color:#2563eb"><?= htmlspecialchars($g['auftrag_nr']) ?></a></td>
                <td><span class="mw-chip <?= $g['typ'] ?>"><?= $typLabel[$g['typ']] ?? $g['typ'] ?></span></td>
                <td class="r"><?= datum($g['gesendet_am']) ?></td>
                <td class="r" style="font-weight:600"><?= eur((float)$g['gebuehr']) ?></td>
                <td class="r"><?= eur($g['offen']) ?></td>
                <td><div class="mw-btns"><button type="button" class="btn btn-secondary btn-sm mw-aktion" data-aktion="erlassen" data-id="<?= (int)$g['id'] ?>">Gebühr erlassen</button></div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$gebuehren): ?><tr><td colspan="7" class="mw-leer">Keine.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="db-card">
    <div class="db-card-title">Verlauf</div>
    <table class="db-table">
        <thead><tr><th>Datum</th><th>Kunde</th><th>Auftrag</th><th>Art</th><th class="r">Betrag</th><th class="r">Gebühr</th><th>PDF</th></tr></thead>
        <tbody>
        <?php foreach ($verlauf as $h): ?>
            <tr>
                <td><?= datum($h['gesendet_am'] ?? $h['vorgeschlagen_am']) ?></td>
                <td><?= htmlspecialchars($h['kunde_name']) ?></td>
                <td><a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= (int)$h['auftrag_id'] ?>" style="color:#2563eb"><?= htmlspecialchars($h['auftrag_nr']) ?></a></td>
                <td><span class="mw-chip <?= $h['status'] === 'verworfen' ? 'verworfen' : $h['typ'] ?>"><?= $typLabel[$h['typ']] ?? $h['typ'] ?></span>
                    <?php if ($h['gebuehr_erlassen_am']): ?><span class="mw-chip">Gebühr erlassen</span><?php endif; ?></td>
                <td class="r"><?= $h['offen_betrag'] !== null ? eur((float)$h['offen_betrag']) : '–' ?></td>
                <td class="r"><?= (float)$h['gebuehr'] > 0 ? eur((float)$h['gebuehr']) : '–' ?></td>
                <td><?php if ($h['dateiname']): ?>
                    <a href="<?= BASE_PATH ?>/auftraege/dokument_download.php?auftrag_id=<?= (int)$h['auftrag_id'] ?>&datei=<?= urlencode($h['dateiname']) ?>" style="color:#2563eb">PDF</a>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$verlauf): ?><tr><td colspan="7" class="mw-leer">Noch nichts verschickt.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<script src="<?= BASE_PATH ?>/js/mahnwesen.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
