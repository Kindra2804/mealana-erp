<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

$service = new KundenService();

$suche  = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$kunden = $service->getAll($suche, $status);

$pageTitle       = 'Kunden';
$activeModule    = 'kunden';
$actionBarContent = <<<HTML
    <a href="/mealana/kunden/neu.php" class="btn btn-primary btn-sm">+ Neuer Kunde</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card">

    <div class="filter-bar" style="margin-bottom:16px">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($suche) ?>"
                placeholder="Name, E-Mail oder Kundennummer …"
                class="erp-input"
                style="width:280px"
            >
            <select name="status" class="erp-select">
                <option value="">Alle Status</option>
                <option value="aktiv"    <?= $status === 'aktiv'    ? 'selected' : '' ?>>Aktiv</option>
                <option value="gesperrt" <?= $status === 'gesperrt' ? 'selected' : '' ?>>Gesperrt</option>
                <option value="geloescht" <?= $status === 'geloescht' ? 'selected' : '' ?>>Gelöscht</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Suchen</button>
            <?php if ($suche !== '' || $status !== ''): ?>
                <a href="liste.php" class="btn btn-secondary btn-sm">✕ Filter aufheben</a>
            <?php endif; ?>
        </form>
        <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
            <?= count($kunden) ?> Kunden gefunden
        </div>
    </div>

    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:110px">KD-NR.</th>
                <th>NAME / FIRMA</th>
                <th style="width:200px">E-MAIL</th>
                <th style="width:120px">KUNDENGRUPPE</th>
                <th style="width:100px">HERKUNFT</th>
                <th style="width:100px">STATUS</th>
                <th style="width:70px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($kunden)): ?>
            <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:var(--color-text-muted)">
                    Keine Kunden gefunden
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($kunden as $k): ?>
            <?php
            // Anzeigename zusammenbauen
            if ($k['ist_firma'] && $k['firmenname']) {
                $name = htmlspecialchars($k['firmenname']);
                if ($k['nachname'] || $k['vorname']) {
                    $name .= ' <span style="color:var(--color-text-muted);font-size:12px">('
                          . htmlspecialchars(trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')))
                          . ')</span>';
                }
            } else {
                $name = htmlspecialchars(trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')));
                if (!$name) $name = '<em style="color:var(--color-text-muted)">–</em>';
            }

            $statusChip = match($k['status']) {
                'aktiv'    => '<span class="chip chip-aktiv">Aktiv</span>',
                'gesperrt' => '<span class="chip" style="background:#fff3cd;color:#856404">Gesperrt</span>',
                'geloescht'=> '<span class="chip chip-inaktiv">Gelöscht</span>',
                default    => '<span class="chip">' . htmlspecialchars($k['status']) . '</span>',
            };

            $herkunftLabel = match($k['kundenherkunft']) {
                'shop'       => 'Shop',
                'messe'      => 'Messe',
                'empfehlung' => 'Empfehlung',
                'walkin'     => 'Walk-in',
                'kasse'      => 'Kasse',
                'erp'        => 'ERP',
                default      => htmlspecialchars($k['kundenherkunft']),
            };
            ?>
            <tr>
                <td>
                    <a href="detail.php?id=<?= $k['id'] ?>" style="font-family:monospace;font-size:12px">
                        <?= htmlspecialchars($k['kundennummer']) ?>
                    </a>
                </td>
                <td>
                    <a href="detail.php?id=<?= $k['id'] ?>"><?= $name ?></a>
                    <?php if ($k['ist_firma']): ?>
                        <span style="font-size:10px;color:var(--color-text-muted);margin-left:4px">B2B</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--color-text-muted);font-size:12px">
                    <?= htmlspecialchars($k['email'] ?? '–') ?>
                </td>
                <td style="font-size:12px">
                    <?= htmlspecialchars($k['kundengruppe'] ?? '–') ?>
                </td>
                <td style="font-size:12px;color:var(--color-text-muted)">
                    <?= $herkunftLabel ?>
                </td>
                <td><?= $statusChip ?></td>
                <td style="text-align:right">
                    <a href="bearbeiten.php?id=<?= $k['id'] ?>" class="btn btn-secondary btn-sm">✏</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
