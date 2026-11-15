<?php
// Newsletter verfassen: Liste der Newsletter je Shop, neu anlegen, kopieren, löschen (Entwurf)
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterService.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterEmpfaengerService.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterVersandService.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterSegmentService.php';

$db     = Database::getInstance();
$svc    = new NewsletterService();
$shops  = $db->query("SELECT id, name FROM shops WHERE ist_aktiv = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$shopId = (int)($_GET['shop_id'] ?? 0);
$liste  = $svc->liste($shopId ?: null);
$empf   = new NewsletterEmpfaengerService();
$vsvc   = new NewsletterVersandService();

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
$statusText = ['entwurf' => ['Entwurf', ''], 'versand_laeuft' => ['wird verschickt', 'chip-auslauf'], 'versendet' => ['verschickt', 'chip-aktiv']];

$pageTitle        = 'Newsletter verfassen';
$activeModule     = 'kunden';
$actionBarContent = '<a href="empfaenger.php" class="btn btn-secondary btn-sm">📧 Empfänger</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<?php if ($erfolg): ?><div class="card" style="border-left:3px solid var(--color-success);padding:10px 16px;margin-bottom:12px;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></div><?php endif; ?>
<?php if ($fehler): ?><div class="card" style="border-left:3px solid var(--color-danger);padding:10px 16px;margin-bottom:12px;color:var(--color-danger)"><?= htmlspecialchars($fehler) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:12px">
    <form method="post" action="editor_aktion.php" style="padding:12px 16px;display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <input type="hidden" name="aktion" value="neu">
        <label style="font-size:13px">Shop<br>
            <select name="shop_id" class="erp-select"><?php foreach ($shops as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $shopId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?> (<?= $empf->zaehler((int)$s['id'])['aktiv'] ?> Empfänger)</option><?php endforeach; ?></select></label>
        <label style="font-size:13px">Interner Name<br><input type="text" name="titel" class="erp-input" placeholder="z.B. Advent 2026" style="width:260px" required></label>
        <button class="btn btn-primary btn-sm">➕ Neuer Newsletter</button>
    </form>
    <form method="post" action="editor_aktion.php" style="padding:0 16px 10px;font-size:12px;color:var(--color-text-muted)">
        <input type="hidden" name="aktion" value="segmente_schalten">
        <label style="cursor:pointer"><input type="checkbox" name="an" value="1" <?= NewsletterSegmentService::aktiv() ? 'checked' : '' ?> onchange="this.form.submit()">
            🎯 Zielgruppen (Segmente) anbieten — Newsletter nur an einen Teil der Empfänger, z.B. Käufer einer Kategorie</label>
    </form>
</div>

<div class="card">
    <table class="erp-table" style="width:100%">
        <thead><tr><th>Name</th><th>Shop</th><th>Betreff</th><th>Status</th><th>Verschickt</th><th>Klicks</th><th>Geändert</th><th>Testmail</th><th></th></tr></thead>
        <tbody>
        <?php if (!$liste): ?><tr><td colspan="9" style="color:var(--color-text-muted)">Noch kein Newsletter.</td></tr><?php endif; ?>
        <?php foreach ($liste as $n): [$st, $cls] = $statusText[$n['status']]; ?>
            <tr>
                <td><a href="editor.php?id=<?= (int)$n['id'] ?>" style="font-weight:600"><?= htmlspecialchars($n['titel']) ?></a></td>
                <td><?= htmlspecialchars($n['shop_name']) ?><?php if (!empty($n['segment'])): ?><br><span style="font-size:11px;color:var(--color-text-muted)" title="<?= htmlspecialchars((new NewsletterSegmentService())->beschreibung(json_decode($n['segment'], true))) ?>">🎯 Zielgruppe</span><?php endif; ?></td>
                <td><?= htmlspecialchars($n['betreff'] ?? '–') ?></td>
                <td><span class="chip <?= $cls ?>"><?= $n['status'] === 'versand_laeuft' && $n['versand_pausiert'] ? 'angehalten' : $st ?></span></td>
                <?php $f = $n['status'] !== 'entwurf' ? $vsvc->fortschritt((int)$n['id']) : null; ?>
                <td><?= $f ? $f['gesendet'] . ' / ' . $f['gesamt'] : '–' ?></td>
                <td><?= $f ? (int)$n['klicks'] : '–' ?></td>
                <td style="color:var(--color-text-muted)"><?= date('d.m.Y H:i', strtotime($n['geaendert_am'] ?? $n['erstellt_am'])) ?></td>
                <td style="color:var(--color-text-muted)"><?= $n['testmail_am'] ? date('d.m. H:i', strtotime($n['testmail_am'])) : '–' ?></td>
                <td style="text-align:right;white-space:nowrap">
                    <form method="post" action="editor_aktion.php" style="display:inline"><input type="hidden" name="aktion" value="kopieren"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-sm">Kopieren</button></form>
                    <?php if ($n['status'] === 'entwurf'): ?>
                    <form method="post" action="editor_aktion.php" style="display:inline" onsubmit="return confirm('Newsletter „<?= htmlspecialchars(addslashes($n['titel'])) ?>“ löschen?')"><input type="hidden" name="aktion" value="loeschen"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-sm">Löschen</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
