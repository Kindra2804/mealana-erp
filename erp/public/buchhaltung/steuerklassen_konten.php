<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db     = Database::getInstance();
$fehler = $_SESSION['fehler'] ?? null;
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

// Fehlende Zeilen (neue Steuerklasse angelegt, aber noch kein Mapping-Eintrag) automatisch ergänzen
$fehlende = $db->query("
    SELECT s.id FROM steuerklassen s
    LEFT JOIN steuerklassen_konten sk ON sk.steuerklasse_id = s.id
    WHERE sk.id IS NULL
")->fetchAll(PDO::FETCH_COLUMN);
foreach ($fehlende as $steuerklasseId) {
    $db->prepare("INSERT INTO steuerklassen_konten (steuerklasse_id) VALUES (:s)")->execute([':s' => $steuerklasseId]);
}

$rows = $db->query("
    SELECT sk.id, sk.steuer_konto_id, s.id AS steuerklasse_id, s.name, s.satz, s.aktiv
    FROM steuerklassen_konten sk
    JOIN steuerklassen s ON s.id = sk.steuerklasse_id
    ORDER BY s.satz DESC
")->fetchAll();

$konten = $db->query("SELECT id, kontonummer, name FROM kontenplan WHERE aktiv = 1 AND typ = 'steuer' ORDER BY kontonummer")->fetchAll();

$pageTitle    = 'Steuerklassen-Konten';
$activeModule = 'buchhaltung';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>
<?php if ($erfolg): ?>
<div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)">
    <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Steuerklasse → Umsatzsteuer-Konto</div>
    <div style="font-size:12px;color:var(--color-text-muted);padding:0 16px 10px">
        Steuerfreie Sätze (0%) brauchen kein Konto. Inaktive Steuerklassen (z.B. für Weitergabe vorbereitet) sind ausgegraut, aber trotzdem zuweisbar.
    </div>
    <form method="post" action="<?= BASE_PATH ?>/buchhaltung/steuerklassen_konten_speichern.php">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Steuerklasse</th>
                    <th style="width:80px;text-align:center">Satz</th>
                    <th style="width:70px;text-align:center">Aktiv</th>
                    <th style="width:220px">USt-Konto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr style="<?= $r['aktiv'] ? '' : 'opacity:.5' ?>">
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td style="text-align:center"><?= number_format((float)$r['satz'], 2, ',', '.') ?>%</td>
                    <td style="text-align:center">
                        <?= $r['aktiv'] ? '<span style="color:#16a34a">✓</span>' : '<span style="color:#dc2626">✗</span>' ?>
                    </td>
                    <td>
                        <select name="konto_id[<?= $r['id'] ?>]" class="erp-input" style="width:100%">
                            <option value="">— kein Konto (z.B. steuerfrei) —</option>
                            <?php foreach ($konten as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $r['steuer_konto_id'] == $k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['kontonummer'] . ' ' . $k['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="padding:12px 16px">
            <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
