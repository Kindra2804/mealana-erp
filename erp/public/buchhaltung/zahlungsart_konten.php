<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db     = Database::getInstance();
$fehler = $_SESSION['fehler'] ?? null;
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

/**
 * Zahlungsart-Werte kommen live aus den ENUM-Spalten von auftraege + kassen_bons
 * (statt hartcodierter Liste) — falls später im Code eine neue Zahlungsart dazukommt,
 * taucht sie hier automatisch auf, ohne dass diese Seite angepasst werden muss.
 */
function enumWerte(PDO $db, string $tabelle, string $spalte): array
{
    $stmt = $db->prepare("
        SELECT COLUMN_TYPE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :s
    ");
    $stmt->execute([':t' => $tabelle, ':s' => $spalte]);
    $typ = $stmt->fetchColumn();
    preg_match_all("/'([^']+)'/", (string)$typ, $treffer);
    return $treffer[1] ?? [];
}

$alleZahlungsarten = array_unique(array_merge(
    enumWerte($db, 'auftraege', 'zahlungsart'),
    enumWerte($db, 'kassen_bons', 'zahlungsart')
));
sort($alleZahlungsarten);

// Fehlende Zeilen (neue Zahlungsart im Code, aber noch kein Mapping-Eintrag) automatisch anlegen
$vorhanden = $db->query("SELECT zahlungsart FROM zahlungsart_konten")->fetchAll(PDO::FETCH_COLUMN);
foreach (array_diff($alleZahlungsarten, $vorhanden) as $neu) {
    $db->prepare("INSERT INTO zahlungsart_konten (zahlungsart) VALUES (:z)")->execute([':z' => $neu]);
}

$rows = $db->query("
    SELECT zk.id, zk.zahlungsart, zk.konto_id, zk.hinweis
    FROM zahlungsart_konten zk
    WHERE zk.zahlungsart IN ('" . implode("','", array_map(fn($v) => addslashes($v), $alleZahlungsarten)) . "')
    ORDER BY zk.zahlungsart
")->fetchAll();

$konten = $db->query("SELECT id, kontonummer, name FROM kontenplan WHERE aktiv = 1 ORDER BY kontonummer")->fetchAll();

$pageTitle    = 'Zahlungsart-Konten';
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
    <div class="card-header">Zahlungsart → Konto</div>
    <div style="font-size:12px;color:var(--color-text-muted);padding:0 16px 10px">
        "Rechnung", "gemischt" und "kombi" haben bewusst kein einfaches Konto (siehe Hinweistext) — die brauchen beim Export Sonderbehandlung statt einer 1:1-Zuordnung.
    </div>
    <form method="post" action="<?= BASE_PATH ?>/buchhaltung/zahlungsart_konten_speichern.php">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Zahlungsart</th>
                    <th style="width:220px">Konto</th>
                    <th>Hinweis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><code style="font-size:12px"><?= htmlspecialchars($r['zahlungsart']) ?></code></td>
                    <td>
                        <select name="konto_id[<?= $r['id'] ?>]" class="erp-input" style="width:100%">
                            <option value="">— kein Konto (Sonderfall) —</option>
                            <?php foreach ($konten as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $r['konto_id'] == $k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['kontonummer'] . ' ' . $k['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="hinweis[<?= $r['id'] ?>]" class="erp-input" style="width:100%"
                               value="<?= htmlspecialchars($r['hinweis'] ?? '') ?>" maxlength="255">
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
