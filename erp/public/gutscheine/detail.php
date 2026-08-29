<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinRepository.php';

$id = (int)($_GET['id'] ?? 0);
$repo = new GutscheinRepository();
$gutschein = $repo->findById($id);

if (!$gutschein) {
    header('Location: liste.php');
    exit;
}

$transaktionen = $repo->findTransaktionenFuerGutschein($id);
$kette = $repo->findKette($id);

$fehler = $_SESSION['fehler'] ?? null;
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$pageTitle    = 'Gutschein ' . $gutschein['code'];
$activeModule = 'gutscheine';
$actionBarContent = <<<HTML
    <a href="pdf_download.php?id={$id}" class="btn btn-secondary btn-sm" target="_blank">📄 PDF herunterladen</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

$statusLabels = [
    'aktiv'      => ['label' => 'Aktiv',       'class' => 'chip-aktiv'],
    'teilweise'  => ['label' => 'Teilweise eingelöst', 'class' => 'chip-auslauf'],
    'eingeloest' => ['label' => 'Eingelöst',   'class' => 'chip-inaktiv'],
    'abgelaufen' => ['label' => 'Abgelaufen',  'class' => 'sc-fehlbest'],
    'storniert'  => ['label' => 'Storniert',   'class' => 'chip-inaktiv'],
];
$sl = $statusLabels[$gutschein['status']] ?? ['label' => $gutschein['status'], 'class' => ''];
$kannEinloesen = in_array($gutschein['status'], ['aktiv', 'teilweise'], true);
?>

<?php if ($erfolg): ?>
<div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)">
    <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>
<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>

<?php if (count($kette) > 1): ?>
<div class="card" style="margin-bottom:16px;background:#fffbea;border:1px solid #f0d878">
    <div style="font-weight:600;margin-bottom:8px">🔗 Gutschein-Verlauf (Teileinlösung — für Support-Rückfragen "mein Code funktioniert nicht")</div>
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;font-size:13px">
        <?php foreach ($kette as $i => $k): ?>
            <?php if ($i > 0): ?><span style="color:#999">→</span><?php endif; ?>
            <?php if ((int)$k['id'] === $id): ?>
                <span class="chip chip-aktiv" style="font-family:monospace"><?= htmlspecialchars($k['code']) ?> (dieser)</span>
            <?php else: ?>
                <a href="detail.php?id=<?= $k['id'] ?>" class="chip" style="font-family:monospace;text-decoration:none">
                    <?= htmlspecialchars($k['code']) ?>
                </a>
            <?php endif; ?>
            <span style="color:#777">(<?= number_format((float)$k['betrag'], 2, ',', '.') ?> €<?= $k['status'] === 'teilweise' ? ', ersetzt' : '' ?>)</span>
        <?php endforeach; ?>
    </div>
    <?php
        $letzter = end($kette);
        if ((int)$letzter['id'] !== $id):
    ?>
    <div style="margin-top:8px;font-size:12px;color:#8a6d00">
        ⚠ Dies ist NICHT der aktuell gültige Code — der Rest wurde auf
        <a href="detail.php?id=<?= $letzter['id'] ?>"><strong><?= htmlspecialchars($letzter['code']) ?></strong></a> übertragen.
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
    <div class="card">
        <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Code</div>
        <div style="font-family:monospace;font-size:18px;font-weight:700"><?= htmlspecialchars($gutschein['code']) ?></div>

        <div style="margin-top:14px;display:flex;gap:24px">
            <div>
                <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase">Betrag</div>
                <div style="font-size:16px"><?= number_format((float)$gutschein['betrag'], 2, ',', '.') ?> €</div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase">Restguthaben</div>
                <div style="font-size:16px;font-weight:700"><?= number_format((float)$gutschein['restguthaben'], 2, ',', '.') ?> €</div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase">Status</div>
                <span class="chip <?= $sl['class'] ?>"><?= $sl['label'] ?></span>
            </div>
        </div>

        <div style="margin-top:14px;font-size:13px;color:var(--color-text-muted)">
            Gültig bis: <?= $gutschein['gueltig_bis'] ? date('d.m.Y', strtotime($gutschein['gueltig_bis'])) : '—' ?><br>
            Erstellt: <?= date('d.m.Y H:i', strtotime($gutschein['erstellt_am'])) ?> (<?= htmlspecialchars($gutschein['kanal_erstellt']) ?>)<br>
            <?php if ($gutschein['empfaenger_name']): ?>Empfänger: <?= htmlspecialchars($gutschein['empfaenger_name']) ?><br><?php endif; ?>
            <?php if ($gutschein['empfaenger_email']): ?>E-Mail: <?= htmlspecialchars($gutschein['empfaenger_email']) ?><br><?php endif; ?>
            Versand: <?= $gutschein['versendet_am'] ? 'verschickt am ' . date('d.m.Y H:i', strtotime($gutschein['versendet_am'])) : 'noch nicht verschickt' ?>
        </div>

        <?php if (!$gutschein['versendet_am'] && $gutschein['empfaenger_email']): ?>
        <form method="post" action="versenden.php" style="margin-top:12px">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn btn-secondary btn-sm">📧 Jetzt per Mail versenden</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($kannEinloesen): ?>
    <div class="card">
        <div style="font-weight:600;margin-bottom:10px">Einlösen (z.B. Telefon-/Laden-Bestellung)</div>
        <form method="post" action="einloesen.php">
            <input type="hidden" name="id" value="<?= $id ?>">
            <label style="display:block;font-size:12px;margin-bottom:4px">Betrag (max. <?= number_format((float)$gutschein['restguthaben'], 2, ',', '.') ?> €)</label>
            <input type="number" name="betrag" class="erp-input" step="0.01" min="0.01"
                   max="<?= htmlspecialchars((string)$gutschein['restguthaben']) ?>" required style="width:140px">
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:6px">Einlösen</button>
        </form>
        <div style="font-size:11px;color:var(--color-text-muted);margin-top:8px">
            Bei Teileinlösung wird automatisch ein neuer Code für den Restbetrag erzeugt.
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">Transaktionen</div>
    <table class="erp-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Betrag</th>
                <th>Kanal</th>
                <th>Notiz</th>
                <th>Auftrag</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transaktionen as $t): ?>
            <tr>
                <td><?= date('d.m.Y H:i', strtotime($t['erstellt_am'])) ?></td>
                <td style="text-align:right;color:<?= $t['betrag'] < 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>">
                    <?= number_format((float)$t['betrag'], 2, ',', '.') ?> €
                </td>
                <td><?= htmlspecialchars($t['kanal']) ?></td>
                <td><?= htmlspecialchars($t['notiz'] ?? '') ?></td>
                <td><?= $t['auftrag_nr'] ? htmlspecialchars($t['auftrag_nr']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
