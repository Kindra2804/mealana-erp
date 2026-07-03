<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/AktionenService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$service  = new AktionenService();
$artikelService = new ArtikelService();
$aktionen = $service->findAll();

$pageTitle       = 'Preise / Aktionen';
$activeModule    = 'artikel';
$kategorienBaum = $artikelService->getKategorienBaum();
$actionBarContent = '<a href="' . BASE_PATH . '/aktionen/bearbeiten.php" class="btn btn-primary btn-sm">+ Neue Aktion</a>';

require_once __DIR__ . '/../includes/shell_top.php';

$statusLabel = [
    'entwurf'    => ['text' => 'Entwurf',    'farbe' => '#888',    'bg' => '#f5f5f5'],
    'geplant'    => ['text' => 'Geplant',    'farbe' => '#0078d4', 'bg' => '#e8f3fc'],
    'aktiv'      => ['text' => 'Aktiv',      'farbe' => '#107c10', 'bg' => '#e8f5e8'],
    'abgelaufen' => ['text' => 'Abgelaufen', 'farbe' => '#999',    'bg' => '#f0f0f0'],
];
?>

<div class="card">
    <?php if (empty($aktionen)): ?>
        <p style="color:var(--color-text-muted);padding:var(--space-md)">
            Noch keine Aktionen angelegt.
            <a href="<?= BASE_PATH ?>/aktionen/bearbeiten.php" class="btn btn-primary btn-sm" style="margin-left:8px">Erste Aktion anlegen</a>
        </p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>KATEGORIEN</th>
                    <th>ZEITRAUM</th>
                    <th>STATUS</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aktionen as $a):
                    $sl  = $statusLabel[$a['status']] ?? $statusLabel['entwurf'];
                    $von = '';
                    $bis = '';
                    if (!empty($a['kategorien'])) {
                        $alleVon = array_column($a['kategorien'], 'gueltig_ab');
                        $alleBis = array_column($a['kategorien'], 'gueltig_bis');
                        $von = min($alleVon);
                        $bis = max($alleBis);
                    }
                ?>
                    <tr>
                        <td>
                            <a href="<?= BASE_PATH ?>/aktionen/bearbeiten.php?id=<?= $a['id'] ?>"
                                style="font-weight:600;color:var(--color-nav);text-decoration:none">
                                <?= htmlspecialchars($a['name']) ?>
                            </a>
                            <?php if ($a['beschreibung']): ?>
                                <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px">
                                    <?= htmlspecialchars(mb_substr($a['beschreibung'], 0, 60)) ?><?= mb_strlen($a['beschreibung']) > 60 ? '…' : '' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:var(--color-text-muted)">
                            <?php if (empty($a['kategorien'])): ?>
                                <span style="color:#ccc">—</span>
                            <?php else: ?>
                                <?= htmlspecialchars(implode(', ', array_column($a['kategorien'], 'kat_name'))) ?>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;white-space:nowrap">
                            <?php if ($von): ?>
                                <?= date('d.m.Y', strtotime($von)) ?> – <?= date('d.m.Y', strtotime($bis)) ?>
                            <?php else: ?>
                                <span style="color:#ccc">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;
                                 color:<?= $sl['farbe'] ?>;background:<?= $sl['bg'] ?>">
                                <?= $sl['text'] ?>
                            </span>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <a href="<?= BASE_PATH ?>/aktionen/bearbeiten.php?id=<?= $a['id'] ?>"
                                class="btn btn-secondary btn-xs">Bearbeiten</a>
                            <button onclick="aktionLoeschen(<?= $a['id'] ?>, <?= htmlspecialchars(json_encode($a['name'])) ?>)"
                                class="btn btn-danger btn-xs" style="margin-left:4px">Löschen</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Löschen-Bestätigung -->
<div id="del-modal" class="modal-backdrop" style="display:none">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span>Aktion löschen</span>
            <button onclick="document.getElementById('del-modal').style.display='none'" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <p id="del-info"></p>
            <p style="font-size:12px;color:var(--color-text-muted);margin-top:8px">
                Alle Kategorie-Zuweisungen und eingetragenen Aktionspreise werden ebenfalls gelöscht.
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="document.getElementById('del-modal').style.display='none'" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button id="del-btn" class="btn btn-danger btn-sm">Löschen</button>
        </div>
    </div>
</div>

<script src="<?= BASE_PATH ?>/js/aktionen_liste.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
