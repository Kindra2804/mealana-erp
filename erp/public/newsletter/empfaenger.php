<?php
// Newsletter-Empfänger je Shop (Schritt 1): Liste, Status, Einwilligungsnachweis, von Hand abmelden.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterEmpfaengerService.php';

$db     = Database::getInstance();
$svc    = new NewsletterEmpfaengerService();
$shops  = $db->query("SELECT id, name FROM shops WHERE ist_aktiv = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$shopId = (int)($_GET['shop_id'] ?? ($shops[0]['id'] ?? 0));
$status = in_array($_GET['status'] ?? '', ['aktiv', 'ausstehend', 'abgemeldet'], true) ? $_GET['status'] : null;
$suche  = trim($_GET['q'] ?? '');
$zaehler = $shopId ? $svc->zaehler($shopId) : [];
$liste   = $shopId ? $svc->liste($shopId, $status, $suche) : [];
$nachweisId = (int)($_GET['nachweis'] ?? 0);
$nachweis   = $nachweisId ? $svc->ereignisse($nachweisId) : [];

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
$statusText = ['aktiv' => ['bestätigt', 'chip-aktiv'], 'ausstehend' => ['wartet auf Bestätigung', ''], 'abgemeldet' => ['abgemeldet', 'chip-inaktiv']];
$d = fn($v) => $v ? date('d.m.Y H:i', strtotime($v)) : '–';
$ereignisText = ['importiert' => 'importiert', 'angemeldet' => 'angemeldet', 'bestaetigt' => 'bestätigt (Opt-in)', 'abgemeldet' => 'abgemeldet'];
$quelleText   = ['jtl_import' => 'Import JTL-Shop', 'shop_formular' => 'Anmeldeformular', 'erp' => 'ERP'];

$pageTitle        = 'Newsletter-Empfänger';
$activeModule     = 'kunden';
$actionBarContent = '<a href="import.php?shop_id=' . $shopId . '" class="btn btn-secondary btn-sm">⤓ Import aus JTL-Shop</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<?php if ($erfolg): ?><div class="card" style="border-left:3px solid var(--color-success);padding:10px 16px;margin-bottom:12px;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></div><?php endif; ?>
<?php if ($fehler): ?><div class="card" style="border-left:3px solid var(--color-danger);padding:10px 16px;margin-bottom:12px;color:var(--color-danger)"><?= htmlspecialchars($fehler) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:12px">
    <form method="get" class="filter-bar" style="padding:12px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <select name="shop_id" class="erp-select" onchange="this.form.submit()">
            <?php foreach ($shops as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $shopId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="erp-select" onchange="this.form.submit()">
            <option value="">Alle Status</option>
            <?php foreach ($statusText as $k => [$t]): ?>
                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $t ?> (<?= (int)($zaehler[$k] ?? 0) ?>)</option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?= htmlspecialchars($suche) ?>" class="erp-input" placeholder="E-Mail oder Name suchen" style="width:240px">
        <button class="btn btn-secondary btn-sm">Suchen</button>
        <span style="margin-left:auto;font-size:13px;color:var(--color-text-muted)">
            <strong><?= (int)($zaehler['aktiv'] ?? 0) ?></strong> bestätigt · <?= (int)($zaehler['ausstehend'] ?? 0) ?> offen · <?= (int)($zaehler['abgemeldet'] ?? 0) ?> abgemeldet
        </span>
    </form>
</div>

<?php if ($nachweis): ?>
<div class="card" style="margin-bottom:12px">
    <div class="card-header">Einwilligungsnachweis</div>
    <table class="erp-table" style="width:100%">
        <thead><tr><th>Zeitpunkt</th><th>Ereignis</th><th>IP</th><th>Quelle</th><th>Benutzer</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($nachweis as $e): ?>
            <tr><td><?= $d($e['zeitpunkt']) ?></td><td><strong><?= htmlspecialchars($ereignisText[$e['ereignis']] ?? $e['ereignis']) ?></strong></td><td><?= htmlspecialchars($e['ip'] ?? '') ?></td>
                <td><?= htmlspecialchars($quelleText[$e['quelle']] ?? (string)$e['quelle']) ?></td><td><?= htmlspecialchars($e['formularname'] ?? '') ?></td>
                <td style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars((string)$e['details']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <table class="erp-table" style="width:100%">
        <thead><tr><th>E-Mail</th><th>Name</th><th>Status</th><th>Eingetragen</th><th>Opt-in</th><th>Opt-in-IP</th><th>Quelle</th><th></th></tr></thead>
        <tbody>
        <?php if (!$liste): ?>
            <tr><td colspan="8" style="color:var(--color-text-muted)">Keine Empfänger<?= $shopId ? ' — Import über „⤓ Import aus JTL-Shop“.' : '.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($liste as $r): [$st, $cls] = $statusText[$r['status']]; ?>
            <tr>
                <td><?= htmlspecialchars($r['email']) ?><?= $r['kunden_id'] ? ' <a href="' . BASE_PATH . '/kunden/detail.php?id=' . (int)$r['kunden_id'] . '" title="Kunde im ERP">👤</a>' : '' ?></td>
                <td><?= htmlspecialchars(trim(($r['vorname'] ?? '') . ' ' . ($r['nachname'] ?? ''))) ?></td>
                <td><span class="chip <?= $cls ?>"><?= $st ?></span></td>
                <td><?= $d($r['eingetragen_am']) ?></td>
                <td><?= $d($r['optin_am']) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($r['optin_ip'] ?? '') ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($quelleText[$r['quelle']] ?? $r['quelle']) ?></td>
                <td style="white-space:nowrap;text-align:right">
                    <a href="?shop_id=<?= $shopId ?>&nachweis=<?= (int)$r['id'] ?>" class="btn btn-sm">Nachweis</a>
                    <?php if ($r['status'] !== 'abgemeldet'): ?>
                    <form method="post" action="aktion.php" style="display:inline" onsubmit="return confirm('<?= htmlspecialchars(addslashes($r['email'])) ?> abmelden?')">
                        <input type="hidden" name="aktion" value="abmelden"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="shop_id" value="<?= $shopId ?>">
                        <button class="btn btn-sm">Abmelden</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (count($liste) >= 200): ?><div style="padding:8px 16px;font-size:12px;color:var(--color-text-muted)">Es werden die neuesten 200 gezeigt — Suche oder Status-Filter grenzt ein.</div><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
