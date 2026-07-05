<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/rollen/RollenService.php';

$eigenerRang = (int)($_SESSION['benutzer']['rolle_rang'] ?? 0);
$ansicht     = (new RollenService())->getMatrixAnsicht($eigenerRang);
$rollen      = $ansicht['rollen'];
$berechtigungen = $ansicht['berechtigungen'];
$lookup      = $ansicht['lookup'];

const GESPERRTE_BERECHTIGUNG = 'lizenz.verwalten';

$pageTitle        = 'Rollen & Berechtigungen';
$activeModule     = 'rollen';
$actionBarContent = '';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card">
    <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:16px">
        Du darfst nur Rollen mit niedrigerem Rang als deinem eigenen bearbeiten (gesperrte Spalten sind ausgegraut).
        "Lizenz verwalten" ist grundsätzlich fix nur für Superadmin und über diese Matrix nicht änderbar.
    </p>

    <?php if ($eigenerRang === 0): ?>
        <div class="card" style="border-left:3px solid var(--color-danger);padding:10px 16px;color:var(--color-danger);margin-bottom:16px">
            Deine Sitzung kennt deinen Rollen-Rang noch nicht — bitte einmal ab- und wieder anmelden.
        </div>
    <?php endif; ?>

    <div style="overflow-x:auto">
    <table class="erp-table" style="white-space:nowrap">
        <thead>
            <tr>
                <th style="position:sticky;left:0;background:var(--color-bg,#fff)">Berechtigung</th>
                <?php foreach ($rollen as $r): ?>
                    <th style="text-align:center;<?= $r['bearbeitbar'] ? '' : 'opacity:.45' ?>">
                        <?= htmlspecialchars(ucfirst($r['name'])) ?>
                        <div style="font-weight:400;font-size:10px;color:var(--color-text-muted)">Rang <?= $r['rang'] ?><?= $r['bearbeitbar'] ? '' : ' 🔒' ?></div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php $letztesModul = null; ?>
        <?php foreach ($berechtigungen as $b): ?>
            <?php
                $modul = explode('.', $b['name'])[0];
                if ($modul !== $letztesModul):
                    $letztesModul = $modul;
            ?>
                <tr style="background:var(--color-bg-subtle)">
                    <td colspan="<?= count($rollen) + 1 ?>" style="font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.05em;position:sticky;left:0">
                        <?= htmlspecialchars($modul) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php $gesperrt = $b['name'] === GESPERRTE_BERECHTIGUNG; ?>
            <tr>
                <td style="position:sticky;left:0;background:var(--color-bg,#fff)">
                    <?= htmlspecialchars($b['name']) ?>
                    <?php if ($gesperrt): ?><span style="color:#e67e22;font-size:11px">🔒 fix</span><?php endif; ?>
                </td>
                <?php foreach ($rollen as $r): ?>
                    <?php
                        $gesetzt     = !empty($lookup[$r['id']][$b['id']]);
                        $editierbar  = $r['bearbeitbar'] && !$gesperrt;
                    ?>
                    <td style="text-align:center">
                        <input type="checkbox"
                               data-rolle="<?= $r['id'] ?>"
                               data-berechtigung="<?= $b['id'] ?>"
                               <?= $gesetzt ? 'checked' : '' ?>
                               <?= $editierbar ? '' : 'disabled' ?>
                               onchange="berechtigungToggle(this)">
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div id="banner" style="display:none;position:fixed;top:16px;right:16px;z-index:2000;padding:10px 18px;border-radius:6px;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.2)"></div>

<script src="<?= BASE_PATH ?>/js/rollen_matrix.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
